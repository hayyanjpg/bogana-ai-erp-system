<?php
/** @var array<string, mixed> $data */

$orders = is_array($data['orders'] ?? null) ? $data['orders'] : [];
$shortOrders = is_array($data['short_orders'] ?? null) ? $data['short_orders'] : [];
$lowStock = is_array($data['low_stock'] ?? null) ? $data['low_stock'] : [];
$topMenus = is_array($data['top_menus'] ?? null) ? $data['top_menus'] : [];

$dateIso = (string) ($data['date'] ?? date('Y-m-d'));
$dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $dateIso) ?: new DateTimeImmutable('today');
$previousDate = $dateObject->modify('-1 day')->format('Y-m-d');
$nextDate = $dateObject->modify('+1 day')->format('Y-m-d');
$isToday = $dateIso === date('Y-m-d');

$statusKeys = ['received', 'parsed', 'confirmed', 'short', 'qc_hold', 'fulfilled', 'parse_failed', 'cancelled'];
$totalOrders = max(0, (int) ($orders['total'] ?? 0));
$fulfilled = (int) ($orders['fulfilled'] ?? 0);
$shortCount = (int) ($orders['short'] ?? 0);
$qcHold = (int) ($orders['qc_hold'] ?? 0);
$attention = $shortCount + $qcHold;
$outOfStock = 0;
foreach ($lowStock as $stockItem) {
    if (($stockItem['status'] ?? '') === 'out') {
        $outOfStock++;
    }
}
$maxMenuQty = 0.0;
foreach ($topMenus as $menuItem) {
    $maxMenuQty = max($maxMenuQty, (float) ($menuItem['ordered_qty'] ?? 0));
}
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Ringkasan operasional</p>
        <h1>Dashboard</h1>
        <p class="lede">
            Posisi order, nilai penjualan, dan tekanan stok untuk
            <?= e(format_date_id($dateIso, true)) ?>.
        </p>
    </div>
    <div class="page-head__actions">
        <form method="get" class="filter-bar">
            <input type="hidden" name="page" value="dashboard">
            <a
                class="btn btn--secondary btn--sm"
                href="?page=dashboard&amp;date=<?= e($previousDate) ?>"
                aria-label="Hari sebelumnya"
                title="Hari sebelumnya"
            >&larr;</a>
            <label class="field">
                <span>Tanggal</span>
                <input class="input input--date" type="date" name="date" value="<?= e($dateIso) ?>">
            </label>
            <a
                class="btn btn--secondary btn--sm"
                href="?page=dashboard&amp;date=<?= e($nextDate) ?>"
                aria-label="Hari berikutnya"
                title="Hari berikutnya"
            >&rarr;</a>
            <button type="submit" class="btn btn--primary">Tampilkan</button>
        </form>
        <?php if (!$isToday): ?>
            <a class="btn btn--ghost" href="?page=dashboard">Hari ini</a>
        <?php endif; ?>
        <a class="btn btn--secondary" href="?page=orders">Buka order &amp; parser</a>
    </div>
</section>

<section class="kpi-grid" aria-label="Indikator utama">
    <article class="kpi kpi--brand">
        <p class="kpi__label">Total order</p>
        <strong class="kpi__value"><?= e($totalOrders) ?></strong>
        <p class="kpi__sub"><strong><?= e($fulfilled) ?></strong> selesai · <strong><?= e($shortCount) ?></strong> stok kurang</p>
    </article>
    <article class="kpi kpi--ink">
        <p class="kpi__label">Nilai order</p>
        <strong class="kpi__value"><small>Rp</small> <?= e(format_number((float) ($data['revenue_forecast'] ?? 0))) ?></strong>
        <p class="kpi__sub">Akumulasi order confirmed + fulfilled</p>
    </article>
    <article class="kpi kpi--turmeric">
        <p class="kpi__label">Perlu perhatian</p>
        <strong class="kpi__value"><?= e($attention) ?></strong>
        <p class="kpi__sub"><strong><?= e($qcHold) ?></strong> tahan QC · <strong><?= e($shortCount) ?></strong> menunggu restock</p>
    </article>
    <article class="kpi kpi--brick">
        <p class="kpi__label">Stok menipis</p>
        <strong class="kpi__value"><?= e(count($lowStock)) ?></strong>
        <p class="kpi__sub"><strong><?= e($outOfStock) ?></strong> bahan habis, perlu restock segera</p>
    </article>
