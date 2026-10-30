<?php
/**
 * Rechnungen: werden aus einem Auftrag erzeugt (Beträge werden aus der angenommenen Offerte
 * übernommen und sind danach unveränderlich). Nummern sind lückenlos – darum gibt es kein
 * Löschen, sondern nur Stornieren.
 */
declare(strict_types=1);

final class InvoiceService
{
    /** SQL: offener Betrag einer Rechnung (Alias i): Rechnungsbetrag − Zahlungen − Skonto/Gutschriften. */
    public const OPEN_SQL = '(i.amount_due - COALESCE((SELECT SUM(p.amount) FROM invoice_payments p WHERE p.invoice_id = i.id), 0) - COALESCE((SELECT SUM(a.amount) FROM invoice_adjustments a WHERE a.invoice_id = i.id), 0))';

    public static function find(int $id): ?array
    {
        return self::hydrate(DB::one('SELECT * FROM invoices WHERE id = ?', [$id]));
    }

    public static function findPublic(string $publicId): ?array
    {
        if (!preg_match('/^[A-Za-z0-9]{24}$/', $publicId)) {
            return null;
        }
        return self::hydrate(DB::one('SELECT * FROM invoices WHERE public_id = ?', [$publicId]));
    }

    private static function hydrate(?array $i): ?array
    {
        if (!$i) {
            return null;
        }
        $i['customer'] = json_decode((string) $i['customer_snapshot'], true) ?: [];
        $i['delivery'] = $i['delivery_address'] ? (json_decode((string) $i['delivery_address'], true) ?: null) : null;
        $i['taxes'] = json_decode((string) $i['tax_breakdown'], true) ?: [];
        $i['items'] = DB::all('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY position', [$i['id']]);
        $i['deduction_list'] = json_decode((string) $i['deductions'], true) ?: [];
        $i['payments'] = DB::all('SELECT * FROM invoice_payments WHERE invoice_id = ? ORDER BY paid_on, id', [$i['id']]);
        $i['paid_total'] = Calc::r2((float) array_sum(array_column($i['payments'], 'amount')));
        $i['adjustments'] = DB::all('SELECT a.*, c.number AS credit_number, c.public_id AS credit_public_id FROM invoice_adjustments a LEFT JOIN invoices c ON c.id = a.credit_invoice_id WHERE a.invoice_id = ? ORDER BY a.adj_date, a.id', [$i['id']]);
        $i['adjusted_total'] = Calc::r2((float) array_sum(array_column($i['adjustments'], 'amount')));
        $i['credit_of_number'] = $i['credit_of'] ? DB::val('SELECT number FROM invoices WHERE id = ?', [$i['credit_of']]) : null;
        $i['open_amount'] = ($i['status'] === 'cancelled' || $i['kind'] === 'credit') ? 0.0 : max(0.0, Calc::r2((float) $i['amount_due'] - $i['paid_total'] - $i['adjusted_total']));
        $i['installments'] = self::plan((int) $i['id'], Calc::r2($i['paid_total'] + $i['adjusted_total']));
        $i['pay_amount'] = self::nextAmount($i['installments'], (float) $i['open_amount']);
        $i['display_status'] = Status::invoiceDisplay($i);
        return $i;
    }

    /* ---------------------------------------------------------------- Zahlungsplan & Zahlungseingänge */

    /**
     * Raten einer Rechnung mit Deckungsstand. Zahlungen decken die Raten der Reihe nach (kumuliert).
     * @return array<int, array{id:int,position:int,label:string,due_date:string,amount:float,paid:float,remaining:float,state:string}>
     */
    public static function plan(int $invoiceId, float $paidTotal): array
    {
        $rows = DB::all('SELECT * FROM invoice_installments WHERE invoice_id = ? ORDER BY position', [$invoiceId]);
        $cum = 0.0;
        $out = [];
        $today = date('Y-m-d');
        foreach ($rows as $r) {
            $amt = (float) $r['amount'];
            $before = $cum;
            $cum = Calc::r2($cum + $amt);
            $paid = max(0.0, min($amt, Calc::r2($paidTotal - $before)));
            $remaining = Calc::r2($amt - $paid);
            $state = $remaining <= 0.004 ? 'paid' : ($r['due_date'] < $today ? 'overdue' : ($paid > 0 ? 'partial' : 'open'));
            $out[] = ['id' => (int) $r['id'], 'position' => (int) $r['position'], 'label' => (string) $r['label'], 'due_date' => (string) $r['due_date'],
                'amount' => $amt, 'paid' => $paid, 'remaining' => $remaining, 'state' => $state];
        }
        return $out;
    }

    /** Bereits erledigter Betrag: Zahlungen (abzgl. Rückerstattungen) plus Skonto/Gutschriften. */
    public static function settled(int $invoiceId): float
    {
        return Calc::r2((float) DB::val('SELECT COALESCE((SELECT SUM(amount) FROM invoice_payments WHERE invoice_id = ?), 0) + COALESCE((SELECT SUM(amount) FROM invoice_adjustments WHERE invoice_id = ?), 0)', [$invoiceId, $invoiceId]));
    }

    /** Betrag, den der Kunde jetzt online bezahlen kann: Rest der nächsten Rate bzw. der ganze offene Betrag. */
    private static function nextAmount(array $plan, float $open): float
    {
        foreach ($plan as $r) {
            if ($r['remaining'] > 0.004) {
                return min($open, $r['remaining']);
            }
        }
        return $open;
    }

