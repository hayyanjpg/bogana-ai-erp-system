from app.services.parser import MenuLite, parse_order_text

MENUS = (
    MenuLite(sku="MNU-NBG-01", name="Nasi Bogana Komplit", base_price=18000.0),
    MenuLite(sku="MNU-NBG-02", name="Nasi Bogana Ayam Kecap", base_price=15000.0),
    MenuLite(sku="MNU-TLB-01", name="Nasi Telur Balado", base_price=13000.0),
)


def test_parse_order_text_extracts_multiple_items():
    result = parse_order_text(
        "gan besok pagi 40 nasi bogana komplit sama 15 ayam kecap ya",
        MENUS,
    )

    assert [item.menu.sku for item in result.items] == ["MNU-NBG-01", "MNU-NBG-02"]
    assert [item.quantity for item in result.items] == [40.0, 15.0]
    assert result.confidence >= 0.8
    assert result.warnings == []


def test_parse_order_text_uses_fractional_quantity():
    result = parse_order_text("2.5 kilo nasi telur balado", MENUS)

    assert len(result.items) == 1
    assert result.items[0].menu.sku == "MNU-TLB-01"
    assert result.items[0].quantity == 2.5


def test_parse_order_text_marks_unknown_menu():
    result = parse_order_text("10 bakso urat", MENUS)

    assert result.items == []
    assert result.warnings[0]["code"] == "MENU_NOT_FOUND"


def test_parse_order_text_marks_missing_quantity():
    result = parse_order_text("nasi bogana komplit", MENUS)

    assert result.items[0].quantity == 1.0
    assert result.warnings[0]["code"] == "QTY_MISSING"


def test_parse_order_text_merges_duplicate_menu():
    result = parse_order_text("10 nasi bogana komplit, 5 nasi bogana komplit", MENUS)

    assert len(result.items) == 1
    assert result.items[0].quantity == 15.0
    assert result.warnings[0]["code"] == "DUPLICATE_LINE"
