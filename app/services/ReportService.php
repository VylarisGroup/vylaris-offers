<?php
/** Auswertungen auf Basis der Rechnungen (Rechnungsdatum, Status offen/bezahlt). */
declare(strict_types=1);

final class ReportService
{
    /** Gültige Rechnungen im Zeitraum inkl. effektiver Steueraufteilung (Schlussrechnung abzüglich Anzahlungen). */
    public static function invoices(string $from, string $to): array
    {
        $rows = DB::all(
            "SELECT i.*, o.product_area_id, p.name AS area_name
             FROM invoices i JOIN orders o ON o.id = i.order_id LEFT JOIN product_areas p ON p.id = o.product_area_id
             WHERE i.status IN ('sent','paid') AND i.issue_date BETWEEN ? AND ? ORDER BY i.issue_date, i.id",
            [$from, $to]
        );
        foreach ($rows as &$r) {
            $r['customer'] = json_decode((string) $r['customer_snapshot'], true) ?: [];
            $groups = self::groupsOf($r);
            $r['groups'] = $groups;
            $r['tax_eff'] = Calc::r2((float) array_sum(array_column($groups, 'tax')));
            $r['net_eff'] = Calc::r2((float) $r['amount_due'] - $r['tax_eff']);
            $r['paid_sum'] = (float) DB::val('SELECT COALESCE(SUM(amount),0) FROM invoice_payments WHERE invoice_id = ?', [$r['id']]);
            $r['adj_sum'] = (float) DB::val('SELECT COALESCE(SUM(amount),0) FROM invoice_adjustments WHERE invoice_id = ?', [$r['id']]);
        }
        unset($r);
        return $rows;
    }

    /** Effektive Steueraufteilung einer Rechnung (Schlussrechnung abzüglich bereits versteuerter Anzahlungen). */
    public static function effectiveGroups(array $r): array
    {
        return self::groupsOf($r);
    }

    private static function groupsOf(array $r): array
    {
        $groups = [];
        foreach (json_decode((string) $r['tax_breakdown'], true) ?: [] as $g) {
            $k = self::key($g);
            $groups[$k] = ['label' => $g['label'], 'rate' => (float) $g['rate'], 'base' => (float) $g['base'], 'tax' => (float) $g['tax']];
        }
        foreach (json_decode((string) $r['deductions'], true) ?: [] as $d) {
            foreach ($d['taxes'] ?? [] as $g) {
                $k = self::key($g);
                $groups[$k] ??= ['label' => $g['label'], 'rate' => (float) $g['rate'], 'base' => 0.0, 'tax' => 0.0];
                $groups[$k]['base'] -= (float) $g['base'];
                $groups[$k]['tax'] -= (float) $g['tax'];
            }
        }
        return array_values($groups);
    }

    /**
     * Buchungs-Positionen der Abrechnungsperiode als Steuergruppen mit Vorzeichen.
     * Vereinbarte Entgelte: Rechnungen/Gutschriften nach Rechnungsdatum, Skonto nach Datum des Nachlasses.
     * Vereinnahmte Entgelte: Zahlungen (und Rückerstattungen) nach Zahlungsdatum, anteilig auf die Steuersätze der Rechnung;
     * Gutschriften/Skonto wirken hier über den niedrigeren Zahlungseingang.
     * @return array<int, array{label:string,rate:float,base:float,tax:float}>
     */
    private static function periodEntries(string $from, string $to, string $method): array
    {
        $entries = [];
        $scale = static function (array $groups, float $share) use (&$entries): void {
            foreach ($groups as $g) {
                $entries[] = ['label' => (string) $g['label'], 'rate' => (float) $g['rate'], 'base' => $g['base'] * $share, 'tax' => $g['tax'] * $share];
            }
        };
        if ($method === 'received') {
            $rows = DB::all(
                "SELECT p.amount AS pay_amount, i.* FROM invoice_payments p JOIN invoices i ON i.id = p.invoice_id
                 WHERE p.paid_on BETWEEN ? AND ? AND i.status IN ('sent','paid') AND i.kind <> 'credit'",
                [$from, $to]
            );
            foreach ($rows as $r) {
                $due = (float) $r['amount_due'];
                if ($due > 0) {
                    $scale(self::groupsOf($r), (float) $r['pay_amount'] / $due);
                }
            }
            return $entries;
        }
        foreach (self::invoices($from, $to) as $r) {
            $scale($r['groups'], 1.0);
        }
        $rows = DB::all(
            "SELECT a.amount AS adj_amount, i.* FROM invoice_adjustments a JOIN invoices i ON i.id = a.invoice_id
             WHERE a.kind = 'discount' AND a.adj_date BETWEEN ? AND ? AND i.status IN ('sent','paid') AND i.kind <> 'credit'",
            [$from, $to]
        );
        foreach ($rows as $r) {
            $due = (float) $r['amount_due'];
            if ($due > 0) {
                $scale(self::groupsOf($r), -(float) $r['adj_amount'] / $due);
            }
        }
        return $entries;
    }

