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
