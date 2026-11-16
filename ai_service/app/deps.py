import secrets

import asyncpg
from fastapi import Depends, Header, Request
from redis.asyncio import Redis

from app.cache import JsonCache
from app.config import get_settings
from app.errors import UnauthorizedError
from app.repositories import (
    DashboardRepository,
    ForecastRepository,
    MenuRepository,
    OrderRepository,
    StockRepository,
)
from app.services.dashboard_service import DashboardService
from app.services.forecast_service import ForecastService
from app.services.order_service import OrderService
from app.services.parse_service import ParseOrderService
from app.services.stock_service import StockService


def get_pg_pool(request: Request) -> asyncpg.Pool | None:
    return getattr(request.app.state, "pool", None)


def get_redis_client(request: Request) -> Redis | None:
    return getattr(request.app.state, "redis", None)


def get_menu_repo(pool: asyncpg.Pool | None = Depends(get_pg_pool)) -> MenuRepository:
    return MenuRepository(pool)


def get_parse_cache(client: Redis | None = Depends(get_redis_client)) -> JsonCache:
    return JsonCache(client, "erp:parse:", ttl_seconds=900)


def get_parse_order_service(
    menu_repo: MenuRepository = Depends(get_menu_repo),
    cache: JsonCache = Depends(get_parse_cache),
) -> ParseOrderService:
    return ParseOrderService(menu_repo, cache)


def get_stock_repo(pool: asyncpg.Pool | None = Depends(get_pg_pool)) -> StockRepository:
    return StockRepository(pool)


def get_stock_cache(client: Redis | None = Depends(get_redis_client)) -> JsonCache:
    return JsonCache(client, "erp:stock:", ttl_seconds=60)


def get_dashboard_cache(client: Redis | None = Depends(get_redis_client)) -> JsonCache:
    return JsonCache(client, "erp:dashboard:", ttl_seconds=30)


def get_stock_service(
    repository: StockRepository = Depends(get_stock_repo),
    cache: JsonCache = Depends(get_stock_cache),
    dashboard_cache: JsonCache = Depends(get_dashboard_cache),
) -> StockService:
    service = StockService(repository, cache)
    service.set_dashboard_cache(dashboard_cache)
    return service


def get_order_repo(pool: asyncpg.Pool | None = Depends(get_pg_pool)) -> OrderRepository:
    return OrderRepository(pool)


def get_order_service(
    repository: OrderRepository = Depends(get_order_repo),
    stock_cache: JsonCache = Depends(get_stock_cache),
    dashboard_cache: JsonCache = Depends(get_dashboard_cache),
) -> OrderService:
    return OrderService(repository, stock_cache, dashboard_cache)


def get_dashboard_repo(pool: asyncpg.Pool | None = Depends(get_pg_pool)) -> DashboardRepository:
    return DashboardRepository(pool)


def get_forecast_repo(pool: asyncpg.Pool | None = Depends(get_pg_pool)) -> ForecastRepository:
    return ForecastRepository(pool)


def get_dashboard_service(
    repository: DashboardRepository = Depends(get_dashboard_repo),
    stock_repository: StockRepository = Depends(get_stock_repo),
    cache: JsonCache = Depends(get_dashboard_cache),
) -> DashboardService:
    return DashboardService(repository, stock_repository, cache)


def get_forecast_service(
    repository: ForecastRepository = Depends(get_forecast_repo),
) -> ForecastService:
    return ForecastService(repository)


def require_internal_token(x_internal_token: str | None = Header(default=None)) -> None:
    settings = get_settings()
    if settings.ai_service_auth_disabled:
        return
    if not x_internal_token or not secrets.compare_digest(
        x_internal_token, settings.ai_service_token
    ):
        raise UnauthorizedError("Token internal tidak valid")
