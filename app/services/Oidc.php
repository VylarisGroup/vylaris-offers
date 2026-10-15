<?php
/**
 * OpenID-Connect-Client (Authorization-Code-Flow mit PKCE) für die Single-Sign-On-Anmeldung der Mitarbeitenden.
 *
 *  - Es genügt die Adresse des Anbieters (z. B. https://vylaris.tech): Endpunkte stammen aus
 *    {Adresse}/.well-known/openid-configuration; ohne Client-ID wird – falls angeboten – automatisch registriert (RFC 7591).
 *  - ID-Token werden vollständig geprüft: Signatur (RS256/384/512, ES256, EdDSA über JWKS), iss, aud/azp, exp, iat, nonce.
 */
declare(strict_types=1);

final class Oidc
{
    private const DISCOVERY_TTL = 3600;
    private const SKEW = 120;

    /* ======================================================== Hilfsfunktionen (rein) */

    public static function b64uEncode(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    public static function b64uDecode(string $s): string|false
    {
        $s = strtr($s, '-_', '+/');
        return base64_decode($s . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
    }

    /** Eingabe → normalisierte Anbieter-Adresse (ohne /.well-known/…, ohne Schrägstrich am Ende). Null bei ungültig. */
    public static function normalizeIssuer(string $input): ?string
    {
        $u = trim($input);
        $u = preg_replace('#/\.well-known/openid-configuration/?$#i', '', $u);
        $u = rtrim((string) $u, '/');
        if ($u !== '' && !preg_match('#^[a-z][a-z0-9+.-]*://#i', $u)) {
            $u = 'https://' . $u;   // «vylaris.tech» genügt
        }
        if ($u === '' || !preg_match('#^(https://[^\s/?\#]+(/[^\s?\#]*)?|http://(localhost|127\.0\.0\.1)(:\d+)?(/[^\s?\#]*)?)$#i', $u)) {
            return null;
        }
        return $u;
    }

    private static function derLen(int $n): string
    {
        return $n < 128 ? chr($n) : ($n < 256 ? "\x81" . chr($n) : "\x82" . chr($n >> 8) . chr($n & 255));
    }

    private static function derInt(string $raw): string
    {
        $raw = ltrim($raw, "\x00");
        if ($raw === '' || ord($raw[0]) > 127) {
            $raw = "\x00" . $raw;
        }
        return "\x02" . self::derLen(strlen($raw)) . $raw;
    }

    private static function pem(string $der): string
    {
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    public static function rsaPem(string $n, string $e): string
    {
        $seq = self::derInt($n) . self::derInt($e);
        $rsa = "\x30" . self::derLen(strlen($seq)) . $seq;
        $bits = "\x03" . self::derLen(strlen($rsa) + 1) . "\x00" . $rsa;
        $alg = hex2bin('300d06092a864886f70d0101010500');
        $body = $alg . $bits;
        return self::pem("\x30" . self::derLen(strlen($body)) . $body);
    }

    public static function ecP256Pem(string $x, string $y): string
    {
        return self::pem(hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . "\x04" . str_pad($x, 32, "\x00", STR_PAD_LEFT) . str_pad($y, 32, "\x00", STR_PAD_LEFT));
    }

    /** JWS-Signatur (r||s) → DER für openssl_verify. */
    public static function rawToDer(string $sig): string
    {
        $h = intdiv(strlen($sig), 2);
        $body = self::derInt(substr($sig, 0, $h)) . self::derInt(substr($sig, $h));
        return "\x30" . self::derLen(strlen($body)) . $body;
    }

    /** DER → r||s (nur für Tests/Hilfszwecke). */
    public static function derToRaw(string $der, int $size = 32): string
    {
        $o = 2 + (ord($der[1]) & 0x80 ? 1 : 0);
        $out = '';
        for ($i = 0; $i < 2; $i++) {
            $len = ord($der[$o + 1]);
            $int = ltrim(substr($der, $o + 2, $len), "\x00");
            $out .= str_pad($int, $size, "\x00", STR_PAD_LEFT);
            $o += 2 + $len;
        }
        return $out;
    }

    /**
     * Prüft ein JWT gegen ein JWKS. @return array{0:array,1:array}|null [Header, Claims]
     */
    public static function verifyJwt(string $jwt, array $jwks): ?array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }
        $h = json_decode((string) self::b64uDecode($parts[0]), true);
        $c = json_decode((string) self::b64uDecode($parts[1]), true);
        $sig = self::b64uDecode($parts[2]);
        if (!is_array($h) || !is_array($c) || $sig === false || $sig === '') {
            return null;
        }
        $alg = (string) ($h['alg'] ?? '');
        if (!in_array($alg, ['RS256', 'RS384', 'RS512', 'ES256', 'EdDSA'], true)) {
            return null;   // «none» und HMAC-Verfahren werden nie akzeptiert
        }
        $kid = $h['kid'] ?? null;
        $input = $parts[0] . '.' . $parts[1];
        foreach ((array) ($jwks['keys'] ?? []) as $k) {
            if (!is_array($k) || ($kid !== null && isset($k['kid']) && $k['kid'] !== $kid) || (isset($k['use']) && $k['use'] !== 'sig')) {
                continue;
            }
            $kty = $k['kty'] ?? '';
            try {
                $ok = false;
                if (str_starts_with($alg, 'RS') && $kty === 'RSA' && isset($k['n'], $k['e'])) {
                    $pem = self::rsaPem((string) self::b64uDecode($k['n']), (string) self::b64uDecode($k['e']));
                    $ok = openssl_verify($input, $sig, $pem, ['RS256' => OPENSSL_ALGO_SHA256, 'RS384' => OPENSSL_ALGO_SHA384, 'RS512' => OPENSSL_ALGO_SHA512][$alg]) === 1;
                } elseif ($alg === 'ES256' && $kty === 'EC' && ($k['crv'] ?? '') === 'P-256' && isset($k['x'], $k['y']) && strlen($sig) === 64) {
                    $pem = self::ecP256Pem((string) self::b64uDecode($k['x']), (string) self::b64uDecode($k['y']));
                    $ok = openssl_verify($input, self::rawToDer($sig), $pem, OPENSSL_ALGO_SHA256) === 1;
                } elseif ($alg === 'EdDSA' && $kty === 'OKP' && ($k['crv'] ?? '') === 'Ed25519' && isset($k['x']) && strlen($sig) === 64 && function_exists('sodium_crypto_sign_verify_detached')) {
                    $ok = sodium_crypto_sign_verify_detached($sig, $input, (string) self::b64uDecode($k['x']));
                }
                if ($ok) {
                    return [$h, $c];
                }
            } catch (Throwable) {
                continue;
            }
        }
        return null;
    }

    /** Prüft die Claims des ID-Tokens. @return string|null Fehlertext oder null, wenn gültig. */
    public static function checkClaims(array $c, string $issuer, string $clientId, string $nonce, int $now): ?string
    {
        if (rtrim((string) ($c['iss'] ?? ''), '/') !== rtrim($issuer, '/')) {
            return 'Aussteller (iss) stimmt nicht überein.';
        }
        $aud = $c['aud'] ?? null;
        $auds = is_array($aud) ? $aud : [$aud];
        if (!in_array($clientId, $auds, true)) {
            return 'Das Token ist nicht für diese Anwendung ausgestellt (aud).';
        }
        if (count($auds) > 1 && ($c['azp'] ?? null) !== $clientId) {
            return 'Ungültige azp-Angabe.';
        }
        if (!isset($c['exp']) || (int) $c['exp'] + self::SKEW < $now) {
            return 'Das Token ist abgelaufen.';
        }
        if (isset($c['iat']) && (int) $c['iat'] - self::SKEW > $now) {
            return 'Das Token wurde in der Zukunft ausgestellt.';
        }
        if (isset($c['nbf']) && (int) $c['nbf'] - self::SKEW > $now) {
            return 'Das Token ist noch nicht gültig.';
        }
        if ($nonce === '' || !hash_equals($nonce, (string) ($c['nonce'] ?? ''))) {
            return 'Ungültiger nonce.';
        }
        if (trim((string) ($c['sub'] ?? '')) === '') {
            return 'Das Token enthält keine Benutzerkennung (sub).';
        }
        return null;
    }

    public static function pkceChallenge(string $verifier): string
    {
        return self::b64uEncode(hash('sha256', $verifier, true));
    }

    /* ======================================================== Netzwerk */

    /** @return array{status:int, body:string, error:?string} */
    private static function http(string $method, string $url, ?array $form = null, ?array $json = null, array $headers = [], ?string $basic = null): array
    {
        if (!preg_match('#^(https://|http://(localhost|127\.0\.0\.1)(:\d+)?/)#i', $url)) {
            return ['status' => 0, 'body' => '', 'error' => 'Nur HTTPS-Adressen sind erlaubt.'];
        }
        $headers[] = 'Accept: application/json';
        $headers[] = 'User-Agent: VylarisOffers/' . APP_VERSION;
        $body = null;
        if ($form !== null) {
            $body = http_build_query($form);
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        } elseif ($json !== null) {
            $body = json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $headers[] = 'Content-Type: application/json';
        }
        if (!function_exists('curl_init')) {
            $ctx = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $headers) . ($basic ? "\r\nAuthorization: Basic " . base64_encode($basic) : ''), 'content' => $body ?? '', 'timeout' => 10, 'ignore_errors' => true, 'follow_location' => 0]]);
            $raw = @file_get_contents($url, false, $ctx);
            $status = 0;
            if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
                $status = (int) $m[1];
            }
            return ['status' => $status, 'body' => (string) $raw, 'error' => $raw === false ? 'Verbindung fehlgeschlagen' : null];
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers, CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        if ($basic !== null) {
            curl_setopt($ch, CURLOPT_USERPWD, $basic);
        }
        $raw = curl_exec($ch);
        $err = $raw === false ? curl_error($ch) : null;
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['status' => $status, 'body' => (string) $raw, 'error' => $err];
    }

    private static function endpointOk(mixed $u): bool
    {
        return is_string($u) && preg_match('#^(https://|http://(localhost|127\.0\.0\.1)(:\d+)?/)#i', $u) === 1;
    }

    /** Discovery-Dokument des Anbieters (zwischengespeichert). @return array{ok:bool, doc?:array, error?:string, issuer?:string} */
    public static function discover(string $input, bool $force = false): array
    {
        $issuer = self::normalizeIssuer($input);
        if ($issuer === null) {
            return ['ok' => false, 'error' => 'Bitte eine gültige https://-Adresse angeben, z. B. https://vylaris.tech.'];
        }
        $cache = json_decode((string) Settings::get('sso_discovery', ''), true);
        if (!$force && is_array($cache) && ($cache['issuer'] ?? '') === $issuer && time() - (int) ($cache['at'] ?? 0) < self::DISCOVERY_TTL && is_array($cache['doc'] ?? null)) {
            return ['ok' => true, 'doc' => $cache['doc'], 'issuer' => $issuer];
        }
        $r = self::http('GET', $issuer . '/.well-known/openid-configuration');
        if ($r['error'] || $r['status'] !== 200) {
            return ['ok' => false, 'error' => 'Konfiguration nicht abrufbar (' . ($r['error'] ?: 'HTTP ' . $r['status']) . '): ' . $issuer . '/.well-known/openid-configuration'];
        }
        $doc = json_decode($r['body'], true);
        if (!is_array($doc) || rtrim((string) ($doc['issuer'] ?? ''), '/') !== $issuer) {
            return ['ok' => false, 'error' => 'Die Antwort ist kein gültiges OpenID-Konfigurationsdokument (issuer stimmt nicht mit der Adresse überein).'];
        }
        foreach (['authorization_endpoint', 'token_endpoint', 'jwks_uri'] as $f) {
            if (!self::endpointOk($doc[$f] ?? null)) {
                return ['ok' => false, 'error' => "Im Konfigurationsdokument fehlt «$f» oder ist keine HTTPS-Adresse."];
            }
        }
        if (isset($doc['code_challenge_methods_supported']) && !in_array('S256', (array) $doc['code_challenge_methods_supported'], true)) {
            return ['ok' => false, 'error' => 'Der Anbieter unterstützt PKCE (S256) nicht.'];
        }
        Settings::set(['sso_discovery' => json_encode(['issuer' => $issuer, 'at' => time(), 'doc' => $doc], JSON_UNESCAPED_SLASHES)]);
        return ['ok' => true, 'doc' => $doc, 'issuer' => $issuer];
    }

    private static function jwks(array $doc, bool $force = false): array
    {
        $cache = json_decode((string) Settings::get('sso_jwks', ''), true);
        if (!$force && is_array($cache) && ($cache['uri'] ?? '') === $doc['jwks_uri'] && time() - (int) ($cache['at'] ?? 0) < self::DISCOVERY_TTL) {
            return (array) $cache['jwks'];
        }
        $r = self::http('GET', (string) $doc['jwks_uri']);
        $j = json_decode($r['body'], true);
        if ($r['error'] || $r['status'] !== 200 || !is_array($j) || empty($j['keys'])) {
            return is_array($cache['jwks'] ?? null) ? $cache['jwks'] : ['keys' => []];
        }
        Settings::set(['sso_jwks' => json_encode(['uri' => $doc['jwks_uri'], 'at' => time(), 'jwks' => $j], JSON_UNESCAPED_SLASHES)]);
        return $j;
    }

    /** Automatische Client-Registrierung (RFC 7591), falls angeboten. */
    public static function register(array $doc, string $redirectUri, string $name): array
    {
        if (!self::endpointOk($doc['registration_endpoint'] ?? null)) {
            return ['ok' => false, 'error' => 'Der Anbieter bietet keine automatische Registrierung an.'];
        }
        $r = self::http('POST', (string) $doc['registration_endpoint'], null, [
            'client_name' => $name, 'redirect_uris' => [$redirectUri], 'grant_types' => ['authorization_code'], 'response_types' => ['code'],
            'token_endpoint_auth_method' => 'client_secret_basic', 'application_type' => 'web', 'scope' => 'openid email profile',
        ]);
        $d = json_decode($r['body'], true);
        if ($r['error'] || !in_array($r['status'], [200, 201], true) || !is_array($d) || empty($d['client_id'])) {
            return ['ok' => false, 'error' => 'Registrierung abgelehnt (' . ($r['error'] ?: 'HTTP ' . $r['status']) . ').'];
        }
        return ['ok' => true, 'client_id' => (string) $d['client_id'], 'client_secret' => (string) ($d['client_secret'] ?? '')];
    }

    public static function redirectUri(): string
    {
        return url('admin/sso/callback');
    }

    /** Autorisierungs-URL erzeugen und Sitzungsdaten (state, nonce, PKCE) ablegen. */
    public static function authorizeUrl(array $doc, string $clientId, string $scopes): string
    {
        $state = bin2hex(random_bytes(16));
        $nonce = bin2hex(random_bytes(16));
        $verifier = self::b64uEncode(random_bytes(48));
        $_SESSION['sso'] = ['state' => $state, 'nonce' => $nonce, 'verifier' => $verifier, 'at' => time()];
        return $doc['authorization_endpoint'] . (str_contains((string) $doc['authorization_endpoint'], '?') ? '&' : '?') . http_build_query([
            'response_type' => 'code', 'client_id' => $clientId, 'redirect_uri' => self::redirectUri(), 'scope' => $scopes,
            'state' => $state, 'nonce' => $nonce, 'code_challenge' => self::pkceChallenge($verifier), 'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Code gegen Token tauschen und ID-Token prüfen.
     * @return array{ok:bool, claims?:array, error?:string}
     */
    public static function complete(array $doc, string $issuer, string $clientId, string $secret, string $code, array $sess): array
    {
        $form = ['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => self::redirectUri(), 'code_verifier' => $sess['verifier']];
        $methods = (array) ($doc['token_endpoint_auth_methods_supported'] ?? ['client_secret_basic']);
        $basic = null;
        if ($secret !== '') {
            if (in_array('client_secret_basic', $methods, true)) {
                $basic = rawurlencode($clientId) . ':' . rawurlencode($secret);
            } else {
                $form['client_id'] = $clientId;
                $form['client_secret'] = $secret;
            }
        } else {
            $form['client_id'] = $clientId;
        }
        $r = self::http('POST', (string) $doc['token_endpoint'], $form, null, [], $basic);
        $t = json_decode($r['body'], true);
        if ($r['error'] || $r['status'] !== 200 || !is_array($t) || empty($t['id_token'])) {
            return ['ok' => false, 'error' => 'Der Anbieter hat den Code nicht akzeptiert (' . ($r['error'] ?: (is_array($t) && isset($t['error']) ? (string) $t['error'] : 'HTTP ' . $r['status'])) . ').'];
        }
        $v = self::verifyJwt((string) $t['id_token'], self::jwks($doc));
        if ($v === null) {
            $v = self::verifyJwt((string) $t['id_token'], self::jwks($doc, true));   // Schlüsselwechsel beim Anbieter
        }
        if ($v === null) {
            return ['ok' => false, 'error' => 'Die Signatur des ID-Tokens ist ungültig.'];
        }
        if ($err = self::checkClaims($v[1], $issuer, $clientId, (string) $sess['nonce'], time())) {
            return ['ok' => false, 'error' => $err];
        }
        $claims = $v[1];
        if (empty($claims['email']) && self::endpointOk($doc['userinfo_endpoint'] ?? null) && !empty($t['access_token'])) {
            $u = self::http('GET', (string) $doc['userinfo_endpoint'], null, null, ['Authorization: Bearer ' . $t['access_token']]);
            $ui = json_decode($u['body'], true);
            if ($u['status'] === 200 && is_array($ui) && ($ui['sub'] ?? null) === $claims['sub']) {
                $claims += array_intersect_key($ui, array_flip(['email', 'email_verified', 'name', 'given_name', 'family_name', 'groups', 'roles']));
            }
        }
        return ['ok' => true, 'claims' => $claims];
    }
}
