<?php
/** @var int $status */
/** @var string $message */
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Error</p>
        <h1>Permintaan gagal (<?= e($status) ?>)</h1>
    </div>
</section>

<section class="panel error">
    <p><?= e($message) ?></p>
    <p><a href="?page=dashboard">Kembali ke dashboard</a></p>
</section>