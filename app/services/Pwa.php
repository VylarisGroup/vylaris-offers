<?php
/**
 * Progressive Web App: Manifest, Service Worker, Icons und Offline-Seite.
 * Aktivierbar unter Einstellungen → Branding. Zwei Apps: Administration (/admin) und Kundenportal (/portal).
 * Der Service Worker legt ausschliesslich statische Dateien (assets/) und das Icon im Cache ab –
 * nie Seiten, Dokumente oder Kundendaten.
 */
declare(strict_types=1);

final class Pwa
{
    public const SIZES = [48, 72, 96, 144, 167, 180, 192, 256, 384, 512];

    public static function enabled(): bool
    {
        return (string) Settings::get('pwa_enabled', '1') !== '0';
    }

    public static function shortName(string $app): string
    {
        $n = trim((string) Settings::get('pwa_short_name', ''));
        if ($n === '') {
            $n = $app === 'portal' ? (string) Settings::get('company_name') : Brand::name();
        }
        $n = mb_substr($n, 0, 24);
        return $n !== '' ? $n : 'Offerten';
    }

    public static function manifest(string $app): array
    {
        $portal = $app === 'portal';
        $color = self::color();
        $icons = [];
        foreach ([192, 512] as $s) {
            $icons[] = ['src' => url('pwa/icon/' . $s) . '?v=' . self::iconVersion(), 'sizes' => $s . 'x' . $s, 'type' => 'image/png', 'purpose' => 'any'];
            $icons[] = ['src' => url('pwa/icon/' . $s) . '?m=1&v=' . self::iconVersion(), 'sizes' => $s . 'x' . $s, 'type' => 'image/png', 'purpose' => 'maskable'];
        }
        return [
            'id' => url($portal ? 'portal' : 'admin'),
            'name' => $portal ? (string) Settings::get('company_name') . ' – Kundenportal' : Brand::name(),
            'short_name' => self::shortName($app),
            'description' => $portal ? 'Ihre Offerten, Aufträge und Rechnungen.' : 'Offerten, Aufträge und Rechnungen verwalten.',
            'lang' => 'de-CH',
            'dir' => 'ltr',
            'start_url' => url($portal ? 'portal/' : 'admin/') . '?source=pwa',
            'scope' => url($portal ? 'portal/' : 'admin/'),
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => '#f8fafc',
            'theme_color' => $color,
            'categories' => ['business', 'productivity'],
            'icons' => $icons,
            'shortcuts' => $portal ? [] : [
                ['name' => 'Neue Offerte', 'url' => url('admin/offers/new'), 'icons' => [['src' => url('pwa/icon/96'), 'sizes' => '96x96', 'type' => 'image/png']]],
                ['name' => 'Rechnungen', 'url' => url('admin/invoices'), 'icons' => [['src' => url('pwa/icon/96'), 'sizes' => '96x96', 'type' => 'image/png']]],
            ],
        ];
    }

    public static function color(): string
    {
        $c = (string) Settings::get('brand_color', '#4f46e5');
        return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? strtolower($c) : '#4f46e5';
    }

    /** Ändert sich mit Farbe und Logo – damit Browser das Icon neu laden. */
    public static function iconVersion(): string
    {
        $logo = Brand::logoPath();
        return substr(md5(self::color() . ($logo ? (string) @filemtime($logo) : '') . (string) Settings::get('company_name')), 0, 8);
    }

