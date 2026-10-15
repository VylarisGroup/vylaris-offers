<?php
declare(strict_types=1);

/** Audit-Log: nur für Administratoren, nur lesend. */
final class AuditController
{
    private const PER_PAGE = 50;

    public function index(): void
    {
        Auth::requireAdmin();
        $action = input_str('action', 40);
        $q = input_str('q', 100);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $where = ['1=1'];
        $params = [];
        if (isset(Audit::LABELS[$action])) {
            $where[] = 'action = :action';
            $params[':action'] = $action;
        }
        if ($q !== '') {
            $where[] = search_clause(['user_label', 'detail', 'entity'], $q, $params);
        }
        $w = implode(' AND ', $where);
        $total = (int) DB::val("SELECT COUNT(*) FROM audit_log WHERE $w", $params);
        $rows = DB::all("SELECT * FROM audit_log WHERE $w ORDER BY id DESC LIMIT " . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE), $params);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        view('admin/audit/index', compact('rows', 'action', 'q', 'page', 'pages', 'total'));
    }
}
