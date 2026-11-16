<?php
/** @var array<string, mixed> $data */

$checks = is_array($data['checks'] ?? null) ? $data['checks'] : [];
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Monitoring</p>
        <h1>Status Sistem</h1>
    </div>
    <a href="?page=health">Muat ulang</a>
</section>

<section class="cards">
    <article class="card">
        <p>Status</p>
        <strong>
            <span class="badge <?= e($data['status'] ?? '') ?>"><?= e($data['status'] ?? '-') ?></span>
        </strong>
    </article>
    <article class="card">
        <p>PostgreSQL</p>
        <strong>
            <span class="badge <?= e($checks['postgres'] ?? '') ?>"><?= e($checks['postgres'] ?? '-') ?></span>
        </strong>
    </article>
    <article class="card">
        <p>Redis</p>
        <strong>
            <span class="badge <?= e($checks['redis'] ?? '') ?>"><?= e($checks['redis'] ?? '-') ?></span>
        </strong>
    </article>
    <article class="card">
        <p>Versi API</p>
        <strong><?= e($data['version'] ?? '-') ?></strong>
    </article>
</section>

<section class="panel">
    <p class="muted">
        Waktu pemeriksaan: <?= e($data['timestamp'] ?? '-') ?>
    </p>
</section>