<?php
declare(strict_types=1);

/** Berichte und CSV-Exporte (nur Administratoren – enthalten Umsatz- und Kundendaten). */
final class ReportController
{
    private function range(): array
    {
        $from = InvoiceService::validDate(input_str('from', 10)) ?? date('Y-01-01');
        $to = InvoiceService::validDate(input_str('to', 10)) ?? date('Y-m-d');
        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }
        return [$from, $to];
    }

    public function index(): void
    {
        Auth::requireAdmin();
        [$from, $to] = $this->range();
        $inv = ReportService::invoices($from, $to);
        $sum = ReportService::summary($inv, $from, $to);
        $recv = ReportService::receivables();
        $offers = ReportService::offerStats($from, $to);
        $ab = ReportService::abrechnung($from, $to);
        $reg = TaxService::liable() ? null : TaxService::registrationStatus();
        view('admin/reports/index', compact('from', 'to', 'sum', 'recv', 'offers', 'ab', 'reg'));
    }

    public function export(string $type): void
    {
        Auth::requireAdmin();
        [$from, $to] = $this->range();
        $stamp = date('Ymd');
        Audit::log('export', "$type $from – $to");
        $n = static fn($v) => number_format((float) $v, 2, '.', '');
        switch ($type) {
            case 'invoices':
                $rows = [];
                foreach (ReportService::invoices($from, $to) as $r) {
                    $rows[] = [$r['number'], $r['kind'] === 'credit' ? 'Gutschrift' : ($r['kind'] === 'advance' ? 'Anzahlung' : 'Rechnung'), $r['issue_date'], $r['due_date'], $r['status'] === 'paid' ? 'bezahlt' : ($r['paid_sum'] > 0 ? 'teilbezahlt' : 'offen'), $r['paid_at'],
                        customer_name($r['customer']), $r['customer']['customer_no'] ?? '', $r['title'], $r['currency'], $n($r['net_eff']), $n($r['tax_eff']), $n($r['total']), $n($r['amount_due']), $n($r['paid_sum'])];
                }
                ReportService::sendCsv("rechnungen-$stamp.csv", ['Nummer', 'Art', 'Rechnungsdatum', 'Fällig', 'Status', 'Bezahlt am', 'Kunde', 'Kunden-Nr.', 'Titel', 'Währung', 'Netto (effektiv)', 'MWST (effektiv)', 'Rechnungstotal', 'Zu zahlen', 'Bezahlt'], $rows);
            case 'vat':
                $sum = ReportService::summary(ReportService::invoices($from, $to), $from, $to);
                $rows = [];
                foreach ($sum['vat'] as $v) {
                    $rows[] = [$v['label'], $n($v['rate']), $n($v['base']), $n($v['tax'])];
                }
                ReportService::sendCsv("mwst-$from-$to.csv", ['Steuerart', 'Satz %', 'Steuerbarer Umsatz', 'Steuer'], $rows);
            case 'abrechnung':
                $ab = ReportService::abrechnung($from, $to);
                $lbl = ['200' => 'Total der vereinbarten bzw. vereinnahmten Entgelte (weltweit)', '205' => 'davon: Entgelte aus Leistungen mit Option (Art. 22)', '220' => 'Von der Steuer befreite Leistungen (Ausfuhr, Art. 23)', '221' => 'Leistungen im Ausland (Ort der Leistung im Ausland)',
                    '225' => 'Übertragung im Meldeverfahren (Art. 38)', '230' => 'Von der Steuer ausgenommene Inlandleistungen (Art. 21) ohne Option', '235' => 'Entgeltsminderungen (Skonti, Rabatte, Gutschriften)', '280' => 'Diverses', '289' => 'Total der Abzüge Ziff. 220–280', '299' => 'Steuerbarer Gesamtumsatz (Ziff. 200 − 289)'];
                $rows = [];
                foreach ($lbl as $k => $t) {
                    $rows[] = [$k, $t, '', $n($ab['z'][$k]), ''];
                }
                foreach ($ab['tax_lines'] as $l) {
                    $rows[] = [$l['ziff'] ?? '', 'Leistungen zum Satz ' . $n($l['rate']) . ' %', $n($l['rate']), $n($l['base']), $n($l['tax'])];
                }
                if ($ab['saldo']) {
                    $rows[] = ['322/323', 'Saldosteuersatz ' . $n($ab['saldo']['rate']) . ' % auf Brutto-Entgelte', $n($ab['saldo']['rate']), $n($ab['saldo']['base']), $n($ab['saldo']['tax'])];
                }
                $rows[] = ['399', 'Total geschuldete Steuer', '', '', $n($ab['tax_total'])];
                ReportService::sendCsv("mwst-abrechnung-$from-$to.csv", ['Ziffer', 'Bezeichnung', 'Satz %', 'Leistung (CHF)', 'Steuer (CHF)'], $rows);
            case 'orders':
                $rows = [];
                foreach (DB::all('SELECT o.*, c.company, c.first_name, c.last_name, c.customer_no FROM orders o JOIN customers c ON c.id = o.customer_id WHERE DATE(o.created_at) BETWEEN ? AND ? ORDER BY o.created_at', [$from, $to]) as $r) {
                    $rows[] = [$r['number'], substr($r['created_at'], 0, 10), customer_name($r), $r['customer_no'], $r['title'], $r['area_type'] === 'digital' ? 'Digital' : 'Physisch',
                        Status::label('order', $r['status'], $r['area_type']), $r['currency'], $n($r['net_total']), $n($r['tax_total']), $n($r['total']), $r['tracking_number']];
                }
                ReportService::sendCsv("auftraege-$stamp.csv", ['Nummer', 'Datum', 'Kunde', 'Kunden-Nr.', 'Titel', 'Bereich', 'Status', 'Währung', 'Netto', 'Steuer', 'Total', 'Sendungsnummer'], $rows);
            case 'offers':
                $rows = [];
                foreach (DB::all('SELECT o.*, c.company, c.first_name, c.last_name FROM offers o JOIN customers c ON c.id = o.customer_id WHERE DATE(o.created_at) BETWEEN ? AND ? ORDER BY o.created_at', [$from, $to]) as $r) {
                    $rows[] = [$r['number'], substr($r['created_at'], 0, 10), customer_name($r), $r['title'], Status::label('offer', $r['status']), $r['valid_until'], $r['currency'], $n($r['net_total']), $n($r['tax_total']), $n($r['total']), $r['responded_at']];
                }
                ReportService::sendCsv("offerten-$stamp.csv", ['Nummer', 'Datum', 'Kunde', 'Titel', 'Status', 'Gültig bis', 'Währung', 'Netto', 'Steuer', 'Total', 'Antwort am'], $rows);
            case 'customers':
                $rows = [];
                foreach (DB::all('SELECT * FROM customers ORDER BY customer_no') as $r) {
                    $rows[] = [$r['customer_no'], $r['company'], $r['salutation'], $r['first_name'], $r['last_name'], $r['email'], $r['phone'], $r['street'], $r['zip'], $r['city'], $r['country'], $r['vat_id']];
                }
                ReportService::sendCsv("kunden-$stamp.csv", ['Kunden-Nr.', 'Firma', 'Anrede', 'Vorname', 'Nachname', 'E-Mail', 'Telefon', 'Strasse', 'PLZ', 'Ort', 'Land', 'UID'], $rows);
        }
        throw new HttpException(404, 'Unbekannter Export.');
    }
}
