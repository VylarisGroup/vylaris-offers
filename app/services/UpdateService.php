<?php
/**
 * Selbst-Update aus GitHub-Releases.
 *
 * Ablauf: neuestes Release lesen → ZIP laden (Asset, sonst Quellcode-ZIP) → Prüfsumme/Version/Pfade prüfen →
 * Sicherung (Datenbank + überschriebene Dateien) → Dateien atomar ersetzen → bei Fehler automatisch zurück.
 * Nie angetastet werden: app/config.php, install.lock, setup.php und storage/.
 */
declare(strict_types=1);

final class UpdateService
{
    public const API = 'https://api.github.com';
    /** Update-Quelle (fest einkompiliert). */
    public const DEFAULT_REPO = 'VylarisGroup/vylaris-offers';
    private const MAX_ZIP = 120 * 1024 * 1024;
    private const MAX_UNPACKED = 300 * 1024 * 1024;
    private const MAX_FILES = 6000;
    /** Pfade (relativ zum Installationsverzeichnis), die ein Update nie überschreibt. */
    private const PROTECTED = ['app/config.php', 'install.lock', 'setup.php'];
    private const SKIP_PREFIX = ['storage/', '.git/', '.github/', '__MACOSX/'];
    private const KEEP_BACKUPS = 3;

    /* ============================================================ Hilfen (rein, testbar) */

    /** «owner/repo» oder GitHub-URL → «owner/repo»; null bei ungültig. */
    public static function parseRepo(string $input): ?string
    {
        $s = trim($input);
        $s = preg_replace('#^(https?://)?(www\.)?github\.com/#i', '', $s);
        $s = preg_replace('#^git@github\.com:#i', '', (string) $s);
        $s = preg_replace('#\.git$#i', '', trim((string) $s, '/'));
        if (!preg_match('#^([A-Za-z0-9](?:[A-Za-z0-9-]{0,38}))/([A-Za-z0-9._-]{1,100})(?:/.*)?$#', (string) $s, $m)) {
            return null;
        }
        return $m[1] . '/' . $m[2];
    }

    /** Release-Tag → Versionsnummer («v1.2.0» → «1.2.0»); null bei ungültig. */
    public static function normalizeVersion(string $tag): ?string
    {
        $v = ltrim(trim($tag), 'vV');
        return preg_match('/^\d+(\.\d+){0,3}([-+][0-9A-Za-z.-]+)?$/', $v) ? $v : null;
    }

    public static function isNewer(string $candidate, string $current): bool
    {
        return version_compare($candidate, $current, '>');
    }

    public static function sameMajor(string $a, string $b): bool
    {
        return (int) $a === (int) $b;
    }

    /** Relativer Zielpfad für einen ZIP-Eintrag unterhalb des Wurzelverzeichnisses; null = überspringen/ungültig. */
    public static function targetPath(string $rel): ?string
    {
        if ($rel === '' || str_contains($rel, "\0") || str_contains($rel, '\\') || str_starts_with($rel, '/') || preg_match('#(^|/)\.\.(/|$)#', $rel) || preg_match('#^[A-Za-z]:#', $rel)) {
            return null;
        }
        if (str_ends_with($rel, '/')) {
            return null;
        }
        foreach (self::SKIP_PREFIX as $p) {
            if (str_starts_with($rel, $p)) {
                return null;
            }
        }
        if (in_array($rel, self::PROTECTED, true)) {
            return null;
        }
        return $rel;
    }

    /** Bestimmt das Präfix der Projektwurzel im ZIP (Ordner, der app/bootstrap.php und index.php enthält). */
    public static function detectRoot(array $names): ?string
    {
        $best = null;
        foreach ($names as $n) {
            if ($n === 'app/bootstrap.php' || str_ends_with($n, '/app/bootstrap.php')) {
                $prefix = substr($n, 0, -strlen('app/bootstrap.php'));
                if (substr_count($prefix, '/') > 3 || !in_array($prefix . 'index.php', $names, true)) {
                    continue;
                }
                if ($best === null || strlen($prefix) < strlen($best)) {
                    $best = $prefix;
                }
            }
        }
        return $best;
    }

    public static function versionInBootstrap(string $php): ?string
    {
        return preg_match("/define\\(\\s*'APP_VERSION'\\s*,\\s*'([^']+)'\\s*\\)/", $php, $m) ? $m[1] : null;
    }

