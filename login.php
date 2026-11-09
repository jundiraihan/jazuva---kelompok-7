<?php
/**
 * JAZUVA - Login Page
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/app/Core/Security.php';

// If already logged in, redirect directly to dashboard
if (isLoggedIn()) {
    redirectBasedOnRole($_SESSION['role_name'] ?? '');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!validateCsrfToken($csrfToken)) {
        $error = 'Validasi keamanan CSRF gagal. Silakan coba kembali.';
    } elseif (empty($username) || empty($password)) {
        $error = 'Username dan password wajib diisi.';
    } else {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT u.*, r.name as role_name, r.display_name as role_display 
                               FROM users u 
                               JOIN roles r ON u.role_id = r.id 
                               WHERE u.username = ? AND u.status = 'active' LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && Security::verifyPassword($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['role_name'] = $user['role_name'];
            $_SESSION['role_display'] = $user['role_display'];
            $_SESSION['avatar'] = $user['avatar'];

            setFlash('success', 'Selamat datang kembali, ' . $user['full_name'] . '!');
            redirectBasedOnRole($user['role_name']);
        } else {
            $error = 'Username atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Jazuva Restaurant &amp; Coffee Shop</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <style>
    body {
      background: linear-gradient(135deg, #24120A 0%, #3B2314 50%, #1A0D07 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .login-container {
      width: 100%;
      max-width: 440px;
      background: #FFFFFF;
      border-radius: var(--radius-xl);
      padding: 40px 36px;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
    }
    .login-brand {
      text-align: center;
      margin-bottom: 28px;
    }
    .brand-icon-box {
      width: 58px;
      height: 58px;
      background: #FAF3E8;
      color: #8B5A2B;
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 26px;
      margin-bottom: 12px;
      border: 2px solid #E8DDD2;
    }
    .login-brand h1 {
      font-family: 'Playfair Display', serif;
      font-size: 28px;
      font-weight: 700;
      color: var(--color-primary);
    }
    .login-brand p {
      font-size: 13px;
      color: var(--color-text-muted);
      margin-top: 4px;
    }
    .demo-accounts {
      margin-top: 24px;
      background: #FAF6F1;
      border: 1px dashed var(--color-border);
      border-radius: var(--radius-md);
      padding: 14px;
    }
    .demo-title {
      font-size: 12px;
      font-weight: 700;
      color: var(--color-primary);
      margin-bottom: 8px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .demo-btn-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 6px;
    }
    .demo-pill {
      font-size: 11px;
      padding: 6px 4px;
      text-align: center;
      background: #FFFFFF;
      border: 1px solid var(--color-border);
      border-radius: var(--radius-sm);
      cursor: pointer;
      color: var(--color-text-main);
      font-weight: 600;
      transition: var(--transition-fast);
    }
    .demo-pill:hover {
      background: var(--color-primary);
      color: #FFFFFF;
      border-color: var(--color-primary);
    }
  </style>
</head>
<body>

<div class="login-container">
  <div class="login-brand">
    <div class="brand-icon-box">
      <i class="fa-solid fa-mug-hot"></i>
    </div>
    <h1>Jazuva</h1>
    <p>Restaurant &amp; Coffee Shop Management System</p>
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger">
      <i class="fa-solid fa-circle-exclamation"></i>
      <div><?= htmlspecialchars($error) ?></div>
    </div>
  <?php endif; ?>

  <form method="POST" action="">
    <?= csrfField() ?>

    <div class="form-group">
      <label class="form-label" for="username">Username</label>
      <div style="position: relative;">
        <input type="text" id="username" name="username" class="form-control" placeholder="Masukkan username" required autofocus value="admin">
      </div>
    </div>

    <div class="form-group">
      <label class="form-label" for="password">Password</label>
      <input type="password" id="password" name="password" class="form-control" placeholder="Masukkan password" required value="admin123">
    </div>

    <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top: 10px;">
      <i class="fa-solid fa-right-to-bracket"></i>
      <span>Masuk ke Sistem</span>
    </button>
  </form>

  <div class="demo-accounts">
    <div class="demo-title"><i class="fa-solid fa-key"></i> Quick Login (Semua Password: admin123)</div>
    <div class="demo-btn-grid">
      <button type="button" class="demo-pill" onclick="fillLogin('admin')">Admin</button>
      <button type="button" class="demo-pill" onclick="fillLogin('kasir')">Kasir (POS)</button>
      <button type="button" class="demo-pill" onclick="fillLogin('kitchen')">Kitchen</button>
      <button type="button" class="demo-pill" onclick="fillLogin('barista')">Barista</button>
      <button type="button" class="demo-pill" onclick="fillLogin('inventory')">Inventory</button>
      <a href="<?= BASE_URL ?>/index.php" class="demo-pill" style="text-decoration:none; display:flex; align-items:center; justify-content:center;">Landing</a>
    </div>
  </div>

  <div style="margin-top: 20px;">
    <a href="<?= BASE_URL ?>/index.php#menu" class="btn btn-secondary btn-block">
      <i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard Pemesanan
    </a>
    <p style="text-align: center; font-size: 11.5px; color: var(--color-text-muted); margin-top: 8px;">
      Untuk memesan, pindai QR resmi di meja dari halaman utama.
    </p>
  </div>
</div>

<script>
function fillLogin(user) {
  document.getElementById('username').value = user;
  document.getElementById('password').value = 'admin123';
}
</script>

</body>
</html>
