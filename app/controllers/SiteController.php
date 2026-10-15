<?php
declare(strict_types=1);

/** Startseite («Splash») und der zugehörige Website-Builder in der Administration. */
final class SiteController
{
    /** GET / – veröffentlichte Startseite. Angemeldete Mitarbeitende/Kunden werden wie bisher weitergeleitet (?view=1 zeigt die Seite). */
    public function home(): void
    {
        $doc = SiteBuilder::published();
        $force = isset($_GET['view']);
        if (!$doc['enabled']) {
            redirect(Auth::user() ? 'admin' : 'portal');
        }
        if (!$force && Auth::user()) {
            redirect('admin');
        }
        if (!$force && Auth::customer()) {
            redirect('portal');
        }
        $this->render($doc, false);
    }

    public function image(string $name): void
    {
        SiteBuilder::sendImage($name);
    }

    /* ------------------------------------------------------------ Administration */

    public function builder(): void
    {
        Auth::requireAdmin();
        view('admin/website/index', [
            'doc' => SiteBuilder::draft(),
            'schema' => SiteBuilder::schema(),
            'dirty' => SiteBuilder::hasDraftChanges(),
        ]);
    }

    public function preview(): void
    {
        Auth::requireAdmin();
        $this->render(SiteBuilder::draft(), true);
    }

    public function save(): void
    {
        Auth::requireAdmin();
        $raw = (string) file_get_contents('php://input', false, null, 0, SiteBuilder::MAX_JSON + 1);
        $doc = strlen($raw) <= SiteBuilder::MAX_JSON ? json_decode($raw, true) : null;
        if (!is_array($doc)) {
            json_response(['ok' => false, 'error' => 'Ungültige oder zu grosse Daten.'], 400);
        }
        $clean = SiteBuilder::saveDraft($doc);
        json_response(['ok' => true, 'doc' => $clean, 'dirty' => SiteBuilder::hasDraftChanges()]);
    }

    public function publish(): void
    {
        $user = Auth::requireAdmin();
        SiteBuilder::publish();
        Audit::log('site_published', 'Startseite veröffentlicht', null, null, $user);
        flash('success', 'Die Startseite wurde veröffentlicht.');
        redirect('admin/website');
    }

    public function reset(): void
    {
        $user = Auth::requireAdmin();
        SiteBuilder::resetDraft();
        Audit::log('site_reset', 'Entwurf auf Vorlage zurückgesetzt', null, null, $user);
        flash('success', 'Der Entwurf wurde auf die Vorlage zurückgesetzt. Veröffentlicht ist weiterhin die bisherige Fassung.');
        redirect('admin/website');
    }

    public function discard(): void
    {
        Auth::requireAdmin();
        Settings::set(['site_draft' => (string) Settings::get('site_published', '')]);
        flash('success', 'Der Entwurf wurde verworfen.');
        redirect('admin/website');
    }

    public function upload(): void
    {
        Auth::requireAdmin();
        try {
            $name = SiteBuilder::storeImage($_FILES['image'] ?? []);
        } catch (ValidationException $e) {
            json_response(['ok' => false, 'error' => $e->getMessage()], 422);
        }
        json_response(['ok' => true, 'name' => $name, 'url' => SiteBuilder::imageUrl($name)]);
    }

    private function render(array $doc, bool $preview): never
    {
        echo View::render('public/splash', ['doc' => $doc, 'preview' => $preview, 'error' => null, 'email' => ''], null);
        exit;
    }
}
