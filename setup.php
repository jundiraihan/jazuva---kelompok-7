<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');
$lockFile = __DIR__ . '/config/.setup.lock';
if (is_file($lockFile)) {
    http_response_code(403);
    exit('Setup sudah selesai. Hapus lock hanya setelah pencadangan dan dengan akses administrator server.');
}

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_NAME') ?: 'jazuva_db';
$name = preg_match('/^[A-Za-z0-9_]+$/', $name) ? $name : '';
if ($name === '') {
    throw new InvalidArgumentException('Nama database hanya boleh berisi huruf, angka, dan garis bawah.');
}
$user = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$charset = 'utf8mb4';

if (PHP_SAPI !== 'cli') {
    $setupToken = getenv('JAZUVA_SETUP_TOKEN');
    $submittedToken = $_POST['setup_token'] ?? '';
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$setupToken || !hash_equals($setupToken, (string)$submittedToken)) {
        http_response_code(403);
        exit('Akses setup ditolak. Jalankan melalui CLI atau gunakan token setup sementara dari konfigurasi server.');
    }
}

$schemaFile = __DIR__ . '/config/database.sql';
if (!is_file($schemaFile)) {
    throw new RuntimeException('File skema database tidak ditemukan.');
}

$server = new PDO("mysql:host={$host};port={$port};charset={$charset}", $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$server->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$db = new PDO("mysql:host={$host};port={$port};dbname={$name};charset={$charset}", $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$tableCount = (int)$db->query(
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'"
)->fetchColumn();
$isFreshDatabase = $tableCount === 0;
$sql = file_get_contents($schemaFile);
if ($sql === false) {
    throw new RuntimeException('Skema database tidak dapat dibaca.');
}
$sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql);
$sql = preg_replace('/^\s*CREATE\s+DATABASE\s+IF\s+NOT\s+EXISTS[^;]+;\s*/im', '', $sql);
$sql = preg_replace('/^\s*USE\s+`?[^;]+;\s*/im', '', $sql);
$parts = preg_split('/;\s*(?:\r?\n|$)/', $sql);
$seeding = false;
foreach ($parts as $statement) {
    if (stripos($statement, 'SEED INITIAL DATA') !== false) {
        $seeding = true;
    }
    $statement = trim($statement);
    if ($statement === '' || ($seeding && !$isFreshDatabase)) {
        continue;
    }
    $db->exec($statement);
}

$hasColumn = static function (string $table, string $column) use ($db): bool {
    $query = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
    );
    $query->execute([$table, $column]);
    return (int)$query->fetchColumn() > 0;
};
$ensureColumn = static function (string $table, string $column, string $definition) use ($db, $hasColumn): void {
    if (!$hasColumn($table, $column)) {
        $db->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }
};
$ensureColumn('orders', 'request_token', 'CHAR(36) NULL');
$ensureColumn('payments', 'subtotal', 'DECIMAL(12, 2) NOT NULL DEFAULT 0.00');
$ensureColumn('payments', 'service_charge_amount', 'DECIMAL(12, 2) NOT NULL DEFAULT 0.00');
$ensureColumn('payments', 'tax_amount', 'DECIMAL(12, 2) NOT NULL DEFAULT 0.00');
$ensureColumn('payments', 'midtrans_transaction_id', 'VARCHAR(100) NULL');
$ensureColumn('payments', 'qris_qr_url', 'TEXT NULL');
$ensureColumn('restaurant_tables', 'token', 'VARCHAR(64) NULL');

$existingTokens = $db->query('SELECT id, token FROM restaurant_tables ORDER BY id')->fetchAll();
$seenTokens = [];
$repairToken = $db->prepare('UPDATE restaurant_tables SET token = ? WHERE id = ?');
foreach ($existingTokens as $table) {
    $token = (string)($table['token'] ?? '');
    if ($token === '' || isset($seenTokens[$token])) {
        do {
            $token = bin2hex(random_bytes(32));
        } while (isset($seenTokens[$token]));
        $repairToken->execute([$token, $table['id']]);
    }
    $seenTokens[$token] = true;
}

$hasUniqueIndex = static function (string $table, string $column) use ($db): bool {
    $query = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.statistics
         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? AND non_unique = 0'
    );
    $query->execute([$table, $column]);
    return (int)$query->fetchColumn() > 0;
};
if (!$hasUniqueIndex('orders', 'request_token')) {
    $duplicates = (int)$db->query(
        'SELECT COUNT(*) FROM (
            SELECT request_token FROM orders
            WHERE request_token IS NOT NULL
            GROUP BY request_token HAVING COUNT(*) > 1
         ) duplicate_tokens'
    )->fetchColumn();
    if ($duplicates > 0) {
        throw new RuntimeException('Nilai request_token ganda ditemukan; perbaiki data sebelum menambahkan unique index.');
    }
    $db->exec('CREATE UNIQUE INDEX uq_orders_request_token ON orders (request_token)');
}
if (!$hasUniqueIndex('restaurant_tables', 'token')) {
    $db->exec('CREATE UNIQUE INDEX uq_restaurant_tables_token ON restaurant_tables (token)');
}
$db->exec(
    'ALTER TABLE ingredients
       MODIFY stock DECIMAL(12, 4) NOT NULL DEFAULT 0.0000,
       MODIFY min_stock DECIMAL(12, 4) NOT NULL DEFAULT 5.0000'
);
$db->exec(
    'ALTER TABLE stock_movements
       MODIFY quantity DECIMAL(12, 4) NOT NULL,
       MODIFY previous_stock DECIMAL(12, 4) NOT NULL,
       MODIFY current_stock DECIMAL(12, 4) NOT NULL'
);
$db->exec('UPDATE payments SET subtotal = total_bill WHERE subtotal = 0 AND total_bill > 0');

$lock = "Setup completed at " . date(DATE_ATOM) . PHP_EOL;
if (file_put_contents($lockFile, $lock, LOCK_EX) === false) {
    throw new RuntimeException('Database siap, tetapi lock setup tidak dapat dibuat. Lindungi setup.php secara manual.');
}
if (!chmod($lockFile, 0600)) {
    throw new RuntimeException('Setup berhasil, tetapi permission lock tidak dapat dibatasi.');
}

echo "Setup Jazuva berhasil. Data awal dimuat hanya untuk database yang benar-benar kosong." . PHP_EOL;
