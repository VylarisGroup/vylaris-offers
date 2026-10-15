<?php
declare(strict_types=1);

/**
 * Website-Builder für die Startseite («Splash»): Seite = Liste von Blöcken, vollständig durch SCHEMA beschrieben.
 * Es wird nie HTML gespeichert – nur geprüfter Text, Links und Bilder. Entwurf und veröffentlichte Fassung
 * liegen als JSON in den Einstellungen (site_draft / site_published).
 */
final class SiteBuilder
{
    public const MAX_BLOCKS = 30;
    public const MAX_JSON = 262144;
    private const IMG_MIME = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    private const IMG_MAX = 2 * 1024 * 1024;

    /** Auswahl für Symbol-Felder (Boxicons, lokal eingebunden). */
    public const ICONS = [
        'bx-check-circle', 'bx-star', 'bx-heart', 'bx-bolt-circle', 'bx-shield-alt-2', 'bx-lock-alt', 'bx-time-five', 'bx-rocket',
        'bx-file', 'bx-receipt', 'bx-package', 'bx-car', 'bx-credit-card', 'bx-mobile-alt', 'bx-desktop', 'bx-cloud', 'bx-cog',
        'bx-support', 'bx-envelope', 'bx-phone', 'bx-map', 'bx-world', 'bx-trophy', 'bx-group', 'bx-user-check', 'bx-line-chart',
        'bx-bulb', 'bx-leaf', 'bx-gift', 'bx-badge-check', 'bx-edit', 'bx-search-alt', 'bx-cart', 'bx-store', 'bx-wrench', 'bx-palette',
    ];

    public const TYPES = [
        'hero' => 'Kopfbereich', 'features' => 'Vorteile', 'text' => 'Text', 'image_text' => 'Bild & Text',
        'steps' => 'Ablauf', 'faq' => 'Häufige Fragen', 'cta' => 'Handlungsaufruf', 'inquiry' => 'Anfrageformular', 'contact' => 'Kontakt',
    ];

    /**
     * Felder je Blocktyp. Art: text|long|link|image|icon|bool|enum|items.
     * Der Builder (JS) erzeugt sein Formular aus genau diesem Schema.
     */
    public static function schema(): array
    {
        $btn = fn(string $p = 'btn') => [
            [$p . '_label', 'text', 'Button-Text', 40],
            [$p . '_url', 'link', 'Button-Ziel', 300],
        ];
        return [
            'hero' => ['fields' => array_merge([
                ['eyebrow', 'text', 'Kleiner Titel über der Überschrift', 80],
                ['title', 'text', 'Überschrift', 160],
                ['subtitle', 'long', 'Untertext', 500],
            ], $btn('btn1'), $btn('btn2'), [
                ['bullets', 'items', 'Stichpunkte', 6, [['icon', 'icon', 'Symbol', 40], ['text', 'text', 'Text', 120]]],
                ['show_login', 'bool', 'Kundenportal-Anmeldung rechts anzeigen', 0],
                ['image', 'image', 'Bild (nur ohne Anmeldung)', 0],
                ['background', 'enum', 'Hintergrund', 0, ['soft' => 'Weich (Markenfarbe)', 'brand' => 'Markenfarbe', 'plain' => 'Schlicht']],
                ['align', 'enum', 'Ausrichtung', 0, ['left' => 'Links', 'center' => 'Zentriert']],
            ])],
            'features' => ['fields' => [
                ['title', 'text', 'Titel', 120], ['subtitle', 'long', 'Untertitel', 300],
                ['columns', 'enum', 'Spalten', 0, ['2' => '2', '3' => '3', '4' => '4']],
                ['items', 'items', 'Vorteile', 12, [['icon', 'icon', 'Symbol', 40], ['title', 'text', 'Titel', 80], ['text', 'long', 'Text', 300]]],
            ]],
            'text' => ['fields' => [
                ['title', 'text', 'Titel', 120], ['body', 'long', 'Text (Absätze mit Leerzeile)', 4000],
                ['align', 'enum', 'Ausrichtung', 0, ['left' => 'Links', 'center' => 'Zentriert']],
            ]],
            'image_text' => ['fields' => array_merge([
                ['title', 'text', 'Titel', 120], ['body', 'long', 'Text', 2000], ['image', 'image', 'Bild', 0],
                ['image_side', 'enum', 'Bild-Seite', 0, ['right' => 'Rechts', 'left' => 'Links']],
            ], $btn())],
            'steps' => ['fields' => [
                ['title', 'text', 'Titel', 120], ['subtitle', 'long', 'Untertitel', 300],
                ['items', 'items', 'Schritte', 8, [['title', 'text', 'Titel', 80], ['text', 'long', 'Text', 300]]],
            ]],
            'faq' => ['fields' => [
                ['title', 'text', 'Titel', 120],
                ['items', 'items', 'Fragen', 12, [['q', 'text', 'Frage', 200], ['a', 'long', 'Antwort', 1500]]],
            ]],
            'cta' => ['fields' => array_merge([
                ['title', 'text', 'Titel', 160], ['text', 'long', 'Text', 400],
                ['style', 'enum', 'Stil', 0, ['brand' => 'Markenfarbe', 'soft' => 'Hell']],
            ], $btn())],
            'inquiry' => ['fields' => [
                ['title', 'text', 'Titel', 120], ['text', 'long', 'Text', 500],
                ['ask_company', 'bool', 'Feld „Firma“ anzeigen', 0], ['ask_phone', 'bool', 'Feld „Telefon“ anzeigen', 0], ['ask_subject', 'bool', 'Feld „Betreff“ anzeigen', 0],
                ['submit_label', 'text', 'Button-Text', 40],
            ]],
            'contact' => ['fields' => [
                ['title', 'text', 'Titel', 120], ['text', 'long', 'Text', 500],
                ['show_address', 'bool', 'Adresse anzeigen', 0], ['show_email', 'bool', 'E-Mail anzeigen', 0], ['show_phone', 'bool', 'Telefon anzeigen', 0],
            ]],
        ];
    }

