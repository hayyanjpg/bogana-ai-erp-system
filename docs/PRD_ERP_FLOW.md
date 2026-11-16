# PRD — Alur Sistem ERP Internal F&B B2B

Dokumen ini menjelaskan alur bisnis end-to-end `bogana-ai-erp-system`. Dokumen ini
adalah sumber kebenaran untuk **logika bisnis**; struktur data mengacu ke
`database/schema.sql` dan kontrak JSON mengacu ke `docs/API_CONTRACT.md`.

---

## 1. Latar belakang

Bogana adalah usaha F&B yang memproduksi menu olahan secara internal dan
menjualnya ke pelanggan B2B (warung, katering, restoran mitra). Order masuk
melalui WhatsApp/email/telepon dalam bentuk **teks bebas**, misalnya:

> "gan, besok pagi 40 nasi bogana sama 15 ayam kecap, kirim ke Jl. Melati 12"

Masalah yang diselesaikan sistem:

1. Order teks bebas harus diubah menjadi angka (menu + kuantitas) secara konsisten.
2. Stok bahan baku harus dicek **sebelum** order dikonfirmasi, bukan setelah.
3. Dashboard operasional PHP butuh data cepat tanpa menembak database berulang kali.
4. Perencanaan produksi butuh prakiraan permintaan (demand forecast) yang tersimpan.

## 2. Aktor

| Aktor | Peran | Media |
| --- | --- | --- |
| Admin Operasional | Memasukkan order teks, mengonfirmasi alokasi | Dashboard PHP |
| Sales B2B | Membaca status order dan forecast | Dashboard PHP |
| QC Dapur | Mengisi hasil quality control per item | Dashboard PHP |
| AI Service | NLP parser, alokasi stok, forecasting | FastAPI (dipanggil PHP) |

## 3. Alur utama (happy path)

```
[1] Order teks masuk
        |
        v
[2] PHP POST /v1/parse-order  --->  FastAPI NLP parser
        |                                   |
        |                                   +--> parser mengembalikan
        |                                        items[{menu_sku, qty, line_text}]
        v
[3] PHP menyimpan b2b_orders (status=received) + order_items
        |
        v
[4] FastAPI cek stok: baca ingredients di PostgreSQL
        |
        +--> stok cukup  --> PHP set status=confirmed
        |
        +--> stok kurang --> status=short, tampilkan daftar kekurangan
        |
        v
[5] PHP GET /v1/stocks (dilayani dari cache Redis bila masih fresh)
        |
        v
[6] QC dapur mengisi qc_logs per order_item
        |
        v
[7] Order dipenuhi -> status=fulfilled, stok dipotong dalam 1 transaksi
        |
        v
[8] FastAPI menghitung demand_forecasts untuk menu terkait
        |
        v
[9] Dashboard PHP menampilkan ringkasan (GET /v1/dashboard/summary)
```

## 4. Detail langkah

### 4.1 Order teks -> NLP parser -> simpan order

- `POST /v1/parse-order`: input `raw_text` (string), `channel`, `customer_name`, `order_date` (opsional). Parser mengembalikan `items` dengan `menu_sku` yang **wajib** cocok dengan `menus.sku` di `database/schema.sql`.
- Aturan parsing: kuantitas tanpa satuan = porsi; kuantitas tidak ditemukan/ambigu/menu tidak dikenali → warning (`QTY_MISSING`/`QTY_AMBIGUOUS`/`MENU_NOT_FOUND`), tidak boleh ditebak; duplikasi menu digabung.
- Parser **tidak** menyentuh stok/DB order. Admin menyimpan hasil parse via `POST /v1/orders` (payload + `items` valid) → order tersimpan berstatus `parsed`.

### 4.2 Cek stok, konfirmasi status, QC, dan restock

- `POST /v1/orders/{id}/check-stock`: kebutuhan dari `menu_recipes` vs `ingredients.stock_on_hand` → `sufficient`, `requirements[]`, `insufficient[]` (laporan, tanpa mutasi).
- `POST /v1/orders/{id}/confirm`: hitung kebutuhan dan **persist** status `confirmed` (cukup) atau `short` (kurang). Order `fulfilled/cancelled` ditolak.
- Jika `short`: tambah stok via `POST /v1/stocks/{sku}/restock` (stok tidak boleh negatif), lalu `confirm` ulang hingga `confirmed`.
- QC: `POST /v1/orders/{id}/items/{item_id}/qc` mencatat `qc_logs` `pass` per bahan resep item. Seluruh item wajib punya QC pass sebelum alokasi.
- `POST /v1/orders/{id}/allocate`: transaksi atomik, syarat `confirmed` + QC pass semua item + stok cukup → potong `ingredients.stock_on_hand`, set `fulfilled`. Stok **tidak boleh** negatif.

