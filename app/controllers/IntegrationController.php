<?php
declare(strict_types=1);

/** Integrationen (nur Administratoren): API-Tokens, Webhooks, Buchungsjournal für die Buchhaltung. */
final class IntegrationController
{
    public function index(): void
    {
        Auth::requireAdmin();
        $once = $_SESSION['integration_once'] ?? null;   // Token/Secret werden genau einmal angezeigt
        unset($_SESSION['integration_once']);
        $tokens = DB::all('SELECT * FROM api_tokens ORDER BY id DESC');
        $hooks = DB::all('SELECT * FROM webhooks ORDER BY id');
        $deliveries = DB::all('SELECT d.id, d.event, d.status, d.attempts, d.last_http, d.last_error, d.created_at, w.name FROM webhook_deliveries d JOIN webhooks w ON w.id = d.webhook_id ORDER BY d.id DESC LIMIT 15');
        $acc = ['acc_debtors' => Settings::get('acc_debtors') ?: '1100', 'acc_revenue' => Settings::get('acc_revenue') ?: '3200', 'acc_bank' => Settings::get('acc_bank') ?: '1020', 'acc_online' => Settings::get('acc_online') ?: '1021', 'acc_cash' => Settings::get('acc_cash') ?: '1000', 'acc_discount' => Settings::get('acc_discount') ?: '3800'];
        view('admin/integrations/index', compact('tokens', 'hooks', 'deliveries', 'acc', 'once'));
    }

    /* ---- API-Tokens */

    public function createToken(): void
    {
        $user = Auth::requireAdmin();
        $name = input_str('name', 120);
        $scopes = array_keys(array_filter((array) ($_POST['scopes'] ?? [])));
        if ($name === '' || !array_intersect($scopes, array_keys(ApiTokens::SCOPES))) {
            flash('error', 'Bitte einen Namen und mindestens einen Bereich wählen.');
            redirect('admin/integrations');
        }
        $days = input_int('days');
        [, $token] = ApiTokens::create($name, $scopes, $days ? max(1, min(3650, $days)) : null, $user);
        $_SESSION['integration_once'] = ['type' => 'token', 'value' => $token, 'name' => $name];
        redirect('admin/integrations');
    }

    public function revokeToken(string $id): void
    {
        $user = Auth::requireAdmin();
        ApiTokens::revoke((int) $id, $user);
        flash('success', 'Token widerrufen.');
        redirect('admin/integrations');
    }

    /* ---- Webhooks */

    public function saveHook(?string $id = null): void
    {
        $user = Auth::requireAdmin();
        $name = input_str('name', 120);
        $url = trim(input_str('url', 500));
        $events = array_values(array_intersect(array_keys(Webhooks::EVENTS), array_keys(array_filter((array) ($_POST['events'] ?? [])))));
        $err = null;
        if ($name === '' || !$events) {
            $err = 'Bitte Namen und mindestens ein Ereignis wählen.';
        } elseif ($e = Webhooks::validateUrl($url)) {
            $err = $e;
        }
        if ($err) {
            flash('error', $err);
            redirect('admin/integrations');
        }
        $data = ['name' => $name, 'url' => $url, 'events' => implode(',', $events), 'active' => !empty($_POST['active']) ? 1 : 0];
        if ($id) {
            DB::update('webhooks', $data, 'id = :id', [':id' => (int) $id]);
            Audit::log('webhook', 'Geändert: ' . $name, 'webhook', (int) $id, $user);
            flash('success', 'Webhook gespeichert.');
        } else {
            $secret = 'whsec_' . bin2hex(random_bytes(24));
            $data['secret'] = Crypto::encrypt($secret);
            $data['created_at'] = now();
            $newId = DB::insert('webhooks', $data);
            Audit::log('webhook', 'Erstellt: ' . $name, 'webhook', $newId, $user);
            $_SESSION['integration_once'] = ['type' => 'secret', 'value' => $secret, 'name' => $name];
        }
        redirect('admin/integrations');
    }

    public function deleteHook(string $id): void
    {
        $user = Auth::requireAdmin();
        DB::exec('DELETE FROM webhooks WHERE id = ?', [(int) $id]);
        Audit::log('webhook', 'Gelöscht', 'webhook', (int) $id, $user);
        flash('success', 'Webhook gelöscht.');
        redirect('admin/integrations');
    }

    public function rotateSecret(string $id): void
    {
        $user = Auth::requireAdmin();
        $hook = DB::one('SELECT * FROM webhooks WHERE id = ?', [(int) $id]);
        if ($hook) {
            $secret = 'whsec_' . bin2hex(random_bytes(24));
            DB::exec('UPDATE webhooks SET secret = ? WHERE id = ?', [Crypto::encrypt($secret), $hook['id']]);
            Audit::log('webhook', 'Secret erneuert', 'webhook', (int) $hook['id'], $user);
            $_SESSION['integration_once'] = ['type' => 'secret', 'value' => $secret, 'name' => $hook['name']];
        }
        redirect('admin/integrations');
    }

    public function testHook(string $id): void
    {
        Auth::requireAdmin();
        $hook = DB::one('SELECT * FROM webhooks WHERE id = ?', [(int) $id]);
        if ($hook && ($e = Webhooks::validateUrl((string) $hook['url']))) {
            flash('error', $e);
        } elseif ($hook) {
            [$http, $err] = Webhooks::ping($hook);
            $http >= 200 && $http < 300 ? flash('success', "Testaufruf erfolgreich (HTTP $http).") : flash('error', 'Testaufruf fehlgeschlagen: ' . ($err ?: "HTTP $http"));
        }
        redirect('admin/integrations');
    }

    /* ---- Buchhaltung */

    public function saveAccounts(): void
    {
        $user = Auth::requireAdmin();
        $vals = [];
        foreach (['acc_debtors', 'acc_revenue', 'acc_bank', 'acc_online', 'acc_cash', 'acc_discount'] as $k) {
            $v = trim(input_str($k, 20));
            if ($v === '' || !preg_match('/^[0-9A-Za-z.\-]{1,20}$/', $v)) {
                flash('error', 'Kontonummern dürfen nur Buchstaben, Ziffern, Punkt und Bindestrich enthalten.');
                redirect('admin/integrations');
            }
            $vals[$k] = $v;
        }
        Settings::set($vals);
        Audit::log('settings_saved', 'Kontenzuordnung Buchungsjournal', null, null, $user);
        flash('success', 'Kontenzuordnung gespeichert.');
        redirect('admin/integrations');
    }

    public function journal(): void
    {
        $user = Auth::requireAdmin();
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['from'] ?? '')) ? $_GET['from'] : date('Y-01-01');
        $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['to'] ?? '')) ? $_GET['to'] : date('Y-m-d');
        Audit::log('export', "Buchungsjournal $from – $to", null, null, $user);
        ReportService::sendCsv("buchungsjournal-$from-$to.csv",
            ['Datum', 'Belegnummer', 'Buchungstext', 'Soll', 'Haben', 'Betrag brutto', 'Währung', 'Steuersatz %', 'Steuerbetrag', 'Kunden-Nr.'],
            ReportService::journal($from, $to));
    }
}
