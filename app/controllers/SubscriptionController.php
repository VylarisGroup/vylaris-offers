<?php
declare(strict_types=1);

final class SubscriptionController
{
    public function index(): void
    {
        Auth::requireUser();
        $subs = DB::all(
            'SELECT s.*, c.company, c.first_name, c.last_name, o.number AS order_number, o.total, o.currency,
                    (SELECT COUNT(*) FROM invoices i WHERE i.subscription_id = s.id) AS invoice_count
             FROM subscriptions s JOIN customers c ON c.id = s.customer_id JOIN orders o ON o.id = s.order_id
             ORDER BY FIELD(s.status, \'active\', \'paused\', \'ended\'), s.next_run'
        );
        view('admin/subscriptions/index', compact('subs'));
    }

    public function store(string $orderId): void
    {
        $user = Auth::requireUser();
        $order = DB::one('SELECT * FROM orders WHERE id = ?', [(int) $orderId]);
        if (!$order) {
            throw new HttpException(404, 'Auftrag nicht gefunden.');
        }
        try {
            SubscriptionService::create($order, [
                'period' => input_str('period', 20), 'start' => input_str('start', 10), 'end' => input_str('end', 10),
                'auto_send' => input('auto_send'), 'payment_days' => input_int('payment_days'), 'title' => input_str('title', 190),
            ], $user);
            flash('success', 'Abo eingerichtet. Die erste Rechnung wird am gewählten Datum erzeugt.');
            redirect('admin/subscriptions');
        } catch (ValidationException $e) {
            flash('error', $e->getMessage());
            redirect('admin/orders/' . $order['id']);
        }
    }

    public function action(string $id, string $action): void
    {
        Auth::requireUser();
        $sub = DB::one('SELECT * FROM subscriptions WHERE id = ?', [(int) $id]);
        if (!$sub) {
            throw new HttpException(404, 'Abo nicht gefunden.');
        }
        switch ($action) {
            case 'pause':
                SubscriptionService::setStatus((int) $id, 'paused');
                flash('success', 'Abo pausiert.');
                break;
            case 'resume':
                SubscriptionService::setStatus((int) $id, 'active');
                flash('success', 'Abo wieder aktiv.');
                break;
            case 'end':
                SubscriptionService::setStatus((int) $id, 'ended');
                flash('success', 'Abo beendet.');
                break;
            case 'autosend':
                DB::exec('UPDATE subscriptions SET auto_send = 1 - auto_send WHERE id = ?', [(int) $id]);
                flash('success', 'Einstellung gespeichert.');
                break;
            case 'run':
                $n = SubscriptionService::runDue((int) $id);
                flash($n ? 'success' : 'info', $n ? "$n Rechnung(en) erzeugt." : 'Noch nichts fällig.');
                break;
        }
        redirect('admin/subscriptions');
    }
}
