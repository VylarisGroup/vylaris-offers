<?php
$title = 'Einstellungen';
$subtitle = 'Firmendaten, Offerten-Standards und E-Mail-Versand.';
include APP_DIR . '/views/partials/page-header.php';
$tabs = ['company' => ['Firma', 'bx-buildings'], 'offers' => ['Offerten', 'bx-file'], 'invoices' => ['Rechnungen', 'bx-receipt'], 'payments' => ['Zahlungen', 'bx-credit-card'], 'branding' => ['Branding', 'bx-palette'], 'mail' => ['E-Mail', 'bx-envelope'], 'privacy' => ['Datenschutz', 'bx-shield-alt-2']];
$tabs['sso'] = ['Single Sign-On', 'bx-key'];
$tab = isset($tabs[$tab]) ? $tab : 'company';
$v = static fn(string $k) => e($s[$k] ?? '');
?>
<nav class="reveal mb-6 inline-flex gap-1 rounded-xl bg-slate-200/60 p-1">
  <?php foreach ($tabs as $k => [$l, $ic]): ?><a href="?tab=<?= $k ?>" class="tab<?= $tab === $k ? ' is-active' : '' ?>"><i class="bx <?= $ic ?>"></i><?= $l ?></a><?php endforeach; ?>
</nav>

