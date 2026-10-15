<?php
declare(strict_types=1);

/** Statushistorie für Offerten und Aufträge. */
final class History
{
    public static function add(
        string $entityType,
        int $entityId,
        string $event,
        ?string $from = null,
        ?string $to = null,
        ?string $note = null,
        bool $customerVisible = false,
        ?array $actor = null,
        string $actorType = 'user'
    ): void {
        if ($actor === null) {
            $actorType = 'system';
        }
        DB::insert('status_history', [
            'entity_type'      => $entityType,
            'entity_id'        => $entityId,
            'event'            => $event,
            'status_from'      => $from,
            'status_to'        => $to,
            'note'             => $note !== null && $note !== '' ? mb_substr($note, 0, 5000) : null,
            'customer_visible' => $customerVisible ? 1 : 0,
            'actor_type'       => $actorType,
            'actor_id'         => $actor['id'] ?? null,
            'actor_name'       => $actor !== null ? mb_substr((string) ($actor['name'] ?? customer_name($actor)), 0, 190) : 'System',
            'created_at'       => now(),
        ]);
    }

    public static function for(string $entityType, int $entityId, bool $customerOnly = false): array
    {
        $sql = 'SELECT * FROM status_history WHERE entity_type = ? AND entity_id = ?'
            . ($customerOnly ? ' AND customer_visible = 1' : '')
            . ' ORDER BY created_at DESC, id DESC';
        return DB::all($sql, [$entityType, $entityId]);
    }

    /** Beschreibung eines Eintrags für die Timeline. */
    public static function describe(array $h, ?string $areaType = null): array
    {
        $group = $h['entity_type'];
        return match ($h['event']) {
            'created'  => [['offer' => 'Offerte erstellt', 'order' => 'Auftrag erstellt', 'invoice' => 'Rechnung erstellt'][$group] ?? 'Erstellt', 'bx-plus-circle', 'brand'],
            'reminder' => ['Zahlungserinnerung versendet', 'bx-bell', 'amber'],
            'payment'  => [$h['status_to'] === 'paid' ? 'Als bezahlt markiert' : 'Zahlung zurückgenommen', 'bx-wallet', 'emerald'],
            'sent'     => ['Per E-Mail versendet', 'bx-envelope', 'sky'],
            'viewed'   => ['Vom Kunden geöffnet', 'bx-show', 'slate'],
            'shipping' => ['Versand: ' . Status::label('shipping', $h['status_to']), 'bx-package', 'indigo'],
            'delivery' => ['Digitale Lieferung aktualisiert', 'bx-cloud-upload', 'indigo'],
            'note'     => ['Notiz', 'bx-note', 'amber'],
            default    => [Status::label($group, $h['status_to'], $areaType), constant(Status::class . '::' . strtoupper($group))[$h['status_to']][2] ?? 'bx-transfer', constant(Status::class . '::' . strtoupper($group))[$h['status_to']][1] ?? 'slate'],
        };
    }
}
