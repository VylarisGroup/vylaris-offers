<?php
/**
 * Nachrichten zwischen Kunden (Kundenportal) und Team. Richtung 'in' = vom Kunden, 'out' = vom Team.
 * Eine Nachricht kann sich auf eine Offerte, einen Auftrag oder eine Rechnung des Kunden beziehen.
 */
declare(strict_types=1);

final class PortalMessage
{
    public const MAX_LEN = 4000;
    public const TYPES = ['general' => 'Allgemein', 'offer' => 'Offerte', 'order' => 'Auftrag', 'invoice' => 'Rechnung'];

    public static function add(int $customerId, string $direction, string $body, string $author, ?int $userId = null, string $type = 'general', ?int $entityId = null): int
    {
        $body = trim(str_replace("\r\n", "\n", $body));
        if ($body === '') {
            throw new ValidationException('Bitte schreiben Sie eine Nachricht.');
        }
        if (mb_strlen($body) > self::MAX_LEN) {
            throw new ValidationException('Die Nachricht ist zu lang (max. ' . self::MAX_LEN . ' Zeichen).');
        }
        if (!isset(self::TYPES[$type]) || $entityId === null || $entityId <= 0) {
            $type = 'general';
            $entityId = null;
        }
        return DB::insert('portal_messages', [
            'customer_id' => $customerId, 'entity_type' => $type, 'entity_id' => $entityId,
            'direction' => $direction === 'out' ? 'out' : 'in', 'user_id' => $userId,
            'author' => mb_substr($author, 0, 190), 'body' => $body, 'created_at' => now(),
        ]);
    }

    /** Gültiger Bezug «typ:id» des Kunden aus dem Formular → [typ, id]; sonst ['general', null]. */
    public static function parseRef(int $customerId, string $ref): array
    {
        if (!preg_match('/^(offer|order|invoice):(\d+)$/', $ref, $m)) {
            return ['general', null];
        }
        $table = ['offer' => 'offers', 'order' => 'orders', 'invoice' => 'invoices'][$m[1]];
        $extra = $m[1] === 'invoice' ? " AND status <> 'draft'" : '';
        $ok = DB::val("SELECT 1 FROM `$table` WHERE id = ? AND customer_id = ?$extra", [(int) $m[2], $customerId]);
        return $ok ? [$m[1], (int) $m[2]] : ['general', null];
    }

    /** Auswahl der Bezüge eines Kunden: [ref => Label]. */
    public static function refsFor(int $customerId): array
    {
        $out = [];
        foreach (DB::all('SELECT id, number, title FROM offers WHERE customer_id = ? ORDER BY id DESC LIMIT 30', [$customerId]) as $r) {
            $out['offer:' . $r['id']] = 'Offerte ' . $r['number'] . ' – ' . mb_strimwidth((string) $r['title'], 0, 40, '…');
        }
        foreach (DB::all('SELECT id, number, title FROM orders WHERE customer_id = ? ORDER BY id DESC LIMIT 30', [$customerId]) as $r) {
            $out['order:' . $r['id']] = 'Auftrag ' . $r['number'] . ' – ' . mb_strimwidth((string) $r['title'], 0, 40, '…');
        }
        foreach (DB::all("SELECT id, number, title FROM invoices WHERE customer_id = ? AND status <> 'draft' ORDER BY id DESC LIMIT 30", [$customerId]) as $r) {
            $out['invoice:' . $r['id']] = 'Rechnung ' . $r['number'] . ' – ' . mb_strimwidth((string) $r['title'], 0, 40, '…');
        }
        return $out;
    }

    /** Anzeige des Bezugs: [Label, Team-URL, Portal-URL] oder null. */
    public static function refLabel(array $m): ?array
    {
        if ($m['entity_type'] === 'general' || !$m['entity_id']) {
            return null;
        }
        $table = ['offer' => 'offers', 'order' => 'orders', 'invoice' => 'invoices'][$m['entity_type']] ?? null;
        $row = $table ? DB::one("SELECT * FROM `$table` WHERE id = ?", [(int) $m['entity_id']]) : null;
        if (!$row) {
            return null;
        }
        $label = self::TYPES[$m['entity_type']] . ' ' . $row['number'];
        $team = url('admin/' . ['offer' => 'offers', 'order' => 'orders', 'invoice' => 'invoices'][$m['entity_type']] . '/' . $row['id']);
        $portal = match ($m['entity_type']) {
            'offer' => url('o/' . $row['public_id']),
            'order' => url('portal/orders/' . $row['id']),
            default => url('r/' . $row['public_id']),
        };
        return [$label, $team, $portal];
    }

    public static function thread(int $customerId): array
    {
        return DB::all('SELECT * FROM portal_messages WHERE customer_id = ? ORDER BY created_at, id', [$customerId]);
    }

    /** Markiert die Nachrichten einer Richtung als gelesen (Kunde liest 'out', Team liest 'in'). */
    public static function markRead(int $customerId, string $direction): void
    {
        DB::exec('UPDATE portal_messages SET read_at = ? WHERE customer_id = ? AND direction = ? AND read_at IS NULL', [now(), $customerId, $direction]);
    }

    public static function unreadForCustomer(int $customerId): int
    {
        return (int) DB::val("SELECT COUNT(*) FROM portal_messages WHERE customer_id = ? AND direction = 'out' AND read_at IS NULL", [$customerId]);
    }

    public static function unreadForTeam(): int
    {
        try {
            return (int) DB::val("SELECT COUNT(*) FROM portal_messages WHERE direction = 'in' AND read_at IS NULL");
        } catch (Throwable) {
            return 0;
        }
    }

    /** Posteingang: pro Kunde letzte Nachricht und Anzahl ungelesener. */
    public static function inbox(): array
    {
        return DB::all(
            "SELECT c.*, m.body AS last_body, m.direction AS last_direction, m.created_at AS last_at,
                    (SELECT COUNT(*) FROM portal_messages u WHERE u.customer_id = c.id AND u.direction = 'in' AND u.read_at IS NULL) AS unread
             FROM customers c
             JOIN portal_messages m ON m.id = (SELECT MAX(id) FROM portal_messages WHERE customer_id = c.id)
             ORDER BY unread DESC, m.created_at DESC"
        );
    }
}
