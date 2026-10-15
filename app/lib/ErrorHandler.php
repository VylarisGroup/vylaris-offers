<?php
declare(strict_types=1);

final class ErrorHandler
{
    public static function log(Throwable $e): string
    {
        $id = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $line = sprintf(
            "[%s] #%s %s: %s in %s:%d\n%s\n\n",
            date('Y-m-d H:i:s'), $id, $e::class, $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString()
        );
        @file_put_contents(APP_ROOT . '/storage/logs/app.log', $line, FILE_APPEND | LOCK_EX);
        return $id;
    }

    public static function handle(Throwable $e): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if ($e instanceof HttpException) {
            $status = $e->status;
            $title = match ($status) {
                403 => 'Kein Zugriff',
                404 => 'Seite nicht gefunden',
                419 => 'Sitzung abgelaufen',
                429 => 'Zu viele Anfragen',
                default => 'Anfrage nicht möglich',
            };
            $message = $e->getMessage() ?: match ($status) {
                403 => 'Sie haben keine Berechtigung für diese Seite.',
                404 => 'Die gewünschte Seite existiert nicht oder wurde entfernt.',
                default => 'Die Anfrage konnte nicht verarbeitet werden.',
            };
            $ref = null;
        } else {
            $status = 500;
            $ref = self::log($e);
            $title = 'Ein unerwarteter Fehler ist aufgetreten';
            $message = 'Bitte versuchen Sie es später erneut. Falls das Problem bestehen bleibt, nennen Sie uns die Fehlernummer.';
            if ($e instanceof PDOException) {
                $message = 'Die Datenbank ist momentan nicht erreichbar oder hat einen Fehler gemeldet. ' . $message;
            }
        }

        if (!headers_sent()) {
            http_response_code($status);
        }

        if (PHP_SAPI !== 'cli' && function_exists('wants_json') && wants_json()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => $message, 'ref' => $ref], JSON_UNESCAPED_UNICODE);
            return;
        }

        $debug = function_exists('app_config') && app_config('app.debug') ? $e : null;
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $e . "\n");
            return;
        }
        try {
            echo View::render('errors/error', compact('status', 'title', 'message', 'ref', 'debug'), 'minimal');
        } catch (Throwable) {
            echo '<h1>' . htmlspecialchars($title) . '</h1><p>' . htmlspecialchars($message) . '</p>';
            if ($ref) {
                echo '<p>Fehlernummer: ' . htmlspecialchars($ref) . '</p>';
            }
        }
    }
}
