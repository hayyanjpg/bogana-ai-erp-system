<?php
/** @var array<string, mixed> $data */
/** @var bool $lowOnly */
/** @var string $category */

$items = is_array($data['items'] ?? null) ? $data['items'] : [];
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Inventori</p>
        <h1>Stok Bahan</h1>
    </div>
    <form method="get" class="filters">
        <input type="hidden" name="page" value="stocks">
        <label>
            Kategori
            <input type="text" name="category" value="<?= e($category) ?>" placeholder="protein">
        </label>
        <label class="checkbox">
            <input type="checkbox" name="low_only" value="1" <?= $lowOnly ? 'checked' : '' ?>>
            Hanya stok menipis
        </label>
        <button type="submit">Filter</button>
    </form>
</section>

<section class="panel">
    <p class="muted">
        Sumber data: <?= e($data['source'] ?? '-') ?> ·
        <?= e($data['generated_at'] ?? '') ?>
    </p>
    <?php if ($items === []): ?>
        <p class="muted">Tidak ada bahan yang cocok dengan filter.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>SKU</th>
                <th>Bahan</th>
                <th>Satuan</th>
                <th>Stok</th>
                <th>Reorder</th>
                <th>Harga</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= e($item['sku'] ?? '') ?></td>
                    <td><?= e($item['name'] ?? '') ?></td>
                    <td><?= e($item['unit'] ?? '') ?></td>
                    <td><?= e(format_quantity((float) ($item['stock_on_hand'] ?? 0))) ?></td>
                    <td><?= e(format_quantity((float) ($item['reorder_point'] ?? 0))) ?></td>
                    <td>Rp <?= e(format_number((float) ($item['unit_cost'] ?? 0))) ?></td>
                    <td>
                        <span class="badge <?= e($item['status'] ?? '') ?>">
                            <?= e($item['status'] ?? '') ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>