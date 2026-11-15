import secrets

import asyncpg
from fastapi import Depends, Header, Request
from redis.asyncio import Redis

from app.cache import ParseCache
from app.config import get_settings
from app.errors import UnauthorizedError
from app.repositories import MenuRepository
from app.services.parse_service import ParseOrderService


def get_pg_pool(request: Request) -> asyncpg.Pool | None:
    return getattr(request.app.state, "pool", None)


def get_redis_client(request: Request) -> Redis | None:
    return getattr(request.app.state, "redis", None)


def get_menu_repo(pool: asyncpg.Pool | None = Depends(get_pg_pool)) -> MenuRepository:
    return MenuRepository(pool)


def get_parse_cache(client: Redis | None = Depends(get_redis_client)) -> ParseCache:
    return ParseCache(client)


def get_parse_order_service(
    menu_repo: MenuRepository = Depends(get_menu_repo),
    cache: ParseCache = Depends(get_parse_cache),
) -> ParseOrderService:
    return ParseOrderService(menu_repo, cache)


def require_internal_token(x_internal_token: str | None = Header(default=None)) -> None:
    settings = get_settings()
    if settings.ai_service_auth_disabled:
        return
    if not x_internal_token or not secrets.compare_digest(
        x_internal_token, settings.ai_service_token
    ):
        raise UnauthorizedError("Token internal tidak valid")
