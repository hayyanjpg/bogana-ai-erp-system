<?php
/** @var array<string, mixed> $data */
/** @var int|null $menuId */
$items = is_array($data['items'] ?? null) ? $data['items'] : [];
$menuId = $menuId ?? null;
$fromDate = (string)($data['from_date'] ?? date('Y-m-d'));
$days = max(1, (int)($data['days'] ?? 7));
$rangeEnd = $fromDate; try { $rangeEnd = (new DateTimeImmutable($fromDate))->modify('+' . max(0,$days-1).' days')->format('Y-m-d'); } catch (Exception $e) {}
$byDate=[]; $totalQuantity=0.0; $confidenceSum=0.0; $menuIds=[];
foreach ($items as $item) { $dk=substr((string)($item['forecast_date']??''),0,10); if($dk==='') continue; $byDate[$dk][]=$item; $totalQuantity+=(float)($item['forecast_qty']??0); $confidenceSum+=(float)($item['confidence']??0); $menuIds[(int)($item['menu_id']??0)]=true; }
ksort($byDate);
$averageConfidence = $items!==[] ? $confidenceSum/count($items) : 0.0;
$avgPct = max(0.0,min(100.0,$averageConfidence*100));
$quickBase = ['page'=>'forecasts','from_date'=>$fromDate]; if($menuId!==null) $quickBase['menu_id']=$menuId;
?>
<div class="flex flex-col gap-6">
  <section class="flex flex-col lg:flex-row lg:items-start justify-between gap-4 bg-surface-container-lowest rounded-xl p-5 shadow-xs border border-outline-variant/20">
    <div><div class="flex items-center gap-2 mb-1"><span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-surface-container-high text-primary text-[11px] uppercase tracking-wider font-semibold"><span class="material-symbols-outlined text-[13px]">insights</span>Perencanaan Produksi</span><span class="text-[11px] text-on-surface-variant">Model <?= e($data['model_version'] ?? 'demand_forecasts') ?></span></div><h1 class="font-headline text-[22px] leading-7 font-semibold tracking-tight">Forecast Permintaan Menu</h1><p class="text-[13px] text-on-surface-variant mt-1 max-w-3xl">Estimasi kuantitas porsi B2B berdasarkan data historis &amp; model demand. Gunakan sebagai saran produksi/restock, bukan kewajiban.</p></div>
    <a href="?page=forecasts&amp;from_date=<?= e($fromDate) ?>&amp;days=<?= e($days) ?><?= $menuId!==null?'&amp;menu_id='.e($menuId):'' ?>" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-surface-container hover:bg-surface-container-high text-[13px] font-medium self-start"><span class="material-symbols-outlined text-[16px]">refresh</span>Muat ulang</a>
  </section>

  <section class="bg-surface-container-lowest rounded-xl p-4 shadow-xs border border-outline-variant/20">
    <form method="get" class="flex flex-wrap items-end gap-3">
      <input type="hidden" name="page" value="forecasts">
      <label class="flex flex-col gap-1"><span class="text-[11px] font-medium text-on-surface-variant uppercase tracking-wide">Mulai</span><input class="h-9 px-3 rounded-lg bg-surface-container-low/60 border border-outline-variant/40 text-[13px] focus:bg-white focus:outline-none focus:ring-1 focus:ring-primary" type="date" name="from_date" value="<?= e($fromDate) ?>"></label>
      <label class="flex flex-col gap-1"><span class="text-[11px] font-medium text-on-surface-variant uppercase tracking-wide">Hari</span><input class="h-9 px-3 w-24 rounded-lg bg-surface-container-low/60 border border-outline-variant/40 text-[13px] focus:bg-white focus:outline-none focus:ring-1 focus:ring-primary" type="number" name="days" min="1" max="30" value="<?= e($days) ?>"></label>
      <label class="flex flex-col gap-1"><span class="text-[11px] font-medium text-on-surface-variant uppercase tracking-wide">Menu ID (opsional)</span><input class="h-9 px-3 w-28 rounded-lg bg-surface-container-low/60 border border-outline-variant/40 text-[13px] focus:bg-white focus:outline-none focus:ring-1 focus:ring-primary" type="number" name="menu_id" min="1" step="1" value="<?= e($menuId ?? '') ?>" placeholder="semua"></label>
      <button class="h-9 px-4 rounded-lg bg-primary hover:bg-tertiary-container text-on-primary text-[13px] font-medium" type="submit">Tampilkan</button>
      <div class="flex items-center gap-1 ml-auto"><?php foreach([7,14,30] as $r): $q=$quickBase; $q['days']=$r; ?><a href="?<?= e(http_build_query($q)) ?>" class="h-8 px-3 inline-flex items-center rounded-lg text-[12px] font-medium <?= $days===$r?'bg-primary text-on-primary':'bg-surface-container text-on-surface-variant hover:bg-surface-container-high' ?>"><?= e($r) ?> Hari</a><?php endforeach; ?></div>
    </form>
    <?php if($menuId!==null): $clr=['page'=>'forecasts','from_date'=>$fromDate,'days'=>$days]; ?><div class="mt-3 text-[12px] text-on-surface-variant">Filter menu ID <strong><?= e($menuId) ?></strong> aktif · <a href="?<?= e(http_build_query($clr)) ?>" class="text-primary font-medium hover:underline">Tampilkan semua menu</a></div><?php endif; ?>
  </section>

  <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
    <div class="bg-surface-container-lowest p-5 rounded-xl shadow-xs border border-outline-variant/20"><div class="flex items-center justify-between"><span class="text-[11px] text-on-surface-variant uppercase tracking-wide font-medium">Total Prakiraan</span><span class="material-symbols-outlined text-primary text-[20px]">lunch_dining</span></div><div class="mt-2 font-headline text-[28px] font-semibold tabular"><?= e(format_quantity($totalQuantity)) ?> <span class="text-[13px] font-normal text-on-surface-variant">porsi</span></div><div class="text-[11px] text-on-surface-variant mt-1"><?= e($days) ?> hari ke depan · ~<?= e($days>0?format_quantity($totalQuantity/$days):'0') ?>/hari</div><div class="w-full bg-surface-container-low h-1 rounded-full mt-2"><div class="bg-primary h-full rounded-full" style="width:70%"></div></div></div>
    <div class="bg-surface-container-lowest p-5 rounded-xl shadow-xs border border-outline-variant/20"><div class="flex items-center justify-between"><span class="text-[11px] text-on-surface-variant uppercase tracking-wide font-medium">Menu Tercakup</span><span class="material-symbols-outlined text-primary text-[20px]">layers</span></div><div class="mt-2 font-headline text-[28px] font-semibold tabular"><?= e(count($menuIds)) ?></div><div class="text-[11px] text-on-surface-variant mt-1">Menu dengan data forecast</div><div class="w-full bg-surface-container-low h-1 rounded-full mt-2"><div class="bg-secondary h-full rounded-full" style="width:100%"></div></div></div>
    <div class="bg-surface-container-lowest p-5 rounded-xl shadow-xs border border-outline-variant/20"><div class="flex items-center justify-between"><span class="text-[11px] text-on-surface-variant uppercase tracking-wide font-medium">Rata-rata Keyakinan</span><span class="material-symbols-outlined text-tertiary-container text-[20px]">query_stats</span></div><div class="mt-2 font-headline text-[28px] font-semibold tabular"><?= e(format_number($avgPct,1)) ?>%</div><div class="text-[11px] mt-1"><span class="inline-flex px-2 py-0.5 rounded-full bg-tertiary-fixed/30 text-tertiary font-semibold"><?= e(confidence_label($averageConfidence)) ?></span> · <?= e(count($items)) ?> baris</div><div class="w-full bg-surface-container-low h-1 rounded-full mt-2"><div class="bg-tertiary-container h-full rounded-full" style="width: <?= e(number_format($avgPct,1,'.','')) ?>%"></div></div></div>
    <div class="bg-surface-container-lowest p-5 rounded-xl shadow-xs border border-outline-variant/20"><div class="flex items-center justify-between"><span class="text-[11px] text-on-surface-variant uppercase tracking-wide font-medium">Rentang</span><span class="material-symbols-outlined text-primary text-[20px]">date_range</span></div><div class="mt-2 font-headline text-[18px] font-semibold"><?= e(format_date_id($fromDate)) ?></div><div class="text-[13px] text-on-surface-variant">s.d. <?= e(format_date_id($rangeEnd)) ?></div><div class="text-[11px] text-on-surface-variant mt-1"><?= e($days) ?> hari proyeksi</div></div>
  </section>

  <?php if ($items === []): ?>
    <section class="bg-surface-container-lowest rounded-xl p-10 shadow-xs border border-outline-variant/20 text-center"><span class="material-symbols-outlined text-[36px] text-outline">trending_up</span><p class="text-[13px] text-on-surface-variant mt-2">Belum ada data forecast untuk rentang ini. Coba ubah tanggal, perluas hari, atau hapus filter menu.</p></section>
  <?php else: ?>
    <section class="flex flex-col gap-4">
      <div class="flex items-center justify-between"><h2 class="font-headline text-[18px] font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-primary text-[20px]">calendar_view_week</span>Rincian Proyeksi Harian</h2><span class="text-[11px] text-on-surface-variant"><?= e(count($byDate)) ?> hari</span></div>
      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-4">
        <?php $idx=0; foreach ($byDate as $dateKey=>$dayItems): $idx++; $dq=0.0; $dc=0.0; foreach($dayItems as $di){$dq+=(float)($di['forecast_qty']??0); $dc+=(float)($di['confidence']??0);} $da=count($dayItems)>0?$dc/count($dayItems):0; $daPct=max(0,min(100,$da*100)); $dTone=confidence_tone($da); $dBadge=['positive'=>'bg-tertiary-fixed/30 text-tertiary','warning'=>'bg-amber-100 text-amber-800','danger'=>'bg-error-container text-error','neutral'=>'bg-surface-container text-on-surface'][$dTone]??'bg-surface-container'; ?>
          <article class="bg-surface-container-lowest rounded-xl p-4 shadow-xs border border-outline-variant/20 flex flex-col">
            <div class="flex items-center justify-between gap-2 mb-2"><span class="px-2 py-0.5 rounded bg-surface-container text-[11px] font-medium text-on-surface">H+<?= e($idx) ?></span><span class="px-2 py-0.5 rounded-full <?= $dBadge ?> text-[11px] font-medium">Keyakinan <?= e(format_number($daPct,0)) ?>%</span></div>
            <h3 class="font-headline text-[15px] font-semibold"><?= e(format_date_id($dateKey, true)) ?></h3>
            <div class="flex items-baseline gap-2 mt-0.5 mb-3"><span class="font-headline text-[22px] font-bold tabular text-primary"><?= e(format_quantity($dq)) ?></span><span class="text-[13px] text-on-surface-variant">porsi · <?= e(count($dayItems)) ?> menu</span></div>
            <div class="flex flex-col gap-2">
              <?php foreach ($dayItems as $it): $c=(float)($it['confidence']??0); $cp=max(0,min(100,$c*100)); $ct=confidence_tone($c); $bar=['positive'=>'bg-primary','warning'=>'bg-amber-500','danger'=>'bg-error','neutral'=>'bg-primary'][$ct]??'bg-primary'; ?>
                <div class="p-2 rounded-lg bg-surface-container-low">
                  <div class="flex items-center justify-between gap-2 text-[13px]"><span class="font-medium truncate" title="<?= e($it['menu_name']??'-') ?>"><?= e($it['menu_name']??'-') ?></span><span class="tabular font-semibold shrink-0"><?= e(format_quantity((float)($it['forecast_qty']??0))) ?></span></div>
                  <div class="flex items-center justify-between text-[11px] text-on-surface-variant mt-0.5"><span class="font-mono truncate"><?= e($it['menu_sku']??'-') ?></span><span><?= e(format_number($cp,0)) ?>%</span></div>
                  <div class="w-full bg-surface-container-high h-1.5 rounded-full overflow-hidden mt-1"><div class="<?= $bar ?> h-full rounded-full" style="width: <?= e(number_format($cp,1,'.','')) ?>%"></div></div>
                </div>
              <?php endforeach; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <div class="p-4 rounded-xl bg-surface-container-low border border-outline-variant/20 flex items-start gap-3 text-[12px] text-on-surface-variant"><span class="material-symbols-outlined text-primary text-[20px] shrink-0">info</span><div><strong class="text-on-surface">Catatan operasional:</strong> Prakiraan bersifat rekomendasi (model <code class="font-mono px-1 py-0.5 rounded bg-surface-container text-[11px] text-primary"><?= e($data['model_version'] ?? '-') ?></code>). Keputusan produksi/restock tetap memerlukan validasi operasional dan ketersediaan bahan (cek di <a href="?page=stocks" class="text-primary font-medium">Stok Bahan</a>).</div></div>
</div>
