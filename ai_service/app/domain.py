from dataclasses import dataclass
from datetime import datetime


@dataclass(frozen=True)
class Ingredient:
    id: int
    sku: str
    name: str
    unit: str
    stock_on_hand: float
    reorder_point: float
    unit_cost: float
    category: str


@dataclass(frozen=True)
class StockRequirement:
    ingredient_id: int
    ingredient_sku: str
    ingredient_name: str
    unit: str
    required: float
    available: float

    @property
    def shortage(self) -> float:
        return max(0.0, self.required - self.available)


@dataclass(frozen=True)
class OrderSnapshot:
    id: int
    order_code: str
    status: str
    version: int
    qc_passed: bool
    requirements: list[StockRequirement]


@dataclass(frozen=True)
class StockDeduction:
    ingredient_sku: str
    quantity: float
    unit: str
    stock_after: float


@dataclass(frozen=True)
class AllocationResult:
    order_id: int
    order_code: str
    status: str
    allocated_at: datetime
    deductions: list[StockDeduction]
