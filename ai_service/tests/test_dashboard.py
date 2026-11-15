from datetime import date


def test_dashboard_summary_returns_aggregates(client):
    response = client.get("/v1/dashboard/summary", params={"date": "2026-11-16"})

    assert response.status_code == 200
    body = response.json()
    assert body["date"] == "2026-11-16"
    assert body["source"] == "postgres"
    assert body["orders"]["total"] == 3
    assert body["orders"]["fulfilled"] == 1
    assert body["orders"]["cancelled"] == 0
    assert body["revenue_forecast"] == 945000.0

    low_stock_skus = {item["sku"] for item in body["low_stock"]}
    assert low_stock_skus == {"ING-KL-02", "ING-DJ-02"}
    assert body["short_orders"][0]["order_code"] == "ORD-20261115-002"
    assert body["top_menus"][0]["ordered_qty"] == 40.0


def test_dashboard_summary_uses_cache_for_same_date(client):
    first = client.get("/v1/dashboard/summary", params={"date": "2026-11-16"})
    second = client.get("/v1/dashboard/summary", params={"date": "2026-11-16"})

    assert first.json()["source"] == "postgres"
    assert second.json()["source"] == "redis"
    assert first.json()["generated_at"] == second.json()["generated_at"]


def test_dashboard_summary_refreshes_for_new_date(client, dashboard_repo):
    client.get("/v1/dashboard/summary", params={"date": "2026-11-16"})
    response = client.get("/v1/dashboard/summary", params={"date": "2026-11-17"})

    assert response.json()["source"] == "postgres"
    assert dashboard_repo.calls == [date(2026, 11, 16), date(2026, 11, 17)]
