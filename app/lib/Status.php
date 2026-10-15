<?php
declare(strict_types=1);

/** Statusbezeichnungen, Farben und Icons an einem Ort. */
final class Status
{
    public const OFFER = [
        'open'       => ['Offen', 'sky', 'bx-time-five'],
        'accepted'   => ['Angenommen', 'emerald', 'bx-check-circle'],
        'declined'   => ['Abgelehnt', 'rose', 'bx-x-circle'],
        'expired'    => ['Abgelaufen', 'slate', 'bx-calendar-x'],
        'superseded' => ['Ersetzt', 'violet', 'bx-git-branch'],
    ];

    public const ORDER = [
        'new'         => ['Eingegangen', 'sky', 'bx-inbox'],
        'in_progress' => ['In Bearbeitung', 'amber', 'bx-loader-circle'],
        'fulfilled'   => ['Geliefert', 'indigo', 'bx-package'],
        'completed'   => ['Abgeschlossen', 'emerald', 'bx-check-double'],
        'cancelled'   => ['Storniert', 'rose', 'bx-block'],
    ];

    public const INVOICE = [
        'draft'     => ['Entwurf', 'slate', 'bx-edit-alt'],
        'sent'      => ['Offen', 'sky', 'bx-send'],
        'partial'   => ['Teilbezahlt', 'amber', 'bx-pie-chart-alt'],
        'credit'    => ['Gutschrift', 'slate', 'bx-undo'],
        'overdue'   => ['Überfällig', 'rose', 'bx-error-circle'],
        'paid'      => ['Bezahlt', 'emerald', 'bx-check-circle'],
        'cancelled' => ['Storniert', 'slate', 'bx-block'],
    ];

    /** Anzeigestatus: „offen“ und fällig vor heute wird zu „überfällig“. */
    public static function invoiceDisplay(array $inv): string
    {
        if (($inv['kind'] ?? '') === 'credit' && $inv['status'] === 'paid') {
            return 'credit';
        }
        if ($inv['status'] !== 'sent') {
            return $inv['status'];
        }
        if ($inv['due_date'] < date('Y-m-d')) {
            return 'overdue';
        }
        return (float) ($inv['paid_total'] ?? 0) + (float) ($inv['adjusted_total'] ?? 0) > 0 ? 'partial' : 'sent';
    }

    public const SHIPPING = [
        'pending'   => ['Ausstehend', 'slate', 'bx-time'],
        'packed'    => ['Verpackt', 'amber', 'bx-box'],
        'shipped'   => ['Versendet', 'indigo', 'bx-send'],
        'delivered' => ['Zugestellt', 'emerald', 'bx-home-heart'],
        'returned'  => ['Retour', 'rose', 'bx-undo'],
    ];

    public static function label(string $group, ?string $status, ?string $areaType = null): string
    {
        if ($group === 'order' && $status === 'fulfilled') {
            return $areaType === 'digital' ? 'Bereitgestellt' : 'Versendet';
        }
        $map = constant(self::class . '::' . strtoupper($group));
        return $map[$status][0] ?? (string) $status;
    }

    public static function badge(string $group, ?string $status, ?string $areaType = null): string
    {
        $map = constant(self::class . '::' . strtoupper($group));
        [, $color, $icon] = $map[$status] ?? ['', 'slate', 'bx-circle'];
        if ($group === 'order' && $status === 'fulfilled' && $areaType === 'digital') {
            $icon = 'bx-cloud-download';
        }
        return sprintf(
            '<span class="badge badge-%s"><i class="bx %s"></i>%s</span>',
            $color, $icon, e(self::label($group, $status, $areaType))
        );
    }
}
