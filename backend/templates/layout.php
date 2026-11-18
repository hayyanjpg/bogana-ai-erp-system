<?php
/** @var string $content */
/** @var string $title */

$active = match ($title) {
    'Dashboard' => 'dashboard',
    'Order & Parser' => 'orders',
    'Stok Bahan' => 'stocks',
    'Forecast' => 'forecasts',
    'Status Sistem' => 'health',
    default => '',
};

$nav = [
    ['label' => 'Operasional', 'items' => [
        ['key' => 'dashboard', 'href' => '?page=dashboard', 'label' => 'Dashboard', 'icon' => 'grid_view'],
        ['key' => 'orders', 'href' => '?page=orders', 'label' => 'Order & Parser', 'icon' => 'receipt_long'],
    ]],
    ['label' => 'Inventori & Rencana', 'items' => [
        ['key' => 'stocks', 'href' => '?page=stocks', 'label' => 'Stok Bahan', 'icon' => 'inventory_2'],
        ['key' => 'forecasts', 'href' => '?page=forecasts', 'label' => 'Forecast Permintaan', 'icon' => 'trending_up'],
    ]],
    ['label' => 'Sistem', 'items' => [
        ['key' => 'health', 'href' => '?page=health', 'label' => 'Status Sistem', 'icon' => 'monitor_heart'],
    ]],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title><?= e($title) ?> — Bogana AI ERP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            colors: {
              surface:'#f8f9ff','surface-dim':'#cbdbf5','surface-container-lowest':'#ffffff','surface-container-low':'#eff4ff','surface-container':'#e5eeff','surface-container-high':'#dce9ff','surface-container-highest':'#d3e4fe',
              'on-surface':'#0b1c30','on-surface-variant':'#3e4947','outline':'#6e7977','outline-variant':'#bdc9c6',
              primary:'#005c55','primary-container':'#0f766e','on-primary':'#ffffff','on-primary-container':'#a3faef',
              secondary:'#515f74','secondary-container':'#d5e3fd','on-secondary-fixed-variant':'#57657b',
              tertiary:'#005c54','tertiary-container':'#00776d','tertiary-fixed':'#89f5e7',
              error:'#ba1a1a','error-container':'#ffdad6','on-error-container':'#93000a','inverse-surface':'#213145'
            },
            fontFamily: { headline:['"Plus Jakarta Sans"','Inter','sans-serif'], body:['Inter','sans-serif'] },
            borderRadius: { DEFAULT:'0.25rem', lg:'0.5rem', xl:'0.75rem', full:'9999px' },
            boxShadow: { xs:'0 1px 2px rgba(15,23,42,.05)' }
          }
        }
      }
    </script>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body class="bg-surface text-on-surface font-body antialiased">
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-[60] focus:bg-on-surface focus:text-white focus:px-3 focus:py-2 focus:rounded">Lewati ke konten</a>
<aside class="app-sidebar fixed left-0 top-0 h-full w-[260px] bg-surface-container-lowest border-r border-outline-variant/40 z-50 flex flex-col justify-between select-none transition-transform duration-200">
  <div class="flex flex-col min-h-0">
    <div class="h-14 px-4 flex items-center gap-3 border-b border-outline-variant/30">
      <div class="h-8 w-8 rounded-lg bg-primary flex items-center justify-center text-on-primary font-headline font-bold">B</div>
      <div class="flex flex-col min-w-0">
        <span class="font-headline text-[15px] font-semibold tracking-tight leading-tight truncate">Bogana AI ERP</span>
        <span class="text-[10px] text-on-surface-variant leading-none mt-0.5 truncate">Sistem Operasional Katering B2B</span>
      </div>
    </div>
    <div class="px-3 py-3">
      <div class="px-3 py-1.5 rounded-lg bg-surface-container-low border border-outline-variant/30 flex items-center justify-between text-on-surface-variant">
        <div class="flex items-center gap-2 min-w-0"><span class="material-symbols-outlined text-[16px] text-primary">countertops</span><span class="text-[12px] text-on-surface font-medium truncate">Dapur Pusat - Senopati</span></div>
        <span class="material-symbols-outlined text-[14px]">unfold_more</span>
      </div>
    </div>
    <nav class="flex-1 overflow-y-auto flex flex-col gap-4 px-3 pb-3">
      <?php foreach ($nav as $group): ?>
        <div class="flex flex-col gap-0.5">
          <div class="px-2 py-1 text-[11px] text-outline uppercase tracking-wider font-medium"><?= e($group['label']) ?></div>
          <?php foreach ($group['items'] as $item): $is = $active === $item['key']; ?>
            <a href="<?= e($item['href']) ?>" <?= $is ? 'aria-current="page"' : '' ?> class="flex items-center gap-2.5 px-2.5 py-1.5 rounded-lg transition-colors text-[13px] <?= $is ? 'bg-primary-container text-on-primary font-medium shadow-xs' : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface' ?>">
              <span class="material-symbols-outlined text-[18px]"><?= e($item['icon']) ?></span><span><?= e($item['label']) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </nav>
  </div>
  <div class="p-3 border-t border-outline-variant/30 flex flex-col gap-2 bg-surface-container-lowest">
    <a href="?page=health" class="flex items-center justify-between px-2 py-1 rounded bg-surface-container-low border border-outline-variant/20 hover:border-primary/40 transition-colors">
      <div class="flex items-center gap-1.5"><span class="relative flex h-2 w-2"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-primary"></span></span><span class="text-[11px] text-on-surface font-medium">FastAPI</span></div>
      <span class="text-[10px] text-primary font-medium uppercase">Terhubung</span>
    </a>
    <div class="flex items-center justify-between text-on-surface-variant px-2">
      <div class="flex items-center gap-1.5"><span class="material-symbols-outlined text-[14px]">schedule</span><span class="text-[11px]">Shift WIB</span></div>
      <span class="tabular text-[13px] text-on-surface font-medium" data-clock><?= e(date('H:i')) ?> WIB</span>
    </div>
    <div class="pt-1 border-t border-outline-variant/20 flex items-center justify-between">
      <div class="flex items-center gap-2 min-w-0"><div class="w-7 h-7 rounded-full bg-primary flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-on-primary text-[16px]">person</span></div><div class="flex flex-col min-w-0"><span class="text-[11px] text-on-surface font-medium truncate"><?= e($currentUser ?? 'Tamu') ?></span><span class="text-[10px] text-on-surface-variant truncate"><?= ($accessMode ?? 'open') === 'required' ? 'Admin Operasional' : 'Akses publik' ?></span></div></div>
      <?php if ($canLogout ?? false): ?>
        <form method="post" action="?page=logout" class="inline-flex">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <button type="submit" class="text-on-surface-variant hover:text-error transition-colors" title="Keluar" aria-label="Keluar">
            <span class="material-symbols-outlined text-[16px]">logout</span>
          </button>
        </form>
      <?php else: ?>
        <span class="material-symbols-outlined text-on-surface-variant text-[16px]">settings</span>
      <?php endif; ?>
    </div>
  </div>
