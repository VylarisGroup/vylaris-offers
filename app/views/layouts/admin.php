<?php
$me = Auth::user();
$path = Router::currentPath();
$active = static function (string $prefix, bool $exact = false) use ($path): string {
    $hit = $exact ? $path === $prefix : ($path === $prefix || str_starts_with($path, $prefix . '/'));
    return $hit ? ' is-active' : '';
};
$newOrders = (int) DB::val("SELECT COUNT(*) FROM orders WHERE status = 'new'");
$newInq = InquiryService::newCount();
$newMsgs = PortalMessage::unreadForTeam();
$openInv = (int) DB::val("SELECT COUNT(*) FROM invoices WHERE status = 'sent' AND due_date < CURDATE()");
$lic = License::status();
$isAdmin = $me && $me['role'] === 'admin';
$tips = ['admin/orders' => 'Neue Aufträge', 'admin/invoices' => 'Überfällige Rechnungen', 'admin/inquiries' => 'Neue Anfragen', 'admin/messages' => 'Ungelesene Kundennachrichten'];
// Einträge: [Pfad, Label, Icon, Zähler|null, Zählerfarbe]
$groups = [
    ['sales', 'Verkauf', 'bx-store-alt', true, [
        ['admin/offers', 'Offerten', 'bx-file', null],
        ['admin/orders', 'Aufträge', 'bx-package', $newOrders ?: null],
        ['admin/invoices', 'Rechnungen', 'bx-receipt', $openInv ?: null],
        ['admin/subscriptions', 'Abos', 'bx-sync', null],
    ]],
    ['crm', 'Kunden', 'bx-group', true, [
        ['admin/inquiries', 'Anfragen', 'bx-envelope-open', $newInq ?: null],
        ['admin/messages', 'Nachrichten', 'bx-message-square-dots', $newMsgs ?: null],
        ['admin/customers', 'Kunden', 'bx-user-pin', null],
    ]],
    ['catalog', 'Katalog', 'bx-category', false, [
        ['admin/areas', 'Produktbereiche', 'bx-category', null],
        ['admin/templates', 'Positionsvorlagen', 'bx-bookmarks', null],
        ['admin/doc-templates', 'Dokumentvorlagen', 'bx-layout', null],
        ['admin/taxes', 'Steuervorlagen', 'bx-receipt', null],
        ['admin/shipping', 'Versandarten', 'bx-car', null],
    ]],
];
if ($isAdmin) {
    // Heikle Bereiche (Benutzer, Einstellungen, Steuern, Mail-Protokoll) sind Administratoren vorbehalten.
    $groups[] = ['manage', 'Verwaltung', 'bx-briefcase-alt-2', false, [
        ['admin/reports', 'Berichte', 'bx-bar-chart-alt-2', null],
        ['admin/users', 'Benutzer', 'bx-user-check', null],
        ['admin/website', 'Webseite', 'bx-layout', null],
        ['admin/integrations', 'Integrationen', 'bx-plug', null],
    ]];
    $groups[] = ['system', 'System', 'bx-cog', false, [
        ['admin/settings', 'Einstellungen', 'bx-slider-alt', null],
        ['admin/update', 'Update', 'bx-cloud-download', UpdateService::badge() ?: null],
        ['admin/license', 'Lizenz', 'bx-key', $lic['mode'] === 'readonly' ? '!' : null],
        ['admin/health', 'Systemstatus', 'bx-pulse', HealthService::badge() ?: null],
        ['admin/mail-log', 'E-Mail-Protokoll', 'bx-envelope', null],
        ['admin/audit', 'Audit-Log', 'bx-shield-quarter', null],
    ]];
} else {
    $groups[2][4] = array_values(array_filter($groups[2][4], static fn ($n) => $n[0] !== 'admin/taxes'));
}
$hidden = static fn (string $href): bool => match ($href) {
    'admin/invoices' => !License::has('invoices'), 'admin/subscriptions' => !License::has('subscriptions'), 'admin/inquiries' => !License::has('website'),
    'admin/reports' => !License::has('reports'), 'admin/website' => !License::has('website'),
    'admin/integrations' => !(License::has('api') || License::has('webhooks') || License::has('reports')), default => false,
};
foreach ($groups as &$g) {
    $g[4] = array_values(array_filter($g[4], static fn ($n) => !$hidden($n[0])));
}
unset($g);
$groups = array_values(array_filter($groups, static fn ($g) => $g[4] !== []));
$pwaApp = 'admin';
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
  <div class="relative flex gap-1.5 px-4 pb-2" data-dropdown>
    <a href="<?= e(url('admin/offers/new')) ?>" class="btn btn-primary min-w-0 flex-1"><i class="bx bx-plus"></i>Neue Offerte</a>
    <button type="button" class="btn btn-primary btn-icon shrink-0" data-dropdown-toggle aria-haspopup="true" aria-expanded="false" aria-label="Weitere Aktionen zum Erstellen" data-tip="Neu erstellen"><i class="bx bx-chevron-down text-xl"></i></button>
    <div class="dropdown-menu left-4 right-4 top-full mt-1" role="menu">
      <p class="px-3 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Mit Assistent</p>
      <a href="<?= e(url('admin/offers/new?wizard=1')) ?>" class="dropdown-item" role="menuitem"><i class="bx bx-magic-wand"></i>Offerte – Schritt für Schritt</a>
      <a href="<?= e(url('admin/customers/new?wizard=1')) ?>" class="dropdown-item" role="menuitem"><i class="bx bx-magic-wand"></i>Kunde – Schritt für Schritt</a>
      <p class="px-3 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Direkt</p>
      <a href="<?= e(url('admin/customers/new')) ?>" class="dropdown-item" role="menuitem"><i class="bx bx-user-plus"></i>Neuer Kunde</a>
    </div>
  </div>
  <nav class="flex-1 overflow-y-auto px-3 pb-4" aria-label="Hauptnavigation" data-nav>
    <div class="space-y-0.5 pt-2">
      <a href="<?= e(url('admin')) ?>" class="nav-link<?= $active('/admin', true) ?>"><i class="bx bx-grid-alt"></i><span class="flex-1">Dashboard</span></a>
    </div>
    <?php foreach ($groups as [$gk, $gLabel, $gIcon, $gOpen, $gItems]):
      $hasActive = false; $sum = 0;
      foreach ($gItems as $it) { if ($active('/' . $it[0]) !== '') { $hasActive = true; } $sum += is_numeric($it[3] ?? null) ? (int) $it[3] : (($it[3] ?? null) ? 1 : 0); }
      $open = $hasActive || $gOpen; ?>
      <div class="nav-group mt-1" data-nav-group="<?= e($gk) ?>" data-default="<?= $gOpen ? '1' : '0' ?>" data-has-active="<?= $hasActive ? '1' : '0' ?>"<?= $open ? ' data-open' : '' ?>>
        <button type="button" class="nav-group-btn" aria-expanded="<?= $open ? 'true' : 'false' ?>" aria-controls="nav-<?= e($gk) ?>">
          <i class="bx <?= $gIcon ?>"></i><span class="flex-1 text-left"><?= e($gLabel) ?></span>
          <?php if ($sum): ?><span class="nav-group-badge rounded-full bg-brand-600 px-1.5 py-0.5 text-[10px] font-semibold text-white" data-tip="Offene Hinweise"><?= $sum ?></span><?php endif; ?>
          <i class="bx bx-chevron-down nav-chevron"></i>
        </button>
        <div class="nav-group-body" id="nav-<?= e($gk) ?>"><div class="min-h-0 overflow-hidden"><div class="nav-sub space-y-0.5 pb-1 pt-0.5">
          <?php foreach ($gItems as $it): [$href, $label, $icon] = $it; $count = $it[3] ?? null; ?>
            <a href="<?= e(url($href)) ?>" class="nav-link nav-link-sub<?= $active('/' . $href) ?>"<?= $active('/' . $href) ? ' aria-current="page"' : '' ?>><i class="bx <?= $icon ?>"></i><span class="flex-1"><?= e($label) ?></span>
              <?php if (!empty($count)): ?><span class="rounded-full <?= in_array($href, ['admin/orders', 'admin/invoices', 'admin/inquiries'], true) ? 'bg-brand-600' : 'bg-amber-500' ?> px-2 py-0.5 text-[11px] font-semibold text-white" data-tip="<?= e($tips[$href] ?? 'Offene Hinweise') ?>"><?= e((string) $count) ?></span><?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div></div></div>
      </div>
    <?php endforeach; ?>
    <div class="mt-3 border-t border-slate-100 pt-3">
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
  <?php if (Pwa::enabled()): ?><button type="button" hidden data-pwa-install class="mx-4 mb-2 inline-flex items-center justify-center gap-2 rounded-xl bg-brand-50 px-3 py-2 text-xs font-semibold text-brand-700 ring-1 ring-brand-100 transition hover:bg-brand-100"><i class="bx bx-download text-base"></i>App installieren</button><?php endif; ?>
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
<?php if (Pwa::enabled()): ?><script src="<?= e(asset('js/pwa.js')) ?>" defer></script><?php endif; ?>
<?php if (!empty($scripts)) foreach ((array) $scripts as $s): ?><script src="<?= e(asset($s)) ?>" defer></script><?php endforeach; ?>
<script src="<?= e(asset('js/wizard.js')) ?>" defer></script>
</body>
</html>
