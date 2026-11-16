<?php
/** @var array<string, mixed> $data */
/** @var string $apiBaseUrl */
$checks = is_array($data['checks'] ?? null) ? $data['checks'] : [];
$status = (string)($data['status'] ?? 'down');
$apiBaseUrl = (string)($apiBaseUrl ?? '-');
$statusText = match ($status) { 'ok'=>'Sehat','degraded'=>'Terdegradasi','down'=>'Mati', default=>ucfirst($status) };
$statusDesc = match ($status) {
  'ok' => 'PostgreSQL dan Redis merespons normal. Dashboard membaca data dan memakai cache optimal.',
  'degraded' => 'Sebagian dependency bermasalah. Sistem berjalan dengan fallback (fail-open), cache mungkin tidak optimal.',
  'down' => 'PostgreSQL tidak dapat dihubungi. Endpoint data dapat gagal (503) sampai koneksi pulih.',
  default => 'Status layanan tidak dikenali.'
};
$hero = match($status){ 'ok'=>['bg'=>'bg-emerald-50/60 border-emerald-200','icon'=>'verified_user','badge'=>'bg-primary-fixed text-on-primary-fixed','dot'=>'bg-primary'], 'degraded'=>['bg'=>'bg-amber-50/70 border-amber-200','icon'=>'warning','badge'=>'bg-amber-100 text-amber-800','dot'=>'bg-amber-500'], default=>['bg'=>'bg-error-container/30 border-error/30','icon'=>'error','badge'=>'bg-error-container text-on-error-container','dot'=>'bg-error'] };
$checkMeta = [
  'postgres' => ['label'=>'PostgreSQL 16','hint'=>'Sumber kebenaran: order, stok, resep, QC, forecast','icon'=>'database'],
  'redis' => ['label'=>'Redis 7','hint'=>'Cache fail-open: stok, parse, agregat dashboard','icon'=>'memory'],
];
?>
<div class="flex flex-col gap-6">
  <section class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
    <div><div class="flex items-center gap-2 mb-1"><span class="inline-block w-2 h-2 rounded-full <?= $hero['dot'] ?> animate-pulse"></span><span class="text-[11px] font-semibold uppercase tracking-wider text-outline">Observabilitas &amp; Infrastruktur</span></div><h1 class="font-headline text-[22px] leading-7 font-semibold tracking-tight">Status Sistem &amp; Layanan</h1><p class="text-[13px] text-on-surface-variant mt-1 max-w-2xl">Verifikasi konektivitas AI service FastAPI, PostgreSQL, dan Redis sebelum menyimpulkan data dashboard tidak akurat.</p></div>
    <div class="flex flex-wrap items-center gap-3">
      <label class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg bg-surface-container-lowest border border-outline-variant/30 shadow-xs cursor-pointer select-none"><span class="text-[13px] text-on-surface">Auto-refresh (30s)</span><input type="checkbox" data-auto-refresh="30000" class="rounded border-outline-variant text-primary focus:ring-primary"></label>
      <a href="?page=health" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-surface-container-lowest border border-outline-variant/30 hover:bg-surface-container text-[13px] shadow-xs"><span class="material-symbols-outlined text-[16px] text-primary">refresh</span>Muat ulang</a>
      <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-surface-container-low text-[11px] text-on-surface-variant"><span class="material-symbols-outlined text-[14px]">history</span><span>Terakhir: <strong class="tabular text-on-surface"><?= e(format_datetime_id($data['timestamp'] ?? null)) ?></strong></span></div>
    </div>
  </section>

  <section class="rounded-xl border <?= $hero['bg'] ?> p-5 shadow-xs">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div class="flex items-start md:items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-surface-container-lowest flex items-center justify-center text-primary shadow-xs"><span class="material-symbols-outlined text-[28px]" style="font-variation-settings:'FILL' 1;"><?= e($hero['icon']) ?></span></div>
        <div><div class="flex items-center gap-2 flex-wrap"><span class="font-headline text-[18px] font-semibold text-on-surface">Status Keseluruhan: <?= e($statusText) ?></span><span class="px-2 py-0.5 rounded-full <?= $hero['badge'] ?> text-[11px] font-semibold uppercase"><?= e($status) ?></span></div><p class="text-[13px] text-on-surface-variant mt-0.5"><?= e($statusDesc) ?></p></div>
      </div>
      <div class="flex items-center gap-4 bg-surface-container-low/70 px-4 py-2 rounded-lg"><div class="text-right"><div class="text-[10px] uppercase tracking-wider text-outline">Versi API</div><div class="font-headline text-[16px] font-semibold tabular text-on-surface"><?= e($data['version'] ?? '-') ?></div></div></div>
    </div>
  </section>

  <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <?php foreach ($checkMeta as $key=>$meta): $val=(string)($checks[$key] ?? 'unknown'); $tone=status_tone($val); $pill=['positive'=>'bg-emerald-50 text-emerald-700','warning'=>'bg-amber-100 text-amber-800','danger'=>'bg-error-container text-error','neutral'=>'bg-surface-container text-on-surface'][$tone]??'bg-surface-container'; $dot=['positive'=>'bg-primary','warning'=>'bg-amber-500','danger'=>'bg-error','neutral'=>'bg-outline'][$tone]??'bg-outline'; $vlabel=match($val){'ok'=>'Operasional','degraded'=>'Degradasi','down'=>'Mati',default=>ucfirst($val)}; ?>
      <div class="rounded-xl bg-surface-container-lowest p-5 shadow-xs border border-outline-variant/20 flex flex-col justify-between">
        <div><div class="flex items-center justify-between pb-3 border-b border-outline-variant/20 mb-3"><div class="flex items-center gap-2"><div class="w-8 h-8 rounded-lg bg-surface-container flex items-center justify-center text-primary"><span class="material-symbols-outlined text-[18px]"><?= e($meta['icon']) ?></span></div><div><h3 class="font-headline text-[15px] font-semibold"><?= e($meta['label']) ?></h3><span class="text-[10px] uppercase tracking-wider text-outline">Dependency</span></div></div><span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full <?= $pill ?> text-[11px] font-semibold"><span class="w-1.5 h-1.5 rounded-full <?= $dot ?>"></span><?= e($vlabel) ?></span></div>
        <p class="text-[13px] text-on-surface-variant leading-snug"><?= e($meta['hint']) ?></p></div>
        <div class="mt-4 p-2.5 rounded-lg bg-surface-container-low text-[11px] text-on-surface-variant">Status: <strong class="text-on-surface"><?= e($val) ?></strong></div>
      </div>
    <?php endforeach; ?>
    <div class="rounded-xl bg-surface-container-lowest p-5 shadow-xs border border-outline-variant/20 flex flex-col justify-between">
      <div><div class="flex items-center justify-between pb-3 border-b border-outline-variant/20 mb-3"><div class="flex items-center gap-2"><div class="w-8 h-8 rounded-lg bg-surface-container flex items-center justify-center text-primary"><span class="material-symbols-outlined text-[18px]">neurology</span></div><div><h3 class="font-headline text-[15px] font-semibold">FastAPI AI Service</h3><span class="text-[10px] uppercase tracking-wider text-outline">NLP &amp; Business Logic</span></div></div><span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[11px] font-semibold"><span class="w-1.5 h-1.5 rounded-full bg-primary"></span>Terhubung</span></div>
      <p class="text-[13px] text-on-surface-variant leading-snug">Endpoint internal yang dihubungi dashboard PHP untuk parse, stok, alokasi, forecast, dan agregasi.</p></div>
      <div class="mt-4 p-2.5 rounded-lg bg-surface-container-low text-[11px] text-on-surface-variant break-all font-mono"><?= e($apiBaseUrl) ?></div>
    </div>
  </section>

  <section class="grid grid-cols-1 lg:grid-cols-12 gap-4">
    <div class="lg:col-span-7 rounded-xl bg-surface-container-lowest p-5 shadow-xs border border-outline-variant/20">
      <div class="flex items-center gap-2 pb-3 mb-3 border-b border-outline-variant/20"><span class="material-symbols-outlined text-primary text-[20px]">terminal</span><h2 class="font-headline text-[16px] font-semibold">Spesifikasi Runtime &amp; Kontrak</h2></div>
      <div class="overflow-x-auto"><table class="w-full text-left"><thead><tr class="bg-surface-container-low text-[11px] uppercase tracking-wider text-outline"><th class="py-2.5 px-3 font-medium">Komponen</th><th class="py-2.5 px-3 font-medium">Konfigurasi</th><th class="py-2.5 px-3 font-medium text-right">Status</th></tr></thead><tbody class="text-[13px]">
        <tr class="hover:bg-surface-container-low/50"><td class="py-3 px-3"><div class="font-medium">Dashboard</div><div class="text-[11px] text-on-surface-variant">Presentasi &amp; interaksi</div></td><td class="py-3 px-3 font-mono text-[12px]">PHP <?= e(PHP_VERSION) ?> · Templates server-rendered</td><td class="py-3 px-3 text-right"><span class="text-primary text-[12px] font-medium">Aktif</span></td></tr>
        <tr class="hover:bg-surface-container-low/50"><td class="py-3 px-3"><div class="font-medium">AI Service</div><div class="text-[11px] text-on-surface-variant">FastAPI /v1</div></td><td class="py-3 px-3 font-mono text-[12px]">Python 3.11 · Uvicorn · asyncpg · redis-py</td><td class="py-3 px-3 text-right"><span class="text-primary text-[12px] font-medium">Terhubung</span></td></tr>
        <tr class="hover:bg-surface-container-low/50"><td class="py-3 px-3"><div class="font-medium">Auth Internal</div><div class="text-[11px] text-on-surface-variant">Service-to-service</div></td><td class="py-3 px-3 font-mono text-[12px]">X-Internal-Token (opsional via env)</td><td class="py-3 px-3 text-right"><span class="text-primary text-[12px] font-medium">Sesuai config</span></td></tr>
        <tr class="hover:bg-surface-container-low/50"><td class="py-3 px-3"><div class="font-medium">Kontrak API</div><div class="text-[11px] text-on-surface-variant">Envelope &amp; error</div></td><td class="py-3 px-3 font-mono text-[12px]">Prefix /v1 · { error: { code,message,details[] } }</td><td class="py-3 px-3 text-right"><span class="text-primary text-[12px] font-medium">Standar</span></td></tr>
      </tbody></table></div>
    </div>
    <div class="lg:col-span-5 rounded-xl bg-surface-container-lowest p-5 shadow-xs border border-outline-variant/20">
      <div class="flex items-center gap-2 pb-3 mb-3 border-b border-outline-variant/20"><span class="material-symbols-outlined text-primary text-[20px]">help_center</span><h2 class="font-headline text-[16px] font-semibold">Panduan Membaca Status</h2></div>
      <div class="space-y-3">
        <div class="p-3 rounded-lg bg-surface-container-low flex gap-3"><span class="w-3 h-3 mt-1 rounded-full bg-primary shrink-0"></span><div><div class="text-[13px] font-semibold">Hijau · Sehat</div><p class="text-[12px] text-on-surface-variant">Semua dependency merespons. Parse, cek/alokasi stok, dan dashboard berjalan normal.</p></div></div>
        <div class="p-3 rounded-lg bg-surface-container-low flex gap-3"><span class="w-3 h-3 mt-1 rounded-full bg-amber-500 shrink-0"></span><div><div class="text-[13px] font-semibold">Kuning · Degradasi</div><p class="text-[12px] text-on-surface-variant">Redis/cache bermasalah; sistem <em>fail-open</em> ke PostgreSQL. Transaksi aman, respons bisa lebih lambat.</p></div></div>
        <div class="p-3 rounded-lg bg-surface-container-low flex gap-3"><span class="w-3 h-3 mt-1 rounded-full bg-error shrink-0"></span><div><div class="text-[13px] font-semibold">Merah · Kritis</div><p class="text-[12px] text-on-surface-variant">PostgreSQL terputus; baca/tulis data dapat gagal (503). Hentikan input transaksi sampai pulih.</p></div></div>
      </div>
    </div>
  </section>
</div>
