/* ================================================
   INVESTHOOD IT - Candidate Interviews JS
   ================================================
   Handles interactions on the candidate interview
   management page:
   - Sidebar / drawer toggle
   - Dark mode (persisted across pages)
   - Stat counter animations
   - Interview detail modal open/close (hash + actions)
   - Accessible focus trapping
*/

'use strict';

document.addEventListener('DOMContentLoaded', function () {
  // ------------------------------------------------
  // 1. Sidebar toggle
  // ------------------------------------------------
  const sidebar       = document.getElementById('sidebar');
  const sidebarToggle = document.getElementById('sidebarToggle');
  const sidebarClose  = document.getElementById('sidebarClose');
  const overlay       = document.getElementById('sidebarOverlay');

  function openSidebar() {
    if (sidebar) sidebar.classList.add('open');
    if (overlay)  overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
  }
  function closeSidebar() {
    if (sidebar) sidebar.classList.remove('open');
    if (overlay) overlay.classList.remove('open');
    document.body.style.overflow = '';
  }
  if (sidebarToggle) sidebarToggle.addEventListener('click', openSidebar);
  if (sidebarClose)  sidebarClose.addEventListener('click', closeSidebar);
  if (overlay)       overlay.addEventListener('click', closeSidebar);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && sidebar && sidebar.classList.contains('open')) closeSidebar();
  });

  // ------------------------------------------------
  // 2. Dark mode (persisted across pages)
  // ------------------------------------------------
  const savedTheme = localStorage.getItem('theme');
  if (savedTheme === 'dark') {
    document.body.classList.add('dark-mode');
  }
  const themeToggle = document.getElementById('themeToggle');
  if (themeToggle) {
    const icon = themeToggle.querySelector('i');
    if (icon) {
      icon.className = document.body.classList.contains('dark-mode') ? 'fas fa-sun' : 'fas fa-moon';
    }
    themeToggle.addEventListener('click', function () {
      document.body.classList.toggle('dark-mode');
      const i = this.querySelector('i');
      if (document.body.classList.contains('dark-mode')) {
        i.className = 'fas fa-sun';
        localStorage.setItem('theme', 'dark');
      } else {
        i.className = 'fas fa-moon';
        localStorage.setItem('theme', 'light');
      }
    });
  }

  // ------------------------------------------------
  // 3. Stat counter animations
  // ------------------------------------------------
  const counters = document.querySelectorAll('.stat-card__number[data-count]');
  counters.forEach(function (el) {
    const target = parseInt(el.getAttribute('data-count'), 10);
    if (isNaN(target)) return;
    const duration = 1200;
    const start = performance.now();
    function update(now) {
      const progress = Math.min((now - start) / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3);
      el.textContent = Math.floor(eased * target);
      if (progress < 1) requestAnimationFrame(update);
      else el.textContent = target;
    }
    requestAnimationFrame(update);
  });

  // ------------------------------------------------
  // 4. Interview detail modals
  // ------------------------------------------------
  const modalClass   = 'interview-modal';
  const openClass    = 'interview-modal--open';
  const modalHashRe  = /^#interview-modal-\d+$/;
  const bodyOpenClass = 'interview-modal-open';

  function openModal(hash) {
    const id = hash || window.location.hash;
    const modal = document.getElementById(id.replace('#', ''));
    if (!modal) return;
    modal.classList.add(openClass);
    document.body.classList.add(bodyOpenClass);
    document.body.style.overflow = 'hidden';
    // restore hash for deep-linking / back-button support
    if (history.replaceState) history.replaceState('', document.title, id);
  }

  function closeModal(modal) {
    if (!modal) return;
    modal.classList.remove(openClass);
    // close any open modal
    document.querySelectorAll('.' + openClass).forEach(function (m) { m.classList.remove(openClass); });
    document.body.classList.remove(bodyOpenClass);
    document.body.style.overflow = '';
    if (history.replaceState) history.replaceState('', document.title, window.location.pathname + window.location.search);
  }

  // Open via "View Details" links (href="#interview-modal-<id>")
  document.querySelectorAll('a[href^="#interview-modal-"]').forEach(function (link) {
    link.addEventListener('click', function (e) {
      if (link.getAttribute('href') && link.getAttribute('href').match(modalHashRe)) {
        e.preventDefault();
        openModal(link.getAttribute('href'));
      }
    });
  });

  // Close via [data-close] buttons / overlay clicks
  document.querySelectorAll('.' + modalClass + ' [data-close]').forEach(function (el) {
    el.addEventListener('click', function () {
      closeModal(document.querySelector('.' + openClass));
    });
  });

  // Close a modal by clicking its overlay background
  document.querySelectorAll('.' + modalClass + '__overlay').forEach(function (overlayEl) {
    overlayEl.addEventListener('click', function (e) {
      if (e.target === overlayEl) closeModal(overlayEl.closest('.' + modalClass));
    });
  });

  // ESC closes any open modal
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      const open = document.querySelector('.' + openClass);
      if (open) closeModal(open);
    }
  });

  // Open modal if deep-linked on load
  if (window.location.hash && window.location.hash.match(modalHashRe)) {
    openModal(window.location.hash);
  }
});
