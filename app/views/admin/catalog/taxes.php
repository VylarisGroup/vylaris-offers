<?php
$title = 'Steuervorlagen';
$subtitle = 'Schweizer Mehrwertsteuer (MWST). Der als <strong>Standard</strong> markierte Satz wird bei neuen Offerten vorgewählt, pro Position lässt sich ein anderer wählen. Änderungen wirken nur auf neue Offerten.';
$actions = '<button type="button" class="btn btn-primary" data-modal-open="modal-tax" data-fill data-modal-title="Neue Steuervorlage"><i class="bx bx-plus"></i>Neue Vorlage</button>';
include APP_DIR . '/views/partials/page-header.php';
?>
<?php if (!TaxService::liable()): ?>
<div class="reveal mb-6 flex gap-3 rounded-2xl bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-200"><i class="bx bx-info-circle text-xl"></i><p><strong>Nicht MWST-pflichtig:</strong> In Offerten und Rechnungen steht nur «Ohne MWST» zur Verfügung. Die Sätze unten sind aktuell ausgeblendet (Einstellungen → Firma → MWST).</p></div>
<?php endif; ?>
<div class="max-w-3xl">
  <section class="reveal card overflow-hidden">
    <div class="card-header"><h2 class="card-title"><span class="grid size-8 place-items-center rounded-lg bg-slate-100 text-xs font-bold text-slate-600">CH</span>Schweiz</h2></div>
    <ul class="divide-y divide-slate-100">
      <?php foreach ($taxes as $t): ?>
        <li class="flex items-center gap-4 px-5 py-3.5 transition hover:bg-slate-50/80 sm:px-6<?= $t['active'] ? '' : ' opacity-60' ?>">
          <span class="w-16 shrink-0 text-lg font-bold text-slate-900 tabular"><?= (float) $t['rate'] > 0 ? rate($t['rate']) : '0 %' ?></span>
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium text-slate-900"><?= e($t['name']) ?></p>
            <p class="text-xs text-slate-500">Kennzeichnung: <span class="font-medium text-slate-700"><?= e($t['display']) ?></span><?php $cl = TaxService::CLASSES[$t['tax_class'] ?? 'taxable'] ?? null; if ($cl && $cl['ziff']): ?> · Abrechnung Ziff. <?= e($cl['ziff']) ?><?php endif; ?><?php if (!empty($t['valid_from']) || !empty($t['valid_to'])): ?> · gültig <?= !empty($t['valid_from']) ? 'ab ' . fmt_date($t['valid_from']) : '' ?> <?= !empty($t['valid_to']) ? 'bis ' . fmt_date($t['valid_to']) : '' ?><?php endif; ?></p>
          </div>
          <?php if ($t['is_default']): ?><span class="badge badge-emerald"><i class="bx bx-star"></i>Standard</span><?php endif; ?>
          <?php if (!$t['active']): ?><span class="badge badge-slate">inaktiv</span><?php endif; ?>
          <div class="flex gap-1">
            <button type="button" class="btn btn-ghost btn-icon" data-modal-open="modal-tax" data-modal-title="Steuervorlage bearbeiten" data-fill="<?= json_attr(['id' => $t['id'], 'name' => $t['name'], 'label' => $t['label'], 'rate' => qty($t['rate']), 'tax_class' => $t['tax_class'] ?? 'taxable', 'valid_from' => $t['valid_from'] ?? '', 'valid_to' => $t['valid_to'] ?? '', 'is_default' => $t['is_default'], 'active' => $t['active'], 'sort' => $t['sort']]) ?>" data-tip="Bearbeiten" aria-label="Bearbeiten"><i class="bx bx-edit text-lg"></i></button>
            <form method="post" action="<?= e(url('admin/taxes/' . $t['id'] . '/delete')) ?>" data-confirm="Steuervorlage „<?= e($t['name']) ?>“ löschen?" data-confirm-label="Löschen"><?= csrf_field() ?><button class="btn btn-ghost btn-icon text-slate-400 hover:text-rose-600" data-tip="Löschen" aria-label="Löschen"><i class="bx bx-trash text-lg"></i></button></form>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
