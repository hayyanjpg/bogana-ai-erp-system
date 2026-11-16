<?php
/** @var array<string, mixed> $data */
/** @var bool $lowOnly */
/** @var string $category */
/** @var string $sku */
/** @var array<string, mixed>|null $detail */
/** @var array<string, mixed>|null $detailError */

$items = is_array($data['items'] ?? null) ? $data['items'] : [];
$detail = is_array($detail ?? null) ? $detail : null;
$detailError = is_array($detailError ?? null) ? $detailError : null;
$lowOnly = (bool) ($lowOnly ?? false);
$category = (string) ($category ?? '');
$sku = (string) ($sku ?? '');

$lowCount = 0;
$outCount = 0;
$inventoryValue = 0.0;
foreach ($items as $item) {
    $status = (string) ($item['status'] ?? '');
    if ($status === 'low') {
        $lowCount++;
    }
    if ($status === 'out') {
        $outCount++;
    }
    $inventoryValue += (float) ($item['stock_on_hand'] ?? 0) * (float) ($item['unit_cost'] ?? 0);
}

$baseQuery = ['page' => 'stocks'];
if ($category !== '') {
    $baseQuery['category'] = $category;
}
if ($lowOnly) {
    $baseQuery['low_only'] = '1';
}
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Inventori</p>
        <h1>Stok bahan</h1>
        <p class="lede">
            Posisi bahan baku, titik reorder, dan nilai persediaan. Data dilayani dari cache Redis
            dan jatuh kembali ke PostgreSQL bila cache tidak tersedia.
        </p>
    </div>
    <form method="get" class="filter-bar">
        <input type="hidden" name="page" value="stocks">
        <label class="field">
            <span>Kategori</span>
            <input
                class="input"
                type="text"
                name="category"
                list="category-options"
                value="<?= e($category) ?>"
                placeholder="protein"
            >
            <datalist id="category-options">
                <option value="sembako"></option>
                <option value="protein"></option>
                <option value="bumbu"></option>
                <option value="bahan"></option>
            </datalist>
        </label>
        <label class="switch">
            <input type="checkbox" name="low_only" value="1" <?= $lowOnly ? 'checked' : '' ?>>
            <span class="switch__track" aria-hidden="true"></span>
            <span>Hanya menipis</span>
        </label>
        <button class="btn btn--primary" type="submit">Terapkan</button>
        <?php if ($category !== '' || $lowOnly): ?>
            <a class="btn btn--ghost" href="?page=stocks">Reset</a>
        <?php endif; ?>
    </form>
</section>

<section class="kpi-grid" aria-label="Ringkasan inventori">
    <article class="kpi kpi--brand">
        <p class="kpi__label">Bahan terpantau</p>
        <strong class="kpi__value"><?= e(count($items)) ?></strong>
        <p class="kpi__sub">Pada filter saat ini</p>
    </article>
    <article class="kpi kpi--turmeric">
        <p class="kpi__label">Stok menipis</p>
        <strong class="kpi__value"><?= e($lowCount) ?></strong>
        <p class="kpi__sub">Sudah menyentuh titik reorder</p>
    </article>
    <article class="kpi kpi--brick">
        <p class="kpi__label">Bahan habis</p>
        <strong class="kpi__value"><?= e($outCount) ?></strong>
        <p class="kpi__sub">Perlu restock sebelum produksi</p>
    </article>
    <article class="kpi kpi--ink">
        <p class="kpi__label">Nilai persediaan</p>
        <strong class="kpi__value"><small>Rp</small> <?= e(format_number($inventoryValue)) ?></strong>
        <p class="kpi__sub">Stok × harga satuan pada filter ini</p>
    </article>
</section>

<?php if ($detailError !== null): ?>
    <div class="alert alert--danger" style="margin-bottom: 16px">
        <p class="alert__title">Detail bahan gagal dimuat</p>
        <p class="alert__text"><?= e($detailError['message'] ?? 'Bahan tidak ditemukan.') ?></p>
        <p class="alert__meta"><?= e($detailError['code'] ?? 'ERROR') ?></p>
    </div>
<?php endif; ?>

