<?php
/**
 * Audit-Log für sicherheitsrelevante Aktionen (Anmeldung, Benutzer, Einstellungen, Exporte, Löschungen).
 * Es werden bewusst keine Inhalte (Passwörter, Schlüssel, Geschäftsdaten) protokolliert, nur Art, Objekt und Akteur.
 * Aufbewahrung: Einstellung „audit_retention_days“ (siehe Retention).
 */
declare(strict_types=1);

final class Audit
{
    public const LABELS = [
        'login'               => 'Anmeldung',
        'login_failed'        => 'Fehlgeschlagene Anmeldung',
        '2fa_failed'          => 'Falscher 2FA-Code',
        'license'             => 'Lizenz',
        'update_settings'     => 'Update-Einstellungen',
        'update_installed'    => 'Update installiert',
        'update_failed'       => 'Update fehlgeschlagen',
        'update_rollback'     => 'Update zurückgesetzt',
        'sso_login'           => 'SSO-Anmeldung',
        'sso_failed'          => 'SSO-Anmeldung abgelehnt',
        'sso_settings'        => 'SSO-Einstellungen',
        '2fa_enabled'         => '2FA aktiviert',
        '2fa_disabled'        => '2FA deaktiviert',
        '2fa_reset'           => '2FA zurückgesetzt (Admin)',
        '2fa_codes'           => 'Wiederherstellungscodes erneuert',
        'password_changed'    => 'Passwort geändert',
        'user_created'        => 'Benutzer angelegt',
        'user_updated'        => 'Benutzer geändert',
        'user_deleted'        => 'Benutzer gelöscht',
        'settings_saved'      => 'Einstellungen geändert',
        'test_mail'           => 'Test-E-Mail gesendet',
        'tax_saved'           => 'Steuervorlage gespeichert',
        'tax_deleted'         => 'Steuervorlage gelöscht',
        'export'              => 'Daten exportiert (CSV)',
        'backup'              => 'Backup heruntergeladen',
        'customer_export'     => 'Kundendaten exportiert (Auskunft)',
        'customer_anonymized' => 'Kunde anonymisiert/eingeschränkt',
        'customer_deleted'    => 'Kunde gelöscht',
        'offer_deleted'       => 'Offerte gelöscht',
        'api_token'           => 'API-Token erstellt/widerrufen',
        'api_access'          => 'API-Zugriff',
        'webhook'             => 'Webhook geändert',
        'retention_run'       => 'Aufbewahrungsfristen angewendet',
    ];

    public static function log(string $action, ?string $detail = null, ?string $entity = null, ?int $entityId = null, ?array $user = null): void
    {
        try {
            $user ??= Auth::user();
            DB::insert('audit_log', [
                'user_id'    => $user['id'] ?? null,
                'user_label' => $user ? mb_substr(($user['name'] ?? '') . ' <' . ($user['email'] ?? '') . '>', 0, 190) : null,
                'action'     => mb_substr($action, 0, 40),
                'entity'     => $entity ? mb_substr($entity, 0, 40) : null,
                'entity_id'  => $entityId,
                'detail'     => $detail !== null && $detail !== '' ? mb_substr($detail, 0, 500) : null,
                'ip'         => client_ip(),
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Das Audit-Log darf den eigentlichen Vorgang nie verhindern.
        }
    }
}
