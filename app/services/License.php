<?php
/**
 * Lizenzsystem (TRS License, Prüf-API v1 – https://licence.vylaris.ch).
 *
 *  - Aktivierung/Prüfung/Heartbeat/Abmeldung/Offline-Lizenz gemäss API-Dokumentation 1.3
 *  - Jede Serverantwort wird mit dem fest hinterlegten öffentlichen Ed25519-Schlüssel geprüft (libsodium);
 *    ausgewertet werden nur die Daten aus `payload`, nie die unsignierten Felder.
 *  - Die Bewertung (`evaluate`) ist rein funktional und prüft die gespeicherten Beweise bei jeder Anfrage erneut.
 *  - Ohne gültige Lizenz arbeitet das System im Lesemodus (Ansehen/Export/Datenschutz bleiben möglich).
 *  - Funktionen (Scopes) stammen aus `features` der Lizenz.
 */
declare(strict_types=1);

final class License
{
    public const DEFAULT_SERVER = 'https://licence.vylaris.ch';
    public const DEFAULT_PRODUCT = 'vylarisoffers';
    private const SPKI_ED25519_PREFIX = '302a300506032b6570032100';
    private const FRESH_SECONDS = 86400;      // Beweis gilt 24 h als „aktuell“
    private const REFRESH_SECONDS = 21600;    // normale Prüfung alle 6 h
    private const RETRY_SECONDS = 900;        // nach Fehlschlag frühestens nach 15 min
    private const MAX_SKEW = 300;

    /** Scope-Kennung => [Bezeichnung, Beschreibung, abhängig von] */
    public const FEATURES = [
        'invoices'      => ['Rechnungen', 'Rechnungen, QR-Rechnung, Gutschriften, Skonto, Mahnungen, MWST-Prüfung', []],
        'subscriptions' => ['Abos', 'Wiederkehrende Rechnungen (Abos)', ['invoices']],
        'payments'      => ['Online-Zahlung', 'Zahlung per Mollie/Stripe auf der Rechnungsseite', ['invoices']],
        'reports'       => ['Berichte & Buchhaltung', 'Berichte, MWST-Abrechnung, Buchungsjournal, CSV-Export', []],
        'api'           => ['API', 'Lesende REST-API mit API-Tokens', []],
        'webhooks'      => ['Webhooks', 'Ereignisse an externe Systeme melden', []],
        'portal'        => ['Kundenportal', 'Kundenportal mit Anmeldelink', []],
        'website'       => ['Website & Anfragen', 'Website-Baukasten, Anfrageformular, Anfragenverwaltung', []],
        'sso'           => ['Single Sign-On', 'Anmeldung per OpenID Connect (z. B. Vylaris-Login)', []],
        'signatures'    => ['Digitale Unterschrift', 'Unterschrift bei der Offertenannahme (offen/strikt)', []],
    ];

    /** Routen => benötigte Funktion (nur Administration und öffentliche Eingänge; bestehende Rechnungen bleiben für Kunden sichtbar). */
    private const ROUTE_FEATURES = [
        '#^/admin/invoices(/|$)#'                        => 'invoices',
        '#^/admin/orders/[^/]+/invoice$#'                => 'invoices',
        '#^/admin/subscriptions(/|$)#'                   => 'subscriptions',
        '#^/admin/orders/[^/]+/subscription$#'           => 'subscriptions',
        '#^/r/[^/]+/pay/[^/]+$#'                         => 'payments',
        '#^/admin/reports(/|$)#'                         => 'reports',
        '#^/admin/integrations/(journal|accounts)#'      => 'reports',
        '#^/admin/integrations/tokens#'                  => 'api',
        '#^/admin/integrations/webhooks#'                => 'webhooks',
        '#^/api/v1/(?!ping$)#'                           => 'api',
        '#^/portal(/|$)#'                                => 'portal',
        '#^/admin/(website|inquiries)(/|$)#'             => 'website',
        '#^/anfrage$#'                                   => 'website',
        '#^/admin/sso(/|$)#'                             => 'sso',
    ];

