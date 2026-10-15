<?php
$title = 'Zweiter Faktor';
$scripts = ['js/passkey.js'];
$hasTotp = !empty($hasTotp);
$hasPasskey = !empty($hasPasskey);
?>
<div class="reveal mx-auto max-w-md pt-6">
  <div class="card p-8">
    <span class="mx-auto grid size-16 place-items-center rounded-2xl bg-brand-50 text-4xl text-brand-600"><i class="bx <?= $hasPasskey ? 'bx-fingerprint' : 'bx-shield-quarter' ?>"></i></span>
    <h1 class="mt-5 text-center text-xl font-bold text-slate-900">Zweiter Faktor</h1>
    <p class="mt-2 text-center text-sm leading-relaxed text-slate-600">
      <?php if ($hasPasskey): ?>Bestätigen Sie die Anmeldung für <strong><?= e($email) ?></strong> mit Ihrem Passkey<?= $hasTotp ? ' oder dem Code aus Ihrer Authenticator-App' : '' ?>.
      <?php else: ?>Geben Sie den 6-stelligen Code aus Ihrer Authenticator-App für <strong><?= e($email) ?></strong> ein.<?php endif; ?>
    </p>
    <?php if (!empty($error)): ?><p class="mt-4 flex items-center gap-2 rounded-xl bg-rose-50 px-3.5 py-2.5 text-sm text-rose-700 ring-1 ring-rose-200"><i class="bx bx-error-circle text-lg"></i><?= e($error) ?></p><?php endif; ?>
    <?php if ($hasPasskey): ?>
    <div class="mt-5" data-passkey-login data-options-url="<?= e(url('admin/2fa/passkey/options')) ?>" data-verify-url="<?= e(url('admin/2fa/passkey/verify')) ?>" data-csrf="<?= e(csrf_token()) ?>">
      <button type="button" class="btn btn-primary btn-lg w-full" data-passkey-start autofocus><i class="bx bx-fingerprint"></i>Mit Passkey bestätigen</button>
      <p class="mt-2 hidden text-center text-sm text-rose-600" data-passkey-error role="alert"></p>
    </div>
    <p class="my-4 text-center text-xs uppercase tracking-wide text-slate-400">oder mit Code</p>
    <?php endif; ?>
    <form method="post" action="<?= e(url('admin/2fa')) ?>" class="<?= $hasPasskey ? '' : 'mt-5 ' ?>space-y-4"><?= csrf_field() ?>
      <input class="input text-center text-xl tracking-[.3em]" name="code" inputmode="text" autocomplete="one-time-code" placeholder="<?= $hasTotp ? '123 456' : 'a1b2c-d3e4f' ?>" <?= $hasPasskey ? '' : 'autofocus' ?> required maxlength="20" aria-label="Bestätigungscode">
      <button class="btn <?= $hasPasskey ? 'btn-secondary' : 'btn-primary' ?> btn-lg w-full"><i class="bx bx-log-in"></i>Bestätigen</button>
      <p class="text-center text-xs text-slate-500"><?= $hasTotp ? 'Gerät verloren? Geben Sie stattdessen' : 'Gerät verloren? Geben Sie' ?> einen Wiederherstellungscode ein (z. B. <span class="font-mono">a1b2c-d3e4f</span>).</p>
    </form>
    <form method="post" action="<?= e(url('admin/2fa/cancel')) ?>" class="mt-3 text-center"><?= csrf_field() ?><button class="btn btn-ghost btn-sm">Abbrechen</button></form>
  </div>
</div>
