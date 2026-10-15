<?php
$title = 'Produktbereiche';
$subtitle = 'Jeder Bereich gehört zur Kategorie <strong>Digital</strong> (Download, Zugang, Lizenz) oder <strong>Physisch</strong> (Versand, Lieferadresse, Tracking).';
$actions = '<button type="button" class="btn btn-primary" data-modal-open="modal-area" data-fill data-modal-title="Neuer Produktbereich"><i class="bx bx-plus"></i>Neuer Bereich</button>';
include APP_DIR . '/views/partials/page-header.php';
?>
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
  <?php foreach ($areas as $i => $a): ?>
    <div class="reveal reveal-delay-<?= $i % 3 ?> card card-hover flex flex-col p-5<?= $a['active'] ? '' : ' opacity-60' ?>">
      <div class="flex items-start justify-between gap-3">
        <span class="kpi-icon <?= $a['type'] === 'digital' ? 'bg-sky-50 text-sky-600' : 'bg-amber-50 text-amber-600' ?>"><i class="bx <?= e($a['icon']) ?>"></i></span>
        <div class="flex gap-1">
          <button type="button" class="btn btn-ghost btn-icon" data-modal-open="modal-area" data-modal-title="Produktbereich bearbeiten" data-fill="<?= json_attr(array_intersect_key($a, array_flip(['id', 'name', 'type', 'description', 'icon', 'active', 'sort']))) ?>" data-tip="Bearbeiten" aria-label="Bearbeiten"><i class="bx bx-edit text-lg"></i></button>
          <form method="post" action="<?= e(url('admin/areas/' . $a['id'] . '/delete')) ?>" data-confirm="Produktbereich „<?= e($a['name']) ?>“ löschen?" data-confirm-label="Löschen"><?= csrf_field() ?>
            <button class="btn btn-ghost btn-icon text-slate-400 hover:text-rose-600" data-tip="Löschen" aria-label="Löschen"><i class="bx bx-trash text-lg"></i></button></form>
        </div>
      </div>
      <h3 class="mt-4 font-semibold text-slate-900"><?= e($a['name']) ?></h3>
      <p class="mt-1 flex-1 text-sm text-slate-500"><?= e($a['description'] ?: '–') ?></p>
      <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
        <span class="badge <?= $a['type'] === 'digital' ? 'badge-sky' : 'badge-amber' ?>"><i class="bx <?= $a['type'] === 'digital' ? 'bx-cloud' : 'bx-box' ?>"></i><?= $a['type'] === 'digital' ? 'Digital' : 'Physisch' ?></span>
        <?php if (!$a['active']): ?><span class="badge badge-slate">inaktiv</span><?php endif; ?>
        <span class="ml-auto text-xs text-slate-400"><?= (int) $a['offer_count'] ?> Offerten · <?= (int) $a['template_count'] ?> Vorlagen</span>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="reveal mt-8 grid gap-4 md:grid-cols-2">
  <div class="rounded-2xl bg-sky-50/60 p-5 ring-1 ring-sky-100">
    <p class="flex items-center gap-2 font-semibold text-sky-900"><i class="bx bx-cloud text-xl"></i>Kategorie Digital</p>
    <p class="mt-1.5 text-sm leading-relaxed text-sky-900/80">Keine Versandkosten. Im Auftrag: Liefer-E-Mail, Download-/Zugangslink sowie Zugangsdaten und Lizenzschlüssel, die der Kunde im Portal sieht.</p>
  </div>
  <div class="rounded-2xl bg-amber-50/60 p-5 ring-1 ring-amber-100">
    <p class="flex items-center gap-2 font-semibold text-amber-900"><i class="bx bx-box text-xl"></i>Kategorie Physisch</p>
    <p class="mt-1.5 text-sm leading-relaxed text-amber-900/80">Versandart und -kosten in der Offerte, abweichende Lieferadresse. Im Auftrag: Versandstatus, Transporteur, Sendungsnummer und Tracking-Link.</p>
  </div>
</div>

<div class="modal" id="modal-area" aria-hidden="true" role="dialog" aria-modal="true">
  <div class="modal-backdrop" data-modal-close></div>
  <form method="post" action="<?= e(url('admin/areas')) ?>" class="modal-panel max-w-lg" data-defaults='{"type":"digital","icon":"bx-category","active":1,"sort":0}'><?= csrf_field() ?>
    <input type="hidden" name="id">
    <div class="flex items-center justify-between border-b border-slate-100 p-5 sm:px-6"><h3 class="text-base font-semibold text-slate-900" data-modal-heading>Produktbereich</h3><button type="button" class="btn btn-ghost btn-icon" data-modal-close aria-label="Schliessen"><i class="bx bx-x text-2xl"></i></button></div>
    <div class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6">
      <div class="sm:col-span-2"><label class="label">Name <span class="text-rose-500">*</span></label><input class="input" name="name" required maxlength="120"></div>
      <div class="sm:col-span-2"><span class="label">Kategorie</span>
        <div class="grid grid-cols-2 gap-2">
          <?php foreach (['digital' => ['Digital', 'bx-cloud'], 'physical' => ['Physisch', 'bx-box']] as $k => [$l, $ic]): ?>
          <label class="flex cursor-pointer items-center gap-2 rounded-xl p-3 text-sm font-medium ring-1 ring-slate-200 transition hover:ring-slate-300 has-[:checked]:bg-brand-50 has-[:checked]:ring-2 has-[:checked]:ring-brand-500"><input type="radio" class="sr-only" name="type" value="<?= $k ?>"><i class="bx <?= $ic ?> text-xl text-slate-400"></i><?= $l ?></label>
          <?php endforeach; ?>
        </div></div>
      <div class="sm:col-span-2"><label class="label">Beschreibung</label><textarea class="input" name="description" rows="2"></textarea></div>
      <div><label class="label">Icon <a class="font-normal text-slate-400 hover:text-brand-600" href="https://boxicons.com/" target="_blank" rel="noopener" data-tip="Namen auf boxicons.com nachschlagen"><i class="bx bx-link-external align-middle"></i></a></label>
        <input class="input font-mono text-[13px]" name="icon" list="icon-list" placeholder="bx-category">
        <datalist id="icon-list"><?php foreach (['bx-category', 'bx-package', 'bx-box', 'bx-cloud-download', 'bx-code-alt', 'bx-palette', 'bx-wrench', 'bx-laptop', 'bx-book', 'bx-camera', 'bx-car', 'bx-cart', 'bx-briefcase', 'bx-paint', 'bx-printer', 'bx-server'] as $ic): ?><option value="<?= $ic ?>"><?php endforeach; ?></datalist></div>
      <div><label class="label">Sortierung</label><input class="input" type="number" name="sort"></div>
      <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-700 sm:col-span-2"><input type="checkbox" class="checkbox" name="active" value="1">Aktiv (in neuen Offerten wählbar)</label>
    </div>
    <div class="flex flex-col-reverse gap-2 rounded-b-2xl bg-slate-50 px-6 py-4 sm:flex-row sm:justify-end"><button type="button" class="btn btn-secondary" data-modal-close>Abbrechen</button><button class="btn btn-primary"><i class="bx bx-save"></i>Speichern</button></div>
  </form>
</div>
