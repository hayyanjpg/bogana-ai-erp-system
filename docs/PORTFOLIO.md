# Panduan Portofolio — Bogana AI ERP

Dokumen ini menjelaskan cara mempresentasikan proyek ini secara jujur dan aman,
termasuk batasannya. Gunakan bersama `README.md`.

---

## 1. Ringkasan untuk reviewer

Bogana AI ERP adalah sistem operasional B2B untuk katering: pesanan dari
WhatsApp/email diurai menjadi item menu, dicek terhadap stok bahan, melalui QC,
lalu dialokasikan secara transaksional. Sistem terdiri dari dua service:

- **Dashboard PHP 8.2** (`backend/`) — antarmuka operasional, tanpa framework,
  dengan CSRF, kontrol akses, dan mode demo.
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
4. **Mode demo hanya-baca** — aksi tulis ditolak di server, bukan hanya
   disembunyikan di UI.

---

## 3. Menyiapkan demo publik yang aman

Jangan bagikan dashboard yang bisa menulis tanpa login. Pilih salah satu:

### Opsi A — Mode demo (paling aman)

```env
DASHBOARD_ACCESS_MODE=demo
```

- Semua halaman dapat dibuka publik.
- Semua `POST` ditolak dengan `403 DEMO_READ_ONLY`.
- Banner "Mode demo (hanya baca)" tampil di setiap halaman.
- Tidak perlu membagikan kredensial ke recruiter.

### Opsi B — Login dengan akun demo

```env
DASHBOARD_ACCESS_MODE=required
DASHBOARD_USER=demo
DASHBOARD_PASSWORD_HASH=<hasil password_hash>
DASHBOARD_SESSION_SECRET=<string acak 32+ karakter>
```

Bagikan kredensial demo secara terpisah, dan reset data secara berkala.

### Opsi C — Keduanya

Pakai `required` untuk admin, dan sediakan instance/preview terpisah dengan
`demo` untuk publik. Ini yang paling mendekati praktik nyata.

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
| 7 | Mode demo | Banner hanya-baca (bukti kontrol akses) |

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
- **Tentang keamanan:** "Dashboard awalnya publik dan bisa menulis. Saya
  tambahkan login dengan cookie bertanda tangan, sesi stateless untuk serverless,
  logout via POST + CSRF, dan mode demo hanya-baca yang ditegakkan di server."
- **Tentang proses:** "Tidak ada commit langsung ke `main`. Setiap perubahan
  lewat branch, commit kecil, PR, dan CI yang menjalankan lint, test, serta test
  kontrol akses."

---

## 6. Batasan yang harus disebutkan (jangan disembunyikan)

- Belum ada multi-user dengan role; hanya satu akun operasional.
- Belum ada audit trail perubahan stok (`stock_movements`).
- Belum ada rate limiting di AI service.
- Belum ada staging terpisah; verifikasi tulis memakai SKU uji di production.
- Belum ada monitoring/alerting otomatis dan backup terjadwal.

Menyebutkan batasan justru meningkatkan kredibilitas, selama Anda juga punya
rencana perbaikannya (lihat Roadmap di `README.md`).

---

## 7. Bukti yang bisa ditunjukkan

```text
ruff check .            → All checks passed
ruff format --check .   → semua file sesuai
pytest -q               → 62 passed
php backend/tests/AuthTest.php → PASS 27 assertion
```

Smoke test production terakhir:

```text
Total: 19 | PASS: 18 | FAIL: 0 | SKIP: 1
```

Cara menjalankan ulang: [`docs/PRODUCTION_SMOKE.md`](PRODUCTION_SMOKE.md).
