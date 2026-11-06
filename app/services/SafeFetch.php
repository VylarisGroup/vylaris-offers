<?php
/**
 * Holt öffentliche Webseiten und Bilder mit SSRF-Schutz: nur http(s), nur öffentliche IPs
 * (die aufgelöste IP wird an cURL gebunden), Weiterleitungen werden einzeln geprüft,
 * Grösse und Dauer sind begrenzt.
 */
declare(strict_types=1);

final class SafeFetch
{
    /** @return array{0: string|null, 1: string|null, 2: string|null} [Inhalt, Content-Type, Fehlermeldung] */
    public static function get(string $url, int $maxBytes = 2_500_000, int $timeout = 15, string $accept = 'text/html,application/xhtml+xml,*/*;q=0.8'): array
    {
        $http11 = false;
        for ($hop = 0; $hop < 6; $hop++) {
            $err = self::validate($url, $ip);
            if ($err) {
                return [null, null, $err];
            }
            $p = parse_url($url);
            $port = $p['port'] ?? (($p['scheme'] ?? '') === 'https' ? 443 : 80);
            $body = '';
            $headers = [];
            $tooBig = false;
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_CONNECTTIMEOUT => 6,
                CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_RESOLVE        => [$p['host'] . ':' . $port . ':' . $ip],
                CURLOPT_ENCODING       => '',
                CURLOPT_HTTP_VERSION   => $http11 ? CURL_HTTP_VERSION_1_1 : CURL_HTTP_VERSION_NONE,
                CURLOPT_HTTPHEADER     => ['Accept: ' . $accept, 'Accept-Language: de,en;q=0.8'],
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; ' . Brand::userAgent() . ')',
                CURLOPT_HEADERFUNCTION => static function ($ch, string $h) use (&$headers): int {
                    if (str_contains($h, ':')) {
                        [$k, $v] = explode(':', $h, 2);
                        $headers[strtolower(trim($k))] = trim($v);
                    }
                    return strlen($h);
                },
                CURLOPT_WRITEFUNCTION  => static function ($ch, string $d) use (&$body, &$tooBig, $maxBytes): int {
                    $body .= $d;
                    if (strlen($body) > $maxBytes) {
                        $tooBig = true;
                        return 0;
                    }
                    return strlen($d);
                },
            ]);
            curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $cno = curl_errno($ch);
            $cerr = $cno ? curl_error($ch) : null;
            curl_close($ch);
            if (in_array($cno, [16, 92], true) && !$http11) {   // HTTP/2-Fehler: einmal mit HTTP/1.1 wiederholen
                $http11 = true;
                continue;
            }
            if ($tooBig) {
                return [null, null, 'Die Datei ist zu gross.'];
            }
            if ($code >= 300 && $code < 400 && !empty($headers['location'])) {
                $url = self::absolute($headers['location'], $url);
                continue;
            }
            if ($cerr) {
                return [null, null, 'Die Seite ist nicht erreichbar (' . $cerr . ').'];
            }
            if (in_array($code, [401, 403, 429, 503], true)) {
                return [null, null, 'Die Seite blockiert automatische Abrufe (HTTP ' . $code . ').'];
            }
            if ($code >= 400 || $code === 0) {
                return [null, null, 'Die Seite antwortet mit HTTP ' . $code . '.'];
            }
            return [$body, strtolower($headers['content-type'] ?? ''), null];
        }
        return [null, null, 'Zu viele Weiterleitungen.'];
    }

    /** Prüft die URL; liefert eine Fehlermeldung oder null und setzt die zu verwendende öffentliche IP. */
    public static function validate(string $url, ?string &$ip = null): ?string
    {
        $p = parse_url($url);
        if (!$p || empty($p['host']) || !in_array($p['scheme'] ?? '', ['http', 'https'], true) || isset($p['user']) || isset($p['pass'])) {
            return 'Bitte eine gültige http(s)-Adresse angeben.';
        }
        $host = trim($p['host'], '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : array_merge(
            gethostbynamel($host) ?: [],
            array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6')
        );
        if (!$ips) {
            return 'Die Adresse ist nicht auflösbar.';
        }
        $allowPrivate = (bool) app_config('ai.allow_private', false);
        foreach ($ips as $i) {
            if (!$allowPrivate && !filter_var($i, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return 'Interne Adressen sind nicht erlaubt.';
            }
        }
        $ip = str_contains($ips[0], ':') ? '[' . $ips[0] . ']' : $ips[0];
        return null;
    }

    /** Macht eine (relative) URL absolut. */
    public static function absolute(string $href, string $base): string
    {
        $href = trim(html_entity_decode($href));
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        $b = parse_url($base);
        $origin = ($b['scheme'] ?? 'https') . '://' . ($b['host'] ?? '') . (isset($b['port']) ? ':' . $b['port'] : '');
        if (str_starts_with($href, '//')) {
            return ($b['scheme'] ?? 'https') . ':' . $href;
        }
        if (str_starts_with($href, '/')) {
            return $origin . $href;
        }
        $dir = preg_replace('#/[^/]*$#', '/', $b['path'] ?? '/');
        return $origin . $dir . $href;
    }
}
