<?php
declare(strict_types=1);

/**
 * Berechnung von Offerten: Positionsrabatte, Gesamtrabatt, Versand,
 * Steuern je Satz und (optional) Rundung auf 5 Rappen.
 * Identische Logik in assets/js/offer-editor.js für die Live-Vorschau.
 */
final class Calc
{
    public static function r2(float $v): float
    {
        return round($v + 0.0, 2, PHP_ROUND_HALF_UP);
    }

    /**
     * @param array $items  [quantity, unit_price, discount_type, discount_value, tax_rate, tax_label]
     * @param array $opts   [discount_type, discount_value, shipping_cost, shipping_tax_rate, shipping_tax_label, round_005]
     */
    public static function compute(array $items, array $opts): array
    {
        $outItems = [];
        $groups = [];
        $subtotal = 0.0;

        foreach ($items as $it) {
            $qty = max(0.0, (float) $it['quantity']);
            $price = (float) $it['unit_price'];
            $lineSub = self::r2($qty * $price);
            $disc = self::discount($lineSub, (string) ($it['discount_type'] ?? 'none'), (float) ($it['discount_value'] ?? 0));
            $lineTotal = self::r2($lineSub - $disc);
            $subtotal += $lineTotal;

            $key = self::groupKey((float) $it['tax_rate'], (string) $it['tax_label']);
            $groups[$key] ??= ['label' => (string) $it['tax_label'], 'rate' => (float) $it['tax_rate'], 'base' => 0.0];
            $groups[$key]['base'] += $lineTotal;

            $outItems[] = array_merge($it, [
                'line_subtotal' => $lineSub,
                'line_discount' => $disc,
                'line_total'    => $lineTotal,
            ]);
        }
        $subtotal = self::r2($subtotal);

        // Gesamtrabatt anteilig auf die Steuergruppen verteilen.
        $discount = self::discount($subtotal, (string) ($opts['discount_type'] ?? 'none'), (float) ($opts['discount_value'] ?? 0));
        if ($discount > 0 && $subtotal > 0) {
            $remaining = $discount;
            $keys = array_keys($groups);
            foreach ($keys as $i => $k) {
                $share = $i === count($keys) - 1 ? $remaining : self::r2($discount * $groups[$k]['base'] / $subtotal);
                $groups[$k]['base'] -= $share;
                $remaining = self::r2($remaining - $share);
            }
        }

        $shipping = self::r2(max(0.0, (float) ($opts['shipping_cost'] ?? 0)));
        if ($shipping > 0) {
            $key = self::groupKey((float) ($opts['shipping_tax_rate'] ?? 0), (string) ($opts['shipping_tax_label'] ?? ''));
            $groups[$key] ??= ['label' => (string) ($opts['shipping_tax_label'] ?? ''), 'rate' => (float) ($opts['shipping_tax_rate'] ?? 0), 'base' => 0.0];
            $groups[$key]['base'] += $shipping;
        }

        $taxTotal = 0.0;
        $breakdown = [];
        foreach ($groups as $g) {
            $base = self::r2($g['base']);
            $tax = self::r2($base * $g['rate'] / 100);
            $taxTotal += $tax;
            $breakdown[] = ['label' => $g['label'], 'rate' => $g['rate'], 'base' => $base, 'tax' => $tax];
        }
        usort($breakdown, static fn($a, $b) => $b['rate'] <=> $a['rate']);

        $net = self::r2($subtotal - $discount + $shipping);
        $taxTotal = self::r2($taxTotal);
        $gross = self::r2($net + $taxTotal);
        $total = !empty($opts['round_005']) ? self::r2(round($gross * 20) / 20) : $gross;

        return [
            'items'           => $outItems,
            'subtotal'        => $subtotal,
            'discount_amount' => $discount,
            'shipping_cost'   => $shipping,
            'net_total'       => $net,
            'tax_breakdown'   => $breakdown,
            'tax_total'       => $taxTotal,
            'rounding'        => self::r2($total - $gross),
            'total'           => $total,
        ];
    }

    private static function discount(float $base, string $type, float $value): float
    {
        if ($base <= 0 || $value <= 0) {
            return 0.0;
        }
        return match ($type) {
            'percent' => self::r2($base * min($value, 100) / 100),
            'amount'  => self::r2(min($value, $base)),
            default   => 0.0,
        };
    }

    private static function groupKey(float $rate, string $label): string
    {
        return number_format($rate, 3, '.', '') . '|' . $label;
    }
}
