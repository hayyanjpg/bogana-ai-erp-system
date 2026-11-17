"""Smoke test production untuk dashboard PHP dan AI service FastAPI.

Default bersifat read-only. Uji restock hanya berjalan bila SMOKE_ALLOW_WRITE=1
dan memakai kuantitas kecil pada SKU yang ditentukan.

Environment:
    SMOKE_WEB_URL     URL dashboard PHP (wajib)
    SMOKE_API_URL     URL AI service FastAPI (wajib)
    SMOKE_TOKEN       X-Internal-Token untuk uji API langsung (opsional)
    SMOKE_ALLOW_WRITE "1" untuk mengaktifkan uji restock (default: mati)
    SMOKE_SKU         SKU bahan untuk uji restock (default: ING-RS-01)
    SMOKE_QTY         Jumlah restock (default: 0.01)
    SMOKE_DASHBOARD_USER     Username login dashboard (opsional)
    SMOKE_DASHBOARD_PASSWORD Password login dashboard (opsional)
"""

from __future__ import annotations

import http.cookiejar
import json
import os
import re
import sys
import urllib.error
import urllib.parse
import urllib.request

Results = list[tuple[str, bool | None, str]]


def fetch(
    opener: urllib.request.OpenerDirector,
    url: str,
    data: bytes | None = None,
    headers: dict[str, str] | None = None,
    timeout: int = 45,
) -> tuple[int, str, dict[str, str]]:
    method = "POST" if data is not None else "GET"
    request = urllib.request.Request(
        url, data=data, headers=headers or {}, method=method
    )
    try:
        with opener.open(request, timeout=timeout) as response:
            body = response.read().decode("utf-8", "replace")
            return response.status, body, dict(response.headers)
    except urllib.error.HTTPError as exc:
        body = exc.read().decode("utf-8", "replace")
        return exc.code, body, dict(exc.headers)
    except urllib.error.URLError as exc:
        return 0, f"NETWORK_ERROR: {exc.reason}", {}


def form(
    opener: urllib.request.OpenerDirector, url: str, fields: dict[str, str]
) -> tuple[int, str]:
    data = urllib.parse.urlencode(fields).encode("utf-8")
    status, body, _ = fetch(
        opener,
        url,
        data=data,
        headers={"Content-Type": "application/x-www-form-urlencoded"},
    )
    return status, body


def text_only(html: str) -> str:
    html = re.sub(r"<script.*?</script>", " ", html, flags=re.DOTALL)
    html = re.sub(r"<style.*?</style>", " ", html, flags=re.DOTALL)
    text = re.sub(r"<[^>]+>", " ", html)
    return re.sub(r"\s+", " ", text).replace("&amp;", "&")


def is_read_only(html: str) -> bool:
    return 'data-demo-mode="read-only"' in html


def csrf_token(html: str) -> str:
    match = re.search(r'name="csrf_token" value="([a-f0-9]{64})"', html)
    return match.group(1) if match else ""


def parse_number(value: str) -> float:
    return float(value.replace(".", "").replace(",", "."))


def record(results: Results, name: str, ok: bool | None, detail: str = "") -> None:
    results.append((name, ok, detail))


def check_api(
    results: Results, opener: urllib.request.OpenerDirector, api: str
) -> None:
    status, body, _ = fetch(opener, api + "/")
    try:
        identity = json.loads(body)
    except json.JSONDecodeError:
        identity = {}
    record(
        results,
        "GET / (identitas service)",
        status == 200 and identity.get("service") == "Bogana AI Service",
        f"status={status}",
    )

    status, body, _ = fetch(opener, api + "/health")
    try:
        health = json.loads(body)
    except json.JSONDecodeError:
        health = {}
    checks = health.get("checks", {})
    record(
        results,
        "GET /health",
        status == 200 and health.get("status") in {"ok", "degraded"},
        f"status={status} health={health.get('status')} checks={checks}",
    )

    status, body, _ = fetch(opener, api + "/docs")
    record(
        results, "GET /docs", status == 200 and "Swagger UI" in body, f"status={status}"
    )

    status, body, _ = fetch(opener, api + "/openapi.json")
    try:
        schema = json.loads(body)
    except json.JSONDecodeError:
        schema = {}
    has_422 = False
    for path_item in schema.get("paths", {}).values():
        for operation in path_item.values():
            if isinstance(operation, dict) and "422" in operation.get("responses", {}):
                has_422 = True
    record(
        results,
        "OpenAPI tidak memakai 422",
        status == 200 and not has_422,
        f"status={status} has_422={has_422}",
    )


def is_login_page(html: str) -> bool:
    return "Masuk ke dashboard" in html


