<?php
declare(strict_types=1);

/** Änderungsprotokoll: Gesamtübersicht und «Verstanden»-Bestätigung. */
final class ChangelogController
{
    public function index(): void
    {
        $user = Auth::requireUser();
        $entries = Changelog::all();
        view('admin/changelog/index', compact('entries', 'user'));
    }

    public function seen(): void
    {
        $user = Auth::requireUser();
        Changelog::markSeen((int) $user['id']);
        if (wants_json()) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        }
        redirect(!empty($_POST['back']) && str_starts_with((string) $_POST['back'], '/') && !str_starts_with((string) $_POST['back'], '//') ? ltrim((string) $_POST['back'], '/') : 'admin');
    }
}
