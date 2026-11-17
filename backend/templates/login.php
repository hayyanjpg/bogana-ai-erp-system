<?php
/** @var string $title */
/** @var string|null $error */
/** @var string $username */

$error = $error ?? null;
$username = $username ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="robots" content="noindex">
    <title><?= e($title ?? 'Masuk') ?> — Bogana AI ERP</title>
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
              surface:'#f8f9ff','surface-container-lowest':'#ffffff','surface-container-low':'#eff4ff','surface-container':'#e5eeff',
              'on-surface':'#0b1c30','on-surface-variant':'#3e4947','outline':'#6e7977','outline-variant':'#bdc9c6',
              primary:'#005c55','primary-container':'#0f766e','on-primary':'#ffffff',
              error:'#ba1a1a','error-container':'#ffdad6','on-error-container':'#93000a'
            },
            fontFamily: { headline:['"Plus Jakarta Sans"','Inter','sans-serif'], body:['Inter','sans-serif'] }
          }
        }
      }
    </script>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body class="min-h-screen bg-surface text-on-surface font-body antialiased">
<main class="min-h-screen flex items-center justify-center px-4 py-10">
  <div class="w-full max-w-[420px]">
    <div class="flex items-center gap-3 mb-6">
      <div class="h-10 w-10 rounded-xl bg-primary flex items-center justify-center text-on-primary font-headline font-bold text-[18px]">B</div>
      <div class="flex flex-col">
        <span class="font-headline text-[17px] font-semibold tracking-tight leading-tight">Bogana AI ERP</span>
        <span class="text-[11px] text-on-surface-variant leading-none mt-1">Sistem Operasional Katering B2B</span>
      </div>
    </div>

    <section class="rounded-xl bg-surface-container-lowest p-6 shadow-xs border border-outline-variant/30">
      <h1 class="font-headline text-[22px] font-semibold">Masuk ke dashboard</h1>
      <p class="text-[13px] text-on-surface-variant mt-1">Gunakan akun operasional untuk mengelola order dan stok.</p>

      <?php if ($error !== null): ?>
        <div role="alert" class="mt-4 rounded-lg bg-error-container text-on-error-container px-3 py-2 text-[13px] border border-error/20">
          <?= e($error) ?>
        </div>
      <?php endif; ?>

      <form method="post" action="?page=login" class="mt-5 space-y-4" data-submit-lock>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <div>
          <label for="username" class="block text-[12px] font-medium text-on-surface mb-1">Username</label>
          <input id="username" name="username" type="text" required maxlength="64" autocomplete="username"
                 value="<?= e($username) ?>"
                 class="w-full h-10 px-3 rounded-lg border border-outline-variant/60 bg-surface-container-lowest text-[14px] focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
        </div>

        <div>
          <label for="password" class="block text-[12px] font-medium text-on-surface mb-1">Password</label>
          <input id="password" name="password" type="password" required maxlength="200" autocomplete="current-password"
                 class="w-full h-10 px-3 rounded-lg border border-outline-variant/60 bg-surface-container-lowest text-[14px] focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
        </div>

        <button type="submit" class="w-full h-10 rounded-lg bg-primary hover:bg-primary-container text-on-primary text-[14px] font-medium transition-colors">
          Masuk
        </button>
      </form>
    </section>

    <p class="text-[11px] text-on-surface-variant text-center mt-4">
      Sesi berakhir otomatis. Jangan bagikan kredensial dashboard.
    </p>
  </div>
</main>
<script>
(function(){
  document.querySelectorAll('form[data-submit-lock]').forEach(function(f){
    f.addEventListener('submit',function(){var b=f.querySelector('button[type="submit"]');if(b){b.disabled=true;b.classList.add('opacity-70');}});
  });
})();
</script>
</body>
</html>
