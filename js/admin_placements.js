/* ============================================================
   INVESTHOOD IT - Admin Placements JavaScript (Stage 12)
   ============================================================ */

'use strict';

document.addEventListener('DOMContentLoaded', function () {

  // Dark mode toggle
  var themeToggle = document.getElementById('themeToggle');
  if (themeToggle) {
    var icon = themeToggle.querySelector('i');
    if (icon) icon.className = document.body.classList.contains('dark-mode') ? 'fas fa-sun' : 'fas fa-moon';
    themeToggle.addEventListener('click', function () {
      document.body.classList.toggle('dark-mode');
      var ic = this.querySelector('i');
      if (document.body.classList.contains('dark-mode')) {
        ic.className = 'fas fa-sun';
        localStorage.setItem('theme', 'dark');
      } else {
        ic.className = 'fas fa-moon';
        localStorage.setItem('theme', 'light');
      }
    });
  }

  // Sidebar toggle
  var sidebarToggle = document.getElementById('sidebarToggle');
  var sidebarClose = document.getElementById('sidebarClose');
  var sidebarOverlay = document.getElementById('sidebarOverlay');
  var sidebar = document.getElementById('adminSidebar');

  function openSidebar() {
    if (sidebar) sidebar.classList.add('open');
    if (sidebarOverlay) sidebarOverlay.classList.add('open');
    document.body.style.overflow = 'hidden';
  }
  function closeSidebar() {
    if (sidebar) sidebar.classList.remove('open');
    if (sidebarOverlay) sidebarOverlay.classList.remove('open');
    document.body.style.overflow = '';
  }
  if (sidebarToggle) sidebarToggle.addEventListener('click', openSidebar);
  if (sidebarClose) sidebarClose.addEventListener('click', closeSidebar);
  if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && sidebar && sidebar.classList.contains('open')) closeSidebar();
  });

  // Search & Filter functionality
  var searchInput = document.getElementById('placementSearch');
  var filterSelects = document.querySelectorAll('.pl-filters__select');
  var resetBtn = document.getElementById('placementFilterReset');

  function debounce(func, wait) {
    var timeout;
    return function () {
      var context = this, args = arguments;
      clearTimeout(timeout);
      timeout = setTimeout(function () { func.apply(context, args); }, wait);
    };
  }

  function applyFilters() {
    var searchValue = searchInput ? searchInput.value.toLowerCase().trim() : '';
    var filterData = {};
    filterSelects.forEach(function (select) {
      if (select && select.value) {
        var key = select.name || select.id;
        filterData[key] = select.value;
      }
    });
    var params = new URLSearchParams(filterData);
    if (searchValue) params.set('search', searchValue);
    var currentParams = new URLSearchParams(window.location.search);
    var newParams = new URLSearchParams();
    for (var [key, value] of currentParams.entries()) {
      if (key !== 'search' && !filterData.hasOwnProperty(key)) newParams.set(key, value);
    }
    for (var [key, value] of params.entries()) newParams.set(key, value);
    var newUrl = window.location.pathname + '?' + newParams.toString();
    if (newParams.toString()) window.location.href = newUrl;
  }

  if (searchInput) searchInput.addEventListener('input', debounce(applyFilters, 300));
  filterSelects.forEach(function (select) { if (select) select.addEventListener('change', applyFilters); });

  if (resetBtn) {
    resetBtn.addEventListener('click', function () {
      if (searchInput) searchInput.value = '';
      filterSelects.forEach(function (select) { if (select) select.value = ''; });
      var url = window.location.pathname;
      var params = new URLSearchParams(window.location.search);
      params.delete('search');
      filterSelects.forEach(function (select) { if (select && select.name) params.delete(select.name); });
      var newUrl = url + '?' + params.toString();
      window.location.href = params.toString() ? newUrl : url;
    });
  }

  // Confirmation modal
  var confirmModal = document.getElementById('placementConfirmModal');
  var confirmBtn = document.querySelector('[data-confirm-action]');
  var cancelBtn = document.querySelector('[data-confirm-cancel]');
  var modalTitle = document.getElementById('confirmModalTitle');
  var modalMessage = document.getElementById('confirmModalMessage');

  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      e.preventDefault();
      var action = this.getAttribute('data-confirm-action') || '';
      var title = this.getAttribute('data-confirm-title') || 'Are you sure?';
      var message = this.getAttribute('data-confirm-message') || 'This action cannot be undone.';
      if (confirmBtn) confirmBtn.setAttribute('data-confirm-action', action);
      if (modalTitle) modalTitle.textContent = title;
      if (modalMessage) modalMessage.textContent = message;
      if (confirmModal) confirmModal.classList.add('show');
    });
  });

  if (confirmBtn) {
    confirmBtn.addEventListener('click', function () {
      var action = this.getAttribute('data-confirm-action');
      if (action && action.startsWith('http')) {
        window.location.href = action;
      } else if (action) {
        var form = document.getElementById(action);
        if (form) form.submit();
        else {
          var event = new CustomEvent('placementConfirm', { detail: { action: action } });
          document.dispatchEvent(event);
        }
      }
      if (confirmModal) confirmModal.classList.remove('show');
    });
  }
  if (cancelBtn) cancelBtn.addEventListener('click', function () {
    if (confirmModal) confirmModal.classList.remove('show');
  });
  if (confirmModal) {
    confirmModal.addEventListener('click', function (e) {
      if (e.target === this) this.classList.remove('show');
    });
  }

  // Date validation
  function validateDateRange(startField, endField, errorField) {
    var start = document.getElementById(startField);
    var end = document.getElementById(endField);
    var error = document.getElementById(errorField);
    if (!start || !end) return true;
    if (end.value && start.value && end.value < start.value) {
      if (error) { error.textContent = 'End date cannot be before start date.'; error.style.display = 'block'; }
      end.setCustomValidity('End date cannot be before start date.');
      return false;
    }
    if (error) { error.textContent = ''; error.style.display = 'none'; }
    end.setCustomValidity('');
    return true;
  }

  var startDateField = document.getElementById('start_date');
  var endDateField = document.getElementById('end_date');
  if (startDateField && endDateField) {
    startDateField.addEventListener('change', function () { validateDateRange('start_date', 'end_date', 'dateRangeError'); });
    endDateField.addEventListener('change', function () { validateDateRange('start_date', 'end_date', 'dateRangeError'); });
  }

  // Status change warning
  var statusSelect = document.getElementById('status');
  var statusChangeWarning = document.getElementById('statusChangeWarning');
  if (statusSelect && statusChangeWarning) {
    statusSelect.addEventListener('change', function () {
      if (this.value) statusChangeWarning.style.display = 'block';
    });
  }

  // Load supervisors dynamically
  var supervisorSelect = document.getElementById('supervisor_id');
  if (supervisorSelect && supervisorSelect.dataset.loadUrl) {
    fetch(supervisorSelect.dataset.loadUrl)
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data && data.supervisors) {
          supervisorSelect.innerHTML = '<option value="">Select Supervisor</option>';
          data.supervisors.forEach(function (s) {
            var opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = s.full_name + ' - ' + (s.email || '');
            supervisorSelect.appendChild(opt);
          });
        }
      }).catch(function () {});
  }

  // Expose utilities
  window.PlacementFilters = {
    applyFilters: applyFilters,
    resetFilters: function () { if (resetBtn) resetBtn.click(); }
  };

});
