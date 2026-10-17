<?php
/** Anhänge an Offerten, Aufträgen und Rechnungen (Dateien in storage/uploads/files). */
declare(strict_types=1);

final class AttachmentService
{
    public const TYPES = ['offer', 'order', 'invoice'];

    public static function forEntity(string $type, int $id, bool $customerOnly = false): array
    {
        return DB::all(
            'SELECT * FROM attachments WHERE entity_type = ? AND entity_id = ?' . ($customerOnly ? ' AND customer_visible = 1' : '') . ' ORDER BY id',
            [$type, $id]
        );
    }

    public static function find(int $id): ?array
    {
        return DB::one('SELECT * FROM attachments WHERE id = ?', [$id]);
    }

    public static function entityExists(string $type, int $id): bool
    {
        $table = ['offer' => 'offers', 'order' => 'orders', 'invoice' => 'invoices'][$type] ?? null;
        return $table !== null && (bool) DB::val("SELECT 1 FROM `$table` WHERE id = ?", [$id]);
    }

    public static function path(array $att): string
    {
        return FileStore::dir('files') . '/' . $att['stored_name'];
    }

    /** @param array $file Element aus $_FILES */
    public static function add(string $type, int $id, array $file, bool $visible, array $user, bool $byCustomer = false): int
    {
        if (!in_array($type, self::TYPES, true) || !self::entityExists($type, $id)) {
            throw new ValidationException('Das Dokument wurde nicht gefunden.');
        }
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE) {
            throw new ValidationException('Bitte wählen Sie eine Datei aus.');
        }
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            throw new ValidationException('Die Datei ist grösser als erlaubt (max. ' . self::limitLabel() . ').');
        }
        if ($err !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            throw new ValidationException('Der Upload ist fehlgeschlagen. Bitte versuchen Sie es erneut.');
        }
        $size = (int) $file['size'];
        if ($size <= 0 || $size > FileStore::MAX_BYTES) {
            throw new ValidationException('Die Datei muss zwischen 1 Byte und ' . (FileStore::MAX_BYTES / 1048576) . ' MB gross sein.');
        }
        $orig = self::cleanName((string) $file['name']);
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!isset(FileStore::ALLOWED[$ext])) {
            throw new ValidationException('Dieser Dateityp ist nicht erlaubt (erlaubt: ' . implode(', ', array_keys(FileStore::ALLOWED)) . ').');
        }
        $mime = 'application/octet-stream';
        if (function_exists('finfo_open') && ($fi = finfo_open(FILEINFO_MIME_TYPE))) {
            $mime = (string) finfo_file($fi, (string) $file['tmp_name']);
            finfo_close($fi);
            if (!in_array($mime, FileStore::ALLOWED[$ext], true)) {
                throw new ValidationException('Der Inhalt der Datei passt nicht zur Endung .' . $ext . '.');
            }
        }
        $stored = bin2hex(random_bytes(20));
        if (!move_uploaded_file((string) $file['tmp_name'], FileStore::dir('files') . '/' . $stored)) {
            throw new ValidationException('Die Datei konnte nicht gespeichert werden.');
        }
        @chmod(FileStore::dir('files') . '/' . $stored, 0640);
        $attId = DB::insert('attachments', [
            'entity_type' => $type, 'entity_id' => $id, 'original_name' => $orig, 'stored_name' => $stored,
            'mime' => $mime, 'size' => $size, 'customer_visible' => $visible ? 1 : 0,
            'uploaded_by' => $byCustomer ? null : $user['id'], 'by_customer' => $byCustomer ? 1 : 0, 'created_at' => now(),
        ]);
        History::add($type, $id, 'note', null, null, ($byCustomer ? 'Datei vom Kunden hochgeladen: ' : 'Anhang hinzugefügt: ') . $orig . (!$byCustomer && $visible ? ' (für Kunden sichtbar)' : ''), $visible, $user, $byCustomer ? 'customer' : 'user');
        return $attId;
    }

    public static function delete(array $att, array $user): void
    {
        @unlink(self::path($att));
        DB::exec('DELETE FROM attachments WHERE id = ?', [$att['id']]);
        History::add($att['entity_type'], (int) $att['entity_id'], 'note', null, null, 'Anhang entfernt: ' . $att['original_name'], false, $user);
    }

    public static function purge(string $type, int $id): void
    {
        foreach (self::forEntity($type, $id) as $a) {
            @unlink(self::path($a));
        }
        DB::exec('DELETE FROM attachments WHERE entity_type = ? AND entity_id = ?', [$type, $id]);
    }

    public static function cleanName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1f\x7f"<>|:*?]+/u', '', $name) ?? '';
        $name = trim($name, ". \t");
        return mb_substr($name !== '' ? $name : 'datei', 0, 200);
    }

    public static function limitLabel(): string
    {
        $ini = (string) ini_get('upload_max_filesize');
        return $ini !== '' ? $ini : '10 MB';
    }

    public static function human(int $bytes): string
    {
        return $bytes >= 1048576 ? number_format($bytes / 1048576, 1) . ' MB' : max(1, (int) round($bytes / 1024)) . ' KB';
    }

    public static function send(array $att): never
    {
        $f = self::path($att);
        if (!is_file($f)) {
            throw new HttpException(404, 'Die Datei existiert nicht mehr.');
        }
        $fn = $att['original_name'];
        header('Content-Type: ' . ($att['mime'] ?: 'application/octet-stream'));
        header("Content-Disposition: attachment; filename=\"" . preg_replace('/[^A-Za-z0-9._-]/', '_', $fn) . "\"; filename*=UTF-8''" . rawurlencode($fn));
        header('Content-Length: ' . filesize($f));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($f);
        exit;
    }
}
