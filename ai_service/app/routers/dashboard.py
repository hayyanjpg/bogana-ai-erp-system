from datetime import date

from fastapi import APIRouter, Depends, Query

from app.deps import get_dashboard_service, require_internal_token
from app.schemas import DashboardSummaryResponse
from app.services.dashboard_service import DashboardService

router = APIRouter(
    prefix="/v1",
    tags=["dashboard"],
    dependencies=[Depends(require_internal_token)],
)


@router.get("/dashboard/summary", response_model=DashboardSummaryResponse)
async def dashboard_summary(
    date_value: date | None = Query(default=None, alias="date"),
    service: DashboardService = Depends(get_dashboard_service),
) -> DashboardSummaryResponse:
    return await service.summary(date_value or date.today())
