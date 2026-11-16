<?php
/** @var array<string, mixed> $data */

$items = is_array($data['items'] ?? null) ? $data['items'] : [];
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Perencanaan Produksi</p>
        <h1>Forecast Permintaan</h1>
    </div>
    <form method="get" class="filters">
        <input type="hidden" name="page" value="forecasts">
        <label>
            Mulai
            <input type="date" name="from_date" value="<?= e($data['from_date'] ?? '') ?>">
        </label>
        <label>
            Hari
            <input type="number" name="days" min="1" max="30" value="<?= e($data['days'] ?? 7) ?>">
        </label>
        <label>
            Menu ID
            <input type="number" name="menu_id" min="1" placeholder="1">
        </label>
        <button type="submit">Tampilkan</button>
    </form>
</section>

<section class="cards">
    <article class="card">
        <p>Mulai</p>
        <strong><?= e($data['from_date'] ?? '-') ?></strong>
    </article>
    <article class="card">
        <p>Hari</p>
        <strong><?= e($data['days'] ?? 0) ?></strong>
    </article>
    <article class="card">
        <p>Versi Model</p>
        <strong><?= e($data['model_version'] ?? '-') ?></strong>
    </article>
</section>

<section class="panel">
    <?php if ($items === []): ?>
        <p class="muted">Belum ada data forecast untuk rentang ini.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Tanggal</th>
                <th>Menu</th>
                <th>Forecast</th>
                <th>Confidence</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= e($item['forecast_date'] ?? '') ?></td>
                    <td>
                        <?= e($item['menu_name'] ?? '') ?>
                        <span class="muted"><?= e($item['menu_sku'] ?? '') ?></span>
                    </td>
                    <td><?= e(format_quantity((float) ($item['forecast_qty'] ?? 0))) ?></td>
                    <td><?= e(format_number((float) ($item['confidence'] ?? 0) * 100, 1)) ?>%</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>