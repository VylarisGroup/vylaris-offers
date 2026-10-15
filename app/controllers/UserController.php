<?php
declare(strict_types=1);

/** Benutzerverwaltung (nur Administratoren) und eigenes Profil (alle). */
final class UserController
{
    public function index(): void
    {
        $me = Auth::requireAdmin();
        $users = DB::all('SELECT id, name, email, role, active, (totp_enabled = 1 OR EXISTS (SELECT 1 FROM passkeys p WHERE p.user_id = users.id)) AS totp_enabled, last_login_at, created_at FROM users ORDER BY role, name');
        view('admin/users/index', compact('users', 'me'));
    }

    public function save(): void
    {
        $me = Auth::requireAdmin();
        $id = input_int('id');
        $data = [
            'name'   => input_str('name', 120),
            'email'  => mb_strtolower(input_str('email', 190)),
            'role'   => input('role') === 'admin' ? 'admin' : 'team',
            'active' => !empty($_POST['active']) ? 1 : 0,
        ];
        $password = (string) ($_POST['password'] ?? '');

        $error = null;
        if ($data['name'] === '') {
            $error = 'Bitte geben Sie einen Namen ein.';
        } elseif (!valid_email($data['email'])) {
            $error = 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
        } elseif (DB::val('SELECT id FROM users WHERE email = ? AND id <> ?', [$data['email'], $id ?? 0])) {
            $error = 'Diese E-Mail-Adresse wird bereits verwendet.';
        } elseif ($password !== '' && ($p = Auth::passwordProblem($password))) {
            $error = $p;
        } elseif ($id === (int) $me['id'] && ($data['role'] !== 'admin' || !$data['active'])) {
            $error = 'Sie können sich selbst weder die Administratorrechte entziehen noch sich deaktivieren.';
        }
        if ($error) {
            flash('error', $error);
            redirect('admin/users');
        }

        if ($id) {
            if ($password !== '') {
                $data['password_hash'] = Auth::hashPassword($password);
            }
            $data['updated_at'] = now();
            DB::update('users', $data, 'id = :id', [':id' => $id]);
            Responsibility::renamed((int) $id, (string) $data['name']);
            Audit::log('user_updated', 'Rolle: ' . $data['role'] . ($data['active'] ? '' : ', deaktiviert') . ($password !== '' ? ', Passwort gesetzt' : ''), 'user', $id);
            flash('success', 'Benutzer „' . $data['name'] . '“ gespeichert.');
        } else {
            // Ohne Passwort wird ein zufälliges gesetzt; die Anmeldung erfolgt dann per Einladungslink.
            $data['password_hash'] = Auth::hashPassword($password !== '' ? $password : bin2hex(random_bytes(24)));
            $data['created_at'] = now();
            $newId = DB::insert('users', $data);
            Audit::log('user_created', 'Rolle: ' . $data['role'], 'user', $newId);
            $msg = 'Benutzer „' . $data['name'] . '“ angelegt.';
            if (!empty($_POST['invite'])) {
                $u = array_merge($data, ['id' => $newId]);
                $msg .= Notifier::staffLink($u, Tokens::create('user', $newId, 60 * 24), true)
                    ? ' Eine Einladung wurde an ' . $data['email'] . ' gesendet.'
                    : ' Die Einladung konnte nicht gesendet werden: ' . Notifier::$lastError;
            }
            flash('success', $msg);
        }
        redirect('admin/users');
    }

    public function invite(string $id): void
    {
        Auth::requireAdmin();
        $u = DB::one('SELECT * FROM users WHERE id = ? AND active = 1', [(int) $id]);
        if (!$u) {
            flash('error', 'Benutzer nicht gefunden oder deaktiviert.');
        } elseif (Notifier::staffLink($u, Tokens::create('user', (int) $u['id'], 60 * 24), true)) {
            flash('success', 'Anmeldelink an ' . $u['email'] . ' gesendet.');
        } else {
            flash('error', 'Die E-Mail konnte nicht gesendet werden: ' . Notifier::$lastError);
        }
        redirect('admin/users');
    }

    public function destroy(string $id): void
    {
        $me = Auth::requireAdmin();
        if ((int) $id === (int) $me['id']) {
            flash('error', 'Sie können Ihr eigenes Konto nicht löschen.');
            redirect('admin/users');
        }
        DB::exec("DELETE FROM login_tokens WHERE subject_type = 'user' AND subject_id = ?", [(int) $id]);
        DB::exec('UPDATE orders SET responsible_id = NULL WHERE responsible_id = ?', [(int) $id]);   // Name bleibt als Hinweis gespeichert
        DB::exec('DELETE FROM users WHERE id = ?', [(int) $id]);
        Audit::log('user_deleted', null, 'user', (int) $id);
        flash('success', 'Benutzer gelöscht. Von ihm erstellte Offerten bleiben mit seinem Namen erhalten; seine Aufträge sind nun nicht zugewiesen.');
        redirect('admin/users');
    }

    public function profile(): void
    {
        $user = Auth::requireUser();
        $tf = DB::one('SELECT totp_enabled, recovery_codes FROM users WHERE id = ?', [$user['id']]);
        $user['totp_enabled'] = (int) $tf['totp_enabled'];
        $user['recovery_left'] = count(json_decode((string) $tf['recovery_codes'], true) ?: []);
        $user['totp'] = (int) $tf['totp_enabled'];
        $passkeys = DB::all('SELECT id, name, created_at, last_used_at FROM passkeys WHERE user_id = ? ORDER BY id', [$user['id']]);
        $user['totp_enabled'] = (int) ($user['totp'] || $passkeys);
        $passkeyAvailable = WebAuthn::available();
        view('admin/users/profile', compact('user', 'passkeys', 'passkeyAvailable'));
    }

    public function saveProfile(): void
    {
        $user = Auth::requireUser();
        $name = input_str('name', 120);
        $new = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');
        if ($name === '') {
            flash('error', 'Bitte geben Sie Ihren Namen ein.');
            redirect('admin/profile');
        }
        $data = ['name' => $name, 'updated_at' => now()];
        if ($new !== '') {
            if ($new !== $confirm) {
                flash('error', 'Die Passwörter stimmen nicht überein.');
                redirect('admin/profile');
            }
            if ($p = Auth::passwordProblem($new)) {
                flash('error', $p);
                redirect('admin/profile');
            }
            $data['password_hash'] = Auth::hashPassword($new);
        }
        DB::update('users', $data, 'id = :id', [':id' => $user['id']]);
        Responsibility::renamed((int) $user['id'], $name);
        if (isset($data['password_hash'])) {
            Audit::log('password_changed', null, 'user', (int) $user['id']);
        }
        flash('success', $new !== '' ? 'Profil und Passwort gespeichert.' : 'Profil gespeichert.');
        redirect('admin/profile');
    }
}
