<?php
$title = 'Zwei-Faktor-Anmeldung einrichten';
$back = ['admin/profile', 'Mein Profil'];
include APP_DIR . '/views/partials/page-header.php';
?>
<div class="reveal card max-w-2xl p-5 sm:p-6">
  <ol class="space-y-6 text-sm text-slate-600">
    <li><p class="font-semibold text-slate-900">1. Authenticator-App öffnen</p><p class="mt-1">Scannen Sie den QR-Code mit Ihrer App – oder geben Sie den Schlüssel manuell ein.</p>
      <div class="mt-3 flex flex-col items-start gap-4 sm:flex-row sm:items-center">
        <div class="rounded-2xl bg-white p-2 ring-1 ring-slate-200"><?= $qr ?></div>
        <div><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Schlüssel</p>
          <p class="mt-1 flex items-center gap-2 font-mono text-sm tracking-wider text-slate-900"><?= e(trim(chunk_split($secret, 4, ' '))) ?>
            <button type="button" class="btn btn-ghost btn-icon size-7" data-copy="<?= e($secret) ?>" data-tip="Kopieren"><i class="bx bx-copy"></i></button></p>
          <p class="mt-1 text-xs text-slate-400">Zeitbasiert (TOTP), 6 Stellen, SHA-1, 30 Sekunden</p></div></div></li>
    <li><p class="font-semibold text-slate-900">2. Code bestätigen</p>
      <form method="post" action="<?= e(url('admin/profile/2fa/enable')) ?>" class="mt-2 flex max-w-sm gap-2"><?= csrf_field() ?>
        <input class="input text-center tracking-[.25em]" name="code" inputmode="numeric" autocomplete="one-time-code" placeholder="123 456" required autofocus maxlength="7">
        <button class="btn btn-primary"><i class="bx bx-check"></i>Aktivieren</button></form>
      <p class="mt-2 text-xs text-slate-400">Danach erhalten Sie 8 Wiederherstellungscodes für den Notfall.</p></li>
  </ol>
</div>
