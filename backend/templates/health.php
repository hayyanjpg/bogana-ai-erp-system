<?php
/** @var array<string, mixed> $data */
/** @var string $apiBaseUrl */

$checks = is_array($data['checks'] ?? null) ? $data['checks'] : [];
$status = (string) ($data['status'] ?? 'down');
$apiBaseUrl = (string) ($apiBaseUrl ?? '-');

$statusClass = match ($status) {
    'degraded' => 'health-status--degraded',
    'down' => 'health-status--down',
    default => '',
};
$statusText = match ($status) {
    'ok' => 'Sehat',
    'degraded' => 'Terdegradasi',
    'down' => 'Mati',
    default => ucfirst($status),
};
$statusDescription = match ($status) {
    'ok' => 'PostgreSQL dan Redis merespons normal. Dashboard dapat membaca data dan memakai cache.',
    'degraded' => 'Salah satu dependency bermasalah. Sistem tetap berjalan dengan fallback ke PostgreSQL, tetapi cache tidak optimal.',
    'down' => 'PostgreSQL tidak dapat dihubungi. Endpoint data akan gagal sampai koneksi pulih.',
    default => 'Status layanan tidak dikenali.',
};

$checkMeta = [
    'postgres' => [
        'label' => 'PostgreSQL',
        'hint' => 'Sumber data utama: order, stok, resep, QC, dan forecast.',
        'icon' => 'DB',
    ],
    'redis' => [
        'label' => 'Redis',
        'hint' => 'Cache stok, hasil parse, dan agregat dashboard. Bersifat fail-open.',
        'icon' => 'RD',
    ],
];
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Monitoring</p>
        <h1>Status sistem</h1>
        <p class="lede">
            Pemeriksaan koneksi langsung ke AI service, PostgreSQL, dan Redis. Gunakan halaman ini
            sebelum menyimpulkan data dashboard tidak akurat.
        </p>
    </div>
    <div class="page-head__actions">
        <label class="switch" title="Muat ulang halaman setiap 30 detik">
            <input type="checkbox" data-auto-refresh="30000">
            <span class="switch__track" aria-hidden="true"></span>
            <span>Auto-refresh</span>
        </label>
        <a class="btn btn--secondary" href="?page=health">Muat ulang</a>
    </div>
</section>

<section class="health-hero">
    <div class="health-status <?= e($statusClass) ?>">
        <div>
            <p class="health-status__label">Status keseluruhan</p>
            <p class="health-status__value">
                <span class="dot" aria-hidden="true"></span>
                <?= e($statusText) ?>
            </p>
        </div>
        <p><?= e($statusDescription) ?></p>
        <p class="panel__meta">
            Diperiksa <?= e(format_datetime_id($data['timestamp'] ?? null)) ?>
        </p>
    </div>

    <section class="panel" aria-labelledby="checks-title">
        <div class="panel__head">
            <div>
                <h2 class="panel__title" id="checks-title">Pemeriksaan dependency</h2>
                <p class="panel__hint">Nilai berasal dari endpoint <span class="code">/health</span>.</p>
            </div>
            <span class="pill pill--<?= e(status_tone($status)) ?>"><?= e($statusText) ?></span>
        </div>

        <?php if ($checks === []): ?>
            <div class="empty">
                <span class="empty__mark" aria-hidden="true">?</span>
                <p>Tidak ada detail pemeriksaan pada respons health.</p>
            </div>
        <?php else: ?>
            <div class="check-list">
                <?php foreach ($checks as $key => $value): ?>
                    <?php
                    $value = (string) $value;
                    $meta = $checkMeta[$key] ?? [
                        'label' => ucfirst((string) $key),
                        'hint' => 'Pemeriksaan tambahan dari AI service.',
                        'icon' => strtoupper(substr((string) $key, 0, 2)),
                    ];
                    $valueLabel = match ($value) {
                        'ok' => 'Sehat',
                        'degraded' => 'Degradasi',
                        'down' => 'Mati',
                        default => status_label($value),
                    };
                    ?>
                    <div class="check">
                        <span class="check__icon" aria-hidden="true"><?= e($meta['icon']) ?></span>
                        <div class="check__body">
                            <p><?= e($meta['label']) ?></p>
                            <small><?= e($meta['hint']) ?></small>
                        </div>
                        <span class="pill pill--<?= e(status_tone($value)) ?>"><?= e($valueLabel) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>

<div class="grid grid--2">
    <section class="panel" aria-labelledby="runtime-title">
        <div class="panel__head">
            <div>
                <h2 class="panel__title" id="runtime-title">Runtime</h2>
                <p class="panel__hint">Identitas layanan yang sedang dihubungi dashboard.</p>
            </div>
        </div>
        <dl class="kv">
            <div>
                <dt>Versi API</dt>
                <dd><?= e($data['version'] ?? '-') ?></dd>
            </div>
            <div>
                <dt>Endpoint internal</dt>
                <dd class="mono" style="word-break: break-all; font-size: .74rem"><?= e($apiBaseUrl) ?></dd>
            </div>
            <div>
                <dt>Timestamp</dt>
                <dd><?= e(format_datetime_id($data['timestamp'] ?? null)) ?></dd>
            </div>
            <div>
                <dt>Dashboard</dt>
                <dd>PHP <?= e(PHP_VERSION) ?></dd>
            </div>
        </dl>
    </section>

    <section class="panel" aria-labelledby="legend-title">
        <div class="panel__head">
            <div>
                <h2 class="panel__title" id="legend-title">Cara membaca status</h2>
                <p class="panel__hint">Arti nilai pada pemeriksaan dependency.</p>
            </div>
        </div>
        <div class="stack">
            <div class="list-row" style="grid-template-columns: auto minmax(0, 1fr)">
                <span class="pill pill--positive">Sehat</span>
                <div class="list-row__body">
                    <p class="list-row__title">Semua dependency merespons</p>
                    <p class="list-row__meta">Data dibaca normal dan cache tersedia.</p>
                </div>
            </div>
            <div class="list-row" style="grid-template-columns: auto minmax(0, 1fr)">
                <span class="pill pill--warning">Degradasi</span>
                <div class="list-row__body">
                    <p class="list-row__title">Sebagian fitur memakai fallback</p>
                    <p class="list-row__meta">Redis bermasalah; data tetap dari PostgreSQL tanpa cache.</p>
                </div>
            </div>
            <div class="list-row" style="grid-template-columns: auto minmax(0, 1fr)">
                <span class="pill pill--danger">Mati</span>
                <div class="list-row__body">
                    <p class="list-row__title">Dependency utama tidak tersedia</p>
                    <p class="list-row__meta">PostgreSQL gagal; endpoint data mengembalikan 503.</p>
                </div>
            </div>
        </div>
    </section>
</div>