    /** Fälligkeitsdatum der Rechnung = Fälligkeit der nächsten unbezahlten Rate (so greifen Überfällig-Logik und Erinnerungen). */
    private static function syncDue(int $invoiceId): void
    {
        $paid = self::settled($invoiceId);
        $plan = self::plan($invoiceId, $paid);
        if (!$plan) {
            return;
        }
        $due = end($plan)['due_date'];
        foreach ($plan as $r) {
            if ($r['remaining'] > 0.004) {
                $due = $r['due_date'];
                break;
            }
        }
        DB::exec('UPDATE invoices SET due_date = ? WHERE id = ?', [$due, $invoiceId]);
    }

    /**
     * Erstellt einen Zahlungsplan (gleich hohe Raten, Rundungsrest auf der letzten Rate).
     * Nur für Entwürfe und offene Rechnungen ohne bisherige Zahlung.
     */
    public static function createPlan(array $inv, int $count, string $firstDue, int $everyMonths): void
    {
        if (!in_array($inv['status'], ['draft', 'sent'], true) || $inv['paid_total'] > 0) {
            throw new ValidationException('Ein Zahlungsplan kann nur für Entwürfe oder offene Rechnungen ohne bisherige Zahlung erstellt werden.');
        }
        $count = max(2, min(12, $count));
        $everyMonths = max(1, min(6, $everyMonths));
        $first = self::validDate($firstDue);
        if (!$first) {
            throw new ValidationException('Bitte geben Sie ein gültiges Datum für die erste Rate ein.');
        }
        $total = (float) $inv['amount_due'];
        if ($total < 0.05 * $count) {
            throw new ValidationException('Der Rechnungsbetrag ist zu klein für diese Anzahl Raten.');
        }
        $part = floor($total / $count * 20) / 20;   // auf 5 Rappen abgerundet
        DB::tx(static function () use ($inv, $count, $first, $everyMonths, $total, $part): void {
            DB::exec('DELETE FROM invoice_installments WHERE invoice_id = ?', [$inv['id']]);
            $sum = 0.0;
            for ($k = 1; $k <= $count; $k++) {
                $amount = $k === $count ? Calc::r2($total - $sum) : $part;
                $sum = Calc::r2($sum + $amount);
                $due = (new DateTimeImmutable($first))->modify('+' . (($k - 1) * $everyMonths) . ' months')->format('Y-m-d');
                DB::insert('invoice_installments', ['invoice_id' => $inv['id'], 'position' => $k, 'label' => "Rate $k/$count", 'due_date' => $due, 'amount' => $amount]);
            }
            self::syncDue((int) $inv['id']);
            History::add('invoice', (int) $inv['id'], 'note', null, null, "Zahlungsplan mit $count Raten erstellt.", false, null);
        });
    }

    public static function removePlan(array $inv): void
    {
        if ($inv['paid_total'] > 0) {
            throw new ValidationException('Der Zahlungsplan kann nach einer Zahlung nicht mehr entfernt werden.');
        }
        DB::exec('DELETE FROM invoice_installments WHERE invoice_id = ?', [$inv['id']]);
        History::add('invoice', (int) $inv['id'], 'note', null, null, 'Zahlungsplan entfernt.', false, null);
    }

    /**
     * Erfasst einen Zahlungseingang. Ist der offene Betrag danach 0, wird die Rechnung „bezahlt“.
     * @return array{id:int, complete:bool, amount:float}
     */
    public static function addPayment(int $invoiceId, float $amount, ?string $paidOn, string $method, ?string $note, ?array $user, ?string $reference = null, ?int $providerPaymentId = null): array
    {
        $amount = Calc::r2($amount);
        $method = in_array($method, ['bank', 'cash', 'online', 'other'], true) ? $method : 'bank';
        $res = DB::tx(static function () use ($invoiceId, $amount, $paidOn, $method, $note, $user, $reference, $providerPaymentId): array {
            $inv = DB::one('SELECT * FROM invoices WHERE id = ? FOR UPDATE', [$invoiceId]);
            if (!$inv) {
                throw new HttpException(404, 'Rechnung nicht gefunden.');
            }
            if ($inv['status'] !== 'sent') {
                throw new ValidationException($inv['status'] === 'draft' ? 'Zahlungen können erst erfasst werden, wenn die Rechnung versendet wurde.' : 'Für diese Rechnung kann keine Zahlung erfasst werden.');
            }
            if ($inv['kind'] === 'credit') {
                throw new ValidationException('Für eine Gutschrift kann keine Zahlung erfasst werden.');
            }
            $open = Calc::r2((float) $inv['amount_due'] - self::settled($invoiceId));
            if ($amount <= 0) {
                throw new ValidationException('Bitte geben Sie einen Betrag grösser als 0 ein.');
            }
            if ($amount > $open + 0.004) {
                throw new ValidationException('Der Betrag übersteigt den offenen Betrag (' . money($open, $inv['currency']) . ').');
            }
            $on = self::validDate($paidOn) ?? date('Y-m-d');
            if ($on > date('Y-m-d')) {
                throw new ValidationException('Das Zahlungsdatum darf nicht in der Zukunft liegen.');
            }
            $pid = DB::insert('invoice_payments', [
                'invoice_id' => $invoiceId, 'amount' => $amount, 'paid_on' => $on, 'method' => $method,
                'reference' => $reference ? mb_substr($reference, 0, 120) : null, 'note' => $note ? mb_substr($note, 0, 255) : null,
                'provider_payment_id' => $providerPaymentId, 'created_by' => $user['id'] ?? null, 'created_by_name' => $user['name'] ?? null, 'created_at' => now(),
            ]);
            $complete = $open - $amount <= 0.004;
            $label = ['bank' => 'Überweisung', 'cash' => 'Bar', 'online' => 'Online', 'other' => 'Sonstige'][$method];
            History::add('invoice', $invoiceId, 'payment', null, null, 'Zahlung ' . money($amount, $inv['currency']) . ' erfasst (' . $label . ($note ? ', ' . $note : '') . ')' . ($complete ? ' – Rechnung vollständig bezahlt.' : '; offen: ' . money($open - $amount, $inv['currency']) . '.'), false, $user);
            if ($complete) {
                self::transition($invoiceId, 'paid', $user ?? ['id' => null, 'name' => 'System'], $note, $on);
            } else {
                self::syncDue($invoiceId);
            }
            return ['id' => $pid, 'complete' => $complete, 'amount' => $amount];
        });
        if (!$res['complete']) {
            $row = DB::one('SELECT * FROM invoices WHERE id = ?', [$invoiceId]);
            if ($row) {
                Webhooks::dispatch('invoice.partial_payment', Webhooks::invoiceData($row) + ['payment_amount' => $amount]);
            }
        }
        return $res;
    }