<?php if ($detail !== null): ?>
    <?php
    $detailStatus = (string) ($detail['status'] ?? '');
    $detailTone = status_tone($detailStatus);
    $detailStock = (float) ($detail['stock_on_hand'] ?? 0);
    $detailReorder = (float) ($detail['reorder_point'] ?? 0);
    $detailPercent = stock_percent($detailStock, $detailReorder);
    $detailValue = $detailStock * (float) ($detail['unit_cost'] ?? 0);
    $usedIn = is_array($detail['used_in_menus'] ?? null) ? $detail['used_in_menus'] : [];
    ?>
    <section class="panel" aria-labelledby="stock-detail-title" style="margin-bottom: 16px">
        <div class="panel__head">
            <div>
                <p class="eyebrow" style="margin-bottom: 4px">Detail bahan</p>
                <div class="detail-name">
                    <h2 id="stock-detail-title"><?= e($detail['name'] ?? '-') ?></h2>
                    <span class="pill pill--<?= e($detailTone) ?>"><?= e(status_label($detailStatus)) ?></span>
                </div>
                <p class="panel__hint mono"><?= e($detail['sku'] ?? '-') ?></p>
            </div>
            <a class="btn btn--ghost btn--sm" href="?<?= e(http_build_query($baseQuery)) ?>">Tutup detail</a>
        </div>

        <div class="detail-hero">
            <div style="display:grid; gap:16px">
                <div class="detail-gauge">
                    <div class="meter-row__head">
                        <span>Stok saat ini</span>
                        <strong>
                            <?= e(format_quantity($detailStock)) ?> <?= e($detail['unit'] ?? '') ?>
                        </strong>
                    </div>
                    <div class="meter meter--<?= e($detailTone) ?>">
                        <span class="meter__fill" style="--pct: <?= e(number_format($detailPercent, 1, '.', '')) ?>%"></span>
                    </div>
                    <p class="panel__hint" style="margin: 0">
                        Titik reorder <?= e(format_quantity($detailReorder)) ?> <?= e($detail['unit'] ?? '') ?>.
                        Gauge penuh saat stok mencapai dua kali reorder point.
                    </p>
                </div>

                <div class="chips">
                    <?php if ($usedIn === []): ?>
                        <span class="chip">Belum dipakai resep</span>
                    <?php else: ?>
                        <?php foreach ($usedIn as $menuSku): ?>
                            <span class="chip"><?= e($menuSku) ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <dl class="kv" style="grid-template-columns: repeat(2, minmax(0, 1fr))">
                <div>
                    <dt>Harga satuan</dt>
                    <dd>Rp <?= e(format_number((float) ($detail['unit_cost'] ?? 0))) ?></dd>
                </div>
                <div>
                    <dt>Nilai stok</dt>
                    <dd>Rp <?= e(format_number($detailValue)) ?></dd>
                </div>
                <div>
                    <dt>Satuan</dt>
                    <dd><?= e($detail['unit'] ?? '-') ?></dd>
                </div>
                <div>
                    <dt>Dipakai di</dt>
                    <dd><?= e(count($usedIn)) ?> menu</dd>
                </div>
            </dl>
        </div>
    </section>
<?php endif; ?>

<section class="panel" aria-labelledby="stock-list-title">
    <div class="panel__head">
        <div>
            <h2 class="panel__title" id="stock-list-title">Daftar bahan</h2>
            <p class="panel__hint">
                Sumber data <span class="code"><?= e($data['source'] ?? '-') ?></span>
                · <?= e(format_datetime_id($data['generated_at'] ?? null)) ?>
            </p>
        </div>
        <?php if ($category !== '' || $lowOnly): ?>
            <span class="pill pill--accent">Filter aktif</span>
        <?php endif; ?>
    </div>

    <?php if ($items === []): ?>
        <div class="empty">
            <span class="empty__mark" aria-hidden="true">∅</span>
            <p>Tidak ada bahan yang cocok dengan filter. Coba kategori lain atau matikan filter stok menipis.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Bahan</th>
                    <th>Stok</th>
                    <th class="num col-reorder">Reorder</th>
                    <th class="num col-price">Harga satuan</th>
                    <th class="num col-value">Nilai stok</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <?php
                    $itemStatus = (string) ($item['status'] ?? '');
                    $itemTone = status_tone($itemStatus);
                    $stock = (float) ($item['stock_on_hand'] ?? 0);
                    $reorder = (float) ($item['reorder_point'] ?? 0);
                    $percent = stock_percent($stock, $reorder);
                    $rowQuery = $baseQuery;
                    $rowQuery['sku'] = (string) ($item['sku'] ?? '');
                    ?>
                    <tr>
                        <td>
                            <a class="table-title" href="?<?= e(http_build_query($rowQuery)) ?>">
                                <?= e($item['name'] ?? '-') ?>
                            </a>
                            <span class="table-sub"><?= e($item['sku'] ?? '-') ?></span>
                        </td>
                        <td style="min-width: 150px">
                            <div class="meter-row">
                                <div class="meter-row__head">
                                    <strong><?= e(format_quantity($stock)) ?> <?= e($item['unit'] ?? '') ?></strong>
                                    <span><?= e(format_number($percent, 0)) ?>%</span>
                                </div>
                                <div class="meter meter--<?= e($itemTone) ?>">
                                    <span class="meter__fill" style="--pct: <?= e(number_format($percent, 1, '.', '')) ?>%"></span>
                                </div>
                            </div>
                        </td>
                        <td class="num col-reorder"><?= e(format_quantity($reorder)) ?> <?= e($item['unit'] ?? '') ?></td>
                        <td class="num col-price">Rp <?= e(format_number((float) ($item['unit_cost'] ?? 0))) ?></td>
                        <td class="num col-value">Rp <?= e(format_number($stock * (float) ($item['unit_cost'] ?? 0))) ?></td>
                        <td>
                            <span class="pill pill--<?= e($itemTone) ?>"><?= e(status_label($itemStatus)) ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
