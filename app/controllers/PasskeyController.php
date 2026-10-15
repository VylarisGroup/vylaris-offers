<?php
declare(strict_types=1);

/** Passkeys (WebAuthn) als zweiter Faktor: Registrierung, Anmeldung, Verwaltung. */
final class PasskeyController
{
    private const MAX_PER_USER = 10;

    private static function body(): array
    {
        $raw = file_get_contents('php://input', false, null, 0, 65536);
        $d = json_decode((string) $raw, true);
        return is_array($d) ? $d : [];
    }

    private static function ids(int $userId): array
    {
        return array_column(DB::all('SELECT credential_id FROM passkeys WHERE user_id = ?', [$userId]), 'credential_id');
    }

    private static function requireAvailable(): void
    {
        if (!WebAuthn::available()) {
            json_response(['ok' => false, 'error' => 'Passkeys benötigen eine HTTPS-Adresse (Basis-URL in der Konfiguration).'], 400);
        }
    }

    /* ---------------------------------------------------------------- Anmeldung (zweiter Faktor) */

    public function loginOptions(): void
    {
        $u = TwoFactorController::pendingUser(true);
        self::requireAvailable();
        $ids = self::ids((int) $u['id']);
        if (!$ids) {
            json_response(['ok' => false, 'error' => 'Für dieses Konto ist kein Passkey hinterlegt.'], 400);
        }
        json_response(['ok' => true, 'options' => WebAuthn::authOptions($ids)]);
    }

    public function loginVerify(): void
    {
        $u = TwoFactorController::pendingUser(true);
        self::requireAvailable();
        $bucket = '2fa:' . $u['id'];
        $ipBucket = '2fa-ip:' . client_ip();
        if (RateLimit::tooMany($bucket, 6, 900) || RateLimit::tooMany($ipBucket, 20, 900)) {
            json_response(['ok' => false, 'error' => 'Zu viele Versuche. Bitte warten Sie 15 Minuten.'], 429);
        }
        try {
            $res = WebAuthn::verifyAssertion(self::body(), DB::all('SELECT * FROM passkeys WHERE user_id = ?', [$u['id']]));
            $p = $res['passkey'];
            // Signaturzähler atomar fortschreiben (Schutz gegen Wiederverwendung/Klone)
            $n = DB::exec('UPDATE passkeys SET sign_count = ?, last_used_at = ? WHERE id = ? AND (sign_count < ? OR (sign_count = 0 AND ? = 0))', [$res['count'], now(), $p['id'], $res['count'], $res['count']]);
            if ($n !== 1) {
                throw new ValidationException('Die Anmeldung wurde bereits verwendet.');
            }
        } catch (ValidationException $e) {
            Audit::log('2fa_failed', 'Passkey: ' . $e->getMessage(), 'user', (int) $u['id'], $u);
            RateLimit::hit($bucket);
            RateLimit::hit($ipBucket);
            json_response(['ok' => false, 'error' => $e->getMessage()], 422);
        }
        RateLimit::clear($bucket);
        unset($_SESSION['2fa_uid'], $_SESSION['2fa_exp']);
        Auth::loginUser($u);
        Audit::log('passkey_login', $p['name'], 'user', (int) $u['id'], $u);
        flash('success', 'Willkommen, ' . $u['name'] . '!');
        json_response(['ok' => true, 'redirect' => TwoFactorController::intendedUrl()]);
    }

    /* ---------------------------------------------------------------- Verwaltung im Profil */

    public function registerOptions(): void
    {
        $user = Auth::requireUser();
        self::requireAvailable();
        $ids = self::ids((int) $user['id']);
        if (count($ids) >= self::MAX_PER_USER) {
            json_response(['ok' => false, 'error' => 'Es sind höchstens ' . self::MAX_PER_USER . ' Passkeys pro Konto möglich.'], 400);
        }
        $u = DB::one('SELECT id, name, email FROM users WHERE id = ?', [$user['id']]);
        json_response(['ok' => true, 'options' => WebAuthn::registrationOptions($u, $ids)]);
    }

