<?php
/**
 * JAZUVA - Helper Functions & Business Logic
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Core/Security.php';

/**
 * Format number to Indonesian Rupiah
 */
function formatRupiah($amount): string {
    return 'Rp ' . number_format((float)$amount, 0, ',', '.');
}

/**
 * Sanitize user input
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return Security::escape(trim((string)$data));
}

/**
 * Set flash alert message
 */
function setFlash(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash_' . $type] = $message;
}

/**
 * Get and clear flash message
 */
function getFlash(string $type): ?string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (isset($_SESSION['flash_' . $type])) {
        $msg = $_SESSION['flash_' . $type];
        unset($_SESSION['flash_' . $type]);
        return $msg;
    }
    return null;
}

/**
 * Get setting value from database
 */
function getSetting(string $key, string $default = ''): string {
    static $settings = null;
    if ($settings === null) {
        $pdo = getDbConnection();
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
    }
    return $settings[$key] ?? $default;
}

function calculateBillTotals(float $subtotal): array {
    if (!is_finite($subtotal) || $subtotal < 0) {
        throw new InvalidArgumentException('Subtotal tagihan tidak valid.');
    }

    $taxPercentage = getSetting('tax_percentage', '0');
    $serviceChargePercentage = getSetting('service_charge', '0');
    if (
        !is_numeric($taxPercentage) ||
        !is_numeric($serviceChargePercentage) ||
        !is_finite((float)$taxPercentage) ||
        !is_finite((float)$serviceChargePercentage) ||
        (float)$taxPercentage < 0 ||
        (float)$taxPercentage > 100 ||
        (float)$serviceChargePercentage < 0 ||
        (float)$serviceChargePercentage > 100
    ) {
        throw new RuntimeException('Persentase pajak atau service charge pada pengaturan tidak valid.');
    }

    $serviceChargeAmount = round($subtotal * (float)$serviceChargePercentage / 100, 2);
    $taxAmount = round(($subtotal + $serviceChargeAmount) * (float)$taxPercentage / 100, 2);

    return [
        'subtotal' => round($subtotal, 2),
        'service_charge_amount' => $serviceChargeAmount,
        'tax_amount' => $taxAmount,
        'total' => round($subtotal + $serviceChargeAmount + $taxAmount, 2),
    ];
}

/**
 * Relative time string (e.g. "2 mnt", "1 jam", "baru saja") matching prototype
 */
function timeElapsedString($datetime): string {
    $timestamp = strtotime($datetime);
    if (!$timestamp) return 'baru saja';
    $difference = time() - $timestamp;

    if ($difference < 60) {
        return 'baru saja';
    } elseif ($difference < 3600) {
        return floor($difference / 60) . ' mnt';
    } elseif ($difference < 86400) {
        return floor($difference / 3600) . ' jam';
    } else {
        return floor($difference / 86400) . ' hari';
    }
}

/**
 * Get stock status badge and color
 */
function getStockStatus(float $stock, float $minStock): array {
    if ($stock <= 0) {
        return ['status' => 'Habis', 'class' => 'status-kritis', 'color' => '#E74C3C', 'dot' => 'kritis'];
    } elseif ($stock <= $minStock * 0.5) {
        return ['status' => 'Kritis', 'class' => 'status-kritis', 'color' => '#E74C3C', 'dot' => 'kritis'];
    } elseif ($stock <= $minStock) {
        return ['status' => 'Menipis', 'class' => 'status-menipis', 'color' => '#E67E22', 'dot' => 'menipis'];
    } else {
        return ['status' => 'Aman', 'class' => 'status-aman', 'color' => '#27AE60', 'dot' => 'aman'];
    }
}

/**
 * Get table by table number
 */
function getTableByNumber(string $tableNumber): ?array {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT * FROM restaurant_tables WHERE table_number = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$tableNumber]);
    return $stmt->fetch() ?: null;
}

/**
 * Get active session for table or create one
 */
