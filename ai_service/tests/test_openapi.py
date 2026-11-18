def test_openapi_tidak_lagi_mendokumentasikan_422(client):
    schema = client.get("/openapi.json").json()

    for path, path_item in schema["paths"].items():
        for method, operation in path_item.items():
            responses = operation.get("responses", {})
            assert "422" not in responses, f"{method.upper()} {path} masih memakai 422"


def test_openapi_parse_order_mendokumentasikan_400(client):
    schema = client.get("/openapi.json").json()
    responses = schema["paths"]["/v1/parse-order"]["post"]["responses"]

    assert responses["400"]["description"] == "Payload tidak valid"


def test_openapi_mendokumentasikan_429_di_endpoint_v1(client):
    schema = client.get("/openapi.json").json()
    responses = schema["paths"]["/v1/parse-order"]["post"]["responses"]

    assert "Retry-After" in responses["429"]["headers"]
    assert "429" not in schema["paths"]["/health"]["get"]["responses"]


def test_openapi_qc_mewajibkan_request_body(client):
    schema = client.get("/openapi.json").json()
    operation = schema["paths"]["/v1/orders/{order_id}/items/{item_id}/qc"]["post"]

    assert operation["requestBody"]["required"] is True
