<?php
/**
 * Dokumentvorlagen: Layout für PDF-Offerten und -Rechnungen (Farbe, Schrift, Logo, Tabellenstil, Fusszeile …).
 * Eine Vorlage ist reine Gestaltung – Beträge, Inhalte und Steuerlogik bleiben unverändert.
 */
declare(strict_types=1);

final class DocTemplate
{
    public const FONTS = ['helvetica' => 'Helvetica (Sans-Serif)', 'times' => 'Times (Serif)', 'courier' => 'Courier (Schreibmaschine)'];
    public const TABLE_STYLES = ['filled' => 'Gefüllte Kopfzeile', 'lines' => 'Kopfzeile mit Linie', 'minimal' => 'Minimal (nur Trennlinien)'];
    public const TYPES = ['all' => 'Offerten und Rechnungen', 'offer' => 'Nur Offerten', 'invoice' => 'Nur Rechnungen'];

    public const DEFAULTS = [
        'accent'         => '',            // leer = Markenfarbe
        'font'           => 'helvetica',
        'font_scale'     => 100,           // 90–110 %
        'margin'         => 20,            // 15–25 mm links/rechts
        'logo'           => 1,
        'logo_pos'       => 'left',
        'logo_height'    => 14,            // 8–30 mm
        'company_block'  => 'full',        // full | name | none
        'header_note'    => '',
        'table_style'    => 'filled',
        'total_style'    => 'tint',         // tint | solid
        'footer_text'    => '{firma} · {nummer}',
        'page_numbers'   => 1,
        'intro_default'  => '',
        'closing_default' => '',
    ];

    /** @return list<array> */
    public static function all(): array
    {
        return DB::all('SELECT * FROM doc_templates ORDER BY name');
    }

    public static function find(int $id): ?array
    {
        return DB::one('SELECT * FROM doc_templates WHERE id = ?', [$id]);
    }

    /** Vorlagen, die für den Typ ('offer' | 'invoice') wählbar sind. */
    public static function forType(string $type): array
    {
        return DB::all("SELECT * FROM doc_templates WHERE doc_type IN ('all', ?) ORDER BY name", [$type]);
    }

    public static function defaultFor(string $type): ?array
    {
        $col = $type === 'offer' ? 'is_default_offer' : 'is_default_invoice';
        return DB::one("SELECT * FROM doc_templates WHERE $col = 1 AND doc_type IN ('all', ?) LIMIT 1", [$type]);
    }

