<?php $title = 'Dokumente'; ?>
<div class="reveal pt-4">
  <p class="text-sm font-medium text-brand-700">Kundenportal</p>
  <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Dokumente</h1>
  <p class="mt-1 text-sm text-slate-500">Alle Offerten, Rechnungen und Dateien an einem Ort – und die Möglichkeit, uns Unterlagen zu senden.</p>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-5">
  <div class="space-y-6 lg:col-span-3">
    <section class="reveal card overflow-hidden">
      <div class="border-b border-slate-100 px-5 py-4 sm:px-6"><h2 class="text-sm font-semibold text-slate-900"><i class="bx bx-file align-middle text-slate-400"></i> Offerten und Rechnungen (PDF)</h2></div>
      <?php if (!$offers && !$invoices): ?>
        <p class="px-5 py-8 text-center text-sm text-slate-500 sm:px-6">Noch keine Belege vorhanden.</p>
      <?php else: ?>
      <ul class="divide-y divide-slate-100">
        <?php foreach ($offers as $o): ?>
        <li><a href="<?= e(url('o/' . $o['public_id'] . '/pdf')) ?>" class="group flex items-center gap-3 px-5 py-3 transition hover:bg-slate-50 sm:px-6">
          <i class="bx bxs-file-pdf text-2xl text-rose-400"></i>
          <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium text-slate-900 group-hover:text-brand-700">Offerte <?= e($o['number']) ?></span><span class="block truncate text-xs text-slate-500"><?= e($o['title']) ?> · <?= fmt_date($o['created_at']) ?></span></span>
          <i class="bx bx-download text-lg text-slate-400"></i></a></li>
        <?php endforeach; ?>
        <?php foreach ($invoices as $i): ?>
        <li><a href="<?= e(url('r/' . $i['public_id'] . '/pdf')) ?>" class="group flex items-center gap-3 px-5 py-3 transition hover:bg-slate-50 sm:px-6">
          <i class="bx bxs-file-pdf text-2xl text-rose-400"></i>
          <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium text-slate-900 group-hover:text-brand-700">Rechnung <?= e($i['number']) ?></span><span class="block truncate text-xs text-slate-500"><?= e($i['title']) ?> · <?= fmt_date($i['issue_date']) ?></span></span>
          <i class="bx bx-download text-lg text-slate-400"></i></a></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </section>

    <section class="reveal card overflow-hidden">
      <div class="border-b border-slate-100 px-5 py-4 sm:px-6"><h2 class="text-sm font-semibold text-slate-900"><i class="bx bx-paperclip align-middle text-slate-400"></i> Dateien</h2></div>
      <?php if (!$files): ?>
        <p class="px-5 py-8 text-center text-sm text-slate-500 sm:px-6">Noch keine Dateien. Hier erscheinen Unterlagen, die wir für Sie freigeben, sowie Ihre eigenen Uploads.</p>
      <?php else: ?>
      <ul class="divide-y divide-slate-100">
        <?php foreach ($files as $a): ?>
        <li class="flex items-center gap-3 px-5 py-3 sm:px-6">
          <a href="<?= e(url('portal/documents/' . $a['id'])) ?>" class="group flex min-w-0 flex-1 items-center gap-3">
            <i class="bx bx-file text-2xl text-slate-400 group-hover:text-brand-600"></i>
            <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium text-slate-900 group-hover:text-brand-700"><?= e($a['original_name']) ?></span>
              <span class="block truncate text-xs text-slate-500"><?= $a['by_customer'] ? 'Von Ihnen' : 'Von uns' ?> · <?= e(AttachmentService::human((int) $a['size'])) ?> · <?= fmt_date($a['created_at']) ?> · <?= e(['offer' => 'Offerte', 'order' => 'Auftrag', 'invoice' => 'Rechnung'][$a['entity_type']] ?? '') ?> <?= e($a['doc_number']) ?></span></span>
            <i class="bx bx-download text-lg text-slate-400"></i></a>
          <?php if ($a['by_customer']): ?>
          <form method="post" action="<?= e(url('portal/documents/' . $a['id'] . '/delete')) ?>" data-confirm="Datei „<?= e($a['original_name']) ?>“ löschen?" data-confirm-label="Löschen"><?= csrf_field() ?>
            <button class="btn btn-ghost btn-icon size-8 text-rose-500" aria-label="Datei löschen"><i class="bx bx-trash"></i></button></form>
          <?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </section>
  </div>

  <aside class="reveal lg:col-span-2">
    <section class="card p-5 sm:p-6">
      <h2 class="text-sm font-semibold text-slate-900"><i class="bx bx-upload align-middle text-slate-400"></i> Datei an uns senden</h2>
      <?php if (!$orders): ?>
        <p class="mt-2 text-sm text-slate-500">Sobald ein Auftrag eröffnet ist, können Sie hier Unterlagen (z. B. Logos, Texte, Pläne) hochladen.</p>
      <?php else: ?>
      <form method="post" enctype="multipart/form-data" action="<?= e(url('portal/documents')) ?>" class="mt-3 space-y-3"><?= csrf_field() ?>
        <div><label class="label" for="order_id">Zu welchem Auftrag?</label>
          <select class="input" id="order_id" name="order_id" required><?php foreach ($orders as $o): ?><option value="<?= (int) $o['id'] ?>"><?= e($o['number'] . ' – ' . mb_strimwidth((string) $o['title'], 0, 40, '…')) ?></option><?php endforeach; ?></select></div>
        <div><label class="label" for="file">Datei</label>
          <input id="file" type="file" name="file" required class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200"></div>
        <button class="btn btn-primary w-full"><i class="bx bx-upload"></i>Hochladen</button>
        <p class="hint">Max. <?= e(AttachmentService::limitLabel()) ?> · PDF, Bilder, Office, ZIP, TXT/CSV</p>
      </form>
      <?php endif; ?>
    </section>
  </aside>
</div>
