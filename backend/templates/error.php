<?php
/** @var int $status */
/** @var string $code */
/** @var string $message */
/** @var list<array<string,mixed>> $details */
$code = $code ?? 'ERROR';
$details = is_array($details ?? null) ? $details : [];
?>
<div class="max-w-2xl">
  <div class="mb-6"><span class="text-[11px] font-semibold uppercase tracking-wider text-error">Kendala Sistem</span><h1 class="font-headline text-[24px] font-semibold mt-1">Permintaan tidak dapat diselesaikan</h1><p class="text-[13px] text-on-surface-variant mt-1">Dashboard tidak menerima respons yang bisa diproses dari AI service. Periksa status layanan lalu coba lagi.</p></div>
  <section class="rounded-xl bg-surface-container-lowest p-6 shadow-xs border border-outline-variant/20 space-y-4">
    <div class="flex items-start gap-4"><div class="font-headline text-[48px] leading-none font-bold text-error tabular"><?= e($status) ?></div><div><div class="font-mono text-[12px] text-on-surface-variant mb-1"><?= e($code) ?></div><div class="text-[16px] font-semibold text-on-surface"><?= e($message) ?></div></div></div>
    <?php if ($details !== []): ?>
      <div class="rounded-lg bg-amber-50 border border-amber-200 p-3"><div class="text-[13px] font-semibold text-amber-900 mb-1">Detail dari layanan</div><ul class="list-disc pl-5 text-[12px] text-amber-900/90 space-y-0.5"><?php foreach($details as $d): ?><li><?php if(is_array($d)): ?><span class="font-mono"><?= e($d['field'] ?? '-') ?></span> — <?= e($d['issue'] ?? $d['message'] ?? '') ?><?php else: ?><?= e($d) ?><?php endif; ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <div class="flex flex-wrap items-center gap-2 pt-1">
      <a href="?page=dashboard" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg bg-primary text-on-primary text-[13px] font-medium">Kembali ke dashboard</a>
      <a href="?page=health" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg bg-surface-container hover:bg-surface-container-high text-[13px] font-medium">Cek status sistem</a>
      <button type="button" onclick="location.reload()" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg text-on-surface-variant hover:bg-surface-container text-[13px]">Muat ulang</button>
    </div>
  </section>
</div>