    /** Entfernt einen manuell erfassten Zahlungseingang (Online-Zahlungen nur beim Zahlungsanbieter rückerstattbar). */
    public static function removePayment(int $invoiceId, int $paymentRowId, array $user): void
    {
        DB::tx(static function () use ($invoiceId, $paymentRowId, $user): void {
            $inv = DB::one('SELECT * FROM invoices WHERE id = ? FOR UPDATE', [$invoiceId]);
            $row = DB::one('SELECT * FROM invoice_payments WHERE id = ? AND invoice_id = ?', [$paymentRowId, $invoiceId]);
            if (!$inv || !$row) {
                throw new HttpException(404, 'Zahlung nicht gefunden.');
            }
            if ($row['provider_payment_id']) {
                throw new ValidationException('Online-Zahlungen können hier nicht entfernt werden. Erstatten Sie den Betrag beim Zahlungsanbieter zurück und halten Sie es in einer Notiz fest.');
            }
            if (!in_array($inv['status'], ['sent', 'paid'], true)) {
                throw new ValidationException('Für diese Rechnung kann keine Zahlung entfernt werden.');
            }
            DB::exec('DELETE FROM invoice_payments WHERE id = ?', [$paymentRowId]);
            History::add('invoice', $invoiceId, 'payment', null, null, ($row['method'] === 'refund' ? 'Rückerstattung ' : 'Zahlung ') . money(abs((float) $row['amount']), $inv['currency']) . ' vom ' . fmt_date($row['paid_on']) . ' entfernt.', false, $user);
            self::reconcile($invoiceId, $user, 'Zahlung entfernt – Rechnung ist wieder offen.');
            self::syncDue($invoiceId);
        });
    }

    /** Gleicht den Status mit dem offenen Betrag ab (bezahlt ⇄ offen), z. B. nach Skonto, Gutschrift oder Rückerstattung. */
    public static function reconcile(int $invoiceId, array $user, string $reopenNote = 'Offener Betrag – Rechnung ist wieder offen.', ?string $paidAt = null): void
    {
        $inv = DB::one('SELECT * FROM invoices WHERE id = ?', [$invoiceId]);
        if (!$inv || $inv['kind'] === 'credit') {
            return;
        }
        $open = Calc::r2((float) $inv['amount_due'] - self::settled($invoiceId));
        $ledger = (int) DB::val('SELECT (SELECT COUNT(*) FROM invoice_payments WHERE invoice_id = ?) + (SELECT COUNT(*) FROM invoice_adjustments WHERE invoice_id = ?)', [$invoiceId, $invoiceId]);
        if ($inv['status'] === 'sent' && $open <= 0.004 && $ledger > 0) {
            self::transition($invoiceId, 'paid', $user, 'Ausgeglichen', $paidAt ?? date('Y-m-d'));
        } elseif ($inv['status'] === 'paid' && $open > 0.004) {
            self::transition($invoiceId, 'sent', $user, $reopenNote);
        }
    }

    /**
     * Skonto / nachträglicher Preisnachlass ohne eigenen Beleg. Mindert das Entgelt und damit die geschuldete MWST
     * (Art. 41 MWSTG; Ziff. 235 der Abrechnung). Der Betrag ist brutto (inkl. MWST).
     */
    public static function addDiscount(int $invoiceId, float $amount, ?string $date, ?string $note, array $user): void
    {
        $amount = Calc::r2($amount);
        DB::tx(static function () use ($invoiceId, $amount, $date, $note, $user): void {
            $inv = DB::one('SELECT * FROM invoices WHERE id = ? FOR UPDATE', [$invoiceId]);
            if (!$inv || $inv['status'] !== 'sent' || $inv['kind'] === 'credit') {
                throw new ValidationException('Skonto oder Preisnachlass kann nur bei offenen Rechnungen erfasst werden.');
            }
            $open = Calc::r2((float) $inv['amount_due'] - self::settled($invoiceId));
            if ($amount <= 0 || $amount > $open + 0.004) {
                throw new ValidationException('Der Nachlass muss grösser als 0 und höchstens so gross wie der offene Betrag (' . money($open, $inv['currency']) . ') sein.');
            }
            $on = self::validDate($date) ?? date('Y-m-d');
            if ($on > date('Y-m-d')) {
                throw new ValidationException('Das Datum darf nicht in der Zukunft liegen.');
            }
            DB::insert('invoice_adjustments', ['invoice_id' => $invoiceId, 'kind' => 'discount', 'amount' => $amount, 'adj_date' => $on,
                'note' => $note ? mb_substr($note, 0, 255) : null, 'created_by_name' => $user['name'] ?? null, 'created_at' => now()]);
            History::add('invoice', $invoiceId, 'payment', null, null, 'Skonto/Preisnachlass ' . money($amount, $inv['currency']) . ' erfasst' . ($note ? ' (' . $note . ')' : '') . '.', false, $user);
            self::reconcile($invoiceId, $user, 'Nachlass entfernt.', $on);
            self::syncDue($invoiceId);
        });
    }

