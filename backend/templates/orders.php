<?php
/** @var array<string, mixed>|null $parseResult */
/** @var array<string, mixed>|null $parseError */
/** @var array<string, mixed>|null $checkResult */
/** @var array<string, mixed>|null $checkError */
/** @var array<string, mixed>|null $allocateResult */
/** @var array<string, mixed>|null $allocateError */
/** @var int|null $orderId */
/** @var string|null $focus */
/** @var array<string, string> $form */

$parseResult = is_array($parseResult ?? null) ? $parseResult : null;
$parseError = is_array($parseError ?? null) ? $parseError : null;
$checkResult = is_array($checkResult ?? null) ? $checkResult : null;
$checkError = is_array($checkError ?? null) ? $checkError : null;
$allocateResult = is_array($allocateResult ?? null) ? $allocateResult : null;
$allocateError = is_array($allocateError ?? null) ? $allocateError : null;
$form = is_array($form ?? null) ? $form : [];
$orderIdValue = $orderId !== null ? (string) $orderId : '';
$focus = $focus ?? null;

$channels = [
    'whatsapp' => 'WhatsApp',
    'email' => 'Email',
    'phone' => 'Telepon',
    'manual' => 'Manual',
];

$samples = [
    ['label' => 'Order nasi', 'value' => 'gan besok pagi 40 nasi bogana, 15 nasi langgi untuk syukuran'],
    ['label' => 'Order box', 'value' => '20 nasi box paket a, 12 nasi box paket b, kirim jam 6'],
    ['label' => 'Contoh warning', 'value' => '10 pizza hawaiian, 8 nasi gudeg'],
];

$parseItems = $parseResult !== null && is_array($parseResult['items'] ?? null) ? $parseResult['items'] : [];
$parseWarnings = $parseResult !== null && is_array($parseResult['warnings'] ?? null) ? $parseResult['warnings'] : [];
$parseTotal = 0.0;
foreach ($parseItems as $item) {
    $parseTotal += (float) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0);
}
$confidence = (float) ($parseResult['confidence'] ?? 0);
$confidencePercent = max(0.0, min(100.0, $confidence * 100));
$confidenceTone = confidence_tone($confidence);

$checkItems = $checkResult !== null && is_array($checkResult['requirements'] ?? null) ? $checkResult['requirements'] : [];
$checkInsufficient = $checkResult !== null && is_array($checkResult['insufficient'] ?? null) ? $checkResult['insufficient'] : [];
$allocateDeductions = $allocateResult !== null && is_array($allocateResult['deductions'] ?? null) ? $allocateResult['deductions'] : [];

$renderError = static function (array $error): void {
    $status = (int) ($error['status'] ?? 0);
    $details = is_array($error['details'] ?? null) ? $error['details'] : [];
    ?>
    <div class="alert alert--danger">
        <p class="alert__title"><?= e($error['message'] ?? 'Permintaan gagal') ?></p>
        <p class="alert__meta">
            <?= e($error['code'] ?? 'ERROR') ?><?= $status > 0 ? ' · HTTP ' . e($status) : '' ?>
        </p>
        <?php if ($details !== []): ?>
            <ul class="alert__list">
                <?php foreach ($details as $detail): ?>
                    <li>
                        <?php if (is_array($detail)): ?>
                            <span class="mono"><?= e($detail['field'] ?? '-') ?></span>
                            — <?= e($detail['issue'] ?? $detail['message'] ?? '') ?>
                        <?php else: ?>
                            <?= e($detail) ?>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <?php
};
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Operasional order</p>
        <h1>Order &amp; parser</h1>
        <p class="lede">
            Ubah teks order dari WhatsApp, email, atau telepon menjadi item menu. Setelah itu cek
            kecukupan bahan dan jalankan alokasi stok untuk order yang sudah tersimpan.
        </p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--secondary btn--sm" href="#check">Cek stok</a>
        <a class="btn btn--secondary btn--sm" href="#allocate">Alokasi</a>
        <a class="btn btn--ghost btn--sm" href="?page=stocks">Lihat stok</a>
    </div>
</section>

