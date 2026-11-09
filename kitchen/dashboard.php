<?php
/**
 * JAZUVA - Kitchen Display Dashboard
 * Matches Prototype Kitchen Screen
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['admin', 'kitchen']);

$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Jazuva | Kitchen Dashboard</title>
  
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dashboard.css">
  
  <style>
    body {
      background-color: #F8F3EC;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }
    .kitchen-top-bar {
      background: #2F1A10;
      color: #FFFFFF;
      padding: 14px 28px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .kitchen-brand-title {
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 19px;
      font-weight: 700;
    }
    .status-badge-online {
      display: flex;
      align-items: center;
      gap: 8px;
      color: #2ECC71;
      font-weight: 600;
      font-size: 13.5px;
    }
    .status-badge-online .dot {
      width: 10px;
      height: 10px;
      background: #2ECC71;
      border-radius: 50%;
      box-shadow: 0 0 0 3px rgba(46, 204, 113, 0.3);
      animation: pulse 2s infinite;
    }
    @keyframes pulse {
      0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(46, 204, 113, 0.7); }
      70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(46, 204, 113, 0); }
      100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(46, 204, 113, 0); }
    }
  </style>
</head>
<body>

  <!-- Top Dark Brown Bar -->
  <header class="kitchen-top-bar">
    <div class="kitchen-brand-title">
      <i class="fa-solid fa-kitchen-set" style="color: #C69255;"></i>
      <span>Jazuva | Kitchen Dashboard</span>
    </div>

    <div style="display: flex; align-items: center; gap: 20px;">
      <button type="button" id="btn-sound-toggle" onclick="toggleAudio()" class="btn btn-secondary btn-sm" style="background: rgba(255,255,255,0.1); color: #FFF; border: none;">
        <i class="fa-solid fa-bell"></i> <span>Suara: Aktif</span>
      </button>

      <?php if (hasRole('admin')): ?>
        <a href="<?= BASE_URL ?>/admin/dashboard.php" class="btn btn-secondary btn-sm" style="background: rgba(255,255,255,0.15); color: #FFF; border: none;">
          <i class="fa-solid fa-gauge"></i> Admin Dashboard
        </a>
      <?php endif; ?>

      <div class="status-badge-online">
        <span class="dot"></span>
        <span>Online</span>
      </div>

      <a href="<?= BASE_URL ?>/logout.php" title="Logout" style="color: #E57373; font-size: 16px;">
        <i class="fa-solid fa-right-from-bracket"></i>
      </a>
    </div>
  </header>

  <!-- Section Header -->
  <div class="station-subbar">
    <h2 class="station-queue-title">Antrean Pesanan Makanan</h2>
    <div class="station-queue-count" id="queue-total-count">Total: 0 Pesanan</div>
  </div>

  <!-- Orders Grid Container -->
  <main class="order-queue-grid" id="kitchen-queue-container">
    <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: var(--color-text-muted);">
      <i class="fa-solid fa-spinner fa-spin" style="font-size: 24px;"></i>
      <p style="margin-top: 10px;">Memuat antrean pesanan makanan...</p>
    </div>
  </main>

  <!-- Bottom Dark Brown Strip Matching Prototype -->
  <footer class="station-bottom-bar">
    <i class="fa-solid fa-bell-concierge" style="color: #C69255; font-size: 16px;"></i>
    <span id="bottom-summary-text">Memeriksa antrean pesanan...</span>
  </footer>

  <script src="<?= BASE_URL ?>/assets/js/audio-chime.js"></script>
  <script>
    const csrfToken = <?= json_encode(generateCsrfToken()) ?>;
    let soundEnabled = true;
    let lastOrderCount = 0;
    const escapeHtml = value => String(value).replace(/[&<>"']/g, character => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    })[character]);

    function toggleAudio() {
      soundEnabled = !soundEnabled;
      const btn = document.getElementById('btn-sound-toggle');
      if (soundEnabled) {
        btn.innerHTML = '<i class="fa-solid fa-bell"></i> <span>Suara: Aktif</span>';
        window.jazuvaChime.play();
      } else {
        btn.innerHTML = '<i class="fa-solid fa-bell-slash"></i> <span>Suara: Nonaktif</span>';
      }
    }

    async function fetchKitchenQueue() {
      try {
        const response = await fetch('<?= BASE_URL ?>/api/kitchen.php');
        const data = await response.json();

        if (data.success) {
          // Play sound if order count increased
          if (soundEnabled && data.count > lastOrderCount && lastOrderCount !== 0) {
            window.jazuvaChime.play();
          }
          lastOrderCount = data.count;

          document.getElementById('queue-total-count').innerText = `Total: ${data.count} Pesanan`;
          document.getElementById('bottom-summary-text').innerText = data.tables_summary;

          renderKitchenCards(data.orders);
        }
      } catch (err) {
        console.error('Error fetching kitchen queue:', err);
      }
    }

    function renderKitchenCards(orders) {
      const container = document.getElementById('kitchen-queue-container');

      if (!orders || orders.length === 0) {
        container.innerHTML = `
          <div style="grid-column: 1 / -1; text-align: center; padding: 60px 20px; background: #FFF; border-radius: 16px; border: 1px dashed #DCCBBF;">
            <i class="fa-solid fa-circle-check" style="font-size: 44px; color: #27AE60; margin-bottom: 12px;"></i>
            <h3 style="font-size: 18px; color: #331B11;">Semua Pesanan Makanan Selesai</h3>
            <p style="font-size: 13px; color: #7E7065; margin-top: 4px;">Belum ada pesanan makanan baru yang masuk.</p>
          </div>
        `;
        return;
      }

      let html = '';
      orders.forEach(order => {
        let badgeClass = 'badge-menunggu';
        let badgeText = 'Menunggu';
        if (order.overall_status === 'diproses') {
          badgeClass = 'badge-diproses';
          badgeText = 'Diproses';
        } else if (order.overall_status === 'siap') {
          badgeClass = 'badge-siap';
          badgeText = 'Siap';
        }

        let itemsHtml = '';
        order.items.forEach(it => {
          itemsHtml += `
            <div class="ticket-item-row">
              <span>${escapeHtml(it.menu_name)}</span>
              <span style="color: var(--color-primary); font-weight: 800;">x${it.quantity}</span>
            </div>
            ${it.notes ? `<div class="ticket-item-note">"${escapeHtml(it.notes)}"</div>` : ''}
          `;
        });

        // Action buttons based on status
        let actionBtn = '';
        if (order.overall_status === 'menunggu') {
          actionBtn = `<button type="button" class="btn btn-caramel btn-sm" onclick="updateOrderStatus(${order.order_id}, 'diproses')"><i class="fa-solid fa-fire-burner"></i> Proses</button>`;
        } else if (order.overall_status === 'diproses') {
          actionBtn = `<button type="button" class="btn btn-success btn-sm" onclick="updateOrderStatus(${order.order_id}, 'siap')"><i class="fa-solid fa-check"></i> Siap Saji</button>`;
        } else if (order.overall_status === 'siap') {
          actionBtn = `<button type="button" class="btn btn-secondary btn-sm" onclick="updateOrderStatus(${order.order_id}, 'selesai')"><i class="fa-solid fa-flag-checkered"></i> Selesai</button>`;
        }

        html += `
          <div class="order-ticket-card">
            <div class="order-ticket-header">
              <span class="ticket-number">${escapeHtml(order.short_code)}</span>
              <span class="badge ${badgeClass}">${badgeText}</span>
            </div>

            <div class="order-ticket-body">
              <div class="ticket-table-name">Meja ${escapeHtml(order.table_number)}</div>
              <div style="margin-top: 8px;">
                ${itemsHtml}
              </div>
            </div>

            <div class="order-ticket-footer">
              <div class="ticket-elapsed-time">
                <i class="fa-regular fa-clock"></i>
                <span>${escapeHtml(order.elapsed)}</span>
              </div>
              <div>
                ${actionBtn}
              </div>
            </div>
          </div>
        `;
      });

      container.innerHTML = html;
    }

    async function updateOrderStatus(orderId, newStatus) {
      try {
        const response = await fetch('<?= BASE_URL ?>/api/kitchen.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
          body: JSON.stringify({ order_id: orderId, status: newStatus })
        });
        const res = await response.json();
        if (res.success) {
          fetchKitchenQueue();
        } else {
          alert('Gagal memperbarui status: ' + res.message);
        }
      } catch (e) {
        console.error(e);
      }
    }

    // Initial fetch & polling every 4 seconds
    fetchKitchenQueue();
    setInterval(fetchKitchenQueue, 4000);
  </script>
</body>
</html>
