<?php
declare(strict_types=1);

/**
 * Zuständigkeiten: Offerten haben einen festen Aussteller (offers.created_by),
 * Aufträge eine änderbare verantwortliche Person (orders.responsible_id).
 */
final class Responsibility
{
    /** Aktive Mitarbeitende für Auswahllisten. */
    public static function staff(): array
    {
        return DB::all('SELECT id, name, email FROM users WHERE active = 1 ORDER BY name');
    }

    /** Name der Person (live, sonst gespeicherter Name – z. B. nach Löschung des Kontos). */
    public static function label(?string $liveName, ?string $snapshot): string
    {
        $n = trim((string) ($liveName ?: $snapshot));
        return $n !== '' ? $n : '';
    }

    /** Verantwortliche Person für einen neuen Auftrag: der Aussteller der Offerte, sofern sein Konto aktiv ist. */
    public static function defaultFor(array $offer): array
    {
        $u = !empty($offer['created_by']) ? DB::one('SELECT id, name FROM users WHERE id = ? AND active = 1', [$offer['created_by']]) : null;
        return $u ? ['responsible_id' => (int) $u['id'], 'responsible_name' => $u['name']] : ['responsible_id' => null, 'responsible_name' => null];
    }

    /** Weist einen Auftrag zu (null = niemand). Gibt true zurück, wenn sich etwas geändert hat. */
    public static function assign(array $order, ?int $userId, array $actor): bool
    {
        $new = null;
        if ($userId) {
            $new = DB::one('SELECT id, name, email FROM users WHERE id = ? AND active = 1', [$userId]);
            if (!$new) {
                throw new ValidationException('Diese Person ist nicht (mehr) aktiv.');
            }
        }
        $changed = DB::tx(static function () use ($order, $new, $actor): bool {
            $cur = DB::one('SELECT responsible_id, responsible_name FROM orders WHERE id = ? FOR UPDATE', [$order['id']]);
            if ((int) ($cur['responsible_id'] ?? 0) === (int) ($new['id'] ?? 0)) {
                return false;
            }
            DB::update('orders', [
                'responsible_id' => $new['id'] ?? null, 'responsible_name' => $new['name'] ?? null, 'updated_at' => now(),
            ], 'id = :id', [':id' => $order['id']]);
            $from = $cur['responsible_name'] ?: 'niemand';
            History::add('order', (int) $order['id'], 'note', null, null, 'Zuständigkeit: ' . $from . ' → ' . ($new['name'] ?? 'niemand'), false, $actor);
            return true;
        });
        if ($changed && $new && (int) $new['id'] !== (int) ($actor['id'] ?? 0)) {
            Notifier::orderAssigned($order, $new, $actor);
        }
        return $changed;
    }

    /** Name der Ansprechperson für Kunden (leer, wenn in den Einstellungen ausgeblendet oder unbekannt). */
    public static function contactName(string $type, array $row): string
    {
        if (Settings::get('show_contact_person') !== '1') {
            return '';
        }
        if ($type === 'offer') {
            $live = !empty($row['created_by']) ? DB::val('SELECT name FROM users WHERE id = ?', [$row['created_by']]) : null;
            return self::label($live, $row['created_by_name'] ?? null);
        }
        $live = !empty($row['responsible_id']) ? DB::val('SELECT name FROM users WHERE id = ?', [$row['responsible_id']]) : null;
        return self::label($live, $row['responsible_name'] ?? null);
    }

    /** E-Mail-Adresse der zuständigen Person (falls aktiv). */
    public static function emailOf(?int $userId): string
    {
        return $userId ? (string) DB::val('SELECT email FROM users WHERE id = ? AND active = 1', [$userId]) : '';
    }

    /** Filter „owner“ für Listen: me | none | {id}. */
    public static function filter(string $owner, string $column, array &$where, array &$params, bool $allowNone): ?string
    {
        if ($owner === 'me') {
            $where[] = "$column = :owner";
            $params[':owner'] = (int) (Auth::user()['id'] ?? 0);
            return 'me';
        }
        if ($owner === 'none' && $allowNone) {
            $where[] = "$column IS NULL";
            return 'none';
        }
        if (ctype_digit($owner) && (int) $owner > 0) {
            $where[] = "$column = :owner";
            $params[':owner'] = (int) $owner;
            return $owner;
        }
        return null;
    }

    /** Aktualisiert die gespeicherten Namen, wenn ein Konto umbenannt wird. */
    public static function renamed(int $userId, string $name): void
    {
        DB::exec('UPDATE offers SET created_by_name = ? WHERE created_by = ?', [$name, $userId]);
        DB::exec('UPDATE orders SET responsible_name = ? WHERE responsible_id = ?', [$name, $userId]);
    }
}
