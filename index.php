<?php
if (isset($_GET['r'])) {
    require_once __DIR__ . '/app/bootstrap.php';
    createApplication()->run();
    exit;
}

/**
 * JAZUVA - Landing Page
 * Matches Prototype Landing Page Design & Identity
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = getDbConnection();

// Fetch favorite menus
$stmtFav = $pdo->query("SELECT m.*, c.name as category_name 
                        FROM menus m 
                        JOIN categories c ON m.category_id = c.id 
                        WHERE m.is_available = 1 AND m.is_favorite = 1
                        ORDER BY m.is_favorite DESC, m.id ASC LIMIT 6");
$favoriteMenus = $stmtFav->fetchAll();
$dashboardPaths = [
    'admin' => '/admin/dashboard.php',
    'cashier' => '/cashier/dashboard.php',
    'kitchen' => '/kitchen/dashboard.php',
    'barista' => '/barista/dashboard.php',
    'inventory' => '/inventory/dashboard.php'
];
$dashboardPath = $dashboardPaths[strtolower($_SESSION['role_name'] ?? '')] ?? '/index.php';

?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Jazuva – Restaurant &amp; Coffee Shop</title>
  
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  
  <style>
    /* Landing Page Specific Header & Hero */
    .landing-header {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      z-index: 50;
      padding: 24px 60px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: linear-gradient(to bottom, rgba(20, 10, 5, 0.85), transparent);
    }

    .landing-brand {
      display: flex;
      align-items: center;
      gap: 12px;
      color: #FFFFFF;
      text-decoration: none;
    }

    .landing-brand-icon {
      font-size: 26px;
      color: #C69255;
    }

    .landing-brand-name {
      font-family: 'Playfair Display', serif;
      font-size: 26px;
      font-weight: 700;
      letter-spacing: 0.5px;
      color: #FFFFFF;
    }

    .landing-nav {
      display: flex;
      gap: 32px;
      align-items: center;
    }

    .landing-nav-link {
      color: #E8DDD2;
      font-size: 14.5px;
      font-weight: 500;
      transition: var(--transition-fast);
      text-decoration: none;
    }

    .landing-nav-link:hover, .landing-nav-link.active {
      color: #C69255;
    }

    .btn-landing-login {
      background: #FFFFFF;
      color: #2C1810;
      padding: 9px 24px;
      border-radius: var(--radius-pill);
      font-weight: 700;
      font-size: 13.5px;
      transition: var(--transition-fast);
    }

    .btn-landing-login:hover {
      background: #C69255;
      color: #FFFFFF;
      transform: translateY(-2px);
    }

    /* Hero Section (Exact Prototype Visuals) */
    .hero-section {
      min-height: 92vh;
      background: linear-gradient(135deg, rgba(25, 12, 6, 0.88) 0%, rgba(45, 23, 14, 0.82) 50%, rgba(20, 10, 5, 0.94) 100%), 
                  url('<?= BASE_URL ?>/assets/images/hero-bg.svg') center/cover no-repeat;
      color: #FFFFFF;
      display: flex;
      align-items: center;
      padding: 120px 60px 60px 60px;
      position: relative;
    }

    .hero-content {
      max-width: 620px;
      z-index: 2;
    }

    .hero-title {
      font-family: 'Playfair Display', serif;
      font-size: 58px;
      font-weight: 700;
      line-height: 1.1;
      margin-bottom: 8px;
      color: #FFFFFF;
    }

    .hero-subtitle {
      font-size: 24px;
      font-weight: 600;
      color: #C69255;
      margin-bottom: 18px;
      letter-spacing: 0.5px;
    }

    .hero-slogan {
      font-size: 17px;
      font-weight: 700;
      color: #EADBC8;
      margin-bottom: 12px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .hero-slogan::before {
      content: '';
      display: inline-block;
      width: 28px;
      height: 2px;
      background: #C69255;
    }

    .hero-desc {
      font-size: 15px;
      color: #D3C2B4;
      line-height: 1.6;
      margin-bottom: 32px;
    }

    .hero-cta {
      display: flex;
      gap: 16px;
      align-items: center;
    }

    .btn-hero-primary {
      background: #C69255;
      color: #FFFFFF;
      padding: 14px 34px;
      border-radius: var(--radius-pill);
      font-weight: 700;
      font-size: 15px;
      display: inline-flex;
      align-items: center;
      gap: 10px;
      box-shadow: 0 6px 20px rgba(198, 146, 85, 0.35);
      transition: var(--transition-fast);
    }

    .btn-hero-primary:hover {
      background: #8B5A2B;
      color: #FFFFFF;
      transform: translateY(-2px);
    }

    .hero-visual {
      position: absolute;
      right: 80px;
      top: 50%;
      transform: translateY(-45%);
      z-index: 1;
    }

    .hero-coffee-badge {
      width: 380px;
      height: 380px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(198, 146, 85, 0.25) 0%, transparent 70%);
      display: flex;
      align-items: center;
      justify-content: center;
      animation: floatBadge 4s ease-in-out infinite;
    }

    @keyframes floatBadge {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-12px); }
    }

    /* Section Containers */
    .section-container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 70px 24px;
    }

    .section-header-center {
      text-align: center;
      margin-bottom: 44px;
    }

    .section-tag {
      font-size: 12px;
      font-weight: 800;
      color: #8B5A2B;
      text-transform: uppercase;
      letter-spacing: 1.5px;
      margin-bottom: 8px;
      display: block;
    }

    .section-main-title {
      font-family: 'Playfair Display', serif;
      font-size: 34px;
      font-weight: 700;
      color: var(--color-primary);
    }

    .featured-menu-grid {
      max-width: 1040px;
      margin: 0 auto;
    }

    .featured-menu-grid .menu-card-img-wrap {
      display: grid;
      place-items: center;
      height: 130px;
    }

    .featured-menu-grid .menu-card-img {
      width: 85%;
      max-width: 180px;
      height: 118px;
      border-radius: var(--radius-md);
      margin: 0;
    }

    /* Table Quick Selector Bar */
    .table-picker-box {
      background: #FFFFFF;
      border-radius: var(--radius-xl);
      padding: 30px;
      box-shadow: var(--shadow-card);
      border: 1px solid var(--color-border);
      margin-top: -50px;
      position: relative;
      z-index: 10;
    }

    .table-pills-row {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      justify-content: center;
      margin-top: 16px;
    }

    .table-btn-pill {
      padding: 10px 20px;
      border-radius: var(--radius-pill);
      background: #FAF5EE;
      border: 1.5px solid var(--color-border);
      color: var(--color-primary);
      font-weight: 700;
      font-size: 13.5px;
      text-decoration: none;
      display: flex;
      align-items: center;
      gap: 8px;
      transition: var(--transition-fast);
    }

    .table-btn-pill:hover {
      background: #8B5A2B;
      color: #FFFFFF;
      border-color: #8B5A2B;
      transform: translateY(-2px);
    }

    /* Footer */
    .landing-footer {
      background: #24120A;
      color: #C4B5A5;
      padding: 50px 24px 24px 24px;
      border-top: 1px solid rgba(255, 255, 255, 0.08);
    }

    .landing-footer-grid {
      max-width: 1200px;
      margin: 0 auto 30px;
      display: grid;
      grid-template-columns: 2fr 1fr 1fr;
      gap: 40px;
    }

    @media (max-width: 900px) {
      .landing-header { padding: 20px; }
      .landing-nav { display: none; }
      .hero-section { padding: 100px 20px 40px 20px; }
      .hero-visual { display: none; }
      .hero-title { font-size: 40px; }
      .landing-footer-grid { grid-template-columns: 1fr; gap: 24px; }
    }
  </style>
