<?php
/**
 * PDF-Erzeugung (FPDF) für Offerten und Rechnungen inkl. Schweizer QR-Einzahlungsschein.
 */
declare(strict_types=1);

require_once APP_DIR . '/vendor/fpdf/fpdf.php';

final class PdfDoc extends FPDF
{
    public string $footerLeft = '';
    public bool $hideFooter = false;
    /** Dokumentvorlage: Schriftfamilie (null = Helvetica wie im Standard), Schriftgrösse-Faktor, Seitenzahlen. */
    public ?string $tplFont = null;
    public float $tplScale = 1.0;
    public bool $pageNumbers = true;

    /** Wendet die Schrift der Dokumentvorlage an (Helvetica wird ersetzt, Courier bleibt). */
    public function SetFont($family, $style = '', $size = 0): void
    {
        if ($this->tplFont !== null && strtolower((string) $family) === 'helvetica') {
            $family = $this->tplFont;
        }
        if ($this->tplScale !== 1.0 && $size > 0) {
            $size = round($size * $this->tplScale, 2);
        }
        parent::SetFont($family, $style, $size);
    }

    public function Footer(): void
    {
        if ($this->hideFooter) {
            return;
        }
        $this->SetY(-14);
        $this->SetFont('Helvetica', '', 7.5);
        $this->SetTextColor(130, 140, 155);
        $this->Cell(0, 4, PdfService::t($this->footerLeft), 0, 0, 'L');
        if ($this->pageNumbers) {
            $this->SetX(-40);
            $this->Cell(20, 4, PdfService::t('Seite ' . $this->PageNo() . '/{nb}'), 0, 0, 'R');
        }
    }

    public function SetDash($black = null, $white = null): void
    {
        $this->_out($black !== null ? sprintf('[%.3F %.3F] 0 d', $black * $this->k, $white * $this->k) : '[] 0 d');
    }

    /** Anzahl Zeilen, die $text bei Breite $w braucht (aktuelle Schrift). */
    public function lines(string $text, float $w): int
    {
        $n = 0;
        foreach (explode("\n", $text) as $para) {
            $line = '';
            $count = 1;
            foreach (preg_split('/(\s+)/', $para, -1, PREG_SPLIT_DELIM_CAPTURE) as $word) {
                if ($this->GetStringWidth($line . $word) > $w && $line !== '') {
                    $count++;
                    $line = ltrim($word);
                } else {
                    $line .= $word;
                }
            }
            $n += $count;
        }
        return max(1, $n);
    }
}

final class PdfService
{
    private static float $margin = 20.0;
    /** Vorschau: überschreibt die Vorlagenkonfiguration (nicht gespeichert). */
    public static ?array $forceConfig = null;

    /** UTF-8 → Windows-1252 für die Standardschriften. */
    public static function t(?string $s): string
    {
        $s = str_replace(["\u{2212}", "\u{2013}", "\u{2011}"], ['-', "\u{2013}", '-'], (string) $s);
        return (string) mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
    }

    /** Eigene UID/MWST-Nummer für Kopf und Fusszeile. */
    public static function uidLine(): string
    {
        $u = TaxService::companyUid();
        return $u === '' ? '' : (TaxService::liable() ? 'MWST-Nr. ' : 'UID ') . $u;
    }

    public static function offer(array $o): string
    {
        $c = $o['customer'];
        $doc = [
            'label'  => 'Offerte', 'number' => $o['number'], 'title' => $o['title'], 'currency' => $o['currency'],
            'dates'  => ['Datum' => fmt_date($o['created_at']), 'Gültig bis' => $o['valid_until'] ? fmt_date($o['valid_until']) : 'unbefristet']
                + (($contact = Responsibility::contactName('offer', $o)) !== '' ? ['Ansprechperson' => $contact] : []),
            'to_label' => 'Offerte für', 'customer' => $c,
            'intro'  => $o['intro'], 'footer' => $o['footer'], 'items' => $o['items'], 'src' => $o,
            'file'   => 'Offerte-' . $o['number'] . '.pdf', 'invoice' => false,
        ];
        if ($o['status'] === 'accepted' && $o['response_name']) {
            $doc['accepted'] = 'Digital angenommen von ' . $o['response_name'] . ' am ' . fmt_datetime($o['responded_at']);
            if (!empty($o['signer_verified_at'])) {
                $doc['accepted'] .= ' – E-Mail-Adresse ' . $o['signer_verified_email'] . ' bestätigt am ' . fmt_datetime($o['signer_verified_at']);
            }
            if (!empty($o['signature_file'])) {
                $f = APP_ROOT . '/storage/uploads/signatures/' . basename((string) $o['signature_file']);
                $doc['signature'] = is_file($f) ? $f : null;
            }
        }
        return self::build($doc);
    }