    public static function removeAdjustment(int $invoiceId, int $adjId, array $user): void
    {
        DB::tx(static function () use ($invoiceId, $adjId, $user): void {
            $row = DB::one('SELECT * FROM invoice_adjustments WHERE id = ? AND invoice_id = ?', [$adjId, $invoiceId]);
            if (!$row) {
                throw new HttpException(404, 'Eintrag nicht gefunden.');
            }
            if ($row['kind'] !== 'discount') {
                throw new ValidationException('Gutschriften sind unveränderlich. Die Gutschrift ist ein Beleg mit eigener Nummer.');
            }
            DB::exec('DELETE FROM invoice_adjustments WHERE id = ?', [$adjId]);
            History::add('invoice', $invoiceId, 'payment', null, null, 'Skonto/Preisnachlass ' . money((float) $row['amount']) . ' entfernt.', false, $user);
            self::reconcile($invoiceId, $user, 'Nachlass entfernt – Rechnung ist wieder offen.');
            self::syncDue($invoiceId);
        });
    }

    /** Rückerstattung an den Kunden (z. B. nach Gutschrift einer bereits bezahlten Rechnung). Wird als negative Zahlung geführt. */
    public static function addRefund(int $invoiceId, float $amount, ?string $date, ?string $note, array $user): void
    {
        $amount = Calc::r2($amount);
        DB::tx(static function () use ($invoiceId, $amount, $date, $note, $user): void {
            $inv = DB::one('SELECT * FROM invoices WHERE id = ? FOR UPDATE', [$invoiceId]);
            if (!$inv || !in_array($inv['status'], ['sent', 'paid'], true) || $inv['kind'] === 'credit') {
                throw new ValidationException('Für diese Rechnung kann keine Rückerstattung erfasst werden.');
            }
            $paid = (float) DB::val('SELECT COALESCE(SUM(amount),0) FROM invoice_payments WHERE invoice_id = ?', [$invoiceId]);
            if ($amount <= 0 || $amount > $paid + 0.004) {
                throw new ValidationException('Die Rückerstattung muss grösser als 0 und höchstens so gross wie die erhaltenen Zahlungen (' . money($paid, $inv['currency']) . ') sein.');
            }
            $on = self::validDate($date) ?? date('Y-m-d');
            if ($on > date('Y-m-d')) {
                throw new ValidationException('Das Datum darf nicht in der Zukunft liegen.');
            }
            DB::insert('invoice_payments', ['invoice_id' => $invoiceId, 'amount' => -$amount, 'paid_on' => $on, 'method' => 'refund', 'reference' => null,
                'note' => $note ? mb_substr($note, 0, 255) : null, 'created_by' => $user['id'] ?? null, 'created_by_name' => $user['name'] ?? null, 'created_at' => now()]);
            History::add('invoice', $invoiceId, 'payment', null, null, 'Rückerstattung ' . money($amount, $inv['currency']) . ' erfasst' . ($note ? ' (' . $note . ')' : '') . '.', false, $user);
            self::reconcile($invoiceId, $user, 'Rückerstattung – Rechnung ist wieder offen.');
            self::syncDue($invoiceId);
        });
    }

