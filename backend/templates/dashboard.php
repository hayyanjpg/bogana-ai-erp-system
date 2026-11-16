<?php
/** @var array<string, mixed> $data */
$orders = is_array($data['orders'] ?? null) ? $data['orders'] : [];
$shortOrders = is_array($data['short_orders'] ?? null) ? $data['short_orders'] : [];
$lowStock = is_array($data['low_stock'] ?? null) ? $data['low_stock'] : [];
$topMenus = is_array($data['top_menus'] ?? null) ? $data['top_menus'] : [];
$dateIso = (string) ($data['date'] ?? date('Y-m-d'));
$dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $dateIso) ?: new DateTimeImmutable('today');
$previousDate = $dateObject->modify('-1 day')->format('Y-m-d');
$nextDate = $dateObject->modify('+1 day')->format('Y-m-d');
$isToday = $dateIso === date('Y-m-d');
$statusKeys = ['received', 'parsed', 'confirmed', 'short', 'qc_hold', 'fulfilled', 'parse_failed', 'cancelled'];
$statusLabels = ['received'=>'Received','parsed'=>'Parsed','confirmed'=>'Confirmed','short'=>'Short','qc_hold'=>'QC Hold','fulfilled'=>'Fulfilled','parse_failed'=>'Parse Failed','cancelled'=>'Cancelled'];
$statusColors = ['received'=>'bg-slate-400','parsed'=>'bg-sky-400','confirmed'=>'bg-blue-600','short'=>'bg-rose-500','qc_hold'=>'bg-amber-500','fulfilled'=>'bg-primary-container','parse_failed'=>'bg-error','cancelled'=>'bg-slate-300'];
$totalOrders = max(0, (int) ($orders['total'] ?? 0));
$fulfilled = (int) ($orders['fulfilled'] ?? 0);
$shortCount = (int) ($orders['short'] ?? 0);
$qcHold = (int) ($orders['qc_hold'] ?? 0);
$attention = $shortCount + $qcHold;
$outOfStock = 0; $lowCountItems = 0;
foreach ($lowStock as $si) { if (($si['status'] ?? '') === 'out') $outOfStock++; else $lowCountItems++; }
$maxMenuQty = 0.0; foreach ($topMenus as $m) { $maxMenuQty = max($maxMenuQty, (float) ($m['ordered_qty'] ?? 0)); }
?>
<div class="flex flex-col gap-6">
  <section class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 bg-surface-container-lowest p-5 rounded-xl shadow-xs border border-outline-variant/20">
    <div class="min-w-0">
      <div class="flex items-center gap-2 mb-1">
        <span class="text-[11px] font-semibold tracking-wider text-primary uppercase">Operasional Harian B2B</span>
        <span class="w-1.5 h-1.5 rounded-full bg-outline-variant"></span>
        <span class="text-[11px] text-on-surface-variant">Ringkasan Produksi</span>
      </div>
      <h1 class="font-headline text-[22px] leading-7 font-semibold tracking-tight text-on-surface">Dashboard Operasional</h1>
      <p class="text-[13px] text-on-surface-variant mt-0.5">Ringkasan pesanan, nilai order, risiko stok, dan menu teratas untuk <?= e(format_date_id($dateIso, true)) ?>.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2.5">
      <form method="get" class="inline-flex items-center rounded-lg bg-surface-container-low p-1 gap-1">
        <input type="hidden" name="page" value="dashboard">
        <a href="?page=dashboard&amp;date=<?= e($previousDate) ?>" class="inline-flex items-center justify-center h-8 px-2 rounded-lg text-on-surface-variant hover:bg-surface-container hover:text-on-surface text-[12px] font-medium" title="Kemarin"><span class="material-symbols-outlined text-[16px]">chevron_left</span></a>
        <div class="relative flex items-center">
          <span class="material-symbols-outlined absolute left-2 text-[15px] text-outline pointer-events-none">calendar_month</span>
          <input type="date" name="date" value="<?= e($dateIso) ?>" class="h-8 pl-7 pr-2 rounded-lg bg-surface-container-lowest text-on-surface tabular text-[12px] font-medium focus:outline-none focus:ring-1 focus:ring-primary shadow-xs">
        </div>
        <a href="?page=dashboard&amp;date=<?= e($nextDate) ?>" class="inline-flex items-center justify-center h-8 px-2 rounded-lg text-on-surface-variant hover:bg-surface-container hover:text-on-surface text-[12px] font-medium" title="Besok"><span class="material-symbols-outlined text-[16px]">chevron_right</span></a>
        <button type="submit" class="h-8 px-3 rounded-lg bg-surface-container hover:bg-surface-container-high text-on-surface text-[13px] font-medium">Tampilkan</button>
      </form>
      <?php if (!$isToday): ?><a href="?page=dashboard" class="h-8 inline-flex items-center px-2.5 rounded-lg text-primary hover:bg-primary/10 text-[12px] font-medium">Hari Ini</a><?php endif; ?>
      <a href="?page=orders" class="inline-flex items-center gap-1.5 h-8 px-3.5 rounded-lg bg-primary-container hover:bg-primary text-on-primary text-[13px] font-medium shadow-xs"><span class="material-symbols-outlined text-[16px]">document_scanner</span><span>Urai Teks Order</span></a>
    </div>
  </section>

  <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
    <div class="bg-surface-container-lowest p-5 rounded-xl shadow-xs border border-outline-variant/20 flex flex-col justify-between">
      <div class="flex items-start justify-between"><span class="text-[11px] text-on-surface-variant font-medium tracking-wide">TOTAL PESANAN</span><span class="inline-flex w-7 h-7 rounded-lg bg-surface-container-low text-primary items-center justify-center"><span class="material-symbols-outlined text-[18px]">receipt_long</span></span></div>
      <div class="mt-3"><div class="flex items-baseline gap-2"><span class="font-headline text-[30px] leading-[38px] font-semibold tabular"><?= e($totalOrders) ?></span><span class="text-[13px] text-on-surface-variant font-medium">Pesanan</span></div>
      <div class="mt-2.5 flex items-center justify-between text-on-surface-variant bg-surface-container-low/70 px-2 py-1 rounded-lg"><span class="text-[11px] truncate"><?= e($fulfilled) ?> Selesai · <?= e($shortCount) ?> Short · <?= e((int)($orders['received']??0)+(int)($orders['parsed']??0)+(int)($orders['confirmed']??0)) ?> Proses</span></div></div>
    </div>
    <div class="bg-surface-container-lowest p-5 rounded-xl shadow-xs border border-outline-variant/20 flex flex-col justify-between">
      <div class="flex items-start justify-between"><span class="text-[11px] text-on-surface-variant font-medium tracking-wide">NILAI ORDER</span><span class="inline-flex w-7 h-7 rounded-lg bg-surface-container-low text-primary items-center justify-center"><span class="material-symbols-outlined text-[18px]">payments</span></span></div>
      <div class="mt-3"><div class="flex items-baseline gap-1"><span class="text-[13px] text-on-surface font-semibold">Rp</span><span class="font-headline text-[30px] leading-[38px] font-semibold tabular"><?= e(format_number((float) ($data['revenue_forecast'] ?? 0))) ?></span></div>
      <div class="mt-2.5 flex items-center justify-between text-on-surface-variant bg-surface-container-low/70 px-2 py-1 rounded-lg"><span class="text-[11px] truncate">Akumulasi confirmed + fulfilled</span></div></div>
    </div>
    <div class="bg-surface-container-lowest p-5 rounded-xl shadow-xs border border-outline-variant/20 flex flex-col justify-between">
      <div class="flex items-start justify-between"><span class="text-[11px] text-on-surface-variant font-medium tracking-wide">PERLU PERHATIAN</span><span class="inline-flex w-7 h-7 rounded-lg bg-amber-50 text-amber-700 items-center justify-center"><span class="material-symbols-outlined text-[18px]">warning</span></span></div>
      <div class="mt-3"><div class="flex items-baseline gap-2"><span class="font-headline text-[30px] leading-[38px] font-semibold tabular text-amber-800"><?= e($attention) ?></span><span class="text-[13px] text-amber-900 font-medium">Order</span></div>
      <div class="mt-2.5 flex items-center justify-between bg-amber-50/80 px-2 py-1 rounded-lg text-amber-900"><span class="text-[11px] truncate"><?= e($shortCount) ?> Short · <?= e($qcHold) ?> QC Hold</span><?php if($attention>0): ?><span class="text-[10px] bg-white px-1.5 py-0.5 rounded text-amber-800 font-semibold">URGENT</span><?php endif; ?></div></div>
    </div>
    <div class="bg-surface-container-lowest p-5 rounded-xl shadow-xs border border-outline-variant/20 flex flex-col justify-between">
      <div class="flex items-start justify-between"><span class="text-[11px] text-on-surface-variant font-medium tracking-wide">STOK KRITIS</span><span class="inline-flex w-7 h-7 rounded-lg bg-error-container text-error items-center justify-center"><span class="material-symbols-outlined text-[18px]">emergency_home</span></span></div>
      <div class="mt-3"><div class="flex items-baseline gap-2"><span class="font-headline text-[30px] leading-[38px] font-semibold tabular text-error"><?= e(count($lowStock)) ?></span><span class="text-[13px] text-error font-medium">Bahan</span></div>
      <div class="mt-2.5 flex items-center justify-between bg-error-container/40 px-2 py-1 rounded-lg text-on-error-container"><span class="text-[11px] truncate"><?= e($outOfStock) ?> Habis · <?= e(max(0,count($lowStock)-$outOfStock)) ?> Menipis</span><?php if(count($lowStock)>0): ?><span class="text-[10px] bg-white px-1.5 py-0.5 rounded text-error font-semibold">ACTION</span><?php endif; ?></div></div>
    </div>
  </section>

  <section class="bg-surface-container-lowest p-5 rounded-xl shadow-xs border border-outline-variant/20">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1 mb-3">
      <div class="flex items-center gap-2"><span class="material-symbols-outlined text-primary text-[20px]">sync_alt</span><h2 class="font-headline text-[15px] font-semibold">Distribusi Status Pesanan</h2></div>
      <span class="text-[12px] text-on-surface-variant">Total <?= e($totalOrders) ?> pesanan tercatat</span>
    </div>
    <?php if ($totalOrders === 0): ?>
      <div class="rounded-lg bg-surface-container-low p-6 text-center text-[13px] text-on-surface-variant">Belum ada order pada tanggal ini. <a href="?page=orders" class="text-primary font-medium">Buka Order &amp; Parser</a>.</div>
    <?php else: ?>
      <div class="w-full h-3 rounded-full bg-surface-container-high overflow-hidden flex p-0.5 gap-0.5 mb-3">
        <?php foreach ($statusKeys as $sk): $c=(int)($orders[$sk]??0); if($c<=0) continue; $w=($c/$totalOrders)*100; ?>
          <div class="h-full rounded-sm <?= e($statusColors[$sk]) ?>" style="width: <?= e(number_format($w,2,'.','')) ?>%" title="<?= e($statusLabels[$sk].': '.$c) ?>"></div>
        <?php endforeach; ?>
      </div>
      <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-2">
        <?php foreach ($statusKeys as $sk): $c=(int)($orders[$sk]??0); ?>
          <div class="flex items-center gap-2 px-2.5 py-1.5 rounded-lg bg-surface-container-low">
            <span class="w-2.5 h-2.5 rounded-full <?= e($statusColors[$sk]) ?> shrink-0"></span>
            <div class="flex flex-col min-w-0"><span class="text-[11px] text-on-surface-variant truncate"><?= e($statusLabels[$sk]) ?></span><span class="tabular text-[13px] font-semibold text-on-surface"><?= e($c) ?></span></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="bg-surface-container-lowest p-5 rounded-xl shadow-xs border border-outline-variant/20 flex flex-col">
      <div class="flex items-center justify-between pb-3">
        <div class="flex items-center gap-2"><?php if($attention>0): ?><span class="w-2 h-2 rounded-full bg-error animate-pulse"></span><?php endif; ?><h2 class="font-headline text-[15px] font-semibold">Order Butuh Perhatian</h2></div>
        <span class="px-2 py-0.5 rounded-full <?= $attention>0?'bg-error-container text-on-error-container':'bg-surface-container text-on-surface-variant' ?> text-[11px] font-semibold"><?= e(count($shortOrders)) ?> Short</span>
      </div>
      <?php if ($shortOrders === []): ?>
        <div class="flex-1 rounded-lg bg-surface-container-low p-6 text-center text-[13px] text-on-surface-variant">Tidak ada order short. Seluruh order aman diproses.</div>
      <?php else: ?>
        <div class="flex flex-col gap-2.5">
          <?php foreach ($shortOrders as $order): ?>
            <div class="p-3 rounded-lg bg-surface-container-low hover:bg-surface-container transition-colors flex flex-col sm:flex-row sm:items-center justify-between gap-3">
              <div class="flex items-start gap-3 min-w-0">
                <div class="w-9 h-9 rounded-lg bg-primary-container text-on-primary text-[13px] font-semibold flex items-center justify-center shrink-0"><?= e(initials($order['customer_name'] ?? '')) ?></div>
                <div class="min-w-0">
                  <div class="flex items-center gap-2 flex-wrap"><span class="text-[13px] font-semibold text-on-surface truncate"><?= e($order['customer_name'] ?? '-') ?></span><span class="text-[11px] font-mono text-on-surface-variant"><?= e($order['order_code'] ?? '-') ?></span></div>
                  <div class="flex items-center gap-2 mt-1"><span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-rose-100 text-rose-800">Status: Short</span></div>
                </div>
              </div>
              <a href="?page=orders&amp;order_id=<?= e((int)($order['order_id'] ?? 0)) ?>&amp;focus=check" class="shrink-0 px-2.5 py-1.5 rounded-lg bg-surface-container-lowest hover:bg-primary hover:text-on-primary text-primary text-[12px] font-medium shadow-xs transition-all">Cek Stok</a>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div class="pt-3 mt-3 flex items-center justify-between text-on-surface-variant text-[11px] border-t border-outline-variant/20"><span>Prioritaskan order short sebelum slot kirim</span><a class="text-primary hover:underline font-medium inline-flex items-center gap-0.5" href="?page=orders#check">Buka cek stok <span class="material-symbols-outlined text-[14px]">arrow_forward</span></a></div>
    </div>

    <div class="bg-surface-container-lowest p-5 rounded-xl shadow-xs border border-outline-variant/20 flex flex-col">
      <div class="flex items-center justify-between pb-3">
        <div class="flex items-center gap-2"><span class="material-symbols-outlined text-error text-[20px]">inventory_2</span><h2 class="font-headline text-[15px] font-semibold">Stok Menipis &amp; Habis</h2></div>
        <a href="?page=stocks&amp;low_only=1" class="text-[12px] text-primary font-medium hover:underline">Lihat semua</a>
      </div>
      <?php if ($lowStock === []): ?>
        <div class="flex-1 rounded-lg bg-surface-container-low p-6 text-center text-[13px] text-on-surface-variant">Semua bahan berada di atas titik reorder.</div>
      <?php else: ?>
        <div class="flex flex-col gap-3">
          <?php foreach (array_slice($lowStock, 0, 5) as $item): $tone = status_tone($item['status'] ?? ''); $percent = stock_percent((float)($item['stock_on_hand']??0),(float)($item['reorder_point']??0)); $isOut = ($item['status']??'')==='out'; $bar=$isOut?'bg-error':($tone==='warning'?'bg-amber-500':'bg-primary'); $badge=$isOut?'bg-error-container text-error':'bg-amber-100 text-amber-800'; ?>
            <div class="p-3 rounded-lg bg-surface-container-low flex flex-col gap-2">
              <div class="flex items-center justify-between gap-2"><div class="flex items-center gap-2 min-w-0"><span class="text-[13px] font-semibold text-on-surface truncate"><?= e($item['name'] ?? '-') ?></span><span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase <?= $badge ?>"><?= e(status_label($item['status'] ?? '')) ?></span></div><a href="?page=stocks&amp;sku=<?= e(rawurlencode((string)($item['sku']??''))) ?>" class="text-primary text-[12px] font-medium shrink-0">Detail</a></div>
              <div class="w-full bg-surface-container-high h-2 rounded-full overflow-hidden"><div class="<?= $bar ?> h-full rounded-full" style="width: <?= e(number_format(max(0,min(100,$percent)),1,'.','')) ?>%"></div></div>
              <div class="flex items-center justify-between text-on-surface-variant text-[11px]"><span>Tersedia: <strong class="tabular <?= $isOut?'text-error':'text-amber-800' ?>"><?= e(format_quantity((float)($item['stock_on_hand']??0))) ?></strong></span><span>Reorder: <strong class="tabular text-on-surface"><?= e(format_quantity((float)($item['reorder_point']??0))) ?></strong></span></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div class="pt-3 mt-3 flex items-center justify-between text-on-surface-variant text-[11px] border-t border-outline-variant/20"><span>ROP mengacu pada reorder point bahan</span><a class="text-primary hover:underline font-medium inline-flex items-center gap-0.5" href="?page=stocks">Kelola stok <span class="material-symbols-outlined text-[14px]">arrow_forward</span></a></div>
    </div>
  </section>

  <section class="bg-surface-container-lowest rounded-xl shadow-xs border border-outline-variant/20 overflow-hidden">
    <div class="p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
      <div class="flex items-center gap-2"><span class="material-symbols-outlined text-primary text-[20px]">restaurant_menu</span><div><h2 class="font-headline text-[15px] font-semibold">Menu Terlaris Hari Ini</h2><p class="text-[12px] text-on-surface-variant">Top 5 menu berdasarkan volume porsi</p></div></div>
      <span class="px-2 py-1 rounded bg-surface-container-low text-[11px] text-on-surface-variant font-medium">Sumber: <?= e($data['source'] ?? '-') ?></span>
    </div>
    <?php if ($topMenus === []): ?>
      <div class="px-5 pb-5"><div class="rounded-lg bg-surface-container-low p-6 text-center text-[13px] text-on-surface-variant">Belum ada item terjual pada tanggal ini.</div></div>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-left">
          <thead><tr class="bg-surface-container-low text-on-surface-variant text-[11px] uppercase tracking-wider"><th class="py-2.5 px-4 font-semibold text-center w-12">#</th><th class="py-2.5 px-4 font-semibold">Menu &amp; SKU</th><th class="py-2.5 px-4 font-semibold text-right">Volume Porsi</th><th class="py-2.5 px-4 font-semibold">Beban Produksi</th></tr></thead>
          <tbody class="text-[13px] text-on-surface">
            <?php foreach (array_slice($topMenus, 0, 5) as $i => $menu): $qty=(float)($menu['ordered_qty']??0); $w=$maxMenuQty>0?($qty/$maxMenuQty)*100:0; ?>
              <tr class="hover:bg-surface-container-low/60 transition-colors">
                <td class="py-3 px-4 text-center tabular font-semibold <?= $i===0?'text-primary':'text-on-surface-variant' ?>"><?= e(str_pad((string)($i+1),2,'0',STR_PAD_LEFT)) ?></td>
                <td class="py-3 px-4"><div class="flex items-center gap-3"><div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center text-primary shrink-0"><span class="material-symbols-outlined text-[18px]">lunch_dining</span></div><div class="min-w-0"><div class="font-semibold truncate"><?= e($menu['menu_name'] ?? '-') ?></div><div class="text-[11px] font-mono text-on-surface-variant"><?= e($menu['menu_sku'] ?? '-') ?></div></div></div></td>
                <td class="py-3 px-4 text-right tabular font-bold"><?= e(format_quantity($qty)) ?> <span class="text-on-surface-variant text-[11px] font-normal">porsi</span></td>
                <td class="py-3 px-4"><div class="flex items-center gap-2"><div class="w-full bg-surface-container-high h-2 rounded-full overflow-hidden max-w-[160px]"><div class="bg-primary-container h-full rounded-full" style="width: <?= e(number_format($w,1,'.','')) ?>%"></div></div><span class="tabular text-[12px] text-on-surface-variant font-medium"><?= e(format_number($w,0)) ?>%</span></div></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
    <div class="px-5 py-3 bg-surface-container-low/50 flex flex-col sm:flex-row sm:items-center justify-between text-on-surface-variant text-[11px] gap-2 border-t border-outline-variant/20">
      <span>Diperbarui <?= e(format_datetime_id($data['generated_at'] ?? null)) ?><?= ($data['source'] ?? '') === 'redis' ? ' · dilayani cache Redis' : '' ?></span>
      <span>Data dari AI Service /v1/dashboard/summary</span>
    </div>
  </section>
</div>
