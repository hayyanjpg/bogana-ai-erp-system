# AGENTS.md

Aturan wajib untuk AI agent (dan manusia) yang berkontribusi di repositori
`bogana-ai-erp-system`. Setiap instruksi di file ini bersifat **hard rule**:
pelanggaran atas aturan ini membuat perubahan dianggap tidak valid dan harus
diperbaiki sebelum merge.

---

## 0. Ringkasan aturan (TL;DR)

1. Baca `database/schema.sql` dan `docs/API_CONTRACT.md` sebelum menulis kode apa pun.
2. Dilarang commit atau push langsung ke `main`.
3. Satu perubahan = satu commit kecil dengan pesan yang jelas.
4. Semua kode wajib lolos linter dan test sebelum merge.

---

## 1. Sumber kebenaran (single source of truth)

| Hal | Sumber kebenaran | Aturan |
| --- | --- | --- |
| Struktur tabel, kolom, relasi, index, seed data | `database/schema.sql` | Jangan pernah mengarang nama tabel/kolom. Buka file ini dulu. |
| Kontrak JSON PHP ↔ FastAPI | `docs/API_CONTRACT.md` | Nama field, tipe data, dan status code mengikuti file ini. |
| Alur bisnis end-to-end | `docs/PRD_ERP_FLOW.md` | Logika baru harus konsisten dengan alur yang terdokumentasi. |
| Topologi service & port | `docker-compose.yml` | Port, nama service, dan env var mengikuti compose file. |

**Larangan keras:**

- Dilarang membuat tabel, kolom, index, atau endpoint baru yang tidak tercatat di
  `database/schema.sql` / `docs/API_CONTRACT.md` tanpa memperbarui file sumber
  kebenaran tersebut lebih dulu.
- Dilarang mengubah tipe data kolom di dalam kode aplikasi (mis. casting paksa)
  tanpa memperbarui `database/schema.sql`.
- Dilarang "menambal" bug dengan mengubah kontrak API secara diam-diam. Kalau
  kontrak berubah, ubah `docs/API_CONTRACT.md` di commit yang sama.

**Alur kerja yang benar:**

```
baca docs/PRD_ERP_FLOW.md
  -> baca database/schema.sql
  -> baca docs/API_CONTRACT.md
  -> baru tulis kode
```

---

## 2. Aturan branch & commit

### 2.1 Dilarang bekerja di `main`

- `main` adalah branch **protected**. Commit/push langsung ke `main` dilarang.
- Semua pekerjaan fitur, fix, atau refactor wajib berada di branch terpisah
  dengan pola nama:

  | Jenis | Pola nama branch | Contoh |
  | --- | --- | --- |
  | Fitur | `feat/<scope>` | `feat/fastapi-ai-service` |
  | Perbaikan bug | `fix/<scope>` | `fix/stok-negatif-qc` |
| Dokumentasi | `docs/<scope>` | `docs/api-contract-v2` |
  | Refactor | `refactor/<scope>` | `refactor/parser-llm-prompt` |
  | Chore/CI | `chore/<scope>` | `chore/ci-cache-pip` |

- Alur yang benar:

  ```bash
  git checkout main
  git pull --ff-only
  git checkout -b feat/nama-fitur
  ```

- Branch fitur wajib up-to-date dengan `main` sebelum membuka Pull Request:

  ```bash
  git fetch origin
  git rebase origin/main
  ```

- Merge hanya melalui Pull Request. Merge commit langsung dari lokal (tanpa PR)
  dianggap pelanggaran.

### 2.2 Commit kecil dan bertahap

- **Satu perubahan = satu commit.** Jangan menumpuk banyak perubahan berbeda
  dalam satu commit.
- Ukuran commit yang diharapkan: satu file, satu fungsi, atau satu perbaikan
  yang utuh dan bisa dijelaskan dalam satu kalimat.
- Commit yang terlalu besar ("big bang commit") akan diminta untuk dipecah.
- Gunakan Conventional Commits:

  ```
  <tipe>(<scope>): <deskripsi singkat imperatif>
  ```

  Tipe yang diizinkan: `feat`, `fix`, `docs`, `style`, `refactor`, `perf`,
  `test`, `build`, `ci`, `chore`, `revert`.

  Contoh yang benar:

  ```
  feat(api): tambah endpoint POST /v1/parse-order
  fix(qc): cegah qc_logs duplikat pada order_items
  docs(contract): dokumentasikan field forecast_confidence
  ```

- Jangan commit file sampah: `node_modules`, `__pycache__`, `.env`, `.venv`,
  dump database, log, atau credential.
- Jangan pernah commit secret (API key, password, token). Gunakan `.env.example`.

---

