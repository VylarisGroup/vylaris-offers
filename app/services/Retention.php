<?php
/**
 * Aufbewahrungsfristen / Datensparsamkeit (DSGVO Art. 5 Abs. 1 lit. c und e):
 * Technische Protokolldaten werden nach einer einstellbaren Frist automatisch gelöscht bzw. gekürzt.
 * Buchhaltungsrelevante Dokumente (Offerten, Rechnungen) unterliegen der gesetzlichen Aufbewahrungspflicht
 * und werden hier NICHT angetastet (siehe CustomerPrivacy).
 */
declare(strict_types=1);

final class Retention
{
    public static function runIfDue(): void
    {
        $last = (int) Settings::get('retention_last_run', '0');
        if (time() - $last < 86400) {
            return;
        }
        Settings::set(['retention_last_run' => (string) time()]);
        self::run();
    }

    /** @return array<string,int> gelöschte/gekürzte Datensätze je Bereich */
    public static function run(): array
    {
        $days = static fn (string $k, int $min, int $max, int $def): int => max($min, min($max, (int) (Settings::get($k) ?: $def)));
        $cut = static fn (int $d): string => date('Y-m-d H:i:s', time() - $d * 86400);
        $res = [];

        $res['audit_log'] = DB::exec('DELETE FROM audit_log WHERE created_at < ?', [$cut($days('audit_retention_days', 30, 3650, 365))]);
        $res['mail_log'] = DB::exec('DELETE FROM mail_log WHERE created_at < ?', [$cut($days('mail_log_retention_days', 30, 3650, 365))]);

        // IP-Adressen kürzen (Nachweiszweck ist nach kurzer Zeit erfüllt).
        $ipCut = $cut($days('ip_retention_days', 1, 365, 90));
        $res['ip_offers'] = DB::exec('UPDATE offers SET response_ip = NULL WHERE response_ip IS NOT NULL AND responded_at < ?', [$ipCut]);
        $res['ip_tokens'] = DB::exec('UPDATE login_tokens SET created_ip = NULL WHERE created_ip IS NOT NULL AND created_at < ?', [$ipCut]);
        $res['ip_audit'] = 0; // Audit-IPs bleiben bis zur Audit-Frist (Sicherheitszweck)

        // Anfragen aus dem Kontaktformular: Löschung nach Ablauf der Frist (Standard 180 Tage); IP früher.
        $res['inquiries'] = DB::exec('DELETE FROM inquiries WHERE created_at < ?', [$cut($days('inquiry_retention_days', 30, 3650, 180))]);
        $res['ip_inquiries'] = DB::exec('UPDATE inquiries SET ip = NULL WHERE ip IS NOT NULL AND created_at < ?', [$ipCut]);

        // Einmalcodes, abgelaufene Anmeldelinks und Webhook-Zustellungen.
        $res['signature_codes'] = DB::exec('DELETE FROM signature_codes WHERE created_at < ?', [$cut(7)]);
        $res['login_tokens'] = DB::exec('DELETE FROM login_tokens WHERE expires_at < ?', [$cut(7)]);
        $res['webhook_deliveries'] = DB::exec('DELETE FROM webhook_deliveries WHERE created_at < ?', [$cut(30)]);
        $res['rate_limits'] = DB::exec('DELETE FROM rate_limits WHERE created_at < ?', [$cut(2)]);

        Audit::log('retention_run', json_encode(array_filter($res), JSON_UNESCAPED_UNICODE) ?: null, null, null, []);
        return $res;
    }
}
