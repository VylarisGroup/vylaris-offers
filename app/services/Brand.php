<?php
declare(strict_types=1);

/**
 * Whitelabel: Produktname, Logo, Favicon und Untertitel der Administration sind frei wählbar.
 * Einzige feste Kennzeichnung ist der Hinweis POWERED_BY unten in der Seitenliste der Administration
 * (nicht abschaltbar, nicht in Kundenseiten, PDFs oder E-Mails).
 */
final class Brand
{
    public const POWERED_BY = 'Powered by VylarisOffers';
    public const DEFAULT_TAGLINE = 'Offerten & Aufträge';
    private const KINDS = ['logo' => 'brand_logo_file', 'favicon' => 'brand_favicon_file'];
    private const MIME = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif', 'image/x-icon' => 'ico', 'image/vnd.microsoft.icon' => 'ico'];
    private const MAX_BYTES = 1024 * 1024;

    /** Anzeigename des Systems (Administration, Login, 2FA-Aussteller, Backups). */
    public static function name(): string
    {
        $n = trim((string) Settings::get('brand_name', ''));
        return $n !== '' ? $n : (string) Settings::get('company_name', 'Meine Firma');
    }

    public static function tagline(): string
    {
        $t = trim((string) Settings::get('brand_tagline', ''));
        return $t !== '' ? $t : self::DEFAULT_TAGLINE;
    }

    /** Dateisicherer Kurzname, z. B. für Backups oder User-Agent. */
    public static function slug(): string
    {
        $s = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', self::asciiName()), '-'));
        return $s !== '' ? $s : 'app';
    }

    public static function userAgent(): string
    {
        return self::slug() . '/' . APP_VERSION;
    }

    private static function asciiName(): string
    {
        $n = self::name();
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $n);
        return $t !== false && $t !== '' ? $t : $n;
    }

    /** URL des Logos: hochgeladene Datei, sonst die Logo-URL der Firma, sonst leer. */
    public static function logoUrl(): string
    {
        $f = self::file('logo');
        if ($f !== null) {
            return url('brand/logo') . '?v=' . filemtime($f);
        }
        return (string) Settings::get('company_logo_url', '');
    }

    public static function faviconUrl(): string
    {
        $f = self::file('favicon');
        return $f !== null ? url('brand/favicon') . '?v=' . filemtime($f) : '';
    }

    public static function hasUpload(string $kind): bool
    {
        return self::file($kind) !== null;
    }

    /** Dateipfad des hochgeladenen Logos (für PDF) oder null. */
    public static function logoPath(): ?string
    {
        return self::file('logo');
    }

    private static function file(string $kind): ?string
    {
        $name = (string) Settings::get(self::KINDS[$kind] ?? '', '');
        if ($name === '' || !preg_match('/^[a-z]+-[0-9a-f]{8}\.(png|jpg|webp|gif|ico)$/', $name)) {
            return null;
        }
        $path = FileStore::dir('brand') . '/' . $name;
        return is_file($path) ? $path : null;
    }

    /** Speichert ein hochgeladenes Bild. Gibt den neuen Dateinamen zurück. */
    public static function store(string $kind, array $file): string
    {
        if (!isset(self::KINDS[$kind])) {
            throw new ValidationException('Unbekannter Bildtyp.');
        }
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            throw new ValidationException($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE ? 'Die Bilddatei ist zu gross (max. 1 MB).' : 'Das Bild konnte nicht hochgeladen werden.');
        }
        $tmp = (string) $file['tmp_name'];
        if (!is_uploaded_file($tmp) || (int) filesize($tmp) > self::MAX_BYTES || (int) filesize($tmp) < 16) {
            throw new ValidationException('Die Bilddatei ist ungültig oder grösser als 1 MB.');
        }
        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $ext = self::MIME[$mime] ?? null;
        if ($ext === null || ($ext === 'ico' && $kind !== 'favicon')) {
            throw new ValidationException('Erlaubt sind PNG, JPG, WebP und GIF' . ($kind === 'favicon' ? ' sowie ICO' : '') . '. SVG wird aus Sicherheitsgründen nicht akzeptiert.');
        }
        if ($ext !== 'ico') {
            $info = @getimagesize($tmp);
            if (!$info || $info[0] < 16 || $info[1] < 16 || $info[0] > 4000 || $info[1] > 4000) {
                throw new ValidationException('Das Bild muss zwischen 16 und 4000 Pixel gross sein.');
            }
        }
        $name = $kind . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (!move_uploaded_file($tmp, FileStore::dir('brand') . '/' . $name)) {
            throw new ValidationException('Das Bild konnte nicht gespeichert werden.');
        }
        self::remove($kind);
        return $name;
    }

    /** Löscht die aktuell gespeicherte Datei (Einstellung wird vom Aufrufer gesetzt). */
    public static function remove(string $kind): void
    {
        $f = self::file($kind);
        if ($f !== null) {
            @unlink($f);
        }
    }

    public static function settingKey(string $kind): string
    {
        return self::KINDS[$kind];
    }

    /** Liefert Logo/Favicon aus. */
    public static function send(string $kind): never
    {
        $f = self::file($kind);
        if ($f === null) {
            throw new HttpException(404, 'Nicht gefunden.');
        }
        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($f);
        header('Content-Type: ' . (isset(self::MIME[$mime]) ? $mime : 'application/octet-stream'));
        header('X-Content-Type-Options: nosniff');
        header('Content-Security-Policy: default-src \'none\'; sandbox');
        header('Cache-Control: public, max-age=86400');
        header('Content-Length: ' . filesize($f));
        readfile($f);
        exit;
    }
}