    /**
     * MWST-Abrechnung nach den Ziffern des ESTV-Formulars (effektive Methode bzw. Saldosteuersatz).
     * Hilfsmittel für die Abrechnung, kein Ersatz für die Einreichung bei der ESTV.
     */
    public static function abrechnung(string $from, string $to): array
    {
        $method = TaxService::method();
        $liable = TaxService::liable();
        $z = ['200' => 0.0, '205' => 0.0, '220' => 0.0, '221' => 0.0, '225' => 0.0, '230' => 0.0, '235' => 0.0, '280' => 0.0, '289' => 0.0, '299' => 0.0];
        $lines = [];
        $vatLines = [];
        $passthrough = 0.0;
        $unclassified = 0.0;
        $grossTaxable = 0.0;
        foreach (self::periodEntries($from, $to, $method) as $e) {
            $class = TaxService::classOfGroup($e['rate'], $e['label']);
            $b = $e['base'];
            $vk = number_format($e['rate'], 3, '.', '') . '|' . $e['label'];
            $vatLines[$vk] ??= ['label' => $e['label'] ?: 'Ohne MWST', 'rate' => $e['rate'], 'base' => 0.0, 'tax' => 0.0];
            $vatLines[$vk]['base'] += $b;
            $vatLines[$vk]['tax'] += $e['tax'];
            switch ($class) {
                case 'taxable':
                case 'option':
                    $z['200'] += max(0.0, $b);
                    $z['235'] += max(0.0, -$b);
                    if ($class === 'option') {
                        $z['205'] += $b;
                    }
                    $lk = number_format($e['rate'], 3, '.', '');
                    $lines[$lk] ??= ['rate' => $e['rate'], 'base' => 0.0, 'tax' => 0.0];
                    $lines[$lk]['base'] += $b;
                    $lines[$lk]['tax'] += $e['tax'];
                    $grossTaxable += $b + $e['tax'];
                    break;
                case 'export':
                case 'abroad':
                case 'reporting':
                case 'exempt':
                    $z['200'] += $b;
                    $z[TaxService::CLASSES[$class]['ziff']] += $b;
                    break;
                case 'passthrough':
                    $passthrough += $b;
                    break;
                default:    // „Ohne MWST“ (nicht pflichtig) bzw. nicht zugeordnet
                    $z['200'] += max(0.0, $b);
                    $z['235'] += max(0.0, -$b);
                    $unclassified += $b;
            }
        }
        foreach ($z as $k => $v) {
            $z[$k] = Calc::r2($v);
        }
        $z['289'] = Calc::r2($z['220'] + $z['221'] + $z['225'] + $z['230'] + $z['235'] + $z['280']);
        $z['299'] = Calc::r2($z['200'] - $z['289']);
        ksort($lines);
        $taxLines = [];
        $taxTotal = 0.0;
        foreach (array_reverse($lines, true) as $l) {
            $l['base'] = Calc::r2($l['base']);
            $l['tax'] = Calc::r2($l['tax']);
            $l['ziff'] = TaxService::rateZiff($l['rate']);
            $taxLines[] = $l;
            $taxTotal += $l['tax'];
        }
        usort($vatLines, static fn($a, $b) => $b['rate'] <=> $a['rate']);
        foreach ($vatLines as &$v) {
            $v['base'] = Calc::r2($v['base']);
            $v['tax'] = Calc::r2($v['tax']);
        }
        unset($v);
        $rate = TaxService::saldoRate();
        $saldo = null;
        if ($rate !== null && $liable) {
            $saldo = ['rate' => $rate, 'base' => Calc::r2($grossTaxable), 'tax' => Calc::r2($grossTaxable * $rate / 100)];
            $taxTotal = $saldo['tax'];
        }
        $taxBaseSum = Calc::r2((float) array_sum(array_column($taxLines, 'base')));
        return [
            'method' => $method, 'liable' => $liable, 'z' => $z, 'tax_lines' => $taxLines, 'tax_total' => Calc::r2($taxTotal), 'saldo' => $saldo,
            'vat_lines' => $vatLines, 'passthrough' => Calc::r2($passthrough), 'unclassified' => Calc::r2($unclassified),
            'reconciled' => abs($z['299'] - $taxBaseSum) < 0.06,
        ];
    }

    private static function key(array $g): string
    {
        return number_format((float) $g['rate'], 3, '.', '') . '|' . $g['label'];
    }