    public static function invoice(array $i): string
    {
        $credit = $i['kind'] === 'credit';
        $label = $credit ? 'Gutschrift' : ($i['kind'] === 'advance' ? 'Anzahlungsrechnung' : 'Rechnung');
        $doc = [
            'label'  => $label, 'number' => $i['number'], 'title' => $i['title'], 'currency' => $i['currency'],
            'dates'  => ['Datum' => fmt_date($i['issue_date'])]
                + ($credit ? ['Zu Rechnung' => (string) ($i['credit_of_number'] ?? '')] : ['Fällig am' => fmt_date($i['due_date'])])
                + (!empty($i['service_period']) ? ['Leistungszeitraum' => $i['service_period']] : []),
            'to_label' => $credit ? 'Gutschrift an' : 'Rechnung an', 'customer' => $i['customer'],
            'intro'  => $i['intro'], 'footer' => $i['footer'], 'items' => $i['items'], 'src' => $i,
            'file'   => ($credit ? 'Gutschrift-' : 'Rechnung-') . $i['number'] . '.pdf', 'invoice' => true,
        ];
        return self::build($doc);
    }

    /** Zahlungsbestätigung (Quittung) für eine bezahlte Rechnung. */
    public static function receipt(array $i, ?int $ledgerId = null): string
    {
        self::$margin = 20.0;
        $row = null;
        $cum = 0.0;
        $cumAt = 0.0;
        foreach ($i['payments'] ?? [] as $lp) {
            $cum += (float) $lp['amount'];
            if ($ledgerId ? (int) $lp['id'] === $ledgerId : true) {
                $row = $lp;
                $cumAt = $cum;
            }
        }
        $pay = $row ? PaymentService::providerPayment($row['provider_payment_id'] ? (int) $row['provider_payment_id'] : null) : PaymentService::paidPayment((int) $i['id']);
        $rowAmount = $row ? (float) $row['amount'] : (float) $i['amount_due'];
        $remainingAfter = max(0.0, Calc::r2((float) $i['amount_due'] - ($row ? $cumAt : (float) $i['amount_due'])));
        $W = 210 - 2 * self::$margin;
        $brand = self::rgb((string) Settings::get('brand_color', '#4f46e5'));
        $pdf = new PdfDoc('P', 'mm', 'A4');
        $pdf->SetMargins(self::$margin, 15, self::$margin);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AliasNbPages();
        $pdf->SetTitle(self::t('Zahlungsbestätigung ' . $i['number']), false);
        $pdf->SetAuthor(self::t((string) Settings::get('company_name')), false);
        $pdf->SetCreator(self::t(Brand::name()), false);
        $pdf->footerLeft = (string) Settings::get('company_name') . ' · Zahlungsbestätigung ' . $i['number'];
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', 'B', 13);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->SetXY(self::$margin, 15);
        $pdf->Cell(95, 6, self::t((string) Settings::get('company_name')), 0, 2);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(90, 100, 115);
        foreach (array_filter([Settings::get('company_street'), trim(Settings::get('company_zip') . ' ' . Settings::get('company_city')), Settings::companyEmail(), Settings::get('company_phone'), self::uidLine()]) as $l) {
            $pdf->Cell(95, 4.4, self::t((string) $l), 0, 2);
        }
        $pdf->SetXY(110, 15);
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->SetTextColor(...$brand);
        $pdf->Cell(80, 4, self::t('ZAHLUNGSBESTÄTIGUNG'), 0, 2, 'R');
        $pdf->SetFont('Helvetica', 'B', 17);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(80, 8, self::t($i['number']), 0, 2, 'R');
        $pdf->SetDrawColor(...$brand);
        $pdf->SetLineWidth(0.6);
        $pdf->Line(self::$margin, 45, 210 - self::$margin, 45);

        $pdf->SetXY(self::$margin, 52);
        $pdf->SetFont('Helvetica', 'B', 7.5);
        $pdf->SetTextColor(130, 140, 155);
        $pdf->Cell(80, 4, self::t('ZAHLUNG VON'), 0, 2);
        $pdf->SetFont('Helvetica', '', 10.5);
        $pdf->SetTextColor(15, 23, 42);
        foreach (address_lines($i['customer']) as $l) {
            $pdf->Cell(80, 5, self::t($l), 0, 2);
        }
        $pdf->SetY(max($pdf->GetY(), 82) + 4);

        // Betragsfeld
        $y = $pdf->GetY();
        $pdf->SetFillColor(...self::tint($brand));
        $pdf->Rect(self::$margin, $y, $W, 26, 'F');
        $pdf->SetXY(self::$margin + 6, $y + 4);
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->SetTextColor(...$brand);
        $pdf->Cell(80, 4, self::t('BEZAHLTER BETRAG'), 0, 2);
        $pdf->SetFont('Helvetica', 'B', 20);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(80, 10, self::t(money($rowAmount, $i['currency'])), 0, 2);
        $pdf->SetXY(110, $y + 6);
        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->SetTextColor(5, 150, 105);
        $pdf->Cell(74, 6, self::t($remainingAfter > 0.004 ? 'TEILZAHLUNG' : 'BEZAHLT'), 0, 2, 'R');
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(90, 100, 115);
        $pdf->Cell(74, 5, self::t('am ' . fmt_date($row ? $row['paid_on'] : ($pay ? substr((string) $pay['paid_at'], 0, 10) : $i['paid_at']))), 0, 2, 'R');
        $pdf->SetY($y + 34);

        $rows = [
            'Rechnung' => $i['number'] . ($i['title'] ? ' – ' . $i['title'] : ''),
            'Rechnungsdatum' => fmt_date($i['issue_date']),
            'Rechnungsbetrag' => money($i['amount_due'], $i['currency']),
        ];
        if ($row) {
            $rows['Bisher bezahlt'] = money($cumAt, $i['currency']) . ' (inkl. dieser Zahlung)';
            $rows['Noch offen'] = money($remainingAfter, $i['currency']);
        }
        if ($pay) {
            $rows['Zahlungsart'] = PaymentService::PROVIDERS[$pay['provider']] . ($pay['method'] ? ' (' . $pay['method'] . ')' : '') . ' – online';
            $rows['Zahlungsreferenz'] = (string) $pay['provider_ref'];
        } else {
            $rows['Zahlungsart'] = ['bank' => 'Überweisung', 'cash' => 'Barzahlung', 'online' => 'Online', 'other' => 'Sonstige'][$row['method'] ?? 'bank'] ?? 'Überweisung';
            $note = $row['note'] ?? ($i['payment_note'] ?? '');
            if (!empty($note)) {
                $rows['Notiz'] = (string) $note;
            }
        }
        $pdf->SetFont('Helvetica', '', 10);
        foreach ($rows as $k => $v) {
            $pdf->SetX(self::$margin);
            $pdf->SetTextColor(110, 120, 135);
            $pdf->Cell(48, 6.5, self::t($k), 0, 0);
            $pdf->SetTextColor(15, 23, 42);
            $pdf->MultiCell($W - 48, 6.5, self::t($v));
        }
        $pdf->Ln(6);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(90, 100, 115);
        $pdf->MultiCell($W, 4.8, self::t('Diese Zahlungsbestätigung belegt den Zahlungseingang zur oben genannten Rechnung. Sie ersetzt die Rechnung nicht; die Angaben zur Mehrwertsteuer entnehmen Sie bitte der Rechnung.'));
        return $pdf->Output('S');
    }

