<?php
/**
 * JAZUVA - Restaurant Settings (Pengaturan)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['admin']);

$pdo = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfPost();
    $settingsToUpdate = [
        'restaurant_name'    => trim($_POST['restaurant_name'] ?? 'Jazuva'),
        'restaurant_tagline' => trim($_POST['restaurant_tagline'] ?? 'Restaurant & Coffee Shop'),
        'restaurant_slogan'  => trim($_POST['restaurant_slogan'] ?? 'Good Food. Good Coffee. Good Mood.'),
        'restaurant_address' => trim($_POST['restaurant_address'] ?? ''),
        'restaurant_phone'   => trim($_POST['restaurant_phone'] ?? ''),
        'restaurant_email'   => trim($_POST['restaurant_email'] ?? ''),
        'tax_percentage'     => trim($_POST['tax_percentage'] ?? '0'),
        'service_charge'     => trim($_POST['service_charge'] ?? '0'),
    ];

    if (
        !is_numeric($settingsToUpdate['tax_percentage']) ||
        !is_numeric($settingsToUpdate['service_charge']) ||
        (float)$settingsToUpdate['tax_percentage'] < 0 ||
        (float)$settingsToUpdate['tax_percentage'] > 100 ||
        (float)$settingsToUpdate['service_charge'] < 0 ||
        (float)$settingsToUpdate['service_charge'] > 100
    ) {
        setFlash('error', 'Pajak dan service charge harus berupa angka antara 0 dan 100 persen.');
        header('Location: ' . BASE_URL . '/admin/settings.php');
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                           ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    foreach ($settingsToUpdate as $key => $val) {
        $stmt->execute([$key, $val]);
    }

    setFlash('success', 'Pengaturan restoran berhasil disimpan!');
    header('Location: ' . BASE_URL . '/admin/settings.php');
    exit;
}

$pageTitle = 'Pengaturan Restoran';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
  <div>
    <h2 style="font-size: 22px; font-weight: 800; color: var(--color-primary);">Pengaturan Sistem Restoran</h2>
    <p style="font-size: 13px; color: var(--color-text-muted);">Konfigurasi identitas restoran, pajak, dan manajemen akun pengguna.</p>
  </div>

  <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-secondary">
    <i class="fa-solid fa-users-gear"></i> Kelola Akun Pengguna
  </a>
</div>

<div style="max-width: 800px; margin: 0 auto;">
  <div class="card">
    <form method="POST" action="">
      <?= csrfField() ?>
      <h3 style="font-size: 16px; font-weight: 800; color: var(--color-primary); margin-bottom: 16px; border-bottom: 1px solid var(--color-border); padding-bottom: 8px;">
        Identitas &amp; Informasi Restoran
      </h3>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
        <div class="form-group">
          <label class="form-label">Nama Aplikasi / Restoran</label>
          <input type="text" name="restaurant_name" class="form-control" value="<?= htmlspecialchars(getSetting('restaurant_name', 'Jazuva')) ?>" required>
        </div>

        <div class="form-group">
          <label class="form-label">Subjudul (Tagline)</label>
          <input type="text" name="restaurant_tagline" class="form-control" value="<?= htmlspecialchars(getSetting('restaurant_tagline', 'Restaurant & Coffee Shop')) ?>">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Slogan Restoran</label>
        <input type="text" name="restaurant_slogan" class="form-control" value="<?= htmlspecialchars(getSetting('restaurant_slogan', 'Good Food. Good Coffee. Good Mood.')) ?>">
      </div>

      <div class="form-group">
        <label class="form-label">Alamat Lengkap</label>
        <textarea name="restaurant_address" class="form-control"><?= htmlspecialchars(getSetting('restaurant_address', 'Jl. Kaliurang KM 5.5, Yogyakarta')) ?></textarea>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
        <div class="form-group">
          <label class="form-label">Nomor Telepon / WhatsApp</label>
          <input type="text" name="restaurant_phone" class="form-control" value="<?= htmlspecialchars(getSetting('restaurant_phone', '0812-3456-7890')) ?>">
        </div>

        <div class="form-group">
          <label class="form-label">Email Kontak</label>
          <input type="email" name="restaurant_email" class="form-control" value="<?= htmlspecialchars(getSetting('restaurant_email', 'info@jazuva.com')) ?>">
        </div>
      </div>

      <h3 style="font-size: 16px; font-weight: 800; color: var(--color-primary); margin: 24px 0 16px 0; border-bottom: 1px solid var(--color-border); padding-bottom: 8px;">
        Pajak &amp; Biaya Layanan
      </h3>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
        <div class="form-group">
          <label class="form-label">Pajak Restoran / PB1 (%)</label>
          <input type="number" min="0" max="100" step="0.1" name="tax_percentage" class="form-control" value="<?= htmlspecialchars(getSetting('tax_percentage', '0')) ?>">
          <span style="font-size: 11px; color: var(--color-text-muted);">Masukkan 0 jika harga menu sudah nett / all-in.</span>
        </div>

        <div class="form-group">
          <label class="form-label">Service Charge (%)</label>
          <input type="number" min="0" max="100" step="0.1" name="service_charge" class="form-control" value="<?= htmlspecialchars(getSetting('service_charge', '0')) ?>">
        </div>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 24px;">
        <button type="submit" class="btn btn-primary btn-lg">
          <i class="fa-solid fa-floppy-disk"></i> Simpan Pengaturan
        </button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
