import asyncpg


async def create_pool(dsn: str) -> asyncpg.Pool | None:
    if not dsn:
        return None
    return await asyncpg.create_pool(dsn=dsn, min_size=1, max_size=5)


async def close_pool(pool: asyncpg.Pool | None) -> None:
    if pool is not None:
        await pool.close()
