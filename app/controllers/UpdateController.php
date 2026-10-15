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
        $raw = trim((string) ($_POST['update_repo'] ?? ''));
        $repo = $raw === '' ? UpdateService::DEFAULT_REPO : UpdateService::parseRepo($raw);
        if ($repo === null) {
            flash('error', 'Das Repository ist ungültig. Erwartet: «besitzer/repository» oder die GitHub-Adresse.');
            redirect('admin/update');
        }
        $set = ['update_repo' => $repo, 'update_auto' => !empty($_POST['update_auto']) ? '1' : '0'];
        if (!empty($_POST['update_token_remove'])) {
            $set['update_token'] = '';
        } elseif (trim((string) ($_POST['update_token'] ?? '')) !== '') {
            $tok = trim((string) $_POST['update_token']);
            if (!preg_match('/^[A-Za-z0-9_\-]{20,255}$/', $tok)) {
                flash('error', 'Das Zugriffs-Token hat ein ungültiges Format.');
                redirect('admin/update');
            }
            $set['update_token'] = $tok;
        }
        if ($repo !== UpdateService::repo()) {
            $set['update_latest'] = '';
            $set['update_error'] = '';
        }
        Settings::set($set);
        Audit::log('update_settings', 'Repository: ' . ($repo ?: '–') . ', Automatik: ' . ($set['update_auto'] === '1' ? 'ein' : 'aus'), null, null, $user);
        if ($repo !== '') {
            $r = UpdateService::check();
            flash($r['ok'] ? 'success' : 'error', $r['message']);
        } else {
            flash('success', 'Gespeichert.');
        }
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
