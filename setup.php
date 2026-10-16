<?php
/**
 * Installationsassistent – Offerten- & Auftragsmanagement
 *
 * Führt durch: Systemprüfung → Datenbank → Firma → Dokumente & Zahlung (inkl. Mollie/Stripe) → E-Mail → Administrator & Team → Datenschutz & Betrieb → Auftritt & Startseite → Installation.
 * Nach erfolgreicher Installation wird der Assistent automatisch gesperrt
 * (app/config.php + storage/install.lock). Löschen Sie diese Datei danach vom Server.
 */
declare(strict_types=1);

define('APP_ROOT', __DIR__);
define('APP_DIR', __DIR__ . '/app');
define('APP_VERSION', '1.5.0');

$configFile = APP_DIR . '/config.php';
$lockFile = APP_ROOT . '/storage/install.lock';

spl_autoload_register(static function (string $class): void {
    foreach (['lib', 'services'] as $dir) {
        $f = APP_DIR . "/$dir/$class.php";
        if (is_file($f)) {
            require $f;
            return;
        }
    }
});
require APP_DIR . '/lib/helpers.php';

error_reporting(E_ALL);
ini_set('display_errors', '0');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

session_name('OFMSETUP');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'secure' => is_https()]);
session_start();

$locked = is_file($configFile) || is_file($lockFile);

/* ------------------------------------------------------------------ Hilfen */

