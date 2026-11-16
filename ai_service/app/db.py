from urllib.parse import parse_qsl, urlencode, urlsplit, urlunsplit

import asyncpg

UNSUPPORTED_DSN_PARAMS = frozenset({"channel_binding"})


def normalize_dsn(dsn: str) -> str:
    """Hapus parameter DSN yang tidak dikenal asyncpg (mis. channel_binding Neon)."""
    parts = urlsplit(dsn)
    if not parts.query:
        return dsn

    pairs = parse_qsl(parts.query, keep_blank_values=True)
    filtered = [(key, value) for key, value in pairs if key not in UNSUPPORTED_DSN_PARAMS]
    if len(filtered) == len(pairs):
        return dsn

    return urlunsplit((parts.scheme, parts.netloc, parts.path, urlencode(filtered), parts.fragment))


async def create_pool(dsn: str) -> asyncpg.Pool | None:
    if not dsn:
        return None
    return await asyncpg.create_pool(dsn=normalize_dsn(dsn), min_size=1, max_size=5)


async def close_pool(pool: asyncpg.Pool | None) -> None:
    if pool is not None:
        await pool.close()
