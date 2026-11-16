from datetime import UTC, datetime

from app.cache import JsonCache
from app.domain import (
    AllocationResult,
    ConfirmResult,
    OrderDetail,
    OrderSnapshot,
    QcRecordResult,
    SavedOrder,
    StockDeduction,
    StockRequirement,
)
from app.errors import ConflictError, InsufficientStockError, NotFoundError
from app.repositories import OrderRepository
from app.schemas import (
    AllocateOrderResponse,
    AllocationDeductionItem,
    CheckStockResponse,
    ConfirmOrderResponse,
    OrderDetailResponse,
    OrderItemDetail,
    OrderListResponse,
    OrderSummary,
    RecordQcResponse,
    SaveOrderRequest,
    SaveOrderResponse,
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

    async def _invalidate(self) -> None:
        await self._stock_cache.delete("all")
        await self._dashboard_cache.delete("summary")

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

    async def save_from_parse(self, payload: SaveOrderRequest) -> SaveOrderResponse:
        saved: SavedOrder = await self._repository.save_from_parse(payload, payload.items)
        await self._dashboard_cache.delete("summary")
        return SaveOrderResponse(
            order_id=saved.order_id,
            order_code=saved.order_code,
            status=saved.status,
            version=saved.version,
            order_date=saved.order_date,
            item_count=saved.item_count,
            total_amount=saved.total_amount,
            created=saved.created,
        )

    async def list_orders(self, status, date_value, limit) -> OrderListResponse:
        rows = await self._repository.list_orders(status=status, date_value=date_value, limit=limit)
        return OrderListResponse(
            items=[
                OrderSummary(
                    id=r.id,
                    order_code=r.order_code,
                    customer_name=r.customer_name,
                    channel=r.channel,
                    status=r.status,
                    order_date=r.order_date,
                    version=r.version,
                    item_count=r.item_count,
                    total_amount=r.total_amount,
                )
                for r in rows
            ]
        )

    async def get_detail(self, order_id: int) -> OrderDetailResponse:
        detail: OrderDetail | None = await self._repository.get_detail(order_id)
        if detail is None:
            raise NotFoundError(f"Order {order_id} tidak ditemukan")
        requirements = [_to_requirement_item(r) for r in detail.requirements]
        insufficient = [r for r in requirements if r.shortage > 0]
        return OrderDetailResponse(
            id=detail.id,
            order_code=detail.order_code,
            customer_name=detail.customer_name,
            channel=detail.channel,
            status=detail.status,
            version=detail.version,
            order_date=detail.order_date,
            notes=detail.notes,
            items=[
                OrderItemDetail(
                    id=i.id,
                    menu_sku=i.menu_sku,
                    menu_name=i.menu_name,
                    quantity=i.quantity,
                    unit_price=i.unit_price,
                    line_total=i.line_total,
                    status=i.status,
                    qc_passed=i.qc_passed,
                )
                for i in detail.items
            ],
            sufficient=not insufficient,
            requirements=requirements,
            insufficient=insufficient,
        )

    async def confirm(self, order_id: int) -> ConfirmOrderResponse:
        result: ConfirmResult = await self._repository.confirm_or_short(order_id)
        await self._dashboard_cache.delete("summary")
        requirements = [_to_requirement_item(r) for r in result.requirements]
        insufficient = [r for r in requirements if r.shortage > 0]
        return ConfirmOrderResponse(
            order_id=result.order_id,
            order_code=result.order_code,
            status=result.status,
            version=result.version,
            sufficient=result.status == "confirmed",
            insufficient=insufficient,
        )

    async def record_qc_pass(self, order_id: int, item_id: int, checked_by: str, note: str | None) -> RecordQcResponse:
        result: QcRecordResult = await self._repository.record_qc_pass(order_id, item_id, checked_by, note)
        return RecordQcResponse(
            order_id=result.order_id,
            order_item_id=result.order_item_id,
            result=result.result,
            qc_passed=result.qc_passed,
            recorded=result.recorded,
        )

    async def allocate(
        self,
        order_id: int,
        expected_version: int | None,
    ) -> AllocateOrderResponse:
        snapshot = await self._require_snapshot(order_id)
        self._validate_for_allocation(snapshot, expected_version)

        result: AllocationResult = await self._repository.allocate(order_id, expected_version)

        await self._invalidate()

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
