<?php
/** @var array<string, mixed>|null $parseResult */
/** @var array<string, mixed>|null $parseError */
/** @var array<string, mixed>|null $saveResult */
/** @var array<string, mixed>|null $saveError */
/** @var array<string, mixed>|null $checkResult */
/** @var array<string, mixed>|null $checkError */
/** @var array<string, mixed>|null $confirmResult */
/** @var array<string, mixed>|null $confirmError */
/** @var array<string, mixed>|null $qcResult */
/** @var array<string, mixed>|null $qcError */
/** @var array<string, mixed>|null $allocateResult */
/** @var array<string, mixed>|null $allocateError */
/** @var array<string, mixed>|null $ordersList */
/** @var array<string, mixed>|null $orderDetail */
/** @var int|null $orderId */
/** @var string|null $focus */
/** @var array<string, string> $form */

$parseResult = is_array($parseResult ?? null) ? $parseResult : null;
$parseError = is_array($parseError ?? null) ? $parseError : null;
$saveResult = is_array($saveResult ?? null) ? $saveResult : null;
$saveError = is_array($saveError ?? null) ? $saveError : null;
$checkResult = is_array($checkResult ?? null) ? $checkResult : null;
$checkError = is_array($checkError ?? null) ? $checkError : null;
$confirmResult = is_array($confirmResult ?? null) ? $confirmResult : null;
$confirmError = is_array($confirmError ?? null) ? $confirmError : null;
$qcResult = is_array($qcResult ?? null) ? $qcResult : null;
$qcError = is_array($qcError ?? null) ? $qcError : null;
$allocateResult = is_array($allocateResult ?? null) ? $allocateResult : null;
$allocateError = is_array($allocateError ?? null) ? $allocateError : null;
$ordersList = is_array($ordersList ?? null) ? $ordersList : ['items' => []];
$orderDetail = is_array($orderDetail ?? null) ? $orderDetail : null;
$form = is_array($form ?? null) ? $form : [];
$orderIdValue = $orderId !== null ? (string) $orderId : (string)($saveResult['order_id'] ?? ($orderDetail['id'] ?? ''));
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
$saveItemsJson = htmlspecialchars(json_encode(array_map(static fn($i)=>['menu_sku'=>(string)($i['menu_sku']??''),'quantity'=>(float)($i['quantity']??0)], $parseItems), JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
$confidence = (float)($parseResult['confidence'] ?? 0);
$confidencePercent = max(0.0, min(100.0, $confidence * 100));
$confidenceTone = confidence_tone($confidence);
$checkItems = $checkResult !== null && is_array($checkResult['requirements'] ?? null) ? $checkResult['requirements'] : (is_array($confirmResult['insufficient'] ?? null) ? [] : []);
$checkInsufficient = $checkResult !== null && is_array($checkResult['insufficient'] ?? null) ? $checkResult['insufficient'] : (is_array($confirmResult['insufficient'] ?? null) ? $confirmResult['insufficient'] : []);
$allocateDeductions = $allocateResult !== null && is_array($allocateResult['deductions'] ?? null) ? $allocateResult['deductions'] : [];
$listItems = is_array($ordersList['items'] ?? null) ? $ordersList['items'] : [];
$detailItems = $orderDetail !== null && is_array($orderDetail['items'] ?? null) ? $orderDetail['items'] : [];
$detailReqs = $orderDetail !== null && is_array($orderDetail['requirements'] ?? null) ? $orderDetail['requirements'] : [];
$detailIns = $orderDetail !== null && is_array($orderDetail['insufficient'] ?? null) ? $orderDetail['insufficient'] : [];
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
$statusPill = static function(string $s): string { $t=status_tone($s); return match($t){'positive'=>'bg-emerald-50 text-emerald-700','warning'=>'bg-amber-100 text-amber-800','danger'=>'bg-error-container text-error',default=>'bg-surface-container text-on-surface'}; };
?>
<div class="flex flex-col gap-6">
  <section class="bg-surface-container-lowest rounded-xl p-5 shadow-xs border border-outline-variant/20 flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div>
      <div class="flex items-center gap-2 mb-1"><span class="px-2 py-0.5 rounded-full bg-primary/10 text-primary text-[11px] uppercase tracking-wider font-semibold">Workbench Pemrosesan Pesanan</span><span class="inline-flex items-center gap-1 text-on-surface-variant text-[11px]"><span class="h-1.5 w-1.5 rounded-full bg-primary animate-pulse"></span>E2E Aktif</span></div>
      <h1 class="font-headline text-[22px] leading-7 font-semibold tracking-tight text-on-surface">Order &amp; Parser</h1>
      <p class="text-[13px] text-on-surface-variant mt-0.5 max-w-3xl">Urai teks → simpan order → cek/konfirmasi status → QC pass → alokasi stok transaksional (end-to-end).</p>
    </div>
    <div class="flex items-center gap-1.5 bg-surface-container-low p-1.5 rounded-lg shrink-0 flex-wrap">
      <a href="#parse" class="px-3 py-1.5 rounded-lg bg-surface-container-lowest text-primary text-[13px] font-medium shadow-sm">1. Parse</a>
      <a href="#order-detail" class="px-3 py-1.5 rounded-lg text-on-surface-variant hover:text-on-surface text-[13px]">2. Order/QC</a>
      <a href="#allocate" class="px-3 py-1.5 rounded-lg text-on-surface-variant hover:text-on-surface text-[13px]">3. Alokasi</a>
    </div>
  </section>

  <?php if ($saveResult !== null): ?><div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-emerald-900"><p class="text-[13px] font-semibold">Order tersimpan: <?= e($saveResult['order_code'] ?? '-') ?> (ID <?= e($saveResult['order_id'] ?? '-') ?>, status <?= e(status_label((string)($saveResult['status'] ?? ''))) ?>)</p><p class="text-[12px] mt-0.5">Lanjut cek/konfirmasi, catat QC pass per item, lalu alokasi bila siap.</p></div><?php endif; ?>
  <?php if ($saveError !== null): ?><?php $renderError($saveError); ?><?php endif; ?>

  <section id="parse" class="grid grid-cols-1 xl:grid-cols-12 gap-4 items-start scroll-mt-20">
    <form method="post" action="?page=orders" class="xl:col-span-5 bg-surface-container-lowest rounded-xl p-5 shadow-xs border border-outline-variant/20 space-y-4" data-submit-lock>
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="parse">
      <div class="flex items-center justify-between pb-1"><div class="flex items-center gap-2"><div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center text-primary"><span class="material-symbols-outlined text-[18px]">edit_note</span></div><div><h2 class="font-headline text-[15px] font-semibold">Input Teks Pesanan</h2><p class="text-[12px] text-on-surface-variant">Tempel teks dari WhatsApp/Email/Telepon</p></div></div></div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <label class="flex flex-col gap-1"><span class="text-[12px] font-medium text-on-surface">Nama Customer <span class="text-error">*</span></span><input class="h-9 px-3 rounded-lg bg-surface-container-low/60 border border-outline-variant/40 text-[13px]" type="text" name="customer_name" minlength="2" maxlength="120" required value="<?= e($form['customer_name'] ?? '') ?>"></label>
        <label class="flex flex-col gap-1"><span class="text-[12px] font-medium text-on-surface">Channel</span><select class="h-9 px-3 rounded-lg bg-surface-container-low/60 border border-outline-variant/40 text-[13px]" name="channel"><?php foreach($channels as $v=>$l): ?><option value="<?= e($v) ?>" <?= ($form['channel']??'whatsapp')===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
        <label class="flex flex-col gap-1"><span class="text-[12px] font-medium text-on-surface">Tanggal Order</span><input class="h-9 px-3 rounded-lg bg-surface-container-low/60 border border-outline-variant/40 text-[13px]" type="date" name="order_date" value="<?= e($form['order_date'] ?? '') ?>"></label>
        <label class="flex flex-col gap-1"><span class="text-[12px] font-medium text-on-surface">Catatan</span><input class="h-9 px-3 rounded-lg bg-surface-container-low/60 border border-outline-variant/40 text-[13px]" type="text" name="notes" maxlength="500" value="<?= e($form['notes'] ?? '') ?>"></label>
      </div>
      <div class="flex flex-wrap gap-1.5"><?php foreach($samples as $s): ?><button type="button" class="px-2.5 py-1 rounded bg-surface-container text-on-surface hover:bg-surface-container-high text-[12px]" data-fill-target="raw_text" data-fill-value="<?= e($s['value']) ?>"><?= e($s['label']) ?></button><?php endforeach; ?></div>
      <label class="flex flex-col gap-1.5"><span class="text-[12px] font-medium text-on-surface">Teks Mentah Pesanan <span class="text-error">*</span></span><textarea id="raw_text" name="raw_text" rows="6" minlength="3" maxlength="500" required class="w-full p-3 rounded-lg bg-surface-container-low font-mono text-[13px] border border-outline-variant/30"><?= e($form['raw_text'] ?? '') ?></textarea></label>
      <div class="flex items-center justify-between gap-3 pt-1"><a href="?page=orders" class="px-3 py-2 rounded-lg bg-surface-container text-[13px] font-medium">Reset</a><button type="submit" class="px-5 py-2 rounded-lg bg-primary text-on-primary text-[13px] font-medium">Urai Pesanan</button></div>
    </form>

    <div class="xl:col-span-7 space-y-4">
      <div class="bg-surface-container-lowest rounded-xl p-5 shadow-xs border border-outline-variant/20 space-y-4">
        <div class="flex items-center justify-between pb-1"><div><h2 class="font-headline text-[15px] font-semibold">Hasil Parse</h2><p class="text-[12px] text-on-surface-variant">Item terstruktur & peringatan</p></div><?php if($parseResult!==null): ?><span class="px-2.5 py-1 rounded-full <?= $toneBg[$confidenceTone] ?? $toneBg['neutral'] ?> text-[11px] font-semibold"><?= e(confidence_label($confidence)) ?> <?= e(format_number($confidencePercent,1)) ?>%</span><?php endif; ?></div>
        <?php if ($parseError !== null): $renderError($parseError); elseif ($parseResult !== null): ?>
          <div class="text-[13px] font-semibold"><?= e(format_date_id((string)($parseResult['order_date']??''), true)) ?></div>
          <?php if ($parseItems !== []): ?>
            <div class="overflow-x-auto rounded-lg border border-outline-variant/20"><table class="w-full text-left"><thead><tr class="bg-surface-container-low text-on-surface-variant text-[11px] uppercase"><th class="px-3 py-2">Baris</th><th class="px-3 py-2">Menu</th><th class="px-3 py-2 text-center">Qty</th><th class="px-3 py-2 text-right">Subtotal</th></tr></thead><tbody class="text-[13px] divide-y divide-surface-container-low"><?php foreach($parseItems as $item): $q=(float)($item['quantity']??0); $up=(float)($item['unit_price']??0); ?><tr><td class="px-3 py-2.5 font-mono text-[11px]">"<?= e($item['line_text']??'-') ?>"</td><td class="px-3 py-2.5"><div class="font-medium"><?= e($item['menu_name']??'-') ?></div><div class="text-[11px] font-mono text-on-surface-variant"><?= e($item['menu_sku']??'-') ?></div></td><td class="px-3 py-2.5 text-center tabular"><?= e(format_quantity($q)) ?></td><td class="px-3 py-2.5 text-right tabular">Rp <?= e(format_number($q*$up)) ?></td></tr><?php endforeach; ?></tbody></table></div>
            <div class="p-3 rounded-lg bg-surface-container-low text-[13px] flex justify-between"><span>Total <?= e(count($parseItems)) ?> item · <?= e(format_quantity($parseQty)) ?> porsi</span><strong class="text-primary">Rp <?= e(format_number($parseTotal)) ?></strong></div>
            <?php if ($parseWarnings === []): ?>
              <form method="post" action="?page=orders#order-detail" data-submit-lock class="flex items-center justify-between gap-3 p-3 rounded-lg bg-primary/5 border border-primary/20">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="raw_text" value="<?= e($form['raw_text'] ?? '') ?>">
                <input type="hidden" name="channel" value="<?= e($form['channel'] ?? 'whatsapp') ?>">
                <input type="hidden" name="customer_name" value="<?= e($form['customer_name'] ?? '') ?>">
                <input type="hidden" name="order_date" value="<?= e($parseResult['order_date'] ?? ($form['order_date'] ?? '')) ?>">
                <input type="hidden" name="notes" value="<?= e($form['notes'] ?? '') ?>">
                <input type="hidden" name="items_json" value="<?= $saveItemsJson ?>">
                <div class="text-[12px] text-on-surface"><strong>Siap disimpan.</strong> Simpan sebagai order tersimpan (status parsed) untuk proses cek/QC/alokasi.</div>
                <button type="submit" class="px-4 py-2 rounded-lg bg-primary text-on-primary text-[13px] font-medium whitespace-nowrap">Simpan Order</button>
              </form>
            <?php else: ?>
              <div class="rounded-lg bg-amber-50 border border-amber-200 p-3 text-[12px] text-amber-900">Perbaiki peringatan parser lalu urai ulang sebelum menyimpan order.</div>
            <?php endif; ?>
          <?php else: ?>
            <div class="rounded-lg bg-amber-50 border border-amber-200 p-3 text-amber-900 text-[13px]">Tidak ada item dikenali.</div>
          <?php endif; ?>
          <?php if ($parseWarnings !== []): ?><div class="space-y-2"><?php foreach($parseWarnings as $w): ?><div class="p-2.5 rounded-lg bg-amber-50/70 border border-amber-200/80 text-[12px]"><span class="font-mono font-semibold"><?= e($w['code']??'') ?></span> — <?= e($w['message']??'') ?> <?php if(!empty($w['line_text'])): ?><span class="font-mono">"<?= e($w['line_text']) ?>"</span><?php endif; ?></div><?php endforeach; ?></div><?php endif; ?>
        <?php else: ?>
          <div class="rounded-lg bg-surface-container-low p-8 text-center text-[13px] text-on-surface-variant">Tempel teks lalu tekan <strong>Urai Pesanan</strong>.</div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <div class="flex items-center gap-3 text-[11px] text-on-surface-variant uppercase tracking-wider font-medium"><span>Order Tersimpan &amp; E2E</span><div class="h-px flex-1 bg-outline-variant/30"></div></div>

  <section id="order-detail" class="grid grid-cols-1 xl:grid-cols-12 gap-4 items-start scroll-mt-20">
    <div class="xl:col-span-5 bg-surface-container-lowest rounded-xl p-5 shadow-xs border border-outline-variant/20 space-y-3">
      <div class="flex items-center justify-between"><h2 class="font-headline text-[15px] font-semibold">Daftar Order Terbaru</h2><span class="text-[11px] text-on-surface-variant"><?= e(count($listItems)) ?> order</span></div>
      <?php if ($listItems === []): ?>
        <div class="rounded-lg bg-surface-container-low p-4 text-[13px] text-on-surface-variant">Belum ada order tersimpan. Urai & simpan dari panel parse.</div>
      <?php else: ?>
        <div class="overflow-x-auto rounded-lg border border-outline-variant/20"><table class="w-full text-left"><thead><tr class="bg-surface-container-low text-on-surface-variant text-[11px] uppercase"><th class="px-3 py-2">ID/Kode</th><th class="px-3 py-2">Customer</th><th class="px-3 py-2">Status</th><th class="px-3 py-2"></th></tr></thead><tbody class="text-[12px] divide-y divide-surface-container-low"><?php foreach($listItems as $o): ?><tr class="<?= (string)($o['id']??'')===$orderIdValue?'bg-primary/5':'' ?>"><td class="px-3 py-2"><div class="font-semibold">#<?= e($o['id']??'-') ?></div><div class="font-mono text-[10px] text-on-surface-variant"><?= e($o['order_code']??'-') ?></div></td><td class="px-3 py-2"><?= e($o['customer_name']??'-') ?><div class="text-[10px] text-on-surface-variant"><?= e(format_date_id((string)($o['order_date']??''))) ?> · <?= e($o['item_count']??0) ?> item</div></td><td class="px-3 py-2"><span class="px-2 py-0.5 rounded-full text-[10px] font-semibold <?= $statusPill((string)($o['status']??'')) ?>"><?= e(status_label((string)($o['status']??''))) ?></span></td><td class="px-3 py-2 text-right"><a class="text-primary font-medium hover:underline" href="?page=orders&order_id=<?= e($o['id']??'') ?>&focus=check#order-detail">Buka</a></td></tr><?php endforeach; ?></tbody></table></div>
      <?php endif; ?>
    </div>

    <div class="xl:col-span-7 space-y-4">
      <?php if ($confirmResult !== null): ?><div class="rounded-lg border <?= ($confirmResult['status']??'')==='confirmed'?'border-emerald-200 bg-emerald-50 text-emerald-900':'border-amber-200 bg-amber-50 text-amber-900' ?> p-3"><p class="text-[13px] font-semibold">Konfirmasi: status <?= e(status_label((string)($confirmResult['status']??''))) ?> (v<?= e($confirmResult['version']??'-') ?>)</p><?php if(!empty($confirmResult['insufficient'])): ?><p class="text-[12px] mt-0.5">Stok kurang pada beberapa bahan — restock lalu konfirmasi ulang.</p><?php endif; ?></div><?php endif; ?>
      <?php if ($confirmError !== null): ?><?php $renderError($confirmError); ?><?php endif; ?>
      <?php if ($qcResult !== null): ?><div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-emerald-900"><p class="text-[13px] font-semibold">QC pass tercatat untuk item #<?= e($qcResult['order_item_id']??'-') ?> (<?= e($qcResult['recorded']??0) ?> log bahan)</p></div><?php endif; ?>
      <?php if ($qcError !== null): ?><?php $renderError($qcError); ?><?php endif; ?>

      <?php if ($orderDetail !== null): ?>
        <div class="bg-surface-container-lowest rounded-xl p-5 shadow-xs border border-outline-variant/20 space-y-4">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div><div class="flex items-center gap-2"><h2 class="font-headline text-[16px] font-semibold"><?= e($orderDetail['order_code'] ?? '-') ?></h2><span class="px-2 py-0.5 rounded-full text-[11px] font-semibold <?= $statusPill((string)($orderDetail['status']??'')) ?>"><?= e(status_label((string)($orderDetail['status']??''))) ?></span></div><div class="text-[12px] text-on-surface-variant mt-0.5">ID #<?= e($orderDetail['id']??'-') ?> · <?= e($orderDetail['customer_name']??'-') ?> · v<?= e($orderDetail['version']??'-') ?> · <?= e(format_date_id((string)($orderDetail['order_date']??''), true)) ?></div></div>
            <div class="flex gap-2">
              <form method="post" action="?page=orders&order_id=<?= e($orderDetail['id']??'') ?>&focus=confirm#order-detail" data-submit-lock><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="confirm"><input type="hidden" name="order_id" value="<?= e($orderDetail['id']??'') ?>"><button class="h-9 px-3 rounded-lg bg-surface-container hover:bg-surface-container-high text-[13px] font-medium" type="submit">Cek & Konfirmasi</button></form>
            </div>
          </div>
          <div class="overflow-x-auto rounded-lg border border-outline-variant/20"><table class="w-full text-left"><thead><tr class="bg-surface-container-low text-on-surface-variant text-[11px] uppercase"><th class="px-3 py-2">Item</th><th class="px-3 py-2 text-center">Qty</th><th class="px-3 py-2 text-right">Total</th><th class="px-3 py-2 text-center">QC</th><th class="px-3 py-2">Aksi QC</th></tr></thead><tbody class="text-[13px] divide-y divide-surface-container-low"><?php foreach($detailItems as $it): ?><tr><td class="px-3 py-2.5"><div class="font-medium"><?= e($it['menu_name']??'-') ?></div><div class="text-[11px] font-mono text-on-surface-variant"><?= e($it['menu_sku']??'-') ?> · item #<?= e($it['id']??'-') ?></div></td><td class="px-3 py-2.5 text-center tabular"><?= e(format_quantity((float)($it['quantity']??0))) ?></td><td class="px-3 py-2.5 text-right tabular">Rp <?= e(format_number((float)($it['line_total']??0))) ?></td><td class="px-3 py-2.5 text-center"><?php if(!empty($it['qc_passed'])): ?><span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[11px] font-semibold">Pass</span><?php else: ?><span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[11px] font-semibold">Belum</span><?php endif; ?></td><td class="px-3 py-2.5"><?php if(empty($it['qc_passed'])): ?><form method="post" action="?page=orders&order_id=<?= e($orderDetail['id']??'') ?>&focus=qc#order-detail" data-submit-lock class="flex items-center gap-1.5"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="qc"><input type="hidden" name="order_id" value="<?= e($orderDetail['id']??'') ?>"><input type="hidden" name="item_id" value="<?= e($it['id']??'') ?>"><input type="hidden" name="checked_by" value="admin"><button class="px-2.5 py-1 rounded bg-primary/10 text-primary text-[12px] font-medium" type="submit">Catat Pass</button></form><?php else: ?><span class="text-[12px] text-on-surface-variant">—</span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
          <?php $detailSufficient = (bool)($orderDetail['sufficient'] ?? false); $dIns = $detailIns; ?>
          <div class="p-3 rounded-lg <?= $detailSufficient?'bg-emerald-50 border border-emerald-200 text-emerald-900':'bg-amber-50 border border-amber-200 text-amber-900' ?>"><p class="text-[13px] font-semibold"><?= $detailSufficient?'Kebutuhan bahan mencukupi':'Stok/bahan belum mencukupi' ?></p><p class="text-[12px] mt-0.5"><?= e(count($dIns)) ?> bahan kurang. Bila kurang, restock di menu Stok lalu klik Cek & Konfirmasi.</p></div>
          <?php if ($detailReqs !== []): ?><div class="overflow-x-auto rounded-lg border border-outline-variant/20"><table class="w-full text-left"><thead><tr class="bg-surface-container-low text-on-surface-variant text-[11px] uppercase"><th class="px-3 py-2">Bahan</th><th class="px-3 py-2 text-right">Butuh</th><th class="px-3 py-2 text-right">Tersedia</th><th class="px-3 py-2 text-right">Kurang</th></tr></thead><tbody class="text-[13px] divide-y divide-surface-container-low"><?php foreach($detailReqs as $rq): $sh=(float)($rq['shortage']??0); ?><tr><td class="px-3 py-2"><div class="font-medium"><?= e($rq['ingredient_name']??'-') ?></div><div class="text-[11px] font-mono text-on-surface-variant"><?= e($rq['ingredient_sku']??'-') ?></div></td><td class="px-3 py-2 text-right tabular"><?= e(format_quantity((float)($rq['required']??0))) ?> <?= e($rq['unit']??'') ?></td><td class="px-3 py-2 text-right tabular"><?= e(format_quantity((float)($rq['available']??0))) ?> <?= e($rq['unit']??'') ?></td><td class="px-3 py-2 text-right tabular"><?php if($sh>0): ?><span class="px-2 py-0.5 rounded-full bg-error-container text-error text-[11px] font-semibold"><?= e(format_quantity($sh)) ?></span><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
        </div>
      <?php else: ?>
        <div class="bg-surface-container-lowest rounded-xl p-8 shadow-xs border border-outline-variant/20 text-center text-[13px] text-on-surface-variant">Pilih order dari daftar untuk melihat detail, mencatat QC, konfirmasi, dan alokasi.</div>
      <?php endif; ?>
    </div>
  </section>

  <section id="allocate" class="grid grid-cols-1 lg:grid-cols-2 gap-4 scroll-mt-20">
    <div class="bg-surface-container-lowest rounded-xl p-5 shadow-xs border <?= $focus==='check'?'border-primary ring-2 ring-primary/20':'border-outline-variant/20' ?>">
      <div class="flex items-center justify-between mb-3"><div><h2 class="font-headline text-[15px] font-semibold">Cek Kecukupan Stok (manual)</h2><p class="text-[12px] text-on-surface-variant">Hitung kebutuhan bahan untuk Order ID</p></div></div>
      <?php if ($checkError !== null): ?><div class="mb-3"><?php $renderError($checkError); ?></div><?php endif; ?>
      <?php if ($checkResult !== null): $sufficient = (bool)($checkResult['sufficient'] ?? false); ?>
        <div class="mb-3 p-3 rounded-lg <?= $sufficient?'bg-emerald-50 border border-emerald-200 text-emerald-900':'bg-error-container/40 border border-error/30 text-on-error-container' ?>"><p class="text-[13px] font-semibold"><?= $sufficient?'Stok mencukupi':'Stok belum mencukupi' ?></p><p class="text-[11px] mt-0.5 font-mono"><?= e($checkResult['order_code']??'-') ?> · <?= e(count($checkInsufficient)) ?> kurang</p></div>
      <?php endif; ?>
      <form method="post" action="?page=orders&focus=check#allocate" data-submit-lock class="flex items-end gap-2">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="check">
        <label class="flex-1 flex flex-col gap-1"><span class="text-[12px] font-medium text-on-surface">Order ID</span><input class="h-9 px-3 rounded-lg bg-surface-container-low/60 border border-outline-variant/40 text-[13px]" type="number" name="order_id" min="1" required value="<?= e($orderIdValue) ?>"></label>
        <button class="h-9 px-4 rounded-lg bg-primary text-on-primary text-[13px] font-medium" type="submit">Cek Stok</button>
      </form>
    </div>

    <div class="bg-surface-container-lowest rounded-xl p-5 shadow-xs border <?= $focus==='allocate'?'border-primary ring-2 ring-primary/20':'border-outline-variant/20' ?>">
      <div class="flex items-center justify-between mb-3"><div><h2 class="font-headline text-[15px] font-semibold">Alokasi Stok</h2><p class="text-[12px] text-on-surface-variant">confirmed + QC pass + stok cukup → fulfilled</p></div></div>
      <?php if ($allocateError !== null): ?><div class="mb-3"><?php $renderError($allocateError); ?></div><?php endif; ?>
      <?php if ($allocateResult !== null): ?>
        <div class="mb-3 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-900"><p class="text-[13px] font-semibold">Alokasi berhasil · <?= e($allocateResult['status'] ?? 'fulfilled') ?></p><p class="text-[11px] mt-0.5 font-mono"><?= e($allocateResult['order_code']??'-') ?> · <?= e(format_datetime_id($allocateResult['allocated_at']??null)) ?></p></div>
        <?php if ($allocateDeductions !== []): ?><div class="overflow-x-auto rounded-lg border border-outline-variant/20 mb-3"><table class="w-full text-left"><thead><tr class="bg-surface-container-low text-on-surface-variant text-[11px] uppercase"><th class="px-3 py-2">Bahan</th><th class="px-3 py-2 text-right">Dipotong</th><th class="px-3 py-2 text-right">Sisa</th></tr></thead><tbody class="text-[13px] divide-y divide-surface-container-low"><?php foreach($allocateDeductions as $d): ?><tr><td class="px-3 py-2 font-mono text-[12px]"><?= e($d['ingredient_sku']??'-') ?></td><td class="px-3 py-2 text-right tabular"><?= e(format_quantity((float)($d['quantity']??0))) ?> <?= e($d['unit']??'') ?></td><td class="px-3 py-2 text-right tabular"><?= e(format_quantity((float)($d['stock_after']??0))) ?> <?= e($d['unit']??'') ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
      <?php else: ?>
        <div class="mb-3 rounded-lg bg-amber-50 border border-amber-200 p-3 text-amber-900 text-[12px]">Prasyarat: order <strong>confirmed</strong>, semua item <strong>QC pass</strong>, stok cukup. Jika short, restock di menu Stok lalu Cek & Konfirmasi.</div>
      <?php endif; ?>
      <form method="post" action="?page=orders&order_id=<?= e($orderIdValue) ?>&focus=allocate#allocate" data-submit-lock class="space-y-3">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="allocate">
        <div class="grid grid-cols-2 gap-3">
          <label class="flex flex-col gap-1"><span class="text-[12px] font-medium">Order ID <span class="text-error">*</span></span><input class="h-9 px-3 rounded-lg bg-surface-container-low/60 border border-outline-variant/40 text-[13px]" type="number" name="order_id" min="1" required value="<?= e($orderIdValue) ?>"></label>
          <label class="flex flex-col gap-1"><span class="text-[12px] font-medium">Expected version</span><input class="h-9 px-3 rounded-lg bg-surface-container-low/60 border border-outline-variant/40 text-[13px]" type="number" name="expected_version" min="1" placeholder="opsional" value="<?= e($orderDetail['version'] ?? '') ?>"></label>
        </div>
        <button class="w-full h-9 rounded-lg bg-primary text-on-primary text-[13px] font-medium" type="submit">Alokasikan Stok</button>
      </form>
    </div>
  </section>
</div>
