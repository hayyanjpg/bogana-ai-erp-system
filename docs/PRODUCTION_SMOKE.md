# Production Smoke Test

Skrip `scripts/production_smoke.py` memverifikasi dashboard PHP dan AI service
FastAPI setelah deploy. Skrip ini **read-only secara default**; uji tulis hanya
berjalan bila diaktifkan secara eksplisit.

## Cakupan

- Identitas AI service, `/health`, `/docs`, dan `/openapi.json`.
- Halaman PHP: dashboard, orders, stocks, forecasts, health.
- Static asset `/assets/app.css` dan `/healthz.php`.
- Halaman 404.
- Alur CSRF dashboard untuk parse order dan halaman forecast.
- Validasi error QC (`checked_by` terlalu panjang) — tidak menulis data.
- Uji restock opsional dengan kuantitas kecil pada SKU tertentu.
- Bila dashboard berjalan dengan login, halaman terproteksi menjadi `SKIP`
  tanpa kredensial smoke (bukan `FAIL`).

## Keamanan

- Tidak ada request `DELETE`.
- Parse order tidak menyimpan order.
- Validasi QC sengaja memakai payload tidak valid sehingga tidak menulis
  `qc_logs`.
- Restock hanya berjalan dengan `SMOKE_ALLOW_WRITE=1` dan menambah stok sebesar
  `SMOKE_QTY` (default `0.01`) pada `SMOKE_SKU` (default `ING-RS-01`).
- Jangan pernah mengisi `SMOKE_TOKEN` di file yang di-commit.

> Dashboard default berjalan **publik penuh**, jadi skrip ini tetap tidak
> menulis kecuali `SMOKE_ALLOW_WRITE=1`.
>
> AI service membatasi laju per IP (default 120 baca / 30 tulis per menit).
> Jalankan smoke test maksimal sekali per menit; bila muncul `429
> RATE_LIMITED`, tunggu sesuai header `Retry-After` lalu ulangi.
>
> Bila dashboard memakai login (`DASHBOARD_ACCESS_MODE=required`), isi
> `SMOKE_DASHBOARD_USER` dan `SMOKE_DASHBOARD_PASSWORD`. Tanpa keduanya,
> halaman terproteksi dilaporkan `SKIP` (bukan `FAIL`), sehingga tidak ada
> pemeriksaan palsu yang dianggap lulus.

## Pemakaian

```bash
# Read-only (aman dijalankan kapan saja)
SMOKE_WEB_URL="https://<dashboard>.vercel.app" \
SMOKE_API_URL="https://<ai-service>.vercel.app" \
python scripts/production_smoke.py

# Dashboard ber-login (uji halaman terproteksi)
SMOKE_WEB_URL="https://<dashboard>.vercel.app" \
SMOKE_API_URL="https://<ai-service>.vercel.app" \
SMOKE_DASHBOARD_USER="admin" \
SMOKE_DASHBOARD_PASSWORD="<password>" \
python scripts/production_smoke.py

# Dengan uji API langsung (butuh token internal)
SMOKE_WEB_URL="https://<dashboard>.vercel.app" \
SMOKE_API_URL="https://<ai-service>.vercel.app" \
SMOKE_TOKEN="<token>" \
python scripts/production_smoke.py

# Dengan uji restock (opt-in, mengubah stok production)
SMOKE_WEB_URL="https://<dashboard>.vercel.app" \
SMOKE_API_URL="https://<ai-service>.vercel.app" \
SMOKE_ALLOW_WRITE=1 \
SMOKE_DASHBOARD_USER="admin" \
SMOKE_DASHBOARD_PASSWORD="<password>" \
SMOKE_SKU="ING-RS-01" \
SMOKE_QTY="0.01" \
python scripts/production_smoke.py
```

PowerShell:

```powershell
$env:SMOKE_WEB_URL = "https://<dashboard>.vercel.app"
$env:SMOKE_API_URL = "https://<ai-service>.vercel.app"
$env:SMOKE_DASHBOARD_USER = "admin"
$env:SMOKE_DASHBOARD_PASSWORD = "<password>"
python scripts/production_smoke.py
```

## Exit code

- `0` — semua pemeriksaan PASS (SKIP tidak dihitung gagal).
- `1` — ada pemeriksaan FAIL.
- `2` — konfigurasi environment belum lengkap.

## Checklist setelah deploy

1. Jalankan smoke test read-only (tanpa `SMOKE_ALLOW_WRITE`).
2. Jalankan smoke test dengan `SMOKE_ALLOW_WRITE=1` pada SKU uji, setelah
   menunggu jendela rate limit bila perlu.
3. Pastikan `/health` menunjukkan `postgres: ok` dan `redis: ok`.
4. Cek log Vercel untuk error 500 setelah smoke test.
5. Simpan output smoke test sebagai bukti rilis.
