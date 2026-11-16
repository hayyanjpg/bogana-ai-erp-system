<?php
/** @var int $status */
/** @var string $code */
/** @var string $message */
/** @var list<array<string, mixed>> $details */

$code = $code ?? 'ERROR';
$details = $details ?? [];
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Kendala sistem</p>
        <h1>Permintaan tidak dapat diselesaikan</h1>
        <p class="lede">Dashboard tidak menerima respons yang bisa diproses dari AI service. Periksa status layanan lalu coba lagi.</p>
    </div>
</section>

<section class="error-page">
    <span class="error-code"><?= e($status) ?></span>
    <div>
        <p class="panel__meta" style="margin: 0 0 6px"><?= e($code) ?></p>
        <h1><?= e($message) ?></h1>
    </div>

    <?php if ($details !== []): ?>
        <div class="alert alert--warning" style="width: 100%">
            <p class="alert__title">Detail dari layanan</p>
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
        </div>
    <?php endif; ?>

    <div class="page-head__actions">
        <a class="btn btn--primary" href="?page=dashboard">Kembali ke dashboard</a>
        <a class="btn btn--secondary" href="?page=health">Cek status sistem</a>
        <button class="btn btn--ghost" type="button" onclick="window.location.reload()">Muat ulang</button>
    </div>
</section>
