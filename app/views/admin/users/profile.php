<?php
$title = 'Mein Profil';
$subtitle = e($user['email']) . ' · ' . ($user['role'] === 'admin' ? 'Administrator' : 'Team');
$scripts = ['js/passkey.js'];
include APP_DIR . '/views/partials/page-header.php';
?>
<form method="post" action="<?= e(url('admin/profile')) ?>" class="reveal card max-w-2xl"><?= csrf_field() ?>
  <div class="grid gap-5 p-5 sm:p-6">
    <div><label class="label" for="name">Name</label><input class="input" id="name" name="name" value="<?= e($user['name']) ?>" required maxlength="120"></div>
    <div class="rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200/70">
      <p class="text-sm font-semibold text-slate-900">Passwort ändern</p>
      <p class="mt-0.5 text-xs text-slate-500">Leer lassen, um das bisherige Passwort zu behalten. Alternativ ist die Anmeldung jederzeit per Anmeldelink möglich.</p>
      <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <div><label class="label" for="password">Neues Passwort</label><input class="input" type="password" id="password" name="password" autocomplete="new-password" minlength="10"></div>
        <div><label class="label" for="password_confirm">Wiederholen</label><input class="input" type="password" id="password_confirm" name="password_confirm" autocomplete="new-password"></div>
      </div>
    </div>
  </div>
  <div class="flex justify-end rounded-b-2xl border-t border-slate-100 bg-slate-50/50 px-5 py-4 sm:px-6"><button class="btn btn-primary"><i class="bx bx-save"></i>Speichern</button></div>
</form>