</head>
<body>

  <!-- Landing Header -->
  <header class="landing-header">
    <a href="<?= BASE_URL ?>/index.php" class="landing-brand">
      <i class="fa-solid fa-mug-hot landing-brand-icon"></i>
      <span class="landing-brand-name">Jazuva</span>
    </a>

    <nav class="landing-nav">
      <a href="#beranda" class="landing-nav-link active">Beranda</a>
      <a href="#menu" class="landing-nav-link">Menu</a>
      <a href="#cara-pesan" class="landing-nav-link">Cara Pesan</a>
      <a href="#kontak" class="landing-nav-link">Kontak</a>
      <a href="<?= BASE_URL ?>/scan.php" class="landing-nav-link"><i class="fa-solid fa-qrcode"></i> Scan QR</a>
    </nav>

    <div style="display: flex; gap: 10px;">
      <?php if (isLoggedIn()): ?>
        <a href="<?= BASE_URL . $dashboardPath ?>" class="btn-landing-login">
          <i class="fa-solid fa-gauge"></i> Dashboard (<?= htmlspecialchars($_SESSION['username'] ?? '') ?>)
        </a>
      <?php else: ?>
        <a href="<?= BASE_URL ?>/login.php" class="btn-landing-login">
          <i class="fa-solid fa-arrow-right-to-bracket"></i> Masuk
        </a>
      <?php endif; ?>
    </div>
  </header>

  <!-- Hero Section -->
  <section class="hero-section" id="beranda">
    <div class="hero-content">
      <h1 class="hero-title">Jazuva</h1>
      <div class="hero-subtitle">Restaurant &amp; Coffee Shop</div>
      
      <div class="hero-slogan">
        Good Food. Good Coffee. Good Mood.
      </div>

      <p class="hero-desc">
        Nikmati pengalaman bersantap terbaik dengan suasana yang nyaman, racikan kopi artisan nusantara, dan hidangan lezat penuh cita rasa.
      </p>

      <div class="hero-cta">
        <a href="#menu" class="btn-hero-primary">
          <span>Lihat Menu</span>
          <i class="fa-solid fa-arrow-right"></i>
        </a>
        <a href="<?= BASE_URL ?>/scan.php" class="btn btn-secondary btn-lg" style="color: #FFFFFF; background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.3);">
          <i class="fa-solid fa-qrcode"></i> Scan QR Meja
        </a>
      </div>
    </div>

    <!-- Coffee art visual matching prototype -->
    <div class="hero-visual">
      <div class="hero-coffee-badge">
        <div style="text-align: center; color: #FAF5EE;">
          <div style="font-size: 110px; line-height: 1;">☕</div>
          <div style="font-family: 'Playfair Display', serif; font-size: 20px; font-weight: 700; margin-top: 10px; color: #C69255;">
            Artisan Roastery
          </div>
          <div style="font-size: 12px; color: #D3C2B4; margin-top: 4px;">Since 2025</div>
        </div>
      </div>
    </div>
  </section>

  <!-- Customer ordering begins by scanning the physical table QR. -->
  <div style="max-width: 1100px; margin: 0 auto; padding: 0 20px;">
    <div class="table-picker-box">
      <div style="text-align: center;">
        <span class="section-tag"><i class="fa-solid fa-qrcode"></i> Pemesanan Mandiri di Meja</span>
        <h3 style="font-size: 20px; font-weight: 800; color: var(--color-primary);">Pindai QR Resmi di Meja Anda</h3>
        <p style="font-size: 13px; color: var(--color-text-muted); margin-top: 4px;">
          QR unik pada meja akan membuka menu untuk nomor meja yang benar.
        </p>
      </div>

      <div style="display: flex; justify-content: center; margin-top: 18px;">
        <a href="<?= BASE_URL ?>/scan.php" class="btn btn-primary">
          <i class="fa-solid fa-qrcode"></i> Buka Pemindai QR
        </a>
      </div>
    </div>
  </div>

  <!-- Featured Menus Section -->
  <section class="section-container" id="menu">
    <div class="section-header-center">
      <span class="section-tag">Menu Pilihan Terbaik</span>
      <h2 class="section-main-title">Kelezatan di Setiap Gigitan &amp; Tegukan</h2>
      <p style="font-size: 14px; color: var(--color-text-muted); margin-top: 8px;">
        Diramu dengan bahan baku segar berkualitas tinggi dan resep istimewa khas Jazuva.
      </p>
    </div>

    <div class="menu-grid featured-menu-grid">
      <?php foreach ($favoriteMenus as $item): ?>
        <div class="menu-card">
          <div class="menu-card-img-wrap">
            <img src="<?= BASE_URL ?>/uploads/menus/<?= htmlspecialchars($item['image'] ?: 'default-food.jpg') ?>" 
                 alt="<?= htmlspecialchars($item['name']) ?>" class="menu-card-img"
                 onerror="this.onerror=null; this.src='<?= BASE_URL ?>/uploads/menus/default-food.jpg';">
            <?php if ($item['is_favorite']): ?>
              <span class="badge badge-menunggu menu-card-badge">
                <i class="fa-solid fa-star"></i> Favorit
              </span>
            <?php endif; ?>
          </div>
          <div class="menu-card-body">
            <div style="font-size: 11px; font-weight: 700; color: #C69255; text-transform: uppercase; margin-bottom: 2px;">
              <?= htmlspecialchars($item['category_name']) ?>
            </div>
            <h4 class="menu-card-title"><?= htmlspecialchars($item['name']) ?></h4>
            <div class="menu-card-price"><?= formatRupiah($item['price']) ?></div>
            
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- How It Works Section -->
  <section class="section-container" id="cara-pesan" style="background: #FAF6F1; border-radius: var(--radius-xl); margin-bottom: 60px;">
    <div class="section-header-center">
      <span class="section-tag">Proses Cepat &amp; Praktis</span>
      <h2 class="section-main-title">Cara Memesan di Jazuva</h2>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 24px;">
      <div class="card" style="text-align: center; border: none;">
        <div style="width: 60px; height: 60px; background: #331B11; color: #C69255; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 16px;">
          <i class="fa-solid fa-chair"></i>
        </div>
        <h4 style="font-size: 16px; margin-bottom: 8px;">1. Duduk di Meja</h4>
        <p style="font-size: 13px; color: var(--color-text-muted);">Pilih meja yang Anda sukai di area indoor atau outdoor resto.</p>
      </div>

      <div class="card" style="text-align: center; border: none;">
        <div style="width: 60px; height: 60px; background: #331B11; color: #C69255; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 16px;">
          <i class="fa-solid fa-qrcode"></i>
        </div>
        <h4 style="font-size: 16px; margin-bottom: 8px;">2. Pindai QR Code</h4>
        <p style="font-size: 13px; color: var(--color-text-muted);">Gunakan kamera smartphone untuk memindai stand QR di meja Anda.</p>
      </div>

      <div class="card" style="text-align: center; border: none;">
        <div style="width: 60px; height: 60px; background: #331B11; color: #C69255; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 16px;">
          <i class="fa-solid fa-utensils"></i>
        </div>
        <h4 style="font-size: 16px; margin-bottom: 8px;">3. Pilih &amp; Kirim</h4>
        <p style="font-size: 13px; color: var(--color-text-muted);">Pilih menu, tambahkan catatan, dan pesanan otomatis masuk ke dapur &amp; barista.</p>
      </div>

      <div class="card" style="text-align: center; border: none;">
        <div style="width: 60px; height: 60px; background: #331B11; color: #C69255; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 16px;">
          <i class="fa-solid fa-bell-concierge"></i>
        </div>
        <h4 style="font-size: 16px; margin-bottom: 8px;">4. Santap &amp; Bayar</h4>
        <p style="font-size: 13px; color: var(--color-text-muted);">Pesanan diantar ke meja Anda. Bayar di kasir secara tunai atau QRIS saat selesai.</p>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="landing-footer" id="kontak">
    <div class="landing-footer-grid">
      <div>
        <div class="landing-brand" style="margin-bottom: 14px;">
          <i class="fa-solid fa-mug-hot landing-brand-icon"></i>
          <span class="landing-brand-name">Jazuva</span>
        </div>
        <p style="font-size: 13.5px; line-height: 1.6; max-width: 380px;">
          Jazuva Restaurant &amp; Coffee Shop menghadirkan perpaduan cita rasa kuliner nusantara dan sajian kopi spesial dengan atmosfer hangat dan berkelas.
        </p>
      </div>

      <div>
        <h4 style="color: #FFFFFF; font-size: 15px; margin-bottom: 14px;">Jam Operasional</h4>
        <p style="font-size: 13px; margin-bottom: 6px;">Senin – Jumat: 08.00 – 23.00 WIB</p>
        <p style="font-size: 13px; margin-bottom: 6px;">Sabtu – Minggu: 07.00 – 24.00 WIB</p>
        <p style="font-size: 13px; color: #C69255;">Buka Setiap Hari Termasuk Libur Nasional</p>
      </div>

      <div>
        <h4 style="color: #FFFFFF; font-size: 15px; margin-bottom: 14px;">Lokasi &amp; Kontak</h4>
        <p style="font-size: 13px; margin-bottom: 6px;"><i class="fa-solid fa-location-dot" style="color: #C69255;"></i> Jl. Kaliurang KM 5.5, Yogyakarta</p>
        <p style="font-size: 13px; margin-bottom: 6px;"><i class="fa-solid fa-phone" style="color: #C69255;"></i> 0812-3456-7890</p>
        <p style="font-size: 13px;"><i class="fa-solid fa-envelope" style="color: #C69255;"></i> info@jazuva.com</p>
      </div>
    </div>

    <div style="text-align: center; border-top: 1px solid rgba(255, 255, 255, 0.08); padding-top: 20px; font-size: 12px;">
      &copy; <?= date('Y') ?> JAZUVA – Restaurant &amp; Coffee Shop Management System. All Rights Reserved.
    </div>
  </footer>

</body>
</html>
