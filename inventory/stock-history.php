<?php
/**
 * JAZUVA - Stock Movements & Audit History
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['admin', 'inventory']);

$pdo = getDbConnection();

$ingFilter = (int)($_GET['ingredient_id'] ?? 0);
$typeFilter = trim($_GET['type'] ?? '');

$sql = "SELECT sm.*, i.name as ingredient_name, i.unit, u.full_name as user_name 
        FROM stock_movements sm 
        JOIN ingredients i ON sm.ingredient_id = i.id 
        LEFT JOIN users u ON sm.created_by = u.id 
        WHERE 1=1";
$params = [];

if ($ingFilter > 0) {
    $sql .= " AND sm.ingredient_id = ?";
    $params[] = $ingFilter;
}

if (!empty($typeFilter)) {
    $sql .= " AND sm.movement_type = ?";
    $params[] = $typeFilter;
}

$sql .= " ORDER BY sm.id DESC LIMIT 200";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$movements = $stmt->fetchAll();

$allIngredients = $pdo->query("SELECT * FROM ingredients ORDER BY name ASC")->fetchAll();

$pageTitle = 'Riwayat Mutasi & Perubahan Stok';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
  <div>
    <h2 style="font-size: 22px; font-weight: 800; color: var(--color-primary);">Riwayat Perubahan Stok Bahan Baku</h2>
    <p style="font-size: 13px; color: var(--color-text-muted);">
      Log lengkap mutasi persediaan akibat pesanan kitchen/barista, stok masuk, atau penyesuaian manual.
    </p>
  </div>
  <a href="<?= BASE_URL ?>/inventory/dashboard.php" class="btn btn-secondary btn-sm">
    <i class="fa-solid fa-arrow-left"></i> Kembali ke Stok
  </a>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom: 24px; padding: 18px 24px;">
  <form method="GET" action="" style="display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap;">
    <div style="flex: 2; min-width: 220px;">
      <label class="form-label">Filter Bahan Baku</label>
      <select name="ingredient_id" class="form-select">
        <option value="">-- Semua Bahan Baku --</option>
        <?php foreach ($allIngredients as $ing): ?>
          <option value="<?= $ing['id'] ?>" <?= $ing['id'] == $ingFilter ? 'selected' : '' ?>>
            <?= htmlspecialchars($ing['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div style="flex: 1; min-width: 180px;">
      <label class="form-label">Jenis Mutasi</label>
      <select name="type" class="form-select">
        <option value="">-- Semua Jenis --</option>
        <option value="pesanan" <?= $typeFilter === 'pesanan' ? 'selected' : '' ?>>Pesanan (Pengurangan Resep)</option>
        <option value="masuk" <?= $typeFilter === 'masuk' ? 'selected' : '' ?>>Stok Masuk (Restock)</option>
        <option value="pembatalan" <?= $typeFilter === 'pembatalan' ? 'selected' : '' ?>>Pengembalian (Batal)</option>
        <option value="penyesuaian" <?= $typeFilter === 'penyesuaian' ? 'selected' : '' ?>>Penyesuaian Manual</option>
      </select>
    </div>

    <div>
      <button type="submit" class="btn btn-primary">
        <i class="fa-solid fa-filter"></i> Filter
      </button>
      <a href="<?= BASE_URL ?>/inventory/stock-history.php" class="btn btn-outline">Reset</a>
    </div>
  </form>
</div>

<!-- Log Table -->
<div class="card">
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>Waktu</th>
          <th>Nama Bahan</th>
          <th>Jenis Mutasi</th>
          <th>Jumlah</th>
          <th>Stok Sebelum</th>
          <th>Stok Akhir</th>
          <th>Keterangan / Referensi</th>
          <th>Petugas</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($movements)): ?>
          <tr>
            <td colspan="8" style="text-align: center; padding: 40px; color: var(--color-text-muted);">
              Belum ada riwayat mutasi stok yang tercatat.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($movements as $m): ?>
            <?php
              $badgeType = 'badge-selesai';
              $sign = '-';
              $color = '#E74C3C';
              if ($m['movement_type'] === 'masuk' || $m['movement_type'] === 'pembatalan') {
                $badgeType = 'badge-siap';
                $sign = '+';
                $color = '#27AE60';
              } elseif ($m['movement_type'] === 'pesanan') {
                $badgeType = 'badge-diproses';
              } elseif ($m['movement_type'] === 'penyesuaian' && (float)$m['current_stock'] > (float)$m['previous_stock']) {
                $badgeType = 'badge-siap';
                $sign = '+';
                $color = '#27AE60';
              }
            ?>
            <tr>
              <td><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></td>
              <td><strong><?= htmlspecialchars($m['ingredient_name']) ?></strong></td>
              <td>
                <span class="badge <?= $badgeType ?>">
                  <?= ucfirst($m['movement_type']) ?>
                </span>
              </td>
              <td style="font-weight: 800; color: <?= $color ?>;">
                <?= $sign ?> <?= (float)$m['quantity'] ?> <?= htmlspecialchars($m['unit']) ?>
              </td>
              <td><?= (float)$m['previous_stock'] ?> <?= htmlspecialchars($m['unit']) ?></td>
              <td><strong><?= (float)$m['current_stock'] ?> <?= htmlspecialchars($m['unit']) ?></strong></td>
              <td style="font-size: 12.5px; color: #555;"><?= htmlspecialchars($m['notes'] ?? '-') ?></td>
              <td><?= htmlspecialchars($m['user_name'] ?? 'Sistem') ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
