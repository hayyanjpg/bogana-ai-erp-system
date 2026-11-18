from functools import lru_cache

from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    app_env: str = "development"
    database_url: str = ""
    redis_url: str = ""
    ai_service_token: str = "dev-internal-token"
    ai_service_auth_disabled: bool = True
    request_timeout_seconds: float = 5.0

    # Pembatasan laju per IP. 0 berarti kuota jenis tersebut dimatikan.
    rate_limit_enabled: bool = True
    rate_limit_window_seconds: int = 60
    rate_limit_read_requests: int = 120
    rate_limit_write_requests: int = 30

    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        extra="ignore",
    )


@lru_cache
def get_settings() -> Settings:
    return Settings()
