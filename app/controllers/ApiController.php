<?php
/**
 * Schreibgeschützte JSON-API (v1). Authentifizierung: Authorization: Bearer omk_…
 * Jeder Token hat Bereiche (Scopes); Kundendaten nur mit Scope „customers“ (Datensparsamkeit).
 */
declare(strict_types=1);

final class ApiController
{
    private ?array $token = null;

    private const FIELDS = [
        'invoices'  => ['id', 'number', 'kind', 'status', 'title', 'issue_date', 'due_date', 'paid_at', 'currency', 'subtotal', 'discount_amount', 'net_total', 'tax_total', 'rounding', 'total', 'amount_due', 'credit_of', 'qr_reference', 'order_id', 'customer_id', 'sent_at', 'created_at'],
        'offers'    => ['id', 'number', 'parent_id', 'title', 'status', 'valid_until', 'currency', 'subtotal', 'discount_amount', 'net_total', 'tax_total', 'rounding', 'total', 'customer_id', 'created_by', 'created_by_name', 'created_at', 'sent_at', 'responded_at'],
        'orders'    => ['id', 'number', 'offer_id', 'title', 'status', 'currency', 'net_total', 'tax_total', 'total', 'shipping_status', 'carrier', 'customer_id', 'responsible_id', 'responsible_name', 'created_at', 'completed_at'],
        'customers' => ['id', 'customer_no', 'salutation', 'first_name', 'last_name', 'company', 'street', 'address_extra', 'zip', 'city', 'country', 'email', 'phone', 'mobile', 'website', 'vat_id', 'created_at'],
    ];
    private const DATE_COL = ['invoices' => 'issue_date', 'offers' => 'created_at', 'orders' => 'created_at', 'customers' => 'created_at'];
    private const TABLE = ['invoices' => 'invoices', 'offers' => 'offers', 'orders' => 'orders', 'customers' => 'customers'];

    private function out(int $status, array $body): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        exit;
    }

    private function auth(?string $scope): void
    {
        $ip = 'api-fail:' . client_ip();
        if (RateLimit::tooMany($ip, 30, 900)) {
            $this->out(429, ['error' => 'too_many_requests']);
        }
        $h = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        $token = preg_match('/^Bearer\s+(\S+)$/i', $h, $m) ? ApiTokens::verify($m[1]) : null;
        if (!$token) {
            RateLimit::hit($ip);
            header('WWW-Authenticate: Bearer');
            $this->out(401, ['error' => 'unauthorized']);
        }
        if (RateLimit::tooMany('api:' . $token['id'], 120, 60)) {
            header('Retry-After: 60');
            $this->out(429, ['error' => 'rate_limited']);
        }
        RateLimit::hit('api:' . $token['id']);
        if ($scope && !in_array($scope, $token['scope_list'], true)) {
            $this->out(403, ['error' => 'forbidden', 'message' => "Token hat keinen Zugriff auf '$scope'."]);
        }
        DB::exec('UPDATE api_tokens SET last_used_at = ? WHERE id = ?', [now(), $token['id']]);
        $this->token = $token;
    }

    public function ping(): void
    {
        $this->auth(null);
        $this->out(200, ['ok' => true, 'version' => 'v1', 'scopes' => $this->token['scope_list']]);
    }

    public function index(string $type): void
    {
        if (!isset(self::FIELDS[$type])) {
            $this->out(404, ['error' => 'not_found']);
        }
        $this->auth($type);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $per = max(1, min(100, (int) ($_GET['per_page'] ?? 50)));
        $where = ['1=1'];
        $p = [];
        if (!empty($_GET['status']) && $type !== 'customers') {
            $where[] = 'status = :status';
            $p[':status'] = (string) $_GET['status'];
        }
        if (!empty($_GET['customer_id']) && $type !== 'customers') {
            $where[] = 'customer_id = :cid';
            $p[':cid'] = (int) $_GET['customer_id'];
        }
        $col = self::DATE_COL[$type];
        foreach (['from' => '>=', 'to' => '<='] as $k => $op) {
            $d = (string) ($_GET[$k] ?? '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                $where[] = "DATE($col) $op :$k";
                $p[":$k"] = $d;
            }
        }
        if ($type === 'invoices' && empty($_GET['status'])) {
            $where[] = "status <> 'draft'";   // Entwürfe sind nicht Teil der Buchhaltung
        }
        $w = implode(' AND ', $where);
        $table = self::TABLE[$type];
        $total = (int) DB::val("SELECT COUNT(*) FROM $table WHERE $w", $p);
        $rows = DB::all('SELECT ' . implode(',', self::FIELDS[$type]) . " FROM $table WHERE $w ORDER BY id LIMIT $per OFFSET " . (($page - 1) * $per), $p);
        $this->out(200, ['data' => $rows, 'page' => $page, 'per_page' => $per, 'total' => $total]);
    }

    public function show(string $type, string $id): void
    {
        if (!isset(self::FIELDS[$type])) {
            $this->out(404, ['error' => 'not_found']);
        }
        $this->auth($type);
        $table = self::TABLE[$type];
        $row = DB::one('SELECT * FROM ' . $table . ' WHERE id = ?', [(int) $id]);
        if (!$row || ($type === 'invoices' && $row['status'] === 'draft')) {
            $this->out(404, ['error' => 'not_found']);
        }
        $data = array_intersect_key($row, array_flip(self::FIELDS[$type]));
        if ($type === 'invoices' || $type === 'offers') {
            $data['tax_breakdown'] = json_decode((string) $row['tax_breakdown'], true) ?: [];
            $itemsTable = $type === 'invoices' ? 'invoice_items' : 'offer_items';
            $fk = $type === 'invoices' ? 'invoice_id' : 'offer_id';
            $data['items'] = DB::all("SELECT position, title, description, quantity, unit, unit_price, discount_type, discount_value, tax_rate, tax_label, line_subtotal, line_discount, line_total, image FROM $itemsTable WHERE $fk = ? ORDER BY position", [$row['id']]);
            foreach ($data['items'] as &$apiIt) {
                $apiIt['image_url'] = ItemImage::url($apiIt['image'] ?? null);
                unset($apiIt['image']);
            }
            unset($apiIt);
        }
        if ($type === 'invoices') {
            $data['deductions'] = json_decode((string) $row['deductions'], true) ?: [];
            $data['service_period'] = $row['service_period'];
            $data['payments'] = DB::all('SELECT amount, paid_on, method, reference FROM invoice_payments WHERE invoice_id = ? ORDER BY paid_on, id', [$row['id']]);
            $data['paid_total'] = round((float) array_sum(array_column($data['payments'], 'amount')), 2);
            $data['open_amount'] = $row['status'] === 'cancelled' ? 0.0 : max(0.0, round((float) $row['amount_due'] - $data['paid_total'], 2));
            $data['installments'] = DB::all('SELECT position, label, due_date, amount FROM invoice_installments WHERE invoice_id = ? ORDER BY position', [$row['id']]);
            if (in_array('customers', $this->token['scope_list'], true)) {
                $data['customer'] = json_decode((string) $row['customer_snapshot'], true) ?: null;
            }
        }
        if ($type === 'customers') {
            $data['restricted'] = !empty($row['restricted_at']);
            $data['anonymized'] = !empty($row['anonymized_at']);
        }
        $this->out(200, ['data' => $data]);
    }
}