</div>
<p class="reveal mt-6 flex gap-2 text-xs leading-relaxed text-slate-500"><i class="bx bx-info-circle text-base"></i><span>Vorbelegt mit den seit 1.1.2024 gültigen Schweizer Sätzen: Normalsatz 8.1 %, reduzierter Satz 2.6 %, Sondersatz Beherbergung 3.8 %. Die Steuerart wird pro Position gewählt, nicht pro Kunde: <strong>Ausfuhr</strong> (Waren ins Ausland, Art. 23, Vorsteuerabzug bleibt), <strong>Im Ausland erbracht</strong> (Dienstleistungen an Empfänger im Ausland, Art. 8), <strong>Ausgenommen</strong> (z. B. Versicherung, Bildung, Gesundheit, Art. 21), <strong>Meldeverfahren</strong> (Art. 38) und <strong>Durchlaufposten</strong> (Art. 24 Abs. 6). Jede Art druckt ihren Pflichthinweis auf den Beleg und landet in der passenden Ziffer der MWST-Abrechnung. Bei Satzänderungen passen Sie die Vorlagen hier an; bereits erstellte Dokumente bleiben unverändert. Im Zweifel mit Ihrer Treuhandstelle klären.</span></p>

<div class="modal" id="modal-tax" aria-hidden="true" role="dialog" aria-modal="true">
  <div class="modal-backdrop" data-modal-close></div>
  <form method="post" action="<?= e(url('admin/taxes')) ?>" class="modal-panel max-w-lg" data-defaults='{"label":"MWST","active":1,"sort":0,"tax_class":"taxable"}'><?= csrf_field() ?>
    <input type="hidden" name="id">
    <div class="flex items-center justify-between border-b border-slate-100 p-5 sm:px-6"><h3 class="text-base font-semibold text-slate-900" data-modal-heading>Steuervorlage</h3><button type="button" class="btn btn-ghost btn-icon" data-modal-close aria-label="Schliessen"><i class="bx bx-x text-2xl"></i></button></div>
    <div class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6">
      <div class="sm:col-span-2"><label class="label">Steuerart</label><select class="input" name="tax_class" data-taxclass><?php foreach (TaxService::CLASSES as $k => $c): if ($k === 'none') { continue; } ?><option value="<?= e($k) ?>"><?= e($c['name']) ?><?= $c['ziff'] ? ' · Ziff. ' . e($c['ziff']) : '' ?></option><?php endforeach; ?></select>
        <p class="hint">Steuerpflichtig = Inlandleistung mit Satz. Alle anderen Arten werden ohne MWST ausgewiesen und erscheinen mit eigenem Hinweis auf dem Beleg und in der MWST-Abrechnung.</p></div>
      <div class="sm:col-span-2"><label class="label">Bezeichnung <span class="text-rose-500">*</span></label><input class="input" name="name" required maxlength="120" placeholder="z. B. Normalsatz"></div>
      <div data-taxclass-rate><label class="label">Satz in %</label><input class="input tabular" name="rate" inputmode="decimal"></div>
      <div data-taxclass-label><label class="label">Kurzbezeichnung <span class="text-rose-500">*</span></label><input class="input" name="label" maxlength="40" placeholder="MWST"><p class="hint">Erscheint auf der Offerte.</p></div>
      <div><label class="label">Gültig ab <span class="font-normal text-slate-400">(optional)</span></label><input class="input" type="date" name="valid_from"></div>
      <div><label class="label">Gültig bis <span class="font-normal text-slate-400">(optional)</span></label><input class="input" type="date" name="valid_to"><p class="hint">Bei Satzänderungen: alten Satz befristen, neuen mit «Gültig ab» anlegen. Abgelaufene Sätze erscheinen in neuen Belegen nicht mehr.</p></div>
      <div><label class="label">Sortierung</label><input class="input" type="number" name="sort"></div>
      <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-700 sm:col-span-2"><input type="checkbox" class="checkbox" name="is_default" value="1">Standard (wird bei neuen Offerten vorgewählt)</label>
      <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-700 sm:col-span-2"><input type="checkbox" class="checkbox" name="active" value="1">Aktiv</label>
    </div>
    <div class="flex flex-col-reverse gap-2 rounded-b-2xl bg-slate-50 px-6 py-4 sm:flex-row sm:justify-end"><button type="button" class="btn btn-secondary" data-modal-close>Abbrechen</button><button class="btn btn-primary"><i class="bx bx-save"></i>Speichern</button></div>
  </form>
</div>
<script>
(function () {
  var sel = document.querySelector('[data-taxclass]'); if (!sel) return;
  var taxed = { taxable: 1, option: 1 };
  var f = function () {
    var form = sel.closest('form'), t = !!taxed[sel.value];
    form.querySelector('[data-taxclass-rate]').classList.toggle('hidden', !t);
    form.querySelector('[data-taxclass-label]').classList.toggle('hidden', sel.value !== 'taxable');
  };
  sel.addEventListener('change', f); document.addEventListener('click', function () { setTimeout(f, 30); }); f();
})();
</script>
