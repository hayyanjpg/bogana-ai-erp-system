from typing import Any

import pytest
from fastapi.testclient import TestClient

from app.deps import (
    get_menu_repo,
    get_parse_cache,
    get_pg_pool,
    get_redis_client,
    get_stock_cache,
    get_stock_repo,
)
from app.domain import Ingredient
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

INGREDIENTS = (
    Ingredient(
        id=1,
        sku="ING-RS-01",
        name="Beras Pandan Wangi",
        unit="kg",
        stock_on_hand=850.0,
        reorder_point=100.0,
        unit_cost=14000.0,
        category="sembako",
    ),
    Ingredient(
        id=2,
        sku="ING-AY-01",
        name="Ayam Fillet",
        unit="kg",
        stock_on_hand=120.0,
        reorder_point=25.0,
        unit_cost=38000.0,
        category="protein",
    ),
    Ingredient(
        id=7,
        sku="ING-TL-01",
        name="Telur Ayam",
        unit="pcs",
        stock_on_hand=720.0,
        reorder_point=150.0,
        unit_cost=2500.0,
        category="protein",
    ),
    Ingredient(
        id=13,
        sku="ING-KL-02",
        name="Kelapa Parut Lama",
        unit="kg",
        stock_on_hand=0.0,
        reorder_point=1.0,
        unit_cost=20000.0,
        category="bahan",
    ),
    Ingredient(
        id=14,
        sku="ING-DJ-02",
        name="Daun Jeruk Kering",
        unit="kg",
        stock_on_hand=0.5,
        reorder_point=1.0,
        unit_cost=45000.0,
        category="bumbu",
    ),
)

MENU_SKUS_BY_INGREDIENT = {
    2: ["MNU-NBG-01", "MNU-NBG-02"],
}


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


class FakeStockRepository:
    def __init__(self, ingredients: tuple[Ingredient, ...]) -> None:
        self._ingredients = ingredients

    async def list_all(self) -> tuple[Ingredient, ...]:
        return self._ingredients

    async def list_menu_skus(self, ingredient_id: int) -> list[str]:
        return MENU_SKUS_BY_INGREDIENT.get(ingredient_id, [])


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
def stock_cache() -> FakeCache:
    return FakeCache()


@pytest.fixture
def client(cache: FakeCache, stock_cache: FakeCache) -> TestClient:
    app = create_app()
    app.dependency_overrides[get_pg_pool] = lambda: FakePool()
    app.dependency_overrides[get_redis_client] = lambda: FakeRedis()
    app.dependency_overrides[get_menu_repo] = lambda: FakeMenuRepository(MENUS)
    app.dependency_overrides[get_parse_cache] = lambda: cache
    app.dependency_overrides[get_stock_repo] = lambda: FakeStockRepository(INGREDIENTS)
    app.dependency_overrides[get_stock_cache] = lambda: stock_cache
    with TestClient(app) as test_client:
        yield test_client
