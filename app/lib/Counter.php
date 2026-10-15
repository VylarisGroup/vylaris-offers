<?php
declare(strict_types=1);

/** Atomare, lückenlose Nummernkreise pro Jahr. */
final class Counter
{
    public static function next(string $name, int $year = 0, int $start = 1): int
    {
        DB::exec(
            'INSERT INTO counters (name, year, value) VALUES (?, ?, LAST_INSERT_ID(?))
             ON DUPLICATE KEY UPDATE value = LAST_INSERT_ID(value + 1)',
            [$name, $year, $start]
        );
        return (int) DB::val('SELECT LAST_INSERT_ID()');
    }

    public static function offerNumber(): string
    {
        $y = (int) date('Y');
        return sprintf('%s-%d-%04d', Settings::get('offer_prefix', 'OF'), $y, self::next('offer', $y));
    }

    public static function orderNumber(): string
    {
        $y = (int) date('Y');
        return sprintf('%s-%d-%04d', Settings::get('order_prefix', 'AU'), $y, self::next('order', $y));
    }

    public static function invoiceNumber(): string
    {
        $y = (int) date('Y');
        return sprintf('%s-%d-%04d', Settings::get('invoice_prefix', 'RE'), $y, self::next('invoice', $y));
    }

    public static function customerNumber(): string
    {
        return 'KD-' . self::next('customer', 0, 1001);
    }
}
