<?php
/**
 * Bootstrap: Konfiguration, Autoloader, Fehlerbehandlung, Session.
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_DIR', __DIR__);
define('APP_VERSION', '1.6.1');

// Mitgelieferte Bibliotheken (kein Composer nötig): FPDF und chillerlan/php-qrcode (MIT).
spl_autoload_register(static function (string $class): void {
    foreach (['chillerlan\\QRCode\\' => 'chillerlan/qrcode', 'chillerlan\\Settings\\' => 'chillerlan/settings'] as $ns => $dir) {
        if (str_starts_with($class, $ns)) {
            $file = APP_DIR . '/vendor/' . $dir . '/' . str_replace('\\', '/', substr($class, strlen($ns))) . '.php';
            if (is_file($file)) {
                require $file;
            }
            return;
        }
    }
});

spl_autoload_register(static function (string $class): void {
    foreach (['lib', 'services', 'controllers'] as $dir) {
        $file = APP_DIR . '/' . $dir . '/' . $class . '.php';
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});

require APP_DIR . '/lib/helpers.php';

$configFile = APP_DIR . '/config.php';
if (!is_file($configFile)) {
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    header('Location: ' . $dir . '/setup.php');
    exit;
}

$GLOBALS['APP_CONFIG'] = require $configFile;

date_default_timezone_set((string) app_config('app.timezone', 'Europe/Zurich'));
mb_internal_encoding('UTF-8');

error_reporting(E_ALL);
ini_set('display_errors', app_config('app.debug') ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', APP_ROOT . '/storage/logs/php-error.log');

set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): bool {
    if (!(error_reporting() & $severity) || in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
set_exception_handler([ErrorHandler::class, 'handle']);

if (PHP_SAPI !== 'cli') {
    start_session();
    send_security_headers();
}
