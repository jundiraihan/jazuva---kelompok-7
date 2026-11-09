<?php
/**
 * JAZUVA - Cashier Transaction History
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['admin', 'cashier']);

$pageTitle = 'Riwayat Transaksi Kasir';
$includePosCss = true;
require_once __DIR__ . '/../includes/header.php';

$pdo = getDbConnection();

$filterDate = $_GET['date'] ?? date('Y-m-d');
$search = trim($_GET['search'] ?? '');

$sql = "SELECT p.*, t.table_number, u.full_name as cashier_name 
        FROM payments p 
        LEFT JOIN table_sessions ts ON p.session_id = ts.id 
        LEFT JOIN restaurant_tables t ON ts.table_id = t.id 
        LEFT JOIN users u ON p.cashier_id = u.id 
        WHERE 1=1";
$params = [];

if (!empty($filterDate)) {
    $sql .= " AND DATE(p.created_at) = ?";
    $params[] = $filterDate;
}

if (!empty($search)) {
    $sql .= " AND (p.transaction_number LIKE ? OR t.table_number LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$sql .= " ORDER BY p.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Calculate total for filtered day
$totalDayRevenue = 0.00;
foreach ($transactions as $trx) {
    if ($trx['payment_status'] === 'lunas') {
        $totalDayRevenue += (float)$trx['total_bill'];
    }
}
?>

<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
  <div>
    <h2 style="font-size: 22px; font-weight: 800; color: var(--color-primary);">Riwayat Transaksi Pembayaran</h2>
    <p style="font-size: 13px; color: var(--color-text-muted);">Daftar seluruh pembayaran kasir yang tercatat di sistem.</p>
  </div>

  <a href="<?= BASE_URL ?>/cashier/dashboard.php" class="btn btn-secondary">
    <i class="fa-solid fa-cash-register"></i> Kembali ke POS Kasir
  </a>
</div>

<!-- Filter card -->
<div class="card" style="margin-bottom: 24px; padding: 18px 24px;">
  <form method="GET" action="" style="display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap;">
    <div style="flex: 1; min-width: 200px;">
      <label class="form-label">Tanggal Transaksi</label>
      <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($filterDate) ?>">
    </div>

    <div style="flex: 2; min-width: 240px;">
      <label class="form-label">Cari No. Transaksi / Meja</label>
      <input type="text" name="search" class="form-control" placeholder="Contoh: TRX2025... atau Meja 01" value="<?= htmlspecialchars($search) ?>">
    </div>

    <div>
      <button type="submit" class="btn btn-primary">
        <i class="fa-solid fa-filter"></i> Filter
      </button>
      <a href="<?= BASE_URL ?>/cashier/history.php" class="btn btn-outline">Reset</a>
    </div>
  </form>
</div>

<!-- Summary banner -->
<div style="display: flex; gap: 20px; margin-bottom: 24px;">
  <div class="card" style="flex: 1; background: #FAF5EE; border-color: #E8DDD2;">
    <div style="font-size: 13px; color: var(--color-text-muted); font-weight: 600;">Total Transaksi</div>
    <div style="font-size: 24px; font-weight: 800; color: var(--color-primary);"><?= count($transactions) ?> Transaksi</div>
  </div>
  <div class="card" style="flex: 1; background: #EAF8EE; border-color: #C3E6CB;">
    <div style="font-size: 13px; color: #1E7E34; font-weight: 600;">Total Pemasukan</div>
    <div style="font-size: 24px; font-weight: 800; color: #155724;"><?= formatRupiah($totalDayRevenue) ?></div>
  </div>
</div>

<!-- Transactions Table -->
<div class="card">
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>No. Transaksi</th>
          <th>Meja</th>
          <th>Waktu</th>
          <th>Metode</th>
          <th>Total Tagihan</th>
          <th>Uang Diterima</th>
          <th>Kembalian</th>
          <th>Kasir</th>
          <th style="text-align: center;">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($transactions)): ?>
          <tr>
            <td colspan="9" style="text-align: center; padding: 40px; color: var(--color-text-muted);">
              Tidak ada data transaksi yang ditemukan.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($transactions as $t): ?>
            <tr>
              <td><strong style="color: var(--color-primary);"><?= htmlspecialchars($t['transaction_number']) ?></strong></td>
              <td><span class="badge badge-menunggu" style="background:#8B5A2B;">Meja <?= htmlspecialchars($t['table_number'] ?? '01') ?></span></td>
              <td><?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></td>
              <td><span class="badge" style="background: #FAF5EE; color: #331B11; border: 1px solid #DCCBBF;"><?= strtoupper($t['payment_method']) ?></span></td>
              <td><strong><?= formatRupiah($t['total_bill']) ?></strong></td>
              <td><?= formatRupiah($t['amount_paid']) ?></td>
              <td><?= formatRupiah($t['change_amount']) ?></td>
              <td><?= htmlspecialchars($t['cashier_name'] ?? 'Kasir') ?></td>
              <td style="text-align: center;">
                <a href="<?= BASE_URL ?>/cashier/receipt.php?trx=<?= urlencode($t['transaction_number']) ?>" class="btn btn-secondary btn-sm" target="_blank">
                  <i class="fa-solid fa-print"></i> Struk
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

