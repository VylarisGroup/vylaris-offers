<?php
/** API-Tokens (nur lesend). Das Token wird nur einmal angezeigt; gespeichert wird ausschliesslich der SHA-256-Hash. */
declare(strict_types=1);

final class ApiTokens
{
    public const SCOPES = [
        'invoices'  => 'Rechnungen',
        'offers'    => 'Offerten',
        'orders'    => 'Aufträge',
        'customers' => 'Kunden (personenbezogene Daten)',
    ];

    /** @return array{0:int,1:string} [id, Klartext-Token] */
    public static function create(string $name, array $scopes, ?int $days, array $user): array
    {
        $scopes = array_values(array_intersect(array_keys(self::SCOPES), $scopes));
        $token = 'omk_' . bin2hex(random_bytes(24));
        $id = DB::insert('api_tokens', [
            'name'         => mb_substr($name, 0, 120),
            'token_prefix' => substr($token, 0, 8),
            'token_hash'   => hash('sha256', $token),
            'scopes'       => implode(',', $scopes),
            'created_by'   => $user['id'] ?? null,
            'created_at'   => now(),
            'expires_at'   => $days ? date('Y-m-d H:i:s', time() + $days * 86400) : null,
        ]);
        Audit::log('api_token', 'Erstellt: ' . $name . ' (' . implode(', ', $scopes) . ')', 'api_token', $id, $user);
        return [$id, $token];
    }

    public static function revoke(int $id, array $user): void
    {
        DB::exec('UPDATE api_tokens SET revoked_at = ? WHERE id = ? AND revoked_at IS NULL', [now(), $id]);
        Audit::log('api_token', 'Widerrufen', 'api_token', $id, $user);
    }

    /** Prüft einen Bearer-Token; liefert die Token-Zeile oder null. */
    public static function verify(string $token): ?array
    {
        if (!preg_match('/^omk_[a-f0-9]{48}$/', $token)) {
            return null;
        }
        $row = DB::one('SELECT * FROM api_tokens WHERE token_hash = ? AND revoked_at IS NULL', [hash('sha256', $token)]);
        if (!$row || ($row['expires_at'] && strtotime((string) $row['expires_at']) < time())) {
            return null;
        }
        $row['scope_list'] = array_filter(explode(',', (string) $row['scopes']));
        return $row;
    }
}
