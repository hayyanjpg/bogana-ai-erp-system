from fastapi import APIRouter, Depends

from app.deps import get_parse_order_service, require_internal_token
from app.schemas import ParseOrderRequest, ParseOrderResponse
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
