<?php
declare(strict_types=1);

/** Einfache Begrenzung von Versuchen pro Schlüssel (Login, Magic-Link). */
final class RateLimit
{
    public static function tooMany(string $bucket, int $max, int $windowSeconds): bool
    {
        $since = date('Y-m-d H:i:s', time() - $windowSeconds);
        $count = (int) DB::val('SELECT COUNT(*) FROM rate_limits WHERE bucket = ? AND created_at >= ?', [$bucket, $since]);
        return $count >= $max;
    }

    public static function hit(string $bucket): void
    {
        DB::insert('rate_limits', ['bucket' => mb_substr($bucket, 0, 190), 'created_at' => now()]);
        if (random_int(1, 50) === 1) {
            DB::exec('DELETE FROM rate_limits WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
        }
    }

    public static function clear(string $bucket): void
    {
        DB::exec('DELETE FROM rate_limits WHERE bucket = ?', [$bucket]);
    }
}
