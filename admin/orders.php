<?php
/**
 * JAZUVA - All Orders Overview (Admin Pesanan)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['admin']);

$pdo = getDbConnection();

// Status cancellation handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order_id'])) {
    requireCsrfPost();
    $cancelId = (int)$_POST['cancel_order_id'];
    try {
        $pdo->beginTransaction();

        // Find all items in this order
        $stmtItems = $pdo->prepare("SELECT id FROM order_items WHERE order_id = ?");
        $stmtItems->execute([$cancelId]);
        $items = $stmtItems->fetchAll();

        foreach ($items as $it) {
            $pdo->prepare("UPDATE order_items SET item_status = 'dibatalkan' WHERE id = ?")->execute([$it['id']]);
            // Reverse stock if previously deducted
            reverseStockForOrderItem($it['id'], getCurrentUser()['id']);
        }

        $pdo->prepare("UPDATE orders SET order_status = 'dibatalkan' WHERE id = ?")->execute([$cancelId]);
        $pdo->commit();
        setFlash('success', 'Pesanan berhasil dibatalkan dan stok bahan telah dikembalikan.');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        setFlash('error', 'Gagal membatalkan pesanan: ' . $e->getMessage());
    }
    header('Location: ' . BASE_URL . '/admin/orders.php');
    exit;
}

$statusFilter = trim($_GET['status'] ?? 'all');
$sql = "SELECT o.*, t.table_number, 
               COUNT(oi.id) as item_count,
               GROUP_CONCAT(CONCAT(m.name, ' x', oi.quantity) SEPARATOR ', ') as items_summary
        FROM orders o 
        JOIN restaurant_tables t ON o.table_id = t.id 
        LEFT JOIN order_items oi ON o.id = oi.order_id 
        LEFT JOIN menus m ON oi.menu_id = m.id 
        WHERE 1=1";
$params = [];

if ($statusFilter !== 'all' && !empty($statusFilter)) {
    $sql .= " AND o.order_status = ?";
    $params[] = $statusFilter;
}

$sql .= " GROUP BY o.id ORDER BY o.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$pageTitle = 'Semua Pesanan Restoran';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
  <div>
    <h2 style="font-size: 22px; font-weight: 800; color: var(--color-primary);">Daftar Seluruh Pesanan Meja</h2>
    <p style="font-size: 13px; color: var(--color-text-muted);">Pantau antrean pesanan makanan, minuman, dan status pembayaran dari setiap meja.</p>
  </div>

  <div style="display: flex; gap: 10px;">
    <a href="<?= BASE_URL ?>/kitchen/dashboard.php" class="btn btn-secondary btn-sm">
      <i class="fa-solid fa-kitchen-set"></i> Kitchen Display
    </a>
    <a href="<?= BASE_URL ?>/barista/dashboard.php" class="btn btn-secondary btn-sm">
      <i class="fa-solid fa-blender"></i> Barista Display
    </a>
    <a href="<?= BASE_URL ?>/cashier/dashboard.php" class="btn btn-caramel btn-sm">
      <i class="fa-solid fa-cash-register"></i> Kasir POS
    </a>
  </div>
</div>

<!-- Status Tabs -->
<div class="nav-pills">
  <a href="<?= BASE_URL ?>/admin/orders.php?status=all" class="nav-pill <?= $statusFilter === 'all' ? 'active' : '' ?>">Semua Status</a>
  <a href="<?= BASE_URL ?>/admin/orders.php?status=menunggu" class="nav-pill <?= $statusFilter === 'menunggu' ? 'active' : '' ?>">Menunggu</a>
  <a href="<?= BASE_URL ?>/admin/orders.php?status=diproses" class="nav-pill <?= $statusFilter === 'diproses' ? 'active' : '' ?>">Diproses</a>
  <a href="<?= BASE_URL ?>/admin/orders.php?status=siap" class="nav-pill <?= $statusFilter === 'siap' ? 'active' : '' ?>">Siap Saji</a>
  <a href="<?= BASE_URL ?>/admin/orders.php?status=selesai" class="nav-pill <?= $statusFilter === 'selesai' ? 'active' : '' ?>">Selesai (Lunas)</a>
  <a href="<?= BASE_URL ?>/admin/orders.php?status=dibatalkan" class="nav-pill <?= $statusFilter === 'dibatalkan' ? 'active' : '' ?>">Dibatalkan</a>
</div>

<!-- Orders Table -->
<div class="card">
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>No. Pesanan</th>
          <th>Meja</th>
          <th>Waktu</th>
          <th>Nama Tamu</th>
          <th>Rincian Item</th>
          <th>Total Tagihan</th>
          <th>Status</th>
          <th style="text-align: center;">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($orders)): ?>
          <tr>
            <td colspan="8" style="text-align: center; padding: 40px; color: var(--color-text-muted);">
              Tidak ada pesanan yang sesuai dengan filter.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($orders as $o): ?>
            <tr>
              <td><strong style="color: var(--color-primary);"><?= htmlspecialchars($o['order_number']) ?></strong></td>
              <td><span class="badge badge-menunggu" style="background: #8B5A2B;">Meja <?= htmlspecialchars($o['table_number']) ?></span></td>
              <td><?= date('d/m H:i', strtotime($o['created_at'])) ?> (<?= timeElapsedString($o['created_at']) ?>)</td>
              <td><?= htmlspecialchars($o['customer_name'] ?? 'Pelanggan') ?></td>
              <td style="max-width: 280px; font-size: 12.5px;"><?= htmlspecialchars($o['items_summary'] ?? '-') ?></td>
              <td><strong><?= formatRupiah($o['final_amount']) ?></strong></td>
              <td>
                <span class="badge badge-<?= $o['order_status'] ?>">
                  <?= ucfirst($o['order_status']) ?>
                </span>
              </td>
              <td style="text-align: center;">
                <div style="display: flex; gap: 6px; justify-content: center;">
                  <a href="<?= BASE_URL ?>/order-status.php?order=<?= urlencode($o['order_number']) ?>" target="_blank" class="btn btn-secondary btn-sm" title="Tracking Status">
                    <i class="fa-solid fa-eye"></i>
                  </a>
                  <?php if ($o['order_status'] !== 'selesai' && $o['order_status'] !== 'dibatalkan'): ?>
                    <form method="POST" action="" onsubmit="return confirm('Batalkan pesanan ini? Stok bahan yang sudah dipotong akan dikembalikan.')">
                      <?= csrfField() ?>
                      <input type="hidden" name="cancel_order_id" value="<?= $o['id'] ?>">
                      <button type="submit" class="btn btn-danger btn-sm" title="Batalkan Pesanan">
                        <i class="fa-solid fa-xmark"></i>
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
