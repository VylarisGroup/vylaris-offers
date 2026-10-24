<?php
declare(strict_types=1);

final class View
{
    public static function render(string $template, array $data = [], ?string $layout = 'admin'): string
    {
        [$content, $vars] = self::capture($template, $data);
        if ($layout === null) {
            return $content;
        }
        // Von der View gesetzte Werte (z. B. $title, $scripts) an das Layout weitergeben.
        $shared = array_intersect_key($vars, array_flip(['title', 'scripts']));
        $html = self::partial('layouts/' . $layout, array_merge($data, $shared, ['content' => $content]));
        return $layout === 'admin' ? Brand::enforceWatermark($html) : $html;
    }

    public static function partial(string $template, array $data = []): string
    {
        return self::capture($template, $data)[0];
    }

    private static function capture(string $__template, array $__data): array
    {
        $template = $__template;
        $data = $__data;
        $file = APP_DIR . '/views/' . $template . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("View nicht gefunden: $template");
        }
        unset($__template, $__data);
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            include $file;
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        $__html = (string) ob_get_clean();
        return [$__html, get_defined_vars()];
    }
}
