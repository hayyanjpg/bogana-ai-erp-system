-- =============================================================================
-- bogana-ai-erp-system — skema PostgreSQL lengkap + seed data
-- Sumber kebenaran struktur data. Jalankan dari nol:
--   docker compose exec -T db psql -U postgres -d bogana -f - < database/schema.sql
-- File ini idempoten: aman dijalankan ulang (drop -> create -> seed).
-- =============================================================================

BEGIN;

DROP TABLE IF EXISTS demand_forecasts;
DROP TABLE IF EXISTS qc_logs;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS b2b_orders;
DROP TABLE IF EXISTS menu_recipes;
DROP TABLE IF EXISTS menus;
DROP TABLE IF EXISTS ingredients;
DROP FUNCTION IF EXISTS bogana_touch_updated_at();

-- -----------------------------------------------------------------------------
-- 1. ingredients — bahan baku + posisi stok
-- -----------------------------------------------------------------------------
CREATE TABLE ingredients (
    id             BIGSERIAL PRIMARY KEY,
    sku            TEXT           NOT NULL UNIQUE,
    name           TEXT           NOT NULL,
    category       TEXT           NOT NULL DEFAULT 'bahan',
    unit           TEXT           NOT NULL CHECK (unit IN ('kg', 'liter', 'pcs', 'pack')),
    stock_on_hand  NUMERIC(12, 3) NOT NULL DEFAULT 0 CHECK (stock_on_hand >= 0),
    reorder_point  NUMERIC(12, 3) NOT NULL DEFAULT 0 CHECK (reorder_point >= 0),
    unit_cost      NUMERIC(12, 2) NOT NULL DEFAULT 0 CHECK (unit_cost >= 0),
    supplier       TEXT,
    is_active      BOOLEAN        NOT NULL DEFAULT TRUE,
    created_at     TIMESTAMPTZ    NOT NULL DEFAULT now(),
    updated_at     TIMESTAMPTZ    NOT NULL DEFAULT now()
);

COMMENT ON TABLE ingredients IS 'Bahan baku dan posisi stok; stock_on_hand tidak boleh negatif.';

-- -----------------------------------------------------------------------------
-- 2. menus — produk yang dijual ke pelanggan B2B
-- -----------------------------------------------------------------------------
CREATE TABLE menus (
    id           BIGSERIAL PRIMARY KEY,
    sku          TEXT           NOT NULL UNIQUE,
    name         TEXT           NOT NULL,
    category     TEXT           NOT NULL DEFAULT 'makanan',
    base_price   NUMERIC(12, 2) NOT NULL CHECK (base_price >= 0),
    description  TEXT,
    is_active    BOOLEAN        NOT NULL DEFAULT TRUE,
    created_at   TIMESTAMPTZ    NOT NULL DEFAULT now(),
    updated_at   TIMESTAMPTZ    NOT NULL DEFAULT now()
);

COMMENT ON TABLE menus IS 'Katalog menu; sku dipakai oleh NLP parser sebagai kunci hasil parse.';

-- -----------------------------------------------------------------------------
-- 3. menu_recipes — bill of materials (BOM) per menu
-- -----------------------------------------------------------------------------
CREATE TABLE menu_recipes (
    id             BIGSERIAL PRIMARY KEY,
    menu_id        BIGINT        NOT NULL REFERENCES menus (id)      ON DELETE RESTRICT,
    ingredient_id  BIGINT        NOT NULL REFERENCES ingredients (id) ON DELETE RESTRICT,
    quantity       NUMERIC(10, 3) NOT NULL CHECK (quantity > 0),
    created_at     TIMESTAMPTZ   NOT NULL DEFAULT now(),
    CONSTRAINT uq_menu_recipes UNIQUE (menu_id, ingredient_id)
);

COMMENT ON TABLE menu_recipes IS 'Resep: jumlah bahan per 1 porsi menu.';

