<?php
/** @var string $content */
/** @var string $title */

$active = match ($title) {
    'Dashboard' => 'dashboard',
    'Stok Bahan' => 'stocks',
    'Forecast' => 'forecasts',
    'Status Sistem' => 'health',
    default => '',
};
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> — Bogana ERP</title>
    <style>
        :root {
            --bg: #f1f5f9;
            --panel: #ffffff;
            --ink: #0f172a;
            --muted: #64748b;
            --line: #e2e8f0;
            --brand: #0f766e;
            --brand-dark: #115e59;
            --danger: #b91c1c;
            --warn: #b45309;
            --ok: #15803d;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: var(--bg);
            color: var(--ink);
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 14px 24px;
            background: #0f172a;
            color: #fff;
        }

        .brand { font-weight: 700; letter-spacing: .02em; font-size: 1.1rem; }
        .brand span { color: #5eead4; }

        nav { display: flex; gap: 6px; flex-wrap: wrap; }
        nav a {
            color: #cbd5e1;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: .92rem;
        }

        nav a:hover, nav a.active { background: #1e293b; color: #fff; }

        main { max-width: 1120px; margin: 0 auto; padding: 24px; }

        .page-head {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .eyebrow {
            margin: 0 0 4px;
            color: var(--brand);
            font-size: .78rem;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        h1 { margin: 0; font-size: 1.6rem; }
        h2 { margin: 0 0 12px; font-size: 1.05rem; }

        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }

        .card {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 16px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
        }

        .card p {
            margin: 0 0 6px;
            color: var(--muted);
            font-size: .8rem;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .card strong { font-size: 1.55rem; }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 16px;
            margin-bottom: 18px;
        }

        .panel {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 18px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
        }

        table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        th, td { text-align: left; padding: 9px 10px; border-bottom: 1px solid var(--line); vertical-align: top; }
        th { color: var(--muted); font-size: .75rem; letter-spacing: .06em; text-transform: uppercase; }
        tbody tr:last-child td { border-bottom: 0; }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .badge.ok { background: #dcfce7; color: var(--ok); }
        .badge.low, .badge.degraded { background: #fef3c7; color: var(--warn); }
        .badge.out, .badge.down { background: #fee2e2; color: var(--danger); }

        .muted { color: var(--muted); font-size: .9rem; }

        .filters { display: flex; gap: 8px; align-items: flex-end; flex-wrap: wrap; }
        .filters label { display: flex; flex-direction: column; gap: 4px; font-size: .78rem; color: var(--muted); }
        .checkbox { display: flex; align-items: center; gap: 6px; font-size: .85rem; color: var(--ink); }
        .filters .checkbox { flex-direction: row; }

        .card strong .badge { font-size: .8rem; }

        input, button { font: inherit; }
        input[type="text"], input[type="date"], input[type="number"] {
            padding: 7px 9px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
        }

        button {
            padding: 8px 14px;
            border: 0;
            border-radius: 8px;
            background: var(--brand);
            color: #fff;
            font-weight: 600;
            cursor: pointer;
        }

        button:hover { background: var(--brand-dark); }

        .error { border-left: 4px solid var(--danger); }

        footer { max-width: 1120px; margin: 0 auto; padding: 0 24px 24px; color: var(--muted); font-size: .78rem; }

        @media (max-width: 640px) {
            .topbar { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
<header class="topbar">
    <div class="brand">Bogana <span>ERP</span></div>
    <nav>
        <a href="?page=dashboard" class="<?= $active === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
        <a href="?page=stocks" class="<?= $active === 'stocks' ? 'active' : '' ?>">Stok Bahan</a>
        <a href="?page=forecasts" class="<?= $active === 'forecasts' ? 'active' : '' ?>">Forecast</a>
        <a href="?page=health" class="<?= $active === 'health' ? 'active' : '' ?>">Status</a>
    </nav>
</header>

<main>
    <?= $content ?>
</main>

<footer>Bogana AI ERP System — PHP <?= e(PHP_VERSION) ?> · AI Service: FastAPI</footer>
</body>
</html>