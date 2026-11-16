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
$channels = ['whatsapp' => 'WhatsApp', 'email' => 'Email', 'phone' => 'Telepon', 'manual' => 'Manual'];
$samples = [
  ['label' => 'Order nasi', 'value' => 'gan besok pagi 40 nasi bogana, 15 nasi langgi untuk syukuran'],
  ['label' => 'Order box', 'value' => '20 nasi box paket a, 12 nasi box paket b, kirim jam 6'],
  ['label' => 'Contoh warning', 'value' => '10 pizza hawaiian, 8 nasi gudeg'],
];
$parseItems = $parseResult !== null && is_array($parseResult['items'] ?? null) ? $parseResult['items'] : [];
$parseWarnings = $parseResult !== null && is_array($parseResult['warnings'] ?? null) ? $parseResult['warnings'] : [];
$parseTotal = 0.0; $parseQty = 0.0;
foreach ($parseItems as $it) { $q = (float)($it['quantity']??0); $parseQty += $q; $parseTotal += $q * (float)($it['unit_price']??0); }
$confidence = (float)($parseResult['confidence'] ?? 0);
$confidencePercent = max(0.0, min(100.0, $confidence * 100));
$confidenceTone = confidence_tone($confidence);
$checkItems = $checkResult !== null && is_array($checkResult['requirements'] ?? null) ? $checkResult['requirements'] : [];
$checkInsufficient = $checkResult !== null && is_array($checkResult['insufficient'] ?? null) ? $checkResult['insufficient'] : [];
$allocateDeductions = $allocateResult !== null && is_array($allocateResult['deductions'] ?? null) ? $allocateResult['deductions'] : [];
$renderError = static function (array $error): void {
  $status = (int)($error['status'] ?? 0);
  $details = is_array($error['details'] ?? null) ? $error['details'] : [];
?>
  <div class="rounded-lg border border-error/30 bg-error-container/40 p-3 text-on-error-container">
    <p class="text-[13px] font-semibold"><?= e($error['message'] ?? 'Permintaan gagal') ?></p>
    <p class="text-[11px] font-mono mt-0.5 opacity-90"><?= e($error['code'] ?? 'ERROR') ?><?= $status>0 ? ' · HTTP '.e($status):'' ?></p>
    <?php if ($details !== []): ?><ul class="mt-2 text-[12px] list-disc pl-5 space-y-0.5"><?php foreach ($details as $d): ?><li><?php if(is_array($d)): ?><span class="font-mono"><?= e($d['field'] ?? '-') ?></span> — <?= e($d['issue'] ?? $d['message'] ?? '') ?><?php else: ?><?= e($d) ?><?php endif; ?></li><?php endforeach; ?></ul><?php endif; ?>
  </div>
<?php };
$toneBg = ['positive'=>'bg-emerald-50 text-emerald-700','warning'=>'bg-amber-100 text-amber-800','danger'=>'bg-error-container text-error','neutral'=>'bg-surface-container text-on-surface'];
$meterBg = ['positive'=>'bg-primary','warning'=>'bg-amber-500','danger'=>'bg-error'];
?>
<div class="flex flex-col gap-6">
  <section class="bg-surface-container-lowest rounded-xl p-5 shadow-xs border border-outline-variant/20 flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div>
      <div class="flex items-center gap-2 mb-1"><span class="px-2 py-0.5 rounded-full bg-primary/10 text-primary text-[11px] uppercase tracking-wider font-semibold">Workbench Pemrosesan Pesanan</span><span class="inline-flex items-center gap-1 text-on-surface-variant text-[11px]"><span class="h-1.5 w-1.5 rounded-full bg-primary animate-pulse"></span>NLP Parser Aktif</span></div>
      <h1 class="font-headline text-[22px] leading-7 font-semibold tracking-tight text-on-surface">Order &amp; Parser Ekstraksi</h1>
      <p class="text-[13px] text-on-surface-variant mt-0.5 max-w-3xl">Urai teks pesanan bebas, validasi kebutuhan bahan (BOM), dan alokasi stok transaksional untuk order tersimpan.</p>
    </div>
    <div class="flex items-center gap-1.5 bg-surface-container-low p-1.5 rounded-lg shrink-0 flex-wrap">
      <a href="#parse" class="px-3 py-1.5 rounded-lg bg-surface-container-lowest text-primary text-[13px] font-medium shadow-sm flex items-center gap-1.5"><span class="material-symbols-outlined text-[16px]">psychology</span>1. Urai Teks</a>
      <a href="#check" class="px-3 py-1.5 rounded-lg text-on-surface-variant hover:text-on-surface text-[13px] flex items-center gap-1.5"><span class="material-symbols-outlined text-[16px]">inventory</span>2. Cek Stok</a>
      <a href="#allocate" class="px-3 py-1.5 rounded-lg text-on-surface-variant hover:text-on-surface text-[13px] flex items-center gap-1.5"><span class="material-symbols-outlined text-[16px]">lock_clock</span>3. Alokasi</a>
    </div>
  </section>

  <section id="parse" class="grid grid-cols-1 xl:grid-cols-12 gap-4 items-start scroll-mt-20">
    <form method="post" action="?page=orders" class="xl:col-span-5 bg-surface-container-lowest rounded-xl p-5 shadow-xs border border-outline-variant/20 space-y-4" data-submit-lock>
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="parse">
      <div class="flex items-center justify-between pb-1"><div class="flex items-center gap-2"><div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center text-primary"><span class="material-symbols-outlined text-[18px]">edit_note</span></div><div><h2 class="font-headline text-[15px] font-semibold">Input Teks Pesanan</h2><p class="text-[12px] text-on-surface-variant">Tempel teks dari WhatsApp/Email/Telepon</p></div></div><span class="px-2 py-0.5 rounded-full bg-secondary-container text-on-secondary-fixed-variant text-[11px] font-medium">NLP Fuzzy</span></div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <label class="flex flex-col gap-1"><span class="text-[12px] font-medium text-on-surface">Nama Customer <span class="text-error">*</span></span><input class="h-9 px-3 rounded-lg bg-surface-container-low/60 border border-outline-variant/40 text-[13px] focus:bg-white focus:outline-none focus:ring-1 focus:ring-primary" type="text" name="customer_name" minlength="2" maxlength="120" required value="<?= e($form['customer_name'] ?? '') ?>" placeholder="PT / Nama klien"></label>
        <label class="flex flex-col gap-1"><span class="text-[12px] font-medium text-on-surface">Channel</span><select class="h-9 px-3 rounded-lg bg-surface-container-low/60 border border-outline-variant/40 text-[13px] focus:bg-white focus:outline-none focus:ring-1 focus:ring-primary" name="channel"><?php foreach($channels as $v=>$l): ?><option value="<?= e($v) ?>" <?= ($form['channel']??'whatsapp')===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
        <label class="flex flex-col gap-1"><span class="text-[12px] font-medium text-on-surface">Tanggal Order</span><input class="h-9 px-3 rounded-lg bg-surface-container-low/60 border border-outline-variant/40 text-[13px] focus:bg-white focus:outline-none focus:ring-1 focus:ring-primary" type="date" name="order_date" value="<?= e($form['order_date'] ?? '') ?>"><span class="text-[11px] text-on-surface-variant">Kosongkan untuk default API (besok).</span></label>
        <label class="flex flex-col gap-1"><span class="text-[12px] font-medium text-on-surface">Catatan <span class="text-on-surface-variant font-normal">(opsional)</span></span><input class="h-9 px-3 rounded-lg bg-surface-container-low/60 border border-outline-variant/40 text-[13px] focus:bg-white focus:outline-none focus:ring-1 focus:ring-primary" type="text" name="notes" maxlength="500" value="<?= e($form['notes'] ?? '') ?>" placeholder="Instruksi kirim/PIC"></label>
      </div>
      <div class="space-y-1.5"><div class="flex items-center justify-between"><span class="text-[12px] text-on-surface-variant">Contoh cepat:</span></div><div class="flex flex-wrap gap-1.5"><?php foreach($samples as $s): ?><button type="button" class="px-2.5 py-1 rounded bg-surface-container text-on-surface hover:bg-surface-container-high text-[12px]" data-fill-target="raw_text" data-fill-value="<?= e($s['value']) ?>"><?= e($s['label']) ?></button><?php endforeach; ?></div></div>
      <label class="flex flex-col gap-1.5"><div class="flex items-center justify-between"><span class="text-[12px] font-medium text-on-surface">Teks Mentah Pesanan <span class="text-error">*</span></span><span class="text-[11px] text-on-surface-variant tabular" data-count-for="raw_text">0 / 500</span></div><textarea id="raw_text" name="raw_text" rows="6" minlength="3" maxlength="500" required placeholder="Contoh: gan besok pagi 40 nasi bogana, 15 nasi langgi untuk syukuran" class="w-full p-3 rounded-lg bg-surface-container-low text-on-surface font-mono text-[13px] leading-relaxed border border-outline-variant/30 focus:bg-white focus:outline-none focus:ring-1 focus:ring-primary resize-y"><?= e($form['raw_text'] ?? '') ?></textarea></label>
      <div class="flex items-center justify-between gap-3 pt-1"><a href="?page=orders" class="px-3 py-2 rounded-lg bg-surface-container text-on-surface hover:bg-surface-container-high text-[13px] font-medium">Reset</a><button type="submit" class="px-5 py-2 rounded-lg bg-primary hover:bg-tertiary-container text-on-primary text-[13px] font-medium shadow-sm flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">bolt</span>Urai Pesanan</button></div>
    </form>

    <div class="xl:col-span-7 space-y-4">
      <div class="bg-surface-container-lowest rounded-xl p-5 shadow-xs border border-outline-variant/20 space-y-4 min-h-[200px]">
        <div class="flex items-center justify-between pb-1"><div><h2 class="font-headline text-[15px] font-semibold">Hasil Parse</h2><p class="text-[12px] text-on-surface-variant">Item terstruktur, keyakinan, dan peringatan</p></div><?php if($parseResult!==null): ?><span class="px-2.5 py-1 rounded-full <?= $toneBg[$confidenceTone] ?? $toneBg['neutral'] ?> text-[11px] font-semibold"><?= e(confidence_label($confidence)) ?> <?= e(format_number($confidencePercent,1)) ?>%</span><?php endif; ?></div>
        <?php if ($parseError !== null): ?>
          <?php $renderError($parseError); ?>
        <?php elseif ($parseResult !== null): ?>
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div><div class="text-[13px] font-semibold text-on-surface"><?= e(format_date_id((string)($parseResult['order_date']??''), true)) ?></div><div class="flex items-center gap-2 mt-1"><span class="text-[11px] text-on-surface-variant">Parse ID</span><span class="font-mono text-[11px] px-2 py-0.5 rounded bg-surface-container-low border border-outline-variant/30"><?= e($parseResult['parse_id'] ?? '-') ?></span><button type="button" class="text-primary text-[11px] font-medium hover:underline" data-copy="<?= e($parseResult['parse_id'] ?? '') ?>">Salin</button></div></div>
            <div class="min-w-[160px]"><div class="flex justify-between text-[11px] text-on-surface-variant mb-1"><span>Keyakinan parser</span><strong class="text-on-surface"><?= e(format_number($confidencePercent,1)) ?>%</strong></div><div class="w-full bg-surface-container-high h-2 rounded-full overflow-hidden"><div class="<?= $meterBg[$confidenceTone]??'bg-primary' ?> h-full rounded-full" style="width: <?= e(number_format($confidencePercent,1,'.','')) ?>%"></div></div></div>
          </div>
          <?php if ($parseItems === []): ?>
            <div class="rounded-lg bg-amber-50 border border-amber-200 p-3 text-amber-900"><p class="text-[13px] font-semibold">Tidak ada item yang berhasil dikenali</p><p class="text-[12px] mt-0.5">Periksa ejaan nama menu/kuantitas lalu urai ulang.</p></div>
          <?php else: ?>
            <div class="overflow-x-auto rounded-lg border border-outline-variant/20"><table class="w-full text-left"><thead><tr class="bg-surface-container-low text-on-surface-variant text-[11px] uppercase tracking-wider h-9"><th class="px-3 py-2 font-medium">Baris Teks</th><th class="px-3 py-2 font-medium">Menu &amp; SKU</th><th class="px-3 py-2 font-medium text-center">Qty</th><th class="px-3 py-2 font-medium text-right">Harga</th><th class="px-3 py-2 font-medium text-right">Subtotal</th></tr></thead><tbody class="text-[13px] divide-y divide-surface-container-low">
              <?php foreach($parseItems as $item): $q=(float)($item['quantity']??0); $up=(float)($item['unit_price']??0); ?>
                <tr class="hover:bg-surface-container-low/50"><td class="px-3 py-2.5 font-mono text-[11px] text-on-surface-variant max-w-[150px] truncate" title="<?= e($item['line_text']??'') ?>">"<?= e($item['line_text']??'-') ?>"</td><td class="px-3 py-2.5"><div class="font-medium"><?= e($item['menu_name']??'-') ?></div><div class="text-[11px] text-on-surface-variant font-mono"><?= e($item['menu_sku']??'-') ?></div></td><td class="px-3 py-2.5 text-center tabular font-semibold"><?= e(format_quantity($q)) ?></td><td class="px-3 py-2.5 text-right tabular text-on-surface-variant">Rp <?= e(format_number($up)) ?></td><td class="px-3 py-2.5 text-right tabular font-semibold">Rp <?= e(format_number($q*$up)) ?></td></tr>
              <?php endforeach; ?></tbody></table></div>
            <div class="p-3 rounded-lg bg-surface-container-low flex flex-col sm:flex-row sm:items-center justify-between gap-2"><div class="flex items-center gap-2 text-[13px]"><span class="material-symbols-outlined text-[18px] text-primary">shopping_bag</span><span class="font-medium">Total <?= e(count($parseItems)) ?> item · <?= e(format_quantity($parseQty)) ?> porsi</span></div><div class="text-[15px] font-semibold text-primary tabular">Estimasi: Rp <?= e(format_number($parseTotal)) ?></div></div>
          <?php endif; ?>
          <?php if ($parseWarnings !== []): ?>
            <div class="space-y-2"><div class="text-[12px] font-semibold text-amber-900 flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">priority_high</span>Peringatan Parser</div><?php foreach($parseWarnings as $w): ?><div class="p-3 rounded-lg bg-amber-50/70 border border-amber-200/80 flex items-start gap-3"><span class="px-1.5 py-0.5 rounded bg-amber-200 text-amber-900 font-mono text-[10px] font-semibold shrink-0"><?= e($w['code']??'WARNING') ?></span><div class="text-[12px] text-amber-900/90"><div><?= e($w['message']??'Perlu review admin') ?></div><?php if(!empty($w['line_text'])): ?><div class="mt-0.5 font-mono text-[11px]">"<?= e($w['line_text']) ?>"</div><?php endif; ?></div></div><?php endforeach; ?></div>
          <?php endif; ?>
          <div class="rounded-lg bg-primary/5 border border-primary/20 p-3 text-[12px] text-on-surface"><strong>Catatan:</strong> Hasil parse belum menjadi order tersimpan. Untuk proses stok/alokasi gunakan order yang sudah tersimpan (input Order ID pada panel di bawah).</div>
        <?php else: ?>
          <div class="rounded-lg bg-surface-container-low p-8 text-center"><span class="material-symbols-outlined text-[32px] text-outline mb-2">text_snippet</span><p class="text-[13px] text-on-surface-variant">Tempel teks order lalu tekan <strong>Urai Pesanan</strong>. Parser mendeteksi menu, kuantitas, harga, dan warning (menu tidak dikenal, qty hilang/ambigu, duplikat, low confidence).</p></div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <div class="flex items-center gap-3 text-[11px] text-on-surface-variant uppercase tracking-wider font-medium"><span>Kontrol Order Tersimpan</span><div class="h-px flex-1 bg-outline-variant/30"></div></div>

  <section class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div id="check" class="bg-surface-container-lowest rounded-xl p-5 shadow-xs border <?= $focus==='check'?'border-primary ring-2 ring-primary/20':'border-outline-variant/20' ?> scroll-mt-20">
      <div class="flex items-center justify-between mb-3"><div><h2 class="font-headline text-[15px] font-semibold">Cek Kecukupan Stok</h2><p class="text-[12px] text-on-surface-variant">Hitung kebutuhan bahan (resep × qty) vs stok tersedia</p></div><span class="text-[11px] px-2 py-0.5 rounded bg-surface-container text-on-surface-variant font-mono">POST /check-stock</span></div>
      <?php if ($checkError !== null): ?><div class="mb-3"><?php $renderError($checkError); ?></div><?php endif; ?>
      <?php if ($checkResult !== null): ?>
        <?php $sufficient = (bool)($checkResult['sufficient'] ?? false); ?>
        <div class="mb-3 p-3 rounded-lg <?= $sufficient?'bg-emerald-50 border border-emerald-200 text-emerald-900':'bg-error-container/40 border border-error/30 text-on-error-container' ?>"><p class="text-[13px] font-semibold"><?= $sufficient?'Stok mencukupi untuk seluruh kebutuhan':'Stok belum mencukupi' ?></p><p class="text-[11px] mt-0.5 font-mono"><?= e($checkResult['order_code']??'-') ?> · <?= e(format_datetime_id($checkResult['checked_at']??null)) ?> · <?= e(count($checkInsufficient)) ?> bahan kurang</p></div>
        <div class="overflow-x-auto rounded-lg border border-outline-variant/20 mb-3"><table class="w-full text-left"><thead><tr class="bg-surface-container-low text-on-surface-variant text-[11px] uppercase tracking-wider"><th class="px-3 py-2">Bahan</th><th class="px-3 py-2 text-right">Butuh</th><th class="px-3 py-2 text-right">Tersedia</th><th class="px-3 py-2 text-right">Kurang</th></tr></thead><tbody class="text-[13px] divide-y divide-surface-container-low"><?php foreach($checkItems as $it): $req=(float)($it['required']??0); $av=(float)($it['available']??0); $sh=(float)($it['shortage']??0); $cov=$req>0?min(100,max(0,($av/$req)*100)):100; ?><tr><td class="px-3 py-2.5"><div class="font-medium"><?= e($it['ingredient_name']??'-') ?></div><div class="text-[11px] font-mono text-on-surface-variant"><?= e($it['ingredient_sku']??'-') ?></div><div class="mt-1 w-full max-w-[140px] bg-surface-container-high h-1.5 rounded-full overflow-hidden"><div class="<?= $sh>0?'bg-error':'bg-primary' ?> h-full rounded-full" style="width: <?= e(number_format($cov,1,'.','')) ?>%"></div></div></td><td class="px-3 py-2.5 text-right tabular"><?= e(format_quantity($req)) ?> <?= e($it['unit']??'') ?></td><td class="px-3 py-2.5 text-right tabular"><?= e(format_quantity($av)) ?> <?= e($it['unit']??'') ?></td><td class="px-3 py-2.5 text-right tabular"><?php if($sh>0): ?><span class="px-2 py-0.5 rounded-full bg-error-container text-error text-[11px] font-semibold"><?= e(format_quantity($sh)) ?></span><?php else: ?><span class="text-on-surface-variant">—</span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
      <?php elseif ($checkError === null): ?>
        <div class="mb-3 rounded-lg bg-surface-container-low p-5 text-center text-[13px] text-on-surface-variant">Masukkan Order ID untuk melihat kebutuhan bahan dan kekurangannya.</div>
      <?php endif; ?>
      <form method="post" action="?page=orders" data-submit-lock class="flex items-end gap-2">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="check">
        <label class="flex-1 flex flex-col gap-1"><span class="text-[12px] font-medium text-on-surface">Order ID</span><input class="h-9 px-3 rounded-lg bg-surface-container-low/60 border border-outline-variant/40 text-[13px] focus:bg-white focus:outline-none focus:ring-1 focus:ring-primary" type="number" name="order_id" min="1" step="1" required value="<?= e($orderIdValue) ?>" placeholder="contoh: 2" <?= $focus==='check'&&$checkResult===null&&$checkError===null?'autofocus':'' ?>></label>
        <button class="h-9 px-4 rounded-lg bg-primary hover:bg-tertiary-container text-on-primary text-[13px] font-medium" type="submit">Cek Stok</button>
      </form>
    </div>

    <div id="allocate" class="bg-surface-container-lowest rounded-xl p-5 shadow-xs border <?= $focus==='allocate'?'border-primary ring-2 ring-primary/20':'border-outline-variant/20' ?> scroll-mt-20">
      <div class="flex items-center justify-between mb-3"><div><h2 class="font-headline text-[15px] font-semibold">Alokasi Stok</h2><p class="text-[12px] text-on-surface-variant">Potong stok atomik & selesaikan order (confirmed → fulfilled)</p></div><span class="text-[11px] px-2 py-0.5 rounded bg-surface-container text-on-surface-variant font-mono">POST /allocate</span></div>
      <?php if ($allocateError !== null): ?><div class="mb-3"><?php $renderError($allocateError); ?></div><?php endif; ?>
      <?php if ($allocateResult !== null): ?>
        <div class="mb-3 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-900"><p class="text-[13px] font-semibold">Alokasi berhasil · status <?= e($allocateResult['status'] ?? 'fulfilled') ?></p><p class="text-[11px] mt-0.5 font-mono"><?= e($allocateResult['order_code']??'-') ?> · <?= e(format_datetime_id($allocateResult['allocated_at']??null)) ?></p></div>
        <div class="overflow-x-auto rounded-lg border border-outline-variant/20 mb-3"><table class="w-full text-left"><thead><tr class="bg-surface-container-low text-on-surface-variant text-[11px] uppercase tracking-wider"><th class="px-3 py-2">Bahan (SKU)</th><th class="px-3 py-2 text-right">Dipotong</th><th class="px-3 py-2 text-right">Sisa Stok</th></tr></thead><tbody class="text-[13px] divide-y divide-surface-container-low"><?php foreach($allocateDeductions as $d): ?><tr><td class="px-3 py-2.5 font-mono text-[12px]"><?= e($d['ingredient_sku']??'-') ?></td><td class="px-3 py-2.5 text-right tabular"><?= e(format_quantity((float)($d['quantity']??0))) ?> <?= e($d['unit']??'') ?></td><td class="px-3 py-2.5 text-right tabular font-medium"><?= e(format_quantity((float)($d['stock_after']??0))) ?> <?= e($d['unit']??'') ?></td></tr><?php endforeach; ?></tbody></table></div>
        <?php $ck = is_array($allocateResult['invalidated_cache_keys']??null)?$allocateResult['invalidated_cache_keys']:[]; if($ck!==[]): ?><div class="mb-3"><div class="text-[11px] text-on-surface-variant mb-1">Cache diinvalidasi:</div><div class="flex flex-wrap gap-1.5"><?php foreach($ck as $k): ?><span class="px-2 py-0.5 rounded bg-surface-container font-mono text-[11px]"><?= e($k) ?></span><?php endforeach; ?></div></div><?php endif; ?>
      <?php elseif ($allocateError === null): ?>
        <div class="mb-3 rounded-lg bg-amber-50 border border-amber-200 p-3 text-amber-900"><p class="text-[13px] font-semibold">Prasyarat alokasi</p><p class="text-[12px] mt-0.5">Order <strong>confirmed</strong>, punya ≥1 item, seluruh item lulus QC (<strong>pass</strong>), dan stok tidak boleh negatif.</p></div>
      <?php endif; ?>
      <form method="post" action="?page=orders" data-submit-lock class="space-y-3">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="allocate">
        <div class="grid grid-cols-2 gap-3">
          <label class="flex flex-col gap-1"><span class="text-[12px] font-medium text-on-surface">Order ID <span class="text-error">*</span></span><input class="h-9 px-3 rounded-lg bg-surface-container-low/60 border border-outline-variant/40 text-[13px] focus:bg-white focus:outline-none focus:ring-1 focus:ring-primary" type="number" name="order_id" min="1" step="1" required value="<?= e($orderIdValue) ?>" placeholder="contoh: 3" <?= $focus==='allocate'&&$allocateResult===null&&$allocateError===null?'autofocus':'' ?>></label>
          <label class="flex flex-col gap-1"><span class="text-[12px] font-medium text-on-surface">Expected version <span class="text-on-surface-variant font-normal">(opsional)</span></span><input class="h-9 px-3 rounded-lg bg-surface-container-low/60 border border-outline-variant/40 text-[13px] focus:bg-white focus:outline-none focus:ring-1 focus:ring-primary" type="number" name="expected_version" min="1" step="1" placeholder="optimistic lock"></label>
        </div>
        <button class="w-full h-9 rounded-lg bg-primary hover:bg-tertiary-container text-on-primary text-[13px] font-medium" type="submit">Alokasikan Stok</button>
      </form>
    </div>
  </section>
</div>
