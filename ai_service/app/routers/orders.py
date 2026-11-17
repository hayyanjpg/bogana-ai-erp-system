from datetime import date

from fastapi import APIRouter, Depends, Query

from app.deps import get_order_service, get_parse_order_service, require_internal_token
from app.schemas import (
    AllocateOrderRequest,
    AllocateOrderResponse,
    CheckStockResponse,
    ConfirmOrderResponse,
    OrderDetailResponse,
    OrderListResponse,
    ParseOrderRequest,
    ParseOrderResponse,
    RecordQcRequest,
    RecordQcResponse,
    SaveOrderRequest,
    SaveOrderResponse,
)
from app.services.order_service import OrderService
from app.services.parse_service import ParseOrderService

router = APIRouter(
    prefix="/v1",
    tags=["orders"],
    dependencies=[Depends(require_internal_token)],
)


@router.post("/parse-order", response_model=ParseOrderResponse)
async def parse_order(
    payload: ParseOrderRequest,
    service: ParseOrderService = Depends(get_parse_order_service),
) -> ParseOrderResponse:
    return await service.handle(payload)


@router.post("/orders", response_model=SaveOrderResponse)
async def save_order(
    payload: SaveOrderRequest,
    service: OrderService = Depends(get_order_service),
) -> SaveOrderResponse:
    return await service.save_from_parse(payload)


@router.get("/orders", response_model=OrderListResponse)
async def list_orders(
    status: str | None = Query(default=None),
    order_date: date | None = Query(default=None, alias="date"),
    limit: int = Query(default=20, ge=1, le=100),
    service: OrderService = Depends(get_order_service),
) -> OrderListResponse:
    return await service.list_orders(status=status, date_value=order_date, limit=limit)


@router.get("/orders/{order_id}", response_model=OrderDetailResponse)
async def get_order(
    order_id: int,
    service: OrderService = Depends(get_order_service),
) -> OrderDetailResponse:
    return await service.get_detail(order_id)


@router.post("/orders/{order_id}/check-stock", response_model=CheckStockResponse)
async def check_order_stock(
    order_id: int,
    service: OrderService = Depends(get_order_service),
) -> CheckStockResponse:
    return await service.check_stock(order_id)


@router.post("/orders/{order_id}/confirm", response_model=ConfirmOrderResponse)
async def confirm_order(
    order_id: int,
    service: OrderService = Depends(get_order_service),
) -> ConfirmOrderResponse:
    return await service.confirm(order_id)


@router.post("/orders/{order_id}/items/{item_id}/qc", response_model=RecordQcResponse)
async def record_item_qc(
    order_id: int,
    item_id: int,
    payload: RecordQcRequest,
    service: OrderService = Depends(get_order_service),
) -> RecordQcResponse:
    return await service.record_qc_pass(order_id, item_id, payload.checked_by, payload.note)


@router.post("/orders/{order_id}/allocate", response_model=AllocateOrderResponse)
async def allocate_order(
    order_id: int,
    payload: AllocateOrderRequest | None = None,
    service: OrderService = Depends(get_order_service),
) -> AllocateOrderResponse:
    expected_version = payload.expected_version if payload is not None else None
    return await service.allocate(order_id, expected_version)