function getOrCreateTableSession(int $tableId, string $customerName = 'Pelanggan'): array {
    $pdo = getDbConnection();
    
    // Find active session
    $stmt = $pdo->prepare("SELECT * FROM table_sessions WHERE table_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$tableId]);
    $session = $stmt->fetch();
    
    if (!$session) {
        $token = 'SESS-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
        $stmtInsert = $pdo->prepare("INSERT INTO table_sessions (table_id, session_token, customer_name, status) VALUES (?, ?, ?, 'active')");
        $stmtInsert->execute([$tableId, $token, $customerName]);
        $sessionId = (int)$pdo->lastInsertId();
        
        // Update table status to 'terisi'
        $pdo->prepare("UPDATE restaurant_tables SET status = 'terisi' WHERE id = ?")->execute([$tableId]);
        
        $session = [
            'id' => $sessionId,
            'table_id' => $tableId,
            'session_token' => $token,
            'customer_name' => $customerName,
            'status' => 'active',
            'total_amount' => 0.00
        ];
    }
    return $session;
}

/**
 * Deduct stock for an order item based on recipe
 * CRITICAL RULE:
 * - Only runs when order is confirmed to 'diproses'
 * - Single item can NEVER be deducted twice (checked via stock_deducted flag)
 * - Prevents negative stock
 * - Records audit trail in stock_movements
 */
function deductStockForOrderItem(int $orderItemId, ?int $userId = null): bool {
    $pdo = getDbConnection();

    // Check item and deduction flag
    $stmt = $pdo->prepare("SELECT oi.*, m.name as menu_name 
                           FROM order_items oi 
                           JOIN menus m ON oi.menu_id = m.id 
                           WHERE oi.id = ? FOR UPDATE");
    $stmt->execute([$orderItemId]);
    $item = $stmt->fetch();

    if (!$item || (int)$item['stock_deducted'] === 1) {
        return false; // Already deducted or item not found
    }

    $menuId = (int)$item['menu_id'];
    $quantity = (int)$item['quantity'];

    // Find recipe
    $stmtRecipe = $pdo->prepare("SELECT id FROM recipes WHERE menu_id = ?");
    $stmtRecipe->execute([$menuId]);
    $recipe = $stmtRecipe->fetch();

    if (!$recipe) {
        // No recipe defined; mark as deducted so we don't re-process
        $pdo->prepare("UPDATE order_items SET stock_deducted = 1 WHERE id = ?")->execute([$orderItemId]);
        return true;
    }

    $recipeId = (int)$recipe['id'];

    // Lock every ingredient before validating so concurrent tickets cannot overspend stock.
    $stmtDetails = $pdo->prepare("SELECT rd.*, i.name as ingredient_name, i.stock 
                                  FROM recipe_details rd 
                                  JOIN ingredients i ON rd.ingredient_id = i.id 
                                  WHERE rd.recipe_id = ? ORDER BY i.id FOR UPDATE");
    $stmtDetails->execute([$recipeId]);
    $ingredients = $stmtDetails->fetchAll();

    foreach ($ingredients as $ing) {
        $needed = (float)$ing['amount_required'] * $quantity;
        $ingredientId = (int)$ing['ingredient_id'];
        $currentStock = (float)$ing['stock'];
        if ($currentStock < $needed) {
            throw new RuntimeException(
                "Stok {$ing['ingredient_name']} tidak cukup untuk memproses {$item['menu_name']}."
            );
        }
    }

    foreach ($ingredients as $ing) {
        $needed = (float)$ing['amount_required'] * $quantity;
        $ingredientId = (int)$ing['ingredient_id'];
        $currentStock = (float)$ing['stock'];
        $newStock = $currentStock - $needed;

        $stmtUp = $pdo->prepare("UPDATE ingredients SET stock = stock - ? WHERE id = ? AND stock >= ?");
        $stmtUp->execute([$needed, $ingredientId, $needed]);
        if ($stmtUp->rowCount() !== 1) {
            throw new RuntimeException("Stok bahan berubah saat pesanan diproses. Silakan coba kembali.");
        }

        // Insert stock movement history
        $stmtHist = $pdo->prepare("INSERT INTO stock_movements 
            (ingredient_id, movement_type, quantity, previous_stock, current_stock, reference_id, reference_type, notes, created_by) 
            VALUES (?, 'pesanan', ?, ?, ?, ?, 'order_item', ?, ?)");
        $notes = "Pesanan {$item['menu_name']} (x{$quantity}) [Item #{$orderItemId}]";
        $stmtHist->execute([
            $ingredientId,
            $needed,
            $currentStock,
            $newStock,
            $orderItemId,
            $notes,
            $userId
        ]);
    }

    // Mark as deducted
    $pdo->prepare("UPDATE order_items SET stock_deducted = 1 WHERE id = ?")->execute([$orderItemId]);
    return true;
}

/**
 * Reverse stock deduction if an order item is cancelled
 * Prevents phantom stock loss
 */
function reverseStockForOrderItem(int $orderItemId, ?int $userId = null): bool {
    $pdo = getDbConnection();

    $stmt = $pdo->prepare("SELECT oi.*, m.name as menu_name 
                           FROM order_items oi 
                           JOIN menus m ON oi.menu_id = m.id 
                           WHERE oi.id = ? FOR UPDATE");
    $stmt->execute([$orderItemId]);
    $item = $stmt->fetch();

    if (!$item || (int)$item['stock_deducted'] === 0) {
        return false; // Was never deducted
    }

    $stmtMovements = $pdo->prepare("SELECT ingredient_id, quantity
                                    FROM stock_movements
                                    WHERE reference_type = 'order_item'
                                      AND reference_id = ?
                                      AND movement_type = 'pesanan'
                                    ORDER BY id ASC");
    $stmtMovements->execute([$orderItemId]);
    $movements = $stmtMovements->fetchAll();

    foreach ($movements as $movement) {
        $ingredientId = (int)$movement['ingredient_id'];
        $returnAmount = (float)$movement['quantity'];
        $stmtIngredient = $pdo->prepare("SELECT stock FROM ingredients WHERE id = ? FOR UPDATE");
        $stmtIngredient->execute([$ingredientId]);
        $currentStock = $stmtIngredient->fetchColumn();
        if ($currentStock === false) {
            throw new RuntimeException("Bahan untuk pembatalan pesanan tidak ditemukan.");
        }
        $currentStock = (float)$currentStock;
        $newStock = $currentStock + $returnAmount;

        $pdo->prepare("UPDATE ingredients SET stock = stock + ? WHERE id = ?")
            ->execute([$returnAmount, $ingredientId]);

        $stmtHist = $pdo->prepare("INSERT INTO stock_movements 
            (ingredient_id, movement_type, quantity, previous_stock, current_stock, reference_id, reference_type, notes, created_by) 
            VALUES (?, 'pembatalan', ?, ?, ?, ?, 'order_item', ?, ?)");
        $notes = "Pembatalan Pesanan {$item['menu_name']} [Item #{$orderItemId}]";
        $stmtHist->execute([
            $ingredientId,
            $returnAmount,
            $currentStock,
            $newStock,
            $orderItemId,
            $notes,
            $userId
        ]);
    }

    $pdo->prepare("UPDATE order_items SET stock_deducted = 0 WHERE id = ?")->execute([$orderItemId]);
    return true;
}
