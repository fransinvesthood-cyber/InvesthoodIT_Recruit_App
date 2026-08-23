/* ================================================
   INVESTHOOD IT - Application Form JavaScript
   Multi-step form navigation, validation, saving,
   and document upload management (Step 4).
   ================================================ */
'use strict';

(function () {
  document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('applicationForm');
    if (!form) return;

    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var CSRF = csrfMeta ? csrfMeta.getAttribute('content') : '';
    var ENDPOINT = (window.APP_URL || '') + '/candidate/application_actions.php';

    var currentStep = 1;
    var totalSteps = 4;
    var isSaving = false;

    var stepNames = {
      1: 'Personal Information',
      2: 'Eligibility',
      3: 'Application Questions',
      4: 'Documents'
    };

    /* ---------------- Helpers ---------------- */
    function showStep(step) {
      currentStep = step;

      // Toggle panels
      document.querySelectorAll('[data-step-panel]').forEach(function (panel) {
        var panelStep = parseInt(panel.getAttribute('data-step-panel'), 10);
        panel.hidden = panelStep !== step;
      });

      // Update progress indicators
      document.querySelectorAll('[data-step-indicator]').forEach(function (ind) {
        var indStep = parseInt(ind.getAttribute('data-step-indicator'), 10);
        ind.classList.toggle('app-form__step--active', indStep === step);
        ind.classList.toggle('app-form__step--done', indStep < step);
      });

      // Update step text
      var stepCount = document.getElementById('stepCount');
      var stepName = document.getElementById('stepName');
      if (stepCount) stepCount.textContent = 'Step ' + step + ' of ' + totalSteps;
      if (stepName) stepName.textContent = stepNames[step] || '';

      // Update hidden current step
      var currentStepInput = document.getElementById('currentStep');
      if (currentStepInput) currentStepInput.value = step;

      // Scroll to top of form
      var formHeader = document.querySelector('.app-form__header');
      if (formHeader) formHeader.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function showError(fieldName, message) {
      var errEl = form.querySelector('[data-error-for="' + fieldName + '"]');
      var field = form.querySelector('[name="' + fieldName + '"]');
      if (errEl) errEl.textContent = message;
      if (field) field.classList.add('form-input--error');
    }

    function clearErrors() {
      form.querySelectorAll('.form-error').forEach(function (el) { el.textContent = ''; });
      form.querySelectorAll('.form-input--error').forEach(function (el) {
        el.classList.remove('form-input--error');
      });
    }

    function setSaveStatus(type, message) {
      var status = document.getElementById('saveStatus');
      if (!status) return;
      status.textContent = message;
      status.className = 'app-form__save-status';
      if (type) status.classList.add('app-form__save-status--' + type);
    }

    /* ---------------- Document Helpers ---------------- */
    function docItemByType(type) {
      return document.querySelector('.app-form__doc-item[data-doc-type="' + type + '"]');
    }

    function showDocMessage(type, message, isError) {
      var area = document.querySelector('[data-upload-area="' + type + '"]');
      if (!area) return;
      var msg = area.querySelector('[data-doc-message="' + type + '"]');
      if (!msg) return;
      msg.textContent = message || '';
      msg.className = 'app-form__doc-message ' + (isError ? 'app-form__doc-message--error' : 'app-form__doc-message--success');
    }

    function clearDocMessage(type) {
      var msg = document.querySelector('[data-doc-message="' + type + '"]');
      if (msg) {
        msg.textContent = '';
        msg.className = 'app-form__doc-message';
      }
    }

    /**
     * Upload (or replace) a document for a document type.
     *
     * @param {string} type      Canonical document type (e.g. CV)
     * @param {number} docId     Existing application_documents.id when replacing, 0 for new upload
     * @param {HTMLInputElement} input File input containing the selected file
     */
    function handleUpload(type, docId, input) {
      if (!input) return;

      var file = input.files && input.files[0];
      if (!file) {
        showDocMessage(type, 'Please select a file.', true);
        return;
      }

      // Client-side size guard (matches server MAX_DOCUMENT_SIZE = 10 MB)
      if (file.size > 10 * 1024 * 1024) {
        showDocMessage(type, 'The file is too large. Please upload a smaller file.', true);
        input.value = '';
        return;
      }

      // Client-side extension hint (server validates MIME too)
      var allowedExt = /\.(pdf|doc|docx)$/i;
      if (!allowedExt.test(file.name)) {
        showDocMessage(type, 'Please upload a PDF, DOC, or DOCX file.', true);
        input.value = '';
        return;
      }

      clearDocMessage(type);
      showDocMessage(type, 'Uploading...');

      var data = {
        action: docId ? 'document_replace' : 'document_upload',
        application_id: form.querySelector('[name="application_id"]').value,
        document_type: type
      };
      if (docId) data.document_id = docId;

      var fd = new FormData();
      Object.keys(data).forEach(function (key) {
        fd.append(key, data[key]);
      });
      fd.append('document', file);
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
          if (json.success) {
            showDocMessage(type, json.message || 'Your document was uploaded successfully.', false);
            // Reload page so server-rendered document state refreshes
            setTimeout(function () {
              window.location.reload();
            }, 800);
          } else {
            showDocMessage(type, json.message || 'We couldn\'t upload your document. Please try again.', true);
            input.value = '';
          }
        })
        .catch(function () {
          showDocMessage(type, 'We couldn\'t upload your document. Please try again.', true);
          input.value = '';
        });
    }

    /**
     * Reuse a candidate's profile document for this application.
     */
    function handleReuse(type, profileDocId) {
      if (!window.confirm('Use this document from your profile for this application?')) {
        return;
      }

      var data = {
        action: 'document_reuse',
        application_id: form.querySelector('[name="application_id"]').value,
        candidate_document_id: profileDocId
      };

      postJson(data, function (json, success) {
        if (success) {
          showDocMessage(type, json.message || 'Document attached successfully.', false);
          setTimeout(function () {
            window.location.reload();
          }, 600);
        } else {
          showDocMessage(type, json.message || 'Could not reuse the document.', true);
        }
      });
    }

    /**
     * Remove an application document after confirmation.
     */
    function handleRemove(type, appDocId) {
      if (!window.confirm('Are you sure you want to remove this document?')) {
        return;
      }

      var data = {
        action: 'document_remove',
        application_id: form.querySelector('[name="application_id"]').value,
        document_id: appDocId
      };

      postJson(data, function (json, success) {
        if (success) {
          showDocMessage(type, 'Document removed.', false);
          setTimeout(function () {
            window.location.reload();
          }, 600);
        } else {
          showDocMessage(type, json.message || 'Could not remove the document.', true);
        }
      });
    }

    /**
     * POST JSON-encoded-ish (FormData) to the endpoint and invoke the callback.
     */
    function postJson(data, callback) {
      var fd = new FormData();
      Object.keys(data).forEach(function (key) {
        fd.append(key, data[key]);
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
          if (callback) callback(json, !!json.success);
        })
        .catch(function () {
          if (callback) callback({ success: false, message: 'Something went wrong. Please try again.' }, false);
        });
    }

    /* ---------------- Document UI Wiring ---------------- */
    // Hide upload area (cancel)
    form.querySelectorAll('.doc-upload-cancel').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var type = btn.getAttribute('data-doc-type');
        var area = document.querySelector('[data-upload-area="' + type + '"]');
        var input = document.querySelector('[data-doc-input="' + type + '"]');
        if (area) area.hidden = true;
        if (input) input.value = '';
        clearDocMessage(type);
      });
    });

    // Set pending upload mode on the confirm button for a document type.
    // docId > 0 → replace mode; 0 → new upload mode.
    function setPendingUpload(type, docId) {
      var confirmBtn = document.querySelector('.doc-upload-confirm[data-doc-type="' + type + '"]');
      if (confirmBtn) {
        confirmBtn.setAttribute('data-pending-doc-id', String(docId || 0));
      }
    }

    // Show upload area (new upload)
    form.querySelectorAll('.doc-upload').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var type = btn.getAttribute('data-doc-type');
        var area = document.querySelector('[data-upload-area="' + type + '"]');
        var input = document.querySelector('[data-doc-input="' + type + '"]');
        if (area) area.hidden = false;
        if (input) input.value = '';
        clearDocMessage(type);
        setPendingUpload(type, 0);
      });
    });

    // Replace button: show upload area and set pending replace mode
    form.querySelectorAll('.doc-replace').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var type = btn.getAttribute('data-doc-type');
        var appDocId = parseInt(btn.getAttribute('data-doc-id') || '0', 10);
        var area = document.querySelector('[data-upload-area="' + type + '"]');
        var input = document.querySelector('[data-doc-input="' + type + '"]');
        if (area) area.hidden = false;
        if (input) input.value = '';
        clearDocMessage(type);
        setPendingUpload(type, appDocId);
      });
    });

    // Confirm upload: single handler that respects the pending mode
    form.querySelectorAll('.doc-upload-confirm').forEach(function (btn) {
      // Mark as a fresh (non-replace) upload by default
      btn.setAttribute('data-pending-doc-id', '0');

      btn.addEventListener('click', function () {
        var type = btn.getAttribute('data-doc-type');
        var input = document.querySelector('[data-doc-input="' + type + '"]');
        var pendingDocId = parseInt(btn.getAttribute('data-pending-doc-id') || '0', 10);
        if (input) handleUpload(type, pendingDocId, input);
      });
    });

    // Reuse button
    form.querySelectorAll('.doc-reuse').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var type = btn.getAttribute('data-doc-type');
        var profileDocId = parseInt(btn.getAttribute('data-profile-doc-id') || '0', 10);
        if (type && profileDocId > 0) {
          handleReuse(type, profileDocId);
        }
      });
    });

    // Remove button
    form.querySelectorAll('.doc-remove').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var type = btn.getAttribute('data-doc-type');
        var appDocId = parseInt(btn.getAttribute('data-doc-id') || '0', 10);
        if (type && appDocId > 0) {
          handleRemove(type, appDocId);
        }
      });
    });

    /* ---------------- Step Navigation ---------------- */
    form.querySelectorAll('[data-next-step]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var next = parseInt(btn.getAttribute('data-next-step'), 10);
        if (next <= currentStep) return;

        clearErrors();

        // Save current step responses then move
        saveStep(function () {
          // Entering Step 4: validate required documents client-side
          if (next === 4) {
            var missingTypes = [];
            document.querySelectorAll('.app-form__doc-item').forEach(function (item) {
              var isRequired = item.querySelector('.badge--primary');
              if (isRequired) {
                var docId = parseInt(item.getAttribute('data-doc-id') || '0', 10);
                if (!docId) {
                  missingTypes.push(item.getAttribute('data-doc-type') || 'document');
                }
              }
            });
            if (missingTypes.length > 0) {
              setSaveStatus('error', 'Please upload all required documents before continuing.');
              return;
            }
          }
          showStep(next);
        });
      });
    });

    form.querySelectorAll('[data-prev-step]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var prev = parseInt(btn.getAttribute('data-prev-step'), 10);
        if (prev >= currentStep) return;
        showStep(prev);
      });
    });

    /* ---------------- Form Submit (Save Draft) ---------------- */
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      clearErrors();

      saveStep(function () {
        setSaveStatus('saved', 'Application saved.');
        // Stay on the form - no submission yet
      });
    });

    /* ---------------- Collect Responses ---------------- */
    function collectResponses() {
      var responses = {};

      // Collect all form fields with name="responses[...]"
      form.querySelectorAll('[name^="responses["]').forEach(function (field) {
        var name = field.getAttribute('name');
        var match = name.match(/responses\[(\d+)\]/);
        if (!match) return;
        var qId = parseInt(match[1], 10);
        if (!qId) return;

        var type = field.type;

        // Checkbox: collect all checked values
        if (type === 'checkbox') {
          if (!responses[qId]) responses[qId] = [];
          if (field.checked) responses[qId].push(field.value);
          return;
        }

        // Radio: only take checked
        if (type === 'radio') {
          if (field.checked) responses[qId] = field.value;
          return;
        }

        // Other types: take value
        responses[qId] = field.value;
      });

      // Convert checkbox arrays to comma-separated strings
      Object.keys(responses).forEach(function (qId) {
        if (Array.isArray(responses[qId])) {
          responses[qId] = responses[qId].join(',');
        }
      });

      return responses;
    }

    function saveStep(callback) {
      if (isSaving) return;
      isSaving = true;

      setSaveStatus('saving', 'Saving...');

      var responses = collectResponses();
      var data = {
        action: 'save_step',
        application_id: form.querySelector('[name="application_id"]').value,
        responses: responses
      };

      var body = Object.keys(data).map(function (k) {
        if (k === 'responses') {
          // Serialize responses object
          return Object.keys(data.responses).map(function (qId) {
            return encodeURIComponent('responses[' + qId + ']') + '=' + encodeURIComponent(data.responses[qId]);
          }).join('&');
        }
        return encodeURIComponent(k) + '=' + encodeURIComponent(data[k]);
      }).join('&');

      fetch(ENDPOINT, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          'X-CSRF-Token': CSRF
        },
        body: body
      })
        .then(function (res) {
          return res.json().catch(function () {
            return { success: false, message: 'Invalid server response.' };
          });
        })
        .then(function (json) {
          isSaving = false;
          if (json.success) {
            setSaveStatus('saved', 'Application saved.');
            if (callback) callback();
          } else {
            setSaveStatus('error', json.message || 'Your responses could not be saved. Please try again.');
          }
        })
        .catch(function () {
          isSaving = false;
          setSaveStatus('error', 'Your responses could not be saved. Please try again.');
        });
    }

    /* ---------------- Initialize ---------------- */
    showStep(1);
  });
})();