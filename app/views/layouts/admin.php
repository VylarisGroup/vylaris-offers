<?php
$me = Auth::user();
$path = Router::currentPath();
$active = static function (string $prefix, bool $exact = false) use ($path): string {
    $hit = $exact ? $path === $prefix : ($path === $prefix || str_starts_with($path, $prefix . '/'));
    return $hit ? ' is-active' : '';
};
$newOrders = (int) DB::val("SELECT COUNT(*) FROM orders WHERE status = 'new'");
$newInq = InquiryService::newCount();
$openInv = (int) DB::val("SELECT COUNT(*) FROM invoices WHERE status = 'sent' AND due_date < CURDATE()");
$nav = [
    ['admin', 'Dashboard', 'bx-grid-alt', true, null],
    ['admin/offers', 'Offerten', 'bx-file', false, null],
    ['admin/inquiries', 'Anfragen', 'bx-envelope-open', false, $newInq ?: null],
    ['admin/orders', 'Aufträge', 'bx-package', false, $newOrders ?: null],
    ['admin/invoices', 'Rechnungen', 'bx-receipt', false, $openInv ?: null],
    ['admin/subscriptions', 'Abos', 'bx-sync', false, null],
    ['admin/customers', 'Kunden', 'bx-group', false, null],
];
$lic = License::status();
$nav = array_values(array_filter($nav, static fn ($n) => match ($n[0]) {
    'admin/invoices' => License::has('invoices'), 'admin/subscriptions' => License::has('subscriptions'), 'admin/inquiries' => License::has('website'), default => true,
}));
$catalog = [
    ['admin/areas', 'Produktbereiche', 'bx-category'],
    ['admin/templates', 'Positionsvorlagen', 'bx-bookmarks'],
    ['admin/taxes', 'Steuervorlagen', 'bx-receipt'],
    ['admin/shipping', 'Versandarten', 'bx-car'],
];
$system = [];
if ($me && $me['role'] === 'admin') {
    // Heikle Bereiche (Benutzer, Einstellungen, Steuern, Mail-Protokoll) sind Administratoren vorbehalten.
    $catalog[2] = ['admin/taxes', 'Steuervorlagen', 'bx-receipt'];
    $system = [['admin/reports', 'Berichte', 'bx-bar-chart-alt-2'], ['admin/users', 'Benutzer', 'bx-user-check'], ['admin/website', 'Webseite', 'bx-layout'], ['admin/settings', 'Einstellungen', 'bx-cog'], ['admin/update', 'Update', 'bx-cloud-download', false, UpdateService::badge()], ['admin/license', 'Lizenz', 'bx-key', false, $lic['mode'] === 'readonly' ? '!' : null], ['admin/mail-log', 'E-Mail-Protokoll', 'bx-envelope'], ['admin/audit', 'Audit-Log', 'bx-shield-quarter'], ['admin/integrations', 'Integrationen', 'bx-plug'], ['admin/health', 'Systemstatus', 'bx-pulse', false, HealthService::badge() ?: null]];
    $system = array_values(array_filter($system, static fn ($n) => match ($n[0]) {
        'admin/reports' => License::has('reports'), 'admin/website' => License::has('website'),
        'admin/integrations' => License::has('api') || License::has('webhooks') || License::has('reports'), default => true,
    }));
} else {
    unset($catalog[2]);
}
$siteName = Brand::name();
$brandLogo = Brand::logoUrl();
?><!doctype html>
<html lang="de" class="h-full bg-slate-50 antialiased">
<head><?php include APP_DIR . '/views/partials/head.php'; ?></head>
<body class="h-full font-sans text-slate-800">
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[90] btn btn-primary">Zum Inhalt springen</a>

<!-- Mobile Off-Canvas -->
<div class="sidebar-backdrop fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-[2px] lg:hidden" data-sidebar-close></div>

