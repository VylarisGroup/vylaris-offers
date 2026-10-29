<?php
declare(strict_types=1);

/** Verwaltung des Kundenportals (Team): Status, Diagnose der Anmeldung, Zugang je Kunde. */
final class PortalAdminController
{
    public function index(): void
    {
        Auth::requireUser();
        $q = trim(input_str('q', 190));
        $diag = $q !== '' ? $this->diagnose(mb_strtolower($q)) : null;
        $checks = $this->checks();
        $stats = [
            'enabled' => (int) DB::val('SELECT COUNT(*) FROM customers WHERE portal_enabled = 1 AND anonymized_at IS NULL'),
            'disabled' => (int) DB::val('SELECT COUNT(*) FROM customers WHERE portal_enabled = 0 AND anonymized_at IS NULL'),
            'logged_in' => (int) DB::val('SELECT COUNT(*) FROM customers WHERE last_portal_login_at IS NOT NULL'),
            'unread' => PortalMessage::unreadForTeam(),
        ];
        $mailLog = DB::all("SELECT * FROM mail_log WHERE context = 'portal_link' ORDER BY id DESC LIMIT 8");
        $filter = in_array($_GET['f'] ?? '', ['on', 'off', 'never'], true) ? $_GET['f'] : '';
        $where = match ($filter) {
            'on' => 'portal_enabled = 1', 'off' => 'portal_enabled = 0',
            'never' => 'portal_enabled = 1 AND last_portal_login_at IS NULL', default => '1',
        };
        $customers = DB::all("SELECT id, customer_no, first_name, last_name, company, email, portal_enabled, last_portal_login_at, restricted_at,
                (SELECT COUNT(*) FROM portal_messages m WHERE m.customer_id = customers.id AND m.direction = 'in' AND m.read_at IS NULL) AS unread
            FROM customers WHERE anonymized_at IS NULL AND $where ORDER BY last_name, company LIMIT 300");
        view('admin/portal/index', compact('q', 'diag', 'checks', 'stats', 'mailLog', 'customers', 'filter'));
    }

    /** Systemprüfung: [Label, ok(bool|null), Hinweis]. */
    private function checks(): array
    {
        $transport = (string) Settings::get('mail_transport', 'smtp');
        $mailOk = $transport === 'log' || $transport === 'file' || ($transport === 'smtp' && (string) Settings::get('smtp_host') !== '') || $transport === 'mail';
        $last = DB::one("SELECT status, error, created_at FROM mail_log ORDER BY id DESC LIMIT 1");
        $https = is_https();
        $base = (string) app_config('app.base_url', '');
        return [
            ['E-Mail-Versand', $mailOk, $mailOk ? 'Transport: ' . $transport . ($last ? ' · letzter Versand: ' . ($last['status'] === 'sent' ? 'ok' : 'FEHLGESCHLAGEN – ' . mb_substr((string) $last['error'], 0, 160)) : '') : 'Kein SMTP-Server eingetragen – Anmeldelinks können nicht versendet werden (Einstellungen → E-Mail).'],
            ['Basis-Adresse (Links in E-Mails)', $base !== '' && !str_contains($base, 'localhost'), $base !== '' ? 'Links zeigen auf ' . $base . ($https ? '' : ' (kein HTTPS erkannt)') : 'app.base_url fehlt in app/config.php – Links in E-Mails sind dann unbrauchbar.'],
            ['Kunden mit Portalzugang', (int) DB::val('SELECT COUNT(*) FROM customers WHERE portal_enabled = 1 AND anonymized_at IS NULL') > 0, 'Nur Kunden mit aktivem Zugang erhalten einen Anmeldelink (Kunde → «Kundenportal-Zugang»).'],
        ];
    }

    /** Warum bekommt diese Adresse keinen Link? */
    private function diagnose(string $email): array
    {
        $out = [];
        if (!valid_email($email)) {
            return [['err', 'Das ist keine gültige E-Mail-Adresse.']];
        }
        $c = DB::one('SELECT * FROM customers WHERE email = ?', [$email]);
        if (!$c) {
            $out[] = ['err', 'Kein Kunde mit dieser E-Mail-Adresse. Die Anmeldung verlangt exakt die beim Kunden hinterlegte Adresse (gross/klein egal). Das Portal verrät dem Besucher bewusst nicht, ob eine Adresse existiert.'];
            return $out;
        }
        $out[] = ['ok', 'Kunde gefunden: ' . customer_name($c) . ' (' . $c['customer_no'] . ').'];
        $out[] = $c['portal_enabled'] ? ['ok', 'Portalzugang ist aktiv.'] : ['err', 'Portalzugang ist deaktiviert (Kunde bearbeiten → «Kundenportal-Zugang»).'];
        if ($c['anonymized_at'] || $c['restricted_at']) {
            $out[] = ['err', 'Der Kunde ist eingeschränkt bzw. anonymisiert (Datenschutz) – der Zugang bleibt gesperrt.'];
        }
        $bucket = (int) DB::val("SELECT COUNT(*) FROM rate_limits WHERE bucket = ? AND created_at > ?", ['portal-link:' . $email, date('Y-m-d H:i:s', time() - 900)]);
        $out[] = $bucket >= 3 ? ['err', 'Zu viele Anforderungen: maximal 3 Links pro 15 Minuten und Adresse. Bitte kurz warten.'] : ['ok', 'Keine Sperre durch Anforderungs-Limit.'];
        $m = DB::one("SELECT status, error, created_at FROM mail_log WHERE recipient = ? AND context = 'portal_link' ORDER BY id DESC LIMIT 1", [$email]);
        if (!$m) {
            $out[] = ['warn', 'Bisher wurde an diese Adresse kein Anmeldelink versendet.'];
        } elseif ($m['status'] === 'sent') {
            $out[] = ['ok', 'Letzter Link wurde am ' . fmt_datetime($m['created_at']) . ' erfolgreich versendet – Spam-Ordner prüfen; Links sind 30 Minuten gültig und einmalig.'];
        } else {
            $out[] = ['err', 'Letzter Versand am ' . fmt_datetime($m['created_at']) . ' fehlgeschlagen: ' . mb_substr((string) $m['error'], 0, 200)];
        }
        return $out;
    }

    public function toggle(string $id): void
    {
        $user = Auth::requireUser();
        $c = DB::one('SELECT * FROM customers WHERE id = ? AND anonymized_at IS NULL', [(int) $id]);
        if (!$c) {
            throw new HttpException(404, 'Kunde nicht gefunden.');
        }
        $new = $c['portal_enabled'] ? 0 : 1;
        DB::exec('UPDATE customers SET portal_enabled = ?, updated_at = ? WHERE id = ?', [$new, now(), $c['id']]);
        if (!$new) {
            DB::exec("DELETE FROM login_tokens WHERE subject_type = 'customer' AND subject_id = ?", [$c['id']]);
        }
        Audit::log('portal_access', ($new ? 'Aktiviert: ' : 'Deaktiviert: ') . $c['customer_no'], 'customer', (int) $c['id'], $user);
        flash('success', 'Portalzugang für ' . customer_name($c) . ($new ? ' aktiviert.' : ' deaktiviert.'));
        redirect('admin/portal' . (!empty($_POST['f']) ? '?f=' . rawurlencode((string) $_POST['f']) : ''));
    }

    public function sendLink(string $id): void
    {
        Auth::requireUser();
        $c = DB::one('SELECT * FROM customers WHERE id = ? AND anonymized_at IS NULL', [(int) $id]);
        if (!$c || !$c['portal_enabled']) {
            flash('error', 'Der Portalzugang dieses Kunden ist deaktiviert.');
        } elseif (Notifier::portalLink($c, Tokens::create('customer', (int) $c['id'], 60 * 24))) {
            flash('success', 'Anmeldelink an ' . $c['email'] . ' gesendet (24 Stunden gültig).');
        } else {
            flash('error', 'Die E-Mail konnte nicht gesendet werden: ' . Notifier::$lastError);
        }
        redirect('admin/portal');
    }
}
