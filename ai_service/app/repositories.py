from datetime import UTC, date, datetime

import asyncpg

from app.domain import (
    AllocationResult,
    ConfirmResult,
    DashboardData,
    ForecastRow,
    Ingredient,
    OrderDetail,
    OrderItemRow,
    OrderSnapshot,
    OrderSummaryRow,
    QcRecordResult,
    RestockResult,
    SavedOrder,
    ShortOrder,
    StockDeduction,
    StockRequirement,
    TopMenu,
)
from app.errors import (
    BusinessRuleError,
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

    async def restock(self, sku: str, quantity: float) -> RestockResult:
        if quantity <= 0:
            raise BusinessRuleError(
                "quantity harus lebih besar dari 0",
                details=[{"field": "quantity", "issue": "must be greater than 0"}],
            )
        pool = self._require_pool()
        async with pool.acquire() as conn, conn.transaction():
            row = await conn.fetchrow(
                "UPDATE ingredients SET stock_on_hand = stock_on_hand + $2 "
                "WHERE sku = $1 RETURNING sku, name, unit, stock_on_hand, reorder_point",
                sku,
                quantity,
            )
            if row is None:
                raise NotFoundError(f"Ingredient {sku} tidak ditemukan")
            stock = float(row["stock_on_hand"])
            rop = float(row["reorder_point"])
            status = "out" if stock <= 0 else "low" if stock <= rop else "ok"
            return RestockResult(row["sku"], row["name"], row["unit"], stock, status)


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



ORDER_ITEMS_SQL = """
SELECT oi.id, m.sku AS menu_sku, m.name AS menu_name,
       oi.quantity::float8 AS quantity, oi.unit_price::float8 AS unit_price,
       oi.line_total::float8 AS line_total, oi.status,
       EXISTS(
           SELECT 1 FROM qc_logs q
           WHERE q.order_item_id = oi.id AND q.result = 'pass'
       ) AS qc_passed
FROM order_items oi
JOIN menus m ON m.id = oi.menu_id
WHERE oi.order_id = $1
ORDER BY oi.id
"""


class _OrderExtMixin:
    async def save_from_parse(self, payload, items):
        pool = self._require_pool()
        async with pool.acquire() as conn, conn.transaction():
            skus = [i.menu_sku for i in items]
            menu_rows = await conn.fetch(
                "SELECT id, sku, base_price::float8 AS base_price "
                "FROM menus WHERE sku = ANY($1::text[])",
                skus,
            )
            menu_map = {r["sku"]: r for r in menu_rows}
            missing = [sku for sku in skus if sku not in menu_map]
            if missing:
                raise ConflictError("Menu tidak valid: " + ", ".join(missing))
            order_date = payload.order_date or datetime.now(UTC).date()
            date_str = order_date.strftime("%Y%m%d")
            seq = await conn.fetchval(
                "SELECT COUNT(*) + 1 FROM b2b_orders WHERE order_code LIKE $1",
                f"ORD-{date_str}-%",
            )
            order_code = f"ORD-{date_str}-{int(seq):03d}"
            row = await conn.fetchrow(
                "INSERT INTO b2b_orders (order_code, customer_name, channel, raw_text, "
                "status, order_date, notes) "
                "VALUES ($1,$2,$3,$4,'parsed',$5,$6) "
                "RETURNING id, order_code, status, version, order_date",
                order_code,
                payload.customer_name,
                payload.channel,
                payload.raw_text,
                order_date,
                payload.notes,
            )
            total = 0.0
            for item in items:
                menu = menu_map[item.menu_sku]
                qty = float(item.quantity)
                price = float(menu["base_price"])
                total += qty * price
                await conn.execute(
                    "INSERT INTO order_items (order_id, menu_id, quantity, unit_price, status) "
                    "VALUES ($1,$2,$3,$4,'pending')",
                    row["id"],
                    menu["id"],
                    qty,
                    price,
                )
            return SavedOrder(
                row["id"],
                row["order_code"],
                row["status"],
                int(row["version"]),
                row["order_date"],
                len(items),
                total,
                True,
            )

    async def list_orders(self, status=None, date_value=None, limit=20):
        pool = self._require_pool()
        clauses = []
        params = []
        if status:
            params.append(status)
            clauses.append(f"status = ${len(params)}")
        if date_value:
            params.append(date_value)
            clauses.append(f"order_date = ${len(params)}")
        params.append(limit)
        where = ("WHERE " + " AND ".join(clauses)) if clauses else ""
        rows = await pool.fetch(
            "SELECT o.id, o.order_code, o.customer_name, o.channel, "
            "o.status, o.order_date, o.version, "
            "COUNT(oi.id)::int AS item_count, "
            "COALESCE(SUM(oi.line_total),0)::float8 AS total_amount "
            f"FROM b2b_orders o LEFT JOIN order_items oi ON oi.order_id=o.id {where} "
            "GROUP BY o.id ORDER BY o.id DESC LIMIT $" + str(len(params)),
            *params,
        )
        return [
            OrderSummaryRow(
                r["id"],
                r["order_code"],
                r["customer_name"],
                r["channel"],
                r["status"],
                r["order_date"],
                int(r["version"]),
                int(r["item_count"]),
                float(r["total_amount"]),
            )
            for r in rows
        ]

    async def get_detail(self, order_id):
        pool = self._require_pool()
        header = await pool.fetchrow(
            "SELECT id, order_code, customer_name, channel, status, version, "
            "order_date, notes FROM b2b_orders WHERE id=$1",
            order_id,
        )
        if header is None:
            return None
        rows = await pool.fetch(ORDER_ITEMS_SQL, order_id)
        items = [
            OrderItemRow(
                r["id"],
                r["menu_sku"],
                r["menu_name"],
                float(r["quantity"]),
                float(r["unit_price"]),
                float(r["line_total"]),
                r["status"],
                bool(r["qc_passed"]),
            )
            for r in rows
        ]
        requirements = await self._fetch_requirements(pool, order_id)
        return OrderDetail(
            header["id"],
            header["order_code"],
            header["customer_name"],
            header["channel"],
            header["status"],
            int(header["version"]),
            header["order_date"],
            header["notes"],
            items,
            requirements,
        )

    async def confirm_or_short(self, order_id):
        pool = self._require_pool()
        async with pool.acquire() as conn, conn.transaction():
            header = await conn.fetchrow(
                "SELECT id, order_code, status, version FROM b2b_orders WHERE id=$1 FOR UPDATE",
                order_id,
            )
            if header is None:
                raise NotFoundError(f"Order {order_id} tidak ditemukan")
            if header["status"] in ("fulfilled", "cancelled"):
                raise ConflictError(f"Order berstatus {header['status']} tidak dapat diubah")
            requirements = await self._fetch_requirements(conn, order_id)
            if not requirements:
                raise ConflictError("Order tidak memiliki item")
            sufficient = all(item.shortage <= 0 for item in requirements)
            new_status = "confirmed" if sufficient else "short"
            if header["status"] != new_status:
                await conn.execute(
                    "UPDATE b2b_orders SET status=$2, version=version+1, "
                    "updated_at=now() WHERE id=$1",
                    order_id,
                    new_status,
                )
            row = await conn.fetchrow(
                "SELECT status, version FROM b2b_orders WHERE id=$1",
                order_id,
            )
            return ConfirmResult(
                header["id"],
                header["order_code"],
                row["status"],
                int(row["version"]),
                requirements,
            )

    async def record_qc_pass(self, order_id, item_id, checked_by, note):
        pool = self._require_pool()
        async with pool.acquire() as conn, conn.transaction():
            row = await conn.fetchrow(
                "SELECT oi.id FROM order_items oi WHERE oi.id=$1 AND oi.order_id=$2 FOR UPDATE",
                item_id, order_id,
            )
            if row is None:
                raise NotFoundError(f"Item {item_id} pada order {order_id} tidak ditemukan")
            ing_rows = await conn.fetch(
                "SELECT mr.ingredient_id FROM menu_recipes mr "
                "JOIN order_items oi ON oi.menu_id=mr.menu_id "
                "WHERE oi.id=$1",
                item_id,
            )
            recorded = 0
            for ing in ing_rows:
                await conn.execute(
                    "INSERT INTO qc_logs (order_item_id, ingredient_id, result, "
                    "checked_by, note) VALUES ($1,$2,'pass',$3,$4)",
                    item_id,
                    ing["ingredient_id"],
                    checked_by,
                    note,
                )
                recorded += 1
            exists = await conn.fetchval(
                "SELECT EXISTS(SELECT 1 FROM qc_logs WHERE order_item_id=$1 AND result='pass')",
                item_id,
            )
            return QcRecordResult(order_id, item_id, "pass", bool(exists), recorded)


# bind mixin methods onto OrderRepository
for _name in ("save_from_parse", "list_orders", "get_detail", "confirm_or_short", "record_qc_pass"):
    setattr(OrderRepository, _name, getattr(_OrderExtMixin, _name))


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
