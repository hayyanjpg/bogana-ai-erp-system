from datetime import date

from fastapi import APIRouter, Depends, Query

from app.deps import get_forecast_service, require_internal_token
from app.schemas import ForecastsResponse
from app.services.forecast_service import ForecastService

router = APIRouter(
    prefix="/v1",
    tags=["forecasts"],
    dependencies=[Depends(require_internal_token)],
)


@router.get("/forecasts", response_model=ForecastsResponse)
async def list_forecasts(
    menu_id: int | None = Query(default=None),
    days: int = Query(default=7, ge=1, le=30),
    from_date: date | None = Query(default=None),
    service: ForecastService = Depends(get_forecast_service),
) -> ForecastsResponse:
    return await service.list(from_date or date.today(), days, menu_id)
