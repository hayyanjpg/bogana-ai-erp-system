from datetime import UTC, datetime

import asyncpg

from app.domain import (
    AllocationResult,
    Ingredient,
    OrderSnapshot,
    StockDeduction,
    StockRequirement,
)
from app.errors import (
    ConflictError,
    DependencyUnavailableError,
    InsufficientStockError,
    NotFoundError,
)
from app.services.parser import MenuLite

REQUIREMENTS_SQL = """
SELECT i.id AS ingredient_id,
       i.sku AS ingredient_sku,
       i.name AS ingredient_name,
       i.unit AS unit,
       i.stock_on_hand AS available,
       SUM(mr.quantity * oi.quantity) AS required
FROM order_items oi
JOIN menu_recipes mr ON mr.menu_id = oi.menu_id
JOIN ingredients i ON i.id = mr.ingredient_id
WHERE oi.order_id = $1
GROUP BY i.id, i.sku, i.name, i.unit, i.stock_on_hand
ORDER BY i.sku
"""

QC_MISSING_SQL = """
SELECT COUNT(*) AS missing
FROM order_items oi
WHERE oi.order_id = $1
  AND NOT EXISTS (
      SELECT 1 FROM qc_logs q
      WHERE q.order_item_id = oi.id AND q.result = 'pass'
  )
"""

Executor = asyncpg.Pool | asyncpg.Connection


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


class OrderRepository:
    def __init__(self, pool: asyncpg.Pool | None) -> None:
        self._pool = pool

    def _require_pool(self) -> asyncpg.Pool:
        if self._pool is None:
            raise DependencyUnavailableError("PostgreSQL tidak tersedia")
        return self._pool

    @staticmethod
    async def _fetch_requirements(
        executor: Executor,
        order_id: int,
    ) -> list[StockRequirement]:
        rows = await executor.fetch(REQUIREMENTS_SQL, order_id)
        return [
            StockRequirement(
                ingredient_id=row["ingredient_id"],
                ingredient_sku=row["ingredient_sku"],
                ingredient_name=row["ingredient_name"],
                unit=row["unit"],
                required=float(row["required"]),
                available=float(row["available"]),
            )
            for row in rows
        ]

    async def get_snapshot(self, order_id: int) -> OrderSnapshot | None:
        pool = self._require_pool()
        header = await pool.fetchrow(
            "SELECT id, order_code, status, version FROM b2b_orders WHERE id = $1",
            order_id,
        )
        if header is None:
            return None
        requirements = await self._fetch_requirements(pool, order_id)
        missing = await pool.fetchval(QC_MISSING_SQL, order_id)
        return OrderSnapshot(
            id=header["id"],
            order_code=header["order_code"],
            status=header["status"],
            version=header["version"],
            qc_passed=not bool(missing),
            requirements=requirements,
        )

    async def allocate(
        self,
        order_id: int,
        expected_version: int | None,
    ) -> AllocationResult:
        pool = self._require_pool()
        async with pool.acquire() as conn, conn.transaction():
            header = await conn.fetchrow(
                "SELECT id, order_code, status, version FROM b2b_orders WHERE id = $1 FOR UPDATE",
                order_id,
            )
            if header is None:
                raise NotFoundError(f"Order {order_id} tidak ditemukan")
            if header["status"] != "confirmed":
                raise ConflictError(
                    f"Order berstatus {header['status']}, hanya confirmed yang bisa dialokasikan"
                )
            if expected_version is not None and header["version"] != expected_version:
                raise ConflictError("Versi order sudah berubah, muat ulang order")

            missing = await conn.fetchval(QC_MISSING_SQL, order_id)
            if missing:
                raise ConflictError("QC belum lengkap untuk seluruh item order")

            requirements = await self._fetch_requirements(conn, order_id)
            if not requirements:
                raise ConflictError("Order tidak memiliki item")
            shortage = [item for item in requirements if item.shortage > 0]
            if shortage:
                raise InsufficientStockError(
                    "Stok bahan tidak mencukupi",
                    details=[
                        {
                            "field": item.ingredient_sku,
                            "issue": f"kurang {item.shortage:.3f} {item.unit}",
                        }
                        for item in shortage
                    ],
                )

            deductions: list[StockDeduction] = []
            for requirement in requirements:
                stock_after = await conn.fetchval(
                    "UPDATE ingredients SET stock_on_hand = stock_on_hand - $2 "
                    "WHERE id = $1 AND stock_on_hand >= $2 RETURNING stock_on_hand",
                    requirement.ingredient_id,
                    requirement.required,
                )
                if stock_after is None:
                    raise InsufficientStockError(
                        "Stok bahan tidak mencukupi",
                        details=[
                            {
                                "field": requirement.ingredient_sku,
                                "issue": "stok berubah saat alokasi",
                            }
                        ],
                    )
                deductions.append(
                    StockDeduction(
                        ingredient_sku=requirement.ingredient_sku,
                        quantity=requirement.required,
                        unit=requirement.unit,
                        stock_after=float(stock_after),
                    )
                )

            await conn.execute(
                "UPDATE b2b_orders "
                "SET status = 'fulfilled', version = version + 1, updated_at = now() "
                "WHERE id = $1",
                order_id,
            )
            return AllocationResult(
                order_id=header["id"],
                order_code=header["order_code"],
                status="fulfilled",
                allocated_at=datetime.now(UTC),
                deductions=deductions,
            )
