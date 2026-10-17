<?php
declare(strict_types=1);

final class PwaController
{
    public function manifest(string $app = 'admin'): void
    {
        if (!Pwa::enabled()) {
            throw new HttpException(404, 'Nicht gefunden.');
        }
        header('Content-Type: application/manifest+json; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        echo json_encode(Pwa::manifest($app === 'portal' ? 'portal' : 'admin'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    public function adminManifest(): void
    {
        $this->manifest('admin');
    }

    public function portalManifest(): void
    {
        $this->manifest('portal');
    }

    public function serviceWorker(): void
    {
        header('Content-Type: text/javascript; charset=utf-8');
        header('Cache-Control: no-cache');
        header('Service-Worker-Allowed: ' . (base_path() ?: '') . '/');
        if (!Pwa::enabled()) {
            // Deaktiviert: Worker meldet sich ab und leert den Cache
            echo "self.addEventListener('install',()=>self.skipWaiting());self.addEventListener('activate',e=>e.waitUntil(caches.keys().then(k=>Promise.all(k.map(x=>caches.delete(x)))).then(()=>self.registration.unregister())));";
            return;
        }
        echo Pwa::serviceWorker();
    }

    public function icon(string $file): void
    {
        $size = (int) $file;
        try {
            $png = Pwa::icon($size, isset($_GET['m']));
        } catch (Throwable) {
            throw new HttpException(404, 'Nicht gefunden.');
        }
        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=86400');
        echo $png;
    }

    public function offline(): void
    {
        view('pwa/offline', ['title' => 'Offline'], 'minimal');
    }
}
