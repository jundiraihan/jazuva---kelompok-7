<?php
/**
 * JAZUVA - Reports & Analytics (Laporan)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['admin']);

$pdo = getDbConnection();

$defaultStartDate = date('Y-m-01');
$defaultEndDate = date('Y-m-d');
$startDate = $_GET['start_date'] ?? $defaultStartDate;
$endDate = $_GET['end_date'] ?? $defaultEndDate;
$reportType = $_GET['type'] ?? 'sales';
$dateError = null;
$isValidDate = static function ($date): bool {
    if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)) {
        return false;
    }

    $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsedDate !== false && $parsedDate->format('Y-m-d') === $date;
};

if (!$isValidDate($startDate) || !$isValidDate($endDate)) {
    $dateError = 'Format tanggal tidak valid. Pilih tanggal mulai dan akhir yang benar.';
    $startDate = $defaultStartDate;
    $endDate = $defaultEndDate;
} elseif ($startDate > $endDate) {
    $dateError = 'Tanggal mulai tidak boleh melewati tanggal akhir.';
}

$rangeStart = $dateError ? null : $startDate . ' 00:00:00';
$rangeEnd = $dateError ? null : $endDate . ' 00:00:00';

// Export CSV handler
if (!$dateError && isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=laporan_jazuva_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['No', 'No. Transaksi', 'Tanggal', 'Meja', 'Metode Bayar', 'Total Tagihan', 'Uang Diterima', 'Kembalian', 'Status']);

    $stmtExp = $pdo->prepare("SELECT p.*, t.table_number 
                              FROM payments p 
                              LEFT JOIN table_sessions ts ON p.session_id = ts.id 
                              LEFT JOIN restaurant_tables t ON ts.table_id = t.id 
                              WHERE p.created_at >= ? AND p.created_at < DATE_ADD(?, INTERVAL 1 DAY)
                                AND p.payment_status = 'lunas'
                              ORDER BY p.id ASC");
    $stmtExp->execute([$rangeStart, $rangeEnd]);
    $no = 1;
    while ($row = $stmtExp->fetch()) {
        fputcsv($output, [
            $no++,
            $row['transaction_number'],
            $row['created_at'],
            'Meja ' . ($row['table_number'] ?? '01'),
            strtoupper($row['payment_method']),
            $row['total_bill'],
            $row['amount_paid'],
            $row['change_amount'],
            $row['payment_status']
        ]);
    }
    fclose($output);
    exit;
}

$summary = [
    'total_tx' => 0,
    'grand_revenue' => 0,
    'cash_rev' => 0,
    'qris_rev' => 0
];
$topFoods = [];
$topDrinks = [];
$transactions = [];

if (!$dateError) {
    // Use payment dates for every sales metric, including the items in paid sessions.
    $stmtSummary = $pdo->prepare("SELECT 
        COUNT(id) as total_tx,
        COALESCE(SUM(total_bill), 0) as grand_revenue,
        COALESCE(SUM(CASE WHEN payment_method = 'tunai' THEN total_bill ELSE 0 END), 0) as cash_rev,
        COALESCE(SUM(CASE WHEN payment_method = 'qris' THEN total_bill ELSE 0 END), 0) as qris_rev
        FROM payments 
        WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)
          AND payment_status = 'lunas'");
    $stmtSummary->execute([$rangeStart, $rangeEnd]);
    $summary = $stmtSummary->fetch();

    $paidSessionCondition = "EXISTS (
        SELECT 1
        FROM payments p
        WHERE p.payment_status = 'lunas'
          AND p.created_at >= ?
          AND p.created_at < DATE_ADD(?, INTERVAL 1 DAY)
          AND (p.order_id = o.id OR (p.session_id IS NOT NULL AND p.session_id = o.session_id))
    )";

    $stmtTopFood = $pdo->prepare("SELECT m.name, SUM(oi.quantity) as total_qty, SUM(oi.subtotal) as total_rev 
                                  FROM order_items oi 
                                  JOIN orders o ON oi.order_id = o.id
                                  JOIN menus m ON oi.menu_id = m.id 
                                  WHERE oi.station = 'kitchen' AND oi.item_status != 'dibatalkan'
                                    AND {$paidSessionCondition}
                                  GROUP BY m.id 
                                  ORDER BY total_qty DESC LIMIT 5");
    $stmtTopFood->execute([$rangeStart, $rangeEnd]);
    $topFoods = $stmtTopFood->fetchAll();

    $stmtTopDrink = $pdo->prepare("SELECT m.name, SUM(oi.quantity) as total_qty, SUM(oi.subtotal) as total_rev 
                                   FROM order_items oi 
                                   JOIN orders o ON oi.order_id = o.id
                                   JOIN menus m ON oi.menu_id = m.id 
                                   WHERE oi.station = 'barista' AND oi.item_status != 'dibatalkan'
                                     AND {$paidSessionCondition}
                                   GROUP BY m.id 
                                   ORDER BY total_qty DESC LIMIT 5");
    $stmtTopDrink->execute([$rangeStart, $rangeEnd]);
    $topDrinks = $stmtTopDrink->fetchAll();

    $stmtTx = $pdo->prepare("SELECT p.*, t.table_number, u.full_name as cashier_name 
                             FROM payments p 
                             LEFT JOIN table_sessions ts ON p.session_id = ts.id 
                             LEFT JOIN restaurant_tables t ON ts.table_id = t.id 
                             LEFT JOIN users u ON p.cashier_id = u.id 
                             WHERE p.created_at >= ? AND p.created_at < DATE_ADD(?, INTERVAL 1 DAY)
                               AND p.payment_status = 'lunas'
                             ORDER BY p.id DESC");
    $stmtTx->execute([$rangeStart, $rangeEnd]);
    $transactions = $stmtTx->fetchAll();
}

$pageTitle = 'Laporan & Statistik Penjualan';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 14px;">
  <div>
    <h2 style="font-size: 22px; font-weight: 800; color: var(--color-primary);">Laporan Keuangan &amp; Penjualan</h2>
    <p style="font-size: 13px; color: var(--color-text-muted);">Analisis omzet, tren menu terlaris, dan rincian transaksi berdasarkan database.</p>
  </div>

  <div style="display: flex; gap: 10px;">
    <a href="<?= BASE_URL ?>/admin/reports.php?start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&export=csv" class="btn btn-secondary btn-sm">
      <i class="fa-solid fa-file-csv"></i> Unduh File CSV
    </a>
    <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
      <i class="fa-solid fa-print"></i> Cetak Laporan
    </button>
  </div>
</div>

<!-- Date Filter Form -->
<div class="card" style="margin-bottom: 24px; padding: 18px 24px;">
  <?php if ($dateError): ?>
    <div class="alert alert-danger" role="alert">
      <i class="fa-solid fa-circle-exclamation"></i>
      <div><?= htmlspecialchars($dateError) ?></div>
    </div>
  <?php endif; ?>
  <form method="GET" action="" style="display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap;">
    <div style="flex: 1; min-width: 180px;">
      <label class="form-label">Tanggal Mulai</label>
      <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
    </div>

    <div style="flex: 1; min-width: 180px;">
      <label class="form-label">Tanggal Akhir</label>
      <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
    </div>

    <div>
      <button type="submit" class="btn btn-primary">
        <i class="fa-solid fa-filter"></i> Terapkan Filter
      </button>
      <a href="<?= BASE_URL ?>/admin/reports.php" class="btn btn-outline">Bulan Ini</a>
    </div>
  </form>
</div>

<!-- Revenue Summary Cards -->
<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; margin-bottom: 24px;">
  <div class="stat-card card-green">
    <div class="stat-icon"><i class="fa-solid fa-sack-dollar"></i></div>
    <div class="stat-info">
      <div class="stat-label">Total Omzet Penjualan</div>
      <div class="stat-value" style="font-size: 20px;"><?= formatRupiah($summary['grand_revenue']) ?></div>
    </div>
  </div>

  <div class="stat-card card-brown">
    <div class="stat-icon"><i class="fa-solid fa-receipt"></i></div>
    <div class="stat-info">
      <div class="stat-label">Total Transaksi</div>
      <div class="stat-value"><?= (int)$summary['total_tx'] ?></div>
    </div>
  </div>

  <div class="stat-card card-blue">
    <div class="stat-icon"><i class="fa-solid fa-money-bill-wave"></i></div>
    <div class="stat-info">
      <div class="stat-label">Pemasukan Tunai</div>
      <div class="stat-value" style="font-size: 18px;"><?= formatRupiah($summary['cash_rev']) ?></div>
    </div>
  </div>

  <div class="stat-card card-brown" style="background: #FDF4E7;">
    <div class="stat-icon" style="background: #C69255;"><i class="fa-solid fa-qrcode"></i></div>
    <div class="stat-info">
      <div class="stat-label">Pemasukan QRIS</div>
      <div class="stat-value" style="font-size: 18px;"><?= formatRupiah($summary['qris_rev']) ?></div>
    </div>
  </div>
</div>

<!-- Top Selling Food & Drinks Grid -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 28px;">
  <!-- Makanan Terlaris -->
  <div class="card">
    <h3 style="font-size: 16px; font-weight: 800; color: var(--color-primary); margin-bottom: 14px; display: flex; align-items: center; gap: 8px;">
      <i class="fa-solid fa-utensils" style="color: #8B5A2B;"></i> 5 Makanan Terlaris
    </h3>
    <table class="table">
      <thead>
        <tr>
          <th>Nama Makanan</th>
          <th style="text-align: center;">Porsi Terjual</th>
          <th style="text-align: right;">Total Pendapatan</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($topFoods)): ?>
          <tr><td colspan="3" style="text-align: center; color: #888;">Belum ada data penjualan makanan.</td></tr>
        <?php else: ?>
          <?php foreach ($topFoods as $f): ?>
            <tr>
              <td><strong><?= htmlspecialchars($f['name']) ?></strong></td>
              <td style="text-align: center;"><span class="badge badge-menunggu"><?= $f['total_qty'] ?> porsi</span></td>
              <td style="text-align: right; font-weight: 700; color: var(--color-primary);"><?= formatRupiah($f['total_rev']) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Minuman Terlaris -->
  <div class="card">
    <h3 style="font-size: 16px; font-weight: 800; color: var(--color-primary); margin-bottom: 14px; display: flex; align-items: center; gap: 8px;">
      <i class="fa-solid fa-mug-hot" style="color: #8B5A2B;"></i> 5 Minuman &amp; Kopi Terlaris
    </h3>
    <table class="table">
      <thead>
        <tr>
          <th>Nama Minuman</th>
          <th style="text-align: center;">Gelas Terjual</th>
          <th style="text-align: right;">Total Pendapatan</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($topDrinks)): ?>
          <tr><td colspan="3" style="text-align: center; color: #888;">Belum ada data penjualan minuman.</td></tr>
        <?php else: ?>
          <?php foreach ($topDrinks as $d): ?>
            <tr>
              <td><strong><?= htmlspecialchars($d['name']) ?></strong></td>
              <td style="text-align: center;"><span class="badge badge-diproses"><?= $d['total_qty'] ?> cup</span></td>
              <td style="text-align: right; font-weight: 700; color: var(--color-primary);"><?= formatRupiah($d['total_rev']) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Detailed Transactions Table -->
<div class="card">
  <h3 style="font-size: 16px; font-weight: 800; color: var(--color-primary); margin-bottom: 16px;">
    Rincian Transaksi Periode <?= date('d/m/Y', strtotime($startDate)) ?> s/d <?= date('d/m/Y', strtotime($endDate)) ?>
  </h3>

  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>No. Transaksi</th>
          <th>Tanggal &amp; Waktu</th>
          <th>Meja</th>
          <th>Metode Bayar</th>
          <th>Total Tagihan</th>
          <th>Nominal Dibayar</th>
          <th>Kembalian</th>
          <th>Kasir</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($transactions)): ?>
          <tr>
            <td colspan="8" style="text-align: center; padding: 30px; color: #888;">
              Tidak ada riwayat transaksi pada rentang tanggal yang dipilih.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($transactions as $t): ?>
            <tr>
              <td><strong><?= htmlspecialchars($t['transaction_number']) ?></strong></td>
              <td><?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></td>
              <td><span class="badge badge-menunggu" style="background: #8B5A2B;">Meja <?= htmlspecialchars($t['table_number'] ?? '01') ?></span></td>
              <td><span class="badge" style="background:#FAF5EE; color:#331B11; border:1px solid #DCCBBF;"><?= strtoupper($t['payment_method']) ?></span></td>
              <td><strong><?= formatRupiah($t['total_bill']) ?></strong></td>
              <td><?= formatRupiah($t['amount_paid']) ?></td>
              <td><?= formatRupiah($t['change_amount']) ?></td>
              <td><?= htmlspecialchars($t['cashier_name'] ?? 'Kasir') ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
