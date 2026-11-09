<?php
/**
 * Public staff registration is disabled. Staff accounts must be created by an administrator.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    redirectBasedOnRole($_SESSION['role_name'] ?? '');
}

http_response_code(403);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pendaftaran Dinonaktifkan - Jazuva</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 30px 20px;">
  <main class="card" style="width: 100%; max-width: 480px; padding: 36px; text-align: center;">
    <h1 style="font-size: 24px; color: var(--color-primary);">Pendaftaran Dinonaktifkan</h1>
    <p style="margin: 16px 0; color: var(--color-text-muted);">
      Akun staf Jazuva hanya dapat dibuat oleh administrator.
    </p>
    <a href="<?= BASE_URL ?>/login.php" class="btn btn-primary">Kembali ke Login</a>
  </main>
</body>
</html>
