<?php
/** @var array<string, mixed> $data */
/** @var bool $lowOnly */
/** @var string $category */
/** @var string $sku */
/** @var array<string, mixed>|null $detail */
/** @var array<string, mixed>|null $detailError */
/** @var array<string, mixed>|null $restockResult */
/** @var array<string, mixed>|null $restockError */
$items = is_array($data['items'] ?? null) ? $data['items'] : [];
$detail = is_array($detail ?? null) ? $detail : null;
$detailError = is_array($detailError ?? null) ? $detailError : null;
$restockResult = is_array($restockResult ?? null) ? $restockResult : null;
$restockError = is_array($restockError ?? null) ? $restockError : null;
$lowOnly = (bool)($lowOnly ?? false);
$category = (string)($category ?? '');
$sku = (string)($sku ?? '');
$lowCount = 0; $outCount = 0; $inventoryValue = 0.0;
foreach ($items as $item) { $st=(string)($item['status']??''); if($st==='low')$lowCount++; if($st==='out')$outCount++; $inventoryValue += (float)($item['stock_on_hand']??0)*(float)($item['unit_cost']??0); }
$baseQuery = ['page'=>'stocks']; if($category!=='') $baseQuery['category']=$category; if($lowOnly) $baseQuery['low_only']='1';
?>
<div class="flex flex-col gap-6">
  <section class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
    <div><span class="text-[11px] font-semibold uppercase tracking-wider text-primary">Manajemen Inventori Dapur</span><h1 class="font-headline text-[22px] leading-7 font-semibold tracking-tight mt-0.5">Stok Bahan Baku &amp; Kualitas</h1><p class="text-[13px] text-on-surface-variant mt-1 max-w-2xl">Pantau posisi inventori, reorder point, nilai persediaan, dan keterkaitan menu. Status kritis membantu Quality Control & perencanaan restock.</p></div>
    <div class="flex items-center gap-2"><a href="?page=orders" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-primary hover:bg-tertiary-container text-on-primary text-[13px] font-medium"><span class="material-symbols-outlined text-[18px]">receipt_long</span>Ke Order &amp; Parser</a></div>
  </section>

  <section class="bg-surface-container-lowest rounded-xl p-4 shadow-xs border border-outline-variant/20">
    <form method="get" class="flex flex-wrap items-end gap-3">
      <input type="hidden" name="page" value="stocks">
      <label class="flex flex-col gap-1"><span class="text-[11px] font-medium text-on-surface-variant uppercase tracking-wide">Kategori</span><input class="h-9 px-3 rounded-lg bg-surface-container-low/60 border border-outline-variant/40 text-[13px] focus:bg-white focus:outline-none focus:ring-1 focus:ring-primary min-w-[180px]" type="text" name="category" list="category-options" value="<?= e($category) ?>" placeholder="protein / sembako / bumbu"><datalist id="category-options"><option value="sembako"></option><option value="protein"></option><option value="bumbu"></option><option value="bahan"></option></datalist></label>
      <label class="inline-flex items-center gap-2 h-9 px-3 rounded-lg bg-surface-container-low/60 border border-outline-variant/40 cursor-pointer select-none"><input type="checkbox" name="low_only" value="1" class="rounded border-outline-variant text-primary focus:ring-primary" <?= $lowOnly?'checked':'' ?>><span class="text-[13px] font-medium">Hanya menipis / kritis</span></label>
      <button class="h-9 px-4 rounded-lg bg-primary-container hover:bg-primary text-on-primary text-[13px] font-medium" type="submit">Terapkan</button>
      <?php if($category!==''||$lowOnly): ?><a class="h-9 inline-flex items-center px-3 rounded-lg bg-surface-container hover:bg-surface-container-high text-[13px]" href="?page=stocks">Reset</a><?php endif; ?>
    </form>
  </section>

  <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
    <div class="bg-surface-container-lowest p-5 rounded-xl shadow-xs border border-outline-variant/20"><div class="flex items-center justify-between"><span class="text-[11px] text-on-surface-variant uppercase tracking-wide font-medium">Bahan Terpantau</span><span class="material-symbols-outlined text-outline text-[20px]">inventory_2</span></div><div class="mt-2 font-headline text-[28px] font-semibold tabular"><?= e(count($items)) ?> <span class="text-[13px] font-normal text-on-surface-variant">item</span></div><div class="mt-1 text-[11px] text-on-surface-variant">Sesuai filter aktif</div></div>
    <div class="bg-surface-container-lowest p-5 rounded-xl shadow-xs border border-outline-variant/20"><div class="flex items-center justify-between"><span class="text-[11px] text-on-surface-variant uppercase tracking-wide font-medium">Stok Menipis</span><span class="material-symbols-outlined text-amber-700 text-[20px]">warning</span></div><div class="mt-2 font-headline text-[28px] font-semibold tabular"><?= e($lowCount) ?></div><div class="mt-1"><span class="inline-flex px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[11px] font-semibold">Perlu restock</span></div></div>
    <div class="bg-surface-container-lowest p-5 rounded-xl shadow-xs border border-outline-variant/20"><div class="flex items-center justify-between"><span class="text-[11px] text-on-surface-variant uppercase tracking-wide font-medium">Bahan Habis</span><span class="material-symbols-outlined text-error text-[20px]">fmd_bad</span></div><div class="mt-2 font-headline text-[28px] font-semibold tabular text-error"><?= e($outCount) ?></div><div class="mt-1"><span class="inline-flex px-2 py-0.5 rounded-full bg-error-container text-on-error-container text-[11px] font-semibold">Out of stock</span></div></div>
    <div class="bg-surface-container-lowest p-5 rounded-xl shadow-xs border border-outline-variant/20"><div class="flex items-center justify-between"><span class="text-[11px] text-on-surface-variant uppercase tracking-wide font-medium">Nilai Persediaan</span><span class="material-symbols-outlined text-primary text-[20px]">payments</span></div><div class="mt-2 font-headline text-[24px] font-semibold tabular">Rp <?= e(format_number($inventoryValue)) ?></div><div class="mt-1 text-[11px] text-on-surface-variant">Stok × harga satuan (filter)</div></div>
  </section>

  <?php if ($restockResult !== null): ?><div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-emerald-900"><p class="text-[13px] font-semibold">Restock berhasil: <?= e($restockResult['sku'] ?? '-') ?> (<?= e($restockResult['name'] ?? '-') ?>)</p><p class="text-[12px] mt-0.5">Stok sekarang <?= e(format_quantity((float)($restockResult['stock_on_hand'] ?? 0))) ?> <?= e($restockResult['unit'] ?? '') ?> · status <?= e(status_label((string)($restockResult['status'] ?? ''))) ?></p></div><?php endif; ?>
  <?php if ($restockError !== null): ?><div class="rounded-lg border border-error/30 bg-error-container/40 p-3"><p class="text-[13px] font-semibold text-on-error-container">Restock gagal</p><p class="text-[12px] text-on-error-container/90 mt-0.5"><?= e($restockError['message'] ?? 'Permintaan gagal') ?></p><p class="text-[11px] font-mono mt-1"><?= e($restockError['code'] ?? 'ERROR') ?></p></div><?php endif; ?>
  <?php if ($detailError !== null): ?><div class="rounded-lg border border-error/30 bg-error-container/40 p-3"><p class="text-[13px] font-semibold text-on-error-container">Detail bahan gagal dimuat</p><p class="text-[12px] text-on-error-container/90 mt-0.5"><?= e($detailError['message'] ?? 'Bahan tidak ditemukan.') ?></p><p class="text-[11px] font-mono mt-1"><?= e($detailError['code'] ?? 'ERROR') ?></p></div><?php endif; ?>

  <?php if ($detail !== null): ?>
    <?php $dSt=(string)($detail['status']??''); $dTone=status_tone($dSt); $dStock=(float)($detail['stock_on_hand']??0); $dRop=(float)($detail['reorder_point']??0); $dPct=stock_percent($dStock,$dRop); $dVal=$dStock*(float)($detail['unit_cost']??0); $used=is_array($detail['used_in_menus']??null)?$detail['used_in_menus']:[]; $pill=['positive'=>'bg-emerald-50 text-emerald-700','warning'=>'bg-amber-100 text-amber-800','danger'=>'bg-error-container text-error','neutral'=>'bg-surface-container text-on-surface'][$dTone]??'bg-surface-container text-on-surface'; $bar=['positive'=>'bg-primary','warning'=>'bg-amber-500','danger'=>'bg-error','neutral'=>'bg-primary'][$dTone]??'bg-primary'; ?>
    <section class="bg-surface-container-lowest rounded-xl p-5 shadow-xs border border-primary/30 ring-1 ring-primary/10">
      <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4 pb-4 border-b border-outline-variant/20">
        <div class="flex items-start gap-3"><div class="w-11 h-11 rounded-xl <?= $dTone==='danger'?'bg-error-container text-error':($dTone==='warning'?'bg-amber-50 text-amber-700':'bg-primary/10 text-primary') ?> flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-[24px]">science</span></div><div><div class="flex items-center gap-2 flex-wrap"><h2 class="font-headline text-[18px] font-semibold"><?= e($detail['name'] ?? '-') ?></h2><span class="px-2 py-0.5 rounded-full <?= $pill ?> text-[11px] font-semibold"><?= e(status_label($dSt)) ?></span><span class="px-2 py-0.5 rounded bg-surface-container text-on-surface-variant text-[11px]"><?= e($detail['category'] ?? 'Bahan') ?></span></div><div class="text-[11px] font-mono text-outline mt-0.5">SKU: <?= e($detail['sku'] ?? '-') ?></div></div></div>
        <a href="?<?= e(http_build_query($baseQuery)) ?>" class="inline-flex items-center gap-1 text-[13px] text-on-surface-variant hover:text-on-surface"><span class="material-symbols-outlined text-[16px]">close</span>Tutup detail</a>
      </div>
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 pt-4">
        <div class="lg:col-span-7 space-y-3">
          <div><div class="flex justify-between text-[12px] text-on-surface-variant mb-1"><span>Stok vs Reorder Point</span><strong class="text-on-surface tabular"><?= e(format_quantity($dStock)) ?> <?= e($detail['unit'] ?? '') ?></strong></div><div class="w-full bg-surface-container-high h-2.5 rounded-full overflow-hidden"><div class="<?= $bar ?> h-full rounded-full" style="width: <?= e(number_format($dPct,1,'.','')) ?>%"></div></div><div class="flex justify-between text-[11px] text-on-surface-variant mt-1"><span>ROP: <?= e(format_quantity($dRop)) ?> <?= e($detail['unit'] ?? '') ?></span><span><?= e(format_number($dPct,0)) ?>% (target 2× ROP)</span></div></div>
          <div class="rounded-lg bg-surface-container-low p-3"><div class="text-[11px] uppercase tracking-wide text-on-surface-variant font-medium mb-2">Menu pemakai bahan (QC &amp; Resep)</div><?php if($used===[]): ?><div class="text-[12px] text-on-surface-variant">Belum dipakai resep.</div><?php else: ?><div class="flex flex-wrap gap-1.5"><?php foreach($used as $ms): ?><span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-surface-container-lowest border border-outline-variant/30 font-mono text-[11px] text-on-surface"><span class="w-1.5 h-1.5 rounded-full bg-primary"></span><?= e($ms) ?></span><?php endforeach; ?></div><?php endif; ?></div>
        </div>
        <div class="lg:col-span-5 grid grid-cols-2 gap-3">
          <div class="p-3 rounded-lg bg-surface-container-low"><div class="text-[11px] text-on-surface-variant">Harga Satuan</div><div class="text-[14px] font-semibold tabular mt-0.5">Rp <?= e(format_number((float)($detail['unit_cost']??0))) ?></div></div>
          <div class="p-3 rounded-lg bg-surface-container-low"><div class="text-[11px] text-on-surface-variant">Nilai Stok</div><div class="text-[14px] font-semibold tabular mt-0.5">Rp <?= e(format_number($dVal)) ?></div></div>
          <div class="p-3 rounded-lg bg-surface-container-low"><div class="text-[11px] text-on-surface-variant">Satuan</div><div class="text-[14px] font-semibold mt-0.5"><?= e($detail['unit'] ?? '-') ?></div></div>
          <div class="p-3 rounded-lg bg-surface-container-low"><div class="text-[11px] text-on-surface-variant">Dipakai di</div><div class="text-[14px] font-semibold tabular mt-0.5"><?= e(count($used)) ?> menu</div></div>
        </div>
      </div>
      <div class="mt-4 pt-4 border-t border-outline-variant/20">
        <h3 class="text-[13px] font-semibold mb-2">Restock / Tambah Stok</h3>
        <form method="post" action="?page=stocks&sku=<?= e($detail['sku'] ?? '') ?>" data-submit-lock class="flex flex-wrap items-end gap-2">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="restock">
          <input type="hidden" name="sku" value="<?= e($detail['sku'] ?? '') ?>">
          <input type="hidden" name="detail_sku" value="<?= e($detail['sku'] ?? '') ?>">
          <input type="hidden" name="category" value="<?= e($category) ?>">
          <input type="hidden" name="low_only" value="<?= $lowOnly ? '1' : '0' ?>">
          <label class="flex flex-col gap-1"><span class="text-[12px] font-medium">Jumlah tambah (<?= e($detail['unit'] ?? '') ?>)</span><input class="h-9 px-3 rounded-lg bg-surface-container-low/60 border border-outline-variant/40 text-[13px] w-40" type="number" name="quantity" min="0.001" step="0.001" required placeholder="contoh: 50"></label>
          <button class="h-9 px-4 rounded-lg bg-primary text-on-primary text-[13px] font-medium" type="submit">Tambah Stok</button>
          <span class="text-[11px] text-on-surface-variant pb-2">Setelah restock, buka Order terkait dan klik Cek & Konfirmasi untuk update status (short→confirmed bila cukup).</span>
        </form>
      </div>
    </section>
  <?php endif; ?>

  <section class="bg-surface-container-lowest rounded-xl shadow-xs border border-outline-variant/20 overflow-hidden">
    <div class="px-5 py-4 flex flex-wrap items-center justify-between gap-2"><div><h2 class="font-headline text-[15px] font-semibold">Pusat Data Bahan Baku</h2><p class="text-[11px] text-on-surface-variant mt-0.5">Sumber <span class="font-mono"><?= e($data['source'] ?? '-') ?></span> · <?= e(format_datetime_id($data['generated_at'] ?? null)) ?></p></div><?php if($category!==''||$lowOnly): ?><span class="px-2 py-0.5 rounded-full bg-primary/10 text-primary text-[11px] font-medium">Filter aktif</span><?php endif; ?></div>
    <?php if ($items === []): ?>
      <div class="px-5 pb-5"><div class="rounded-lg bg-surface-container-low p-8 text-center text-[13px] text-on-surface-variant">Tidak ada bahan yang cocok dengan filter.</div></div>
    <?php else: ?>
      <div class="overflow-x-auto"><table class="w-full text-left"><thead><tr class="bg-surface-container-low text-on-surface-variant text-[11px] uppercase tracking-wider"><th class="py-2.5 px-4 font-medium">SKU</th><th class="py-2.5 px-4 font-medium">Nama Bahan</th><th class="py-2.5 px-4 font-medium">Kategori</th><th class="py-2.5 px-4 font-medium text-right">Stok</th><th class="py-2.5 px-4 font-medium text-right">Reorder</th><th class="py-2.5 px-4 font-medium">Rasio</th><th class="py-2.5 px-4 font-medium text-right">Harga</th><th class="py-2.5 px-4 font-medium text-right">Nilai</th><th class="py-2.5 px-4 font-medium text-center">Status / QC</th></tr></thead><tbody class="text-[13px] divide-y divide-surface-container-low/70">
        <?php foreach($items as $item): $st=(string)($item['status']??''); $tone=status_tone($st); $stock=(float)($item['stock_on_hand']??0); $rop=(float)($item['reorder_point']??0); $pct=stock_percent($stock,$rop); $rowQ=$baseQuery; $rowQ['sku']=(string)($item['sku']??''); $pill=['positive'=>'bg-emerald-50 text-emerald-700','warning'=>'bg-amber-100 text-amber-800','danger'=>'bg-error-container text-error','neutral'=>'bg-surface-container text-on-surface'][$tone]??'bg-surface-container'; $bar=['positive'=>'bg-primary','warning'=>'bg-amber-500','danger'=>'bg-error','neutral'=>'bg-primary'][$tone]??'bg-primary'; $isSel = $sku!=='' && $sku===(string)($item['sku']??''); ?>
          <tr class="hover:bg-surface-container-low/60 transition-colors <?= $isSel?'bg-primary/5':'' ?>">
            <td class="py-3 px-4 font-mono text-[11px] text-primary font-medium whitespace-nowrap"><?= e($item['sku'] ?? '-') ?></td>
            <td class="py-3 px-4"><a href="?<?= e(http_build_query($rowQ)) ?>" class="font-medium text-on-surface hover:text-primary"><?= e($item['name'] ?? '-') ?></a><div class="text-[11px] text-outline"><?= e($item['supplier'] ?? '') ?></div></td>
            <td class="py-3 px-4"><span class="px-2 py-0.5 rounded bg-surface-container text-on-surface-variant text-[11px]"><?= e($item['category'] ?? '-') ?></span></td>
            <td class="py-3 px-4 text-right tabular font-semibold <?= $tone==='danger'?'text-error':($tone==='warning'?'text-amber-700':'text-on-surface') ?>"><?= e(format_quantity($stock)) ?> <?= e($item['unit'] ?? '') ?></td>
            <td class="py-3 px-4 text-right tabular text-on-surface-variant"><?= e(format_quantity($rop)) ?></td>
            <td class="py-3 px-4 min-w-[130px]"><div class="flex items-center gap-2"><div class="w-full bg-surface-container-high h-2 rounded-full overflow-hidden"><div class="<?= $bar ?> h-full rounded-full" style="width: <?= e(number_format($pct,1,'.','')) ?>%"></div></div><span class="tabular text-[11px] text-on-surface-variant w-8 text-right"><?= e(format_number($pct,0)) ?>%</span></div></td>
            <td class="py-3 px-4 text-right tabular">Rp <?= e(format_number((float)($item['unit_cost']??0))) ?></td>
            <td class="py-3 px-4 text-right tabular font-medium">Rp <?= e(format_number($stock*(float)($item['unit_cost']??0))) ?></td>
            <td class="py-3 px-4 text-center"><span class="inline-flex items-center px-2 py-0.5 rounded-full <?= $pill ?> text-[11px] font-semibold"><?= e(status_label($st)) ?></span></td>
          </tr>
        <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
    <div class="px-5 py-3 bg-surface-container-low/50 border-t border-outline-variant/20 text-[11px] text-on-surface-variant flex items-center justify-between"><span>Menampilkan <?= e(count($items)) ?> bahan · tabel <code class="font-mono">ingredients</code> PostgreSQL</span><a href="?page=health" class="text-primary font-medium hover:underline">Cek status sistem</a></div>
  </section>
</div>
