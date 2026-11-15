from datetime import UTC, datetime

from fastapi import APIRouter, Depends
from fastapi.responses import JSONResponse

from app.deps import get_pg_pool, get_redis_client
from app.schemas import HealthResponse

router = APIRouter(tags=["health"])


@router.get("/health", response_model=HealthResponse)
async def health_check(
    pool=Depends(get_pg_pool),
    redis_client=Depends(get_redis_client),
) -> JSONResponse | HealthResponse:
    postgres_status = "down"
    if pool is not None:
        try:
            await pool.fetchval("SELECT 1")
            postgres_status = "ok"
        except Exception:
            postgres_status = "down"

    redis_status = "down"
    if redis_client is not None:
        try:
            await redis_client.ping()
            redis_status = "ok"
        except Exception:
            redis_status = "degraded"

    status = "ok" if postgres_status == "ok" else "down"
    if status == "ok" and redis_status != "ok":
        status = "degraded"

    payload = HealthResponse(
        status=status,
        version="1.0.0",
        checks={"postgres": postgres_status, "redis": redis_status},
        timestamp=datetime.now(UTC),
    )

    if status == "down":
        return JSONResponse(status_code=503, content=payload.model_dump(mode="json"))
    return payload
