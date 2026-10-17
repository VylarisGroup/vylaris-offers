<?php
$title = 'Nachrichten: ' . customer_name($c);
$subtitle = e($c['email']) . ' · Kunden-Nr. ' . e($c['customer_no']) . (empty($c['portal_enabled']) ? ' · <span class="text-amber-600">Portalzugang deaktiviert</span>' : '');
$back = ['admin/messages', 'Nachrichten'];
$actions = '<a href="' . e(url('admin/customers/' . $c['id'])) . '" class="btn btn-secondary btn-sm"><i class="bx bx-user-pin"></i>Kundenprofil</a>';
include APP_DIR . '/views/partials/page-header.php';
$refs = PortalMessage::refsFor((int) $c['id']);
$viewer = 'team';
?>
<div class="reveal card max-w-3xl">
  <?php include APP_DIR . '/views/partials/message-thread.php'; ?>
  <form method="post" action="<?= e(url('admin/messages/' . $c['id'])) ?>" class="space-y-3 border-t border-slate-100 bg-slate-50/60 p-5 sm:p-6" data-wizard-skip><?= csrf_field() ?>
    <label class="label" for="body">Antwort</label>
    <textarea class="input" id="body" name="body" rows="4" maxlength="<?= PortalMessage::MAX_LEN ?>" required placeholder="Ihre Antwort an <?= e(customer_name($c)) ?> …"></textarea>
    <div class="flex flex-wrap items-center gap-3">
      <select class="input !w-auto max-w-full" name="ref" aria-label="Bezug"><option value="">Kein Bezug</option><?php foreach ($refs as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select>
      <button class="btn btn-primary ml-auto"><i class="bx bx-send"></i>Senden</button>
    </div>
    <p class="hint">Der Kunde erhält eine E-Mail mit Link zum Portal; der Text selbst steht nur im Portal.</p>
  </form>
</div>
