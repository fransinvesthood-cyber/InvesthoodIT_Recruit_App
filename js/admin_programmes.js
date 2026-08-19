/**
 * ================================================
 * INVESTHOOD IT - Programme & Cohort Management JS
 * ================================================
 * Powers interactive behaviour for the Programme &
 * Cohort Management module:
 *   - Tabbed workspaces
 *   - Search & filtering on the programme list
 *   - Three-dot "more" action menus
 *   - Dynamic skill row add/remove
 *   - Hash-based tab navigation
 */

(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    initTabs();
    initProgrammeFilters();
    initMoreMenus();
    initSkillRows();
    initHashNavigation();
  });

  /* ------------------------------------------------------------
     Tabs (programme detail / cohort detail workspaces)
     ------------------------------------------------------------ */
  function initTabs() {
    var tabNavs = document.querySelectorAll('.pm-tabs');

    tabNavs.forEach(function (nav) {
      var tabs = nav.querySelectorAll('.pm-tab');

      tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
          var tabName = tab.getAttribute('data-tab');
          activateTab(nav, tabName);
          // Update URL hash without full reload
          if (history.replaceState) {
            history.replaceState(null, '', '#' + tabName);
          }
        });
      });
    });
  }

  function activateTab(nav, tabName) {
    // Deactivate all tabs in this nav
    nav.querySelectorAll('.pm-tab').forEach(function (t) {
      t.classList.remove('active');
    });

    // Activate the clicked tab
    var activeTab = nav.querySelector('.pm-tab[data-tab="' + tabName + '"]');
    if (activeTab) {
      activeTab.classList.add('active');
    }

    // Find the closest workspace to scope panels
    var workspace = nav.closest('.pm-workspace');
    if (!workspace) return;

    // Toggle panels
    workspace.querySelectorAll('.pm-tab-panel').forEach(function (panel) {
      panel.classList.remove('is-active');
    });
    var targetPanel = workspace.querySelector('.pm-tab-panel[data-tab="' + tabName + '"]');
    if (targetPanel) {
      targetPanel.id = 'tab-' + tabName;
      targetPanel.classList.add('is-active');
    }

    // Special case: panels are keyed by id (tab-overview, tab-cohorts, etc.)
    var panelById = workspace.querySelector('#tab-' + tabName);
    if (panelById) {
      panelById.classList.add('is-active');
    }
  }

  /* ------------------------------------------------------------
     Hash-based navigation (e.g. #cohorts, #eligibility, #documents)
     ------------------------------------------------------------ */
  function initHashNavigation() {
    var hash = window.location.hash.replace('#', '');
    if (!hash) return;

    // Depth-first: find the workspace containing a matching panel
    var workspaces = document.querySelectorAll('.pm-workspace');
    workspaces.forEach(function (ws) {
      var panel = ws.querySelector('#tab-' + hash);
      var nav = ws.querySelector('.pm-tabs');
      if (panel && nav) {
        activateTab(nav, hash);
      }
    });
  }

  /* ------------------------------------------------------------
     Programme list search & filtering
     ------------------------------------------------------------ */
  function initProgrammeFilters() {
    var searchInput = document.getElementById('pmSearchInput');
    var statusFilter = document.getElementById('pmFilterStatus');
    var typeFilter = document.getElementById('pmFilterType');
    var startFilter = document.getElementById('pmFilterStart');
    var endFilter = document.getElementById('pmFilterEnd');
    var applyBtn = document.getElementById('pmApplyFilters');
    var clearBtn = document.getElementById('pmClearFilters');
    var countEl = document.getElementById('pmResultsCount');
    var list = document.getElementById('pmList');

    if (!list) return;

    var cards = list.querySelectorAll('.pm-card');

    function applyFilters() {
      var q = (searchInput ? searchInput.value : '').trim().toLowerCase();
      var st = statusFilter ? statusFilter.value : '';
      var ty = typeFilter ? typeFilter.value : '';
      var sd = startFilter ? startFilter.value : '';
      var ed = endFilter ? endFilter.value : '';

      var visible = 0;

      cards.forEach(function (card) {
        var name = (card.getAttribute('data-name') || '').toLowerCase();
        var type = (card.getAttribute('data-type') || '').toLowerCase();
        var desc = (card.getAttribute('data-description') || '').toLowerCase();
        var status = card.getAttribute('data-status') || '';
        var start = card.getAttribute('data-start') || '';
        var end = card.getAttribute('data-end') || '';

        var show = true;

        // Search across name, type, description
        if (q && name.indexOf(q) === -1 && type.indexOf(q) === -1 && desc.indexOf(q) === -1) {
          show = false;
        }

        // Status filter
        if (show && st && status !== st) {
          show = false;
        }

        // Type filter
        if (show && ty && type !== ty) {
          show = false;
        }

        // Start date filter (>= selected)
        if (show && sd && start && start < sd) {
          show = false;
        }

        // End date filter (<= selected)
        if (show && ed && end && end > ed) {
          show = false;
        }

        card.style.display = show ? '' : 'none';
        if (show) visible++;
      });

      // Update results count
      if (countEl) {
        countEl.textContent = visible + (visible === 1 ? ' programme found' : ' programmes found');
      }
    }

    function clearFilters() {
      if (searchInput) searchInput.value = '';
      if (statusFilter) statusFilter.value = '';
      if (typeFilter) typeFilter.value = '';
      if (startFilter) startFilter.value = '';
      if (endFilter) endFilter.value = '';
      applyFilters();
    }

    if (applyBtn) applyBtn.addEventListener('click', applyFilters);
    if (clearBtn) clearBtn.addEventListener('click', clearFilters);

    // Live search on input if present
    if (searchInput) {
      searchInput.addEventListener('input', applyFilters);
    }

    // Initial count
    applyFilters();
  }

  /* ------------------------------------------------------------
     Three-dot "more" action menus
     ------------------------------------------------------------ */
  function initMoreMenus() {
    var moreBtns = document.querySelectorAll('.pm-more-btn');

    moreBtns.forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var menu = btn.parentElement.querySelector('.pm-card__menu');
        if (!menu) return;

        var isOpen = menu.classList.contains('is-open');

        // Close all open menus
        document.querySelectorAll('.pm-card__menu.is-open').forEach(function (m) {
          m.classList.remove('is-open');
        });

        if (!isOpen) {
          menu.classList.add('is-open');
        }
      });
    });

    // Close menus when clicking outside
    document.addEventListener('click', function () {
      document.querySelectorAll('.pm-card__menu.is-open').forEach(function (m) {
        m.classList.remove('is-open');
      });
    });
  }

  /* ------------------------------------------------------------
     Skill row add/remove (cohort detail)
     ------------------------------------------------------------ */
  function initSkillRows() {
    // Add skill
    document.querySelectorAll('.pm-skill-add').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var cat = btn.getAttribute('data-cat');
        var container = btn.closest('.pm-skills-cat');
        if (!container || !cat) return;

        var template = container.querySelector('.pm-skill-row--template[data-cat="' + cat + '"]');
        if (!template) return;

        var clone = template.cloneNode(true);
        clone.style.display = 'flex';
        clone.classList.remove('pm-skill-row--template');
        clone.removeAttribute('style');

        // Insert before the add button
        container.insertBefore(clone, btn);
      });
    });

    // Remove skill (event delegation)
    document.addEventListener('click', function (e) {
      var removeBtn = e.target.closest('.pm-skill-remove');
      if (!removeBtn) return;

      var row = removeBtn.closest('.pm-skill-row');
      if (row && !row.classList.contains('pm-skill-row--template')) {
        row.remove();
      }
    });
  }
})();
