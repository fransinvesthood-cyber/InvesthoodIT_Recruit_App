/* ============================================================
   INVESTHOOD IT - Admin Selection & Offers JavaScript (Stage 11)
   ============================================================
   1. Server-side filter forms auto-apply on change (search is
      debounced), matching the Applications module behaviour.
   2. Confirmation modal for important actions (selection
      decisions, issuing/withdrawing offers) — the request is
      only submitted after the administrator confirms.
   ============================================================ */

'use strict';

document.addEventListener('DOMContentLoaded', function () {

  // ============================================
  // 1. FILTER HANDLING (server-side GET filters)
  // ============================================
  var filterForms = document.querySelectorAll('form[data-auto-filters]');

  filterForms.forEach(function (form) {
    var baseUrl = form.getAttribute('data-base') || window.location.pathname;
    var searchInput = form.querySelector('input[type="search"][data-filter], input[type="text"][data-filter]');
    var clearButton = form.querySelector('[data-clear-filters]');
    var debounceTimeout;

    function applyFilters() {
      var params = new URLSearchParams();
      var fields = form.querySelectorAll('[data-filter]');

      fields.forEach(function (field) {
        var name = field.getAttribute('data-filter');
        var value = (field.value || '').trim();
        if (name && value !== '') {
          params.set(name, value);
        }
      });

      var queryString = params.toString();
      window.location.href = baseUrl + (queryString ? '?' + queryString : '');
    }

    if (searchInput) {
      searchInput.addEventListener('input', function () {
        clearTimeout(debounceTimeout);
        debounceTimeout = setTimeout(applyFilters, 500);
      });
      // Pressing Enter applies immediately instead of submitting.
      searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          clearTimeout(debounceTimeout);
          applyFilters();
        }
      });
    }

    form.querySelectorAll('select[data-filter]').forEach(function (select) {
      select.addEventListener('change', applyFilters);
    });
    form.querySelectorAll('input[type="date"][data-filter]').forEach(function (input) {
      input.addEventListener('change', applyFilters);
    });

    if (clearButton) {
      clearButton.addEventListener('click', function () {
        window.location.href = baseUrl;
      });
    }

    // Never submit the filter form traditionally; auto-apply keeps
    // the UX consistent with the Applications module.
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      clearTimeout(debounceTimeout);
      applyFilters();
    });
  });

  // ============================================
  // 2. CONFIRMATION MODAL
  // ============================================
  var modal = document.createElement('div');
  modal.className = 'sel-modal';
  modal.setAttribute('aria-hidden', 'true');
  modal.innerHTML =
    '<div class="sel-modal__overlay" data-modal-close></div>' +
    '<div class="sel-modal__panel" role="dialog" aria-modal="true">' +
    '  <div class="sel-modal__icon" id="selConfirmIcon"><i class="fas fa-question-circle"></i></div>' +
    '  <h3 class="sel-modal__title" id="selConfirmTitle">Confirm action</h3>' +
    '  <p class="sel-modal__message" id="selConfirmMessage"></p>' +
    '  <div class="sel-modal__actions">' +
    '    <button type="button" class="btn btn--ghost btn--sm" data-modal-close><i class="fas fa-times"></i> Cancel</button>' +
    '    <button type="button" class="btn btn--primary btn--sm" id="selConfirmAccept"><i class="fas fa-check"></i> Confirm</button>' +
    '  </div>' +
    '</div>';
  document.body.appendChild(modal);

  var modalTitle = modal.querySelector('#selConfirmTitle');
  var modalMessage = modal.querySelector('#selConfirmMessage');
  var modalIcon = modal.querySelector('#selConfirmIcon');
  var modalAccept = modal.querySelector('#selConfirmAccept');

  var pendingForm = null;
  var pendingHref = null;

  function openModal(form, title, message, danger, confirmLabel, href) {
    pendingForm = form || null;
    pendingHref = href || null;
    modalTitle.textContent = title || 'Confirm action';
    modalMessage.textContent = message || '';
    modalIcon.className = 'sel-modal__icon' + (danger ? ' sel-modal__icon--danger' : '');
    modalIcon.innerHTML = danger
      ? '<i class="fas fa-exclamation-triangle"></i>'
      : '<i class="fas fa-question-circle"></i>';
    modalAccept.innerHTML = '<i class="fas fa-check"></i> ' + (confirmLabel || 'Confirm');
    modal.classList.add('active');
    modal.setAttribute('aria-hidden', 'false');
  }

  function closeModal() {
    modal.classList.remove('active');
    modal.setAttribute('aria-hidden', 'true');
    pendingForm = null;
    pendingHref = null;
  }

  modal.querySelectorAll('[data-modal-close]').forEach(function (el) {
    el.addEventListener('click', closeModal);
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal.classList.contains('active')) {
      closeModal();
    }
  });

  modalAccept.addEventListener('click', function () {
    if (pendingForm) {
      pendingForm.removeEventListener('submit', guardSubmit);
      pendingForm.submit();
    } else if (pendingHref) {
      window.location.href = pendingHref;
    }
    closeModal();
  });

  function guardSubmit(e) {
    e.preventDefault();
    e.stopImmediatePropagation();
    var form = e.target;
    openModal(
      form,
      form.getAttribute('data-confirm-title') || 'Confirm action',
      form.getAttribute('data-confirm-message') || 'Are you sure you want to continue?',
      form.hasAttribute('data-confirm-danger'),
      form.getAttribute('data-confirm-label')
    );
  }

  document.querySelectorAll('form[data-confirm-title]').forEach(function (form) {
    form.addEventListener('submit', guardSubmit);
  });

  // Standalone confirmation links (e.g. [Issue Offer] on the offer
  // detail page when configured as a GET link) navigate after confirm.
  document.querySelectorAll('[data-confirm-href]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      e.preventDefault();
      openModal(
        null,
        el.getAttribute('data-confirm-title') || 'Confirm action',
        el.getAttribute('data-confirm-message') || 'Are you sure you want to continue?',
        el.hasAttribute('data-confirm-danger'),
        el.getAttribute('data-confirm-label'),
        el.getAttribute('data-confirm-href')
      );
    });
  });

  // ============================================
  // 3. DECISION OPTION HIGHLIGHTING
  // ============================================
  function syncDecisionOptions() {
    document.querySelectorAll('.decision-option').forEach(function (option) {
      option.classList.toggle('is-checked', option.querySelector('input[type="radio"]:checked') !== null);
    });
  }

  document.querySelectorAll('.decision-options input[type="radio"]').forEach(function (radio) {
    radio.addEventListener('change', syncDecisionOptions);
  });
  syncDecisionOptions();

});