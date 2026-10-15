<?php
declare(strict_types=1);

/** Einmal-Tokens für Magic-Links. In der DB wird nur der SHA-256-Hash gespeichert. */
final class Tokens
{
    public static function create(string $type, int $subjectId, int $ttlMinutes = 30): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        DB::insert('login_tokens', [
            'subject_type' => $type,
            'subject_id'   => $subjectId,
            'token_hash'   => hash('sha256', $token),
            'expires_at'   => date('Y-m-d H:i:s', time() + $ttlMinutes * 60),
            'created_ip'   => client_ip(),
            'created_at'   => now(),
        ]);
        return $token;
    }

    public static function find(string $type, string $token): ?array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{20,100}$/', $token)) {
            return null;
        }
        return DB::one(
            'SELECT * FROM login_tokens WHERE token_hash = ? AND subject_type = ? AND used_at IS NULL AND expires_at > ?',
            [hash('sha256', $token), $type, now()]
        );
    }

    /** Markiert das Token atomar als verwendet. Gibt false zurück, wenn es bereits verwendet wurde. */
    public static function consume(array $row): bool
    {
        $done = DB::exec('UPDATE login_tokens SET used_at = ? WHERE id = ? AND used_at IS NULL', [now(), $row['id']]) === 1;
        DB::exec('DELETE FROM login_tokens WHERE expires_at < ?', [date('Y-m-d H:i:s', time() - 86400 * 7)]);
        return $done;
    }
}
