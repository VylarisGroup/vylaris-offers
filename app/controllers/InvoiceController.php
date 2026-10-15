<?php
declare(strict_types=1);

/** Rechnungen: Verwaltung im Backend. */
final class InvoiceController
{
    public function index(): void
    {
        Auth::requireUser();
        SubscriptionService::runIfDue();
        $status = input_str('status', 20);
        $q = input_str('q', 100);
        $where = ['1=1'];
        $params = [];
        if ($status === 'overdue') {
            $where[] = "i.status = 'sent' AND i.due_date < CURDATE()";
        } elseif ($status === 'sent') {
            $where[] = "i.status = 'sent' AND i.due_date >= CURDATE()";
        } elseif (isset(Status::INVOICE[$status])) {
            $where[] = 'i.status = :status';
            $params[':status'] = $status;
        }
        if ($q !== '') {
            $where[] = search_clause(['i.number', 'i.title', 'c.company', 'c.last_name', 'c.first_name'], $q, $params);
        }
        $invoices = DB::all(
            'SELECT i.*, c.company, c.first_name, c.last_name, o.number AS order_number,
                    COALESCE((SELECT SUM(p.amount) FROM invoice_payments p WHERE p.invoice_id = i.id), 0) + COALESCE((SELECT SUM(a.amount) FROM invoice_adjustments a WHERE a.invoice_id = i.id), 0) AS paid_total
             FROM invoices i JOIN customers c ON c.id = i.customer_id JOIN orders o ON o.id = i.order_id
             WHERE ' . implode(' AND ', $where) . ' ORDER BY i.issue_date DESC, i.id DESC LIMIT 500',
            $params
        );
        $counts = ['' => (int) DB::val('SELECT COUNT(*) FROM invoices')];
        foreach (DB::all('SELECT status, COUNT(*) n FROM invoices GROUP BY status') as $r) {
            $counts[$r['status']] = (int) $r['n'];
        }
        $counts['overdue'] = (int) DB::val("SELECT COUNT(*) FROM invoices WHERE status = 'sent' AND due_date < CURDATE()");
        $counts['sent'] = (int) ($counts['sent'] ?? 0) - $counts['overdue'];
        $sums = DB::one("SELECT COALESCE(SUM(" . InvoiceService::OPEN_SQL . "),0) AS open_sum FROM invoices i WHERE i.status = 'sent' AND i.kind <> 'credit'");
        view('admin/invoices/index', compact('invoices', 'status', 'q', 'counts', 'sums'));
    }

    private function find(string $id): array
    {
        $inv = InvoiceService::find((int) $id);
        if (!$inv) {
            throw new HttpException(404, 'Diese Rechnung wurde nicht gefunden.');
        }
        return $inv;
    }

    public function show(string $id): void
    {
        Auth::requireUser();
        $inv = $this->find($id);
        $order = DB::one('SELECT id, number, status, area_type FROM orders WHERE id = ?', [$inv['order_id']]);
        $creator = $inv['created_by'] ? DB::one('SELECT name FROM users WHERE id = ?', [$inv['created_by']]) : null;
        $history = History::for('invoice', (int) $inv['id']);
        $payments = DB::all('SELECT * FROM payments WHERE invoice_id = ? ORDER BY id DESC', [$inv['id']]);
        $review = ($inv['kind'] === 'credit' || !in_array($inv['status'], ['draft', 'sent'], true)) ? [] : TaxService::review($inv['customer'], $inv['taxes'], $inv['issue_date'], (string) $inv['area_type'], true);
        $credits = DB::all("SELECT id, number, status, amount_due, issue_date FROM invoices WHERE credit_of = ? AND kind = 'credit' AND status <> 'cancelled' ORDER BY id", [$inv['id']]);
        view('admin/invoices/show', compact('inv', 'order', 'creator', 'history', 'payments', 'review', 'credits'));
    }

