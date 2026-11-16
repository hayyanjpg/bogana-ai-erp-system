# API Contract — PHP (Dashboard) ↔ FastAPI (AI Service)

Kontrak ini mengikat semua komunikasi JSON antara **PHP** (dashboard/admin ERP)
dan **FastAPI** (`ai_service/`). Nama field, tipe, dan status code di sini bersifat
**normatif**. Jika kontrak berubah, dokumen ini wajib diperbarui pada commit yang sama.

- Base URL (docker-compose): `http://ai_service:8000`
- Format: `application/json; charset=utf-8`
- Timezone: seluruh timestamp bertipe ISO-8601 dengan offset, contoh `2026-11-16T04:10:00+07:00`
- ID resource: integer (`BIGINT`) sesuai `database/schema.sql`
- Versi API: prefix `/v1`. Perubahan yang tidak kompatibel wajib menaikkan versi.

---

## 1. Konvensi umum

### 1.1 Autentikasi

- Service-to-service memakai header `X-Internal-Token: <token>`.
- Token dibaca dari environment variable `AI_SERVICE_TOKEN` (FastAPI) dan
  `AI_SERVICE_TOKEN` (PHP). Dilarang menulis token di kode.
- Localhost/dev boleh menonaktifkan lewat `AI_SERVICE_AUTH_DISABLED=true`.

### 1.2 Format error

