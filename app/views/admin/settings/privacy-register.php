<?php
$title = 'Verzeichnis der Verarbeitungstätigkeiten';
$subtitle = 'Art. 30 DSGVO / Art. 12 DSG – automatisch aus Ihren Einstellungen erzeugt. Bitte prüfen, ergänzen und aufbewahren.';
$back = ['admin/settings?tab=privacy', 'Einstellungen'];
$actions = '<button type="button" class="btn btn-secondary" onclick="window.print()"><i class="bx bx-printer"></i>Drucken / als PDF speichern</button>';
include APP_DIR . '/views/partials/page-header.php';
$co = trim(Settings::get('company_name') . ', ' . Settings::get('company_street') . ', ' . Settings::get('company_zip') . ' ' . Settings::get('company_city'), ' ,');
$rows = [
    ['Kundenverwaltung, Offerten, Aufträge, Rechnungen', 'Vertragsanbahnung und -erfüllung, Buchführung (Art. 6 Abs. 1 lit. b, c DSGVO)',
     'Kunden, Interessenten', 'Name, Firma, Adresse, E-Mail, Telefon, UID, Positionen, Beträge, Lieferadresse, Unterschrift und IP bei Annahme',
     'Intern (Benutzer mit Zugriff); Steuerberatung/Treuhand nach Bedarf', $cfg['years'] . ' Jahre ab Rechnungsdatum (Art. 958f OR), danach Anonymisierung'],
    ['Anfrageformular', 'Beantwortung von Anfragen mit Einwilligung (Art. 6 Abs. 1 lit. a, b)',
     'Interessenten', 'Name, E-Mail, optional Firma/Telefon/Betreff, Nachricht, IP', 'Intern', $cfg['inq'] . ' Tage (IP: ' . $cfg['ip'] . ' Tage)'],
    ['Kundenportal und Anmeldelinks', 'Bereitstellung des Portals (Art. 6 Abs. 1 lit. b)',
     'Kunden mit Portalzugang', 'E-Mail, Anmelde-Token (gehasht), IP beim Anfordern', 'Intern', 'Token bis Ablauf + 7 Tage; IP ' . $cfg['ip'] . ' Tage'],
    ['E-Mail-Versand und Protokoll', 'Zustellung von Offerten, Rechnungen, Benachrichtigungen (Art. 6 Abs. 1 lit. b, f)',
     'Kunden, Interessenten', 'Empfänger, Betreff, Status, Fehlertext', 'E-Mail-Anbieter: ' . $cfg['mail'], $cfg['mailLog'] . ' Tage'],
    ['Audit-Log und Sicherheit', 'IT-Sicherheit, Nachvollziehbarkeit (Art. 6 Abs. 1 lit. f, Art. 32)',
     'Benutzer (Team)', 'Benutzer, Aktion, IP, Zeitpunkt', 'Intern (Administratoren)', $cfg['audit'] . ' Tage'],
    ['Benutzerverwaltung', 'Betrieb des Systems (Art. 6 Abs. 1 lit. b, f)',
     'Mitarbeitende', 'Name, E-Mail, Passwort-Hash, 2FA-Daten (verschlüsselt), Passkeys (öffentlicher Schlüssel)', 'Intern', 'Bis zur Löschung des Kontos'],
];
if (class_exists('SsoController') && SsoController::active()) {
    $rows[] = ['Single Sign-On', 'Anmeldung der Mitarbeitenden (Art. 6 Abs. 1 lit. b, f)', 'Mitarbeitende', 'E-Mail, Name, Anbieter-Kennung (sub)',
        'Identitätsanbieter ' . (parse_url((string) Settings::get('sso_issuer'), PHP_URL_HOST) ?: ''), 'Bis zur Löschung des Kontos'];
}
if ($cfg['mollie'] || $cfg['stripe']) {
    $rows[] = ['Online-Zahlung', 'Zahlungsabwicklung (Art. 6 Abs. 1 lit. b)', 'Kunden',
        'Betrag, Rechnungsnummer, E-Mail, Zahlungsstatus (keine Kartendaten im System)',
        implode(', ', array_filter([$cfg['mollie'] ? 'Mollie B.V. (NL)' : null, $cfg['stripe'] ? 'Stripe (Drittlandtransfer prüfen)' : null])), $cfg['years'] . ' Jahre (Buchhaltung)'];
}
if ($cfg['hooks'] || $cfg['api']) {
    $rows[] = ['Schnittstellen (Webhooks / API)', 'Anbindung eigener Systeme (Art. 6 Abs. 1 lit. f)', 'Kunden', 'Je nach Webhook-Ereignis: Rechnungs-/Offertendaten, Kundenname, E-Mail',
        $cfg['hooks'] . ' aktive(r) Webhook-Empfänger, ' . $cfg['api'] . ' API-Token – Empfänger selbst prüfen', 'Zustellprotokoll 30 Tage'];
}
?>
<div class="reveal card max-w-5xl p-5 sm:p-6">
  <dl class="mb-5 grid gap-3 text-sm sm:grid-cols-2">
    <div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Verantwortlicher</dt><dd class="mt-1 text-slate-800"><?= e($co) ?></dd></div>
    <div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Kontakt Datenschutz</dt><dd class="mt-1 text-slate-800"><?= e($cfg['contact']) ?></dd></div>
    <div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Stand</dt><dd class="mt-1 text-slate-800"><?= e(date('d.m.Y')) ?></dd></div>
    <div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Technische Massnahmen</dt><dd class="mt-1 text-slate-800">HTTPS/HSTS, Passwort-Hashing, 2FA/Passkeys, Rollenrechte, CSRF-Schutz, Rate-Limits, Verschlüsselung von Zugangsdaten, Audit-Log, Backup (siehe README 7.2)</dd></div>
  </dl>
  <div class="overflow-x-auto">
    <table class="w-full text-left text-sm">
      <thead><tr class="border-b border-slate-200 text-xs uppercase tracking-wider text-slate-400">
        <th class="py-2 pr-3">Tätigkeit</th><th class="py-2 pr-3">Zweck / Rechtsgrundlage</th><th class="py-2 pr-3">Betroffene</th><th class="py-2 pr-3">Datenkategorien</th><th class="py-2 pr-3">Empfänger</th><th class="py-2">Löschfrist</th></tr></thead>
      <tbody class="align-top">
      <?php foreach ($rows as $r): ?>
        <tr class="border-b border-slate-100"><?php foreach ($r as $i => $cell): ?><td class="py-2.5 pr-3 <?= $i === 0 ? 'font-medium text-slate-900' : 'text-slate-600' ?>"><?= e($cell) ?></td><?php endforeach; ?></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="mt-4 text-xs text-slate-500">Hoster/Rechenzentrum (Auftragsverarbeiter, Standort und AV-Vertrag selbst prüfen) ist für alle Tätigkeiten Empfänger. Die Vorlage ersetzt keine Rechtsberatung; zusätzliche Tätigkeiten (z. B. Newsletter, Buchhaltungssoftware) bitte ergänzen. Datenpannen: Meldung an den EDÖB bzw. die Aufsichtsbehörde innert 72 Stunden prüfen (Art. 33 DSGVO / Art. 24 DSG) – das Audit-Log hilft bei der Aufklärung.</p>
</div>
