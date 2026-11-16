# Bogana AI ERP System

Sistem ERP internal untuk usaha F&B B2B: order teks (WhatsApp/email/telepon) diubah
menjadi item menu oleh **NLP parser**, stok bahan diperiksa di **PostgreSQL**,
hasil agregat dan status stok dilayani cepat melalui **Redis**, dan dashboard
operasional ditulis dengan **PHP**.

Proyek ini dibangun sebagai portofolio end-to-end untuk posisi **AI Engineer**
(PostgreSQL, Redis, PHP, Python FastAPI).

[![CI](https://github.com/hayyanjpg/bogana-ai-erp-system/actions/workflows/ci.yml/badge.svg)](https://github.com/hayyanjpg/bogana-ai-erp-system/actions/workflows/ci.yml)
![Python](https://img.shields.io/badge/Python-3.11-3776AB?logo=python&logoColor=white)
![FastAPI](https://img.shields.io/badge/FastAPI-0.115-009688?logo=fastapi&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php&logoColor=white)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-4169E1?logo=postgresql&logoColor=white)
![Redis](https://img.shields.io/badge/Redis-7-DC382D?logo=redis&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker&logoColor=white)

---

## Daftar Isi

- [Fitur Utama](#fitur-utama)
- [Arsitektur](#arsitektur)
- [Alur Order](#alur-order)
- [Teknologi](#teknologi)
- [Struktur Repositori](#struktur-repositori)
- [Quick Start (Docker)](#quick-start-docker)
- [Deploy Gratis (Vercel + Neon)](#deploy-gratis-vercel--neon)
- [Pengembangan Lokal](#pengembangan-lokal)
- [API](#api)
- [Testing & Kualitas Kode](#testing--kualitas-kode)
- [Konvensi Kontribusi](#konvensi-kontribusi)
- [Roadmap](#roadmap)

---

## Fitur Utama

| Fitur | Deskripsi |
| --- | --- |
| NLP order parser | Teks bebas `"40 nasi bogana komplit, 15 ayam kecap"` → item menu + kuantitas, dengan warning `MENU_NOT_FOUND`, `QTY_MISSING`, `QTY_AMBIGUOUS`, `DUPLICATE_LINE`, `LOW_CONFIDENCE`. |
| Cek stok | Hitung kebutuhan bahan dari resep (`menu_recipes`) terhadap stok `ingredients`. |
| Alokasi transaksional | Potong stok dalam satu transaksi PostgreSQL, cegah stok negatif, dukung optimistic locking (`version`). |
| Quality control | Order hanya bisa `fulfilled` bila seluruh item punya `qc_logs` `pass`. |
| Cache Redis | Snapshot stok (60 s), hasil parse (15 menit), agregat dashboard (30 s), fail-open bila Redis mati. |
| Dashboard PHP | Ringkasan order, stok menipis, order `short`, menu terlaris, forecast, status sistem. |
| Demand forecast | Prakiraan permintaan per menu dengan `confidence` dan `model_version`. |
| CI/CD | GitHub Actions: `php -l`, `ruff`, `pytest` pada setiap PR ke `main`. |

---

## Arsitektur

```mermaid
flowchart LR
    Admin[Admin Operasional] --> PHP[Dashboard PHP 8.2]
    PHP -->|HTTP + JSON| API[FastAPI AI Service]
    API --> Parser[NLP Parser]
    API --> Forecast[Forecast Service]
    API --> PG[(PostgreSQL 16)]
    API --> Redis[(Redis 7)]

    subgraph Kontrak["docs/API_CONTRACT.md"]
        API
    end

    subgraph Data["database/schema.sql"]
        PG
    end
```

Komponen:

| Service | Port | Tanggung jawab |
| --- | --- | --- |
| `php` | 8080 | Dashboard, form filter, render data dari FastAPI |
| `ai_service` | 8000 | NLP parser, cek stok, alokasi, dashboard summary, forecast |
| `db` | 5432 | PostgreSQL 16 + seed data |
| `redis` | 6379 | Cache stok, parse, dashboard |

---

## Alur Order

```
Order teks
   │
   ▼
POST /v1/parse-order ──► FastAPI NLP parser ──► item menu + warning
   │
   ▼
POST /v1/orders/{id}/check-stock ──► resep × kuantitas vs stok
   │
   ├── stok cukup ──► status confirmed
   └── stok kurang ─► status short
   │
   ▼
QC dapur (qc_logs)
   │
   ▼
POST /v1/orders/{id}/allocate
   • transaksi: FOR UPDATE order
   • cek status/version/QC/stok
   • potong stok (anti-negatif)
   • status fulfilled
   • invalidasi cache
   │
   ▼
Dashboard PHP + demand_forecasts
```

Diagram status lengkap dan aturan bisnis: [`docs/PRD_ERP_FLOW.md`](docs/PRD_ERP_FLOW.md).

---

## Teknologi

| Lapisan | Teknologi |
| --- | --- |
| Backend AI | Python 3.11, FastAPI, Pydantic v2, asyncpg, Redis |
| Frontend | PHP 8.2, HTML + CSS (tanpa framework) |
| Database | PostgreSQL 16 |
| Cache | Redis 7 |
| Infra | Docker Compose, Vercel (deploy gratis), GitHub Actions |
| Lint/Test | ruff, pytest, `php -l` |

---

## Struktur Repositori

```
bogana-ai-erp-system/
├── AGENTS.md                     # aturan kontribusi untuk AI & manusia
├── README.md
├── docker-compose.yml
├── .env.example
├── pyproject.toml                # konfigurasi pytest
├── database/
│   └── schema.sql                # skema + seed data (source of truth)
├── docs/
│   ├── PRD_ERP_FLOW.md           # alur bisnis end-to-end
│   ├── API_CONTRACT.md           # kontrak JSON PHP ↔ FastAPI
│   └── DEPLOY_VERCEL.md          # deploy gratis tanpa kartu kredit
├── ai_service/                   # Python FastAPI
│   ├── app/
│   │   ├── routers/              # health, orders, stocks, dashboard, forecasts
│   │   ├── services/             # parser, stock, order, dashboard, forecast
│   │   ├── repositories.py
│   │   ├── schemas.py
│   │   ├── cache.py
│   │   ├── config.py
│   │   ├── db.py
│   │   └── main.py
│   ├── tests/
│   ├── Dockerfile
│   └── requirements.txt
├── backend/                      # PHP dashboard
│   ├── public/index.php
│   ├── public/healthz.php        # health check ringan
│   ├── src/                      # Config, ApiClient, Router, View
│   ├── templates/                # layout, dashboard, stocks, forecasts, health
│   ├── Caddyfile                 # web server FrankenPHP
│   ├── Dockerfile                # FrankenPHP + Caddy (Vercel & compose)
│   └── composer.json
└── .github/workflows/ci.yml
```

---

## Quick Start (Docker)

Prasyarat: Docker + Docker Compose.

```bash
git clone https://github.com/hayyanjpg/bogana-ai-erp-system.git
cd bogana-ai-erp-system
cp .env.example .env

docker compose up -d --build
```

Akses:

| Layanan | URL |
| --- | --- |
| Dashboard PHP | http://localhost:8080 |
| API FastAPI | http://localhost:8000 |
| Swagger UI | http://localhost:8000/docs |
| Health check | http://localhost:8000/health |

`database/schema.sql` otomatis dijalankan saat volume PostgreSQL pertama kali
dibuat (mount ke `/docker-entrypoint-initdb.d`). Untuk menjalankan ulang seed:

```bash
docker compose exec -T db psql -U postgres -d bogana -f - < database/schema.sql
```

> Kredensial pada `.env.example` hanya untuk development. Jangan dipakai di
> production.

### Perintah umum

```bash
docker compose logs -f ai_service
docker compose logs -f php
docker compose down            # stop
docker compose down -v         # stop + hapus volume (reset database)
```

---

## Deploy Gratis (Vercel + Neon)

Aplikasi ini bisa online **tanpa kartu kredit** dan tanpa biaya bulanan:

| Lapisan | Penyedia | Free tier |
| --- | --- | --- |
| FastAPI | Vercel (Python runtime) | Hobby: 4 CPU-hours, 100 GB, 1M invocations |
| Dashboard PHP | Vercel (Container runtime) | Hobby: container beta, scale-to-zero |
| PostgreSQL | Neon | 1 GB, 100 CU-hours/bulan, scale-to-zero, tanpa kedaluwarsa |
| Redis | Upstash | 250 MB, 500K commands/bulan (opsional) |

Dua project Vercel dari satu repo:

| Project | Root Directory | URL |
| --- | --- | --- |
| `bogana-ai-service` | `ai_service` | `https://bogana-ai-service.vercel.app` |
| `bogana-php` | `backend` | `https://bogana-php.vercel.app` |

Langkah singkat:

1. Buat project Neon, lalu muat `database/schema.sql` melalui SQL Editor.
2. (Opsional) Buat database Redis di Upstash dan copy URL `rediss://`.
3. Vercel → **Add New → Project** → import repo → **Root Directory** `ai_service` →
   isi `DATABASE_URL`, `AI_SERVICE_TOKEN`, `AI_SERVICE_AUTH_DISABLED=false`.
4. Ulangi untuk project kedua dengan **Root Directory** `backend` → isi
   `AI_SERVICE_URL` (URL project pertama) + token yang sama.
5. Buka `https://bogana-php.vercel.app`.

Panduan lengkap, batasan free tier, dan troubleshooting:
[`docs/DEPLOY_VERCEL.md`](docs/DEPLOY_VERCEL.md).

> Vercel Hobby gratis tanpa kartu (untuk proyek portofolio/non-komersial).
> Container runtime masih beta; instance scale-to-zero setelah 5 menit idle.

---

## Pengembangan Lokal

### FastAPI

```bash
cd ai_service
python -m venv .venv
# Windows: .venv\Scripts\activate
# Linux/macOS: source .venv/bin/activate

pip install -r requirements.txt
export DATABASE_URL=postgresql://postgres:postgres@localhost:5432/bogana
export REDIS_URL=redis://localhost:6379/0
uvicorn app.main:app --reload --port 8000
```

### PHP

```bash
cd backend
composer install
AI_SERVICE_URL=http://127.0.0.1:8000 php -S 127.0.0.1:8080 -t public
```

Buka http://127.0.0.1:8080.

---

## API

Kontrak lengkap: [`docs/API_CONTRACT.md`](docs/API_CONTRACT.md).

| Method | Endpoint | Fungsi |
| --- | --- | --- |
| GET | `/health` | Health check PostgreSQL + Redis |
| POST | `/v1/parse-order` | Teks order → item menu |
| GET | `/v1/stocks` | Daftar stok bahan |
| GET | `/v1/stocks/{sku}` | Detail bahan + menu yang memakainya |
| POST | `/v1/orders/{id}/check-stock` | Cek kecukupan bahan |
| POST | `/v1/orders/{id}/allocate` | Alokasi stok transaksional |
| GET | `/v1/dashboard/summary` | Agregat dashboard |
| GET | `/v1/forecasts` | Forecast per menu |

Contoh:

```bash
curl -X POST http://localhost:8000/v1/parse-order \
  -H "Content-Type: application/json" \
  -d '{
    "raw_text": "gan besok pagi 40 nasi bogana komplit sama 15 ayam kecap",
    "channel": "whatsapp",
    "customer_name": "Warung Bu Sri"
  }'
```

---

## Testing & Kualitas Kode

```bash
# Python
ruff check .
ruff format --check .
pytest -q

# PHP
find backend -name '*.php' -not -path '*/vendor/*' -print0 | xargs -0 -n1 php -l
```

CI di `.github/workflows/ci.yml` menjalankan:

1. `php -l` untuk seluruh file PHP.
2. `ruff check .` dan `ruff format --check .`.
3. `pytest -q` dengan service PostgreSQL 16 + Redis 7, skema dimuat dari
   `database/schema.sql`.

---

## Konvensi Kontribusi

Aturan lengkap ada di [`AGENTS.md`](AGENTS.md). Ringkas:

- Baca `database/schema.sql` dan `docs/API_CONTRACT.md` sebelum menulis kode.
- Dilarang commit/push langsung ke `main`; gunakan branch `feat/*`, `fix/*`, dst.
- Satu perubahan = satu commit kecil (Conventional Commits).
- Wajib lolos `ruff`, `pytest`, dan `php -l` sebelum PR.
- Merge hanya melalui Pull Request dengan CI hijau.

---

## Roadmap

- [x] Fondasi: schema, PRD, API contract, CI, docker-compose.
- [x] FastAPI: health, parse-order, stocks, check-stock, allocate, dashboard, forecasts.
- [x] Dashboard PHP: dashboard, stok, forecast, status.
- [ ] Validasi `docker compose up` end-to-end (butuh Docker di mesin berbeda).
- [ ] Integrasi test dengan PostgreSQL nyata (bukan fake repository).
- [ ] Screenshot dashboard untuk README.
- [ ] Parser berbasis LLM dengan fallback rule-based.
- [ ] Forecast model v2 (mis. statsmodels/Prophet) + evaluasi confidence.
- [ ] Autentikasi multi-user & audit log dashboard.