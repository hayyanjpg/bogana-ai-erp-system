from datetime import date, timedelta

from app.domain import ForecastRow
from app.repositories import ForecastRepository
from app.schemas import ForecastItem, ForecastsResponse


class ForecastService:
    def __init__(self, repository: ForecastRepository) -> None:
        self._repository = repository

    async def list(
        self,
        from_date: date,
        days: int,
        menu_id: int | None,
    ) -> ForecastsResponse:
        to_date = from_date + timedelta(days=days)
        model_version, rows = await self._repository.list(from_date, to_date, menu_id)
        return ForecastsResponse(
            from_date=from_date,
            days=days,
            model_version=model_version,
            items=[self._to_item(row) for row in rows],
        )

    @staticmethod
    def _to_item(row: ForecastRow) -> ForecastItem:
        return ForecastItem(
            menu_id=row.menu_id,
            menu_sku=row.menu_sku,
            menu_name=row.menu_name,
            forecast_date=row.forecast_date,
            forecast_qty=row.forecast_qty,
            confidence=row.confidence,
        )