    public static function summary(array $inv, ?string $from = null, ?string $to = null): array
    {
        $months = [];
        $areas = [];
        $customers = [];
        $vat = [];
        $tot = ['count' => 0, 'net' => 0.0, 'tax' => 0.0, 'gross' => 0.0, 'paid' => 0.0, 'open' => 0.0];
        foreach ($inv as $r) {
            $m = substr($r['issue_date'], 0, 7);
            $months[$m] ??= ['count' => 0, 'net' => 0.0, 'tax' => 0.0, 'gross' => 0.0];
            $months[$m]['count']++;
            $months[$m]['net'] += $r['net_eff'];
            $months[$m]['tax'] += $r['tax_eff'];
            $months[$m]['gross'] += (float) $r['amount_due'];

            $a = $r['area_name'] ?: 'Ohne Bereich';
            $areas[$a] ??= ['count' => 0, 'net' => 0.0, 'gross' => 0.0];
            $areas[$a]['count']++;
            $areas[$a]['net'] += $r['net_eff'];
            $areas[$a]['gross'] += (float) $r['amount_due'];

            $cid = (int) $r['customer_id'];
            $customers[$cid] ??= ['name' => customer_name($r['customer']), 'count' => 0, 'gross' => 0.0];
            $customers[$cid]['count']++;
            $customers[$cid]['gross'] += (float) $r['amount_due'];

            foreach ($r['groups'] as $g) {
                $k = number_format($g['rate'], 3, '.', '') . '|' . $g['label'];
                $vat[$k] ??= ['label' => $g['label'] ?: 'Ohne MWST', 'rate' => $g['rate'], 'base' => 0.0, 'tax' => 0.0];
                $vat[$k]['base'] += $g['base'];
                $vat[$k]['tax'] += $g['tax'];
            }
            $tot['count']++;
            $tot['net'] += $r['net_eff'];
            $tot['tax'] += $r['tax_eff'];
            $tot['gross'] += (float) $r['amount_due'];
            $tot['paid'] += (float) $r['paid_sum'];
            $tot['open'] += $r['kind'] === 'credit' ? 0.0 : max(0.0, (float) $r['amount_due'] - (float) $r['paid_sum'] - (float) $r['adj_sum']);
        }
        ksort($months);
        uasort($customers, static fn($a, $b) => $b['gross'] <=> $a['gross']);
        uasort($areas, static fn($a, $b) => $b['gross'] <=> $a['gross']);
        usort($vat, static fn($a, $b) => $b['rate'] <=> $a['rate']);
        $method = TaxService::method();
        $saldo = null;
        if ($from !== null && $to !== null) {
            $ab = self::abrechnung($from, $to);
            $vat = $ab['vat_lines'];
            $saldo = $ab['saldo'];
        }
        return ['months' => $months, 'areas' => $areas, 'customers' => array_slice($customers, 0, 10, true), 'vat' => $vat, 'total' => $tot, 'vat_method' => $method, 'saldo' => $saldo];
    }

    /** Offene Posten (Stand heute), unabhängig vom Zeitraum. */
    public static function receivables(): array
    {
        $rows = DB::all("SELECT i.*, c.company, c.first_name, c.last_name, " . InvoiceService::OPEN_SQL . " AS open_amount FROM invoices i JOIN customers c ON c.id = i.customer_id WHERE i.status = 'sent' AND i.kind <> 'credit' ORDER BY i.due_date");
        $buckets = ['current' => ['Noch nicht fällig', 0.0, 0], 'd30' => ['1–30 Tage überfällig', 0.0, 0], 'd60' => ['31–60 Tage überfällig', 0.0, 0], 'd60p' => ['über 60 Tage überfällig', 0.0, 0]];
        $today = strtotime(date('Y-m-d'));
        foreach ($rows as &$r) {
            $days = (int) floor(($today - strtotime($r['due_date'])) / 86400);
            $r['days_overdue'] = max(0, $days);
            $k = $days <= 0 ? 'current' : ($days <= 30 ? 'd30' : ($days <= 60 ? 'd60' : 'd60p'));
            $buckets[$k][1] += (float) $r['open_amount'];
            $buckets[$k][2]++;
        }
        unset($r);
        return ['rows' => $rows, 'buckets' => $buckets];
    }

    public static function offerStats(string $from, string $to): array
    {
        $st = ['open' => [0, 0.0], 'accepted' => [0, 0.0], 'declined' => [0, 0.0], 'expired' => [0, 0.0], 'superseded' => [0, 0.0]];
        foreach (DB::all('SELECT status, COUNT(*) n, COALESCE(SUM(total),0) s FROM offers WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY status', [$from, $to]) as $r) {
            $st[$r['status']] = [(int) $r['n'], (float) $r['s']];
        }
        $decided = $st['accepted'][0] + $st['declined'][0] + $st['expired'][0];
        return ['by_status' => $st, 'rate' => $decided ? round($st['accepted'][0] / $decided * 100) : null];
    }

