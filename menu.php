<?php
/**
 * JAZUVA - Customer Mobile Menu & Ordering Page
 * Modul Rivalino: Pemesanan Pelanggan Melalui QR Code
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = getDbConnection();

// QR credentials bind the order page to the table
$tableNumber = trim($_GET['meja'] ?? '01');
$tableToken = trim($_GET['token'] ?? '');
$table = getTableByNumber($tableNumber);

if (!$table) {
    http_response_code(404);
    die("<h3>Meja tidak ditemukan atau tidak aktif. Silakan hubungi staf atau pelayan resto.</h3><p><a href='" . BASE_URL . "/index.php'>Kembali ke Beranda</a></p>");
}

// Customer ordering is only available through the table's official QR code.
if (!preg_match('/^[a-f0-9]{64}$/i', $tableToken) || !hash_equals($table['token'], $tableToken)) {
    http_response_code(403);
    die("<h3>QR meja tidak valid. Silakan pindai QR resmi di meja atau hubungi staf.</h3><p><a href='" . BASE_URL . "/scan.php'>Pindai QR kembali</a></p>");
}

// Fetch all active categories
$stmtCat = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC");
$categories = $stmtCat->fetchAll();

// Fetch all menus with their categories
$stmtMenu = $pdo->query("SELECT m.*, c.name as category_name, c.slug as category_slug, c.target_station 
                         FROM menus m 
                         JOIN categories c ON m.category_id = c.id AND c.is_active = 1
                         ORDER BY c.sort_order ASC, m.is_favorite DESC, m.name ASC");
$menus = $stmtMenu->fetchAll();

// Check if this table currently has an active order/session
$stmtActiveSession = $pdo->prepare("SELECT ts.*, o.order_number, o.order_status, o.id as order_id 
                                   FROM table_sessions ts 
                                   LEFT JOIN orders o ON ts.id = o.session_id 
                                   WHERE ts.table_id = ? AND ts.status = 'active' 
                                   ORDER BY o.id DESC LIMIT 1");
$stmtActiveSession->execute([$table['id']]);
$activeSession = $stmtActiveSession->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Menu Meja <?= htmlspecialchars($table['table_number']) ?> - Jazuva</title>
  
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/customer.css">
</head>
<body class="customer-body">

  <!-- Header with Back Button and Table Badge -->
  <header class="customer-header">
    <div class="customer-topbar">
      <div style="display: flex; align-items: center; gap: 10px;">
        <!-- Tombol Back / Kembali ke Beranda -->
        <a href="<?= BASE_URL ?>/index.php" class="btn-customer-back" title="Kembali ke Beranda">
          <i class="fa-solid fa-arrow-left"></i>
          <span>Kembali</span>
        </a>

        <div class="customer-brand">
          <i class="fa-solid fa-mug-hot" style="color: #C69255; font-size: 18px;"></i>
          <span class="customer-brand-name">Jazuva</span>
        </div>
      </div>

      <div class="table-badge-pill">
        <i class="fa-solid fa-chair"></i>
        <span>Meja <?= htmlspecialchars($table['table_number']) ?></span>
      </div>
    </div>

    <!-- Search input -->
    <div class="customer-search-box">
      <i class="fa-solid fa-magnifying-glass customer-search-icon"></i>
      <input type="text" id="menu-search-input" class="customer-search-input" placeholder="Cari kopi, makanan, snack..." oninput="filterMenus()">
    </div>
  </header>

  <!-- Active Order Banner if exists -->
  <?php if ($activeSession && !empty($activeSession['order_number'])): ?>
    <div style="background: #EAF8EE; border-bottom: 1px solid #C3E6CB; padding: 10px 20px; display: flex; align-items: center; justify-content: space-between;">
      <div style="font-size: 12.5px; color: #1E7E34; font-weight: 600;">
        <i class="fa-solid fa-bell"></i> Ada pesanan aktif (<?= htmlspecialchars($activeSession['order_number']) ?>)
      </div>
      <a href="<?= BASE_URL ?>/order-status.php?order=<?= urlencode($activeSession['order_number']) ?>" style="font-size: 12px; font-weight: 700; color: #155724; text-decoration: underline;">
        Lihat Status Pesanan &rarr;
      </a>
    </div>
  <?php endif; ?>

  <!-- Category Scroll Bar -->
  <div class="category-scroll-bar">
    <button type="button" class="cat-chip active" onclick="selectCategory('all', this)">Semua Menu</button>
    <?php foreach ($categories as $cat): ?>
      <button type="button" class="cat-chip" onclick="selectCategory('<?= htmlspecialchars($cat['slug']) ?>', this)">
        <?= htmlspecialchars($cat['name']) ?>
      </button>
    <?php endforeach; ?>
  </div>

  <!-- Menu List -->
  <main class="customer-menu-container">
    <div id="menus-wrapper">
      <?php foreach ($menus as $m): ?>
        <?php 
          $fallbackImg = ($m['target_station'] === 'barista') ? 'default-drink.jpg' : 'default-food.jpg';
          $itemImg = !empty($m['image']) ? $m['image'] : $fallbackImg;
        ?>
        <div class="customer-menu-card menu-item-node" 
             data-category="<?= htmlspecialchars($m['category_slug']) ?>" 
             data-name="<?= strtolower(htmlspecialchars($m['name'])) ?>"
             data-id="<?= $m['id'] ?>">
          
          <div class="customer-card-img-wrap">
            <img src="<?= BASE_URL ?>/uploads/menus/<?= htmlspecialchars($itemImg) ?>" 
                 alt="<?= htmlspecialchars($m['name']) ?>" 
                 class="customer-card-img"
                 onerror="this.onerror=null; this.src='<?= BASE_URL ?>/uploads/menus/<?= $fallbackImg ?>';">
            
            <?php if (!$m['is_available']): ?>
              <div style="position: absolute; inset: 0; background: rgba(0,0,0,0.65); color: #FFF; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; text-transform: uppercase;">
                Habis
              </div>
            <?php elseif ($m['is_favorite']): ?>
              <div style="position: absolute; top: 6px; left: 6px; background: #C69255; color: #FFF; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: var(--radius-pill); z-index: 2;">
                Favorit
              </div>
            <?php endif; ?>
          </div>

          <div class="customer-card-details">
            <h4 class="customer-item-title"><?= htmlspecialchars($m['name']) ?></h4>
            <p class="customer-item-desc"><?= htmlspecialchars($m['description'] ?: 'Menu lezat pilihan khas Jazuva.') ?></p>
            
            <!-- Fitur Catatan Pelanggan pada Setiap Menu -->
            <div class="customer-item-note-wrapper">
              <div class="customer-card-note-box">
                <i class="fa-regular fa-comment-dots" style="color: #8B5A2B; font-size: 12px;"></i>
                <input type="text" id="note-menu-<?= $m['id'] ?>" class="customer-card-note-input" 
                       placeholder="Catatan (misal: pedas sedang, less sugar, tanpa es...)">
              </div>
            </div>

            <div class="customer-item-bottom">
              <span class="customer-item-price"><?= formatRupiah($m['price']) ?></span>

              <?php if ($m['is_available']): ?>
                <button type="button" class="btn-add-cart" id="btn-add-<?= $m['id'] ?>" 
                        data-name="<?= htmlspecialchars($m['name'], ENT_QUOTES, 'UTF-8') ?>" 
                        data-price="<?= (float)$m['price'] ?>" 
                        data-station="<?= htmlspecialchars($m['target_station'], ENT_QUOTES, 'UTF-8') ?>" 
                        onclick="addToCart(<?= (int)$m['id'] ?>)">
                  <i class="fa-solid fa-plus"></i> Tambah
                </button>
              <?php else: ?>
                <button type="button" class="btn btn-secondary btn-sm" disabled style="opacity: 0.6; font-size: 11px;">
                  Habis
                </button>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </main>

  <!-- Floating Cart Bar at Bottom -->
  <div class="floating-cart-bar" id="floating-cart-bar">
    <div class="cart-summary-text">
      <span class="cart-count-badge" id="cart-item-count">0 Item</span>
      <span class="cart-total-price" id="cart-total-price">Rp 0</span>
    </div>
    <button type="button" class="btn-view-cart" onclick="openCartDrawer()">
      <i class="fa-solid fa-basket-shopping"></i> Lihat Pesanan
    </button>
  </div>

  <!-- Cart Drawer / Bottom Sheet -->
  <div class="cart-drawer" id="cart-drawer">
    <div class="cart-drawer-sheet">
      <div class="cart-drawer-header">
        <div>
          <h3 style="font-size: 17px; font-weight: 800; color: var(--color-primary); margin: 0;">
            Pesanan Anda (Meja <?= htmlspecialchars($table['table_number']) ?>)
          </h3>
          <span style="font-size: 12px; color: var(--color-text-muted);">Periksa kembali pesanan Anda sebelum mengirim</span>
        </div>
        <button type="button" onclick="closeCartDrawer()" style="background:none; border:none; font-size: 20px; cursor: pointer; color: #555;">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>

      <div class="cart-drawer-body" id="cart-items-container">
        <!-- Cart Items injected via JavaScript -->
      </div>

      <div class="cart-drawer-footer">
        <div class="form-group" style="margin-bottom: 12px;">
          <label class="form-label" for="customer_name" style="font-size: 12px;">Nama Pemesan (Opsional)</label>
          <input type="text" id="customer_name" class="form-control" placeholder="Contoh: Budi / Meja <?= htmlspecialchars($table['table_number']) ?>" style="padding: 8px 12px; font-size: 13px;">
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
          <span style="font-size: 14px; font-weight: 700; color: var(--color-text-muted);">Total Pembayaran</span>
          <span style="font-size: 19px; font-weight: 800; color: var(--color-primary);" id="drawer-total-price">Rp 0</span>
        </div>

        <button type="button" id="btn-submit-order" class="btn btn-primary btn-block btn-lg" onclick="submitCustomerOrder()">
          <i class="fa-solid fa-paper-plane"></i>
          <span>Kirim Pesanan ke Restoran</span>
        </button>
      </div>
    </div>
  </div>

  <!-- Hidden form fields -->
  <input type="hidden" id="current_table_id" value="<?= $table['id'] ?>">
  <input type="hidden" id="current_table_number" value="<?= htmlspecialchars($table['table_number']) ?>">
  <input type="hidden" id="current_table_token" value="<?= htmlspecialchars($table['token']) ?>">

  <script src="<?= BASE_URL ?>/assets/js/app.js"></script>
  <script>
    let cart = [];
    let orderRequestToken = createOrderRequestToken();
    const csrfToken = <?= json_encode(generateCsrfToken()) ?>;

    function escapeHtml(str) {
      if (typeof str !== 'string') return '';
      return str.replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
    }

    function createOrderRequestToken() {
      if (window.crypto && typeof window.crypto.randomUUID === 'function') {
        return window.crypto.randomUUID();
      }
      const bytes = new Uint8Array(16);
      window.crypto.getRandomValues(bytes);
      const hex = Array.from(bytes, value => value.toString(16).padStart(2, '0')).join('');
      return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
    }

    // Filter categories
    function selectCategory(slug, btn) {
      document.querySelectorAll('.cat-chip').forEach(c => c.classList.remove('active'));
      btn.classList.add('active');

      const items = document.querySelectorAll('.menu-item-node');
      items.forEach(item => {
        if (slug === 'all' || item.getAttribute('data-category') === slug) {
          item.style.display = 'flex';
        } else {
          item.style.display = 'none';
        }
      });
    }

    // Search filter
    function filterMenus() {
      const q = document.getElementById('menu-search-input').value.toLowerCase().trim();
      const items = document.querySelectorAll('.menu-item-node');
      items.forEach(item => {
        const name = item.getAttribute('data-name');
        if (name.includes(q)) {
          item.style.display = 'flex';
        } else {
          item.style.display = 'none';
        }
      });
    }

    // Cart Management
    function addToCart(menuId) {
      const button = document.getElementById('btn-add-' + menuId) || document.querySelector(`.btn-add-cart[onclick="addToCart(${menuId})"]`);
      if (!button) return;

      // Ambil catatan khusus yang diketikkan pelanggan pada menu ini
      const noteInput = document.getElementById('note-menu-' + menuId);
      const itemNote = noteInput ? noteInput.value.trim() : '';

      // Cek apakah item dengan menuId dan catatan yang sama sudah ada di keranjang
      const existing = cart.find(i => i.menuId === menuId && (i.notes || '') === itemNote);
      if (existing) {
        if (existing.quantity >= 99) {
          alert('Jumlah maksimal untuk satu menu adalah 99.');
          return;
        }
        existing.quantity += 1;
      } else {
        cart.push({
          menuId: menuId,
          name: button.dataset.name,
          price: Number(button.dataset.price),
          station: button.dataset.station,
          quantity: 1,
          notes: itemNote
        });
      }

      renderCartUI();

      // Animasi feedback tombol
      const originalHtml = button.innerHTML;
      button.innerHTML = '<i class="fa-solid fa-check"></i> Ditambah';
      button.style.backgroundColor = '#27AE60';
      setTimeout(() => {
        button.innerHTML = originalHtml;
        button.style.backgroundColor = '';
      }, 700);

      // Subtle vibration on mobile devices
      if (navigator.vibrate) navigator.vibrate(40);
    }

    function updateCartQty(index, change) {
      if (!cart[index]) return;

      if (cart[index].quantity + change > 99) {
        alert('Jumlah maksimal untuk satu menu adalah 99.');
        return;
      }
      cart[index].quantity += change;
      if (cart[index].quantity <= 0) {
        cart.splice(index, 1);
      }
      renderCartUI();
    }

    function updateCartNotesByIndex(index, notes) {
      if (cart[index]) {
        cart[index].notes = notes;
      }
    }

    function renderCartUI() {
      const count = cart.reduce((sum, item) => sum + item.quantity, 0);
      const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);

      const floatingBar = document.getElementById('floating-cart-bar');
      const countEl = document.getElementById('cart-item-count');
      const totalEl = document.getElementById('cart-total-price');
      const drawerTotalEl = document.getElementById('drawer-total-price');

      if (count > 0) {
        floatingBar.classList.add('active');
        countEl.innerText = count + ' Item Dipilih';
        totalEl.innerText = formatRupiah(total);
        drawerTotalEl.innerText = formatRupiah(total);
      } else {
        floatingBar.classList.remove('active');
        closeCartDrawer();
      }

      // Render Drawer items
      const container = document.getElementById('cart-items-container');
      if (cart.length === 0) {
        container.innerHTML = '<div style="text-align:center; padding: 30px; color: #888;">Keranjang belanja Anda masih kosong.</div>';
        return;
      }

      let html = '';
      cart.forEach((item, idx) => {
        html += `
          <div class="cart-item-row">
            <div style="flex: 1; padding-right: 12px;">
              <div style="font-size: 14px; font-weight: 700; color: #331B11;">${escapeHtml(item.name)}</div>
              <div style="font-size: 13px; font-weight: 600; color: #8B5A2B; margin-top: 2px;">
                ${formatRupiah(item.price)} x ${item.quantity} = ${formatRupiah(item.price * item.quantity)}
              </div>
              <div style="margin-top: 6px;">
                <input type="text" class="form-control" style="font-size: 11.5px; padding: 4px 8px;" 
                       placeholder="Catatan (e.g. Pedas sedang, Less sugar)" 
                       value="${escapeHtml(item.notes)}" 
                       oninput="updateCartNotesByIndex(${idx}, this.value)">
              </div>
            </div>
            <div class="cart-qty-ctrl">
              <button type="button" class="cart-qty-btn" onclick="updateCartQty(${idx}, -1)">-</button>
              <span style="font-weight: 700; min-width: 20px; text-align: center;">${item.quantity}</span>
              <button type="button" class="cart-qty-btn" onclick="updateCartQty(${idx}, 1)">+</button>
            </div>
          </div>
        `;
      });
      container.innerHTML = html;
    }

    function openCartDrawer() {
      document.getElementById('cart-drawer').classList.add('open');
      renderCartUI();
    }

    function closeCartDrawer() {
      document.getElementById('cart-drawer').classList.remove('open');
    }

    // Submit Order to backend API
    async function submitCustomerOrder() {
      if (cart.length === 0) {
        alert('Keranjang belanja Anda kosong!');
        return;
      }

      const btn = document.getElementById('btn-submit-order');
      btn.disabled = true;
      btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Mengirim pesanan...';

      const tableId = document.getElementById('current_table_id').value;
      const tableNumber = document.getElementById('current_table_number').value;
      const customerName = document.getElementById('customer_name').value.trim() || `Pelanggan Meja ${tableNumber}`;

      try {
        const response = await fetch('<?= BASE_URL ?>/api/orders.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
          },
          body: JSON.stringify({
            action: 'create_order',
            table_id: tableId,
            table_token: document.getElementById('current_table_token').value,
            request_token: orderRequestToken,
            customer_name: customerName,
            items: cart
          })
        });

        const res = await response.json();

        if (res.success) {
          cart = [];
          renderCartUI();
          window.location.href = `<?= BASE_URL ?>/order-status.php?order=${encodeURIComponent(res.order_number)}`;
        } else {
          alert('Gagal mengirim pesanan: ' + (res.message || 'Terjadi kesalahan'));
          btn.disabled = false;
          btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Kirim Pesanan ke Restoran';
        }
      } catch (err) {
        console.error(err);
        alert('Terjadi kesalahan koneksi saat mengirim pesanan. Silakan periksa jaringan Anda.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Kirim Pesanan ke Restoran';
      }
    }
  </script>
</body>
</html>
