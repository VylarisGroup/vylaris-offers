<?php
$customer = Auth::customer();
$logo = Brand::logoUrl();
$pwaApp = preg_match('#/portal(/|$)#', (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH)) ? 'portal' : null;
?><!doctype html>
<html lang="de" class="h-full bg-slate-50 antialiased">
<head>
<?php include APP_DIR . '/views/partials/head.php'; ?>
<meta name="robots" content="noindex, nofollow">
</head>
<body class="min-h-full font-sans text-slate-800 flex flex-col">
<div class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[420px] bg-gradient-to-b from-brand-100/60 via-brand-50/30 to-transparent print:hidden"></div>
<header class="print:hidden">
  <div class="mx-auto flex h-20 max-w-5xl items-center justify-between gap-4 px-4 sm:px-6">
    <a href="<?= e(url('portal')) ?>" class="flex items-center gap-3">
      <?php if ($logo): ?>
        <img src="<?= e($logo) ?>" alt="<?= e(Settings::get('company_name')) ?>" class="h-9 w-auto max-w-[180px] object-contain">
      <?php else: ?>
        <span class="grid size-10 place-items-center rounded-xl bg-brand-600 text-white shadow-sm shadow-brand-600/30"><i class="bx bx-briefcase-alt-2 text-xl"></i></span>
        <span class="text-base font-bold tracking-tight text-slate-900"><?= e(Settings::get('company_name')) ?></span>
      <?php endif; ?>
    </a>
    <?php if ($customer): ?>
      <div class="flex items-center gap-2">
        <?php if ($pwaApp && Pwa::enabled()): ?><button type="button" hidden data-pwa-install class="btn btn-ghost btn-sm"><i class="bx bx-download"></i><span class="hidden sm:inline">App installieren</span></button><?php endif; ?>
        <a href="<?= e(url('portal')) ?>" class="btn btn-ghost btn-sm hidden sm:inline-flex"><i class="bx bx-home-alt"></i>Übersicht</a>
        <form method="post" action="<?= e(url('portal/logout')) ?>"><?= csrf_field() ?>
          <button class="btn btn-secondary btn-sm"><i class="bx bx-log-out"></i><span class="hidden sm:inline">Abmelden</span></button>
        </form>
      </div>
    <?php else: ?>
      <a href="<?= e(url('portal')) ?>" class="btn btn-secondary btn-sm"><i class="bx bx-user-circle"></i>Kundenportal</a>
    <?php endif; ?>
  </div>
</header>
<?php if ($customer && $pwaApp === 'portal'):
    $pp = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $unreadMsg = PortalMessage::unreadForCustomer((int) $customer['id']);
    $nav = [['portal', 'Übersicht', 'bx-home-alt', 0], ['portal/messages', 'Nachrichten', 'bx-message-square-dots', $unreadMsg], ['portal/documents', 'Dokumente', 'bx-folder-open', 0], ['portal/profile', 'Meine Angaben', 'bx-user-circle', 0]]; ?>
<nav class="print:hidden" aria-label="Kundenportal"><div class="mx-auto flex max-w-5xl gap-1 overflow-x-auto px-4 pb-1 sm:px-6">
  <?php foreach ($nav as [$h, $l, $ic, $n]):
      $on = $h === 'portal' ? (rtrim($pp, '/') === rtrim(base_path() . '/portal', '/') || str_contains($pp, '/portal/orders')) : str_contains($pp, '/' . $h); ?>
    <a href="<?= e(url($h)) ?>" class="tab shrink-0<?= $on ? ' is-active' : '' ?>"<?= $on ? ' aria-current="page"' : '' ?>><i class="bx <?= $ic ?>"></i><?= e($l) ?><?php if ($n): ?><span class="ml-1 rounded-full bg-brand-600 px-1.5 py-0.5 text-[10px] font-semibold leading-none text-white"><?= (int) $n ?></span><?php endif; ?></a>
  <?php endforeach; ?>
</div></nav>
<?php endif; ?>
<main id="main" class="mx-auto w-full max-w-5xl flex-1 px-4 pb-16 pt-2 sm:px-6 print:p-0 print:max-w-none">
  <?= $content ?>
</main>
<footer class="border-t border-slate-200/80 bg-white/60 print:hidden">
  <div class="mx-auto flex max-w-5xl flex-col gap-2 px-4 py-6 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6">
    <p><?= e(Settings::get('company_name')) ?><?php foreach ([Settings::get('company_street'), trim(Settings::get('company_zip') . ' ' . Settings::get('company_city'))] as $p) if ($p) echo ' · ' . e($p); ?></p>
    <p class="flex flex-wrap gap-x-4 gap-y-1">
      <?php if (Settings::companyEmail()): ?><a class="hover:text-slate-800 transition" href="mailto:<?= e(Settings::companyEmail()) ?>"><i class="bx bx-envelope align-middle"></i> <?= e(Settings::companyEmail()) ?></a><?php endif; ?>
      <a class="hover:text-slate-800 transition" href="<?= e(url('datenschutz')) ?>"><i class="bx bx-shield-alt-2 align-middle"></i> Datenschutz</a>
      <?php if (Settings::get('company_phone')): ?><a class="hover:text-slate-800 transition" href="tel:<?= e(preg_replace('/[^+\d]/', '', (string) Settings::get('company_phone'))) ?>"><i class="bx bx-phone align-middle"></i> <?= e(Settings::get('company_phone')) ?></a><?php endif; ?>
    </p>
  </div>
</footer>
<?php include APP_DIR . '/views/partials/flash.php'; ?>
<?php include APP_DIR . '/views/partials/confirm-modal.php'; ?>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?php if ($pwaApp && Pwa::enabled()): ?><script src="<?= e(asset('js/pwa.js')) ?>" defer></script><?php endif; ?>
</body>
</html>