<aside id="sidebar" class="sidebar fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-slate-200/80 bg-white lg:translate-x-0">
  <div class="flex h-16 shrink-0 items-center gap-3 px-5">
    <?php if ($brandLogo !== ''): ?>
      <img src="<?= e($brandLogo) ?>" alt="" class="size-9 shrink-0 rounded-xl object-contain">
    <?php else: ?>
      <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-brand-600 text-white shadow-sm shadow-brand-600/30"><i class="bx bx-file text-xl"></i></span>
    <?php endif; ?>
    <div class="min-w-0">
      <p class="truncate text-sm font-bold text-slate-900"><?= e($siteName) ?></p>
      <p class="truncate text-xs text-slate-500"><?= e(Brand::tagline()) ?></p>
    </div>
    <button type="button" class="btn btn-ghost btn-icon ml-auto lg:hidden" data-sidebar-close aria-label="Menü schliessen"><i class="bx bx-x text-2xl"></i></button>
  </div>
  <div class="px-4 pb-2">
    <a href="<?= e(url('admin/offers/new')) ?>" class="btn btn-primary w-full"><i class="bx bx-plus"></i>Neue Offerte</a>
  </div>
  <nav class="flex-1 overflow-y-auto px-3 pb-4" aria-label="Hauptnavigation">
    <div class="space-y-0.5 pt-2">
      <?php foreach ($nav as [$href, $label, $icon, $exact, $count]): ?>
        <a href="<?= e(url($href)) ?>" class="nav-link<?= $active('/' . $href, $exact) ?>"><i class="bx <?= $icon ?>"></i><span class="flex-1"><?= e($label) ?></span>
          <?php if ($count): ?><span class="rounded-full bg-brand-600 px-2 py-0.5 text-[11px] font-semibold text-white" data-tip="<?= e(['admin/orders' => 'Neue Aufträge', 'admin/invoices' => 'Überfällige Rechnungen', 'admin/inquiries' => 'Neue Anfragen'][$href] ?? '') ?>"><?= $count ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
    <p class="section-label">Katalog</p>
    <div class="space-y-0.5">
      <?php foreach ($catalog as [$href, $label, $icon]): ?>
        <a href="<?= e(url($href)) ?>" class="nav-link<?= $active('/' . $href) ?>"><i class="bx <?= $icon ?>"></i><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
    <p class="section-label">System</p>
    <div class="space-y-0.5">
      <?php foreach ($system as $sysItem): [$href, $label, $icon] = $sysItem; $count = $sysItem[4] ?? null; ?>
        <a href="<?= e(url($href)) ?>" class="nav-link<?= $active('/' . $href) ?>"><i class="bx <?= $icon ?>"></i><span class="flex-1"><?= e($label) ?></span><?php if (!empty($count)): ?><span class="rounded-full bg-amber-500 px-2 py-0.5 text-[11px] font-semibold text-white" data-tip="Offene Hinweise"><?= (int) $count ?></span><?php endif; ?></a>
      <?php endforeach; ?>
      <a href="<?= e(url('portal')) ?>" target="_blank" rel="noopener" class="nav-link"><i class="bx bx-link-external"></i>Kundenportal</a>
    </div>
  </nav>
  <?php if ($me): ?>
  <div class="relative border-t border-slate-100 p-3" data-dropdown>
    <button type="button" class="flex w-full items-center gap-3 rounded-xl p-2 text-left transition hover:bg-slate-100" data-dropdown-toggle aria-haspopup="true" aria-expanded="false">
      <span class="grid size-9 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-500 to-brand-700 text-xs font-bold text-white"><?= e(initials($me['name'])) ?></span>
      <span class="min-w-0 flex-1">
        <span class="block truncate text-sm font-semibold text-slate-900"><?= e($me['name']) ?></span>
        <span class="block truncate text-xs text-slate-500"><?= $me['role'] === 'admin' ? 'Administrator' : 'Team' ?></span>
      </span>
      <i class="bx bx-chevron-up text-xl text-slate-400"></i>
    </button>
    <div class="dropdown-menu bottom-full left-3 right-3 mb-2" role="menu">
      <a href="<?= e(url('admin/profile')) ?>" class="dropdown-item" role="menuitem"><i class="bx bx-user"></i>Mein Profil</a>
      <form method="post" action="<?= e(url('admin/logout')) ?>"><?= csrf_field() ?>
        <button class="dropdown-item" role="menuitem"><i class="bx bx-log-out"></i>Abmelden</button>
      </form>
    </div>
  </div>
  <?php endif; ?>
  <p class="shrink-0 border-t border-slate-100 px-5 py-3 text-center text-[11px] font-medium tracking-wide text-slate-400" data-powered-by><?= e(Brand::POWERED_BY) ?> · <a href="<?= e(url('admin/changelog')) ?>" class="hover:text-slate-600" data-tip="Änderungsprotokoll">v<?= e(APP_VERSION) ?></a></p>
</aside>

<div class="lg:pl-72">
  <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-slate-200/80 bg-white/85 px-4 backdrop-blur-md lg:hidden">
    <button type="button" class="btn btn-ghost btn-icon" data-sidebar-open aria-label="Menü öffnen"><i class="bx bx-menu text-2xl"></i></button>
    <p class="flex-1 truncate text-sm font-semibold text-slate-900"><?= e($title ?? $siteName) ?></p>
    <a href="<?= e(url('admin/offers/new')) ?>" class="btn btn-primary btn-icon" data-tip="Neue Offerte" aria-label="Neue Offerte"><i class="bx bx-plus text-xl"></i></a>
  </header>
  <main id="main" class="mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8 lg:px-10 lg:py-10">
    <?php $soon = $lic['expires'] && $lic['expires'] - time() < 14 * 86400 && $lic['mode'] === 'full';
    if ($lic['mode'] === 'readonly' || $soon || in_array($lic['status'], ['offline', 'grace'], true) || $lic['trial']): $bad = $lic['mode'] === 'readonly'; ?>
      <div class="mb-6 flex flex-wrap items-center gap-3 rounded-xl px-4 py-3 text-sm ring-1 <?= $bad ? 'bg-rose-50 text-rose-800 ring-rose-200' : 'bg-amber-50 text-amber-800 ring-amber-200' ?>" role="status">
        <i class="bx <?= $bad ? 'bx-lock-alt' : 'bx-time-five' ?> text-lg"></i>
        <span class="flex-1"><strong><?= $bad ? 'Lesemodus.' : ($lic['trial'] && !$soon ? 'Testlizenz.' : 'Lizenz.') ?></strong> <?= e($soon && $lic['status'] === 'active' ? 'Ihre Lizenz läuft am ' . date('d.m.Y', $lic['expires']) . ' ab.' : $lic['message']) ?></span>
        <?php if ($me && $me['role'] === 'admin'): ?><a class="btn btn-secondary btn-sm" href="<?= e(url('admin/license')) ?>"><i class="bx bx-key"></i>Lizenz</a><?php endif; ?>
      </div>
    <?php endif; ?>
    <?= $content ?>
  </main>
</div>

<?php include APP_DIR . '/views/partials/flash.php'; ?>
<?php include APP_DIR . '/views/partials/confirm-modal.php'; ?>
<?php if (empty($noChangelog)) include APP_DIR . '/views/partials/changelog-modal.php'; ?>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?php if (!empty($scripts)) foreach ((array) $scripts as $s): ?><script src="<?= e(asset($s)) ?>" defer></script><?php endforeach; ?>
</body>
</html>