<div class="grid grid--workbench">
    <form method="post" action="?page=orders" class="panel" data-submit-lock aria-labelledby="parse-form-title">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="parse">

        <div class="panel__head">
            <div>
                <h2 class="panel__title" id="parse-form-title">Urai teks order</h2>
                <p class="panel__hint">Parser mencocokkan nama menu dan kuantitas. Tidak menyimpan order.</p>
            </div>
            <span class="pill pill--accent">NLP</span>
        </div>

        <div class="form-grid">
            <label class="field field--full">
                <span>Teks order</span>
                <textarea
                    class="textarea"
                    id="raw_text"
                    name="raw_text"
                    rows="4"
                    minlength="3"
                    maxlength="500"
                    required
                    placeholder="Contoh: gan besok pagi 40 nasi bogana, 15 nasi langgi untuk syukuran"
                ><?= e($form['raw_text'] ?? '') ?></textarea>
                <span class="counter" data-count-for="raw_text">0 / 500</span>
            </label>

            <label class="field">
                <span>Nama customer</span>
                <input
                    class="input"
                    type="text"
                    name="customer_name"
                    minlength="2"
                    maxlength="120"
                    required
                    value="<?= e($form['customer_name'] ?? '') ?>"
                    placeholder="Warung Bu Sri"
                >
            </label>

            <label class="field">
                <span>Channel</span>
                <select class="select" name="channel">
                    <?php foreach ($channels as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= ($form['channel'] ?? 'manual') === $value ? 'selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field">
                <span>Tanggal order</span>
                <input class="input" type="date" name="order_date" value="<?= e($form['order_date'] ?? '') ?>">
                <span class="field__hint">Kosongkan untuk default besok dari API.</span>
            </label>

            <label class="field">
                <span>Catatan <small style="text-transform:none; letter-spacing:0">(opsional)</small></span>
                <input
                    class="input"
                    type="text"
                    name="notes"
                    maxlength="500"
                    value="<?= e($form['notes'] ?? '') ?>"
                    placeholder="Diantar sebelum jam 07.00"
                >
            </label>
        </div>

        <div class="quick-samples" aria-label="Contoh teks order">
            <?php foreach ($samples as $sample): ?>
                <button
                    class="sample-btn"
                    type="button"
                    data-fill-target="raw_text"
                    data-fill-value="<?= e($sample['value']) ?>"
                ><?= e($sample['label']) ?></button>
            <?php endforeach; ?>
        </div>

        <button class="btn btn--primary btn--block" type="submit" style="margin-top: 15px">
            Urai order
        </button>
    </form>

    <section class="panel" aria-labelledby="parse-result-title">
        <div class="panel__head">
            <div>
                <h2 class="panel__title" id="parse-result-title">Hasil parse</h2>
                <p class="panel__hint">Item menu, keyakinan parser, dan peringatan yang perlu direview admin.</p>
            </div>
            <?php if ($parseResult !== null): ?>
                <span class="pill pill--<?= e($confidenceTone) ?>"><?= e(confidence_label($confidence)) ?></span>
            <?php endif; ?>
        </div>

        <?php if ($parseError !== null): ?>
            <?php $renderError($parseError); ?>
        <?php elseif ($parseResult !== null): ?>
            <div class="result-head">
                <div>
                    <h3><?= e(format_date_id((string) ($parseResult['order_date'] ?? ''), true)) ?></h3>
                    <p>
                        Parse ID
                        <span class="code"><?= e($parseResult['parse_id'] ?? '-') ?></span>
                        <button
                            class="btn btn--ghost btn--sm"
                            type="button"
                            data-copy="<?= e($parseResult['parse_id'] ?? '') ?>"
                        >Salin</button>
                    </p>
                </div>
                <div class="confidence">
                    <div class="confidence__head">
                        <span>Keyakinan parser</span>
                        <strong><?= e(format_number($confidencePercent, 1)) ?>%</strong>
                    </div>
                    <div class="meter meter--<?= e($confidenceTone) ?>">
                        <span class="meter__fill" style="--pct: <?= e(number_format($confidencePercent, 1, '.', '')) ?>%"></span>
                    </div>
                </div>
            </div>

            <?php if ($parseItems === []): ?>
                <div class="alert alert--warning">
                    <p class="alert__title">Tidak ada item yang berhasil dikenali</p>
                    <p class="alert__text">Periksa ejaan nama menu atau lengkapi kuantitas, lalu urai ulang teks order.</p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th class="col-line">Teks baris</th>
                            <th>Menu</th>
                            <th class="num">Qty</th>
                            <th class="num">Harga</th>
                            <th class="num">Subtotal</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($parseItems as $item): ?>
                            <?php
                            $quantity = (float) ($item['quantity'] ?? 0);
                            $unitPrice = (float) ($item['unit_price'] ?? 0);
                            ?>
                            <tr>
                                <td class="mono col-line"><?= e($item['line_text'] ?? '-') ?></td>
                                <td>
                                    <span class="table-title"><?= e($item['menu_name'] ?? '-') ?></span>
                                    <span class="table-sub"><?= e($item['menu_sku'] ?? '-') ?></span>
                                    <span class="table-sub table-sub--mobile">“<?= e($item['line_text'] ?? '-') ?>”</span>
                                </td>
                                <td class="num"><?= e(format_quantity($quantity)) ?></td>
                                <td class="num">Rp <?= e(format_number($unitPrice)) ?></td>
                                <td class="num">Rp <?= e(format_number($quantity * $unitPrice)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="table-summary">
                    <span class="table-summary__label">Estimasi total</span>
                    <span class="table-summary__value">Rp <?= e(format_number($parseTotal)) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($parseWarnings !== []): ?>
                <div class="warnings">
                    <?php foreach ($parseWarnings as $warning): ?>
                        <div class="warning-row">
                            <span class="warning-row__code"><?= e($warning['code'] ?? 'WARNING') ?></span>
                            <div>
                                <p><?= e($warning['message'] ?? 'Perlu review admin') ?></p>
                                <?php if (!empty($warning['line_text'])): ?>
                                    <small>“<?= e($warning['line_text']) ?>”</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="alert alert--info" style="margin-top: 14px">
                <p class="alert__title">Hasil ini belum menjadi order</p>
                <p class="alert__text">
                    Endpoint parser hanya mengembalikan item dan warning. Order contoh yang sudah tersimpan
                    dapat dicek stoknya lewat panel di bawah, atau dari daftar “Order perlu perhatian” di dashboard.
                </p>
            </div>
        <?php else: ?>
            <div class="empty">
                <span class="empty__mark" aria-hidden="true">Aa</span>
                <p>
                    Tempel teks order dari WhatsApp atau email, lalu tekan <strong>Urai order</strong>.
                    Contoh cepat tersedia di bawah kolom teks.
                </p>
            </div>
            <div class="alert alert--info" style="margin-top: 14px">
                <p class="alert__title">Yang dideteksi parser</p>
                <p class="alert__text">
                    Nama menu, kuantitas, harga satuan, dan warning seperti menu tidak dikenal,
                    kuantitas hilang, duplikat baris, atau keyakinan rendah.
                </p>
            </div>
        <?php endif; ?>
    </section>
</div>

<div class="section-divider">Kontrol order tersimpan</div>

<div class="grid grid--ops">
    <section
        id="check"
        class="panel <?= $focus === 'check' ? 'panel--focus' : '' ?>"
        aria-labelledby="check-title"
    >
        <div class="panel__head">
            <div>
                <h2 class="panel__title" id="check-title">Cek kecukupan stok</h2>
                <p class="panel__hint">Hitung kebutuhan bahan dari resep seluruh item order.</p>
            </div>
            <span class="pill pill--neutral">POST</span>
        </div>

        <?php if ($checkError !== null): ?>
            <div style="margin-bottom: 14px"><?php $renderError($checkError); ?></div>
        <?php elseif ($checkResult !== null): ?>
            <?php
            $sufficient = (bool) ($checkResult['sufficient'] ?? false);
            $tone = $sufficient ? 'positive' : 'danger';
            ?>
            <div class="alert alert--<?= e($tone) ?>" style="margin-bottom: 14px">
                <p class="alert__title">
                    <?= $sufficient ? 'Stok mencukupi untuk seluruh kebutuhan' : 'Stok belum mencukupi' ?>
                </p>
                <p class="alert__meta">
                    <?= e($checkResult['order_code'] ?? '-') ?>
                    · <?= e(format_datetime_id($checkResult['checked_at'] ?? null)) ?>
                    · <?= e(count($checkInsufficient)) ?> bahan kurang
                </p>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Bahan</th>
                        <th class="num">Butuh</th>
                        <th class="num">Tersedia</th>
                        <th class="num">Kurang</th>
                        <th class="col-coverage">Cakupan</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($checkItems as $item): ?>
                        <?php
                        $required = (float) ($item['required'] ?? 0);
                        $available = (float) ($item['available'] ?? 0);
                        $shortage = (float) ($item['shortage'] ?? 0);
                        $coverage = $required > 0 ? min(100.0, max(0.0, ($available / $required) * 100)) : 100.0;
                        $rowTone = $shortage > 0 ? 'danger' : 'positive';
                        ?>
                        <tr>
                            <td>
                                <span class="table-title"><?= e($item['ingredient_name'] ?? '-') ?></span>
                                <span class="table-sub"><?= e($item['ingredient_sku'] ?? '-') ?></span>
                            </td>
                            <td class="num"><?= e(format_quantity($required)) ?> <?= e($item['unit'] ?? '') ?></td>
                            <td class="num"><?= e(format_quantity($available)) ?> <?= e($item['unit'] ?? '') ?></td>
                            <td class="num">
                                <?php if ($shortage > 0): ?>
                                    <span class="pill pill--danger"><?= e(format_quantity($shortage)) ?></span>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-coverage" style="min-width: 100px">
                                <div class="meter meter--<?= e($rowTone) ?>">
                                    <span class="meter__fill" style="--pct: <?= e(number_format($coverage, 1, '.', '')) ?>%"></span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty" style="margin-bottom: 14px">
                <span class="empty__mark" aria-hidden="true">01</span>
                <p>Masukkan ID order yang sudah tersimpan untuk melihat kebutuhan bahan dan kekurangannya.</p>
            </div>
        <?php endif; ?>

        <form method="post" action="?page=orders" data-submit-lock>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="check">
            <div class="filter-bar" style="padding: 0; border: 0; background: transparent">
                <label class="field" style="flex: 1">
                    <span>Order ID</span>
                    <input
                        class="input"
                        type="number"
                        name="order_id"
                        min="1"
                        step="1"
                        required
                        value="<?= e($orderIdValue) ?>"
                        placeholder="2"
                        <?= $focus === 'check' && $checkResult === null && $checkError === null ? 'autofocus' : '' ?>
                    >
                </label>
                <button class="btn btn--primary" type="submit">Cek stok</button>
            </div>
        </form>
    </section>

    <section
        id="allocate"
        class="panel <?= $focus === 'allocate' ? 'panel--focus' : '' ?>"
        aria-labelledby="allocate-title"
    >
        <div class="panel__head">
            <div>
                <h2 class="panel__title" id="allocate-title">Alokasi stok</h2>
                <p class="panel__hint">Potong stok dalam satu transaksi dan ubah order menjadi fulfilled.</p>
            </div>
            <span class="pill pill--neutral">POST</span>
        </div>

        <?php if ($allocateError !== null): ?>
            <div style="margin-bottom: 14px"><?php $renderError($allocateError); ?></div>
        <?php elseif ($allocateResult !== null): ?>
            <div class="alert alert--positive" style="margin-bottom: 14px">
                <p class="alert__title">Alokasi berhasil · order <?= e($allocateResult['status'] ?? 'fulfilled') ?></p>
                <p class="alert__meta">
                    <?= e($allocateResult['order_code'] ?? '-') ?>
                    · <?= e(format_datetime_id($allocateResult['allocated_at'] ?? null)) ?>
                </p>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Bahan</th>
                        <th class="num">Dipotong</th>
                        <th class="num">Sisa stok</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($allocateDeductions as $deduction): ?>
                        <tr>
                            <td class="mono"><?= e($deduction['ingredient_sku'] ?? '-') ?></td>
                            <td class="num"><?= e(format_quantity((float) ($deduction['quantity'] ?? 0))) ?> <?= e($deduction['unit'] ?? '') ?></td>
                            <td class="num"><?= e(format_quantity((float) ($deduction['stock_after'] ?? 0))) ?> <?= e($deduction['unit'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php $cacheKeys = is_array($allocateResult['invalidated_cache_keys'] ?? null) ? $allocateResult['invalidated_cache_keys'] : []; ?>
            <?php if ($cacheKeys !== []): ?>
                <p class="panel__hint" style="margin: 12px 0 6px">Cache yang diinvalidasi</p>
                <div class="chips">
                    <?php foreach ($cacheKeys as $cacheKey): ?>
                        <span class="chip"><?= e($cacheKey) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="alert alert--warning" style="margin-bottom: 14px">
                <p class="alert__title">Prasyarat alokasi</p>
                <p class="alert__text">
                    Order harus berstatus <strong>confirmed</strong>, punya minimal satu item, dan seluruh
                    item sudah memiliki QC <strong>pass</strong>. Stok tidak boleh menjadi negatif.
                </p>
            </div>
        <?php endif; ?>

        <form method="post" action="?page=orders" data-submit-lock>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="allocate">
            <div class="form-grid">
                <label class="field">
                    <span>Order ID</span>
                    <input
                        class="input"
                        type="number"
                        name="order_id"
                        min="1"
                        step="1"
                        required
                        value="<?= e($orderIdValue) ?>"
                        placeholder="3"
                        <?= $focus === 'allocate' && $allocateResult === null && $allocateError === null ? 'autofocus' : '' ?>
                    >
                </label>
                <label class="field">
                    <span>Expected version <small style="text-transform:none; letter-spacing:0">(opsional)</small></span>
                    <input class="input" type="number" name="expected_version" min="1" step="1">
                    <span class="field__hint">Untuk optimistic locking.</span>
                </label>
            </div>
            <button class="btn btn--danger btn--block" type="submit" style="margin-top: 14px">
                Alokasikan stok
            </button>
        </form>
    </section>
</div>
