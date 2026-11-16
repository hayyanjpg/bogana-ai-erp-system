<?php
/** @var string $content */
/** @var string $title */

$active = match ($title) {
    'Dashboard' => 'dashboard',
    'Order & Parser' => 'orders',
    'Stok Bahan' => 'stocks',
    'Forecast' => 'forecasts',
    'Status Sistem' => 'health',
    default => '',
};

$icons = [
    'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
    'orders' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Z"/><path d="M9 8h6M9 12h6"/>',
    'stocks' => '<path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z"/><path d="m4 7.5 8 4.5 8-4.5M12 12v9"/>',
    'forecasts' => '<path d="M4 19V5M4 19h16"/><path d="m7 15 4-5 3 3 5-7"/>',
    'health' => '<path d="M3 12h4l2-6 4 12 2-6h6"/>',
];

$navGroups = [
    [
        'label' => 'Operasional',
        'items' => [
            ['key' => 'dashboard', 'href' => '?page=dashboard', 'label' => 'Dashboard', 'hint' => 'Ringkasan harian'],
            ['key' => 'orders', 'href' => '?page=orders', 'label' => 'Order & Parser', 'hint' => 'Urai teks, cek stok'],
        ],
    ],
    [
        'label' => 'Inventori',
        'items' => [
            ['key' => 'stocks', 'href' => '?page=stocks', 'label' => 'Stok Bahan', 'hint' => 'Posisi & reorder'],
            ['key' => 'forecasts', 'href' => '?page=forecasts', 'label' => 'Forecast', 'hint' => 'Rencana produksi'],
        ],
    ],
    [
        'label' => 'Sistem',
        'items' => [
            ['key' => 'health', 'href' => '?page=health', 'label' => 'Status Sistem', 'hint' => 'Database & cache'],
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title><?= e($title) ?> — Bogana ERP</title>
    <style>
        :root {
            --paper: #f3eee4;
            --paper-deep: #ece4d6;
            --surface: #fffdf8;
            --surface-2: #f8f2e7;
            --surface-3: #efe7d8;
            --ink: #1d1b17;
            --ink-soft: #49443b;
            --muted: #7b7365;
            --line: #e1d9ca;
            --line-strong: #d1c5b1;
            --brand: #24543f;
            --brand-deep: #173a2c;
            --brand-soft: #e3efe7;
            --turmeric: #b47a15;
            --turmeric-soft: #f8edd6;
            --brick: #a34632;
            --brick-soft: #f8e3dd;
            --positive: #256b46;
            --positive-soft: #e3f0e7;
            --warning: #8a5c0c;
            --warning-soft: #faf0d8;
            --danger: #9c3526;
            --danger-soft: #f8e1db;
            --radius: 16px;
            --radius-sm: 10px;
            --font-ui: "Segoe UI Variable", "Segoe UI", ui-sans-serif, system-ui, -apple-system, Roboto, "Helvetica Neue", Arial, sans-serif;
            --font-display: "Iowan Old Style", "Palatino Linotype", "Book Antiqua", Palatino, Georgia, "Times New Roman", serif;
            --font-mono: "Cascadia Code", "SFMono-Regular", Consolas, "Liberation Mono", monospace;
        }

        * { box-sizing: border-box; }

        html { scroll-behavior: smooth; }

        body {
            margin: 0;
            font-family: var(--font-ui);
            font-size: 15px;
            line-height: 1.55;
            color: var(--ink);
            background-color: var(--paper);
            background-image: radial-gradient(rgba(29, 27, 23, .045) 1px, transparent 1px);
            background-size: 22px 22px;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }

        ::selection { background: var(--turmeric-soft); color: var(--ink); }

        a { color: var(--brand); text-underline-offset: 3px; }

        a:hover { color: var(--brand-deep); }

        :focus-visible {
            outline: 2px solid var(--brand);
            outline-offset: 3px;
        }

        h1, h2, h3, p { margin-top: 0; }

        .skip-link {
            position: absolute;
            left: 16px;
            top: -60px;
            z-index: 50;
            padding: 10px 14px;
            border-radius: var(--radius-sm);
            background: var(--ink);
            color: var(--surface);
            text-decoration: none;
            transition: top .18s ease;
        }

        .skip-link:focus { top: 16px; color: var(--surface); }

        .shell {
            display: grid;
            grid-template-columns: 268px minmax(0, 1fr);
            min-height: 100vh;
            min-width: 0;
        }

        /* ---------- Sidebar ---------- */
        .sidebar {
            position: sticky;
            top: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            gap: 28px;
            padding: 26px 20px 22px;
            background:
                radial-gradient(circle at 18% 0%, rgba(180, 122, 21, .16), transparent 34%),
                linear-gradient(180deg, #1d3529 0%, #172a21 58%, #14241d 100%);
            color: #e9e3d6;
            overflow-y: auto;
            min-width: 0;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #fffaf0;
            text-decoration: none;
        }

        .brand:hover { color: #fffaf0; }

        .brand__mark {
            display: grid;
            place-items: center;
            width: 42px;
            height: 42px;
            flex: 0 0 42px;
            border-radius: 12px 12px 12px 4px;
            background: var(--turmeric);
            color: #1e1b14;
            font-family: var(--font-display);
            font-size: 1.7rem;
            font-weight: 700;
            line-height: 1;
            box-shadow: inset 0 -2px 0 rgba(0, 0, 0, .14);
        }

        .brand__text { display: flex; flex-direction: column; line-height: 1.15; }
        .brand__text strong {
            font-family: var(--font-display);
            font-size: 1.35rem;
            font-weight: 600;
            letter-spacing: -.01em;
        }
        .brand__text small {
            color: #a9b8ab;
            font-size: .64rem;
            font-weight: 600;
            letter-spacing: .16em;
            text-transform: uppercase;
            margin-top: 3px;
        }

        .nav { display: flex; flex-direction: column; gap: 20px; min-width: 0; }

        .nav__label {
            margin: 0 0 7px 10px;
            color: #84988a;
            font-family: var(--font-mono);
            font-size: .62rem;
            font-weight: 600;
            letter-spacing: .16em;
            text-transform: uppercase;
        }

        .nav__group { display: flex; flex-direction: column; gap: 3px; }

        .nav__link {
            position: relative;
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 9px 10px;
            border-radius: 10px;
            color: #c3cec3;
            text-decoration: none;
            transition: background .16s ease, color .16s ease, transform .16s ease;
        }

        .nav__link svg {
            width: 19px;
            height: 19px;
            flex: 0 0 19px;
            fill: none;
            stroke: currentColor;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-width: 1.6;
            opacity: .82;
        }

        .nav__link:hover {
            background: rgba(255, 252, 240, .07);
            color: #fffaf0;
            transform: translateX(2px);
        }

        .nav__link[aria-current="page"] {
            background: rgba(255, 252, 240, .11);
            color: #fffaf0;
            box-shadow: inset 2px 0 0 var(--turmeric);
        }

        .nav__link[aria-current="page"] svg { opacity: 1; color: #e8bf6c; }

        .nav__copy { display: flex; flex-direction: column; min-width: 0; }
        .nav__copy strong { font-size: .88rem; font-weight: 600; }
        .nav__copy small { color: #8fa294; font-size: .7rem; margin-top: 1px; }
        .nav__link[aria-current="page"] .nav__copy small { color: #b9c8bb; }

        .sidebar__foot { margin-top: auto; display: grid; gap: 12px; }

        .side-card {
            padding: 13px;
            border: 1px solid rgba(255, 252, 240, .11);
            border-radius: 12px;
            background: rgba(255, 252, 240, .05);
        }

        .side-card p { margin: 0; }

        .side-card__label {
            color: #8fa294;
            font-family: var(--font-mono);
            font-size: .6rem;
            letter-spacing: .14em;
            text-transform: uppercase;
        }

        .side-card__value {
            display: flex;
            align-items: center;
            gap: 7px;
            margin: 5px 0 8px !important;
            font-size: .82rem;
            font-weight: 600;
            color: #f1ecdf;
        }

        .side-card a { color: #e8bf6c; font-size: .72rem; text-decoration: none; }
        .side-card a:hover { text-decoration: underline; }

        .dot {
            width: 7px;
            height: 7px;
            flex: 0 0 7px;
            border-radius: 50%;
            background: currentColor;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, .06);
        }

        .dot--live { color: #7ec99a; }

        .sidebar__meta {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            margin: 0;
            color: #788d7e;
            font-family: var(--font-mono);
            font-size: .62rem;
            letter-spacing: .04em;
        }

        .side-clock {
            margin: 0;
            color: #f1ecdf;
            font-family: var(--font-mono);
            font-size: .78rem;
            font-variant-numeric: tabular-nums;
        }

        /* ---------- Main ---------- */
        .main {
            min-width: 0;
            display: flex;
            flex-direction: column;
            padding: 26px clamp(20px, 3.4vw, 48px) 20px;
        }

        .main__inner {
            width: 100%;
            max-width: 1180px;
            margin: 0 auto;
            flex: 1;
        }

        .utility {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 26px;
            color: var(--muted);
            font-family: var(--font-mono);
            font-size: .66rem;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .utility__crumb { display: flex; align-items: center; gap: 8px; }
        .utility__crumb span { color: var(--line-strong); }
        .utility__status { display: flex; align-items: center; gap: 7px; }
        .utility__status .dot { color: var(--positive); box-shadow: 0 0 0 3px var(--positive-soft); }

        .footer {
            width: 100%;
            max-width: 1180px;
            margin: 32px auto 0;
            padding-top: 16px;
            border-top: 1px solid var(--line);
            display: flex;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            color: var(--muted);
            font-size: .72rem;
        }

        /* ---------- Page header ---------- */
        .page-head {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 22px;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }

        .page-head > div:first-child { max-width: 720px; }

        .eyebrow {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0 0 7px;
            color: var(--brand);
            font-family: var(--font-mono);
            font-size: .65rem;
            font-weight: 700;
            letter-spacing: .16em;
            text-transform: uppercase;
        }

        .eyebrow::before {
            content: "";
            width: 18px;
            height: 1px;
            background: var(--turmeric);
        }

        h1 {
            margin: 0;
            font-family: var(--font-display);
            font-size: clamp(1.9rem, 3vw, 2.55rem);
            font-weight: 600;
            line-height: 1.05;
            letter-spacing: -.025em;
        }

        .lede {
            margin: 9px 0 0;
            max-width: 68ch;
            color: var(--muted);
            font-size: .9rem;
        }

        .page-head__actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* ---------- Buttons & forms ---------- */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            min-height: 38px;
            padding: 0 14px;
            border: 1px solid transparent;
            border-radius: var(--radius-sm);
            font: inherit;
            font-size: .82rem;
            font-weight: 650;
            line-height: 1;
            text-decoration: none;
            cursor: pointer;
            transition: background .15s ease, border-color .15s ease, color .15s ease, transform .15s ease;
            white-space: nowrap;
        }

        .btn:active { transform: translateY(1px); }

        .btn:disabled { opacity: .62; cursor: progress; }

        .btn--primary { background: var(--brand); color: #fffdf5; }
        .btn--primary:hover { background: var(--brand-deep); color: #fffdf5; }

        .btn--secondary {
            background: var(--surface);
            border-color: var(--line-strong);
            color: var(--ink-soft);
        }
        .btn--secondary:hover { border-color: var(--brand); color: var(--brand); }

        .btn--ghost { background: transparent; color: var(--muted); }
        .btn--ghost:hover { background: var(--surface-2); color: var(--ink); }

        .btn--danger { background: var(--brick); color: #fff9f6; }
        .btn--danger:hover { background: #893927; color: #fff9f6; }

        .btn--sm { min-height: 31px; padding: 0 10px; font-size: .74rem; }
        .btn--block { width: 100%; }

        .btn.is-loading::after {
            content: "";
            width: 12px;
            height: 12px;
            border: 2px solid currentColor;
            border-right-color: transparent;
            border-radius: 50%;
            animation: spin .7s linear infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        .filter-bar {
            display: flex;
            align-items: flex-end;
            gap: 10px;
            flex-wrap: wrap;
            padding: 11px;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: var(--surface);
        }

        .filter-bar__group { display: flex; align-items: flex-end; gap: 8px; flex-wrap: wrap; }
        .filter-bar__spacer { flex: 1; }

        .field { display: grid; gap: 6px; min-width: 0; }

        .field > span,
        .field > label {
            color: var(--muted);
            font-family: var(--font-mono);
            font-size: .62rem;
            font-weight: 600;
            letter-spacing: .1em;
            text-transform: uppercase;
        }

        .field__hint { color: var(--muted); font-size: .7rem; line-height: 1.4; }

        .input,
        .select,
        .textarea {
            width: 100%;
            min-height: 38px;
            padding: 8px 11px;
            border: 1px solid var(--line-strong);
            border-radius: var(--radius-sm);
            background: #fffefb;
            color: var(--ink);
            font: inherit;
            font-size: .84rem;
            transition: border-color .15s ease, box-shadow .15s ease;
        }

        .textarea { min-height: 108px; resize: vertical; line-height: 1.55; }
        .input--date { min-width: 148px; }
        .input--short { width: 92px; }

        .input:focus,
        .select:focus,
        .textarea:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(36, 84, 63, .12);
            outline: none;
        }

        .input::placeholder, .textarea::placeholder { color: #aaa294; }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 13px;
        }

        .form-grid .field--full { grid-column: 1 / -1; }

        .counter {
            justify-self: end;
            color: var(--muted);
            font-family: var(--font-mono);
            font-size: .66rem;
            letter-spacing: 0;
            text-transform: none;
        }

        .switch {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 38px;
            padding: 0 10px;
            border: 1px solid var(--line-strong);
            border-radius: var(--radius-sm);
            background: #fffefb;
            color: var(--ink-soft);
            font-size: .78rem;
            cursor: pointer;
            user-select: none;
        }

        .switch input { position: absolute; opacity: 0; pointer-events: none; }

        .switch__track {
            position: relative;
            width: 30px;
            height: 17px;
            flex: 0 0 30px;
            border-radius: 999px;
            background: var(--line-strong);
            transition: background .15s ease;
        }

        .switch__track::after {
            content: "";
            position: absolute;
            top: 2px;
            left: 2px;
            width: 13px;
            height: 13px;
            border-radius: 50%;
            background: #fffdf8;
            box-shadow: 0 1px 2px rgba(0, 0, 0, .2);
            transition: transform .15s ease;
        }

        .switch input:checked + .switch__track { background: var(--brand); }
        .switch input:checked + .switch__track::after { transform: translateX(13px); }
        .switch:focus-within { box-shadow: 0 0 0 3px rgba(36, 84, 63, .12); border-color: var(--brand); }

        /* ---------- Panels ---------- */
        .panel {
            min-width: 0;
            padding: 18px;
            border: 1px solid var(--line);
            border-radius: var(--radius);
            background: var(--surface);
            box-shadow: 0 1px 0 rgba(29, 27, 23, .025);
        }

        .panel--flush { padding: 0; overflow: hidden; }
        .panel--muted { background: var(--surface-2); box-shadow: none; }
        .panel--focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(36, 84, 63, .1); }
        .panel--danger { border-color: #e7bdb2; }

        .panel__head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 14px;
        }

        .panel__head h2 { margin: 0; }

        .panel__title {
            margin: 0;
            font-family: var(--font-display);
            font-size: 1.18rem;
            font-weight: 600;
            letter-spacing: -.01em;
        }

        .panel__hint { margin: 3px 0 0; color: var(--muted); font-size: .76rem; }
        .panel__meta {
            color: var(--muted);
            font-family: var(--font-mono);
            font-size: .65rem;
            letter-spacing: .04em;
            white-space: nowrap;
        }

        /* ---------- KPI ---------- */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }

        .kpi {
            position: relative;
            min-width: 0;
            padding: 16px 17px 15px;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: var(--surface);
            overflow: hidden;
        }

        .kpi::before {
            content: "";
            position: absolute;
            inset: 0 0 auto 0;
            height: 3px;
            background: var(--accent, var(--brand));
        }

        .kpi--brand { --accent: var(--brand); }
        .kpi--turmeric { --accent: var(--turmeric); }
        .kpi--brick { --accent: var(--brick); }
        .kpi--ink { --accent: var(--ink-soft); }

        .kpi__label {
            margin: 0;
            color: var(--muted);
            font-family: var(--font-mono);
            font-size: .61rem;
            font-weight: 600;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        .kpi__value {
            display: block;
            margin: 7px 0 3px;
            font-family: var(--font-display);
            font-size: clamp(1.65rem, 2.5vw, 2.05rem);
            font-weight: 600;
            line-height: 1;
            letter-spacing: -.02em;
            font-variant-numeric: tabular-nums;
        }

        .kpi__value small { font-family: var(--font-ui); font-size: .72rem; font-weight: 500; color: var(--muted); letter-spacing: 0; }

        .kpi__sub { margin: 0; color: var(--muted); font-size: .72rem; }
        .kpi__sub strong { color: var(--ink-soft); font-weight: 600; }

        /* ---------- Grids ---------- */
        .grid { display: grid; gap: 16px; margin-bottom: 16px; }
        .grid--2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .grid--3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .grid--main { grid-template-columns: minmax(0, 1.05fr) minmax(0, .95fr); }
        .grid--workbench { grid-template-columns: minmax(300px, .82fr) minmax(0, 1.18fr); align-items: start; }
        .grid--ops { grid-template-columns: repeat(2, minmax(0, 1fr)); align-items: start; }

        /* ---------- Status bar & legend ---------- */
        .status-bar {
            display: flex;
            height: 11px;
            border-radius: 999px;
            overflow: hidden;
            background: var(--surface-3);
            margin: 4px 0 14px;
        }

        .status-bar__seg { min-width: 0; height: 100%; }
        .seg--received { background: #a1988a; }
        .seg--parsed { background: #c9a24a; }
        .seg--confirmed { background: #4b8167; }
        .seg--short { background: #b46a2b; }
        .seg--qc_hold { background: #8a5c0c; }
        .seg--fulfilled { background: var(--brand); }
        .seg--parse_failed { background: var(--brick); }
        .seg--cancelled { background: #c9c0b0; }

        .legend {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 7px 16px;
        }

        .legend__item {
            display: flex;
            align-items: center;
            gap: 7px;
            min-width: 0;
            color: var(--muted);
            font-size: .74rem;
        }

        .legend__dot {
            width: 8px;
            height: 8px;
            flex: 0 0 8px;
            border-radius: 2px;
            background: var(--dot, var(--line-strong));
        }

        .legend__name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .legend__value { margin-left: auto; color: var(--ink); font-family: var(--font-mono); font-size: .7rem; font-weight: 600; }

        /* ---------- Lists ---------- */
        .stack { display: grid; gap: 2px; }

        .list-row {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            align-items: center;
            gap: 11px;
            padding: 11px 0;
            border-bottom: 1px solid var(--line);
        }

        .list-row:last-child { border-bottom: 0; padding-bottom: 0; }
        .list-row:first-child { padding-top: 0; }

        .avatar {
            display: grid;
            place-items: center;
            width: 34px;
            height: 34px;
            flex: 0 0 34px;
            border-radius: 50%;
            background: var(--brand-soft);
            color: var(--brand);
            font-family: var(--font-mono);
            font-size: .68rem;
            font-weight: 700;
        }

        .list-row__body { min-width: 0; }
        .list-row__title { margin: 0; font-size: .84rem; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .list-row__meta { margin: 1px 0 0; color: var(--muted); font-size: .71rem; }
        .list-row__action { justify-self: end; }
        .forecast-row { grid-template-columns: minmax(0, 1fr) auto; }

        .rank {
            display: grid;
            place-items: center;
            width: 25px;
            height: 25px;
            flex: 0 0 25px;
            border-radius: 7px;
            background: var(--surface-2);
            color: var(--ink-soft);
            font-family: var(--font-mono);
            font-size: .68rem;
            font-weight: 700;
        }

        /* ---------- Meter ---------- */
        .meter {
            height: 5px;
            border-radius: 999px;
            background: var(--surface-3);
            overflow: hidden;
        }

        .meter__fill {
            display: block;
            height: 100%;
            width: var(--pct, 0%);
            border-radius: inherit;
            background: var(--meter-color, var(--brand));
        }

        .meter--positive { --meter-color: var(--positive); }
        .meter--warning { --meter-color: var(--turmeric); }
        .meter--danger { --meter-color: var(--brick); }

        .meter-row { display: grid; gap: 5px; }
        .meter-row__head { display: flex; justify-content: space-between; gap: 10px; color: var(--muted); font-size: .7rem; }
        .meter-row__head strong { color: var(--ink-soft); font-weight: 600; font-variant-numeric: tabular-nums; }

        /* ---------- Tables ---------- */
        .table-wrap { overflow-x: auto; }

        table { width: 100%; border-collapse: collapse; font-size: .82rem; }

        th, td {
            padding: 9px 10px;
            text-align: left;
            vertical-align: top;
            border-bottom: 1px solid var(--line);
        }

        th {
            color: var(--muted);
            font-family: var(--font-mono);
            font-size: .62rem;
            font-weight: 600;
            letter-spacing: .1em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        thead th { border-bottom-color: var(--line-strong); }

        tbody tr:last-child td { border-bottom: 0; }
        tbody tr:hover td { background: rgba(248, 242, 231, .5); }

        .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .mono { font-family: var(--font-mono); font-size: .74rem; }
        .nowrap { white-space: nowrap; }
        .muted { color: var(--muted); }

        .table-title { font-weight: 600; }
        .table-sub { display: block; margin-top: 1px; color: var(--muted); font-family: var(--font-mono); font-size: .66rem; }
        .table-sub--mobile { display: none; }

        .table-summary {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            justify-content: space-between;
            gap: 6px 16px;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid var(--line-strong);
        }

        .table-summary__label {
            color: var(--muted);
            font-family: var(--font-mono);
            font-size: .64rem;
            font-weight: 600;
            letter-spacing: .1em;
            text-transform: uppercase;
        }

        .table-summary__value {
            font-family: var(--font-display);
            font-size: 1.05rem;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
            color: var(--ink);
        }

        /* ---------- Pills & chips ---------- */
        .pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 8px;
            border-radius: 999px;
            border: 1px solid transparent;
            font-family: var(--font-mono);
            font-size: .62rem;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .pill::before {
            content: "";
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: currentColor;
        }

        .pill--positive { background: var(--positive-soft); color: var(--positive); }
        .pill--warning { background: var(--warning-soft); color: var(--warning); }
        .pill--danger { background: var(--danger-soft); color: var(--danger); }
        .pill--neutral { background: var(--surface-3); color: var(--ink-soft); }
        .pill--accent { background: var(--brand-soft); color: var(--brand); }

        .chips { display: flex; flex-wrap: wrap; gap: 6px; }

        .chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 8px;
            border: 1px solid var(--line);
            border-radius: 7px;
            background: var(--surface-2);
            color: var(--ink-soft);
            font-family: var(--font-mono);
            font-size: .66rem;
            white-space: nowrap;
        }

        .code {
            padding: 2px 6px;
            border: 1px solid var(--line);
            border-radius: 6px;
            background: var(--surface-2);
            color: var(--ink-soft);
            font-family: var(--font-mono);
            font-size: .7rem;
        }

        /* ---------- Alerts ---------- */
        .alert {
            display: grid;
            gap: 4px;
            padding: 12px 14px;
            border: 1px solid var(--line);
            border-left: 3px solid var(--tone, var(--brand));
            border-radius: var(--radius-sm);
            background: var(--surface-2);
        }

        .alert--positive { --tone: var(--positive); background: var(--positive-soft); border-color: #c6dfce; border-left-color: var(--positive); }
        .alert--warning { --tone: var(--warning); background: var(--warning-soft); border-color: #e9d7ab; border-left-color: var(--warning); }
        .alert--danger { --tone: var(--danger); background: var(--danger-soft); border-color: #edc7bf; border-left-color: var(--danger); }
        .alert--info { --tone: var(--brand); background: var(--brand-soft); border-color: #cbe0d3; border-left-color: var(--brand); }

        .alert__title { margin: 0; font-size: .82rem; font-weight: 700; color: var(--ink); }
        .alert__text { margin: 0; color: var(--ink-soft); font-size: .78rem; }
        .alert__meta { margin: 2px 0 0; color: var(--muted); font-family: var(--font-mono); font-size: .65rem; }

        .alert__list { margin: 4px 0 0; padding-left: 18px; color: var(--ink-soft); font-size: .74rem; }
        .alert__list li + li { margin-top: 2px; }

        /* ---------- Empty states ---------- */
        .empty {
            display: grid;
            justify-items: center;
            gap: 7px;
            padding: 28px 18px;
            border: 1px dashed var(--line-strong);
            border-radius: var(--radius-sm);
            background: var(--surface-2);
            text-align: center;
            color: var(--muted);
        }

        .empty__mark {
            display: grid;
            place-items: center;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: var(--surface);
            border: 1px solid var(--line);
            color: var(--brand);
            font-family: var(--font-display);
            font-size: 1.15rem;
        }

        .empty p { margin: 0; font-size: .78rem; max-width: 42ch; }

        /* ---------- Detail / result blocks ---------- */
        .result-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 13px;
        }

        .result-head h3 {
            margin: 0;
            font-family: var(--font-display);
            font-size: 1.3rem;
            font-weight: 600;
            letter-spacing: -.01em;
        }

        .result-head p { margin: 3px 0 0; color: var(--muted); font-size: .76rem; }

        .confidence {
            display: grid;
            gap: 5px;
            min-width: 150px;
        }

        .confidence__head { display: flex; justify-content: space-between; gap: 10px; font-family: var(--font-mono); font-size: .66rem; color: var(--muted); }
        .confidence__head strong { color: var(--ink); font-size: .74rem; }

        .kv {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 10px 16px;
            margin: 0;
        }

        .kv div { min-width: 0; }
        .kv dt { color: var(--muted); font-family: var(--font-mono); font-size: .6rem; letter-spacing: .08em; text-transform: uppercase; }
        .kv dd { margin: 2px 0 0; font-size: .82rem; font-weight: 600; }

        .warnings { display: grid; gap: 7px; margin-top: 13px; }

        .warning-row {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            gap: 9px;
            padding: 9px 10px;
            border: 1px solid #ecd9ae;
            border-radius: 9px;
            background: #fdf6e6;
        }

        .warning-row__code { font-family: var(--font-mono); font-size: .6rem; font-weight: 700; color: var(--warning); letter-spacing: .04em; }
        .warning-row p { margin: 0; font-size: .75rem; color: var(--ink-soft); }
        .warning-row small { display: block; margin-top: 2px; color: var(--muted); font-family: var(--font-mono); font-size: .64rem; }

        .quick-samples { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 9px; }

        .sample-btn {
            padding: 4px 8px;
            border: 1px dashed var(--line-strong);
            border-radius: 7px;
            background: transparent;
            color: var(--muted);
            font: inherit;
            font-size: .68rem;
            cursor: pointer;
        }

        .sample-btn:hover { border-color: var(--brand); color: var(--brand); background: var(--surface-2); }

        .section-divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 26px 0 14px;
            color: var(--muted);
            font-family: var(--font-mono);
            font-size: .62rem;
            letter-spacing: .14em;
            text-transform: uppercase;
        }

        .section-divider::after { content: ""; height: 1px; flex: 1; background: var(--line); }

        /* ---------- Stock detail ---------- */
        .detail-hero {
            display: grid;
            grid-template-columns: minmax(0, 1.4fr) minmax(220px, .6fr);
            gap: 18px;
            align-items: start;
        }

        .detail-name { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .detail-name h2 { margin: 0; font-family: var(--font-display); font-size: 1.5rem; font-weight: 600; letter-spacing: -.02em; }

        .detail-gauge { display: grid; gap: 7px; }

        /* ---------- Health ---------- */
        .health-hero {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1.25fr);
            gap: 16px;
            align-items: stretch;
            margin-bottom: 16px;
        }

        .health-status {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 18px;
            padding: 22px;
            border-radius: var(--radius);
            border: 1px solid #c6dfce;
            background:
                radial-gradient(circle at 100% 0%, rgba(37, 107, 70, .13), transparent 45%),
                var(--positive-soft);
        }

        .health-status--degraded { border-color: #e9d7ab; background: radial-gradient(circle at 100% 0%, rgba(180, 122, 21, .14), transparent 45%), var(--warning-soft); }
        .health-status--down { border-color: #edc7bf; background: radial-gradient(circle at 100% 0%, rgba(156, 53, 38, .13), transparent 45%), var(--danger-soft); }

        .health-status__label { margin: 0; font-family: var(--font-mono); font-size: .62rem; letter-spacing: .14em; text-transform: uppercase; color: var(--muted); }

        .health-status__value {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 6px 0 0;
            font-family: var(--font-display);
            font-size: clamp(2rem, 4vw, 2.9rem);
            font-weight: 600;
            letter-spacing: -.03em;
            line-height: 1;
        }

        .health-status__value .dot { width: 12px; height: 12px; flex-basis: 12px; box-shadow: 0 0 0 5px rgba(37, 107, 70, .12); }

        .health-status p { margin: 0; color: var(--ink-soft); font-size: .78rem; }

        .check-list { display: grid; gap: 1px; }

        .check {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            align-items: center;
            gap: 11px;
            padding: 11px 0;
            border-bottom: 1px solid var(--line);
        }

        .check:last-child { border-bottom: 0; padding-bottom: 0; }
        .check:first-child { padding-top: 0; }

        .check__icon {
            display: grid;
            place-items: center;
            width: 30px;
            height: 30px;
            border-radius: 8px;
            background: var(--surface-2);
            color: var(--muted);
            font-family: var(--font-mono);
            font-size: .58rem;
            font-weight: 700;
        }

        .check__body p { margin: 0; font-size: .82rem; font-weight: 600; }
        .check__body small { display: block; color: var(--muted); font-size: .7rem; }

        /* ---------- Error ---------- */
        .error-page {
            display: grid;
            justify-items: start;
            gap: 14px;
            max-width: 680px;
            padding: clamp(22px, 4vw, 38px);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            background: var(--surface);
        }

        .error-code {
            font-family: var(--font-display);
            font-size: clamp(3.2rem, 8vw, 5.4rem);
            font-weight: 600;
            line-height: .9;
            letter-spacing: -.06em;
            color: var(--brick);
        }

        .error-page h1 { font-size: clamp(1.5rem, 3vw, 2.1rem); }

        /* ---------- Responsive ---------- */
        @media (max-width: 1100px) {
            .kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .grid--workbench, .grid--main, .grid--ops, .health-hero, .detail-hero { grid-template-columns: minmax(0, 1fr); }
        }

        @media (max-width: 900px) {
            .shell { grid-template-columns: minmax(0, 1fr); }

            .sidebar {
                position: static;
                height: auto;
                padding: 16px 18px;
                gap: 16px;
                overflow: visible;
            }

            .nav {
                flex-direction: row;
                align-items: center;
                gap: 18px;
                width: 100%;
                max-width: 100%;
                overflow-x: auto;
                padding-bottom: 2px;
                scrollbar-width: thin;
            }

            .nav__group { flex-direction: row; }

            .nav__label { display: none; }

            .nav__link { padding: 7px 9px; }
            .nav__copy small { display: none; }
            .nav__link:hover { transform: none; }
            .nav__link[aria-current="page"] { box-shadow: inset 0 -2px 0 var(--turmeric); }

            .sidebar__foot {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                margin-top: 0;
            }

            .side-card { display: none; }
            .sidebar__meta { gap: 14px; }

            .main { padding-top: 20px; }
        }

        @media (max-width: 640px) {
            body { font-size: 14px; }
            .utility { display: none; }
            .page-head { align-items: flex-start; }
            .page-head__actions, .filter-bar { width: 100%; }
            .filter-bar { align-items: center; }
            .filter-bar .field { flex: 1 1 145px; }
            .filter-bar .input--date { min-width: 0; }
            .filter-bar .btn { flex: 0 0 auto; }
            .filter-bar .btn--primary { flex: 1 1 100%; }
            .filter-bar .switch { flex: 1 1 100%; justify-content: space-between; }
            .panel__meta { white-space: normal; }
            .col-line, .col-coverage, .col-reorder, .col-price, .col-value { display: none; }
            .table-sub--mobile {
                display: block;
                max-width: 110px;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                font-family: var(--font-ui);
                font-size: .64rem;
            }
            table { font-size: .78rem; }
            th, td { padding: 8px 6px; }
            .kpi-grid, .grid--2, .grid--3 { grid-template-columns: minmax(0, 1fr); }
            .legend { grid-template-columns: minmax(0, 1fr); }
            .form-grid { grid-template-columns: minmax(0, 1fr); }
            .brand__text small { display: none; }
            .sidebar__foot { font-size: .7rem; }
            th, td { padding: 8px 8px; }
            .panel { padding: 15px; }
            .footer { margin-top: 22px; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: .001ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: .001ms !important;
                scroll-behavior: auto !important;
            }
        }
    </style>
</head>
<body>
<a class="skip-link" href="#main">Lewati ke konten</a>

<div class="shell">
    <aside class="sidebar">
        <a class="brand" href="?page=dashboard" aria-label="Bogana ERP — ke dashboard">
            <span class="brand__mark" aria-hidden="true">b</span>
            <span class="brand__text">
                <strong>bogana</strong>
                <small>ERP Operasional</small>
            </span>
        </a>

        <nav class="nav" aria-label="Navigasi utama">
            <?php foreach ($navGroups as $group): ?>
                <div class="nav__group">
                    <p class="nav__label"><?= e($group['label']) ?></p>
                    <?php foreach ($group['items'] as $item): ?>
                        <a
                            class="nav__link"
                            href="<?= e($item['href']) ?>"
                            <?= $active === $item['key'] ? 'aria-current="page"' : '' ?>
                        >
                            <svg viewBox="0 0 24 24" aria-hidden="true"><?= $icons[$item['key']] ?? '' ?></svg>
                            <span class="nav__copy">
                                <strong><?= e($item['label']) ?></strong>
                                <small><?= e($item['hint']) ?></small>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar__foot">
            <div class="side-card">
                <p class="side-card__label">Koneksi data</p>
                <p class="side-card__value"><span class="dot dot--live" aria-hidden="true"></span> FastAPI · internal</p>
                <a href="?page=health">Lihat status sistem →</a>
            </div>
            <p class="side-clock" data-clock><?= e(date('H:i')) ?> WIB</p>
            <p class="sidebar__meta">
                <span>PHP <?= e(PHP_VERSION) ?></span>
                <span>API v1</span>
            </p>
        </div>
    </aside>

    <main id="main" class="main">
        <div class="main__inner">
            <div class="utility">
                <p class="utility__crumb">Bogana <span>/</span> <?= e($title) ?></p>
                <p class="utility__status"><span class="dot" aria-hidden="true"></span> Operasional internal</p>
            </div>

            <?= $content ?>
        </div>

        <footer class="footer">
            <span>Bogana AI ERP System · Nasi Bogana B2B</span>
            <span>Data: FastAPI · PostgreSQL · Redis</span>
        </footer>
    </main>
</div>

<script>
    (function () {
        var clock = document.querySelector('[data-clock]');
        if (clock) {
            var tick = function () {
                try {
                    var time = new Intl.DateTimeFormat('id-ID', {
                        hour: '2-digit',
                        minute: '2-digit',
                        hour12: false,
                        timeZone: 'Asia/Jakarta'
                    }).format(new Date());
                    clock.textContent = time + ' WIB';
                } catch (error) {
                    /* biarkan waktu server tampil */
                }
            };
            tick();
            window.setInterval(tick, 30000);
        }

        document.querySelectorAll('form[data-submit-lock]').forEach(function (form) {
            form.addEventListener('submit', function () {
                var button = form.querySelector('button[type="submit"]');
                if (button) {
                    button.classList.add('is-loading');
                    button.disabled = true;
                }
            });
        });

        document.querySelectorAll('[data-count-for]').forEach(function (counter) {
            var field = document.getElementById(counter.getAttribute('data-count-for'));
            if (!field) {
                return;
            }
            var max = Number(field.getAttribute('maxlength') || 0);
            var update = function () {
                counter.textContent = field.value.length + (max ? ' / ' + max : '');
            };
            field.addEventListener('input', update);
            update();
        });

        document.querySelectorAll('[data-fill-target]').forEach(function (button) {
            button.addEventListener('click', function () {
                var field = document.getElementById(button.getAttribute('data-fill-target'));
                if (!field) {
                    return;
                }
                field.value = button.getAttribute('data-fill-value') || '';
                field.dispatchEvent(new Event('input'));
                field.focus();
            });
        });

        var auto = document.querySelector('[data-auto-refresh]');
        if (auto) {
            var key = 'bogana:auto-refresh';
            var interval = Number(auto.getAttribute('data-auto-refresh')) || 30000;
            var apply = function () {
                window.clearTimeout(window.__boganaRefresh);
                if (auto.checked) {
                    window.__boganaRefresh = window.setTimeout(function () {
                        window.location.reload();
                    }, interval);
                }
            };
            try {
                auto.checked = window.localStorage.getItem(key) === '1';
            } catch (error) {
                /* localStorage bisa diblokir */
            }
            auto.addEventListener('change', function () {
                try {
                    window.localStorage.setItem(key, auto.checked ? '1' : '0');
                } catch (error) {
                    /* abaikan */
                }
                apply();
            });
            apply();
        }

        document.querySelectorAll('[data-copy]').forEach(function (button) {
            button.addEventListener('click', function () {
                var value = button.getAttribute('data-copy') || '';
                if (!navigator.clipboard) {
                    return;
                }
                navigator.clipboard.writeText(value).then(function () {
                    var previous = button.textContent;
                    button.textContent = 'Tersalin';
                    window.setTimeout(function () {
                        button.textContent = previous;
                    }, 1400);
                }).catch(function () {
                    /* abaikan */
                });
            });
        });
    })();
</script>
</body>
</html>
