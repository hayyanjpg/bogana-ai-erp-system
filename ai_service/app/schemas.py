from __future__ import annotations

from datetime import date, datetime
from typing import Literal
from uuid import UUID

from pydantic import BaseModel, Field

WarningCode = Literal[
    "MENU_NOT_FOUND",
    "QTY_MISSING",
    "QTY_AMBIGUOUS",
    "DUPLICATE_LINE",
    "LOW_CONFIDENCE",
]

Channel = Literal["whatsapp", "email", "phone", "manual"]


class ParseOrderRequest(BaseModel):
    raw_text: str = Field(min_length=3, max_length=500)
    channel: Channel
    customer_name: str = Field(min_length=2, max_length=120)
    order_date: date | None = None
    notes: str | None = Field(default=None, max_length=500)


class ParsedItem(BaseModel):
    line_text: str
    menu_sku: str
    menu_name: str
    quantity: float
    unit_price: float


class ParseWarning(BaseModel):
    code: WarningCode
    message: str
    line_text: str | None = None


class ParseOrderResponse(BaseModel):
    parse_id: UUID
    order_date: date
    confidence: float = Field(ge=0.0, le=1.0)
    items: list[ParsedItem]
    warnings: list[ParseWarning]


class HealthResponse(BaseModel):
    status: Literal["ok", "degraded", "down"]
    version: str
    checks: dict[str, Literal["ok", "degraded", "down"]]
    timestamp: datetime


class StockItem(BaseModel):
    id: int
    sku: str
    name: str
    unit: str
    stock_on_hand: float
    reorder_point: float
    unit_cost: float
    status: Literal["ok", "low", "out"]


class StockListResponse(BaseModel):
    source: Literal["redis", "postgres"]
    generated_at: datetime
    items: list[StockItem]


class StockDetailResponse(StockItem):
    used_in_menus: list[str]


class StockRequirementItem(BaseModel):
    ingredient_sku: str
    ingredient_name: str
    unit: str
    required: float
    available: float
    shortage: float


class CheckStockResponse(BaseModel):
    order_id: int
    order_code: str
    sufficient: bool
    checked_at: datetime
    requirements: list[StockRequirementItem]
    insufficient: list[StockRequirementItem]


class AllocateOrderRequest(BaseModel):
    expected_version: int | None = None


class AllocationDeductionItem(BaseModel):
    ingredient_sku: str
    quantity: float
    unit: str
    stock_after: float


class AllocateOrderResponse(BaseModel):
    order_id: int
    order_code: str
    status: str
    allocated_at: datetime
    deductions: list[AllocationDeductionItem]
    invalidated_cache_keys: list[str]


class SaveOrderItem(BaseModel):
    menu_sku: str = Field(min_length=1, max_length=64)
    quantity: float = Field(gt=0)


class SaveOrderRequest(BaseModel):
    raw_text: str = Field(min_length=3, max_length=500)
    channel: Channel
    customer_name: str = Field(min_length=2, max_length=120)
    order_date: date | None = None
    notes: str | None = Field(default=None, max_length=500)
    items: list[SaveOrderItem] = Field(min_length=1)


class SaveOrderResponse(BaseModel):
    order_id: int
    order_code: str
    status: str
    version: int
    order_date: date
    item_count: int
    total_amount: float
    created: bool


class OrderSummary(BaseModel):
    id: int
    order_code: str
    customer_name: str
    channel: str
    status: str
    order_date: date
    version: int
    item_count: int
    total_amount: float


class OrderListResponse(BaseModel):
    items: list[OrderSummary]


class OrderItemDetail(BaseModel):
    id: int
    menu_sku: str
    menu_name: str
    quantity: float
    unit_price: float
    line_total: float
    status: str
    qc_passed: bool


class OrderDetailResponse(BaseModel):
    id: int
    order_code: str
    customer_name: str
    channel: str
    status: str
    version: int
    order_date: date
    notes: str | None
    items: list[OrderItemDetail]
    sufficient: bool
    requirements: list[StockRequirementItem]
    insufficient: list[StockRequirementItem]


class ConfirmOrderResponse(BaseModel):
    order_id: int
    order_code: str
    status: str
    version: int
    sufficient: bool
    insufficient: list[StockRequirementItem]


class RecordQcRequest(BaseModel):
    checked_by: str = Field(min_length=2, max_length=120)
    note: str | None = Field(default=None, max_length=500)
    result: Literal["pass"] = "pass"


class RecordQcResponse(BaseModel):
    order_id: int
    order_item_id: int
    result: str
    qc_passed: bool
    recorded: int


class RestockRequest(BaseModel):
    quantity: float = Field(gt=0)


class RestockResponse(BaseModel):
    sku: str
    name: str
    unit: str
    stock_on_hand: float
    status: Literal["ok", "low", "out"]


class ListOrdersQuery(BaseModel):
    status: str | None = None
    order_date: date | None = None
    limit: int = Field(default=20, ge=1, le=100)


class DashboardOrders(BaseModel):
    total: int
    received: int
    parsed: int
    confirmed: int
    short: int
    qc_hold: int
    fulfilled: int
    parse_failed: int
    cancelled: int


class LowStockItem(BaseModel):
    sku: str
    name: str
    stock_on_hand: float
    reorder_point: float
    status: Literal["ok", "low", "out"]


class ShortOrderItem(BaseModel):
    order_id: int
    order_code: str
    customer_name: str


class TopMenuItem(BaseModel):
    menu_sku: str
    menu_name: str
    ordered_qty: float


class DashboardSummaryResponse(BaseModel):
    date: date
    source: Literal["redis", "postgres"]
    generated_at: datetime
    orders: DashboardOrders
    revenue_forecast: float
    low_stock: list[LowStockItem]
    short_orders: list[ShortOrderItem]
    top_menus: list[TopMenuItem]


class ForecastItem(BaseModel):
    menu_id: int
    menu_sku: str
    menu_name: str
    forecast_date: date
    forecast_qty: float
    confidence: float


class ForecastsResponse(BaseModel):
    from_date: date
    days: int
    model_version: str
    items: list[ForecastItem]
