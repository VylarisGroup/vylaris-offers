<?php
declare(strict_types=1);

/** Öffentliche Rechnungsseite /r/{public_id} (druckfreundlich). */
final class PublicInvoiceController
{
    public function file(string $id, string $fid): void
    {
        $inv = InvoiceService::findPublic($id);
        $att = AttachmentService::find((int) $fid);
        if (!$inv || $inv['status'] === 'draft' || !$att || $att['entity_type'] !== 'invoice' || (int) $att['entity_id'] !== (int) $inv['id'] || !$att['customer_visible']) {
            throw new HttpException(404, 'Anhang nicht gefunden.');
        }
        AttachmentService::send($att);
    }

    public function pdf(string $id): void
    {
        $inv = InvoiceService::findPublic($id);
        if (!$inv || $inv['status'] === 'draft') {
            throw new HttpException(404, 'Diese Rechnung existiert nicht oder wurde entfernt.');
        }
        PdfService::send(PdfService::invoice($inv), 'Rechnung-' . $inv['number'] . '.pdf');
    }

    public function receipt(string $id, string $pid = ''): void
    {
        $inv = InvoiceService::findPublic($id);
        $ids = $inv ? array_map(static fn($p) => (int) $p['id'], $inv['payments']) : [];
        if (!$inv || !$ids || $inv['status'] === 'cancelled' || ($pid !== '' && !in_array((int) $pid, $ids, true))) {
            throw new HttpException(404, 'Für diese Rechnung liegt keine Zahlungsbestätigung vor.');
        }
        PdfService::send(PdfService::receipt($inv, $pid !== '' ? (int) $pid : null), 'Zahlungsbestaetigung-' . $inv['number'] . '.pdf');
    }

    public function show(string $id): void
    {
        $inv = InvoiceService::findPublic($id);
        if (!$inv || $inv['status'] === 'draft') {
            throw new HttpException(404, 'Diese Rechnung existiert nicht oder wurde entfernt. Bitte prüfen Sie den Link.');
        }
        header('X-Robots-Tag: noindex, nofollow');
        if (!Auth::user()) {
            InvoiceService::markViewed($inv);
        }
        $order = DB::one('SELECT id, number FROM orders WHERE id = ?', [$inv['order_id']]);
        $files = AttachmentService::forEntity('invoice', (int) $inv['id'], true);
        view('public/invoice', compact('inv', 'order', 'files'), 'public');
    }
}
