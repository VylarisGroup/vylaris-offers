<?php
/**
 * Systemstatus: prüft Umgebung, Datenbank, Cronjob, Backup, E-Mail, Zahlungen und Konfiguration.
 * Jede Prüfung liefert ok | warn | fail mit einer verständlichen Erklärung und – falls nötig – einer Handlungsanweisung.
 */
declare(strict_types=1);

final class HealthService
{
    public const LEVEL = ['ok' => ['In Ordnung', 'emerald', 'bx-check-circle'], 'warn' => ['Hinweis', 'amber', 'bx-error'], 'fail' => ['Problem', 'rose', 'bx-x-circle']];

    private static function c(string $group, string $id, string $label, string $level, string $detail, string $hint = ''): array
    {
        return ['group' => $group, 'id' => $id, 'label' => $label, 'level' => $level, 'detail' => $detail, 'hint' => $hint];
    }

    /** @return array<int, array{group:string,id:string,label:string,level:string,detail:string,hint:string}> */
    public static function checks(): array
    {
        $out = [];
        $age = static function (?string $ts): ?int {
            return $ts !== null && $ts !== '' && ctype_digit($ts) ? time() - (int) $ts : null;
        };
        $ago = static function (int $sec): string {
            return $sec < 3600 ? 'vor ' . max(1, (int) round($sec / 60)) . ' Min.' : ($sec < 172800 ? 'vor ' . (int) round($sec / 3600) . ' Std.' : 'vor ' . (int) round($sec / 86400) . ' Tagen');
        };

        /* ---- System */
        $out[] = PHP_VERSION_ID >= 80300
            ? self::c('System', 'php', 'PHP-Version', 'ok', 'PHP ' . PHP_VERSION)
            : self::c('System', 'php', 'PHP-Version', PHP_VERSION_ID >= 80100 ? 'warn' : 'fail', 'PHP ' . PHP_VERSION, 'Empfohlen ist PHP 8.3 oder neuer (Plesk → PHP-Einstellungen).');
        $missing = array_values(array_filter(['pdo_mysql', 'mbstring', 'curl', 'openssl', 'json', 'fileinfo', 'zlib', 'sodium'], static fn($e) => !extension_loaded($e)));
        $out[] = $missing
            ? self::c('System', 'ext', 'PHP-Erweiterungen', 'fail', 'Fehlend: ' . implode(', ', $missing), 'Aktivieren Sie die Erweiterungen in Plesk unter „PHP-Einstellungen“.')
            : self::c('System', 'ext', 'PHP-Erweiterungen', 'ok', 'Alle benötigten Erweiterungen sind aktiv.');
        $lic = License::status();
        $out[] = $lic['mode'] === 'readonly'
            ? self::c('System', 'license', 'Lizenz', 'fail', $lic['message'], 'Das System läuft im Lesemodus – unter «Lizenz» Schlüssel prüfen oder erneuern.')
            : (in_array($lic['status'], ['offline', 'grace'], true) || ($lic['expires'] && $lic['expires'] - time() < 14 * 86400)
                ? self::c('System', 'license', 'Lizenz', 'warn', $lic['status'] === 'active' ? 'Läuft am ' . date('d.m.Y', (int) $lic['expires']) . ' ab.' : $lic['message'], 'Verlängern bzw. Verbindung zum Lizenzserver prüfen.')
                : self::c('System', 'license', 'Lizenz', 'ok', 'Gültig' . ($lic['plan'] ? ' (Tarif ' . $lic['plan'] . ')' : '') . '.'));
        $out[] = !class_exists('ZipArchive')
            ? self::c('System', 'zip', 'ZIP-Unterstützung', 'warn', 'ZipArchive fehlt.', 'Ohne die Erweiterung „zip“ ist nur das Datenbank-Backup ohne Dateien möglich.')
            : self::c('System', 'zip', 'ZIP-Unterstützung', 'ok', 'Verfügbar (Backup inklusive Dateien).');
        $free = @disk_free_space(APP_ROOT);
        if ($free !== false) {
            $mb = (int) ($free / 1048576);
            $out[] = self::c('System', 'disk', 'Freier Speicherplatz', $mb < 100 ? 'fail' : ($mb < 500 ? 'warn' : 'ok'), number_format($mb, 0, '.', '’') . ' MB frei', $mb < 500 ? 'Schaffen Sie Platz oder erweitern Sie das Hosting-Paket.' : '');
        }
        $bad = [];
        foreach (['storage', 'storage/logs', 'storage/mail', 'storage/uploads'] as $d) {
            if (!is_dir(APP_ROOT . '/' . $d) || !is_writable(APP_ROOT . '/' . $d)) {
                $bad[] = $d;
            }
        }
        $out[] = $bad
            ? self::c('System', 'dirs', 'Schreibrechte', 'fail', 'Nicht beschreibbar: ' . implode(', ', $bad), 'Setzen Sie die Schreibrechte für den Webserver-Benutzer (Plesk → Dateimanager → Berechtigungen).')
            : self::c('System', 'dirs', 'Schreibrechte', 'ok', 'storage/ ist beschreibbar.');
        $log = APP_ROOT . '/storage/logs/app.log';
        if (is_file($log)) {
            $recent = 0;
            $tail = (string) @file_get_contents($log, false, null, max(0, (int) filesize($log) - 60000));
            $since = date('Y-m-d H:i:s', time() - 86400);
            if (preg_match_all('/^\[(\d{4}-\d\d-\d\d \d\d:\d\d:\d\d)\] #/m', $tail, $m)) {
                $recent = count(array_filter($m[1], static fn($t) => $t >= $since));
            }
            $out[] = $recent > 0
                ? self::c('System', 'errors', 'Fehlerprotokoll', $recent >= 10 ? 'fail' : 'warn', $recent . ' Fehler in den letzten 24 Stunden', 'Sehen Sie in storage/logs/app.log nach (Fehler-ID wird auch auf der Fehlerseite angezeigt).')
                : self::c('System', 'errors', 'Fehlerprotokoll', 'ok', 'Keine Fehler in den letzten 24 Stunden.');
        }

        /* ---- Sicherheit */
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || str_starts_with((string) app_config('app.base_url', ''), 'https://');
        $out[] = $https
            ? self::c('Sicherheit', 'https', 'HTTPS', 'ok', 'Die Seite wird verschlüsselt ausgeliefert.')
            : self::c('Sicherheit', 'https', 'HTTPS', 'fail', 'Die Seite wird unverschlüsselt ausgeliefert.', 'Aktivieren Sie ein Zertifikat (Plesk → Let’s Encrypt) und leiten Sie auf HTTPS um.');
        $out[] = app_config('app.debug')
            ? self::c('Sicherheit', 'debug', 'Debug-Modus', 'fail', 'Fehlerdetails werden im Browser angezeigt.', 'Setzen Sie in app/config.php „debug“ => false.')
            : self::c('Sicherheit', 'debug', 'Debug-Modus', 'ok', 'Ausgeschaltet.');
        $cfg = APP_DIR . '/config.php';
        $perm = is_file($cfg) ? fileperms($cfg) & 0007 : 0;
        $out[] = $perm
            ? self::c('Sicherheit', 'cfgperm', 'Konfigurationsdatei', 'warn', 'app/config.php ist für alle lesbar.', 'Setzen Sie die Rechte auf 640 oder 600.')
            : self::c('Sicherheit', 'cfgperm', 'Konfigurationsdatei', 'ok', 'Zugriffsrechte sind eingeschränkt.');
        $out[] = is_file(APP_ROOT . '/setup.php')
            ? self::c('Sicherheit', 'setup', 'Installationsassistent', 'warn', 'setup.php liegt noch auf dem Server.', 'Löschen Sie setup.php nach der Installation.')
            : self::c('Sicherheit', 'setup', 'Installationsassistent', 'ok', 'Entfernt.');
        $admins = (int) DB::val("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1");
        $without = (int) DB::val("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1 AND totp_enabled = 0 AND NOT EXISTS (SELECT 1 FROM passkeys p WHERE p.user_id = users.id)");
        $out[] = $without > 0
            ? self::c('Sicherheit', '2fa', 'Zweiter Faktor (Administratoren)', 'warn', "$without von $admins Administrator(en) ohne 2FA oder Passkey", 'Aktivieren Sie unter „Mein Profil“ die Zwei-Faktor-Anmeldung oder einen Passkey.')
            : self::c('Sicherheit', '2fa', 'Zweiter Faktor (Administratoren)', 'ok', 'Alle Administratoren sind geschützt.');

        /* ---- Datenbank */
        try {
            $v = (int) DB::val("SELECT value FROM settings WHERE `key` = 'schema_version'");
            $out[] = $v === Schema::VERSION
                ? self::c('Datenbank', 'db', 'Datenbank & Schema', 'ok', 'Verbunden, Schema-Version ' . $v)
                : self::c('Datenbank', 'db', 'Datenbank & Schema', 'warn', "Schema-Version $v (erwartet " . Schema::VERSION . ')', 'Rufen Sie eine beliebige Seite auf – die Aktualisierung läuft automatisch.');
        } catch (Throwable $e) {
            $out[] = self::c('Datenbank', 'db', 'Datenbank & Schema', 'fail', 'Abfrage fehlgeschlagen.');
        }

        /* ---- Hintergrundaufgaben */
        $cron = $age((string) Settings::get('cron_last_run', ''));
        $out[] = $cron === null
            ? self::c('Hintergrundaufgaben', 'cron', 'Cronjob', 'warn', 'Noch nie ausgeführt.', 'Richten Sie in Plesk unter „Geplante Aufgaben“ täglich cron.php ein. Ohne Cronjob laufen Abo-Rechnungen, Webhooks und die Datenlöschung erst beim Aufruf der Administration.')
            : ($cron > 129600
                ? self::c('Hintergrundaufgaben', 'cron', 'Cronjob', 'warn', 'Letzter Lauf ' . $ago($cron), 'Prüfen Sie die geplante Aufgabe in Plesk.')
                : self::c('Hintergrundaufgaben', 'cron', 'Cronjob', 'ok', 'Letzter Lauf ' . $ago($cron)));
        $stuck = (int) DB::val("SELECT COUNT(*) FROM webhook_deliveries WHERE status = 'failed' AND created_at >= ?", [date('Y-m-d H:i:s', time() - 7 * 86400)]);
        $out[] = $stuck > 0
            ? self::c('Hintergrundaufgaben', 'webhooks', 'Webhooks', 'warn', "$stuck fehlgeschlagene Zustellung(en) in 7 Tagen", 'Prüfen Sie unter „Integrationen“ die Ziel-URL des Webhooks.')
            : self::c('Hintergrundaufgaben', 'webhooks', 'Webhooks', 'ok', 'Keine fehlgeschlagenen Zustellungen.');

        /* ---- Software-Update */
        if (UpdateService::configured()) {
            $ue = (string) Settings::get('update_error', '');
            $out[] = $ue !== ''
                ? self::c('System', 'update', 'Software-Update', 'warn', $ue, 'Unter «Update» prüfen.')
                : (UpdateService::available()
                    ? self::c('System', 'update', 'Software-Update', 'warn', 'Version ' . (UpdateService::latest()['version'] ?? '') . ' ist verfügbar (installiert: ' . APP_VERSION . ').', 'Unter «Update» installieren.')
                    : self::c('System', 'update', 'Software-Update', 'ok', 'Aktuell (' . APP_VERSION . ').'));
        }

        /* ---- Backup */
        $bk = $age((string) Settings::get('last_backup_at', ''));
        $out[] = $bk === null
            ? self::c('Datensicherung', 'backup', 'Backup', 'warn', 'Es wurde noch kein Backup über das System heruntergeladen.', 'Laden Sie unter „Einstellungen → Datenschutz“ ein Backup herunter oder richten Sie die Plesk-Sicherung ein.')
            : ($bk > 35 * 86400
                ? self::c('Datensicherung', 'backup', 'Backup', 'warn', 'Letztes Backup ' . $ago($bk), 'Laden Sie ein aktuelles Backup herunter.')
                : self::c('Datensicherung', 'backup', 'Backup', 'ok', 'Letztes Backup ' . $ago($bk)));

        /* ---- E-Mail */
        $transport = (string) Settings::get('mail_transport', 'smtp');
        if ($transport === 'log') {
            $out[] = self::c('E-Mail', 'mail', 'E-Mail-Versand', 'fail', 'Nur Protokoll – es werden keine E-Mails versendet.', 'Wählen Sie unter „Einstellungen → E-Mail“ SMTP oder PHP mail().');
        } elseif ($transport === 'smtp' && (string) Settings::get('smtp_host', '') === '') {
            $out[] = self::c('E-Mail', 'mail', 'E-Mail-Versand', 'fail', 'SMTP ist gewählt, aber kein Server eingetragen.', 'Tragen Sie die SMTP-Daten unter „Einstellungen → E-Mail“ ein.');
        } else {
            $failed = (int) DB::val("SELECT COUNT(*) FROM mail_log WHERE status <> 'sent' AND created_at >= ?", [date('Y-m-d H:i:s', time() - 7 * 86400)]);
            $sent = (int) DB::val("SELECT COUNT(*) FROM mail_log WHERE status = 'sent' AND created_at >= ?", [date('Y-m-d H:i:s', time() - 7 * 86400)]);
            $out[] = $failed > 0 && $failed >= max(1, $sent / 4)
                ? self::c('E-Mail', 'mail', 'E-Mail-Versand', $sent === 0 ? 'fail' : 'warn', "$failed fehlgeschlagene E-Mail(s) in 7 Tagen (erfolgreich: $sent)", 'Details unter „E-Mail-Protokoll“; prüfen Sie Zugangsdaten und Absenderadresse.')
                : self::c('E-Mail', 'mail', 'E-Mail-Versand', 'ok', ucfirst($transport) . ', ' . $sent . ' E-Mail(s) in 7 Tagen versendet.');
        }
        $out[] = Settings::companyEmail() === ''
            ? self::c('E-Mail', 'sender', 'Absenderadresse', 'warn', 'Keine Firmen-E-Mail hinterlegt.', 'Tragen Sie unter „Einstellungen → Firma“ eine E-Mail-Adresse ein.')
            : self::c('E-Mail', 'sender', 'Absenderadresse', 'ok', Settings::companyEmail());

        /* ---- Rechnungen & MWST */
        $miss = array_filter(['Firmenname' => 'company_name', 'Strasse' => 'company_street', 'PLZ' => 'company_zip', 'Ort' => 'company_city'], static fn($k) => trim((string) Settings::get($k)) === '');
        $out[] = $miss
            ? self::c('Rechnungen & MWST', 'company', 'Firmendaten', 'warn', 'Es fehlen: ' . implode(', ', array_keys($miss)), 'Rechnungen müssen Name und Adresse des Leistungserbringers enthalten (Art. 26 MWSTG).')
            : self::c('Rechnungen & MWST', 'company', 'Firmendaten', 'ok', 'Vollständig.');
        $uid = trim((string) Settings::get('company_vat_id', ''));
        if (TaxService::liable()) {
            $out[] = $uid === '' || !TaxService::validUid($uid)
                ? self::c('Rechnungen & MWST', 'uid', 'UID / MWST-Nummer', 'fail', $uid === '' ? 'Als MWST-pflichtig geführt, aber keine UID hinterlegt.' : 'Die UID ist ungültig.', 'Rechnungen ohne gültige MWST-Nummer sind für Vorsteuerabzug und Prüfung problematisch (Art. 26 MWSTG).')
                : self::c('Rechnungen & MWST', 'uid', 'UID / MWST-Nummer', 'ok', TaxService::companyUid());
        } else {
            $out[] = self::c('Rechnungen & MWST', 'uid', 'MWST-Status', 'ok', 'Nicht MWST-pflichtig – auf Dokumenten wird keine MWST ausgewiesen.');
        }
        $iban = (string) Settings::get('invoice_iban', '');
        $out[] = $iban === ''
            ? self::c('Rechnungen & MWST', 'iban', 'IBAN für QR-Rechnung', 'warn', 'Keine IBAN hinterlegt – Rechnungen enthalten keinen Einzahlungsschein.', 'Tragen Sie die IBAN unter „Einstellungen → Rechnungen“ ein.')
            : (SwissQr::validIban($iban)
                ? self::c('Rechnungen & MWST', 'iban', 'IBAN für QR-Rechnung', 'ok', SwissQr::formatIban($iban))
                : self::c('Rechnungen & MWST', 'iban', 'IBAN für QR-Rechnung', 'fail', 'Die hinterlegte IBAN ist ungültig.', 'Korrigieren Sie die IBAN unter „Einstellungen → Rechnungen“.'));
        $zeroDefault = TaxService::defaultTemplate();
        if (TaxService::liable() && (!$zeroDefault || (float) $zeroDefault['rate'] <= 0)) {
            $out[] = self::c('Rechnungen & MWST', 'taxdef', 'Standard-Steuersatz', 'warn', 'Kein MWST-Satz als Standard gesetzt.', 'Legen Sie unter „Steuervorlagen“ den Normalsatz (8.1 %) als Standard fest.');
        }

        if (TaxService::liable()) {
            $odd = array_values(array_filter(TaxService::ratesOn(date('Y-m-d')), static fn($r) => !in_array(round($r, 1), [8.1, 2.6, 3.8], true)));
            $out[] = $odd
                ? self::c('Rechnungen & MWST', 'rates', 'Steuersätze', 'warn', 'Abweichend von 8.1 / 2.6 / 3.8 %: ' . implode(', ', array_map(static fn($r) => qty($r) . ' %', $odd)), 'Die Schweizer Sätze (seit 1.1.2024) sind 8.1 %, 2.6 % und 3.8 %. Bei Satzänderungen den alten Satz mit «Gültig bis» befristen und den neuen mit «Gültig ab» anlegen. Prüfen Sie auf Tippfehler.')
                : self::c('Rechnungen & MWST', 'rates', 'Steuersätze', 'ok', 'Gültige Sätze entsprechen den ESTV-Sätzen (8.1 / 2.6 / 3.8 %).');
        } else {
            $reg = TaxService::registrationStatus();
            $out[] = $reg['pct'] >= 100
                ? self::c('Rechnungen & MWST', 'reglimit', 'MWST-Eintragungsgrenze', 'fail', 'Steuerbarer Umsatz ' . money($reg['max']) . ' – über CHF 100’000.', 'Die Eintragung als MWST-pflichtig ist bei der ESTV unverzüglich zu beantragen (Art. 10 Abs. 2 und Art. 66 MWSTG). Danach in den Einstellungen «MWST-pflichtig» aktivieren.')
                : ($reg['pct'] >= 80
                    ? self::c('Rechnungen & MWST', 'reglimit', 'MWST-Eintragungsgrenze', 'warn', 'Steuerbarer Umsatz ' . money($reg['max']) . ' (' . round($reg['pct']) . ' % von CHF 100’000).', 'Bei Überschreiten der Grenze müssen Sie sich bei der ESTV eintragen lassen. Planen Sie rechtzeitig mit Ihrer Treuhandstelle.')
                    : self::c('Rechnungen & MWST', 'reglimit', 'MWST-Eintragungsgrenze', 'ok', 'Steuerbarer Umsatz ' . money($reg['max']) . ' (' . round($reg['pct']) . ' % von CHF 100’000).'));
        }

        /* ---- Zahlungen */
        foreach (['mollie' => 'Mollie', 'stripe' => 'Stripe'] as $p => $name) {
            if (Settings::get("pay_{$p}_enabled") === '1') {
                $key = (string) Settings::get($p === 'mollie' ? 'mollie_api_key' : 'stripe_secret_key');
                if ($key === '') {
                    $out[] = self::c('Online-Zahlung', $p, $name, 'fail', 'Aktiviert, aber kein API-Schlüssel hinterlegt.', 'Hinterlegen Sie den Schlüssel unter „Einstellungen → Zahlungen“.');
                } elseif (PaymentService::isTestKey($p)) {
                    $out[] = self::c('Online-Zahlung', $p, $name, 'warn', 'Testmodus aktiv – es werden keine echten Zahlungen verarbeitet.', 'Wechseln Sie vor dem Echtbetrieb auf den Live-Schlüssel.');
                } elseif ($p === 'stripe' && (string) Settings::get('stripe_webhook_secret') === '') {
                    $out[] = self::c('Online-Zahlung', $p, $name, 'warn', 'Live-Modus, aber kein Webhook-Secret hinterlegt.', 'Ohne Webhook wird eine Zahlung erst bei Rückkehr des Kunden verbucht.');
                } else {
                    $out[] = self::c('Online-Zahlung', $p, $name, 'ok', 'Live-Modus aktiv.');
                }
            }
        }

        /* ---- Anfragen */
        if (InquiryService::enabled()) {
            $newInq = (int) DB::val("SELECT COUNT(*) FROM inquiries WHERE status = 'new' AND created_at < ?", [date('Y-m-d H:i:s', time() - 3 * 86400)]);
            $out[] = $newInq > 0
                ? self::c('Anfragen', 'inquiries', 'Unbeantwortete Anfragen', 'warn', "$newInq Anfrage(n) seit über 3 Tagen offen", 'Öffnen Sie „Anfragen“ und bearbeiten Sie die offenen Einträge.')
                : self::c('Anfragen', 'inquiries', 'Unbeantwortete Anfragen', 'ok', 'Keine überfälligen Anfragen.');
        }
        return $out;
    }

    /** @return array{level:string, ok:int, warn:int, fail:int} */
    public static function summary(?array $checks = null): array
    {
        $checks ??= self::checks();
        $n = ['ok' => 0, 'warn' => 0, 'fail' => 0];
        foreach ($checks as $c) {
            $n[$c['level']]++;
        }
        return ['level' => $n['fail'] ? 'fail' : ($n['warn'] ? 'warn' : 'ok')] + $n;
    }

    /** Für die Seitenleiste: Anzahl offener Punkte, 5 Minuten in der Session zwischengespeichert. */
    public static function badge(): int
    {
        $c = $_SESSION['health_badge'] ?? null;
        if (is_array($c) && time() - $c[0] < 300) {
            return (int) $c[1];
        }
        try {
            $s = self::summary();
            $n = $s['warn'] + $s['fail'];
        } catch (Throwable) {
            $n = 0;
        }
        $_SESSION['health_badge'] = [time(), $n];
        return $n;
    }

    public static function token(): string
    {
        return (string) Settings::get('health_token', '');
    }
}
