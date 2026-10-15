<?php
/**
 * Globale Hilfsfunktionen.
 */
declare(strict_types=1);

/* ---------------------------------------------------------------- Konfiguration */

function app_config(?string $key = null, mixed $default = null): mixed
{
    $cfg = $GLOBALS['APP_CONFIG'] ?? [];
    if ($key === null) {
        return $cfg;
    }
    $cur = $cfg;
    foreach (explode('.', $key) as $part) {
        if (!is_array($cur) || !array_key_exists($part, $cur)) {
            return $default;
        }
        $cur = $cur[$part];
    }
    return $cur;
}

function base_path(): string
{
    $path = parse_url((string) app_config('app.base_url', ''), PHP_URL_PATH) ?: '';
    return rtrim($path, '/');
}

function url(string $path = ''): string
{
    return rtrim((string) app_config('app.base_url', ''), '/') . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = APP_ROOT . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? (string) filemtime($file) : APP_VERSION;
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
        return true;
    }
    return (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

/* ---------------------------------------------------------------- Session & Sicherheit */

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('OFMSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => (base_path() ?: '') . '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

function send_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    // Alle Ressourcen kommen vom eigenen Server (keine Drittanbieter); Logos dürfen per https eingebunden werden.
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; font-src 'self'; connect-src 'self'; form-action 'self'; frame-ancestors 'self'; base-uri 'self'; object-src 'none'");
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $sent = (string) ($_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if ($sent === '' || !hash_equals(csrf_token(), $sent)) {
        throw new HttpException(419, 'Die Sitzung ist abgelaufen oder das Formular ist ungültig. Bitte laden Sie die Seite neu und versuchen Sie es erneut.');
    }
}

function random_id(int $length = 24): string
{
    $alphabet = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $max = strlen($alphabet) - 1;
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $alphabet[random_int(0, $max)];
    }
    return $out;
}

/* ---------------------------------------------------------------- Ausgabe */

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Mehrzeiliger Text sicher mit Zeilenumbrüchen ausgeben. */
function nl(mixed $value): string
{
    return nl2br(e($value), false);
}

function json_attr(mixed $data): string
{
    return e(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP));
}

function money(mixed $amount, ?string $currency = null, bool $withCurrency = true): string
{
    $currency = $currency ?: (string) Settings::get('currency', 'CHF');
    $style = (string) Settings::get('number_style', $currency === 'CHF' ? 'ch' : 'de');
    [$dec, $thousands] = match ($style) {
        'ch'    => ['.', '’'],
        'en'    => ['.', ','],
        default => [',', '.'],
    };
    $formatted = number_format((float) $amount, 2, $dec, $thousands);
    return $withCurrency ? $currency . ' ' . $formatted : $formatted;
}

function qty(mixed $value): string
{
    $s = rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');
    return $s === '' ? '0' : $s;
}

function rate(mixed $value): string
{
    return qty($value) . ' %';
}

function fmt_date(?string $value): string
{
    if (!$value) {
        return '–';
    }
    $ts = strtotime($value);
    return $ts ? date('d.m.Y', $ts) : '–';
}

function fmt_datetime(?string $value): string
{
    if (!$value) {
        return '–';
    }
    $ts = strtotime($value);
    return $ts ? date('d.m.Y, H:i', $ts) : '–';
}

function time_ago(?string $value): string
{
    if (!$value) {
        return '–';
    }
    $diff = time() - (int) strtotime($value);
    return match (true) {
        $diff < 60     => 'gerade eben',
        $diff < 3600   => 'vor ' . (int) floor($diff / 60) . ' Min.',
        $diff < 86400  => 'vor ' . (int) floor($diff / 3600) . ' Std.',
        $diff < 604800 => 'vor ' . (int) floor($diff / 86400) . ' Tg.',
        default        => fmt_date($value),
    };
}

/* ---------------------------------------------------------------- Request */

function request_method(): string
{
    return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
}

function input(string $key, mixed $default = null): mixed
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function input_str(string $key, int $max = 255): string
{
    $v = input($key, '');
    $v = is_string($v) ? $v : '';
    return mb_substr(str_replace("\0", '', $v), 0, $max);
}

function input_int(string $key): ?int
{
    $v = input($key);
    return is_numeric($v) ? (int) $v : null;
}

