document.addEventListener('input', function (event) {
  const input = event.target;
  if (!(input instanceof HTMLInputElement) || !input.matches('[data-currency-mask]')) return;

  const digits = input.value.replace(/[^\d]/g, '');
  input.value = digits ? new Intl.NumberFormat('id-ID').format(Number(digits)) : '';
});

document.addEventListener('submit', function (event) {
  const form = event.target;
  if (!(form instanceof HTMLFormElement)) return;
  form.querySelectorAll('[data-currency-mask]').forEach(function (input) {
    input.value = input.value.replace(/[^\d]/g, '');
  });
});