    private static function build(array $d): string
    {
        $o = $d['src'];
        $cur = $d['currency'];
        $tpl = self::$forceConfig !== null
            ? DocTemplate::sanitize(self::$forceConfig)
            : DocTemplate::resolve($d['invoice'] ? 'invoice' : 'offer', isset($o['template_id']) ? (int) $o['template_id'] : null);
        self::$margin = (float) $tpl['margin'];
        $pdf = new PdfDoc('P', 'mm', 'A4');
        $pdf->tplFont = $tpl['font'] === 'helvetica' ? null : $tpl['font'];
        $pdf->tplScale = $tpl['font_scale'] / 100;
        $pdf->pageNumbers = (bool) $tpl['page_numbers'];
        $pdf->SetMargins(self::$margin, 15, self::$margin);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AliasNbPages();
        $pdf->SetTitle(self::t($d['label'] . ' ' . $d['number']), false);
        $pdf->SetAuthor(self::t((string) Settings::get('company_name')), false);
        $pdf->SetCreator(self::t(Brand::name()), false);
        $pdf->footerLeft = DocTemplate::footerLine($tpl, (string) $d['number']);
        $pdf->AddPage();
        $m = self::$margin;
        $W = 210 - 2 * $m;
        $brand = self::rgb($tpl['accent'] !== '' ? $tpl['accent'] : (string) Settings::get('brand_color', '#4f46e5'));

        // Kopf: Firma (und Logo) links, Dokumentdaten rechts – Logo wahlweise links oder rechts
        $logoFile = $tpl['logo'] ? self::logoFile() : null;
        $logoH = (float) $tpl['logo_height'];
        $leftY = 15.0;
        $rightY = 15.0;
        $logoW = 0.0;
        if ($logoFile !== null) {
            $info = @getimagesize($logoFile);
            if ($info && $info[1] > 0) {
                $logoW = min($logoH * $info[0] / $info[1], 70.0);
                $lh = $logoW * $info[1] / $info[0];
                try {
                    if ($tpl['logo_pos'] === 'right') {
                        $pdf->Image($logoFile, 210 - $m - $logoW, 15, $logoW, $lh);
                        $rightY = 15 + $lh + 2.5;
                    } else {
                        $pdf->Image($logoFile, $m, 15, $logoW, $lh);
                        $leftY = 15 + $lh + 2.5;
                    }
                } catch (Throwable) {
                    $logoW = 0.0;
                }
            }
        }
        $pdf->SetXY($m, $leftY);
        if ($tpl['company_block'] !== 'none') {
            $pdf->SetFont('Helvetica', 'B', 13);
            $pdf->SetTextColor(15, 23, 42);
            $pdf->Cell(95, 6, self::t((string) Settings::get('company_name')), 0, 2);
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->SetTextColor(90, 100, 115);
            if ($tpl['company_block'] === 'full') {
                foreach (array_filter([Settings::get('company_street'), trim(Settings::get('company_zip') . ' ' . Settings::get('company_city')), Settings::companyEmail(), Settings::get('company_phone'), self::uidLine()]) as $l) {
                    $pdf->Cell(95, 4.4, self::t((string) $l), 0, 2);
                }
            }
        }
        if ($tpl['header_note'] !== '') {
            $pdf->SetFont('Helvetica', '', 8.5);
            $pdf->SetTextColor(110, 120, 135);
            $pdf->SetX($m);
            $pdf->Cell(95, 4.4, self::t($tpl['header_note']), 0, 2);
        }
        $leftEnd = $pdf->GetY();
        $rx = 210 - $m - 80;
        $pdf->SetXY($rx, $rightY);
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->SetTextColor(...$brand);
        $pdf->Cell(80, 4, self::t(mb_strtoupper($d['label'])), 0, 2, 'R');
        $pdf->SetFont('Helvetica', 'B', 17);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(80, 8, self::t($d['number']), 0, 2, 'R');
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(90, 100, 115);
        foreach ($d['dates'] as $k => $v) {
            $pdf->Cell(80, 4.6, self::t("$k: $v"), 0, 2, 'R');
        }
        if (!empty($d['customer']['customer_no'])) {
            $pdf->Cell(80, 4.6, self::t('Kunden-Nr.: ' . $d['customer']['customer_no']), 0, 2, 'R');
        }
        $lineY = max(45.0, $leftEnd + 2, $pdf->GetY() + 2);
        $pdf->SetDrawColor(...$brand);
        $pdf->SetLineWidth(0.6);
        $pdf->Line($m, $lineY, 210 - $m, $lineY);

        // Adresse
        $pdf->SetXY($m, $lineY + 7);
        $pdf->SetFont('Helvetica', 'B', 7.5);
        $pdf->SetTextColor(130, 140, 155);
        $pdf->Cell(80, 4, self::t(mb_strtoupper($d['to_label'])), 0, 2);
        $pdf->SetFont('Helvetica', '', 10.5);
        $pdf->SetTextColor(15, 23, 42);
        foreach (address_lines($d['customer']) as $l) {
            $pdf->Cell(80, 5, self::t($l), 0, 2);
        }
        if (!empty($d['customer']['vat_id'])) {
            $pdf->SetFont('Helvetica', '', 8.5);
            $pdf->SetTextColor(110, 120, 135);
            $pdf->Cell(80, 4.6, self::t('UID: ' . $d['customer']['vat_id']), 0, 2);
        }
        $pdf->SetY(max($pdf->GetY(), 82) + 2);

        // Titel & Einleitung
        $pdf->SetFont('Helvetica', 'B', 13);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->MultiCell($W, 6.5, self::t($d['title']));
        if (!empty($o['delivery_info']) && $d['invoice']) {
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->SetTextColor(90, 100, 115);
            $pdf->MultiCell($W, 4.6, self::t($o['delivery_info']));
        }
        if ($d['intro']) {
            $pdf->Ln(1.5);
            $pdf->SetFont('Helvetica', '', 9.5);
            $pdf->SetTextColor(70, 80, 95);
            $pdf->MultiCell($W, 4.8, self::t($d['intro']));
        }
        $pdf->Ln(4);

        // Positionen
        $cols = [10, $W - 90, 22, 28, 30];
        $style = $tpl['table_style'];
        $head = static function () use ($pdf, $cols, $cur, $style, $brand, $m, $W): void {
            $pdf->SetFont('Helvetica', 'B', 8);
            $fill = $style === 'filled';
            $pdf->SetTextColor(...($style === 'filled' ? [100, 110, 125] : [60, 70, 85]));
            $pdf->SetFillColor(241, 245, 249);
            $h = $style === 'minimal' ? 6 : 7;
            $pdf->Cell($cols[0], $h, 'Pos.', 0, 0, 'L', $fill);
            $pdf->Cell($cols[1], $h, 'Bezeichnung', 0, 0, 'L', $fill);
            $pdf->Cell($cols[2], $h, 'Menge', 0, 0, 'R', $fill);
            $pdf->Cell($cols[3], $h, 'Preis', 0, 0, 'R', $fill);
            $pdf->Cell($cols[4], $h, PdfService::t('Total ' . $cur), 0, 1, 'R', $fill);
            if ($style === 'lines') {
                $pdf->SetDrawColor(...$brand);
                $pdf->SetLineWidth(0.4);
                $pdf->Line($m, $pdf->GetY(), $m + $W, $pdf->GetY());
            }
        };
        $head();
        $multiTax = count(array_unique(array_map(static fn($i) => $i['tax_rate'] . '|' . $i['tax_label'], $d['items']))) > 1 && TaxService::liable();
        foreach ($d['items'] as $it) {
            $sub = [];
            if ($it['description']) {
                $sub[] = trim((string) $it['description']);
            }
            if ((float) $it['line_discount'] > 0) {
                $sub[] = 'Rabatt −' . money($it['line_discount'], $cur, false) . ($it['discount_type'] === 'percent' ? ' (' . rate($it['discount_value']) . ')' : '');
            }
            if ($multiTax) {
                $sub[] = $it['tax_label'] ?: ('Steuer ' . rate($it['tax_rate']));
            }
            $pdf->SetFont('Helvetica', 'B', 9.5);
            $hTitle = $pdf->lines(self::t($it['title']), $cols[1] - 2) * 4.8;
            $pdf->SetFont('Helvetica', '', 8.5);
            $hSub = $sub ? $pdf->lines(self::t(implode("\n", $sub)), $cols[1] - 2) * 4.1 : 0;
            $h = $hTitle + $hSub + 3;
            if ($pdf->GetY() + $h > 277 - 6) {
                $pdf->AddPage();
                $head();
            }
            $y = $pdf->GetY() + 1.5;
            $pdf->SetTextColor(150, 160, 175);
            $pdf->SetFont('Helvetica', '', 8.5);
            $pdf->SetXY(self::$margin, $y);
            $pdf->Cell($cols[0], 4.8, (string) $it['position'], 0, 0);
            $pdf->SetXY(self::$margin + $cols[0], $y);
            $pdf->SetFont('Helvetica', 'B', 9.5);
            $pdf->SetTextColor(15, 23, 42);
            $pdf->MultiCell($cols[1] - 2, 4.8, self::t($it['title']));
            if ($sub) {
                $pdf->SetX(self::$margin + $cols[0]);
                $pdf->SetFont('Helvetica', '', 8.5);
                $pdf->SetTextColor(100, 110, 125);
                $pdf->MultiCell($cols[1] - 2, 4.1, self::t(implode("\n", $sub)));
            }
            $x = self::$margin + $cols[0] + $cols[1];
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->SetTextColor(40, 50, 65);
            $pdf->SetXY($x, $y);
            $pdf->Cell($cols[2], 4.8, self::t(qty($it['quantity']) . ' ' . $it['unit']), 0, 0, 'R');
            $pdf->Cell($cols[3], 4.8, self::t(money($it['unit_price'], $cur, false)), 0, 0, 'R');
            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->Cell($cols[4], 4.8, self::t(money($it['line_total'], $cur, false)), 0, 0, 'R');
            $pdf->SetY($y + $h - 1.5);
            $pdf->SetDrawColor(226, 232, 240);
            $pdf->SetLineWidth(0.2);
            $pdf->Line(self::$margin, $pdf->GetY(), 210 - self::$margin, $pdf->GetY());
        }

        // Summen
        $rows = [['Zwischensumme', money($o['subtotal'], $cur), 0]];
        if ((float) $o['discount_amount'] > 0) {
            $rows[] = ['Rabatt' . ($o['discount_type'] === 'percent' ? ' ' . rate($o['discount_value']) : ''), '−' . money($o['discount_amount'], $cur), 0];
        }
        if ($o['area_type'] === 'physical') {
            $rows[] = ['Versand' . (!empty($o['shipping_method']) ? ' (' . $o['shipping_method'] . ')' : ''), (float) $o['shipping_cost'] > 0 ? money($o['shipping_cost'], $cur) : 'kostenlos', 0];
        }
        $liable = TaxService::liable();
        if ($liable) {
            $rows[] = ['Total exkl. MWST', money($o['net_total'], $cur), 1];
        }
        if ($liable) {
            $taxes = $o['taxes'] ?? (json_decode((string) $o['tax_breakdown'], true) ?: []);
            foreach ($taxes as $t) {
                $rows[] = [($t['label'] ?: ('Steuer ' . rate($t['rate']))) . ' auf ' . money($t['base'], $cur), money($t['tax'], $cur), 0];
            }
        }
        if ((float) $o['rounding'] != 0.0) {
            $rows[] = ['Rundungsdifferenz', money($o['rounding'], $cur), 0];
        }
        $need = count($rows) * 5.6 + 24;
        if ($pdf->GetY() + $need > 277) {
            $pdf->AddPage();
        }
        $pdf->Ln(3);
        $lx = 210 - self::$margin - 95;
        foreach ($rows as [$l, $v, $strong]) {
            $pdf->SetX($lx);
            $pdf->SetFont('Helvetica', $strong ? 'B' : '', 9);
            $pdf->SetTextColor($strong ? 15 : 90, $strong ? 23 : 100, $strong ? 42 : 115);
            $pdf->Cell(65, 5.6, self::t($l), 0, 0);
            $pdf->Cell(30, 5.6, self::t($v), 0, 1, 'R');
        }
        $pdf->Ln(1.5);
        $pdf->SetX($lx);
        $solid = $tpl['total_style'] === 'solid';
        $pdf->SetFillColor(...($solid ? $brand : self::tint($brand)));
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->SetTextColor(...($solid ? [255, 255, 255] : [15, 23, 42]));
        $pdf->Cell(50, 9, self::t(' Gesamtbetrag'), 0, 0, 'L', true);
        $pdf->Cell(45, 9, self::t(money($o['total'], $cur) . ' '), 0, 1, 'R', true);

        if ($d['invoice'] && !empty($o['deduction_list'])) {
            $pdf->Ln(1);
            foreach ($o['deduction_list'] as $ded) {
                $pdf->SetX($lx);
                $pdf->SetFont('Helvetica', '', 9);
                $pdf->SetTextColor(90, 100, 115);
                $pdf->Cell(65, 5.6, self::t('abzgl. Anzahlung ' . $ded['number'] . ' (' . fmt_date($ded['date']) . ')'), 0, 0);
                $pdf->Cell(30, 5.6, self::t('−' . money($ded['total'], $cur)), 0, 1, 'R');
            }
            $pdf->SetX($lx);
            $pdf->SetFont('Helvetica', 'B', 11);
            $pdf->SetTextColor(...($solid ? [255, 255, 255] : [15, 23, 42]));
            $pdf->SetFillColor(...($solid ? $brand : self::tint($brand)));
            $pdf->Cell(50, 9, self::t(' Zu zahlen'), 0, 0, 'L', true);
            $pdf->Cell(45, 9, self::t(money($o['amount_due'], $cur) . ' '), 0, 1, 'R', true);
        }
        $pdf->Ln(5);

        // Hinweise
        if (!$liable) {
            $pdf->SetFont('Helvetica', '', 8.5);
            $pdf->SetTextColor(100, 110, 125);
            $pdf->MultiCell($W, 4.6, self::t('Nicht MWST-pflichtig – es wird keine Mehrwertsteuer ausgewiesen.'));
            $pdf->Ln(2);
        }
        $notes = TaxService::notes($o['taxes'] ?? (json_decode((string) $o['tax_breakdown'], true) ?: []));
        if ($notes) {
            $pdf->SetFont('Helvetica', '', 8.5);
            $pdf->SetTextColor(100, 110, 125);
            foreach ($notes as $n) {
                $pdf->MultiCell($W, 4.6, self::t($n));
            }
            $pdf->Ln(2);
        }
        $isCr = ($o['kind'] ?? '') === 'credit';
        if ($d['invoice'] && !$isCr && (!empty($o['payments']) || !empty($o['adjustments'])) && $o['status'] !== 'cancelled') {
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->SetTextColor(90, 100, 115);
            foreach ($o['payments'] as $lp) {
                $ref = (float) $lp['amount'] < 0;
                $pdf->Cell($W - 35, 5, self::t(($ref ? 'Rückerstattung vom ' : 'Zahlung vom ') . fmt_date($lp['paid_on'])), 0, 0);
                $pdf->Cell(35, 5, self::t(($ref ? '+' : '−') . money(abs((float) $lp['amount']), $cur)), 0, 1, 'R');
            }
            foreach ($o['adjustments'] ?? [] as $ad) {
                $pdf->Cell($W - 35, 5, self::t(($ad['kind'] === 'credit' ? 'Gutschrift ' . $ad['credit_number'] : 'Skonto/Preisnachlass') . ' vom ' . fmt_date($ad['adj_date'])), 0, 0);
                $pdf->Cell(35, 5, self::t('−' . money($ad['amount'], $cur)), 0, 1, 'R');
            }
            if ($o['status'] !== 'paid') {
                $pdf->SetFont('Helvetica', 'B', 10);
                $pdf->SetTextColor(15, 23, 42);
                $pdf->Cell($W - 35, 6, self::t('Noch offen'), 0, 0);
                $pdf->Cell(35, 6, self::t(money($o['open_amount'], $cur)), 0, 1, 'R');
            }
            $pdf->Ln(2);
        }
        if ($d['invoice']) {
            $pdf->SetFont('Helvetica', 'B', 9.5);
            $pdf->SetTextColor(15, 23, 42);
            if ($isCr) {
                $pdf->Cell($W, 5.5, self::t('Gutschrift zu Rechnung ' . ($o['credit_of_number'] ?? '') . ' – wird verrechnet bzw. zurückerstattet.'), 0, 1);
            } elseif ($o['status'] === 'paid') {
                $pdf->SetTextColor(4, 120, 87);
                $pdf->Cell($W, 5.5, self::t('Bezahlt am ' . fmt_date($o['paid_at']) . ' – vielen Dank!'), 0, 1);
            } elseif ($o['status'] === 'cancelled') {
                $pdf->SetTextColor(190, 18, 60);
                $pdf->Cell($W, 5.5, self::t('STORNIERT – diese Rechnung ist nicht mehr gültig.'), 0, 1);
            } else {
                if (!empty($o['installments'])) {
                    $pdf->Cell($W, 5.5, self::t('Zahlbar in ' . count($o['installments']) . ' Raten:'), 0, 1);
                    $pdf->SetFont('Helvetica', '', 9);
                    foreach ($o['installments'] as $r) {
                        $pdf->SetTextColor($r['state'] === 'paid' ? 4 : 60, $r['state'] === 'paid' ? 120 : 70, $r['state'] === 'paid' ? 87 : 85);
                        $pdf->Cell(60, 5, self::t($r['label'] . ' · fällig ' . fmt_date($r['due_date'])), 0, 0);
                        $pdf->Cell(35, 5, self::t(money($r['amount'], $cur)), 0, 0, 'R');
                        $pdf->Cell(0, 5, self::t($r['state'] === 'paid' ? '  bezahlt' : ($r['state'] === 'partial' ? '  teilweise bezahlt' : '')), 0, 1);
                    }
                    $pdf->SetFont('Helvetica', 'B', 9.5);
                    $pdf->SetTextColor(15, 23, 42);
                } else {
                    $pdf->Cell($W, 5.5, self::t('Zahlbar bis ' . fmt_date($o['due_date'])), 0, 1);
                }
                if ($o['payment_info']) {
                    $pdf->SetFont('Courier', '', 8.5);
                    $pdf->SetTextColor(60, 70, 85);
                    $pdf->MultiCell($W, 4.4, self::t($o['payment_info']));
                }
            }
            $pdf->Ln(2);
        }
        if ($d['footer']) {
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->SetTextColor(70, 80, 95);
            $pdf->MultiCell($W, 4.6, self::t($d['footer']));
        }
        if (self::uidLine() !== '') {
            $pdf->Ln(2);
            $pdf->SetFont('Helvetica', '', 7.5);
            $pdf->SetTextColor(130, 140, 155);
            $pdf->Cell($W, 4, self::t(Settings::get('company_name') . ' · ' . self::uidLine()), 0, 1);
        }
        if (!empty($d['accepted'])) {
            $pdf->Ln(4);
            if ($pdf->GetY() > 240) {
                $pdf->AddPage();
            }
            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->SetTextColor(4, 120, 87);
            $pdf->Cell($W, 5, self::t($d['accepted']), 0, 1);
            if (!empty($d['signature'])) {
                try {
                    $pdf->Image($d['signature'], self::$margin, $pdf->GetY() + 1, 55);
                } catch (Throwable) {
                }
            }
        }

        // Einzahlungsschein
        if ($d['invoice'] && $o['status'] !== 'cancelled' && $o['status'] !== 'paid' && ($o['kind'] ?? '') !== 'credit') {
            $payload = SwissQr::payload($o);
            if ($payload !== null) {
                if ($pdf->GetY() > 192) {
                    $pdf->AddPage();
                }
                $pdf->SetAutoPageBreak(false);
                $pdf->hideFooter = true;
                $pdf->tplFont = null;   // Zahlteil: immer Helvetica in Originalgrösse (Schweizer Vorgabe)
                $pdf->tplScale = 1.0;
                self::paymentPart($pdf, $o, $payload);
            }
        }

        return $pdf->Output('S');
    }

