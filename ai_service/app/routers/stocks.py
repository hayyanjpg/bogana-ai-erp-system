from fastapi import APIRouter, Depends, Query

from app.deps import get_stock_service, require_internal_token
from app.schemas import RestockRequest, RestockResponse, StockDetailResponse, StockListResponse
from app.services.stock_service import StockService

router = APIRouter(
    prefix="/v1",
    tags=["stocks"],
    dependencies=[Depends(require_internal_token)],
)


@router.get("/stocks", response_model=StockListResponse)
async def list_stocks(
    low_only: bool = Query(default=False),
    category: str | None = Query(default=None),
    service: StockService = Depends(get_stock_service),
) -> StockListResponse:
    return await service.list_stocks(low_only=low_only, category=category)


@router.get("/stocks/{sku}", response_model=StockDetailResponse)
async def get_stock(
    sku: str,
    service: StockService = Depends(get_stock_service),
) -> StockDetailResponse:
    return await service.get_stock(sku)


@router.post("/stocks/{sku}/restock", response_model=RestockResponse)
async def restock(
    sku: str,
    payload: RestockRequest,
    service: StockService = Depends(get_stock_service),
) -> RestockResponse:
    return await service.restock(sku, payload.quantity)
