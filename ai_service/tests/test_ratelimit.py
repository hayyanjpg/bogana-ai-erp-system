import asyncio
import os
import uuid

import pytest
from fastapi.testclient import TestClient
from starlette.requests import Request

from app.cache import create_redis_client
from app.config import Settings
from app.ratelimit import (
    RateLimiter,
    RateLimitResult,
    forwarded_ip_is_trusted,
    resolve_client_ip,
)

PARSE_PAYLOAD = {
    "raw_text": "40 nasi bogana komplit",
    "channel": "whatsapp",
    "customer_name": "QA Rate Limit",
}


class FakeRateRedis:
    def __init__(self) -> None:
        self.counters: dict[str, int] = {}

    async def eval(self, script: str, numkeys: int, key: str, window: int):
        value = self.counters.get(key, 0) + 1
        self.counters[key] = value
        return [value, int(window)]


class FailingRedis:
    async def eval(self, script: str, numkeys: int, key: str, window: int):
        raise RuntimeError("redis down")


def make_request(
    headers: dict[str, str] | None = None,
    client: tuple[str, int] | None = ("192.0.2.10", 1234),
) -> Request:
    raw_headers = [
        (name.lower().encode("latin-1"), value.encode("latin-1"))
        for name, value in (headers or {}).items()
    ]
    scope = {
        "type": "http",
        "http_version": "1.1",
        "method": "GET",
        "scheme": "http",
        "path": "/",
        "raw_path": b"/",
        "query_string": b"",
        "headers": raw_headers,
        "client": client,
        "server": ("testserver", 80),
    }
    return Request(scope)


def test_hit_memakai_kunci_terpisah_untuk_baca_dan_tulis() -> None:
    redis = FakeRateRedis()
    limiter = RateLimiter(redis, window_seconds=60, read_limit=2, write_limit=1)

    tulis = asyncio.run(limiter.hit("203.0.113.1", "POST"))
    assert tulis is not None
    assert tulis.allowed
    assert (tulis.limit, tulis.used, tulis.retry_after) == (1, 1, 60)

    tulis_kedua = asyncio.run(limiter.hit("203.0.113.1", "POST"))
    assert tulis_kedua is not None
    assert not tulis_kedua.allowed

    baca = asyncio.run(limiter.hit("203.0.113.1", "GET"))
    assert baca is not None
    assert baca.allowed
    assert baca.used == 1


def test_hit_memisahkan_kuota_antar_ip() -> None:
    redis = FakeRateRedis()
    limiter = RateLimiter(redis, window_seconds=60, read_limit=1, write_limit=1)

    assert asyncio.run(limiter.hit("203.0.113.1", "POST")).allowed
    assert not asyncio.run(limiter.hit("203.0.113.1", "POST")).allowed
    assert asyncio.run(limiter.hit("203.0.113.2", "POST")).allowed


def test_limiter_tanpa_redis_dilewatkan() -> None:
    limiter = RateLimiter(None, window_seconds=60, read_limit=1, write_limit=1)

    assert asyncio.run(limiter.hit("203.0.113.1", "POST")) is None


def test_kuota_nol_mematikan_scope() -> None:
    limiter = RateLimiter(FakeRateRedis(), window_seconds=60, read_limit=0, write_limit=5)

    assert asyncio.run(limiter.hit("203.0.113.1", "GET")) is None
    assert asyncio.run(limiter.hit("203.0.113.1", "POST")) is not None


def test_redis_error_fail_open() -> None:
    limiter = RateLimiter(FailingRedis(), window_seconds=60, read_limit=1, write_limit=1)

    assert asyncio.run(limiter.hit("203.0.113.1", "POST")) is None


