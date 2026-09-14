/* ================================================
   INVESTHOOD IT - Admin Applications JavaScript (Stage 9)
   ================================================ */

'use strict';

document.addEventListener('DOMContentLoaded', function () {

  // ============================================
  // 1. FILTER HANDLING
  // ============================================
  var searchInput = document.getElementById('appSearchInput');
  var filterStatus = document.getElementById('appFilterStatus');
  var filterProgramme = document.getElementById('appFilterProgramme');
  var filterCohort = document.getElementById('appFilterCohort');
  var filterOpportunity = document.getElementById('appFilterOpportunity');
  var filterDateFrom = document.getElementById('appFilterDateFrom');
  var filterDateTo = document.getElementById('appFilterDateTo');
  var clearFiltersBtn = document.getElementById('appClearFilters');

  function debounce(func, wait) {
    var timeout;
    return function () {
      var context = this;
      var args = arguments;
      clearTimeout(timeout);
      timeout = setTimeout(function () {
        func.apply(context, args);
      }, wait);
    };
  }

  function applyFilters() {
    var params = new URLSearchParams();
    var searchVal = searchInput ? searchInput.value.trim() : '';
    var statusVal = filterStatus ? filterStatus.value : '';
    var programmeVal = filterProgramme ? filterProgramme.value : '';
    var cohortVal = filterCohort ? filterCohort.value : '';
    var opportunityVal = filterOpportunity ? filterOpportunity.value : '';
    var dateFromVal = filterDateFrom ? filterDateFrom.value : '';
    var dateToVal = filterDateTo ? filterDateTo.value : '';

    if (searchVal) params.set('q', searchVal);
    if (statusVal) params.set('status', statusVal);
    if (programmeVal) params.set('programme', programmeVal);
    if (cohortVal) params.set('cohort', cohortVal);
    if (opportunityVal) params.set('opportunity', opportunityVal);
    if (dateFromVal) params.set('date_from', dateFromVal);
    if (dateToVal) params.set('date_to', dateToVal);

    var queryString = params.toString();
    var baseUrl = window.APP_URL + '/admin/applications.php';
    window.location.href = baseUrl + (queryString ? '?' + queryString : '');
  }

  if (searchInput) {
    searchInput.addEventListener('input', debounce(function () { applyFilters(); }, 500));
  }
  if (filterStatus) filterStatus.addEventListener('change', applyFilters);
  if (filterProgramme) filterProgramme.addEventListener('change', applyFilters);
  if (filterCohort) filterCohort.addEventListener('change', applyFilters);
  if (filterOpportunity) filterOpportunity.addEventListener('change', applyFilters);
  if (filterDateFrom) filterDateFrom.addEventListener('change', applyFilters);
  if (filterDateTo) filterDateTo.addEventListener('change', applyFilters);

  if (clearFiltersBtn) {
    clearFiltersBtn.addEventListener('click', function () {
      window.location.href = window.APP_URL + '/admin/applications.php';
    });
  }

  // ============================================
  // 2. STATUS UPDATE HANDLING (Detail Page)
  // ============================================
  var statusForm = document.getElementById('statusUpdateForm');
  var statusSubmitBtn = document.getElementById('statusSubmitBtn');
  var statusMessage = document.getElementById('statusMessage');

  if (statusForm) {
    statusForm.addEventListener('submit', function (e) {
      e.preventDefault();

      var applicationId = this.dataset.applicationId;
      var newStatus = document.getElementById('newStatusSelect').value;
      var reason = document.getElementById('statusReason') ? document.getElementById('statusReason').value : '';
      var csrfToken = document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').content : '';

      if (!newStatus) {
        showStatusMessage('Please select a status.', 'error');
        return;
      }

      if (statusSubmitBtn) {
        statusSubmitBtn.disabled = true;
        statusSubmitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
      }

      var formData = new FormData();
      formData.append('application_id', applicationId);
      formData.append('new_status', newStatus);
      formData.append('reason', reason);
      formData.append('csrf_token', csrfToken);

      fetch(window.APP_URL + '/admin/application_status.php', {
        method: 'POST',
        body: formData,
        headers: { 'X-CSRF-Token': csrfToken }
      })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        if (data.success) {
          showStatusMessage(data.message || 'Status updated successfully.', 'success');
          setTimeout(function () { window.location.reload(); }, 1500);
        } else {
          showStatusMessage(data.message || 'Failed to update status.', 'error');
        }
      })
      .catch(function (error) {
        showStatusMessage('An error occurred. Please try again.', 'error');
        console.error('Status update error:', error);
      })
      .finally(function () {
        if (statusSubmitBtn) {
          statusSubmitBtn.disabled = false;
          statusSubmitBtn.innerHTML = '<i class="fas fa-check"></i> Update Status';
        }
      });
    });
  }

  function showStatusMessage(message, type) {
    if (!statusMessage) return;
    statusMessage.textContent = message;
    statusMessage.className = 'alert alert--' + (type === 'success' ? 'success' : 'error');
    statusMessage.style.display = 'block';
  }

});