    /**
     * Gutschrift (Korrekturbeleg) zu einer versendeten oder bezahlten Rechnung: Entwurf mit negativen Beträgen.
     * Die Steueraufteilung folgt der Originalrechnung anteilig. Beim Ausstellen verrechnet sie sich mit der Rechnung.
     * @param float|null $amount Brutto-Betrag; null = volle Gutschrift
     */
    public static function createCredit(array $inv, ?float $amount, string $reason, array $user): int
    {
        if (!in_array($inv['status'], ['sent', 'paid'], true) || $inv['kind'] === 'credit') {
            throw new ValidationException('Eine Gutschrift ist nur zu versendeten oder bezahlten Rechnungen möglich.');
        }
        return DB::tx(static function () use ($inv, $amount, $reason, $user): int {
            $locked = DB::one('SELECT * FROM invoices WHERE id = ? FOR UPDATE', [$inv['id']]);
            $due = (float) $locked['amount_due'];
            $credited = (float) DB::val("SELECT COALESCE(SUM(-amount_due),0) FROM invoices WHERE credit_of = ? AND kind = 'credit' AND status IN ('draft','paid')", [$inv['id']]);
            $room = Calc::r2($due - $credited);
            if ($due <= 0 || $room <= 0.004) {
                throw new ValidationException('Diese Rechnung ist bereits vollständig gutgeschrieben.');
            }
            $full = $amount === null || $amount <= 0;
            $amount = $full ? $room : Calc::r2($amount);
            if ($amount > $room + 0.004) {
                throw new ValidationException('Die Gutschrift übersteigt den noch gutschreibbaren Betrag (' . money($room, $inv['currency']) . ').');
            }
            $p = $amount / $due;
            $items = [];
            foreach (ReportService::effectiveGroups($locked) as $g) {
                $net = Calc::r2((float) $g['base'] * $p);
                if (abs($net) < 0.005) {
                    continue;
                }
                $items[] = [
                    'title' => 'Gutschrift zu Rechnung ' . $locked['number'] . ($reason !== '' ? ' – ' . $reason : '') . (count(ReportService::effectiveGroups($locked)) > 1 ? ' (' . ($g['label'] ?: 'ohne Steuer') . ')' : ''),
                    'description' => null, 'quantity' => 1, 'unit' => 'pauschal', 'unit_price' => -$net,
                    'discount_type' => 'none', 'discount_value' => 0, 'tax_template_id' => null, 'tax_rate' => $g['rate'], 'tax_label' => $g['label'],
                ];
            }
            if (!$items) {
                throw new ValidationException('Der Betrag ist zu klein.');
            }
            $calc = Calc::compute($items, ['round_005' => false]);
            // Gesamtbetrag exakt auf den gutgeschriebenen Betrag ausrichten (Rundung ausserhalb der Steuerbasis).
            $calc['rounding'] = Calc::r2(-$amount - (float) ($calc['net_total'] + $calc['tax_total']));
            $calc['total'] = -$amount;
            $order = DB::one('SELECT * FROM orders WHERE id = ?', [$locked['order_id']]);
            $offer = ['area_type' => $locked['area_type'], 'currency' => $locked['currency'], 'customer_snapshot' => $locked['customer_snapshot'], 'delivery_address' => null,
                'discount_type' => 'none', 'discount_value' => 0, 'shipping_method' => null, 'shipping_cost' => 0, 'delivery_info' => null];
            $id = self::insertInvoice($order, $locked, $offer, 'credit', ['title' => 'Gutschrift zu ' . $locked['number'], 'service_period' => $locked['service_period']],
                $calc['items'], $calc, [], date('Y-m-d'), date('Y-m-d'), null, $user);
            DB::exec('UPDATE invoices SET credit_of = ?, amount_due = ?, intro = NULL, payment_info = NULL WHERE id = ?', [$inv['id'], -$amount, $id]);
            History::add('invoice', (int) $inv['id'], 'note', null, null, 'Gutschriftsentwurf ' . DB::val('SELECT number FROM invoices WHERE id = ?', [$id]) . ' über ' . money($amount, $inv['currency']) . ' erstellt.', false, $user);
            return $id;
        });
    }

    /** Stellt eine Gutschrift aus (Entwurf → ausgestellt) und verrechnet sie mit der Originalrechnung. */
    public static function issueCredit(int $creditId, array $user): void
    {
        DB::tx(static function () use ($creditId, $user): void {
            $cr = DB::one('SELECT * FROM invoices WHERE id = ? FOR UPDATE', [$creditId]);
            if (!$cr || $cr['kind'] !== 'credit' || $cr['status'] !== 'draft' || !$cr['credit_of']) {
                throw new ValidationException('Nur ein Gutschriftsentwurf kann ausgestellt werden.');
            }
            $orig = DB::one('SELECT * FROM invoices WHERE id = ? FOR UPDATE', [$cr['credit_of']]);
            if (!$orig || !in_array($orig['status'], ['sent', 'paid'], true)) {
                throw new ValidationException('Die Originalrechnung ist nicht mehr gültig.');
            }
            $amount = Calc::r2(-(float) $cr['amount_due']);
            $already = (float) DB::val("SELECT COALESCE(SUM(amount),0) FROM invoice_adjustments WHERE invoice_id = ? AND kind = 'credit'", [$orig['id']]);
            if ($amount + $already > (float) $orig['amount_due'] + 0.004) {
                throw new ValidationException('Die Gutschriften würden den Rechnungsbetrag übersteigen.');
            }
            $on = $cr['issue_date'];
            DB::update('invoices', ['status' => 'paid', 'sent_at' => now(), 'paid_at' => $on, 'payment_note' => 'Gutschrift – mit Rechnung ' . $orig['number'] . ' verrechnet', 'updated_at' => now()], 'id = :id', [':id' => $creditId]);
            DB::insert('invoice_adjustments', ['invoice_id' => (int) $orig['id'], 'kind' => 'credit', 'amount' => $amount, 'adj_date' => $on, 'credit_invoice_id' => $creditId,
                'note' => 'Gutschrift ' . $cr['number'], 'created_by_name' => $user['name'] ?? null, 'created_at' => now()]);
            History::add('invoice', $creditId, 'sent', 'draft', 'paid', 'Gutschrift ausgestellt und mit ' . $orig['number'] . ' verrechnet.', false, $user);
            History::add('invoice', (int) $orig['id'], 'payment', null, null, 'Gutschrift ' . $cr['number'] . ' über ' . money($amount, $orig['currency']) . ' verrechnet.', false, $user);
            self::reconcile((int) $orig['id'], $user, 'Gutschrift – Rechnung ist wieder offen.', $on);
            self::syncDue((int) $orig['id']);
        });
    }

    public static function url(array $invoice): string
    {
        return url('r/' . $invoice['public_id']);
    }

    /** Aktive (nicht stornierte) Rechnungen eines Auftrags (ohne Abo-Rechnungen). */
    public static function activeForOrder(int $orderId): array
    {
        return DB::all("SELECT * FROM invoices WHERE order_id = ? AND subscription_id IS NULL AND status <> 'cancelled' ORDER BY id", [$orderId]);
    }

    /** Übersicht für die Auftragsseite: Anzahlungen, Schlussrechnung, noch offener Rahmen. */
    public static function orderOverview(array $order): array
    {
        $list = self::activeForOrder((int) $order['id']);
        $advances = array_values(array_filter($list, static fn($i) => $i['kind'] === 'advance'));
        $final = null;
        foreach ($list as $i) {
            if ($i['kind'] === 'final') {
                $final = $i;
            }
        }
        $advTotal = Calc::r2((float) array_sum(array_column($advances, 'total')));
        return [
            'list' => $list, 'advances' => $advances, 'final' => $final,
            'advance_total' => $advTotal, 'remaining' => max(0.0, Calc::r2((float) $order['total'] - $advTotal)),
        ];
    }

