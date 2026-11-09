/**
 * JAZUVA - Main Client-Side Application Scripts
 */

// Modal Utility
function openModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
  }
}

function closeModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.remove('show');
    document.body.style.overflow = '';
  }
}

// Close modal when clicking outside content
document.addEventListener('click', function(e) {
  if (e.target.classList.contains('modal-backdrop')) {
    e.target.classList.remove('show');
    document.body.style.overflow = '';
  }
});

// Format Rupiah in JS
function formatRupiah(num) {
  return 'Rp ' + Number(num).toLocaleString('id-ID');
}

function escapeHtml(value) {
  return String(value).replace(/[&<>"']/g, function(character) {
    return {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    }[character];
  });
}

// Auto dismiss flash alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
  const alerts = document.querySelectorAll('.alert');
  alerts.forEach(function(alert) {
    setTimeout(function() {
      alert.style.transition = 'opacity 0.5s ease';
      alert.style.opacity = '0';
      setTimeout(function() { alert.remove(); }, 500);
    }, 5000);
  });

  const sidebar = document.querySelector('.sidebar');
  const toggle = document.querySelector('.sidebar-toggle');
  if (!sidebar || !toggle) return;

  let backdrop;
  function closeSidebar() {
    sidebar.classList.remove('is-open');
    toggle.setAttribute('aria-expanded', 'false');
    if (backdrop) backdrop.remove();
    backdrop = null;
  }

  toggle.addEventListener('click', function() {
    if (sidebar.classList.contains('is-open')) {
      closeSidebar();
      return;
    }
    sidebar.classList.add('is-open');
    toggle.setAttribute('aria-expanded', 'true');
    backdrop = document.createElement('button');
    backdrop.type = 'button';
    backdrop.className = 'sidebar-backdrop';
    backdrop.setAttribute('aria-label', 'Tutup navigasi');
    backdrop.addEventListener('click', closeSidebar);
    document.body.appendChild(backdrop);
  });

  document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') closeSidebar();
  });
});
