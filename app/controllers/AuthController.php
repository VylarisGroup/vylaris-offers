<?php
declare(strict_types=1);

/** Anmeldung für Administratoren und Team (Passwort oder Magic-Link). */
final class AuthController
{
    public function loginForm(): void
    {
        if (Auth::user()) {
            redirect('admin');
        }
        view('auth/login', ['email' => ''], 'auth');
    }

    public function login(): void
    {
        if (SsoController::required() && empty($_POST['local'])) {
            redirect('admin/login');
        }
        $email = mb_strtolower(input_str('email', 190));
        $password = (string) ($_POST['password'] ?? '');
        $ipBucket = 'login-ip:' . client_ip();
        $mailBucket = 'login-mail:' . $email;

        if (RateLimit::tooMany($ipBucket, 20, 900) || RateLimit::tooMany($mailBucket, 6, 900)) {
            view('auth/login', ['email' => $email, 'error' => 'Zu viele Anmeldeversuche. Bitte warten Sie 15 Minuten oder verwenden Sie einen Anmeldelink.'], 'auth');
        }

        $user = $email !== '' ? DB::one('SELECT * FROM users WHERE email = ?', [$email]) : null;
        // Gleiche Laufzeit, auch wenn der Benutzer nicht existiert.
        $hash = $user['password_hash'] ?? '$2y$10$abcdefghijklmnopqrstuuYcHkWjWZlqIrA3x2rQ0yR0ozv8nFyW2';
        $ok = password_verify($password, $hash) && $user && $user['active'];

        if (!$ok) {
            RateLimit::hit($ipBucket);
            RateLimit::hit($mailBucket);
            Audit::log('login_failed', $user ? 'Konto vorhanden' : 'Unbekanntes Konto', 'user', $user ? (int) $user['id'] : null, $user ?: null);
            view('auth/login', ['email' => $email, 'error' => 'E-Mail-Adresse oder Passwort ist falsch.'], 'auth');
        }

        RateLimit::clear($mailBucket);
        if (password_needs_rehash($user['password_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT)) {
            DB::exec('UPDATE users SET password_hash = ? WHERE id = ?', [Auth::hashPassword($password), $user['id']]);
        }
        Auth::startLogin($user);
        $this->redirectIntended();
    }

    public function requestLink(): void
    {
        if (SsoController::required() && empty($_POST['local'])) {
            redirect('admin/login');
        }
        $email = mb_strtolower(input_str('email', 190));
        if (!valid_email($email)) {
            view('auth/login', ['email' => $email, 'mode' => 'magic', 'error' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.'], 'auth');
        }
        $bucket = 'staff-link:' . $email;
        if (!RateLimit::tooMany($bucket, 3, 900) && !RateLimit::tooMany('staff-link-ip:' . client_ip(), 10, 900)) {
            RateLimit::hit($bucket);
            RateLimit::hit('staff-link-ip:' . client_ip());
            $user = DB::one('SELECT * FROM users WHERE email = ? AND active = 1', [$email]);
            if ($user) {
                Notifier::staffLink($user, Tokens::create('user', (int) $user['id'], 30));
            }
        }
        view('auth/link-sent', ['email' => $email, 'back' => 'admin/login'], 'auth');
    }

    public function confirm(string $token): void
    {
        $row = Tokens::find('user', $token);
        view($row ? 'auth/confirm' : 'auth/link-invalid', [
            'action' => 'admin/auth/' . $token,
            'back'   => 'admin/login',
            'area'   => 'Administration',
        ], 'auth');
    }

    public function consume(string $token): void
    {
        $row = Tokens::find('user', $token);
        $user = $row ? DB::one('SELECT * FROM users WHERE id = ? AND active = 1', [$row['subject_id']]) : null;
        if (!$row || !$user || !Tokens::consume($row)) {
            view('auth/link-invalid', ['back' => 'admin/login'], 'auth');
        }
        Auth::startLogin($user);
        flash('success', 'Willkommen, ' . $user['name'] . '!');
        $this->redirectIntended();
    }

    public function logout(): void
    {
        Auth::logoutUser();
        flash('success', 'Sie wurden abgemeldet.');
        redirect('admin/login');
    }

    private function redirectIntended(): never
    {
        $intended = (string) ($_SESSION['intended'] ?? '');
        unset($_SESSION['intended']);
        $base = base_path() . '/admin';
        if ($intended !== '' && str_starts_with($intended, $base) && !str_contains($intended, '//') && !str_contains($intended, '/admin/auth')) {
            $host = rtrim((string) preg_replace('#^(https?://[^/]+).*$#', '$1', (string) app_config('app.base_url')), '/');
            redirect($host . $intended);
        }
        redirect('admin');
    }
}