</aside>
<div class="app-shell pl-[260px] min-h-screen flex flex-col">
  <header class="fixed top-0 left-[260px] right-0 h-14 bg-surface-container-lowest/90 backdrop-blur-md border-b border-outline-variant/40 z-40">
    <div class="h-full w-full px-6 flex items-center justify-between gap-4">
      <div class="flex items-center gap-3 min-w-0">
        <button type="button" class="lg:hidden inline-flex items-center justify-center w-8 h-8 rounded hover:bg-surface-container" data-sidebar-toggle aria-label="Buka menu"><span class="material-symbols-outlined text-[20px]">menu</span></button>
        <div class="flex items-center gap-2 text-[13px] text-on-surface-variant truncate"><span class="hover:text-on-surface">Bogana ERP</span><span class="text-outline-variant">/</span><span class="text-on-surface font-medium truncate"><?= e($title) ?></span></div>
        <span class="hidden md:inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-surface-container border border-outline-variant/30 text-on-surface text-[11px]"><span class="material-symbols-outlined text-[14px] text-primary">calendar_today</span><span><?= e(format_date_id(date('Y-m-d'), true)) ?></span></span>
      </div>
      <div class="flex items-center gap-2">
        <a href="?page=health" class="hidden sm:flex items-center gap-1.5 px-2 py-1 rounded-lg bg-surface-container-low border border-outline-variant/30 text-on-surface-variant text-[11px]"><span class="h-1.5 w-1.5 rounded-full bg-primary"></span><span>PostgreSQL Aktif</span></a>
        <a href="?page=orders" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-primary hover:bg-tertiary-container text-on-primary text-[13px] font-medium transition-colors shadow-xs"><span class="material-symbols-outlined text-[16px]">add</span><span class="hidden sm:inline">Teks Order Baru</span></a>
      </div>
    </div>
  </header>
  <main id="main" class="relative pt-14 flex-1">
    <div class="w-full px-6 py-6">
      <?= $content ?>
    </div>
    <footer class="px-6 pb-6 text-[11px] text-on-surface-variant flex flex-wrap items-center justify-between gap-2">
      <span>Bogana AI ERP System · Nasi Bogana B2B</span>
      <span>Data: FastAPI · PostgreSQL · Redis</span>
    </footer>
  </main>