<?php if ($tab === 'branding'): include APP_DIR . '/views/admin/settings/branding.php'; elseif ($tab === 'sso'): include APP_DIR . '/views/admin/settings/sso.php'; else: ?>
<form method="post" action="<?= e(url('admin/settings')) ?>" class="reveal card max-w-4xl"><?= csrf_field() ?>
  <input type="hidden" name="tab" value="<?= e($tab) ?>">
  <div class="grid gap-5 p-5 sm:grid-cols-6 sm:p-6">
  <?php if ($tab === 'company'): ?>
    <div class="sm:col-span-6"><label class="label">Firmenname</label><input class="input" name="company_name" value="<?= $v('company_name') ?>" required></div>
    <div class="sm:col-span-6"><label class="label">Strasse und Nr.</label><input class="input" name="company_street" value="<?= $v('company_street') ?>"></div>
    <div class="sm:col-span-2"><label class="label">PLZ</label><input class="input" name="company_zip" value="<?= $v('company_zip') ?>"></div>
    <div class="sm:col-span-4"><label class="label">Ort</label><input class="input" name="company_city" value="<?= $v('company_city') ?>"></div>
    <div class="sm:col-span-6 border-t border-slate-100 pt-5"><h3 class="text-sm font-semibold text-slate-900">Mehrwertsteuer (Schweiz)</h3><p class="hint">Gemäss MWSTG sind Unternehmen ab CHF 100’000 Jahresumsatz steuerpflichtig. Nicht steuerpflichtige Unternehmen dürfen keine MWST ausweisen.</p></div>
    <label class="flex cursor-pointer items-start gap-3 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200/70 sm:col-span-6"><input type="checkbox" class="checkbox mt-0.5" name="mwst_liable" value="1" <?= ($s['mwst_liable'] ?? '1') === '1' ? 'checked' : '' ?>><span><span class="block text-sm font-medium text-slate-900">Wir sind MWST-pflichtig</span><span class="block text-xs text-slate-500">Ohne Haken wird auf Offerten und Rechnungen keine MWST ausgewiesen; es erscheint der Hinweis „Nicht MWST-pflichtig“.</span></span></label>
    <div class="sm:col-span-3"><label class="label">UID / MWST-Nummer</label><input class="input" name="company_vat_id" value="<?= $v('company_vat_id') ?>" placeholder="CHE-123.456.789"><p class="hint">Wird auf Rechnungen als „CHE-123.456.789 MWST“ (bzw. ohne Zusatz, wenn nicht pflichtig) ausgegeben. Die Prüfziffer wird kontrolliert.</p></div>
    <div class="sm:col-span-3"><label class="label">Abrechnungsart</label><select class="input" name="mwst_method"><option value="agreed"<?= ($s['mwst_method'] ?? 'agreed') === 'agreed' ? ' selected' : '' ?>>Nach vereinbarten Entgelten (Rechnungsstellung)</option><option value="received"<?= ($s['mwst_method'] ?? '') === 'received' ? ' selected' : '' ?>>Nach vereinnahmten Entgelten (Zahlungseingang)</option></select><p class="hint">Bestimmt, in welcher Periode die MWST im MWST-Bericht erscheint.</p></div>
    <div class="sm:col-span-3"><label class="label">Abrechnungsperiode</label><select class="input" name="mwst_period"><?php foreach (['quarter' => 'Vierteljährlich', 'semester' => 'Halbjährlich', 'year' => 'Jährlich'] as $pk => $pl): ?><option value="<?= $pk ?>"<?= ($s['mwst_period'] ?? 'quarter') === $pk ? ' selected' : '' ?>><?= $pl ?></option><?php endforeach; ?></select><p class="hint">Effektive Methode: in der Regel vierteljährlich; Saldosteuersatz: halbjährlich. Steuert die Schnellauswahl im MWST-Bericht.</p></div>
    <div class="sm:col-span-3"><label class="label">Saldosteuersatz (optional)</label><input class="input" name="mwst_saldo_rate" value="<?= $v('mwst_saldo_rate') ?>" inputmode="decimal" placeholder="z. B. 6.2"><p class="hint">Nur, falls Sie von der ESTV die Abrechnung mit Saldosteuersatz bewilligt bekamen. Auf Rechnungen wird weiterhin der Normalsatz ausgewiesen; der Bericht zeigt die geschuldete Steuer.</p></div>
    <div class="sm:col-span-3"><label class="label">E-Mail (Kontakt &amp; „Neue Offerte anfragen“)</label><input class="input" type="email" name="company_email" value="<?= $v('company_email') ?>"></div>
    <div class="sm:col-span-3"><label class="label">Telefon</label><input class="input" name="company_phone" value="<?= $v('company_phone') ?>"></div>
    <div class="sm:col-span-3"><label class="label">Website</label><input class="input" name="company_website" value="<?= $v('company_website') ?>"></div>
    <div class="sm:col-span-6 rounded-xl bg-slate-50 p-4 text-sm text-slate-600 ring-1 ring-slate-200/70"><i class="bx bx-palette align-middle text-slate-400"></i> Logo, Markenfarbe, Systemname und Favicon stellen Sie im Reiter <a class="link" href="?tab=branding">Branding</a> ein.</div>
  <?php elseif ($tab === 'offers'): ?>
    <div class="sm:col-span-2"><label class="label">Währung</label><select class="input" name="currency"><?php foreach (['CHF', 'EUR'] as $cur): ?><option<?= ($s['currency'] ?? 'CHF') === $cur ? ' selected' : '' ?>><?= $cur ?></option><?php endforeach; ?></select><p class="hint">Gilt für neue Offerten. Die QR-Rechnung unterstützt CHF und EUR.</p></div>
    <div class="sm:col-span-2"><label class="label">Standard-Gültigkeit (Tage)</label><input class="input" type="number" min="0" max="365" name="offer_validity_days" value="<?= $v('offer_validity_days') ?>"><p class="hint">0 = ohne Ablaufdatum</p></div>
    <div class="sm:col-span-3"><label class="label">Präfix Offertennummer</label><input class="input" name="offer_prefix" value="<?= $v('offer_prefix') ?>" maxlength="8"><p class="hint">Ergibt z. B. <?= $v('offer_prefix') ?>-<?= date('Y') ?>-0001</p></div>
    <div class="sm:col-span-3"><label class="label">Präfix Auftragsnummer</label><input class="input" name="order_prefix" value="<?= $v('order_prefix') ?>" maxlength="8"></div>
    <label class="flex cursor-pointer items-start gap-3 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200/70 sm:col-span-6"><input type="checkbox" class="checkbox mt-0.5" name="show_contact_person" value="1" <?= ($s['show_contact_person'] ?? '1') === '1' ? 'checked' : '' ?>><span><span class="block text-sm font-medium text-slate-900">Ansprechperson für Kunden anzeigen</span><span class="block text-xs text-slate-500">Der Name der Person, die die Offerte ausgestellt hat (bzw. den Auftrag betreut), erscheint auf Offerte, PDF und im Kundenportal. Die E-Mail-Adresse wird nicht angezeigt.</span></span></label>
    <label class="flex cursor-pointer items-start gap-3 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200/70 sm:col-span-6"><input type="checkbox" class="checkbox mt-0.5" name="round_005" value="1" <?= ($s['round_005'] ?? '') === '1' ? 'checked' : '' ?>><span><span class="block text-sm font-medium text-slate-900">Gesamtbetrag auf 5 Rappen runden</span><span class="block text-xs text-slate-500">Nur bei Währung CHF. Die Rundungsdifferenz wird separat ausgewiesen.</span></span></label>
    <div class="sm:col-span-6"><label class="label">Standard-Einleitung</label><textarea class="input" name="offer_intro" rows="3"><?= $v('offer_intro') ?></textarea></div>
    <div class="sm:col-span-6"><label class="label">Standard-Schlusstext / Konditionen</label><textarea class="input" name="offer_footer" rows="3"><?= $v('offer_footer') ?></textarea></div>
  <?php elseif ($tab === 'invoices'): ?>
    <div class="sm:col-span-3"><label class="label">Präfix Rechnungsnummer</label><input class="input" name="invoice_prefix" value="<?= $v('invoice_prefix') ?>" maxlength="8"><p class="hint">Ergibt z. B. <?= $v('invoice_prefix') ?>-<?= date('Y') ?>-0001. Lückenlos pro Jahr.</p></div>
    <div class="sm:col-span-3"><label class="label">Zahlungsfrist (Tage)</label><input class="input" type="number" min="0" max="365" name="invoice_payment_days" value="<?= $v('invoice_payment_days') ?>"><p class="hint">Standard bei neuen Rechnungen.</p></div>
    <div class="sm:col-span-6"><label class="label">IBAN für QR-Rechnung <span class="font-normal text-slate-400">(optional)</span></label><input class="input font-mono" name="invoice_iban" value="<?= $v('invoice_iban') ?>" placeholder="CH93 0076 2011 6238 5295 7" autocomplete="off"><p class="hint">Mit gültiger IBAN (CH/LI) erhalten Rechnungs-PDFs in CHF/EUR automatisch einen Schweizer Einzahlungsschein mit QR-Code. Bei einer QR-IBAN wird eine QR-Referenz, sonst eine Creditor Reference (RF…) erzeugt. Firmenadresse unter „Firma“ muss vollständig sein.</p></div>
    <div class="sm:col-span-6"><label class="label">Zahlungsinformationen</label><textarea class="input font-mono text-[13px]" name="invoice_payment_info" rows="4" placeholder="IBAN: CH00 0000 0000 0000 0000 0&#10;Bank: Musterbank AG&#10;Zahlungsreferenz: Rechnungsnummer"><?= $v('invoice_payment_info') ?></textarea><p class="hint">Wird auf jeder Rechnung angezeigt (Bankverbindung, Hinweise). Die Zahlung selbst wird mit dem Kunden abgewickelt.</p></div>
    <div class="sm:col-span-6"><label class="label">Standard-Einleitung</label><textarea class="input" name="invoice_intro" rows="2"><?= $v('invoice_intro') ?></textarea></div>
    <div class="sm:col-span-6"><label class="label">Standard-Schlusstext</label><textarea class="input" name="invoice_footer" rows="2"><?= $v('invoice_footer') ?></textarea></div>
  <?php elseif ($tab === 'payments'): ?>
    <div class="sm:col-span-6 rounded-xl bg-slate-50 p-4 text-sm text-slate-600 ring-1 ring-slate-200/70"><i class="bx bx-info-circle align-middle text-slate-400"></i> Kunden können versendete Rechnungen im Portal online bezahlen (immer der offene Gesamtbetrag). Der Zahlungsstatus wird stets direkt beim Anbieter abgefragt; erst dann wird die Rechnung als bezahlt markiert. Kartendaten gelangen nie auf Ihren Server. Die Seite sollte über HTTPS laufen.</div>
    <?php
      $payBase = rtrim(url(''), '/');
      foreach ([
        'mollie' => ['Mollie', 'bx-credit-card', 'mollie_api_key', 'API-Key', 'live_… oder test_…'],
        'stripe' => ['Stripe', 'bx-credit-card-front', 'stripe_secret_key', 'Geheimer Schlüssel', 'sk_live_… oder sk_test_…'],
      ] as $pk => [$pn, $pic, $keyField, $keyLabel, $ph]):
        $isTest = PaymentService::isTestKey($pk);
        $hasKey = !empty($s[$keyField . '_set']);
    ?>
    <div class="sm:col-span-6 rounded-2xl p-4 ring-1 ring-slate-200">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <label class="flex cursor-pointer items-center gap-3"><input type="checkbox" class="checkbox" name="pay_<?= $pk ?>_enabled" value="1" <?= ($s['pay_' . $pk . '_enabled'] ?? '0') === '1' ? 'checked' : '' ?>><span class="text-sm font-semibold text-slate-900"><i class="bx <?= $pic ?> align-middle text-slate-400"></i> <?= $pn ?> aktivieren</span></label>
        <?php if ($hasKey): ?><span class="badge<?= $isTest ? '' : ' badge-brand' ?>"><?= $isTest ? 'Testmodus' : 'Live-Modus' ?></span><?php endif; ?>
      </div>
      <div class="mt-3 grid gap-4 sm:grid-cols-2">
        <div><label class="label"><?= $keyLabel ?></label><input class="input font-mono" type="password" name="<?= $keyField ?>" value="<?= $v($keyField) ?>" placeholder="<?= e($ph) ?>" autocomplete="new-password">
          <?php if ($hasKey): ?><label class="mt-1.5 flex items-center gap-2 text-xs text-slate-500"><input type="checkbox" class="checkbox" name="<?= $keyField ?>_clear" value="1"> Gespeicherten Schlüssel entfernen</label><?php endif; ?>
          <p class="hint"><i class="bx bx-lock-alt align-middle"></i> Verschlüsselt gespeichert (AES-256-GCM).</p></div>
        <?php if ($pk === 'stripe'): ?>
        <div><label class="label">Webhook-Signing-Secret</label><input class="input font-mono" type="password" name="stripe_webhook_secret" value="<?= $v('stripe_webhook_secret') ?>" placeholder="whsec_…" autocomplete="new-password">
          <?php if (!empty($s['stripe_webhook_secret_set'])): ?><label class="mt-1.5 flex items-center gap-2 text-xs text-slate-500"><input type="checkbox" class="checkbox" name="stripe_webhook_secret_clear" value="1"> Gespeichertes Secret entfernen</label><?php endif; ?></div>
        <?php endif; ?>
      </div>
      <p class="mt-3 text-xs text-slate-500">
      <?php if ($pk === 'mollie'): ?>
        Mollie erhält die Webhook-URL pro Zahlung automatisch (nur bei öffentlich erreichbarer HTTPS-Adresse). Ohne Webhook wird der Status beim Zurückkehren des Kunden abgefragt.
      <?php else: ?>
        Webhook-Endpunkt im Stripe-Dashboard anlegen: <code class="select-all rounded bg-slate-100 px-1.5 py-0.5"><?= e($payBase) ?>/webhooks/stripe</code><br>Ereignisse: <code>checkout.session.completed</code>, <code>checkout.session.async_payment_succeeded</code>, <code>checkout.session.async_payment_failed</code>, <code>checkout.session.expired</code>
      <?php endif; ?>
      </p>
    </div>
    <?php endforeach; ?>
  <?php elseif ($tab === 'mail'): ?>
    <div class="sm:col-span-6"><span class="label">Versandart</span>
      <div class="grid gap-2 sm:grid-cols-3">
        <?php foreach (['smtp' => ['SMTP', 'Empfohlen', 'bx-server'], 'phpmail' => ['PHP mail()', 'Server-Standard', 'bx-code-alt'], 'log' => ['Nur protokollieren', 'Test: Dateien in storage/mail', 'bx-file']] as $k => [$l, $d, $ic]): ?>
          <label class="flex cursor-pointer items-start gap-3 rounded-xl p-3.5 ring-1 ring-slate-200 transition hover:ring-slate-300 has-[:checked]:bg-brand-50 has-[:checked]:ring-2 has-[:checked]:ring-brand-500"><input type="radio" class="sr-only" name="mail_transport" value="<?= $k ?>" <?= ($s['mail_transport'] ?? 'smtp') === $k ? 'checked' : '' ?>><i class="bx <?= $ic ?> text-xl text-slate-400"></i><span><span class="block text-sm font-medium"><?= $l ?></span><span class="block text-xs text-slate-500"><?= $d ?></span></span></label>
        <?php endforeach; ?>
      </div></div>
    <div class="sm:col-span-3"><label class="label">SMTP-Server</label><input class="input" name="smtp_host" value="<?= $v('smtp_host') ?>" placeholder="mail.ihredomain.ch"></div>
    <div class="sm:col-span-1"><label class="label">Port</label><input class="input" type="number" name="smtp_port" value="<?= $v('smtp_port') ?>"></div>
    <div class="sm:col-span-2"><label class="label">Verschlüsselung</label><select class="input" name="smtp_encryption"><?php foreach (['tls' => 'STARTTLS (587)', 'ssl' => 'SSL/TLS (465)', 'none' => 'Keine (25)'] as $k => $l): ?><option value="<?= $k ?>"<?= ($s['smtp_encryption'] ?? 'tls') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
    <div class="sm:col-span-3"><label class="label">Benutzername</label><input class="input" name="smtp_user" value="<?= $v('smtp_user') ?>" autocomplete="off"></div>
    <div class="sm:col-span-3"><label class="label">Passwort</label><input class="input" type="password" name="smtp_pass" value="<?= $v('smtp_pass') ?>" autocomplete="new-password"><p class="hint"><i class="bx bx-lock-alt align-middle"></i> Verschlüsselt gespeichert (AES-256-GCM).</p></div>
    <div class="sm:col-span-3"><label class="label">Absenderadresse</label><input class="input" type="email" name="mail_from_email" value="<?= $v('mail_from_email') ?>"></div>
    <div class="sm:col-span-3"><label class="label">Absendername</label><input class="input" name="mail_from_name" value="<?= $v('mail_from_name') ?>"></div>
    <label class="flex cursor-pointer items-start gap-3 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200/70 sm:col-span-6"><input type="checkbox" class="checkbox mt-0.5" name="mail_attach_pdf" value="1" <?= ($s['mail_attach_pdf'] ?? '1') === '1' ? 'checked' : '' ?>><span class="text-sm text-slate-700">Offerten und Rechnungen als PDF an die E-Mail anhängen<span class="block text-xs text-slate-400">Das PDF enthält personenbezogene Daten und wird per E-Mail übertragen. Für höchste Vertraulichkeit deaktivieren (dann nur Link).</span></span></label>
    <div class="sm:col-span-6"><label class="label">Benachrichtigungen an (Team)</label><input class="input" type="email" name="notify_email" value="<?= $v('notify_email') ?>" placeholder="Standard: Firmen-E-Mail"><p class="hint">Erhält Meldungen bei Annahme, Ablehnung und Anfragen neuer Offerten.</p></div>
  <?php elseif ($tab === 'privacy'): ?>
    <div class="sm:col-span-6 rounded-xl bg-slate-50 p-4 text-sm text-slate-600 ring-1 ring-slate-200/70"><i class="bx bx-info-circle align-middle text-slate-400"></i> Technische Daten werden nach diesen Fristen automatisch gelöscht (täglich per Cronjob bzw. beim Aufruf der Administration). Offerten und Rechnungen unterliegen der gesetzlichen Aufbewahrungspflicht und sind davon ausgenommen.</div>
    <div class="sm:col-span-3"><label class="label">Aufbewahrung Rechnungen/Buchungsbelege (Jahre)</label><input class="input" type="number" min="1" max="30" name="retention_years" value="<?= $v('retention_years') ?>"><p class="hint">Schweiz: 10 Jahre (Art. 958f OR). Deutschland: 8 Jahre (Buchungsbelege, § 147 AO), Österreich: 7 Jahre. Bis dahin ist keine vollständige Anonymisierung möglich.</p></div>
    <div class="sm:col-span-3"><label class="label">IP-Adressen speichern (Tage)</label><input class="input" type="number" min="1" max="365" name="ip_retention_days" value="<?= $v('ip_retention_days') ?>"><p class="hint">IP bei Annahme/Anmeldelinks; danach wird sie gelöscht.</p></div>
    <div class="sm:col-span-3"><label class="label">E-Mail-Protokoll (Tage)</label><input class="input" type="number" min="30" max="3650" name="mail_log_retention_days" value="<?= $v('mail_log_retention_days') ?>"></div>
    <div class="sm:col-span-3"><label class="label">Audit-Log (Tage)</label><input class="input" type="number" min="30" max="3650" name="audit_retention_days" value="<?= $v('audit_retention_days') ?>"></div>
    <div class="sm:col-span-6"><label class="label">Kontakt für Datenschutzanfragen</label><input class="input" type="email" name="privacy_contact" value="<?= $v('privacy_contact') ?>" placeholder="Standard: Firmen-E-Mail"></div>
    <div class="sm:col-span-6"><label class="label">Datenschutzerklärung (eigener Text) <span class="font-normal text-slate-400">(optional)</span></label><textarea class="input" rows="8" name="privacy_text" placeholder="Leer lassen, um die mitgelieferte Vorlage unter /datenschutz zu verwenden."><?= $v('privacy_text') ?></textarea><p class="hint">Reiner Text, Absätze durch Leerzeilen. Die Vorlage ersetzt keine Rechtsberatung – bitte prüfen und anpassen. Sichtbar unter <a class="link" href="<?= e(url('datenschutz')) ?>" target="_blank">/datenschutz</a>.</p></div>
  <?php endif; ?>
  </div>
  <div class="flex justify-end rounded-b-2xl border-t border-slate-100 bg-slate-50/50 px-5 py-4 sm:px-6"><button class="btn btn-primary"><i class="bx bx-save"></i>Speichern</button></div>
