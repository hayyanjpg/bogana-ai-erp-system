import json
from typing import Any

from redis.asyncio import Redis

CACHE_PREFIX = "erp:parse:"
DEFAULT_TTL_SECONDS = 900


def create_redis_client(url: str) -> Redis | None:
    if not url:
        return None
    return Redis.from_url(url, decode_responses=True)


class ParseCache:
    def __init__(self, client: Redis | None, ttl_seconds: int = DEFAULT_TTL_SECONDS) -> None:
        self._client = client
        self._ttl = ttl_seconds

    async def get(self, key: str) -> dict[str, Any] | None:
        if self._client is None:
            return None
        try:
            raw = await self._client.get(CACHE_PREFIX + key)
        except Exception:
            return None
        if raw is None:
            return None
        try:
            return json.loads(raw)
        except json.JSONDecodeError:
            return None

    async def set(self, key: str, payload: dict[str, Any]) -> None:
        if self._client is None:
            return
        try:
            await self._client.set(CACHE_PREFIX + key, json.dumps(payload), ex=self._ttl)
        except Exception:
            return
