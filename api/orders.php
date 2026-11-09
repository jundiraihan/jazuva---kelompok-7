<?php
/**
 * JAZUVA - Customer Order API
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = getDbConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'check_status') {
        $orderNumber = trim($_GET['order'] ?? '');
        $stmt = $pdo->prepare("SELECT order_status FROM orders WHERE order_number = ? LIMIT 1");
        $stmt->execute([$orderNumber]);
        $order = $stmt->fetch();

        if ($order) {
            echo json_encode(['success' => true, 'status' => $order['order_status']]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Pesanan tidak ditemukan.']);
        }
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Aksi tidak valid.']);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    header('Allow: GET, POST');
    echo json_encode(['success' => false, 'message' => 'Metode permintaan tidak didukung.']);
    exit;
}

requireApiCsrfToken();

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

if (($input['action'] ?? '') !== 'create_order') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenali.']);
    exit;
}

$tableId = filter_var($input['table_id'] ?? null, FILTER_VALIDATE_INT);
$tableToken = trim($input['table_token'] ?? '');
$requestToken = trim($input['request_token'] ?? '');
$customerName = trim($input['customer_name'] ?? 'Pelanggan');
$items = $input['items'] ?? null;

if (!$tableId || !preg_match('/^[a-f0-9]{64}$/i', $tableToken)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Identitas meja tidak valid. Pindai QR meja kembali.']);
    exit;
}

if (!preg_match('/^[a-f0-9-]{36}$/i', $requestToken)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Token pengiriman pesanan tidak valid. Muat ulang halaman.']);
    exit;
}

if (!is_array($items) || count($items) < 1 || count($items) > 30) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Keranjang pesanan tidak valid.']);
    exit;
}

if ($customerName === '') {
    $customerName = 'Pelanggan';
}
if (strlen($customerName) > 100) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nama pemesan maksimal 100 karakter.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmtTable = $pdo->prepare("SELECT * FROM restaurant_tables
                                WHERE id = ? AND token = ? AND is_active = 1
                                FOR UPDATE");
    $stmtTable->execute([$tableId, $tableToken]);
    $table = $stmtTable->fetch();
    if (!$table) {
        throw new InvalidArgumentException('Meja tidak ditemukan atau QR Code tidak valid.');
    }

    $stmtExisting = $pdo->prepare("SELECT id, order_number FROM orders WHERE request_token = ? LIMIT 1");
    $stmtExisting->execute([$requestToken]);
    $existingOrder = $stmtExisting->fetch();
    if ($existingOrder) {
        $pdo->commit();
        echo json_encode([
            'success' => true,
            'order_number' => $existingOrder['order_number'],
            'order_id' => (int)$existingOrder['id'],
            'message' => 'Pesanan sudah berhasil dikirim.'
        ]);
        exit;
    }

    $session = getOrCreateTableSession((int)$table['id'], $customerName);
    $sessionId = (int)$session['id'];
    $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $totalAmount = 0.00;
    $preparedItems = [];

    foreach ($items as $item) {
        if (!is_array($item)) {
            throw new InvalidArgumentException('Rincian keranjang tidak valid.');
        }

        $menuId = filter_var($item['menuId'] ?? null, FILTER_VALIDATE_INT);
        $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);
        $notes = trim($item['notes'] ?? '');
        if (!$menuId || !$quantity || $quantity < 1 || $quantity > 99 || strlen($notes) > 255) {
            throw new InvalidArgumentException('Jumlah atau catatan menu tidak valid.');
        }

        $stmtMenu = $pdo->prepare("SELECT m.id, m.name, m.price, c.target_station
                                   FROM menus m
                                   JOIN categories c ON m.category_id = c.id
                                   WHERE m.id = ? AND m.is_available = 1 AND c.is_active = 1
                                   FOR UPDATE");
        $stmtMenu->execute([$menuId]);
        $menu = $stmtMenu->fetch();
        if (!$menu) {
            throw new InvalidArgumentException("Menu #{$menuId} tidak tersedia saat ini.");
        }

        $unitPrice = (float)$menu['price'];
        $subtotal = $unitPrice * $quantity;
        $totalAmount += $subtotal;
        $preparedItems[] = [
            'menu_id' => (int)$menu['id'],
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $subtotal,
            'notes' => $notes,
            'station' => $menu['target_station'] === 'barista' ? 'barista' : 'kitchen'
        ];
    }

    $stmtOrder = $pdo->prepare("INSERT INTO orders
        (order_number, table_id, session_id, customer_name, total_amount, final_amount, request_token, order_status, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'menunggu', '')");
    $stmtOrder->execute([
        $orderNumber,
        $tableId,
        $sessionId,
        $customerName,
        $totalAmount,
        $totalAmount,
        $requestToken
    ]);
    $orderId = (int)$pdo->lastInsertId();

    $stmtItem = $pdo->prepare("INSERT INTO order_items
        (order_id, menu_id, quantity, unit_price, subtotal, notes, station, item_status, stock_deducted)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'menunggu', 0)");
    foreach ($preparedItems as $preparedItem) {
        $stmtItem->execute([
            $orderId,
            $preparedItem['menu_id'],
            $preparedItem['quantity'],
            $preparedItem['unit_price'],
            $preparedItem['subtotal'],
            $preparedItem['notes'],
            $preparedItem['station']
        ]);
    }

    $pdo->prepare("UPDATE table_sessions SET total_amount = total_amount + ? WHERE id = ?")
        ->execute([$totalAmount, $sessionId]);
    $pdo->prepare("UPDATE restaurant_tables SET status = 'terisi' WHERE id = ?")
        ->execute([$tableId]);

    $pdo->commit();
    echo json_encode([
        'success' => true,
        'order_number' => $orderNumber,
        'order_id' => $orderId,
        'message' => 'Pesanan berhasil dikirim ke resto!'
    ]);
} catch (InvalidArgumentException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Jazuva order API: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Pesanan belum dapat diproses. Silakan coba kembali.']);
}
