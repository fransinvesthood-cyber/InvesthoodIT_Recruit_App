/* ================================================
   INVESTHOOD IT - Applications JS
   ================================================
   Handles interactions on the candidate applications pages:
   - Sidebar/drawer toggle
   - Dark mode
   - Empty state fallback (if needed)
   - Stats counter animations
   - Responsive details for mobile
*/

'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const sidebar = document.getElementById('sidebar');
  const sidebarToggle = document.getElementById('sidebarToggle');
  const sidebarClose = document.getElementById('sidebarClose');
  const overlay = document.getElementById('sidebarOverlay');

  function openSidebar() {
    if (sidebar) sidebar.classList.add('open');
    if (overlay) overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function closeSidebar() {
    if (sidebar) sidebar.classList.remove('open');
    if (overlay) overlay.classList.remove('open');
    document.body.style.overflow = '';
  }

  if (sidebarToggle) sidebarToggle.addEventListener('click', openSidebar);
  if (sidebarClose) sidebarClose.addEventListener('click', closeSidebar);
  if (overlay) overlay.addEventListener('click', closeSidebar);

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && sidebar && sidebar.classList.contains('open')) closeSidebar();
  });

  // Dark mode
  const themeToggle = document.getElementById('themeToggle');
  if (themeToggle) {
    themeToggle.addEventListener('click', function () {
      document.body.classList.toggle('dark-mode');
      const icon = this.querySelector('i');
      if (document.body.classList.contains('dark-mode')) {
        icon.className = 'fas fa-sun';
      } else {
        icon.className = 'fas fa-moon';
      }
    });
  }

  // Optional: Animate stat numbers if they exist
  const counters = document.querySelectorAll('.stat-card__number');
  if (counters.length) {
    counters.forEach(function (el) {
      const target = parseInt(el.getAttribute('data-count'), 10);
      if (isNaN(target)) return;
      const duration = 1200;
      const start = performance.now();

      function updateCounter(now) {
        const elapsed = now - start;
        const progress = Math.min(elapsed / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        el.textContent = Math.floor(eased * target);
        if (progress < 1) requestAnimationFrame(updateCounter);
        else el.textContent = target;
      }
      requestAnimationFrame(updateCounter);
    });
  }

  // Mobile filter/sidebar-related toggles if needed
});