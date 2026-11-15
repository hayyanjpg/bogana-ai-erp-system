import logging
from collections.abc import AsyncIterator
from contextlib import asynccontextmanager

from fastapi import FastAPI, Request
from fastapi.exceptions import RequestValidationError
from fastapi.responses import JSONResponse
from starlette.exceptions import HTTPException as StarletteHTTPException

from app.cache import create_redis_client
from app.config import get_settings
from app.db import close_pool, create_pool
from app.errors import AppError
from app.routers import health, orders

logger = logging.getLogger("bogana.ai_service")


@asynccontextmanager
async def lifespan(app: FastAPI) -> AsyncIterator[None]:
    settings = get_settings()
    app.state.pool = None
    app.state.redis = None

    try:
        app.state.pool = await create_pool(settings.database_url)
    except Exception:
        logger.exception("Gagal membuat connection pool PostgreSQL")

    try:
        client = create_redis_client(settings.redis_url)
        if client is not None:
            await client.ping()
        app.state.redis = client
    except Exception:
        logger.exception("Gagal menghubungkan Redis")
        app.state.redis = None

    yield

    await close_pool(app.state.pool)
    if app.state.redis is not None:
        await app.state.redis.aclose()


def _error_payload(
    code: str,
    message: str,
    details: list[dict[str, str]] | None = None,
) -> dict[str, object]:
    error: dict[str, object] = {"code": code, "message": message}
    if details:
        error["details"] = details
    return {"error": error}


async def handle_app_error(request: Request, exc: AppError) -> JSONResponse:
    return JSONResponse(
        status_code=exc.status_code,
        content=_error_payload(exc.code, exc.message, exc.details),
    )


async def handle_validation_error(request: Request, exc: RequestValidationError) -> JSONResponse:
    details: list[dict[str, str]] = []
    for error in exc.errors():
        location = [str(part) for part in error.get("loc", []) if part != "body"]
        details.append(
            {
                "field": ".".join(location),
                "issue": str(error.get("msg", "invalid")),
            }
        )
    return JSONResponse(
        status_code=400,
        content=_error_payload("VALIDATION_ERROR", "Payload tidak valid", details),
    )


async def handle_http_exception(
    request: Request,
    exc: StarletteHTTPException,
) -> JSONResponse:
    code = "NOT_FOUND" if exc.status_code == 404 else "HTTP_ERROR"
    message = str(exc.detail) if exc.detail else "Permintaan tidak dapat diproses"
    return JSONResponse(
        status_code=exc.status_code,
        content=_error_payload(code, message),
    )


async def handle_unexpected_error(request: Request, exc: Exception) -> JSONResponse:
    logger.exception("Kesalahan tak terduga", exc_info=exc)
    return JSONResponse(
        status_code=500,
        content=_error_payload("INTERNAL_ERROR", "Kesalahan internal server"),
    )


def create_app() -> FastAPI:
    app = FastAPI(title="Bogana AI Service", version="1.0.0", lifespan=lifespan)
    app.include_router(health.router)
    app.include_router(orders.router)
    app.add_exception_handler(AppError, handle_app_error)
    app.add_exception_handler(RequestValidationError, handle_validation_error)
    app.add_exception_handler(StarletteHTTPException, handle_http_exception)
    app.add_exception_handler(Exception, handle_unexpected_error)
    return app


app = create_app()
