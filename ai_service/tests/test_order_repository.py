import asyncio
from datetime import UTC, datetime

import pytest

from app.errors import ConflictError, NotFoundError
from app.repositories import OrderRepository


class _FakeTransaction:
    async def __aenter__(self) -> None:
        return None

    async def __aexit__(self, exc_type, exc, tb) -> bool:
        return False


class _CancelConnection:
    def __init__(self, status: str) -> None:
        self.status = status
        self.fetchrow_calls: list[tuple[str, tuple[object, ...]]] = []
        self.execute_calls: list[tuple[str, tuple[object, ...]]] = []

    def transaction(self) -> _FakeTransaction:
        return _FakeTransaction()

    async def fetchrow(self, query: str, *args: object) -> dict[str, object] | None:
        self.fetchrow_calls.append((query, args))
        if "SELECT id, order_code, status FROM b2b_orders" in query:
            if self.status == "missing":
                return None
            return {"id": 2, "order_code": "ORD-TEST-002", "status": self.status}
        if "UPDATE b2b_orders" in query:
            self.status = "cancelled"
            return {
                "status": "cancelled",
                "version": 2,
                "updated_at": datetime(2026, 11, 18, 2, 30, tzinfo=UTC),
            }
        raise AssertionError(f"query tidak dikenal: {query}")

    async def execute(self, query: str, *args: object) -> str:
        self.execute_calls.append((query, args))
        return "UPDATE 2"


class _FakeAcquire:
    def __init__(self, connection: _CancelConnection) -> None:
        self._connection = connection

    async def __aenter__(self) -> _CancelConnection:
        return self._connection

    async def __aexit__(self, exc_type, exc, tb) -> bool:
        return False


class _FakePool:
    def __init__(self, connection: _CancelConnection) -> None:
        self._connection = connection

    def acquire(self) -> _FakeAcquire:
        return _FakeAcquire(self._connection)


def test_cancel_repository_marks_header_and_items() -> None:
    connection = _CancelConnection("confirmed")
    repository = OrderRepository(_FakePool(connection))  # type: ignore[arg-type]

    result = asyncio.run(repository.cancel(2))

    assert result.order_id == 2
    assert result.status == "cancelled"
    assert result.version == 2
    assert connection.status == "cancelled"
    assert len(connection.execute_calls) == 1
    assert "UPDATE order_items SET status='cancelled'" in connection.execute_calls[0][0]
    assert connection.execute_calls[0][1] == (2,)


def test_cancel_repository_rejects_fulfilled_order() -> None:
    connection = _CancelConnection("fulfilled")
    repository = OrderRepository(_FakePool(connection))  # type: ignore[arg-type]

    with pytest.raises(ConflictError):
        asyncio.run(repository.cancel(2))

    assert connection.execute_calls == []


def test_cancel_repository_rejects_already_cancelled_order() -> None:
    connection = _CancelConnection("cancelled")
    repository = OrderRepository(_FakePool(connection))  # type: ignore[arg-type]

    with pytest.raises(ConflictError):
        asyncio.run(repository.cancel(2))

    assert connection.execute_calls == []


def test_cancel_repository_unknown_order_raises_not_found() -> None:
    connection = _CancelConnection("missing")
    repository = OrderRepository(_FakePool(connection))  # type: ignore[arg-type]

    with pytest.raises(NotFoundError):
        asyncio.run(repository.cancel(99))

    assert connection.execute_calls == []
