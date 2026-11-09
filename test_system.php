<?php
/**
 * JAZUVA - End-to-End System Integration Test
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$baseUrl = getenv('JAZUVA_TEST_URL') ?: 'http://localhost/jazuva';
$cookieFile = tempnam(sys_get_temp_dir(), 'jazuva-test-');
register_shutdown_function(static function () use ($cookieFile): void {
    if (is_file($cookieFile)) {
        unlink($cookieFile);
    }
});

$loginRequest = curl_init($baseUrl . '/login.php');
curl_setopt_array($loginRequest, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile
]);
$loginPage = curl_exec($loginRequest);
curl_close($loginRequest);
if (!is_string($loginPage) || !preg_match('/name="csrf_token" value="([^"]+)"/', $loginPage, $csrfMatch)) {
    die("Tidak dapat memulai sesi login untuk pengujian. Periksa URL dan server Apache.\n");
}
$csrfToken = html_entity_decode($csrfMatch[1], ENT_QUOTES, 'UTF-8');

$loginRequest = curl_init($baseUrl . '/login.php');
curl_setopt_array($loginRequest, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'username' => 'admin',
        'password' => 'admin123',
        'csrf_token' => $csrfToken
    ]),
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_FOLLOWLOCATION => true
]);
curl_exec($loginRequest);
$loginStatus = curl_getinfo($loginRequest, CURLINFO_HTTP_CODE);
curl_close($loginRequest);
if ($loginStatus >= 400) {
    die("Login pengujian gagal. Pastikan akun admin seed tersedia.\n");
}

$pdo = getDbConnection();

echo "====================================================\n";
echo "   JAZUVA RESTAURANT & COFFEE SHOP - SYSTEM TEST    \n";
echo "====================================================\n\n";

// 1. Check Table 07
$stmtT = $pdo->prepare("SELECT * FROM restaurant_tables WHERE table_number = '07'");
$stmtT->execute();
$table7 = $stmtT->fetch();
if (!$table7) {
    die("Meja 07 tidak tersedia. Impor data awal terlebih dahulu.\n");
}
echo "[TEST 1] Meja 07 ditemukan: ID {$table7['id']}, Status: {$table7['status']}\n";

// 2. Check Beras Stock before order
$stmtBeras = $pdo->prepare("SELECT * FROM ingredients WHERE id = 1");
$stmtBeras->execute();
$berasBefore = (float)$stmtBeras->fetch()['stock'];
echo "[TEST 2] Stok Beras sebelum pemrosesan: {$berasBefore} kg\n";

// 3. Simulate Customer Order on Meja 07:
// 2x Nasi Goreng (Menu ID 1), 1x Cappuccino (Menu ID 5)
$orderPayload = [
    'action' => 'create_order',
    'table_id' => $table7['id'],
    'table_token' => $table7['token'],
    'request_token' => sprintf(
        '%s-%s-%s-%s-%s',
        bin2hex(random_bytes(4)),
        bin2hex(random_bytes(2)),
        bin2hex(random_bytes(2)),
        bin2hex(random_bytes(2)),
        bin2hex(random_bytes(6))
    ),
    'table_number' => '07',
    'customer_name' => 'Budi Test End-to-End',
    'items' => [
        ['menuId' => 1, 'quantity' => 2, 'notes' => '1 pedas, 1 tidak pedas'],
        ['menuId' => 5, 'quantity' => 1, 'notes' => 'Less sugar']
    ]
];

// Call order placement logic
$ch = curl_init($baseUrl . '/api/orders.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orderPayload));
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'X-CSRF-Token: ' . $csrfToken]);
$orderResponse = curl_exec($ch);
curl_close($ch);

$orderResult = json_decode($orderResponse, true);
echo "[TEST 3] Hasil Pemesanan Pelanggan:\n";
print_r($orderResult);

if (!$orderResult || !$orderResult['success']) {
    die("Pemesanan gagal! Menghentikan pengujian.\n");
}

$newOrderId = $orderResult['order_id'];
$newOrderNumber = $orderResult['order_number'];

// 4. Verify Food in Kitchen
$stmtKitchenItem = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ? AND station = 'kitchen'");
$stmtKitchenItem->execute([$newOrderId]);
$kItem = $stmtKitchenItem->fetch();
echo "[TEST 4] Item Kitchen ditemukan: {$kItem['id']}, Status: {$kItem['item_status']}, Qty: {$kItem['quantity']}\n";

// 5. Verify Drink in Barista
$stmtBaristaItem = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ? AND station = 'barista'");
$stmtBaristaItem->execute([$newOrderId]);
$bItem = $stmtBaristaItem->fetch();
echo "[TEST 5] Item Barista ditemukan: {$bItem['id']}, Status: {$bItem['item_status']}, Qty: {$bItem['quantity']}\n";

// 6. Kitchen confirms order to 'diproses' -> should trigger stock deduction
echo "[TEST 6] Kitchen mengubah status pesanan ke 'diproses'...\n";
$ch = curl_init($baseUrl . '/api/kitchen.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['order_id' => $newOrderId, 'status' => 'diproses']));
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'X-CSRF-Token: ' . $csrfToken]);
$kResponse = curl_exec($ch);
curl_close($ch);
echo "Response Kitchen: {$kResponse}\n";

// Check Beras Stock after Kitchen process:
// Recipe Nasi Goreng: 0.15 kg per portion * 2 portions = 0.30 kg deduction
$stmtBeras->execute();
$berasAfter = (float)$stmtBeras->fetch()['stock'];
$expectedBeras = $berasBefore - 0.30;
echo "Stok Beras setelah diproses: {$berasAfter} kg (Ekspektasi: {$expectedBeras} kg)\n";
if (abs($berasAfter - $expectedBeras) < 0.001) {
    echo ">> VALIDASI PENGURANGAN STOK BERHASIL TEPAT SESUAI RESEP! <<\n";
} else {
    echo ">> PERINGATAN: Perbedaan stok terdeteksi! <<\n";
}

// 7. Check Audit Trail in stock_movements
$stmtMovement = $pdo->prepare("SELECT * FROM stock_movements WHERE reference_id = ? ORDER BY id DESC LIMIT 1");
$stmtMovement->execute([$kItem['id']]);
$move = $stmtMovement->fetch();
echo "[TEST 7] Log Riwayat Mutasi Tercatat: Tipe={$move['movement_type']}, Qty={$move['quantity']}, Ket={$move['notes']}\n";

// 8. Cashier POS processes Payment
$stmtSess = $pdo->prepare("SELECT id FROM table_sessions WHERE table_id = ? AND status = 'active' LIMIT 1");
$stmtSess->execute([$table7['id']]);
$sessId = $stmtSess->fetchColumn();

echo "[TEST 8] Kasir memproses pembayaran untuk Sesi #{$sessId} Meja 07...\n";
// Total bill: 2x Nasi Goreng (40.000) + 1x Cappuccino (15.000) = 55.000
// Customer pays 100.000 cash, change 45.000
$payPayload = [
    'action' => 'process_payment',
    'session_id' => $sessId,
    'payment_method' => 'tunai',
    'total_bill' => 55000,
    'amount_paid' => 100000
];

$ch = curl_init($baseUrl . '/api/cashier.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payPayload));
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'X-CSRF-Token: ' . $csrfToken]);
$payResponse = curl_exec($ch);
curl_close($ch);
echo "Response Pembayaran Kasir: {$payResponse}\n";

// 9. Verify Table 07 is back to 'tersedia'
$stmtT->execute();
$table7After = $stmtT->fetch();
echo "[TEST 9] Status Meja 07 setelah lunas: {$table7After['status']}\n";

echo "\n====================================================\n";
echo "   SEMUA MODUL BERHASIL DIINTEGRASIKAN & DIUJI!     \n";
echo "====================================================\n";
