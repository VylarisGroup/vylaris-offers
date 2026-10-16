<?php
$title = 'Versandarten';
$subtitle = 'Werden in Offerten für physische Produkte vorgeschlagen. Kosten sind Nettobeträge.';
$actions = '<button type="button" class="btn btn-primary" data-modal-open="modal-ship" data-fill data-modal-title="Neue Versandart"><i class="bx bx-plus"></i>Neue Versandart</button>';
include APP_DIR . '/views/partials/page-header.php';
?>
<div class="reveal card overflow-hidden">
  <?php if (!$methods): ?>
    <div class="empty-state"><i class="bx bx-car"></i><p class="font-semibold text-slate-900">Keine Versandarten</p></div>
  <?php else: ?>
  <ul class="divide-y divide-slate-100">
    <?php foreach ($methods as $m): ?>
      <li class="flex items-center gap-4 px-5 py-4 transition hover:bg-slate-50/80 sm:px-6<?= $m['active'] ? '' : ' opacity-60' ?>">
        <span class="kpi-icon size-10 bg-slate-100 text-xl text-slate-500"><i class="bx bx-car"></i></span>
        <div class="min-w-0 flex-1"><p class="font-medium text-slate-900"><?= e($m['name']) ?><?= $m['active'] ? '' : ' <span class="badge badge-slate">inaktiv</span>' ?></p><p class="truncate text-xs text-slate-500"><?= e($m['description'] ?: '') ?></p></div>
        <span class="font-semibold text-slate-900 tabular"><?= (float) $m['cost'] > 0 ? money($m['cost']) : 'kostenlos' ?></span>
        <div class="flex gap-1">
          <button type="button" class="btn btn-ghost btn-icon" data-modal-open="modal-ship" data-modal-title="Versandart bearbeiten" data-fill="<?= json_attr(['id' => $m['id'], 'name' => $m['name'], 'description' => $m['description'], 'cost' => number_format((float) $m['cost'], 2, '.', ''), 'active' => $m['active'], 'sort' => $m['sort']]) ?>" data-tip="Bearbeiten" aria-label="Bearbeiten"><i class="bx bx-edit text-lg"></i></button>
          <form method="post" action="<?= e(url('admin/shipping/' . $m['id'] . '/delete')) ?>" data-confirm="Versandart „<?= e($m['name']) ?>“ löschen?" data-confirm-label="Löschen"><?= csrf_field() ?><button class="btn btn-ghost btn-icon text-slate-400 hover:text-rose-600" data-tip="Löschen" aria-label="Löschen"><i class="bx bx-trash text-lg"></i></button></form>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</div>
<div class="modal" id="modal-ship" aria-hidden="true" role="dialog" aria-modal="true">
  <div class="modal-backdrop" data-modal-close></div>
  <form method="post" action="<?= e(url('admin/shipping')) ?>" class="modal-panel max-w-md" data-wizard="auto" data-defaults='{"active":1,"sort":0,"cost":"0.00"}'><?= csrf_field() ?>
    <input type="hidden" name="id">
    <div class="flex items-center justify-between border-b border-slate-100 p-5 sm:px-6"><h3 class="text-base font-semibold text-slate-900" data-modal-heading>Versandart</h3><button type="button" class="btn btn-ghost btn-icon" data-modal-close aria-label="Schliessen"><i class="bx bx-x text-2xl"></i></button></div>
    <div class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6" data-wizard-fields>
      <div class="sm:col-span-2"><label class="label">Name <span class="text-rose-500">*</span></label><input class="input" name="name" required maxlength="120"></div>
      <div class="sm:col-span-2"><label class="label">Beschreibung</label><input class="input" name="description" maxlength="255"></div>
      <div><label class="label">Kosten (netto)</label><input class="input tabular" name="cost" inputmode="decimal"></div>
      <div><label class="label">Sortierung</label><input class="input" type="number" name="sort"></div>
      <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-700 sm:col-span-2"><input type="checkbox" class="checkbox" name="active" value="1">Aktiv</label>
    </div>
    <div class="flex flex-col-reverse gap-2 rounded-b-2xl bg-slate-50 px-6 py-4 sm:flex-row sm:justify-end" data-wizard-final><button type="button" class="btn btn-secondary" data-modal-close>Abbrechen</button><button class="btn btn-primary"><i class="bx bx-save"></i>Speichern</button></div>
  </form>
</div>
