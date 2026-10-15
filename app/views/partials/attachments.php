<?php
/** Staff-Karte „Anhänge“. @var string $attType  @var int $attId */
$attachments = AttachmentService::forEntity($attType, (int) $attId);
?>
<section class="reveal card p-5 sm:p-6">
  <h2 class="text-sm font-semibold text-slate-900"><i class="bx bx-paperclip align-middle text-slate-400"></i> Anhänge</h2>
  <?php if ($attachments): ?>
  <ul class="mt-3 divide-y divide-slate-100">
    <?php foreach ($attachments as $a): ?>
      <li class="flex items-center gap-3 py-2.5">
        <i class="bx bx-file text-xl text-slate-400"></i>
        <div class="min-w-0 flex-1">
          <a class="block truncate text-sm font-medium text-slate-900 hover:text-brand-700" href="<?= e(url('admin/attachments/' . $a['id'])) ?>"><?= e($a['original_name']) ?></a>
          <p class="text-xs text-slate-500"><?= e(AttachmentService::human((int) $a['size'])) ?> · <?= fmt_date($a['created_at']) ?></p>
        </div>
        <form method="post" action="<?= e(url('admin/attachments/' . $a['id'] . '/visibility')) ?>"><?= csrf_field() ?>
          <button class="badge <?= $a['customer_visible'] ? 'badge-emerald' : 'badge-slate' ?>" data-tip="<?= $a['customer_visible'] ? 'Für Kunden sichtbar – klicken zum Verbergen' : 'Nur intern – klicken, um für Kunden freizugeben' ?>"><i class="bx <?= $a['customer_visible'] ? 'bx-show' : 'bx-hide' ?>"></i><?= $a['customer_visible'] ? 'Kunde' : 'intern' ?></button></form>
        <form method="post" action="<?= e(url('admin/attachments/' . $a['id'] . '/delete')) ?>" data-confirm="Anhang „<?= e($a['original_name']) ?>“ löschen?" data-confirm-label="Löschen"><?= csrf_field() ?>
          <button class="btn btn-ghost btn-icon size-8 text-rose-500" aria-label="Löschen"><i class="bx bx-trash"></i></button></form>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php else: ?><p class="mt-2 text-sm text-slate-500">Noch keine Anhänge.</p><?php endif; ?>
  <form method="post" enctype="multipart/form-data" action="<?= e(url('admin/attachments/add/' . $attType . '/' . (int) $attId)) ?>" class="mt-4 space-y-2 border-t border-slate-100 pt-4"><?= csrf_field() ?>
    <input type="file" name="file" required class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
    <label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" class="checkbox" name="customer_visible" value="1">Für den Kunden sichtbar</label>
    <button class="btn btn-secondary w-full"><i class="bx bx-upload"></i>Hochladen</button>
    <p class="hint">Max. <?= e(AttachmentService::limitLabel()) ?> · PDF, Bilder, Office, ZIP, TXT/CSV</p>
  </form>
</section>
