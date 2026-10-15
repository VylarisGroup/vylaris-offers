<?php
declare(strict_types=1);

final class DashboardController
{
    public function index(): void
    {
        $user = Auth::requireUser();
        OfferService::sweepExpired();
        SubscriptionService::runIfDue();
        Retention::runIfDue();
        Webhooks::runIfDue();

        $monthStart = date('Y-m-01 00:00:00');
        $stats = [
            'open_offers'    => (int) DB::val("SELECT COUNT(*) FROM offers WHERE status = 'open'"),
            'open_value'     => (float) DB::val("SELECT COALESCE(SUM(total),0) FROM offers WHERE status = 'open'"),
            'active_orders'  => (int) DB::val("SELECT COUNT(*) FROM orders WHERE status IN ('new','in_progress','fulfilled')"),
            'my_orders'      => (int) DB::val("SELECT COUNT(*) FROM orders WHERE status IN ('new','in_progress','fulfilled') AND responsible_id = ?", [(int) $user['id']]),
            'unassigned'     => (int) DB::val("SELECT COUNT(*) FROM orders WHERE status IN ('new','in_progress','fulfilled') AND responsible_id IS NULL"),
            'new_orders'     => (int) DB::val("SELECT COUNT(*) FROM orders WHERE status = 'new'"),
            'month_revenue'  => (float) DB::val('SELECT COALESCE(SUM(total),0) FROM orders WHERE status <> \'cancelled\' AND created_at >= ?', [$monthStart]),
            'inv_open'       => (float) DB::val("SELECT COALESCE(SUM(" . InvoiceService::OPEN_SQL . "),0) FROM invoices i WHERE i.status = 'sent' AND i.kind <> 'credit'"),
            'inv_overdue'    => (int) DB::val("SELECT COUNT(*) FROM invoices WHERE status = 'sent' AND due_date < CURDATE()"),
            'customers'      => (int) DB::val('SELECT COUNT(*) FROM customers'),
        ];
        $decided = (int) DB::val("SELECT COUNT(*) FROM offers WHERE status IN ('accepted','declined','expired') AND created_at >= ?", [date('Y-m-d', strtotime('-12 months'))]);
        $accepted = (int) DB::val("SELECT COUNT(*) FROM offers WHERE status = 'accepted' AND created_at >= ?", [date('Y-m-d', strtotime('-12 months'))]);
        $stats['acceptance'] = $decided > 0 ? round($accepted / $decided * 100) : null;

        // Volumen der letzten 6 Monate
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $ts = strtotime(date('Y-m-01') . " -$i months");
            $months[date('Y-m', $ts)] = ['label' => self::monthName((int) date('n', $ts)), 'offers' => 0.0, 'orders' => 0.0];
        }
        $from = array_key_first($months) . '-01';
        foreach (DB::all("SELECT DATE_FORMAT(created_at, '%Y-%m') m, SUM(total) s FROM offers WHERE created_at >= ? GROUP BY m", [$from]) as $r) {
            if (isset($months[$r['m']])) {
                $months[$r['m']]['offers'] = (float) $r['s'];
            }
        }
        foreach (DB::all("SELECT DATE_FORMAT(created_at, '%Y-%m') m, SUM(total) s FROM orders WHERE status <> 'cancelled' AND created_at >= ? GROUP BY m", [$from]) as $r) {
            if (isset($months[$r['m']])) {
                $months[$r['m']]['orders'] = (float) $r['s'];
            }
        }

        $openOffers = DB::all(
            "SELECT o.*, c.company, c.first_name, c.last_name, COALESCE(u.name, o.created_by_name) AS issuer_label FROM offers o JOIN customers c ON c.id = o.customer_id LEFT JOIN users u ON u.id = o.created_by
             WHERE o.status = 'open' ORDER BY (o.valid_until IS NULL), o.valid_until, o.created_at DESC LIMIT 6"
        );
        $activeOrders = DB::all(
            "SELECT o.*, c.company, c.first_name, c.last_name, COALESCE(u.name, o.responsible_name) AS responsible_label FROM orders o JOIN customers c ON c.id = o.customer_id LEFT JOIN users u ON u.id = o.responsible_id
             WHERE o.status IN ('new','in_progress','fulfilled') ORDER BY FIELD(o.status,'new','in_progress','fulfilled'), o.created_at LIMIT 6"
        );
        $activity = DB::all(
            "SELECT h.*, COALESCE(f.number, r.number) AS ref_number, r.area_type
             FROM status_history h
             LEFT JOIN offers f ON h.entity_type = 'offer' AND f.id = h.entity_id
             LEFT JOIN orders r ON h.entity_type = 'order' AND r.id = h.entity_id
             ORDER BY h.created_at DESC, h.id DESC LIMIT 10"
        );

        view('admin/dashboard', compact('user', 'stats', 'months', 'openOffers', 'activeOrders', 'activity'));
    }

    public static function monthName(int $m): string
    {
        return ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'][$m - 1];
    }
}
