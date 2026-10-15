<?php
/**
 * Swiss QR-Rechnung (Swiss Payment Standards 2.0): IBAN-Prüfung, Referenzen (QRR/SCOR) und QR-Payload.
 */
declare(strict_types=1);

final class SwissQr
{
    public static function iban(): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string) Settings::get('invoice_iban', '')) ?? '');
    }

    public static function validIban(string $iban): bool
    {
        $iban = strtoupper(preg_replace('/\s+/', '', $iban) ?? '');
        if (!preg_match('/^(CH|LI)\d{2}[A-Z0-9]{17}$/', $iban)) {
            return false;
        }
        $rearranged = substr($iban, 4) . substr($iban, 0, 4);
        $num = '';
        foreach (str_split($rearranged) as $ch) {
            $num .= ctype_alpha($ch) ? (string) (ord($ch) - 55) : $ch;
        }
        $rem = 0;
        foreach (str_split($num, 7) as $chunk) {
            $rem = (int) ($rem . $chunk) % 97;
        }
        return $rem === 1;
    }

    public static function isQrIban(string $iban): bool
    {
        $iid = (int) substr($iban, 4, 5);
        return $iid >= 30000 && $iid <= 31999;
    }

    public static function formatIban(string $iban): string
    {
        return trim(chunk_split($iban, 4, ' '));
    }

    /** Modulo-10 rekursiv (Prüfziffer für QR-Referenz). */
    public static function mod10(string $digits): int
    {
        $table = [0, 9, 4, 6, 8, 2, 7, 1, 3, 5];
        $carry = 0;
        foreach (str_split($digits) as $d) {
            $carry = $table[($carry + (int) $d) % 10];
        }
        return (10 - $carry) % 10;
    }

    /** Referenz passend zur IBAN-Art: QRR (27-stellig) bei QR-IBAN, sonst SCOR (RF…). Null ohne gültige IBAN. */
    public static function referenceFor(array $inv): ?string
    {
        $iban = self::iban();
        if ($iban === '' || !self::validIban($iban)) {
            return null;
        }
        if (self::isQrIban($iban)) {
            $base = str_pad((string) $inv['id'], 26, '0', STR_PAD_LEFT);
            return $base . self::mod10($base);
        }
        $body = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $inv['number']) ?? '');
        $body = substr($body, -21);
        $num = '';
        foreach (str_split($body . 'RF00') as $ch) {
            $num .= ctype_alpha($ch) ? (string) (ord($ch) - 55) : $ch;
        }
        $rem = 0;
        foreach (str_split($num, 7) as $chunk) {
            $rem = (int) ($rem . $chunk) % 97;
        }
        return 'RF' . str_pad((string) (98 - $rem), 2, '0', STR_PAD_LEFT) . $body;
    }

    public static function refType(?string $ref): string
    {
        if (!$ref) {
            return 'NON';
        }
        return str_starts_with($ref, 'RF') ? 'SCOR' : 'QRR';
    }

    public static function formatRef(?string $ref): string
    {
        if (!$ref) {
            return '';
        }
        if (str_starts_with($ref, 'RF')) {
            return trim(chunk_split($ref, 4, ' '));
        }
        $first = substr($ref, 0, 2);
        return $first . ' ' . trim(chunk_split(substr($ref, 2), 5, ' '));
    }

    /** Ist für diese Rechnung eine QR-Rechnung möglich? */
    public static function available(array $inv): bool
    {
        $iban = self::iban();
        return $iban !== '' && self::validIban($iban) && in_array($inv['currency'], ['CHF', 'EUR'], true) && (float) $inv['amount_due'] > 0;
    }

    /** @return array{street:string,nr:string} */
    public static function splitStreet(string $street): array
    {
        $street = trim($street);
        if (preg_match('/^(.*?)[\s,]+(\d+[a-zA-Z]?(?:[-\/]\d+[a-zA-Z]?)?)$/u', $street, $m)) {
            return ['street' => $m[1], 'nr' => $m[2]];
        }
        return ['street' => $street, 'nr' => ''];
    }

    public static function creditor(): array
    {
        $s = self::splitStreet((string) Settings::get('company_street', ''));
        return [
            'name' => (string) Settings::get('company_name'), 'street' => $s['street'], 'nr' => $s['nr'],
            'zip' => (string) Settings::get('company_zip', ''), 'city' => (string) Settings::get('company_city', ''),
            'country' => (string) Settings::get('company_country', 'CH'),
        ];
    }

    public static function debtor(array $c): ?array
    {
        $name = trim((string) ($c['company'] ?? '')) ?: trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
        $s = self::splitStreet((string) ($c['street'] ?? ''));
        $d = ['name' => $name, 'street' => $s['street'], 'nr' => $s['nr'], 'zip' => (string) ($c['zip'] ?? ''), 'city' => (string) ($c['city'] ?? ''), 'country' => (string) ($c['country'] ?? '')];
        return ($d['name'] !== '' && $d['zip'] !== '' && $d['city'] !== '' && $d['country'] !== '') ? $d : null;
    }

    private static function addr(?array $a): array
    {
        if (!$a) {
            return array_fill(0, 7, '');
        }
        return ['S', mb_substr($a['name'], 0, 70), mb_substr($a['street'], 0, 70), mb_substr($a['nr'], 0, 16), mb_substr($a['zip'], 0, 16), mb_substr($a['city'], 0, 35), strtoupper(substr($a['country'], 0, 2))];
    }

    public static function message(array $inv): string
    {
        return mb_substr($inv['number'] . ' – ' . $inv['title'], 0, 140);
    }

    public static function payload(array $inv): ?string
    {
        if (!self::available($inv)) {
            return null;
        }
        $ref = $inv['qr_reference'] ?: self::referenceFor($inv);
        $lines = ['SPC', '0200', '1', self::iban()];
        $lines = array_merge($lines, self::addr(self::creditor()), array_fill(0, 7, ''));
        $lines[] = number_format((float) ($inv['pay_amount'] ?? $inv['amount_due']), 2, '.', '');
        $lines[] = $inv['currency'];
        $lines = array_merge($lines, self::addr(self::debtor($inv['customer'])));
        $lines[] = self::refType($ref);
        $lines[] = (string) $ref;
        $lines[] = self::message($inv);
        $lines[] = 'EPD';
        return implode("\n", $lines);
    }

    /** QR-Matrix (bool[][]) mit korrekter Fehlerkorrektur M, ohne Ruhezone. */
    public static function matrix(string $data): array
    {
        $opt = new \chillerlan\QRCode\QROptions([
            'eccLevel' => \chillerlan\QRCode\Common\EccLevel::M,
            'addQuietzone' => false,
            'versionMin' => 1, 'versionMax' => 25,
        ]);
        $qr = new \chillerlan\QRCode\QRCode($opt);
        $qr->addByteSegment($data);
        $m = $qr->getQRMatrix();
        $n = $m->getSize();
        $rows = [];
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                $rows[$y][$x] = $m->check($x, $y);
            }
        }
        return $rows;
    }

    /** SVG für die Bildschirmanzeige (z. B. 2FA-Einrichtung, beliebiger Text). */
    public static function svg(string $data, int $px = 200): string
    {
        $opt = new \chillerlan\QRCode\QROptions(['eccLevel' => \chillerlan\QRCode\Common\EccLevel::M, 'addQuietzone' => true, 'quietzoneSize' => 2]);
        $qr = new \chillerlan\QRCode\QRCode($opt);
        $qr->addByteSegment($data);
        $m = $qr->getQRMatrix();
        $n = $m->getSize();
        $path = '';
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($m->check($x, $y)) {
                    $path .= "M$x,{$y}h1v1h-1z";
                }
            }
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $n . ' ' . $n . '" width="' . $px . '" height="' . $px . '" shape-rendering="crispEdges" role="img" aria-label="QR-Code"><rect width="100%" height="100%" fill="#fff"/><path d="' . $path . '" fill="#000"/></svg>';
    }
}
