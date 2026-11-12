(function () {
  'use strict';
  var STORAGE_KEY = 'investhood_candidate_theme';
  var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
  function readPreference() {
    try { return localStorage.getItem(STORAGE_KEY) || 'system'; } catch (e) { return 'system'; }
  }
  function valid(value) { return value === 'dark' || value === 'light' || value === 'system' ? value : 'system'; }
  function applyTheme(preference) {
    preference = valid(preference);
    var dark = preference === 'dark' || (preference === 'system' && media && media.matches);
    var root = document.documentElement;
    root.setAttribute('data-theme', dark ? 'dark' : 'light');
    root.style.colorScheme = dark ? 'dark' : 'light';
    function applyBody() {
      if (!document.body) return;
      document.body.classList.toggle('dark-mode', !!dark);
      document.body.classList.toggle('light-mode', !dark);
    }
    applyBody();
    document.querySelectorAll('#themeToggle').forEach(function (button) {
      var icon = button.querySelector('i');
      if (icon) {
        icon.classList.toggle('fa-moon', !dark);
        icon.classList.toggle('fa-sun', !!dark);
      }
      button.setAttribute('aria-pressed', String(!!dark));
      button.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
      button.setAttribute('title', dark ? 'Switch to light mode' : 'Switch to dark mode');
    });
    document.querySelectorAll('input[name="theme"]').forEach(function (input) {
      input.checked = input.value === preference;
      input.disabled = false;
    });
  }
  function saveAndApply(value) {
    value = valid(value);
    try { localStorage.setItem(STORAGE_KEY, value); } catch (e) {}
    applyTheme(value);
  }
  document.addEventListener('DOMContentLoaded', function () { applyTheme(readPreference()); });
  applyTheme(readPreference());
  document.addEventListener('click', function (event) {
    var button = event.target && event.target.closest ? event.target.closest('#themeToggle') : null;
    if (!button) return;
    event.preventDefault();
    saveAndApply(document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
  });
  document.addEventListener('change', function (event) {
    if (event.target && event.target.matches && event.target.matches('input[name="theme"]')) {
      saveAndApply(event.target.value);
    }
  });
  if (media) {
    var onSystemChange = function () { if (readPreference() === 'system') applyTheme('system'); };
    if (media.addEventListener) media.addEventListener('change', onSystemChange);
    else if (media.addListener) media.addListener(onSystemChange);
  }

  // Candidate sidebar: desktop collapse + mobile slide-in drawer.
  function setSidebar(open) {
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    var layout = document.querySelector('.dashboard');
    var mobile = window.matchMedia && window.matchMedia('(max-width: 900px)').matches;
    if (!sidebar) return;
    if (mobile) {
      sidebar.classList.toggle('is-open', !!open);
      if (overlay) overlay.classList.toggle('is-visible', !!open);
      document.body.classList.toggle('sidebar-drawer-open', !!open);
    } else {
      if (layout) layout.classList.toggle('sidebar-collapsed', !open);
      sidebar.classList.toggle('is-collapsed', !open);
      if (overlay) overlay.classList.remove('is-visible');
      document.body.classList.remove('sidebar-drawer-open');
    }
    document.querySelectorAll('#sidebarToggle').forEach(function (button) {
      button.setAttribute('aria-expanded', String(!!open));
      button.setAttribute('aria-label', open ? 'Hide menu' : 'Show menu');
      var icon = button.querySelector('i');
      if (icon) {
        icon.classList.toggle('fa-bars', !open);
        icon.classList.toggle('fa-indent', !!open);
      }
    });
  }
  function closeSidebar() { setSidebar(false); }
  document.addEventListener('click', function (event) {
    var target = event.target;
    if (!target || !target.closest) return;
    var toggle = target.closest('#sidebarToggle');
    if (toggle) {
      event.preventDefault();
      var sidebar = document.getElementById('sidebar');
      var mobile = window.matchMedia && window.matchMedia('(max-width: 900px)').matches;
      var isOpen = mobile ? !!(sidebar && sidebar.classList.contains('is-open')) : !(document.querySelector('.dashboard') && document.querySelector('.dashboard').classList.contains('sidebar-collapsed'));
      setSidebar(!isOpen);
      return;
    }
    if (target.closest('#sidebarClose') || target.closest('#sidebarOverlay')) {
      event.preventDefault(); closeSidebar(); return;
    }
    if (target.closest('#sidebar .sidebar__link') && window.matchMedia && window.matchMedia('(max-width: 900px)').matches) {
      closeSidebar();
    }
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') closeSidebar();
  });
  window.addEventListener('resize', function () {
    var mobile = window.matchMedia && window.matchMedia('(max-width: 900px)').matches;
    if (!mobile) {
      var sidebar = document.getElementById('sidebar');
      if (sidebar) sidebar.classList.remove('is-open');
      var overlay = document.getElementById('sidebarOverlay');
      if (overlay) overlay.classList.remove('is-visible');
      document.body.classList.remove('sidebar-drawer-open');
    } else {
      var layout = document.querySelector('.dashboard');
      if (layout) layout.classList.remove('sidebar-collapsed');
      var side = document.getElementById('sidebar');
      if (side) side.classList.remove('is-collapsed');
    }
  });
})();