    /* ------------------------------------------------------------ Vorlage */

    public static function defaults(): array
    {
        $company = (string) Settings::get('company_name', 'Meine Firma');
        return [
            'enabled' => true,
            'seo' => ['title' => $company, 'description' => 'Ihre Offerten und Aufträge online – übersichtlich, sicher und jederzeit abrufbar.', 'index' => false],
            'nav' => ['show_portal' => true, 'cta_label' => 'Offerte anfragen', 'cta_url' => ''],
            'footer' => ['text' => '', 'show_admin_link' => false],
            'blocks' => [
                self::blk('hero', [
                    'eyebrow' => 'Sicherer Zugang ohne Passwort',
                    'title' => 'Ihre Offerten und Aufträge auf einen Blick.',
                    'subtitle' => 'Im Kundenportal von ' . $company . ' sehen Sie alle Offerten, den Bearbeitungsstand Ihrer Aufträge und – sobald verfügbar – Versand- und Lieferinformationen.',
                    'bullets' => [
                        ['icon' => 'bx-file', 'text' => 'Offerten ansehen, annehmen oder ablehnen'],
                        ['icon' => 'bx-package', 'text' => 'Auftragsstatus in Echtzeit verfolgen'],
                        ['icon' => 'bx-receipt', 'text' => 'Rechnungen einsehen und online bezahlen'],
                    ],
                    'show_login' => true, 'background' => 'soft', 'align' => 'left',
                ]),
                self::blk('features', [
                    'title' => 'Alles an einem Ort', 'subtitle' => 'Transparent, nachvollziehbar und immer aktuell.', 'columns' => '3',
                    'items' => [
                        ['icon' => 'bx-bolt-circle', 'title' => 'Schnell entscheiden', 'text' => 'Offerten online prüfen und mit einem Klick annehmen – auch von unterwegs.'],
                        ['icon' => 'bx-shield-alt-2', 'title' => 'Sicher & datenschutzkonform', 'text' => 'Anmeldung per Einmal-Link, Daten ausschliesslich auf Servern in Ihrer Region.'],
                        ['icon' => 'bx-support', 'title' => 'Persönlich betreut', 'text' => 'Bei Fragen erreichen Sie direkt Ihre Ansprechperson.'],
                    ],
                ]),
                self::blk('steps', [
                    'title' => 'So einfach funktioniert es', 'subtitle' => '',
                    'items' => [
                        ['title' => 'Anfrage', 'text' => 'Sie schildern uns Ihr Anliegen.'],
                        ['title' => 'Offerte', 'text' => 'Sie erhalten Ihre Offerte per E-Mail und im Portal.'],
                        ['title' => 'Auftrag & Lieferung', 'text' => 'Nach Ihrer Zusage verfolgen Sie den Fortschritt live.'],
                    ],
                ]),
                self::blk('inquiry', ['title' => 'Offerte anfragen', 'text' => 'Schildern Sie uns kurz Ihr Anliegen – wir melden uns mit einem passenden Angebot.', 'ask_company' => true, 'ask_phone' => true, 'ask_subject' => false, 'submit_label' => 'Anfrage senden']),
                self::blk('contact', ['title' => 'Fragen? Wir sind für Sie da.', 'text' => '', 'show_address' => true, 'show_email' => true, 'show_phone' => true]),
            ],
        ];
    }

