/**
 * ================================================
 * INVESTHOOD IT - Admin Interview Management JS
 * ================================================
 * Client-side interactions for interview module.
 */
(function () {
  'use strict';

  // ============================================
  // CANCEL MODAL
  // ============================================
  window.showCancelModal = function (interviewId) {
    var modal = document.getElementById('cancelModal');
    var input = document.getElementById('cancelInterviewId');
    if (modal && input) {
      input.value = interviewId;
      modal.style.display = 'flex';
    }
  };

  // ============================================
  // UPDATE INTERVIEW STATUS (AJAX)
  // ============================================
  window.updateInterviewStatus = function (interviewId, newStatus) {
    if (!confirm('Are you sure you want to mark this interview as "' + newStatus.replace('_', ' ') + '"?')) {
      return;
    }

    var csrfToken = document.querySelector('meta[name="csrf-token"]');
    var token = csrfToken ? csrfToken.getAttribute('content') : '';

    var formData = new FormData();
    formData.append('interview_id', interviewId);
    formData.append('action', newStatus);
    formData.append('csrf_token', token);

    fetch(window.APP_URL + '/admin/interview_status.php', {
      method: 'POST',
      body: formData,
    })
      .then(function (res) { return res.text(); })
      .then(function () {
        window.location.reload();
      })
      .catch(function (err) {
        alert('An error occurred. Please try again.');
        console.error('[Interview Status]', err);
      });
  };

  // ============================================
  // INIT
  // ============================================
  document.addEventListener('DOMContentLoaded', function () {
    // Close cancel modal on overlay click
    var cancelModal = document.getElementById('cancelModal');
    if (cancelModal) {
      cancelModal.addEventListener('click', function (e) {
        if (e.target === cancelModal) {
          cancelModal.style.display = 'none';
        }
      });
    }

    // Close modal on Escape key
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && cancelModal && cancelModal.style.display === 'flex') {
        cancelModal.style.display = 'none';
      }
    });
  });
})();
