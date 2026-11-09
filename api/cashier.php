<?php
/**
 * JAZUVA - API Cashier POS
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/midtrans.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = getDbConnection();
$method = $_SERVER['REQUEST_METHOD'];
requireApiRole(['admin', 'cashier']);

// GET: Fetch active orders per table and detail for selected table
if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list_tables';

    if ($action === 'qris_status') {
        $transactionNumber = trim($_GET['trx'] ?? '');
        if (!preg_match('/^TRX\d{8}[A-F0-9]{6}$/', $transactionNumber)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Nomor transaksi QRIS tidak valid.']);
            exit;
        }

        if (!isMidtransConfigured()) {
            http_response_code(503);
            echo json_encode(['success' => false, 'message' => 'Kunci Server Midtrans belum dikonfigurasi.']);
            exit;
        }

        try {
            $stmtPayment = $pdo->prepare("SELECT * FROM payments
                                          WHERE transaction_number = ? AND payment_method = 'qris'
                                          LIMIT 1");
            $stmtPayment->execute([$transactionNumber]);
            $payment = $stmtPayment->fetch();
            if (!$payment) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Transaksi QRIS tidak ditemukan.']);
                exit;
            }

            if ($payment['payment_status'] === 'lunas' || $payment['payment_status'] === 'batal') {
                echo json_encode([
                    'success' => true,
                    'status' => $payment['payment_status'],
                    'transaction_number' => $transactionNumber
                ]);
                exit;
            }

            $midtransStatus = midtransRequest(
                'GET',
                '/v2/' . rawurlencode($transactionNumber) . '/status'
            );
            $paymentStatus = applyMidtransQrisStatus($pdo, $payment, $midtransStatus);
            echo json_encode([
                'success' => true,
                'status' => $paymentStatus,
                'transaction_number' => $transactionNumber
            ]);
        } catch (Throwable $e) {
            error_log('Jazuva Midtrans status check: ' . $e->getMessage());
            http_response_code(502);
            echo json_encode(['success' => false, 'message' => 'Status QRIS belum dapat diverifikasi. Coba kembali.']);
        }
        exit;
    }

    if ($action === 'list_tables') {
        // Fetch all active table sessions or orders waiting for payment
        $stmt = $pdo->query("SELECT t.id as table_id, t.table_number, t.status as table_status,
                                    ts.id as session_id, ts.customer_name,
                                    COALESCE(SUM(oi.subtotal), 0) as total_bill,
                                    GROUP_CONCAT(CONCAT(m.name, ' x', oi.quantity) SEPARATOR ', ') as summary_items
                             FROM restaurant_tables t
                             JOIN table_sessions ts ON t.id = ts.table_id AND ts.status = 'active'
                             JOIN orders o ON ts.id = o.session_id AND o.order_status NOT IN ('selesai', 'dibatalkan')
                             JOIN order_items oi ON o.id = oi.order_id AND oi.item_status != 'dibatalkan'
                             JOIN menus m ON oi.menu_id = m.id
                             GROUP BY t.id, ts.id
                             ORDER BY t.table_number ASC");
        $tablesWithOrders = $stmt->fetchAll();
        foreach ($tablesWithOrders as &$table) {
            $billTotals = calculateBillTotals((float)$table['total_bill']);
            $table['total_bill'] = $billTotals['total'];
        }
        unset($table);

        echo json_encode(['success' => true, 'tables' => $tablesWithOrders]);
        exit;
    }

    if ($action === 'table_detail') {
        $tableId = (int)($_GET['table_id'] ?? 0);

        // Fetch active session and all items across orders for this table session
        $stmtSession = $pdo->prepare("SELECT ts.*, t.table_number 
                                      FROM table_sessions ts 
                                      JOIN restaurant_tables t ON ts.table_id = t.id 
                                      WHERE ts.table_id = ? AND ts.status = 'active' LIMIT 1");
        $stmtSession->execute([$tableId]);
        $session = $stmtSession->fetch();

        if (!$session) {
            echo json_encode(['success' => false, 'message' => 'Tidak ada sesi aktif pada meja ini']);
            exit;
        }

        // Get all order items for this table session
        $stmtItems = $pdo->prepare("SELECT oi.*, m.name as menu_name, o.order_number 
                                    FROM order_items oi
                                    JOIN orders o ON oi.order_id = o.id
                                    JOIN menus m ON oi.menu_id = m.id
                                    WHERE o.session_id = ? AND oi.item_status != 'dibatalkan'
                                    ORDER BY oi.id ASC");
        $stmtItems->execute([$session['id']]);
        $items = $stmtItems->fetchAll();

        $subtotal = 0.00;
        foreach ($items as $item) {
            $subtotal += (float)$item['subtotal'];
        }

        $billTotals = calculateBillTotals($subtotal);
        echo json_encode([
            'success' => true,
            'session' => $session,
            'items'   => $items,
            'subtotal' => $billTotals['subtotal'],
            'service_charge_amount' => $billTotals['service_charge_amount'],
            'tax_amount' => $billTotals['tax_amount'],
            'total' => $billTotals['total']
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Action invalid']);
    exit;
}

// POST: Process Payment
if ($method === 'POST') {
    requireApiCsrfToken();
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $action = $input['action'] ?? '';

    if ($action === 'create_qris') {
        $sessionId = filter_var($input['session_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$sessionId || !isMidtransConfigured()) {
            http_response_code($sessionId ? 503 : 400);
            echo json_encode([
                'success' => false,
                'message' => $sessionId
                    ? 'Kunci Server Midtrans belum dikonfigurasi.'
                    : 'Sesi meja tidak valid.'
            ]);
            exit;
        }

        try {
            $pdo->beginTransaction();
            $stmtSession = $pdo->prepare("SELECT * FROM table_sessions WHERE id = ? FOR UPDATE");
            $stmtSession->execute([$sessionId]);
            $session = $stmtSession->fetch();
            if (!$session || $session['status'] !== 'active') {
                throw new InvalidArgumentException('Sesi meja tidak aktif.');
            }

            $stmtPending = $pdo->prepare("SELECT *
                                          FROM payments
                                          WHERE session_id = ? AND payment_method = 'qris'
                                            AND payment_status = 'menunggu'
                                          ORDER BY id DESC LIMIT 1");
            $stmtPending->execute([$sessionId]);
            $pendingPayment = $stmtPending->fetch();
            if ($pendingPayment && !empty($pendingPayment['qris_qr_url'])) {
                if (!isTrustedMidtransQrUrl((string)$pendingPayment['qris_qr_url'])) {
                    throw new RuntimeException('URL QRIS tersimpan tidak berasal dari Midtrans.');
                }
                $pdo->commit();
                echo json_encode([
                    'success' => true,
                    'transaction_number' => $pendingPayment['transaction_number'],
                    'total' => (float)$pendingPayment['total_bill'],
                    'qr_url' => $pendingPayment['qris_qr_url']
                ]);
                exit;
            }
            if ($pendingPayment) {
                $pdo->commit();
                $midtransStatus = midtransRequest(
                    'GET',
                    '/v2/' . rawurlencode($pendingPayment['transaction_number']) . '/status'
                );
                $paymentStatus = applyMidtransQrisStatus($pdo, $pendingPayment, $midtransStatus);
                if ($paymentStatus === 'lunas') {
                    echo json_encode([
                        'success' => true,
                        'status' => 'lunas',
                        'transaction_number' => $pendingPayment['transaction_number']
                    ]);
                    exit;
                }
                if ($paymentStatus === 'batal') {
                    http_response_code(409);
                    echo json_encode([
                        'success' => false,
                        'message' => 'QRIS sebelumnya kedaluwarsa. Muat ulang meja lalu buat QR baru.'
                    ]);
                    exit;
                }

                $transactionId = (string)($midtransStatus['transaction_id'] ?? '');
                $qrUrl = midtransQrCodeUrl($transactionId);
                if (!isTrustedMidtransQrUrl($qrUrl)) {
                    throw new RuntimeException('URL QRIS Midtrans hasil pemulihan tidak valid.');
                }
                $pdo->prepare("UPDATE payments
                               SET midtrans_transaction_id = ?, qris_qr_url = ?
                               WHERE id = ? AND payment_status = 'menunggu'")
                    ->execute([$transactionId, $qrUrl, (int)$pendingPayment['id']]);
                echo json_encode([
                    'success' => true,
                    'transaction_number' => $pendingPayment['transaction_number'],
                    'total' => (float)$pendingPayment['total_bill'],
                    'qr_url' => $qrUrl
                ]);
                exit;
            }

            $stmtBill = $pdo->prepare("SELECT COALESCE(SUM(oi.subtotal), 0)
                                       FROM orders o
                                       JOIN order_items oi ON oi.order_id = o.id
                                       WHERE o.session_id = ?
                                         AND o.order_status != 'dibatalkan'
                                         AND oi.item_status != 'dibatalkan'");
            $stmtBill->execute([$sessionId]);
            $subtotal = (float)$stmtBill->fetchColumn();
            $billTotals = calculateBillTotals($subtotal);
            if ($billTotals['total'] <= 0) {
                throw new InvalidArgumentException('Tidak ada item aktif pada tagihan meja ini.');
            }
            if (abs($billTotals['total'] - round($billTotals['total'])) > 0.00001) {
                throw new InvalidArgumentException('Total QRIS harus dalam nominal rupiah bulat.');
            }

            $datePrefix = date('Ymd');
            $transactionNumber = 'TRX' . $datePrefix . strtoupper(bin2hex(random_bytes(3)));
            $stmtOrder = $pdo->prepare("SELECT id FROM orders WHERE session_id = ? ORDER BY id ASC LIMIT 1");
            $stmtOrder->execute([$sessionId]);
            $primaryOrderId = $stmtOrder->fetchColumn() ?: null;

            $stmtPayment = $pdo->prepare("INSERT INTO payments
                (order_id, session_id, transaction_number, payment_method, subtotal, service_charge_amount,
                 tax_amount, total_bill, amount_paid, change_amount, payment_status, cashier_id, notes)
                VALUES (?, ?, ?, 'qris', ?, ?, ?, ?, 0, 0, 'menunggu', ?, 'Menunggu pembayaran QRIS Midtrans.')");
            $stmtPayment->execute([
                $primaryOrderId,
                $sessionId,
                $transactionNumber,
                $billTotals['subtotal'],
                $billTotals['service_charge_amount'],
                $billTotals['tax_amount'],
                $billTotals['total'],
                getCurrentUser()['id'] ?? null
            ]);
            $paymentId = (int)$pdo->lastInsertId();
            $pdo->commit();

            $charge = midtransRequest('POST', '/v2/charge', [
                'payment_type' => 'gopay',
                'transaction_details' => [
                    'order_id' => $transactionNumber,
                    'gross_amount' => (int)$billTotals['total']
                ]
            ]);
            $qrUrl = null;
            foreach ($charge['actions'] ?? [] as $actionDetail) {
                if (($actionDetail['name'] ?? '') === 'generate-qr-code') {
                    $candidateUrl = (string)($actionDetail['url'] ?? '');
                    if (isTrustedMidtransQrUrl($candidateUrl)) {
                        $qrUrl = $candidateUrl;
                    }
                    break;
                }
            }
            if (
                $qrUrl === null ||
                ($charge['order_id'] ?? '') !== $transactionNumber ||
                !isset($charge['transaction_id'])
            ) {
                throw new RuntimeException('Respons QRIS Midtrans tidak lengkap atau tidak valid.');
            }

            $pdo->prepare("UPDATE payments
                           SET midtrans_transaction_id = ?, qris_qr_url = ?
                           WHERE id = ? AND payment_status = 'menunggu'")
                ->execute([
                    (string)$charge['transaction_id'],
                    $qrUrl,
                    $paymentId
                ]);

            echo json_encode([
                'success' => true,
                'transaction_number' => $transactionNumber,
                'total' => $billTotals['total'],
                'qr_url' => $qrUrl
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
            if (
                isset($paymentId) &&
                $e instanceof MidtransApiException &&
                in_array($e->httpStatus, [400, 401, 403, 404, 422], true)
            ) {
                try {
                    $pdo->prepare("UPDATE payments
                                   SET payment_status = 'batal', notes = ?
                                   WHERE id = ? AND payment_status = 'menunggu'")
                        ->execute([
                            'Permintaan QRIS ditolak Midtrans (HTTP ' . $e->httpStatus . ').',
                            $paymentId
                        ]);
                } catch (PDOException $updateError) {
                    error_log('Jazuva Midtrans rejected-payment cleanup: ' . $updateError->getMessage());
                }
            }
            error_log('Jazuva Midtrans QR creation: ' . $e->getMessage());
            http_response_code(502);
            echo json_encode(['success' => false, 'message' => 'QRIS belum dapat dibuat. Periksa konfigurasi Midtrans dan coba kembali.']);
        }
        exit;
    }

    if ($action === 'process_payment') {
        $sessionId = (int)($input['session_id'] ?? 0);
        $paymentMethod = $input['payment_method'] ?? '';
        $amountPaid = is_numeric($input['amount_paid'] ?? null) ? (float)$input['amount_paid'] : 0.0;

        if (!$sessionId || !in_array($paymentMethod, ['tunai', 'qris'], true) || $amountPaid < 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Data tagihan tidak valid']);
            exit;
        }

        if ($paymentMethod !== 'tunai') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Pembayaran QRIS harus menggunakan QR dinamis Midtrans.']);
            exit;
        }

        $currentUser = getCurrentUser();
        $cashierId = $currentUser['id'] ?? null;

        try {
            $pdo->beginTransaction();

            // Fetch session & table
            $stmtSess = $pdo->prepare("SELECT * FROM table_sessions WHERE id = ? FOR UPDATE");
            $stmtSess->execute([$sessionId]);
            $session = $stmtSess->fetch();

            if (!$session || $session['status'] !== 'active') {
                throw new Exception("Sesi meja tidak ditemukan.");
            }

            $stmtPendingQris = $pdo->prepare("SELECT id FROM payments
                                             WHERE session_id = ? AND payment_method = 'qris'
                                               AND payment_status = 'menunggu'
                                             LIMIT 1 FOR UPDATE");
            $stmtPendingQris->execute([$sessionId]);
            if ($stmtPendingQris->fetch()) {
                throw new InvalidArgumentException('Selesaikan atau batalkan transaksi QRIS yang masih menunggu sebelum menerima pembayaran tunai.');
            }

            $tableId = (int)$session['table_id'];

            $stmtBill = $pdo->prepare("SELECT COALESCE(SUM(oi.subtotal), 0)
                                       FROM orders o
                                       JOIN order_items oi ON oi.order_id = o.id
                                       WHERE o.session_id = ?
                                         AND o.order_status != 'dibatalkan'
                                         AND oi.item_status != 'dibatalkan'");
            $stmtBill->execute([$sessionId]);
            $subtotal = (float)$stmtBill->fetchColumn();
            $billTotals = calculateBillTotals($subtotal);
            $totalBill = $billTotals['total'];
            if ($totalBill <= 0) {
                throw new InvalidArgumentException('Tidak ada item aktif pada tagihan meja ini.');
            }
            if ($paymentMethod === 'tunai' && $amountPaid < $totalBill) {
                throw new InvalidArgumentException('Jumlah uang tunai kurang dari total tagihan.');
            }
            $changeAmount = $paymentMethod === 'tunai' ? $amountPaid - $totalBill : 0.00;

            $trxNumber = 'TRX' . date('Ymd') . strtoupper(bin2hex(random_bytes(3)));

            // Find primary order of session
            $stmtOrd = $pdo->prepare("SELECT id FROM orders WHERE session_id = ? ORDER BY id ASC LIMIT 1");
            $stmtOrd->execute([$sessionId]);
            $primaryOrderId = $stmtOrd->fetchColumn() ?: null;

            // Insert into payments
            $stmtPay = $pdo->prepare("INSERT INTO payments 
                (order_id, session_id, transaction_number, payment_method, subtotal, service_charge_amount, tax_amount, total_bill, amount_paid, change_amount, payment_status, cashier_id, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'lunas', ?, ?)");
            $stmtPay->execute([
                $primaryOrderId,
                $sessionId,
                $trxNumber,
                $paymentMethod,
                $billTotals['subtotal'],
                $billTotals['service_charge_amount'],
                $billTotals['tax_amount'],
                $totalBill,
                $amountPaid,
                $changeAmount,
                $cashierId,
                null
            ]);
            $paymentId = (int)$pdo->lastInsertId();

            // Mark all orders in session as 'selesai'
            $pdo->prepare("UPDATE orders SET order_status = 'selesai' WHERE session_id = ?")->execute([$sessionId]);

            // Mark all items in session as 'selesai'
            $pdo->prepare("UPDATE order_items oi 
                           JOIN orders o ON oi.order_id = o.id 
                           SET oi.item_status = 'selesai' 
                           WHERE o.session_id = ? AND oi.item_status != 'dibatalkan'")->execute([$sessionId]);

            // Close session
            $pdo->prepare("UPDATE table_sessions SET status = 'completed', end_time = NOW() WHERE id = ?")->execute([$sessionId]);

            // Reset table status to 'tersedia'
            $pdo->prepare("UPDATE restaurant_tables SET status = 'tersedia' WHERE id = ?")->execute([$tableId]);

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'transaction_number' => $trxNumber,
                'payment_id' => $paymentId,
                'message' => 'Pembayaran berhasil diproses dan dicatat!'
            ]);
            exit;

        } catch (InvalidArgumentException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Jazuva cashier API: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Pembayaran gagal diproses. Silakan coba kembali.']);
            exit;
        }
    }

    echo json_encode(['success' => false, 'message' => 'Action tidak dikenali']);
    exit;
}
