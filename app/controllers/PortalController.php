<?php
declare(strict_types=1);

/** Kundenportal mit Magic-Link-Anmeldung. */
final class PortalController
{
    public function index(): void
    {
        $c = Auth::customer();
        if (!$c) {
            view('portal/login', [], 'public');
        }
        OfferService::sweepExpired();
        $offers = DB::all(
            'SELECT o.*, (SELECT id FROM orders WHERE offer_id = o.id) AS order_id FROM offers o WHERE o.customer_id = ? ORDER BY o.created_at DESC',
            [$c['id']]
        );
        $orders = DB::all('SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC', [$c['id']]);
        $invoices = DB::all("SELECT i.*, o.number AS order_number FROM invoices i JOIN orders o ON o.id = i.order_id WHERE i.customer_id = ? AND i.status <> 'draft' ORDER BY i.issue_date DESC, i.id DESC", [$c['id']]);
        view('portal/index', compact('c', 'offers', 'orders', 'invoices'), 'public');
    }

    public function requestLink(): void
    {
        $email = mb_strtolower(input_str('email', 190));
        if (!valid_email($email)) {
            view('portal/login', ['email' => $email, 'error' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.'], 'public');
        }
        $bucket = 'portal-link:' . $email;
        $ipBucket = 'portal-link-ip:' . client_ip();
        if (!RateLimit::tooMany($bucket, 3, 900) && !RateLimit::tooMany($ipBucket, 10, 900)) {
            RateLimit::hit($bucket);
            RateLimit::hit($ipBucket);
            $c = DB::one('SELECT * FROM customers WHERE email = ? AND portal_enabled = 1', [$email]);
            if ($c) {
                Notifier::portalLink($c, Tokens::create('customer', (int) $c['id'], 30));
            }
        }
        // Immer die gleiche Antwort – verrät nicht, ob eine Adresse existiert.
        view('auth/link-sent', ['email' => $email, 'back' => 'portal'], 'public');
    }

    public function confirm(string $token): void
    {
        $row = Tokens::find('customer', $token);
        view($row ? 'auth/confirm' : 'auth/link-invalid', [
            'action' => 'portal/auth/' . $token,
            'back'   => 'portal',
            'area'   => 'Kundenportal',
        ], 'public');
    }

    public function login(string $token): void
    {
        $row = Tokens::find('customer', $token);
        $c = $row ? DB::one('SELECT * FROM customers WHERE id = ? AND portal_enabled = 1', [$row['subject_id']]) : null;
        if (!$row || !$c || !Tokens::consume($row)) {
            view('auth/link-invalid', ['back' => 'portal'], 'public');
        }
        Auth::loginCustomer($c);
        redirect('portal');
    }

    public function logout(): void
    {
        Auth::logoutCustomer();
        flash('success', 'Sie wurden abgemeldet.');
        redirect('portal');
    }

    public function file(string $id, string $fid): void
    {
        $c = Auth::requireCustomer();
        $att = AttachmentService::find((int) $fid);
        $owns = $att && $att['entity_type'] === 'order' && (int) $att['entity_id'] === (int) $id
            && DB::val('SELECT 1 FROM orders WHERE id = ? AND customer_id = ?', [(int) $id, $c['id']]);
        if (!$owns || !$att['customer_visible']) {
            throw new HttpException(404, 'Anhang nicht gefunden.');
        }
        AttachmentService::send($att);
    }

    public function order(string $id): void
    {
        $c = Auth::requireCustomer();
        $order = DB::one(
            'SELECT o.*, f.public_id AS offer_public_id, f.number AS offer_number, p.name AS area_name
             FROM orders o JOIN offers f ON f.id = o.offer_id LEFT JOIN product_areas p ON p.id = o.product_area_id
             WHERE o.id = ? AND o.customer_id = ?',
            [(int) $id, $c['id']]
        );
        if (!$order) {
            throw new HttpException(404, 'Dieser Auftrag wurde nicht gefunden.');
        }
        $history = History::for('order', (int) $order['id'], true);
        $files = AttachmentService::forEntity('order', (int) $order['id'], true);
        view('portal/order', compact('c', 'order', 'history', 'files'), 'public');
    }
}
