from app.db import normalize_dsn


def test_normalize_dsn_removes_channel_binding() -> None:
    dsn = (
        "postgresql://user:pass@ep-cool.ap-southeast-1.aws.neon.tech/bogana"
        "?sslmode=require&channel_binding=require"
    )

    assert normalize_dsn(dsn) == (
        "postgresql://user:pass@ep-cool.ap-southeast-1.aws.neon.tech/bogana?sslmode=require"
    )


def test_normalize_dsn_keeps_supported_params() -> None:
    dsn = "postgresql://user:pass@host:5432/bogana?sslmode=require&application_name=bogana"

    assert normalize_dsn(dsn) == dsn


def test_normalize_dsn_without_query() -> None:
    dsn = "postgresql://user:pass@host:5432/bogana"

    assert normalize_dsn(dsn) == dsn
