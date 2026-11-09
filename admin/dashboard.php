<?php
/**
 * JAZUVA - Admin Dashboard (Dashboard Restoran)
 * Matches Prototype Admin Dashboard Screen
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['admin']);

$pdo = getDbConnection();

// 1. Total Pesanan Hari Ini
$stmtTodayOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()");
$totalOrdersToday = (int)$stmtTodayOrders->fetchColumn();

// 2. Pendapatan Hari Ini
$stmtTodayRev = $pdo->query("SELECT COALESCE(SUM(total_bill), 0) FROM payments WHERE DATE(created_at) = CURDATE() AND payment_status = 'lunas'");
$todayRevenue = (float)$stmtTodayRev->fetchColumn();

// 3. Meja Terisi vs Total Meja
$stmtTotalTables = $pdo->query("SELECT COUNT(*) FROM restaurant_tables WHERE is_active = 1");
$totalTables = (int)$stmtTotalTables->fetchColumn();

$stmtOccupiedTables = $pdo->query("SELECT COUNT(*) FROM restaurant_tables WHERE is_active = 1 AND status IN ('terisi', 'menunggu_pembayaran')");
$occupiedTables = (int)$stmtOccupiedTables->fetchColumn();

// 4. Stok Menipis / Kritis
$stmtLowStock = $pdo->query("SELECT COUNT(*) FROM ingredients WHERE stock <= min_stock");
$lowStockCount = (int)$stmtLowStock->fetchColumn();

// Table counts for Donut Chart
$stmtTersedia = $pdo->query("SELECT COUNT(*) FROM restaurant_tables WHERE status = 'tersedia' AND is_active = 1");
$countTersedia = (int)$stmtTersedia->fetchColumn();

$stmtTerisi = $pdo->query("SELECT COUNT(*) FROM restaurant_tables WHERE status = 'terisi' AND is_active = 1");
$countTerisi = (int)$stmtTerisi->fetchColumn();

$stmtDipesan = $pdo->query("SELECT COUNT(*) FROM restaurant_tables WHERE status = 'dipesan' AND is_active = 1");
$countDipesan = (int)$stmtDipesan->fetchColumn();

// Sales are attributed to the payment date, matching the reports page.
$stmtWeeklySales = $pdo->query("SELECT DATE(p.created_at) AS sale_date, oi.station,
                                       SUM(oi.subtotal) AS sales_total
                                FROM payments p
                                JOIN orders o ON o.id = p.order_id
                                    OR (p.session_id IS NOT NULL AND o.session_id = p.session_id)
                                JOIN order_items oi ON oi.order_id = o.id
                                WHERE p.payment_status = 'lunas'
                                  AND p.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                                  AND p.created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
                                  AND oi.item_status != 'dibatalkan'
                                GROUP BY DATE(p.created_at), oi.station");

$dayNames = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
$chartDates = [];
$chartDateIndexes = [];
$foodSales = array_fill(0, 7, 0);
$drinkSales = array_fill(0, 7, 0);
for ($dayOffset = 6; $dayOffset >= 0; $dayOffset--) {
    $chartDate = date('Y-m-d', strtotime("-{$dayOffset} days"));
    $chartDateIndexes[$chartDate] = count($chartDates);
    $chartDates[] = $dayNames[(int)date('w', strtotime($chartDate))];
}

foreach ($stmtWeeklySales->fetchAll() as $dailySale) {
    $index = $chartDateIndexes[$dailySale['sale_date']] ?? null;
    if ($index === null) {
        continue;
    }

    if ($dailySale['station'] === 'kitchen') {
        $foodSales[$index] = (float)$dailySale['sales_total'];
    } elseif ($dailySale['station'] === 'barista') {
        $drinkSales[$index] = (float)$dailySale['sales_total'];
    }
}

$pageTitle = 'Dashboard Restoran';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- 4 STATS CARDS MATCHING PROTOTYPE -->
<div class="stats-grid">
  <!-- Card 1: Total Pesanan Hari Ini -->
  <div class="stat-card card-brown">
    <div class="stat-icon">
      <i class="fa-solid fa-receipt"></i>
    </div>
    <div class="stat-info">
      <div class="stat-label">Total Pesanan Hari Ini</div>
      <div class="stat-value"><?= $totalOrdersToday ?></div>
    </div>
  </div>

  <!-- Card 2: Pendapatan Hari Ini -->
  <div class="stat-card card-green">
    <div class="stat-icon">
      <i class="fa-solid fa-wallet"></i>
    </div>
    <div class="stat-info">
      <div class="stat-label">Pendapatan Hari Ini</div>
      <div class="stat-value"><?= formatRupiah($todayRevenue) ?></div>
    </div>
  </div>

  <!-- Card 3: Meja Terisi -->
  <div class="stat-card card-blue">
    <div class="stat-icon">
      <i class="fa-solid fa-chair"></i>
    </div>
    <div class="stat-info">
      <div class="stat-label">Meja Terisi</div>
      <div class="stat-value"><?= $occupiedTables ?> / <?= $totalTables ?></div>
    </div>
  </div>

  <!-- Card 4: Stok Menipis -->
  <div class="stat-card card-red">
    <div class="stat-icon">
      <i class="fa-solid fa-bell"></i>
    </div>
    <div class="stat-info">
      <div class="stat-label">Stok Menipis</div>
      <div class="stat-value" style="color: #C0392B;"><?= $lowStockCount ?> Bahan</div>
    </div>
  </div>
</div>

<!-- CHARTS GRID: Grafik Penjualan (Line Chart) + Status Meja (Donut Chart) -->
<div class="charts-grid">
  <!-- Left: Line Chart Penjualan -->
  <div class="chart-card">
    <div class="chart-header">
      <div class="chart-title">Grafik Penjualan</div>
      <div style="display: flex; gap: 14px; font-size: 12px; font-weight: 600;">
        <span style="display: flex; align-items: center; gap: 6px;">
          <span style="width: 10px; height: 10px; border-radius: 50%; background: #8B5A2B;"></span> Makanan
        </span>
        <span style="display: flex; align-items: center; gap: 6px;">
          <span style="width: 10px; height: 10px; border-radius: 50%; background: #E67E22;"></span> Minuman
        </span>
      </div>
    </div>
    <div style="height: 280px; position: relative;">
      <canvas id="salesChart"></canvas>
    </div>
  </div>

  <!-- Right: Donut Chart Status Meja -->
  <div class="chart-card">
    <div class="chart-header">
      <div class="chart-title">Status Meja</div>
    </div>
    <div style="height: 200px; position: relative; display: flex; align-items: center; justify-content: center;">
      <canvas id="tableStatusChart"></canvas>
    </div>
    <div style="display: flex; justify-content: space-around; margin-top: 18px; font-size: 12px; font-weight: 700;">
      <span style="display: flex; align-items: center; gap: 6px;">
        <span class="legend-dot" style="background: #27AE60;"></span> Tersedia <?= $countTersedia ?>
      </span>
      <span style="display: flex; align-items: center; gap: 6px;">
        <span class="legend-dot" style="background: #E67E22;"></span> Terisi <?= $countTerisi ?>
      </span>
      <span style="display: flex; align-items: center; gap: 6px;">
        <span class="legend-dot" style="background: #8B5A2B;"></span> Dipesan <?= $countDipesan ?>
      </span>
    </div>
  </div>
</div>

<!-- Quick Link Action Cards -->
<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-top: 24px;">
  <a href="<?= BASE_URL ?>/admin/tables.php" class="card" style="text-decoration: none; display: flex; align-items: center; gap: 14px; padding: 16px;">
    <div style="width: 44px; height: 44px; background: #FAF5EE; color: #8B5A2B; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px;">
      <i class="fa-solid fa-table-cells-large"></i>
    </div>
    <div>
      <div style="font-weight: 700; color: var(--color-primary); font-size: 14px;">Denah &amp; QR Meja</div>
      <div style="font-size: 11.5px; color: var(--color-text-muted);">Kelola meja &amp; cetak QR</div>
    </div>
  </a>

  <a href="<?= BASE_URL ?>/admin/menus.php" class="card" style="text-decoration: none; display: flex; align-items: center; gap: 14px; padding: 16px;">
    <div style="width: 44px; height: 44px; background: #FAF5EE; color: #8B5A2B; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px;">
      <i class="fa-solid fa-utensils"></i>
    </div>
    <div>
      <div style="font-weight: 700; color: var(--color-primary); font-size: 14px;">Manajemen Menu</div>
      <div style="font-size: 11.5px; color: var(--color-text-muted);">Tambah &amp; edit makanan</div>
    </div>
  </a>

  <a href="<?= BASE_URL ?>/cashier/dashboard.php" class="card" style="text-decoration: none; display: flex; align-items: center; gap: 14px; padding: 16px;">
    <div style="width: 44px; height: 44px; background: #EAF8EE; color: #27AE60; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px;">
      <i class="fa-solid fa-cash-register"></i>
    </div>
    <div>
      <div style="font-weight: 700; color: var(--color-primary); font-size: 14px;">Kasir (POS)</div>
      <div style="font-size: 11.5px; color: var(--color-text-muted);">Proses tagihan meja</div>
    </div>
  </a>

  <a href="<?= BASE_URL ?>/kitchen/dashboard.php" class="card" style="text-decoration: none; display: flex; align-items: center; gap: 14px; padding: 16px;">
    <div style="width: 44px; height: 44px; background: #FEF5EB; color: #E67E22; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px;">
      <i class="fa-solid fa-fire-burner"></i>
    </div>
    <div>
      <div style="font-weight: 700; color: var(--color-primary); font-size: 14px;">Kitchen &amp; Barista</div>
      <div style="font-size: 11.5px; color: var(--color-text-muted);">Antrean pesanan aktif</div>
    </div>
  </a>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // 1. Line Chart Penjualan (Makanan vs Minuman)
  const ctxSales = document.getElementById('salesChart').getContext('2d');
  new Chart(ctxSales, {
    type: 'line',
    data: {
      labels: <?= json_encode($chartDates) ?>,
      datasets: [
        {
          label: 'Makanan',
          data: <?= json_encode($foodSales) ?>,
          borderColor: '#8B5A2B',
          backgroundColor: 'rgba(139, 90, 43, 0.08)',
          fill: true,
          tension: 0.35,
          borderWidth: 2.5,
          pointBackgroundColor: '#8B5A2B',
          pointRadius: 4
        },
        {
          label: 'Minuman',
          data: <?= json_encode($drinkSales) ?>,
          borderColor: '#E67E22',
          backgroundColor: 'rgba(230, 126, 34, 0.05)',
          fill: true,
          tension: 0.35,
          borderWidth: 2.5,
          pointBackgroundColor: '#E67E22',
          pointRadius: 4
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            callback: value => 'Rp ' + Number(value).toLocaleString('id-ID')
          },
          grid: { color: 'rgba(0,0,0,0.04)' }
        },
        x: {
          grid: { display: false }
        }
      }
    }
  });

  // 2. Donut Chart Status Meja
  const ctxTable = document.getElementById('tableStatusChart').getContext('2d');
  new Chart(ctxTable, {
    type: 'doughnut',
    data: {
      labels: ['Tersedia', 'Terisi', 'Dipesan'],
      datasets: [{
        data: [<?= $countTersedia ?>, <?= $countTerisi ?>, <?= $countDipesan ?>],
        backgroundColor: ['#27AE60', '#E67E22', '#8B5A2B'],
        borderWidth: 0,
        hoverOffset: 4
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '72%',
      plugins: {
        legend: { display: false }
      }
    }
  });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
