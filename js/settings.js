/* ================================================
   INVESTHOOD IT - Candidate Settings JavaScript
   Wires settings forms/modals toggles to the secure
   AJAX dispatcher (candidate/settings_actions.php).
   Mirrors the pattern used by js/profile.js.
   ================================================ */
'use strict';

(function () {
  document.addEventListener('DOMContentLoaded', function () {
    var settingsContent = document.getElementById('dashContent');
    if (!settingsContent) return;

    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var CSRF = csrfMeta ? csrfMeta.getAttribute('content') : '';
    var ENDPOINT = (window.APP_URL || '') + '/candidate/settings_actions.php';

    /* ---------------- Helpers ---------------- */
    function notify(type, title, message) {
      if (window.InvesthoodNotifications) {
        window.InvesthoodNotifications.show(type, title, message);
      } else {
        alert(title + '\n' + message);
      }
    }

    function post(action, data, cb, isFormData) {
      var headers = { 'X-CSRF-Token': CSRF };
      var body;
      if (isFormData) {
        if (!(data instanceof FormData)) {
          var fd = new FormData();
          Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
          data = fd;
        }
        data.append('action', action);
        body = data;
      } else {
        data = data || {};
        data.action = action;
        headers['Content-Type'] = 'application/x-www-form-urlencoded; charset=UTF-8';
        body = Object.keys(data).map(function (k) {
          return encodeURIComponent(k) + '=' + encodeURIComponent(data[k]);
        }).join('&');
      }
      fetch(ENDPOINT, { method: 'POST', headers: headers, body: body })
        .then(function (res) {
          return res.json().catch(function () {
            return { success: false, message: 'Invalid server response.' };
          });
        })
        .then(function (json) { cb(json); })
        .catch(function () { cb({ success: false, message: 'Network error. Please try again.' }); });
    }

    function showFormErrors(form, errors) {
      if (!form || !errors) return;
      Object.keys(errors).forEach(function (key) {
        var errEl = form.querySelector('[data-error-for="' + key + '"]');
        var field = form.querySelector('[name="' + key + '"]');
        if (errEl) errEl.textContent = errors[key];
        if (field) field.classList.add('form-input--error');
      });
    }

    function clearFormErrors(form) {
      if (!form) return;
      form.querySelectorAll('.form-error').forEach(function (el) { el.textContent = ''; });
      form.querySelectorAll('.form-input--error').forEach(function (el) {
        el.classList.remove('form-input--error');
      });
    }

    function setLoading(btn, loading) {
      if (!btn) return;
      btn.disabled = loading;
      btn.classList.toggle('loading', loading);
    }

    function reloadAfter(msg, title) {
      notify('success', title || 'Saved', msg);
      setTimeout(function () { window.location.reload(); }, 900);
    }

    /* ---------------- Modal helpers ---------------- */
    function openModal(id) { var m = document.getElementById(id); if (m) m.classList.add('active'); }
    function closeModal(id) { var m = document.getElementById(id); if (m) m.classList.remove('active'); }

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        var active = document.querySelectorAll('.modal-overlay.active');
        if (active.length) closeModal(active[active.length - 1].id);
      }
    });

    document.querySelectorAll('.modal-overlay').forEach(function (ov) {
      ov.addEventListener('click', function (e) { if (e.target === this) this.classList.remove('active'); });
    });

    document.querySelectorAll('.modal-cancel').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-modal');
        if (id) closeModal(id);
      });
    });

    /* ================ SETTINGS NAVIGATION (panels) ================ */
    var navItems = document.querySelectorAll('.settings-nav__item[data-settings]');
    var mobileSelect = document.getElementById('settingsMobileSelect');

    function activatePanel(name) {
      if (!name) return;
      document.querySelectorAll('.settings-panel[data-panel]').forEach(function (p) {
        p.classList.remove('is-active');
        if (p.getAttribute('data-panel') === name) p.classList.add('is-active');
      });
      navItems.forEach(function (n) {
        n.classList.toggle('active', n.getAttribute('data-settings') === name);
      });
      if (mobileSelect) mobileSelect.value = name;

      // Scroll to top of the panels area on mobile
      var panels = document.querySelector('.settings-panels');
      if (panels && window.innerWidth <= 1100) {
        panels.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }

    navItems.forEach(function (item) {
      item.addEventListener('click', function (e) {
        e.preventDefault();
        activatePanel(item.getAttribute('data-settings'));
      });
    });

    if (mobileSelect) {
      mobileSelect.addEventListener('change', function () {
        activatePanel(this.value);
      });
    }

    // Support deep-link via hash (e.g. #settings-account)
    function resolveFromHash() {
      var m = (window.location.hash || '').match(/^#settings-(.+)$/);
      if (m && m[1]) activatePanel(m[1]);
    }
    window.addEventListener('hashchange', resolveFromHash);
    resolveFromHash();

    /* ================ 1. PASSWORD SHOW/HIDE ================ */
    document.querySelectorAll('.password-toggle').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var wrapper = btn.closest('.password-input-wrapper');
        if (!wrapper) return;
        var input = wrapper.querySelector('input[type="password"], input[type="text"]');
        if (!input) return;
        var showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        btn.innerHTML = showing ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
      });
    });

    /* ================ 2. PASSWORD STRENGTH ================ */
    var newPasswordInput = document.getElementById('new_password');
    if (newPasswordInput) {
      var segments = document.querySelectorAll('.password-strength__segment');
      var strengthText = document.querySelector('.password-strength__text');

      function evaluateStrength(pw) {
        var score = 0;
        if (pw.length >= 8) score++;
        if (/[A-Z]/.test(pw)) score++;
        if (/[a-z]/.test(pw)) score++;
        if (/[0-9]/.test(pw)) score++;
        if (/[^a-zA-Z0-9]/.test(pw)) score++;

        segments.forEach(function (seg, idx) {
          seg.classList.remove('active', 'weak', 'medium', 'strong');
          if (idx < score) {
            seg.classList.add('active');
            seg.classList.add(score <= 2 ? 'weak' : (score <= 4 ? 'medium' : 'strong'));
          }
        });

        if (!strengthText) return;
        if (pw === '') {
          strengthText.textContent = '';
          strengthText.className = 'password-strength__text';
        } else if (score <= 2) {
          strengthText.textContent = 'Weak';
          strengthText.className = 'password-strength__text weak';
        } else if (score <= 4) {
          strengthText.textContent = 'Medium';
          strengthText.className = 'password-strength__text medium';
        } else {
          strengthText.textContent = 'Strong';
          strengthText.className = 'password-strength__text strong';
        }
      }

      // The settings form uses 5 segments; evaluate against them directly.
      newPasswordInput.addEventListener('input', function () { evaluateStrength(this.value); });
    }

    /* ================ 3. ACCOUNT FORM ================ */
    var accountForm = document.getElementById('accountForm');
    if (accountForm) {
      var accountBtn = document.getElementById('accountSaveBtn');
      accountForm.addEventListener('submit', function (e) {
        e.preventDefault();
        clearFormErrors(accountForm);
        setLoading(accountBtn, true);
        var data = {
          first_name: accountForm.first_name.value,
          last_name: accountForm.last_name.value,
          username: accountForm.username.value,
          email: accountForm.email.value,
          phone: accountForm.phone.value
        };
        post('update_account', data, function (json) {
          setLoading(accountBtn, false);
          if (json.success) {
            reloadAfter(json.message, 'Account updated');
          } else {
            notify('error', 'Update failed', json.message);
            showFormErrors(accountForm, json.errors);
          }
        });
      });
    }

    /* ================ 4. RESEND VERIFICATION ================ */
    var resendVerifyBtn = document.getElementById('resendVerifyBtn');
    if (resendVerifyBtn) {
      resendVerifyBtn.addEventListener('click', function () {
        setLoading(resendVerifyBtn, true);
        post('resend_verification', {}, function (json) {
          setLoading(resendVerifyBtn, false);
          if (json.success) {
            notify('success', 'Email sent', json.message);
          } else {
            notify('error', 'Not sent', json.message);
          }
        });
      });
    }

    /* ================ 5. PASSWORD FORM ================ */
    var passwordForm = document.getElementById('passwordForm');
    if (passwordForm) {
      var passwordBtn = document.getElementById('passwordSaveBtn');
      passwordForm.addEventListener('submit', function (e) {
        e.preventDefault();
        clearFormErrors(passwordForm);
        setLoading(passwordBtn, true);
        var data = {
          current_password: passwordForm.current_password.value,
          new_password: passwordForm.new_password.value,
          confirm_password: passwordForm.confirm_password.value
        };
        post('change_password', data, function (json) {
          setLoading(passwordBtn, false);
          if (json.success) {
            passwordForm.reset();
            notify('success', 'Password updated', json.message);
          } else {
            notify('error', 'Update failed', json.message);
            showFormErrors(passwordForm, json.errors);
          }
        });
      });
    }

    /* ================ 6. NOTIFICATIONS FORM ================ */
    var notificationsForm = document.getElementById('notificationsForm');
    if (notificationsForm) {
      var notifBtn = document.getElementById('notificationsSaveBtn');
      notificationsForm.addEventListener('submit', function (e) {
        e.preventDefault();
        clearFormErrors(notificationsForm);
        setLoading(notifBtn, true);
        var data = {};
        notificationsForm.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
          data[cb.name] = cb.checked ? '1' : '';
        });
        post('save_notifications', data, function (json) {
          setLoading(notifBtn, false);
          if (json.success) {
            notify('success', 'Saved', json.message);
          } else {
            notify('error', 'Save failed', json.message);
            showFormErrors(notificationsForm, json.errors);
          }
        });
      });
    }

    /* ================ 7. PREFERENCES FORM ================ */
    var preferencesForm = document.getElementById('preferencesForm');
    if (preferencesForm) {
      var prefsBtn = document.getElementById('preferencesSaveBtn');
      preferencesForm.addEventListener('submit', function (e) {
        e.preventDefault();
        clearFormErrors(preferencesForm);
        setLoading(prefsBtn, true);
        var data = {
          theme: (preferencesForm.querySelector('input[name="theme"]:checked') || {}).value || 'system',
          email_language: preferencesForm.email_language ? preferencesForm.email_language.value : 'en',
          timezone: preferencesForm.timezone ? preferencesForm.timezone.value : 'auto',
          date_format: preferencesForm.date_format ? preferencesForm.date_format.value : 'DD/MM/YYYY'
        };
        post('save_preferences', data, function (json) {
          setLoading(prefsBtn, false);
          if (json.success) {
            notify('success', 'Saved', json.message);
          } else {
            notify('error', 'Save failed', json.message);
            showFormErrors(preferencesForm, json.errors);
          }
        });
      });
    }

    /* ================ 8. CONSENT TOGGLES ================ */
    document.querySelectorAll('.consent-toggle').forEach(function (toggle) {
      toggle.addEventListener('change', function () {
        if (this.disabled) { this.checked = true; return; }
        var purpose = this.getAttribute('data-purpose');
        post('consent_update', { purpose: purpose, granted: this.checked ? 'true' : 'false' }, function (json) {
          if (!json.success) {
            toggle.checked = !toggle.checked;
            notify('error', 'Update failed', json.message);
          }
        });
      });
    });

    /* ================ 9. SESSIONS ================ */
    var logoutOtherBtn = document.getElementById('logoutOtherBtn');
    if (logoutOtherBtn) {
      logoutOtherBtn.addEventListener('click', function () {
        setLoading(logoutOtherBtn, true);
        post('logout_other_sessions', {}, function (json) {
          setLoading(logoutOtherBtn, false);
          if (json.success) {
            reloadAfter(json.message, 'Sessions ended');
          } else {
            notify('error', 'Failed', json.message);
          }
        });
      });
    }

    var logoutEverywhereBtn = document.getElementById('logoutEverywhereBtn');
    if (logoutEverywhereBtn) {
      logoutEverywhereBtn.addEventListener('click', function () {
        openModal('logoutEverywhereModal');
      });
    }

    var logoutEverywhereConfirmBtn = document.getElementById('logoutEverywhereConfirmBtn');
    if (logoutEverywhereConfirmBtn) {
      logoutEverywhereConfirmBtn.addEventListener('click', function () {
        setLoading(logoutEverywhereConfirmBtn, true);
        post('logout_everywhere', {}, function (json) {
          setLoading(logoutEverywhereConfirmBtn, false);
          closeModal('logoutEverywhereModal');
          if (json.success) {
            notify('success', 'Logged out', json.message);
            setTimeout(function () { window.location.href = (window.APP_URL || '') + '/login.php'; }, 1000);
          } else {
            notify('error', 'Failed', json.message);
          }
        });
      });
    }

    /* ================ 10. TRUSTED DEVICES ================ */
    document.querySelectorAll('.device-revoke').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-id');
        setLoading(btn, true);
        post('revoke_device', { device_id: id }, function (json) {
          setLoading(btn, false);
          if (json.success) {
            reloadAfter(json.message, 'Device revoked');
          } else {
            notify('error', 'Failed', json.message);
          }
        });
      });
    });

    var revokeAllDevicesBtn = document.getElementById('revokeAllDevicesBtn');
    if (revokeAllDevicesBtn) {
      revokeAllDevicesBtn.addEventListener('click', function () {
        setLoading(revokeAllDevicesBtn, true);
        post('revoke_all_devices', {}, function (json) {
          setLoading(revokeAllDevicesBtn, false);
          if (json.success) {
            reloadAfter(json.message, 'Devices revoked');
          } else {
            notify('error', 'Failed', json.message);
          }
        });
      });
    }

    /* ================ 11. DATA EXPORT ================ */
    var exportDataBtn = document.getElementById('exportDataBtn');
    if (exportDataBtn) {
      exportDataBtn.addEventListener('click', function () {
        setLoading(exportDataBtn, true);
        post('export_data', {}, function (json) {
          setLoading(exportDataBtn, false);
          if (json.success && json.data) {
            var blob = new Blob([JSON.stringify(json.data, null, 2)], { type: 'application/json' });
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = 'investhood-data-export.json';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            notify('success', 'Data exported', json.message);
          } else {
            notify('error', 'Export failed', json.message);
          }
        });
      });
    }

    /* ================ 12. DEACTIVATE ACCOUNT ================ */
    var deactivateBtn = document.getElementById('deactivateBtn');
    if (deactivateBtn) {
      deactivateBtn.addEventListener('click', function () {
        var form = document.getElementById('deactivateForm');
        if (form) form.reset();
        clearFormErrors(form);
        openModal('deactivateModal');
      });
    }

    var deactivateForm = document.getElementById('deactivateForm');
    if (deactivateForm) {
      var deactivateConfirmBtn = document.getElementById('deactivateConfirmBtn');
      deactivateForm.addEventListener('submit', function (e) {
        e.preventDefault();
        clearFormErrors(deactivateForm);
        setLoading(deactivateConfirmBtn, true);
        post('deactivate_account', { password: deactivateForm.password.value }, function (json) {
          setLoading(deactivateConfirmBtn, false);
          if (json.success) {
            closeModal('deactivateModal');
            notify('success', 'Account deactivated', json.message);
            if (json.redirect) {
              setTimeout(function () { window.location.href = json.redirect; }, 1200);
            }
          } else {
            notify('error', 'Deactivation failed', json.message);
            showFormErrors(deactivateForm, json.errors);
          }
        });
      });
    }

    /* ================ 13. DELETE ACCOUNT ================ */
    var deleteBtn = document.getElementById('deleteBtn');
    if (deleteBtn) {
      deleteBtn.addEventListener('click', function () {
        var form = document.getElementById('deleteForm');
        if (form) form.reset();
        clearFormErrors(form);
        openModal('deleteModal');
      });
    }

    var deleteForm = document.getElementById('deleteForm');
    if (deleteForm) {
      var deleteConfirmBtn = document.getElementById('deleteConfirmBtn');
      deleteForm.addEventListener('submit', function (e) {
        e.preventDefault();
        clearFormErrors(deleteForm);
        setLoading(deleteConfirmBtn, true);
        post('request_deletion', {
          password: deleteForm.password.value,
          reason: deleteForm.reason.value
        }, function (json) {
          setLoading(deleteConfirmBtn, false);
          if (json.success) {
            closeModal('deleteModal');
            notify('success', 'Request submitted', json.message);
          } else {
            notify('error', 'Request failed', json.message);
            showFormErrors(deleteForm, json.errors);
          }
        });
      });
    }

    /* ================ 14. EDIT TOGGLES (slide-over sections) ================ */
    document.querySelectorAll('.profile-edit-toggle').forEach(function (toggle) {
      toggle.addEventListener('click', function () {
        var targetId = this.getAttribute('data-toggle-target');
        var target = document.getElementById(targetId);
        if (!target) return;
        var isOpen = target.classList.contains('is-open');
        document.querySelectorAll('.profile-edit-section.is-open').forEach(function (sec) {
          sec.classList.remove('is-open');
        });
        if (!isOpen) target.classList.add('is-open');
      });
    });

    console.log('%c Investhood IT Settings ', 'background: #06b6d4; color: white; font-size: 14px; font-weight: bold; padding: 6px 10px; border-radius: 4px;');
  });
})();