### 4.3 Cache Redis

| Key | Isi | TTL | Invalidasi |
| --- | --- | --- | --- |
| `erp:stock:{ingredient_id}` | snapshot stok bahan | 60 s | saat stok berubah (restock/pemenuhan) |
| `erp:stock:all` | daftar stok ringkas | 60 s | saat stok berubah (restock/pemenuhan) |
| `erp:dashboard:summary` | agregat dashboard | 30 s | setiap order berubah status / tersimpan / dikonfirmasi |
| `erp:parse:{sha1(raw_text)}` | hasil parse (idempotensi) | 15 menit | jangan dihapus sebelum TTL |

- Cache bersifat **best-effort**: jika Redis mati, sistem tetap jalan dengan
  membaca PostgreSQL (fail-open), hanya lebih lambat.
- Kunci wajib punya TTL; dilarang menyimpan key tanpa kadaluarsa.

### 4.4 Dashboard PHP

- PHP hanya berkomunikasi dengan FastAPI melalui `docs/API_CONTRACT.md`.
- PHP **tidak** menjalankan model NLP/ML sendiri.
- Halaman dashboard:
  1. Ringkasan order hari ini + status.
  2. Posisi stok bahan (cache Redis).
  3. Daftar order `short` beserta kekurangan bahan.
  4. QC harian.
  5. Forecast 7 hari per menu.

## 5. Mesin status order (`b2b_orders.status`)

```
received --parse ok--> parsed --stok cukup--> confirmed --QC pass--> fulfilled
   |                     |                      |
   |                     |                      +--QC fail--> qc_hold
   |                     +--stok kurang--> short --restock--> confirmed
   +--parse gagal--> parse_failed
   (cancelled bisa dari status apa pun sebelum fulfilled)
```

| Status | Arti |
| --- | --- |
| `received` | Order tersimpan, belum diproses |
| `parsed` | Teks sudah diubah menjadi item menu |
| `confirmed` | Stok mencukupi, order disetujui |
| `short` | Stok tidak mencukupi, menunggu restock |
| `qc_hold` | Menunggu hasil QC |
| `fulfilled` | Selesai, stok sudah dipotong |
| `parse_failed` | Parser gagal / menu tidak dikenali |
| `cancelled` | Dibatalkan admin |

## 6. Quality control

- Setiap `order_items` yang akan dipenuhi wajib punya minimal satu `qc_logs`
  berstatus `pass`.
- QC `fail` -> order tidak boleh `fulfilled`; masuk `qc_hold`.
- QC dicatat atas dasar bahan (`ingredient_id`) yang dipakai item tersebut.

## 7. Demand forecast

- Dihitung otomatis setelah order `fulfilled`.
- Menyimpan `forecast_date`, `forecast_qty`, `confidence` (0..1),
  `model_version` agar hasil bisa dibandingkan antar versi model.
- Forecast adalah **prediksi**, bukan kewajiban produksi; dipakai untuk
  saran restock di dashboard.

## 8. Aturan bisnis kritis

1. Stok bahan tidak boleh negatif, tanpa kecuali.
2. Satu order = satu transaksi saat pemotongan stok.
3. `menu_sku` pada item order harus ada di `menus.sku`.
4. Perubahan kontrak JSON wajib memperbarui `docs/API_CONTRACT.md` di commit yang sama.
5. Semua perubahan skema wajib punya seed data yang konsisten.

## 9. Non-functional requirement

| Aspek | Target |
| --- | --- |
| Latency parse order | < 1,5 detik (p95) |
| Latency baca stok (cache hit) | < 50 ms |
| Ketersediaan | 99,5% pada jam operasional 05.00–22.00 |
| Cache | Redis wajib fail-open |
| Audit | Setiap perubahan status order tercatat di `updated_at` |

## 10. Out of scope (v1)

- Pembayaran & invoice pajak.
- Multi-cabang / multi-gudang.
- Aplikasi mobile native.
- Integrasi WhatsApp Business API (order tetap di-input manual oleh admin).

## 11. Definition of Done alur

Sebuah fitur dianggap selesai bila alur `[1]`–`[9]` dapat dijalankan dari nol
mengikuti dokumen ini, skema termigrasi, dan kontrak API dipenuhi.