<?php
/**
 * JAZUVA - Printable QR Table Tent Stands
 * Matches Mobile Frame / Stand Preview in Prototype
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['admin']);

$pdo = getDbConnection();
$tables = $pdo->query("SELECT * FROM restaurant_tables WHERE is_active = 1 ORDER BY table_number ASC")->fetchAll();
$filterTable = trim($_GET['meja'] ?? '');

if (!empty($filterTable)) {
    $tables = array_filter($tables, fn($t) => $t['table_number'] === $filterTable);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cetak QR Stand Meja - Jazuva</title>
  
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

  <style>
    body {
      background: #F4EDE4;
      padding: 30px;
    }
    .print-toolbar {
      max-width: 900px;
      margin: 0 auto 30px auto;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .stands-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 30px;
      max-width: 1000px;
      margin: 0 auto;
    }
    /* Prototype Stand Card Design */
    .stand-card {
      background: #FFFFFF;
      border-radius: 28px;
      border: 4px solid #331B11;
      padding: 32px 24px;
      text-align: center;
      box-shadow: 0 10px 25px rgba(51, 27, 17, 0.15);
      position: relative;
      display: flex;
      flex-direction: column;
      align-items: center;
      page-break-inside: avoid;
    }
    .stand-brand {
      display: flex;
      align-items: center;
      gap: 8px;
      font-family: 'Playfair Display', serif;
      font-size: 24px;
      font-weight: 700;
      color: #331B11;
      margin-bottom: 12px;
    }
    .stand-table-title {
      font-size: 19px;
      font-weight: 800;
      color: #331B11;
      margin-bottom: 20px;
    }
    .stand-qr-box {
      background: #FFF;
      padding: 16px;
      border-radius: 16px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
      margin-bottom: 18px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .stand-slogan {
      font-size: 13.5px;
      font-weight: 700;
      color: #8B5A2B;
    }
    .stand-footer-sub {
      font-size: 11px;
      color: #888;
      margin-top: 4px;
    }
    @media print {
      body { background: #FFF; padding: 0; }
      .print-toolbar { display: none; }
      .stands-grid { gap: 20px; }
      .stand-card { box-shadow: none; border-width: 2px; }
    }
  </style>
</head>
<body>

  <div class="print-toolbar">
    <div>
      <h2 style="font-size: 20px; font-weight: 800; color: #331B11;">Stand QR Meja Restoran</h2>
      <p style="font-size: 13px; color: #777;">Siap cetak untuk diletakkan di atas meja pelanggan.</p>
    </div>

    <div style="display: flex; gap: 10px;">
      <a href="<?= BASE_URL ?>/admin/tables.php" class="btn btn-secondary">
        <i class="fa-solid fa-arrow-left"></i> Kembali
      </a>
      <button type="button" class="btn btn-primary" onclick="window.print()">
        <i class="fa-solid fa-print"></i> Cetak Stand QR
      </button>
    </div>
  </div>

  <div class="stands-grid">
    <?php foreach ($tables as $tbl): ?>
      <div class="stand-card">
        <div class="stand-brand">
          <i class="fa-solid fa-mug-hot" style="color: #C69255;"></i>
          <span>Jazuva</span>
        </div>

        <div class="stand-table-title">Menu Meja No. <?= htmlspecialchars($tbl['table_number']) ?></div>

        <div class="stand-qr-box">
          <div class="qr-render-target" data-table="<?= htmlspecialchars($tbl['table_number'], ENT_QUOTES, 'UTF-8') ?>" data-token="<?= htmlspecialchars($tbl['token'], ENT_QUOTES, 'UTF-8') ?>" style="width: 180px; height: 180px;"></div>
        </div>

        <div class="stand-slogan">Scan untuk memesan menu</div>
        <div class="stand-footer-sub">Buka kamera smartphone &amp; arahkan ke QR</div>
      </div>
    <?php endforeach; ?>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const targets = document.querySelectorAll('.qr-render-target');
      targets.forEach(el => {
        const tableNum = el.getAttribute('data-table');
        const tableToken = el.getAttribute('data-token');
        const url = `<?= BASE_URL ?>/menu.php?meja=${encodeURIComponent(tableNum)}&token=${encodeURIComponent(tableToken)}`;
        new QRCode(el, {
          text: url,
          width: 180,
          height: 180,
          colorDark: "#2F1A10",
          colorLight: "#FFFFFF",
          correctLevel: QRCode.CorrectLevel.H
        });
      });
    });
  </script>
</body>
</html>
