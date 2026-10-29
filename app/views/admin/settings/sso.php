<?php
$g = static fn (string $k, string $d = '') => e((string) Settings::get($k, $d));
$issuer = (string) Settings::get('sso_issuer', '');
$hasClient = trim((string) Settings::get('sso_client_id', '')) !== '';
$disc = json_decode((string) Settings::get('sso_discovery', ''), true);
?>
<form method="post" action="<?= e(url('admin/sso')) ?>" class="reveal card mt-6 max-w-4xl"><?= csrf_field() ?>
  <div class="grid gap-5 p-5 sm:grid-cols-6 sm:p-6">
    <label class="flex cursor-pointer items-start gap-3 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200/70 sm:col-span-6">
      <input type="checkbox" class="checkbox mt-0.5" name="sso_enabled" value="1" <?= Settings::get('sso_enabled') === '1' ? 'checked' : '' ?>>
      <span><span class="block text-sm font-semibold text-slate-900">Single Sign-On (OpenID Connect) aktivieren</span>
      <span class="block text-sm text-slate-500">Mitarbeitende melden sich mit ihrem Konto beim Anbieter an. Zwei-Faktor/Passkey übernimmt der Anbieter.</span></span>
    </label>
    <div class="sm:col-span-6">
      <label class="label" for="sso_issuer">Adresse des Anbieters</label>
      <div class="flex flex-col gap-2 sm:flex-row">
        <input class="input flex-1 font-mono text-[13px]" id="sso_issuer" name="sso_issuer" value="<?= e($issuer) ?>" placeholder="https://vylaris.tech" autocomplete="off" spellcheck="false">
        <button class="btn btn-secondary" formaction="<?= e(url('admin/sso/test')) ?>"><i class="bx bx-plug"></i>Verbindung testen</button>
      </div>
      <p class="hint">Nur die Adresse genügen – Endpunkte kommen aus <code>/.well-known/openid-configuration</code>, der Client wird automatisch registriert, sofern der Anbieter das unterstützt.
        <?php if ($issuer !== '' && is_array($disc) && !empty($disc['doc'])): ?><br><span class="text-emerald-700"><i class="bx bx-check-circle align-middle"></i> Konfiguration gefunden<?= $hasClient ? ' · Client eingerichtet' : '' ?>.</span><?php endif; ?></p>
    </div>
    <div class="sm:col-span-3"><label class="label" for="sso_label">Beschriftung des Buttons</label><input class="input" id="sso_label" name="sso_label" value="<?= $g('sso_label', 'Vylaris-Login') ?>" maxlength="40"><p class="hint">«Mit … anmelden»</p></div>
    <div class="sm:col-span-3"><label class="label" for="sso_allowed_domains">Zugelassene E-Mail-Domains <span class="font-normal text-slate-400">(optional)</span></label><input class="input" id="sso_allowed_domains" name="sso_allowed_domains" value="<?= $g('sso_allowed_domains') ?>" placeholder="firma.ch, partner.ch"></div>
    <label class="flex cursor-pointer items-start gap-3 sm:col-span-3"><input type="checkbox" class="checkbox mt-0.5" name="sso_auto_create" value="1" <?= Settings::get('sso_auto_create') === '1' ? 'checked' : '' ?>><span><span class="block text-sm font-medium text-slate-900">Konten automatisch anlegen</span><span class="block text-xs text-slate-500">Unbekannte Personen erhalten beim ersten Login ein Konto mit der Rolle «Team». Sonst nur bestehende Benutzer (Zuordnung per E-Mail).</span></span></label>
    <label class="flex cursor-pointer items-start gap-3 sm:col-span-3"><input type="checkbox" class="checkbox mt-0.5" name="sso_required" value="1" <?= Settings::get('sso_required') === '1' ? 'checked' : '' ?>><span><span class="block text-sm font-medium text-slate-900">Nur Single Sign-On erlauben</span><span class="block text-xs text-slate-500">Passwort und Anmeldelink werden ausgeblendet. Notfall-Zugang: <code>/admin/login?local=1</code>.</span></span></label>
    <div class="sm:col-span-6 rounded-xl bg-slate-50 p-4 text-sm ring-1 ring-slate-200/70">
      <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Redirect-URI (beim Anbieter eintragen, falls nicht automatisch registriert)</p>
      <p class="mt-1 break-all font-mono text-[13px] text-slate-800"><?= e(Oidc::redirectUri()) ?></p>
    </div>
    <details class="sm:col-span-6" <?= ($issuer !== '' && !$hasClient) ? 'open' : '' ?>>
      <summary class="cursor-pointer text-sm font-semibold text-slate-900"><i class="bx bx-cog align-middle text-slate-400"></i> Erweitert</summary>
      <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <div><label class="label" for="sso_client_id">Client-ID</label><input class="input font-mono text-[13px]" id="sso_client_id" name="sso_client_id" value="<?= $g('sso_client_id') ?>" autocomplete="off"><p class="hint">Leer lassen für automatische Registrierung.</p></div>
        <div><label class="label" for="sso_client_secret">Client-Secret</label><input class="input font-mono text-[13px]" type="password" id="sso_client_secret" name="sso_client_secret" value="" placeholder="<?= Settings::get('sso_client_secret', '') !== '' ? '•••••••• (gesetzt)' : '' ?>" autocomplete="new-password"></div>
        <div><label class="label" for="sso_scopes">Scopes</label><input class="input font-mono text-[13px]" id="sso_scopes" name="sso_scopes" value="<?= $g('sso_scopes', 'openid email profile') ?>"></div>
        <div><label class="label" for="sso_admin_group">Gruppe/Rolle für Administratoren</label><input class="input" id="sso_admin_group" name="sso_admin_group" value="<?= $g('sso_admin_group') ?>" placeholder="z. B. offerten-admin"><p class="hint">Gilt nur beim automatischen Anlegen (Claim <code>groups</code> oder <code>roles</code>). Bestehende Rollen werden nie geändert.</p></div>
      </div>
    </details>
  </div>
  <div class="flex justify-end rounded-b-2xl border-t border-slate-100 bg-slate-50/50 px-5 py-4 sm:px-6"><button class="btn btn-primary"><i class="bx bx-save"></i>Speichern</button></div>
</form>
<p class="mt-3 max-w-4xl text-sm text-slate-500">Die Anmeldung läuft über den Autorisierungscode-Ablauf mit PKCE; ID-Token werden auf Signatur, Aussteller, Empfänger, Gültigkeit und nonce geprüft.</p>
