def test_list_stocks_returns_all_items(client):
    response = client.get("/v1/stocks")

    assert response.status_code == 200
    body = response.json()
    assert body["source"] == "postgres"
    assert len(body["items"]) == 5

    status_by_sku = {item["sku"]: item["status"] for item in body["items"]}
    assert status_by_sku["ING-TL-01"] == "ok"
    assert status_by_sku["ING-KL-02"] == "out"
    assert status_by_sku["ING-DJ-02"] == "low"


def test_list_stocks_uses_cache_on_second_call(client):
    first = client.get("/v1/stocks")
    second = client.get("/v1/stocks")

    assert first.json()["source"] == "postgres"
    assert second.json()["source"] == "redis"
    assert first.json()["generated_at"] == second.json()["generated_at"]


def test_list_stocks_low_only_filter(client):
    response = client.get("/v1/stocks", params={"low_only": "true"})

    assert response.status_code == 200
    skus = {item["sku"] for item in response.json()["items"]}
    assert skus == {"ING-KL-02", "ING-DJ-02"}


def test_list_stocks_category_filter(client):
    response = client.get("/v1/stocks", params={"category": "protein"})

    assert response.status_code == 200
    skus = {item["sku"] for item in response.json()["items"]}
    assert skus == {"ING-AY-01", "ING-TL-01"}


def test_get_stock_detail_includes_menu_usage(client):
    response = client.get("/v1/stocks/ING-AY-01")

    assert response.status_code == 200
    body = response.json()
    assert body["sku"] == "ING-AY-01"
    assert body["status"] == "ok"
    assert body["used_in_menus"] == ["MNU-NBG-01", "MNU-NBG-02"]


def test_get_unknown_stock_returns_404(client):
    response = client.get("/v1/stocks/ING-TIDAK-ADA")

    assert response.status_code == 404
    assert response.json()["error"]["code"] == "NOT_FOUND"
