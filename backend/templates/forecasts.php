<?php
/** @var array<string, mixed> $data */
/** @var int|null $menuId */

$items = is_array($data['items'] ?? null) ? $data['items'] : [];
$menuId = $menuId ?? null;
$fromDate = (string) ($data['from_date'] ?? date('Y-m-d'));
$days = max(1, (int) ($data['days'] ?? 7));

$rangeEnd = $fromDate;
try {
    $rangeEnd = (new DateTimeImmutable($fromDate))->modify('+' . max(0, $days - 1) . ' days')->format('Y-m-d');
} catch (Exception) {
    $rangeEnd = $fromDate;
}

$byDate = [];
$totalQuantity = 0.0;
$confidenceSum = 0.0;
$menuIds = [];
foreach ($items as $item) {
    $dateKey = substr((string) ($item['forecast_date'] ?? ''), 0, 10);
    if ($dateKey === '') {
        continue;
    }
    $byDate[$dateKey][] = $item;
    $totalQuantity += (float) ($item['forecast_qty'] ?? 0);
    $confidenceSum += (float) ($item['confidence'] ?? 0);
    $menuIds[(int) ($item['menu_id'] ?? 0)] = true;
}
ksort($byDate);

$averageConfidence = $items !== [] ? $confidenceSum / count($items) : 0.0;
$averageTone = confidence_tone($averageConfidence);

$quickRangeQuery = ['page' => 'forecasts', 'from_date' => $fromDate];
if ($menuId !== null) {
    $quickRangeQuery['menu_id'] = $menuId;
}
$clearMenuQuery = ['page' => 'forecasts', 'from_date' => $fromDate, 'days' => $days];
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Perencanaan produksi</p>
        <h1>Forecast permintaan</h1>
        <p class="lede">
            Prakiraan permintaan harian per menu beserta tingkat keyakinan model. Gunakan sebagai
            saran produksi dan restock, bukan kewajiban produksi.
        </p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--secondary" href="?page=forecasts&amp;from_date=<?= e($fromDate) ?>&amp;days=<?= e($days) ?><?= $menuId !== null ? '&amp;menu_id=' . e($menuId) : '' ?>">
            Muat ulang
        </a>
        <a class="btn btn--ghost" href="?page=stocks">Cek stok</a>
    </div>
</section>

<section class="panel" aria-label="Filter forecast" style="margin-bottom: 16px">
    <form method="get" class="filter-bar" style="padding: 0; border: 0; background: transparent">
        <input type="hidden" name="page" value="forecasts">
        <label class="field">
            <span>Mulai</span>
            <input class="input input--date" type="date" name="from_date" value="<?= e($fromDate) ?>">
        </label>
        <label class="field">
            <span>Hari</span>
            <input class="input input--short" type="number" name="days" min="1" max="30" value="<?= e($days) ?>">
        </label>
        <label class="field">
            <span>Menu ID <small style="text-transform:none; letter-spacing:0">(opsional)</small></span>
            <input
                class="input input--short"
                type="number"
                name="menu_id"
                min="1"
                step="1"
                value="<?= e($menuId ?? '') ?>"
                placeholder="semua"
            >
        </label>
        <button class="btn btn--primary" type="submit">Tampilkan</button>
        <div class="chips" style="margin-left: auto; align-self: center" aria-label="Rentang cepat">
            <?php foreach ([7, 14, 30] as $range): ?>
                <?php $quickRangeQuery['days'] = $range; ?>
                <a
                    class="chip"
                    href="?<?= e(http_build_query($quickRangeQuery)) ?>"
                    style="<?= $days === $range ? 'border-color: var(--brand); color: var(--brand);' : '' ?>"
                ><?= e($range) ?> hari</a>
            <?php endforeach; ?>
        </div>
    </form>
</section>

<?php if ($menuId !== null): ?>
    <div class="alert alert--info" style="margin-bottom: 16px">
        <p class="alert__title">Filter menu aktif · ID <?= e($menuId) ?></p>
        <p class="alert__text">
            Menampilkan forecast untuk satu menu saja.
            <a href="?<?= e(http_build_query($clearMenuQuery)) ?>">Tampilkan semua menu</a>
        </p>
    </div>
<?php endif; ?>

