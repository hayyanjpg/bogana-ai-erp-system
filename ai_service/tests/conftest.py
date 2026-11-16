from datetime import date
from typing import Any

import pytest
from fastapi.testclient import TestClient

from app.deps import (
    get_dashboard_cache,
    get_dashboard_repo,
    get_forecast_repo,
    get_menu_repo,
    get_order_repo,
    get_parse_cache,
    get_pg_pool,
    get_redis_client,
    get_stock_cache,
    get_stock_repo,
)
from app.domain import (
    AllocationResult,
    DashboardData,
    ForecastRow,
    Ingredient,
    OrderSnapshot,
    ShortOrder,
    TopMenu,
)
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

DASHBOARD_DATA = DashboardData(
    date=date(2026, 11, 16),
    status_counts={"fulfilled": 1, "short": 1, "parsed": 1},
    revenue=945000.0,
    short_orders=(
        ShortOrder(
            id=2,
            order_code="ORD-20261115-002",
            customer_name="Katering Dapur Ibu Hana",
        ),
    ),
    top_menus=(TopMenu(menu_sku="MNU-NBG-01", menu_name="Nasi Bogana Komplit", ordered_qty=40.0),),
)

FORECAST_ROWS = (
    ForecastRow(
        menu_id=1,
        menu_sku="MNU-NBG-01",
        menu_name="Nasi Bogana Komplit",
        forecast_date=date(2026, 11, 16),
        forecast_qty=46.0,
        confidence=0.82,
    ),
    ForecastRow(
        menu_id=2,
        menu_sku="MNU-NBG-02",
        menu_name="Nasi Bogana Ayam Kecap",
        forecast_date=date(2026, 11, 16),
        forecast_qty=38.0,
        confidence=0.76,
    ),
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


class FakeStockRepository:
    def __init__(self, ingredients: tuple[Ingredient, ...]) -> None:
        self._ingredients = list(ingredients)
        self.restock_calls: list[tuple[str, float]] = []

    async def list_all(self) -> tuple[Ingredient, ...]:
        return tuple(self._ingredients)

    async def list_menu_skus(self, ingredient_id: int) -> list[str]:
        return MENU_SKUS_BY_INGREDIENT.get(ingredient_id, [])

    async def restock(self, sku: str, quantity: float):
        from app.domain import RestockResult
        self.restock_calls.append((sku, quantity))
        for i, ing in enumerate(self._ingredients):
            if ing.sku == sku:
                stock = ing.stock_on_hand + quantity
                rop = ing.reorder_point
                status = "out" if stock <= 0 else "low" if stock <= rop else "ok"
                self._ingredients[i] = Ingredient(
                    id=ing.id, sku=ing.sku, name=ing.name, unit=ing.unit,
                    stock_on_hand=stock, reorder_point=ing.reorder_point,
                    unit_cost=ing.unit_cost, category=ing.category,
                )
                return RestockResult(sku=ing.sku, name=ing.name, unit=ing.unit, stock_on_hand=stock, status=status)
        from app.errors import NotFoundError
        raise NotFoundError(f"Ingredient {sku} tidak ditemukan")


class FakeOrderRepository:
    def __init__(self) -> None:
        self.snapshot: OrderSnapshot | None = None
        self.allocation: AllocationResult | None = None
        self.allocate_calls: list[tuple[int, int | None]] = []
        self.saved = None
        self.detail = None
        self.orders = []
        self.confirm_result = None
        self.confirm_calls: list[int] = []
        self.qc_result = None
        self.qc_calls = []

    async def get_snapshot(self, order_id: int) -> OrderSnapshot | None:
        return self.snapshot

    async def allocate(
        self,
        order_id: int,
        expected_version: int | None,
    ) -> AllocationResult:
        self.allocate_calls.append((order_id, expected_version))
        if self.allocation is None:
            raise AssertionError("allocation belum disiapkan pada fake repository")
        return self.allocation

    async def save_from_parse(self, payload, items):
        from app.domain import SavedOrder
        from datetime import date
        if self.saved is not None:
            return self.saved
        d = payload.order_date or date.today()
        return SavedOrder(99, "ORD-TEST-001", "parsed", 1, d, len(items), 0.0, True)

    async def list_orders(self, status=None, date_value=None, limit=20):
        return self.orders

    async def get_detail(self, order_id: int):
        return self.detail

    async def confirm_or_short(self, order_id: int):
        self.confirm_calls.append(order_id)
        if self.confirm_result is None:
            raise AssertionError("confirm_result belum disiapkan pada fake repository")
        return self.confirm_result

    async def record_qc_pass(self, order_id: int, item_id: int, checked_by: str, note):
        self.qc_calls.append((order_id, item_id, checked_by, note))
        if self.qc_result is None:
            raise AssertionError("qc_result belum disiapkan pada fake repository")
        return self.qc_result


class FakeDashboardRepository:
    def __init__(self, data: DashboardData) -> None:
        self.data = data
        self.calls: list[date] = []

    async def fetch(self, date_value: date) -> DashboardData:
        self.calls.append(date_value)
        return self.data


class FakeForecastRepository:
    def __init__(
        self,
        model_version: str,
        rows: tuple[ForecastRow, ...],
    ) -> None:
        self.model_version = model_version
        self.rows = rows
        self.calls: list[tuple[date, date, int | None]] = []

    async def list(
        self,
        from_date: date,
        to_date: date,
        menu_id: int | None,
    ) -> tuple[str, list[ForecastRow]]:
        self.calls.append((from_date, to_date, menu_id))
        return self.model_version, list(self.rows)


class FakeCache:
    def __init__(self) -> None:
        self.store: dict[str, Any] = {}

    async def get(self, key: str) -> Any | None:
        return self.store.get(key)

    async def set(self, key: str, payload: Any) -> None:
        self.store[key] = payload

    async def delete(self, key: str) -> None:
        self.store.pop(key, None)


@pytest.fixture
def cache() -> FakeCache:
    return FakeCache()


@pytest.fixture
def stock_cache() -> FakeCache:
    return FakeCache()


@pytest.fixture
def order_repo() -> FakeOrderRepository:
    return FakeOrderRepository()


@pytest.fixture
def dashboard_repo() -> FakeDashboardRepository:
    return FakeDashboardRepository(DASHBOARD_DATA)


@pytest.fixture
def forecast_repo() -> FakeForecastRepository:
    return FakeForecastRepository("bogana-forecast-v1", FORECAST_ROWS)


@pytest.fixture
def client(
    cache: FakeCache,
    stock_cache: FakeCache,
    order_repo: FakeOrderRepository,
    dashboard_repo: FakeDashboardRepository,
    forecast_repo: FakeForecastRepository,
) -> TestClient:
    app = create_app()
    app.dependency_overrides[get_pg_pool] = lambda: FakePool()
    app.dependency_overrides[get_redis_client] = lambda: FakeRedis()
    app.dependency_overrides[get_menu_repo] = lambda: FakeMenuRepository(MENUS)
    app.dependency_overrides[get_parse_cache] = lambda: cache
    app.dependency_overrides[get_stock_repo] = lambda: FakeStockRepository(INGREDIENTS)
    app.dependency_overrides[get_stock_cache] = lambda: stock_cache
    app.dependency_overrides[get_dashboard_cache] = lambda: stock_cache
    app.dependency_overrides[get_order_repo] = lambda: order_repo
    app.dependency_overrides[get_dashboard_repo] = lambda: dashboard_repo
    app.dependency_overrides[get_forecast_repo] = lambda: forecast_repo
    with TestClient(app) as test_client:
        yield test_client
