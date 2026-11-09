<?php
declare(strict_types=1);

final class App
{
    private array $routes = [];

    public function get(string $path, string $action): void
    {
        $this->routes['GET ' . $this->normalize($path)] = $action;
    }

    public function post(string $path, string $action): void
    {
        $this->routes['POST ' . $this->normalize($path)] = $action;
    }

    public function run(): void
    {
        $path = $this->normalize((string)($_GET['r'] ?? parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)));
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $action = $this->routes[$method . ' ' . $path] ?? null;
        if ($action === null) {
            http_response_code(404);
            require BASE_PATH . '/app/Views/errors/404.php';
            return;
        }

        [$class, $methodName] = explode('@', $action, 2);
        if (!class_exists($class) || !method_exists($class, $methodName)) {
            throw new RuntimeException('Rute aplikasi tidak valid.');
        }
        (new $class())->$methodName();
    }

    private function normalize(string $path): string
    {
        $path = '/' . trim(rawurldecode($path), '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
