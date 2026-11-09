<?php
declare(strict_types=1);

abstract class Controller
{
    protected function view(string $template, array $data = []): void
    {
        $file = BASE_PATH . '/app/Views/' . ltrim($template, '/\\') . '.php';
        if (!is_file($file)) {
            throw new RuntimeException('Template tidak ditemukan: ' . $template);
        }
        extract($data, EXTR_SKIP);
        require $file;
    }

    protected function redirect(string $url, int $status = 302): never
    {
        header('Location: ' . $url, true, $status);
        exit;
    }

    protected function legacyPage(string $relativePath, string|array|null $roles = null): never
    {
        if ($roles !== null) {
            Security::requireRole($roles);
        }
        $file = BASE_PATH . '/' . ltrim($relativePath, '/\\');
        if (!is_file($file)) {
            throw new RuntimeException('Halaman modul tidak ditemukan.');
        }
        require $file;
        exit;
    }
}
