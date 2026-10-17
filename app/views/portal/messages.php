<?php $title = 'Nachrichten'; $viewer = 'customer'; ?>
<div class="reveal pt-4">
  <p class="text-sm font-medium text-brand-700">Kundenportal</p>
  <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Nachrichten</h1>
  <p class="mt-1 text-sm text-slate-500">Schreiben Sie uns direkt – zu einer Offerte, einem Auftrag, einer Rechnung oder allgemein.</p>
</div>
<div class="reveal mt-6 card">
  <?php include APP_DIR . '/views/partials/message-thread.php'; ?>
  <form method="post" action="<?= e(url('portal/messages')) ?>" class="space-y-3 border-t border-slate-100 bg-slate-50/60 p-5 sm:p-6"><?= csrf_field() ?>
    <label class="label" for="body">Neue Nachricht</label>
    <textarea class="input" id="body" name="body" rows="4" maxlength="<?= PortalMessage::MAX_LEN ?>" required placeholder="Wie können wir helfen?"></textarea>
    <div class="flex flex-wrap items-center gap-3">
      <select class="input !w-auto max-w-full" name="ref" aria-label="Betrifft"><option value="">Allgemeine Frage</option><?php foreach ($refs as $k => $l): ?><option value="<?= e($k) ?>"<?= $preset === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
      <button class="btn btn-primary ml-auto"><i class="bx bx-send"></i>Senden</button>
    </div>
  </form>
</div>
