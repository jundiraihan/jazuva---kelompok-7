<?php
declare(strict_types=1);

final class Security
{
    public static function startSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
                'use_strict_mode' => true,
                'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            ]);
        }
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function authenticated(): bool
    {
        self::startSession();
        return !empty($_SESSION['user_id']);
    }

    public static function allows(string|array $roles): bool
    {
        if (!self::authenticated()) {
            return false;
        }
        $allowed = array_map('strtolower', (array)$roles);
        return in_array(strtolower((string)($_SESSION['role_name'] ?? '')), $allowed, true);
    }

    public static function requireRole(string|array $roles): void
    {
        if (!self::allows($roles)) {
            http_response_code(self::authenticated() ? 403 : 401);
            exit(self::authenticated() ? 'Akses ditolak.' : 'Silakan login terlebih dahulu.');
        }
    }

    public static function csrfToken(): string
    {
        self::startSession();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        self::startSession();
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
    }

    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function validate(array $input, array $rules): array
    {
        $errors = [];
        foreach ($rules as $field => $ruleList) {
            $value = $input[$field] ?? null;
            if ($value !== null && !is_scalar($value)) {
                $errors[$field] = 'Format data tidak valid.';
                continue;
            }
            foreach ((array)$ruleList as $rule) {
                if ($rule === 'required' && ($value === null || trim((string)$value) === '')) {
                    $errors[$field] = 'Wajib diisi.';
                    break;
                }
                if ($rule === 'email' && $value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field] = 'Format email tidak valid.';
                    break;
                }
                if ($rule === 'numeric' && $value !== null && $value !== '' && !is_numeric($value)) {
                    $errors[$field] = 'Harus berupa angka.';
                    break;
                }
            }
        }
        return $errors;
    }
}
