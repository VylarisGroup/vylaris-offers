<?php
/**
 * Kundenanfragen über das Formular auf der Startseite.
 * Schutz: CSRF, Honeypot-Feld, Mindest-Ausfüllzeit (Session), Rate-Limit je IP und E-Mail, Link-Filter.
 * Datenschutz: ausdrückliche Einwilligung, Datensparsamkeit, automatische Löschung (Retention).
 */
declare(strict_types=1);

final class InquiryService
{
    public const STATUS = ['new' => ['Neu', 'sky'], 'handled' => ['Erledigt', 'emerald'], 'spam' => ['Spam', 'slate']];

    public static function enabled(): bool
    {
        return Settings::get('inquiry_enabled', '1') === '1';
    }

    /** Beim Anzeigen des Formulars aufrufen: merkt sich den Zeitpunkt für die Mindest-Ausfüllzeit. */
    public static function arm(): void
    {
        if (empty($_SESSION['inq_t']) || time() - (int) $_SESSION['inq_t'] > 7200) {
            $_SESSION['inq_t'] = time();
        }
    }

    /**
     * @return array{ok: bool, message: string, id?: int}
     */
    public static function submit(array $in): array
    {
        $generic = ['ok' => true, 'message' => 'Vielen Dank für Ihre Anfrage! Wir melden uns so rasch wie möglich bei Ihnen.'];
        if (!self::enabled()) {
            return ['ok' => false, 'message' => 'Das Anfrageformular ist derzeit nicht verfügbar.'];
        }
        $ip = client_ip();
        if (RateLimit::tooMany('inq:' . $ip, 5, 3600)) {
            return ['ok' => false, 'message' => 'Zu viele Anfragen von Ihrer Adresse. Bitte versuchen Sie es später erneut.'];
        }
        // Honeypot und Mindestzeit: Bots erhalten dieselbe Erfolgsmeldung, es wird aber nichts gespeichert.
        $armed = (int) ($_SESSION['inq_t'] ?? 0);
        if (trim((string) ($in['website'] ?? '')) !== '' || $armed === 0 || time() - $armed < 3) {
            RateLimit::hit('inq:' . $ip);
            return $armed === 0 ? ['ok' => false, 'message' => 'Die Sitzung ist abgelaufen. Bitte laden Sie die Seite neu und senden Sie das Formular erneut.'] : $generic;
        }

        $clean = static fn(string $k, int $max): string => mb_substr(trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) ($in[$k] ?? '')) ?? ''), 0, $max);
        $d = [
            'name' => $clean('name', 120), 'company' => $clean('company', 120), 'email' => mb_strtolower($clean('email', 190)),
            'phone' => $clean('phone', 50), 'subject' => $clean('subject', 190),
            'message' => str_replace("\r\n", "\n", mb_substr(trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) ($in['message'] ?? '')) ?? ''), 0, 4000)),
        ];
        $errors = [];
        if (mb_strlen($d['name']) < 2) {
            $errors[] = 'Bitte geben Sie Ihren Namen an.';
        }
        if (!valid_email($d['email'])) {
            $errors[] = 'Bitte geben Sie eine gültige E-Mail-Adresse an.';
        }
        if (mb_strlen($d['message']) < 10) {
            $errors[] = 'Bitte beschreiben Sie Ihr Anliegen in einigen Sätzen.';
        }
        if (empty($in['consent'])) {
            $errors[] = 'Bitte bestätigen Sie die Kenntnisnahme der Datenschutzerklärung.';
        }
        if ($errors) {
            return ['ok' => false, 'message' => implode(' ', $errors)];
        }
        if (RateLimit::tooMany('inq-mail:' . $d['email'], 3, 86400)) {
            return ['ok' => false, 'message' => 'Zu dieser E-Mail-Adresse liegen bereits mehrere Anfragen vor. Wir melden uns bei Ihnen.'];
        }
        RateLimit::hit('inq:' . $ip);
        RateLimit::hit('inq-mail:' . $d['email']);

        // Offensichtlicher Spam (viele Links) wird als „Spam“ abgelegt, ohne Kunde und ohne Benachrichtigung.
        $links = preg_match_all('~https?://|www\.~i', $d['message'] . ' ' . $d['subject']);
        $spam = $links > 2;

        $id = DB::insert('inquiries', $d + [
            'status' => $spam ? 'spam' : 'new', 'consent_at' => now(), 'ip' => $ip, 'created_at' => now(),
        ]);
        unset($_SESSION['inq_t']);
        if ($spam) {
            return $generic + ['id' => $id];
        }

        $customerId = self::customerFor($d);
        if ($customerId) {
            DB::exec('UPDATE inquiries SET customer_id = ? WHERE id = ?', [$customerId, $id]);
        }
        try {
            $inq = DB::one('SELECT * FROM inquiries WHERE id = ?', [$id]) ?: [];
            Notifier::inquiryReceived($inq);
            if (Settings::get('inquiry_confirm_mail', '1') === '1') {
                Notifier::inquiryConfirmation($inq);
            }
        } catch (Throwable $e) {
            ErrorHandler::log($e);
        }
        return $generic + ['id' => $id];
    }

    /** Bestehenden Kunden (gleiche E-Mail) wiederverwenden oder neu anlegen (ohne Portalzugang, ohne Daten zu überschreiben). */
    private static function customerFor(array $d): ?int
    {
        $c = DB::one('SELECT * FROM customers WHERE email = ?', [$d['email']]);
        if ($c) {
            return CustomerPrivacy::isBlocked($c) ? null : (int) $c['id'];
        }
        $parts = preg_split('/\s+/u', $d['name']) ?: [$d['name']];
        $last = count($parts) > 1 ? array_pop($parts) : $d['name'];
        $first = count($parts) > 0 && $last !== $d['name'] ? implode(' ', $parts) : '';
        return DB::insert('customers', [
            'customer_no' => Counter::customerNumber(), 'first_name' => mb_substr($first, 0, 100), 'last_name' => mb_substr($last, 0, 100),
            'company' => $d['company'] !== '' ? $d['company'] : null, 'street' => '', 'zip' => '', 'city' => '', 'country' => 'CH',
            'email' => $d['email'], 'phone' => $d['phone'] !== '' ? $d['phone'] : null, 'portal_enabled' => 0,
            'notes' => 'Über das Anfrageformular erfasst – Adresse ergänzen.', 'created_at' => now(),
        ]);
    }

    public static function newCount(): int
    {
        return (int) DB::val("SELECT COUNT(*) FROM inquiries WHERE status = 'new'");
    }
}
