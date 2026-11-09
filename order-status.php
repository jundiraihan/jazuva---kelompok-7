<?php
/**
 * JAZUVA - Customer Live Order Tracking
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = getDbConnection();

$orderNumber = trim($_GET['order'] ?? '');

$stmt = $pdo->prepare("SELECT o.*, t.table_number, t.token as table_token
                       FROM orders o 
                       JOIN restaurant_tables t ON o.table_id = t.id 
                       WHERE o.order_number = ? LIMIT 1");
$stmt->execute([$orderNumber]);
$order = $stmt->fetch();

if (!$order) {
    die("<h3>Pesanan tidak ditemukan.</h3><p><a href='" . BASE_URL . "/index.php'>Kembali ke Beranda</a></p>");
}

// Fetch order items
$stmtItems = $pdo->prepare("SELECT oi.*, m.name as menu_name, m.image as menu_image, c.type as category_type 
                            FROM order_items oi 
                            JOIN menus m ON oi.menu_id = m.id 
                            JOIN categories c ON m.category_id = c.id 
                            WHERE oi.order_id = ? 
                            ORDER BY oi.id ASC");
$stmtItems->execute([$order['id']]);
$items = $stmtItems->fetchAll();

// Progress step calculation
$statusIndex = 1;
if ($order['order_status'] === 'diproses') $statusIndex = 2;
elseif ($order['order_status'] === 'siap') $statusIndex = 3;
elseif ($order['order_status'] === 'selesai') $statusIndex = 4;
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Status Pesanan #<?= htmlspecialchars($order['order_number']) ?> - Jazuva</title>
  
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  
  <style>
    body {
      background: #F8F3EC;
      color: #2A211B;
      min-height: 100vh;
      padding: 24px 16px;
    }
    .status-container {
      max-width: 540px;
      margin: 0 auto;
    }
    .status-card {
      background: #FFFFFF;
      border-radius: var(--radius-xl);
      padding: 28px 24px;
      box-shadow: var(--shadow-md);
      border: 1px solid var(--color-border);
      margin-bottom: 20px;
    }
    /* Steps Progress Bar */
    .stepper {
      display: flex;
      justify-content: space-between;
      position: relative;
      margin: 28px 0 32px 0;
    }
    .stepper::before {
      content: '';
      position: absolute;
      top: 18px;
      left: 30px;
      right: 30px;
      height: 4px;
      background: #E8DDD2;
      z-index: 1;
    }
    .step-node {
      position: relative;
      z-index: 2;
      text-align: center;
      width: 60px;
    }
    .step-icon {
      width: 38px;
      height: 38px;
      border-radius: 50%;
      background: #E8DDD2;
      color: #7E7065;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 8px auto;
      font-size: 14px;
      font-weight: 700;
      transition: var(--transition-fast);
    }
    .step-node.active .step-icon {
      background: #8B5A2B;
      color: #FFFFFF;
      box-shadow: 0 0 0 5px rgba(139, 90, 43, 0.2);
    }
    .step-node.completed .step-icon {
      background: #27AE60;
      color: #FFFFFF;
    }
    .step-label {
      font-size: 11px;
      font-weight: 700;
      color: var(--color-text-muted);
    }
    .step-node.active .step-label {
      color: var(--color-primary);
    }
    .item-card {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 12px 0;
      border-bottom: 1px solid var(--color-border-light);
    }
    .station-badge {
      font-size: 10px;
      padding: 2px 8px;
      border-radius: var(--radius-pill);
      font-weight: 700;
      text-transform: uppercase;
    }
    .station-kitchen { background: #FDEED9; color: #D35400; }
    .station-barista { background: #E8F4F8; color: #2980B9; }
  </style>
</head>
<body>

  <div class="status-container">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
      <a href="<?= BASE_URL ?>/menu.php?meja=<?= urlencode($order['table_number']) ?>&amp;token=<?= urlencode($order['table_token']) ?>" class="btn btn-secondary btn-sm">
        <i class="fa-solid fa-arrow-left"></i> Tambah Menu Lain
      </a>
      <div style="font-size: 13px; font-weight: 700; color: #8B5A2B;">
        <i class="fa-solid fa-chair"></i> Meja <?= htmlspecialchars($order['table_number']) ?>
      </div>
    </div>

    <div class="status-card">
      <div style="text-align: center; margin-bottom: 20px;">
        <div style="width: 54px; height: 54px; background: #FAF3E8; color: #8B5A2B; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 10px;">
          <i class="fa-solid fa-receipt"></i>
        </div>
        <h2 style="font-size: 20px; font-weight: 800; color: var(--color-primary);">Pesanan Diterima!</h2>
        <div style="font-size: 13px; color: var(--color-text-muted); margin-top: 2px;">
          No. Pesanan: <strong style="color: var(--color-primary);"><?= htmlspecialchars($order['order_number']) ?></strong>
        </div>
        <div style="font-size: 12px; color: #888; margin-top: 2px;">
          Waktu: <?= date('H:i', strtotime($order['created_at'])) ?> WIB
        </div>
      </div>

      <!-- Live Stepper Tracker -->
      <div class="stepper">
        <div class="step-node <?= $statusIndex >= 1 ? ($statusIndex == 1 ? 'active' : 'completed') : '' ?>">
          <div class="step-icon">
            <i class="fa-solid <?= $statusIndex > 1 ? 'fa-check' : 'fa-hourglass-start' ?>"></i>
          </div>
          <div class="step-label">Menunggu</div>
        </div>

        <div class="step-node <?= $statusIndex >= 2 ? ($statusIndex == 2 ? 'active' : 'completed') : '' ?>">
          <div class="step-icon">
            <i class="fa-solid <?= $statusIndex > 2 ? 'fa-check' : 'fa-fire-burner' ?>"></i>
          </div>
          <div class="step-label">Diproses</div>
        </div>

        <div class="step-node <?= $statusIndex >= 3 ? ($statusIndex == 3 ? 'active' : 'completed') : '' ?>">
          <div class="step-icon">
            <i class="fa-solid <?= $statusIndex > 3 ? 'fa-check' : 'fa-bell-concierge' ?>"></i>
          </div>
          <div class="step-label">Siap</div>
        </div>

        <div class="step-node <?= $statusIndex >= 4 ? 'completed' : '' ?>">
          <div class="step-icon">
            <i class="fa-solid fa-flag-checkered"></i>
          </div>
          <div class="step-label">Selesai</div>
        </div>
      </div>

      <!-- Order Items Detail -->
      <h4 style="font-size: 14px; font-weight: 800; color: var(--color-primary); margin-bottom: 12px; border-bottom: 1px solid var(--color-border); padding-bottom: 8px;">
        Rincian Pesanan
      </h4>

      <?php foreach ($items as $item): ?>
        <div class="item-card">
          <div>
            <div style="font-weight: 700; font-size: 13.5px; color: var(--color-primary);">
              <?= htmlspecialchars($item['menu_name']) ?> <span style="color: var(--color-accent); font-weight: 800;">x<?= $item['quantity'] ?></span>
            </div>
            <?php if (!empty($item['notes'])): ?>
              <div style="font-size: 11.5px; color: #888; font-style: italic; margin-top: 2px;">
                "<?= htmlspecialchars($item['notes']) ?>"
              </div>
            <?php endif; ?>
            <div style="margin-top: 4px; display: flex; gap: 6px;">
              <span class="station-badge <?= $item['station'] === 'kitchen' ? 'station-kitchen' : 'station-barista' ?>">
                <?= $item['station'] === 'kitchen' ? 'Kitchen Dapur' : 'Barista Minuman' ?>
              </span>
              <span class="badge badge-<?= $item['item_status'] ?>" style="font-size: 10px; padding: 2px 8px;">
                <?= ucfirst($item['item_status']) ?>
              </span>
            </div>
          </div>
          <div style="font-weight: 700; font-size: 13.5px;">
            <?= formatRupiah($item['subtotal']) ?>
          </div>
        </div>
      <?php endforeach; ?>

      <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 18px; padding-top: 14px; border-top: 1px dashed var(--color-border);">
        <span style="font-weight: 700; color: var(--color-text-muted);">Total Tagihan</span>
        <span style="font-size: 18px; font-weight: 800; color: var(--color-primary);"><?= formatRupiah($order['final_amount']) ?></span>
      </div>
    </div>

    <!-- Additional info card -->
    <div style="text-align: center; color: var(--color-text-muted); font-size: 12.5px; line-height: 1.5;">
      <p><i class="fa-solid fa-info-circle" style="color: #8B5A2B;"></i> Halaman ini diperbarui secara otomatis. Silakan tunggu di meja Anda.</p>
      <p style="margin-top: 4px;">Setelah selesai bersantap, silakan menuju ke meja kasir untuk pembayaran.</p>
    </div>
  </div>

  <script>
    // Poll order status every 5 seconds
    setInterval(async function() {
      try {
        const res = await fetch('<?= BASE_URL ?>/api/orders.php?action=check_status&order=<?= urlencode($order['order_number']) ?>');
        const data = await res.json();
        if (data.success && data.status !== '<?= $order['order_status'] ?>') {
          // Status changed, reload to show new progress
          window.location.reload();
        }
      } catch (e) {
        // network silent retry
      }
    }, 5000);
  </script>
</body>
</html>
