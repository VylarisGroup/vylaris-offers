<?php
declare(strict_types=1);

/**
 * Minimale WebAuthn-Implementierung (Passkeys / Sicherheitsschlüssel als zweiter Faktor).
 * Unterstützt Attestation „none“, Algorithmen ES256 (-7) und RS256 (-257).
 * Geprüft werden: Challenge, Origin, RP-ID-Hash, User-Presence, Signatur und Signaturzähler.
 */
final class WebAuthn
{
    public static function b64u(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    public static function unb64u(string $s): string
    {
        $r = base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
        return $r === false ? '' : $r;
    }

    /** Relying-Party-ID = Hostname der Basis-URL. */
    public static function rpId(): string
    {
        return (string) parse_url((string) app_config('app.base_url'), PHP_URL_HOST);
    }

    public static function origin(): string
    {
        $u = parse_url((string) app_config('app.base_url'));
        return ($u['scheme'] ?? 'https') . '://' . ($u['host'] ?? '') . (isset($u['port']) ? ':' . $u['port'] : '');
    }

    /** Passkeys erfordern HTTPS (ausser localhost). */
    public static function available(): bool
    {
        $u = parse_url((string) app_config('app.base_url'));
        $host = (string) ($u['host'] ?? '');
        return function_exists('openssl_verify') && $host !== '' && (($u['scheme'] ?? '') === 'https' || $host === 'localhost' || $host === '127.0.0.1');
    }

    public static function challenge(string $key): string
    {
        $c = random_bytes(32);
        $_SESSION['wa_' . $key] = ['c' => $c, 'exp' => time() + 300];
        return self::b64u($c);
    }

    private static function takeChallenge(string $key): string
    {
        $c = $_SESSION['wa_' . $key] ?? null;
        unset($_SESSION['wa_' . $key]);   // einmalig verwendbar
        if (!is_array($c) || $c['exp'] < time()) {
            throw new ValidationException('Die Anfrage ist abgelaufen. Bitte versuchen Sie es erneut.');
        }
        return $c['c'];
    }

    /** Stabile, nicht personenbezogene Benutzerkennung (Passkey-User-Handle). */
    public static function userHandle(int $userId): string
    {
        return hash_hmac('sha256', 'passkey-user:' . $userId, (string) app_config('app.key'), true);
    }

    private static function checkClientData(string $json, string $type, string $challenge): void
    {
        $cd = json_decode($json, true);
        if (!is_array($cd) || ($cd['type'] ?? '') !== $type) {
            throw new ValidationException('Ungültige Antwort des Geräts.');
        }
        if (!hash_equals(self::b64u($challenge), (string) ($cd['challenge'] ?? ''))) {
            throw new ValidationException('Ungültige Antwort des Geräts (Challenge).');
        }
        if (!hash_equals(self::origin(), (string) ($cd['origin'] ?? ''))) {
            throw new ValidationException('Die Anfrage stammt von einer anderen Adresse und wurde abgelehnt.');
        }
        if (!empty($cd['crossOrigin'])) {
            throw new ValidationException('Die Anfrage stammt aus einem fremden Frame und wurde abgelehnt.');
        }
    }

    /** @return array{0:string,1:int,2:bool} rpIdHash, signCount, uvFlag */
    private static function checkAuthData(string $ad, bool $needAttested): array
    {
        if (strlen($ad) < 37) {
            throw new ValidationException('Ungültige Authenticator-Daten.');
        }
        if (!hash_equals(hash('sha256', self::rpId(), true), substr($ad, 0, 32))) {
            throw new ValidationException('Der Passkey gehört zu einer anderen Domain.');
        }
        $flags = ord($ad[32]);
        if (!($flags & 0x01)) {
            throw new ValidationException('Die Anwesenheit des Benutzers wurde nicht bestätigt.');
        }
        if ($needAttested && !($flags & 0x40)) {
            throw new ValidationException('Der Passkey enthält keine Schlüsseldaten.');
        }
        $count = unpack('N', substr($ad, 33, 4))[1];
        return [substr($ad, 0, 32), (int) $count, (bool) ($flags & 0x04)];
    }

    /* ---------------------------------------------------------------- Registrierung */

    public static function registrationOptions(array $user, array $existingIds): array
    {
        return [
            'challenge' => self::challenge('reg'),
            'rp' => ['id' => self::rpId(), 'name' => Brand::name()],
            'user' => ['id' => self::b64u(self::userHandle((int) $user['id'])), 'name' => (string) $user['email'], 'displayName' => (string) $user['name']],
            'pubKeyCredParams' => [['type' => 'public-key', 'alg' => -7], ['type' => 'public-key', 'alg' => -257]],
            'timeout' => 120000,
            'attestation' => 'none',
            'authenticatorSelection' => ['residentKey' => 'discouraged', 'userVerification' => 'preferred'],
            'excludeCredentials' => array_map(static fn ($id) => ['type' => 'public-key', 'id' => $id], $existingIds),
        ];
    }

    /** @return array{id:string,key:string,alg:int,count:int} */
    public static function verifyRegistration(array $cred): array
    {
        $challenge = self::takeChallenge('reg');
        $clientData = self::unb64u((string) ($cred['clientDataJSON'] ?? ''));
        $att = self::unb64u((string) ($cred['attestationObject'] ?? ''));
        $rawId = (string) ($cred['id'] ?? '');
        if ($clientData === '' || $att === '' || $rawId === '' || strlen($rawId) > 340) {
            throw new ValidationException('Unvollständige Antwort des Geräts.');
        }
        self::checkClientData($clientData, 'webauthn.create', $challenge);
        $obj = Cbor::decode($att);
        if (!is_array($obj) || !isset($obj['authData']) || !is_string($obj['authData'])) {
            throw new ValidationException('Ungültige Attestation.');
        }
        $ad = $obj['authData'];
        [, $count] = self::checkAuthData($ad, true);
        $pos = 37 + 16;   // AAGUID überspringen
        if (strlen($ad) < $pos + 2) {
            throw new ValidationException('Ungültige Authenticator-Daten.');
        }
        $len = unpack('n', substr($ad, $pos, 2))[1];
        $credId = substr($ad, $pos + 2, $len);
        if ($len < 16 || strlen($credId) !== $len || !hash_equals(self::b64u($credId), $rawId)) {
            throw new ValidationException('Die Schlüssel-ID stimmt nicht überein.');
        }
        $offset = $pos + 2 + $len;
        $cose = Cbor::decode($ad, $offset, true);
        if (!is_array($cose)) {
            throw new ValidationException('Ungültiger öffentlicher Schlüssel.');
        }
        [$pem, $alg] = self::coseToPem($cose);
        return ['id' => self::b64u($credId), 'key' => $pem, 'alg' => $alg, 'count' => $count];
    }

    /** COSE-Schlüssel → PEM (ES256 / RS256). */
    private static function coseToPem(array $k): array
    {
        $kty = $k[1] ?? null;
        $alg = $k[3] ?? null;
        if ($kty === 2 && $alg === -7 && ($k[-1] ?? null) === 1 && isset($k[-2], $k[-3]) && strlen($k[-2]) === 32 && strlen($k[-3]) === 32) {
            $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . "\x04" . $k[-2] . $k[-3];
        } elseif ($kty === 3 && $alg === -257 && isset($k[-1], $k[-2])) {
            $int = static function (string $b): string {
                $b = ltrim($b, "\0");
                if ($b === '' || ord($b[0]) & 0x80) {
                    $b = "\0" . $b;
                }
                return "\x02" . self::derLen(strlen($b)) . $b;
            };
            $seq = "\x30" . self::derLen(strlen($int($k[-1]) . $int($k[-2]))) . $int($k[-1]) . $int($k[-2]);
            $bits = "\x03" . self::derLen(strlen($seq) + 1) . "\0" . $seq;
            $algId = hex2bin('300d06092a864886f70d0101010500');
            $der = "\x30" . self::derLen(strlen($algId . $bits)) . $algId . $bits;
        } else {
            throw new ValidationException('Dieser Schlüsseltyp wird nicht unterstützt.');
        }
        $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
        if (!openssl_pkey_get_public($pem)) {
            throw new ValidationException('Ungültiger öffentlicher Schlüssel.');
        }
        return [$pem, (int) $alg];
    }

    private static function derLen(int $n): string
    {
        if ($n < 128) {
            return chr($n);
        }
        $b = ltrim(pack('N', $n), "\0");
        return chr(0x80 | strlen($b)) . $b;
    }

    /* ---------------------------------------------------------------- Anmeldung */

    public static function authOptions(array $credentialIds): array
    {
        return [
            'challenge' => self::challenge('auth'),
            'rpId' => self::rpId(),
            'timeout' => 120000,
            'userVerification' => 'preferred',
            'allowCredentials' => array_map(static fn ($id) => ['type' => 'public-key', 'id' => $id], $credentialIds),
        ];
    }

    /**
     * Prüft die Assertion gegen die gespeicherten Passkeys des Benutzers.
     * @param array<int,array> $passkeys Zeilen aus `passkeys`
     * @return array{passkey:array,count:int}
     */
    public static function verifyAssertion(array $cred, array $passkeys): array
    {
        $challenge = self::takeChallenge('auth');
        $match = null;
        foreach ($passkeys as $p) {
            if (hash_equals((string) $p['credential_id'], (string) ($cred['id'] ?? ''))) {
                $match = $p;
            }
        }
        if (!$match) {
            throw new ValidationException('Dieser Passkey ist für das Konto nicht hinterlegt.');
        }
        $clientData = self::unb64u((string) ($cred['clientDataJSON'] ?? ''));
        $ad = self::unb64u((string) ($cred['authenticatorData'] ?? ''));
        $sig = self::unb64u((string) ($cred['signature'] ?? ''));
        if ($clientData === '' || $ad === '' || $sig === '') {
            throw new ValidationException('Unvollständige Antwort des Geräts.');
        }
        self::checkClientData($clientData, 'webauthn.get', $challenge);
        [, $count] = self::checkAuthData($ad, false);
        $ok = openssl_verify($ad . hash('sha256', $clientData, true), $sig, (string) $match['public_key'], OPENSSL_ALGO_SHA256);
        if ($ok !== 1) {
            throw new ValidationException('Die Signatur ist ungültig.');
        }
        $stored = (int) $match['sign_count'];
        if (($stored > 0 || $count > 0) && $count <= $stored) {
            throw new ValidationException('Der Signaturzähler des Passkeys ist ungültig (möglicherweise kopierter Schlüssel).');
        }
        return ['passkey' => $match, 'count' => $count];
    }
}

/** Minimaler CBOR-Decoder (RFC 8949) für WebAuthn: Ganzzahlen, Byte-/Text-Strings, Arrays, Maps, Simple Values. */
final class Cbor
{
    /** @param bool $prefix true: Daten dürfen nach dem Wert weitergehen; $offset zeigt auf den Start. */
    public static function decode(string $data, int $offset = 0, bool $prefix = false): mixed
    {
        $pos = $offset;
        $v = self::item($data, $pos, 0);
        if (!$prefix && $pos !== strlen($data)) {
            throw new ValidationException('Ungültige CBOR-Daten.');
        }
        return $v;
    }

