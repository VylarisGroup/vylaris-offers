<?php
declare(strict_types=1);

/** Software-Update aus GitHub-Releases. Nur Administratoren. */
final class UpdateController
{
    public function index(): void
    {
        Auth::requireAdmin();
        $latest = UpdateService::latest();
        $last = json_decode((string) Settings::get('update_last', ''), true) ?: null;
        $backups = UpdateService::backups();
        $zipOk = class_exists('ZipArchive');
        view('admin/update/index', compact('latest', 'last', 'backups', 'zipOk'));
    }

    public function save(): void
    {
        $user = Auth::requireAdmin();
        $auto = !empty($_POST['update_auto']) ? '1' : '0';
        Settings::set(['update_auto' => $auto]);
        Audit::log('update_settings', 'Automatische Updates: ' . ($auto === '1' ? 'ein' : 'aus'), null, null, $user);
        flash('success', 'Gespeichert.');
        redirect('admin/update');
    }

    public function action(string $action): void
    {
        $user = Auth::requireAdmin();
        switch ($action) {
            case 'check':
                $r = UpdateService::check();
                flash($r['ok'] ? 'success' : 'error', $r['message']);
                break;
            case 'install':
                $r = UpdateService::install($user['email'] ?? 'Admin');
                Audit::log($r['ok'] ? 'update_installed' : 'update_failed', $r['message'], null, null, $user);
                flash($r['ok'] ? 'success' : 'error', $r['message']);
                break;
            case 'rollback':
                $r = UpdateService::rollback($user['email'] ?? 'Admin');
                Audit::log('update_rollback', $r['message'], null, null, $user);
                flash($r['ok'] ? 'success' : 'error', $r['message']);
                break;
            default:
                http_response_code(404);
                exit;
        }
        redirect('admin/update');
    }
}
