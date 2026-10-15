<?php
$title = 'Aufträge';
$subtitle = 'Aufträge entstehen automatisch, sobald ein Kunde eine Offerte annimmt.';
include APP_DIR . '/views/partials/page-header.php';
$filters = ['active' => 'In Arbeit', '' => 'Alle'] + array_map(static fn($s) => $s[0], Status::ORDER);
$filters['fulfilled'] = 'Geliefert';
?>
<div class="reveal mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
  <nav class="-mx-1 flex gap-1 overflow-x-auto px-1 pb-1" aria-label="Statusfilter">
    <?php foreach ($filters as $k => $label): ?>
      <a href="?<?= e(http_build_query(array_filter(['status' => $k, 'q' => $q, 'owner' => $owner]))) ?>" class="tab shrink-0<?= $status === $k ? ' is-active' : '' ?>"><?= e($label) ?> <span class="text-xs text-slate-400"><?= $counts[$k] ?? 0 ?></span></a>
    <?php endforeach; ?>
  </nav>
  <form method="get" class="flex flex-col gap-2 sm:flex-row lg:items-center" role="search">
    <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <?php $withNone = true; include APP_DIR . '/views/partials/owner-filter.php'; ?>
    <div class="relative lg:w-72"><i class="bx bx-search pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-lg text-slate-400"></i>
    <input class="input pl-10" type="search" name="q" value="<?= e($q) ?>" placeholder="Nummer, Titel, Kunde, Tracking …"></div>
  </form>
</div>
<div class="reveal card overflow-hidden">
  <?php if (!$orders): ?>
    <div class="empty-state"><i class="bx bx-package"></i><p class="font-semibold text-slate-900">Keine Aufträge</p><p class="mt-1 text-sm text-slate-500">Hier erscheinen angenommene Offerten.</p></div>
  <?php else: ?>
  <div class="overflow-x-auto">
    <table class="table">
      <thead><tr><th>Auftrag</th><th class="hidden md:table-cell">Kunde</th><th class="hidden lg:table-cell">Eingang</th><th class="hidden md:table-cell">Verantwortlich</th><th class="hidden sm:table-cell">Lieferung</th><th>Status</th><th class="text-right">Betrag</th></tr></thead>
      <tbody>
      <?php foreach ($orders as $o): ?>
        <tr class="row-link" data-href="<?= e(url('admin/orders/' . $o['id'])) ?>">
          <td>
            <div class="flex items-center gap-3">
              <span class="hidden size-9 shrink-0 place-items-center rounded-lg bg-slate-100 text-lg text-slate-500 sm:grid"><i class="bx <?= e($o['area_icon'] ?: 'bx-category') ?>"></i></span>
              <div class="min-w-0">
                <a href="<?= e(url('admin/orders/' . $o['id'])) ?>" class="block max-w-[260px] truncate font-medium text-slate-900 hover:text-brand-700"><?= e($o['title']) ?></a>
                <p class="text-xs text-slate-500"><?= e($o['number']) ?><span class="md:hidden"> · <?= e(customer_name($o)) ?></span></p>
              </div>
            </div>
          </td>
          <td class="hidden md:table-cell"><span class="block max-w-[200px] truncate"><?= e(customer_name($o)) ?></span></td>
          <td class="hidden lg:table-cell text-slate-500"><?= fmt_date($o['created_at']) ?></td>
          <td class="hidden max-w-[170px] md:table-cell"><?= person_chip($o['responsible_label']) ?></td>
          <td class="hidden sm:table-cell">
            <?php if ($o['area_type'] === 'physical'): ?>
              <?= Status::badge('shipping', $o['shipping_status'] ?: 'pending') ?>
            <?php else: ?>
              <span class="badge <?= $o['download_url'] || $o['access_info'] ? 'badge-emerald' : 'badge-slate' ?>"><i class="bx bx-cloud"></i><?= $o['download_url'] || $o['access_info'] ? 'bereit' : 'ausstehend' ?></span>
            <?php endif; ?>
          </td>
          <td><?= Status::badge('order', $o['status'], $o['area_type']) ?></td>
          <td class="text-right font-semibold text-slate-900 tabular"><?= money($o['total'], $o['currency']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