    /* ============================================================ Einstellungen */

    public static function repo(): string
    {
        return self::DEFAULT_REPO;   // Quelle ist fest vorgegeben und nicht über die Oberfläche änderbar
    }

    public static function configured(): bool
    {
        return self::repo() !== '';
    }

    private static function api(): string
    {
        $b = rtrim((string) app_config('update.api', ''), '/');   // nur per app/config.php (Tests)
        return $b !== '' ? $b : self::API;
    }

    private static function dir(): string
    {
        $d = APP_ROOT . '/storage/update';
        if (!is_dir($d)) {
            @mkdir($d, 0775, true);
        }
        return $d;
    }

    public static function latest(): ?array
    {
        $j = json_decode((string) Settings::get('update_latest', ''), true);
        return is_array($j) && !empty($j['version']) ? $j : null;
    }

    public static function available(): bool
    {
        $l = self::latest();
        return $l !== null && self::configured() && self::isNewer($l['version'], APP_VERSION);
    }

    public static function badge(): ?string
    {
        return self::available() ? '●' : null;
    }

    /* ============================================================ Netzwerk */

    /**
     * HTTPS-Abruf mit manueller Weiterleitung: Das Token geht nur an den GitHub-API-Host, nie an Weiterleitungsziele.
     * @return array{status:int, body:string, error:?string, headers:array}
     */
    private static function http(string $url, array $headers = [], ?string $saveTo = null, bool $auth = true, int $redirects = 5): array
    {
        $token = (string) app_config('update.token', '');   // optional, nur per app/config.php (privates Repository)
        $apiHost = parse_url(self::api(), PHP_URL_HOST);
        for ($i = 0; $i <= $redirects; $i++) {
            if (!preg_match('#^(https://|http://(localhost|127\.0\.0\.1)(:\d+)?/)#i', $url)) {
                return ['status' => 0, 'body' => '', 'error' => 'Nur HTTPS-Adressen sind erlaubt.', 'headers' => []];
            }
            if (!function_exists('curl_init')) {
                return ['status' => 0, 'body' => '', 'error' => 'Die PHP-Erweiterung «curl» fehlt.', 'headers' => []];
            }
            $h = array_merge(['User-Agent: VylarisOffers/' . APP_VERSION, 'X-GitHub-Api-Version: 2022-11-28'], $headers);
            if ($auth && $token !== '' && parse_url($url, PHP_URL_HOST) === $apiHost) {
                $h[] = 'Authorization: Bearer ' . $token;
            }
            $ch = curl_init($url);
            $fh = null;
            $opts = [CURLOPT_HTTPHEADER => $h, CURLOPT_TIMEOUT => $saveTo ? 300 : 20, CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_HEADER => false];
            $respHeaders = [];
            $opts[CURLOPT_HEADERFUNCTION] = static function ($c, string $line) use (&$respHeaders): int {
                if (str_contains($line, ':')) {
                    [$k, $v] = explode(':', $line, 2);
                    $respHeaders[strtolower(trim($k))] = trim($v);
                }
                return strlen($line);
            };
            $written = 0;
            if ($saveTo !== null) {
                $fh = fopen($saveTo, 'wb');
                if (!$fh) {
                    return ['status' => 0, 'body' => '', 'error' => 'Zieldatei nicht beschreibbar.', 'headers' => []];
                }
                $opts[CURLOPT_WRITEFUNCTION] = static function ($c, string $data) use ($fh, &$written): int {
                    $written += strlen($data);
                    if ($written > self::MAX_ZIP) {
                        return 0;
                    }
                    return (int) fwrite($fh, $data);
                };
            } else {
                $opts[CURLOPT_RETURNTRANSFER] = true;
            }
            curl_setopt_array($ch, $opts);
            $raw = curl_exec($ch);
            $err = $raw === false ? curl_error($ch) : null;
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            if ($fh) {
                fclose($fh);
            }
            if ($status >= 300 && $status < 400 && !empty($respHeaders['location'])) {
                $loc = $respHeaders['location'];
                if (!preg_match('#^https?://#i', $loc)) {
                    $p = parse_url($url);
                    $loc = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '') . '/' . ltrim($loc, '/');
                }
                $url = $loc;
                continue;
            }
            return ['status' => $status, 'body' => $saveTo ? '' : (string) $raw, 'error' => $err, 'headers' => $respHeaders];
        }
        return ['status' => 0, 'body' => '', 'error' => 'Zu viele Weiterleitungen.', 'headers' => []];
    }

    /* ============================================================ Prüfen */

    /** Liest das neueste Release und legt es im Cache ab. @return array{ok:bool, message:string} */
    public static function check(): array
    {
        $repo = self::repo();
        if ($repo === '') {
            return ['ok' => false, 'message' => 'Es ist kein GitHub-Repository eingetragen.'];
        }
        $r = self::http(self::api() . '/repos/' . $repo . '/releases/latest', ['Accept: application/vnd.github+json']);
        Settings::set(['update_checked_at' => (string) time()]);
        if ($r['error'] !== null || $r['status'] === 0) {
            return ['ok' => false, 'message' => 'GitHub nicht erreichbar: ' . ($r['error'] ?? 'unbekannter Fehler')];
        }
        if ($r['status'] === 404) {
            return ['ok' => false, 'message' => 'Kein Release gefunden. Repository-Name prüfen, ein Release veröffentlichen – bei privaten Repositories zusätzlich ein Zugriffs-Token eintragen.'];
        }
        if ($r['status'] === 401 || $r['status'] === 403) {
            return ['ok' => false, 'message' => $r['status'] === 403 && ($r['headers']['x-ratelimit-remaining'] ?? '1') === '0'
                ? 'GitHub-Abfragelimit erreicht – später erneut versuchen (mit Token ist das Limit höher).'
                : 'Zugriff verweigert – das Zugriffs-Token ist ungültig, abgelaufen oder hat keine Leserechte (Contents: Read).'];
        }
        $j = json_decode($r['body'], true);
        if ($r['status'] !== 200 || !is_array($j)) {
            return ['ok' => false, 'message' => 'Unerwartete Antwort von GitHub (HTTP ' . $r['status'] . ').'];
        }
        $ver = self::normalizeVersion((string) ($j['tag_name'] ?? ''));
        if ($ver === null) {
            return ['ok' => false, 'message' => 'Der Release-Tag «' . (string) ($j['tag_name'] ?? '') . '» ist keine Versionsnummer (erwartet z. B. v1.2.0).'];
        }
        $zip = null;
        $sha = null;
        $assets = is_array($j['assets'] ?? null) ? $j['assets'] : [];
        foreach ($assets as $a) {
            $n = (string) ($a['name'] ?? '');
            if (preg_match('/\.zip$/i', $n) && ($zip === null || stripos($n, 'vylaris') !== false)) {
                $zip = ['name' => $n, 'url' => (string) ($a['url'] ?? ''), 'size' => (int) ($a['size'] ?? 0)];
            }
        }
        if ($zip !== null) {
            foreach ($assets as $a) {
                if (strcasecmp((string) ($a['name'] ?? ''), $zip['name'] . '.sha256') === 0) {
                    $sha = (string) ($a['url'] ?? '');
                }
            }
        }
        $info = [
            'tag' => (string) $j['tag_name'], 'version' => $ver, 'name' => (string) ($j['name'] ?? ''), 'notes' => mb_substr((string) ($j['body'] ?? ''), 0, 6000),
            'published' => (string) ($j['published_at'] ?? ''), 'url' => (string) ($j['html_url'] ?? ''),
            'zip_url' => $zip['url'] ?? (string) ($j['zipball_url'] ?? ''), 'zip_asset' => $zip !== null, 'zip_name' => $zip['name'] ?? '', 'sha_url' => $sha,
            'repo' => $repo, 'checked' => time(),
        ];
        Settings::set(['update_latest' => json_encode($info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        return ['ok' => true, 'message' => self::isNewer($ver, APP_VERSION)
            ? 'Neue Version ' . $ver . ' verfügbar (installiert: ' . APP_VERSION . ').'
            : 'Sie verwenden die aktuelle Version (' . APP_VERSION . ').'];
    }

    public static function checkIfDue(): void
    {
        if (!self::configured() || time() - (int) Settings::get('update_checked_at', '0') < 6 * 3600) {
            return;
        }
        try {
            self::check();
        } catch (Throwable) {
            Settings::set(['update_checked_at' => (string) time()]);
        }
    }

    /* ============================================================ Installieren */

    /**
     * Lädt das neueste Release und installiert es.
     * @return array{ok:bool, message:string, version?:string}
     */
    public static function install(string $by = 'system'): array
    {
        if (!class_exists('ZipArchive')) {
            return ['ok' => false, 'message' => 'Die PHP-Erweiterung «zip» fehlt (Plesk → PHP-Einstellungen).'];
        }
        $lock = fopen(self::dir() . '/update.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            return ['ok' => false, 'message' => 'Es läuft bereits ein Update.'];
        }
        @set_time_limit(600);
        $tmp = null;
        try {
            $c = self::check();
            if (!$c['ok']) {
                return $c;
            }
            $info = self::latest();
            if ($info === null || !self::isNewer($info['version'], APP_VERSION)) {
                return ['ok' => false, 'message' => 'Es ist keine neuere Version verfügbar.'];
            }
            $tmp = self::dir() . '/dl-' . bin2hex(random_bytes(6)) . '.zip';
            $accept = $info['zip_asset'] ? 'Accept: application/octet-stream' : 'Accept: application/vnd.github+json';
            $d = self::http($info['zip_url'], [$accept], $tmp);
            if ($d['status'] !== 200 || $d['error'] !== null || !is_file($tmp) || filesize($tmp) < 1000) {
                return ['ok' => false, 'message' => 'Der Download ist fehlgeschlagen (HTTP ' . $d['status'] . ($d['error'] ? ', ' . $d['error'] : '') . ').'];
            }
            if (!empty($info['sha_url'])) {
                $s = self::http($info['sha_url'], ['Accept: application/octet-stream']);
                if ($s['status'] !== 200 || !preg_match('/\b([a-f0-9]{64})\b/i', $s['body'], $m)) {
                    return ['ok' => false, 'message' => 'Die Prüfsummen-Datei des Releases konnte nicht gelesen werden – Update abgebrochen.'];
                }
                if (!hash_equals(strtolower($m[1]), hash_file('sha256', $tmp))) {
                    return ['ok' => false, 'message' => 'Die Prüfsumme (SHA-256) des Downloads stimmt nicht – Update abgebrochen.'];
                }
            }
            $res = self::installZip($tmp, $info['version'], $by);
            return $res;
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Update fehlgeschlagen: ' . $e->getMessage()];
        } finally {
            if ($tmp !== null) {
                @unlink($tmp);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Prüft ein ZIP und ersetzt die Programmdateien. $root = Installationsverzeichnis (für Tests überschreibbar).
     * @return array{ok:bool, message:string, version?:string}
     */
    public static function installZip(string $file, string $expectVersion, string $by = 'system', ?string $root = null, bool $backupDb = true): array
    {
        $root = rtrim($root ?? APP_ROOT, '/');
        $zip = new ZipArchive();
        if ($zip->open($file) !== true) {
            return ['ok' => false, 'message' => 'Das heruntergeladene Archiv ist kein gültiges ZIP.'];
        }
        $names = [];
        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $st = $zip->statIndex($i);
            $names[$i] = (string) $st['name'];
            $total += (int) $st['size'];
        }
        if ($zip->numFiles > self::MAX_FILES || $total > self::MAX_UNPACKED) {
            $zip->close();
            return ['ok' => false, 'message' => 'Das Archiv ist unplausibel gross – abgebrochen.'];
        }
        $prefix = self::detectRoot($names);
        if ($prefix === null) {
            $zip->close();
            return ['ok' => false, 'message' => 'Das Archiv enthält kein VylarisOffers-Programmverzeichnis (app/bootstrap.php und index.php fehlen).'];
        }
        $boot = $zip->getFromName($prefix . 'app/bootstrap.php');
        $zipVer = self::versionInBootstrap((string) $boot);
        if ($zipVer === null || $zipVer !== $expectVersion) {
            $zip->close();
            return ['ok' => false, 'message' => 'Die Version im Archiv (' . ($zipVer ?? '?') . ') passt nicht zum Release «' . $expectVersion . '». In app/bootstrap.php APP_VERSION anpassen und das Release neu erstellen.'];
        }
        // Zu schreibende Dateien bestimmen
        $plan = [];
        foreach ($names as $i => $n) {
            if (!str_starts_with($n, $prefix) || str_ends_with($n, '/')) {
                continue;
            }
            $rel = self::targetPath(substr($n, strlen($prefix)));
            if ($rel !== null) {
                $plan[$rel] = $i;
            }
        }
        if (!isset($plan['index.php'], $plan['app/bootstrap.php'])) {
            $zip->close();
            return ['ok' => false, 'message' => 'Das Archiv ist unvollständig.'];
        }
        // Schreibrechte vorab prüfen
        foreach (array_keys($plan) as $rel) {
            $p = $root . '/' . $rel;
            $dir = is_file($p) ? $p : dirname($p);
            while (!file_exists($dir) && $dir !== $root && $dir !== '/') {
                $dir = dirname($dir);
            }
            if (!is_writable($dir)) {
                $zip->close();
                return ['ok' => false, 'message' => 'Keine Schreibrechte für «' . $rel . '» – Dateirechte in Plesk prüfen (der PHP-Benutzer muss das Verzeichnis beschreiben dürfen).'];
            }
        }
        // Sicherung: Datenbank + zu überschreibende Dateien
        $stamp = date('Ymd-His');
        $bdir = self::dir();
        $backup = $bdir . '/code-' . APP_VERSION . '-' . $stamp . '.zip';
        $bz = new ZipArchive();
        if ($bz->open($backup, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $zip->close();
            return ['ok' => false, 'message' => 'Sicherung konnte nicht erstellt werden (storage/update nicht beschreibbar).'];
        }
        $created = [];
        foreach (array_keys($plan) as $rel) {
            if (is_file($root . '/' . $rel)) {
                $bz->addFile($root . '/' . $rel, 'files/' . $rel);
            } else {
                $created[] = $rel;
            }
        }
        $bz->addFromString('manifest.json', json_encode(['from' => APP_VERSION, 'to' => $expectVersion, 'created' => $created, 'at' => date('c')]));
        if (!$bz->close()) {
            $zip->close();
            return ['ok' => false, 'message' => 'Sicherung konnte nicht geschrieben werden.'];
        }
        if ($backupDb) {
            try {
                BackupService::dumpTo($bdir . '/db-' . APP_VERSION . '-' . $stamp . '.sql.gz');
            } catch (Throwable $e) {
                $zip->close();
                @unlink($backup);
                return ['ok' => false, 'message' => 'Datenbank-Sicherung fehlgeschlagen: ' . $e->getMessage()];
            }
        }
        // Dateien ersetzen (Einstiegsdateien zuletzt)
        $order = array_keys($plan);
        usort($order, static fn ($a, $b) => (in_array($a, ['app/bootstrap.php', 'index.php'], true) <=> in_array($b, ['app/bootstrap.php', 'index.php'], true)));
        $written = [];
        try {
            foreach ($order as $rel) {
                $data = $zip->getFromIndex($plan[$rel]);
                if ($data === false) {
                    throw new RuntimeException('«' . $rel . '» konnte nicht gelesen werden.');
                }
                $dest = $root . '/' . $rel;
                if (!is_dir(dirname($dest)) && !@mkdir(dirname($dest), 0775, true) && !is_dir(dirname($dest))) {
                    throw new RuntimeException('Verzeichnis für «' . $rel . '» nicht erstellbar.');
                }
                $t = $dest . '.upd' . bin2hex(random_bytes(3));
                if (file_put_contents($t, $data) === false) {
                    @unlink($t);
                    throw new RuntimeException('«' . $rel . '» nicht schreibbar.');
                }
                if (is_file($dest)) {
                    @chmod($t, fileperms($dest) & 0777);
                }
                if (!@rename($t, $dest)) {
                    @unlink($t);
                    throw new RuntimeException('«' . $rel . '» konnte nicht ersetzt werden.');
                }
                $written[] = $rel;
            }
        } catch (Throwable $e) {
            $zip->close();
            self::restore($backup, $root);
            return ['ok' => false, 'message' => 'Update fehlgeschlagen und zurückgesetzt: ' . $e->getMessage()];
        }
        $zip->close();
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        self::prune();
        Settings::set(['update_last' => json_encode(['from' => APP_VERSION, 'to' => $expectVersion, 'at' => time(), 'by' => $by, 'files' => count($written)]), 'update_error' => '']);
        return ['ok' => true, 'message' => 'Version ' . $expectVersion . ' wurde installiert (' . count($written) . ' Dateien).', 'version' => $expectVersion];
    }

    /** Stellt Dateien aus einer Code-Sicherung wieder her und entfernt neu angelegte Dateien. */
    public static function restore(string $backup, ?string $root = null): bool
    {
        $root = rtrim($root ?? APP_ROOT, '/');
        $z = new ZipArchive();
        if ($z->open($backup) !== true) {
            return false;
        }
        $man = json_decode((string) $z->getFromName('manifest.json'), true) ?: [];
        $ok = true;
        for ($i = 0; $i < $z->numFiles; $i++) {
            $n = (string) $z->getNameIndex($i);
            if (!str_starts_with($n, 'files/') || str_ends_with($n, '/')) {
                continue;
            }
            $rel = self::targetPath(substr($n, 6));
            if ($rel === null) {
                continue;
            }
            $data = $z->getFromIndex($i);
            $t = $root . '/' . $rel . '.rst' . bin2hex(random_bytes(3));
            if ($data === false || file_put_contents($t, $data) === false || !@rename($t, $root . '/' . $rel)) {
                @unlink($t);
                $ok = false;
            }
        }
        foreach ((array) ($man['created'] ?? []) as $rel) {
            if (is_string($rel) && self::targetPath($rel) !== null && is_file($root . '/' . $rel)) {
                @unlink($root . '/' . $rel);
            }
        }
        $z->close();
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        return $ok;
    }

    /** Neueste Code-Sicherungen (Pfad, Version, Zeit). */
    public static function backups(): array
    {
        $out = [];
        foreach (glob(self::dir() . '/code-*.zip') ?: [] as $f) {
            if (preg_match('/code-(.+)-(\d{8}-\d{6})\.zip$/', $f, $m)) {
                $out[] = ['file' => $f, 'version' => $m[1], 'stamp' => $m[2], 'time' => filemtime($f), 'db' => is_file(dirname($f) . '/db-' . $m[1] . '-' . $m[2] . '.sql.gz')];
            }
        }
        usort($out, static fn ($a, $b) => $b['time'] <=> $a['time']);
        return $out;
    }

    public static function rollback(string $by): array
    {
        $b = self::backups()[0] ?? null;
        if ($b === null) {
            return ['ok' => false, 'message' => 'Keine Sicherung vorhanden.'];
        }
        if (!self::restore($b['file'])) {
            return ['ok' => false, 'message' => 'Wiederherstellung unvollständig – Dateirechte prüfen.'];
        }
        Settings::set(['update_last' => json_encode(['from' => APP_VERSION, 'to' => $b['version'], 'at' => time(), 'by' => $by, 'rollback' => true])]);
        return ['ok' => true, 'message' => 'Programmdateien der Version ' . $b['version'] . ' wurden wiederhergestellt. Die Datenbank bleibt unverändert (Neuere Tabellenspalten schaden älteren Versionen nicht).'];
    }

    private static function prune(): void
    {
        $b = self::backups();
        foreach (array_slice($b, self::KEEP_BACKUPS) as $old) {
            @unlink($old['file']);
            @unlink(dirname($old['file']) . '/db-' . $old['version'] . '-' . $old['stamp'] . '.sql.gz');
        }
        foreach (glob(self::dir() . '/dl-*.zip') ?: [] as $f) {
            if (filemtime($f) < time() - 3600) {
                @unlink($f);
            }
        }
    }

    /* ============================================================ Cronjob */

    /** Prüft und installiert bei aktivierter Automatik (nur gleiche Hauptversion). */
    public static function cron(): string
    {
        if (!self::configured()) {
            return 'Update: kein Repository eingetragen';
        }
        $c = self::check();
        if (!$c['ok']) {
            Settings::set(['update_error' => $c['message']]);
            return 'Update: ' . $c['message'];
        }
        if (!self::available()) {
            Settings::set(['update_error' => '']);
            return 'Update: aktuell (' . APP_VERSION . ')';
        }
        $l = self::latest();
        if (Settings::get('update_auto', '0') !== '1') {
            return 'Update: Version ' . $l['version'] . ' verfügbar (Automatik aus)';
        }
        if (!self::sameMajor($l['version'], APP_VERSION)) {
            return 'Update: Version ' . $l['version'] . ' ist ein Hauptversions-Sprung – bitte manuell installieren';
        }
        $r = self::install('Automatik');
        Settings::set(['update_error' => $r['ok'] ? '' : $r['message']]);
        Audit::log($r['ok'] ? 'update_installed' : 'update_failed', $r['message'], null, null, null);
        return 'Update: ' . $r['message'];
    }
}
