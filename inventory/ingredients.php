<?php
/**
 * JAZUVA - CRUD Bahan Baku (Ingredients)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['admin', 'inventory']);

$pdo = getDbConnection();
$action = $_GET['action'] ?? 'index';
$id = (int)($_GET['id'] ?? 0);

// Handle POST actions (Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfPost();
    $postAction = $_POST['post_action'] ?? '';
    
    if ($postAction === 'save') {
        $name = trim($_POST['name'] ?? '');
        $category = $_POST['category'] ?? 'makanan';
        $stockValue = $_POST['stock'] ?? null;
        $unit = trim($_POST['unit'] ?? 'kg');
        $minStockValue = $_POST['min_stock'] ?? null;
        $costPerUnitValue = $_POST['cost_per_unit'] ?? '0';
        $editId = (int)($_POST['ingredient_id'] ?? 0);

        if (
            $name === '' ||
            !in_array($category, ['makanan', 'minuman', 'bumbu', 'packaging', 'lainnya'], true) ||
            $unit === '' ||
            !is_numeric($stockValue) ||
            !is_numeric($minStockValue) ||
            !is_numeric($costPerUnitValue) ||
            (float)$stockValue < 0 ||
            (float)$minStockValue < 0 ||
            (float)$costPerUnitValue < 0
        ) {
            setFlash('error', 'Nama, kategori, satuan, stok, batas minimum, dan harga bahan harus diisi dengan nilai yang valid dan tidak negatif.');
        } else {
            $stock = round((float)$stockValue, 4);
            $minStock = round((float)$minStockValue, 4);
            $costPerUnit = (float)$costPerUnitValue;

            try {
                $pdo->beginTransaction();

                if ($editId > 0) {
                    $stmtCurrent = $pdo->prepare("SELECT stock FROM ingredients WHERE id = ? FOR UPDATE");
                    $stmtCurrent->execute([$editId]);
                    $currentStock = $stmtCurrent->fetchColumn();
                    if ($currentStock === false) {
                        throw new RuntimeException('Bahan baku tidak ditemukan.');
                    }

                    $previousStock = (float)$currentStock;
                    $stmt = $pdo->prepare("UPDATE ingredients SET name = ?, category = ?, stock = ?, unit = ?, min_stock = ?, cost_per_unit = ? WHERE id = ?");
                    $stmt->execute([$name, $category, $stock, $unit, $minStock, $costPerUnit, $editId]);

                    $stockChange = round($stock - $previousStock, 4);
                    if (abs($stockChange) > 0) {
                        $pdo->prepare("INSERT INTO stock_movements
                            (ingredient_id, movement_type, quantity, previous_stock, current_stock, reference_type, notes, created_by)
                            VALUES (?, 'penyesuaian', ?, ?, ?, 'manual_adjustment', ?, ?)")
                            ->execute([
                                $editId,
                                abs($stockChange),
                                $previousStock,
                                $stock,
                                'Penyesuaian stok melalui formulir bahan baku.',
                                getCurrentUser()['id']
                            ]);
                    }
                    $successMessage = "Bahan baku '{$name}' berhasil diperbarui.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO ingredients (name, category, stock, unit, min_stock, cost_per_unit) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $category, $stock, $unit, $minStock, $costPerUnit]);
                    $newId = (int)$pdo->lastInsertId();

                    if ($stock > 0) {
                        $pdo->prepare("INSERT INTO stock_movements (ingredient_id, movement_type, quantity, previous_stock, current_stock, notes, created_by) VALUES (?, 'masuk', ?, 0, ?, 'Stok awal bahan baru', ?)")
                            ->execute([$newId, $stock, $stock, getCurrentUser()['id']]);
                    }
                    $successMessage = "Bahan baku '{$name}' berhasil ditambahkan.";
                }

                $pdo->commit();
                setFlash('success', $successMessage);
                header('Location: ' . BASE_URL . '/inventory/dashboard.php');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Jazuva inventory ingredient save: ' . $e->getMessage());
                setFlash('error', 'Gagal menyimpan perubahan bahan baku. Silakan coba kembali.');
            }
        }
    } elseif ($postAction === 'delete') {
        $delId = (int)($_POST['delete_id'] ?? 0);
        if ($delId > 0) {
            try {
                $pdo->prepare("DELETE FROM ingredients WHERE id = ?")->execute([$delId]);
                setFlash('success', 'Bahan baku berhasil dihapus.');
            } catch (Exception $e) {
                setFlash('error', 'Tidak dapat menghapus bahan yang masih digunakan dalam resep aktif.');
            }
        }
        header('Location: ' . BASE_URL . '/inventory/dashboard.php');
        exit;
    }
}

$editItem = null;
if ($action === 'edit' && $id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM ingredients WHERE id = ?");
    $stmt->execute([$id]);
    $editItem = $stmt->fetch();
}

$pageTitle = ($action === 'create' || $action === 'edit') ? ($editItem ? 'Edit Bahan Baku' : 'Tambah Bahan Baku') : 'Manajemen Bahan Baku';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 700px; margin: 0 auto;">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
    <div>
      <h2 style="font-size: 20px; font-weight: 800; color: var(--color-primary);">
        <?= $editItem ? 'Edit Bahan Baku' : 'Tambah Bahan Baku Baru' ?>
      </h2>
      <p style="font-size: 13px; color: var(--color-text-muted);">Kelola detail bahan, satuan takaran, dan batas peringatan stok.</p>
    </div>
    <a href="<?= BASE_URL ?>/inventory/dashboard.php" class="btn btn-secondary btn-sm">
      <i class="fa-solid fa-arrow-left"></i> Kembali
    </a>
  </div>

  <div class="card">
    <form method="POST" action="">
      <?= csrfField() ?>
      <input type="hidden" name="post_action" value="save">
      <input type="hidden" name="ingredient_id" value="<?= $editItem['id'] ?? 0 ?>">

      <div class="form-group">
        <label class="form-label" for="name">Nama Bahan Baku *</label>
        <input type="text" id="name" name="name" class="form-control" required 
               placeholder="Contoh: Beras, Biji Kopi Arabika, Susu Segar" 
               value="<?= htmlspecialchars($editItem['name'] ?? '') ?>">
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
        <div class="form-group">
          <label class="form-label" for="category">Kategori Bahan</label>
          <select id="category" name="category" class="form-select">
            <option value="makanan" <?= ($editItem['category'] ?? '') === 'makanan' ? 'selected' : '' ?>>Makanan</option>
            <option value="minuman" <?= ($editItem['category'] ?? '') === 'minuman' ? 'selected' : '' ?>>Minuman</option>
            <option value="bumbu" <?= ($editItem['category'] ?? '') === 'bumbu' ? 'selected' : '' ?>>Bumbu &amp; Rempah</option>
            <option value="packaging" <?= ($editItem['category'] ?? '') === 'packaging' ? 'selected' : '' ?>>Kemasan (Packaging)</option>
            <option value="lainnya" <?= ($editItem['category'] ?? '') === 'lainnya' ? 'selected' : '' ?>>Lainnya</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" for="unit">Satuan Ukuran *</label>
          <select id="unit" name="unit" class="form-select">
            <option value="kg" <?= ($editItem['unit'] ?? '') === 'kg' ? 'selected' : '' ?>>kg (Kilogram)</option>
            <option value="gram" <?= ($editItem['unit'] ?? '') === 'gram' ? 'selected' : '' ?>>gram</option>
            <option value="liter" <?= ($editItem['unit'] ?? '') === 'liter' ? 'selected' : '' ?>>liter</option>
            <option value="ml" <?= ($editItem['unit'] ?? '') === 'ml' ? 'selected' : '' ?>>ml (Mililiter)</option>
            <option value="butir" <?= ($editItem['unit'] ?? '') === 'butir' ? 'selected' : '' ?>>butir</option>
            <option value="pcs" <?= ($editItem['unit'] ?? '') === 'pcs' ? 'selected' : '' ?>>pcs / buah</option>
            <option value="lembar" <?= ($editItem['unit'] ?? '') === 'lembar' ? 'selected' : '' ?>>lembar</option>
          </select>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
        <div class="form-group">
          <label class="form-label" for="stock">Stok Saat Ini *</label>
          <input type="number" min="0" step="0.0001" id="stock" name="stock" class="form-control" required 
                 value="<?= (float)($editItem['stock'] ?? 0) ?>">
        </div>

        <div class="form-group">
          <label class="form-label" for="min_stock">Stok Minimum (Alert) *</label>
          <input type="number" min="0" step="0.0001" id="min_stock" name="min_stock" class="form-control" required 
                 value="<?= (float)($editItem['min_stock'] ?? 5) ?>">
        </div>

        <div class="form-group">
          <label class="form-label" for="cost_per_unit">Harga Pokok / Satuan (Rp)</label>
          <input type="number" step="100" id="cost_per_unit" name="cost_per_unit" class="form-control" 
                 value="<?= (float)($editItem['cost_per_unit'] ?? 0) ?>">
        </div>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
        <a href="<?= BASE_URL ?>/inventory/dashboard.php" class="btn btn-outline">Batal</a>
        <button type="submit" class="btn btn-primary">
          <i class="fa-solid fa-floppy-disk"></i> Simpan Bahan Baku
        </button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
