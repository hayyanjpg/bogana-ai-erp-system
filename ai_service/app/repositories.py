from datetime import UTC, date, datetime

import asyncpg

from app.domain import (
    AllocationResult,
    DashboardData,
    ForecastRow,
    Ingredient,
    OrderSnapshot,
    ShortOrder,
    StockDeduction,
    StockRequirement,
    TopMenu,
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


class DashboardRepository:
    def __init__(self, pool: asyncpg.Pool | None) -> None:
        self._pool = pool

    def _require_pool(self) -> asyncpg.Pool:
        if self._pool is None:
            raise DependencyUnavailableError("PostgreSQL tidak tersedia")
        return self._pool

    async def fetch(self, date_value: date) -> DashboardData:
        pool = self._require_pool()

        status_rows = await pool.fetch(
            "SELECT status, COUNT(*) AS total FROM b2b_orders "
            "WHERE order_date = $1 GROUP BY status",
            date_value,
        )
        status_counts = {row["status"]: row["total"] for row in status_rows}

        revenue = await pool.fetchval(
            "SELECT COALESCE(SUM(oi.line_total), 0) FROM order_items oi "
            "JOIN b2b_orders o ON o.id = oi.order_id "
            "WHERE o.order_date = $1 AND o.status IN ('confirmed', 'fulfilled')",
            date_value,
        )

        short_rows = await pool.fetch(
            "SELECT id, order_code, customer_name FROM b2b_orders "
            "WHERE order_date = $1 AND status = 'short' ORDER BY id",
            date_value,
        )

        top_rows = await pool.fetch(
            "SELECT m.sku AS menu_sku, m.name AS menu_name, "
            "SUM(oi.quantity) AS ordered_qty FROM order_items oi "
            "JOIN b2b_orders o ON o.id = oi.order_id "
            "JOIN menus m ON m.id = oi.menu_id "
            "WHERE o.order_date = $1 "
            "GROUP BY m.sku, m.name "
            "ORDER BY ordered_qty DESC, m.sku ASC LIMIT 5",
            date_value,
        )

        return DashboardData(
            date=date_value,
            status_counts=status_counts,
            revenue=float(revenue or 0),
            short_orders=[
                ShortOrder(
                    id=row["id"],
                    order_code=row["order_code"],
                    customer_name=row["customer_name"],
                )
                for row in short_rows
            ],
            top_menus=[
                TopMenu(
                    menu_sku=row["menu_sku"],
                    menu_name=row["menu_name"],
                    ordered_qty=float(row["ordered_qty"]),
                )
                for row in top_rows
            ],
        )


class ForecastRepository:
    def __init__(self, pool: asyncpg.Pool | None) -> None:
        self._pool = pool

    def _require_pool(self) -> asyncpg.Pool:
        if self._pool is None:
            raise DependencyUnavailableError("PostgreSQL tidak tersedia")
        return self._pool

    async def list(
        self,
        from_date: date,
        to_date: date,
        menu_id: int | None,
    ) -> tuple[str, list[ForecastRow]]:
        pool = self._require_pool()
        model_version = await pool.fetchval(
            "SELECT model_version FROM demand_forecasts ORDER BY created_at DESC, id DESC LIMIT 1"
        )
        if model_version is None:
            return "", []

        if menu_id is None:
            rows = await pool.fetch(
                "SELECT f.menu_id, m.sku AS menu_sku, m.name AS menu_name, "
                "f.forecast_date, f.forecast_qty, f.confidence FROM demand_forecasts f "
                "JOIN menus m ON m.id = f.menu_id "
                "WHERE f.model_version = $1 "
                "AND f.forecast_date >= $2 AND f.forecast_date < $3 "
                "ORDER BY f.forecast_date ASC, f.menu_id ASC",
                model_version,
                from_date,
                to_date,
            )
        else:
            rows = await pool.fetch(
                "SELECT f.menu_id, m.sku AS menu_sku, m.name AS menu_name, "
                "f.forecast_date, f.forecast_qty, f.confidence FROM demand_forecasts f "
                "JOIN menus m ON m.id = f.menu_id "
                "WHERE f.model_version = $1 "
                "AND f.forecast_date >= $2 AND f.forecast_date < $3 AND f.menu_id = $4 "
                "ORDER BY f.forecast_date ASC, f.menu_id ASC",
                model_version,
                from_date,
                to_date,
                menu_id,
            )

        return model_version, [
            ForecastRow(
                menu_id=row["menu_id"],
                menu_sku=row["menu_sku"],
                menu_name=row["menu_name"],
                forecast_date=row["forecast_date"],
                forecast_qty=float(row["forecast_qty"]),
                confidence=float(row["confidence"]),
            )
            for row in rows
        ]
