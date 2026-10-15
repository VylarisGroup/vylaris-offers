<?php
$title = 'Offerten';
$subtitle = 'Offerten sind nach dem Erstellen unveränderlich. Änderungen erfolgen über eine neue Offerte auf Basis der alten.';
$actions = '<a href="' . e(url('admin/offers/new')) . '" class="btn btn-primary"><i class="bx bx-plus"></i>Neue Offerte</a>';
include APP_DIR . '/views/partials/page-header.php';
$filters = ['' => 'Alle'] + array_map(static fn($s) => $s[0], Status::OFFER);
?>
<div class="reveal mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
  <nav class="-mx-1 flex gap-1 overflow-x-auto px-1 pb-1" aria-label="Statusfilter">
    <?php foreach ($filters as $k => $label): ?>
      <a href="?<?= e(http_build_query(array_filter(['status' => $k, 'q' => $q, 'owner' => $owner]))) ?>" class="tab shrink-0<?= $status === $k ? ' is-active' : '' ?>"><?= e($label) ?> <span class="text-xs text-slate-400"><?= $counts[$k] ?? 0 ?></span></a>
    <?php endforeach; ?>
  </nav>
  <form method="get" class="flex flex-col gap-2 sm:flex-row lg:items-center" role="search">
    <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <?php $withNone = false; include APP_DIR . '/views/partials/owner-filter.php'; ?>
    <div class="relative lg:w-72"><i class="bx bx-search pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-lg text-slate-400"></i>
    <input class="input pl-10" type="search" name="q" value="<?= e($q) ?>" placeholder="Nummer, Titel oder Kunde …"></div>
  </form>
</div>

<div class="reveal card overflow-hidden">
  <?php if (!$offers): ?>
    <div class="empty-state"><i class="bx bx-file-blank"></i>
      <p class="font-semibold text-slate-900">Keine Offerten gefunden</p>
      <p class="mt-1 text-sm text-slate-500"><?= $status || $q ? 'Passen Sie die Filter an.' : 'Erstellen Sie Ihre erste Offerte.' ?></p>
      <?php if (!$status && !$q): ?><a href="<?= e(url('admin/offers/new')) ?>" class="btn btn-primary mt-5"><i class="bx bx-plus"></i>Neue Offerte</a><?php endif; ?>
    </div>
  <?php else: ?>
  <div class="overflow-x-auto">
    <table class="table">
      <thead><tr><th>Offerte</th><th class="hidden md:table-cell">Kunde</th><th class="hidden lg:table-cell">Erstellt</th><th class="hidden xl:table-cell">Ausgestellt von</th><th class="hidden sm:table-cell">Gültig bis</th><th>Status</th><th class="text-right">Betrag</th><th class="w-10"><span class="sr-only">Aktionen</span></th></tr></thead>
      <tbody>
      <?php foreach ($offers as $o): ?>
        <tr class="row-link" data-href="<?= e(url('admin/offers/' . $o['id'])) ?>">
          <td>
            <div class="flex items-center gap-3">
              <span class="hidden size-9 shrink-0 place-items-center rounded-lg bg-slate-100 text-lg text-slate-500 sm:grid" data-tip="<?= e($o['area_name'] ?? '') ?>"><i class="bx <?= e($o['area_icon'] ?: 'bx-category') ?>"></i></span>
              <div class="min-w-0">
                <a href="<?= e(url('admin/offers/' . $o['id'])) ?>" class="block max-w-[260px] truncate font-medium text-slate-900 hover:text-brand-700"><?= e($o['title']) ?></a>
                <p class="text-xs text-slate-500"><?= e($o['number']) ?><span class="md:hidden"> · <?= e(customer_name($o)) ?></span>
                  <?php if ($o['viewed_at'] && $o['status'] === 'open'): ?><i class="bx bx-show ml-1 align-middle text-sky-500" data-tip="Vom Kunden geöffnet <?= e(time_ago($o['viewed_at'])) ?>"></i><?php endif; ?></p>
              </div>
            </div>
          </td>
          <td class="hidden md:table-cell"><span class="block max-w-[200px] truncate"><?= e(customer_name($o)) ?></span></td>
          <td class="hidden lg:table-cell text-slate-500"><?= fmt_date($o['created_at']) ?></td>
          <td class="hidden max-w-[160px] xl:table-cell"><?= person_chip($o['issuer_label'], '–') ?></td>
          <td class="hidden sm:table-cell text-slate-500"><?= $o['valid_until'] ? fmt_date($o['valid_until']) : '–' ?></td>
          <td><?= Status::badge('offer', $o['status']) ?></td>
          <td class="text-right font-semibold text-slate-900 tabular"><?= money($o['total'], $o['currency']) ?></td>
          <td>
            <div data-dropdown>
              <button type="button" class="btn btn-ghost btn-icon" data-dropdown-toggle aria-label="Aktionen"><i class="bx bx-dots-vertical-rounded text-xl"></i></button>
              <div class="dropdown-menu right-0">
                <a class="dropdown-item" href="<?= e(url('admin/offers/' . $o['id'])) ?>"><i class="bx bx-show"></i>Ansehen</a>
                <a class="dropdown-item" href="<?= e(url('o/' . $o['public_id'])) ?>" target="_blank" rel="noopener"><i class="bx bx-link-external"></i>Kundenansicht</a>
                <button type="button" class="dropdown-item" data-copy="<?= e(url('o/' . $o['public_id'])) ?>"><i class="bx bx-copy"></i>Link kopieren</button>
                <a class="dropdown-item" href="<?= e(url('admin/offers/new?from=' . $o['id'])) ?>"><i class="bx bx-git-branch"></i>Neue Offerte auf dieser Basis</a>
              </div>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
