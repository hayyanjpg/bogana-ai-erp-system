from datetime import date, timedelta


def test_parse_order_contract_example(client):
    response = client.post(
        "/v1/parse-order",
        json={
            "raw_text": "gan besok pagi 40 nasi bogana komplit sama 15 ayam kecap ya",
            "channel": "whatsapp",
            "customer_name": "Warung Bu Sri",
        },
    )

    assert response.status_code == 200
    body = response.json()
    assert body["warnings"] == []
    assert body["confidence"] >= 0.5
    assert body["order_date"] == (date.today() + timedelta(days=1)).isoformat()

    items = {item["menu_sku"]: item for item in body["items"]}
    assert items["MNU-NBG-01"]["quantity"] == 40
    assert items["MNU-NBG-01"]["unit_price"] == 18000
    assert items["MNU-NBG-02"]["quantity"] == 15
    assert items["MNU-NBG-02"]["unit_price"] == 15000


def test_parse_order_unknown_menu_returns_warning(client):
    response = client.post(
        "/v1/parse-order",
        json={
            "raw_text": "gan 10 pizza hawaian",
            "channel": "manual",
            "customer_name": "Warung Bu Sri",
        },
    )

    assert response.status_code == 200
    body = response.json()
    assert body["items"] == []
    assert body["warnings"][0]["code"] == "MENU_NOT_FOUND"


def test_parse_order_missing_quantity_warns(client):
    response = client.post(
        "/v1/parse-order",
        json={
            "raw_text": "nasi bogana komplit",
            "channel": "phone",
            "customer_name": "Katering Ibu Hana",
        },
    )

    assert response.status_code == 200
    body = response.json()
    codes = {warning["code"] for warning in body["warnings"]}
    assert "QTY_MISSING" in codes
    assert body["items"][0]["quantity"] == 1


def test_parse_order_merges_duplicate_lines(client):
    response = client.post(
        "/v1/parse-order",
        json={
            "raw_text": "20 nasi bogana komplit, 5 nasi bogana komplit",
            "channel": "whatsapp",
            "customer_name": "Warung Bu Sri",
        },
    )

    assert response.status_code == 200
    body = response.json()
    assert len(body["items"]) == 1
    assert body["items"][0]["quantity"] == 25
    assert "DUPLICATE_LINE" in {warning["code"] for warning in body["warnings"]}


def test_parse_order_rejects_short_payload(client):
    response = client.post(
        "/v1/parse-order",
        json={
            "raw_text": "ab",
            "channel": "whatsapp",
            "customer_name": "Warung Bu Sri",
        },
    )

    assert response.status_code == 400
    body = response.json()
    assert body["error"]["code"] == "VALIDATION_ERROR"
    assert any(detail["field"] == "raw_text" for detail in body["error"]["details"])


def test_parse_order_is_cached(client):
    payload = {
        "raw_text": "gan 12 nasi bogana ayam kecap",
        "channel": "email",
        "customer_name": "Restoran Pak Joko",
    }

    first = client.post("/v1/parse-order", json=payload)
    second = client.post("/v1/parse-order", json=payload)

    assert first.status_code == 200
    assert second.status_code == 200
    assert first.json()["parse_id"] == second.json()["parse_id"]