Semua error mengikuti envelope berikut dan **tidak pernah** mengembalikan
`{"detail": ...}` gaya FastAPI default:

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "quantity harus lebih besar dari 0",
    "details": [
      { "field": "items.0.quantity", "issue": "must be greater than 0" }
    ]
  }
}
```

| HTTP | `error.code` | Kapan dipakai |
| --- | --- | --- |
| 400 | `VALIDATION_ERROR` | Body tidak lolos validasi Pydantic |
| 401 | `UNAUTHORIZED` | Token hilang/salah |
| 404 | `NOT_FOUND` | Order/menu/ingredient tidak ada |
| 409 | `CONFLICT` | Idempotency key bentrok atau status order tidak mengizinkan aksi |
| 422 | `BUSINESS_RULE_VIOLATION` | Melanggar aturan bisnis (mis. stok negatif) |
| 429 | `RATE_LIMITED` | Melebihi batas pemanggilan parser |
| 500 | `INTERNAL_ERROR` | Kesalahan tak terduga |
| 503 | `DEPENDENCY_UNAVAILABLE` | PostgreSQL/Redis tidak dapat dihubungi |

### 1.3 Idempotensi

- Endpoint yang mengubah state (`POST`) menerima header `Idempotency-Key` (opsional,
  maksimal 64 karakter).
- Key yang sama dengan payload sama mengembalikan response pertama (200) tanpa
  mengeksekusi ulang. Key sama dengan payload berbeda -> `409 CONFLICT`.
- Tanpa key, `POST /v1/parse-order` menyimpan cache hasil pada Redis
  `erp:parse:{sha1(raw_text)}` selama 15 menit.

---

## 2. `GET /` dan `GET /health`

`GET /` (tanpa autentikasi) mengembalikan identitas service:

```json
{
  "service": "Bogana AI Service",
  "version": "1.0.0",
  "docs": "/docs",
  "health": "/health"
}
```

`GET /health` untuk docker healthcheck dan monitoring.

**Response 200**

```json
{
  "status": "ok",
  "version": "1.0.0",
  "checks": {
    "postgres": "ok",
    "redis": "ok"
  },
  "timestamp": "2026-11-16T04:10:00+07:00"
}
```

| `checks.*` | Nilai |
| --- | --- |
| `ok` | Koneksi sehat |
| `degraded` | Redis gagal, PostgreSQL masih ok (fail-open) |
| `down` | Koneksi gagal total |

Jika salah satu `down`, response tetap 200 dengan `status: "degraded"`; hanya
`status: "down"` (PostgreSQL mati) yang menghasilkan 503.

---

## 3. `POST /v1/parse-order`

Mengubah teks order bebas menjadi item menu terstruktur. **Tidak** mengubah state
order dan tidak mengecek stok.

### 3.1 Request

```json
{
  "raw_text": "gan besok pagi 40 nasi bogana komplit sama 15 ayam kecap",
  "channel": "whatsapp",
  "customer_name": "Warung Bu Sri",
  "order_date": "2026-11-16",
  "notes": "diantar sebelum jam 7"
}
```

| Field | Tipe | Wajib | Aturan |
| --- | --- | --- | --- |
| `raw_text` | string | ya | 3–500 karakter, tidak boleh kosong |
| `channel` | string | ya | `whatsapp` \| `email` \| `phone` \| `manual` |
| `customer_name` | string | ya | 2–120 karakter |
| `order_date` | string(date) | tidak | default `besok` relatif waktu server |
| `notes` | string \| null | tidak | maks. 500 karakter |

### 3.2 Response 200

```json
{
  "parse_id": "5f1c0a9e-7c2b-4d3f-9a1e-2b7c8d9e0f11",
  "order_date": "2026-11-16",
  "confidence": 0.88,
  "items": [
    {
      "line_text": "40 nasi bogana komplit",
      "menu_sku": "MNU-NBG-01",
      "menu_name": "Nasi Bogana Komplit",
      "quantity": 40,
      "unit_price": 18000.00
    },
    {
      "line_text": "15 ayam kecap",
      "menu_sku": "MNU-NBG-02",
      "menu_name": "Nasi Bogana Ayam Kecap",
      "quantity": 15,
      "unit_price": 15000.00
    }
  ],
  "warnings": []
}
```

| Field | Tipe | Catatan |
| --- | --- | --- |
| `parse_id` | string(uuid) | Identitas hasil parse untuk audit/log |
| `confidence` | number | 0.0–1.0, rata-rata keyakinan parser |
| `items[].quantity` | number | > 0, satuan porsi |
| `items[].unit_price` | number | Diambil dari `menus.base_price` |
| `warnings` | array | Lihat tabel di bawah |

### 3.3 Warning codes

| `code` | Arti | Perilaku PHP |
| --- | --- | --- |
| `MENU_NOT_FOUND` | Teks menyebut menu yang tidak ada di `menus.sku` | Tampilkan ke admin, jangan tebak |
| `QTY_MISSING` | Menu dikenali tapi kuantitas tidak ada | Minta admin melengkapi |
| `QTY_AMBIGUOUS` | Kuantitas ambigu (" beberapa ") | Minta konfirmasi |
| `DUPLICATE_LINE` | Menu sama muncul beberapa kali | Sudah digabung otomatis |
| `LOW_CONFIDENCE` | `confidence` < 0.5 | Wajib review admin |

Response tetap 200 meski ada warning; order **tidak** otomatis disimpan.

---

## 4. `GET /v1/stocks`

Daftar posisi stok bahan baku, dilayani dari cache Redis bila masih fresh
(`erp:stock:all`, TTL 60 detik).

### 4.1 Query

| Param | Tipe | Default | Aturan |
| --- | --- | --- | --- |
| `low_only` | bool | `false` | Hanya `stock_on_hand <= reorder_point` |
| `category` | string | - | Filter kategori bahan |

### 4.2 Response 200

```json
{
  "source": "redis",
  "generated_at": "2026-11-16T04:10:00+07:00",
  "items": [
    {
      "id": 1,
      "sku": "ING-RS-01",
      "name": "Beras Pandan Wangi",
      "unit": "kg",
      "stock_on_hand": 850.000,
      "reorder_point": 100.000,
      "unit_cost": 14000.00,
      "status": "ok"
    },
    {
      "id": 7,
      "sku": "ING-TL-01",
      "name": "Telur Ayam",
      "unit": "pcs",
      "stock_on_hand": 720.000,
      "reorder_point": 150.000,
      "unit_cost": 2500.00,
      "status": "ok"
    }
  ]
}
```

| `source` | Nilai |
| --- | --- |
| `redis` | Cache hit |
| `postgres` | Cache miss / Redis degraded |

| `status` | Aturan |
| --- | --- |
| `out` | `stock_on_hand = 0` |
| `low` | `0 < stock_on_hand <= reorder_point` |
| `ok` | `stock_on_hand > reorder_point` |

---

## 5. `GET /v1/stocks/{sku}`

Detail satu bahan baku.

**Response 200**

```json
{
  "id": 2,
  "sku": "ING-AY-01",
  "name": "Ayam Fillet",
  "unit": "kg",
  "stock_on_hand": 120.000,
  "reorder_point": 25.000,
  "unit_cost": 38000.00,
  "status": "ok",
  "used_in_menus": ["MNU-NBG-01", "MNU-NBG-02"]
}
```

404 `NOT_FOUND` bila `sku` tidak terdaftar.

---

## 6. `POST /v1/orders/{order_id}/check-stock`

Menghitung kebutuhan bahan dari `menu_recipes` untuk seluruh `order_items` pada
order tersebut, lalu membandingkan dengan `ingredients.stock_on_hand`.

### 6.1 Request

```json
{}
```

### 6.2 Response 200

```json
{
  "order_id": 1,
  "order_code": "ORD-20261115-001",
  "sufficient": true,
  "checked_at": "2026-11-16T04:12:00+07:00",
  "requirements": [
    {
      "ingredient_sku": "ING-AY-01",
      "ingredient_name": "Ayam Fillet",
      "unit": "kg",
      "required": 8.700,
      "available": 120.000,
      "shortage": 0.000
    },
    {
      "ingredient_sku": "ING-KC-01",
      "ingredient_name": "Kecap Manis",
      "unit": "liter",
      "required": 1.175,
      "available": 60.000,
      "shortage": 0.000
    },
    {
      "ingredient_sku": "ING-TL-01",
      "ingredient_name": "Telur Ayam",
      "unit": "pcs",
      "required": 40.000,
      "available": 720.000,
      "shortage": 0.000
    }
  ],
  "insufficient": []
}
```

- `sufficient = true` bila `insufficient` kosong.
- `requirements` berisi **seluruh** bahan yang dibutuhkan, termasuk yang cukup.
- `shortage` selalu `>= 0`.
- Order dengan status `fulfilled`/`cancelled` -> `409 CONFLICT`.

---

## 7. `POST /v1/orders/{order_id}/allocate`

Mengalokasikan stok bahan untuk order dalam **satu transaksi PostgreSQL**.
Memotong `ingredients.stock_on_hand` dan mengubah status order
`confirmed -> fulfilled` (lihat `docs/PRD_ERP_FLOW.md`).

### 7.1 Request

```json
{
  "expected_version": 3
}
```

| Field | Tipe | Wajib | Aturan |
| --- | --- | --- | --- |
| `expected_version` | integer | tidak | Optimistic locking; bentrok -> 409 |

### 7.2 Response 200

```json
{
  "order_id": 1,
  "order_code": "ORD-20261115-001",
  "status": "fulfilled",
  "allocated_at": "2026-11-16T04:15:00+07:00",
  "deductions": [
    { "ingredient_sku": "ING-AY-01", "quantity": 8.700, "unit": "kg", "stock_after": 111.300 },
    { "ingredient_sku": "ING-KC-01", "quantity": 1.175, "unit": "liter", "stock_after": 58.825 },
    { "ingredient_sku": "ING-TL-01", "quantity": 40.000, "unit": "pcs", "stock_after": 680.000 }
  ],
  "invalidated_cache_keys": ["erp:stock:all", "erp:dashboard:summary"]
}
```

### 7.3 Aturan

- Seluruh pemotongan berada dalam satu transaksi; kegagalan sekecil apa pun ->
  rollback penuh dan `500 INTERNAL_ERROR`.
- Stok tidak boleh menjadi negatif -> `422 BUSINESS_RULE_VIOLATION` dengan
  `error.code = "INSUFFICIENT_STOCK"`.
- Order yang belum `confirmed` -> `409 CONFLICT`.
- Order wajib memiliki minimal satu item -> `409 CONFLICT`.
- Seluruh `order_items` wajib memiliki minimal satu `qc_logs` berstatus `pass`
  (lihat `docs/PRD_ERP_FLOW.md` bagian 6) -> `409 CONFLICT` bila belum.
- `expected_version` yang tidak cocok dengan versi order saat transaksi ->
  `409 CONFLICT`.
- Respons sukses wajib menyertakan daftar key cache yang diinvalidasi.

---

## 8. `GET /v1/dashboard/summary`

Agregat untuk dashboard PHP. Dilayani dari Redis `erp:dashboard:summary` (TTL 30 s).

### 8.1 Query

| Param | Tipe | Default | Aturan |
| --- | --- | --- | --- |
| `date` | string(date) | hari ini | - |

### 8.2 Response 200

```json
{
  "date": "2026-11-16",
  "source": "redis",
  "generated_at": "2026-11-16T04:10:00+07:00",
  "orders": {
    "total": 3,
    "received": 0,
    "parsed": 1,
    "confirmed": 0,
    "short": 1,
    "qc_hold": 0,
    "fulfilled": 1,
    "parse_failed": 0,
    "cancelled": 0
  },
  "revenue_forecast": 945000.00,
  "low_stock": [
    { "sku": "ING-DJ-01", "name": "Daun Jeruk Purut", "stock_on_hand": 5.000, "reorder_point": 1.000, "status": "ok" }
  ],
  "short_orders": [
    { "order_id": 2, "order_code": "ORD-20261115-002", "customer_name": "Katering Dapur Ibu Hana" }
  ],
  "top_menus": [
    { "menu_sku": "MNU-NBG-01", "menu_name": "Nasi Bogana Komplit", "ordered_qty": 40.00 }
  ]
}
```

- `orders.*` wajib berisi seluruh nilai pada `b2b_orders.status`, termasuk yang 0.
- `revenue_forecast` = total `line_total` order berstatus `confirmed` + `fulfilled`
  pada tanggal tersebut.
- `low_stock` hanya berisi bahan dengan `status` `low` atau `out`.
- `top_menus` berisi maksimal 5 menu dengan `ordered_qty` terbesar pada tanggal
  tersebut.
- Cache `erp:dashboard:summary` hanya dipakai bila `date` di dalam cache sama
  dengan `date` yang diminta; selain itu data diambil ulang dari PostgreSQL.

---

## 9. `GET /v1/forecasts`

Prakiraan permintaan per menu dari `demand_forecasts`.

### 9.1 Query

| Param | Tipe | Default | Aturan |
| --- | --- | --- | --- |
| `menu_id` | integer | - | Filter satu menu |
| `days` | integer | `7` | 1–30 |
| `from_date` | string(date) | hari ini | - |

### 9.2 Response 200

```json
{
  "from_date": "2026-11-16",
  "days": 7,
  "model_version": "bogana-forecast-v1",
  "items": [
    {
      "menu_id": 1,
      "menu_sku": "MNU-NBG-01",
      "menu_name": "Nasi Bogana Komplit",
      "forecast_date": "2026-11-16",
      "forecast_qty": 46.00,
      "confidence": 0.820
    }
  ]
}
```

- Diurutkan `forecast_date ASC, menu_id ASC`.
- `confidence` bernilai 0.000–1.000.

---

## 10. Ringkasan endpoint

| Method | Path | Fungsi | Cache |
| --- | --- | --- | --- |
| GET | `/` | Identitas service | - |
| GET | `/health` | Health check | - |
| POST | `/v1/parse-order` | Teks -> item menu | Redis 15 m |
| GET | `/v1/stocks` | Daftar stok | Redis 60 s |
| GET | `/v1/stocks/{sku}` | Detail stok | Redis 60 s |
| POST | `/v1/orders/{order_id}/check-stock` | Cek kecukupan bahan | - |
| POST | `/v1/orders/{order_id}/allocate` | Alokasi stok (transaksi) | invalidasi cache |
| GET | `/v1/dashboard/summary` | Agregat dashboard | Redis 30 s |
| GET | `/v1/forecasts` | Forecast per menu | - |

## 11. Aturan versi

1. Menambah field opsional = perubahan minor, tetap `/v1`.
2. Menghapus/mengganti nama field atau mengubah tipe = wajib `/v2` dan
   mempertahankan `/v1` selama masa transisi.
3. Semua perubahan kontrak wajib disertai test kontrak di `tests/`.