<?php
/** @var string $custom @var string $contact @var array $ret */
$title = 'Datenschutzerklärung';
$company = (string) Settings::get('company_name');
$addr = implode(', ', array_filter([Settings::get('company_street'), trim(Settings::get('company_zip') . ' ' . Settings::get('company_city'))]));
?>
<article class="reveal mx-auto max-w-3xl pt-6">
  <h1 class="text-2xl font-bold tracking-tight text-slate-900">Datenschutzerklärung</h1>
  <?php if ($custom !== ''): ?>
    <div class="mt-6 space-y-4 text-sm leading-relaxed text-slate-700"><?php foreach (preg_split('/\R{2,}/', $custom) as $p): ?><p><?= nl2br(e(trim($p))) ?></p><?php endforeach; ?></div>
  <?php else: ?>
  <div class="mt-6 space-y-6 text-sm leading-relaxed text-slate-700">
    <section><h2 class="mb-1 font-semibold text-slate-900">1. Verantwortlicher</h2>
      <p><?= e($company) ?><?= $addr ? ', ' . e($addr) : '' ?>.<?php if ($contact): ?> Datenschutzanfragen: <a class="link" href="mailto:<?= e($contact) ?>"><?= e($contact) ?></a>.<?php endif; ?></p></section>
    <section><h2 class="mb-1 font-semibold text-slate-900">2. Welche Daten wir verarbeiten</h2>
      <ul class="list-disc space-y-1 pl-5">
        <li><strong>Stammdaten:</strong> Anrede, Name, Firma, Adresse, E-Mail, Telefon, UID/MWST-Nummer.</li>
        <li><strong>Vertragsdaten:</strong> Offerten, Aufträge, Rechnungen, Lieferadressen, Zahlungsstatus, Bemerkungen.</li>
        <li><strong>Annahme/Unterschrift:</strong> Name, Zeitpunkt, IP-Adresse, ggf. Unterschriftsbild sowie – bei strikter Unterschrift – die bestätigte E-Mail-Adresse.</li>
        <li><strong>Kommunikation:</strong> E-Mails zu Offerten, Aufträgen und Rechnungen (Protokoll mit Empfänger, Betreff, Zeitpunkt).</li>
        <li><strong>Anfragen:</strong> Name, E-Mail, ggf. Firma, Telefon, Betreff und Nachricht sowie Zeitpunkt der Einwilligung und IP-Adresse, wenn Sie das Anfrageformular nutzen. Anfragen werden nach spätestens 180 Tagen gelöscht, sofern daraus kein Vertragsverhältnis entsteht.</li>
        <li><strong>Technische Daten:</strong> Zeitpunkt des ersten Aufrufs einer Offerte, IP-Adresse bei Anmeldelinks und Annahme, Sitzungs-Cookie.</li>
      </ul></section>
    <section><h2 class="mb-1 font-semibold text-slate-900">3. Zwecke und Rechtsgrundlagen</h2>
      <p>Wir verarbeiten Ihre Daten zur Erstellung und Abwicklung von Offerten, Aufträgen und Rechnungen (Vertrag bzw. vorvertragliche Massnahmen, Art. 6 Abs. 1 lit. b DSGVO), zur Erfüllung gesetzlicher Aufbewahrungs- und Buchführungspflichten (lit. c) sowie aufgrund unseres berechtigten Interesses an Sicherheit, Missbrauchsschutz und dem Nachweis von Annahmen (lit. f). Eine Weitergabe zu Werbezwecken findet nicht statt.</p></section>
    <section><h2 class="mb-1 font-semibold text-slate-900">4. Cookies und Drittanbieter</h2>
      <p>Diese Seiten verwenden ausschliesslich ein technisch notwendiges Sitzungs-Cookie (Anmeldung, Formularschutz). Es gibt kein Tracking, keine Analyse und keine Werbung. Schriften, Symbole und Skripte werden von unserem eigenen Server ausgeliefert; es werden dafür keine Daten an Dritte übermittelt.</p></section>
    <section><h2 class="mb-1 font-semibold text-slate-900">5. Empfänger</h2>
      <p>Hosting- und E-Mail-Dienstleister (als Auftragsverarbeiter), bei Bedarf Treuhand/Buchhaltung, bei Online-Zahlung der gewählte Zahlungsdienstleister (Mollie bzw. Stripe; übermittelt werden Rechnungsbetrag, Rechnungsnummer und – falls vorhanden – Ihre E-Mail-Adresse; Zahlungsdaten geben Sie ausschliesslich beim Zahlungsdienstleister ein und wir erhalten sie nicht) sowie Versanddienstleister (nur Lieferadresse). Eine Übermittlung ausserhalb der Schweiz bzw. des EWR erfolgt nur, wenn ein angemessenes Schutzniveau besteht (z. B. Angemessenheitsbeschluss oder Standardvertragsklauseln).</p></section>
    <section><h2 class="mb-1 font-semibold text-slate-900">6. Speicherdauer</h2>
      <ul class="list-disc space-y-1 pl-5">
        <li>Offerten, Aufträge und Rechnungen: bis zum Ablauf der gesetzlichen Aufbewahrungsfrist (<?= (int) $ret['years'] ?> Jahre), danach Löschung bzw. Anonymisierung.</li>
        <li>IP-Adressen bei Annahme und Anmeldelinks: <?= (int) $ret['ip'] ?> Tage.</li>
        <li>E-Mail-Protokoll: <?= (int) $ret['mail'] ?> Tage. Anmeldelinks und Bestätigungscodes: wenige Minuten gültig, danach gelöscht.</li>
      </ul></section>
    <section><h2 class="mb-1 font-semibold text-slate-900">7. Ihre Rechte</h2>
      <p>Sie haben das Recht auf Auskunft, Berichtigung, Löschung, Einschränkung der Verarbeitung, Datenübertragbarkeit und Widerspruch. Im <a class="link" href="<?= e(url('portal')) ?>">Kundenportal</a> können Sie Ihre Daten selbst herunterladen. Anfragen richten Sie bitte an<?= $contact ? ' <a class="link" href="mailto:' . e($contact) . '">' . e($contact) . '</a>' : ' uns' ?>. Sie können sich ausserdem beim Eidgenössischen Datenschutz- und Öffentlichkeitsbeauftragten (EDÖB) bzw. bei der für Sie zuständigen Datenschutz-Aufsichtsbehörde beschweren.</p></section>
    <section><h2 class="mb-1 font-semibold text-slate-900">8. Sicherheit</h2>
      <p>Die Übertragung erfolgt verschlüsselt (HTTPS). Zugänge sind rollenbasiert, Passwörter werden nur als Hash gespeichert, eine Zwei-Faktor-Anmeldung ist möglich, sicherheitsrelevante Aktionen werden protokolliert.</p></section>
    <p class="text-xs text-slate-400">Stand: <?= date('d.m.Y') ?></p>
  </div>
  <?php endif; ?>
</article>
