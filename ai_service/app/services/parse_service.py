import hashlib
from datetime import date, timedelta
from uuid import uuid4

from app.cache import ParseCache
from app.repositories import MenuRepository
from app.schemas import ParsedItem, ParseOrderRequest, ParseOrderResponse, ParseWarning
from app.services.parser import parse_order_text


class ParseOrderService:
    def __init__(self, menu_repo: MenuRepository, cache: ParseCache) -> None:
        self._menu_repo = menu_repo
        self._cache = cache

    async def handle(self, payload: ParseOrderRequest) -> ParseOrderResponse:
        order_date = payload.order_date or date.today() + timedelta(days=1)
        cache_key = hashlib.sha1(payload.raw_text.strip().lower().encode("utf-8")).hexdigest()

        cached = await self._cache.get(cache_key)
        if cached is not None:
            return ParseOrderResponse.model_validate(cached)

        menus = await self._menu_repo.list_active()
        result = parse_order_text(payload.raw_text, menus)

        response = ParseOrderResponse(
            parse_id=uuid4(),
            order_date=order_date,
            confidence=result.confidence,
            items=[
                ParsedItem(
                    line_text=item.line_text,
                    menu_sku=item.menu.sku,
                    menu_name=item.menu.name,
                    quantity=item.quantity,
                    unit_price=item.menu.base_price,
                )
                for item in result.items
            ],
            warnings=[ParseWarning(**warning) for warning in result.warnings],
        )

        await self._cache.set(cache_key, response.model_dump(mode="json"))
        return response
