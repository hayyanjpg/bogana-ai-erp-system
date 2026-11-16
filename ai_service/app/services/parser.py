from __future__ import annotations

import re
from collections.abc import Sequence
from dataclasses import dataclass, replace
from difflib import SequenceMatcher

GENERIC_TOKENS = frozenset({"nasi", "bogana", "isi", "per", "porsi", "buah"})

STOPWORDS = frozenset(
    {
        "yang",
        "untuk",
        "buat",
        "pesan",
        "order",
        "tolong",
        "please",
        "ya",
        "yah",
        "dong",
        "kak",
        "gan",
        "ganz",
        "pak",
        "bu",
        "sist",
        "mas",
        "mbak",
        "min",
        "pagi",
        "siang",
        "sore",
        "malam",
        "besok",
        "hari",
        "ini",
        "juga",
        "saja",
        "dan",
        "sama",
        "serta",
        "dengan",
    }
)

SEPARATOR_RE = re.compile(r"[,;\n]|\s+(?:dan|sama|serta|&|\+)\s+", re.IGNORECASE)
NUMBER_RE = re.compile(r"\d+(?:[.,]\d+)?")
NON_ALNUM_RE = re.compile(r"[^a-z0-9\s]")
WHITESPACE_RE = re.compile(r"\s+")
TIME_RE = re.compile(
    r"\b(?:jam|pukul|wib|wita|wit)\b|\b\d{1,2}\s*[:.]\d{2}\b|\b\d{1,2}(?:\s*(?:pagi|siang|sore|malam))\b",
    re.IGNORECASE,
)
ADDRESS_HINT_RE = re.compile(r"\b(?:kirim|antar|alamat|ke\s+jl|jl\.?|jalan)\b", re.IGNORECASE)

MATCH_THRESHOLD = 0.55
LOW_CONFIDENCE_THRESHOLD = 0.5


@dataclass(frozen=True)
class MenuLite:
    sku: str
    name: str
    base_price: float


@dataclass(frozen=True)
class ParsedLine:
    line_text: str
    menu: MenuLite
    quantity: float
    score: float


@dataclass(frozen=True)
class ParseResult:
    items: list[ParsedLine]
    warnings: list[dict[str, str | None]]
    confidence: float


@dataclass(frozen=True)
class QuantityResult:
    quantity: float | None
    cleaned_text: str
    ambiguous: bool


def normalize_text(text: str) -> str:
    lowered = text.lower()
    cleaned = NON_ALNUM_RE.sub(" ", lowered)
    return WHITESPACE_RE.sub(" ", cleaned).strip()


def build_aliases(name: str) -> tuple[str, ...]:
    normalized = normalize_text(name)
    tokens = [token for token in normalized.split() if token not in GENERIC_TOKENS]
    aliases = {normalized}
    if tokens:
        aliases.add(" ".join(tokens))
        aliases.add(tokens[-1])
        if len(tokens) >= 2:
            aliases.add(" ".join(tokens[-2:]))
    return tuple(sorted(alias for alias in aliases if alias))


def extract_quantity(text: str) -> QuantityResult:
    matches = NUMBER_RE.findall(text)
    if not matches:
        return QuantityResult(quantity=None, cleaned_text=text, ambiguous=False)
    try:
        value = float(matches[0].replace(",", "."))
    except ValueError:
        return QuantityResult(quantity=None, cleaned_text=text, ambiguous=False)
    if value <= 0:
        return QuantityResult(quantity=None, cleaned_text=text, ambiguous=False)
    cleaned = NUMBER_RE.sub(" ", text, count=1)
    return QuantityResult(quantity=value, cleaned_text=cleaned, ambiguous=len(matches) > 1)


def content_text(text: str) -> str:
    tokens = [token for token in normalize_text(text).split() if token not in STOPWORDS]
    return " ".join(tokens)


def _looks_like_instruction(text: str) -> bool:
    if TIME_RE.search(text):
        return True
    if ADDRESS_HINT_RE.search(text):
        return True
    # "kirim jam 6", "diantar jam 7 pagi", "jam 6"
    if re.search(r"\b(?:kirim|antar|dikirim|diantar)\b", text, re.IGNORECASE):
        return True
    return False


def _match_score(candidate: str, aliases: Sequence[str]) -> float:
    if not candidate:
        return 0.0
    best = 0.0
    for alias in aliases:
        if candidate == alias:
            return 1.0
        best = max(best, SequenceMatcher(None, candidate, alias).ratio())
    return best


def _warning(code: str, message: str, line_text: str | None) -> dict[str, str | None]:
    return {"code": code, "message": message, "line_text": line_text}


def _merge_lines(
    lines: list[ParsedLine],
    warnings: list[dict[str, str | None]],
) -> list[ParsedLine]:
    merged: dict[str, ParsedLine] = {}
    ordered: list[str] = []
    for line in lines:
        if line.menu.sku in merged:
            existing = merged[line.menu.sku]
            merged[line.menu.sku] = replace(
                existing,
                quantity=round(existing.quantity + line.quantity, 3),
                score=max(existing.score, line.score),
            )
            warnings.append(_warning("DUPLICATE_LINE", "Menu duplikat digabung", line.line_text))
            continue
        merged[line.menu.sku] = line
        ordered.append(line.menu.sku)
    return [merged[sku] for sku in ordered]


def parse_order_text(raw_text: str, menus: Sequence[MenuLite]) -> ParseResult:
    index = [(menu, build_aliases(menu.name)) for menu in menus]
    segments = [part.strip() for part in SEPARATOR_RE.split(raw_text) if part and part.strip()]
    if not segments:
        segments = [raw_text.strip()]

    warnings: list[dict[str, str | None]] = []
    lines: list[ParsedLine] = []

    for segment in segments:
        quantity_result = extract_quantity(segment)
        candidate = content_text(quantity_result.cleaned_text)
        if _looks_like_instruction(segment):
            # Ignore delivery/time/address notes that are not menu items
            continue

        best_menu: MenuLite | None = None
        best_score = 0.0
        for menu, aliases in index:
            score = _match_score(candidate, aliases)
            if score > best_score:
                best_menu = menu
                best_score = score

        if best_menu is None or best_score < MATCH_THRESHOLD:
            warnings.append(_warning("MENU_NOT_FOUND", "Menu tidak dikenali", segment))
            continue

        quantity = quantity_result.quantity
        if quantity is None:
            quantity = 1.0
            warnings.append(_warning("QTY_MISSING", "Kuantitas tidak ditemukan", segment))
        if quantity_result.ambiguous:
            warnings.append(_warning("QTY_AMBIGUOUS", "Kuantitas ambigu", segment))

        lines.append(
            ParsedLine(
                line_text=segment,
                menu=best_menu,
                quantity=quantity,
                score=round(best_score, 4),
            )
        )

    items = _merge_lines(lines, warnings)
    confidence = 0.0
    if items:
        confidence = round(sum(item.score for item in items) / len(items), 3)
        if confidence < LOW_CONFIDENCE_THRESHOLD:
            warnings.append(_warning("LOW_CONFIDENCE", "Keyakinan parser rendah", None))

    return ParseResult(items=items, warnings=warnings, confidence=confidence)
