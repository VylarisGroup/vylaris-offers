<?php
declare(strict_types=1);

/** Anhänge verwalten (Team) und herunterladen. */
final class AttachmentController
{
    private const BACK = ['offer' => 'admin/offers/', 'order' => 'admin/orders/', 'invoice' => 'admin/invoices/'];

    public function upload(string $type, string $id): void
    {
        $user = Auth::requireUser();
        $back = (self::BACK[$type] ?? 'admin/') . (int) $id;
        try {
            AttachmentService::add($type, (int) $id, $_FILES['file'] ?? ['error' => UPLOAD_ERR_NO_FILE], !empty($_POST['customer_visible']), $user);
            flash('success', 'Anhang hochgeladen.');
        } catch (ValidationException $e) {
            flash('error', $e->getMessage());
        }
        redirect($back);
    }

    public function download(string $id): void
    {
        Auth::requireUser();
        $att = AttachmentService::find((int) $id);
        if (!$att) {
            throw new HttpException(404, 'Anhang nicht gefunden.');
        }
        AttachmentService::send($att);
    }

    public function visibility(string $id): void
    {
        $user = Auth::requireUser();
        $att = AttachmentService::find((int) $id);
        if (!$att) {
            throw new HttpException(404, 'Anhang nicht gefunden.');
        }
        DB::exec('UPDATE attachments SET customer_visible = 1 - customer_visible WHERE id = ?', [$att['id']]);
        redirect(self::BACK[$att['entity_type']] . $att['entity_id']);
    }

    public function destroy(string $id): void
    {
        $user = Auth::requireUser();
        $att = AttachmentService::find((int) $id);
        if (!$att) {
            throw new HttpException(404, 'Anhang nicht gefunden.');
        }
        AttachmentService::delete($att, $user);
        flash('success', 'Anhang entfernt.');
        redirect(self::BACK[$att['entity_type']] . $att['entity_id']);
    }
}
