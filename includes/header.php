<?php
/**
 * JAZUVA - Main Admin/Staff Top Header
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$currentUser = getCurrentUser();
$pageTitle = $pageTitle ?? 'Dashboard Restoran';

// Format current date in Indonesian (e.g. "Selasa, 28 Okt 2025")
$days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$dayName = $days[date('w')];
$monthName = $months[(int)date('n')];
$currentDateIndo = $dayName . ', ' . date('d') . ' ' . $monthName . ' ' . date('Y');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> - Jazuva Restaurant &amp; Coffee Shop</title>
  
  <!-- Font Awesome 6 Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <!-- Core App Stylesheets -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/responsive.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dashboard.css">
  <?php if (isset($includePosCss) && $includePosCss): ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/pos.css">
  <?php endif; ?>
</head>
<body>

<div class="app-layout">
  <!-- Sidebar -->
  <?php require_once __DIR__ . '/sidebar.php'; ?>

  <!-- Main Content Area -->
  <div class="main-wrapper">
    <!-- Topbar -->
    <header class="topbar">
      <div class="topbar-title">
        <button type="button" class="sidebar-toggle" aria-label="Buka navigasi" aria-expanded="false">
          <i class="fa-solid fa-bars"></i>
        </button>
        <span><?= htmlspecialchars($pageTitle) ?></span>
      </div>

      <div class="topbar-actions">
        <div class="date-pill">
          <i class="fa-regular fa-calendar" style="color: var(--color-accent);"></i>
          <span><?= $currentDateIndo ?></span>
        </div>

        <div class="user-profile-pill" onclick="window.location.href='<?= BASE_URL ?>/logout.php'" title="Klik untuk Logout">
          <div class="user-avatar">
            <i class="fa-solid fa-user"></i>
          </div>
          <span><?= htmlspecialchars($currentUser['full_name'] ?? 'Pengguna') ?></span>
          <i class="fa-solid fa-chevron-down" style="font-size: 10px; color: var(--color-text-muted);"></i>
        </div>
      </div>
    </header>

    <!-- Page Content Container -->
    <main class="page-content">
      <!-- Flash Alert Messages -->
      <?php if ($successMsg = getFlash('success')): ?>
        <div class="alert alert-success">
          <i class="fa-solid fa-circle-check"></i>
          <div><?= htmlspecialchars($successMsg) ?></div>
        </div>
      <?php endif; ?>

      <?php if ($errorMsg = getFlash('error')): ?>
        <div class="alert alert-danger">
          <i class="fa-solid fa-circle-exclamation"></i>
          <div><?= htmlspecialchars($errorMsg) ?></div>
        </div>
      <?php endif; ?>
