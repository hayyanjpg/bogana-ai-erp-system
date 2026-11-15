import json
from typing import Any

from redis.asyncio import Redis

DEFAULT_TTL_SECONDS = 300


def create_redis_client(url: str) -> Redis | None:
    if not url:
        return None
    return Redis.from_url(url, decode_responses=True)


class JsonCache:
    def __init__(
        self,
        client: Redis | None,
        prefix: str,
        ttl_seconds: int = DEFAULT_TTL_SECONDS,
    ) -> None:
        self._client = client
        self._prefix = prefix
        self._ttl = ttl_seconds

    async def get(self, key: str) -> Any | None:
        if self._client is None:
            return None
        try:
            raw = await self._client.get(self._prefix + key)
        except Exception:
            return None
        if raw is None:
            return None
        try:
            return json.loads(raw)
        except json.JSONDecodeError:
            return None

    async def set(self, key: str, payload: Any) -> None:
        if self._client is None:
            return
        try:
            await self._client.set(
                self._prefix + key,
                json.dumps(payload, default=str),
                ex=self._ttl,
            )
        except Exception:
            return