<section class="kpi-grid" aria-label="Ringkasan forecast">
    <article class="kpi kpi--brand">
        <p class="kpi__label">Total prakiraan</p>
        <strong class="kpi__value"><?= e(format_quantity($totalQuantity)) ?></strong>
        <p class="kpi__sub">Porsi untuk <?= e($days) ?> hari ke depan</p>
    </article>
    <article class="kpi kpi--ink">
        <p class="kpi__label">Menu tercakup</p>
        <strong class="kpi__value"><?= e(count($menuIds)) ?></strong>
        <p class="kpi__sub">Menu dengan data forecast</p>
    </article>
    <article class="kpi kpi--turmeric">
        <p class="kpi__label">Rata-rata confidence</p>
        <strong class="kpi__value"><?= e(format_number($averageConfidence * 100, 1)) ?>%</strong>
        <p class="kpi__sub"><?= e(confidence_label($averageConfidence)) ?> · <?= e(count($items)) ?> baris data</p>
    </article>
    <article class="kpi kpi--brand">
        <p class="kpi__label">Rentang</p>
        <strong class="kpi__value" style="font-size: 1.45rem; line-height: 1.2">
            <?= e(format_date_id($fromDate)) ?>
        </strong>
        <p class="kpi__sub">hingga <?= e(format_date_id($rangeEnd)) ?></p>
    </article>
</section>

<?php if ($items === []): ?>
    <section class="panel">
        <div class="empty">
            <span class="empty__mark" aria-hidden="true">↗</span>
            <p>
                Belum ada data forecast untuk rentang ini. Coba mulai dari tanggal lain,
                perluas jumlah hari, atau hapus filter menu.
            </p>
        </div>
    </section>
<?php else: ?>
    <div class="grid grid--2">
        <?php foreach ($byDate as $dateKey => $dayItems): ?>
            <?php
            $dayQuantity = 0.0;
            $dayConfidence = 0.0;
            foreach ($dayItems as $dayItem) {
                $dayQuantity += (float) ($dayItem['forecast_qty'] ?? 0);
                $dayConfidence += (float) ($dayItem['confidence'] ?? 0);
            }
            $dayAverage = count($dayItems) > 0 ? $dayConfidence / count($dayItems) : 0.0;
            $dayTone = confidence_tone($dayAverage);
            ?>
            <section class="panel" aria-labelledby="forecast-<?= e($dateKey) ?>">
                <div class="result-head" style="margin-bottom: 10px">
                    <div>
                        <h3 id="forecast-<?= e($dateKey) ?>"><?= e(format_date_id($dateKey, true)) ?></h3>
                        <p><?= e(count($dayItems)) ?> menu · confidence rata-rata <?= e(format_number($dayAverage * 100, 0)) ?>%</p>
                    </div>
                    <span class="pill pill--accent"><?= e(format_quantity($dayQuantity)) ?> porsi</span>
                </div>

                <div class="stack">
                    <?php foreach ($dayItems as $item): ?>
                        <?php
                        $confidence = (float) ($item['confidence'] ?? 0);
                        $tone = confidence_tone($confidence);
                        $percent = max(0.0, min(100.0, $confidence * 100));
                        ?>
                        <div class="list-row forecast-row">
                            <div class="list-row__body">
                                <p class="list-row__title"><?= e($item['menu_name'] ?? '-') ?></p>
                                <p class="list-row__meta mono"><?= e($item['menu_sku'] ?? '-') ?></p>
                                <div class="meter meter--<?= e($tone) ?>" style="margin-top: 6px">
                                    <span class="meter__fill" style="--pct: <?= e(number_format($percent, 1, '.', '')) ?>%"></span>
                                </div>
                            </div>
                            <div style="text-align:right">
                                <strong class="mono"><?= e(format_quantity((float) ($item['forecast_qty'] ?? 0))) ?></strong>
                                <p class="list-row__meta"><?= e(format_number($percent, 0)) ?>% · <?= e(confidence_label($confidence)) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<p class="panel__meta" style="margin-top: 14px">
    Model <?= e($data['model_version'] ?? '-') ?>
    · rentang <?= e(format_date_id($fromDate)) ?> – <?= e(format_date_id($rangeEnd)) ?>
    · confidence keseluruhan <?= e(format_number($averageConfidence * 100, 1)) ?>%
    <span class="pill pill--<?= e($averageTone) ?>" style="margin-left: 6px"><?= e(confidence_label($averageConfidence)) ?></span>
</p>
