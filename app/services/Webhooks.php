<?php
/**
 * Webhooks: signierte HTTPS-POSTs bei Ereignissen (Offerte angenommen, Rechnung versendet/bezahlt …).
 * Datensparsam: Nutzdaten enthalten nur Kennungen, Nummern, Beträge und Kundenname/-nummer; Details holt der
 * Empfänger über die API (mit Scope). Schutz vor SSRF: nur https, nur öffentliche IP-Adressen, DNS-Pinning, keine Weiterleitungen.
 * Signatur: Header X-Offerten-Signature: t=<unix>,v1=<HMAC-SHA256(secret, "<t>.<body>")>
 */
declare(strict_types=1);

final class Webhooks
{
    public const EVENTS = [
        'offer.accepted'    => 'Offerte angenommen',
        'offer.declined'    => 'Offerte abgelehnt',
        'order.created'     => 'Auftrag erstellt',
        'invoice.sent'      => 'Rechnung versendet',
        'invoice.partial_payment' => 'Teilzahlung eingegangen',
        'invoice.paid'      => 'Rechnung bezahlt',
        'invoice.cancelled' => 'Rechnung storniert',
    ];
    private const BACKOFF = [60, 300, 1800, 7200, 43200];   // Sekunden bis zum nächsten Versuch
    private static bool $shutdownRegistered = false;

    /* ---------------------------------------------------------------- Nutzdaten */

    private static function customer(int $id): array
    {
        $c = DB::one('SELECT id, customer_no, first_name, last_name, company, anonymized_at FROM customers WHERE id = ?', [$id]);
        return $c ? ['id' => (int) $c['id'], 'customer_no' => $c['customer_no'], 'name' => customer_name($c)] : ['id' => $id];
    }

    public static function offerData(array $o): array
    {
        return ['id' => (int) $o['id'], 'number' => $o['number'], 'status' => $o['status'], 'currency' => $o['currency'], 'total' => (float) $o['total'], 'customer' => self::customer((int) $o['customer_id']), 'api' => 'api/v1/offers/' . $o['id']];
    }

    public static function orderData(array $o): array
    {
        return ['id' => (int) $o['id'], 'number' => $o['number'], 'offer_id' => (int) $o['offer_id'], 'status' => $o['status'], 'currency' => $o['currency'], 'total' => (float) $o['total'], 'customer' => self::customer((int) $o['customer_id']), 'api' => 'api/v1/orders/' . $o['id']];
    }

    public static function invoiceData(array $i): array
    {
        return ['id' => (int) $i['id'], 'number' => $i['number'], 'kind' => $i['kind'], 'status' => $i['status'], 'issue_date' => $i['issue_date'], 'due_date' => $i['due_date'], 'paid_at' => $i['paid_at'],
            'currency' => $i['currency'], 'total' => (float) $i['total'], 'amount_due' => (float) $i['amount_due'], 'paid_total' => (float) DB::val('SELECT COALESCE(SUM(amount),0) FROM invoice_payments WHERE invoice_id = ?', [$i['id']]), 'customer' => self::customer((int) $i['customer_id']), 'api' => 'api/v1/invoices/' . $i['id']];
    }

    /* ---------------------------------------------------------------- Versand */

