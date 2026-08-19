/* ================================================
   INVESTHOOD IT - Candidate Profile JavaScript
   Wires profile forms/modals/skills/consent/docs to
   the secure AJAX dispatcher (candidate/profile_actions.php).
   ================================================ */
'use strict';

(function () {
  document.addEventListener('DOMContentLoaded', function () {
    var profileContent = document.getElementById('dashContent');
    if (!profileContent) return;

    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var CSRF = csrfMeta ? csrfMeta.getAttribute('content') : '';
    var ENDPOINT = (window.APP_URL || '') + '/candidate/profile_actions.php';

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

    function refreshAvatars() {
      document.querySelectorAll('img[src]').forEach(function (img) {
        var src = img.getAttribute('src') || '';
        if (src.indexOf('avatar.php') !== -1) {
          img.setAttribute('src', src.split('?')[0] + '?t=' + new Date().getTime());
        }
      });
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

    function confirmAction(title, message, onConfirm) {
      var titleEl = document.getElementById('confirmTitle');
      var msgEl = document.getElementById('confirmMessage');
      if (titleEl) titleEl.textContent = title;
      if (msgEl) msgEl.textContent = message;
      var okBtn = document.getElementById('confirmOkBtn');
      var cancelBtn = document.getElementById('confirmCancelBtn');
      var okBtnEl = okBtn ? okBtn.querySelector('.btn__text') : null;

      function cleanup() {
        closeModal('confirmModal');
        if (okBtn) { okBtn.removeEventListener('click', handleOk); okBtn.disabled = false; }
        if (cancelBtn) cancelBtn.removeEventListener('click', handleCancel);
      }
      function handleOk() {
        if (okBtn) { okBtn.disabled = true; if (okBtnEl) okBtnEl.textContent = 'Confirming...'; }
        if (cancelBtn) cancelBtn.disabled = true;
        onConfirm(cleanup);
      }
      function handleCancel() { cleanup(); }
      okBtn.addEventListener('click', handleOk);
      cancelBtn.addEventListener('click', handleCancel);
      openModal('confirmModal');
    }

    /* ================ 1. PERSONAL FORM ================ */
    var personalForm = document.getElementById('personalForm');
    if (personalForm) {
      var personalBtn = document.getElementById('personalSaveBtn');
      personalForm.addEventListener('submit', function (e) {
        e.preventDefault();
        clearFormErrors(personalForm);
        setLoading(personalBtn, true);
        var data = {
          first_name: personalForm.first_name.value,
          last_name: personalForm.last_name.value,
          email: personalForm.email.value,
          phone: personalForm.phone.value,
          date_of_birth: personalForm.date_of_birth.value,
          gender: personalForm.gender.value,
          address: personalForm.address ? personalForm.address.value : '',
          province: personalForm.province.value,
          city: personalForm.city ? personalForm.city.value : ''
        };
        post('update_personal', data, function (json) {
          setLoading(personalBtn, false);
          if (json.success) {
            var heroName = document.getElementById('heroName');
            if (heroName) heroName.textContent = (data.first_name + ' ' + data.last_name).trim() || heroName.textContent;
            var heroMail = document.querySelector('.profile-hero__meta .fa-envelope');
            if (heroMail) heroMail.parentNode.innerHTML = '<i class="fas fa-envelope"></i> ' + data.email;
            reloadAfter(json.message, 'Profile updated');
          } else {
            notify('error', 'Update failed', json.message);
            showFormErrors(personalForm, json.errors);
          }
        });
      });
    }

/* ================ 2. PROFILE PICTURE ================ */
    var pictureInput = document.getElementById('pictureInput');
    var picturePreview = document.getElementById('picturePreview');
    var pictureSaveBtn = document.getElementById('pictureSaveBtn');
    var pictureChooseBtn = document.getElementById('pictureChooseBtn');
    var pictureRemoveBtn = document.getElementById('pictureRemoveBtn');
    var pictureCancelBtn = document.getElementById('pictureCancelBtn');

    function openPictureModal() {
      if (pictureInput) pictureInput.value = '';
      if (picturePreview) { picturePreview.src = ''; picturePreview.hidden = true; }
      if (pictureSaveBtn) pictureSaveBtn.disabled = true;
      if (pictureRemoveBtn) pictureRemoveBtn.disabled = false;
      openModal('pictureModal');
    }

var uploadPictureBtn = document.getElementById('uploadPictureBtn');
    var uploadPictureBtn2 = document.getElementById('uploadPictureBtn2');
    var uploadPictureBtn3 = document.getElementById('uploadPictureBtn3');
    if (uploadPictureBtn) uploadPictureBtn.addEventListener('click', openPictureModal);
    if (uploadPictureBtn2) uploadPictureBtn2.addEventListener('click', openPictureModal);
    if (uploadPictureBtn3) uploadPictureBtn3.addEventListener('click', openPictureModal);

    function uploadSelectedPicture() {
      var file = pictureInput.files[0];
      if (!file) return;
      var fd = new FormData();
      fd.append('profile_picture', file);
      if (pictureSaveBtn) setLoading(pictureSaveBtn, true);
      if (pictureCancelBtn) pictureCancelBtn.disabled = true;
      post('upload_picture', fd, function (json) {
        if (pictureSaveBtn) setLoading(pictureSaveBtn, false);
        if (pictureCancelBtn) pictureCancelBtn.disabled = false;
        if (json.success) {
          closeModal('pictureModal');
          refreshAvatars();
          reloadAfter(json.message, 'Success');
        } else {
          notify('error', 'Upload failed', json.message);
        }
      }, true);
    }

    if (pictureInput) pictureInput.addEventListener('change', function () {
      var file = pictureInput.files[0];
      if (!file) return;
      var reader = new FileReader();
      reader.onload = function (ev) {
        if (picturePreview) { picturePreview.src = ev.target.result; picturePreview.hidden = false; }
        if (pictureSaveBtn) pictureSaveBtn.disabled = false;
      };
      reader.readAsDataURL(file);
    });

    if (pictureChooseBtn) pictureChooseBtn.addEventListener('click', function () { pictureInput.click(); });

    // Upload only happens when the user clicks the Upload button (manual flow).
    if (pictureSaveBtn) pictureSaveBtn.addEventListener('click', uploadSelectedPicture);

    if (pictureRemoveBtn) pictureRemoveBtn.addEventListener('click', function () {
      setLoading(pictureRemoveBtn, true);
      post('remove_picture', {}, function (json) {
        setLoading(pictureRemoveBtn, false);
        if (json.success) {
          closeModal('pictureModal');
          refreshAvatars();
          reloadAfter(json.message, 'Success');
        } else {
          notify('error', 'Failed', json.message);
        }
      });
    });

    if (pictureCancelBtn) pictureCancelBtn.addEventListener('click', function () {
      closeModal('pictureModal');
    });

    /* ================ 4. QUALIFICATIONS ================ */
    var qualForm = document.getElementById('qualificationForm');
    var qualTitle = document.getElementById('qualificationModalTitle');
    var qualSaveBtn = document.getElementById('qualificationSaveBtn');
    var qualificationsList = document.getElementById('qualificationsList');

    var addQualBtn = document.getElementById('addQualificationBtn');
    if (addQualBtn) addQualBtn.addEventListener('click', function () {
      if (qualForm) qualForm.reset();
      clearFormErrors(qualForm);
      var idField = document.getElementById('qual_id'); if (idField) idField.value = '';
      if (qualTitle) qualTitle.textContent = 'Add Qualification';
      openModal('qualificationModal');
    });

    if (qualificationsList) qualificationsList.addEventListener('click', function (e) {
      var editBtn = e.target.closest('.qualification-edit');
      var delBtn = e.target.closest('.qualification-delete');
      if (editBtn) {
        clearFormErrors(qualForm);
        var idField = document.getElementById('qual_id'); if (idField) idField.value = editBtn.getAttribute('data-id');
        var n = document.getElementById('qual_name'); if (n) n.value = editBtn.getAttribute('data-name') || '';
        var i = document.getElementById('qual_institution'); if (i) i.value = editBtn.getAttribute('data-institution') || '';
        var y = document.getElementById('qual_year'); if (y) y.value = editBtn.getAttribute('data-year') || '';
        var l = document.getElementById('qual_level'); if (l) l.value = editBtn.getAttribute('data-level') || '';
        if (qualTitle) qualTitle.textContent = 'Edit Qualification';
        openModal('qualificationModal');
      }
      if (delBtn) {
        var id = delBtn.getAttribute('data-id');
        var name = delBtn.getAttribute('data-name') || 'this qualification';
        confirmAction('Delete Qualification', 'Remove "' + name + '" from your profile?', function (cleanup) {
post('qualification_delete', { id: id }, function (json) {
            if (json.success) {
              var item = delBtn.closest('.timeline-item');
              if (item) item.remove();
              cleanup();
              reloadAfter(json.message, 'Deleted');
            } else {
              cleanup();
              notify('error', 'Failed', json.message);
            }
          });
        });
      }
    });

    if (qualForm && qualSaveBtn) {
      qualSaveBtn.addEventListener('click', function () {
        clearFormErrors(qualForm);
        setLoading(qualSaveBtn, true);
        var id = document.getElementById('qual_id').value;
        var action = id ? 'qualification_update' : 'qualification_add';
        var data = {
          id: id,
          name: document.getElementById('qual_name').value,
          institution: document.getElementById('qual_institution').value,
          year_completed: document.getElementById('qual_year').value,
          level: document.getElementById('qual_level').value
        };
        post(action, data, function (json) {
          setLoading(qualSaveBtn, false);
          if (json.success) {
            closeModal('qualificationModal');
            reloadAfter(json.message, 'Saved');
          } else {
            notify('error', 'Save failed', json.message);
            showFormErrors(qualForm, json.errors);
          }
        });
      });

document.querySelectorAll('.qualification-modal-cancel').forEach(function (b) {
        b.addEventListener('click', function () { closeModal('qualificationModal'); });
      });
    }

    /* ================ 4b. CERTIFICATIONS ================ */
    var certForm = document.getElementById('certificationForm');
    var certTitle = document.getElementById('certificationModalTitle');
    var certSaveBtn = document.getElementById('certificationSaveBtn');
    var certificationsList = document.getElementById('certificationsList');

    function openCertModal() {
      if (certForm) certForm.reset();
      clearFormErrors(certForm);
      var idField = document.getElementById('cert_id'); if (idField) idField.value = '';
      if (certTitle) certTitle.textContent = 'Add Certification';
      openModal('certificationModal');
    }

    var addCertBtn = document.getElementById('addCertificationBtn');
    if (addCertBtn) addCertBtn.addEventListener('click', openCertModal);
    var addCertBtn2 = document.getElementById('addCertificationBtn2');
    if (addCertBtn2) addCertBtn2.addEventListener('click', openCertModal);
    var addCertBtn3 = document.getElementById('addCertificationBtn3');
    if (addCertBtn3) addCertBtn3.addEventListener('click', openCertModal);

    if (certificationsList) certificationsList.addEventListener('click', function (e) {
      var editBtn = e.target.closest('.certification-edit');
      var delBtn = e.target.closest('.certification-delete');
      if (editBtn) {
        clearFormErrors(certForm);
        var idField = document.getElementById('cert_id'); if (idField) idField.value = editBtn.getAttribute('data-id');
        var n = document.getElementById('cert_name'); if (n) n.value = editBtn.getAttribute('data-name') || '';
        var o = document.getElementById('cert_org'); if (o) o.value = editBtn.getAttribute('data-org') || '';
        var y = document.getElementById('cert_year'); if (y) y.value = editBtn.getAttribute('data-year') || '';
        var x = document.getElementById('cert_expiry'); if (x) x.value = editBtn.getAttribute('data-expiry') || '';
        var c = document.getElementById('cert_credential'); if (c) c.value = editBtn.getAttribute('data-credential') || '';
        if (certTitle) certTitle.textContent = 'Edit Certification';
        openModal('certificationModal');
      }
      if (delBtn) {
        var id = delBtn.getAttribute('data-id');
        var name = delBtn.getAttribute('data-name') || 'this certification';
        confirmAction('Delete Certification', 'Remove "' + name + '" from your profile?', function (cleanup) {
          post('certification_delete', { id: id }, function (json) {
            if (json.success) {
              var item = delBtn.closest('.timeline-item');
              if (item) item.remove();
              cleanup();
              reloadAfter(json.message, 'Deleted');
            } else {
              cleanup();
              notify('error', 'Failed', json.message);
            }
          });
        });
      }
    });

    if (certForm && certSaveBtn) {
      certSaveBtn.addEventListener('click', function () {
        clearFormErrors(certForm);
        setLoading(certSaveBtn, true);
        var id = document.getElementById('cert_id').value;
        var action = id ? 'certification_update' : 'certification_add';
        var data = {
          id: id,
          name: document.getElementById('cert_name').value,
          issuing_organisation: document.getElementById('cert_org').value,
          year_obtained: document.getElementById('cert_year').value,
          expiry_date: document.getElementById('cert_expiry').value,
          credential_id: document.getElementById('cert_credential').value
        };
        post(action, data, function (json) {
          setLoading(certSaveBtn, false);
          if (json.success) {
            closeModal('certificationModal');
            reloadAfter(json.message, 'Saved');
          } else {
            notify('error', 'Save failed', json.message);
            showFormErrors(certForm, json.errors);
          }
        });
      });

      document.querySelectorAll('.certification-modal-cancel').forEach(function (b) {
        b.addEventListener('click', function () { closeModal('certificationModal'); });
      });
    }

    /* ================ 5. WORK EXPERIENCE ================ */
    var expForm = document.getElementById('experienceForm');
    var expTitle = document.getElementById('experienceModalTitle');
    var expSaveBtn = document.getElementById('experienceSaveBtn');
    var experiencesList = document.getElementById('experiencesList');

    function setExpCurrent(current) {
      var curField = document.getElementById('exp_current');
      if (curField) curField.checked = !!current;
      var endField = document.getElementById('exp_end');
      if (endField) endField.disabled = !!current;
    }

    var addExpBtn = document.getElementById('addExperienceBtn');
    if (addExpBtn) addExpBtn.addEventListener('click', function () {
      if (expForm) expForm.reset();
      clearFormErrors(expForm);
      var idField = document.getElementById('exp_id'); if (idField) idField.value = '';
      if (expTitle) expTitle.textContent = 'Add Work Experience';
      setExpCurrent(false);
      openModal('experienceModal');
    });

    var expCurrentCheck = document.getElementById('exp_current');
    if (expCurrentCheck) expCurrentCheck.addEventListener('change', function () {
      setExpCurrent(this.checked);
    });

    if (experiencesList) experiencesList.addEventListener('click', function (e) {
      var editBtn = e.target.closest('.experience-edit');
      var delBtn = e.target.closest('.experience-delete');
      if (editBtn) {
        clearFormErrors(expForm);
        var idField = document.getElementById('exp_id'); if (idField) idField.value = editBtn.getAttribute('data-id');
        var j = document.getElementById('exp_job'); if (j) j.value = editBtn.getAttribute('data-job') || '';
        var c = document.getElementById('exp_company'); if (c) c.value = editBtn.getAttribute('data-company') || '';
        var s = document.getElementById('exp_start'); if (s) s.value = editBtn.getAttribute('data-start') || '';
        var en = document.getElementById('exp_end'); if (en) en.value = editBtn.getAttribute('data-end') || '';
        var d = document.getElementById('exp_desc'); if (d) d.value = editBtn.getAttribute('data-desc') || '';
        setExpCurrent(editBtn.getAttribute('data-current') === '1');
        if (expTitle) expTitle.textContent = 'Edit Work Experience';
        openModal('experienceModal');
      }
      if (delBtn) {
        var id = delBtn.getAttribute('data-id');
        var job = delBtn.getAttribute('data-job') || 'this role';
        confirmAction('Delete Work Experience', 'Remove "' + job + '" from your profile?', function (cleanup) {
post('experience_delete', { id: id }, function (json) {
            if (json.success) {
              var item = delBtn.closest('.timeline-item');
              if (item) item.remove();
              cleanup();
              reloadAfter(json.message, 'Deleted');
            } else {
              cleanup();
              notify('error', 'Failed', json.message);
            }
          });
        });
      }
    });

    if (expForm && expSaveBtn) {
      expSaveBtn.addEventListener('click', function () {
        clearFormErrors(expForm);
        setLoading(expSaveBtn, true);
        var id = document.getElementById('exp_id').value;
        var action = id ? 'experience_update' : 'experience_add';
        var current = document.getElementById('exp_current').checked;
        var data = {
          id: id,
          job_title: document.getElementById('exp_job').value,
          company: document.getElementById('exp_company').value,
          start_date: document.getElementById('exp_start').value,
          end_date: current ? '' : document.getElementById('exp_end').value,
          is_current: current ? 'on' : '',
          description: document.getElementById('exp_desc').value
        };
        post(action, data, function (json) {
          setLoading(expSaveBtn, false);
          if (json.success) {
            closeModal('experienceModal');
            reloadAfter(json.message, 'Saved');
          } else {
            notify('error', 'Save failed', json.message);
            showFormErrors(expForm, json.errors);
          }
        });
      });

      document.querySelectorAll('.experience-modal-cancel').forEach(function (b) {
        b.addEventListener('click', function () { closeModal('experienceModal'); });
      });
    }

    /* ================ 6. DOCUMENTS ================ */
    var docForm = document.getElementById('documentForm');
    var docTitle = document.getElementById('documentModalTitle');
    var docSaveBtn = document.getElementById('documentSaveBtn');

    var uploadDocBtn = document.getElementById('uploadDocumentBtn');
    if (uploadDocBtn) uploadDocBtn.addEventListener('click', function () {
      if (docForm) docForm.reset();
      clearFormErrors(docForm);
      var idField = document.getElementById('doc_id'); if (idField) idField.value = '';
      if (docTitle) docTitle.textContent = 'Upload Document';
      openModal('documentModal');
    });

    function openDocReplace(id, titleText) {
      if (docForm) docForm.reset();
      clearFormErrors(docForm);
      var idField = document.getElementById('doc_id'); if (idField) idField.value = id;
      if (docTitle) docTitle.textContent = titleText;
      var typeField = document.getElementById('doc_type');
      if (typeField) typeField.disabled = true;
      openModal('documentModal');
    }

    function closeDocModal() {
      closeModal('documentModal');
      var typeField = document.getElementById('doc_type');
      if (typeField) typeField.disabled = false;
    }

    document.querySelectorAll('.doc-replace').forEach(function (btn) {
      btn.addEventListener('click', function () {
        openDocReplace(btn.getAttribute('data-id'), 'Replace Document');
      });
    });

    document.querySelectorAll('.doc-delete').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-id');
        confirmAction('Delete Document', 'Remove this document from your profile?', function (cleanup) {
          post('document_delete', { id: id }, function (json) {
            if (json.success) {
              var item = btn.closest('.doc-item');
              if (item) item.remove();
              cleanup();
              reloadAfter(json.message, 'Deleted');
            } else {
              cleanup();
              notify('error', 'Failed', json.message);
            }
          });
        });
      });
    });

    if (docForm && docSaveBtn) {
      docSaveBtn.addEventListener('click', function () {
        var fileInput = document.getElementById('doc_file');
        if (!fileInput.files.length) {
          notify('error', 'No file', 'Please choose a file to upload.');
          return;
        }
        clearFormErrors(docForm);
        setLoading(docSaveBtn, true);
        var id = document.getElementById('doc_id').value;
        var isReplace = !!id;
        var fd = new FormData();
        fd.append('document', fileInput.files[0]);
        fd.append('document_type', document.getElementById('doc_type').value);
        if (isReplace) fd.append('id', id);
        post(isReplace ? 'document_replace' : 'document_upload', fd, function (json) {
          setLoading(docSaveBtn, false);
          if (json.success) {
            closeDocModal();
            reloadAfter(json.message, 'Uploaded');
          } else {
            notify('error', 'Upload failed', json.message);
            showFormErrors(docForm, json.errors);
          }
        }, true);
      });

      document.querySelectorAll('.document-modal-cancel').forEach(function (b) {
        b.addEventListener('click', function () { closeDocModal(); });
      });
    }

    /* ================ 7. CONSENT TOGGLES ================ */
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

    /* ================ 8. SKILLS ================ */
    document.querySelectorAll('.skill-search').forEach(function (search) {
      var input = search.querySelector('.skill-search__input');
      var results = search.querySelector('.skill-search__results');
      var category = input ? input.getAttribute('data-category') : 'technical';

      if (!input || !results) return;

      function doSearch() {
        var q = input.value.trim();
        var url = ENDPOINT + '?action=search_skills&category=' + encodeURIComponent(category) + '&q=' + encodeURIComponent(q);
        fetch(url)
          .then(function (res) { return res.json(); })
          .then(function (json) {
            results.innerHTML = '';
            var skills = (json.success && json.skills) ? json.skills : [];
            if (!skills.length) {
              results.innerHTML = '<div class="skill-search__empty">No matching skills.</div>';
              results.hidden = false;
              return;
            }
skills.forEach(function (sk) {
              var item = document.createElement('div');
              item.className = 'skill-search__result';

              var name = document.createElement('span');
              name.className = 'skill-search__result-name';
              name.textContent = sk.name;

              var levelWrap = document.createElement('div');
              levelWrap.className = 'skill-search__level-wrap';

              var levelLabel = document.createElement('span');
              levelLabel.className = 'skill-search__level-label';
              levelLabel.textContent = 'Level:';

              var levelSelect = document.createElement('select');
              levelSelect.className = 'skill-search__level';
              levelSelect.setAttribute('aria-label', 'Proficiency level for ' + sk.name);
              ['beginner', 'intermediate', 'advanced', 'expert'].forEach(function (lv) {
                var opt = document.createElement('option');
                opt.value = lv;
                opt.textContent = lv.charAt(0).toUpperCase() + lv.slice(1);
                if (lv === 'intermediate') opt.selected = true;
                levelSelect.appendChild(opt);
              });
              levelSelect.addEventListener('click', function (e) { e.stopPropagation(); });

              var addBtn = document.createElement('button');
              addBtn.type = 'button';
              addBtn.className = 'skill-search__result-add';
              addBtn.innerHTML = '<i class="fas fa-plus"></i> Add';
              addBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                addSkill(sk, category, levelSelect.value);
                results.hidden = true;
                input.value = '';
              });

              levelWrap.appendChild(levelLabel);
              levelWrap.appendChild(levelSelect);
              item.appendChild(name);
              item.appendChild(levelWrap);
              item.appendChild(addBtn);
              results.appendChild(item);
            });
            results.hidden = false;
          })
          .catch(function () {});
      }

      var debounce;
      input.addEventListener('input', function () {
        clearTimeout(debounce);
        debounce = setTimeout(doSearch, 250);
      });

      document.addEventListener('click', function (e) {
        if (!search.contains(e.target)) results.hidden = true;
      });
    });

