<?php
declare(strict_types=1);

final class Router
{
    private array $routes = [];

    public function get(string $pattern, callable|array $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable|array $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    private function add(string $method, string $pattern, callable|array $handler): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[A-Za-z0-9_-]+)', rtrim($pattern, '/') ?: '/') . '$#';
        $this->routes[] = [$method, $regex, $handler];
    }

    public static function currentPath(): string
    {
        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $path = rawurldecode($path);
        $base = base_path();
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        if (str_starts_with($path, '/index.php')) {
            $path = substr($path, 10);
        }
        $path = '/' . trim($path, '/');
        return $path;
    }

    public function dispatch(string $method, string $path): void
    {
        $allowed = false;
        foreach ($this->routes as [$m, $regex, $handler]) {
            if (!preg_match($regex, $path, $match)) {
                continue;
            }
            $allowed = true;
            if ($m !== $method) {
                continue;
            }
            $params = array_filter($match, 'is_string', ARRAY_FILTER_USE_KEY);
            if ($method === 'POST' && !str_starts_with($path, '/webhooks/')) {   // Webhooks von Zahlungsanbietern: Signatur/API-Abfrage statt CSRF
                csrf_check();
            }
            if (is_array($handler)) {
                [$class, $action] = $handler;
                (new $class())->$action(...array_values($params));
            } else {
                $handler(...array_values($params));
            }
            return;
        }
        if ($allowed) {
            throw new HttpException(405, 'Diese Aktion ist auf diesem Weg nicht erlaubt.');
        }
        throw new HttpException(404);
    }
}
