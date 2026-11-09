<?php
/**
 * JAZUVA - Main Dashboard Sidebar
 * Matches Prototype Dark Coffee Brown Sidebar Layout
 */

$currentUser = getCurrentUser();
$currentRole = strtolower($currentUser['role_name'] ?? '');
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir  = basename(dirname($_SERVER['PHP_SELF']));
?>
<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="sidebar-brand-icon">
      <i class="fa-solid fa-mug-hot"></i>
    </div>
    <span class="sidebar-brand-text">Jazuva</span>
  </div>

  <ul class="sidebar-menu">
    <?php if ($currentRole === 'admin'): ?>
      <li class="sidebar-item">
        <a href="<?= BASE_URL ?>/admin/dashboard.php" class="sidebar-link <?= ($currentPage === 'dashboard.php' && $currentDir === 'admin') ? 'active' : '' ?>">
          <i class="fa-solid fa-gauge-high"></i>
          <span>Dashboard</span>
        </a>
      </li>
      <li class="sidebar-item">
        <a href="<?= BASE_URL ?>/admin/tables.php" class="sidebar-link <?= ($currentPage === 'tables.php' || $currentPage === 'qr-management.php') ? 'active' : '' ?>">
          <i class="fa-solid fa-table-cells-large"></i>
          <span>Meja</span>
        </a>
      </li>
      <li class="sidebar-item">
        <a href="<?= BASE_URL ?>/admin/menus.php" class="sidebar-link <?= ($currentPage === 'menus.php') ? 'active' : '' ?>">
          <i class="fa-solid fa-utensils"></i>
          <span>Menu</span>
        </a>
      </li>
      <li class="sidebar-item">
        <a href="<?= BASE_URL ?>/cashier/dashboard.php" class="sidebar-link <?= ($currentDir === 'cashier') ? 'active' : '' ?>">
          <i class="fa-solid fa-cash-register"></i>
          <span>Kasir POS</span>
        </a>
      </li>
      <li class="sidebar-item">
        <a href="<?= BASE_URL ?>/admin/orders.php" class="sidebar-link <?= ($currentPage === 'orders.php' && $currentDir === 'admin') ? 'active' : '' ?>">
          <i class="fa-solid fa-receipt"></i>
          <span>Pesanan</span>
        </a>
      </li>
      <li class="sidebar-item">
        <a href="<?= BASE_URL ?>/kitchen/dashboard.php" class="sidebar-link <?= ($currentDir === 'kitchen') ? 'active' : '' ?>">
          <i class="fa-solid fa-kitchen-set"></i>
          <span>Kitchen</span>
        </a>
      </li>
      <li class="sidebar-item">
        <a href="<?= BASE_URL ?>/barista/dashboard.php" class="sidebar-link <?= ($currentDir === 'barista') ? 'active' : '' ?>">
          <i class="fa-solid fa-blender"></i>
          <span>Barista</span>
        </a>
      </li>
      <li class="sidebar-item">
        <a href="<?= BASE_URL ?>/inventory/dashboard.php" class="sidebar-link <?= ($currentDir === 'inventory') ? 'active' : '' ?>">
          <i class="fa-solid fa-boxes-stacked"></i>
          <span>Stok Bahan</span>
        </a>
      </li>
      <li class="sidebar-item">
        <a href="<?= BASE_URL ?>/admin/reports.php" class="sidebar-link <?= ($currentPage === 'reports.php') ? 'active' : '' ?>">
          <i class="fa-solid fa-chart-line"></i>
          <span>Laporan</span>
        </a>
      </li>
      <li class="sidebar-item">
        <a href="<?= BASE_URL ?>/admin/settings.php" class="sidebar-link <?= ($currentPage === 'settings.php' || $currentPage === 'users.php') ? 'active' : '' ?>">
          <i class="fa-solid fa-gear"></i>
          <span>Pengaturan</span>
        </a>
      </li>
    <?php elseif ($currentRole === 'cashier'): ?>
      <li class="sidebar-item">
        <a href="<?= BASE_URL ?>/cashier/dashboard.php" class="sidebar-link <?= ($currentPage === 'dashboard.php') ? 'active' : '' ?>">
          <i class="fa-solid fa-cash-register"></i>
          <span>Kasir POS</span>
        </a>
      </li>
      <li class="sidebar-item">
        <a href="<?= BASE_URL ?>/cashier/history.php" class="sidebar-link <?= ($currentPage === 'history.php') ? 'active' : '' ?>">
          <i class="fa-solid fa-clock-rotate-left"></i>
          <span>Riwayat Transaksi</span>
        </a>
      </li>
    <?php elseif ($currentRole === 'kitchen'): ?>
      <li class="sidebar-item">
        <a href="<?= BASE_URL ?>/kitchen/dashboard.php" class="sidebar-link active">
          <i class="fa-solid fa-kitchen-set"></i>
          <span>Antrean Kitchen</span>
        </a>
      </li>
    <?php elseif ($currentRole === 'barista'): ?>
      <li class="sidebar-item">
        <a href="<?= BASE_URL ?>/barista/dashboard.php" class="sidebar-link active">
          <i class="fa-solid fa-blender"></i>
          <span>Antrean Barista</span>
        </a>
      </li>
    <?php elseif ($currentRole === 'inventory'): ?>
      <li class="sidebar-item">
        <a href="<?= BASE_URL ?>/inventory/dashboard.php" class="sidebar-link <?= ($currentPage === 'dashboard.php') ? 'active' : '' ?>">
          <i class="fa-solid fa-boxes-stacked"></i>
          <span>Stok Bahan</span>
        </a>
      </li>
      <li class="sidebar-item">
        <a href="<?= BASE_URL ?>/inventory/ingredients.php" class="sidebar-link <?= ($currentPage === 'ingredients.php') ? 'active' : '' ?>">
          <i class="fa-solid fa-list-check"></i>
          <span>Bahan Baku</span>
        </a>
      </li>
      <li class="sidebar-item">
        <a href="<?= BASE_URL ?>/inventory/recipes.php" class="sidebar-link <?= ($currentPage === 'recipes.php') ? 'active' : '' ?>">
          <i class="fa-solid fa-book-bookmark"></i>
          <span>Resep Menu</span>
        </a>
      </li>
      <li class="sidebar-item">
        <a href="<?= BASE_URL ?>/inventory/stock-in.php" class="sidebar-link <?= ($currentPage === 'stock-in.php') ? 'active' : '' ?>">
          <i class="fa-solid fa-truck-ramp-box"></i>
          <span>Stok Masuk</span>
        </a>
      </li>
      <li class="sidebar-item">
        <a href="<?= BASE_URL ?>/inventory/stock-history.php" class="sidebar-link <?= ($currentPage === 'stock-history.php') ? 'active' : '' ?>">
          <i class="fa-solid fa-timeline"></i>
          <span>Riwayat Mutasi</span>
        </a>
      </li>
    <?php endif; ?>
  </ul>

  <div class="sidebar-footer">
    <a href="<?= BASE_URL ?>/logout.php" class="sidebar-logout" onclick="return confirm('Apakah Anda yakin ingin logout?');">
      <i class="fa-solid fa-arrow-right-from-bracket"></i>
      <span>Logout</span>
    </a>
  </div>
</aside>

