<?php
/**
 * JAZUVA - Recipe Management (Resep Menu)
 * Modul Zahra: Hubungkan Menu dengan Bahan Baku & Takaran
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['admin', 'inventory']);

$pdo = getDbConnection();
$selectedMenuId = (int)($_GET['menu_id'] ?? 1);

// Handle POST actions (Add ingredient to recipe, delete ingredient, update recipe)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfPost();
    $postAction = $_POST['post_action'] ?? '';

    if ($postAction === 'add_detail') {
        $menuId = (int)$_POST['menu_id'];
        $ingredientId = (int)$_POST['ingredient_id'];
        $amount = (float)$_POST['amount_required'];
        $unit = trim($_POST['unit'] ?? 'kg');

        if ($menuId > 0 && $ingredientId > 0 && $amount > 0) {
            // Find or create recipe record
            $stmtR = $pdo->prepare("SELECT id FROM recipes WHERE menu_id = ?");
            $stmtR->execute([$menuId]);
            $recipeId = $stmtR->fetchColumn();

            if (!$recipeId) {
                $pdo->prepare("INSERT INTO recipes (menu_id, notes) VALUES (?, 'Resep menu')")->execute([$menuId]);
                $recipeId = (int)$pdo->lastInsertId();
            }

            // Check if already in recipe
            $stmtCheck = $pdo->prepare("SELECT id FROM recipe_details WHERE recipe_id = ? AND ingredient_id = ?");
            $stmtCheck->execute([$recipeId, $ingredientId]);
            if ($stmtCheck->fetch()) {
                $pdo->prepare("UPDATE recipe_details SET amount_required = ?, unit = ? WHERE recipe_id = ? AND ingredient_id = ?")
                    ->execute([$amount, $unit, $recipeId, $ingredientId]);
            } else {
                $pdo->prepare("INSERT INTO recipe_details (recipe_id, ingredient_id, amount_required, unit) VALUES (?, ?, ?, ?)")
                    ->execute([$recipeId, $ingredientId, $amount, $unit]);
            }
            setFlash('success', 'Bahan resep berhasil disimpan!');
        }
        header("Location: " . BASE_URL . "/inventory/recipes.php?menu_id={$menuId}");
        exit;
    } elseif ($postAction === 'delete_detail') {
        $detailId = (int)$_POST['detail_id'];
        $menuId = (int)$_POST['menu_id'];
        $pdo->prepare("DELETE FROM recipe_details WHERE id = ?")->execute([$detailId]);
        setFlash('success', 'Bahan berhasil dihapus dari resep.');
        header("Location: " . BASE_URL . "/inventory/recipes.php?menu_id={$menuId}");
        exit;
    }
}

// Fetch all menus
$menus = $pdo->query("SELECT m.*, c.name as category_name FROM menus m JOIN categories c ON m.category_id = c.id ORDER BY m.name ASC")->fetchAll();

// Fetch all available ingredients
$allIngredients = $pdo->query("SELECT * FROM ingredients ORDER BY name ASC")->fetchAll();

// Fetch recipe details for selected menu
$stmtCurrent = $pdo->prepare("SELECT r.id as recipe_id, m.name as menu_name, m.price, m.image 
                             FROM menus m 
                             LEFT JOIN recipes r ON m.id = r.menu_id 
                             WHERE m.id = ?");
$stmtCurrent->execute([$selectedMenuId]);
$currentMenu = $stmtCurrent->fetch();

$recipeDetails = [];
if ($currentMenu && !empty($currentMenu['recipe_id'])) {
    $stmtDet = $pdo->prepare("SELECT rd.*, i.name as ingredient_name, i.unit as base_unit, i.stock, i.cost_per_unit 
                              FROM recipe_details rd 
                              JOIN ingredients i ON rd.ingredient_id = i.id 
                              WHERE rd.recipe_id = ?");
    $stmtDet->execute([$currentMenu['recipe_id']]);
    $recipeDetails = $stmtDet->fetchAll();
}

$pageTitle = 'Resep Menu & Komposisi Bahan';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
  <div>
    <h2 style="font-size: 22px; font-weight: 800; color: var(--color-primary);">Manajemen Resep &amp; Takaran Bahan</h2>
    <p style="font-size: 13px; color: var(--color-text-muted);">
      Tentukan takaran bahan baku per porsi menu untuk pemotongan stok otomatis saat pesanan diproses di Kitchen/Barista.
    </p>
  </div>
  <a href="<?= BASE_URL ?>/inventory/dashboard.php" class="btn btn-secondary btn-sm">
    <i class="fa-solid fa-arrow-left"></i> Kembali ke Stok
  </a>
</div>

<div style="display: grid; grid-template-columns: 320px 1fr; gap: 24px;">
  
  <!-- Left Column: Menu Picker -->
  <div class="card" style="padding: 16px;">
    <h4 style="font-size: 14px; font-weight: 700; color: var(--color-primary); margin-bottom: 12px;">Pilih Menu</h4>
    <div style="display: flex; flex-direction: column; gap: 6px; max-height: 600px; overflow-y: auto;">
      <?php foreach ($menus as $m): ?>
        <a href="<?= BASE_URL ?>/inventory/recipes.php?menu_id=<?= $m['id'] ?>" 
           style="padding: 10px 14px; border-radius: var(--radius-md); text-decoration: none; display: flex; justify-content: space-between; align-items: center; font-size: 13px; font-weight: 600; 
                  background: <?= $m['id'] == $selectedMenuId ? '#8B5A2B' : '#FAF5EE' ?>; 
                  color: <?= $m['id'] == $selectedMenuId ? '#FFFFFF' : '#331B11' ?>;">
          <span><?= htmlspecialchars($m['name']) ?></span>
          <span style="font-size: 11px; opacity: 0.8;"><?= htmlspecialchars($m['category_name']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Right Column: Current Recipe Details -->
  <div>
    <div class="card" style="margin-bottom: 20px;">
      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
        <div>
          <h3 style="font-size: 18px; font-weight: 800; color: var(--color-primary);">
            Komposisi: <?= htmlspecialchars($currentMenu['menu_name'] ?? 'Pilih Menu') ?>
          </h3>
          <span style="font-size: 12.5px; color: var(--color-text-muted);">Takaran bahan baku per 1 porsi menu</span>
        </div>
      </div>

      <!-- Recipe details table -->
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>Nama Bahan</th>
              <th>Takaran per Porsi</th>
              <th>Satuan</th>
              <th>Stok Tersedia</th>
              <th style="text-align: center;">Hapus</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recipeDetails)): ?>
              <tr>
                <td colspan="5" style="text-align: center; padding: 30px; color: #888;">
                  Belum ada bahan baku yang diatur untuk resep ini. Tambahkan bahan di bawah.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($recipeDetails as $det): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($det['ingredient_name']) ?></strong></td>
                  <td>
                    <span style="font-size: 15px; font-weight: 700; color: var(--color-primary);">
                      <?= (float)$det['amount_required'] ?>
                    </span>
                  </td>
                  <td><?= htmlspecialchars($det['unit']) ?></td>
                  <td><?= (float)$det['stock'] ?> <?= htmlspecialchars($det['base_unit']) ?></td>
                  <td style="text-align: center;">
                    <form method="POST" action="" onsubmit="return confirm('Hapus bahan ini dari resep?')">
                      <?= csrfField() ?>
                      <input type="hidden" name="post_action" value="delete_detail">
                      <input type="hidden" name="detail_id" value="<?= $det['id'] ?>">
                      <input type="hidden" name="menu_id" value="<?= $selectedMenuId ?>">
                      <button type="submit" class="btn btn-danger btn-sm" style="padding: 4px 8px;">
                        <i class="fa-solid fa-trash"></i>
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Add Ingredient to Recipe Form -->
    <div class="card">
      <h4 style="font-size: 15px; font-weight: 700; color: var(--color-primary); margin-bottom: 14px;">
        <i class="fa-solid fa-plus-circle"></i> Tambah / Perbarui Bahan Resep
      </h4>

      <form method="POST" action="" style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 12px; align-items: flex-end;">
        <?= csrfField() ?>
        <input type="hidden" name="post_action" value="add_detail">
        <input type="hidden" name="menu_id" value="<?= $selectedMenuId ?>">

        <div class="form-group" style="margin: 0;">
          <label class="form-label">Pilih Bahan Baku</label>
          <select name="ingredient_id" class="form-select" required>
            <option value="">-- Pilih Bahan --</option>
            <?php foreach ($allIngredients as $ing): ?>
              <option value="<?= $ing['id'] ?>">
                <?= htmlspecialchars($ing['name']) ?> (Stok: <?= (float)$ing['stock'] ?> <?= $ing['unit'] ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group" style="margin: 0;">
          <label class="form-label">Jumlah Takaran</label>
          <input type="number" step="0.0001" name="amount_required" class="form-control" required placeholder="Contoh: 0.15">
        </div>

        <div class="form-group" style="margin: 0;">
          <label class="form-label">Satuan</label>
          <input type="text" name="unit" class="form-control" required placeholder="kg / liter / butir" value="kg">
        </div>

        <button type="submit" class="btn btn-primary" style="height: 42px;">
          <i class="fa-solid fa-plus"></i> Tambah ke Resep
        </button>
      </form>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
