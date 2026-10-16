<?php
$title = 'Positionsvorlagen';
$subtitle = 'Häufig verwendete Positionen – im Offerten-Editor mit einem Klick einfügen.';
$actions = '<button type="button" class="btn btn-primary" data-modal-open="modal-tpl" data-fill data-modal-title="Neue Vorlage"><i class="bx bx-plus"></i>Neue Vorlage</button>';
include APP_DIR . '/views/partials/page-header.php';
?>
<div class="reveal card overflow-hidden">
  <?php if (!$templates): ?>
    <div class="empty-state"><i class="bx bx-bookmarks"></i><p class="font-semibold text-slate-900">Noch keine Vorlagen</p><p class="mt-1 max-w-sm text-sm text-slate-500">Legen Sie Vorlagen hier an oder speichern Sie Positionen direkt im Offerten-Editor über „Als Vorlage speichern“.</p></div>
  <?php else: ?>
  <div class="overflow-x-auto"><table class="table">
    <thead><tr><th>Vorlage</th><th class="hidden md:table-cell">Bereich</th><th class="hidden sm:table-cell">Steuer</th><th class="text-right">Preis</th><th class="w-24"><span class="sr-only">Aktionen</span></th></tr></thead>
    <tbody>
    <?php foreach ($templates as $t): ?>
      <tr class="<?= $t['active'] ? '' : 'opacity-60' ?>">
        <td><p class="font-medium text-slate-900"><?= e($t['title']) ?><?= $t['active'] ? '' : ' <span class="badge badge-slate">inaktiv</span>' ?></p><?php if ($t['description']): ?><p class="max-w-md truncate text-xs text-slate-500"><?= e($t['description']) ?></p><?php endif; ?></td>
        <td class="hidden md:table-cell"><?= $t['area_name'] ? '<span class="badge badge-slate"><i class="bx ' . e($t['area_icon']) . '"></i>' . e($t['area_name']) . '</span>' : '<span class="text-slate-400">alle</span>' ?></td>
        <td class="hidden sm:table-cell text-xs text-slate-500"><?= $t['tax_label'] !== null ? e(TaxService::display(['label' => $t['tax_label'], 'rate' => $t['tax_rate'], 'country' => $t['tax_country']])) : 'Standard' ?></td>
        <td class="whitespace-nowrap text-right font-medium tabular"><?= money($t['unit_price']) ?> <span class="text-xs font-normal text-slate-400">/ <?= e($t['unit']) ?></span></td>
        <td><div class="flex justify-end gap-1">
          <button type="button" class="btn btn-ghost btn-icon" data-modal-open="modal-tpl" data-modal-title="Vorlage bearbeiten" data-fill="<?= json_attr(['id' => $t['id'], 'title' => $t['title'], 'description' => $t['description'], 'unit' => $t['unit'], 'unit_price' => number_format((float) $t['unit_price'], 2, '.', ''), 'product_area_id' => $t['product_area_id'], 'tax_template_id' => $t['tax_template_id'], 'active' => $t['active']]) ?>" data-tip="Bearbeiten" aria-label="Bearbeiten"><i class="bx bx-edit text-lg"></i></button>
          <form method="post" action="<?= e(url('admin/templates/' . $t['id'] . '/delete')) ?>" data-confirm="Vorlage „<?= e($t['title']) ?>“ löschen? Bestehende Offerten bleiben unverändert." data-confirm-label="Löschen"><?= csrf_field() ?><button class="btn btn-ghost btn-icon text-slate-400 hover:text-rose-600" data-tip="Löschen" aria-label="Löschen"><i class="bx bx-trash text-lg"></i></button></form>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>

<div class="modal" id="modal-tpl" aria-hidden="true" role="dialog" aria-modal="true">
  <div class="modal-backdrop" data-modal-close></div>
  <form method="post" action="<?= e(url('admin/templates')) ?>" class="modal-panel max-w-lg" data-wizard="auto" data-defaults='{"unit":"Stk.","active":1}'><?= csrf_field() ?>
    <input type="hidden" name="id">
    <div class="flex items-center justify-between border-b border-slate-100 p-5 sm:px-6"><h3 class="text-base font-semibold text-slate-900" data-modal-heading>Vorlage</h3><button type="button" class="btn btn-ghost btn-icon" data-modal-close aria-label="Schliessen"><i class="bx bx-x text-2xl"></i></button></div>
    <div class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6" data-wizard-fields>
      <div class="sm:col-span-2"><label class="label">Titel <span class="text-rose-500">*</span></label><input class="input" name="title" required maxlength="190"></div>
      <div class="sm:col-span-2"><label class="label">Beschreibung</label><textarea class="input" name="description" rows="3"></textarea></div>
      <div><label class="label">Einzelpreis (netto)</label><input class="input tabular" name="unit_price" inputmode="decimal" placeholder="0.00"></div>
      <div><label class="label">Einheit</label><input class="input" name="unit" maxlength="30"></div>
      <div><label class="label">Produktbereich</label><select class="input" name="product_area_id"><option value="">Alle Bereiche</option><?php foreach ($areas as $a): ?><option value="<?= (int) $a['id'] ?>"><?= e($a['name']) ?></option><?php endforeach; ?></select></div>
      <div><label class="label">Steuer</label><select class="input" name="tax_template_id"><option value="">Standard der Offerte</option><?php foreach ($taxes as $t): ?><option value="<?= (int) $t['id'] ?>"><?= e($t['display']) ?></option><?php endforeach; ?></select></div>
      <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-700 sm:col-span-2"><input type="checkbox" class="checkbox" name="active" value="1">Aktiv</label>
    </div>
    <div class="flex flex-col-reverse gap-2 rounded-b-2xl bg-slate-50 px-6 py-4 sm:flex-row sm:justify-end" data-wizard-final><button type="button" class="btn btn-secondary" data-modal-close>Abbrechen</button><button class="btn btn-primary"><i class="bx bx-save"></i>Speichern</button></div>
  </form>
</div>
