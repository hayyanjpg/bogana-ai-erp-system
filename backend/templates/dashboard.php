<?php
/** @var array<string, mixed> $data */

$orders = is_array($data['orders'] ?? null) ? $data['orders'] : [];
$shortOrders = is_array($data['short_orders'] ?? null) ? $data['short_orders'] : [];
$lowStock = is_array($data['low_stock'] ?? null) ? $data['low_stock'] : [];
$topMenus = is_array($data['top_menus'] ?? null) ? $data['top_menus'] : [];
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Ringkasan Operasional</p>
        <h1>Dashboard <?= e($data['date'] ?? '') ?></h1>
    </div>
    <form method="get" class="filters">
        <input type="hidden" name="page" value="dashboard">
        <label>
            Tanggal
            <input type="date" name="date" value="<?= e($data['date'] ?? '') ?>">
        </label>
        <button type="submit">Tampilkan</button>
    </form>
</section>

<section class="cards">
    <article class="card">
        <p>Total Order</p>
        <strong><?= e($orders['total'] ?? 0) ?></strong>
    </article>
    <article class="card">
        <p>Confirmed</p>
        <strong><?= e($orders['confirmed'] ?? 0) ?></strong>
    </article>
    <article class="card">
        <p>Fulfilled</p>
        <strong><?= e($orders['fulfilled'] ?? 0) ?></strong>
    </article>
    <article class="card">
        <p>Short</p>
        <strong><?= e($orders['short'] ?? 0) ?></strong>
    </article>
    <article class="card">
        <p>Revenue Forecast</p>
        <strong>Rp <?= e(format_number((float) ($data['revenue_forecast'] ?? 0))) ?></strong>
    </article>
</section>

<div class="grid">
    <section class="panel">
        <h2>Order Perlu Perhatian</h2>
        <?php if ($shortOrders === []): ?>
            <p class="muted">Tidak ada order berstatus short.</p>
        <?php else: ?>
            <table>
                <thead>
                <tr>
                    <th>Kode</th>
                    <th>Customer</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($shortOrders as $order): ?>
                    <tr>
                        <td><?= e($order['order_code'] ?? '') ?></td>
                        <td><?= e($order['customer_name'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2>Stok Menipis</h2>
        <?php if ($lowStock === []): ?>
            <p class="muted">Semua stok berada di atas reorder point.</p>
        <?php else: ?>
            <table>
                <thead>
                <tr>
                    <th>SKU</th>
                    <th>Bahan</th>
                    <th>Stok</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($lowStock as $item): ?>
                    <tr>
                        <td><?= e($item['sku'] ?? '') ?></td>
                        <td><?= e($item['name'] ?? '') ?></td>
                        <td><?= e(format_quantity((float) ($item['stock_on_hand'] ?? 0))) ?></td>
                        <td><span class="badge <?= e($item['status'] ?? '') ?>"><?= e($item['status'] ?? '') ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</div>

<section class="panel">
    <h2>Menu Terlaris</h2>
    <?php if ($topMenus === []): ?>
        <p class="muted">Belum ada order pada tanggal ini.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>SKU</th>
                <th>Menu</th>
                <th>Terjual</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($topMenus as $menu): ?>
                <tr>
                    <td><?= e($menu['menu_sku'] ?? '') ?></td>
                    <td><?= e($menu['menu_name'] ?? '') ?></td>
                    <td><?= e(format_quantity((float) ($menu['ordered_qty'] ?? 0))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>