<?php
/**
 * JAZUVA - API Kitchen Display System
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = getDbConnection();
$method = $_SERVER['REQUEST_METHOD'];
requireApiRole(['admin', 'kitchen']);

// GET: Fetch current active kitchen queue
if ($method === 'GET') {
    // Fetch orders that have items for kitchen with status waiting or processing or ready
    $stmt = $pdo->query("SELECT oi.id as item_id, oi.order_id, oi.quantity, oi.notes as item_notes, 
                                oi.item_status, oi.created_at as item_time,
                                o.order_number, o.created_at as order_time,
                                t.table_number, m.name as menu_name
                         FROM order_items oi
                         JOIN orders o ON oi.order_id = o.id
                         JOIN restaurant_tables t ON o.table_id = t.id
                         JOIN menus m ON oi.menu_id = m.id
                         WHERE oi.station = 'kitchen' 
                           AND oi.item_status IN ('menunggu', 'diproses', 'siap')
                         ORDER BY oi.id ASC");
    $rawItems = $stmt->fetchAll();

    // Group items by order
    $ordersGrouped = [];
    $tableList = [];

    foreach ($rawItems as $row) {
        $oid = $row['order_id'];
        if (!isset($ordersGrouped[$oid])) {
            $ordersGrouped[$oid] = [
                'order_id'     => $oid,
                'order_number' => $row['order_number'],
                'short_code'   => '#' . substr($row['order_number'], -3),
                'table_number' => $row['table_number'],
                'created_at'   => $row['order_time'],
                'elapsed'      => timeElapsedString($row['order_time']),
                'overall_status' => $row['item_status'],
                'items'        => []
            ];
            $tableList[] = 'Meja ' . $row['table_number'];
        }

        // If any item is 'menunggu', group is considered 'menunggu'; if none, check 'diproses'
        if ($row['item_status'] === 'menunggu') {
            $ordersGrouped[$oid]['overall_status'] = 'menunggu';
        }

        $ordersGrouped[$oid]['items'][] = [
            'item_id'     => $row['item_id'],
            'menu_name'   => $row['menu_name'],
            'quantity'    => $row['quantity'],
            'notes'       => $row['item_notes'],
            'item_status' => $row['item_status']
        ];
    }

    $uniqueTables = array_unique($tableList);
    $tableSummaryText = !empty($uniqueTables) ? 'Pesanan dari ' . implode(', ', $uniqueTables) : 'Tidak ada antrean pesanan makanan';

    echo json_encode([
        'success' => true,
        'count'   => count($ordersGrouped),
        'orders'  => array_values($ordersGrouped),
        'tables_summary' => $tableSummaryText
    ]);
    exit;
}

// POST: Update status of kitchen order / items
if ($method === 'POST') {
    requireApiCsrfToken();
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $orderId = (int)($input['order_id'] ?? 0);
    $newStatus = trim($input['status'] ?? '');

    if (!$orderId || !in_array($newStatus, ['diproses', 'siap', 'selesai', 'dibatalkan'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Status atau ID pesanan tidak valid']);
        exit;
    }

    $currentUser = getCurrentUser();
    $userId = $currentUser['id'] ?? null;

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT id, item_status FROM order_items
                               WHERE order_id = ? AND station = 'kitchen' FOR UPDATE");
        $stmt->execute([$orderId]);
        $items = $stmt->fetchAll();
        if (!$items) {
            throw new InvalidArgumentException('Pesanan makanan tidak ditemukan.');
        }

        foreach ($items as $it) {
            $itemId = (int)$it['id'];
            $currentStatus = $it['item_status'];
            if ($currentStatus === $newStatus) {
                continue;
            }
            $transitions = [
                'menunggu' => ['diproses', 'dibatalkan'],
                'diproses' => ['siap', 'dibatalkan'],
                'siap' => ['selesai', 'dibatalkan']
            ];
            if (!in_array($newStatus, $transitions[$currentStatus] ?? [], true)) {
                throw new InvalidArgumentException('Perubahan status pesanan tidak diperbolehkan.');
            }

            $pdo->prepare("UPDATE order_items SET item_status = ? WHERE id = ?")->execute([$newStatus, $itemId]);

            if ($newStatus === 'diproses') {
                deductStockForOrderItem($itemId, $userId);
            } elseif ($newStatus === 'dibatalkan') {
                reverseStockForOrderItem($itemId, $userId);
            }
        }

        // Check whether the order is ready or all of its items were cancelled.
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) AS remaining,
                                           SUM(CASE WHEN item_status != 'dibatalkan' THEN 1 ELSE 0 END) AS active_items
                                    FROM order_items WHERE order_id = ?");
        $stmtCheck->execute([$orderId]);
        $orderProgress = $stmtCheck->fetch();
        $remaining = (int)$orderProgress['remaining'];
        $activeItems = (int)$orderProgress['active_items'];

        if ($activeItems === 0) {
            $pdo->prepare("UPDATE orders SET order_status = 'dibatalkan' WHERE id = ? AND order_status != 'selesai'")->execute([$orderId]);
        } elseif ($remaining === 0) {
            $pdo->prepare("UPDATE orders SET order_status = 'siap' WHERE id = ? AND order_status NOT IN ('selesai')")->execute([$orderId]);
        } else {
            $pdo->prepare("UPDATE orders SET order_status = 'diproses' WHERE id = ? AND order_status = 'menunggu'")->execute([$orderId]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Status pesanan makanan berhasil diperbarui!']);
        exit;

    } catch (InvalidArgumentException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Jazuva kitchen API: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Status pesanan gagal diperbarui. Silakan coba kembali.']);
        exit;
    }
}
