import asyncio

import pytest

from app.errors import BusinessRuleError, DependencyUnavailableError, NotFoundError
from app.repositories import StockRepository


class _FakeTransaction:
    async def __aenter__(self) -> None:
        return None

    async def __aexit__(self, exc_type, exc, tb) -> bool:
        return False


class _FakeConnection:
    def __init__(self, rows: dict[str, dict[str, object]]) -> None:
        self._rows = rows
        self.calls: list[tuple[str, tuple[object, ...]]] = []

    def transaction(self) -> _FakeTransaction:
        return _FakeTransaction()

    async def fetchrow(self, query: str, *args: object) -> dict[str, object] | None:
        self.calls.append((query, args))
        sku, quantity = args
        row = self._rows.get(str(sku))
        if row is None:
            return None
        updated = {
            "sku": row["sku"],
            "name": row["name"],
            "unit": row["unit"],
            "stock_on_hand": float(row["stock_on_hand"]) + float(quantity),
            "reorder_point": row["reorder_point"],
        }
        self._rows[str(sku)] = updated
        return updated


class _FakeAcquire:
    def __init__(self, connection: _FakeConnection) -> None:
        self._connection = connection

    async def __aenter__(self) -> _FakeConnection:
        return self._connection

    async def __aexit__(self, exc_type, exc, tb) -> bool:
        return False


class _FakePool:
    def __init__(self, connection: _FakeConnection) -> None:
        self._connection = connection
        self.acquire_calls = 0

    def acquire(self) -> _FakeAcquire:
        self.acquire_calls += 1
        return _FakeAcquire(self._connection)


def _ingredient_row(sku: str = "ING-RS-01") -> dict[str, object]:
    return {
        "sku": sku,
        "name": "Beras Pandan Wangi",
        "unit": "kg",
        "stock_on_hand": 850.0,
        "reorder_point": 100.0,
    }


def test_restock_updates_stock_through_real_repository() -> None:
    connection = _FakeConnection({"ING-RS-01": _ingredient_row()})
    pool = _FakePool(connection)
    repository = StockRepository(pool)  # type: ignore[arg-type]

    result = asyncio.run(repository.restock("ING-RS-01", 0.01))

    assert result.sku == "ING-RS-01"
    assert result.name == "Beras Pandan Wangi"
    assert result.unit == "kg"
    assert result.stock_on_hand == pytest.approx(850.01)
    assert result.status == "ok"
    assert pool.acquire_calls == 1
    assert len(connection.calls) == 1
    assert "UPDATE ingredients" in connection.calls[0][0]
    assert connection.calls[0][1] == ("ING-RS-01", 0.01)


def test_restock_rejects_non_positive_quantity() -> None:
    connection = _FakeConnection({"ING-RS-01": _ingredient_row()})
    pool = _FakePool(connection)
    repository = StockRepository(pool)  # type: ignore[arg-type]

    with pytest.raises(BusinessRuleError):
        asyncio.run(repository.restock("ING-RS-01", 0))

    assert pool.acquire_calls == 0
    assert connection.calls == []


def test_restock_unknown_sku_raises_not_found() -> None:
    connection = _FakeConnection({})
    repository = StockRepository(_FakePool(connection))  # type: ignore[arg-type]

    with pytest.raises(NotFoundError):
        asyncio.run(repository.restock("ING-TIDAK-ADA", 1))


def test_restock_without_pool_raises_dependency_unavailable() -> None:
    repository = StockRepository(None)

    with pytest.raises(DependencyUnavailableError):
        asyncio.run(repository.restock("ING-RS-01", 1))
