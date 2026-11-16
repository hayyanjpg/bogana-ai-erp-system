# Deploy Gratis: Render + Neon + Upstash

Panduan ini menayangkan aplikasi ke internet **tanpa kartu kredit** dan tanpa
biaya bulanan. Total biaya: **Rp 0**.

| Kebutuhan | Penyedia | Free tier |
| --- | --- | --- |
| Hosting PHP + FastAPI | Render | 2 web service, 512 MB RAM, 750 jam/bulan per workspace |
| PostgreSQL | Neon | 1 GB, 100 CU-hours/project/bulan, scale-to-zero |
| Redis (opsional) | Upstash | 250 MB, 500K commands/bulan |

Semua pendaftaran di atas bisa memakai akun GitHub yang sudah ada.

> Render **tidak** memakai kartu kredit untuk free tier. Render Postgres gratis
> kedaluwarsa 30 hari, jadi proyek ini memakai Neon (tanpa kedaluwarsa).

---

## Arsitektur

```
        https://bogana-php.onrender.com
                    │
                    ▼
        PHP 8.2 + Apache (Render, free)
                    │  HTTPS + JSON
                    ▼
        https://bogana-ai-service.onrender.com
                    │
        ┌───────────┴───────────┐
        ▼                       ▼
   Neon PostgreSQL        Upstash Redis
   (data ERP)             (cache, opsional)
```

Skema dan seed dimuat ke Neon dari `database/schema.sql`.

---

## Langkah 1 — Neon (PostgreSQL)

1. Buka <https://neon.com> → **Sign up** → pilih **Continue with GitHub**.
2. **Create project**:
   - Name: `bogana-erp`
   - Postgres version: 16 (atau default)
   - Region: **AWS · Asia Pacific (Singapore)** — paling dekat dari Indonesia
3. Setelah project dibuat, buka **Connection Details**:
   - Matikan toggle **Pooled connection** (gunakan **direct** connection string).
     `asyncpg` memakai prepared statement, jadi hindari PgBouncer.
   - Copy connection string. Bentuknya:
     ```
     postgresql://<user>:<password>@ep-xxxx.ap-southeast-1.aws.neon.tech/bogana?sslmode=require
     ```
   > Neon biasanya menyertakan `&channel_binding=require`. Biarkan apa adanya —
   > `app/db.py` membuangnya otomatis karena asyncpg belum mendukungnya.
4. Muat skema + seed:
   - Buka **SQL Editor** di sidebar Neon.
   - Paste **seluruh isi** `database/schema.sql`.
   - Klik **Run**.
5. Verifikasi di SQL Editor:
   ```sql
   SELECT 'ingredients' AS tabel, count(*) FROM ingredients
   UNION ALL
   SELECT 'menus', count(*) FROM menus
   UNION ALL
   SELECT 'b2b_orders', count(*) FROM b2b_orders;
   ```
   Harus muncul 12 bahan, 6 menu, 3 order (seed).

Simpan connection string Neon untuk Langkah 3.

---

## Langkah 2 — Upstash (Redis, opsional)

Lewati langkah ini bila ingin lebih cepat. Aplikasi tetap jalan tanpa Redis
(cache nonaktif, `health` menampilkan `degraded`).

1. Buka <https://upstash.com> → **Sign up** → **Continue with GitHub**.
2. **Create Database** → pilih **Redis**:
   - Name: `bogana-cache`
   - Type: **Regional**
   - Region: **Singapore** (`ap-southeast-1`)
3. Di halaman database, bagian **Connect**, copy URL:
   ```
   rediss://default:<password>@<host>.upstash.io:6379
   ```
   Pastikan diawali `rediss://` (TLS), bukan `redis://`.

Simpan URL Upstash untuk Langkah 3.

---

## Langkah 3 — Render (Blueprint)

1. Buka <https://dashboard.render.com> → **Sign up** → **Continue with GitHub**.
2. Klik **New** → **Blueprint**.
3. Pilih repository `hayyanjpg/bogana-ai-erp-system`, branch **main**.
4. Render membaca `render.yaml` dan menampilkan dua service:
   - `bogana-ai-service` (FastAPI)
   - `bogana-php` (dashboard)
