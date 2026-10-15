<?php
declare(strict_types=1);

/** Symmetrische Verschlüsselung (AES-256-GCM) mit dem App-Schlüssel aus config.php. */
final class Crypto
{
    private static function key(): string
    {
        $key = (string) app_config('app.key', '');
        if (strlen($key) < 32) {
            throw new RuntimeException('Kein gültiger App-Schlüssel in config.php.');
        }
        return hash('sha256', $key, true);
    }

    /** Schlüsselabhängiger Hash (HMAC-SHA256), z. B. für Einmalcodes. */
    public static function hmac(string $data): string
    {
        return hash_hmac('sha256', $data, self::key());
    }

    public static function encrypt(string $plain): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        return 'v1:' . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $payload): ?string
    {
        if (!str_starts_with($payload, 'v1:')) {
            return null;
        }
        $raw = base64_decode(substr($payload, 3), true);
        if ($raw === false || strlen($raw) < 29) {
            return null;
        }
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $plain === false ? null : $plain;
    }
}
