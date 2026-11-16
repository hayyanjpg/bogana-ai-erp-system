# Deploy Gratis: Vercel + Neon

Panduan menayangkan aplikasi ke internet **tanpa kartu kredit** dan tanpa biaya
bulanan. Total biaya: **Rp 0**.

| Lapisan | Penyedia | Free tier (Hobby) |
| --- | --- | --- |
| FastAPI | Vercel — Python runtime | 4 CPU-hours, 100 GB transfer, 1M invocations/bulan |
| Dashboard PHP | Vercel — Container runtime (beta) | Container scale-to-zero, kuota Hobby yang sama |
| PostgreSQL | Neon | 1 GB, 100 CU-hours/project/bulan, scale-to-zero |
| Redis (opsional) | Upstash | 250 MB, 500K commands/bulan |

Vercel Hobby **tidak memakai kartu kredit** dan tidak punya masa aktif. Hobby
hanya untuk penggunaan pribadi/non-komersial — portofolio termasuk.

---

## Arsitektur

```
https://bogana-php.vercel.app          (Container: FrankenPHP + PHP 8.4)
            │  HTTPS + JSON + X-Internal-Token
            ▼
https://bogana-ai-service.vercel.app   (Python: FastAPI)
            │
   ┌────────┴────────┐
   ▼                 ▼
Neon PostgreSQL   Upstash Redis
```

Satu repo GitHub dipakai oleh dua project Vercel, dibedakan lewat **Root
Directory**.

---

## Langkah 1 — Neon (PostgreSQL)

1. <https://neon.com> → **Sign up** → **Continue with GitHub**.
2. **Create project**:
   - Name: `bogana-erp`
   - Region: **AWS · Asia Pacific (Singapore)**
3. Buka **SQL Editor**, paste seluruh isi `database/schema.sql`, lalu **Run**.
4. Klik **Connect** di navigasi Console → matikan toggle **Connection pooling**
   (direct) → copy connection string.
   ```
   postgresql://<user>:<password>@ep-xxxx.ap-southeast-1.aws.neon.tech/neondb?sslmode=require&channel_binding=require
   ```
   `channel_binding` boleh dibiarkan; aplikasi membuangnya otomatis.

---

## Langkah 2 — Project Vercel pertama (FastAPI)

1. <https://vercel.com/new> → **Import Git Repository** → pilih
   `hayyanjpg/bogana-ai-erp-system`.
2. Isi:
   - **Project Name**: `bogana-ai-service`
   - **Root Directory**: `ai_service` (klik **Edit**)
   - **Framework Preset**: FastAPI (terdeteksi otomatis)
3. **Environment Variables** (Environment: Production):

   | Key | Value |
   | --- | --- |
   | `DATABASE_URL` | Connection string Neon (direct) |
   | `REDIS_URL` | URL Upstash `rediss://...` atau kosongkan |
   | `AI_SERVICE_TOKEN` | String acak minimal 16 karakter (samakan dengan project PHP) |
   | `AI_SERVICE_AUTH_DISABLED` | `false` |
   | `APP_ENV` | `production` |

4. Klik **Deploy** dan tunggu status **Ready**.
5. Catat URL, misalnya `https://bogana-ai-service.vercel.app`.
6. Verifikasi:
   ```bash
   curl https://bogana-ai-service.vercel.app/health
   curl https://bogana-ai-service.vercel.app/docs
   ```

---

## Langkah 3 — Project Vercel kedua (dashboard PHP)

1. <https://vercel.com/new> → import repo yang sama.
2. Isi:
   - **Project Name**: `bogana-php`
   - **Root Directory**: `backend`
   - **Framework Preset**: **Container** (terdeteksi dari `backend/Dockerfile`).
     Bila tidak terdeteksi, pilih **Container** secara manual.
