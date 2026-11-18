"""Kembalikan database ke kondisi demo awal dengan menjalankan ulang schema.sql.

Seed di `database/schema.sql` memakai tanggal relatif terhadap `CURRENT_DATE`,
jadi order dan forecast langsung terisi pada hari script ini dijalankan.

Contoh pemakaian dari root repositori:

    DATABASE_URL=postgresql://user:pass@host/db python scripts/reset_demo.py --yes

PERINGATAN: semua tabel di database tujuan akan dihapus lalu dibuat ulang.
Hanya jalankan untuk database demo/development, bukan data produksi nyata.
"""

from __future__ import annotations

import asyncio
import os
import sys
from pathlib import Path

import asyncpg

ROOT = Path(__file__).resolve().parents[1]
SCHEMA_PATH = ROOT / "database" / "schema.sql"

# Pakai helper normalisasi DSN yang sama dengan AI service agar URL Neon
# (mis. `?channel_binding=require`) bisa dibuka asyncpg.
sys.path.insert(0, str(ROOT / "ai_service"))
from app.db import normalize_dsn


async def reset(database_url: str) -> tuple[int, int]:
    schema_sql = SCHEMA_PATH.read_text(encoding="utf-8")
    connection = await asyncpg.connect(dsn=normalize_dsn(database_url))
    try:
        await connection.execute(schema_sql)
        order_count = await connection.fetchval("SELECT count(*) FROM b2b_orders")
        forecast_count = await connection.fetchval(
            "SELECT count(*) FROM demand_forecasts"
        )
    finally:
        await connection.close()
    return int(order_count), int(forecast_count)


def main() -> int:
    database_url = os.getenv("DATABASE_URL", "").strip()
    if not database_url:
        print("DATABASE_URL belum diisi. Contoh:", file=sys.stderr)
        print(
            "  DATABASE_URL=postgresql://user:pass@host/db python scripts/reset_demo.py --yes",
            file=sys.stderr,
        )
        return 1

    if "--yes" not in sys.argv[1:]:
        print(
            "Script ini menghapus dan mengisi ulang seluruh tabel. "
            "Tambahkan --yes bila database tujuan memang database demo.",
            file=sys.stderr,
        )
        return 2

    orders, forecasts = asyncio.run(reset(database_url))
    print(f"Reset demo selesai: {orders} order dan {forecasts} baris forecast dibuat.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
