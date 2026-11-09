<?php
/**
 * JAZUVA - Inventory Dashboard (Stok Bahan Baku)
 * Matches Prototype "Jazuva | Stok Bahan Baku" Screen
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['admin', 'inventory']);

$pdo = getDbConnection();

// Fetch statistics
$stmtTotal = $pdo->query("SELECT COUNT(*) FROM ingredients");
$totalIngredients = (int)$stmtTotal->fetchColumn();

// Fetch low and critical stock counts
$stmtMenipis = $pdo->query("SELECT COUNT(*) FROM ingredients WHERE stock > min_stock * 0.5 AND stock <= min_stock");
$countMenipis = (int)$stmtMenipis->fetchColumn();

$stmtKritis = $pdo->query("SELECT COUNT(*) FROM ingredients WHERE stock <= min_stock * 0.5");
$countKritis = (int)$stmtKritis->fetchColumn();

// Fetch all ingredients
$search = trim($_GET['search'] ?? '');
$sql = "SELECT * FROM ingredients WHERE 1=1";
$params = [];
if (!empty($search)) {
    $sql .= " AND name LIKE ?";
    $params[] = "%{$search}%";
}
$sql .= " ORDER BY stock / min_stock ASC, name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ingredients = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Jazuva | Stok Bahan Baku</title>
  
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dashboard.css">
  
  <style>
    body {
      background-color: #F8F3EC;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }
    .inv-top-bar {
      background: #2F1A10;
      color: #FFFFFF;
      padding: 14px 28px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .inv-brand-title {
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 19px;
      font-weight: 700;
    }
  </style>
</head>
<body>

  <!-- Top Dark Brown Bar Matching Prototype -->
  <header class="inv-top-bar">
    <div class="inv-brand-title">
      <i class="fa-solid fa-boxes-stacked" style="color: #C69255;"></i>
      <span>Jazuva | Stok Bahan Baku</span>
    </div>

    <div style="display: flex; align-items: center; gap: 16px;">
      <a href="<?= BASE_URL ?>/inventory/stock-in.php" class="btn btn-secondary btn-sm" style="background: rgba(255,255,255,0.15); color: #FFF; border: none;">
        <i class="fa-solid fa-truck-ramp-box"></i> Stok Masuk
      </a>
      <a href="<?= BASE_URL ?>/inventory/recipes.php" class="btn btn-secondary btn-sm" style="background: rgba(255,255,255,0.15); color: #FFF; border: none;">
        <i class="fa-solid fa-book-bookmark"></i> Resep Menu
      </a>
      <a href="<?= BASE_URL ?>/inventory/stock-history.php" class="btn btn-secondary btn-sm" style="background: rgba(255,255,255,0.15); color: #FFF; border: none;">
        <i class="fa-solid fa-timeline"></i> Riwayat Mutasi
      </a>

      <?php if (hasRole('admin')): ?>
        <a href="<?= BASE_URL ?>/admin/dashboard.php" class="btn btn-secondary btn-sm" style="background: rgba(255,255,255,0.15); color: #FFF; border: none;">
          <i class="fa-solid fa-gauge"></i> Admin Dashboard
        </a>
      <?php endif; ?>

      <div class="user-profile-pill" style="background: rgba(255,255,255,0.1); color: #FFF; border: 1px solid rgba(255,255,255,0.2);">
        <div class="user-avatar" style="background: #C69255;">
          <i class="fa-solid fa-user"></i>
        </div>
        <span><?= htmlspecialchars(getCurrentUser()['full_name'] ?? 'Staff') ?></span>
      </div>

      <a href="<?= BASE_URL ?>/logout.php" title="Logout" style="color: #E57373; font-size: 16px;">
        <i class="fa-solid fa-right-from-bracket"></i>
      </a>
    </div>
  </header>

  <main class="page-content" style="max-width: 1200px; margin: 0 auto; width: 100%;">
    
    <!-- 3 Stat Cards Matching Prototype -->
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 24px;">
      <!-- Card 1: Total Bahan (Green leaf icon) -->
      <div class="stat-card card-green">
        <div class="stat-icon">
          <i class="fa-solid fa-leaf"></i>
        </div>
        <div class="stat-info">
          <div class="stat-label">Total Bahan</div>
          <div class="stat-value"><?= $totalIngredients ?> Jenis</div>
        </div>
      </div>

      <!-- Card 2: Stok Menipis (Orange box icon) -->
      <div class="stat-card card-brown" style="background: #FEF5EB;">
        <div class="stat-icon" style="background: #E67E22;">
          <i class="fa-solid fa-box-open"></i>
        </div>
        <div class="stat-info">
          <div class="stat-label">Stok Menipis</div>
          <div class="stat-value" style="color: #D35400;"><?= $countMenipis ?> Bahan</div>
        </div>
      </div>

      <!-- Card 3: Stok Kritis (Red alert icon) -->
      <div class="stat-card card-red">
        <div class="stat-icon">
          <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <div class="stat-info">
          <div class="stat-label">Stok Kritis</div>
          <div class="stat-value" style="color: #C0392B;"><?= $countKritis ?> Bahan</div>
        </div>
      </div>
    </div>

    <!-- Table Card Matching Prototype -->
    <div class="card">
      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 14px;">
        <div>
          <h3 style="font-size: 17px; font-weight: 800; color: var(--color-primary);">Daftar Stok Bahan</h3>
          <span style="font-size: 12.5px; color: var(--color-text-muted);">Monitor persediaan bahan makanan dan minuman secara real-time.</span>
        </div>

        <div style="display: flex; gap: 10px; align-items: center;">
          <form method="GET" action="" style="display: flex; gap: 8px;">
            <input type="text" name="search" class="form-control" placeholder="Cari bahan..." 
                   value="<?= htmlspecialchars($search) ?>" style="width: 220px; padding: 7px 14px; font-size: 13px;">
            <button type="submit" class="btn btn-primary btn-sm">
              <i class="fa-solid fa-magnifying-glass"></i>
            </button>
            <?php if (!empty($search)): ?>
              <a href="<?= BASE_URL ?>/inventory/dashboard.php" class="btn btn-outline btn-sm">Reset</a>
            <?php endif; ?>
          </form>

          <a href="<?= BASE_URL ?>/inventory/ingredients.php?action=create" class="btn btn-caramel btn-sm">
            <i class="fa-solid fa-plus"></i> Tambah Bahan
          </a>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>Nama Bahan</th>
              <th>Stok Saat Ini</th>
              <th>Satuan</th>
              <th>Batas Min.</th>
              <th>Status</th>
              <th style="text-align: center;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($ingredients as $ing): ?>
              <?php 
                $stInfo = getStockStatus((float)$ing['stock'], (float)$ing['min_stock']);
              ?>
              <tr>
                <td><strong><?= htmlspecialchars($ing['name']) ?></strong></td>
                <td>
                  <span style="font-size: 15px; font-weight: 800; color: var(--color-primary);">
                    <?= (float)$ing['stock'] == (int)$ing['stock'] ? (int)$ing['stock'] : number_format((float)$ing['stock'], 2, ',', '.') ?>
                  </span>
                </td>
                <td><?= htmlspecialchars($ing['unit']) ?></td>
                <td style="color: var(--color-text-muted); font-size: 12.5px;">
                  <?= (float)$ing['min_stock'] == (int)$ing['min_stock'] ? (int)$ing['min_stock'] : (float)$ing['min_stock'] ?> <?= htmlspecialchars($ing['unit']) ?>
                </td>
                <td>
                  <span class="badge <?= $stInfo['class'] ?>">
                    <span class="badge-dot" style="background-color: <?= $stInfo['color'] ?>;"></span>
                    <?= $stInfo['status'] ?>
                  </span>
                </td>
                <td style="text-align: center;">
                  <a href="<?= BASE_URL ?>/inventory/stock-in.php?ing_id=<?= $ing['id'] ?>" class="btn btn-secondary btn-sm" title="Tambah Stok Masuk">
                    <i class="fa-solid fa-plus"></i> Restock
                  </a>
                  <a href="<?= BASE_URL ?>/inventory/ingredients.php?action=edit&id=<?= $ing['id'] ?>" class="btn btn-outline btn-sm" title="Edit Bahan">
                    <i class="fa-solid fa-pen-to-square"></i>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>

  <script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>

