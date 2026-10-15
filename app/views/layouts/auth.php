<?php $siteName = Brand::name(); $brandLogo = Brand::logoUrl(); ?><!doctype html>
<html lang="de" class="h-full bg-slate-50 antialiased">
<head><?php include APP_DIR . '/views/partials/head.php'; ?><meta name="robots" content="noindex, nofollow"></head>
<body class="min-h-full font-sans text-slate-800">
<div class="relative flex min-h-screen items-center justify-center overflow-hidden px-4 py-12">
  <div class="pointer-events-none absolute -top-40 left-1/2 -z-0 size-[640px] -translate-x-1/2 rounded-full bg-brand-200/40 blur-3xl"></div>
  <div class="relative w-full max-w-md">
    <div class="mb-8 flex flex-col items-center text-center">
      <?php if ($brandLogo !== ''): ?>
        <img src="<?= e($brandLogo) ?>" alt="" class="h-14 w-auto max-w-[220px] object-contain">
      <?php else: ?>
        <span class="grid size-14 place-items-center rounded-2xl bg-brand-600 text-white shadow-lg shadow-brand-600/30"><i class="bx bx-file text-3xl"></i></span>
      <?php endif; ?>
      <p class="mt-4 text-lg font-bold text-slate-900"><?= e($siteName) ?></p>
      <p class="text-sm text-slate-500">Administration</p>
    </div>
    <?= $content ?>
  </div>
</div>
<?php include APP_DIR . '/views/partials/flash.php'; ?>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?php if (!empty($scripts)) foreach ((array) $scripts as $s): ?><script src="<?= e(asset($s)) ?>" defer></script><?php endforeach; ?>
</body>
</html>
