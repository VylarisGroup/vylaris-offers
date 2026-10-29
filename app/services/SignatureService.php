<?php
/**
 * Unterschrift-Modi einer Offerte:
 *  - none   : keine Unterschrift
 *  - open   : Unterschrift erforderlich, jede Person mit dem Link kann unterschreiben
 *  - strict : Unterschrift einer bestimmten natürlichen Person; deren E-Mail-Adresse wird
 *             per Einmalcode bestätigt. Der Code geht ausschliesslich an die in der Offerte
 *             hinterlegte Adresse (nie an eine vom Besucher eingegebene).
 */
declare(strict_types=1);

final class SignatureService
{
    public const MODES = [
        'none'   => 'Keine Unterschrift',
        'open'   => 'Offene Unterschrift',
        'strict' => 'Strikte Unterschrift',
    ];

    private const TTL = 900;          // Code 15 Minuten gültig
    private const MAX_ATTEMPTS = 5;   // Fehlversuche pro Code
    private const COOLDOWN = 60;      // Sekunden zwischen zwei Codes
    private const MAX_PER_HOUR = 5;   // Codes pro Offerte und Stunde

    public static function mode(array $offer): string
    {
        $m = (string) ($offer['signature_mode'] ?? 'none');
        return isset(self::MODES[$m]) ? $m : 'none';
    }

    public static function maskEmail(string $email): string
    {
        [$u, $d] = array_pad(explode('@', $email, 2), 2, '');
        $u = mb_substr($u, 0, 1) . str_repeat('•', max(2, min(6, mb_strlen($u) - 1)));
        return $u . '@' . $d;
    }