    public static function createFromOrder(array $order, ?string $issueDate, ?int $paymentDays, array $user, string $kind = 'final', string $mode = 'percent', float $value = 0): int
    {
        $issue = self::validDate($issueDate) ?? date('Y-m-d');
        $days = $paymentDays ?? (int) Settings::get('invoice_payment_days', 30);
        $days = max(0, min(365, $days));
        $due = date('Y-m-d', strtotime($issue . " +$days days"));
        $kind = $kind === 'advance' ? 'advance' : 'final';

        return DB::tx(static function () use ($order, $issue, $due, $user, $kind, $mode, $value): int {
            $locked = DB::one('SELECT id, status, offer_id, customer_id, total FROM orders WHERE id = ? FOR UPDATE', [$order['id']]);
            if (!$locked) {
                throw new ValidationException('Auftrag nicht gefunden.');
            }
            if ($locked['status'] === 'cancelled') {
                throw new ValidationException('Für einen stornierten Auftrag kann keine Rechnung erstellt werden.');
            }
            $ov = self::orderOverview($locked);
            if ($ov['final']) {
                throw new ValidationException('Zu diesem Auftrag existiert bereits eine Schlussrechnung.');
            }
            $offer = OfferService::find((int) $locked['offer_id']);
            if (!$offer) {
                throw new ValidationException('Die zugehörige Offerte fehlt.');
            }

            $deductions = [];
            if ($kind === 'advance') {
                [$items, $calc, $label] = self::advanceLines($offer, $ov, $mode, $value);
                $head = ['title' => $label . ' – ' . $offer['title']];
                $totals = $calc;
            } else {
                $items = $offer['items'];
                foreach ($ov['advances'] as $a) {
                    $deductions[] = ['id' => (int) $a['id'], 'number' => $a['number'], 'date' => $a['issue_date'], 'total' => (float) $a['total'], 'taxes' => json_decode((string) $a['tax_breakdown'], true) ?: []];
                }
                $head = ['title' => $offer['title']];
                $totals = [
                    'subtotal' => $offer['subtotal'], 'discount_amount' => $offer['discount_amount'], 'net_total' => $offer['net_total'],
                    'tax_total' => $offer['tax_total'], 'rounding' => $offer['rounding'], 'total' => $offer['total'],
                    'tax_breakdown' => $offer['taxes'],
                ];
            }
            return self::insertInvoice($order, $locked, $offer, $kind, $head, $items, $totals, $deductions, $issue, $due, null, $user);
        });
    }

    /**
     * Anzahlungspositionen: je Steuergruppe der Offerte eine Zeile, proportional zum Anteil.
     * @return array{0: array, 1: array, 2: string}
     */
    private static function advanceLines(array $offer, array $ov, string $mode, float $value): array
    {
        $total = (float) $offer['total'];
        if ($total <= 0) {
            throw new ValidationException('Der Auftrag hat keinen Betrag, der angezahlt werden könnte.');
        }
        if ($mode === 'amount') {
            if ($value <= 0) {
                throw new ValidationException('Bitte geben Sie einen Betrag grösser als 0 ein.');
            }
            $p = $value / $total;
            $label = 'Anzahlung ' . money($value, $offer['currency']);
        } else {
            if ($value <= 0 || $value > 100) {
                throw new ValidationException('Der Prozentsatz muss zwischen 0 und 100 liegen.');
            }
            $p = $value / 100;
            $label = 'Anzahlung ' . rate($value);
        }
        if ($p * $total > $ov['remaining'] + 0.06) {
            throw new ValidationException('Die Anzahlung übersteigt den noch nicht in Rechnung gestellten Betrag (' . money($ov['remaining'], $offer['currency']) . ').');
        }
        $groups = $offer['taxes'];
        $items = [];
        foreach ($groups as $g) {
            $net = Calc::r2((float) $g['base'] * $p);
            if ($net <= 0) {
                continue;
            }
            $items[] = [
                'title' => $label . (count($groups) > 1 ? ' (' . ($g['label'] ?: 'ohne Steuer') . ' ' . rate($g['rate']) . ')' : ''),
                'description' => 'Auf Auftrag ' . ($offer['number'] ?? ''),
                'quantity' => 1, 'unit' => 'pauschal', 'unit_price' => $net,
                'discount_type' => 'none', 'discount_value' => 0,
                'tax_template_id' => null, 'tax_rate' => $g['rate'], 'tax_label' => $g['label'],
            ];
        }
        if (!$items) {
            throw new ValidationException('Der Betrag ist zu klein.');
        }
        $calc = Calc::compute($items, [
            'round_005' => Settings::get('round_005') === '1' && $offer['currency'] === 'CHF',
        ]);
        return [$calc['items'], $calc, $label];
    }