5. Isi environment variable yang diminta:

   | Service | Variable | Isi |
   | --- | --- | --- |
   | `bogana-ai-service` | `DATABASE_URL` | Connection string Neon (direct, `sslmode=require`) |
   | `bogana-ai-service` | `REDIS_URL` | URL Upstash, atau **kosongkan** bila lewati Langkah 2 |
   | `bogana-php` | — | Otomatis dari `render.yaml` (`AI_SERVICE_URL`, token, timeout) |

   `AI_SERVICE_TOKEN` dibuat otomatis oleh Render (`generateValue`) dan dibagi
   ke service PHP lewat `fromService`.
6. Klik **Apply** / **Create**.
7. Tunggu dua build selesai (± 5–10 menit). Status `Live` muncul di dashboard.

URL publik:

| Service | URL |
| --- | --- |
| Dashboard PHP | `https://bogana-php.onrender.com` |
| API FastAPI | `https://bogana-ai-service.onrender.com` |
| Swagger UI | `https://bogana-ai-service.onrender.com/docs` |

---

## Langkah 4 — Verifikasi

```bash
# 1. Health API (harus "status": "ok" atau "degraded" bila Redis kosong)
curl https://bogana-ai-service.onrender.com/health

# 2. Dashboard (harus 200)
curl -I https://bogana-php.onrender.com/?page=dashboard

# 3. Parser
curl -X POST https://bogana-ai-service.onrender.com/v1/parse-order \
  -H "Content-Type: application/json" \
  -H "X-Internal-Token: <token dari Render>" \
  -d '{"raw_text":"40 nasi bogana komplit, 15 ayam kecap","channel":"whatsapp","customer_name":"Warung Bu Sri"}'
```

Halaman `/health` pada dashboard PHP juga menampilkan status PostgreSQL dan
Redis secara langsung.

---

## Operasional

- **Auto deploy**: setiap `git push` ke `main` memicu build ulang kedua service.
- **Cold start**: setelah 15 menit idle, request pertama butuh 30–60 detik.
  Dashboard PHP menampilkan pesan "AI service tidak dapat dihubungi" bila
  request pertama gagal — cukup muat ulang.
- **Mengganti env var**: Render Dashboard → service → **Environment** → edit →
  **Save Changes** (service restart otomatis).
- **Reset data**: jalankan ulang `database/schema.sql` di Neon SQL Editor
  (skema idempoten: `DROP` → `CREATE` → seed).

---

## Batasan free tier

| Penyedia | Batasan |
| --- | --- |
| Render web service | Sleep setelah 15 menit idle; 750 jam/bulan per workspace; 512 MB RAM; tanpa disk persisten |
| Render build | 500 menit/bulan |
| Neon | 1 GB storage, 100 CU-hours/project/bulan, 5 GB egress; write gagal bila storage penuh |
| Upstash | 250 MB, 500K commands/bulan (FAQ: 10K request/hari), 1 database |

Jangan simpan data sensitif di free tier. Untuk produksi, upgrade Render
($6/bln) atau Neon Launch.

---

## Troubleshooting

| Gejala | Penyebab | Solusi |
| --- | --- | --- |
| Deploy `bogana-ai-service` gagal health check | `DATABASE_URL` salah / Neon belum bisa dihubungi | Perbaiki env var, cek Neon SQL Editor |
| Dashboard error `relation "ingredients" does not exist` | Skema belum dimuat | Jalankan `database/schema.sql` di Neon SQL Editor |
| `password authentication failed` | Connection string salah/terpotong | Copy ulang dari Neon Connection Details |
| `AI service tidak dapat dihubungi` | FastAPI cold start / masih deploy | Tunggu 1 menit, muat ulang |
| Health menampilkan `redis: down` | `REDIS_URL` kosong/salah | Isi URL Upstash atau abaikan (cache nonaktif) |
| `No such file or directory` saat build PHP | Build context salah | Pastikan `dockerContext: ./backend` di `render.yaml` |
| Service tidur terus | Free tier sleep 15 menit | Buka URL untuk membangunkan; pertimbangkan Render paid |

---

## Deploy dari mesin lain

Karena build terjadi di Render, **Docker tidak dibutuhkan di komputer Anda**.
Cukup `git push`; Render yang membangun image dari `ai_service/Dockerfile` dan
`backend/Dockerfile`.