<?php
/**
 * JAZUVA - Stock In (Penerimaan Bahan Baku / Restock)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['admin', 'inventory']);

$pdo = getDbConnection();
$selectedIngId = (int)($_GET['ing_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfPost();
    $ingredientId = (int)($_POST['ingredient_id'] ?? 0);
    $quantity = (float)($_POST['quantity'] ?? 0);
    $notes = trim($_POST['notes'] ?? 'Penerimaan stok dari supplier');

    if ($ingredientId <= 0 || $quantity <= 0) {
        setFlash('error', 'Silakan pilih bahan dan masukkan jumlah stok masuk yang valid.');
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT * FROM ingredients WHERE id = ? FOR UPDATE");
            $stmt->execute([$ingredientId]);
            $ing = $stmt->fetch();

            if (!$ing) {
                throw new Exception("Bahan baku tidak ditemukan.");
            }

            $prevStock = (float)$ing['stock'];
            $newStock = $prevStock + $quantity;

            // Update stock
            $pdo->prepare("UPDATE ingredients SET stock = ? WHERE id = ?")->execute([$newStock, $ingredientId]);

            // Record movement
            $pdo->prepare("INSERT INTO stock_movements (ingredient_id, movement_type, quantity, previous_stock, current_stock, reference_type, notes, created_by) 
                           VALUES (?, 'masuk', ?, ?, ?, 'manual_in', ?, ?)")
                ->execute([$ingredientId, $quantity, $prevStock, $newStock, $notes, getCurrentUser()['id']]);

            $pdo->commit();
            setFlash('success', "Berhasil menambahkan {$quantity} {$ing['unit']} untuk {$ing['name']}. Stok sekarang: {$newStock} {$ing['unit']}.");
            header('Location: ' . BASE_URL . '/inventory/dashboard.php');
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            setFlash('error', 'Gagal memproses stok masuk: ' . $e->getMessage());
        }
    }
}

$allIngredients = $pdo->query("SELECT * FROM ingredients ORDER BY name ASC")->fetchAll();

$pageTitle = 'Penerimaan Bahan Baku (Stok Masuk)';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 600px; margin: 0 auto;">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
    <div>
      <h2 style="font-size: 20px; font-weight: 800; color: var(--color-primary);">Penerimaan Bahan Baku (Stok Masuk)</h2>
      <p style="font-size: 13px; color: var(--color-text-muted);">Catat penambahan stok dari supplier atau pembelian baru.</p>
    </div>
    <a href="<?= BASE_URL ?>/inventory/dashboard.php" class="btn btn-secondary btn-sm">
      <i class="fa-solid fa-arrow-left"></i> Kembali
    </a>
  </div>

  <div class="card">
    <form method="POST" action="">
      <?= csrfField() ?>
      <div class="form-group">
        <label class="form-label">Pilih Bahan Baku *</label>
        <select name="ingredient_id" class="form-select" required>
          <option value="">-- Pilih Bahan Baku --</option>
          <?php foreach ($allIngredients as $ing): ?>
            <option value="<?= $ing['id'] ?>" <?= $ing['id'] == $selectedIngId ? 'selected' : '' ?>>
              <?= htmlspecialchars($ing['name']) ?> (Stok saat ini: <?= (float)$ing['stock'] ?> <?= $ing['unit'] ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Jumlah Masuk *</label>
        <input type="number" min="0.0001" step="0.0001" name="quantity" class="form-control" required placeholder="Contoh: 10">
        <span style="font-size: 11px; color: var(--color-text-muted);">Masukkan angka sesuai satuan bahan (kg, liter, butir, pcs).</span>
      </div>

      <div class="form-group">
        <label class="form-label">Catatan / Supplier / No. Invoice</label>
        <textarea name="notes" class="form-control" placeholder="Contoh: Restock Toko Beras Jaya - Inv #9910"></textarea>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
        <a href="<?= BASE_URL ?>/inventory/dashboard.php" class="btn btn-outline">Batal</a>
        <button type="submit" class="btn btn-primary">
          <i class="fa-solid fa-plus-circle"></i> Tambah Stok Masuk
        </button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