    /** Prüft die Eingaben des Offerten-Editors und liefert die Felder für die Datenbank. */
    public static function fromInput(array $in, array $customer, array &$errors): array
    {
        $mode = (string) ($in['signature_mode'] ?? 'none');
        if (!isset(self::MODES[$mode])) {
            $mode = 'none';
        }
        $out = ['signature_mode' => $mode, 'require_signature' => $mode === 'none' ? 0 : 1, 'signer_name' => null, 'signer_email' => null];
        if ($mode !== 'strict') {
            return $out;
        }
        $name = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($in['signer_name'] ?? ''))), 0, 190);
        $email = mb_strtolower(trim((string) ($in['signer_email'] ?? '')));
        if ($name === '') {
            $name = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
        }
        if ($email === '') {
            $email = mb_strtolower(trim((string) ($customer['email'] ?? '')));
        }
        if (mb_strlen($name) < 3 || !str_contains($name, ' ') || !preg_match('/^[\p{L}][\p{L}\p{M}\s\'’.\-]+$/u', $name)) {
            $errors['signer_name'] = 'Bitte Vor- und Nachname der unterschreibenden Person angeben (natürliche Person).';
        }
        if (!valid_email($email)) {
            $errors['signer_email'] = 'Bitte eine gültige E-Mail-Adresse der unterschreibenden Person angeben.';
        }
        $out['signer_name'] = $name;
        $out['signer_email'] = $email;
        return $out;
    }

    /** Sendet einen neuen Code. Rückgabe: [ok, Meldung]. */
    public static function sendCode(array $offer): array
    {
        if (self::mode($offer) !== 'strict' || $offer['status'] !== 'open' || !valid_email((string) $offer['signer_email'])) {
            return [false, 'Für diese Offerte ist keine E-Mail-Bestätigung möglich.'];
        }
        $id = (int) $offer['id'];
        $ip = client_ip();
        if (RateLimit::tooMany("sigcode-ip:$ip", 15, 3600)) {
            return [false, 'Zu viele Anfragen. Bitte versuchen Sie es später erneut.'];
        }
        $last = DB::val('SELECT MAX(created_at) FROM signature_codes WHERE offer_id = ?', [$id]);
        if ($last && time() - strtotime((string) $last) < self::COOLDOWN) {
            return [false, 'Bitte warten Sie eine Minute, bevor Sie einen neuen Code anfordern.'];
        }
        $since = date('Y-m-d H:i:s', time() - 3600);
        if ((int) DB::val('SELECT COUNT(*) FROM signature_codes WHERE offer_id = ? AND created_at >= ?', [$id, $since]) >= self::MAX_PER_HOUR) {
            return [false, 'Es wurden zu viele Codes angefordert. Bitte versuchen Sie es in einer Stunde erneut.'];
        }
        RateLimit::hit("sigcode-ip:$ip");

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        DB::tx(static function () use ($id, $offer, $code, $ip): void {
            DB::exec('UPDATE signature_codes SET used_at = ? WHERE offer_id = ? AND used_at IS NULL', [now(), $id]);
            DB::insert('signature_codes', [
                'offer_id'   => $id,
                'email'      => $offer['signer_email'],
                'code_hash'  => self::hash($id, (string) $offer['signer_email'], $code),
                'expires_at' => date('Y-m-d H:i:s', time() + self::TTL),
                'request_ip' => $ip,
                'created_at' => now(),
            ]);
        });
        if (!Notifier::signatureCode($offer, $code)) {
            return [false, 'Die E-Mail konnte nicht gesendet werden. Bitte versuchen Sie es später erneut.'];
        }
        return [true, 'Der Code wurde an ' . self::maskEmail((string) $offer['signer_email']) . ' gesendet (15 Minuten gültig).'];
    }

    /**
     * Prüft und verbraucht den Code (einmalig, atomar). Fehlversuche werden gezählt;
     * nach 5 Fehlversuchen ist der Code ungültig. Wirft ValidationException bei Fehlern.
     */
    public static function consumeCode(array $offer, string $input): void
    {
        $id = (int) $offer['id'];
        $input = preg_replace('/\D/', '', $input) ?? '';
        $bucket = "sigverify:$id";
        if (RateLimit::tooMany($bucket, 12, 3600) || RateLimit::tooMany('sigverify-ip:' . client_ip(), 20, 3600)) {
            throw new ValidationException('Zu viele Fehlversuche. Bitte versuchen Sie es später erneut.');
        }
        $row = DB::one(
            'SELECT * FROM signature_codes WHERE offer_id = ? AND used_at IS NULL AND email = ? ORDER BY id DESC LIMIT 1',
            [$id, $offer['signer_email']]
        );
        if (!$row || strtotime((string) $row['expires_at']) < time()) {
            throw new ValidationException('Kein gültiger Bestätigungscode vorhanden. Bitte fordern Sie einen neuen Code an.');
        }
        if ((int) $row['attempts'] >= self::MAX_ATTEMPTS) {
            throw new ValidationException('Der Code wurde zu oft falsch eingegeben. Bitte fordern Sie einen neuen Code an.');
        }
        if (strlen($input) !== 6 || !hash_equals((string) $row['code_hash'], self::hash($id, (string) $offer['signer_email'], $input))) {
            DB::exec('UPDATE signature_codes SET attempts = attempts + 1 WHERE id = ?', [$row['id']]);
            RateLimit::hit($bucket);
            RateLimit::hit('sigverify-ip:' . client_ip());
            throw new ValidationException('Der Bestätigungscode ist nicht korrekt.');
        }
        // Atomar verbrauchen: nur ein Request kann den Code einlösen.
        $n = DB::exec('UPDATE signature_codes SET used_at = ? WHERE id = ? AND used_at IS NULL', [now(), $row['id']]);
        if ($n !== 1) {
            throw new ValidationException('Der Code wurde bereits verwendet. Bitte fordern Sie einen neuen Code an.');
        }
    }

    private static function hash(int $offerId, string $email, string $code): string
    {
        return Crypto::hmac("sigcode|$offerId|" . mb_strtolower($email) . "|$code");
    }
}