</div>
<script>
(function(){
  var clock=document.querySelector('[data-clock]');
  if(clock){var tick=function(){try{clock.textContent=new Intl.DateTimeFormat('id-ID',{hour:'2-digit',minute:'2-digit',hour12:false,timeZone:'Asia/Jakarta'}).format(new Date())+' WIB';}catch(e){}};tick();setInterval(tick,30000);} 
  document.querySelectorAll('form[data-submit-lock]').forEach(function(f){f.addEventListener('submit',function(){var b=f.querySelector('button[type="submit"]');if(b){b.disabled=true;b.classList.add('opacity-70');}});});
  document.querySelectorAll('[data-count-for]').forEach(function(c){var el=document.getElementById(c.getAttribute('data-count-for'));if(!el)return;var max=Number(el.getAttribute('maxlength')||0);var u=function(){c.textContent=el.value.length+(max?' / '+max:'');};el.addEventListener('input',u);u();});
  document.querySelectorAll('[data-fill-target]').forEach(function(b){b.addEventListener('click',function(){var el=document.getElementById(b.getAttribute('data-fill-target'));if(!el)return;el.value=b.getAttribute('data-fill-value')||'';el.dispatchEvent(new Event('input'));el.focus();});});
  var auto=document.querySelector('[data-auto-refresh]'); if(auto){var key='bogana:auto-refresh';var ms=Number(auto.getAttribute('data-auto-refresh'))||30000;var apply=function(){clearTimeout(window.__boganaRefresh);if(auto.checked){window.__boganaRefresh=setTimeout(function(){location.reload();},ms);}};try{auto.checked=localStorage.getItem(key)==='1';}catch(e){}auto.addEventListener('change',function(){try{localStorage.setItem(key,auto.checked?'1':'0');}catch(e){}apply();});apply();}
  document.querySelectorAll('[data-copy]').forEach(function(b){b.addEventListener('click',function(){var v=b.getAttribute('data-copy')||'';if(!navigator.clipboard)return;navigator.clipboard.writeText(v).then(function(){var p=b.textContent;b.textContent='Tersalin';setTimeout(function(){b.textContent=p;},1400);});});});
  var tog=document.querySelector('[data-sidebar-toggle]'); if(tog){tog.addEventListener('click',function(){document.querySelector('.app-sidebar').classList.toggle('is-open');});}
})();
</script>
</body>
</html>