    /** Schreibende Bereiche, die im Lesemodus gesperrt sind. */
    private const WRITE_AREAS = '#^/admin/(offers|orders|invoices|subscriptions|customers|areas|templates|taxes|shipping|website|integrations|attachments|inquiries)(/|$)#';
    private const WRITE_EXEMPT = '#^/admin/customers/[^/]+/(privacy-export|restrict|anonymize)$#';
    private const PUBLIC_WRITES = '#^/(o/[^/]+/(accept|code)|anfrage)$#';

    private static ?array $eval = null;

    /* ======================================================== Reine Funktionen (testbar) */

    public static function pemToRaw(string $pem): ?string
    {
        if (!preg_match('/-----BEGIN PUBLIC KEY-----(.+?)-----END PUBLIC KEY-----/s', $pem, $m)) {
            return null;
        }
        $der = base64_decode(preg_replace('/\s+/', '', $m[1]), true);
        if ($der === false || strlen($der) !== 44 || bin2hex(substr($der, 0, 12)) !== self::SPKI_ED25519_PREFIX) {
            return null;
        }
        return substr($der, 12);
    }

    public static function verify(string $payload, string $signatureB64, string $pem): bool
    {
        $raw = self::pemToRaw($pem);
        $sig = base64_decode($signatureB64, true);
        if ($raw === null || $sig === false || strlen($sig) !== 64 || !function_exists('sodium_crypto_sign_verify_detached')) {
            return false;
        }
        try {
            return sodium_crypto_sign_verify_detached($sig, $payload, $raw);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Bewertet den gespeicherten Zustand.
     * @param array $s  ['key'=>string, 'pem'=>string, 'hwid'=>string, 'proof'=>['payload','signature']|null,
     *                   'offline'=>['payload','signature']|null, 'net_error'=>bool, 'grace_days'=>int]
     * @return array{status:string, mode:string, features:array, plan:?string, trial:bool, expires:?int, message:string, checked:?int, valid_until:?int}
     */
    public static function evaluate(array $s, int $now): array
    {
        $out = ['status' => 'unlicensed', 'mode' => 'readonly', 'features' => [], 'plan' => null, 'product_name' => null, 'trial' => false, 'expires' => null,
                'message' => 'Es ist kein Lizenzschlüssel hinterlegt.', 'checked' => null, 'valid_until' => null, 'devices' => null, 'max_devices' => null];
        if (trim((string) ($s['key'] ?? '')) === '') {
            return $out;
        }
        $out['status'] = 'unverified';
        $out['message'] = 'Die Lizenz wurde noch nicht erfolgreich geprüft.';
        if (self::pemToRaw((string) ($s['pem'] ?? '')) === null) {
            $out['message'] = 'Der öffentliche Schlüssel des Lizenzservers fehlt oder ist ungültig (Lizenz → Erweitert).';
            return $out;
        }
        $proof = $s['proof'] ?? null;
        if (!is_array($proof) || !self::verify((string) ($proof['payload'] ?? ''), (string) ($proof['signature'] ?? ''), (string) $s['pem'])) {
            return $out;
        }
        $d = json_decode((string) $proof['payload'], true);
        if (!is_array($d) || !isset($d['ts'])) {
            return $out;
        }
        $lic = is_array($d['license'] ?? null) ? $d['license'] : [];
        $out['features'] = array_values(array_filter(array_map('strval', (array) ($lic['features'] ?? []))));
        $out['plan'] = $lic['plan'] ?? null;
        $out['product_name'] = $lic['productName'] ?? null;
        $out['trial'] = !empty($lic['trial']);
        $out['devices'] = $lic['devices'] ?? null;
        $out['max_devices'] = $lic['maxDevices'] ?? null;
        $exp = !empty($lic['expiresAt']) ? strtotime((string) $lic['expiresAt']) : null;
        $out['expires'] = $exp ?: null;
        $out['checked'] = (int) $d['ts'];
        $code = (string) ($d['code'] ?? '');

        if (empty($d['valid']) || $code !== 'OK') {
            if ($code !== 'OK') {
                $out['features'] = in_array($code, ['EXPIRED', 'BLOCKED'], true) ? $out['features'] : [];
            }
            $out['status'] = strtolower($code ?: 'invalid');
            $out['message'] = self::codeMessage($code);
            return $out;
        }
        if ($exp !== null && $exp < $now) {
            $out['status'] = 'expired';
            $out['message'] = self::codeMessage('EXPIRED');
            return $out;
        }

        $age = $now - (int) $d['ts'];
        if (empty($s['net_error']) && $age <= self::FRESH_SECONDS) {
            return ['status' => 'active', 'mode' => 'full', 'message' => 'Lizenz gültig.'] + $out;
        }
        // Server nicht erreichbar oder lange nicht geprüft: Offline-Lizenz, danach Karenzfrist.
        $off = $s['offline'] ?? null;
        if (is_array($off) && self::verify((string) ($off['payload'] ?? ''), (string) ($off['signature'] ?? ''), (string) $s['pem'])) {
            $o = json_decode((string) $off['payload'], true);
            $until = is_array($o) && !empty($o['validUntil']) ? strtotime((string) $o['validUntil']) : false;
            $licExp = is_array($o) && !empty($o['licenseExpiresAt']) ? strtotime((string) $o['licenseExpiresAt']) : null;
            if (is_array($o) && ($o['hwid'] ?? null) === ($s['hwid'] ?? '') && ($o['key'] ?? null) === ($s['key'] ?? '')
                && $until && $until > $now && ($licExp === null || $licExp > $now)) {
                $out['valid_until'] = $until;
                return ['status' => 'offline', 'mode' => 'full', 'message' => 'Der Lizenzserver ist nicht erreichbar – es gilt die Offline-Lizenz bis ' . date('d.m.Y H:i', $until) . '.'] + $out;
            }
        }
        $grace = max(0, (int) ($s['grace_days'] ?? 3));
        if ($age <= $grace * 86400) {
            $out['valid_until'] = (int) $d['ts'] + $grace * 86400;
            return ['status' => 'grace', 'mode' => 'full', 'message' => 'Der Lizenzserver ist nicht erreichbar – Karenzfrist bis ' . date('d.m.Y H:i', $out['valid_until']) . '.'] + $out;
        }
        $out['status'] = 'unreachable';
        $out['message'] = 'Der Lizenzserver ist seit längerer Zeit nicht erreichbar und die Karenzfrist ist abgelaufen.';
        return $out;
    }

    public static function codeMessage(string $code): string
    {
        return match ($code) {
            'OK'             => 'Lizenz gültig.',
            'NOT_FOUND'      => 'Der Lizenzschlüssel ist unbekannt.',
            'BLOCKED'        => 'Die Lizenz wurde gesperrt.',
            'EXPIRED'        => 'Die Lizenz ist abgelaufen.',
            'WRONG_PRODUCT'  => 'Der Schlüssel gehört zu einem anderen Produkt.',
            'HWID_REQUIRED'  => 'Geräte-ID fehlt.',
            'NOT_ACTIVATED'  => 'Diese Installation ist noch nicht aktiviert (oder die Domain hat sich geändert).',
            'DEVICE_LIMIT'   => 'Alle Geräteplätze der Lizenz sind belegt. Setzen Sie ein Gerät im Lizenzportal zurück.',
            default          => 'Die Lizenz ist ungültig.',
        };
    }

    /** Funktion inkl. Abhängigkeiten verfügbar? (rein) */
    public static function featureIn(array $features, string $feature): bool
    {
        if ($feature === 'core') {
            return true;
        }
        if (!isset(self::FEATURES[$feature]) || !in_array($feature, $features, true)) {
            return false;
        }
        foreach (self::FEATURES[$feature][2] as $dep) {
            if (!in_array($dep, $features, true)) {
                return false;
            }
        }
        return true;
    }

    /** Welche Funktion braucht dieser Pfad? */
    public static function featureForPath(string $path): ?string
    {
        foreach (self::ROUTE_FEATURES as $re => $f) {
            if (preg_match($re, $path)) {
                return $f;
            }
        }
        return null;
    }

    /** Entscheidung für eine Anfrage: null = erlaubt, sonst ['reason'=>'feature'|'readonly', 'feature'=>?string]. */
    public static function decide(string $method, string $path, array $ev): ?array
    {
        if (preg_match('#^/(admin/license|admin/login|admin/logout|admin/magic|admin/auth/|admin/2fa|admin/profile|health|webhooks/|brand/|site/img/)#', $path)) {
            return null;
        }
        $f = self::featureForPath($path);
        if ($f !== null && !self::featureIn($ev['features'], $f)) {
            return ['reason' => 'feature', 'feature' => $f];
        }
        $write = $method !== 'GET' && $method !== 'HEAD';
        if ($write && $ev['mode'] === 'readonly'
            && ((preg_match(self::WRITE_AREAS, $path) && !preg_match(self::WRITE_EXEMPT, $path)) || preg_match(self::PUBLIC_WRITES, $path))) {
            return ['reason' => 'readonly', 'feature' => null];
        }
        return null;
    }

    /* ======================================================== Zustand / Einstellungen */

    public static function key(): string
    {
        return trim((string) Settings::get('license_key', ''));
    }

    public static function server(): string
    {
        return rtrim((string) (app_config('license.server') ?: self::DEFAULT_SERVER), '/');   // nur per app/config.php änderbar (Tests)
    }

    public static function product(): string
    {
        return self::DEFAULT_PRODUCT;
    }

    /** Fest eingebauter öffentlicher Ed25519-Schlüssel des Lizenzservers (nicht über die Oberfläche änderbar). */
    public const DEFAULT_PUBLIC_KEY = "-----BEGIN PUBLIC KEY-----\nMCowBQYDK2VwAyEAhV7L176abx5a05XciRbOOeFj6wkCnNiDrwEXw7KceiU=\n-----END PUBLIC KEY-----";

    public static function publicKey(): string
    {
        $k = trim((string) app_config('license.public_key', ''));   // nur per app/config.php (Tests)
        return $k !== '' ? $k : self::DEFAULT_PUBLIC_KEY;
    }

    private static function instanceId(): string
    {
        $id = (string) Settings::get('license_instance', '');
        if ($id === '') {
            $id = bin2hex(random_bytes(8));
            Settings::set(['license_instance' => $id]);
        }
        return $id;
    }

    /** Geräte-ID = Domain + Installations-ID. Im Web aktuell berechnet, im Cronjob (keine Domain) die zuletzt aktivierte. */
    public static function hwid(): string
    {
        $host = PHP_SAPI === 'cli' ? '' : strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
        if ($host === '') {
            $stored = (string) Settings::get('license_hwid', '');
            if ($stored !== '') {
                return $stored;
            }
            $host = strtolower((string) (parse_url((string) app_config('app.base_url', ''), PHP_URL_HOST) ?: 'unbekannt'));
        }
        return mb_substr($host . '#' . self::instanceId(), 0, 128);
    }

    public static function deviceName(): string
    {
        return mb_substr('VylarisOffers – ' . (string) Settings::get('company_name', ''), 0, 80);
    }

    private static function state(): array
    {
        $s = json_decode((string) Settings::get('license_state', ''), true);
        return is_array($s) ? $s : [];
    }

    private static function saveState(array $s): void
    {
        Settings::set(['license_state' => json_encode($s, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
        self::$eval = null;
    }

    /** Bewertung für diese Anfrage (ohne Netzwerkzugriff). */
    public static function status(): array
    {
        if (self::$eval === null) {
            try {
                $st = self::state();
                self::$eval = self::evaluate([
                    'key' => self::key(), 'pem' => self::publicKey(), 'hwid' => self::hwid(),
                    'proof' => $st['proof'] ?? null, 'offline' => $st['offline'] ?? null,
                    'net_error' => !empty($st['net_error']),
                    'grace_days' => (int) (Settings::get('license_grace_days') ?: 3),
                ], time());
            } catch (Throwable) {
                self::$eval = self::evaluate(['key' => ''], time());   // z. B. vor der Installation
            }
        }
        return self::$eval;
    }

    public static function has(string $feature): bool
    {
        return self::featureIn(self::status()['features'], $feature);
    }

    public static function readonly(): bool
    {
        return self::status()['mode'] === 'readonly';
    }

    /** Aktive Scopes (nur für die Anzeige). */
    public static function scopes(): array
    {
        $out = [];
        foreach (self::FEATURES as $id => [$label, $desc, $deps]) {
            $out[$id] = ['label' => $label, 'desc' => $desc, 'on' => self::has($id)];
        }
        return $out;
    }

    /* ======================================================== Netzwerk */

    /** @return array{ok:bool, data:?array, error:?string} */
    private static function request(string $method, string $path, ?array $body = null): array
    {
        $url = self::server() . $path;
        $json = $body === null ? null : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = ['Accept: application/json', 'User-Agent: VylarisOffers/' . APP_VERSION];
        if ($json !== null) {
            $headers[] = 'Content-Type: application/json';
        }
        $raw = false;
        $err = null;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => $json, CURLOPT_FOLLOWLOCATION => false, CURLOPT_MAXREDIRS => 0,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS]);
            $raw = curl_exec($ch);
            if ($raw === false) {
                $err = curl_error($ch);
            } elseif (($code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE)) >= 500 || $code === 429) {
                $err = "HTTP $code";
                $raw = false;
            }
            curl_close($ch);
        } else {
            $ctx = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $headers), 'content' => $json ?? '', 'timeout' => 8, 'ignore_errors' => true, 'follow_location' => 0]]);
            $raw = @file_get_contents($url, false, $ctx);
            $err = $raw === false ? 'Verbindung fehlgeschlagen' : null;
        }
        if ($raw === false) {
            return ['ok' => false, 'data' => null, 'error' => $err ?: 'Verbindung fehlgeschlagen'];
        }
        return ['ok' => true, 'data' => json_decode((string) $raw, true), 'raw' => (string) $raw, 'error' => null];
    }

    /** Prüft Antwort + Signatur und liefert die signierten Daten. */
    private static function signed(array $res): ?array
    {
        $a = $res['data'] ?? null;
        if (!is_array($a) || !isset($a['payload'], $a['signature']) || !self::verify((string) $a['payload'], (string) $a['signature'], self::publicKey())) {
            return null;
        }
        $d = json_decode((string) $a['payload'], true);
        return is_array($d) && isset($d['ts']) && abs(time() - (int) $d['ts']) <= self::MAX_SKEW ? ['d' => $d, 'proof' => ['payload' => (string) $a['payload'], 'signature' => (string) $a['signature']]] : null;
    }

    /**
     * Ruft die Prüf-API auf und speichert den signierten Beweis.
     * @param string $action validate|heartbeat|activate|deactivate
     */
    public static function call(string $action, bool $remember = true): array
    {
        $key = self::key();
        if ($key === '') {
            return ['ok' => false, 'code' => 'NO_KEY', 'message' => 'Kein Lizenzschlüssel hinterlegt.'];
        }
        if (self::pemToRaw(self::publicKey()) === null) {
            return ['ok' => false, 'code' => 'NO_PUBLIC_KEY', 'message' => 'Der öffentliche Schlüssel des Lizenzservers fehlt oder ist ungültig.'];
        }
        $hwid = self::hwid();
        $res = self::request('POST', '/api/v1/' . $action, ['key' => $key, 'hwid' => $hwid, 'name' => self::deviceName(), 'product' => self::product()]);
        $st = self::state();
        $st['last_try'] = time();
        if (!$res['ok']) {
            $st['net_error'] = true;
            $st['error'] = $res['error'];
            self::saveState($st);
            return ['ok' => false, 'code' => 'NETWORK', 'message' => 'Lizenzserver nicht erreichbar: ' . $res['error']];
        }
        $sig = self::signed($res);
        if ($sig === null) {
            $st['net_error'] = true;
            $st['error'] = 'Ungültige oder veraltete Signatur';
            self::saveState($st);
            return ['ok' => false, 'code' => 'BAD_SIGNATURE', 'message' => 'Die Antwort des Lizenzservers hat keine gültige Signatur (öffentlicher Schlüssel prüfen).'];
        }
        $d = $sig['d'];
        $st['net_error'] = false;
        $st['error'] = null;
        if ($action !== 'deactivate' && $remember) {
            $st['proof'] = $sig['proof'];
        }
        if ($action === 'activate' && !empty($d['valid'])) {
            Settings::set(['license_hwid' => $hwid]);
        }
        if ($action === 'deactivate' && !empty($d['valid'])) {
            $st['proof'] = null;
            $st['offline'] = null;
        }
        self::saveState($st);
        return ['ok' => !empty($d['valid']), 'code' => (string) ($d['code'] ?? ''), 'message' => self::codeMessage((string) ($d['code'] ?? '')), 'data' => $d];
    }

    public static function fetchOffline(): array
    {
        $res = self::request('POST', '/api/v1/offline', ['key' => self::key(), 'hwid' => self::hwid(), 'product' => self::product()]);
        if (!$res['ok']) {
            return ['ok' => false, 'message' => 'Lizenzserver nicht erreichbar: ' . $res['error']];
        }
        $a = $res['data'];
        if (!is_array($a) || empty($a['valid'])) {
            return ['ok' => false, 'message' => self::codeMessage((string) ($a['code'] ?? ''))];
        }
        if (!self::verify((string) ($a['payload'] ?? ''), (string) ($a['signature'] ?? ''), self::publicKey())) {
            return ['ok' => false, 'message' => 'Ungültige Signatur der Offline-Lizenz.'];
        }
        $st = self::state();
        $st['offline'] = ['payload' => (string) $a['payload'], 'signature' => (string) $a['signature']];
        $st['offline_at'] = time();
        self::saveState($st);
        return ['ok' => true, 'message' => 'Offline-Lizenz gespeichert.'];
    }

    /** Vollständige Aktivierung: aktivieren, Offline-Lizenz holen. */
    public static function activate(): array
    {
        $r = self::call('activate');
        if ($r['ok']) {
            self::fetchOffline();
        }
        return $r;
    }

    public static function check(): array
    {
        $r = self::call('heartbeat');
        if (!$r['ok'] && $r['code'] === 'NOT_ACTIVATED') {
            $st = self::state();
            if (time() - (int) ($st['auto_activate'] ?? 0) > 3600) {
                $st['auto_activate'] = time();
                self::saveState($st);
                $r = self::activate();
            }
        } elseif ($r['ok']) {
            $st = self::state();
            if (time() - (int) ($st['offline_at'] ?? 0) > 86400 * 3) {
                self::fetchOffline();
            }
        }
        return $r;
    }

    /** Wird bei Admin-Aufrufen und im Cronjob aufgerufen; prüft höchstens alle 6 Stunden (nach Fehler alle 15 Minuten). */
    public static function refreshIfDue(bool $force = false): void
    {
        if (self::key() === '' || self::pemToRaw(self::publicKey()) === null) {
            return;
        }
        $st = self::state();
        $last = (int) ($st['last_try'] ?? 0);
        $wait = !empty($st['net_error']) || self::status()['status'] !== 'active' ? self::RETRY_SECONDS : self::REFRESH_SECONDS;
        if (!$force && time() - $last < $wait) {
            return;
        }
        // Sperre gegen parallele Anfragen
        $st['last_try'] = time();
        self::saveState($st);
        self::check();
    }

    public static function deactivate(): array
    {
        $r = self::call('deactivate');
        if ($r['ok']) {
            Settings::set(['license_hwid' => '']);
        }
        return $r;
    }

    public static function setKey(string $key): void
    {
        $key = strtoupper(trim($key));
        if ($key !== self::key()) {
            Settings::set(['license_key' => $key, 'license_state' => '']);
            self::$eval = null;
        }
    }

    /** Zentrale Prüfung vor dem Routing. */
    public static function gate(string $method, string $path): void
    {
        $api = str_starts_with($path, '/api/');
        if (str_starts_with($path, '/admin') && $method === 'GET' && !str_starts_with($path, '/admin/license') && Auth::user()) {
            self::refreshIfDue();
            if (!str_starts_with($path, '/admin/update')) {
                UpdateService::checkIfDue();
            }
        }
        $d = self::decide($method, $path, self::status());
        if ($d === null) {
            return;
        }
        if ($d['reason'] === 'feature') {
            $label = self::FEATURES[$d['feature']][0] ?? $d['feature'];
            $msg = 'Die Funktion «' . $label . '» ist in Ihrer Lizenz nicht enthalten.';
        } else {
            $msg = self::status()['message'] . ' Das System ist im Lesemodus – Änderungen sind erst mit gültiger Lizenz möglich.';
        }
        if ($api) {
            json_response(['error' => 'license', 'message' => $msg], 403);
        }
        if (str_starts_with($path, '/admin')) {
            if ($method === 'GET') {
                throw new HttpException(403, $msg);
            }
            flash('error', $msg);
            redirect('admin/license');
        }
        throw new HttpException(403, $d['reason'] === 'readonly'
            ? 'Diese Aktion ist derzeit nicht verfügbar. Bitte kontaktieren Sie den Anbieter.' : 'Diese Funktion ist nicht verfügbar.');
    }
}
