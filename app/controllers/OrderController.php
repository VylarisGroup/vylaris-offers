<?php
declare(strict_types=1);

final class OrderController
{
    public function index(): void
    {
        Auth::requireUser();
        $status = input_str('status', 20);
        $q = input_str('q', 100);
        $where = ['1=1'];
        $params = [];
        $owner = (string) Responsibility::filter(input_str('owner', 12), 'o.responsible_id', $where, $params, true);
        if ($status === 'active') {
            $where[] = "o.status IN ('new','in_progress','fulfilled')";
        } elseif (isset(Status::ORDER[$status])) {
            $where[] = 'o.status = :status';
            $params[':status'] = $status;
        }
        if ($q !== '') {
            $where[] = search_clause(['o.number', 'o.title', 'c.company', 'c.last_name', 'o.tracking_number'], $q, $params);
        }
        $orders = DB::all(
            'SELECT o.*, c.company, c.first_name, c.last_name, p.name AS area_name, p.icon AS area_icon, COALESCE(u.name, o.responsible_name) AS responsible_label
             FROM orders o JOIN customers c ON c.id = o.customer_id LEFT JOIN product_areas p ON p.id = o.product_area_id LEFT JOIN users u ON u.id = o.responsible_id
             WHERE ' . implode(' AND ', $where) . ' ORDER BY o.created_at DESC LIMIT 500',
            $params
        );
        $counts = ['' => (int) DB::val('SELECT COUNT(*) FROM orders'), 'active' => (int) DB::val("SELECT COUNT(*) FROM orders WHERE status IN ('new','in_progress','fulfilled')")];
        foreach (DB::all('SELECT status, COUNT(*) n FROM orders GROUP BY status') as $r) {
            $counts[$r['status']] = (int) $r['n'];
        }
        $staff = Responsibility::staff();
        view('admin/orders/index', compact('orders', 'status', 'q', 'counts', 'owner', 'staff'));
    }

    public function show(string $id): void
    {
        Auth::requireUser();
        $order = $this->find((int) $id);
        $offer = OfferService::find((int) $order['offer_id']);
        $history = History::for('order', (int) $order['id']);
        $shippingMethods = DB::all('SELECT * FROM shipping_methods WHERE active = 1 ORDER BY sort, name');
        $staff = Responsibility::staff();
        view('admin/orders/show', compact('order', 'offer', 'history', 'shippingMethods', 'staff'));
    }

    public function assign(string $id): void
    {
        $user = Auth::requireUser();
        $order = $this->find((int) $id);
        $uid = (int) ($_POST['user_id'] ?? 0);
        try {
            $changed = Responsibility::assign($order, $uid > 0 ? $uid : null, $user);
        } catch (ValidationException $e) {
            flash('error', $e->getMessage());
            redirect('admin/orders/' . $order['id']);
        }
        if ($changed) {
            Audit::log('order_assigned', $order['number'] . ' → ' . ($uid > 0 ? (string) DB::val('SELECT name FROM users WHERE id = ?', [$uid]) : 'niemand'), 'order', (int) $order['id']);
        }
        flash('success', $changed ? 'Zuständigkeit gespeichert.' : 'Keine Änderung.');
        redirect('admin/orders/' . $order['id']);
    }

    public function status(string $id): void
    {
        $user = Auth::requireUser();
        $order = $this->find((int) $id);
        $status = input_str('status', 20);
        $note = input_str('note', 5000) ?: null;
        $visible = !empty($_POST['customer_visible']);
        try {
            $changed = OrderService::setStatus($order, $status, $note, $visible, $user);
        } catch (ValidationException $e) {
            flash('error', $e->getMessage());
            redirect('admin/orders/' . $order['id']);
        }
        $msg = $changed ? 'Status auf „' . Status::label('order', $status, $order['area_type']) . '“ geändert.' : 'Notiz gespeichert.';
        if (!empty($_POST['notify']) && $visible) {
            $fresh = $this->find((int) $order['id']);
            $text = ($changed ? 'Neuer Status: ' . Status::label('order', $status, $order['area_type']) : '') . ($note ? ($changed ? "\n\n" : '') . $note : '');
            if ($text !== '') {
                $msg .= Notifier::orderUpdate($fresh, $text) ? ' Der Kunde wurde benachrichtigt.' : ' E-Mail fehlgeschlagen: ' . Notifier::$lastError;
            }
        }
        flash('success', $msg);
        redirect('admin/orders/' . $order['id']);
    }

    public function fulfillment(string $id): void
    {
        $user = Auth::requireUser();
        $order = $this->find((int) $id);
        try {
            $note = OrderService::updateFulfillment($order, $_POST, $user);
        } catch (ValidationException $e) {
            flash('error', $e->getMessage());
            redirect('admin/orders/' . $order['id']);
        }
        $msg = $order['area_type'] === 'physical' ? 'Versanddaten gespeichert.' : 'Lieferdaten gespeichert.';
        if ($note && !empty($_POST['notify'])) {
            $msg .= Notifier::orderUpdate($this->find((int) $order['id']), $note) ? ' Der Kunde wurde benachrichtigt.' : ' E-Mail fehlgeschlagen: ' . Notifier::$lastError;
        }
        flash('success', $msg);
        redirect('admin/orders/' . $order['id']);
    }

    public function destroy(string $id): void
    {
        $user = Auth::requireUser();
        $order = $this->find((int) $id);
        try {
            OrderService::delete((int) $id);
            Audit::log('order_deleted', $order['number'], 'order', (int) $id, $user);
            flash('success', 'Der Auftrag wurde gelöscht. Die Offerte bleibt erhalten.');
            redirect('admin/orders');
        } catch (ValidationException $e) {
            flash('error', $e->getMessage());
            redirect('admin/orders/' . (int) $id);
        }
    }

    private function find(int $id): array
    {
        $o = OrderService::find($id);
        if (!$o) {
            throw new HttpException(404, 'Der Auftrag wurde nicht gefunden.');
        }
        return $o;
    }
}
