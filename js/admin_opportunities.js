/**
 * ================================================
 * INVESTHOOD IT - Opportunity Management JS
 * ================================================
 * Power interactive behaviour for the Opportunity
 * Management module:
 *   - Live search & filtering on the opportunity list
 *   - Dynamic cohort dropdown loading (AJAX)
 *   - Cohort date hint on create/edit forms
 *   - Document row add/remove
 *   - Skill row add/remove (delegated)
 *   - Three-dot "more" action menus (delegated)
 */

(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    initOpportunityFilters();
    initCohortLoader();
    initDocRows();
    initMoreMenus();
  });

  /* ------------------------------------------------------------
     Opportunity list search & filtering
     ------------------------------------------------------------ */
  function initOpportunityFilters() {
    var searchInput = document.getElementById('oppSearchInput');
    var statusFilter = document.getElementById('oppFilterStatus');
    var typeFilter = document.getElementById('oppFilterType');
    var progFilter = document.getElementById('oppFilterProgramme');
    var cohortFilter = document.getElementById('oppFilterCohort');
    var provFilter = document.getElementById('oppFilterProvince');
    var openFilter = document.getElementById('oppFilterOpen');
    var closeFilter = document.getElementById('oppFilterClose');
    var applyBtn = document.getElementById('oppApplyFilters');
    var clearBtn = document.getElementById('oppClearFilters');
    var countEl = document.getElementById('oppResultsCount');
    var list = document.getElementById('oppList');

    if (!list) return;

    var cards = list.querySelectorAll('.opp-card');

    function norm(v) { return (v || '').toString().trim().toLowerCase(); }

    function applyFilters() {
      var q = norm(searchInput ? searchInput.value : '');
      var st = norm(statusFilter ? statusFilter.value : '');
      var ty = norm(typeFilter ? typeFilter.value : '');
      var pg = norm(progFilter ? progFilter.value : '');
      var co = norm(cohortFilter ? cohortFilter.value : '');
      var pv = norm(provFilter ? provFilter.value : '');
      var od = norm(openFilter ? openFilter.value : '');
      var cd = norm(closeFilter ? closeFilter.value : '');

      var visible = 0;

      cards.forEach(function (card) {
        var title = norm(card.getAttribute('data-title'));
        var programme = norm(card.getAttribute('data-programme'));
        var progId = norm(card.getAttribute('data-programme-id'));
        var cohort = norm(card.getAttribute('data-cohort'));
        var cohortId = norm(card.getAttribute('data-cohort-id'));
        var skills = norm(card.getAttribute('data-skills'));
        var location = norm(card.getAttribute('data-location'));
        var type = norm(card.getAttribute('data-type'));
        var province = norm(card.getAttribute('data-province'));
        var status = norm(card.getAttribute('data-status'));
        var open = norm(card.getAttribute('data-open'));
        var close = norm(card.getAttribute('data-close'));

        var show = true;

        // Full-text search across title, programme, cohort, skills, location
        if (q) {
          var haystack = [title, programme, cohort, skills, location].join(' ');
          if (haystack.indexOf(q) === -1) {
            show = false;
          }
        }

        // Status filter
        if (show && st && status !== st) show = false;

        // Type filter
        if (show && ty && type !== ty) show = false;

        // Programme filter (by id)
        if (show && pg && progId !== pg) show = false;

        // Cohort filter (by id)
        if (show && co && cohortId !== co) show = false;

        // Province filter
        if (show && pv && province !== pv) show = false;

        // Opening date filter (>= selected)
        if (show && od && open && open < od) show = false;

        // Closing date filter (<= selected)
        if (show && cd && close && close > cd) show = false;

        card.style.display = show ? '' : 'none';
        if (show) visible++;
      });

      if (countEl) {
        countEl.textContent = visible + (visible === 1 ? ' opportunity found' : ' opportunities found');
      }
    }

    function clearFilters() {
      var ids = [
        'oppSearchInput', 'oppFilterStatus', 'oppFilterType', 'oppFilterProgramme',
        'oppFilterCohort', 'oppFilterProvince', 'oppFilterOpen', 'oppFilterClose'
      ];
      ids.forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.value = '';
      });
      applyFilters();
    }

    if (applyBtn) applyBtn.addEventListener('click', applyFilters);
    if (clearBtn) clearBtn.addEventListener('click', clearFilters);
    if (searchInput) searchInput.addEventListener('input', applyFilters);

    // Initial count
    applyFilters();
  }

  /* ------------------------------------------------------------
     Dynamic cohort dropdown (create/edit forms)
     ------------------------------------------------------------ */
  function initCohortLoader() {
    var programmeSelect = document.getElementById('programme_id');
    var cohortSelect = document.getElementById('cohort_id');
    var dateHint = document.getElementById('cohortDateHint');

    if (!programmeSelect || !cohortSelect) return;

    var baseUrl = window.APP_URL || '';
    var currentSelected = cohortSelect.getAttribute('data-current-cohort')
      || cohortSelect.querySelector('option[selected]');

    function setHint(text) {
      if (!dateHint) return;
      if (!text) {
        dateHint.style.display = 'none';
        dateHint.textContent = '';
        return;
      }
      dateHint.textContent = text;
      dateHint.style.display = 'block';
    }

    function loadCohorts(programmeId, preserveSelected) {
      cohortSelect.innerHTML = '<option value="">No cohort (programme-wide)</option>';
      setHint('');

      if (!programmeId) {
        return;
      }

      var url = baseUrl + '/admin/ajax_cohorts.php?programme_id=' + encodeURIComponent(programmeId);
      fetch(url)
        .then(function (res) { return res.json(); })
        .then(function (data) {
          var cohorts = (data && data.cohorts) || [];
          if (cohorts.length === 0) {
            cohortSelect.innerHTML = '<option value="">No cohorts available</option>';
            setHint('This programme does not have any available cohorts yet.');
            return;
          }
          cohorts.forEach(function (c) {
            var opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = c.name;
            opt.setAttribute('data-open', c.application_open_date || '');
            opt.setAttribute('data-close', c.application_close_date || '');
            opt.setAttribute('data-start', c.start_date || '');
            opt.setAttribute('data-end', c.end_date || '');
            cohortSelect.appendChild(opt);
          });

          // Preserve the currently selected cohort (edit form)
          if (preserveSelected && currentSelected) {
            cohortSelect.value = currentSelected;
          }
        })
        .catch(function () {
          setHint('Could not load cohorts for this programme. Please refresh and try again.');
        });
    }

    // Initial load if a programme is pre-selected
    if (programmeSelect.value) {
      loadCohorts(programmeSelect.value, true);
    }

    programmeSelect.addEventListener('change', function () {
      loadCohorts(programmeSelect.value, false);
    });

    // Show cohort application date hint when a cohort is selected
    cohortSelect.addEventListener('change', function () {
      var opt = cohortSelect.options[cohortSelect.selectedIndex];
      if (!opt || !opt.value) {
        setHint('');
        return;
      }
      var open = opt.getAttribute('data-open');
      var close = opt.getAttribute('data-close');
      if (close) {
        setHint('Selected cohort application close date: ' + close + (open ? ' (open ' + open + ')' : '') + '.');
      } else {
        setHint('');
      }
    });
  }

  /* ------------------------------------------------------------
     Document row add/remove (application information)
     ------------------------------------------------------------ */
  function initDocRows() {
    var container = document.getElementById('oppDocRows');
    var addBtn = document.getElementById('oppAddDocRow');
    if (!container || !addBtn) return;

    addBtn.addEventListener('click', function () {
      var rows = container.querySelectorAll('.opp-doc-row');
      var idx = rows.length;

      var row = document.createElement('div');
      row.className = 'pm-doc-item opp-doc-row';
      row.innerHTML =
        '<span class="pm-doc-item__icon"><i class="fas fa-file-alt"></i></span>' +
        '<div class="pm-doc-item__info">' +
          '<input type="text" name="document_name[]" class="form-input" placeholder="Document name">' +
          '<div class="pm-doc-item__tags">' +
            '<label class="pm-toggle"><input type="radio" name="document_required[' + idx + ']" value="1" checked><span class="pm-toggle__box"></span> Required</label>' +
            '<label class="pm-toggle"><input type="radio" name="document_required[' + idx + ']" value="0"><span class="pm-toggle__box"></span> Optional</label>' +
          '</div>' +
        '</div>' +
        '<button type="button" class="btn btn--ghost btn--sm opp-doc-remove" aria-label="Remove"><i class="fas fa-times"></i></button>';

      container.appendChild(row);
      row.querySelector('.opp-doc-remove').addEventListener('click', function () {
        row.remove();
      });
    });

    // Handle removal of existing/pre-rendered rows
    container.addEventListener('click', function (e) {
      var removeBtn = e.target.closest('.opp-doc-remove');
      if (removeBtn) {
        var row = removeBtn.closest('.opp-doc-row');
        if (row) row.remove();
      }
    });
  }

  /* ------------------------------------------------------------
     Three-dot "more" action menus (reuse programme module markup)
     ------------------------------------------------------------ */
  function initMoreMenus() {
    document.addEventListener('click', function (e) {
      var moreBtn = e.target.closest('.pm-more-btn');
      if (moreBtn) {
        e.stopPropagation();
        var menu = moreBtn.parentElement.querySelector('.pm-card__menu');
        if (!menu) return;
        var isOpen = menu.classList.contains('is-open');
        document.querySelectorAll('.pm-card__menu.is-open').forEach(function (m) {
          m.classList.remove('is-open');
        });
        if (!isOpen) menu.classList.add('is-open');
        return;
      }

      // Close all open menus when clicking elsewhere
      document.querySelectorAll('.pm-card__menu.is-open').forEach(function (m) {
        m.classList.remove('is-open');
      });
    });
  }
})();