-- -----------------------------------------------------------------------------
-- 4. b2b_orders — header order
-- -----------------------------------------------------------------------------
CREATE TABLE b2b_orders (
    id             BIGSERIAL PRIMARY KEY,
    order_code     TEXT           NOT NULL UNIQUE,
    customer_name  TEXT           NOT NULL,
    customer_phone TEXT,
    channel        TEXT           NOT NULL DEFAULT 'manual'
                                  CHECK (channel IN ('whatsapp', 'email', 'phone', 'manual')),
    raw_text       TEXT           NOT NULL,
    status         TEXT           NOT NULL DEFAULT 'received'
                                  CHECK (status IN ('received', 'parsed', 'confirmed', 'short',
                                                   'qc_hold', 'fulfilled', 'parse_failed', 'cancelled')),
    order_date     DATE           NOT NULL DEFAULT CURRENT_DATE,
    notes          TEXT,
    version        INTEGER        NOT NULL DEFAULT 1 CHECK (version >= 1),
    created_at     TIMESTAMPTZ    NOT NULL DEFAULT now(),
    updated_at     TIMESTAMPTZ    NOT NULL DEFAULT now()
);

COMMENT ON TABLE b2b_orders IS 'Header order B2B. raw_text menyimpan teks asli untuk audit parser.';

CREATE INDEX idx_b2b_orders_status      ON b2b_orders (status);
CREATE INDEX idx_b2b_orders_order_date  ON b2b_orders (order_date DESC);

-- -----------------------------------------------------------------------------
-- 5. order_items — detail item per order
-- -----------------------------------------------------------------------------
CREATE TABLE order_items (
    id          BIGSERIAL PRIMARY KEY,
    order_id    BIGINT         NOT NULL REFERENCES b2b_orders (id) ON DELETE CASCADE,
    menu_id     BIGINT         NOT NULL REFERENCES menus (id)       ON DELETE RESTRICT,
    quantity    NUMERIC(12, 2) NOT NULL CHECK (quantity > 0),
    unit_price  NUMERIC(12, 2) NOT NULL CHECK (unit_price >= 0),
    line_total  NUMERIC(14, 2) GENERATED ALWAYS AS (quantity * unit_price) STORED,
    status      TEXT           NOT NULL DEFAULT 'pending'
                               CHECK (status IN ('pending', 'allocated', 'qc_hold', 'fulfilled', 'cancelled')),
    created_at  TIMESTAMPTZ    NOT NULL DEFAULT now(),
    CONSTRAINT uq_order_items UNIQUE (order_id, menu_id)
);

COMMENT ON TABLE order_items IS 'Detail item order; line_total dihitung otomatis dari quantity * unit_price.';

CREATE INDEX idx_order_items_order ON order_items (order_id);
CREATE INDEX idx_order_items_menu  ON order_items (menu_id);