function s_e(mixed $v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function s_csrf(): string
{
    $_SESSION['setup_csrf'] ??= bin2hex(random_bytes(32));
    return $_SESSION['setup_csrf'];
}

function s_check_csrf(): void
{
    if (!hash_equals(s_csrf(), (string) ($_POST['_csrf'] ?? ''))) {
        http_response_code(419);
        exit('Ungültiges Formular. Bitte Seite neu laden.');
    }
}

function s_val(string $group, string $key, string $default = ''): string
{
    return (string) ($_SESSION['setup'][$group][$key] ?? $default);
}

function s_detect_base_url(): string
{
    $scheme = is_https() ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return $scheme . '://' . $host . $dir;
}

function s_db_connect(array $db): PDO
{
    $GLOBALS['APP_CONFIG']['db'] = $db;
    return DB::connect($db);
}

function s_requirements(): array
{
    $checks = [
        ['PHP-Version ≥ 8.1 (aktuell ' . PHP_VERSION . ')', version_compare(PHP_VERSION, '8.1.0', '>=')],
        ['PDO MySQL', extension_loaded('pdo_mysql')],
        ['OpenSSL (Verschlüsselung, SMTP/TLS)', extension_loaded('openssl')],
        ['Mbstring', extension_loaded('mbstring')],
        ['JSON', extension_loaded('json')],
        ['Sodium (Prüfung der Lizenzsignatur)', function_exists('sodium_crypto_sign_verify_detached')],
        ['Ordner app/ beschreibbar (für config.php)', is_writable(APP_DIR)],
        ['Ordner storage/ beschreibbar', is_writable(APP_ROOT . '/storage')],
        ['storage/logs/ beschreibbar', is_dir(APP_ROOT . '/storage/logs') ? is_writable(APP_ROOT . '/storage/logs') : is_writable(APP_ROOT . '/storage')],
    ];
    return $checks;
}

/** Empfohlene (nicht zwingende) Voraussetzungen: [Bezeichnung, erfüllt, Hinweis]. */
function s_recommendations(): array
{
    return [
        ['HTTPS aktiv', is_https(), 'Für Online-Zahlung und Passkeys erforderlich – in Plesk unter „SSL/TLS-Zertifikate“ (Let’s Encrypt) aktivieren und „Permanente 301-Weiterleitung auf HTTPS“ einschalten.'],
        ['PHP-Erweiterung cURL', function_exists('curl_init'), 'Wird für Mollie/Stripe benötigt (Online-Zahlung).'],
        ['PHP-Erweiterung ZipArchive', class_exists('ZipArchive'), 'Für das Backup inklusive Anhängen.'],
        ['Statische Dateien (assets/vendor) vorhanden', is_file(APP_ROOT . '/assets/vendor/tailwind/tailwindcss-browser-4.3.3.js'), 'Ordner assets/ vollständig hochladen, sonst erscheint die Oberfläche ungestylt.'],
        ['PHP-Funktion exec/CLI für Cronjob (php-cli)', defined('PHP_BINARY') && PHP_BINARY !== '', 'Für den täglichen Cronjob (cron.php).'],
    ];
}

/** Verbindungstest zu Mollie/Stripe (nur Lesezugriff). */
function s_test_provider(string $provider, string $key): array
{
    if (!function_exists('curl_init')) {
        return [false, 'PHP-Erweiterung cURL fehlt.'];
    }
    if ($provider === 'mollie') {
        if (!preg_match('/^(live|test)_[A-Za-z0-9]{20,}$/', $key)) {
            return [false, 'Der Mollie-API-Key muss mit live_ oder test_ beginnen.'];
        }
        $url = 'https://api.mollie.com/v2/methods';
    } else {
        if (!preg_match('/^(sk|rk)_(live|test)_[A-Za-z0-9_]{20,}$/', $key)) {
            return [false, 'Der Stripe-Schlüssel muss mit sk_live_/sk_test_ (oder rk_…) beginnen.'];
        }
        $url = 'https://api.stripe.com/v1/balance';
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 12, CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Accept: application/json']]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_errno($ch) ? curl_error($ch) : '';
    curl_close($ch);
    if ($raw === false) {
        return [false, 'Keine Verbindung: ' . $err];
    }
    if ($code === 401 || $code === 403) {
        return [false, 'Der Schlüssel wurde abgelehnt (HTTP ' . $code . ').'];
    }
    if ($code < 200 || $code >= 300) {
        return [false, 'Unerwartete Antwort (HTTP ' . $code . ').'];
    }
    return [true, 'Verbindung erfolgreich – ' . (str_contains($key, 'test_') ? 'Testmodus' : 'Live-Modus')];
}

/* ------------------------------------------------------------------ Aktionen */

$step = (int) ($_SESSION['setup_step'] ?? 1);
$error = null;
$notice = null;
$done = $_SESSION['setup_done'] ?? null;

if ($locked && !$done) {
    $step = 0;
}

if (!$locked && $_SERVER['REQUEST_METHOD'] === 'POST') {
    s_check_csrf();
    $action = (string) ($_POST['action'] ?? '');

    // AJAX: Datenbankverbindung testen
    if ($action === 'test_db') {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $pdo = s_db_connect([
                'host' => trim((string) $_POST['db_host']), 'port' => (int) ($_POST['db_port'] ?: 3306),
                'name' => trim((string) $_POST['db_name']), 'user' => trim((string) $_POST['db_user']), 'pass' => (string) $_POST['db_pass'],
            ]);
            $ver = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
            $tables = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
            echo json_encode(['ok' => true, 'message' => "Verbindung erfolgreich – Server $ver" . ($tables ? ", $tables Tabelle(n) vorhanden" : ', Datenbank ist leer')], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'message' => 'Verbindung fehlgeschlagen: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    // AJAX: Zahlungsanbieter testen
    if ($action === 'test_pay') {
        header('Content-Type: application/json; charset=utf-8');
        $prov = (string) ($_POST['provider'] ?? '') === 'stripe' ? 'stripe' : 'mollie';
        $key = trim((string) ($_POST[$prov === 'stripe' ? 'stripe_secret_key' : 'mollie_api_key'] ?? ''));
        if ($key === '') {
            $key = s_val('pay', $prov === 'stripe' ? 'stripe_secret_key' : 'mollie_api_key');
        }
        [$ok, $msg] = s_test_provider($prov, $key);
        echo json_encode(['ok' => $ok, 'message' => $msg], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'back') {
        $_SESSION['setup_step'] = max(1, $step - 1);
        header('Location: setup.php');
        exit;
    }

    try {
        switch ($step) {
            case 1:
                foreach (s_requirements() as [$label, $ok]) {
                    if (!$ok) {
                        throw new RuntimeException("Voraussetzung nicht erfüllt: $label");
                    }
                }
                $_SESSION['setup_step'] = 2;
                break;

            case 2:
                $db = [
                    'host' => trim((string) $_POST['db_host']) ?: 'localhost',
                    'port' => (int) ($_POST['db_port'] ?: 3306),
                    'name' => trim((string) $_POST['db_name']),
                    'user' => trim((string) $_POST['db_user']),
                    'pass' => (string) $_POST['db_pass'],
                ];
                $_SESSION['setup']['db'] = $db;
                if ($db['name'] === '' || $db['user'] === '') {
                    throw new RuntimeException('Bitte Datenbankname und Benutzer angeben.');
                }
                s_db_connect($db)->query('SELECT 1');
                $_SESSION['setup_step'] = 3;
                break;

            case 3:
                $app = [];
                foreach (['base_url', 'company_name', 'brand_name', 'brand_color', 'company_email', 'company_street', 'company_zip', 'company_city', 'company_phone', 'company_vat_id', 'currency', 'mwst_method', 'mwst_saldo_rate', 'mwst_period'] as $k) {
                    $app[$k] = trim((string) ($_POST[$k] ?? ''));
                }
                // Das System ist auf die Schweiz ausgelegt: Land, Zahlenformat und Zeitzone sind fest.
                $app['company_country'] = 'CH';
                $app['number_style'] = 'ch';
                $app['timezone'] = 'Europe/Zurich';
                $app['mwst_liable'] = !empty($_POST['mwst_liable']) ? '1' : '0';
                $app['base_url'] = rtrim($app['base_url'], '/');
                $app['currency'] = strtoupper($app['currency'] ?: 'CHF');
                $_SESSION['setup']['app'] = $app;
                if (!preg_match('#^https?://[^\s/]+(/[^\s]*)?$#', $app['base_url'])) {
                    throw new RuntimeException('Bitte eine gültige Basis-URL angeben, z. B. https://offerten.ihredomain.ch');
                }
                if ($app['company_name'] === '') {
                    throw new RuntimeException('Bitte den Firmennamen angeben.');
                }
                $app['brand_name'] = mb_substr($app['brand_name'], 0, 60);
                $app['brand_color'] = $app['brand_color'] === '' ? '#4f46e5' : $app['brand_color'];
                if (!preg_match('/^#[0-9a-fA-F]{6}$/', $app['brand_color'])) {
                    throw new RuntimeException('Die Markenfarbe muss als Hex-Wert angegeben werden, z. B. #4f46e5.');
                }
                if (!valid_email($app['company_email'])) {
                    throw new RuntimeException('Bitte eine gültige Firmen-E-Mail-Adresse angeben.');
                }
                if (!in_array($app['currency'], ['CHF', 'EUR'], true)) {
                    throw new RuntimeException('Die Währung muss CHF oder EUR sein.');
                }
                if ($app['company_vat_id'] !== '') {
                    if (!preg_match('/^CHE/i', $app['company_vat_id']) || !TaxService::validUid($app['company_vat_id'])) {
                        throw new RuntimeException('Die UID ist ungültig (Format CHE-123.456.789, mit gültiger Prüfziffer).');
                    }
                    $app['company_vat_id'] = TaxService::formatUid($app['company_vat_id'], false);
                } elseif ($app['mwst_liable'] === '1') {
                    throw new RuntimeException('MWST-pflichtige Unternehmen müssen ihre UID/MWST-Nummer angeben – oder entfernen Sie den Haken bei „MWST-pflichtig“.');
                }
                $app['mwst_method'] = $app['mwst_method'] === 'received' ? 'received' : 'agreed';
                $app['mwst_period'] = in_array($app['mwst_period'] ?? '', ['quarter', 'semester', 'year'], true) ? $app['mwst_period'] : 'quarter';
                if ($app['mwst_saldo_rate'] !== '') {
                    $r = str_replace(',', '.', $app['mwst_saldo_rate']);
                    if (!is_numeric($r) || (float) $r <= 0 || (float) $r > 10) {
                        throw new RuntimeException('Der Saldosteuersatz muss zwischen 0.1 und 10 % liegen.');
                    }
                    $app['mwst_saldo_rate'] = rtrim(rtrim(number_format((float) $r, 1, '.', ''), '0'), '.');
                }
                $_SESSION['setup']['app'] = $app;
                $lic = [];
                foreach (['license_key'] as $k) {
                    $lic[$k] = trim(str_replace("\r", '', (string) ($_POST[$k] ?? '')));
                }
                $lic['license_key'] = strtoupper($lic['license_key']);
                $_SESSION['setup']['lic'] = $lic;
                if ($lic['license_key'] !== '' && !preg_match('/^[A-Z0-9][A-Z0-9_-]{5,63}$/', $lic['license_key'])) {
                    throw new RuntimeException('Der Lizenzschlüssel hat ein ungültiges Format.');
                }
                $sso = ['sso_issuer' => trim((string) ($_POST['sso_issuer'] ?? '')), 'sso_auto_create' => !empty($_POST['sso_auto_create']) ? '1' : '0'];
                if ($sso['sso_issuer'] !== '') {
                    $norm = Oidc::normalizeIssuer($sso['sso_issuer']);
                    if ($norm === null) {
                        $_SESSION['setup']['sso'] = $sso;
                        throw new RuntimeException('Die Adresse des Single-Sign-On-Anbieters ist ungültig (https://… erforderlich).');
                    }
                    $sso['sso_issuer'] = $norm;
                }
                $_SESSION['setup']['sso'] = $sso;
                $_SESSION['setup_step'] = 4;
                break;

            case 4:
                $doc = [];
                foreach (['offer_prefix', 'order_prefix', 'invoice_prefix', 'offer_validity_days', 'invoice_payment_days', 'invoice_iban', 'invoice_payment_info'] as $k) {
                    $doc[$k] = trim((string) ($_POST[$k] ?? ''));
                }
                foreach (['offer_prefix' => 'OF', 'order_prefix' => 'AU', 'invoice_prefix' => 'RE'] as $k => $def) {
                    $doc[$k] = preg_replace('/[^A-Za-z0-9]/', '', $doc[$k]) ?: $def;
                    $doc[$k] = strtoupper(substr($doc[$k], 0, 8));
                }
                $doc['offer_validity_days'] = (string) max(0, min(365, (int) ($doc['offer_validity_days'] === '' ? 30 : $doc['offer_validity_days'])));
                $doc['invoice_payment_days'] = (string) max(0, min(365, (int) ($doc['invoice_payment_days'] === '' ? 30 : $doc['invoice_payment_days'])));
                $doc['invoice_iban'] = strtoupper(preg_replace('/\s+/', '', $doc['invoice_iban']) ?? '');
                $_SESSION['setup']['doc'] = $doc;

                $pay = ['pay_mollie_enabled' => !empty($_POST['pay_mollie_enabled']) ? '1' : '0', 'pay_stripe_enabled' => !empty($_POST['pay_stripe_enabled']) ? '1' : '0'];
                $rules = [
                    'mollie_api_key'        => ['/^(live|test)_[A-Za-z0-9]{20,}$/', 'Der Mollie-API-Key muss mit live_ oder test_ beginnen.'],
                    'stripe_secret_key'     => ['/^(sk|rk)_(live|test)_[A-Za-z0-9_]{20,}$/', 'Der Stripe-Schlüssel muss mit sk_live_/sk_test_ (oder rk_…) beginnen.'],
                    'stripe_webhook_secret' => ['/^whsec_[A-Za-z0-9]{16,}$/', 'Das Stripe-Webhook-Secret muss mit whsec_ beginnen.'],
                ];
                foreach ($rules as $k => [$re, $msg]) {
                    $v = trim((string) ($_POST[$k] ?? ''));
                    if ($v !== '' && !preg_match($re, $v)) {
                        $pay[$k] = s_val('pay', $k);   // ungültige Eingabe nicht übernehmen
                        $_SESSION['setup']['pay'] = $pay;
                        throw new RuntimeException($msg);
                    }
                    $pay[$k] = $v !== '' ? $v : s_val('pay', $k);
                }
                $_SESSION['setup']['pay'] = $pay;
                if ($doc['invoice_iban'] !== '' && !SwissQr::validIban($doc['invoice_iban'])) {
                    throw new RuntimeException('Die IBAN ist ungültig (erwartet: CH oder LI, 21 Zeichen, gültige Prüfziffer).');
                }
                if ($pay['pay_mollie_enabled'] === '1' && $pay['mollie_api_key'] === '') {
                    throw new RuntimeException('Für Mollie wird ein API-Key benötigt – oder Mollie deaktivieren.');
                }
                if ($pay['pay_stripe_enabled'] === '1' && $pay['stripe_secret_key'] === '') {
                    throw new RuntimeException('Für Stripe wird ein geheimer Schlüssel benötigt – oder Stripe deaktivieren.');
                }
                $_SESSION['setup_step'] = 5;
                break;

            case 5:
                $mail = [];
                foreach (['mail_transport', 'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_user', 'smtp_pass', 'mail_from_email', 'mail_from_name', 'notify_email'] as $k) {
                    $mail[$k] = trim((string) ($_POST[$k] ?? ''));
                }
                if ($mail['smtp_pass'] === '' && s_val('mail', 'smtp_pass') !== '') {
                    $mail['smtp_pass'] = s_val('mail', 'smtp_pass');
                }
                $mail['mail_transport'] = in_array($mail['mail_transport'], ['smtp', 'phpmail', 'log'], true) ? $mail['mail_transport'] : 'smtp';
                $mail['smtp_encryption'] = in_array($mail['smtp_encryption'], ['tls', 'ssl', 'none'], true) ? $mail['smtp_encryption'] : 'tls';
                $_SESSION['setup']['mail'] = $mail;
                if ($mail['mail_from_email'] !== '' && !valid_email($mail['mail_from_email'])) {
                    throw new RuntimeException('Die Absenderadresse ist ungültig.');
                }
                if ($mail['mail_transport'] === 'smtp' && $mail['smtp_host'] === '' && empty($_POST['skip'])) {
                    throw new RuntimeException('Bitte einen SMTP-Server angeben oder „Später konfigurieren“ wählen.');
                }
                if (!empty($_POST['do_test']) && !empty($_POST['test_to'])) {
                    $to = trim((string) $_POST['test_to']);
                    $GLOBALS['APP_CONFIG'] = ['app' => ['base_url' => s_val('app', 'base_url'), 'key' => str_repeat('x', 32)], 'db' => $_SESSION['setup']['db']];
                    $mailer = new Mailer([
                        'transport' => $mail['mail_transport'], 'host' => $mail['smtp_host'], 'port' => (int) $mail['smtp_port'],
                        'encryption' => $mail['smtp_encryption'], 'user' => $mail['smtp_user'], 'pass' => $mail['smtp_pass'],
                        'from_email' => $mail['mail_from_email'] ?: s_val('app', 'company_email'), 'from_name' => $mail['mail_from_name'] ?: s_val('app', 'company_name'),
                    ]);
                    try {
                        $mailer->send($to, 'Test – Installationsassistent', '<p>Der E-Mail-Versand funktioniert.</p>', 'Der E-Mail-Versand funktioniert.', 'setup');
                        $notice = "Test-E-Mail an $to erfolgreich übergeben.";
                    } catch (Throwable $e) {
                        throw new RuntimeException('Test fehlgeschlagen: ' . $e->getMessage());
                    }
                    break; // auf Schritt 5 bleiben
                }
                if ($mail['notify_email'] !== '' && !valid_email($mail['notify_email'])) {
                    throw new RuntimeException('Die Benachrichtigungsadresse ist ungültig.');
                }
                $_SESSION['setup_step'] = 6;
                break;

            case 6:
                $admin = ['name' => trim((string) $_POST['admin_name']), 'email' => mb_strtolower(trim((string) $_POST['admin_email']))];
                $_SESSION['setup']['admin'] = $admin;
                $pw = (string) $_POST['admin_password'];
                if ($admin['name'] === '' || !valid_email($admin['email'])) {
                    throw new RuntimeException('Bitte Name und gültige E-Mail-Adresse angeben.');
                }
                if ($pw !== (string) $_POST['admin_password2']) {
                    throw new RuntimeException('Die Passwörter stimmen nicht überein.');
                }
                if ($p = Auth::passwordProblem($pw)) {
                    throw new RuntimeException($p);
                }
                $_SESSION['setup']['admin']['hash'] = Auth::hashPassword($pw);
                $team = [];
                $names = (array) ($_POST['team_name'] ?? []);
                $emails = (array) ($_POST['team_email'] ?? []);
                $roles = (array) ($_POST['team_role'] ?? []);
                $seen = [$admin['email']];
                foreach (array_slice(array_keys($emails), 0, 5) as $i) {
                    $n = trim((string) ($names[$i] ?? ''));
                    $m = mb_strtolower(trim((string) ($emails[$i] ?? '')));
                    if ($n === '' && $m === '') {
                        continue;
                    }
                    if ($n === '' || !valid_email($m)) {
                        $_SESSION['setup']['team'] = $team;
                        throw new RuntimeException('Teammitglied: Bitte Name und gültige E-Mail-Adresse angeben (oder die Zeile leer lassen).');
                    }
                    if (in_array($m, $seen, true)) {
                        throw new RuntimeException('Die E-Mail-Adresse ' . $m . ' ist doppelt vorhanden.');
                    }
                    $seen[] = $m;
                    $team[] = ['name' => mb_substr($n, 0, 120), 'email' => $m, 'role' => ($roles[$i] ?? 'team') === 'admin' ? 'admin' : 'team'];
                }
                $_SESSION['setup']['team'] = $team;
                $_SESSION['setup']['invite'] = !empty($_POST['invite']) ? '1' : '0';
                $_SESSION['setup_step'] = 7;
                break;

            case 7:
                $ops = [];
                foreach (['privacy_contact', 'retention_years', 'ip_retention_days', 'mail_log_retention_days', 'audit_retention_days'] as $k) {
                    $ops[$k] = trim((string) ($_POST[$k] ?? ''));
                }
                if ($ops['privacy_contact'] !== '' && !valid_email($ops['privacy_contact'])) {
                    $_SESSION['setup']['ops'] = $ops;
                    throw new RuntimeException('Die E-Mail-Adresse für Datenschutzanfragen ist ungültig.');
                }
                $ops['retention_years'] = (string) max(1, min(30, (int) ($ops['retention_years'] ?: 10)));
                $ops['ip_retention_days'] = (string) max(1, min(365, (int) ($ops['ip_retention_days'] ?: 90)));
                $ops['mail_log_retention_days'] = (string) max(30, min(3650, (int) ($ops['mail_log_retention_days'] ?: 365)));
                $ops['audit_retention_days'] = (string) max(30, min(3650, (int) ($ops['audit_retention_days'] ?: 365)));
                $ops['show_contact_person'] = !empty($_POST['show_contact_person']) ? '1' : '0';
                $ops['mail_attach_pdf'] = !empty($_POST['mail_attach_pdf']) ? '1' : '0';
                $ops['update_auto'] = !empty($_POST['update_auto']) ? '1' : '0';
                $_SESSION['setup']['ops'] = $ops;
                $_SESSION['setup_step'] = 8;
                break;

            case 8:
                $site = [];
                foreach (['site_title', 'site_headline', 'site_subtitle', 'site_cta_label', 'brand_tagline'] as $k) {
                    $site[$k] = trim((string) ($_POST[$k] ?? ''));
                }
                $site['site_title'] = mb_substr($site['site_title'], 0, 70);
                $site['site_headline'] = mb_substr($site['site_headline'], 0, 160);
                $site['site_subtitle'] = mb_substr($site['site_subtitle'], 0, 500);
                $site['site_cta_label'] = mb_substr($site['site_cta_label'], 0, 40);
                $site['brand_tagline'] = mb_substr($site['brand_tagline'], 0, 60);
                $site['site_enabled'] = !empty($_POST['site_enabled']) ? '1' : '0';
                $site['site_index'] = !empty($_POST['site_index']) ? '1' : '0';
                $site['site_admin_link'] = !empty($_POST['site_admin_link']) ? '1' : '0';
                $site['inquiry_enabled'] = !empty($_POST['inquiry_enabled']) ? '1' : '0';
                $site['inquiry_confirm_mail'] = !empty($_POST['inquiry_confirm_mail']) ? '1' : '0';
                // Logo und Favicon: gleiche Prüfung wie in der Administration (Brand::store); Dateien liegen bereits im Zielordner.
                $files = $_SESSION['setup']['site']['files'] ?? [];
                $uploadError = null;
                foreach (['logo', 'favicon'] as $kind) {
                    if (!empty($_POST[$kind . '_remove']) && !empty($files[$kind])) {
                        @unlink(FileStore::dir('brand') . '/' . $files[$kind]);
                        unset($files[$kind]);
                    }
                    if (isset($_FILES[$kind]) && (int) ($_FILES[$kind]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                        try {
                            $new = Brand::store($kind, $_FILES[$kind]);
                            if (!empty($files[$kind])) {
                                @unlink(FileStore::dir('brand') . '/' . $files[$kind]);
                            }
                            $files[$kind] = $new;
                        } catch (ValidationException $e) {
                            $uploadError = $e->getMessage();
                        }
                    }
                }
                $site['files'] = $files;
                $_SESSION['setup']['site'] = $site;
                if ($uploadError !== null) {
                    throw new RuntimeException($uploadError);
                }
                $_SESSION['setup_step'] = 9;
                break;

            case 9:
                $cfg = $_SESSION['setup'];
                $key = bin2hex(random_bytes(32));
                $GLOBALS['APP_CONFIG'] = [
                    'db'  => $cfg['db'],
                    'app' => ['base_url' => $cfg['app']['base_url'], 'key' => $key, 'debug' => false, 'timezone' => $cfg['app']['timezone']],
                ];
                date_default_timezone_set($cfg['app']['timezone']);

                $pdo = DB::pdo();
                foreach (Schema::statements() as $sql) {
                    $pdo->exec($sql);
                }
                Schema::seed($pdo);

                $settings = array_intersect_key($cfg['app'], array_flip(['company_name', 'brand_name', 'brand_color', 'company_email', 'company_street', 'company_zip', 'company_city', 'company_country', 'company_phone', 'company_vat_id', 'currency', 'number_style', 'mwst_liable', 'mwst_method', 'mwst_saldo_rate', 'mwst_period']));
                $settings += array_intersect_key($cfg['mail'] ?? [], array_flip(['mail_transport', 'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_user', 'smtp_pass', 'mail_from_email', 'mail_from_name']));
                $settings['notify_email'] = ($cfg['mail']['notify_email'] ?? '') ?: $cfg['app']['company_email'];
                $settings['round_005'] = $cfg['app']['currency'] === 'CHF' ? '1' : '0';
                $settings += $cfg['doc'] ?? [];
                $settings += array_intersect_key($cfg['pay'] ?? [], array_flip(['pay_mollie_enabled', 'pay_stripe_enabled', 'mollie_api_key', 'stripe_secret_key', 'stripe_webhook_secret']));
                $settings += $cfg['ops'] ?? [];
                $lic = $cfg['lic'] ?? [];
                $sso = $cfg['sso'] ?? [];
                if (($sso['sso_issuer'] ?? '') !== '') {
                    $settings += ['sso_issuer' => $sso['sso_issuer'], 'sso_enabled' => '1', 'sso_auto_create' => $sso['sso_auto_create'] ?? '0'];
                }
                $settings = array_filter($settings, static fn ($v) => $v !== null);
                Settings::set($settings);
                $licenseResult = null;
                if (($lic['license_key'] ?? '') !== '') {
                    License::setKey($lic['license_key']);
                    $licenseResult = ['ok' => false, 'message' => 'Der öffentliche Schlüssel fehlt – unter «Lizenz» ergänzen und aktivieren.'];
                    if (License::pemToRaw(License::publicKey()) !== null) {
                        $licenseResult = License::activate();
                    }
                }

                // Branding-Dateien und Startseite (Website-Builder) übernehmen.
                $site = $cfg['site'] ?? [];
                $brandSet = [];
                foreach (['logo' => 'brand_logo_file', 'favicon' => 'brand_favicon_file'] as $kind => $key) {
                    if (!empty($site['files'][$kind])) {
                        $brandSet[$key] = $site['files'][$kind];
                    }
                }
                if (($site['brand_tagline'] ?? '') !== '') {
                    $brandSet['brand_tagline'] = $site['brand_tagline'];
                }
                if ($brandSet) {
                    Settings::set($brandSet);
                }
                if ($site) {
                    $page = SiteBuilder::defaults();
                    $page['enabled'] = ($site['site_enabled'] ?? '1') === '1';
                    $page['seo']['index'] = ($site['site_index'] ?? '0') === '1';
                    $page['footer']['show_admin_link'] = ($site['site_admin_link'] ?? '0') === '1';
                    if (($site['site_title'] ?? '') !== '') {
                        $page['seo']['title'] = $site['site_title'];
                    }
                    if (($site['site_cta_label'] ?? '') !== '') {
                        $page['nav']['cta_label'] = $site['site_cta_label'];
                    }
                    foreach ($page['blocks'] as &$blk) {
                        if ($blk['type'] === 'hero') {
                            if ($site['site_headline'] !== '') {
                                $blk['title'] = $site['site_headline'];
                            }
                            if ($site['site_subtitle'] !== '') {
                                $blk['subtitle'] = $site['site_subtitle'];
                            }
                            break;
                        }
                    }
                    unset($blk);
                    if (($site['inquiry_enabled'] ?? '1') !== '1') {
                        $page['blocks'] = array_values(array_filter($page['blocks'], static fn($b) => $b['type'] !== 'inquiry'));
                    }
                    SiteBuilder::saveDraft($page);
                    SiteBuilder::publish();
                }
                Settings::set([
                    'inquiry_enabled' => ($site['inquiry_enabled'] ?? '1') === '1' ? '1' : '0',
                    'inquiry_confirm_mail' => ($site['inquiry_confirm_mail'] ?? '1') === '1' ? '1' : '0',
                    'health_token' => bin2hex(random_bytes(20)),
                ]);

                DB::tx(static function () use ($cfg): void {
                    $existing = DB::val('SELECT id FROM users WHERE email = ?', [$cfg['admin']['email']]);
                    if ($existing) {
                        DB::update('users', ['name' => $cfg['admin']['name'], 'password_hash' => $cfg['admin']['hash'], 'role' => 'admin', 'active' => 1, 'updated_at' => now()], 'id = :id', [':id' => $existing]);
                    } else {
                        DB::insert('users', ['name' => $cfg['admin']['name'], 'email' => $cfg['admin']['email'], 'password_hash' => $cfg['admin']['hash'], 'role' => 'admin', 'active' => 1, 'created_at' => now()]);
                    }
                });

                $php = "<?php\n/**\n * Konfiguration – erstellt durch setup.php am " . date('d.m.Y H:i') . ".\n"
                    . " * Diese Datei enthält Zugangsdaten. Nicht öffentlich zugänglich machen (app/.htaccess).\n"
                    . " * Der App-Schlüssel verschlüsselt gespeicherte Passwörter – bei Verlust SMTP-Passwort neu setzen.\n */\n\n"
                    . 'return ' . var_export($GLOBALS['APP_CONFIG'], true) . ";\n";
                $tmp = $configFile . '.tmp';
                if (file_put_contents($tmp, $php, LOCK_EX) === false || !rename($tmp, $configFile)) {
                    throw new RuntimeException('config.php konnte nicht geschrieben werden. Bitte Schreibrechte für den Ordner app/ prüfen.');
                }
                @chmod($configFile, 0640);
                @mkdir(APP_ROOT . '/storage/logs', 0750, true);
                file_put_contents($lockFile, 'Installiert am ' . date('c') . "\n", LOCK_EX);

                // Weitere Benutzer anlegen (Einladung per E-Mail optional); Fehler stoppen die Installation nicht.
                $invites = [];
                foreach ($cfg['team'] ?? [] as $m) {
                    try {
                        $uid = DB::insert('users', ['name' => $m['name'], 'email' => $m['email'], 'password_hash' => Auth::hashPassword(bin2hex(random_bytes(24))), 'role' => $m['role'], 'active' => 1, 'created_at' => now()]);
                        $row = ['name' => $m['name'], 'email' => $m['email'], 'status' => 'angelegt'];
                        if (($cfg['invite'] ?? '0') === '1' && ($cfg['mail']['mail_transport'] ?? 'smtp') !== 'log') {
                            $u = ['id' => $uid, 'name' => $m['name'], 'email' => $m['email']];
                            $row['status'] = Notifier::staffLink($u, Tokens::create('user', $uid, 60 * 24), true) ? 'Einladung gesendet' : 'angelegt – Einladung fehlgeschlagen (später unter Benutzer erneut senden)';
                        } else {
                            $row['status'] = 'angelegt – Anmeldelink unter Benutzer senden';
                        }
                        $invites[] = $row;
                    } catch (Throwable $e) {
                        $invites[] = ['name' => $m['name'], 'email' => $m['email'], 'status' => 'Fehler: ' . $e->getMessage()];
                    }
                }

                $_SESSION['setup_done'] = [
                    'base_url' => $cfg['app']['base_url'], 'email' => $cfg['admin']['email'], 'invites' => $invites,
                    'https' => str_starts_with($cfg['app']['base_url'], 'https://'),
                    'mollie' => ($cfg['pay']['pay_mollie_enabled'] ?? '0') === '1', 'stripe' => ($cfg['pay']['pay_stripe_enabled'] ?? '0') === '1',
                    'stripe_hook' => ($cfg['pay']['stripe_webhook_secret'] ?? '') !== '',
                    'mail' => $cfg['mail']['mail_transport'] ?? 'smtp', 'mail_host' => ($cfg['mail']['smtp_host'] ?? '') !== '',
                    'iban' => ($cfg['doc']['invoice_iban'] ?? '') !== '', 'cron' => APP_ROOT . '/cron.php', 'php' => PHP_BINARY,
                    'country' => $cfg['app']['company_country'] ?? 'CH',
                    'logo' => !empty($site['files']['logo']), 'site' => ($site['site_enabled'] ?? '1') === '1',
                    'health_url' => rtrim($cfg['app']['base_url'], '/') . '/health?token=' . Settings::get('health_token'),
                    'sso' => ($sso['sso_issuer'] ?? '') !== '' ? $sso['sso_issuer'] : '', 'license' => $licenseResult, 'license_key' => ($lic['license_key'] ?? '') !== '',
                    'liable' => ($cfg['app']['mwst_liable'] ?? '1') === '1', 'uid' => ($cfg['app']['company_vat_id'] ?? '') !== '',
                ];
                unset($_SESSION['setup'], $_SESSION['setup_step']);
                break;
        }
    } catch (PDOException $e) {
        $error = 'Datenbankfehler: ' . $e->getMessage();
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }

    if (!$error && !$notice) {
        header('Location: setup.php');
        exit;
    }
    $step = (int) ($_SESSION['setup_step'] ?? $step);
}

$done = $_SESSION['setup_done'] ?? null;
if ($done) {
    $step = 10;
}

$steps = [1 => 'System', 2 => 'Datenbank', 3 => 'Firma', 4 => 'Zahlung', 5 => 'E-Mail', 6 => 'Team', 7 => 'Betrieb', 8 => 'Auftritt', 9 => 'Installation'];
?><!doctype html>
<html lang="de" class="h-full bg-slate-50 antialiased">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Installation · VylarisOffers</title>
<link rel="stylesheet" href="assets/vendor/inter/inter.css"><link rel="stylesheet" href="assets/vendor/boxicons/css/boxicons.min.css">
<script src="assets/vendor/tailwind/tailwindcss-browser-4.3.3.js"></script>
<style type="text/tailwindcss">
@theme { --font-sans: "Inter", ui-sans-serif, system-ui, sans-serif; --color-brand-50:#eef2ff; --color-brand-100:#e0e7ff; --color-brand-500:#6366f1; --color-brand-600:#4f46e5; --color-brand-700:#4338ca; }
@layer components {
  .btn { @apply inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition duration-200 cursor-pointer focus-visible:outline-none focus-visible:ring-4 disabled:opacity-60 active:scale-[.98]; }
  .btn-primary { @apply bg-brand-600 text-white shadow-sm shadow-brand-600/25 hover:bg-brand-700 hover:-translate-y-px focus-visible:ring-brand-500/30; }
  .btn-secondary { @apply bg-white text-slate-700 ring-1 ring-inset ring-slate-200 hover:bg-slate-50; }
  .btn-ghost { @apply text-slate-600 hover:bg-slate-100; }
  .input { @apply block w-full rounded-xl border-0 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm ring-1 ring-inset ring-slate-200 placeholder:text-slate-400 transition hover:ring-slate-300 focus:ring-2 focus:ring-brand-500 focus:outline-none; }
  .label { @apply block text-sm font-medium text-slate-700 mb-1.5; }
  .hint { @apply mt-1.5 text-xs text-slate-500; }
}
</style>
<style>
.fade-in{animation:fi .5s cubic-bezier(.2,.7,.2,1) both}@keyframes fi{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
.btn.is-loading{color:transparent!important;position:relative;pointer-events:none}.btn.is-loading::after{content:"";position:absolute;inset:0;margin:auto;width:1.1rem;height:1.1rem;border-radius:9999px;border:2px solid #fff;border-right-color:transparent;animation:sp .7s linear infinite}@keyframes sp{to{transform:rotate(360deg)}}
@media (prefers-reduced-motion:reduce){*,*::before,*::after{animation-duration:.01ms!important;transition-duration:.01ms!important}}
</style>
</head>
<body class="min-h-full font-sans text-slate-800">
<div class="pointer-events-none fixed inset-x-0 top-0 -z-10 h-96 bg-gradient-to-b from-brand-100/70 to-transparent"></div>
<div class="mx-auto max-w-3xl px-4 py-10 sm:py-16">
  <div class="mb-8 flex items-center gap-3">
    <span class="grid size-11 place-items-center rounded-xl bg-brand-600 text-white shadow-lg shadow-brand-600/30"><i class="bx bx-file text-2xl"></i></span>
    <div><p class="text-lg font-bold text-slate-900">VylarisOffers</p><p class="text-sm text-slate-500">Installationsassistent</p></div>
  </div>

  <?php if ($step >= 1 && $step <= 9): ?>
  <ol class="mb-8 grid grid-cols-9 gap-1.5 sm:gap-2" aria-label="Fortschritt">
    <?php foreach ($steps as $n => $label): ?>
      <li class="flex flex-col gap-2">
        <div class="h-1.5 overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full bg-brand-600 transition-all duration-500" style="width:<?= $n < $step ? 100 : ($n === $step ? 50 : 0) ?>%"></div></div>
        <span class="hidden text-xs font-medium sm:block <?= $n <= $step ? 'text-slate-900' : 'text-slate-400' ?>"><?= $n < $step ? '<i class="bx bxs-check-circle align-middle text-brand-600"></i> ' : '' ?><?= $label ?></span>
      </li>
    <?php endforeach; ?>
  </ol>
  <?php endif; ?>

  <?php if ($error): ?><div class="fade-in mb-6 flex gap-3 rounded-2xl bg-rose-50 p-4 text-sm text-rose-800 ring-1 ring-rose-200" role="alert"><i class="bx bx-error-circle text-xl"></i><p><?= s_e($error) ?></p></div><?php endif; ?>
  <?php if ($notice): ?><div class="fade-in mb-6 flex gap-3 rounded-2xl bg-emerald-50 p-4 text-sm text-emerald-800 ring-1 ring-emerald-200" role="status"><i class="bx bx-check-circle text-xl"></i><p><?= s_e($notice) ?></p></div><?php endif; ?>

  <div class="fade-in rounded-2xl bg-white shadow-[0_1px_2px_rgb(15_23_42/.04),0_8px_24px_-8px_rgb(15_23_42/.1)] ring-1 ring-slate-200/80">
  <?php if ($step === 0): ?>
    <div class="p-8 text-center sm:p-10">
      <span class="mx-auto grid size-16 place-items-center rounded-2xl bg-amber-50 text-4xl text-amber-600"><i class="bx bx-lock-alt"></i></span>
      <h1 class="mt-5 text-xl font-bold text-slate-900">Installation gesperrt</h1>
      <p class="mt-2 text-sm leading-relaxed text-slate-600">Das System ist bereits installiert. Aus Sicherheitsgründen kann der Assistent nicht erneut ausgeführt werden.<br>Für eine Neuinstallation <code class="rounded bg-slate-100 px-1">app/config.php</code> und <code class="rounded bg-slate-100 px-1">storage/install.lock</code> entfernen.</p>
      <p class="mt-4 text-sm font-medium text-rose-600">Bitte löschen Sie <code>setup.php</code> vom Server.</p>
      <a href="index.php" class="btn btn-primary mt-6"><i class="bx bx-right-arrow-alt"></i>Zur Anwendung</a>
    </div>

  <?php elseif ($step === 10): ?>
    <div class="p-8 sm:p-10">
      <div class="text-center">
        <span class="mx-auto grid size-16 place-items-center rounded-2xl bg-emerald-50 text-4xl text-emerald-600"><i class="bx bx-check-double"></i></span>
        <h1 class="mt-5 text-2xl font-bold text-slate-900">Installation abgeschlossen 🎉</h1>
        <p class="mt-2 text-sm leading-relaxed text-slate-600">Alle Tabellen wurden erstellt, die Grunddaten (Produktbereiche „Digital“ und „Physisch“, Schweizer MWST-Sätze 8.1 % / 2.6 % / 3.8 %, Versandarten) sind angelegt und der Installer ist gesperrt.</p>
      </div>
      <h2 class="mt-8 text-sm font-semibold text-slate-900">Nächste Schritte</h2>
      <ol class="mt-3 space-y-3 text-sm text-slate-700">
        <li class="flex gap-3 rounded-xl bg-amber-50 p-4 ring-1 ring-amber-200"><i class="bx bx-shield-quarter mt-0.5 text-xl text-amber-600"></i><span><strong>setup.php löschen.</strong> Löschen Sie die Datei jetzt vom Server (Plesk → Dateien).</span></li>
        <?php if (!$done['https']): ?><li class="flex gap-3 rounded-xl bg-amber-50 p-4 ring-1 ring-amber-200"><i class="bx bx-lock-open-alt mt-0.5 text-xl text-amber-600"></i><span><strong>HTTPS aktivieren.</strong> Die Basis-URL beginnt mit <code>http://</code>. In Plesk unter „SSL/TLS-Zertifikate“ ein Zertifikat (Let’s Encrypt) ausstellen, danach in <code>app/config.php</code> die <code>base_url</code> auf <code>https://…</code> ändern. Online-Zahlung und Passkeys funktionieren erst mit HTTPS.</span></li><?php endif; ?>
        <?php $lr = $done['license']; $lok = $lr && !empty($lr['ok']); ?>
        <li class="flex gap-3 rounded-xl p-4 ring-1 <?= $lok ? 'bg-emerald-50 ring-emerald-200' : 'bg-amber-50 ring-amber-200' ?>"><i class="bx bx-key mt-0.5 text-xl <?= $lok ? 'text-emerald-600' : 'text-amber-600' ?>"></i><span><strong><?= $lok ? 'Lizenz aktiviert.' : 'Lizenz aktivieren:' ?></strong> <?= $lok ? 'Alle Funktionen Ihres Tarifs sind freigeschaltet.' : s_e(!$done['license_key'] ? 'Ohne Lizenz läuft das System im Lesemodus. Schlüssel und öffentlichen Schlüssel unter Lizenz eintragen und aktivieren.' : (string) ($lr['message'] ?? 'Aktivierung nicht möglich – unter Lizenz erneut versuchen.')) ?></span></li>
        <?php if (!empty($done['sso'])): ?><li class="flex gap-3 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200"><i class="bx bx-log-in-circle mt-0.5 text-xl text-slate-500"></i><span><strong>Single Sign-On vorbereitet.</strong> Anbieter: <?= s_e($done['sso']) ?>. Die Redirect-URI <code class="rounded bg-white px-1"><?= s_e(rtrim($done['base_url'], '/')) ?>/admin/sso/callback</code> wird bei der ersten Anmeldung automatisch registriert; andernfalls unter Einstellungen → Single Sign-On Client-ID und Secret eintragen.</span></li><?php endif; ?>
        <li class="flex gap-3 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200/70"><i class="bx bx-time-five mt-0.5 text-xl text-slate-400"></i><span><strong>Cronjob einrichten</strong> (Plesk → Geplante Aufgaben → „PHP-Skript ausführen“), täglich z. B. 06:00:<br><code class="mt-1 block break-all rounded bg-white px-2 py-1 text-xs ring-1 ring-slate-200"><?= s_e($done['cron']) ?></code><span class="text-xs text-slate-500">Alternativ als Befehl: <code><?= s_e($done['php']) ?> <?= s_e($done['cron']) ?></code></span></span></li>
        <?php if ($done['mail'] === 'log' || ($done['mail'] === 'smtp' && !$done['mail_host'])): ?><li class="flex gap-3 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200/70"><i class="bx bx-envelope mt-0.5 text-xl text-slate-400"></i><span><strong>E-Mail-Versand konfigurieren</strong> unter <em>Einstellungen → E-Mail</em> und mit „Test senden“ prüfen – bis dahin werden keine Mails verschickt.</span></li><?php endif; ?>
        <?php if ($done['stripe']): ?><li class="flex gap-3 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200/70"><i class="bx bx-credit-card mt-0.5 text-xl text-slate-400"></i><span><strong>Stripe-Webhook anlegen</strong> (Stripe-Dashboard → Entwickler → Webhooks): Endpunkt <code class="break-all"><?= s_e($done['base_url']) ?>/webhooks/stripe</code>, Ereignisse <code>checkout.session.completed</code>, <code>checkout.session.async_payment_succeeded</code>, <code>checkout.session.async_payment_failed</code>, <code>checkout.session.expired</code>. Das Signing-Secret (<code>whsec_…</code>) tragen Sie unter <em>Einstellungen → Zahlungen</em> ein<?= $done['stripe_hook'] ? ' (bereits hinterlegt)' : '' ?>.</span></li><?php endif; ?>
        <?php if ($done['mollie'] || $done['stripe']): ?><li class="flex gap-3 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200/70"><i class="bx bx-test-tube mt-0.5 text-xl text-slate-400"></i><span><strong>Testzahlung durchführen.</strong> Versenden Sie eine Test-Rechnung und bezahlen Sie sie im Testmodus des Anbieters, bevor Sie Live-Schlüssel verwenden.</span></li><?php endif; ?>
        <?php if (!$done['iban']): ?><li class="flex gap-3 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200/70"><i class="bx bx-qr mt-0.5 text-xl text-slate-400"></i><span><strong>QR-Rechnung:</strong> Mit einer IBAN unter <em>Einstellungen → Rechnungen</em> erhalten Rechnungs-PDFs einen Einzahlungsschein.</span></li><?php endif; ?>
        <li class="flex gap-3 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200/70"><i class="bx bx-receipt mt-0.5 text-xl text-slate-400"></i><span><strong>MWST prüfen:</strong> <?= $done['liable'] ? 'Sie sind als MWST-pflichtig eingetragen – kontrollieren Sie unter <em>Steuervorlagen</em> die Sätze (Normalsatz 8.1 %, reduziert 2.6 %, Beherbergung 3.8 %).' : 'Sie sind als nicht MWST-pflichtig eingetragen: Auf Offerten und Rechnungen wird keine MWST ausgewiesen.' ?></span></li>
        <li class="flex gap-3 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200/70"><i class="bx bx-pulse mt-0.5 text-xl text-slate-400"></i><span><strong>Systemstatus überwachen:</strong> Unter <em>Systemstatus</em> prüft das System Umgebung, Backup, E-Mail und Rechnungsdaten. Für einen Überwachungsdienst (z. B. UptimeRobot) verwenden Sie:<br><code class="mt-1 block break-all rounded bg-white px-2 py-1.5 text-xs ring-1 ring-slate-200"><?= s_e($done['health_url']) ?></code></span></li>
        <li class="flex gap-3 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200/70"><i class="bx bx-fingerprint mt-0.5 text-xl text-slate-400"></i><span><strong>Konto absichern:</strong> Unter <em>Mein Profil</em> können Sie Passkeys<?= $done['https'] ? '' : ' (nach HTTPS-Umstellung)' ?> und/oder eine Authenticator-App als zweiten Faktor einrichten.</span></li>
        <li class="flex gap-3 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200/70"><i class="bx bx-layout mt-0.5 text-xl text-slate-400"></i><span><strong>Startseite gestalten:</strong> Unter <em>Webseite</em> bearbeiten Sie Bausteine, Texte und Bilder mit Live-Vorschau<?= $done['site'] ? '' : ' (die Startseite ist derzeit ausgeschaltet)' ?>.<?= $done['logo'] ? '' : ' Ein Logo laden Sie unter <em>Einstellungen → Branding</em> hoch.' ?></span></li>
        <li class="flex gap-3 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200/70"><i class="bx bx-list-check mt-0.5 text-xl text-slate-400"></i><span><strong>Katalog &amp; Texte:</strong> Produktbereiche, Positionsvorlagen und Steuervorlagen prüfen; Datenschutzerklärung (<code>/datenschutz</code>) lesen und ggf. anpassen; mit Hoster, E-Mail- und Zahlungsanbieter Auftragsverarbeitungsverträge abschliessen.</span></li>
      </ol>
      <?php if (!empty($done['invites'])): ?>
      <h2 class="mt-8 text-sm font-semibold text-slate-900">Weitere Benutzer</h2>
      <ul class="mt-3 divide-y divide-slate-100 rounded-xl text-sm ring-1 ring-slate-200">
        <?php foreach ($done['invites'] as $iv): ?><li class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:justify-between"><span class="font-medium text-slate-900"><?= s_e($iv['name']) ?> <span class="font-normal text-slate-500">· <?= s_e($iv['email']) ?></span></span><span class="text-slate-600"><?= s_e($iv['status']) ?></span></li><?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <div class="mt-8 flex flex-col justify-center gap-2 sm:flex-row">
        <a href="<?= s_e($done['base_url']) ?>/admin/login" class="btn btn-primary"><i class="bx bx-log-in"></i>Zur Administration</a>
        <a href="<?= s_e($done['base_url']) ?>/portal" class="btn btn-secondary"><i class="bx bx-user-circle"></i>Kundenportal</a>
      </div>
      <p class="mt-4 text-center text-xs text-slate-500">Anmeldung mit <?= s_e($done['email']) ?></p>
    </div>
    <?php unset($_SESSION['setup_done']); ?>

  <?php else: ?>
  <form method="post" enctype="multipart/form-data" class="divide-y divide-slate-100" id="setup-form">
    <input type="hidden" name="_csrf" value="<?= s_e(s_csrf()) ?>">
    <div class="p-6 sm:p-8">
    <?php if ($step === 1): ?>
      <h1 class="text-xl font-bold text-slate-900">Willkommen</h1>
      <p class="mt-1 text-sm text-slate-500">Der Assistent prüft zuerst, ob der Server alle Voraussetzungen erfüllt.</p>
      <ul class="mt-6 divide-y divide-slate-100 rounded-xl ring-1 ring-slate-200">
        <?php $allOk = true; foreach (s_requirements() as [$label, $ok]): $allOk = $allOk && $ok; ?>
          <li class="flex items-center justify-between gap-4 px-4 py-3 text-sm"><span><?= s_e($label) ?></span>
            <?= $ok ? '<span class="inline-flex items-center gap-1 font-medium text-emerald-600"><i class="bx bxs-check-circle text-lg"></i>OK</span>' : '<span class="inline-flex items-center gap-1 font-medium text-rose-600"><i class="bx bxs-x-circle text-lg"></i>Fehlt</span>' ?></li>
        <?php endforeach; ?>
      </ul>
      <?php if (!$allOk): ?><p class="mt-4 text-sm text-rose-600">Bitte beheben Sie die fehlenden Punkte (z. B. in Plesk unter „PHP-Einstellungen“ bzw. Dateiberechtigungen) und laden Sie die Seite neu.</p><?php endif; ?>
      <h2 class="mt-8 text-sm font-semibold text-slate-900">Empfohlen</h2>
      <p class="mt-1 text-xs text-slate-500">Nicht zwingend – einzelne Funktionen sind ohne diese Punkte eingeschränkt.</p>
      <ul class="mt-3 divide-y divide-slate-100 rounded-xl ring-1 ring-slate-200">
        <?php foreach (s_recommendations() as [$label, $ok, $hint]): ?>
          <li class="px-4 py-3 text-sm"><div class="flex items-center justify-between gap-4"><span><?= s_e($label) ?></span>
            <?= $ok ? '<span class="inline-flex items-center gap-1 font-medium text-emerald-600"><i class="bx bxs-check-circle text-lg"></i>OK</span>' : '<span class="inline-flex items-center gap-1 font-medium text-amber-600"><i class="bx bxs-error text-lg"></i>Prüfen</span>' ?></div>
            <?php if (!$ok): ?><p class="mt-1 text-xs text-slate-500"><?= s_e($hint) ?></p><?php endif; ?></li>
        <?php endforeach; ?>
      </ul>

    <?php elseif ($step === 2): ?>
      <h1 class="text-xl font-bold text-slate-900">Datenbank</h1>
      <p class="mt-1 text-sm text-slate-500">Erstellen Sie in Plesk unter <em>Datenbanken</em> eine MySQL-/MariaDB-Datenbank mit Benutzer und tragen Sie die Daten hier ein.</p>
      <div class="mt-6 grid gap-4 sm:grid-cols-6">
        <div class="sm:col-span-4"><label class="label">Host</label><input class="input" name="db_host" value="<?= s_e(s_val('db', 'host', 'localhost')) ?>" required></div>
        <div class="sm:col-span-2"><label class="label">Port</label><input class="input" name="db_port" type="number" value="<?= s_e(s_val('db', 'port', '3306')) ?>"></div>
        <div class="sm:col-span-6"><label class="label">Datenbankname</label><input class="input" name="db_name" value="<?= s_e(s_val('db', 'name')) ?>" required></div>
        <div class="sm:col-span-3"><label class="label">Benutzer</label><input class="input" name="db_user" value="<?= s_e(s_val('db', 'user')) ?>" required autocomplete="off"></div>
        <div class="sm:col-span-3"><label class="label">Passwort</label><input class="input" name="db_pass" type="password" value="<?= s_e(s_val('db', 'pass')) ?>" autocomplete="new-password"></div>
      </div>
      <div class="mt-5 flex flex-wrap items-center gap-3">
        <button type="button" class="btn btn-secondary" id="test-db"><i class="bx bx-plug"></i>Verbindung testen</button>
        <p id="test-result" class="text-sm" aria-live="polite"></p>
      </div>

    <?php elseif ($step === 3): ?>
      <h1 class="text-xl font-bold text-slate-900">Firma &amp; System</h1>
      <p class="mt-1 text-sm text-slate-500">Diese Angaben erscheinen auf Offerten und in E-Mails und lassen sich später ändern.</p>
      <div class="mt-6 grid gap-4 sm:grid-cols-6">
        <div class="sm:col-span-6"><label class="label">Basis-URL</label><input class="input font-mono text-[13px]" name="base_url" value="<?= s_e(s_val('app', 'base_url', s_detect_base_url())) ?>" required><p class="hint">Ohne abschliessenden Schrägstrich. Offerten-Links lauten dann <span class="font-mono">…/o/ID</span>, das Portal <span class="font-mono">…/portal</span>. <strong>Empfohlen: https://</strong> – Online-Zahlung und Passkeys funktionieren nur mit HTTPS.</p></div>
        <div class="sm:col-span-6"><label class="label">Firmenname</label><input class="input" name="company_name" value="<?= s_e(s_val('app', 'company_name')) ?>" required></div>
        <div class="sm:col-span-3"><label class="label">Systemname <span class="font-normal text-slate-400">(Whitelabel, optional)</span></label><input class="input" name="brand_name" maxlength="60" value="<?= s_e(s_val('app', 'brand_name')) ?>" placeholder="Leer = Firmenname"><p class="hint">Name der Administration. Logo und Favicon laden Sie später unter Einstellungen → Branding hoch.</p></div>
        <div class="sm:col-span-3"><label class="label">Markenfarbe</label><input class="input font-mono" name="brand_color" value="<?= s_e(s_val('app', 'brand_color', '#4f46e5')) ?>" pattern="#[0-9a-fA-F]{6}"></div>
        <div class="sm:col-span-3"><label class="label">E-Mail</label><input class="input" type="email" name="company_email" value="<?= s_e(s_val('app', 'company_email')) ?>" required></div>
        <div class="sm:col-span-3"><label class="label">Telefon</label><input class="input" name="company_phone" value="<?= s_e(s_val('app', 'company_phone')) ?>"></div>
        <div class="sm:col-span-6"><label class="label">Strasse und Nr.</label><input class="input" name="company_street" value="<?= s_e(s_val('app', 'company_street')) ?>"></div>
        <div class="sm:col-span-2"><label class="label">PLZ</label><input class="input" name="company_zip" value="<?= s_e(s_val('app', 'company_zip')) ?>"></div>
        <div class="sm:col-span-4"><label class="label">Ort</label><input class="input" name="company_city" value="<?= s_e(s_val('app', 'company_city')) ?>"></div>
        <div class="sm:col-span-2"><label class="label">Währung</label><select class="input" name="currency"><?php foreach (['CHF', 'EUR'] as $c): ?><option<?= s_val('app', 'currency', 'CHF') === $c ? ' selected' : '' ?>><?= $c ?></option><?php endforeach; ?></select></div>
        <div class="sm:col-span-4 flex items-end"><p class="rounded-xl bg-slate-50 px-3.5 py-2.5 text-xs text-slate-500 ring-1 ring-slate-200/70">Das System ist auf die Schweiz ausgelegt: Land Schweiz, Zahlenformat 1’234.50, Zeitzone Europe/Zurich.</p></div>

        <div class="sm:col-span-6 mt-2 border-t border-slate-100 pt-4"><h2 class="flex items-center gap-2 text-base font-semibold text-slate-900"><i class="bx bx-receipt text-xl text-slate-400"></i>Mehrwertsteuer (Schweiz)</h2>
          <p class="mt-1 text-sm text-slate-500">Ab CHF 100’000 Jahresumsatz sind Unternehmen MWST-pflichtig. Nicht Pflichtige dürfen keine MWST ausweisen. Normalsatz 8.1 %, reduziert 2.6 %, Beherbergung 3.8 % (seit 1.1.2024) – anpassbar unter „Steuervorlagen“.</p></div>
        <label class="flex cursor-pointer items-start gap-3 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200/70 sm:col-span-6"><input type="checkbox" class="mt-0.5 size-4 rounded accent-indigo-600" name="mwst_liable" value="1" <?= s_val('app', 'mwst_liable', '1') === '1' ? 'checked' : '' ?>><span><span class="block text-sm font-medium text-slate-900">Wir sind MWST-pflichtig</span><span class="block text-xs text-slate-500">Ohne Haken wird auf Dokumenten keine MWST ausgewiesen („Nicht MWST-pflichtig“).</span></span></label>
        <div class="sm:col-span-3"><label class="label">UID / MWST-Nummer</label><input class="input" name="company_vat_id" value="<?= s_e(s_val('app', 'company_vat_id')) ?>" placeholder="CHE-123.456.789"><p class="hint">Die Prüfziffer wird kontrolliert. Auf Rechnungen erscheint „CHE-123.456.789 MWST“.</p></div>
        <div class="sm:col-span-3"><label class="label">Abrechnungsart</label><select class="input" name="mwst_method"><option value="agreed"<?= s_val('app', 'mwst_method', 'agreed') === 'agreed' ? ' selected' : '' ?>>Vereinbarte Entgelte (Rechnungsstellung)</option><option value="received"<?= s_val('app', 'mwst_method') === 'received' ? ' selected' : '' ?>>Vereinnahmte Entgelte (Zahlungseingang)</option></select></div>
        <div class="sm:col-span-3"><label class="label">Abrechnungsperiode</label><select class="input" name="mwst_period"><?php foreach (['quarter' => 'Vierteljährlich', 'semester' => 'Halbjährlich (z. B. Saldosteuersatz)', 'year' => 'Jährlich'] as $pk => $pl): ?><option value="<?= $pk ?>"<?= s_val('app', 'mwst_period', 'quarter') === $pk ? ' selected' : '' ?>><?= $pl ?></option><?php endforeach; ?></select></div>
        <div class="sm:col-span-3"><label class="label">Saldosteuersatz <span class="font-normal text-slate-400">(optional)</span></label><input class="input" name="mwst_saldo_rate" value="<?= s_e(s_val('app', 'mwst_saldo_rate')) ?>" inputmode="decimal" placeholder="z. B. 6.2"><p class="hint">Nur mit Bewilligung der ESTV.</p></div>
        <div class="sm:col-span-6 mt-2 border-t border-slate-100 pt-4"><h2 class="flex items-center gap-2 text-base font-semibold text-slate-900"><i class="bx bx-key text-xl text-slate-400"></i>Lizenz</h2>
          <p class="mt-1 text-sm text-slate-500">VylarisOffers ist lizenzpflichtig. Ohne gültige Lizenz läuft das System im Lesemodus. Den Schlüssel können Sie auch später unter <em>Lizenz</em> eingeben.</p></div>
        <?php $lc = $_SESSION['setup']['lic'] ?? []; ?>
        <div class="sm:col-span-6"><label class="label">Lizenzschlüssel</label><input class="input font-mono" name="license_key" value="<?= s_e($lc['license_key'] ?? '') ?>" placeholder="XX-XXXX-XXXX-XXXX" autocomplete="off" spellcheck="false"></div>
        <?php $sc = $_SESSION['setup']['sso'] ?? []; ?>
        <div class="sm:col-span-6 mt-2 border-t border-slate-100 pt-4"><h2 class="flex items-center gap-2 text-base font-semibold text-slate-900"><i class="bx bx-log-in-circle text-xl text-slate-400"></i>Single Sign-On <span class="font-normal text-slate-400">(optional)</span></h2>
          <p class="mt-1 text-sm text-slate-500">Mitarbeitende melden sich per OpenID Connect an. Es genügt die Adresse des Anbieters (z. B. <em>https://vylaris.tech</em>) – alles Weitere wird automatisch erkannt. Benötigt die Lizenzfunktion «sso».</p></div>
        <div class="sm:col-span-4"><label class="label">Adresse des Anbieters</label><input class="input font-mono text-[13px]" name="sso_issuer" value="<?= s_e($sc['sso_issuer'] ?? '') ?>" placeholder="https://vylaris.tech" autocomplete="off" spellcheck="false"></div>
        <label class="flex cursor-pointer items-center gap-3 sm:col-span-2"><input type="checkbox" class="size-4 rounded accent-indigo-600" name="sso_auto_create" value="1" <?= ($sc['sso_auto_create'] ?? '0') === '1' ? 'checked' : '' ?>><span class="text-sm text-slate-700">Unbekannte Konten automatisch anlegen</span></label>
      </div>

    <?php elseif ($step === 4): ?>
      <h1 class="text-xl font-bold text-slate-900">Dokumente &amp; Zahlung</h1>
      <p class="mt-1 text-sm text-slate-500">Nummernkreise, Zahlungsbedingungen und – optional – Online-Zahlung. Alles lässt sich später in den Einstellungen ändern.</p>
      <div class="mt-6 grid gap-4 sm:grid-cols-6">
        <div class="sm:col-span-2"><label class="label">Präfix Offerten</label><input class="input" name="offer_prefix" maxlength="8" value="<?= s_e(s_val('doc', 'offer_prefix', 'OF')) ?>"><p class="hint">z. B. OF-<?= date('Y') ?>-0001</p></div>
        <div class="sm:col-span-2"><label class="label">Präfix Aufträge</label><input class="input" name="order_prefix" maxlength="8" value="<?= s_e(s_val('doc', 'order_prefix', 'AU')) ?>"></div>
        <div class="sm:col-span-2"><label class="label">Präfix Rechnungen</label><input class="input" name="invoice_prefix" maxlength="8" value="<?= s_e(s_val('doc', 'invoice_prefix', 'RE')) ?>"></div>
        <div class="sm:col-span-3"><label class="label">Offerten gültig (Tage)</label><input class="input" type="number" min="0" max="365" name="offer_validity_days" value="<?= s_e(s_val('doc', 'offer_validity_days', '30')) ?>"><p class="hint">0 = ohne Ablaufdatum</p></div>
        <div class="sm:col-span-3"><label class="label">Zahlungsfrist Rechnungen (Tage)</label><input class="input" type="number" min="0" max="365" name="invoice_payment_days" value="<?= s_e(s_val('doc', 'invoice_payment_days', '30')) ?>"></div>
        <div class="sm:col-span-6"><label class="label">IBAN für QR-Rechnung <span class="font-normal text-slate-400">(optional, CH/LI)</span></label><input class="input font-mono" name="invoice_iban" value="<?= s_e(s_val('doc', 'invoice_iban')) ?>" placeholder="CH93 0076 2011 6238 5295 7" autocomplete="off"><p class="hint">Mit gültiger IBAN erhalten Rechnungs-PDFs in CHF/EUR einen Schweizer Einzahlungsschein mit QR-Code. Die Firmenadresse (Schritt 3) muss dafür vollständig sein.</p></div>
        <div class="sm:col-span-6"><label class="label">Zahlungsinformationen <span class="font-normal text-slate-400">(optional)</span></label><textarea class="input font-mono text-[13px]" name="invoice_payment_info" rows="3" placeholder="Bank: Musterbank AG&#10;Zahlungsreferenz: Rechnungsnummer"><?= s_e(s_val('doc', 'invoice_payment_info')) ?></textarea><p class="hint">Erscheint auf jeder Rechnung.</p></div>
      </div>

      <h2 class="mt-8 flex items-center gap-2 text-base font-semibold text-slate-900"><i class="bx bx-credit-card text-xl text-slate-400"></i>Online-Zahlung <span class="text-sm font-normal text-slate-400">(optional)</span></h2>
      <p class="mt-1 text-sm text-slate-500">Kunden bezahlen versendete Rechnungen direkt im Portal. Sie benötigen ein Konto bei Mollie und/oder Stripe. Beginnen Sie am besten mit einem Test-Schlüssel.</p>
      <?php if (!is_https() || !str_starts_with(s_val('app', 'base_url'), 'https://')): ?><p class="mt-3 rounded-xl bg-amber-50 px-3.5 py-2.5 text-sm text-amber-800 ring-1 ring-amber-200"><i class="bx bxs-error align-middle"></i> Online-Zahlung setzt eine HTTPS-Adresse voraus (Basis-URL in Schritt 3). Sie können die Schlüssel schon jetzt hinterlegen und die Zahlung später aktivieren.</p><?php endif; ?>
      <div class="mt-4 space-y-4">
        <div class="rounded-2xl p-4 ring-1 ring-slate-200">
          <label class="flex cursor-pointer items-center gap-3 text-sm font-semibold text-slate-900"><input type="checkbox" class="size-4 rounded accent-indigo-600" name="pay_mollie_enabled" value="1" <?= s_val('pay', 'pay_mollie_enabled') === '1' ? 'checked' : '' ?>>Mollie aktivieren</label>
          <div class="mt-3"><label class="label">API-Key</label><input class="input font-mono text-[13px]" type="password" name="mollie_api_key" autocomplete="new-password" placeholder="<?= s_val('pay', 'mollie_api_key') !== '' ? '•••••••• (gespeichert)' : 'test_… oder live_…' ?>">
            <div class="mt-2 flex flex-wrap items-center gap-3"><button type="button" class="btn btn-secondary" data-test-pay="mollie"><i class="bx bx-plug"></i>Verbindung testen</button><p class="text-sm" data-pay-result="mollie" aria-live="polite"></p></div>
            <p class="hint">Mollie-Dashboard → Entwickler → API-Schlüssel. Der Webhook wird pro Zahlung automatisch übergeben.</p></div>
        </div>
        <div class="rounded-2xl p-4 ring-1 ring-slate-200">
          <label class="flex cursor-pointer items-center gap-3 text-sm font-semibold text-slate-900"><input type="checkbox" class="size-4 rounded accent-indigo-600" name="pay_stripe_enabled" value="1" <?= s_val('pay', 'pay_stripe_enabled') === '1' ? 'checked' : '' ?>>Stripe aktivieren</label>
          <div class="mt-3 grid gap-4 sm:grid-cols-2">
            <div><label class="label">Geheimer Schlüssel</label><input class="input font-mono text-[13px]" type="password" name="stripe_secret_key" autocomplete="new-password" placeholder="<?= s_val('pay', 'stripe_secret_key') !== '' ? '•••••••• (gespeichert)' : 'sk_test_… oder sk_live_…' ?>"></div>
            <div><label class="label">Webhook-Signing-Secret</label><input class="input font-mono text-[13px]" type="password" name="stripe_webhook_secret" autocomplete="new-password" placeholder="<?= s_val('pay', 'stripe_webhook_secret') !== '' ? '•••••••• (gespeichert)' : 'whsec_… (kann nachgetragen werden)' ?>"></div>
          </div>
          <div class="mt-2 flex flex-wrap items-center gap-3"><button type="button" class="btn btn-secondary" data-test-pay="stripe"><i class="bx bx-plug"></i>Verbindung testen</button><p class="text-sm" data-pay-result="stripe" aria-live="polite"></p></div>
          <p class="hint">Den Webhook legen Sie nach der Installation im Stripe-Dashboard an: <span class="font-mono"><?= s_e(s_val('app', 'base_url')) ?>/webhooks/stripe</span> – die Ereignisse und das Secret zeigt Ihnen der Abschluss-Bildschirm.</p>
        </div>
      </div>

    <?php elseif ($step === 5): ?>
      <h1 class="text-xl font-bold text-slate-900">E-Mail-Versand</h1>
      <p class="mt-1 text-sm text-slate-500">Für Offerten, Anmeldelinks und Benachrichtigungen. In Plesk finden Sie die Daten unter <em>E-Mail → Postfach → E-Mail-Client konfigurieren</em>.</p>
      <div class="mt-6 grid gap-4 sm:grid-cols-6">
        <div class="sm:col-span-6"><label class="label">Versandart</label><select class="input" name="mail_transport">
          <?php foreach (['smtp' => 'SMTP (empfohlen)', 'phpmail' => 'PHP mail() des Servers', 'log' => 'Nur in Datei protokollieren (Test)'] as $k => $l): ?><option value="<?= $k ?>"<?= s_val('mail', 'mail_transport', 'smtp') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
        <div class="sm:col-span-3"><label class="label">SMTP-Server</label><input class="input" name="smtp_host" value="<?= s_e(s_val('mail', 'smtp_host')) ?>" placeholder="mail.ihredomain.ch"></div>
        <div class="sm:col-span-1"><label class="label">Port</label><input class="input" type="number" name="smtp_port" value="<?= s_e(s_val('mail', 'smtp_port', '587')) ?>"></div>
        <div class="sm:col-span-2"><label class="label">Verschlüsselung</label><select class="input" name="smtp_encryption"><?php foreach (['tls' => 'STARTTLS (587)', 'ssl' => 'SSL/TLS (465)', 'none' => 'Keine'] as $k => $l): ?><option value="<?= $k ?>"<?= s_val('mail', 'smtp_encryption', 'tls') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
        <div class="sm:col-span-3"><label class="label">Benutzername</label><input class="input" name="smtp_user" value="<?= s_e(s_val('mail', 'smtp_user')) ?>" autocomplete="off"></div>
        <div class="sm:col-span-3"><label class="label">Passwort</label><input class="input" type="password" name="smtp_pass" placeholder="<?= s_val('mail', 'smtp_pass') !== '' ? '•••••••• (gespeichert)' : '' ?>" autocomplete="new-password"></div>
        <div class="sm:col-span-3"><label class="label">Absenderadresse</label><input class="input" type="email" name="mail_from_email" value="<?= s_e(s_val('mail', 'mail_from_email', s_val('app', 'company_email'))) ?>"></div>
        <div class="sm:col-span-3"><label class="label">Absendername</label><input class="input" name="mail_from_name" value="<?= s_e(s_val('mail', 'mail_from_name', s_val('app', 'company_name'))) ?>"></div>
        <div class="sm:col-span-6"><label class="label">Benachrichtigungen an (Team)</label><input class="input" type="email" name="notify_email" value="<?= s_e(s_val('mail', 'notify_email', s_val('app', 'company_email'))) ?>"><p class="hint">Erhält Meldungen bei Annahme/Ablehnung von Offerten und bei Online-Zahlungen (zusätzlich die zuständige Person).</p></div>
        <div class="flex flex-col gap-2 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200/70 sm:col-span-6 sm:flex-row sm:items-end">
          <div class="flex-1"><label class="label">Test-E-Mail an (optional)</label><input class="input" type="email" name="test_to" id="test_to" value="<?= s_e(s_val('app', 'company_email')) ?>"></div>
          <button class="btn btn-secondary" name="do_test" value="1" onclick="document.getElementById('test_to').required=true"><i class="bx bx-paper-plane"></i>Test senden</button>
        </div>
      </div>

    <?php elseif ($step === 6): ?>
      <h1 class="text-xl font-bold text-slate-900">Administrator &amp; Team</h1>
      <p class="mt-1 text-sm text-slate-500">Das Passwort wird mit <?= defined('PASSWORD_ARGON2ID') ? 'Argon2id' : 'bcrypt' ?> sicher gehasht. Optional können Sie unten gleich weitere Personen anlegen.</p>
      <div class="mt-6 grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2"><label class="label">Name</label><input class="input" name="admin_name" value="<?= s_e(s_val('admin', 'name')) ?>" required autocomplete="name"></div>
        <div class="sm:col-span-2"><label class="label">E-Mail</label><input class="input" type="email" name="admin_email" value="<?= s_e(s_val('admin', 'email', s_val('app', 'company_email'))) ?>" required autocomplete="email"></div>
        <div><label class="label">Passwort</label><input class="input" type="password" name="admin_password" minlength="10" required autocomplete="new-password"><p class="hint">Mind. 10 Zeichen, Buchstaben und Ziffern.</p></div>
        <div><label class="label">Passwort wiederholen</label><input class="input" type="password" name="admin_password2" minlength="10" required autocomplete="new-password"></div>
      </div>

      <h2 class="mt-8 flex items-center gap-2 text-base font-semibold text-slate-900"><i class="bx bx-group text-xl text-slate-400"></i>Weitere Benutzer <span class="text-sm font-normal text-slate-400">(optional)</span></h2>
      <p class="mt-1 text-sm text-slate-500"><strong>Team</strong>: Offerten, Aufträge, Rechnungen – ohne heikle Einstellungen. <strong>Administrator</strong>: voller Zugriff. Die Personen melden sich per Einladungslink an und setzen dann ihr Passwort.</p>
      <?php $team = $_SESSION['setup']['team'] ?? []; ?>
      <div class="mt-4 space-y-3">
        <?php for ($i = 0; $i < 3; $i++): $m = $team[$i] ?? ['name' => '', 'email' => '', 'role' => 'team']; ?>
        <div class="grid gap-3 sm:grid-cols-6">
          <input class="input sm:col-span-2" name="team_name[]" value="<?= s_e($m['name']) ?>" placeholder="Name" autocomplete="off" aria-label="Name Teammitglied <?= $i + 1 ?>">
          <input class="input sm:col-span-3" type="email" name="team_email[]" value="<?= s_e($m['email']) ?>" placeholder="E-Mail" autocomplete="off" aria-label="E-Mail Teammitglied <?= $i + 1 ?>">
          <select class="input" name="team_role[]" aria-label="Rolle"><option value="team"<?= $m['role'] === 'team' ? ' selected' : '' ?>>Team</option><option value="admin"<?= $m['role'] === 'admin' ? ' selected' : '' ?>>Admin</option></select>
        </div>
        <?php endfor; ?>
        <label class="flex cursor-pointer items-center gap-3 text-sm text-slate-700"><input type="checkbox" class="size-4 rounded accent-indigo-600" name="invite" value="1" <?= ($_SESSION['setup']['invite'] ?? '1') === '1' ? 'checked' : '' ?>>Einladung per E-Mail senden (benötigt funktionierenden E-Mail-Versand)</label>
      </div>

    <?php elseif ($step === 7): $o = $_SESSION['setup']['ops'] ?? []; $defYears = '10'; ?>
      <h1 class="text-xl font-bold text-slate-900">Datenschutz &amp; Betrieb</h1>
      <p class="mt-1 text-sm text-slate-500">Aufbewahrungsfristen und Kundenansicht. Die Vorgaben passen zum Schweizer DSG (und zur DSGVO) und lassen sich später ändern.</p>
      <div class="mt-6 grid gap-4 sm:grid-cols-6">
        <div class="sm:col-span-6"><label class="label">Kontakt für Datenschutzanfragen <span class="font-normal text-slate-400">(optional)</span></label><input class="input" type="email" name="privacy_contact" value="<?= s_e($o['privacy_contact'] ?? '') ?>" placeholder="Standard: Firmen-E-Mail"><p class="hint">Erscheint in der mitgelieferten Datenschutzerklärung (<span class="font-mono">/datenschutz</span>). Die Vorlage ersetzt keine Rechtsberatung.</p></div>
        <div class="sm:col-span-3"><label class="label">Aufbewahrung Rechnungen/Belege (Jahre)</label><input class="input" type="number" min="1" max="30" name="retention_years" value="<?= s_e($o['retention_years'] ?? $defYears) ?>"><p class="hint">In der Schweiz gelten 10 Jahre (Art. 958f OR, Art. 70 MWSTG).</p></div>
        <div class="sm:col-span-3"><label class="label">IP-Adressen speichern (Tage)</label><input class="input" type="number" min="1" max="365" name="ip_retention_days" value="<?= s_e($o['ip_retention_days'] ?? '90') ?>"></div>
        <div class="sm:col-span-3"><label class="label">E-Mail-Protokoll (Tage)</label><input class="input" type="number" min="30" max="3650" name="mail_log_retention_days" value="<?= s_e($o['mail_log_retention_days'] ?? '365') ?>"></div>
        <div class="sm:col-span-3"><label class="label">Audit-Log (Tage)</label><input class="input" type="number" min="30" max="3650" name="audit_retention_days" value="<?= s_e($o['audit_retention_days'] ?? '365') ?>"></div>
        <label class="flex cursor-pointer items-start gap-3 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200/70 sm:col-span-6"><input type="checkbox" class="mt-0.5 size-4 rounded accent-indigo-600" name="show_contact_person" value="1" <?= ($o['show_contact_person'] ?? '1') === '1' ? 'checked' : '' ?>><span class="text-sm text-slate-700">Ansprechperson für Kunden anzeigen<span class="block text-xs text-slate-400">Name der Person, die die Offerte ausgestellt hat, auf Offerte, PDF und im Kundenportal (keine E-Mail-Adresse).</span></span></label>
        <label class="flex cursor-pointer items-start gap-3 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200/70 sm:col-span-6"><input type="checkbox" class="mt-0.5 size-4 rounded accent-indigo-600" name="mail_attach_pdf" value="1" <?= ($o['mail_attach_pdf'] ?? '1') === '1' ? 'checked' : '' ?>><span class="text-sm text-slate-700">Offerten, Rechnungen und Zahlungsbestätigungen als PDF an E-Mails anhängen<span class="block text-xs text-slate-400">Das PDF enthält personenbezogene Daten. Für höchste Vertraulichkeit deaktivieren (dann nur Link).</span></span></label>
      </div>
      <div class="mt-6 grid gap-4 sm:grid-cols-6">
        <div class="sm:col-span-6"><h2 class="flex items-center gap-2 text-base font-semibold text-slate-900"><i class="bx bx-cloud-download text-xl text-slate-400"></i>Software-Updates</h2>
          <p class="mt-1 text-sm text-slate-500">Neue Versionen werden von der offiziellen Quelle geladen und per Klick (oder automatisch per Cronjob) eingespielt – mit Sicherung. Später unter <em>Update</em> änderbar.</p></div>
        <label class="flex cursor-pointer items-center gap-3 sm:col-span-6"><input type="checkbox" class="size-4 rounded accent-indigo-600" name="update_auto" value="1" <?= ($o['update_auto'] ?? '0') === '1' ? 'checked' : '' ?>><span class="text-sm text-slate-700">Updates automatisch installieren (benötigt den Cronjob)</span></label>
      </div>
      <div class="mt-6 rounded-xl bg-slate-50 p-4 text-sm text-slate-600 ring-1 ring-slate-200/70"><p class="flex gap-2"><i class="bx bx-time-five mt-0.5 text-lg text-slate-400"></i><span><strong>Cronjob:</strong> Für Abo-Rechnungen, Webhook-Wiederholungen und Aufbewahrungsfristen empfehlen wir einen täglichen Cronjob. Die genaue Angabe zeigt Ihnen der Abschluss-Bildschirm. Ohne Cronjob läuft die Prüfung automatisch beim Öffnen der Administration.</span></p></div>

    <?php elseif ($step === 8): $w = $_SESSION['setup']['site'] ?? []; $fl = $w['files'] ?? []; $co = s_val('app', 'company_name'); ?>
      <h1 class="text-xl font-bold text-slate-900">Auftritt &amp; Startseite</h1>
      <p class="mt-1 text-sm text-slate-500">Logo und Startseite Ihres Kundenportals. Alles lässt sich später unter <em>Webseite</em> und <em>Einstellungen → Branding</em> im Baukasten weiter anpassen.</p>
      <div class="mt-6 grid gap-4 sm:grid-cols-6">
        <div class="sm:col-span-3"><label class="label">Logo <span class="font-normal text-slate-400">(optional)</span></label>
          <?php if (!empty($fl['logo'])): ?><p class="mb-2 flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 text-xs text-emerald-700 ring-1 ring-slate-200/70"><span><i class="bx bx-check-circle align-middle"></i> Logo hochgeladen</span><label class="flex items-center gap-1.5 text-slate-600"><input type="checkbox" name="logo_remove" value="1">Entfernen</label></p><?php endif; ?>
          <input class="input" type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/gif"><p class="hint">PNG, JPG, WebP oder GIF, max. 1 MB.</p></div>
        <div class="sm:col-span-3"><label class="label">Favicon <span class="font-normal text-slate-400">(optional)</span></label>
          <?php if (!empty($fl['favicon'])): ?><p class="mb-2 flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 text-xs text-emerald-700 ring-1 ring-slate-200/70"><span><i class="bx bx-check-circle align-middle"></i> Favicon hochgeladen</span><label class="flex items-center gap-1.5 text-slate-600"><input type="checkbox" name="favicon_remove" value="1">Entfernen</label></p><?php endif; ?>
          <input class="input" type="file" name="favicon" accept="image/png,image/x-icon,image/vnd.microsoft.icon,image/jpeg,image/webp,image/gif"><p class="hint">PNG oder ICO, mindestens 32 × 32 Pixel.</p></div>
        <div class="sm:col-span-6"><label class="label">Untertitel in der Administration <span class="font-normal text-slate-400">(optional)</span></label><input class="input" name="brand_tagline" maxlength="60" value="<?= s_e($w['brand_tagline'] ?? '') ?>" placeholder="Offerten &amp; Aufträge"></div>
        <label class="flex cursor-pointer items-start gap-3 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200/70 sm:col-span-6"><input type="checkbox" class="mt-0.5 size-4 rounded accent-indigo-600" name="site_enabled" value="1" <?= ($w['site_enabled'] ?? '1') === '1' ? 'checked' : '' ?>><span class="text-sm text-slate-700"><strong class="block text-slate-900">Startseite aktivieren</strong>Besucher sehen eine Willkommensseite mit Kundenportal-Anmeldung. Ohne Häkchen leitet die Adresse direkt zum Portal weiter.</span></label>
        <div class="sm:col-span-6"><label class="label">Überschrift der Startseite</label><input class="input" name="site_headline" maxlength="160" value="<?= s_e($w['site_headline'] ?? '') ?>" placeholder="Ihre Offerten und Aufträge auf einen Blick."></div>
        <div class="sm:col-span-6"><label class="label">Einleitungstext</label><textarea class="input" name="site_subtitle" rows="3" maxlength="500" placeholder="Im Kundenportal von <?= s_e($co) ?> sehen Sie alle Offerten …"><?= s_e($w['site_subtitle'] ?? '') ?></textarea></div>
        <div class="sm:col-span-3"><label class="label">Seitentitel (Browser-Tab)</label><input class="input" name="site_title" maxlength="70" value="<?= s_e($w['site_title'] ?? '') ?>" placeholder="<?= s_e($co) ?>"></div>
        <div class="sm:col-span-3"><label class="label">Button in der Kopfzeile</label><input class="input" name="site_cta_label" maxlength="40" value="<?= s_e($w['site_cta_label'] ?? 'Offerte anfragen') ?>"><p class="hint">Öffnet eine E-Mail an Ihre Firmenadresse.</p></div>
        <label class="flex cursor-pointer items-start gap-3 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200/70 sm:col-span-3"><input type="checkbox" class="mt-0.5 size-4 rounded accent-indigo-600" name="site_index" value="1" <?= ($w['site_index'] ?? '0') === '1' ? 'checked' : '' ?>><span class="text-sm text-slate-700">Von Suchmaschinen indexieren lassen</span></label>
        <label class="flex cursor-pointer items-start gap-3 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200/70 sm:col-span-3"><input type="checkbox" class="mt-0.5 size-4 rounded accent-indigo-600" name="inquiry_enabled" value="1" <?= ($w['inquiry_enabled'] ?? '1') === '1' ? 'checked' : '' ?>><span><span class="block text-sm font-medium text-slate-900">Anfrageformular anzeigen</span><span class="block text-xs text-slate-500">Interessenten können direkt auf der Startseite eine Offerte anfragen (mit Spam- und Datenschutz-Schutz).</span></span></label>
        <label class="flex cursor-pointer items-start gap-3 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200/70 sm:col-span-3"><input type="checkbox" class="mt-0.5 size-4 rounded accent-indigo-600" name="inquiry_confirm_mail" value="1" <?= ($w['inquiry_confirm_mail'] ?? '1') === '1' ? 'checked' : '' ?>><span><span class="block text-sm font-medium text-slate-900">Eingangsbestätigung per E-Mail</span><span class="block text-xs text-slate-500">Die anfragende Person erhält automatisch eine kurze Bestätigung.</span></span></label>
        <label class="flex cursor-pointer items-start gap-3 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200/70 sm:col-span-3"><input type="checkbox" class="mt-0.5 size-4 rounded accent-indigo-600" name="site_admin_link" value="1" <?= ($w['site_admin_link'] ?? '0') === '1' ? 'checked' : '' ?>><span class="text-sm text-slate-700">Link «Administration» in der Fusszeile</span></label>
      </div>

    <?php elseif ($step === 9): $c = $_SESSION['setup']; ?>
      <h1 class="text-xl font-bold text-slate-900">Bereit zur Installation</h1>
      <p class="mt-1 text-sm text-slate-500">Bitte prüfen Sie die Angaben. Mit „Jetzt installieren“ werden Tabellen erstellt, Grunddaten angelegt und die Konfiguration gespeichert.</p>
      <dl class="mt-6 divide-y divide-slate-100 rounded-xl text-sm ring-1 ring-slate-200">
        <?php foreach ([
            'Datenbank' => $c['db']['user'] . '@' . $c['db']['host'] . ':' . $c['db']['port'] . ' / ' . $c['db']['name'],
            'Basis-URL' => $c['app']['base_url'],
            'Firma' => $c['app']['company_name'] . ' · ' . $c['app']['company_email'],
            'Währung & MWST' => $c['app']['currency'] . ' · ' . (($c['app']['mwst_liable'] ?? '1') === '1' ? 'MWST-pflichtig (' . (($c['app']['mwst_method'] ?? 'agreed') === 'received' ? 'vereinnahmt' : 'vereinbart') . ')' : 'nicht MWST-pflichtig') . (($c['app']['company_vat_id'] ?? '') !== '' ? ' · ' . $c['app']['company_vat_id'] : ''),
            'E-Mail' => ($c['mail']['mail_transport'] ?? 'smtp') === 'smtp' ? (($c['mail']['smtp_host'] ?? '') ?: 'später konfigurieren') : ($c['mail']['mail_transport'] ?? ''),
            'Nummernkreise' => ($c['doc']['offer_prefix'] ?? 'OF') . ' / ' . ($c['doc']['order_prefix'] ?? 'AU') . ' / ' . ($c['doc']['invoice_prefix'] ?? 'RE') . ' · IBAN: ' . (($c['doc']['invoice_iban'] ?? '') ?: 'keine'),
            'Online-Zahlung' => implode(', ', array_filter([($c['pay']['pay_mollie_enabled'] ?? '0') === '1' ? 'Mollie' : '', ($c['pay']['pay_stripe_enabled'] ?? '0') === '1' ? 'Stripe' : ''])) ?: 'nicht aktiviert',
            'Administrator' => $c['admin']['name'] . ' · ' . $c['admin']['email'],
            'Weitere Benutzer' => $c['team'] ? implode(', ', array_map(static fn ($m) => $m['name'] . ' (' . ($m['role'] === 'admin' ? 'Admin' : 'Team') . ')', $c['team'])) : 'keine',
            'Auftritt' => (($c['site']['site_enabled'] ?? '1') === '1' ? 'Startseite aktiv' : 'Startseite aus (Weiterleitung zum Portal)') . ' · Logo: ' . (!empty($c['site']['files']['logo']) ? 'ja' : 'nein') . ' · Anfrageformular: ' . (($c['site']['inquiry_enabled'] ?? '1') === '1' ? 'ja' : 'nein'),
            'Aufbewahrung' => 'Belege ' . ($c['ops']['retention_years'] ?? '10') . ' Jahre · IP ' . ($c['ops']['ip_retention_days'] ?? '90') . ' Tage',
        ] as $k => $val): ?>
          <div class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:justify-between"><dt class="text-slate-500"><?= $k ?></dt><dd class="font-medium text-slate-900 break-all sm:text-right"><?= s_e($val) ?></dd></div>
        <?php endforeach; ?>
      </dl>
    <?php endif; ?>
    </div>
    <div class="flex items-center justify-between gap-3 rounded-b-2xl bg-slate-50/70 px-6 py-4 sm:px-8">
      <?php if ($step > 1): ?><button class="btn btn-ghost" name="action" value="back" formnovalidate><i class="bx bx-chevron-left"></i>Zurück</button><?php else: ?><span></span><?php endif; ?>
      <div class="flex gap-2">
        <?php if ($step === 5): ?><button class="btn btn-ghost" name="skip" value="1" formnovalidate onclick="document.getElementById('test_to').value=''">Später konfigurieren</button><?php endif; ?>
        <button class="btn btn-primary" data-main onclick="<?= $step === 5 ? "document.getElementById('test_to').value='';" : '' ?>"><?= $step === 9 ? '<i class="bx bx-rocket"></i>Jetzt installieren' : 'Weiter<i class="bx bx-chevron-right"></i>' ?></button>
      </div>
    </div>
  </form>
  <?php endif; ?>
  </div>
  <p class="mt-6 text-center text-xs text-slate-400">VylarisOffers <?= APP_VERSION ?> · PHP <?= PHP_VERSION ?></p>
</div>
<script>
document.getElementById('setup-form')?.addEventListener('submit', (e) => { const b = e.submitter; if (b && b.dataset.main !== undefined) b.classList.add('is-loading'); });
document.querySelectorAll('[data-test-pay]').forEach((btn) => btn.addEventListener('click', async () => {
  const prov = btn.dataset.testPay, out = document.querySelector('[data-pay-result="' + prov + '"]'), f = document.getElementById('setup-form');
  const data = new FormData(f); data.set('action', 'test_pay'); data.set('provider', prov);
  btn.disabled = true; out.className = 'text-sm text-slate-500'; out.textContent = 'Verbinde …';
  try {
    const r = await fetch('setup.php', { method: 'POST', body: data }); const j = await r.json();
    out.className = 'text-sm font-medium ' + (j.ok ? 'text-emerald-600' : 'text-rose-600');
    out.textContent = j.message;
  } catch (e) { out.className = 'text-sm text-rose-600'; out.textContent = 'Test fehlgeschlagen.'; }
  btn.disabled = false;
}));
document.getElementById('test-db')?.addEventListener('click', async (ev) => {
  const btn = ev.currentTarget, out = document.getElementById('test-result'), f = document.getElementById('setup-form');
  const data = new FormData(f); data.set('action', 'test_db');
  btn.disabled = true; out.className = 'text-sm text-slate-500'; out.textContent = 'Verbinde …';
  try {
    const r = await fetch('setup.php', { method: 'POST', body: data }); const j = await r.json();
    out.className = 'text-sm font-medium ' + (j.ok ? 'text-emerald-600' : 'text-rose-600');
    out.innerHTML = '<i class="bx ' + (j.ok ? 'bxs-check-circle' : 'bxs-x-circle') + ' align-middle text-lg"></i> ';
    out.append(document.createTextNode(j.message));
  } catch (e) { out.className = 'text-sm text-rose-600'; out.textContent = 'Test fehlgeschlagen.'; }
  btn.disabled = false;
});
</script>
</body>
</html>