    private static function paymentPart(PdfDoc $pdf, array $inv, string $payload): void
    {
        $y0 = 297 - 105;
        $cur = $inv['currency'];
        $cred = SwissQr::creditor();
        $debt = SwissQr::debtor($inv['customer']);
        $ref = $inv['qr_reference'] ?: SwissQr::referenceFor($inv);
        $iban = SwissQr::formatIban(SwissQr::iban());
        $credLines = [$cred['name'], trim($cred['street'] . ' ' . $cred['nr']), trim($cred['zip'] . ' ' . $cred['city'])];
        $debtLines = $debt ? [$debt['name'], trim($debt['street'] . ' ' . $debt['nr']), trim($debt['zip'] . ' ' . $debt['city'])] : [];
        $amount = number_format((float) ($inv['pay_amount'] ?? $inv['amount_due']), 2, '.', ' ');

        $label = static function (float $x, float $y, string $text, float $size) use ($pdf): void {
            $pdf->SetFont('Helvetica', 'B', $size);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 3, PdfService::t($text), 0, 0);
        };
        $text = static function (float $x, float $y, array $lines, float $size, float $w, float $lh) use ($pdf): float {
            $pdf->SetFont('Helvetica', '', $size);
            $pdf->SetTextColor(0, 0, 0);
            foreach (array_filter($lines, static fn($l) => $l !== '') as $l) {
                $pdf->SetXY($x, $y);
                $pdf->MultiCell($w, $lh, PdfService::t((string) $l));
                $y = $pdf->GetY();
            }
            return $y;
        };

