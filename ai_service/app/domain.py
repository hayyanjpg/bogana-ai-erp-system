from dataclasses import dataclass


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
