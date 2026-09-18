/**
 * ================================================
 * INVESTHOOD IT - Admin User Management JavaScript
 * ================================================
 * Handles user management page interactions.
 */

(function () {
  'use strict';

  // ============================================
  // SIDEBAR TOGGLE (reuse from admin_dashboard.js)
  // ============================================
  var sidebarToggle = document.getElementById('sidebarToggle');
  var sidebar = document.getElementById('adminSidebar');
  var sidebarOverlay = document.getElementById('sidebarOverlay');
  var sidebarClose = document.getElementById('sidebarClose');

  if (sidebarToggle && sidebar) {
    sidebarToggle.addEventListener('click', function () {
      sidebar.classList.toggle('active');
      if (sidebarOverlay) sidebarOverlay.classList.toggle('active');
    });
  }

  if (sidebarClose && sidebar) {
    sidebarClose.addEventListener('click', function () {
      sidebar.classList.remove('active');
      if (sidebarOverlay) sidebarOverlay.classList.remove('active');
    });
  }

  if (sidebarOverlay && sidebar) {
    sidebarOverlay.addEventListener('click', function () {
      sidebar.classList.remove('active');
      sidebarOverlay.classList.remove('active');
    });
  }

  // ============================================
  // THEME TOGGLE
  // ============================================
  var themeToggle = document.getElementById('themeToggle');
  if (themeToggle) {
    themeToggle.addEventListener('click', function () {
      document.body.classList.toggle('dark-mode');
      var icon = this.querySelector('i');
      if (icon) {
        icon.classList.toggle('fa-moon');
        icon.classList.toggle('fa-sun');
      }
    });
  }

  // ============================================
  // CONFIRMATION DIALOGS
  // ============================================
  var confirmForms = document.querySelectorAll('form[data-confirm]');
  confirmForms.forEach(function (form) {
    form.addEventListener('submit', function (e) {
      var message = form.getAttribute('data-confirm');
      if (!confirm(message)) {
        e.preventDefault();
      }
    });
  });

  // ============================================
  // PASSWORD VISIBILITY TOGGLE
  // ============================================
  var passwordToggles = document.querySelectorAll('.password-toggle');
  passwordToggles.forEach(function (toggle) {
    toggle.addEventListener('click', function () {
      var input = this.previousElementSibling;
      var icon = this.querySelector('i');
      if (input && input.type === 'password') {
        input.type = 'text';
        if (icon) {
          icon.classList.toggle('fa-eye');
          icon.classList.toggle('fa-eye-slash');
        }
      } else if (input) {
        input.type = 'password';
        if (icon) {
          icon.classList.toggle('fa-eye');
          icon.classList.toggle('fa-eye-slash');
        }
      }
    });
  });

  // ============================================
  // AUTO-HIDE FLASH NOTIFICATIONS
  // ============================================
  var notifications = document.querySelectorAll('.notification');
  notifications.forEach(function (notification) {
    setTimeout(function () {
      notification.classList.add('removing');
      setTimeout(function () {
        notification.remove();
      }, 300);
    }, 5000);
  });

  // ============================================
  // ENHANCED USER SEARCH
  // Debounced live search, clear button, spinner,
  // keyboard shortcuts (Ctrl+K / Cmd+K / "/")
  // ============================================
  var userSearch = document.getElementById('userSearch');
  var searchInput = document.getElementById('userSearchInput');
  var clearBtn = document.getElementById('userSearchClear');
  var kbdHint = document.getElementById('userSearchKbd');
  var spinner = document.getElementById('userSearchSpinner');

  if (userSearch && searchInput) {
    var searchForm = searchInput.closest('form');
    var searchTimer = null;
    var DEBOUNCE_MS = 500;
    // Value loaded from the server - avoids re-submitting on blur/clear when unchanged
    var initialQuery = searchInput.value;

    function syncSearchState() {
      var hasValue = searchInput.value.trim() !== '';
      var isFocused = document.activeElement === searchInput;
      userSearch.classList.toggle('is-focused', isFocused);
      if (clearBtn) clearBtn.hidden = !hasValue;
      if (kbdHint) kbdHint.hidden = hasValue || !isFocused;
    }

    function submitSearch() {
      if (!searchForm) return;
      if (searchInput.value === initialQuery) return; // nothing changed
      initialQuery = searchInput.value;
      searchForm.submit();
    }

    function queueSearch() {
      if (spinner) spinner.hidden = false;
      clearTimeout(searchTimer);
      searchTimer = setTimeout(function () {
        if (spinner) spinner.hidden = true;
        submitSearch();
      }, DEBOUNCE_MS);
    }

    searchInput.addEventListener('input', function () {
      syncSearchState();
      queueSearch();
    });

    searchInput.addEventListener('focus', syncSearchState);
    searchInput.addEventListener('blur', syncSearchState);

    searchInput.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        e.preventDefault();
        if (searchInput.value !== '') {
          searchInput.value = '';
          syncSearchState();
          initialQuery = '';
          if (searchForm) searchForm.submit();
        } else {
          searchInput.blur();
        }
      }
    });

    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        searchInput.value = '';
        syncSearchState();
        initialQuery = '';
        if (searchForm) searchForm.submit();
        searchInput.focus();
      });
    }

    // Keyboard shortcuts: Ctrl+K / Cmd+K focuses search from anywhere,
    // "/" focuses it when not typing in another field.
    document.addEventListener('keydown', function (e) {
      var isCtrlK = (e.ctrlKey || e.metaKey) && String(e.key).toLowerCase() === 'k';
      var active = document.activeElement;
      var typing = active && /^(INPUT|TEXTAREA|SELECT)$/.test(active.tagName);
      var isSlash = e.key === '/' && !typing;

      if (isCtrlK || isSlash) {
        e.preventDefault();
        searchInput.focus();
        searchInput.select();
        syncSearchState();
      }
    });

    // If the page loaded with an active search, pre-select it for quick edits
    if (searchInput.value !== '') {
      syncSearchState();
    }
  }

})();