</section>

<section class="panel" aria-labelledby="status-pipeline-title">
    <div class="panel__head">
        <div>
            <h2 class="panel__title" id="status-pipeline-title">Alur status order</h2>
            <p class="panel__hint">Distribusi seluruh order pada tanggal ini, dari diterima sampai selesai.</p>
        </div>
        <span class="panel__meta"><?= e($totalOrders) ?> order</span>
    </div>

    <?php if ($totalOrders === 0): ?>
        <div class="empty">
            <span class="empty__mark" aria-hidden="true">0</span>
            <p>Belum ada order pada tanggal ini. Coba pilih tanggal lain atau buat hasil parse dari halaman Order &amp; Parser.</p>
        </div>
    <?php else: ?>
        <div class="status-bar" role="img" aria-label="Distribusi status order">
            <?php foreach ($statusKeys as $statusKey): ?>
                <?php
                $count = (int) ($orders[$statusKey] ?? 0);
                if ($count <= 0) {
                    continue;
                }
                $width = ($count / $totalOrders) * 100;
                ?>
                <span
                    class="status-bar__seg seg--<?= e($statusKey) ?>"
                    style="width: <?= e(number_format($width, 2, '.', '')) ?>%"
                    title="<?= e(status_label($statusKey) . ': ' . $count) ?>"
                ></span>
            <?php endforeach; ?>
        </div>
        <div class="legend">
            <?php foreach ($statusKeys as $statusKey): ?>
                <div class="legend__item">
                    <span class="legend__dot seg--<?= e($statusKey) ?>" aria-hidden="true"></span>
                    <span class="legend__name"><?= e(status_label($statusKey)) ?></span>
                    <span class="legend__value"><?= e((int) ($orders[$statusKey] ?? 0)) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<div class="grid grid--2" style="margin-top: 16px">
    <section class="panel" aria-labelledby="attention-title">
        <div class="panel__head">
            <div>
                <h2 class="panel__title" id="attention-title">Order perlu perhatian</h2>
                <p class="panel__hint">Order berstatus short yang menunggu restock.</p>
            </div>
            <span class="pill pill--warning"><?= e(count($shortOrders)) ?> order</span>
        </div>

        <?php if ($shortOrders === []): ?>
            <div class="empty">
                <span class="empty__mark" aria-hidden="true">✓</span>
                <p>Tidak ada order short. Seluruh order pada tanggal ini aman diproses.</p>
            </div>
        <?php else: ?>
            <div class="stack">
                <?php foreach ($shortOrders as $order): ?>
                    <div class="list-row">
                        <span class="avatar" aria-hidden="true"><?= e(initials($order['customer_name'] ?? '')) ?></span>
                        <div class="list-row__body">
                            <p class="list-row__title"><?= e($order['customer_name'] ?? '-') ?></p>
                            <p class="list-row__meta mono"><?= e($order['order_code'] ?? '-') ?></p>
                        </div>
                        <a
                            class="btn btn--secondary btn--sm list-row__action"
                            href="?page=orders&amp;order_id=<?= e((int) ($order['order_id'] ?? 0)) ?>&amp;focus=check"
                        >Cek stok</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel" aria-labelledby="low-stock-title">
        <div class="panel__head">
            <div>
                <h2 class="panel__title" id="low-stock-title">Stok menipis</h2>
                <p class="panel__hint">Bahan yang sudah menyentuh atau melewati titik reorder.</p>
            </div>
            <a class="btn btn--ghost btn--sm" href="?page=stocks&amp;low_only=1">Lihat semua</a>
        </div>

        <?php if ($lowStock === []): ?>
            <div class="empty">
                <span class="empty__mark" aria-hidden="true">✓</span>
                <p>Semua bahan berada di atas titik reorder.</p>
            </div>
        <?php else: ?>
            <div class="stack">
                <?php foreach ($lowStock as $item): ?>
                    <?php
                    $tone = status_tone($item['status'] ?? '');
                    $percent = stock_percent((float) ($item['stock_on_hand'] ?? 0), (float) ($item['reorder_point'] ?? 0));
                    ?>
                    <div class="list-row">
                        <span class="avatar" aria-hidden="true"><?= e(strtoupper(substr((string) ($item['sku'] ?? '?'), 4, 2))) ?></span>
                        <div class="list-row__body">
                            <div style="display:flex; align-items:center; gap:8px; justify-content:space-between">
                                <p class="list-row__title"><?= e($item['name'] ?? '-') ?></p>
                                <span class="pill pill--<?= e($tone) ?>"><?= e(status_label($item['status'] ?? '')) ?></span>
                            </div>
                            <p class="list-row__meta mono">
                                <?= e(format_quantity((float) ($item['stock_on_hand'] ?? 0))) ?> tersedia ·
                                reorder <?= e(format_quantity((float) ($item['reorder_point'] ?? 0))) ?>
                            </p>
                            <div class="meter meter--<?= e($tone) ?>" style="margin-top: 6px">
                                <span class="meter__fill" style="--pct: <?= e(number_format($percent, 1, '.', '')) ?>%"></span>
                            </div>
                        </div>
                        <a class="btn btn--ghost btn--sm list-row__action" href="?page=stocks&amp;sku=<?= e(rawurlencode((string) ($item['sku'] ?? ''))) ?>">Detail</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<section class="panel" aria-labelledby="top-menu-title">
    <div class="panel__head">
        <div>
            <h2 class="panel__title" id="top-menu-title">Menu terlaris</h2>
            <p class="panel__hint">Maksimal lima menu dengan kuantitas terbesar pada tanggal ini.</p>
        </div>
        <span class="panel__meta">Sumber: <?= e($data['source'] ?? '-') ?></span>
    </div>

    <?php if ($topMenus === []): ?>
        <div class="empty">
            <span class="empty__mark" aria-hidden="true">—</span>
            <p>Belum ada item terjual pada tanggal ini.</p>
        </div>
    <?php else: ?>
        <div class="stack">
            <?php foreach ($topMenus as $index => $menu): ?>
                <?php
                $quantity = (float) ($menu['ordered_qty'] ?? 0);
                $width = $maxMenuQty > 0 ? ($quantity / $maxMenuQty) * 100 : 0;
                ?>
                <div class="list-row">
                    <span class="rank" aria-hidden="true"><?= e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></span>
                    <div class="list-row__body">
                        <p class="list-row__title"><?= e($menu['menu_name'] ?? '-') ?></p>
                        <p class="list-row__meta mono"><?= e($menu['menu_sku'] ?? '-') ?></p>
                        <div class="meter" style="margin-top: 6px">
                            <span class="meter__fill" style="--pct: <?= e(number_format($width, 1, '.', '')) ?>%"></span>
                        </div>
                    </div>
                    <span class="mono list-row__action"><?= e(format_quantity($quantity)) ?> porsi</span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <p class="panel__meta" style="margin: 14px 0 0">
        Diperbarui <?= e(format_datetime_id($data['generated_at'] ?? null)) ?>
        <?php if (($data['source'] ?? '') === 'redis'): ?>
            · dilayani cache Redis
        <?php endif; ?>
    </p>
</section>