</form>
<?php endif; ?>

<?php if ($tab === 'privacy'): ?>
<section class="reveal card mt-6 max-w-4xl p-5 sm:p-6">
  <h2 class="text-sm font-semibold text-slate-900"><i class="bx bx-data align-middle text-slate-400"></i> Datensicherung</h2>
  <p class="mt-1 text-sm text-slate-500">Lädt die komplette Datenbank als komprimierte SQL-Datei herunter (inkl. personenbezogener Daten). Die Datei enthält nicht den App-Schlüssel aus <code>app/config.php</code> – sichern Sie diesen separat. Bewahren Sie Backups verschlüsselt auf und löschen Sie sie, sobald sie nicht mehr gebraucht werden. Jeder Download wird im Audit-Log festgehalten.</p>
  <div class="mt-4 flex flex-wrap gap-2">
    <form method="post" action="<?= e(url('admin/backup')) ?>"><?= csrf_field() ?><button class="btn btn-secondary"><i class="bx bx-download"></i>Datenbank-Backup (.sql.gz)</button></form>
    <?php if (class_exists('ZipArchive')): ?><form method="post" action="<?= e(url('admin/backup')) ?>"><?= csrf_field() ?><input type="hidden" name="files" value="1"><button class="btn btn-secondary"><i class="bx bx-archive-in"></i>Backup inkl. Anhänge (.zip)</button></form><?php endif; ?>
  </div>