function addSkill(skill, category, proficiency) {
      proficiency = proficiency || 'intermediate';
      post('skill_add', { skill_id: skill.id, proficiency: proficiency }, function (json) {
        if (json.success) {
          notify('success', 'Skill added', json.message);
          var containerId = category === 'soft' ? 'softSkills' : 'technicalSkills';
          var container = document.getElementById(containerId);
          if (container) {
            var empty = container.querySelector('.skill-empty');
            if (empty) empty.remove();
            var chip = document.createElement('span');
            chip.className = 'skill-chip' + (category === 'soft' ? ' skill-chip--soft' : '');
            chip.setAttribute('data-id', skill.id);
            chip.innerHTML = '<span class="skill-chip__name">' + skill.name + '</span>' +
              '<span class="skill-chip__level">' + proficiency.charAt(0).toUpperCase() + proficiency.slice(1) + '</span>' +
              '<button type="button" class="skill-chip__remove skill-remove" data-id="' + skill.id + '" data-name="' + skill.name + '"><i class="fas fa-times"></i></button>';
            container.appendChild(chip);
            chip.querySelector('.skill-remove').addEventListener('click', removeSkillHandler);
          }
        } else {
          notify('error', 'Could not add', json.message);
        }
      });
    }

    function removeSkillHandler() {
      var btn = this;
      var id = btn.getAttribute('data-id');
      var name = btn.getAttribute('data-name') || 'this skill';
      confirmAction('Remove Skill', 'Remove "' + name + '" from your profile?', function (cleanup) {
        post('skill_remove', { skill_id: id }, function (json) {
          cleanup();
          if (json.success) {
            var chip = btn.closest('.skill-chip');
            if (chip) chip.remove();
            notify('success', 'Removed', json.message);
          } else {
            notify('error', 'Failed', json.message);
          }
        });
      });
    }

