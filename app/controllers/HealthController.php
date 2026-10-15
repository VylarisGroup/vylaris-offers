<?php
declare(strict_types=1);

/** Systemstatus: Seite für Administratoren und ein Token-geschützter JSON-Endpunkt für Monitoring (z. B. UptimeRobot). */
final class HealthController
{
    public function index(): void
    {
        Auth::requireAdmin();
        unset($_SESSION['health_badge']);
        $checks = HealthService::checks();
        $summary = HealthService::summary($checks);
        $token = HealthService::token();
        view('admin/health/index', compact('checks', 'summary', 'token'));
    }

    public function token(): void
    {
        $user = Auth::requireAdmin();
        if (input('revoke')) {
            Settings::set(['health_token' => '']);
            Audit::log('health_token', 'Monitoring-Token widerrufen', null, null, $user);
            flash('success', 'Monitoring-Token widerrufen.');
        } else {
            Settings::set(['health_token' => bin2hex(random_bytes(20))]);
            Audit::log('health_token', 'Monitoring-Token erzeugt', null, null, $user);
            flash('success', 'Neuer Monitoring-Token erzeugt.');
        }
        redirect('admin/health');
    }

    /** GET /health?token=… – liefert nur Status je Prüfung, keine Details. 200 bei ok/warn, 503 bei Problemen. */
    public function ping(): void
    {
        $token = HealthService::token();
        $given = (string) ($_GET['token'] ?? '');
        if ($token === '' || RateLimit::tooMany('health:' . client_ip(), 60, 60)) {
            http_response_code($token === '' ? 404 : 429);
            exit;
        }
        RateLimit::hit('health:' . client_ip());
        if (!hash_equals($token, $given)) {
            http_response_code(403);
            exit;
        }
        $checks = HealthService::checks();
        $s = HealthService::summary($checks);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        http_response_code($s['level'] === 'fail' ? 503 : 200);
        echo json_encode([
            'status' => $s['level'], 'ok' => $s['ok'], 'warn' => $s['warn'], 'fail' => $s['fail'], 'time' => date('c'),
            'checks' => array_map(static fn($c) => ['id' => $c['id'], 'status' => $c['level']], $checks),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
