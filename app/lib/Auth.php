<?php
declare(strict_types=1);

/**
 * Authentifizierung für Mitarbeitende (Passwort oder Magic-Link)
 * und Kunden (ausschliesslich Magic-Link). Getrennte Session-Schlüssel.
 */
final class Auth
{
    private const STAFF_IDLE = 8 * 3600;
    private const CUSTOMER_IDLE = 4 * 3600;

    private static ?array $user = null;
    private static ?array $customer = null;

    /* -------- Mitarbeitende -------- */

    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }
        $id = $_SESSION['uid'] ?? null;
        if (!$id) {
            return null;
        }
        if (time() - (int) ($_SESSION['uid_seen'] ?? 0) > self::STAFF_IDLE) {
            self::logoutUser();
            return null;
        }
        $u = DB::one('SELECT id, name, email, role, active FROM users WHERE id = ?', [$id]);
        if (!$u || !$u['active']) {
            self::logoutUser();
            return null;
        }
        $_SESSION['uid_seen'] = time();
        return self::$user = $u;
    }

    public static function requireUser(): array
    {
        $u = self::user();
        if (!$u) {
            if (wants_json()) {
                throw new HttpException(401, 'Bitte melden Sie sich erneut an.');
            }
            $_SESSION['intended'] = $_SERVER['REQUEST_URI'] ?? null;
            redirect('admin/login');
        }
        return $u;
    }

    public static function requireAdmin(): array
    {
        $u = self::requireUser();
        if ($u['role'] !== 'admin') {
            throw new HttpException(403, 'Diese Funktion ist Administratoren vorbehalten.');
        }
        return $u;
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? null) === 'admin';
    }

    /** Meldet an – oder verlangt zuerst den zweiten Faktor, falls das Konto 2FA aktiviert hat. */
    public static function startLogin(array $u): void
    {
        if (!empty($u['totp_enabled']) || DB::val('SELECT COUNT(*) FROM passkeys WHERE user_id = ?', [$u['id']])) {
            session_regenerate_id(true);
            $_SESSION['2fa_uid'] = (int) $u['id'];
            $_SESSION['2fa_exp'] = time() + 300;
            redirect('admin/2fa');
        }
        self::loginUser($u);
    }

    public static function loginUser(array $u): void
    {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $u['id'];
        $_SESSION['uid_seen'] = time();
        self::$user = null;
        DB::exec('UPDATE users SET last_login_at = ? WHERE id = ?', [now(), $u['id']]);
        Audit::log('login', null, 'user', (int) $u['id'], $u);
    }

    public static function logoutUser(): void
    {
        unset($_SESSION['uid'], $_SESSION['uid_seen']);
        self::$user = null;
        session_regenerate_id(true);
    }

    /* -------- Kunden -------- */

    public static function customer(): ?array
    {
        if (self::$customer !== null) {
            return self::$customer;
        }
        $id = $_SESSION['cid'] ?? null;
        if (!$id) {
            return null;
        }
        if (time() - (int) ($_SESSION['cid_seen'] ?? 0) > self::CUSTOMER_IDLE) {
            self::logoutCustomer();
            return null;
        }
        $c = DB::one('SELECT * FROM customers WHERE id = ? AND portal_enabled = 1', [$id]);
        if (!$c) {
            self::logoutCustomer();
            return null;
        }
        $_SESSION['cid_seen'] = time();
        return self::$customer = $c;
    }

    public static function requireCustomer(): array
    {
        $c = self::customer();
        if (!$c) {
            redirect('portal');
        }
        return $c;
    }

    public static function loginCustomer(array $c): void
    {
        session_regenerate_id(true);
        $_SESSION['cid'] = (int) $c['id'];
        $_SESSION['cid_seen'] = time();
        self::$customer = null;
        DB::exec('UPDATE customers SET last_portal_login_at = ? WHERE id = ?', [now(), $c['id']]);
    }

    public static function logoutCustomer(): void
    {
        unset($_SESSION['cid'], $_SESSION['cid_seen']);
        self::$customer = null;
    }

    public static function hashPassword(string $password): string
    {
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        return password_hash($password, $algo);
    }

    public static function passwordProblem(string $password): ?string
    {
        if (mb_strlen($password) < 10) {
            return 'Das Passwort muss mindestens 10 Zeichen lang sein.';
        }
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            return 'Das Passwort muss Buchstaben und Ziffern enthalten.';
        }
        return null;
    }
}