        // Trennlinien
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.2);
        $pdf->SetDash(1, 1);
        $pdf->Line(0, $y0, 210, $y0);
        $pdf->Line(62, $y0, 62, 297);
        $pdf->SetDash();
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->SetTextColor(90, 90, 90);
        $pdf->SetXY(0, $y0 - 3.2);
        $pdf->Cell(210, 3, self::t('Vor der Einzahlung abzutrennen / A détacher avant le versement / Da staccare prima del versamento'), 0, 0, 'C');

        // Empfangsschein
        $label(5, $y0 + 5, 'Empfangsschein', 11);
        $y = $y0 + 12;
        $label(5, $y, 'Konto / Zahlbar an', 6);
        $y = $text(5, $y + 3.2, array_merge([$iban], $credLines), 8, 52, 3.4) + 2;
        if ($ref) {
            $label(5, $y, 'Referenz', 6);
            $y = $text(5, $y + 3.2, [SwissQr::formatRef($ref)], 8, 52, 3.4) + 2;
        }
        $label(5, $y, 'Zahlbar durch', 6);
        if ($debtLines) {
            $text(5, $y + 3.2, $debtLines, 8, 52, 3.4);
        } else {
            self::corners($pdf, 5, $y + 4, 52, 15);
        }
        $label(5, $y0 + 68, 'Währung', 6);
        $label(22, $y0 + 68, 'Betrag', 6);
        $text(5, $y0 + 71.5, [$cur], 8, 15, 3.6);
        $text(22, $y0 + 71.5, [$amount], 8, 35, 3.6);
        $pdf->SetFont('Helvetica', 'B', 6);
        $pdf->SetXY(5, $y0 + 82);
        $pdf->Cell(52, 3, self::t('Annahmestelle'), 0, 0, 'R');

        // Zahlteil
        $label(67, $y0 + 5, 'Zahlteil', 11);
        $matrix = SwissQr::matrix($payload);
        $n = count($matrix);
        $size = 46.0;
        $m = $size / $n;
        $qx = 67.0;
        $qy = $y0 + 17.0;
        $pdf->SetFillColor(0, 0, 0);
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                if ($matrix[$r][$c]) {
                    $pdf->Rect($qx + $c * $m, $qy + $r * $m, $m + 0.03, $m + 0.03, 'F');
                }
            }
        }
        // Schweizer Kreuz (7 x 7 mm, weisser Rand)
        $cx = $qx + $size / 2;
        $cy = $qy + $size / 2;
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect($cx - 4.5, $cy - 4.5, 9, 9, 'F');
        $pdf->SetFillColor(0, 0, 0);
        $pdf->Rect($cx - 3.5, $cy - 3.5, 7, 7, 'F');
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect($cx - 0.7, $cy - 2.1, 1.4, 4.2, 'F');
        $pdf->Rect($cx - 2.1, $cy - 0.7, 4.2, 1.4, 'F');

        $label(67, $y0 + 68, 'Währung', 8);
        $label(87, $y0 + 68, 'Betrag', 8);
        $text(67, $y0 + 72.5, [$cur], 10, 18, 4.4);
        $text(87, $y0 + 72.5, [$amount], 10, 40, 4.4);

        $x = 118.0;
        $y = $y0 + 5;
        $label($x, $y, 'Konto / Zahlbar an', 8);
        $y = $text($x, $y + 3.6, array_merge([$iban], $credLines), 10, 87, 4.3) + 2.5;
        if ($ref) {
            $label($x, $y, 'Referenz', 8);
            $y = $text($x, $y + 3.6, [SwissQr::formatRef($ref)], 10, 87, 4.3) + 2.5;
        }
        $label($x, $y, 'Zusätzliche Informationen', 8);
        $y = $text($x, $y + 3.6, [SwissQr::message($inv)], 10, 87, 4.3) + 2.5;
        $label($x, $y, 'Zahlbar durch', 8);
        if ($debtLines) {
            $text($x, $y + 3.6, $debtLines, 10, 87, 4.3);
        } else {
            self::corners($pdf, $x, $y + 5, 65, 25);
        }
    }

    /** Leeres Schreibfeld mit Eckmarken (wenn kein Schuldner bekannt ist). */
    private static function corners(PdfDoc $pdf, float $x, float $y, float $w, float $h): void
    {
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.2);
        $l = 3.0;
        foreach ([[$x, $y, 1, 1], [$x + $w, $y, -1, 1], [$x, $y + $h, 1, -1], [$x + $w, $y + $h, -1, -1]] as [$cx, $cy, $dx, $dy]) {
            $pdf->Line($cx, $cy, $cx + $dx * $l, $cy);
            $pdf->Line($cx, $cy, $cx, $cy + $dy * $l);
        }
    }

    /** Pfad des Logos als von FPDF lesbare Datei (PNG/JPG/GIF; WebP wird umgewandelt) oder null. */
    private static function logoFile(): ?string
    {
        $f = Brand::logoPath();
        if ($f === null) {
            return null;
        }
        if (str_ends_with($f, '.webp')) {
            if (!function_exists('imagecreatefromwebp')) {
                return null;
            }
            $im = @imagecreatefromwebp($f);
            if (!$im) {
                return null;
            }
            $tmp = sys_get_temp_dir() . '/vo-logo-' . md5($f . filemtime($f)) . '.png';
            if (!is_file($tmp)) {
                imagesavealpha($im, true);
                imagepng($im, $tmp);
            }
            return is_file($tmp) ? $tmp : null;
        }
        return preg_match('/\.(png|jpe?g|gif)$/', $f) ? $f : null;
    }

    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private static function tint(array $rgb): array
    {
        return array_map(static fn($c) => (int) round(255 - (255 - $c) * 0.12), $rgb);
    }

    /** Sendet das PDF an den Browser. */
    public static function send(string $pdf, string $filename, bool $inline = false): never
    {
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) . '"');
        header('Content-Length: ' . strlen($pdf));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        echo $pdf;
        exit;
    }
}
