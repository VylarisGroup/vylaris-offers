<?php
declare(strict_types=1);

/** Optionale Zwei-Faktor-Anmeldung (TOTP) für Mitarbeitende. */
final class TwoFactorController
{
    /* ---------------------------------------------------------------- Anmeldung */

    private function pending(): array
    {
        return self::pendingUser();
    }

    /** Konto, das gerade den zweiten Faktor eingibt (TOTP-Code und/oder Passkey). */
    public static function pendingUser(bool $json = false): array
    {
        $id = (int) ($_SESSION['2fa_uid'] ?? 0);
        $exp = (int) ($_SESSION['2fa_exp'] ?? 0);
        $u = $id && $exp > time() ? DB::one('SELECT * FROM users WHERE id = ? AND active = 1 AND (totp_enabled = 1 OR EXISTS (SELECT 1 FROM passkeys p WHERE p.user_id = users.id))', [$id]) : null;
        if (!$u) {
            unset($_SESSION['2fa_uid'], $_SESSION['2fa_exp']);
            if ($json) {
                json_response(['ok' => false, 'error' => 'Die Anmeldung ist abgelaufen. Bitte starten Sie neu.', 'redirect' => url('admin/login')], 401);
            }
            flash('error', 'Die Anmeldung ist abgelaufen. Bitte starten Sie neu.');
            redirect('admin/login');
        }
        return $u;
    }

    private static function formData(array $u, ?string $error = null): array
    {
        return [
            'email' => $u['email'], 'error' => $error,
            'hasTotp' => (bool) $u['totp_enabled'],
            'hasPasskey' => (bool) DB::val('SELECT COUNT(*) FROM passkeys WHERE user_id = ?', [$u['id']]) && WebAuthn::available(),
        ];
    }

    public function form(): void
    {
        $u = $this->pending();
        view('auth/two-factor', self::formData($u), 'auth');
    }

    public function verify(): void
    {
        $u = $this->pending();
        $bucket = '2fa:' . $u['id'];
        $ipBucket = '2fa-ip:' . client_ip();
        if (RateLimit::tooMany($bucket, 6, 900) || RateLimit::tooMany($ipBucket, 20, 900)) {
            view('auth/two-factor', self::formData($u, 'Zu viele Versuche. Bitte warten Sie 15 Minuten.'), 'auth');
        }
        $code = trim((string) ($_POST['code'] ?? ''));
        $ok = false;
        $usedRecovery = false;
        if (preg_match('/^\d{3}\s?\d{3}$/', $code)) {
            $ok = self::consumeTotp($u, $code);
        } elseif ($code !== '') {
            $ok = self::consumeRecovery($u, $code);
            $usedRecovery = $ok;
        }
        if (!$ok) {
            Audit::log('2fa_failed', null, 'user', (int) $u['id'], $u);
            RateLimit::hit($bucket);
            RateLimit::hit($ipBucket);
            view('auth/two-factor', self::formData($u, 'Der Code ist ungültig oder abgelaufen.'), 'auth');
        }
        RateLimit::clear($bucket);
        unset($_SESSION['2fa_uid'], $_SESSION['2fa_exp']);
        Auth::loginUser($u);
        if ($usedRecovery) {
            $left = count(json_decode((string) DB::val('SELECT recovery_codes FROM users WHERE id = ?', [$u['id']]), true) ?: []);
            flash('warning', "Wiederherstellungscode verwendet. Verbleibende Codes: $left. Unter „Mein Profil“ können Sie neue erzeugen.");
        } else {
            flash('success', 'Willkommen, ' . $u['name'] . '!');
        }
        self::redirectIntended();
    }

    public function cancel(): void
    {
        unset($_SESSION['2fa_uid'], $_SESSION['2fa_exp']);
        redirect('admin/login');
    }

    private static function redirectIntended(): never
    {
        redirect(self::intendedUrl());
    }

    /** Zielseite nach der Anmeldung (nur Adressen unterhalb von /admin). */
    public static function intendedUrl(): string
    {
        $intended = (string) ($_SESSION['intended'] ?? '');
        unset($_SESSION['intended']);
        $base = base_path() . '/admin';
        if ($intended !== '' && str_starts_with($intended, $base) && !str_contains($intended, '//') && !str_contains($intended, '/admin/auth')) {
            $host = rtrim((string) preg_replace('#^(https?://[^/]+).*$#', '$1', (string) app_config('app.base_url')), '/');
            return $host . $intended;
        }
        return url('admin');
    }

    /** Prüft den Code und sperrt den Zeitschritt gegen Wiederverwendung (atomar). */
    public static function consumeTotp(array $u, string $code): bool
    {
        $secret = Crypto::decrypt((string) $u['totp_secret']);
        if (!$secret) {
            return false;
        }
        $step = Totp::verify($secret, $code, $u['totp_last_step'] !== null ? (int) $u['totp_last_step'] : null);
        if ($step === null) {
            return false;
        }
        return DB::exec('UPDATE users SET totp_last_step = ? WHERE id = ? AND (totp_last_step IS NULL OR totp_last_step < ?)', [$step, $u['id'], $step]) === 1;
    }

    public static function consumeRecovery(array $u, string $code): bool
    {
        $hash = Totp::hashCode($code);
        return (bool) DB::tx(static function () use ($u, $hash): bool {
            $row = DB::one('SELECT recovery_codes FROM users WHERE id = ? FOR UPDATE', [$u['id']]);
            $list = json_decode((string) ($row['recovery_codes'] ?? ''), true) ?: [];
            foreach ($list as $i => $h) {
                if (hash_equals($h, $hash)) {
                    unset($list[$i]);
                    DB::exec('UPDATE users SET recovery_codes = ? WHERE id = ?', [json_encode(array_values($list)), $u['id']]);
                    return true;
                }
            }
            return false;
        });
    }

