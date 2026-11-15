from datetime import date


def test_forecasts_default_window(client, forecast_repo):
    response = client.get("/v1/forecasts", params={"from_date": "2026-11-16"})

    assert response.status_code == 200
    body = response.json()
    assert body["from_date"] == "2026-11-16"
    assert body["days"] == 7
    assert body["model_version"] == "bogana-forecast-v1"
    assert len(body["items"]) == 2
    assert body["items"][0]["menu_sku"] == "MNU-NBG-01"
    assert body["items"][0]["confidence"] == 0.82
    assert forecast_repo.calls == [(date(2026, 11, 16), date(2026, 11, 23), None)]


def test_forecasts_menu_filter(client, forecast_repo):
    response = client.get(
        "/v1/forecasts",
        params={"from_date": "2026-11-16", "menu_id": 1, "days": 3},
    )

    assert response.status_code == 200
    assert forecast_repo.calls == [(date(2026, 11, 16), date(2026, 11, 19), 1)]


def test_forecasts_rejects_days_out_of_range(client):
    response = client.get("/v1/forecasts", params={"days": 31})

    assert response.status_code == 400
    assert response.json()["error"]["code"] == "VALIDATION_ERROR"


def test_forecasts_empty_result(client, forecast_repo):
    forecast_repo.model_version = ""
    forecast_repo.rows = ()

    response = client.get("/v1/forecasts", params={"from_date": "2026-11-16"})

    assert response.status_code == 200
    body = response.json()
    assert body["items"] == []
    assert body["model_version"] == ""
