from typing import Any

import pytest
from fastapi.testclient import TestClient

from app.deps import get_menu_repo, get_parse_cache, get_pg_pool, get_redis_client
from app.main import create_app
from app.services.parser import MenuLite

MENUS = (
    MenuLite(sku="MNU-NBG-01", name="Nasi Bogana Komplit", base_price=18000.0),
    MenuLite(sku="MNU-NBG-02", name="Nasi Bogana Ayam Kecap", base_price=15000.0),
    MenuLite(sku="MNU-SOT-01", name="Soto Betawi Bogana", base_price=22000.0),
    MenuLite(sku="MNU-RND-01", name="Rendang Sapi Bogana", base_price=30000.0),
    MenuLite(sku="MNU-KLP-01", name="Klepon Bogana Isi Gula Merah", base_price=12000.0),
    MenuLite(sku="MNU-TLB-01", name="Nasi Telur Balado", base_price=13000.0),
)


class FakePool:
    async def fetchval(self, query: str) -> int:
        return 1


class FakeRedis:
    async def ping(self) -> bool:
        return True


class FakeMenuRepository:
    def __init__(self, menus: tuple[MenuLite, ...]) -> None:
        self._menus = menus

    async def list_active(self) -> tuple[MenuLite, ...]:
        return self._menus


class FakeCache:
    def __init__(self) -> None:
        self.store: dict[str, Any] = {}

    async def get(self, key: str) -> Any | None:
        return self.store.get(key)

    async def set(self, key: str, payload: Any) -> None:
        self.store[key] = payload


@pytest.fixture
def cache() -> FakeCache:
    return FakeCache()


@pytest.fixture
def client(cache: FakeCache) -> TestClient:
    app = create_app()
    app.dependency_overrides[get_pg_pool] = lambda: FakePool()
    app.dependency_overrides[get_redis_client] = lambda: FakeRedis()
    app.dependency_overrides[get_menu_repo] = lambda: FakeMenuRepository(MENUS)
    app.dependency_overrides[get_parse_cache] = lambda: cache
    with TestClient(app) as test_client:
        yield test_client