    public function register(): void
    {
        $user = Auth::requireUser();
        self::requireAvailable();
        if (RateLimit::tooMany('passkey-reg:' . $user['id'], 10, 900)) {
            json_response(['ok' => false, 'error' => 'Zu viele Versuche. Bitte warten Sie einige Minuten.'], 429);
        }
        $body = self::body();
        try {
            $k = WebAuthn::verifyRegistration($body);
            if ((int) DB::val('SELECT COUNT(*) FROM passkeys WHERE user_id = ?', [$user['id']]) >= self::MAX_PER_USER) {
                throw new ValidationException('Es sind höchstens ' . self::MAX_PER_USER . ' Passkeys pro Konto möglich.');
            }
            if (DB::val('SELECT id FROM passkeys WHERE credential_id = ?', [$k['id']])) {
                throw new ValidationException('Dieser Passkey ist bereits registriert.');
            }
        } catch (ValidationException $e) {
            RateLimit::hit('passkey-reg:' . $user['id']);
            json_response(['ok' => false, 'error' => $e->getMessage()], 422);
        }
        $name = trim(mb_substr(strip_tags((string) ($body['name'] ?? '')), 0, 80));
        $name = $name !== '' ? $name : 'Passkey vom ' . date('d.m.Y');
        $first = !DB::val('SELECT COUNT(*) FROM passkeys WHERE user_id = ?', [$user['id']]) && !DB::val('SELECT totp_enabled FROM users WHERE id = ?', [$user['id']]);
        DB::insert('passkeys', [
            'user_id' => $user['id'], 'credential_id' => $k['id'], 'public_key' => $k['key'], 'alg' => $k['alg'],
            'sign_count' => $k['count'], 'name' => $name, 'created_at' => now(),
        ]);
        Audit::log('passkey_added', $name, 'user', (int) $user['id']);
        // Wiederherstellungscodes: beim ersten zweiten Faktor erzeugen
        if ($first) {
            $codes = Totp::recoveryCodes();
            DB::update('users', ['recovery_codes' => json_encode(array_map([Totp::class, 'hashCode'], $codes)), 'updated_at' => now()], 'id = :id', [':id' => $user['id']]);
            $_SESSION['2fa_new_codes'] = $codes;
            json_response(['ok' => true, 'redirect' => url('admin/profile/2fa/codes')]);
        }
        flash('success', 'Passkey „' . $name . '“ wurde hinzugefügt.');
        json_response(['ok' => true, 'redirect' => url('admin/profile')]);
    }

    public function delete(string $id): void
    {
        $user = Auth::requireUser();
        $pk = DB::one('SELECT * FROM passkeys WHERE id = ? AND user_id = ?', [(int) $id, $user['id']]);
        if (!$pk) {
            throw new HttpException(404, 'Passkey nicht gefunden.');
        }
        TwoFactorController::requireCode($user);
        DB::exec('DELETE FROM passkeys WHERE id = ?', [$pk['id']]);
        Audit::log('passkey_removed', $pk['name'], 'user', (int) $user['id']);
        $left = (int) DB::val('SELECT COUNT(*) FROM passkeys WHERE user_id = ?', [$user['id']]);
        if ($left === 0 && !DB::val('SELECT totp_enabled FROM users WHERE id = ?', [$user['id']])) {
            DB::update('users', ['recovery_codes' => null, 'updated_at' => now()], 'id = :id', [':id' => $user['id']]);
            Audit::log('2fa_disabled', null, 'user', (int) $user['id']);
            flash('success', 'Passkey entfernt. Die Zwei-Faktor-Anmeldung ist nun ausgeschaltet.');
        } else {
            flash('success', 'Passkey „' . $pk['name'] . '“ wurde entfernt.');
        }
        redirect('admin/profile');
    }
}
