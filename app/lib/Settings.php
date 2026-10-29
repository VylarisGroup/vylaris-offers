<?php
declare(strict_types=1);

/** Einstellungen (Tabelle settings). Sensible Werte werden verschlüsselt abgelegt. */
final class Settings
{
    private static ?array $cache = null;
    private const ENCRYPTED = ['sso_client_secret', 'smtp_pass', 'mollie_api_key', 'stripe_secret_key', 'stripe_webhook_secret'];

    public const DEFAULTS = [
        'company_name' => 'Meine Firma',
        'company_street' => '',
        'company_zip' => '',
        'company_city' => '',
        'company_country' => 'CH',
        'company_email' => '',
        'company_phone' => '',
        'company_website' => '',
        'company_vat_id' => '',
        'mwst_liable' => '1',
        'mwst_method' => 'agreed',
        'mwst_saldo_rate' => '',
        'mwst_period' => 'quarter',
        'company_logo_url' => '',
        'brand_color' => '#4f46e5',
        'brand_name' => '',
        'brand_tagline' => '',
        'brand_logo_file' => '',
        'brand_favicon_file' => '',
        'currency' => 'CHF',
        'number_style' => 'ch',
        'round_005' => '1',
        'offer_prefix' => 'OF',
        'order_prefix' => 'AU',
        'offer_validity_days' => '30',
        'offer_intro' => "Vielen Dank für Ihre Anfrage. Gerne unterbreiten wir Ihnen folgendes Angebot:",
        'offer_footer' => "Wir freuen uns auf Ihren Auftrag. Bei Fragen stehen wir Ihnen jederzeit gerne zur Verfügung.",
        'invoice_prefix' => 'RE',
        'invoice_payment_days' => '30',
        'invoice_intro' => "Vielen Dank für Ihren Auftrag. Wir erlauben uns, Ihnen folgende Leistungen in Rechnung zu stellen:",
        'invoice_footer' => "Vielen Dank für Ihr Vertrauen.",
        'invoice_payment_info' => '',
        'invoice_iban' => '',
        'notify_email' => '',
        'mail_transport' => 'smtp',
        'smtp_host' => '',
        'smtp_port' => '587',
        'smtp_encryption' => 'tls',
        'smtp_user' => '',
        'smtp_pass' => '',
        'mail_from_email' => '',
        'mail_from_name' => '',
        'mail_attach_pdf' => '1',
        'audit_retention_days' => '365',
        'ip_retention_days' => '90',
        'mail_log_retention_days' => '365',
        'retention_years' => '10',
        'sso_enabled' => '0',
        'sso_issuer' => '',
        'sso_client_id' => '',
        'sso_client_secret' => '',
        'sso_scopes' => 'openid email profile',
        'sso_label' => 'Vylaris-Login',
        'sso_auto_create' => '0',
        'sso_default_role' => 'team',
        'sso_admin_group' => '',
        'sso_allowed_domains' => '',
        'sso_required' => '0',
        'update_auto' => '0',
        'update_latest' => '',
        'update_checked_at' => '0',
        'update_last' => '',
        'update_error' => '',
        'privacy_contact' => '',
        'privacy_text' => '',
        'acc_debtors' => '1100',
        'acc_revenue' => '3200',
        'acc_discount' => '3800',
        'acc_cash' => '1000',
        'acc_bank' => '1020',
        'acc_online' => '1021',
        'show_contact_person' => '1',
        'pay_mollie_enabled' => '0',
        'pay_stripe_enabled' => '0',
        'mollie_api_key' => '',
        'stripe_secret_key' => '',
        'stripe_webhook_secret' => '',
        'health_token' => '',
        'cron_last_run' => '',
        'last_backup_at' => '',
        'inquiry_enabled' => '1',
        'inquiry_confirm_mail' => '1',
        'inquiry_retention_days' => '180',
    ];

    public static function load(): array
    {
        if (self::$cache === null) {
            self::$cache = self::DEFAULTS;
            foreach (DB::all('SELECT `key`, `value` FROM settings') as $row) {
                $val = (string) $row['value'];
                if (in_array($row['key'], self::ENCRYPTED, true) && $val !== '') {
                    $val = Crypto::decrypt($val) ?? '';
                }
                self::$cache[$row['key']] = $val;
            }
        }
        return self::$cache;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            $all = self::load();
        } catch (Throwable) {
            return $default;
        }
        $v = $all[$key] ?? null;
        return ($v === null || $v === '') ? ($default ?? $v) : $v;
    }

    public static function set(array $values): void
    {
        foreach ($values as $key => $val) {
            $val = (string) $val;
            if (in_array($key, self::ENCRYPTED, true) && $val !== '') {
                $val = Crypto::encrypt($val);
            }
            DB::exec('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)', [$key, $val]);
        }
        self::$cache = null;
    }

    public static function companyEmail(): string
    {
        return (string) (self::get('company_email') ?: self::get('mail_from_email') ?: '');
    }
}