    /* ---------------------------------------------------------------- CSV */

    /**
     * Buchungsjournal (generisch): Rechnungsbuchungen (Debitoren an Ertrag, je Steuersatz, Betrag brutto mit Steuerangabe)
     * und Zahlungseingänge (Bank an Debitoren). Konten gemäss Einstellung.
     */
    public static function journal(string $from, string $to): array
    {
        $n = static fn ($v) => number_format((float) $v, 2, '.', '');
        $deb = Settings::get('acc_debtors') ?: '1100';
        $rev = Settings::get('acc_revenue') ?: '3200';
        $bank = Settings::get('acc_bank') ?: '1020';
        $online = Settings::get('acc_online') ?: '1021';
        $rows = [];
        foreach (self::invoices($from, $to) as $r) {
            $no = $r['customer']['customer_no'] ?? '';
            foreach ($r['groups'] as $g) {
                $gross = $g['base'] + $g['tax'];
                if (abs($gross) < 0.005) {
                    continue;
                }
                $credit = $gross < 0;   // Gutschrift: Konten vertauscht, Beträge positiv
                $rows[] = [$r['issue_date'], $r['number'], ($r['kind'] === 'credit' ? 'Gutschrift ' : ($r['kind'] === 'advance' ? 'Anzahlung ' : 'Rechnung ')) . $r['number'] . ' ' . $r['title'], $credit ? $rev : $deb, $credit ? $deb : $rev, $n(abs($gross)), $r['currency'], $n($g['rate']), $n(abs($g['tax'])), $no];
            }
        }
        $cash = Settings::get('acc_cash') ?: '1000';
        $pays = DB::all("SELECT p.*, i.number, i.currency, i.customer_snapshot FROM invoice_payments p JOIN invoices i ON i.id = p.invoice_id WHERE p.paid_on BETWEEN ? AND ? AND i.status IN ('sent','paid') ORDER BY p.paid_on, p.id", [$from, $to]);
        foreach ($pays as $p) {
            $c = json_decode((string) $p['customer_snapshot'], true) ?: [];
            $acc = ['online' => $online, 'cash' => $cash][$p['method']] ?? $bank;
            $refund = (float) $p['amount'] < 0;
            $rows[] = [$p['paid_on'], $p['number'], ($refund ? 'Rückerstattung ' : 'Zahlung ') . $p['number'] . ($p['method'] === 'online' ? ' (online)' : ($p['method'] === 'cash' ? ' (bar)' : '')), $refund ? $deb : $acc, $refund ? $acc : $deb, $n(abs((float) $p['amount'])), $p['currency'], '', '', $c['customer_no'] ?? ''];
        }
        $disc = Settings::get('acc_discount') ?: '3800';
        $adj = DB::all("SELECT a.*, i.number, i.currency, i.customer_snapshot, i.tax_breakdown, i.deductions, i.amount_due FROM invoice_adjustments a JOIN invoices i ON i.id = a.invoice_id WHERE a.kind = 'discount' AND a.adj_date BETWEEN ? AND ? AND i.status IN ('sent','paid') ORDER BY a.adj_date, a.id", [$from, $to]);
        foreach ($adj as $a) {
            $c = json_decode((string) $a['customer_snapshot'], true) ?: [];
            $rows[] = [$a['adj_date'], $a['number'], 'Skonto/Nachlass ' . $a['number'], $disc, $deb, $n($a['amount']), $a['currency'], '', '', $c['customer_no'] ?? ''];
        }
        usort($rows, static fn ($a, $b) => strcmp($a[0] . $a[1], $b[0] . $b[1]));
        return $rows;
    }

    public static function csvCell(mixed $v): string
    {
        if ($v === null) {
            return '';
        }
        $s = (string) $v;
        if ($s !== '' && !is_numeric($s) && preg_match('/^[=+\-@\t\r]/', $s)) {
            $s = "'" . $s; // Schutz vor Formel-Injektion in Excel/Calc
        }
        return '"' . str_replace('"', '""', $s) . '"';
    }

    public static function sendCsv(string $filename, array $header, iterable $rows): never
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF" . 'sep=;' . "\r\n");
        fwrite($out, implode(';', array_map([self::class, 'csvCell'], $header)) . "\r\n");
        foreach ($rows as $row) {
            fwrite($out, implode(';', array_map([self::class, 'csvCell'], $row)) . "\r\n");
        }
        fclose($out);
        exit;
    }
}