    /** Prüft eine gewählte Vorlage; ungültig/leer → null (= Standardvorlage). */
    public static function validId(string $type, mixed $id): ?int
    {
        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }
        return DB::val("SELECT id FROM doc_templates WHERE id = ? AND doc_type IN ('all', ?)", [$id, $type]) ? $id : null;
    }

    /** Vorlage für ein Dokument: gewählte, sonst Standard des Typs, sonst eingebaute Standardwerte. */
    public static function resolve(string $type, ?int $id): array
    {
        $row = ($id ? self::find($id) : null);
        if (!$row || !in_array($row['doc_type'], ['all', $type], true)) {
            $row = self::defaultFor($type);
        }
        return self::config($row);
    }

    /** Konfiguration einer Zeile, ergänzt um die Standardwerte und bereinigt. */
    public static function config(?array $row): array
    {
        $cfg = $row ? (json_decode((string) $row['config'], true) ?: []) : [];
        return self::sanitize(array_merge(self::DEFAULTS, is_array($cfg) ? $cfg : []));
    }

    /** Erzwingt gültige Werte (auch für Eingaben aus dem Formular). */
    public static function sanitize(array $in): array
    {
        $d = self::DEFAULTS;
        $out = [];
        $accent = trim((string) ($in['accent'] ?? ''));
        $out['accent'] = preg_match('/^#[0-9a-fA-F]{6}$/', $accent) ? strtolower($accent) : '';
        $out['font'] = isset(self::FONTS[$in['font'] ?? '']) ? $in['font'] : $d['font'];
        $out['font_scale'] = max(90, min(110, (int) ($in['font_scale'] ?? $d['font_scale'])));
        $out['margin'] = max(15, min(25, (int) ($in['margin'] ?? $d['margin'])));
        $out['logo'] = !empty($in['logo']) ? 1 : 0;
        $out['logo_pos'] = ($in['logo_pos'] ?? '') === 'right' ? 'right' : 'left';
        $out['logo_height'] = max(8, min(30, (int) ($in['logo_height'] ?? $d['logo_height'])));
        $out['company_block'] = in_array($in['company_block'] ?? '', ['full', 'name', 'none'], true) ? $in['company_block'] : 'full';
        $out['header_note'] = mb_substr(trim(preg_replace('/\s+/', ' ', (string) ($in['header_note'] ?? '')) ?? ''), 0, 160);
        $out['table_style'] = isset(self::TABLE_STYLES[$in['table_style'] ?? '']) ? $in['table_style'] : 'filled';
        $out['total_style'] = ($in['total_style'] ?? '') === 'solid' ? 'solid' : 'tint';
        $out['footer_text'] = mb_substr(trim(preg_replace('/\s+/', ' ', (string) ($in['footer_text'] ?? $d['footer_text'])) ?? ''), 0, 160);
        $out['page_numbers'] = !empty($in['page_numbers']) ? 1 : 0;
        $out['intro_default'] = mb_substr(trim((string) ($in['intro_default'] ?? '')), 0, 2000);
        $out['closing_default'] = mb_substr(trim((string) ($in['closing_default'] ?? '')), 0, 2000);
        return $out;
    }

    /** Fusszeile mit Platzhaltern {firma} {nummer} {uid} {datum}. */
    public static function footerLine(array $cfg, string $number): string
    {
        $uid = PdfService::uidLine();
        $line = strtr((string) $cfg['footer_text'], [
            '{firma}' => (string) Settings::get('company_name'), '{nummer}' => $number, '{uid}' => $uid, '{datum}' => date('d.m.Y'),
        ]);
        return trim(preg_replace('/(\s·\s)+$/u', '', $line) ?? $line);
    }

    /** Standardtexte der Vorlage für neue Dokumente (Einleitung/Schluss). */
    public static function defaultText(string $type, string $field): ?string
    {
        $tpl = self::defaultFor($type);
        $cfg = self::config($tpl);
        $v = (string) ($field === 'intro' ? $cfg['intro_default'] : $cfg['closing_default']);
        return $v !== '' ? $v : null;
    }

    /** Standardtext (Einleitung/Schluss) einer Vorlage – leer, wenn die Vorlage keinen vorsieht. */
    public static function textFor(string $type, ?int $id, string $field): ?string
    {
        $cfg = self::resolve($type, $id);
        $v = (string) ($field === 'intro' ? $cfg['intro_default'] : $cfg['closing_default']);
        return $v !== '' ? $v : null;
    }

    public static function save(?int $id, string $name, string $type, array $cfg): int
    {
        $name = mb_substr(trim($name), 0, 120);
        if ($name === '') {
            throw new ValidationException('Bitte geben Sie der Vorlage einen Namen.');
        }
        if (!isset(self::TYPES[$type])) {
            $type = 'all';
        }
        $row = ['name' => $name, 'doc_type' => $type, 'config' => json_encode(self::sanitize($cfg), JSON_UNESCAPED_UNICODE), 'updated_at' => now()];
        if ($id && self::find($id)) {
            DB::update('doc_templates', $row, 'id = :id', [':id' => $id]);
            // Standard-Markierung entfernen, wenn die Vorlage für den Typ nicht mehr passt
            if ($type === 'invoice') {
                DB::exec('UPDATE doc_templates SET is_default_offer = 0 WHERE id = ?', [$id]);
            } elseif ($type === 'offer') {
                DB::exec('UPDATE doc_templates SET is_default_invoice = 0 WHERE id = ?', [$id]);
            }
            return $id;
        }
        $row['created_at'] = now();
        return DB::insert('doc_templates', $row);
    }

    public static function setDefault(int $id, string $type): void
    {
        $tpl = self::find($id);
        if (!$tpl || !in_array($tpl['doc_type'], ['all', $type], true)) {
            throw new ValidationException('Diese Vorlage kann für diesen Dokumenttyp nicht Standard sein.');
        }
        $col = $type === 'offer' ? 'is_default_offer' : 'is_default_invoice';
        DB::tx(static function () use ($col, $id): void {
            DB::exec("UPDATE doc_templates SET $col = 0");
            DB::exec("UPDATE doc_templates SET $col = 1 WHERE id = ?", [$id]);
        });
    }

    public static function delete(int $id): void
    {
        $tpl = self::find($id);
        if (!$tpl) {
            return;
        }
        if ((int) DB::val('SELECT COUNT(*) FROM doc_templates') <= 1) {
            throw new ValidationException('Die letzte Vorlage kann nicht gelöscht werden.');
        }
        DB::tx(static function () use ($id, $tpl): void {
            DB::exec('DELETE FROM doc_templates WHERE id = ?', [$id]);
            DB::exec('UPDATE offers SET template_id = NULL WHERE template_id = ?', [$id]);
            DB::exec('UPDATE invoices SET template_id = NULL WHERE template_id = ?', [$id]);
            foreach (['offer' => 'is_default_offer', 'invoice' => 'is_default_invoice'] as $type => $col) {
                if ((int) $tpl[$col] === 1) {   // Standard nachrücken lassen
                    $next = DB::val("SELECT id FROM doc_templates WHERE doc_type IN ('all', ?) ORDER BY id LIMIT 1", [$type]);
                    if ($next) {
                        DB::exec("UPDATE doc_templates SET $col = 1 WHERE id = ?", [$next]);
                    }
                }
            }
        });
    }

    public static function usage(int $id): int
    {
        return (int) DB::val('SELECT (SELECT COUNT(*) FROM offers WHERE template_id = ?) + (SELECT COUNT(*) FROM invoices WHERE template_id = ?)', [$id, $id]);
    }

    /** Legt die mitgelieferten Vorlagen an, falls noch keine existiert (idempotent). */
    public static function seed(PDO $pdo): void
    {
        if ((int) $pdo->query('SELECT COUNT(*) FROM doc_templates')->fetchColumn() > 0) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $rows = [
            ['Standard', 'all', 1, 1, []],
            ['Elegant (Serifenschrift)', 'all', 0, 0, ['font' => 'times', 'table_style' => 'lines', 'total_style' => 'solid', 'logo_pos' => 'right']],
            ['Kompakt', 'all', 0, 0, ['font_scale' => 94, 'margin' => 16, 'table_style' => 'minimal', 'company_block' => 'name', 'logo_height' => 10]],
        ];
        $st = $pdo->prepare('INSERT INTO doc_templates (name, doc_type, is_default_offer, is_default_invoice, config, created_at) VALUES (?,?,?,?,?,?)');
        foreach ($rows as [$name, $type, $do, $di, $cfg]) {
            $st->execute([$name, $type, $do, $di, json_encode(self::sanitize(array_merge(self::DEFAULTS, $cfg)), JSON_UNESCAPED_UNICODE), $now]);
        }
    }
}
