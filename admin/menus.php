<?php
/**
 * JAZUVA - Menu Management (Manajemen Menu)
 * Modul Jundi: CRUD Menu Makanan & Minuman
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['admin']);

$pdo = getDbConnection();

// Handle POST actions (Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfPost();
    $postAction = $_POST['post_action'] ?? '';

    if ($postAction === 'save_menu') {
        $editId = (int)($_POST['menu_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 1);
        $price = (float)($_POST['price'] ?? 0);
        $costPrice = (float)($_POST['cost_price'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $isAvailable = isset($_POST['is_available']) ? 1 : 0;
        $isFavorite = isset($_POST['is_favorite']) ? 1 : 0;

        if (empty($name) || $price <= 0) {
            setFlash('error', 'Nama menu dan harga jual harus diisi dengan benar.');
        } else {
            // Slug
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
            if (empty($slug)) $slug = 'menu-' . time();

            // Handle file upload & preset image
            $presetImage = trim($_POST['preset_image'] ?? '');
            
            // Determine default image by category station
            $stmtCat = $pdo->prepare("SELECT target_station FROM categories WHERE id = ?");
            $stmtCat->execute([$categoryId]);
            $targetStation = $stmtCat->fetchColumn() ?: 'kitchen';
            $defaultByCat = ($targetStation === 'barista') ? 'default-drink.jpg' : 'default-food.jpg';

            $imageName = $_POST['existing_image'] ?? $defaultByCat;
            if (!empty($presetImage)) {
                $imageName = $presetImage;
            }

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

            if ($editId > 0) {
                $stmt = $pdo->prepare("UPDATE menus SET name = ?, slug = ?, category_id = ?, price = ?, cost_price = ?, description = ?, image = ?, is_available = ?, is_favorite = ? WHERE id = ?");
                $stmt->execute([$name, $slug, $categoryId, $price, $costPrice, $description, $imageName, $isAvailable, $isFavorite, $editId]);
                setFlash('success', "Menu '{$name}' berhasil diperbarui.");
            } else {
                $stmt = $pdo->prepare("INSERT INTO menus (name, slug, category_id, price, cost_price, description, image, is_available, is_favorite) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $slug, $categoryId, $price, $costPrice, $description, $imageName, $isAvailable, $isFavorite]);
                setFlash('success', "Menu baru '{$name}' berhasil ditambahkan.");
            }
        }
        header('Location: ' . BASE_URL . '/admin/menus.php');
        exit;

    } elseif ($postAction === 'toggle_menu_availability') {
        $menuId = filter_var($_POST['menu_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$menuId || $menuId < 1) {
            setFlash('error', 'Menu tidak valid.');
        } else {
            $stmt = $pdo->prepare(
                'UPDATE menus SET is_available = IF(is_available = 1, 0, 1) WHERE id = ?'
            );
            $stmt->execute([$menuId]);

            if ($stmt->rowCount() > 0) {
                $stmtMenu = $pdo->prepare('SELECT name, is_available FROM menus WHERE id = ?');
                $stmtMenu->execute([$menuId]);
                $menu = $stmtMenu->fetch();
                $message = $menu['is_available']
                    ? "Menu '{$menu['name']}' dibuka kembali dan tersedia untuk dipesan."
                    : "Menu '{$menu['name']}' ditutup sementara dan tidak dapat dipesan.";
                setFlash('success', $message);
            } else {
                $stmtExists = $pdo->prepare('SELECT id FROM menus WHERE id = ?');
                $stmtExists->execute([$menuId]);
                $menuExists = (bool)$stmtExists->fetch();
                setFlash(
                    $menuExists ? 'success' : 'error',
                    $menuExists ? 'Status menu tidak berubah.' : 'Menu tidak ditemukan.'
                );
            }
        }
        header('Location: ' . BASE_URL . '/admin/menus.php');
        exit;

    } elseif ($postAction === 'delete_menu') {
        $delId = (int)($_POST['delete_id'] ?? 0);
        if ($delId > 0) {
            try {
                $pdo->prepare("DELETE FROM menus WHERE id = ?")->execute([$delId]);
                setFlash('success', 'Menu berhasil dihapus.');
            } catch (Exception $e) {
                setFlash('error', 'Menu ini tidak dapat dihapus karena tercatat dalam riwayat pesanan.');
            }
        }
        header('Location: ' . BASE_URL . '/admin/menus.php');
        exit;
    }
}

// Fetch categories
$categories = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll();

// Filters
$catFilter = trim($_GET['category'] ?? 'all');
$search = trim($_GET['search'] ?? '');

$sql = "SELECT m.*, c.name as category_name, c.slug as category_slug 
        FROM menus m 
        JOIN categories c ON m.category_id = c.id 
        WHERE 1=1";
$params = [];

if ($catFilter !== 'all' && !empty($catFilter)) {
    $sql .= " AND (c.slug = ? OR c.type = ?)";
    $params[] = $catFilter;
    $params[] = $catFilter;
}

if (!empty($search)) {
    $sql .= " AND m.name LIKE ?";
    $params[] = "%{$search}%";
}

$sql .= " ORDER BY c.sort_order ASC, m.id ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$menus = $stmt->fetchAll();

$pageTitle = 'Manajemen Menu';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
  <div>
    <h2 style="font-size: 22px; font-weight: 800; color: var(--color-primary);">Manajemen Menu Restoran</h2>
    <p style="font-size: 13px; color: var(--color-text-muted);">Kelola daftar hidangan makanan, aneka kopi, dan minuman pendukung.</p>
  </div>

  <button type="button" class="btn btn-caramel" onclick="openAddMenuModal()">
    <i class="fa-solid fa-plus"></i> Tambah Menu
  </button>
</div>

<!-- Category Pills Filter matching prototype -->
<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-bottom: 20px;">
  <div class="nav-pills" style="margin-bottom: 0;">
    <a href="<?= BASE_URL ?>/admin/menus.php?category=all" class="nav-pill <?= $catFilter === 'all' ? 'active' : '' ?>">Semua</a>
    <a href="<?= BASE_URL ?>/admin/menus.php?category=makanan" class="nav-pill <?= $catFilter === 'makanan' ? 'active' : '' ?>">Makanan</a>
    <a href="<?= BASE_URL ?>/admin/menus.php?category=minuman" class="nav-pill <?= $catFilter === 'minuman' ? 'active' : '' ?>">Minuman</a>
    <a href="<?= BASE_URL ?>/admin/menus.php?category=kopi" class="nav-pill <?= $catFilter === 'kopi' ? 'active' : '' ?>">Kopi</a>
    <a href="<?= BASE_URL ?>/admin/menus.php?category=snack" class="nav-pill <?= $catFilter === 'snack' ? 'active' : '' ?>">Snack</a>
  </div>

  <form method="GET" action="" style="display: flex; gap: 8px;">
    <input type="hidden" name="category" value="<?= htmlspecialchars($catFilter) ?>">
    <input type="text" name="search" class="form-control" placeholder="Cari nama menu..." 
           value="<?= htmlspecialchars($search) ?>" style="width: 220px; padding: 7px 14px; font-size: 13px;">
    <button type="submit" class="btn btn-primary btn-sm">
      <i class="fa-solid fa-magnifying-glass"></i>
    </button>
    <?php if (!empty($search)): ?>
      <a href="<?= BASE_URL ?>/admin/menus.php?category=<?= urlencode($catFilter) ?>" class="btn btn-outline btn-sm">Reset</a>
    <?php endif; ?>
  </form>
</div>

<!-- Menu Grid Matching Prototype -->
<div class="menu-grid">
  <?php if (empty($menus)): ?>
    <div style="grid-column: 1 / -1; text-align: center; padding: 60px; background: #FFF; border-radius: 16px; border: 1px dashed var(--color-border);">
      <p style="color: var(--color-text-muted);">Tidak ada menu yang sesuai dengan filter pencarian.</p>
    </div>
  <?php else: ?>
    <?php foreach ($menus as $m): ?>
      <div class="menu-card">
        <div class="menu-card-img-wrap">
          <img src="<?= BASE_URL ?>/uploads/menus/<?= htmlspecialchars($m['image'] ?: 'default-food.jpg') ?>" 
               alt="<?= htmlspecialchars($m['name']) ?>" class="menu-card-img"
               onerror="this.onerror=null; this.src='<?= BASE_URL ?>/uploads/menus/default-food.jpg';">
          
          <?php if (!$m['is_available']): ?>
            <span class="badge badge-batal menu-card-badge">Habis</span>
          <?php elseif ($m['is_favorite']): ?>
            <span class="badge badge-menunggu menu-card-badge"><i class="fa-solid fa-star"></i> Favorit</span>
          <?php endif; ?>
        </div>

        <div class="menu-card-body">
          <div style="font-size: 11px; font-weight: 700; color: #C69255; text-transform: uppercase; margin-bottom: 2px;">
            <?= htmlspecialchars($m['category_name']) ?>
          </div>
          <h4 class="menu-card-title"><?= htmlspecialchars($m['name']) ?></h4>
          <div class="menu-card-price"><?= formatRupiah($m['price']) ?></div>

          <div class="menu-card-actions">
            <button type="button" class="btn btn-outline btn-sm" style="flex: 1;" 
                    onclick='openEditMenuModal(<?= json_encode($m) ?>)'>
              <i class="fa-solid fa-pen-to-square"></i> Edit
            </button>
            <form method="POST" action="" style="margin: 0;">
              <?= csrfField() ?>
              <input type="hidden" name="post_action" value="toggle_menu_availability">
              <input type="hidden" name="menu_id" value="<?= (int)$m['id'] ?>">
              <button type="submit"
                      class="btn btn-sm <?= $m['is_available'] ? 'btn-secondary' : 'btn-caramel' ?>"
                      title="<?= $m['is_available'] ? 'Tutup menu sementara' : 'Buka kembali menu' ?>"
                      aria-label="<?= $m['is_available'] ? 'Tutup menu ' : 'Buka kembali menu ' ?><?= htmlspecialchars($m['name'], ENT_QUOTES, 'UTF-8') ?>">
                <i class="fa-solid <?= $m['is_available'] ? 'fa-lock' : 'fa-lock-open' ?>"></i>
                <?= $m['is_available'] ? 'Tutup' : 'Buka' ?>
              </button>
            </form>
            <form method="POST" action="" onsubmit="return confirm('Apakah Anda yakin ingin menghapus menu ini?')" style="margin: 0;">
              <?= csrfField() ?>
              <input type="hidden" name="post_action" value="delete_menu">
              <input type="hidden" name="delete_id" value="<?= $m['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm" title="Hapus Menu">
                <i class="fa-solid fa-trash"></i>
              </button>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- MODAL TAMBAH / EDIT MENU -->
<div class="modal-backdrop" id="modal-menu">
  <div class="modal-content" style="max-width: 580px;">
    <div class="modal-header">
      <h4 class="modal-title" id="modal-menu-title"><i class="fa-solid fa-utensils"></i> Tambah Menu Baru</h4>
      <button type="button" class="modal-close" onclick="closeModal('modal-menu')">&times;</button>
    </div>

    <form method="POST" action="" enctype="multipart/form-data">
      <?= csrfField() ?>
      <input type="hidden" name="post_action" value="save_menu">
      <input type="hidden" name="menu_id" id="form_menu_id" value="0">
      <input type="hidden" name="existing_image" id="form_existing_image" value="">

      <div class="modal-body">
        <div class="form-group">
          <label class="form-label" for="form_name">Nama Menu *</label>
          <input type="text" id="form_name" name="name" class="form-control" required placeholder="Contoh: Nasi Goreng Spesial">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
          <div class="form-group">
            <label class="form-label" for="form_category_id">Kategori Menu *</label>
            <select id="form_category_id" name="category_id" class="form-select" required>
              <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= ucfirst($c['target_station']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="form_price">Harga Jual (Rp) *</label>
            <input type="number" step="500" id="form_price" name="price" class="form-control" required placeholder="20000">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="form_cost_price">Harga Modal (HPP / Biaya Bahan) (Rp)</label>
          <input type="number" step="500" id="form_cost_price" name="cost_price" class="form-control" placeholder="10000">
        </div>

        <div class="form-group">
          <label class="form-label" for="form_description">Deskripsi Menu</label>
          <textarea id="form_description" name="description" class="form-control" placeholder="Jelaskan cita rasa atau komposisi hidangan..."></textarea>
        </div>

        <div class="form-group">
          <label class="form-label" for="form_preset_image">Pilihan Gambar Menu</label>
          <select id="form_preset_image" name="preset_image" class="form-select" style="margin-bottom: 8px;">
            <option value="">-- Pertahankan Gambar Saat Ini / Otomatis Sesuai Kategori --</option>
            <option value="nasi-goreng.jpg">Nasi Goreng Spesial</option>
            <option value="ayam-geprek.jpg">Ayam Geprek Sambal</option>
            <option value="mie-goreng.jpg">Mie Goreng Jawa</option>
            <option value="kentang-goreng.jpg">Kentang Goreng Krispi</option>
            <option value="es-teh.jpg">Es Teh Manis Segar</option>
            <option value="cappuccino.jpg">Cappuccino Art</option>
            <option value="matcha-latte.jpg">Matcha Latte</option>
            <option value="es-kopi-susu.jpg">Es Kopi Susu Aren</option>
            <option value="default-food.jpg">Piring Makanan Utama</option>
            <option value="default-drink.jpg">Gelas Minuman &amp; Kopi</option>
          </select>

          <label class="form-label" style="font-size: 11.5px; color: var(--color-text-muted);">Atau Upload Foto Baru Dari Komputer / HP:</label>
          <input type="file" id="form_image" name="image" class="form-control" accept="image/*">
          <span style="font-size: 11px; color: var(--color-text-muted);">Format didukung: JPG, PNG, WEBP, SVG.</span>
        </div>

        <div style="display: flex; gap: 24px; margin-top: 10px;">
          <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; font-weight: 600;">
            <input type="checkbox" id="form_is_available" name="is_available" value="1" checked>
            <span>Tersedia (Ready to Order)</span>
          </label>

          <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; font-weight: 600;">
            <input type="checkbox" id="form_is_favorite" name="is_favorite" value="1">
            <span>Tandai Menu Favorit</span>
          </label>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-menu')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Menu</button>
      </div>
    </form>
  </div>
</div>

<script>
function openAddMenuModal() {
  document.getElementById('modal-menu-title').innerHTML = '<i class="fa-solid fa-plus-circle"></i> Tambah Menu Baru';
  document.getElementById('form_menu_id').value = '0';
  document.getElementById('form_name').value = '';
  document.getElementById('form_price').value = '';
  document.getElementById('form_cost_price').value = '';
  document.getElementById('form_description').value = '';
  document.getElementById('form_existing_image').value = 'nasi-goreng.jpg';
  document.getElementById('form_preset_image').value = '';
  document.getElementById('form_is_available').checked = true;
  document.getElementById('form_is_favorite').checked = false;
  openModal('modal-menu');
}

function openEditMenuModal(menu) {
  document.getElementById('modal-menu-title').innerHTML = '<i class="fa-solid fa-pen-to-square"></i> Edit Menu: ' + menu.name;
  document.getElementById('form_menu_id').value = menu.id;
  document.getElementById('form_name').value = menu.name;
  document.getElementById('form_category_id').value = menu.category_id;
  document.getElementById('form_price').value = menu.price;
  document.getElementById('form_cost_price').value = menu.cost_price;
  document.getElementById('form_description').value = menu.description || '';
  document.getElementById('form_existing_image').value = menu.image || '';
  document.getElementById('form_preset_image').value = menu.image || '';
  document.getElementById('form_is_available').checked = (parseInt(menu.is_available) === 1);
  document.getElementById('form_is_favorite').checked = (parseInt(menu.is_favorite) === 1);
  openModal('modal-menu');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