<section class="reveal card mt-6 max-w-2xl p-5 sm:p-6">
  <div class="flex items-start gap-4">
    <span class="grid size-11 shrink-0 place-items-center rounded-xl <?= $user['totp_enabled'] ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500' ?> text-2xl"><i class="bx bx-shield-quarter"></i></span>
    <div class="flex-1">
      <h2 class="text-sm font-semibold text-slate-900">Zwei-Faktor-Anmeldung <span class="badge <?= $user['totp_enabled'] ? 'badge-emerald' : 'badge-slate' ?> ml-1"><?= $user['totp_enabled'] ? 'aktiv' : 'optional · aus' ?></span></h2>
      <p class="mt-1 text-sm text-slate-500">Zusätzlich zu Passwort bzw. Anmeldelink verlangt die Anmeldung einen zweiten Faktor: einen <strong>Passkey</strong> (Fingerabdruck, Gesichtserkennung, Geräte-PIN oder Sicherheitsschlüssel) und/oder einen Code aus einer <strong>Authenticator-App</strong>. Sie können beides kombinieren.</p>
      <?php if ($user['totp_enabled']): ?><p class="mt-2 text-xs text-slate-500">Verbleibende Wiederherstellungscodes: <strong><?= (int) $user['recovery_left'] ?></strong></p><?php endif; ?>
    </div>
  </div>

  <div class="mt-6 border-t border-slate-100 pt-5">
    <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-900"><i class="bx bx-fingerprint text-lg text-slate-400"></i>Passkeys</h3>
    <?php if ($passkeys): ?>
    <ul class="mt-3 divide-y divide-slate-100 rounded-xl ring-1 ring-slate-200/80">
      <?php foreach ($passkeys as $pk): ?>
      <li class="flex flex-col gap-2 p-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0"><p class="truncate text-sm font-medium text-slate-900"><i class="bx bx-key align-middle text-slate-400"></i> <?= e($pk['name']) ?></p>
          <p class="text-xs text-slate-500">Hinzugefügt am <?= fmt_date($pk['created_at']) ?> · <?= $pk['last_used_at'] ? 'zuletzt verwendet ' . fmt_datetime($pk['last_used_at']) : 'noch nicht verwendet' ?></p></div>
        <form method="post" action="<?= e(url('admin/profile/passkeys/' . $pk['id'] . '/delete')) ?>" class="flex items-center gap-2" data-confirm="Passkey „<?= e($pk['name']) ?>“ entfernen?" data-confirm-label="Entfernen"><?= csrf_field() ?>
          <input class="input h-9 w-40 text-sm" title="<?= $user['totp'] ? 'Code der Authenticator-App oder Wiederherstellungscode' : 'Wiederherstellungscode' ?>" name="code" placeholder="Code bestätigen" autocomplete="off" required aria-label="Bestätigungscode">
          <button class="btn btn-ghost btn-icon text-slate-400 hover:text-rose-600" data-tip="Entfernen" aria-label="Passkey entfernen"><i class="bx bx-trash text-lg"></i></button></form>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php else: ?>
    <p class="mt-1 text-sm text-slate-500">Noch kein Passkey eingerichtet.</p>
    <?php endif; ?>
    <?php if ($passkeyAvailable): ?>
    <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-end" data-passkey-register data-options-url="<?= e(url('admin/profile/passkeys/options')) ?>" data-register-url="<?= e(url('admin/profile/passkeys/register')) ?>" data-csrf="<?= e(csrf_token()) ?>">
      <div class="flex-1"><label class="label" for="pk-name">Bezeichnung</label><input class="input" id="pk-name" data-passkey-name maxlength="80" placeholder="z. B. MacBook, iPhone, YubiKey"></div>
      <button type="button" class="btn btn-secondary" data-passkey-start><i class="bx bx-plus"></i>Passkey hinzufügen</button>
    </div>
    <p class="mt-2 hidden text-sm text-rose-600" data-passkey-error role="alert"></p>
    <p class="mt-2 hidden text-sm text-slate-500" data-passkey-unsupported>Dieser Browser unterstützt keine Passkeys.</p>
    <?php else: ?>
    <p class="mt-3 rounded-xl bg-amber-50 px-3.5 py-2.5 text-sm text-amber-800 ring-1 ring-amber-200">Passkeys sind erst nach Umstellung der Basis-URL auf <strong>HTTPS</strong> verfügbar.</p>
    <?php endif; ?>
  </div>

  <div class="mt-6 border-t border-slate-100 pt-5">
    <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-900"><i class="bx bx-mobile-alt text-lg text-slate-400"></i>Authenticator-App <span class="badge <?= $user['totp'] ? 'badge-emerald' : 'badge-slate' ?>"><?= $user['totp'] ? 'aktiv' : 'aus' ?></span></h3>
    <p class="mt-1 text-sm text-slate-500">6-stelliger Code aus einer Authenticator-App (z. B. Google/Microsoft Authenticator, 1Password, Aegis).</p>
    <?php if (!$user['totp']): ?>
      <a href="<?= e(url('admin/profile/2fa')) ?>" class="btn btn-secondary mt-3"><i class="bx bx-shield-plus"></i>Einrichten</a>
    <?php else: ?>
      <form method="post" action="<?= e(url('admin/profile/2fa/disable')) ?>" class="mt-3 space-y-2" data-confirm="Authenticator-App wirklich entfernen?" data-confirm-label="Entfernen"><?= csrf_field() ?>
        <label class="label">Entfernen</label>
        <input class="input" name="code" placeholder="Aktueller Code oder Wiederherstellungscode" autocomplete="one-time-code" required>
        <button class="btn btn-ghost text-rose-600 hover:bg-rose-50"><i class="bx bx-shield-x"></i>Authenticator-App entfernen</button></form>
    <?php endif; ?>
  </div>

  <?php if ($user['totp_enabled']): ?>
  <div class="mt-6 border-t border-slate-100 pt-5">
    <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-900"><i class="bx bx-lifebuoy text-lg text-slate-400"></i>Wiederherstellungscodes</h3>
    <p class="mt-1 text-sm text-slate-500">Einmalcodes für den Notfall (Gerät verloren). Neue Codes machen die bisherigen ungültig.</p>
    <form method="post" action="<?= e(url('admin/profile/2fa/regenerate')) ?>" class="mt-3 flex flex-col gap-2 sm:flex-row"><?= csrf_field() ?>
      <input class="input" name="code" placeholder="<?= $user['totp'] ? 'Aktueller App- oder Wiederherstellungscode' : 'Ein bisheriger Wiederherstellungscode' ?>" autocomplete="off" required>
      <button class="btn btn-secondary shrink-0"><i class="bx bx-refresh"></i>Neu erzeugen</button></form>
  </div>
  <?php endif; ?>
</section>