/** Zahl aus Formular – akzeptiert 1'234.50 / 1.234,50 / 1234.5 */
function parse_number(mixed $value): float
{
    if (is_int($value) || is_float($value)) {
        return (float) $value;
    }
    $s = trim((string) $value);
    $s = str_replace(["’", "'", ' ', "\u{00A0}"], '', $s);
    if (str_contains($s, ',') && str_contains($s, '.')) {
        // Das letzte Trennzeichen ist das Dezimaltrennzeichen.
        if (strrpos($s, ',') > strrpos($s, '.')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } else {
            $s = str_replace(',', '', $s);
        }
    } else {
        $s = str_replace(',', '.', $s);
    }
    return is_numeric($s) ? (float) $s : 0.0;
}

/**
 * Suchbedingung über mehrere Spalten mit eindeutigen Platzhaltern
 * (native Prepared Statements erlauben keinen mehrfach verwendeten Namen).
 */
function search_clause(array $columns, string $term, array &$params): string
{
    $parts = [];
    foreach (array_values($columns) as $i => $col) {
        $params[":q$i"] = '%' . addcslashes($term, '%_\\') . '%';
        $parts[] = "$col LIKE :q$i";
    }
    return '(' . implode(' OR ', $parts) . ')';
}

function wants_json(): bool
{
    return str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
        || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
}

function valid_email(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

/* ---------------------------------------------------------------- Antworten */

function redirect(string $path, int $code = 302): never
{
    $target = preg_match('#^https?://#', $path) ? $path : url($path);
    header('Location: ' . $target, true, $code);
    exit;
}

function back(string $fallback = 'admin'): never
{
    $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    $base = rtrim((string) app_config('app.base_url'), '/');
    if ($ref !== '' && str_starts_with($ref, $base)) {
        header('Location: ' . $ref, true, 302);
        exit;
    }
    redirect($fallback);
}

function json_response(mixed $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

function view(string $template, array $data = [], ?string $layout = 'admin'): never
{
    echo View::render($template, $data, $layout);
    exit;
}

/* ---------------------------------------------------------------- Stammdaten */

function countries(): array
{
    return [
        // Schweizer Unternehmen: Kundenadressen in der Schweiz und den Nachbarländern
        'CH' => 'Schweiz', 'LI' => 'Liechtenstein', 'DE' => 'Deutschland', 'AT' => 'Österreich', 'FR' => 'Frankreich', 'IT' => 'Italien',
    ];
}

function country_name(?string $code): string
{
    return countries()[strtoupper((string) $code)] ?? (string) $code;
}

function customer_name(array $c): string
{
    $person = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
    return !empty($c['company']) ? $c['company'] : ($person ?: ($c['email'] ?? ''));
}

function customer_person(array $c): string
{
    return trim(($c['salutation'] ?? '') . ' ' . ($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
}

/** Adressblock als Zeilen. */
function address_lines(array $a): array
{
    $lines = [];
    if (!empty($a['company'])) {
        $lines[] = $a['company'];
    }
    $person = trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''));
    if ($person === '' && !empty($a['name'])) {
        $person = $a['name'];
    }
    if ($person !== '') {
        $lines[] = $person;
    }
    foreach (['street', 'address_extra'] as $k) {
        if (!empty($a[$k])) {
            $lines[] = $a[$k];
        }
    }
    $city = trim(($a['zip'] ?? '') . ' ' . ($a['city'] ?? ''));
    if ($city !== '') {
        $lines[] = $city;
    }
    if (!empty($a['country']) && strtoupper($a['country']) !== strtoupper((string) Settings::get('company_country', 'CH'))) {
        $lines[] = country_name($a['country']);
    }
    return $lines;
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $i = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $i .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $i ?: '?';
}


/** Kleines Personen-Label mit Initialen (für Zuständigkeiten). */
function person_chip(?string $name, string $empty = 'Nicht zugewiesen'): string
{
    $name = trim((string) $name);
    if ($name === '') {
        return '<span class="inline-flex items-center gap-1.5 whitespace-nowrap text-slate-400"><span class="grid size-6 place-items-center rounded-full border border-dashed border-slate-300 text-[10px]"><i class="bx bx-user"></i></span>' . e($empty) . '</span>';
    }
    $parts = preg_split('/\s+/', $name) ?: [$name];
    $ini = mb_strtoupper(mb_substr($parts[0], 0, 1) . (count($parts) > 1 ? mb_substr(end($parts), 0, 1) : ''));
    return '<span class="inline-flex items-center gap-1.5"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-brand-50 text-[10px] font-semibold text-brand-700">' . e($ini) . '</span><span class="truncate">' . e($name) . '</span></span>';
}
