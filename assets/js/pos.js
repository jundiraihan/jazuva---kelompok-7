/**
 * JAZUVA - Cashier POS Interaction & Calculator
 */

let selectedMethod = 'tunai';
let currentBillAmount = 0;

function selectPaymentMethod(method) {
  if (
    method === 'tunai' &&
    typeof activeQrisPayment !== 'undefined' &&
    activeQrisPayment?.sessionId === currentSelectedSessionId
  ) {
    const qrisState = document.getElementById('qris_payment_state');
    if (qrisState) {
      qrisState.textContent =
        'Selesaikan atau tunggu QRIS kedaluwarsa sebelum mengganti pembayaran ke tunai.';
    }
    return;
  }

  selectedMethod = method;
  document.getElementById('input_payment_method').value = method;
  
  const btnTunai = document.getElementById('btn_method_tunai');
  const btnQris = document.getElementById('btn_method_qris');
  const cashContainer = document.getElementById('cash_input_container');
  const qrisContainer = document.getElementById('qris_modal_container');
  const submitButtonText = document.querySelector('#btn_submit_payment span');

  if (method === 'tunai') {
    btnTunai.classList.add('active');
    btnQris.classList.remove('active');
    if (cashContainer) cashContainer.style.display = 'block';
    if (qrisContainer) qrisContainer.style.display = 'none';
    if (submitButtonText) submitButtonText.textContent = 'Proses Pembayaran Tunai';
  } else {
    btnQris.classList.add('active');
    btnTunai.classList.remove('active');
    if (cashContainer) cashContainer.style.display = 'none';
    if (qrisContainer) qrisContainer.style.display = 'block';
    if (submitButtonText) submitButtonText.textContent = 'Buat QRIS';
  }
  calculateChange();
}

function setQuickCash(amount) {
  const input = document.getElementById('amount_paid');
  if (input) {
    input.value = amount;
    calculateChange();
  }
}

function setExactCash() {
  const input = document.getElementById('amount_paid');
  if (input) {
    input.value = currentBillAmount;
    calculateChange();
  }
}

function calculateChange() {
  const total = currentBillAmount;
  const paidInput = document.getElementById('amount_paid');
  const changeEl = document.getElementById('change_amount_display');
  const changeValInput = document.getElementById('change_amount');
  const submitBtn = document.getElementById('btn_submit_payment');

  if (!paidInput || !changeEl) return;

  const rawPaid = paidInput.value.trim();
  const paid = rawPaid === '' ? null : Number(rawPaid);

  if (selectedMethod === 'qris') {
    changeEl.innerText = formatRupiah(0);
    if (changeValInput) changeValInput.value = 0;
    if (submitBtn) submitBtn.disabled = false;
    return;
  }

  if (paid === null || !Number.isFinite(paid) || paid < 0) {
    changeEl.innerText = 'Masukkan uang diterima';
    changeEl.style.color = '#E74C3C';
    if (changeValInput) changeValInput.value = 0;
    if (submitBtn) submitBtn.disabled = true;
    return;
  }

  const change = paid - total;

  if (change >= 0) {
    changeEl.innerText = formatRupiah(change);
    changeEl.style.color = '#27AE60';
    if (changeValInput) changeValInput.value = change;
    if (submitBtn) submitBtn.disabled = false;
  } else {
    changeEl.innerText = 'Kurang ' + formatRupiah(Math.abs(change));
    changeEl.style.color = '#E74C3C';
    if (changeValInput) changeValInput.value = 0;
    if (submitBtn) submitBtn.disabled = true;
  }
}