def test_lua_script_valid_pada_redis_nyata() -> None:
    url = os.environ.get("REDIS_URL", "")
    if not url:
        pytest.skip("REDIS_URL tidak diisi")

    async def scenario() -> tuple[RateLimitResult | None, RateLimitResult | None]:
        client = create_redis_client(url)
        assert client is not None
        try:
            try:
                await client.ping()
            except Exception:
                pytest.skip("Redis lokal tidak dapat dihubungi")

            limiter = RateLimiter(
                client,
                window_seconds=60,
                read_limit=1,
                write_limit=1,
                prefix=f"bogana:test:{uuid.uuid4().hex}:",
            )
            pertama = await limiter.hit("203.0.113.99", "POST")
            kedua = await limiter.hit("203.0.113.99", "POST")
        finally:
            await client.aclose()
        return pertama, kedua

    pertama, kedua = asyncio.run(scenario())
    assert pertama is not None and pertama.allowed
    assert kedua is not None and not kedua.allowed


def test_resolve_client_ip_memakai_header_bila_dipercaya() -> None:
    request = make_request({"X-Client-IP": "203.0.113.7"})

    assert resolve_client_ip(request, trust_forwarded=True) == "203.0.113.7"


def test_resolve_client_ip_menolak_header_tidak_valid() -> None:
    request = make_request({"X-Client-IP": "bukan-ip"})

    assert resolve_client_ip(request, trust_forwarded=True) == "192.0.2.10"


def test_resolve_client_ip_mengabaikan_header_bila_tidak_dipercaya() -> None:
    request = make_request({"X-Client-IP": "203.0.113.7"})

    assert resolve_client_ip(request, trust_forwarded=False) == "192.0.2.10"


def test_resolve_client_ip_tanpa_client() -> None:
    request = make_request(client=None)

    assert resolve_client_ip(request, trust_forwarded=False) == "unknown"


def test_forwarded_ip_hanya_dipercaya_dengan_token_internal() -> None:
    settings = Settings(
        ai_service_auth_disabled=False,
        ai_service_token="rahasia-internal",
    )

    tanpa_token = make_request({"X-Client-IP": "203.0.113.7"})
    assert not forwarded_ip_is_trusted(tanpa_token, settings)

    token_salah = make_request({"X-Client-IP": "203.0.113.7", "X-Internal-Token": "salah"})
    assert not forwarded_ip_is_trusted(token_salah, settings)

    token_benar = make_request(
        {"X-Client-IP": "203.0.113.7", "X-Internal-Token": "rahasia-internal"}
    )
    assert forwarded_ip_is_trusted(token_benar, settings)


def test_api_membatasi_tulis_per_ip(app) -> None:
    with TestClient(app) as test_client:
        test_client.app.state.rate_limiter = RateLimiter(
            FakeRateRedis(), window_seconds=60, read_limit=10, write_limit=2
        )
        ip_a = {"X-Client-IP": "203.0.113.50"}
        ip_b = {"X-Client-IP": "203.0.113.51"}

        assert (
            test_client.post("/v1/parse-order", json=PARSE_PAYLOAD, headers=ip_a).status_code == 200
        )
        assert (
            test_client.post("/v1/parse-order", json=PARSE_PAYLOAD, headers=ip_a).status_code == 200
        )

        limited = test_client.post("/v1/parse-order", json=PARSE_PAYLOAD, headers=ip_a)
        assert limited.status_code == 429
        assert limited.headers["Retry-After"] == "60"
        assert limited.headers["X-RateLimit-Limit"] == "2"
        assert limited.headers["X-RateLimit-Remaining"] == "0"

        error = limited.json()["error"]
        assert error["code"] == "RATE_LIMITED"
        assert "Coba lagi" in error["message"]

        assert (
            test_client.post("/v1/parse-order", json=PARSE_PAYLOAD, headers=ip_b).status_code == 200
        )


def test_api_membatasi_baca_dan_mengecualikan_health(app) -> None:
    with TestClient(app) as test_client:
        test_client.app.state.rate_limiter = RateLimiter(
            FakeRateRedis(), window_seconds=60, read_limit=1, write_limit=10
        )

        for _ in range(3):
            assert test_client.get("/health").status_code == 200

        assert test_client.get("/v1/stocks").status_code == 200
        assert test_client.get("/v1/stocks").status_code == 429
