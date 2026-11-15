from fastapi import APIRouter, Depends

from app.deps import get_order_service, get_parse_order_service, require_internal_token
from app.schemas import (
    AllocateOrderRequest,
    AllocateOrderResponse,
    CheckStockResponse,
    ParseOrderRequest,
    ParseOrderResponse,
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


@router.post("/orders/{order_id}/check-stock", response_model=CheckStockResponse)
async def check_order_stock(
    order_id: int,
    service: OrderService = Depends(get_order_service),
) -> CheckStockResponse:
    return await service.check_stock(order_id)


@router.post("/orders/{order_id}/allocate", response_model=AllocateOrderResponse)
async def allocate_order(
    order_id: int,
    payload: AllocateOrderRequest | None = None,
    service: OrderService = Depends(get_order_service),
) -> AllocateOrderResponse:
    expected_version = payload.expected_version if payload is not None else None
    return await service.allocate(order_id, expected_version)