    public function pdf(string $id): void
    {
        Auth::requireUser();
        $inv = $this->find($id);
        PdfService::send(PdfService::invoice($inv), 'Rechnung-' . $inv['number'] . '.pdf');
    }

    public function store(string $orderId): void
    {
        $user = Auth::requireUser();
        $order = DB::one('SELECT * FROM orders WHERE id = ?', [(int) $orderId]);
        if (!$order) {
            throw new HttpException(404, 'Auftrag nicht gefunden.');
        }
        $daysRaw = trim((string) input('payment_days', ''));
        try {
            $kind = input('kind') === 'advance' ? 'advance' : 'final';
            $mode = input('mode') === 'amount' ? 'amount' : 'percent';
            $id = InvoiceService::createFromOrder($order, input_str('issue_date', 10) ?: null, $daysRaw === '' ? null : (int) $daysRaw, $user, $kind, $mode, parse_number(input('value', 0)));
        } catch (ValidationException $e) {
            flash('error', $e->getMessage());
            redirect('admin/orders/' . $order['id']);
        }
        flash('success', 'Entwurf erstellt. Prüfen Sie die Angaben und senden Sie sie anschliessend.');
        redirect('admin/invoices/' . $id);
    }

    public function update(string $id): void
    {
        Auth::requireUser();
        $inv = $this->find($id);
        try {
            InvoiceService::updateDraft($inv, $_POST);
            flash('success', 'Entwurf gespeichert.');
        } catch (ValidationException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/invoices/' . $inv['id']);
    }

    public function send(string $id): void
    {
        $user = Auth::requireUser();
        $inv = $this->find($id);
        if (!in_array($inv['status'], ['draft', 'sent'], true) && !($inv['kind'] === 'credit' && $inv['status'] === 'paid' && input('resend'))) {
            flash('error', 'Diese Rechnung kann nicht versendet werden.');
            redirect('admin/invoices/' . $inv['id']);
        }
        try {
            if ($inv['status'] === 'draft') {
                if ($inv['kind'] !== 'credit') {
                    $errs = array_filter(TaxService::review($inv['customer'], $inv['taxes'], $inv['issue_date'], (string) $inv['area_type'], true), static fn($r) => $r['level'] === 'error');
                    if ($errs) {
                        throw new ValidationException('Nicht versendet: ' . implode(' ', array_column($errs, 'text')));
                    }
                    InvoiceService::transition((int) $inv['id'], 'sent', $user);
                } else {
                    InvoiceService::issueCredit((int) $inv['id'], $user);
                }
            }
        } catch (ValidationException $e) {
            flash('error', $e->getMessage());
            redirect('admin/invoices/' . $inv['id']);
        }
        $fresh = $this->find($id);
        if (Notifier::invoiceSent($fresh)) {
            DB::exec('UPDATE invoices SET sent_at = ? WHERE id = ?', [now(), $inv['id']]);
            flash('success', 'Rechnung an ' . ($fresh['customer']['email'] ?? '') . ' gesendet.');
        } else {
            flash('error', 'Die Rechnung ist als „offen“ markiert, die E-Mail konnte aber nicht gesendet werden: ' . Notifier::$lastError);
        }
        redirect('admin/invoices/' . $inv['id']);
    }

    public function remind(string $id): void
    {
        $user = Auth::requireUser();
        $inv = $this->find($id);
        if ($inv['status'] !== 'sent') {
            flash('error', 'Erinnerungen sind nur für offene Rechnungen möglich.');
            redirect('admin/invoices/' . $inv['id']);
        }
        if (Notifier::invoiceSent($inv, true)) {
            InvoiceService::markReminder((int) $inv['id'], $user);
            flash('success', 'Zahlungserinnerung gesendet.');
        } else {
            flash('error', 'E-Mail fehlgeschlagen: ' . Notifier::$lastError);
        }
        redirect('admin/invoices/' . $inv['id']);
    }

    public function paid(string $id): void
    {
        $user = Auth::requireUser();
        $inv = $this->find($id);
        $raw = trim((string) input('amount', ''));
        $amount = $raw === '' ? (float) $inv['open_amount'] : parse_number($raw);
        try {
            $res = InvoiceService::addPayment((int) $inv['id'], $amount, input_str('paid_at', 10) ?: null, input_str('method', 10) ?: 'bank', input_str('note', 255) ?: null, $user);
            flash('success', $res['complete'] ? 'Zahlung erfasst – die Rechnung ist vollständig bezahlt.' : 'Teilzahlung erfasst.');
        } catch (ValidationException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/invoices/' . $inv['id']);
    }

    public function discount(string $id): void
    {
        $user = Auth::requireUser();
        try {
            InvoiceService::addDiscount((int) $id, parse_number(input('amount', 0)), input_str('date', 10) ?: null, input_str('note', 255) ?: null, $user);
            flash('success', 'Skonto/Preisnachlass erfasst – die MWST wird in der Abrechnung entsprechend korrigiert.');
        } catch (ValidationException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/invoices/' . (int) $id);
    }

    public function removeAdjustment(string $id, string $aid): void
    {
        $user = Auth::requireUser();
        try {
            InvoiceService::removeAdjustment((int) $id, (int) $aid, $user);
            flash('success', 'Eintrag entfernt.');
        } catch (ValidationException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/invoices/' . (int) $id);
    }

    public function refund(string $id): void
    {
        $user = Auth::requireUser();
        try {
            InvoiceService::addRefund((int) $id, parse_number(input('amount', 0)), input_str('date', 10) ?: null, input_str('note', 255) ?: null, $user);
            flash('success', 'Rückerstattung erfasst.');
        } catch (ValidationException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/invoices/' . (int) $id);
    }

    public function credit(string $id): void
    {
        $user = Auth::requireUser();
        $inv = $this->find($id);
        $raw = trim((string) input('amount', ''));
        try {
            $cid = InvoiceService::createCredit($inv, $raw === '' ? null : parse_number($raw), input_str('reason', 120), $user);
        } catch (ValidationException $e) {
            flash('error', $e->getMessage());
            redirect('admin/invoices/' . $inv['id']);
        }
        flash('success', 'Gutschriftsentwurf erstellt. Prüfen Sie ihn und stellen Sie ihn aus.');
        redirect('admin/invoices/' . $cid);
    }

    public function removePayment(string $id, string $pid): void
    {
        $user = Auth::requireUser();
        try {
            InvoiceService::removePayment((int) $id, (int) $pid, $user);
            flash('success', 'Zahlung entfernt.');
        } catch (ValidationException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/invoices/' . (int) $id);
    }

    public function plan(string $id): void
    {
        Auth::requireUser();
        $inv = $this->find($id);
        try {
            if (input('remove')) {
                InvoiceService::removePlan($inv);
                flash('success', 'Zahlungsplan entfernt.');
            } else {
                InvoiceService::createPlan($inv, (int) input('count', 2), input_str('first_due', 10), (int) input('every', 1));
                flash('success', 'Zahlungsplan erstellt.');
            }
        } catch (ValidationException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/invoices/' . $inv['id']);
    }

    public function cancel(string $id): void
    {
        $user = Auth::requireUser();
        $this->transition($this->find($id), 'cancelled', $user, input_str('note', 255) ?: null, null, 'Rechnung storniert. Die Nummer bleibt reserviert.');
    }

    private function transition(array $inv, string $to, array $user, ?string $note, ?string $paidAt, string $msg): void
    {
        try {
            InvoiceService::transition((int) $inv['id'], $to, $user, $note, $paidAt);
            flash('success', $msg);
        } catch (ValidationException $e) {
            flash('error', $e->getMessage());
        }
        redirect('admin/invoices/' . $inv['id']);
    }
}
