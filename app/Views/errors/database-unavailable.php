<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Koneksi database belum tersedia | Jazuva</title>
  <link rel="stylesheet" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/css/responsive.css">
  <style>
    body{min-height:100vh;display:grid;place-items:center;padding:24px;background:#FBF8F4;color:#331B11}
    .database-error{width:min(100%,640px);padding:36px;border:1px solid #E8DDD2;border-radius:20px;background:#fff;box-shadow:0 16px 44px rgba(51,27,17,.12)}
    .database-error h1{margin:0 0 12px;font-size:clamp(23px,5vw,32px)}
    .database-error p,.database-error li{line-height:1.65;color:#5D5149}
    .database-error h2{margin:24px 0 8px;font-size:17px}
    .database-error code{padding:2px 6px;border-radius:5px;background:#FBF3E8;color:#6D4323}
    .database-error a{display:inline-block;margin-top:12px;padding:11px 18px;border-radius:24px;background:#8B5A2B;color:#fff;text-decoration:none}
  </style>
</head>
<body>
  <main class="database-error" role="alert">
    <h1>Database Jazuva belum dapat dihubungi</h1>
    <p>Aplikasi tidak bisa membaca data karena layanan MySQL/MariaDB di XAMPP belum menerima koneksi. Data proyek tidak dihapus atau diubah.</p>
    <h2>Langkah perbaikan</h2>
    <ol>
      <li>Buka <strong>XAMPP Control Panel</strong> dan klik <strong>Start</strong> pada baris <strong>MySQL</strong>.</li>
      <li>Pastikan status MySQL menjadi <strong>Running</strong> dan port sesuai konfigurasi di <code>config/database.php</code> (default <code>3306</code>).</li>
      <li>Jika MySQL gagal berjalan, periksa log <code>C:\xampp\mysql\data\mysql_error.log</code> dan pastikan port tersebut tidak digunakan aplikasi lain.</li>
      <li>Muat ulang halaman ini. Jika database belum pernah disiapkan, jalankan <code>C:\xampp\php\php.exe setup.php</code> dari folder proyek setelah MySQL aktif.</li>
    </ol>
    <a href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/">Coba buka Jazuva lagi</a>
  </main>
</body>
</html>
