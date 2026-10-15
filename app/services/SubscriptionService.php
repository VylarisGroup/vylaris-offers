<?php
/**
 * Abos / wiederkehrende Rechnungen: Aus einem Auftrag wird in festen Abständen automatisch
 * eine Rechnung (Positionen der ursprünglichen Offerte) erzeugt – optional direkt per E-Mail versendet.
 * Ausgelöst per Cronjob (cron.php) oder – als Rückfall – beim Aufruf der Administration.
 */
declare(strict_types=1);

final class SubscriptionService
{
    public const PERIODS = ['monthly' => ['Monatlich', 1], 'quarterly' => ['Vierteljährlich', 3], 'yearly' => ['Jährlich', 12]];

    public static function addPeriod(string $date, string $period, int $anchorDay): string
    {
        $months = self::PERIODS[$period][1] ?? 1;
        $d = new DateTimeImmutable($date);
        $first = $d->modify('first day of this month')->modify("+$months months");
        $day = min($anchorDay, (int) $first->format('t'));
        return $first->setDate((int) $first->format('Y'), (int) $first->format('n'), $day)->format('Y-m-d');
    }

    public static function create(array $order, array $in, array $user): int
    {
        if ($order['status'] === 'cancelled') {
            throw new ValidationException('Für einen stornierten Auftrag kann kein Abo eingerichtet werden.');
        }
        $period = isset(self::PERIODS[$in['period'] ?? '']) ? $in['period'] : 'monthly';
        $start = InvoiceService::validDate((string) ($in['start'] ?? ''));
        if (!$start || $start < date('Y-m-d')) {
            throw new ValidationException('Das Startdatum der ersten Rechnung darf nicht in der Vergangenheit liegen.');
        }
        $end = trim((string) ($in['end'] ?? '')) !== '' ? InvoiceService::validDate((string) $in['end']) : null;
        if (trim((string) ($in['end'] ?? '')) !== '' && (!$end || $end < $start)) {
            throw new ValidationException('Das Enddatum muss nach dem Startdatum liegen.');
        }
        $title = mb_substr(trim((string) ($in['title'] ?? '')), 0, 190) ?: $order['title'];
        return DB::insert('subscriptions', [
            'order_id' => (int) $order['id'], 'customer_id' => (int) $order['customer_id'], 'title' => $title,
            'period' => $period, 'anchor_day' => (int) date('j', strtotime($start)), 'next_run' => $start, 'end_date' => $end,
            'auto_send' => !empty($in['auto_send']) ? 1 : 0,
            'payment_days' => max(0, min(365, (int) ($in['payment_days'] ?? Settings::get('invoice_payment_days', 30)))),
            'status' => 'active', 'created_by' => $user['id'], 'created_at' => now(),
        ]);
    }

    public static function setStatus(int $id, string $status): void
    {
        if (!in_array($status, ['active', 'paused', 'ended'], true)) {
            return;
        }
        DB::exec("UPDATE subscriptions SET status = ? WHERE id = ? AND status <> 'ended'", [$status, $id]);
        if ($status === 'active') {
            // Nicht rückwirkend für die Pausenzeit abrechnen: überfälligen Termin auf heute setzen.
            DB::exec('UPDATE subscriptions SET next_run = ? WHERE id = ? AND next_run < ?', [date('Y-m-d'), $id, date('Y-m-d')]);
        }
    }

    /** Führt höchstens alle 30 Minuten aus (Rückfall, wenn kein Cronjob eingerichtet ist). */
    public static function runIfDue(): void
    {
        try {
            $last = (int) Settings::get('sub_last_run', 0);
            if (time() - $last < 1800) {
                return;
            }
            Settings::set(['sub_last_run' => (string) time()]);
            self::runDue();
        } catch (Throwable $e) {
            ErrorHandler::log($e);
        }
    }

    /** @return int Anzahl erzeugter Rechnungen */
    public static function runDue(?int $onlyId = null): int
    {
        if (License::readonly() || !License::has('subscriptions')) {
            return 0;   // ohne gültige Lizenz entstehen keine neuen Rechnungen
        }
        $today = date('Y-m-d');
        $created = 0;
        $ids = DB::all(
            "SELECT id FROM subscriptions WHERE status = 'active' AND next_run <= ?" . ($onlyId ? ' AND id = ?' : ''),
            $onlyId ? [$today, $onlyId] : [$today]
        );
        foreach ($ids as $row) {
            for ($guard = 0; $guard < 24; $guard++) {
                $invoiceId = DB::tx(static function () use ($row, $today): ?int {
                    $s = DB::one('SELECT * FROM subscriptions WHERE id = ? FOR UPDATE', [$row['id']]);
                    if (!$s || $s['status'] !== 'active' || $s['next_run'] > $today) {
                        return null;
                    }
                    if ($s['end_date'] && $s['next_run'] > $s['end_date']) {
                        DB::exec("UPDATE subscriptions SET status = 'ended' WHERE id = ?", [$s['id']]);
                        return null;
                    }
                    $next = self::addPeriod($s['next_run'], $s['period'], (int) $s['anchor_day']);
                    $label = 'Leistungszeitraum ' . fmt_date($s['next_run']) . ' – ' . fmt_date(date('Y-m-d', strtotime($next . ' -1 day')));
                    $id = InvoiceService::createRecurring($s, $s['next_run'], $label);
                    $ended = $s['end_date'] && $next > $s['end_date'];
                    DB::exec('UPDATE subscriptions SET next_run = ?, last_run = ?, runs = runs + 1, status = ? WHERE id = ?', [$next, $s['next_run'], $ended ? 'ended' : 'active', $s['id']]);
                    if ($s['auto_send']) {
                        InvoiceService::transition($id, 'sent', ['id' => null, 'name' => 'Abo (automatisch)']);
                    }
                    return $id;
                });
                if ($invoiceId === null) {
                    break;
                }
                $created++;
                $sub = DB::one('SELECT auto_send FROM subscriptions WHERE id = ?', [$row['id']]);
                if ($sub && $sub['auto_send']) {
                    $inv = InvoiceService::find($invoiceId);
                    if ($inv && Notifier::invoiceSent($inv)) {
                        DB::exec('UPDATE invoices SET sent_at = ? WHERE id = ?', [now(), $invoiceId]);
                    }
                }
            }
        }
        return $created;
    }
}
