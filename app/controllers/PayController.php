<?php
declare(strict_types=1);

/** Online-Zahlung: Start, Rückkehr vom Anbieter und eingehende Webhooks. */
final class PayController
{
    private function invoice(string $publicId): array
    {
        $inv = InvoiceService::findPublic($publicId);
        if (!$inv || $inv['status'] === 'draft') {
            throw new HttpException(404, 'Diese Rechnung existiert nicht oder wurde entfernt.');
        }
        return $inv;
    }

    public function start(string $id, string $provider): void
    {
        $inv = $this->invoice($id);
        if (!empty($_POST['full'])) {
            $inv['pay_amount'] = $inv['open_amount'];
        }
        try {
            $url = PaymentService::start($inv, $provider, !empty($_POST['full']));
        } catch (ValidationException $e) {
            flash('error', $e->getMessage());
            redirect('r/' . $inv['public_id']);
        }
        // Zwischenseite statt 302: Die CSP (form-action 'self') würde eine Weiterleitung eines Formulars auf eine fremde Domain blockieren.
        header('Cache-Control: no-store');
        header('Referrer-Policy: no-referrer');
        view('public/pay-redirect', compact('url', 'inv'), 'minimal');
    }

    /** Rückkehr vom Anbieter: Status direkt beim Anbieter abfragen (funktioniert auch ohne erreichbaren Webhook). */
    public function back(string $id, string $paymentId): void
    {
        $inv = $this->invoice($id);
        $p = DB::one('SELECT id, status FROM payments WHERE id = ? AND invoice_id = ?', [(int) $paymentId, $inv['id']]);
        if ($p) {
            try {
                $status = PaymentService::sync((int) $p['id']);
            } catch (Throwable $e) {
                ErrorHandler::log($e);
                $status = $p['status'];
            }
            match ($status) {
                'paid'     => flash('success', 'Vielen Dank! Ihre Zahlung ist eingegangen.'),
                'open'     => flash('warning', 'Ihre Zahlung wird noch verarbeitet. Die Rechnung wird automatisch als bezahlt markiert, sobald der Betrag bestätigt ist.'),
                default    => flash('error', 'Die Zahlung wurde nicht abgeschlossen. Sie können es erneut versuchen oder den Betrag überweisen.'),
            };
        }
        redirect('r/' . $inv['public_id']);
    }

    /** Mollie sendet nur die Zahlungs-ID (id=tr_…); der Status wird per API abgerufen. */
    public function mollieHook(): void
    {
        try {
            PaymentService::handleMollieId((string) ($_POST['id'] ?? ''));
        } catch (Throwable $e) {
            ErrorHandler::log($e);
            http_response_code(500);   // Mollie wiederholt den Aufruf
            exit;
        }
        http_response_code(200);
        exit;
    }

    public function stripeHook(): void
    {
        $payload = (string) file_get_contents('php://input');
        $secret = (string) Settings::get('stripe_webhook_secret');
        if (!PaymentService::verifyStripeSignature($payload, (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''), $secret)) {
            http_response_code(400);
            exit;
        }
        $event = json_decode($payload, true);
        if (!is_array($event)) {
            http_response_code(400);
            exit;
        }
        try {
            PaymentService::handleStripeEvent($event);
        } catch (Throwable $e) {
            ErrorHandler::log($e);
            http_response_code(500);   // Stripe wiederholt den Aufruf
            exit;
        }
        http_response_code(200);
        exit;
    }

    /** Admin: Status einer Zahlung manuell beim Anbieter abfragen. */
    public function adminSync(string $id, string $paymentId): void
    {
        Auth::requireUser();
        $p = DB::one('SELECT id FROM payments WHERE id = ? AND invoice_id = ?', [(int) $paymentId, (int) $id]);
        if ($p) {
            try {
                flash('success', 'Status beim Anbieter abgefragt: ' . PaymentService::sync((int) $p['id']) . '.');
            } catch (Throwable $e) {
                flash('error', 'Abfrage fehlgeschlagen: ' . $e->getMessage());
            }
        }
        redirect('admin/invoices/' . (int) $id);
    }
}
