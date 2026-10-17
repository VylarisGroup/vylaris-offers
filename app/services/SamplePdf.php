<?php
/** Beispieldokumente für die Vorschau der Dokumentvorlagen (keine echten Kundendaten). */
declare(strict_types=1);

final class SamplePdf
{
    private static function base(): array
    {
        $liable = TaxService::liable();
        $rate = $liable ? 8.1 : 0.0;
        $items = [];
        foreach ([
            ['Beratung und Konzeption', 'Workshop, Anforderungsanalyse und Projektplanung', 8.0, 'Std.', 150.0],
            ['Umsetzung', 'Design, Entwicklung und Test gemäss Konzept', 1.0, 'Pauschal', 4200.0],
            ['Schulung', null, 2.0, 'Tage', 900.0],
        ] as $i => [$title, $desc, $qty, $unit, $price]) {
            $line = round($qty * $price, 2);
            $items[] = [
                'position' => $i + 1, 'title' => $title, 'description' => $desc, 'quantity' => $qty, 'unit' => $unit, 'unit_price' => $price,
                'discount_type' => 'none', 'discount_value' => 0, 'line_subtotal' => $line, 'line_discount' => 0, 'line_total' => $line,
                'tax_template_id' => null, 'tax_rate' => $rate, 'tax_label' => $liable ? 'MWST' : null,
            ];
        }
        $net = (float) array_sum(array_column($items, 'line_total'));
        $tax = round($net * $rate / 100, 2);
        $total = round($net + $tax, 2);
        $taxes = $liable ? [['label' => 'MWST', 'rate' => $rate, 'base' => $net, 'tax' => $tax]] : [];
        return [
            'id' => 0, 'public_id' => str_repeat('x', 24), 'title' => 'Website-Relaunch 2027', 'currency' => (string) Settings::get('currency', 'CHF'),
            'customer' => [
                'id' => 0, 'customer_no' => 'KD-1001', 'salutation' => 'Frau', 'first_name' => 'Erika', 'last_name' => 'Beispiel', 'company' => 'Beispiel AG',
                'street' => 'Musterstrasse 12', 'address_extra' => '', 'zip' => '8000', 'city' => 'Zürich', 'country' => 'CH', 'email' => 'erika@example.ch', 'phone' => '', 'vat_id' => '',
            ],
            'items' => $items, 'subtotal' => $net, 'discount_type' => 'none', 'discount_value' => 0, 'discount_amount' => 0.0,
            'area_type' => 'digital', 'shipping_method' => null, 'shipping_cost' => 0.0, 'delivery_info' => null,
            'net_total' => $net, 'tax_total' => $tax, 'rounding' => 0.0, 'total' => $total, 'taxes' => $taxes, 'tax_breakdown' => json_encode($taxes),
            'intro' => 'Vielen Dank für Ihre Anfrage. Gerne unterbreiten wir Ihnen folgendes Angebot:',
            'footer' => 'Wir freuen uns auf die Zusammenarbeit und stehen für Rückfragen jederzeit zur Verfügung.',
            'template_id' => null, 'created_by' => null, 'created_by_name' => 'Anna Muster', 'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    public static function offer(): array
    {
        return self::base() + [
            'number' => 'OF-2027-0001', 'status' => 'open', 'valid_until' => date('Y-m-d', strtotime('+30 days')),
            'response_name' => null, 'responded_at' => null, 'signature_mode' => 'none',
        ];
    }

    public static function invoice(): array
    {
        $b = self::base();
        return $b + [
            'number' => 'RE-2027-0001', 'status' => 'sent', 'kind' => 'final', 'issue_date' => date('Y-m-d'), 'due_date' => date('Y-m-d', strtotime('+30 days')),
            'payment_info' => "Zahlbar innert 30 Tagen netto.\nBitte geben Sie die Rechnungsnummer an.", 'service_period' => null,
            'payments' => [], 'adjustments' => [], 'installments' => [], 'deduction_list' => [], 'amount_due' => $b['total'], 'open_amount' => $b['total'],
            'paid_at' => null, 'credit_of_number' => null, 'qr_reference' => null,
        ];
    }
}
