<?php
/**
 * JAZUVA - User Accounts Management (Manajemen Pengguna)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['admin']);

$pdo = getDbConnection();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfPost();
    $postAction = $_POST['post_action'] ?? '';

    if ($postAction === 'save_user') {
        $editId = (int)($_POST['user_id'] ?? 0);
        $roleId = (int)($_POST['role_id'] ?? 2);
        $username = trim($_POST['username'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $status = $_POST['status'] ?? 'active';
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($fullName)) {
            setFlash('error', 'Username dan nama lengkap wajib diisi.');
        } else {
            if ($editId > 0) {
                // Update
                if (!empty($password)) {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE users SET role_id = ?, username = ?, full_name = ?, email = ?, phone = ?, status = ?, password = ? WHERE id = ?");
                    $stmt->execute([$roleId, $username, $fullName, $email, $phone, $status, $hash, $editId]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET role_id = ?, username = ?, full_name = ?, email = ?, phone = ?, status = ? WHERE id = ?");
                    $stmt->execute([$roleId, $username, $fullName, $email, $phone, $status, $editId]);
                }
                setFlash('success', "Akun '{$username}' berhasil diperbarui.");
            } else {
                // Create
                if (empty($password)) $password = 'admin123';
                $hash = password_hash($password, PASSWORD_BCRYPT);

                // Check duplicate username
                $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmtCheck->execute([$username]);
                if ($stmtCheck->fetch()) {
                    setFlash('error', "Username '{$username}' sudah digunakan.");
                } else {
                    $stmt = $pdo->prepare("INSERT INTO users (role_id, username, password, full_name, email, phone, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$roleId, $username, $hash, $fullName, $email, $phone, $status]);
                    setFlash('success', "Akun baru '{$username}' berhasil dibuat.");
                }
            }
        }
        header('Location: ' . BASE_URL . '/admin/users.php');
        exit;
    } elseif ($postAction === 'delete_user') {
        $delId = (int)$_POST['delete_id'];
        if ($delId === (int)getCurrentUser()['id']) {
            setFlash('error', 'Anda tidak dapat menghapus akun Anda sendiri saat sedang login.');
        } else {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$delId]);
            setFlash('success', 'Akun berhasil dihapus.');
        }
        header('Location: ' . BASE_URL . '/admin/users.php');
        exit;
    }
}

// Fetch all roles
$roles = $pdo->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll();

// Fetch all users
$users = $pdo->query("SELECT u.*, r.display_name as role_display 
                      FROM users u 
                      JOIN roles r ON u.role_id = r.id 
                      ORDER BY u.id ASC")->fetchAll();

$pageTitle = 'Manajemen Pengguna';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
  <div>
    <h2 style="font-size: 22px; font-weight: 800; color: var(--color-primary);">Manajemen Akun Pengguna &amp; Hak Akses</h2>
    <p style="font-size: 13px; color: var(--color-text-muted);">Kelola staf kasir, kitchen, barista, dan tim logistik inventaris.</p>
  </div>

  <button type="button" class="btn btn-caramel" onclick="openAddUserModal()">
    <i class="fa-solid fa-user-plus"></i> Tambah Pengguna
  </button>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>Nama Lengkap</th>
          <th>Username</th>
          <th>Role / Jabatan</th>
          <th>Kontak</th>
          <th>Status</th>
          <th style="text-align: center;">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td>
              <div style="display: flex; align-items: center; gap: 10px;">
                <div class="user-avatar" style="width: 32px; height: 32px; font-size: 13px;">
                  <i class="fa-solid fa-user"></i>
                </div>
                <div>
                  <strong><?= htmlspecialchars($u['full_name']) ?></strong>
                  <div style="font-size: 11.5px; color: var(--color-text-muted);"><?= htmlspecialchars($u['email'] ?: '-') ?></div>
                </div>
              </div>
            </td>
            <td><code><?= htmlspecialchars($u['username']) ?></code></td>
            <td>
              <span class="badge" style="background: #FAF5EE; color: #331B11; border: 1px solid #DCCBBF;">
                <?= htmlspecialchars($u['role_display']) ?>
              </span>
            </td>
            <td><?= htmlspecialchars($u['phone'] ?: '-') ?></td>
            <td>
              <span class="badge <?= $u['status'] === 'active' ? 'status-tersedia' : 'status-kritis' ?>">
                <span class="badge-dot" style="background: <?= $u['status'] === 'active' ? '#27AE60' : '#E74C3C' ?>;"></span>
                <?= ucfirst($u['status']) ?>
              </span>
            </td>
            <td style="text-align: center;">
              <div style="display: flex; gap: 6px; justify-content: center;">
                <button type="button" class="btn btn-outline btn-sm" onclick='openEditUserModal(<?= json_encode($u) ?>)'>
                  <i class="fa-solid fa-pen-to-square"></i> Edit
                </button>
                <?php if ($u['id'] !== (int)getCurrentUser()['id']): ?>
                  <form method="POST" action="" onsubmit="return confirm('Hapus akun pengguna ini?')" style="margin: 0;">
                    <?= csrfField() ?>
                    <input type="hidden" name="post_action" value="delete_user">
                    <input type="hidden" name="delete_id" value="<?= $u['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm">
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal User Form -->
<div class="modal-backdrop" id="modal-user">
  <div class="modal-content">
    <div class="modal-header">
      <h4 class="modal-title" id="modal-user-title"><i class="fa-solid fa-user-plus"></i> Tambah Pengguna</h4>
      <button type="button" class="modal-close" onclick="closeModal('modal-user')">&times;</button>
    </div>

    <form method="POST" action="">
      <?= csrfField() ?>
      <input type="hidden" name="post_action" value="save_user">
      <input type="hidden" name="user_id" id="form_user_id" value="0">

      <div class="modal-body">
        <div class="form-group">
          <label class="form-label" for="u_full_name">Nama Lengkap *</label>
          <input type="text" id="u_full_name" name="full_name" class="form-control" required placeholder="Contoh: Sarah Barista">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
          <div class="form-group">
            <label class="form-label" for="u_username">Username *</label>
            <input type="text" id="u_username" name="username" class="form-control" required placeholder="Contoh: sarah">
          </div>

          <div class="form-group">
            <label class="form-label" for="u_role_id">Role / Hak Akses *</label>
            <select id="u_role_id" name="role_id" class="form-select" required>
              <?php foreach ($roles as $r): ?>
                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['display_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="u_password">Password</label>
          <input type="password" id="u_password" name="password" class="form-control" placeholder="Kosongkan jika tidak ingin mengubah password">
          <span style="font-size: 11px; color: var(--color-text-muted);">Default password untuk akun baru adalah: <code>admin123</code></span>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
          <div class="form-group">
            <label class="form-label" for="u_email">Email</label>
            <input type="email" id="u_email" name="email" class="form-control" placeholder="staf@jazuva.com">
          </div>

          <div class="form-group">
            <label class="form-label" for="u_phone">Nomor Telepon</label>
            <input type="text" id="u_phone" name="phone" class="form-control" placeholder="08123456789">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="u_status">Status Akun</label>
          <select id="u_status" name="status" class="form-select">
            <option value="active">Active (Aktif)</option>
            <option value="inactive">Inactive (Non-aktif)</option>
          </select>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-user')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Pengguna</button>
      </div>
    </form>
  </div>
</div>

<script>
function openAddUserModal() {
  document.getElementById('modal-user-title').innerHTML = '<i class="fa-solid fa-user-plus"></i> Tambah Pengguna Baru';
  document.getElementById('form_user_id').value = '0';
  document.getElementById('u_full_name').value = '';
  document.getElementById('u_username').value = '';
  document.getElementById('u_password').value = '';
  document.getElementById('u_email').value = '';
  document.getElementById('u_phone').value = '';
  document.getElementById('u_status').value = 'active';
  openModal('modal-user');
}

function openEditUserModal(user) {
  document.getElementById('modal-user-title').innerHTML = '<i class="fa-solid fa-user-pen"></i> Edit Pengguna: ' + user.username;
  document.getElementById('form_user_id').value = user.id;
  document.getElementById('u_full_name').value = user.full_name;
  document.getElementById('u_username').value = user.username;
  document.getElementById('u_role_id').value = user.role_id;
  document.getElementById('u_password').value = '';
  document.getElementById('u_email').value = user.email || '';
  document.getElementById('u_phone').value = user.phone || '';
  document.getElementById('u_status').value = user.status;
  openModal('modal-user');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
