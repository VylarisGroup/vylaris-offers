<?php
declare(strict_types=1);

/** Lizenz-Seite: Status, Funktionen (Scopes), Schlüssel eingeben, aktivieren, prüfen, Offline-Lizenz. Nur Administratoren. */
final class LicenseController
{
    public function index(): void
    {
        Auth::requireAdmin();
        $ev = License::status();
        $scopes = License::scopes();
        $hwid = License::hwid();
        $st = json_decode((string) Settings::get('license_state', ''), true) ?: [];
        view('admin/license/index', compact('ev', 'scopes', 'hwid', 'st'));
    }

    public function save(): void
    {
        $user = Auth::requireAdmin();
        $key = strtoupper(trim((string) ($_POST['license_key'] ?? '')));
        if ($key !== '' && !preg_match('/^[A-Z0-9][A-Z0-9_-]{5,63}$/', $key)) {
            flash('error', 'Der Lizenzschlüssel hat ein ungültiges Format.');
            redirect('admin/license');
        }
        if (isset($_POST['advanced'])) {
            Settings::set(['license_grace_days' => (string) max(0, min(14, (int) ($_POST['license_grace_days'] ?? 3)))]);
        }
        License::setKey($key);
        Audit::log('license', $key === '' ? 'Schlüssel entfernt' : 'Schlüssel/Einstellungen geändert', null, null, $user);
        if ($key !== '' && !isset($_POST['advanced'])) {
            $r = License::activate();
            flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Die Lizenz wurde aktiviert.' : $r['message']);
        } else {
            flash('success', 'Gespeichert.');
        }
        redirect('admin/license');
    }

    public function action(string $action): void
    {
        $user = Auth::requireAdmin();
        $res = match ($action) {
            'check'      => License::call('validate'),
            'activate'   => License::activate(),
            'deactivate' => License::deactivate(),
            'offline'    => License::fetchOffline() + ['ok' => true],
            default      => throw new HttpException(404),
        };
        if ($action === 'offline') {
            $res['ok'] = str_starts_with((string) $res['message'], 'Offline-Lizenz gespeichert');
        }
        Audit::log('license', 'Aktion: ' . $action . ' → ' . ($res['ok'] ? 'ok' : (string) ($res['code'] ?? 'Fehler')), null, null, $user);
        $ok = $res['ok'] || ($action === 'check' && ($res['code'] ?? '') === 'OK');
        flash($ok ? 'success' : 'error', $ok ? ($action === 'deactivate' ? 'Das Gerät wurde abgemeldet – der Platz ist wieder frei.' : ($res['message'] ?: 'Erledigt.')) : $res['message']);
        redirect('admin/license');
    }
}
