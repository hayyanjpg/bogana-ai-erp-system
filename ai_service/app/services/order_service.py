from datetime import UTC, datetime

from app.cache import JsonCache
from app.domain import AllocationResult, OrderSnapshot, StockDeduction, StockRequirement
from app.errors import ConflictError, InsufficientStockError, NotFoundError
from app.repositories import OrderRepository
from app.schemas import (
    AllocateOrderResponse,
    AllocationDeductionItem,
    CheckStockResponse,
    StockRequirementItem,
)

INVALIDATED_CACHE_KEYS = ["erp:stock:all", "erp:dashboard:summary"]


def _to_requirement_item(requirement: StockRequirement) -> StockRequirementItem:
    return StockRequirementItem(
        ingredient_sku=requirement.ingredient_sku,
        ingredient_name=requirement.ingredient_name,
        unit=requirement.unit,
        required=requirement.required,
        available=requirement.available,
        shortage=requirement.shortage,
    )


def _to_deduction_item(deduction: StockDeduction) -> AllocationDeductionItem:
    return AllocationDeductionItem(
        ingredient_sku=deduction.ingredient_sku,
        quantity=deduction.quantity,
        unit=deduction.unit,
        stock_after=deduction.stock_after,
    )


class OrderService:
    def __init__(
        self,
        repository: OrderRepository,
        stock_cache: JsonCache,
        dashboard_cache: JsonCache,
    ) -> None:
        self._repository = repository
        self._stock_cache = stock_cache
        self._dashboard_cache = dashboard_cache

    async def check_stock(self, order_id: int) -> CheckStockResponse:
        snapshot = await self._require_snapshot(order_id)
        requirements = [_to_requirement_item(item) for item in snapshot.requirements]
        insufficient = [item for item in requirements if item.shortage > 0]
        return CheckStockResponse(
            order_id=snapshot.id,
            order_code=snapshot.order_code,
            sufficient=not insufficient,
            checked_at=datetime.now(UTC),
            requirements=requirements,
            insufficient=insufficient,
        )

    async def allocate(
        self,
        order_id: int,
        expected_version: int | None,
    ) -> AllocateOrderResponse:
        snapshot = await self._require_snapshot(order_id)
        self._validate_for_allocation(snapshot, expected_version)

        result: AllocationResult = await self._repository.allocate(order_id, expected_version)

        await self._stock_cache.delete("all")
        await self._dashboard_cache.delete("summary")

        return AllocateOrderResponse(
            order_id=result.order_id,
            order_code=result.order_code,
            status=result.status,
            allocated_at=result.allocated_at,
            deductions=[_to_deduction_item(item) for item in result.deductions],
            invalidated_cache_keys=INVALIDATED_CACHE_KEYS,
        )

    async def _require_snapshot(self, order_id: int) -> OrderSnapshot:
        snapshot = await self._repository.get_snapshot(order_id)
        if snapshot is None:
            raise NotFoundError(f"Order {order_id} tidak ditemukan")
        return snapshot

    @staticmethod
    def _validate_for_allocation(
        snapshot: OrderSnapshot,
        expected_version: int | None,
    ) -> None:
        if snapshot.status != "confirmed":
            raise ConflictError(
                f"Order berstatus {snapshot.status}, hanya confirmed yang bisa dialokasikan"
            )
        if expected_version is not None and snapshot.version != expected_version:
            raise ConflictError("Versi order sudah berubah, muat ulang order")
        if not snapshot.qc_passed:
            raise ConflictError("QC belum lengkap untuk seluruh item order")
        shortage = [item for item in snapshot.requirements if item.shortage > 0]
        if shortage:
            raise InsufficientStockError(
                "Stok bahan tidak mencukupi",
                details=[
                    {
                        "field": item.ingredient_sku,
                        "issue": f"kurang {item.shortage:.3f} {item.unit}",
                    }
                    for item in shortage
                ],
            )