## 3. Wajib lolos linter & test

Setiap commit **wajib** lolos pemeriksaan berikut sebelum Pull Request dibuka.
CI (`.github/workflows/ci.yml`) akan menolak merge jika salah satu gagal.

### 3.1 PHP

```bash
find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l
```

- Setiap file PHP harus bebas *parse error*.
- Standar gaya: PSR-12.

### 3.2 Python (FastAPI)

```bash
ruff check .
ruff format --check .
pytest -q
```

- `ruff` wajib **0 error** (bukan cuma warning).
- `pytest` wajib **semua hijau**; test baru wajib dibuat untuk setiap endpoint
  atau fungsi bisnis baru.
- Target cakupan minimal 70% untuk modul `app/`.

### 3.3 SQL

- `database/schema.sql` wajib bisa dieksekusi ulang dari nol di PostgreSQL
  (`docker compose up -d db` lalu `psql -f database/schema.sql`) tanpa error.
- Perubahan skema wajib menyertakan seed/dummy data yang tetap konsisten dengan
  tabel baru.

### 3.4 Perintah lokal sebelum push

```bash
# PHP
find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l

# Python
ruff check .
pytest -q

# Verifikasi skema
docker compose exec -T db psql -U postgres -d bogana -f - < database/schema.sql
```

Aturan tambahan:

- **Dilarang** menonaktifkan linter (`# noqa` tanpa alasan, `// @phpstan-ignore`,
  `eslint-disable`) tanpa komentar alasan yang jelas.
- **Dilarang** men-skip atau menghapus test yang gagal. Perbaiki kode atau
  perbaiki test beserta alasannya di deskripsi commit.
- Test yang di-skip wajib dicatat alasannya di deskripsi Pull Request.

---

## 4. Konvensi kode

### 4.1 Struktur direktori

```
bogana-ai-erp-system/
├── AGENTS.md
├── PRD_ERP_FLOW.md -> docs/PRD_ERP_FLOW.md
├── docs/
│   ├── PRD_ERP_FLOW.md
│   └── API_CONTRACT.md
├── database/
│   └── schema.sql
├── backend/            # PHP (dashboard & admin ERP)
├── ai_service/         # Python FastAPI (NLP parser, forecasting)
├── tests/
├── docker-compose.yml
└── .github/workflows/ci.yml
```

### 4.2 Python

- Python 3.11+, FastAPI, Pydantic v2 untuk semua payload request/response.
- Seluruh payload API didefinisikan sebagai model Pydantic di
  `ai_service/app/schemas.py`, dan **wajib** dicocokkan dengan
  `docs/API_CONTRACT.md`.
- Format: `ruff format` (line length 100).
- Type hints wajib pada semua fungsi publik.
- Koneksi DB memakai connection pool (asyncpg / SQLAlchemy async), jangan buka
  koneksi baru per request.

### 4.3 PHP

- PHP 8.2+, PSR-12, PSR-4 autoloading.
- Jangan taruh kredensial di kode; baca dari environment variable.
- Query SQL memakai prepared statement (PDO). **Dilarang** menyusun SQL dengan
  string concatenation dari input pengguna.
- Dashboard PHP hanya berbicara dengan FastAPI melalui kontrak JSON di
  `docs/API_CONTRACT.md`; PHP **tidak** boleh memanggil model ML/NLP secara
  langsung.

---

## 5. Aturan domain (ERP F&B B2B)

- Setiap order dari customer B2B **wajib** melewati alur:
  `order_items -> stok ingredients -> qc_logs -> demand_forecasts`.
- Stok `ingredients` tidak boleh negatif. Penambahan stok hanya lewat transaksi
  yang tercatat (mis. `qc_logs`/`stock_movements`), bukan update manual liar.
- Semua perubahan stok harus berada dalam satu transaksi database
  (`BEGIN ... COMMIT`), agar tidak ada stok "hilang" saat order masuk bersamaan.
- ID unik memakai tipe yang konsisten (UUID atau `BIGSERIAL`) sesuai
  `database/schema.sql`, jangan mencampur keduanya dalam satu tabel.

---

## 6. Definition of Done

Sebuah perubahan dianggap selesai hanya jika:

- [ ] Berada di branch fitur (bukan `main`).
- [ ] `ruff check .` dan `pytest -q` lolos (untuk perubahan Python).
- [ ] `php -l` lolos untuk semua file PHP yang tersentuh.
- [ ] `database/schema.sql` tetap dapat dieksekusi dari nol.
- [ ] Kontrak API diperbarui jika ada payload yang berubah.
- [ ] Commit kecil, pesan Conventional Commits, tanpa file sampah.
- [ ] Pull Request dibuka ke `main` dan CI hijau.