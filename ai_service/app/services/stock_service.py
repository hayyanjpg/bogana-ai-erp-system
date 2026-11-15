from dataclasses import asdict
from datetime import UTC, datetime
from typing import Any

from app.cache import JsonCache
from app.domain import Ingredient
from app.errors import NotFoundError
from app.repositories import StockRepository
from app.schemas import StockDetailResponse, StockItem, StockListResponse

CACHE_KEY = "all"


def stock_status(ingredient: Ingredient) -> str:
    if ingredient.stock_on_hand <= 0:
        return "out"
    if ingredient.stock_on_hand <= ingredient.reorder_point:
        return "low"
    return "ok"


class StockService:
    def __init__(self, repository: StockRepository, cache: JsonCache) -> None:
        self._repository = repository
        self._cache = cache

    async def list_stocks(self, low_only: bool, category: str | None) -> StockListResponse:
        source, generated_at, ingredients = await self._load()
        items = [
            self._to_item(ingredient)
            for ingredient in ingredients
            if self._matches(ingredient, low_only, category)
        ]
        return StockListResponse(source=source, generated_at=generated_at, items=items)

    async def get_stock(self, sku: str) -> StockDetailResponse:
        _, _, ingredients = await self._load()
        ingredient = next((item for item in ingredients if item.sku == sku), None)
        if ingredient is None:
            raise NotFoundError(f"Ingredient {sku} tidak ditemukan")
        menu_skus = await self._repository.list_menu_skus(ingredient.id)
        detail = self._to_item(ingredient)
        return StockDetailResponse(**detail.model_dump(), used_in_menus=menu_skus)

    async def _load(self) -> tuple[str, datetime, list[Ingredient]]:
        cached = await self._cache.get(CACHE_KEY)
        if cached is not None:
            snapshot = self._parse_snapshot(cached)
            if snapshot is not None:
                generated_at, ingredients = snapshot
                return "redis", generated_at, ingredients

        ingredients = await self._repository.list_all()
        generated_at = datetime.now(UTC)
        await self._cache.set(
            CACHE_KEY,
            {
                "generated_at": generated_at.isoformat(),
                "items": [asdict(ingredient) for ingredient in ingredients],
            },
        )
        return "postgres", generated_at, ingredients

    @staticmethod
    def _parse_snapshot(cached: Any) -> tuple[datetime, list[Ingredient]] | None:
        try:
            generated_at = datetime.fromisoformat(cached["generated_at"])
            ingredients = [Ingredient(**item) for item in cached["items"]]
        except (KeyError, TypeError, ValueError):
            return None
        return generated_at, ingredients

    @staticmethod
    def _to_item(ingredient: Ingredient) -> StockItem:
        return StockItem(
            id=ingredient.id,
            sku=ingredient.sku,
            name=ingredient.name,
            unit=ingredient.unit,
            stock_on_hand=ingredient.stock_on_hand,
            reorder_point=ingredient.reorder_point,
            unit_cost=ingredient.unit_cost,
            status=stock_status(ingredient),
        )

    @staticmethod
    def _matches(ingredient: Ingredient, low_only: bool, category: str | None) -> bool:
        if category is not None and ingredient.category != category:
            return False
        if low_only and stock_status(ingredient) == "ok":
            return False
        return True
