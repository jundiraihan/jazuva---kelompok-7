<?php
/**
 * JAZUVA - Cashier POS Dashboard
 * Matches Prototype Cashier Screen
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['admin', 'cashier']);

$currentUser = getCurrentUser();
$pdo = getDbConnection();

// Fetch all tables to show filter tabs
$allTables = $pdo->query("SELECT * FROM restaurant_tables WHERE is_active = 1 ORDER BY table_number ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Jazuva | Kasir (POS)</title>
  
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dashboard.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/pos.css">
  
  <style>
    body {
      background-color: #F8F3EC;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }
    .pos-top-bar {
      background: #2F1A10;
      color: #FFFFFF;
      padding: 14px 28px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .pos-brand-title {
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 19px;
      font-weight: 700;
    }
    .quick-cash-row {
      display: flex;
      gap: 8px;
      margin-top: 8px;
    }
    .btn-quick-cash {
      flex: 1;
      padding: 6px 10px;
      font-size: 12px;
      font-weight: 700;
      background: #FAF5EE;
      border: 1px solid var(--color-border);
      border-radius: var(--radius-sm);
      cursor: pointer;
      color: var(--color-primary);
      transition: var(--transition-fast);
    }
    .btn-quick-cash:hover {
      background: #8B5A2B;
      color: #FFFFFF;
      border-color: #8B5A2B;
    }

    @media (max-width: 600px) {
      .pos-top-bar {
        flex-wrap: wrap;
        gap: 12px;
        padding: 12px 16px;
      }

      .pos-top-bar > div:last-child {
        width: 100%;
        justify-content: flex-end;
        flex-wrap: wrap;
        gap: 12px !important;
      }
    }
  </style>
</head>
<body>

  <!-- Top Dark Brown Bar Matching Prototype -->
  <header class="pos-top-bar">
    <div class="pos-brand-title">
      <i class="fa-solid fa-cash-register" style="color: #C69255;"></i>
      <span>Jazuva | Kasir (POS)</span>
    </div>

    <div style="display: flex; align-items: center; gap: 16px;">
      <a href="<?= BASE_URL ?>/cashier/history.php" class="btn btn-secondary btn-sm" style="background: rgba(255,255,255,0.15); color: #FFF; border: none;">
        <i class="fa-solid fa-clock-rotate-left"></i> Riwayat Transaksi
      </a>

      <?php if (hasRole('admin')): ?>
        <a href="<?= BASE_URL ?>/admin/dashboard.php" class="btn btn-secondary btn-sm" style="background: rgba(255,255,255,0.15); color: #FFF; border: none;">
          <i class="fa-solid fa-gauge"></i> Admin Dashboard
        </a>
      <?php endif; ?>

      <div class="user-profile-pill" style="background: rgba(255,255,255,0.1); color: #FFF; border: 1px solid rgba(255,255,255,0.2);">
        <div class="user-avatar" style="background: #C69255;">
          <i class="fa-solid fa-user"></i>
        </div>
        <span>Kasir</span>
      </div>

      <a href="<?= BASE_URL ?>/logout.php" title="Logout" style="color: #E57373; font-size: 16px;">
        <i class="fa-solid fa-right-from-bracket"></i>
      </a>
    </div>
  </header>

  <!-- POS Main Body Split 2 Columns -->
  <main class="page-content" style="max-width: 1300px; margin: 0 auto; width: 100%;">
    <div class="pos-container">
      
      <!-- LEFT COLUMN: Daftar Pesanan per Meja -->
      <div class="pos-panel">
        <div class="pos-panel-header">
          <div class="pos-panel-title">Daftar Pesanan per Meja</div>
          <button type="button" class="btn btn-outline btn-sm" onclick="fetchTableOrders()" title="Refresh Data">
            <i class="fa-solid fa-rotate-right"></i> Refresh
          </button>
        </div>

        <div class="pos-panel-body">
          <!-- Filter Tabs -->
          <div class="nav-pills" id="table-filter-tabs">
            <button type="button" class="nav-pill active" onclick="filterByTable('all', this)">Semua</button>
            <?php foreach (array_slice($allTables, 0, 6) as $tbl): ?>
              <button type="button" class="nav-pill" onclick="filterByTable('<?= htmlspecialchars($tbl['table_number']) ?>', this)">
                Meja <?= htmlspecialchars($tbl['table_number']) ?>
              </button>
            <?php endforeach; ?>
          </div>

          <!-- Orders Table List -->
          <div id="orders-list-container">
            <div style="text-align: center; padding: 40px; color: var(--color-text-muted);">
              <i class="fa-solid fa-spinner fa-spin"></i> Memuat daftar pesanan...
            </div>
          </div>
        </div>
      </div>

      <!-- RIGHT COLUMN: Detail Pesanan Meja Selected -->
      <div class="pos-panel">
        <div class="pos-panel-header">
          <div class="pos-panel-title" id="detail-panel-title">Detail Pesanan - Pilih Meja</div>
        </div>

        <div class="pos-panel-body" id="detail-panel-body">
          <div style="text-align: center; padding: 60px 20px; color: var(--color-text-muted);">
            <i class="fa-solid fa-hand-pointer" style="font-size: 36px; color: #C69255; margin-bottom: 12px;"></i>
            <p style="font-size: 14px; font-weight: 600;">Silakan pilih salah satu meja di sebelah kiri untuk melihat rincian tagihan dan memproses pembayaran.</p>
          </div>
        </div>
      </div>

    </div>
  </main>

  <script src="<?= BASE_URL ?>/assets/js/app.js"></script>
  <script src="<?= BASE_URL ?>/assets/js/pos.js"></script>
  <script>
    const csrfToken = <?= json_encode(generateCsrfToken()) ?>;
    let activeOrdersData = [];
    let currentSelectedTableId = null;
    let currentSelectedSessionId = null;
    let activeQrisPayment = null;
    let currentActiveFilter = 'all';

    async function fetchTableOrders() {
      try {
        const response = await fetch('<?= BASE_URL ?>/api/cashier.php?action=list_tables');
        const data = await response.json();
        if (data.success) {
          activeOrdersData = data.tables;
          renderOrdersList();

          // If a table is already selected, re-fetch its details
          if (currentSelectedTableId) {
            loadTableDetail(currentSelectedTableId, true);
          } else if (activeOrdersData.length > 0) {
            // Select first table automatically like prototype (Meja 01)
            loadTableDetail(activeOrdersData[0].table_id);
          }
        }
      } catch (e) {
        console.error('Error fetching cashier tables:', e);
      }
    }

    function filterByTable(tableNum, btn) {
      currentActiveFilter = tableNum;
      document.querySelectorAll('#table-filter-tabs .nav-pill').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      renderOrdersList();
    }

    function renderOrdersList() {
      const container = document.getElementById('orders-list-container');
      
      const filtered = activeOrdersData.filter(row => {
        if (currentActiveFilter === 'all') return true;
        return row.table_number === currentActiveFilter;
      });

      if (filtered.length === 0) {
        container.innerHTML = `
          <div style="text-align: center; padding: 40px; color: #888;">
            <i class="fa-solid fa-clipboard-check" style="font-size: 32px; color: #27AE60; margin-bottom: 8px;"></i>
            <p>Tidak ada pesanan aktif pada meja ini.</p>
          </div>
        `;
        return;
      }

      let html = '';
      filtered.forEach(row => {
        const isSelected = row.table_id === currentSelectedTableId ? 'active' : '';
        html += `
          <div class="pos-table-row ${isSelected}" onclick="loadTableDetail(${Number(row.table_id)})">
            <div class="pos-table-num">Meja ${escapeHtml(row.table_number)}</div>
            <div class="pos-table-summary">${escapeHtml(row.summary_items)}</div>
            <div class="pos-table-price">${formatRupiah(row.total_bill)}</div>
          </div>
        `;
      });
      container.innerHTML = html;
    }

    async function loadTableDetail(tableId, preservePaymentInput = false) {
      const previousTableId = currentSelectedTableId;
      const previousSessionId = currentSelectedSessionId;
      const previousAmount = previousTableId === tableId
        ? document.getElementById('amount_paid')?.value ?? ''
        : '';
      const previousMethod = previousTableId === tableId
        ? document.getElementById('input_payment_method')?.value ?? 'tunai'
        : 'tunai';
      currentSelectedTableId = tableId;
      renderOrdersList();

      const titleEl = document.getElementById('detail-panel-title');
      const bodyEl = document.getElementById('detail-panel-body');

      try {
        const response = await fetch(`<?= BASE_URL ?>/api/cashier.php?action=table_detail&table_id=${tableId}`);
        const data = await response.json();

        if (!data.success) {
          titleEl.innerText = 'Detail Pesanan';
          bodyEl.innerHTML = `<div class="alert alert-warning">${escapeHtml(data.message)}</div>`;
          return;
        }

        const session = data.session;
        const items = data.items;
        const sessionId = Number(session.id);
        const sameSession = previousTableId === tableId && previousSessionId === sessionId;
        const pendingQris = activeQrisPayment?.sessionId === sessionId ? activeQrisPayment : null;
        currentSelectedSessionId = sessionId;
        currentBillAmount = data.total;

        titleEl.innerText = `Detail Pesanan - Meja ${session.table_number}`;

        let itemsRows = '';
        items.forEach(it => {
          itemsRows += `
            <tr>
              <td><strong>${escapeHtml(it.menu_name)}</strong></td>
              <td class="text-center">${it.quantity}</td>
              <td class="text-right">${formatRupiah(it.unit_price)}</td>
              <td class="text-right"><strong>${formatRupiah(it.subtotal)}</strong></td>
            </tr>
          `;
        });

        bodyEl.innerHTML = `
          <table class="pos-items-table">
            <thead>
              <tr>
                <th>Menu</th>
                <th class="text-center">Qty</th>
                <th class="text-right">Harga</th>
                <th class="text-right">Subtotal</th>
              </tr>
            </thead>
            <tbody>
              ${itemsRows}
            </tbody>
          </table>

          <div class="pos-calculation">
            <div class="calc-row total-row">
              <span>Subtotal</span>
              <span>${formatRupiah(data.subtotal)}</span>
            </div>
            <div class="calc-row">
              <span>Service Charge (<?= htmlspecialchars(getSetting('service_charge', '0')) ?>%)</span>
              <span>${formatRupiah(data.service_charge_amount)}</span>
            </div>
            <div class="calc-row">
              <span>PB1 (<?= htmlspecialchars(getSetting('tax_percentage', '0')) ?>%)</span>
              <span>${formatRupiah(data.tax_amount)}</span>
            </div>
            <div class="calc-row total-row">
              <span>Total Tagihan</span>
              <span>${formatRupiah(data.total)}</span>
            </div>

            <div style="font-size: 13px; font-weight: 700; color: var(--color-primary); margin-bottom: 8px;">
              Metode Pembayaran
            </div>

            <div class="payment-methods-grid">
              <button type="button" id="btn_method_tunai" class="payment-method-btn active" onclick="selectPaymentMethod('tunai')">
                <i class="fa-solid fa-money-bill-wave"></i> Tunai
              </button>
              <button type="button" id="btn_method_qris" class="payment-method-btn" onclick="selectPaymentMethod('qris')">
                <i class="fa-solid fa-qrcode"></i> QRIS
              </button>
            </div>

            <input type="hidden" id="input_payment_method" value="tunai">
            <input type="hidden" id="change_amount" value="0">

            <!-- Tunai container -->
            <div id="cash_input_container">
              <div class="form-group" style="margin-bottom: 8px;">
                <label class="form-label">Uang Diterima (Rp)</label>
                <input type="number" id="amount_paid" class="form-control" min="0" step="1" inputmode="numeric"
                       placeholder="Masukkan uang yang diterima" oninput="calculateChange()">
                <span style="font-size: 11px; color: var(--color-text-muted);">Isi sesuai uang dari pelanggan. Pembayaran kurang dari total tagihan tidak dapat diproses.</span>
                <div class="quick-cash-row">
                  <button type="button" class="btn-quick-cash" onclick="setExactCash()">Uang Pas</button>
                  <button type="button" class="btn-quick-cash" onclick="setQuickCash(50000)">Rp 50.000</button>
                  <button type="button" class="btn-quick-cash" onclick="setQuickCash(100000)">Rp 100.000</button>
                </div>
              </div>

              <div class="calc-row" style="margin-top: 12px; font-weight: 700;">
                <span>Kembalian</span>
                <span id="change_amount_display" style="color: #27AE60; font-size: 16px;">Rp 0</span>
              </div>
            </div>

            <!-- QRIS container -->
            <div id="qris_modal_container" style="display: none; text-align: center; padding: 14px; background: #FFF; border-radius: 8px; border: 1px dashed var(--color-border); margin-bottom: 12px;">
              <div style="font-size: 13px; font-weight: 700; color: #8B5A2B; margin-bottom: 6px;">QRIS Midtrans</div>
              <p id="qris_payment_state" style="font-size: 12px; color: #7E7065; margin: 8px 0;">
                Buat QR untuk menampilkan nominal tagihan ini.
              </p>
              <img id="qris_qr_image" alt="QR pembayaran Midtrans" style="display: none; width: min(260px, 100%); margin: 0 auto;">
              <p style="font-size: 11px; color: #7E7065; margin-top: 6px;">
                Pindai QR menggunakan aplikasi bank atau dompet digital yang mendukung QRIS. Pembayaran akan diverifikasi otomatis.
              </p>
            </div>

            <button type="button" id="btn_submit_payment" class="btn btn-primary btn-block btn-lg" 
                    style="margin-top: 14px;" onclick="executePayment(${session.id})">
              <i class="fa-solid fa-circle-check"></i>
              <span>Proses Pembayaran</span>
            </button>
          </div>
        `;

        const amountInput = document.getElementById('amount_paid');
        if (preservePaymentInput && sameSession && amountInput) {
          amountInput.value = previousAmount;
        }
        selectPaymentMethod(pendingQris ? 'qris' : preservePaymentInput && sameSession ? previousMethod : 'tunai');
        if (pendingQris) {
          document.getElementById('qris_qr_image').src = pendingQris.qrUrl;
          document.getElementById('qris_qr_image').style.display = 'block';
          document.getElementById('qris_payment_state').textContent =
            `Tagihan ${formatRupiah(pendingQris.total)}. Menunggu pembayaran QRIS...`;
          const submitButton = document.getElementById('btn_submit_payment');
          submitButton.disabled = true;
          submitButton.innerHTML = '<i class="fa-solid fa-clock"></i> Menunggu Pembayaran QRIS';
        }
        calculateChange();

      } catch (err) {
        console.error('Error loading table detail:', err);
      }
    }

    async function executePayment(sessionId) {
      const method = document.getElementById('input_payment_method').value;
      if (method === 'qris') {
        await createQrisPayment(sessionId);
        return;
      }

      const amountInput = document.getElementById('amount_paid');
      const amountPaid = amountInput.value.trim() !== ''
        ? Number(amountInput.value)
        : 0;
      const submitBtn = document.getElementById('btn_submit_payment');

      if (method === 'tunai' && (!Number.isFinite(amountPaid) || amountPaid < currentBillAmount)) {
        calculateChange();
        amountInput.focus();
        return;
      }

      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memproses transaksi...';

      try {
        const response = await fetch('<?= BASE_URL ?>/api/cashier.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
          body: JSON.stringify({
            action: 'process_payment',
            session_id: sessionId,
            payment_method: method,
            amount_paid: amountPaid
          })
        });

        const res = await response.json();

        if (res.success) {
          // Open thermal receipt page/popup
          window.location.href = `<?= BASE_URL ?>/cashier/receipt.php?trx=${encodeURIComponent(res.transaction_number)}`;
        } else {
          alert('Gagal memproses pembayaran: ' + res.message);
          submitBtn.disabled = false;
          submitBtn.innerHTML = '<i class="fa-solid fa-circle-check"></i> Proses Pembayaran';
        }
      } catch (err) {
        console.error(err);
        alert('Terjadi kesalahan koneksi.');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fa-solid fa-circle-check"></i> Proses Pembayaran';
      }
    }

    async function createQrisPayment(sessionId) {
      const submitBtn = document.getElementById('btn_submit_payment');
      const stateEl = document.getElementById('qris_payment_state');
      const qrImage = document.getElementById('qris_qr_image');
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Membuat QRIS...';
      stateEl.textContent = 'Menghubungkan ke Midtrans...';
      qrImage.style.display = 'none';

      try {
        const response = await fetch('<?= BASE_URL ?>/api/cashier.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
          body: JSON.stringify({ action: 'create_qris', session_id: sessionId })
        });
        const result = await response.json();
        if (!response.ok || !result.success) {
          throw new Error(result.message || 'QRIS belum dapat dibuat.');
        }

        if (result.status === 'lunas') {
          window.location.href = `<?= BASE_URL ?>/cashier/receipt.php?trx=${encodeURIComponent(result.transaction_number)}`;
          return;
        }

        qrImage.src = result.qr_url;
        qrImage.style.display = 'block';
        stateEl.textContent = `Tagihan ${formatRupiah(result.total)}. Menunggu pembayaran QRIS...`;
        activeQrisPayment = {
          sessionId: Number(sessionId),
          transactionNumber: result.transaction_number,
          qrUrl: result.qr_url,
          total: Number(result.total)
        };
        submitBtn.innerHTML = '<i class="fa-solid fa-clock"></i> Menunggu Pembayaran QRIS';
        pollQrisPayment(result.transaction_number);
      } catch (error) {
        console.error('Error creating Midtrans QRIS payment:', error);
        stateEl.textContent = error.message || 'QRIS gagal dibuat. Silakan coba kembali.';
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fa-solid fa-qrcode"></i> Buat QRIS';
      }
    }

    async function pollQrisPayment(transactionNumber) {
      const stateEl = document.getElementById('qris_payment_state');
      const submitBtn = document.getElementById('btn_submit_payment');
      const qrisPayment = activeQrisPayment?.transactionNumber === transactionNumber
        ? activeQrisPayment
        : null;
      const isCurrentSession = qrisPayment && currentSelectedSessionId === qrisPayment.sessionId;

      try {
        const response = await fetch(
          `<?= BASE_URL ?>/api/cashier.php?action=qris_status&trx=${encodeURIComponent(transactionNumber)}`
        );
        const result = await response.json();
        if (!response.ok || !result.success) {
          throw new Error(result.message || 'Status pembayaran belum dapat diperiksa.');
        }
        if (result.status === 'lunas') {
          activeQrisPayment = null;
          if (isCurrentSession) {
            window.location.href = `<?= BASE_URL ?>/cashier/receipt.php?trx=${encodeURIComponent(transactionNumber)}`;
          }
          return;
        }
        if (result.status === 'batal') {
          activeQrisPayment = null;
          if (isCurrentSession) {
            stateEl.textContent = 'QRIS kedaluwarsa atau dibatalkan. Buat QRIS baru untuk mencoba kembali.';
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fa-solid fa-qrcode"></i> Buat QRIS Baru';
            document.getElementById('qris_qr_image').style.display = 'none';
          }
          return;
        }
        if (isCurrentSession) {
          stateEl.textContent = 'Menunggu pembayaran QRIS... Status akan diperiksa otomatis.';
        }
      } catch (error) {
        console.error('Error checking Midtrans QRIS status:', error);
        if (isCurrentSession) {
          stateEl.textContent = 'Menunggu pembayaran. Status Midtrans belum dapat diperiksa; sistem akan mencoba kembali.';
        }
      }

      window.setTimeout(() => pollQrisPayment(transactionNumber), 4000);
    }

    // Initial load
    fetchTableOrders();
    setInterval(fetchTableOrders, 5000);
  </script>
</body>
</html>
