from datetime import UTC, date, datetime

from app.cache import JsonCache
from app.domain import DashboardData
from app.repositories import DashboardRepository, StockRepository
from app.schemas import (
    DashboardOrders,
    DashboardSummaryResponse,
    LowStockItem,
    ShortOrderItem,
    TopMenuItem,
)
from app.services.stock_service import stock_status

ORDER_STATUSES = (
    "received",
    "parsed",
    "confirmed",
    "short",
    "qc_hold",
    "fulfilled",
    "parse_failed",
    "cancelled",
)


class DashboardService:
    def __init__(
        self,
        repository: DashboardRepository,
        stock_repository: StockRepository,
        cache: JsonCache,
    ) -> None:
        self._repository = repository
        self._stock_repository = stock_repository
        self._cache = cache

    async def summary(self, date_value: date) -> DashboardSummaryResponse:
        cached = await self._cache.get("summary")
        if cached and cached.get("date") == date_value.isoformat():
            response = DashboardSummaryResponse.model_validate(cached)
            return response.model_copy(update={"source": "redis"})

        data = await self._repository.fetch(date_value)
        ingredients = await self._stock_repository.list_all()
        low_stock = [
            LowStockItem(
                sku=ingredient.sku,
                name=ingredient.name,
                stock_on_hand=ingredient.stock_on_hand,
                reorder_point=ingredient.reorder_point,
                status=stock_status(ingredient),
            )
            for ingredient in ingredients
            if stock_status(ingredient) in {"low", "out"}
        ]

        response = DashboardSummaryResponse(
            date=data.date,
            source="postgres",
            generated_at=datetime.now(UTC),
            orders=self._build_orders(data),
            revenue_forecast=data.revenue,
            low_stock=low_stock,
            short_orders=[
                ShortOrderItem(
                    order_id=item.id,
                    order_code=item.order_code,
                    customer_name=item.customer_name,
                )
                for item in data.short_orders
            ],
            top_menus=[
                TopMenuItem(
                    menu_sku=item.menu_sku,
                    menu_name=item.menu_name,
                    ordered_qty=item.ordered_qty,
                )
                for item in data.top_menus
            ],
        )

        await self._cache.set("summary", response.model_dump(mode="json"))
        return response

    @staticmethod
    def _build_orders(data: DashboardData) -> DashboardOrders:
        counts = {status: data.status_counts.get(status, 0) for status in ORDER_STATUSES}
        return DashboardOrders(total=sum(counts.values()), **counts)
