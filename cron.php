<?php
/**
 * Cronjob (nur per Kommandozeile): erzeugt fällige Abo-Rechnungen und setzt abgelaufene Offerten.
 * Plesk → Geplante Aufgaben → „PHP-Skript ausführen“, z. B. täglich um 06:00:
 *   Skriptpfad: /var/www/vhosts/ihre-domain.ch/httpdocs/cron.php
 * (Ohne Cronjob läuft dieselbe Prüfung automatisch beim Aufruf der Administration.)
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/app/bootstrap.php';

License::refreshIfDue(true);
fwrite(STDOUT, date('c') . ' – ' . UpdateService::cron() . "\n");
fwrite(STDOUT, date('c') . ' – Lizenz: ' . License::status()['status'] . "\n");
OfferService::sweepExpired();
$n = SubscriptionService::runDue();
Settings::set(['sub_last_run' => (string) time(), 'cron_last_run' => (string) time()]);
fwrite(STDOUT, date('c') . " – $n Abo-Rechnung(en) erzeugt\n");
$wh = Webhooks::processDue(50);
fwrite(STDOUT, date('c') . " – $wh Webhook-Zustellung(en) verarbeitet\n");
$ret = Retention::run();
Settings::set(['retention_last_run' => (string) time()]);
fwrite(STDOUT, date('c') . ' – Aufbewahrung: ' . json_encode(array_filter($ret)) . "\n");
