<?php
/**
 * JAZUVA - Table Management & Visual Floor Plan (Manajemen Meja)
 * Modul Jundi: Denah Visual, CRUD Meja, & Dynamic QR Code
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['admin']);

$pdo = getDbConnection();

// Handle POST actions (Create table, Update status, Delete table)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfPost();
    $postAction = $_POST['post_action'] ?? '';

    if ($postAction === 'create') {
        $tableNum = trim($_POST['table_number'] ?? '');
        $capacity = (int)($_POST['capacity'] ?? 4);
        $status = $_POST['status'] ?? 'tersedia';

        if ($tableNum === '' || strlen($tableNum) > 10 || $capacity < 1 || $capacity > 20 || !in_array($status, ['tersedia', 'terisi', 'dipesan'], true)) {
            setFlash('error', 'Nomor meja wajib diisi (maksimal 10 karakter), kapasitas harus 1–20 orang, dan status meja harus valid.');
        } else {
            try {
                $pdo->beginTransaction();
                $stmtC = $pdo->prepare("SELECT id, is_active FROM restaurant_tables WHERE table_number = ? FOR UPDATE");
                $stmtC->execute([$tableNum]);
                $existingTable = $stmtC->fetch();

                if ($existingTable && (int)$existingTable['is_active'] === 1) {
                    $pdo->rollBack();
                    setFlash('error', "Meja dengan nomor {$tableNum} sudah ada.");
                } elseif ($existingTable) {
                    $stmtOpenOrders = $pdo->prepare(
                        "SELECT COUNT(*) FROM table_sessions ts
                         JOIN orders o ON o.session_id = ts.id
                         WHERE ts.table_id = ? AND ts.status = 'active'
                           AND o.order_status NOT IN ('selesai', 'dibatalkan')"
                    );
                    $stmtOpenOrders->execute([$existingTable['id']]);
                    if ((int)$stmtOpenOrders->fetchColumn() > 0) {
                        $pdo->rollBack();
                        setFlash('error', "Meja {$tableNum} masih memiliki pesanan aktif. Selesaikan pesanan sebelum menambahkan kembali meja ini.");
                    } else {
                        $pdo->prepare(
                            "UPDATE table_sessions SET status = 'cancelled', end_time = CURRENT_TIMESTAMP
                             WHERE table_id = ? AND status = 'active'"
                        )->execute([$existingTable['id']]);
                        $token = bin2hex(random_bytes(32));
                        $pdo->prepare(
                            "UPDATE restaurant_tables
                             SET capacity = ?, status = ?, token = ?, is_active = 1
                             WHERE id = ?"
                        )->execute([$capacity, $status, $token, $existingTable['id']]);
                        $pdo->commit();
                        setFlash('success', "Meja {$tableNum} berhasil diaktifkan kembali dengan QR baru.");
                    }
                } else {
                    $token = bin2hex(random_bytes(32));
                    $stmt = $pdo->prepare("INSERT INTO restaurant_tables (table_number, capacity, status, token) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$tableNum, $capacity, $status, $token]);
                    $pdo->commit();
                    setFlash('success', "Meja {$tableNum} berhasil ditambahkan!");
                }
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('[Jazuva table creation] ' . $exception->getMessage());
                setFlash('error', 'Meja gagal ditambahkan. Silakan coba kembali.');
            }
        }
        header('Location: ' . BASE_URL . '/admin/tables.php');
        exit;
    } elseif ($postAction === 'update_status') {
        $tableId = (int)$_POST['table_id'];
        $status = $_POST['status'] ?? 'tersedia';
        $pdo->prepare("UPDATE restaurant_tables SET status = ? WHERE id = ?")->execute([$status, $tableId]);
        setFlash('success', "Status meja berhasil diperbarui menjadi " . ucfirst($status));
        header('Location: ' . BASE_URL . '/admin/tables.php?selected=' . $tableId);
        exit;
    } elseif ($postAction === 'create_menu') {
        $name = trim($_POST['name'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 1);
        $price = (float)($_POST['price'] ?? 0);
        $costPrice = (float)($_POST['cost_price'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $presetImage = trim($_POST['preset_image'] ?? '');
        $isAvailable = isset($_POST['is_available']) ? 1 : 0;
        $isFavorite = isset($_POST['is_favorite']) ? 1 : 0;

        if (empty($name) || $price <= 0) {
            setFlash('error', 'Nama menu dan harga jual wajib diisi.');
        } else {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
            if (empty($slug)) $slug = 'menu-' . time();

            // Target station check for default image
            $stmtCat = $pdo->prepare("SELECT target_station FROM categories WHERE id = ?");
            $stmtCat->execute([$categoryId]);
            $targetStation = $stmtCat->fetchColumn() ?: 'kitchen';

            $imageName = !empty($presetImage) ? $presetImage : ($targetStation === 'barista' ? 'default-drink.jpg' : 'default-food.jpg');

            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $fileTmp = $_FILES['image']['tmp_name'];
                $fileName = $_FILES['image']['name'];
                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
                if (in_array($ext, $allowed)) {
                    $newFileName = 'menu_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                    $targetPath = __DIR__ . '/../uploads/menus/' . $newFileName;
                    if (move_uploaded_file($fileTmp, $targetPath)) {
                        $imageName = $newFileName;
                    }
                }
            }

            $stmt = $pdo->prepare("INSERT INTO menus (name, slug, category_id, price, cost_price, description, image, is_available, is_favorite) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $slug, $categoryId, $price, $costPrice, $description, $imageName, $isAvailable, $isFavorite]);
            $newMenuId = (int)$pdo->lastInsertId();

            // Create default recipe entry
            $pdo->prepare("INSERT INTO recipes (menu_id, notes) VALUES (?, ?)")
                ->execute([$newMenuId, 'Resep untuk ' . $name]);

            setFlash('success', "Menu baru '{$name}' berhasil ditambahkan! Menu ini sekarang langsung tampil di pemesanan pelanggan.");
        }
        header('Location: ' . BASE_URL . '/admin/tables.php');
        exit;
    } elseif ($postAction === 'delete') {
        $delId = (int)$_POST['delete_id'];
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT id FROM restaurant_tables WHERE id = ? AND is_active = 1 FOR UPDATE");
            $stmt->execute([$delId]);
            $tableToDelete = $stmt->fetch();

            if (!$tableToDelete) {
                $pdo->rollBack();
                setFlash('error', 'Meja tidak ditemukan atau sudah dihapus.');
            } else {
                $stmtOpenOrders = $pdo->prepare(
                    "SELECT COUNT(*) FROM table_sessions ts
                     JOIN orders o ON o.session_id = ts.id
                     WHERE ts.table_id = ? AND ts.status = 'active'
                       AND o.order_status NOT IN ('selesai', 'dibatalkan')"
                );
                $stmtOpenOrders->execute([$delId]);
                if ((int)$stmtOpenOrders->fetchColumn() > 0) {
                    $pdo->rollBack();
                    setFlash('error', 'Meja masih memiliki pesanan aktif. Selesaikan pesanan sebelum menghapus meja.');
                } else {
                    $pdo->prepare(
                        "UPDATE table_sessions SET status = 'cancelled', end_time = CURRENT_TIMESTAMP
                         WHERE table_id = ? AND status = 'active'"
                    )->execute([$delId]);
                    $newToken = bin2hex(random_bytes(32));
                    $pdo->prepare(
                        "UPDATE restaurant_tables
                         SET is_active = 0, status = 'tersedia', token = ?
                         WHERE id = ?"
                    )->execute([$newToken, $delId]);
                    $pdo->commit();
                    setFlash('success', 'Meja berhasil dihapus. QR lama tidak dapat digunakan.');
                }
            }
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[Jazuva table deletion] ' . $exception->getMessage());
            setFlash('error', 'Meja gagal dihapus. Silakan coba kembali.');
        }
        header('Location: ' . BASE_URL . '/admin/tables.php');
        exit;
    }
}

// Fetch all active tables
$stmt = $pdo->query("SELECT * FROM restaurant_tables WHERE is_active = 1 ORDER BY table_number ASC");
$tables = $stmt->fetchAll();

// Fetch all active categories for quick menu creation
$categories = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll();

$selectedId = (int)($_GET['selected'] ?? ($tables[0]['id'] ?? 0));
$selectedTable = null;
foreach ($tables as $t) {
    if ($t['id'] == $selectedId) {
        $selectedTable = $t;
        break;
    }
}
if (!$selectedTable && !empty($tables)) {
    $selectedTable = $tables[0];
}

$pageTitle = 'Manajemen Meja';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- QR Code Library via CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
  <div>
    <h2 style="font-size: 22px; font-weight: 800; color: var(--color-primary);">Manajemen Meja &amp; Denah Visual</h2>
    <p style="font-size: 13px; color: var(--color-text-muted);">
      Pantau okupansi meja pelanggan secara visual dan kelola QR Code unik untuk pemesanan mandiri.
    </p>
  </div>

  <div style="display: flex; gap: 10px; flex-wrap: wrap;">
    <button type="button" class="btn btn-primary" onclick="openModal('modal-add-menu-quick')">
      <i class="fa-solid fa-utensils"></i> + Tambah Menu Baru
    </button>
    <a href="<?= BASE_URL ?>/admin/qr-management.php" class="btn btn-secondary">
      <i class="fa-solid fa-print"></i> Cetak Stand QR Semua Meja
    </a>
    <button type="button" class="btn btn-caramel" onclick="openModal('modal-add-table')">
      <i class="fa-solid fa-plus"></i> Tambah Meja
    </button>
    <form method="POST" action="" onsubmit="return confirmDeleteTable()" style="margin: 0;">
      <?= csrfField() ?>
      <input type="hidden" name="post_action" value="delete">
      <input type="hidden" name="delete_id" id="delete_table_id" value="<?= (int)($selectedTable['id'] ?? 0) ?>">
      <button type="submit" class="btn btn-danger" <?= $selectedTable ? '' : 'disabled' ?>>
        <i class="fa-solid fa-trash"></i> Hapus Meja
      </button>
    </form>
  </div>
</div>

<!-- MAIN FLOOR PLAN & QR CARD WRAPPER (Exact Prototype Visuals) -->
<div class="floor-plan-wrapper">
  
  <!-- LEFT: FLOOR PLAN VISUAL ROOM -->
  <div class="floor-plan-canvas">
    <div>
      <div style="font-size: 13px; font-weight: 700; color: var(--color-primary); margin-bottom: 14px;">
        <i class="fa-solid fa-map-location-dot" style="color: #8B5A2B;"></i> Area Makan Utama (Indoor &amp; Outdoor)
      </div>

      <!-- Grid of tables with chairs matching prototype -->
      <div class="floor-plan-grid">
        <?php if (empty($tables)): ?>
          <p style="font-size: 13px; color: var(--color-text-muted);">Belum ada meja aktif. Gunakan tombol Tambah Meja untuk membuat meja baru.</p>
        <?php endif; ?>
        <?php foreach ($tables as $t): ?>
          <?php 
            $statusClass = 'status-' . $t['status'];
            $isSelected = ($selectedTable && $t['id'] == $selectedTable['id']) ? 'active' : '';
          ?>
          <div class="table-node <?= $statusClass ?> <?= $isSelected ?>" 
               onclick="selectTable(<?= (int)$t['id'] ?>, '<?= htmlspecialchars($t['table_number'], ENT_QUOTES, 'UTF-8') ?>', '<?= $t['status'] ?>', <?= (int)$t['capacity'] ?>, '<?= htmlspecialchars($t['token'], ENT_QUOTES, 'UTF-8') ?>')">
            <!-- Top Chair -->
            <div class="table-chair top"></div>
            <!-- Left Chair -->
            <div class="table-chair left"></div>
            <!-- Surface with table number -->
            <div class="table-surface">
              <span><?= htmlspecialchars($t['table_number']) ?></span>
            </div>
            <!-- Right Chair -->
            <div class="table-chair right"></div>
            <!-- Bottom Chair -->
            <div class="table-chair bottom"></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Entrance Door & Legend matching prototype -->
    <div>
      <div style="display: flex; justify-content: center;">
        <div class="floor-door">Pintu Masuk</div>
      </div>

      <div class="floor-legend">
        <div class="legend-item">
          <span class="legend-dot" style="background: #27AE60;"></span>
          <span>Tersedia</span>
        </div>
        <div class="legend-item">
          <span class="legend-dot" style="background: #E67E22;"></span>
          <span>Terisi</span>
        </div>
        <div class="legend-item">
          <span class="legend-dot" style="background: #8B5A2B;"></span>
          <span>Dipesan</span>
        </div>
      </div>
    </div>
  </div>

  <!-- RIGHT: QR PREVIEW CARD (Matching Prototype) -->
  <div class="card" style="display: flex; flex-direction: column; align-items: center; text-align: center; padding: 26px 20px;">
    <?php if ($selectedTable): ?>
      <h3 style="font-size: 18px; font-weight: 800; color: var(--color-primary); margin-bottom: 16px;" id="qr-card-title">
        QR Meja <?= htmlspecialchars($selectedTable['table_number']) ?>
      </h3>

      <!-- QR Code Graphic Container -->
      <div style="background: #FFFFFF; padding: 16px; border-radius: var(--radius-lg); border: 2px solid var(--color-border); box-shadow: var(--shadow-sm); margin-bottom: 16px;">
        <div id="qrcode-preview" style="width: 170px; height: 170px; display: flex; align-items: center; justify-content: center;"></div>
      </div>

      <div style="font-size: 12px; color: var(--color-text-muted); margin-bottom: 16px; max-width: 240px;" id="qr-target-url-text">
        Scan untuk memesan menu
      </div>

      <div style="display: flex; flex-direction: column; gap: 8px; width: 100%;">
        <button type="button" class="btn btn-primary btn-block" onclick="downloadQRCode()">
          <i class="fa-solid fa-download"></i> Download QR
        </button>

        <a id="btn-preview-menu" href="<?= BASE_URL ?>/menu.php?meja=<?= urlencode($selectedTable['table_number']) ?>&amp;token=<?= urlencode($selectedTable['token']) ?>" target="_blank" class="btn btn-secondary btn-block">
          <i class="fa-solid fa-arrow-up-right-from-square"></i> Buka Menu Meja
        </a>

        <!-- Quick status change form -->
        <form method="POST" action="" style="margin-top: 14px; text-align: left; width: 100%;">
          <?= csrfField() ?>
          <input type="hidden" name="post_action" value="update_status">
          <input type="hidden" name="table_id" id="status_table_id" value="<?= $selectedTable['id'] ?>">

          <label class="form-label" style="font-size: 11.5px;">Ubah Status Meja:</label>
          <div style="display: flex; gap: 8px;">
            <select name="status" id="select_table_status" class="form-select" style="font-size: 12px; padding: 6px 10px;">
              <option value="tersedia" <?= $selectedTable['status'] === 'tersedia' ? 'selected' : '' ?>>Tersedia</option>
              <option value="terisi" <?= $selectedTable['status'] === 'terisi' ? 'selected' : '' ?>>Terisi</option>
              <option value="dipesan" <?= $selectedTable['status'] === 'dipesan' ? 'selected' : '' ?>>Dipesan</option>
              <option value="menunggu_pembayaran" <?= $selectedTable['status'] === 'menunggu_pembayaran' ? 'selected' : '' ?>>Menunggu Pembayaran</option>
            </select>
            <button type="submit" class="btn btn-caramel btn-sm">Update</button>
          </div>
        </form>
      </div>
    <?php else: ?>
      <h3 style="font-size: 18px; font-weight: 800; color: var(--color-primary); margin-bottom: 16px;">Belum ada meja aktif</h3>
      <p style="font-size: 13px; color: var(--color-text-muted);">QR menu meja akan muncul di sini setelah meja ditambahkan.</p>
    <?php endif; ?>
  </div>

</div>

<!-- MODAL TAMBAH MEJA -->
<div class="modal-backdrop" id="modal-add-table">
  <div class="modal-content">
    <div class="modal-header">
      <h4 class="modal-title"><i class="fa-solid fa-plus-circle"></i> Tambah Meja Restoran</h4>
      <button type="button" class="modal-close" onclick="closeModal('modal-add-table')">&times;</button>
    </div>

    <form method="POST" action="">
      <?= csrfField() ?>
      <input type="hidden" name="post_action" value="create">

      <div class="modal-body">
        <div class="form-group">
          <label class="form-label" for="add_table_number">Nomor Meja *</label>
          <input type="text" id="add_table_number" name="table_number" class="form-control" required placeholder="Contoh: 07, 08, VIP-01">
        </div>

        <div class="form-group">
          <label class="form-label" for="add_capacity">Kapasitas Kursi (Orang)</label>
          <input type="number" id="add_capacity" name="capacity" class="form-control" value="4" min="1" max="20">
        </div>

        <div class="form-group">
          <label class="form-label" for="add_status">Status Awal</label>
          <select id="add_status" name="status" class="form-select">
            <option value="tersedia">Tersedia</option>
            <option value="terisi">Terisi</option>
            <option value="dipesan">Dipesan</option>
          </select>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-add-table')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Meja</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL TAMBAH MENU DARI DASHBOARD MEJA -->
<div class="modal-backdrop" id="modal-add-menu-quick">
  <div class="modal-content" style="max-width: 580px;">
    <div class="modal-header">
      <h4 class="modal-title"><i class="fa-solid fa-utensils"></i> Tambah Menu Baru ke Restoran</h4>
      <button type="button" class="modal-close" onclick="closeModal('modal-add-menu-quick')">&times;</button>
    </div>

    <form method="POST" action="" enctype="multipart/form-data">
      <?= csrfField() ?>
      <input type="hidden" name="post_action" value="create_menu">

      <div class="modal-body">
        <div class="form-group">
          <label class="form-label" for="quick_menu_name">Nama Menu *</label>
          <input type="text" id="quick_menu_name" name="name" class="form-control" required placeholder="Contoh: Nasi Liwet Komplit / Caramel Macchiato">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
          <div class="form-group">
            <label class="form-label" for="quick_category_id">Kategori Menu *</label>
            <select id="quick_category_id" name="category_id" class="form-select" required>
              <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= ucfirst($c['target_station']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="quick_price">Harga Jual (Rp) *</label>
            <input type="number" step="500" id="quick_price" name="price" class="form-control" required placeholder="Contoh: 25000">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="quick_cost_price">Harga Modal / HPP (Rp)</label>
          <input type="number" step="500" id="quick_cost_price" name="cost_price" class="form-control" placeholder="Contoh: 12000">
        </div>

        <div class="form-group">
          <label class="form-label" for="quick_description">Deskripsi Menu</label>
          <textarea id="quick_description" name="description" class="form-control" placeholder="Rasa khas, porsi, atau bahan hidangan..."></textarea>
        </div>

        <div class="form-group">
          <label class="form-label" for="quick_preset_image">Pilihan Gambar Menu</label>
          <select id="quick_preset_image" name="preset_image" class="form-select" style="margin-bottom: 8px;">
            <option value="">-- Gunakan Foto Otomatis Sesuai Kategori --</option>
            <option value="nasi-goreng.jpg">Nasi Goreng Spesial</option>
            <option value="ayam-geprek.jpg">Ayam Geprek Sambal</option>
            <option value="mie-goreng.jpg">Mie Goreng Jawa</option>
            <option value="kentang-goreng.jpg">Kentang Goreng Krispi</option>
            <option value="es-teh.jpg">Es Teh Manis Segar</option>
            <option value="cappuccino.jpg">Cappuccino Art</option>
            <option value="matcha-latte.jpg">Matcha Latte</option>
            <option value="es-kopi-susu.jpg">Es Kopi Susu Aren</option>
            <option value="default-food.jpg">Hidangan Makanan Utama</option>
            <option value="default-drink.jpg">Minuman &amp; Kopi Segar</option>
          </select>

          <label class="form-label" style="font-size: 11.5px; color: var(--color-text-muted);">Atau Upload Foto Baru Dari Perangkat:</label>
          <input type="file" id="quick_image" name="image" class="form-control" accept="image/*">
        </div>

        <div style="display: flex; gap: 20px; margin-top: 12px;">
          <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; font-weight: 600;">
            <input type="checkbox" name="is_available" value="1" checked>
            <span>Tersedia untuk Dipesan</span>
          </label>
          <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; font-weight: 600;">
            <input type="checkbox" name="is_favorite" value="1">
            <span>Menu Favorit</span>
          </label>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-add-menu-quick')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus-circle"></i> Tambah Menu ke Resto</button>
      </div>
    </form>
  </div>
</div>

<script>
let currentSelectedTableNumber = <?= json_encode($selectedTable['table_number'] ?? null) ?>;
let currentSelectedTableToken = <?= json_encode($selectedTable['token'] ?? null) ?>;
let qrInstance = null;

function renderQR(tableNumber, tableToken) {
  const container = document.getElementById('qrcode-preview');
  container.innerHTML = '';
  
  const targetUrl = `<?= BASE_URL ?>/menu.php?meja=${encodeURIComponent(tableNumber)}&token=${encodeURIComponent(tableToken)}`;
  document.getElementById('qr-target-url-text').innerText = targetUrl;
  document.getElementById('btn-preview-menu').href = targetUrl;

  qrInstance = new QRCode(container, {
    text: targetUrl,
    width: 170,
    height: 170,
    colorDark : "#2C1810",
    colorLight : "#FFFFFF",
    correctLevel : QRCode.CorrectLevel.H
  });
}

function selectTable(id, number, status, capacity, token) {
  currentSelectedTableNumber = number;
  currentSelectedTableToken = token;
  document.getElementById('qr-card-title').innerText = `QR Meja ${number}`;
  document.getElementById('delete_table_id').value = id;
  document.getElementById('status_table_id').value = id;
  document.getElementById('select_table_status').value = status;
  
  // Highlight node
  document.querySelectorAll('.table-node').forEach(node => node.classList.remove('active'));
  event.currentTarget.classList.add('active');

  renderQR(number, token);
}

function confirmDeleteTable() {
  return currentSelectedTableNumber
    ? confirm('Hapus meja ' + currentSelectedTableNumber + '? QR meja ini tidak akan dapat digunakan lagi.')
    : false;
}

function downloadQRCode() {
  const img = document.querySelector('#qrcode-preview img');
  if (img && img.src) {
    const a = document.createElement('a');
    a.href = img.src;
    a.download = `QR-JAZUVA-MEJA-${currentSelectedTableNumber}.png`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
  } else {
    // If canvas
    const canvas = document.querySelector('#qrcode-preview canvas');
    if (canvas) {
      const a = document.createElement('a');
      a.href = canvas.toDataURL('image/png');
      a.download = `QR-JAZUVA-MEJA-${currentSelectedTableNumber}.png`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
    }
  }
}

document.addEventListener('DOMContentLoaded', function() {
  if (currentSelectedTableNumber && currentSelectedTableToken) {
    renderQR(currentSelectedTableNumber, currentSelectedTableToken);
  }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
