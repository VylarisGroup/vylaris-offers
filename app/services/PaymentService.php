<?php
/**
 * Online-Zahlung von Rechnungen über Mollie und Stripe (Checkout, Weiterleitung auf die Zahlungsseite des Anbieters).
 * Es werden keine Kartendaten verarbeitet oder gespeichert. Der Status wird bei jedem Ereignis (Webhook, Rückkehr,
 * manuelle Abfrage) direkt beim Anbieter abgefragt – ein gefälschter Webhook kann also nichts als bezahlt markieren.
 */
declare(strict_types=1);

final class PaymentService
{
    public const PROVIDERS = ['mollie' => 'Mollie', 'stripe' => 'Stripe'];
    private const ZERO_DECIMAL = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];

    /* ---------------------------------------------------------------- Konfiguration */

    /** @return string[] aktivierte und konfigurierte Anbieter */
    public static function enabled(): array
    {
        $out = [];
        if (Settings::get('pay_mollie_enabled') === '1' && (string) Settings::get('mollie_api_key') !== '') {
            $out[] = 'mollie';
        }
        if (Settings::get('pay_stripe_enabled') === '1' && (string) Settings::get('stripe_secret_key') !== '') {
            $out[] = 'stripe';
        }
        return $out;
    }

    /** Die erfolgreiche Online-Zahlung einer Rechnung (falls vorhanden). */
    public static function paidPayment(int $invoiceId): ?array
    {
        return DB::one("SELECT * FROM payments WHERE invoice_id = ? AND status = 'paid' ORDER BY id LIMIT 1", [$invoiceId]);
    }

    /** Die Online-Zahlung zu einem Eintrag im Zahlungsjournal (falls vorhanden). */
    public static function providerPayment(?int $paymentId): ?array
    {
        return $paymentId ? DB::one('SELECT * FROM payments WHERE id = ?', [$paymentId]) : null;
    }

    public static function isTestKey(string $provider): bool
    {
        $k = (string) Settings::get($provider === 'mollie' ? 'mollie_api_key' : 'stripe_secret_key');
        return str_starts_with($k, 'test_') || str_contains($k, '_test_');
    }

    /** Kann diese Rechnung jetzt online bezahlt werden? */
    public static function payable(array $inv): bool
    {
        return $inv['status'] === 'sent' && (float) ($inv['pay_amount'] ?? 0) > 0
            && !in_array(strtoupper((string) $inv['currency']), self::ZERO_DECIMAL, true)
            && self::enabled() !== [];
    }

    private static function base(string $provider): string
    {
        return rtrim((string) app_config('payments.' . $provider . '_base', $provider === 'mollie' ? 'https://api.mollie.com/v2' : 'https://api.stripe.com/v1'), '/');
    }

    public static function returnUrl(array $inv, int $paymentId): string
    {
        return url('r/' . $inv['public_id'] . '/return/' . $paymentId);
    }

    /* ---------------------------------------------------------------- Zahlung starten */

    /** @return string URL der Zahlungsseite des Anbieters */
    public static function start(array $inv, string $provider, bool $full = false): string
    {
        if ($full) {
            $inv['pay_amount'] = $inv['open_amount'];
        }
        if (!isset(self::PROVIDERS[$provider]) || !in_array($provider, self::enabled(), true) || !self::payable($inv)) {
            throw new ValidationException('Diese Rechnung kann derzeit nicht online bezahlt werden.');
        }
        $ip = 'pay:' . client_ip();
        if (RateLimit::tooMany($ip, 12, 600) || RateLimit::tooMany('pay-inv:' . $inv['id'], 20, 3600)) {
            throw new ValidationException('Zu viele Zahlungsversuche. Bitte warten Sie einige Minuten.');
        }
        RateLimit::hit($ip);
        RateLimit::hit('pay-inv:' . $inv['id']);

        $pid = DB::insert('payments', [
            'invoice_id' => $inv['id'], 'provider' => $provider, 'status' => 'open',
            'amount' => $inv['pay_amount'], 'currency' => strtoupper((string) $inv['currency']), 'created_at' => now(),
        ]);
        try {
            [$ref, $checkout] = $provider === 'mollie' ? self::mollieCreate($inv, $pid) : self::stripeCreate($inv, $pid);
            DB::exec('UPDATE payments SET provider_ref = ? WHERE id = ?', [$ref, $pid]);
            return $checkout;
        } catch (Throwable $e) {
            DB::exec("UPDATE payments SET status = 'failed', note = ?, updated_at = ? WHERE id = ?", [mb_substr($e->getMessage(), 0, 250), now(), $pid]);
            ErrorHandler::log($e);
            throw new ValidationException('Die Zahlung konnte nicht gestartet werden. Bitte versuchen Sie es später erneut oder überweisen Sie den Betrag.');
        }
    }

    private static function mollieCreate(array $inv, int $pid): array
    {
        $body = [
            'amount'      => ['currency' => strtoupper((string) $inv['currency']), 'value' => number_format((float) $inv['pay_amount'], 2, '.', '')],
            'description' => 'Rechnung ' . $inv['number'] . ((float) $inv['pay_amount'] < (float) $inv['amount_due'] - 0.004 ? ' (Teilzahlung)' : ''),
            'redirectUrl' => self::returnUrl($inv, $pid),
            'metadata'    => ['invoice_id' => (int) $inv['id'], 'payment_id' => $pid],
        ];
        $hook = url('webhooks/mollie');
        if (str_starts_with($hook, 'https://')) {   // Mollie akzeptiert nur öffentlich erreichbare https-Webhooks
            $body['webhookUrl'] = $hook;
        }
        $r = self::http('POST', self::base('mollie') . '/payments', ['Authorization: Bearer ' . Settings::get('mollie_api_key'), 'Content-Type: application/json'], json_encode($body));
        $ref = (string) ($r['id'] ?? '');
        $url = (string) ($r['_links']['checkout']['href'] ?? '');
        if ($ref === '' || !str_starts_with($url, 'https://') && !app_config('payments.allow_http', false)) {
            throw new RuntimeException('Unerwartete Antwort von Mollie.');
        }
        return [$ref, $url];
    }

    private static function stripeCreate(array $inv, int $pid): array
    {
        $cust = $inv['customer'] ?? [];
        $params = [
            'mode'                => 'payment',
            'success_url'         => self::returnUrl($inv, $pid),
            'cancel_url'          => self::returnUrl($inv, $pid),
            'client_reference_id' => 'inv-' . $inv['id'] . '-pay-' . $pid,
            'line_items'          => [[
                'quantity'   => 1,
                'price_data' => [
                    'currency'     => strtolower((string) $inv['currency']),
                    'unit_amount'  => (int) round((float) $inv['pay_amount'] * 100),
                    'product_data' => ['name' => 'Rechnung ' . $inv['number']],
                ],
            ]],
            'metadata'            => ['invoice_id' => (int) $inv['id'], 'payment_id' => $pid],
            'payment_intent_data' => ['metadata' => ['invoice_id' => (int) $inv['id'], 'payment_id' => $pid]],
        ];
        if (!empty($cust['email']) && valid_email((string) $cust['email'])) {
            $params['customer_email'] = $cust['email'];
        }
        $r = self::http('POST', self::base('stripe') . '/checkout/sessions', [
            'Authorization: Bearer ' . Settings::get('stripe_secret_key'),
            'Content-Type: application/x-www-form-urlencoded',
            'Idempotency-Key: om-pay-' . $pid,
        ], http_build_query($params));
        $ref = (string) ($r['id'] ?? '');
        $url = (string) ($r['url'] ?? '');
        if ($ref === '' || !str_starts_with($url, 'https://') && !app_config('payments.allow_http', false)) {
            throw new RuntimeException('Unerwartete Antwort von Stripe.');
        }
        return [$ref, $url];
    }

    /* ---------------------------------------------------------------- Status abgleichen */

    /** Fragt den Status beim Anbieter ab und übernimmt ihn. Rückgabe: neuer Status. */
    public static function sync(int $paymentId): string
    {
        $p = DB::one('SELECT * FROM payments WHERE id = ?', [$paymentId]);
        if (!$p || !$p['provider_ref']) {
            return $p['status'] ?? 'failed';
        }
        if ($p['provider'] === 'mollie') {
            $r = self::http('GET', self::base('mollie') . '/payments/' . rawurlencode((string) $p['provider_ref']), ['Authorization: Bearer ' . Settings::get('mollie_api_key')]);
            $status = match ($r['status'] ?? '') {
                'paid' => 'paid', 'canceled' => 'canceled', 'expired' => 'expired', 'failed' => 'failed', default => 'open',
            };
            $amount = isset($r['amount']['value']) ? (float) $r['amount']['value'] : null;
            $currency = $r['amount']['currency'] ?? null;
            $method = $r['method'] ?? null;
        } else {
            $r = self::http('GET', self::base('stripe') . '/checkout/sessions/' . rawurlencode((string) $p['provider_ref']), ['Authorization: Bearer ' . Settings::get('stripe_secret_key')]);
            $status = ($r['payment_status'] ?? '') === 'paid' ? 'paid' : (($r['status'] ?? '') === 'expired' ? 'expired' : 'open');
            $amount = isset($r['amount_total']) ? ((int) $r['amount_total']) / 100 : null;
            $currency = isset($r['currency']) ? strtoupper((string) $r['currency']) : null;
            $method = null;
        }
        self::apply((int) $p['id'], $status, $method, $amount, $currency);
        return (string) DB::val('SELECT status FROM payments WHERE id = ?', [$paymentId]);
    }

    /** Übernimmt einen (beim Anbieter verifizierten) Status; idempotent und gegen Doppelzahlung abgesichert. */
    private static function apply(int $paymentId, string $status, ?string $method, ?float $amount, ?string $currency): void
    {
        $becamePaid = null;
        DB::tx(static function () use ($paymentId, $status, $method, $amount, $currency, &$becamePaid): void {
            $p = DB::one('SELECT * FROM payments WHERE id = ? FOR UPDATE', [$paymentId]);
            if (!$p || $p['status'] === 'paid') {
                return;
            }
            if ($status === 'open' && $p['status'] === 'open') {
                return;
            }
            $upd = ['status' => $status, 'method' => $method ? mb_substr($method, 0, 40) : $p['method'], 'updated_at' => now()];
            if ($status !== 'paid') {
                DB::update('payments', $upd, 'id = :id', [':id' => $paymentId]);
                return;
            }
            $upd['paid_at'] = now();
            $inv = DB::one('SELECT * FROM invoices WHERE id = ? FOR UPDATE', [$p['invoice_id']]);
            $sys = ['id' => null, 'name' => 'Online-Zahlung (' . self::PROVIDERS[$p['provider']] . ')'];
            $openNow = Calc::r2((float) $inv['amount_due'] - InvoiceService::settled((int) $inv['id']));
            $mismatch = $amount === null || $currency === null || abs($amount - (float) $p['amount']) > 0.004 || strtoupper($currency) !== strtoupper((string) $p['currency'])
                || (float) $p['amount'] > $openNow + 0.004 && $inv['status'] === 'sent';
            if ($mismatch) {
                $upd['note'] = 'Betrag weicht ab – bitte manuell prüfen';
                History::add('invoice', (int) $inv['id'], 'note', null, null, 'Online-Zahlung eingegangen, Betrag/Währung weicht von der Rechnung ab (' . self::PROVIDERS[$p['provider']] . ' ' . $p['provider_ref'] . ') – bitte manuell prüfen.', false, $sys);
            } elseif ($inv['status'] === 'sent') {
                $res = InvoiceService::addPayment((int) $inv['id'], (float) $p['amount'], date('Y-m-d'), 'online', 'Online-Zahlung via ' . self::PROVIDERS[$p['provider']], $sys, (string) $p['provider_ref'], $paymentId);
                $becamePaid = [(int) $inv['id'], (float) $p['amount'], $res['complete']];
            } else {
                $upd['note'] = 'Zusätzliche Zahlung – Rechnung war bereits ' . ($inv['status'] === 'paid' ? 'bezahlt' : 'storniert') . '; Rückerstattung prüfen';
                History::add('invoice', (int) $inv['id'], 'note', null, null, 'Zusätzliche Online-Zahlung (' . self::PROVIDERS[$p['provider']] . ' ' . $p['provider_ref'] . ') bei bereits ' . ($inv['status'] === 'paid' ? 'bezahlter' : 'stornierter') . ' Rechnung – bitte Rückerstattung prüfen.', false, $sys);
            }
            DB::update('payments', $upd, 'id = :id', [':id' => $paymentId]);
        });
        if ($becamePaid) {
            $inv = InvoiceService::find($becamePaid[0]);
            if ($inv) {
                Notifier::invoicePaidOnline($inv, $becamePaid[1], $becamePaid[2], $paymentId);
            }
        }
    }

    /* ---------------------------------------------------------------- Eingehende Webhooks */

    /** Stripe-Signatur prüfen (Header „Stripe-Signature: t=…,v1=…“, HMAC-SHA256 über „t.payload“, Toleranz 5 Minuten). */
    public static function verifyStripeSignature(string $payload, string $header, string $secret): bool
    {
        if ($secret === '' || $header === '') {
            return false;
        }
        $t = null;
        $sigs = [];
        foreach (explode(',', $header) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($k === 't') {
                $t = (int) $v;
            } elseif ($k === 'v1') {
                $sigs[] = $v;
            }
        }
        if (!$t || abs(time() - $t) > 300) {
            return false;
        }
        $expected = hash_hmac('sha256', $t . '.' . $payload, $secret);
        foreach ($sigs as $s) {
            if (hash_equals($expected, $s)) {
                return true;
            }
        }
        return false;
    }

    public static function handleStripeEvent(array $event): void
    {
        $type = (string) ($event['type'] ?? '');
        if (!in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded', 'checkout.session.async_payment_failed', 'checkout.session.expired'], true)) {
            return;
        }
        $ref = (string) ($event['data']['object']['id'] ?? '');
        $id = DB::val("SELECT id FROM payments WHERE provider = 'stripe' AND provider_ref = ?", [$ref]);
        if ($id) {
            self::sync((int) $id);   // maßgeblich ist der Abruf beim Anbieter, nicht der Inhalt des Ereignisses
        }
    }

    public static function handleMollieId(string $ref): void
    {
        if (!preg_match('/^[A-Za-z0-9_]{3,60}$/', $ref)) {
            return;
        }
        $id = DB::val("SELECT id FROM payments WHERE provider = 'mollie' AND provider_ref = ?", [$ref]);
        if ($id) {
            self::sync((int) $id);
        }
    }

    /* ---------------------------------------------------------------- HTTP */

    private static function http(string $method, string $url, array $headers, ?string $body = null): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP-Erweiterung curl fehlt.');
        }
        $ch = curl_init($url);
        $opts = [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => array_merge(['Accept: application/json', 'User-Agent: ' . Brand::userAgent()], $headers),
        ];
        if (!app_config('payments.allow_http', false)) {
            $opts[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
        }
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);
        if ($raw === false || $err) {
            throw new RuntimeException('Verbindung zum Zahlungsanbieter fehlgeschlagen: ' . $err);
        }
        $json = json_decode((string) $raw, true);
        if ($code < 200 || $code >= 300 || !is_array($json)) {
            $msg = is_array($json) ? ($json['detail'] ?? $json['error']['message'] ?? $json['title'] ?? '') : '';
            throw new RuntimeException("Zahlungsanbieter antwortete mit HTTP $code" . ($msg ? ': ' . (is_string($msg) ? $msg : json_encode($msg)) : ''));
        }
        return $json;
    }
}
