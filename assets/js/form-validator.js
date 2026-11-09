document.addEventListener('submit', function (event) {
  const form = event.target;
  if (!(form instanceof HTMLFormElement) || !form.matches('[data-validate]')) return;

  if (!form.checkValidity()) {
    event.preventDefault();
    event.stopPropagation();
    form.classList.add('was-validated');
    const invalid = form.querySelector(':invalid');
    if (invalid) invalid.focus();
  }
});