    /** Quadratisches PNG: Farbfläche, darauf Logo (falls vorhanden) oder Initiale. $maskable = Sicherheitsrand für runde Masken. */
    public static function icon(int $size, bool $maskable): string
    {
        $size = in_array($size, self::SIZES, true) ? $size : 192;
        if (!function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('GD fehlt');
        }
        $im = imagecreatetruecolor($size, $size);
        imagealphablending($im, true);
        [$r, $g, $b] = sscanf(self::color(), '#%02x%02x%02x');
        $bg = imagecolorallocate($im, $r, $g, $b);
        imagefilledrectangle($im, 0, 0, $size, $size, $bg);
        $white = imagecolorallocate($im, 255, 255, 255);
        $pad = (int) round($size * ($maskable ? 0.28 : 0.2));
        $box = $size - 2 * $pad;
        $drawn = false;
        $logo = Brand::logoPath();
        if ($logo && is_file($logo)) {
            $data = @file_get_contents($logo);
            $src = $data !== false ? @imagecreatefromstring($data) : false;
            if ($src) {
                $w = imagesx($src);
                $h = imagesy($src);
                $scale = min($box / $w, $box / $h);
                $nw = max(1, (int) round($w * $scale));
                $nh = max(1, (int) round($h * $scale));
                // Weisse Kachel hinter dem Logo, damit dunkle Logos lesbar bleiben
                $tile = (int) round($size * 0.04);
                imagefilledrectangle($im, (int) (($size - $nw) / 2) - $tile, (int) (($size - $nh) / 2) - $tile, (int) (($size + $nw) / 2) + $tile, (int) (($size + $nh) / 2) + $tile, $white);
                imagecopyresampled($im, $src, (int) (($size - $nw) / 2), (int) (($size - $nh) / 2), 0, 0, $nw, $nh, $w, $h);
                $drawn = true;
            }
        }
        if (!$drawn) {
            // Dokument-Symbol: weisses Blatt mit drei Zeilen
            $dw = (int) round($box * 0.62);
            $dh = (int) round($box * 0.8);
            $x = (int) (($size - $dw) / 2);
            $y = (int) (($size - $dh) / 2);
            imagefilledrectangle($im, $x, $y, $x + $dw, $y + $dh, $white);
            $line = imagecolorallocate($im, $r, $g, $b);
            $t = max(2, (int) round($size * 0.035));
            for ($i = 0; $i < 3; $i++) {
                $ly = $y + (int) round($dh * (0.25 + 0.22 * $i));
                $lw = $i === 2 ? (int) round($dw * 0.45) : (int) round($dw * 0.64);
                imagefilledrectangle($im, $x + (int) round($dw * 0.18), $ly, $x + (int) round($dw * 0.18) + $lw, $ly + $t, $line);
            }
        }
        ob_start();
        imagepng($im, null, 6);
        return (string) ob_get_clean();
    }

    /** Quelltext des Service Workers (versioniert, damit Updates den Cache leeren). */
    public static function serviceWorker(): string
    {
        $v = json_encode(APP_VERSION . '-' . self::iconVersion());
        $offline = json_encode(url('offline'));
        $assets = json_encode(parse_url(url('assets/'), PHP_URL_PATH));
        $icon = json_encode(parse_url(url('pwa/icon/'), PHP_URL_PATH));
        return <<<JS
'use strict';
const VERSION = $v;
const CACHE = 'vylaris-' + VERSION;
const OFFLINE = $offline;
const ASSETS = $assets;
const ICONS = $icon;

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.add(new Request(OFFLINE, { credentials: 'same-origin' }))).then(() => self.skipWaiting()));
});
self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((ks) => Promise.all(ks.filter((k) => k.startsWith('vylaris-') && k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const u = new URL(req.url);
  if (u.origin !== self.location.origin) return;
  // Seiten: immer vom Server (nie zwischenspeichern – enthalten Kundendaten); offline → Hinweisseite
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() => caches.match(OFFLINE).then((r) => r || new Response('Offline', { status: 503 }))));
    return;
  }
  // Statische Dateien: aus dem Cache, im Hintergrund aktualisieren
  if (u.pathname.startsWith(ASSETS) || u.pathname.startsWith(ICONS)) {
    e.respondWith(caches.open(CACHE).then((c) => c.match(req).then((hit) => {
      const net = fetch(req).then((res) => { if (res.ok) c.put(req, res.clone()); return res; }).catch(() => hit);
      return hit || net;
    })));
  }
});
JS;
    }
}
