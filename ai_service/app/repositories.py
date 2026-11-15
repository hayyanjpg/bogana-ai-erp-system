import asyncpg

from app.domain import Ingredient
from app.errors import DependencyUnavailableError
from app.services.parser import MenuLite


class MenuRepository:
    def __init__(self, pool: asyncpg.Pool | None) -> None:
        self._pool = pool

    async def list_active(self) -> list[MenuLite]:
        if self._pool is None:
            raise DependencyUnavailableError("PostgreSQL tidak tersedia")
        rows = await self._pool.fetch(
            "SELECT sku, name, base_price FROM menus WHERE is_active = TRUE ORDER BY sku"
        )
        return [
            MenuLite(
                sku=row["sku"],
                name=row["name"],
                base_price=float(row["base_price"]),
            )
            for row in rows
        ]


class StockRepository:
    def __init__(self, pool: asyncpg.Pool | None) -> None:
        self._pool = pool

    async def list_all(self) -> list[Ingredient]:
        if self._pool is None:
            raise DependencyUnavailableError("PostgreSQL tidak tersedia")
        rows = await self._pool.fetch(
            "SELECT id, sku, name, unit, stock_on_hand, reorder_point, unit_cost, category "
            "FROM ingredients ORDER BY sku"
        )
        return [
            Ingredient(
                id=row["id"],
                sku=row["sku"],
                name=row["name"],
                unit=row["unit"],
                stock_on_hand=float(row["stock_on_hand"]),
                reorder_point=float(row["reorder_point"]),
                unit_cost=float(row["unit_cost"]),
                category=row["category"],
            )
            for row in rows
        ]

    async def list_menu_skus(self, ingredient_id: int) -> list[str]:
        if self._pool is None:
            raise DependencyUnavailableError("PostgreSQL tidak tersedia")
        rows = await self._pool.fetch(
            "SELECT m.sku FROM menus m "
            "JOIN menu_recipes mr ON mr.menu_id = m.id "
            "WHERE mr.ingredient_id = $1 ORDER BY m.sku",
            ingredient_id,
        )
        return [row["sku"] for row in rows]