</section>
<section class="reveal card mt-6 max-w-4xl p-5 sm:p-6">
  <h2 class="text-sm font-semibold text-slate-900"><i class="bx bx-user-voice align-middle text-slate-400"></i> Betroffenenrechte</h2>
  <p class="mt-1 text-sm text-slate-500">Auskunft (Art. 15), Datenübertragbarkeit (Art. 20), Einschränkung (Art. 18) und Löschung/Anonymisierung (Art. 17) erledigen Sie pro Kunde in der Kundenansicht unter „Datenschutz“. Kunden können ihre Daten auch selbst im Portal herunterladen.</p>
  <p class="mt-3 text-sm text-slate-500"><a class="link" href="<?= e(url('admin/audit')) ?>">Audit-Log</a> · <a class="link" href="<?= e(url('admin/privacy-register')) ?>">Verzeichnis der Verarbeitungstätigkeiten (Art. 30)</a> · <a class="link" href="<?= e(url('datenschutz')) ?>" target="_blank">Datenschutzerklärung ansehen</a></p>
</section>
<?php endif; ?>

<?php if ($tab === 'mail'): ?>
<form method="post" action="<?= e(url('admin/settings/test-mail')) ?>" class="reveal card mt-6 flex max-w-4xl flex-col gap-3 p-5 sm:flex-row sm:items-end sm:p-6"><?= csrf_field() ?>
  <div class="flex-1"><label class="label">Test-E-Mail senden an</label><input class="input" type="email" name="to" value="<?= e(Auth::user()['email']) ?>"></div>
  <button class="btn btn-secondary"><i class="bx bx-paper-plane"></i>Test senden</button>
</form>
<p class="mt-3 max-w-4xl text-sm text-slate-500"><a class="link" href="<?= e(url('admin/mail-log')) ?>">E-Mail-Protokoll ansehen</a> – zeigt Fehlermeldungen des Servers bei fehlgeschlagenem Versand.</p>
<?php endif; ?>
