<?php
declare(strict_types=1);

/** Anfrageformular der Startseite (öffentlich) und Anfragenliste im Backend. */
final class InquiryController
{
    public function submit(): void
    {
        $res = InquiryService::submit($_POST);
        flash($res['ok'] ? 'success' : 'error', $res['message']);
        redirect('');
    }

    public function index(): void
    {
        Auth::requireUser();
        $status = input_str('status', 10);
        $where = isset(InquiryService::STATUS[$status]) ? 'WHERE q.status = ' . DB::pdo()->quote($status) : '';
        $rows = DB::all("SELECT q.*, o.number AS offer_number FROM inquiries q LEFT JOIN offers o ON o.id = q.offer_id $where ORDER BY q.created_at DESC LIMIT 300");
        $counts = ['' => (int) DB::val('SELECT COUNT(*) FROM inquiries')];
        foreach (DB::all('SELECT status, COUNT(*) n FROM inquiries GROUP BY status') as $r) {
            $counts[$r['status']] = (int) $r['n'];
        }
        view('admin/inquiries/index', compact('rows', 'status', 'counts'));
    }

    public function setStatus(string $id, string $status): void
    {
        Auth::requireUser();
        if (!isset(InquiryService::STATUS[$status])) {
            throw new HttpException(404, 'Unbekannter Status.');
        }
        DB::exec('UPDATE inquiries SET status = ?, handled_at = ? WHERE id = ?', [$status, $status === 'new' ? null : now(), (int) $id]);
        redirect('admin/inquiries');
    }

    public function delete(string $id): void
    {
        $user = Auth::requireUser();
        DB::exec('DELETE FROM inquiries WHERE id = ?', [(int) $id]);
        Audit::log('inquiry_deleted', 'Anfrage #' . (int) $id, 'inquiry', (int) $id, $user);
        flash('success', 'Anfrage gelöscht.');
        redirect('admin/inquiries');
    }
}