3. **Environment Variables**:

   | Key | Value |
   | --- | --- |
   | `AI_SERVICE_URL` | URL project FastAPI (Langkah 2) |
   | `AI_SERVICE_TOKEN` | Token yang sama dengan project FastAPI |
   | `AI_SERVICE_AUTH_DISABLED` | `false` |
   | `AI_SERVICE_TIMEOUT` | `60` |
   | `APP_ENV` | `production` |

4. **Deploy**. Build pertama menarik image FrankenPHP (± 2–4 menit).
5. Catat URL, misalnya `https://bogana-php.vercel.app`.

> Container runtime Vercel masih **beta**. Vercel membangun `backend/Dockerfile`
> (FrankenPHP + Caddy) dan menjalankannya sebagai Vercel Function yang listen di
> `$PORT` (default 80).

---

## Langkah 4 — Verifikasi

```bash
# Dashboard
curl -I https://bogana-php.vercel.app/

# Health API
curl https://bogana-ai-service.vercel.app/health

# Parser
curl -X POST https://bogana-ai-service.vercel.app/v1/parse-order \
  -H "Content-Type: application/json" \
  -H "X-Internal-Token: <token>" \
  -d '{"raw_text":"40 nasi bogana komplit, 15 ayam kecap","channel":"whatsapp","customer_name":"Warung Bu Sri"}'
```

Halaman `/?page=health` pada dashboard juga menampilkan status PostgreSQL dan
Redis.

---

## Operasional

- **Auto deploy**: setiap `git push` ke `main` memicu build ulang kedua project.
- **Cold start**: container scale-to-zero setelah 5 menit idle; request pertama
  butuh beberapa detik.
- **Environment variable**: Project → Settings → Environment Variables → edit →
  **Save** → redeploy.
- **Deployment Protection**: bila URL production meminta login Vercel, buka
  Project → Settings → **Deployment Protection** → matikan **Standard
  Protection** (atau set *Bypass*).
- **Reset data**: jalankan ulang `database/schema.sql` di Neon SQL Editor.

---

## Batasan free tier (Hobby)

| Resource | Kuota |
| --- | --- |
| Active CPU | 4 CPU-hours/bulan |
| Provisioned Memory | 360 GB-hours/bulan |
| Function Invocations | 1.000.000/bulan |
| Fast Data Transfer | 100 GB/bulan |
| Deployments | 100/hari |
| Max duration | 300 detik |

Jika kuota terlampaui, Vercel menjeda project sampai kuota reset (tidak menagih
kartu). Untuk demo portofolio, kuota ini lebih dari cukup.

---

## Troubleshooting

| Gejala | Penyebab | Solusi |
| --- | --- | --- |
| Build FastAPI gagal | Root directory salah / `requirements.txt` tidak ditemukan | Pastikan Root Directory `ai_service` |
| `500 INTERNAL_ERROR` / `PostgreSQL tidak tersedia` | `DATABASE_URL` salah atau Neon unreachable | Perbaiki env var, redeploy |
| `401` dari API | `AI_SERVICE_TOKEN` berbeda antara dua project | Samakan token, redeploy |
| Dashboard menampilkan "AI service tidak dapat dihubungi" | `AI_SERVICE_URL` salah / API cold start | Cek URL project FastAPI, tunggu lalu muat ulang |
| Build PHP gagal | Framework preset bukan Container | Set Framework Preset = **Container** |
| URL minta login Vercel | Deployment Protection aktif | Matikan di Settings → Deployment Protection |
| `relation "ingredients" does not exist` | Skema belum dimuat | Jalankan `database/schema.sql` di Neon SQL Editor |
| Health menampilkan `redis: down` | `REDIS_URL` kosong | Isi URL Upstash atau abaikan (cache nonaktif) |

---

## Catatan

- Vercel tidak menyediakan runtime PHP resmi; proyek ini memakai **Container
  runtime + FrankenPHP** sesuai panduan resmi Vercel.
- `backend/Dockerfile` tetap dipakai oleh `docker-compose` untuk development
  lokal (port 80 di dalam container).
- Jangan commit `DATABASE_URL`, token, atau password apa pun ke repository.