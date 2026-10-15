<?php
/** TOTP (RFC 6238, SHA-1, 6 Stellen, 30 s) – kompatibel mit Google/Microsoft Authenticator, 1Password, Aegis u. a. */
declare(strict_types=1);

final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    public static function base32Encode(string $bin): string
    {
        $bits = '';
        foreach (str_split($bin) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }
        return $out;
    }

    public static function base32Decode(string $s): string
    {
        $s = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $s) ?? '');
        $bits = '';
        foreach (str_split($s) as $c) {
            $bits .= str_pad(decbin((int) strpos(self::ALPHABET, $c)), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }
        return $out;
    }

    public static function code(string $secret, int $step): string
    {
        $hash = hash_hmac('sha1', pack('J', $step), self::base32Decode($secret), true);
        $o = ord($hash[19]) & 0x0F;
        $n = ((ord($hash[$o]) & 0x7F) << 24) | (ord($hash[$o + 1]) << 16) | (ord($hash[$o + 2]) << 8) | ord($hash[$o + 3]);
        return str_pad((string) ($n % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /** Gibt den passenden Zeitschritt zurück (±1 Fenster) oder null. $minStep verhindert Wiederverwendung. */
    public static function verify(string $secret, string $input, ?int $minStepExclusive = null, ?int $now = null): ?int
    {
        $input = preg_replace('/\s+/', '', $input) ?? '';
        if (!preg_match('/^\d{6}$/', $input)) {
            return null;
        }
        $current = intdiv($now ?? time(), 30);
        for ($d = -1; $d <= 1; $d++) {
            $step = $current + $d;
            if ($minStepExclusive !== null && $step <= $minStepExclusive) {
                continue;
            }
            if (hash_equals(self::code($secret, $step), $input)) {
                return $step;
            }
        }
        return null;
    }

    public static function uri(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account) . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
    }

    /** @return string[] 8 Einmal-Codes im Format xxxxx-xxxxx */
    public static function recoveryCodes(int $n = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $n; $i++) {
            $raw = strtolower(bin2hex(random_bytes(5)));
            $codes[] = substr($raw, 0, 5) . '-' . substr($raw, 5, 5);
        }
        return $codes;
    }

    public static function hashCode(string $code): string
    {
        return hash('sha256', strtolower(preg_replace('/[^a-z0-9]/i', '', $code) ?? ''));
    }
}