    private static function item(string $d, int &$p, int $depth): mixed
    {
        if ($depth > 8 || $p >= strlen($d)) {
            throw new ValidationException('Ungültige CBOR-Daten.');
        }
        $ib = ord($d[$p++]);
        $major = $ib >> 5;
        $info = $ib & 31;
        $n = match (true) {
            $info < 24 => $info,
            $info === 24 => self::read($d, $p, 1),
            $info === 25 => self::read($d, $p, 2),
            $info === 26 => self::read($d, $p, 4),
            $info === 27 => self::read($d, $p, 8),
            default => throw new ValidationException('Nicht unterstütztes CBOR.'),
        };
        switch ($major) {
            case 0:
                return $n;
            case 1:
                return -1 - $n;
            case 2:
            case 3:
                if ($n > 65536 || $p + $n > strlen($d)) {
                    throw new ValidationException('Ungültige CBOR-Daten.');
                }
                $s = substr($d, $p, $n);
                $p += $n;
                return $s;
            case 4:
                if ($n > 64) {
                    throw new ValidationException('Ungültige CBOR-Daten.');
                }
                $a = [];
                for ($i = 0; $i < $n; $i++) {
                    $a[] = self::item($d, $p, $depth + 1);
                }
                return $a;
            case 5:
                if ($n > 64) {
                    throw new ValidationException('Ungültige CBOR-Daten.');
                }
                $m = [];
                for ($i = 0; $i < $n; $i++) {
                    $k = self::item($d, $p, $depth + 1);
                    if (!is_int($k) && !is_string($k)) {
                        throw new ValidationException('Ungültige CBOR-Daten.');
                    }
                    $m[$k] = self::item($d, $p, $depth + 1);
                }
                return $m;
            case 7:
                return match ($info) { 20 => false, 21 => true, 22, 23 => null, default => throw new ValidationException('Nicht unterstütztes CBOR.') };
            default:
                throw new ValidationException('Nicht unterstütztes CBOR.');
        }
    }

    private static function read(string $d, int &$p, int $len): int
    {
        if ($p + $len > strlen($d)) {
            throw new ValidationException('Ungültige CBOR-Daten.');
        }
        $v = 0;
        for ($i = 0; $i < $len; $i++) {
            $v = ($v << 8) | ord($d[$p + $i]);
        }
        $p += $len;
        return $v;
    }
}
