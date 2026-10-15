<?php
$title = 'Kunden';
$subtitle = count($customers) . ' Kunden' . ($q !== '' ? ' gefunden' : '');
$actions = '<a href="' . e(url('admin/customers/new')) . '" class="btn btn-primary"><i class="bx bx-user-plus"></i>Neuer Kunde</a>';
include APP_DIR . '/views/partials/page-header.php';
?>
<div class="reveal card overflow-hidden">
  <form method="get" class="flex gap-2 border-b border-slate-100 p-4 sm:px-6" role="search">
    <div class="relative flex-1">
      <i class="bx bx-search pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-lg text-slate-400"></i>
      <input class="input pl-10" type="search" name="q" value="<?= e($q) ?>" placeholder="Name, Firma, E-Mail, Ort oder Kunden-Nr. suchen …">
    </div>
    <button class="btn btn-secondary" data-no-loading>Suchen</button>
  </form>
  <?php if (!$customers): ?>
    <div class="empty-state"><i class="bx bx-group"></i>
      <p class="font-semibold text-slate-900"><?= $q !== '' ? 'Keine Treffer' : 'Noch keine Kunden' ?></p>
      <p class="mt-1 text-sm text-slate-500"><?= $q !== '' ? 'Versuchen Sie einen anderen Suchbegriff.' : 'Legen Sie Ihren ersten Kunden an, um Offerten zu erstellen.' ?></p>
      <?php if ($q === ''): ?><a href="<?= e(url('admin/customers/new')) ?>" class="btn btn-primary mt-5"><i class="bx bx-user-plus"></i>Kunde anlegen</a><?php endif; ?>
    </div>
  <?php else: ?>
  <div class="overflow-x-auto">
    <table class="table">
      <thead><tr><th>Kunde</th><th class="hidden md:table-cell">Kontakt</th><th class="hidden lg:table-cell">Ort</th><th class="hidden sm:table-cell text-right">Offerten / Aufträge</th><th class="text-right">Umsatz</th></tr></thead>
      <tbody>
      <?php foreach ($customers as $c): ?>
        <tr class="row-link" data-href="<?= e(url('admin/customers/' . $c['id'])) ?>">
          <td>
            <div class="flex items-center gap-3">
              <span class="grid size-9 shrink-0 place-items-center rounded-full bg-slate-100 text-xs font-bold text-slate-600"><?= e(initials(customer_name($c))) ?></span>
              <div class="min-w-0">
                <a href="<?= e(url('admin/customers/' . $c['id'])) ?>" class="block truncate font-medium text-slate-900 hover:text-brand-700"><?= e(customer_name($c)) ?></a>
                <p class="truncate text-xs text-slate-500"><?= e($c['customer_no']) ?><?= $c['company'] ? ' · ' . e(trim($c['first_name'] . ' ' . $c['last_name'])) : '' ?>
                  </p>
              </div>
            </div>
          </td>
          <td class="hidden md:table-cell"><p class="truncate"><?= e($c['email']) ?></p><p class="text-xs text-slate-500"><?= e($c['phone'] ?: $c['mobile'] ?: '') ?></p></td>
          <td class="hidden lg:table-cell"><?= e(trim($c['zip'] . ' ' . $c['city'])) ?> <span class="text-xs text-slate-400"><?= e($c['country']) ?></span></td>
          <td class="hidden sm:table-cell text-right tabular"><?= (int) $c['offer_count'] ?> / <?= (int) $c['order_count'] ?></td>
          <td class="text-right font-medium text-slate-900 tabular"><?= money($c['revenue']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
