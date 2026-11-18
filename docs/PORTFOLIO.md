# Panduan Portofolio — Bogana AI ERP

Dokumen ini menjelaskan cara mempresentasikan proyek ini secara jujur dan aman,
termasuk batasannya. Gunakan bersama `README.md`.

---

## 1. Ringkasan untuk reviewer

Bogana AI ERP adalah sistem operasional B2B untuk katering: pesanan dari
WhatsApp/email diurai menjadi item menu, dicek terhadap stok bahan, melalui QC,
lalu dialokasikan secara transaksional. Sistem terdiri dari dua service:

- **Dashboard PHP 8.2** (`backend/`) — antarmuka operasional, tanpa framework,
  dengan CSRF dan kontrol akses opsional.
- **AI service FastAPI** (`ai_service/`) — parser teks, aturan stok, QC,
  alokasi, dashboard, dan forecast.
- **PostgreSQL** sebagai sumber kebenaran, **Redis** untuk cache/idempotensi.

Yang layak ditonjolkan bukan jumlah fitur, tetapi **cara kerja**: kontrak API
tertulis, CI, test regresi, smoke test production, dan penanganan bug nyata.

---

## 2. Arsitektur

```text
Browser
   │
   ▼
Dashboard PHP (Vercel, container)
   │  HTTP/JSON + X-Internal-Token
   ▼
FastAPI AI service (Vercel, Python runtime)
   │
   ├── PostgreSQL (Neon)  → ingredients, orders, qc_logs, demand_forecasts
   └── Redis (Upstash)    → cache stok, dashboard, idempotensi parser
```

Keputusan desain yang bisa diceritakan:

1. **PHP tidak memanggil model langsung.** Semua logika bisnis ada di FastAPI,
   PHP hanya menyajikan UI dan meneruskan kontrak JSON.
2. **Stok diubah dalam transaksi** (`SELECT ... FOR UPDATE`), bukan update liar.
3. **Sesi dashboard stateless** — cookie bertanda tangan HMAC, cocok untuk
   serverless yang tidak punya session file bersama.
4. **Rate limit per IP** — demo publik boleh menulis, jadi AI service membatasi
   laju baca/tulis dan menolak kelebihan dengan `429 RATE_LIMITED`, bukan
   menonaktifkan fitur.

---

## 3. Demo publik yang boleh dicoba

Dashboard production sengaja berjalan **publik penuh** supaya reviewer bisa
langsung mencoba alur tulis: simpan order, konfirmasi, QC, alokasi, dan restock.
Tidak ada mode hanya-baca dan tidak ada kredensial yang perlu dibagikan.

```env
DASHBOARD_ACCESS_MODE=open
```

Konsekuensinya data demo bisa berubah. Pulihkan kondisi awal dengan menjalankan
ulang `database/schema.sql` (skrip ini men-drop lalu mengisi ulang tabel).

Pengamanan yang aktif:

- **Rate limit per IP** di AI service: default 120 permintaan baca dan 30 tulis
  per menit. Kelebihan mendapat `429 RATE_LIMITED` + `Retry-After`.
- PHP meneruskan IP pengunjung lewat `X-Client-IP`; FastAPI hanya memercayainya
  bila permintaan membawa token internal yang sah.
- Penghitung disimpan di Redis sehingga konsisten antar instance serverless.
- Bila Redis tidak tersedia, pembatasan dilewati (fail open) agar dashboard
  tetap hidup; kejadian ini dicatat di log.
- `GET /health` dikecualikan supaya uptime check tidak memakan kuota.

Untuk pemakaian bisnis nyata, aktifkan login:

```env
DASHBOARD_ACCESS_MODE=required
DASHBOARD_USER=admin
DASHBOARD_PASSWORD_HASH=<hasil password_hash>
DASHBOARD_SESSION_SECRET=<string acak 32+ karakter>
```

---

## 4. Checklist screenshot / video

Ambil screenshot pada viewport desktop 1440×900 dan mobile 390×844:

| # | Layar | Yang ingin ditunjukkan |
| --- | --- | --- |
| 1 | Dashboard | Ringkasan order, stok kritis, status sistem |
| 2 | Order & Parser | Teks WhatsApp mentah → item terurai |
| 3 | Detail order | Alur cek stok → konfirmasi → QC → alokasi |
| 4 | Stok bahan | Gauge stok vs reorder point + restock |
| 5 | Forecast | Proyeksi permintaan + confidence |
| 6 | Status sistem | `postgres: ok`, `redis: ok` |
| 7 | Rate limit | Respons `429 RATE_LIMITED` (opsional, bukti proteksi) |

Simpan di `docs/screenshots/` dengan nama `01-dashboard.png`, dst., lalu
tambahkan ke `README.md`:

```md
![Dashboard](docs/screenshots/01-dashboard.png)
```

Video 60–90 detik yang efektif:

1. Tempel teks order → urai → simpan.
2. Cek stok → konfirmasi → QC → alokasi.
3. Tunjukkan smoke test production lulus di terminal.

---

## 5. Narasi yang bisa dipakai saat interview

- **Tentang bug P0:** "Saya menemukan restock selalu 500 di production.
  Ternyata satu repository memanggil method yang tidak pernah didefinisikan, dan
  test lama menutupinya karena memakai fake repository. Saya tambahkan
  `_require_pool()`, regression test dengan repository asli, lalu verifikasi
  ulang di production lewat smoke test."
- **Tentang keamanan:** "Dashboard ini sengaja publik supaya reviewer bisa
  mencoba alur tulis. Karena itu saya menambahkan rate limit per IP di AI
  service dengan Redis, meneruskan IP asli dari PHP, dan hanya memercayai
  header itu bila request membawa token internal. Login berbasis cookie
  bertanda tangan tetap tersedia untuk pemakaian nyata."
- **Tentang proses:** "Tidak ada commit langsung ke `main`. Setiap perubahan
  lewat branch, commit kecil, PR, dan CI yang menjalankan lint, test, serta test
  kontrol akses."

---

## 6. Batasan yang harus disebutkan (jangan disembunyikan)

- Belum ada multi-user dengan role; hanya satu akun operasional.
- Belum ada audit trail perubahan stok (`stock_movements`).
- Data demo publik bisa diubah pengunjung; pemulihan masih manual lewat
  `database/schema.sql`.
- Belum ada staging terpisah; verifikasi tulis memakai SKU uji di production.
- Belum ada monitoring/alerting otomatis dan backup terjadwal.

Menyebutkan batasan justru meningkatkan kredibilitas, selama Anda juga punya
rencana perbaikannya (lihat Roadmap di `README.md`).

---

## 7. Bukti yang bisa ditunjukkan

```text
ruff check .            → All checks passed
ruff format --check .   → semua file sesuai
pytest -q               → semua lulus (test Redis nyata di-skip bila Redis lokal mati)
php backend/tests/AuthTest.php     → PASS 25 assertion
php backend/tests/ClientIpTest.php → PASS 9 assertion
```

Smoke test production terakhir:

```text
Total: 19 | PASS: 18 | FAIL: 0 | SKIP: 1
```

Cara menjalankan ulang: [`docs/PRODUCTION_SMOKE.md`](PRODUCTION_SMOKE.md).
