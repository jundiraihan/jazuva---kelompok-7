<?php
/**
 * JAZUVA - Restaurant & Coffee Shop Management System
 * Database Configuration & Global Constants
 */

date_default_timezone_set('Asia/Jakarta');

// Define Base URL dynamically so it works in both /jazuva/ and /Project PWD Kelompok 7/
$scriptName = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$basePath = preg_replace('/(\/(admin|cashier|kitchen|barista|inventory|api|includes|config).*)$/', '', $scriptName);
$basePath = rtrim($basePath, '/');

// Absolute Base URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', $protocol . $host . $basePath);
define('BASE_PATH', __DIR__ . '/..');

// Database Credentials
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'jazuva_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

require_once __DIR__ . '/../app/Core/Database.php';

function getDbConnection(): PDO {
    try {
        return Database::getInstance()->connection();
    } catch (PDOException $exception) {
        error_log('[Jazuva database connection] ' . $exception->getMessage());

        if (PHP_SAPI === 'cli') {
            throw $exception;
        }

        http_response_code(503);
        $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
        if (preg_match('~(?:^|/)api/~', $requestPath) === 1) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Database belum tersedia. Jalankan MySQL dari XAMPP lalu coba kembali.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        require BASE_PATH . '/app/Views/errors/database-unavailable.php';
        exit;
    }
}

// Global PDO instance
$pdo = getDbConnection();