    /** Wiederkehrende Rechnung aus dem Abo (Positionen der Original-Offerte). */
    public static function createRecurring(array $sub, string $runDate, string $periodLabel): int
    {
        $order = DB::one('SELECT * FROM orders WHERE id = ?', [$sub['order_id']]);
        $offer = $order ? OfferService::find((int) $order['offer_id']) : null;
        if (!$order || !$offer) {
            throw new ValidationException('Ursprungsauftrag fehlt.');
        }
        $due = date('Y-m-d', strtotime($runDate . ' +' . (int) $sub['payment_days'] . ' days'));
        $totals = [
            'subtotal' => $offer['subtotal'], 'discount_amount' => $offer['discount_amount'], 'net_total' => $offer['net_total'],
            'tax_total' => $offer['tax_total'], 'rounding' => $offer['rounding'], 'total' => $offer['total'], 'tax_breakdown' => $offer['taxes'],
        ];
        $head = ['title' => $sub['title'], 'delivery_info' => $periodLabel];
        return DB::tx(static fn() => self::insertInvoice($order, $order, $offer, 'final', $head, $offer['items'], $totals, [], $runDate, $due, (int) $sub['id'], null));
    }

    private static function insertInvoice(array $order, array $locked, array $offer, string $kind, array $head, array $items, array $t, array $deductions, string $issue, string $due, ?int $subscriptionId, ?array $user): int
    {
        $publicId = '';
        do {
            $publicId = random_id(24);
        } while (DB::val('SELECT 1 FROM invoices WHERE public_id = ?', [$publicId]));

        $dedSum = Calc::r2((float) array_sum(array_column($deductions, 'total')));
        $amountDue = max(0.0, Calc::r2((float) $t['total'] - $dedSum));
        $tplId = DocTemplate::validId('invoice', $offer['template_id'] ?? null);
        $id = DB::insert('invoices', [
            'template_id'       => $tplId,
            'public_id'         => $publicId,
            'number'            => Counter::invoiceNumber(),
            'order_id'          => (int) $order['id'],
            'customer_id'       => (int) $locked['customer_id'],
            'area_type'         => $offer['area_type'],
            'title'             => mb_substr($head['title'], 0, 190),
            'intro'             => DocTemplate::textFor('invoice', $tplId, 'intro') ?? (Settings::get('invoice_intro') ?: null),
            'footer'            => DocTemplate::textFor('invoice', $tplId, 'closing') ?? (Settings::get('invoice_footer') ?: null),
            'payment_info'      => Settings::get('invoice_payment_info') ?: null,
            'currency'          => $offer['currency'],
            'status'            => 'draft',
            'kind'              => $kind,
            'subscription_id'   => $subscriptionId,
            'amount_due'        => $amountDue,
            'deductions'        => $deductions ? json_encode($deductions, JSON_UNESCAPED_UNICODE) : null,
            'issue_date'        => $issue,
            'due_date'          => $due,
            'customer_snapshot' => $offer['customer_snapshot'],
            'delivery_address'  => $kind === 'advance' ? null : $offer['delivery_address'],
            'tax_exempt'        => 0,
            'tax_exempt_reason' => null,
            'discount_type'     => $kind === 'advance' ? 'none' : $offer['discount_type'],
            'discount_value'    => $kind === 'advance' ? 0 : $offer['discount_value'],
            'shipping_method'   => $kind === 'advance' ? null : $offer['shipping_method'],
            'shipping_cost'     => $kind === 'advance' ? 0 : $offer['shipping_cost'],
            'delivery_info'     => $head['delivery_info'] ?? ($kind === 'advance' ? null : $offer['delivery_info']),
            'service_period'    => $head['service_period'] ?? null,
            'subtotal'          => $t['subtotal'],
            'discount_amount'   => $t['discount_amount'],
            'net_total'         => $t['net_total'],
            'tax_total'         => $t['tax_total'],
            'rounding'          => $t['rounding'],
            'total'             => $t['total'],
            'tax_breakdown'     => json_encode($t['tax_breakdown'], JSON_UNESCAPED_UNICODE),
            'created_by'        => $user['id'] ?? null,
            'created_at'        => now(),
        ]);
        $pos = 0;
        foreach ($items as $it) {
            DB::insert('invoice_items', [
                'invoice_id' => $id, 'position' => ++$pos, 'title' => $it['title'], 'description' => $it['description'] ?? null,
                'quantity' => $it['quantity'], 'unit' => $it['unit'] ?? null, 'unit_price' => $it['unit_price'],
                'discount_type' => $it['discount_type'] ?? 'none', 'discount_value' => $it['discount_value'] ?? 0,
                'tax_template_id' => $it['tax_template_id'] ?? null, 'tax_rate' => $it['tax_rate'], 'tax_label' => $it['tax_label'] ?? null,
                'line_subtotal' => $it['line_subtotal'], 'line_discount' => $it['line_discount'], 'line_total' => $it['line_total'],
                'image' => ItemImage::clean($it['image'] ?? null),
            ]);
        }
        $ref = SwissQr::referenceFor(['id' => $id, 'number' => DB::val('SELECT number FROM invoices WHERE id = ?', [$id])]);
        if ($ref) {
            DB::exec('UPDATE invoices SET qr_reference = ? WHERE id = ?', [$ref, $id]);
        }
        $note = $subscriptionId ? 'Automatisch aus Abo erstellt' : ('Aus Auftrag ' . ($order['number'] ?? ''));
        History::add('invoice', $id, 'created', null, 'draft', $note, false, $user);
        return $id;
    }

