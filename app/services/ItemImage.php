<?php
/**
 * Bilder pro Position (Offerten, Rechnungen, Leistungsvorlagen).
 * Hochgeladene Bilder werden neu kodiert (JPEG, max. 900 px, ohne Metadaten) und unter
 * einem zufälligen 32-stelligen Namen in storage/uploads/items abgelegt.
 */
declare(strict_types=1);

final class ItemImage
{
    public const MAX_PX = 900;
    public const MAX_BYTES = 8 * 1024 * 1024;

    public static function valid(?string $name): bool
    {
        return is_string($name) && preg_match('/^[a-f0-9]{32}$/', $name) === 1;
    }

    public static function path(string $name): string
    {
        return FileStore::dir('items') . '/' . $name . '.jpg';
    }

    public static function exists(?string $name): bool
    {
        return self::valid($name) && is_file(self::path((string) $name));
    }

    /** Prüft einen eingegebenen Namen: gültig und vorhanden, sonst null. */
    public static function clean(mixed $name): ?string
    {
        $n = is_string($name) ? trim($name) : '';
        return self::exists($n) ? $n : null;
    }

    public static function url(?string $name): ?string
    {
        return self::valid($name) ? url('item-img/' . $name) : null;
    }

    /** Verarbeitet eine Upload-Datei ($_FILES-Eintrag). Gibt den Namen zurück oder wirft RuntimeException. */
    public static function store(array $file): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            throw new RuntimeException('Der Upload ist fehlgeschlagen.');
        }
        if ((int) $file['size'] > self::MAX_BYTES) {
            throw new RuntimeException('Das Bild ist zu gross (max. 8 MB).');
        }
        return self::storeFile((string) $file['tmp_name']);
    }

    public static function storeFile(string $tmp): string
    {
        $info = @getimagesize($tmp);
        $loaders = [IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_GIF => 'imagecreatefromgif', IMAGETYPE_WEBP => 'imagecreatefromwebp'];
        if (!$info || !isset($loaders[$info[2]]) || !function_exists($loaders[$info[2]])) {
            throw new RuntimeException('Bitte ein Bild im Format JPG, PNG, WebP oder GIF wählen.');
        }
        if ($info[0] * $info[1] > 40_000_000) {
            throw new RuntimeException('Das Bild hat eine zu hohe Auflösung.');
        }
        $src = @$loaders[$info[2]]($tmp);
        if (!$src) {
            throw new RuntimeException('Das Bild konnte nicht gelesen werden.');
        }
        // EXIF-Ausrichtung berücksichtigen
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($tmp);
            $rot = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 0] ?? 0;
            if ($rot) {
                $r = imagerotate($src, $rot, 0);
                if ($r) {
                    $src = $r;
                }
            }
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, self::MAX_PX / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // Transparenz → weiss
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $name = bin2hex(random_bytes(16));
        $ok = imagejpeg($dst, self::path($name), 85);
        if (!$ok) {
            throw new RuntimeException('Das Bild konnte nicht gespeichert werden.');
        }
        @chmod(self::path($name), 0640);
        return $name;
    }

    /** Liefert das Bild aus (öffentlich; der Name ist nicht erratbar). */
    public static function send(string $name): void
    {
        if (!self::exists($name)) {
            http_response_code(404);
            exit;
        }
        $file = self::path($name);
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . filesize($file));
        header('Cache-Control: public, max-age=31536000, immutable');
        header('X-Content-Type-Options: nosniff');
        readfile($file);
        exit;
    }

    /** Entfernt Bilder, die nirgends mehr verwendet werden und älter als $days Tage sind. */
    public static function cleanup(int $days = 2): int
    {
        $dir = FileStore::dir('items');
        $used = [];
        foreach (['offer_items', 'invoice_items', 'item_templates'] as $t) {
            foreach (DB::all("SELECT DISTINCT image FROM $t WHERE image IS NOT NULL") as $r) {
                $used[$r['image']] = true;
            }
        }
        $n = 0;
        foreach (glob($dir . '/*.jpg') ?: [] as $f) {
            $name = basename($f, '.jpg');
            if (!isset($used[$name]) && filemtime($f) < time() - $days * 86400 && @unlink($f)) {
                $n++;
            }
        }
        return $n;
    }
}
