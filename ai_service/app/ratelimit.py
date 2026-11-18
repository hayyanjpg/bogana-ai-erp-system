"""Pembatasan laju permintaan berbasis Redis.

Dashboard publik boleh menulis, jadi setiap IP mendapat kuota baca dan tulis
per jendela waktu. Penghitung disimpan di Redis supaya konsisten antar instance
serverless.

Prinsip:

- Hanya IP yang valid yang dipakai sebagai kunci.
- `X-Client-IP` hanya dipercaya bila permintaan membawa token internal yang sah
  (atau auth sedang dinonaktifkan untuk lokal). Tanpa itu, IP socket dipakai.
- Bila Redis tidak tersedia, permintaan dilewatkan (fail open) dan dicatat di
  log. Memblokir seluruh dashboard karena Redis down lebih buruk untuk demo.
"""

from __future__ import annotations

import ipaddress
import logging
import secrets
from dataclasses import dataclass

from fastapi import FastAPI, Request
from fastapi.responses import JSONResponse
from redis.asyncio import Redis

from app.config import Settings, get_settings

logger = logging.getLogger("bogana.ai_service.ratelimit")

SAFE_METHODS = frozenset({"GET", "HEAD", "OPTIONS"})

# Endpoint yang dipakai health check dan tidak menyentuh database.
UNLIMITED_PATHS = frozenset({"/", "/health"})

# INCR lalu pasang TTL hanya saat kunci baru dibuat, dalam satu round-trip.
_WINDOW_SCRIPT = """
local current = redis.call('INCR', KEYS[1])
if current == 1 then
  redis.call('EXPIRE', KEYS[1], ARGV[1])
end
local ttl = redis.call('TTL', KEYS[1])
return {current, ttl}
"""


@dataclass(frozen=True)
class RateLimitResult:
    limit: int
    used: int
    retry_after: int

    @property
    def allowed(self) -> bool:
        return self.used <= self.limit


class RateLimiter:
    def __init__(
        self,
        client: Redis | None,
        *,
        window_seconds: int,
        read_limit: int,
        write_limit: int,
        prefix: str = "bogana:rl:",
    ) -> None:
        self._client = client
        self._window = max(1, int(window_seconds))
        self._read_limit = max(0, int(read_limit))
        self._write_limit = max(0, int(write_limit))
        self._prefix = prefix
        self._warned = False

    def limit_for(self, method: str) -> int:
        return self._read_limit if method.upper() in SAFE_METHODS else self._write_limit

    async def hit(self, client_ip: str, method: str) -> RateLimitResult | None:
        """Catat satu permintaan; `None` berarti pembatasan tidak aktif."""
        if self._client is None:
            return None

        limit = self.limit_for(method)
        if limit <= 0:
            return None

        scope = "read" if method.upper() in SAFE_METHODS else "write"
        key = f"{self._prefix}{scope}:{client_ip}"
        try:
            used, ttl = await self._client.eval(_WINDOW_SCRIPT, 1, key, self._window)
        except Exception:
            if not self._warned:
                self._warned = True
                logger.warning(
                    "Redis rate limiter tidak tersedia; permintaan dilewatkan",
                    exc_info=True,
                )
            return None

        retry_after = int(ttl) if isinstance(ttl, int) and ttl > 0 else self._window
        return RateLimitResult(
            limit=limit,
            used=int(used),
            retry_after=max(1, min(retry_after, self._window)),
        )


def resolve_client_ip(request: Request, *, trust_forwarded: bool) -> str:
    """IP klien untuk kunci rate limit, tervalidasi bila berasal dari header."""
    if trust_forwarded:
        forwarded = request.headers.get("x-client-ip", "").strip()
        if forwarded:
            try:
                return ipaddress.ip_address(forwarded).compressed
            except ValueError:
                pass

    if request.client is not None and request.client.host:
        return request.client.host

    return "unknown"


def forwarded_ip_is_trusted(request: Request, settings: Settings) -> bool:
    if settings.ai_service_auth_disabled:
        return True

    provided = request.headers.get("x-internal-token")
    if not provided:
        return False

    try:
        return secrets.compare_digest(provided, settings.ai_service_token)
    except TypeError:
        return False


def _rate_limit_headers(result: RateLimitResult) -> dict[str, str]:
    return {
        "X-RateLimit-Limit": str(result.limit),
        "X-RateLimit-Remaining": str(max(0, result.limit - result.used)),
    }


def install_rate_limit(app: FastAPI) -> None:
    @app.middleware("http")
    async def rate_limit_middleware(request: Request, call_next):
        settings = get_settings()
        limiter: RateLimiter | None = getattr(request.app.state, "rate_limiter", None)
        if (
            limiter is None
            or not settings.rate_limit_enabled
            or request.url.path in UNLIMITED_PATHS
        ):
            return await call_next(request)

        client_ip = resolve_client_ip(
            request,
            trust_forwarded=forwarded_ip_is_trusted(request, settings),
        )
        result = await limiter.hit(client_ip, request.method)

        if result is not None and not result.allowed:
            headers = _rate_limit_headers(result)
            headers["Retry-After"] = str(result.retry_after)
            return JSONResponse(
                status_code=429,
                content={
                    "error": {
                        "code": "RATE_LIMITED",
                        "message": (
                            "Terlalu banyak permintaan dari alamat ini. "
                            f"Coba lagi dalam {result.retry_after} detik."
                        ),
                        "details": [
                            {
                                "field": "rate_limit",
                                "issue": f"retry_after_seconds={result.retry_after}",
                            }
                        ],
                    }
                },
                headers=headers,
            )

        response = await call_next(request)
        if result is not None:
            response.headers.update(_rate_limit_headers(result))
        return response
