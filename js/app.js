/**
 * Prismatch - Vanilla Application JS
 * Standalone UI interactions without any Bootstrap JS dependency
 */

(function () {
  'use strict';

  // Mobile navigation drawer / toggler
  document.addEventListener('DOMContentLoaded', () => {
    const navToggle = document.getElementById('pmNavToggle');
    const navCollapse = document.getElementById('pmNav');

    if (navToggle && navCollapse) {
      navToggle.addEventListener('click', (e) => {
        e.preventDefault();
        const isOpen = navCollapse.classList.toggle('show');
        navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      });

      // Close mobile nav when clicking outside
      document.addEventListener('click', (e) => {
        if (!navCollapse.contains(e.target) && !navToggle.contains(e.target) && navCollapse.classList.contains('show')) {
          navCollapse.classList.remove('show');
          navToggle.setAttribute('aria-expanded', 'false');
        }
      });
    }

    // Auto-dismiss alerts
    document.querySelectorAll('[data-dismiss="alert"]').forEach(btn => {
      btn.addEventListener('click', () => {
        const alert = btn.closest('.alert');
        if (alert) {
          alert.style.opacity = '0';
          alert.style.transform = 'translateY(-6px)';
          setTimeout(() => alert.remove(), 200);
        }
      });
    });
  });
})();