def dashboard_login(
    opener: urllib.request.OpenerDirector,
    web: str,
    username: str,
    password: str,
) -> bool:
    _, body, _ = fetch(opener, web + "/?page=login")
    token = csrf_token(body)
    if not token:
        return False

    status, body, _ = fetch(
        opener,
        web + "/?page=login",
        data=urllib.parse.urlencode(
            {"csrf_token": token, "username": username, "password": password}
        ).encode("utf-8"),
        headers={"Content-Type": "application/x-www-form-urlencoded"},
    )

    # Login dianggap berhasil bila server tidak lagi menampilkan halaman login.
    # Status akhir bisa 5xx bila AI service bermasalah; itu bukan kegagalan login.
    return status != 0 and not is_login_page(body)


def check_web(
    results: Results,
    opener: urllib.request.OpenerDirector,
    web: str,
    username: str,
    password: str,
) -> tuple[bool, bool]:
    _, body, _ = fetch(opener, web + "/")
    requires_login = is_login_page(body)
    authenticated = not requires_login

    if requires_login:
        if username and password:
            authenticated = dashboard_login(opener, web, username, password)
            record(
                results,
                "Login dashboard",
                authenticated,
                "berhasil" if authenticated else "gagal — periksa kredensial smoke",
            )
        else:
            record(
                results,
                "Login dashboard",
                None,
                "SMOKE_DASHBOARD_USER/PASSWORD tidak diisi",
            )

    pages = {
        "Dashboard": web + "/",
        "Orders": web + "/?page=orders",
        "Stocks": web + "/?page=stocks",
        "Forecasts": web + "/?page=forecasts",
        "Health": web + "/?page=health",
    }
    read_only = False
    for label, url in pages.items():
        status, body, _ = fetch(opener, url)
        if label == "Dashboard":
            read_only = is_read_only(body)
        if requires_login and not authenticated:
            record(results, f"Halaman {label}", None, "butuh login dashboard")
            continue
        record(
            results,
            f"Halaman {label}",
            status == 200
            and "Bogana AI ERP" in body
            and "INTERNAL_ERROR" not in body
            and not is_login_page(body),
            f"status={status}",
        )

    status, body, headers = fetch(opener, web + "/assets/app.css")
    record(
        results,
        "Static /assets/app.css",
        status == 200 and "text/css" in headers.get("Content-Type", ""),
        f"status={status} bytes={len(body)}",
    )

    status, body, _ = fetch(opener, web + "/healthz.php")
    record(
        results,
        "PHP /healthz.php",
        status == 200 and body.strip() == "ok",
        f"status={status}",
    )

    status, _, _ = fetch(opener, web + "/?page=does-not-exist")
    record(results, "Halaman 404", status == 404, f"status={status}")

    if requires_login:
        mode_detail = (
            "login aktif, terautentikasi"
            if authenticated
            else "login aktif, belum terautentikasi"
        )
    elif read_only:
        mode_detail = "demo (hanya baca)"
    else:
        mode_detail = "akses tulis terbuka"

    record(results, "Mode dashboard", True, mode_detail)

    return read_only, authenticated


def check_php_flows(
    results: Results,
    opener: urllib.request.OpenerDirector,
    web: str,
    read_only: bool,
    authenticated: bool,
) -> None:
    if not authenticated:
        for name in (
            "POST parse via PHP",
            "GET forecasts via PHP",
            "Validasi QC (checked_by terlalu panjang)",
        ):
            record(results, name, None, "butuh login dashboard")

        return

    status, body, _ = fetch(opener, web + "/?page=orders")
    token = csrf_token(body)
    record(
        results,
        "Form Orders + CSRF",
        status == 200 and bool(token),
        f"status={status} csrf={'ada' if token else 'tidak ada'}",
    )
    if read_only:
        record(
            results,
            "POST parse via PHP",
            None,
            "dashboard mode demo (hanya baca)",
        )
    elif status == 200 and token:
        status, body = form(
            opener,
            web + "/?page=orders",
            {
                "csrf_token": token,
                "action": "parse",
                "raw_text": "40 nasi bogana komplit",
                "channel": "whatsapp",
                "customer_name": "QA Smoke Test",
                "order_date": "",
                "notes": "",
            },
        )
        text = text_only(body)
        record(
            results,
            "POST parse via PHP",
            status == 200 and "Hasil Parse" in text and "INTERNAL_ERROR" not in text,
            f"status={status}",
        )

    status, body, _ = fetch(opener, web + "/?page=forecasts")
    text = text_only(body)
    record(
        results,
        "GET forecasts via PHP",
        status == 200
        and "Forecast Permintaan" in text
        and "INTERNAL_ERROR" not in text,
        f"status={status}",
    )

    if read_only:
        record(
            results,
            "Validasi QC (checked_by terlalu panjang)",
            None,
            "dashboard mode demo (hanya baca)",
        )

        return

    status, body, _ = fetch(opener, web + "/?page=orders")
    token = csrf_token(body)
    if token:
        status, body = form(
            opener,
            web + "/?page=orders",
            {
                "csrf_token": token,
                "action": "qc",
                "order_id": "1",
                "item_id": "1",
                "checked_by": "x" * 150,
            },
        )
        text = text_only(body)
        record(
            results,
            "Validasi QC (checked_by terlalu panjang)",
            status == 200
            and "VALIDATION_ERROR" in text
            and "INTERNAL_ERROR" not in text,
            f"status={status}",
        )