    public static function dispatch(string $event, array $data): void
    {
        try {
            if (!isset(self::EVENTS[$event]) || !License::has('webhooks')) {
                return;
            }
            $hooks = DB::all('SELECT id, events FROM webhooks WHERE active = 1');
            $queued = false;
            foreach ($hooks as $h) {
                if (!in_array($event, array_filter(explode(',', (string) $h['events'])), true)) {
                    continue;
                }
                DB::insert('webhook_deliveries', [
                    'webhook_id'   => $h['id'],
                    'event'        => $event,
                    'payload'      => json_encode(['id' => bin2hex(random_bytes(8)), 'event' => $event, 'created_at' => date('c'), 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION),
                    'status'       => 'pending',
                    'next_attempt' => now(),
                    'created_at'   => now(),
                ]);
                $queued = true;
            }
            if ($queued && !self::$shutdownRegistered && PHP_SAPI !== 'cli') {
                self::$shutdownRegistered = true;
                register_shutdown_function(static function (): void {
                    if (function_exists('fastcgi_finish_request')) {
                        @fastcgi_finish_request();
                    }
                    self::processDue(5);
                });
            }
        } catch (Throwable $e) {
            ErrorHandler::log($e);   // Webhooks dürfen den eigentlichen Vorgang nie stören.
        }
    }

    public static function runIfDue(): void
    {
        if ((int) DB::val("SELECT COUNT(*) FROM webhook_deliveries WHERE status = 'pending' AND next_attempt <= ?", [now()]) > 0) {
            self::processDue(10);
        }
    }

    public static function processDue(int $limit = 10): int
    {
        $n = 0;
        $rows = DB::all("SELECT d.*, w.url, w.secret, w.active FROM webhook_deliveries d JOIN webhooks w ON w.id = d.webhook_id WHERE d.status = 'pending' AND d.next_attempt <= ? ORDER BY d.id LIMIT " . (int) $limit, [now()]);
        foreach ($rows as $d) {
            if (DB::exec("UPDATE webhook_deliveries SET attempts = attempts + 1, next_attempt = ? WHERE id = ? AND status = 'pending' AND next_attempt <= ?", [date('Y-m-d H:i:s', time() + 120), $d['id'], now()]) !== 1) {
                continue;   // von einem parallelen Prozess übernommen
            }
            $n++;
            if (!$d['active']) {
                DB::exec("UPDATE webhook_deliveries SET status = 'failed', last_error = 'Webhook deaktiviert' WHERE id = ?", [$d['id']]);
                continue;
            }
            [$http, $err] = self::post((string) $d['url'], (string) Crypto::decrypt((string) $d['secret']), (string) $d['payload'], (string) $d['event'], (int) $d['id']);
            $attempts = (int) $d['attempts'] + 1;
            if ($http >= 200 && $http < 300) {
                DB::exec("UPDATE webhook_deliveries SET status = 'sent', sent_at = ?, last_http = ?, last_error = NULL, next_attempt = NULL WHERE id = ?", [now(), $http, $d['id']]);
            } elseif ($attempts > count(self::BACKOFF)) {
                DB::exec("UPDATE webhook_deliveries SET status = 'failed', last_http = ?, last_error = ?, next_attempt = NULL WHERE id = ?", [$http ?: null, mb_substr((string) $err, 0, 255), $d['id']]);
            } else {
                DB::exec('UPDATE webhook_deliveries SET last_http = ?, last_error = ?, next_attempt = ? WHERE id = ?', [$http ?: null, mb_substr((string) $err, 0, 255), date('Y-m-d H:i:s', time() + self::BACKOFF[$attempts - 1]), $d['id']]);
            }
        }
        return $n;
    }

    /** Synchroner Test mit Ereignis „ping“. */
    public static function ping(array $hook): array
    {
        $payload = json_encode(['id' => bin2hex(random_bytes(8)), 'event' => 'ping', 'created_at' => date('c'), 'data' => ['message' => 'Testaufruf']]);
        return self::post((string) $hook['url'], (string) Crypto::decrypt((string) $hook['secret']), (string) $payload, 'ping', 0);
    }

    /** @return array{0:int,1:?string} [HTTP-Status, Fehler] */
    private static function post(string $url, string $secret, string $payload, string $event, int $deliveryId): array
    {
        if (!function_exists('curl_init')) {
            return [0, 'PHP-Erweiterung curl fehlt'];
        }
        $p = parse_url($url);
        $host = (string) ($p['host'] ?? '');
        $port = (int) ($p['port'] ?? (($p['scheme'] ?? '') === 'http' ? 80 : 443));
        $ips = self::publicIps($host);
        if (!$ips) {
            return [0, 'Ziel nicht erreichbar oder keine öffentliche Adresse'];
        }
        $t = time();
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_RESOLVE        => ["$host:$port:" . (str_contains($ips[0], ':') ? '[' . $ips[0] . ']' : $ips[0])],
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS | (self::allowPrivate() ? CURLPROTO_HTTP : 0),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'User-Agent: ' . Brand::userAgent(),
                'X-Offerten-Event: ' . $event,
                'X-Offerten-Delivery: ' . $deliveryId,
                'X-Offerten-Signature: t=' . $t . ',v1=' . hash_hmac('sha256', $t . '.' . $payload, $secret),
            ],
        ]);
        curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_errno($ch) ? curl_error($ch) : ($http >= 300 ? "HTTP $http" : null);
        curl_close($ch);
        return [$http, $err];
    }

    /* ---------------------------------------------------------------- SSRF-Schutz */

    private static function allowPrivate(): bool
    {
        return (bool) app_config('webhooks.allow_private', false);
    }

    /** Öffentliche IPs des Hosts (leer, wenn der Host auflösbar ist, aber auf interne Adressen zeigt). */
    private static function publicIps(string $host): array
    {
        $host = trim($host, '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : array_merge(
            gethostbynamel($host) ?: [],
            array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6')
        );
        if (self::allowPrivate()) {
            return $ips;
        }
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return [];   // schon eine interne Adresse genügt zur Ablehnung
            }
        }
        return array_values($ips);
    }

    /** @return string|null Fehlermeldung oder null, wenn die URL zulässig ist */
    public static function validateUrl(string $url): ?string
    {
        $p = parse_url($url);
        if (!$p || empty($p['host']) || isset($p['user']) || isset($p['pass'])) {
            return 'Ungültige URL.';
        }
        if (($p['scheme'] ?? '') !== 'https' && !(self::allowPrivate() && ($p['scheme'] ?? '') === 'http')) {
            return 'Es sind nur https-Adressen erlaubt.';
        }
        if (!self::publicIps($p['host'])) {
            return 'Die Adresse ist nicht auflösbar oder zeigt auf eine interne/private IP.';
        }
        return null;
    }
}
