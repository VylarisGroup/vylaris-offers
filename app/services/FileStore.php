<?php
/**
 * Dateiablage ausserhalb des Web-Roots-Zugriffs (storage/uploads, per .htaccess gesperrt):
 * Unterschriften (PNG) und Anhänge.
 */
declare(strict_types=1);

final class FileStore
{
    public const ALLOWED = [
        'pdf' => ['application/pdf'],
        'png' => ['image/png'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'gif' => ['image/gif'], 'webp' => ['image/webp'],
        'txt' => ['text/plain'], 'csv' => ['text/csv', 'text/plain', 'application/csv'],
        'doc' => ['application/msword', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'xls' => ['application/vnd.ms-excel', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/octet-stream'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'],
        'odt' => ['application/vnd.oasis.opendocument.text', 'application/zip'], 'ods' => ['application/vnd.oasis.opendocument.spreadsheet', 'application/zip'],
        'zip' => ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
    ];
    public const MAX_BYTES = 10 * 1024 * 1024;

    public static function dir(string $sub = ''): string
    {
        $dir = APP_ROOT . '/storage/uploads' . ($sub !== '' ? '/' . $sub : '');
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Upload-Ordner konnte nicht erstellt werden.');
        }
        return $dir;
    }

    /** Speichert eine Unterschrift (PNG-Data-URL aus dem Canvas). Gibt den Dateinamen zurück oder null. */
    public static function saveSignature(string $dataUrl): ?string
    {
        if (!preg_match('#^data:image/png;base64,([A-Za-z0-9+/=\s]+)$#', $dataUrl, $m)) {
            return null;
        }
        $bin = base64_decode(preg_replace('/\s+/', '', $m[1]) ?? '', true);
        if ($bin === false || strlen($bin) < 300 || strlen($bin) > 400 * 1024 || !str_starts_with($bin, "\x89PNG\r\n\x1a\n")) {
            return null;
        }
        $info = @getimagesizefromstring($bin);
        if (!$info || $info[2] !== IMAGETYPE_PNG || $info[0] > 1600 || $info[1] > 800 || $info[0] < 50 || $info[1] < 20) {
            return null;
        }
        // Leere Fläche zurückweisen (nur wenn GD vorhanden).
        if (function_exists('imagecreatefromstring')) {
            $im = @imagecreatefromstring($bin);
            if ($im) {
                $ink = 0;
                $w = imagesx($im);
                $h = imagesy($im);
                for ($y = 0; $y < $h; $y += 3) {
                    for ($x = 0; $x < $w; $x += 3) {
                        $rgb = imagecolorat($im, $x, $y);
                        if ((($rgb >> 16) & 255) < 200 && (($rgb >> 8) & 255) < 200 && ($rgb & 255) < 200) {
                            $ink++;
                        }
                    }
                }
                if ($ink < 12) {
                    return null;
                }
            }
        }
        $name = bin2hex(random_bytes(16)) . '.png';
        file_put_contents(self::dir('signatures') . '/' . $name, $bin);
        return $name;
    }

    public static function signaturePath(?string $name): ?string
    {
        if (!$name || !preg_match('/^[a-f0-9]{32}\.png$/', $name)) {
            return null;
        }
        $f = APP_ROOT . '/storage/uploads/signatures/' . $name;
        return is_file($f) ? $f : null;
    }

    public static function deleteSignature(?string $name): void
    {
        if ($p = self::signaturePath($name)) {
            @unlink($p);
        }
    }
}
