<?php
/**
 * Änderungsprotokoll (CHANGELOG.md im Installationsverzeichnis).
 * Wird mit jedem Update mitgeliefert und beim ersten Aufruf der Administration nach einem Update angezeigt.
 */
declare(strict_types=1);

final class Changelog
{
    private const MAX_SHOWN = 6;

    /**
     * Zerlegt Markdown in Versionen.
     * @return list<array{version:string, date:string, sections:array<string, list<string>>}>
     */
    public static function parse(string $md): array
    {
        $out = [];
        $cur = null;
        $sec = 'Neu';
        foreach (preg_split('/\R/', $md) ?: [] as $line) {
            if (preg_match('/^##\s+v?(\d+(?:\.\d+){0,3}(?:[-+][0-9A-Za-z.-]+)?)\s*(?:[–—-]\s*(.+?))?\s*$/u', $line, $m)) {
                if ($cur !== null) {
                    $out[] = $cur;
                }
                $cur = ['version' => $m[1], 'date' => trim($m[2] ?? ''), 'sections' => []];
                $sec = 'Neu';
            } elseif ($cur !== null && preg_match('/^###\s+(.+?)\s*$/u', $line, $m)) {
                $sec = $m[1];
            } elseif ($cur !== null && preg_match('/^\s*[-*]\s+(.+?)\s*$/u', $line, $m)) {
                $cur['sections'][$sec][] = $m[1];
            } elseif ($cur !== null && $cur['sections'] && preg_match('/^\s{2,}\S/', $line)) {
                // Fortsetzungszeile eines Aufzählungspunkts
                $last = array_key_last($cur['sections'][$sec] ?? []);
                if ($last !== null) {
                    $cur['sections'][$sec][$last] .= ' ' . trim($line);
                }
            }
        }
        if ($cur !== null) {
            $out[] = $cur;
        }
        return $out;
    }

    /** @return list<array{version:string, date:string, sections:array<string, list<string>>}> */
    public static function all(): array
    {
        $f = APP_ROOT . '/CHANGELOG.md';
        return is_file($f) ? self::parse((string) @file_get_contents($f)) : [];
    }

    /**
     * Einträge, die der Benutzer noch nicht gesehen hat (neuere Version als zuletzt bestätigt, höchstens die installierte).
     * Ohne bisherigen Stand wird nur die aktuelle Version angezeigt.
     */
    public static function pending(?string $seen, array $entries, string $current): array
    {
        $new = array_values(array_filter($entries, static fn ($e) => version_compare($e['version'], $current, '<=')
            && ($seen === null || $seen === '' || version_compare($e['version'], $seen, '>'))));
        if (($seen === null || $seen === '') && $new) {
            $new = [$new[0]];
        }
        return array_slice($new, 0, self::MAX_SHOWN);
    }

    public static function pendingFor(?array $user): array
    {
        if (!$user) {
            return [];
        }
        return self::pending($user['changelog_seen'] ?? null, self::all(), APP_VERSION);
    }

    public static function markSeen(int $userId): void
    {
        DB::run('UPDATE users SET changelog_seen = ? WHERE id = ?', [APP_VERSION, $userId]);
    }

    /** Sichere Darstellung: HTML wird maskiert, **fett** und `code` bleiben erhalten. */
    public static function inline(string $text): string
    {
        $h = e($text);
        $h = preg_replace('/\*\*(.+?)\*\*/u', '<strong class="font-semibold text-slate-900">$1</strong>', $h);
        $h = preg_replace('/`([^`]+)`/u', '<code class="rounded bg-slate-100 px-1 py-0.5 font-mono text-[12px] text-slate-700">$1</code>', (string) $h);
        return (string) $h;
    }

    /** Icon und Farbe je Abschnittstitel. */
    public static function style(string $section): array
    {
        return match (mb_strtolower($section)) {
            'neu'       => ['bx-star', 'emerald'],
            'verbessert' => ['bx-trending-up', 'sky'],
            'behoben'   => ['bx-wrench', 'amber'],
            'hinweis', 'wichtig' => ['bx-info-circle', 'rose'],
            default     => ['bx-chevron-right', 'slate'],
        };
    }
}