document.querySelectorAll('.skill-remove').forEach(function (btn) {
      btn.addEventListener('click', removeSkillHandler);
    });

    /* ================ 9. EDIT TOGGLES (slide-over sections) ================ */
    document.querySelectorAll('.profile-edit-toggle').forEach(function (toggle) {
      toggle.addEventListener('click', function () {
        var targetId = this.getAttribute('data-toggle-target');
        var target = document.getElementById(targetId);
        if (!target) return;
        var isOpen = target.classList.contains('is-open');
        // Close all others
        document.querySelectorAll('.profile-edit-section.is-open').forEach(function (sec) {
          sec.classList.remove('is-open');
        });
        if (!isOpen) target.classList.add('is-open');
      });
    });

    /* ================ 10. ACCOUNT INFO collapsible ================ */
var accountToggle = document.querySelector('[data-toggle-account]');
    if (accountToggle) accountToggle.addEventListener('click', function () {
      var wrap = document.getElementById('accountInfo');
      if (wrap) wrap.classList.toggle('open');
      var collapse = accountToggle.closest('.account-collapse');
      if (collapse) collapse.classList.toggle('open');
    });

    /* ================ 11. Extra quick-action / section buttons ================ */
    var qualAddBtn2 = document.getElementById('qualificationAddBtn');
    if (qualAddBtn2) qualAddBtn2.addEventListener('click', function () {
      if (qualForm) qualForm.reset();
      clearFormErrors(qualForm);
      var idField = document.getElementById('qual_id'); if (idField) idField.value = '';
      if (qualTitle) qualTitle.textContent = 'Add Qualification';
      openModal('qualificationModal');
    });

    var expAddBtn2 = document.getElementById('experienceAddBtn');
    if (expAddBtn2) expAddBtn2.addEventListener('click', function () {
      if (expForm) expForm.reset();
      clearFormErrors(expForm);
      var idField = document.getElementById('exp_id'); if (idField) idField.value = '';
      if (expTitle) expTitle.textContent = 'Add Work Experience';
      setExpCurrent(false);
      openModal('experienceModal');
    });

    var uploadOtherDocBtn = document.getElementById('uploadOtherDocBtn');
    if (uploadOtherDocBtn) uploadOtherDocBtn.addEventListener('click', function () {
      if (docForm) docForm.reset();
      clearFormErrors(docForm);
      var idField = document.getElementById('doc_id'); if (idField) idField.value = '';
      var typeField = document.getElementById('doc_type');
      if (typeField) typeField.value = 'supporting';
      if (docTitle) docTitle.textContent = 'Upload Document';
      openModal('documentModal');
    });

    /* ================ 12. PROFESSIONAL INFO FORM (employment + availability) ================ */
    var profInfoForm = document.getElementById('profInfoForm');
    if (profInfoForm) {
      var profInfoBtn = document.getElementById('profInfoSaveBtn');
      profInfoForm.addEventListener('submit', function (e) {
        e.preventDefault();
        clearFormErrors(profInfoForm);
        setLoading(profInfoBtn, true);
var data = {
          professional_title: profInfoForm.professional_title ? profInfoForm.professional_title.value : '',
          professional_summary: profInfoForm.professional_summary ? profInfoForm.professional_summary.value : '',
          career_interests: profInfoForm.career_interests ? profInfoForm.career_interests.value : '',
          employment_status: profInfoForm.employment_status.value,
          availability_status: profInfoForm.availability_status.value,
          availability_date: profInfoForm.availability_date ? profInfoForm.availability_date.value : ''
        };
        post('update_professional', data, function (json) {
          setLoading(profInfoBtn, false);
          if (json.success) {
            reloadAfter(json.message, 'Profile updated');
          } else {
            notify('error', 'Update failed', json.message);
            showFormErrors(profInfoForm, json.errors);
          }
        });
      });
    }

    /* ================ 13. AVAILABILITY FORM ================ */
    var availForm = document.getElementById('availabilityForm');
    if (availForm) {
      var availFormBtn = document.getElementById('availabilitySaveBtn');
      var availSelect2 = document.getElementById('availability_select');
      var availDateWrap2 = document.getElementById('availabilityDateWrap2');
      function toggleAvailDate2() {
        var show = availSelect2 && availSelect2.value === 'available_from_date';
        if (availDateWrap2) availDateWrap2.style.display = show ? '' : 'none';
      }
      if (availSelect2) availSelect2.addEventListener('change', toggleAvailDate2);
      toggleAvailDate2();

      availForm.addEventListener('submit', function (e) {
        e.preventDefault();
        clearFormErrors(availForm);
        setLoading(availFormBtn, true);
var data = {
          professional_title: profInfoForm && profInfoForm.professional_title ? profInfoForm.professional_title.value : '',
          professional_summary: profInfoForm && profInfoForm.professional_summary ? profInfoForm.professional_summary.value : '',
          career_interests: profInfoForm && profInfoForm.career_interests ? profInfoForm.career_interests.value : '',
          availability_status: availForm['availability_status'].value,
          availability_date: document.getElementById('availability_date2') ? document.getElementById('availability_date2').value : ''
        };
        post('update_professional', data, function (json) {
          setLoading(availFormBtn, false);
          if (json.success) {
            reloadAfter(json.message, 'Availability updated');
          } else {
            notify('error', 'Update failed', json.message);
            showFormErrors(availForm, json.errors);
          }
        });
      });
    }

