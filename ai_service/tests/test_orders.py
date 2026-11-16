from datetime import UTC, datetime

from app.domain import AllocationResult, OrderSnapshot, StockDeduction, StockRequirement


def build_snapshot(
    *,
    status: str = "confirmed",
    version: int = 1,
    qc_passed: bool = True,
    requirements: tuple[StockRequirement, ...] | None = None,
) -> OrderSnapshot:
    if requirements is None:
        requirements = (
            StockRequirement(1, "ING-RS-01", "Beras Pandan Wangi", "kg", 5.0, 100.0),
            StockRequirement(2, "ING-AY-01", "Ayam Fillet", "kg", 6.0, 25.0),
        )
    return OrderSnapshot(
        id=1,
        order_code="ORD-20261116-002",
        status=status,
        version=version,
        qc_passed=qc_passed,
        requirements=list(requirements),
    )


def build_allocation() -> AllocationResult:
    return AllocationResult(
        order_id=1,
        order_code="ORD-20261116-002",
        status="fulfilled",
        allocated_at=datetime.now(UTC),
        deductions=[StockDeduction("ING-AY-01", 6.0, "kg", 19.0)],
    )


def test_check_stock_reports_sufficient(client, order_repo):
    order_repo.snapshot = build_snapshot()

    response = client.post("/v1/orders/1/check-stock", json={})

    assert response.status_code == 200
    body = response.json()
    assert body["order_code"] == "ORD-20261116-002"
    assert body["sufficient"] is True
    assert body["insufficient"] == []
    assert len(body["requirements"]) == 2


def test_check_stock_reports_shortage(client, order_repo):
    order_repo.snapshot = build_snapshot(
        requirements=(StockRequirement(1, "ING-RS-01", "Beras Pandan Wangi", "kg", 5.0, 2.0),)
    )

    response = client.post("/v1/orders/1/check-stock")

    assert response.status_code == 200
    body = response.json()
    assert body["sufficient"] is False
    assert body["insufficient"][0]["ingredient_sku"] == "ING-RS-01"
    assert body["insufficient"][0]["shortage"] == 3.0


def test_check_stock_unknown_order_returns_404(client, order_repo):
    order_repo.snapshot = None

    response = client.post("/v1/orders/99/check-stock")

    assert response.status_code == 404
    assert response.json()["error"]["code"] == "NOT_FOUND"


def test_allocate_order_deducts_stock(client, order_repo):
    order_repo.snapshot = build_snapshot()
    order_repo.allocation = build_allocation()

    response = client.post("/v1/orders/1/allocate", json={"expected_version": 1})

    assert response.status_code == 200
    body = response.json()
    assert body["status"] == "fulfilled"
    assert body["deductions"][0]["ingredient_sku"] == "ING-AY-01"
    assert body["deductions"][0]["stock_after"] == 19.0
    assert body["invalidated_cache_keys"] == ["erp:stock:all", "erp:dashboard:summary"]
    assert order_repo.allocate_calls == [(1, 1)]


def test_allocate_rejects_non_confirmed_order(client, order_repo):
    order_repo.snapshot = build_snapshot(status="parsed")

    response = client.post("/v1/orders/1/allocate")

    assert response.status_code == 409
    assert response.json()["error"]["code"] == "CONFLICT"
    assert order_repo.allocate_calls == []


def test_allocate_rejects_version_mismatch(client, order_repo):
    order_repo.snapshot = build_snapshot(version=2)

    response = client.post("/v1/orders/1/allocate", json={"expected_version": 1})

    assert response.status_code == 409
    assert order_repo.allocate_calls == []


def test_allocate_requires_qc_pass(client, order_repo):
    order_repo.snapshot = build_snapshot(qc_passed=False)

    response = client.post("/v1/orders/1/allocate")

    assert response.status_code == 409
    assert "QC" in response.json()["error"]["message"]
    assert order_repo.allocate_calls == []


def test_allocate_rejects_insufficient_stock(client, order_repo):
    order_repo.snapshot = build_snapshot(
        requirements=(StockRequirement(2, "ING-AY-01", "Ayam Fillet", "kg", 30.0, 25.0),)
    )

    response = client.post("/v1/orders/1/allocate")

    assert response.status_code == 422
    assert response.json()["error"]["code"] == "INSUFFICIENT_STOCK"
    assert order_repo.allocate_calls == []
