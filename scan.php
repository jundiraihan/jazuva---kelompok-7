<?php
/**
 * JAZUVA - QR Code Scanner Page
 * Supports Smartphone Camera scanning and quick table selection
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = getDbConnection();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Scan QR Meja - Jazuva</title>
  
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  
  <!-- html5-qrcode library for camera scanning -->
  <script src="https://unpkg.com/html5-qrcode"></script>

  <style>
    body {
      background: #24120A;
      color: #FFFFFF;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }
    .scanner-header {
      padding: 18px 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: rgba(0,0,0,0.3);
      border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    .scanner-box {
      max-width: 460px;
      margin: 20px auto;
      padding: 24px;
      background: #331B11;
      border-radius: var(--radius-xl);
      box-shadow: 0 10px 30px rgba(0,0,0,0.5);
      text-align: center;
      width: 90%;
    }
    #reader {
      width: 100%;
      border-radius: var(--radius-lg);
      overflow: hidden;
      margin-bottom: 20px;
      background: #000;
    }
    .manual-table-btn {
      padding: 8px 14px;
      border-radius: var(--radius-pill);
      background: rgba(255,255,255,0.1);
      color: #FFF;
      font-size: 13px;
      font-weight: 600;
      border: 1px solid rgba(255,255,255,0.2);
      text-decoration: none;
      transition: var(--transition-fast);
    }
    .manual-table-btn:hover {
      background: #C69255;
      border-color: #C69255;
    }
  </style>
</head>
<body>

  <header class="scanner-header">
    <a href="<?= BASE_URL ?>/index.php" style="color: #FFF; font-size: 14px; display: flex; align-items: center; gap: 8px;">
      <i class="fa-solid fa-arrow-left"></i> Beranda
    </a>
    <div style="font-family: 'Playfair Display', serif; font-size: 20px; font-weight: 700; color: #C69255;">
      Jazuva QR Scanner
    </div>
    <div style="width: 60px;"></div>
  </header>

  <div class="scanner-box">
    <div style="margin-bottom: 16px;">
      <i class="fa-solid fa-qrcode" style="font-size: 36px; color: #C69255; margin-bottom: 8px;"></i>
      <h2 style="font-size: 18px; color: #FFFFFF; margin-bottom: 4px;">Pindai QR Code di Meja Anda</h2>
      <p style="font-size: 12.5px; color: #C4B5A5;">Arahkan kamera smartphone ke QR Code yang tertempel di meja resto.</p>
    </div>

    <!-- QR Reader Camera Container -->
    <div id="reader"></div>

    <div id="scan-result" style="display: none; padding: 12px; background: #27AE60; border-radius: var(--radius-md); margin-bottom: 16px;">
      <i class="fa-solid fa-circle-check"></i> QR Code Terdeteksi! Membuka menu...
    </div>

    <p style="font-size: 12px; color: #C4B5A5;">Jika pemindai tidak tersedia, minta bantuan staf untuk membuka menu meja.</p>
  </div>

  <script>
    function onScanSuccess(decodedText, decodedResult) {
      console.log(`Code matched = ${decodedText}`, decodedResult);
      const result = document.getElementById('scan-result');
      try {
        const scannedUrl = new URL(decodedText);
        const menuUrl = new URL('<?= BASE_URL ?>/menu.php');
        const table = scannedUrl.searchParams.get('meja');
        const token = scannedUrl.searchParams.get('token');
        if (scannedUrl.origin !== menuUrl.origin || scannedUrl.pathname !== menuUrl.pathname || !table || !/^[a-f0-9]{64}$/i.test(token || '')) {
          throw new Error('QR Code bukan QR meja Jazuva yang valid.');
        }
        result.style.display = 'block';
        result.innerText = 'QR Code Jazuva terdeteksi. Membuka menu...';
        window.location.href = scannedUrl.href;
      } catch (error) {
        result.style.display = 'block';
        result.style.background = '#B83A32';
        result.innerText = error.message || 'QR Code tidak valid. Pindai QR resmi di meja.';
      }
    }

    function onScanFailure(error) {
      // ignore frame scan failures
    }

    const html5QrcodeScanner = new Html5QrcodeScanner(
      "reader",
      { fps: 10, qrbox: { width: 250, height: 250 } },
      /* verbose= */ false
    );
    html5QrcodeScanner.render(onScanSuccess, onScanFailure);
  </script>
</body>
</html>