-- -----------------------------------------------------------------------------
-- 6. qc_logs — hasil quality control per item
-- -----------------------------------------------------------------------------
CREATE TABLE qc_logs (
    id             BIGSERIAL PRIMARY KEY,
    order_item_id  BIGINT      NOT NULL REFERENCES order_items (id)  ON DELETE CASCADE,
    ingredient_id  BIGINT      NOT NULL REFERENCES ingredients (id)  ON DELETE RESTRICT,
    result         TEXT        NOT NULL CHECK (result IN ('pass', 'fail', 'hold')),
    checked_by     TEXT        NOT NULL,
    note           TEXT,
    checked_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE qc_logs IS 'Hasil QC bahan per item order; order hanya boleh fulfilled bila semua QC pass.';

CREATE INDEX idx_qc_logs_order_item ON qc_logs (order_item_id);
CREATE INDEX idx_qc_logs_result     ON qc_logs (result);

-- -----------------------------------------------------------------------------
-- 7. demand_forecasts — prakiraan permintaan per menu
-- -----------------------------------------------------------------------------
CREATE TABLE demand_forecasts (
    id             BIGSERIAL PRIMARY KEY,
    menu_id        BIGINT        NOT NULL REFERENCES menus (id) ON DELETE CASCADE,
    forecast_date  DATE          NOT NULL,
    forecast_qty   NUMERIC(12, 2) NOT NULL CHECK (forecast_qty >= 0),
    confidence     NUMERIC(4, 3) NOT NULL CHECK (confidence >= 0 AND confidence <= 1),
    model_version  TEXT          NOT NULL,
    created_at     TIMESTAMPTZ   NOT NULL DEFAULT now(),
    CONSTRAINT uq_demand_forecasts UNIQUE (menu_id, forecast_date, model_version)
);

COMMENT ON TABLE demand_forecasts IS 'Prediksi permintaan harian per menu beserta confidence dan versi model.';

CREATE INDEX idx_demand_forecasts_date ON demand_forecasts (forecast_date, menu_id);

-- -----------------------------------------------------------------------------
-- Trigger updated_at
-- -----------------------------------------------------------------------------
CREATE FUNCTION bogana_touch_updated_at()
    RETURNS TRIGGER
    LANGUAGE plpgsql
AS $$
BEGIN
    NEW.updated_at := now();
    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_ingredients_updated_at
    BEFORE UPDATE ON ingredients
    FOR EACH ROW EXECUTE FUNCTION bogana_touch_updated_at();

CREATE TRIGGER trg_menus_updated_at
    BEFORE UPDATE ON menus
    FOR EACH ROW EXECUTE FUNCTION bogana_touch_updated_at();

CREATE TRIGGER trg_b2b_orders_updated_at
    BEFORE UPDATE ON b2b_orders
    FOR EACH ROW EXECUTE FUNCTION bogana_touch_updated_at();

-- =============================================================================
-- SEED DATA — realistis untuk operasional Bogana
-- =============================================================================

-- 7.1 ingredients (id eksplisit agar seed mudah dirujuk)
INSERT INTO ingredients (id, sku, name, category, unit, stock_on_hand, reorder_point, unit_cost, supplier) VALUES
    (1,  'ING-RS-01', 'Beras Pandan Wangi',     'sembako', 'kg',    850.000, 100.000, 14000.00, 'CV Sumber Pangan'),
    (2,  'ING-AY-01', 'Ayam Fillet',            'protein', 'kg',    120.000,  25.000, 38000.00, 'PT Unggas Jaya'),
    (3,  'ING-KC-01', 'Kecap Manis',            'bumbu',   'liter',  60.000,  10.000, 22000.00, 'CV Sumber Pangan'),
    (4,  'ING-BM-01', 'Bawang Merah',           'bumbu',   'kg',     45.000,  10.000, 30000.00, 'Pasar Induk Kramat'),
    (5,  'ING-BP-01', 'Bawang Putih',           'bumbu',   'kg',     30.000,   8.000, 32000.00, 'Pasar Induk Kramat'),
    (6,  'ING-MN-01', 'Minyak Goreng',          'bumbu',   'liter',  90.000,  20.000, 18000.00, 'PT Sari Minyak'),
    (7,  'ING-TL-01', 'Telur Ayam',             'protein', 'pcs',    50.000, 150.000,  2500.00, 'PT Unggas Jaya'),
    (8,  'ING-SP-01', 'Sapi Sirloin',           'protein', 'kg',     40.000,  10.000, 120000.00, 'PT Prima Meat'),
    (9,  'ING-KL-01', 'Kelapa Parut',           'bahan',   'kg',      4.000,   5.000, 25000.00, 'CV Sumber Pangan'),
    (10, 'ING-SG-01', 'Sagu',                   'bahan',   'kg',      2.000,   4.000, 26000.00, 'CV Sumber Pangan'),
    (11, 'ING-GR-01', 'Gula Merah',             'bahan',   'kg',     35.000,   8.000, 17000.00, 'CV Sumber Pangan'),
    (12, 'ING-DJ-01', 'Daun Jeruk Purut',       'bumbu',   'kg',      5.000,   1.000, 40000.00, 'Pasar Induk Kramat');

-- 7.2 menus (mengacu pada katalog asli Nasi Bogana Ny. An Lay)
INSERT INTO menus (id, sku, name, category, base_price, description) VALUES
    (1, 'MNU-NBG-01',  'Nasi Bogana',        'nasi', 32000.00, 'Nasi gurih + sambal goreng kacang panjang, sambal goreng ampela, ayam opor suwir, dendeng age, telur pindang'),
    (2, 'MNU-LNG-01',  'Nasi Langgi',        'nasi', 37000.00, 'Nasi putih + sambal goreng tempe, sambal goreng ampela, telur dadar, empal sapi, abon, serundeng'),
    (3, 'MNU-RMS-01',  'Nasi Rames',         'nasi', 32000.00, 'Nasi gurih + ayam suwir balado, irisan telur dadar, mie goreng, orek tempe, dendeng sapi kering'),
    (4, 'MNU-GDG-01',  'Nasi Gudeg',         'nasi', 32000.00, 'Nasi putih + gudeg, krecek, ayam opor, telur pindang, areh'),
    (5, 'MNU-BOX-A',   'Nasi Box Paket A',   'box',  40000.00, 'Nasi, ayam goreng, telur balado, mie goreng, tumis buncis, kerupuk'),
    (6, 'MNU-BOX-B',   'Nasi Box Paket B',   'box',  40000.00, 'Nasi, ayam kecap, perkedel, mie goreng, kentang buncis balado, kerupuk');

-- 7.3 menu_recipes (BOM per 1 porsi)
INSERT INTO menu_recipes (menu_id, ingredient_id, quantity) VALUES
    -- Nasi Bogana Komplit
    (1,  1, 0.125), (1,  2, 0.150), (1,  3, 0.020), (1,  4, 0.010), (1,  5, 0.005), (1,  6, 0.020), (1,  7, 1.000),
    -- Nasi Bogana Ayam Kecap
    (2,  1, 0.125), (2,  2, 0.180), (2,  3, 0.025), (2,  4, 0.010), (2,  5, 0.005), (2,  6, 0.020),
    -- Soto Betawi Bogana
    (3,  1, 0.125), (3,  8, 0.120), (3,  9, 0.030), (3,  4, 0.015), (3,  5, 0.008), (3,  6, 0.015), (3, 12, 0.002),
    -- Rendang Sapi Bogana
    (4,  1, 0.125), (4,  8, 0.150), (4,  9, 0.040), (4,  4, 0.020), (4,  5, 0.010), (4,  6, 0.020), (4, 11, 0.010), (4, 12, 0.002),
    -- Klepon Bogana
    (5, 10, 0.080), (5,  9, 0.030), (5, 11, 0.040),
    -- Nasi Telur Balado
    (6,  1, 0.125), (6,  7, 2.000), (6,  4, 0.015), (6,  5, 0.010), (6,  6, 0.030), (6,  3, 0.010);

-- 7.4 b2b_orders — tanggal relatif agar dashboard hari ini selalu terisi
INSERT INTO b2b_orders (id, order_code, customer_name, customer_phone, channel, raw_text, status, order_date, notes) VALUES
    (1, 'ORD-' || to_char(CURRENT_DATE, 'YYYYMMDD') || '-001', 'Warung Bu Sri',          '081200011001', 'whatsapp',
     'gan besok pagi 40 nasi bogana komplit sama 15 ayam kecap ya', 'fulfilled', CURRENT_DATE,
     'Diantar maksimal jam 06.30'),
    (2, 'ORD-' || to_char(CURRENT_DATE, 'YYYYMMDD') || '-002', 'Katering Dapur Ibu Hana', '081200011002', 'email',
     '30 nasi telur balado 20 klepon untuk acara syukuran', 'short', CURRENT_DATE,
     'Menunggu restock telur'),
    (3, 'ORD-' || to_char(CURRENT_DATE, 'YYYYMMDD') || '-003', 'Restoran Sederhana Pak Joko', '081200011003', 'phone',
     '25 rendang sapi 10 soto betawi', 'confirmed', CURRENT_DATE,
     NULL);

-- 7.5 order_items
INSERT INTO order_items (order_id, menu_id, quantity, unit_price, status) VALUES
    (1, 1, 40.00, 18000.00, 'fulfilled'),
    (1, 2, 15.00, 15000.00, 'fulfilled'),
    (2, 6, 30.00, 13000.00, 'pending'),
    (2, 5, 20.00, 12000.00, 'pending'),
    (3, 4, 25.00, 30000.00, 'pending'),
    (3, 3, 10.00, 22000.00, 'pending');

-- 7.6 qc_logs — jam QC relatif terhadap hari ini (WIB)
INSERT INTO qc_logs (order_item_id, ingredient_id, result, checked_by, note, checked_at) VALUES
    (1,  1, 'pass', 'Siti Aminah', 'Beras kering, tidak lembap',      (CURRENT_DATE + TIME '04:10') AT TIME ZONE 'Asia/Jakarta'),
    (1,  2, 'pass', 'Siti Aminah', 'Ayam segar, suhu 4 C',            (CURRENT_DATE + TIME '04:18') AT TIME ZONE 'Asia/Jakarta'),
    (1,  7, 'pass', 'Siti Aminah', 'Telur utuh, tidak retak',         (CURRENT_DATE + TIME '04:25') AT TIME ZONE 'Asia/Jakarta'),
    (2,  2, 'pass', 'Dewi Lestari', 'Ayam sesuai standar',            (CURRENT_DATE + TIME '04:30') AT TIME ZONE 'Asia/Jakarta'),
    (2,  6, 'pass', 'Dewi Lestari', 'Kemasan minyak baik',            (CURRENT_DATE + TIME '04:35') AT TIME ZONE 'Asia/Jakarta'),
    (3,  4, 'pass', 'Dewi Lestari', 'Bawang merah kering',            (CURRENT_DATE + TIME '05:12') AT TIME ZONE 'Asia/Jakarta'),
    (3,  5, 'hold', 'Dewi Lestari', 'Menunggu cek ulang kadar air',   (CURRENT_DATE + TIME '05:15') AT TIME ZONE 'Asia/Jakarta');

-- 7.7 demand_forecasts — 7 hari ke depan agar halaman forecast tidak kosong
INSERT INTO demand_forecasts (menu_id, forecast_date, forecast_qty, confidence, model_version)
SELECT seed.menu_id,
       CURRENT_DATE + seed_day.day_offset,
       ROUND(seed.base_qty + ((seed_day.day_offset * 7) % 11) - 3, 2),
       seed.confidence,
       'bogana-forecast-v1'
FROM (VALUES
    (1, 46.00, 0.820),
    (2, 38.00, 0.760),
    (3, 16.00, 0.610),
    (4, 22.00, 0.700),
    (5, 30.00, 0.540),
    (6, 27.00, 0.730)
) AS seed(menu_id, base_qty, confidence)
CROSS JOIN generate_series(0, 6) AS seed_day(day_offset);

-- -----------------------------------------------------------------------------
-- Sinkronkan sequence setelah insert id eksplisit
-- -----------------------------------------------------------------------------
SELECT setval(pg_get_serial_sequence('ingredients',      'id'), (SELECT MAX(id) FROM ingredients));
SELECT setval(pg_get_serial_sequence('menus',            'id'), (SELECT MAX(id) FROM menus));
SELECT setval(pg_get_serial_sequence('menu_recipes',     'id'), (SELECT MAX(id) FROM menu_recipes));
SELECT setval(pg_get_serial_sequence('b2b_orders',       'id'), (SELECT MAX(id) FROM b2b_orders));
SELECT setval(pg_get_serial_sequence('order_items',      'id'), (SELECT MAX(id) FROM order_items));
SELECT setval(pg_get_serial_sequence('qc_logs',          'id'), (SELECT MAX(id) FROM qc_logs));
SELECT setval(pg_get_serial_sequence('demand_forecasts', 'id'), (SELECT MAX(id) FROM demand_forecasts));

COMMIT;