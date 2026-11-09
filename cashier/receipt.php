<?php
/**
 * JAZUVA - Thermal Receipt View & Print
 * Matches Prototype Thermal Receipt Layout
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['admin', 'cashier']);

$trxNumber = trim($_GET['trx'] ?? '');
$pdo = getDbConnection();

// Fetch payment details
$stmt = $pdo->prepare("SELECT p.*, ts.table_id, ts.customer_name, t.table_number, u.full_name as cashier_name 
                       FROM payments p 
                       LEFT JOIN table_sessions ts ON p.session_id = ts.id 
                       LEFT JOIN restaurant_tables t ON ts.table_id = t.id 
                       LEFT JOIN users u ON p.cashier_id = u.id 
                       WHERE p.transaction_number = ? LIMIT 1");
$stmt->execute([$trxNumber]);
$payment = $stmt->fetch();

if (!$payment) {
    die("<h3>Data transaksi tidak ditemukan.</h3><p><a href='" . BASE_URL . "/cashier/dashboard.php'>Kembali ke Kasir POS</a></p>");
}

// Fetch items for this session or order
$stmtItems = $pdo->prepare("SELECT oi.*, m.name as menu_name 
                            FROM order_items oi 
                            JOIN orders o ON oi.order_id = o.id 
                            JOIN menus m ON oi.menu_id = m.id 
                            WHERE o.session_id = ? AND oi.item_status != 'dibatalkan'");
$stmtItems->execute([$payment['session_id']]);
$items = $stmtItems->fetchAll();

// If empty by session_id, fallback to order_id
if (empty($items) && !empty($payment['order_id'])) {
    $stmtItems2 = $pdo->prepare("SELECT oi.*, m.name as menu_name 
                                 FROM order_items oi 
                                 JOIN menus m ON oi.menu_id = m.id 
                                 WHERE oi.order_id = ? AND oi.item_status != 'dibatalkan'");
    $stmtItems2->execute([$payment['order_id']]);
    $items = $stmtItems2->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Struk Pembayaran <?= htmlspecialchars($payment['transaction_number']) ?> - Jazuva</title>
  
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/pos.css">
  
  <style>
    body {
      background: #F4EDE4;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 30px 16px;
    }
    .print-actions {
      display: flex;
      gap: 12px;
      margin-bottom: 24px;
    }
    @media print {
      .print-actions, .no-print {
        display: none !important;
      }
      body {
        background: transparent;
        padding: 0;
      }
    }
  </style>
</head>
<body>

  <!-- Screen Action Buttons -->
  <div class="print-actions no-print">
    <button type="button" class="btn btn-primary" onclick="window.print()">
      <i class="fa-solid fa-print"></i> Cetak Struk
    </button>
    <a href="<?= BASE_URL ?>/cashier/dashboard.php" class="btn btn-secondary">
      <i class="fa-solid fa-arrow-left"></i> Kembali ke Kasir POS
    </a>
    <a href="<?= BASE_URL ?>/cashier/history.php" class="btn btn-outline">
      <i class="fa-solid fa-clock-rotate-left"></i> Riwayat
    </a>
  </div>

  <!-- Thermal Receipt Structure Matching Prototype -->
  <div class="thermal-receipt">
    <div class="receipt-header">
      <div class="receipt-logo-icon">☕</div>
      <div class="receipt-brand">Jazuva</div>
      <div style="font-size: 11px; color: #555;">Restaurant &amp; Coffee Shop</div>
      <div class="receipt-title">Struk Pembayaran</div>
    </div>

    <div class="receipt-divider"></div>

    <div class="receipt-meta">
      <div class="receipt-meta-row">
        <span>No. Transaksi</span>
        <span>: <?= htmlspecialchars($payment['transaction_number']) ?></span>
      </div>
      <div class="receipt-meta-row">
        <span>Meja</span>
        <span>: <?= htmlspecialchars($payment['table_number'] ?? '01') ?></span>
      </div>
      <div class="receipt-meta-row">
        <span>Waktu</span>
        <span>: <?= date('d/m/Y H:i', strtotime($payment['created_at'])) ?></span>
      </div>
      <div class="receipt-meta-row">
        <span>Kasir</span>
        <span>: <?= htmlspecialchars($payment['cashier_name'] ?? 'Kasir') ?></span>
      </div>
    </div>

    <div class="receipt-divider"></div>

    <!-- Items list -->
    <div class="receipt-items">
      <?php foreach ($items as $item): ?>
        <div class="receipt-item-line">
          <span><?= htmlspecialchars($item['menu_name']) ?> x<?= $item['quantity'] ?></span>
          <span><?= formatRupiah($item['subtotal']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="receipt-divider"></div>

    <!-- Totals -->
    <div class="receipt-totals">
      <div class="receipt-total-row">
        <span>Subtotal</span>
        <span>: <?= formatRupiah($payment['subtotal']) ?></span>
      </div>
      <div class="receipt-total-row">
        <span>Service Charge</span>
        <span>: <?= formatRupiah($payment['service_charge_amount']) ?></span>
      </div>
      <div class="receipt-total-row">
        <span>PB1</span>
        <span>: <?= formatRupiah($payment['tax_amount']) ?></span>
      </div>
      <div class="receipt-total-row">
        <strong>Total Tagihan</strong>
        <strong>: <?= formatRupiah($payment['total_bill']) ?></strong>
      </div>
      <div class="receipt-total-row">
        <span>Metode Bayar</span>
        <span>: <?= strtoupper($payment['payment_method']) ?></span>
      </div>
      <div class="receipt-total-row">
        <span>Pembayaran</span>
        <span>: <?= formatRupiah($payment['amount_paid']) ?></span>
      </div>
      <div class="receipt-total-row">
        <span>Kembalian</span>
        <span>: <?= formatRupiah($payment['change_amount']) ?></span>
      </div>
    </div>

    <div class="receipt-divider"></div>

    <div class="receipt-footer">
      <div>Terima Kasih</div>
      <div>atas kunjungan Anda!</div>
      <div style="font-size: 14px; margin-top: 4px;">❤️</div>
    </div>
  </div>

</body>
</html>
