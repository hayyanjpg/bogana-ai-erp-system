from dataclasses import FrozenInstanceError

import pytest

from app.services.parser import MenuLite, build_aliases


def test_build_aliases_drops_generic_words():
    aliases = build_aliases("Nasi Bogana Ayam Kecap")

    assert "nasi bogana ayam kecap" in aliases
    assert "ayam kecap" in aliases
    assert "kecap" in aliases
    assert "nasi" not in aliases


def test_build_aliases_keeps_single_token():
    aliases = build_aliases("Klepon Bogana Isi Gula Merah")

    assert "klepon bogana isi gula merah" in aliases
    assert "klepon gula merah" in aliases
    assert "gula merah" in aliases


def test_menu_lite_is_frozen():
    menu = MenuLite(sku="MNU-TLB-01", name="Nasi Telur Balado", base_price=13000.0)

    with pytest.raises(FrozenInstanceError):
        menu.sku = "X"
