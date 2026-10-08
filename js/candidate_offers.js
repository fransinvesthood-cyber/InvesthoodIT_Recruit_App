/* ================================================
   INVESTHOOD IT - Candidate Offers JS
   - Confirm before Accept / Decline (prevent accidents)
   - Optional decline reason via prompt()
   - Reuses dashboard sidebar + dark-mode patterns
   ================================================ */
'use strict';
document.addEventListener('DOMContentLoaded', function () {
  var sidebar = document.getElementById('sidebar');
  var toggle = document.getElementById('sidebarToggle');
  var closeBtn = document.getElementById('sidebarClose');
  var overlay = document.getElementById('sidebarOverlay');
  function openSidebar() {
    if (sidebar) sidebar.classList.add('open');
    if (overlay) overlay.classList.add('open');
  }
  function closeSidebar() {
    if (sidebar) sidebar.classList.remove('open');
    if (overlay) overlay.classList.remove('open');
  }
  if (toggle) toggle.addEventListener('click', openSidebar);
  if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
  if (overlay) overlay.addEventListener('click', closeSidebar);

  var savedTheme = null;
  try { savedTheme = localStorage.getItem('theme'); } catch (e) {}
  if (savedTheme === 'dark') document.body.classList.add('dark-mode');
  var themeToggle = document.getElementById('themeToggle');
  if (themeToggle) {
    themeToggle.addEventListener('click', function () {
      document.body.classList.toggle('dark-mode');
      try { localStorage.setItem('theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light'); } catch (e) {}
    });
  }

  document.querySelectorAll('.stat-card__number[data-count]').forEach(function (el) {
    var target = parseInt(el.getAttribute('data-count'), 10);
    if (isNaN(target)) return;
    var start = performance.now();
    var duration = 900;
    function update(now) {
      var p = Math.min((now - start) / duration, 1);
      var eased = 1 - Math.pow(1 - p, 3);
      el.textContent = Math.floor(eased * target);
      if (p < 1) requestAnimationFrame(update);
      else el.textContent = target;
    }
    requestAnimationFrame(update);
  });

  document.querySelectorAll('form[data-offer-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      var kind = form.getAttribute('data-offer-confirm');
      var message = kind === 'accept'
        ? 'Accept this offer? Your response will be recorded and shared with the programme team.'
        : 'Decline this offer? Your response will be recorded and shared with the programme team.';
      if (!window.confirm(message)) {
        e.preventDefault();
        return;
      }
      if (kind === 'decline') {
        var reasonInput = form.querySelector('input[name="decline_reason"]');
        if (reasonInput && reasonInput.value === '') {
          var reason = window.prompt('Optional: tell the programme team why you are declining (max 500 characters).', '');
          if (reason === null) {
            e.preventDefault();
            return;
          }
          reasonInput.value = String(reason).slice(0, 500);
        }
      }
      var btn = form.querySelector('button[type="submit"]');
      if (btn) {
        btn.disabled = true;
        btn.setAttribute('aria-disabled', 'true');
      }
    });
  });

  document.querySelectorAll('a[href^="#offer-modal-"]').forEach(function (link) {
    link.addEventListener('click', function (e) {
      var modal = document.getElementById(link.getAttribute('href').replace('#', ''));
      if (!modal) return;
      e.preventDefault();
      modal.classList.add('interview-modal--open');
      document.body.style.overflow = 'hidden';
    });
  });
  document.querySelectorAll('.interview-modal [data-close]').forEach(function (el) {
    el.addEventListener('click', function () {
      document.querySelectorAll('.interview-modal--open').forEach(function (m) {
        m.classList.remove('interview-modal--open');
      });
      document.body.style.overflow = '';
    });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      document.querySelectorAll('.interview-modal--open').forEach(function (m) {
        m.classList.remove('interview-modal--open');
      });
      document.body.style.overflow = '';
    }
  });
});