    /** Nur Entwürfe: Daten, Texte und Zahlungsinformationen. Beträge bleiben unverändert. */
    public static function updateDraft(array $inv, array $in): void
    {
        if ($inv['status'] !== 'draft') {
            throw new ValidationException('Nur Entwürfe können bearbeitet werden.');
        }
        $issue = self::validDate((string) ($in['issue_date'] ?? ''));
        $due = self::validDate((string) ($in['due_date'] ?? ''));
        if (!$issue || !$due) {
            throw new ValidationException('Bitte geben Sie gültige Daten ein.');
        }
        if ($due < $issue) {
            throw new ValidationException('Das Fälligkeitsdatum darf nicht vor dem Rechnungsdatum liegen.');
        }
        DB::update('invoices', [
            'issue_date'   => $issue,
            'due_date'     => $due,
            'intro'        => mb_substr(trim((string) ($in['intro'] ?? '')), 0, 5000) ?: null,
            'footer'       => mb_substr(trim((string) ($in['footer'] ?? '')), 0, 5000) ?: null,
            'payment_info' => mb_substr(trim((string) ($in['payment_info'] ?? '')), 0, 5000) ?: null,
            'template_id'  => DocTemplate::validId('invoice', $in['template_id'] ?? null),
            'service_period' => mb_substr(trim((string) ($in['service_period'] ?? '')), 0, 120) ?: null,
            'updated_at'   => now(),
        ], 'id = :id', [':id' => $inv['id']]);
    }

    /** Statusübergang mit Sperre und Historie. Erlaubt: draft→sent, draft|sent→cancelled, sent→paid, paid→sent. */
    public static function transition(int $id, string $to, array $user, ?string $note = null, ?string $paidAt = null): array
    {
        $allowed = ['draft' => ['sent', 'cancelled'], 'sent' => ['paid', 'cancelled', 'sent'], 'paid' => ['sent'], 'cancelled' => []];
        $cur = DB::tx(static function () use ($id, $to, $user, $note, $paidAt, $allowed): array {
            $cur = DB::one('SELECT * FROM invoices WHERE id = ? FOR UPDATE', [$id]);
            if (!$cur) {
                throw new HttpException(404, 'Rechnung nicht gefunden.');
            }
            if (!in_array($to, $allowed[$cur['status']] ?? [], true)) {
                throw new ValidationException('Dieser Statuswechsel ist nicht möglich.');
            }
            if ($cur['kind'] === 'credit' && !($cur['status'] === 'draft' && $to === 'cancelled')) {
                throw new ValidationException('Gutschriften werden über «Gutschrift ausstellen» ausgestellt und können danach nicht mehr geändert werden.');
            }
            if ($to === 'cancelled' && self::settled($id) != 0.0) {
                throw new ValidationException('Für diese Rechnung sind Zahlungen, Skonto oder Gutschriften erfasst. Eine versendete Rechnung wird per Gutschrift korrigiert (Art. 41 MWSTG); entfernen Sie sonst zuerst die Zahlungen.');
            }
            if ($to === 'cancelled' && $cur['status'] === 'sent') {
                $linked = DB::val("SELECT 1 FROM invoices WHERE credit_of = ? AND status = 'draft' LIMIT 1", [$id]);
                if ($linked) {
                    throw new ValidationException('Zu dieser Rechnung existiert ein Gutschriftsentwurf. Stellen Sie ihn aus oder stornieren Sie ihn zuerst.');
                }
            }
            if ($to === 'cancelled' && $cur['kind'] === 'advance' && DB::val("SELECT 1 FROM invoices WHERE order_id = ? AND kind = 'final' AND subscription_id IS NULL AND status <> 'cancelled' LIMIT 1", [$cur['order_id']])) {
                throw new ValidationException('Diese Anzahlungsrechnung ist in der Schlussrechnung verrechnet. Stornieren Sie zuerst die Schlussrechnung.');
            }
            $upd = ['status' => $to, 'updated_at' => now()];
            if ($to === 'sent' && $cur['status'] === 'draft') {
                $upd['sent_at'] = now();
            }
            if ($to === 'paid') {
                $upd['paid_at'] = self::validDate($paidAt) ?? date('Y-m-d');
                $upd['payment_note'] = $note ? mb_substr($note, 0, 255) : null;
            }
            if ($cur['status'] === 'paid' && $to === 'sent') {
                $upd['paid_at'] = null;
                $upd['payment_note'] = null;
            }
            if ($to === 'cancelled' && $cur['status'] === 'sent') {
                $upd['payment_note'] = $note ? mb_substr($note, 0, 255) : null;
            }
            DB::update('invoices', $upd, 'id = :id', [':id' => $id]);
            $event = ($to === 'paid' || $cur['status'] === 'paid') ? 'payment' : (($cur['status'] === 'draft' && $to === 'sent') ? 'sent' : 'status');
            History::add('invoice', $id, $event, $cur['status'], $to, $note, false, $user);
            return $cur;
        });
        $hook = ['sent' => 'invoice.sent', 'paid' => 'invoice.paid', 'cancelled' => 'invoice.cancelled'][$to] ?? null;
        if ($hook && $cur['status'] !== $to) {
            Webhooks::dispatch($hook, Webhooks::invoiceData(DB::one('SELECT * FROM invoices WHERE id = ?', [$id]) ?: $cur));
        }
        return $cur;
    }

    public static function markReminder(int $id, array $user): void
    {
        DB::exec('UPDATE invoices SET reminder_count = reminder_count + 1, last_reminder_at = ? WHERE id = ?', [now(), $id]);
        History::add('invoice', $id, 'reminder', null, null, null, false, $user);
    }

    public static function markViewed(array $inv): void
    {
        if (!$inv['viewed_at'] && $inv['status'] !== 'draft') {
            DB::exec('UPDATE invoices SET viewed_at = ? WHERE id = ?', [now(), $inv['id']]);
            History::add('invoice', (int) $inv['id'], 'viewed', null, null, null, false, null);
        }
    }

    public static function validDate(?string $d): ?string
    {
        $d = trim((string) $d);
        $dt = DateTime::createFromFormat('Y-m-d', $d);
        return $dt && $dt->format('Y-m-d') === $d ? $d : null;
    }
}