    /* ---------------------------------------------------------------- Profil: Einrichtung */

    public function setup(): void
    {
        $user = Auth::requireUser();
        $row = DB::one('SELECT totp_enabled FROM users WHERE id = ?', [$user['id']]);
        if ($row['totp_enabled']) {
            flash('info', 'Die Zwei-Faktor-Anmeldung ist bereits aktiv.');
            redirect('admin/profile');
        }
        $_SESSION['2fa_setup'] = $_SESSION['2fa_setup'] ?? Totp::generateSecret();
        $secret = $_SESSION['2fa_setup'];
        $issuer = Brand::name();
        $uri = Totp::uri($secret, $user['email'], $issuer);
        view('admin/users/two-factor-setup', ['secret' => $secret, 'qr' => SwissQr::svg($uri, 220)]);
    }

    public function enable(): void
    {
        $user = Auth::requireUser();
        $secret = (string) ($_SESSION['2fa_setup'] ?? '');
        if ($secret === '') {
            redirect('admin/profile/2fa');
        }
        if (RateLimit::tooMany('2fa-setup:' . $user['id'], 8, 900)) {
            flash('error', 'Zu viele Versuche. Bitte warten Sie einige Minuten.');
            redirect('admin/profile/2fa');
        }
        $step = Totp::verify($secret, (string) ($_POST['code'] ?? ''));
        if ($step === null) {
            RateLimit::hit('2fa-setup:' . $user['id']);
            flash('error', 'Der Code stimmt nicht. Prüfen Sie die Uhrzeit Ihres Geräts und versuchen Sie es erneut.');
            redirect('admin/profile/2fa');
        }
        $codes = Totp::recoveryCodes();
        DB::update('users', [
            'totp_secret' => Crypto::encrypt($secret), 'totp_enabled' => 1, 'totp_last_step' => $step,
            'recovery_codes' => json_encode(array_map([Totp::class, 'hashCode'], $codes)), 'updated_at' => now(),
        ], 'id = :id', [':id' => $user['id']]);
        unset($_SESSION['2fa_setup']);
        Audit::log('2fa_enabled', null, 'user', (int) $user['id']);
        $_SESSION['2fa_new_codes'] = $codes;
        redirect('admin/profile/2fa/codes');
    }

    /** Zeigt frisch erzeugte Wiederherstellungscodes genau einmal. */
    public function codes(): void
    {
        Auth::requireUser();
        $codes = $_SESSION['2fa_new_codes'] ?? null;
        unset($_SESSION['2fa_new_codes']);
        if (!$codes) {
            redirect('admin/profile');
        }
        view('admin/users/two-factor-codes', compact('codes'));
    }

    public static function requireCode(array $user): array
    {
        $u = DB::one('SELECT * FROM users WHERE id = ?', [$user['id']]);
        $bucket = '2fa-manage:' . $user['id'];
        if (RateLimit::tooMany($bucket, 6, 900)) {
            flash('error', 'Zu viele Versuche. Bitte warten Sie einige Minuten.');
            redirect('admin/profile');
        }
        $code = trim((string) ($_POST['code'] ?? ''));
        $ok = preg_match('/^\d{3}\s?\d{3}$/', $code) ? self::consumeTotp($u, $code) : ($code !== '' && self::consumeRecovery($u, $code));
        if (!$ok) {
            RateLimit::hit($bucket);
            flash('error', 'Der Code ist ungültig.');
            redirect('admin/profile');
        }
        return $u;
    }

    public function disable(): void
    {
        $user = Auth::requireUser();
        self::requireCode($user);
        $hasPasskey = (bool) DB::val('SELECT COUNT(*) FROM passkeys WHERE user_id = ?', [$user['id']]);
        $upd = ['totp_secret' => null, 'totp_enabled' => 0, 'totp_last_step' => null, 'updated_at' => now()];
        if (!$hasPasskey) {
            $upd['recovery_codes'] = null;   // Wiederherstellungscodes bleiben, solange ein Passkey aktiv ist
        }
        DB::update('users', $upd, 'id = :id', [':id' => $user['id']]);
        Audit::log('2fa_disabled', $hasPasskey ? 'Authenticator-App' : null, 'user', (int) $user['id']);
        flash('success', $hasPasskey ? 'Authenticator-App entfernt. Ihre Passkeys bleiben aktiv.' : 'Zwei-Faktor-Anmeldung deaktiviert.');
        redirect('admin/profile');
    }

    public function regenerate(): void
    {
        $user = Auth::requireUser();
        self::requireCode($user);
        $codes = Totp::recoveryCodes();
        DB::update('users', ['recovery_codes' => json_encode(array_map([Totp::class, 'hashCode'], $codes))], 'id = :id', [':id' => $user['id']]);
        Audit::log('2fa_codes', null, 'user', (int) $user['id']);
        $_SESSION['2fa_new_codes'] = $codes;
        redirect('admin/profile/2fa/codes');
    }

    /** Administratoren können die 2FA eines Kontos zurücksetzen (z. B. bei verlorenem Gerät). */
    public function adminReset(string $id): void
    {
        $me = Auth::requireAdmin();
        DB::tx(static function () use ($id): void {
            DB::exec('DELETE FROM passkeys WHERE user_id = ?', [(int) $id]);
            DB::update('users', ['totp_secret' => null, 'totp_enabled' => 0, 'totp_last_step' => null, 'recovery_codes' => null, 'updated_at' => now()], 'id = :id', [':id' => (int) $id]);
        });
        Audit::log('2fa_reset', null, 'user', (int) $id);
        flash('success', 'Zwei-Faktor-Anmeldung des Benutzers wurde zurückgesetzt.');
        redirect('admin/users');
    }
}
