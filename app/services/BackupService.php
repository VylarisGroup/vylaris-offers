<?php
/** Datenbank-Backup (reines PHP, ohne mysqldump) – nur für Administratoren, jeder Download wird protokolliert. */
declare(strict_types=1);

final class BackupService
{
    /** Tabellen ohne Backup-Wert (kurzlebige Sicherheitsdaten). */
    private const SKIP = ['rate_limits', 'login_tokens', 'signature_codes'];

    /** Schreibt einen SQL-Dump in die Datei (Pfad) und gibt die Anzahl Zeilen zurück. */
    public static function dumpTo(string $file): int
    {
        $pdo = DB::pdo();
        $fh = gzopen($file, 'wb6');
        if (!$fh) {
            throw new RuntimeException('Backup-Datei konnte nicht erstellt werden.');
        }
        $rows = 0;
        gzwrite($fh, "-- " . Brand::name() . " Backup · " . date('c') . "\n-- Enthält personenbezogene Daten – vertraulich behandeln.\n"
            . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");
        foreach ($pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(PDO::FETCH_NUM) as [$table]) {
            if (in_array($table, self::SKIP, true)) {
                continue;
            }
            $create = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '', $table) . '`')->fetch(PDO::FETCH_NUM)[1];
            gzwrite($fh, "DROP TABLE IF EXISTS `$table`;\n$create;\n\n");
            $st = $pdo->query('SELECT * FROM `' . str_replace('`', '', $table) . '`', PDO::FETCH_NUM);
            $batch = [];
            while ($r = $st->fetch(PDO::FETCH_NUM)) {
                $batch[] = '(' . implode(',', array_map(static fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $r)) . ')';
                $rows++;
                if (count($batch) >= 200) {
                    gzwrite($fh, "INSERT INTO `$table` VALUES\n" . implode(",\n", $batch) . ";\n");
                    $batch = [];
                }
            }
            if ($batch) {
                gzwrite($fh, "INSERT INTO `$table` VALUES\n" . implode(",\n", $batch) . ";\n");
            }
            gzwrite($fh, "\n");
        }
        gzwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
        gzclose($fh);
        return $rows;
    }

    public static function download(bool $withFiles): never
    {
        @set_time_limit(300);
        Settings::set(['last_backup_at' => (string) time()]);
        $dir = sys_get_temp_dir();
        $sql = tempnam($dir, 'om_sql_');
        $stamp = date('Ymd-His');
        try {
            self::dumpTo($sql);
            if ($withFiles && class_exists('ZipArchive')) {
                $zipPath = tempnam($dir, 'om_zip_');
                $zip = new ZipArchive();
                $zip->open($zipPath, ZipArchive::OVERWRITE);
                $zip->addFile($sql, "datenbank-$stamp.sql.gz");
                foreach (['files', 'signatures'] as $sub) {
                    $base = APP_ROOT . '/storage/uploads/' . $sub;
                    foreach (is_dir($base) ? (scandir($base) ?: []) : [] as $f) {
                        if ($f !== '.' && $f !== '..' && is_file("$base/$f")) {
                            $zip->addFile("$base/$f", "uploads/$sub/$f");
                        }
                    }
                }
                $zip->close();
                self::send($zipPath, Brand::slug() . "-backup-$stamp.zip", 'application/zip');
            }
            self::send($sql, Brand::slug() . "-backup-$stamp.sql.gz", 'application/gzip');
        } finally {
            @unlink($sql);
        }
    }

    private static function send(string $path, string $name, string $mime): never
    {
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, no-store');
        readfile($path);
        @unlink($path);
        exit;
    }
}
