<?php
declare(strict_types=1);

final class Database
{
    private static ?self $instance = null;
    private PDO $connection;

    private function __construct()
    {
        $host = getenv('DB_HOST') ?: (defined('DB_HOST') ? DB_HOST : '127.0.0.1');
        $port = getenv('DB_PORT') ?: (defined('DB_PORT') ? DB_PORT : '3306');
        $name = getenv('DB_NAME') ?: (defined('DB_NAME') ? DB_NAME : 'jazuva_db');
        $user = getenv('DB_USER') ?: (defined('DB_USER') ? DB_USER : 'root');
        $password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : (defined('DB_PASS') ? DB_PASS : '');
        $charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

        $this->connection = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function connection(): PDO
    {
        return $this->connection;
    }

    private function __clone()
    {
    }

    public function __wakeup(): void
    {
        throw new LogicException('Database singleton cannot be unserialized.');
    }
}
