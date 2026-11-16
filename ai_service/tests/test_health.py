from app.deps import get_pg_pool, get_redis_client


def test_root_returns_service_info(client):
    response = client.get("/")

    assert response.status_code == 200
    body = response.json()
    assert body["service"] == "Bogana AI Service"
    assert body["version"] == "1.0.0"
    assert body["docs"] == "/docs"


def test_health_ok(client):
    response = client.get("/health")

    assert response.status_code == 200
    body = response.json()
    assert body["status"] == "ok"
    assert body["checks"]["postgres"] == "ok"
    assert body["checks"]["redis"] == "ok"
    assert body["version"] == "1.0.0"


def test_health_degraded_when_redis_down(client):
    class BrokenRedis:
        async def ping(self) -> bool:
            raise RuntimeError("redis down")

    client.app.dependency_overrides[get_redis_client] = lambda: BrokenRedis()

    response = client.get("/health")

    assert response.status_code == 200
    assert response.json()["status"] == "degraded"


def test_health_down_when_postgres_missing(client):
    client.app.dependency_overrides[get_pg_pool] = lambda: None

    response = client.get("/health")

    assert response.status_code == 503
    assert response.json()["status"] == "down"


def test_unknown_route_returns_error_envelope(client):
    response = client.get("/v1/tidak-ada")

    assert response.status_code == 404
    assert response.json()["error"]["code"] == "NOT_FOUND"
