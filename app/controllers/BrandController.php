<?php
declare(strict_types=1);

/** Öffentliche Auslieferung von Logo und Favicon (Whitelabel). */
final class BrandController
{
    public function logo(): void
    {
        Brand::send('logo');
    }

    public function favicon(): void
    {
        Brand::send('favicon');
    }
}