/* ================ 14. SUMMARY FORM (professional summary + career interests) ================ */
    var summaryForm = document.getElementById('summaryForm');
    if (summaryForm) {
      var summarySaveBtn = document.getElementById('summarySaveBtn');
      summaryForm.addEventListener('submit', function (e) {
        e.preventDefault();
        clearFormErrors(summaryForm);
        setLoading(summarySaveBtn, true);
var data = {
          professional_title: profInfoForm && profInfoForm.professional_title ? profInfoForm.professional_title.value : '',
          professional_summary: summaryForm.professional_summary.value,
          career_interests: summaryForm.career_interests.value
        };
        post('update_professional', data, function (json) {
          setLoading(summarySaveBtn, false);
          if (json.success) {
            reloadAfter(json.message, 'Profile updated');
          } else {
            notify('error', 'Update failed', json.message);
            showFormErrors(summaryForm, json.errors);
          }
        });
      });
    }

    /* ================ 15. SECONDARY ADD BUTTONS (qualifications / experience) ================ */
    var qualAddBtn3 = document.getElementById('addQualificationBtn2');
    if (qualAddBtn3) qualAddBtn3.addEventListener('click', function () {
      if (qualForm) qualForm.reset();
      clearFormErrors(qualForm);
      var idField = document.getElementById('qual_id'); if (idField) idField.value = '';
      if (qualTitle) qualTitle.textContent = 'Add Qualification';
      openModal('qualificationModal');
    });

    var expAddBtn3 = document.getElementById('addExperienceBtn2');
    if (expAddBtn3) expAddBtn3.addEventListener('click', function () {
      if (expForm) expForm.reset();
      clearFormErrors(expForm);
      var idField = document.getElementById('exp_id'); if (idField) idField.value = '';
      if (expTitle) expTitle.textContent = 'Add Work Experience';
      setExpCurrent(false);
      openModal('experienceModal');
    });

    /* ================ 16. UPLOAD CV BUTTON ================ */
    var uploadCvBtn = document.getElementById('uploadCvBtn');
    if (uploadCvBtn) uploadCvBtn.addEventListener('click', function () {
      if (docForm) docForm.reset();
      clearFormErrors(docForm);
      var idField = document.getElementById('doc_id'); if (idField) idField.value = '';
      var typeField = document.getElementById('doc_type');
      if (typeField) typeField.value = 'cv';
      if (docTitle) docTitle.textContent = 'Upload CV';
      openModal('documentModal');
    });

    console.log('%c Investhood IT Profile ', 'background: #06b6d4; color: white; font-size: 14px; font-weight: bold; padding: 6px 10px; border-radius: 4px;');
  });
})();

