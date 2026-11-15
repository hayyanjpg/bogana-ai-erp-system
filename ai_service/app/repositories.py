import asyncpg

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
