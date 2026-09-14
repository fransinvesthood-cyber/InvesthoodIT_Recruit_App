/* ================================================
   INVESTHOOD IT - Application Review JavaScript
   Stage 5: Review & Declaration
   ================================================ */
'use strict';

(function () {
  document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('reviewForm');
    if (!form) return;

    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var CSRF = csrfMeta ? csrfMeta.getAttribute('content') : '';
    var ENDPOINT = (window.APP_URL || '') + '/candidate/application_actions.php';

    var declarationCheckbox = document.getElementById('declarationCheckbox');
    var continueBtn = document.getElementById('continueSubmissionBtn');
    var declarationError = document.getElementById('declarationError');
    var consentError = document.getElementById('consentError');
    var reviewStatus = document.getElementById('reviewStatus');
    var isSaving = false;

    var applicationIdInput = form.querySelector('[name="application_id"]');
    var applicationId = applicationIdInput ? applicationIdInput.value : '0';

    /* ---------------- Helpers ---------------- */
    function setStatus(type, message) {
      if (!reviewStatus) return;
      reviewStatus.textContent = message || '';
      reviewStatus.className = 'app-review__save-status';
      if (type) reviewStatus.classList.add('app-review__save-status--' + type);
    }

    function setDeclError(message) {
      if (declarationError) declarationError.textContent = message || '';
    }

    function setConsentError(message) {
      if (consentError) consentError.textContent = message || '';
    }

    function collectConsentPurposes() {
      var purposes = [];
      form.querySelectorAll('.consent-checkbox:checked').forEach(function (cb) {
        purposes.push(cb.value);
      });
      return purposes;
    }

    /* ---------------- Validation ---------------- */
    function validateDeclaration() {
      if (!declarationCheckbox || !declarationCheckbox.checked) {
        setDeclError('Please complete the required declaration before continuing.');
        return false;
      }
      setDeclError('');
      return true;
    }

    function validateConsent() {
      // The required programme_administration consent must be confirmed
      var requiredCb = form.querySelector('.consent-checkbox[required]');
      if (requiredCb && !requiredCb.checked) {
        setConsentError('Please confirm the required consent purpose before continuing.');
        return false;
      }
      setConsentError('');
      return true;
    }

    function validate() {
      var ok = true;
      if (!validateDeclaration()) ok = false;
      if (!validateConsent()) ok = false;
      return ok;
    }

    /* ---------------- Save Confirmation ---------------- */
    function saveConfirmation(callback) {
      if (isSaving) return;
      isSaving = true;

      setStatus('saving', 'Saving your declaration...');

      var data = {
        action: 'save_review_confirmation',
        application_id: applicationId,
        declaration: declarationCheckbox && declarationCheckbox.checked ? '1' : '0',
        consent_purposes: collectConsentPurposes()
      };

      var fd = new FormData();
      Object.keys(data).forEach(function (key) {
        if (Array.isArray(data[key])) {
          data[key].forEach(function (val) {
            fd.append(key + '[]', val);
          });
        } else {
          fd.append(key, data[key]);
        }
      });
      fd.append('csrf_token', CSRF);

      fetch(ENDPOINT, {
        method: 'POST',
        headers: { 'X-CSRF-Token': CSRF },
        body: fd
      })
        .then(function (res) {
          return res.json().catch(function () {
            return { success: false, message: 'Invalid server response.' };
          });
        })
        .then(function (json) {
          isSaving = false;
          if (json.success) {
            setStatus('saved', json.message || 'Your declaration has been saved.');
            if (callback) callback(true, json);
          } else {
            setStatus('error', json.message || 'We could not save your declaration. Please try again.');
            if (callback) callback(false, json);
          }
        })
        .catch(function () {
          isSaving = false;
          setStatus('error', 'We could not save your declaration. Please try again.');
          if (callback) callback(false, null);
        });
    }

    /* ---------------- Continue to Submission ---------------- */
    function validateSubmissionReadiness(callback) {
      var fd = new FormData();
      fd.append('action', 'validate_submission_readiness');
      fd.append('application_id', applicationId);
      fd.append('csrf_token', CSRF);

      fetch(ENDPOINT, {
        method: 'POST',
        headers: { 'X-CSRF-Token': CSRF },
        body: fd
      })
        .then(function (res) {
          return res.json().catch(function () {
            return { valid: false, errors: ['Invalid server response.'] };
          });
        })
        .then(function (json) {
          if (callback) callback(json);
        })
        .catch(function () {
          if (callback) callback({ valid: false, errors: ['Something went wrong. Please try again.'] });
        });
    }

    /* ---------------- Button Click ---------------- */
    if (continueBtn) {
      continueBtn.addEventListener('click', function () {
        // First, client-side validation
        if (!validate()) {
          setStatus('error', 'Please complete all required declarations before continuing.');
          if (continueBtn) continueBtn.disabled = false;
          return;
        }

        // Save the temporary confirmation
        saveConfirmation();

        // Then perform server-side readiness validation
        validateSubmissionReadiness(function (result) {
          if (result && result.valid) {
            // If ready, proceed to the Stage 6 placeholder
            setStatus('saved', 'Your application is ready. Preparing submission...');
            setTimeout(function () {
              window.location.href = (window.APP_URL || '') + '/candidate/application_submit.php?id=' + applicationId;
            }, 800);
          } else {
            // Show friendly missing information from the server
            var errors = (result && result.errors) || ['Your application is incomplete. Please complete the required information before continuing.'];
            setStatus('error', errors[0] || 'Your application is incomplete.');
          }
        });
      });
    }

    // Update readiness indicator when declaration changes
    if (declarationCheckbox) {
      declarationCheckbox.addEventListener('change', function () {
        var readinessItem = document.querySelector('.app-review__readiness-item:last-child');
        if (readinessItem) {
          if (declarationCheckbox.checked) {
            readinessItem.classList.remove('app-review__readiness-item--missing');
            var icon = readinessItem.querySelector('i');
            if (icon) {
              icon.className = 'fas fa-check-circle';
            }
          } else {
            readinessItem.classList.add('app-review__readiness-item--missing');
            var icon2 = readinessItem.querySelector('i');
            if (icon2) {
              icon2.className = 'fas fa-times-circle';
            }
          }
        }
        validateDeclaration();
      });
    }

    // ---------------- Initial Validation (do not pre-select) ----------------
    // The declaration checkbox is intentionally NEVER pre-selected.
    // Consent checkboxes are also not pre-selected on the first visit.
    // If a server-side confirmation exists, we restore it (marked in the
    // server-rendered HTML by the `checked` attribute).

    // Initialize error messages as empty
    setDeclError('');
    setConsentError('');
  });
})();