def check_restock(
    results: Results,
    opener: urllib.request.OpenerDirector,
    web: str,
    sku: str,
    quantity: str,
    read_only: bool,
    authenticated: bool,
) -> None:
    if not authenticated:
        record(
            results,
            f"POST restock {sku} qty={quantity}",
            None,
            "butuh login dashboard",
        )
        return

    if read_only:
        record(
            results,
            f"POST restock {sku} qty={quantity}",
            None,
            "dashboard mode demo (hanya baca)",
        )
        return

    status, body, _ = fetch(opener, f"{web}/?page=stocks&sku={urllib.parse.quote(sku)}")
    text = text_only(body)
    before_match = re.search(r"Stok vs Reorder Point\s+([0-9.,]+)", text)
    token = csrf_token(body)
    if status != 200 or not token or before_match is None:
        record(
            results,
            f"Restock {sku} (persiapan)",
            False,
            f"status={status} csrf={'ada' if token else 'tidak ada'}",
        )
        return

    before = parse_number(before_match.group(1))
    status, body = form(
        opener,
        f"{web}/?page=stocks&sku={urllib.parse.quote(sku)}",
        {
            "csrf_token": token,
            "action": "restock",
            "sku": sku,
            "detail_sku": sku,
            "category": "",
            "low_only": "0",
            "quantity": quantity,
        },
    )
    text = text_only(body)
    success = "Restock berhasil" in text and "INTERNAL_ERROR" not in text
    record(
        results,
        f"POST restock {sku} qty={quantity}",
        status == 200 and success,
        f"status={status} pesan={'berhasil' if success else 'gagal'}",
    )

    if not success:
        return

    status, body, _ = fetch(opener, f"{web}/?page=stocks&sku={urllib.parse.quote(sku)}")
    after_match = re.search(r"Stok vs Reorder Point\s+([0-9.,]+)", text_only(body))
    after = parse_number(after_match.group(1)) if after_match else before
    record(
        results,
        f"Stok {sku} bertambah",
        after > before,
        f"sebelum={before} sesudah={after}",
    )


def main() -> int:
    web = os.environ.get("SMOKE_WEB_URL", "").rstrip("/")
    api = os.environ.get("SMOKE_API_URL", "").rstrip("/")
    if not web or not api:
        print("SMOKE_WEB_URL dan SMOKE_API_URL wajib diisi.")
        return 2

    allow_write = os.environ.get("SMOKE_ALLOW_WRITE", "0") in {"1", "true", "yes"}
    sku = os.environ.get("SMOKE_SKU", "ING-RS-01")
    quantity = os.environ.get("SMOKE_QTY", "0.01")
    token = os.environ.get("SMOKE_TOKEN", "")
    dashboard_user = os.environ.get("SMOKE_DASHBOARD_USER", "")
    dashboard_password = os.environ.get("SMOKE_DASHBOARD_PASSWORD", "")

    jar = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
    opener.addheaders = [
        ("User-Agent", "BoganaProductionSmoke/1.0"),
        ("Accept", "text/html,application/json"),
    ]

    results: Results = []
    check_api(results, opener, api)
    read_only, authenticated = check_web(
        results, opener, web, dashboard_user, dashboard_password
    )

    if token:
        status, body, _ = fetch(
            opener,
            api + "/v1/parse-order",
            data=json.dumps(
                {
                    "raw_text": "40 nasi bogana komplit",
                    "channel": "whatsapp",
                    "customer_name": "QA Smoke Test",
                }
            ).encode("utf-8"),
            headers={
                "Content-Type": "application/json",
                "X-Internal-Token": token,
            },
        )
        try:
            parsed = json.loads(body)
        except json.JSONDecodeError:
            parsed = {}
        record(
            results,
            "POST /v1/parse-order langsung",
            status == 200 and bool(parsed.get("items")),
            f"status={status}",
        )
    else:
        record(
            results, "POST /v1/parse-order langsung", None, "SMOKE_TOKEN tidak diisi"
        )

    check_php_flows(results, opener, web, read_only, authenticated)

    if allow_write:
        check_restock(results, opener, web, sku, quantity, read_only, authenticated)
    else:
        record(results, "Restock (write)", None, "SMOKE_ALLOW_WRITE tidak diaktifkan")

    failed = 0
    skipped = 0
    for name, ok, detail in results:
        if ok is None:
            skipped += 1
            label = "SKIP"
        elif ok:
            label = "PASS"
        else:
            label = "FAIL"
            failed += 1
        suffix = f" — {detail}" if detail else ""
        print(f"[{label}] {name}{suffix}")

    print(
        f"\nTotal: {len(results)} | PASS: {len(results) - failed - skipped} | FAIL: {failed} | SKIP: {skipped}"
    )
    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main())