    private static function blk(string $type, array $data): array
    {
        return ['id' => bin2hex(random_bytes(4)), 'type' => $type, 'visible' => true] + $data;
    }

    /* ------------------------------------------------------------ Laden / Speichern */

    public static function published(): array
    {
        return self::decode((string) Settings::get('site_published', '')) ?? self::sanitize(self::defaults());
    }

    public static function draft(): array
    {
        return self::decode((string) Settings::get('site_draft', '')) ?? self::published();
    }

    public static function hasDraftChanges(): bool
    {
        $d = (string) Settings::get('site_draft', '');
        return $d !== '' && $d !== (string) Settings::get('site_published', '');
    }

    private static function decode(string $json): ?array
    {
        if ($json === '') {
            return null;
        }
        $a = json_decode($json, true);
        return is_array($a) ? self::sanitize($a) : null;
    }

    public static function saveDraft(array $doc): array
    {
        $clean = self::sanitize($doc);
        Settings::set(['site_draft' => json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        return $clean;
    }

    public static function publish(): void
    {
        $d = json_encode(self::draft(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        Settings::set(['site_published' => $d, 'site_draft' => $d]);
        self::gc();
    }

    /** Zurück auf die Vorlage (Entwurf). Veröffentlicht wird erst nach «Veröffentlichen». */
    public static function resetDraft(): array
    {
        return self::saveDraft(self::defaults());
    }

    /* ------------------------------------------------------------ Prüfung */

    public static function sanitize(array $in): array
    {
        $def = self::defaults();
        $str = static fn(mixed $v, int $max): string => mb_substr(trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) (is_scalar($v) ? $v : '')) ?? ''), 0, $max);
        $long = static fn(mixed $v, int $max): string => mb_substr(trim(str_replace("\r\n", "\n", preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) (is_scalar($v) ? $v : '')) ?? '')), 0, $max);
        $seo = is_array($in['seo'] ?? null) ? $in['seo'] : [];
        $nav = is_array($in['nav'] ?? null) ? $in['nav'] : [];
        $foot = is_array($in['footer'] ?? null) ? $in['footer'] : [];
        $out = [
            'enabled' => !empty($in['enabled']),
            'seo' => ['title' => $str($seo['title'] ?? '', 70), 'description' => $str($seo['description'] ?? '', 200), 'index' => !empty($seo['index'])],
            'nav' => ['show_portal' => !empty($nav['show_portal']), 'cta_label' => $str($nav['cta_label'] ?? '', 40), 'cta_url' => self::cleanLink($nav['cta_url'] ?? '')],
            'footer' => ['text' => $str($foot['text'] ?? '', 200), 'show_admin_link' => !empty($foot['show_admin_link'])],
            'blocks' => [],
        ];
        if ($out['seo']['title'] === '') {
            $out['seo']['title'] = $def['seo']['title'];
        }
        $schema = self::schema();
        $seen = [];
        foreach (array_slice(is_array($in['blocks'] ?? null) ? $in['blocks'] : [], 0, self::MAX_BLOCKS) as $b) {
            if (!is_array($b) || !isset($schema[$b['type'] ?? ''])) {
                continue;
            }
            $id = preg_match('/^[a-f0-9]{8}$/', (string) ($b['id'] ?? '')) && !isset($seen[$b['id']]) ? $b['id'] : bin2hex(random_bytes(4));
            $seen[$id] = true;
            $blk = ['id' => $id, 'type' => $b['type'], 'visible' => !empty($b['visible'])];
            foreach ($schema[$b['type']]['fields'] as $f) {
                $blk[$f[0]] = self::cleanField($f, $b[$f[0]] ?? null, $str, $long);
            }
            $out['blocks'][] = $blk;
        }
        return $out;
    }

    private static function cleanField(array $f, mixed $v, callable $str, callable $long): mixed
    {
        [$key, $kind, , $max] = $f;
        return match ($kind) {
            'text' => $str($v, $max),
            'long' => $long($v, $max),
            'link' => self::cleanLink($v),
            'image' => self::cleanImage($v),
            'icon' => self::cleanIcon($v),
            'bool' => !empty($v),
            'enum' => array_key_exists((string) $v, $f[4]) ? (string) $v : (string) array_key_first($f[4]),
            'items' => (static function () use ($f, $v, $str, $long, $max) {
                $rows = [];
                foreach (array_slice(is_array($v) ? $v : [], 0, $max) as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $r = [];
                    foreach ($f[4] as $sf) {
                        $r[$sf[0]] = self::cleanField($sf, $row[$sf[0]] ?? null, $str, $long);
                    }
                    $rows[] = $r;
                }
                return $rows;
            })(),
            default => '',
        };
    }

    public static function cleanIcon(mixed $v): string
    {
        $v = (string) $v;
        return in_array($v, self::ICONS, true) ? $v : self::ICONS[0];
    }

    public static function cleanImage(mixed $v): string
    {
        return preg_match('/^[a-f0-9]{16}\.(png|jpg|webp|gif)$/', (string) $v) ? (string) $v : '';
    }

    /** Erlaubt: https://, http://, mailto:, tel:, #anker, interne Pfade (portal, datenschutz …). Alles andere (javascript:, data:) wird verworfen. */
    public static function cleanLink(mixed $v): string
    {
        $v = trim((string) (is_scalar($v) ? $v : ''));
        if ($v === '' || strlen($v) > 300 || preg_match('/[\x00-\x20<>"\'`\\\\]/', $v)) {
            return '';
        }
        if (preg_match('#^https?://[^\s/]+#i', $v) || preg_match('/^(mailto:[^\s@]+@[^\s@]+|tel:[+0-9()\-.]{3,30})$/i', $v) || preg_match('/^#[A-Za-z0-9_-]*$/', $v) || preg_match('#^/?[A-Za-z0-9_\-./]+(\?[A-Za-z0-9_=&%.\-]*)?(\#[A-Za-z0-9_-]+)?$#', $v)) {
            return $v;
        }
        return '';
    }

    /** Fertiger href für die Ausgabe (interne Pfade werden auf die Basis-URL bezogen). */
    public static function href(string $link): string
    {
        if ($link === '') {
            return '';
        }
        if (preg_match('#^(https?://|mailto:|tel:|\#)#i', $link)) {
            return $link;
        }
        return url(ltrim($link, '/'));
    }

    /* ------------------------------------------------------------ Bilder */

    public static function imageUrl(string $name): string
    {
        return $name === '' ? '' : url('site/img/' . pathinfo($name, PATHINFO_FILENAME));
    }

    public static function storeImage(array $file): string
    {
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new ValidationException('Das Bild konnte nicht hochgeladen werden (max. 2 MB).');
        }
        $tmp = (string) $file['tmp_name'];
        if (!is_uploaded_file($tmp) || filesize($tmp) > self::IMG_MAX || filesize($tmp) < 16) {
            throw new ValidationException('Das Bild ist ungültig oder grösser als 2 MB.');
        }
        $ext = self::IMG_MIME[(string) (new finfo(FILEINFO_MIME_TYPE))->file($tmp)] ?? null;
        $info = $ext ? @getimagesize($tmp) : false;
        if (!$ext || !$info || $info[0] < 16 || $info[1] < 16 || $info[0] > 6000 || $info[1] > 6000) {
            throw new ValidationException('Erlaubt sind PNG, JPG, WebP und GIF bis 6000 × 6000 Pixel. SVG wird nicht akzeptiert.');
        }
        $name = bin2hex(random_bytes(8)) . '.' . $ext;
        if (!move_uploaded_file($tmp, FileStore::dir('site') . '/' . $name)) {
            throw new ValidationException('Das Bild konnte nicht gespeichert werden.');
        }
        return $name;
    }

    public static function sendImage(string $name): never
    {
        // Die URL enthält nur die ID (ohne Endung); die Datei wird anhand der ID gefunden.
        $path = preg_match('/^[a-f0-9]{16}$/', $name) ? (glob(FileStore::dir('site') . '/' . $name . '.*')[0] ?? '') : '';
        if ($path === '' || self::cleanImage(basename($path)) === '' || !is_file($path)) {
            throw new HttpException(404, 'Nicht gefunden.');
        }
        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($path);
        header('Content-Type: ' . (isset(self::IMG_MIME[$mime]) ? $mime : 'application/octet-stream'));
        header('X-Content-Type-Options: nosniff');
        header('Content-Security-Policy: default-src \'none\'; sandbox');
        header('Cache-Control: public, max-age=604800, immutable');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    /** Löscht Bilder, die weder im Entwurf noch in der veröffentlichten Fassung vorkommen. */
    public static function gc(): void
    {
        $used = [];
        foreach ([(string) Settings::get('site_draft', ''), (string) Settings::get('site_published', '')] as $json) {
            if (preg_match_all('/[a-f0-9]{16}\.(?:png|jpg|webp|gif)/', $json, $m)) {
                $used += array_flip($m[0]);
            }
        }
        foreach (glob(FileStore::dir('site') . '/*') ?: [] as $f) {
            if (!isset($used[basename($f)]) && filemtime($f) < time() - 3600) {
                @unlink($f);
            }
        }
    }
}
