/**
 * ================================================
 * INVESTHOOD IT - Opportunities Module JavaScript
 * ================================================
 * Handles filter interactions, sorting, saving,
 * and responsive behavior for opportunities.
 */

document.addEventListener('DOMContentLoaded', function() {
  initializeOpportunities();
});

function initializeOpportunities() {
  // Sidebar toggle
  const sidebarToggle = document.getElementById('sidebarToggle');
  const sidebarClose = document.getElementById('sidebarClose');
  const sidebar = document.getElementById('sidebar');
  const sidebarOverlay = document.getElementById('sidebarOverlay');

  if (sidebarToggle) {
    sidebarToggle.addEventListener('click', function() {
      sidebar.classList.toggle('is-open');
      sidebarOverlay.classList.toggle('is-visible');
    });
  }

  if (sidebarClose) {
    sidebarClose.addEventListener('click', closeSidebar);
  }

  if (sidebarOverlay) {
    sidebarOverlay.addEventListener('click', closeSidebar);
  }

  // Filters panel
  const toggleFiltersBtn = document.getElementById('toggleFilters');
  const filtersPanel = document.getElementById('filtersPanel');
  const closeFiltersBtn = document.getElementById('closeFilters');
  const filtersForm = document.getElementById('filtersForm');

  if (toggleFiltersBtn) {
    toggleFiltersBtn.addEventListener('click', function() {
      if (filtersPanel) {
        filtersPanel.classList.toggle('is-open');
      }
    });
  }

  if (closeFiltersBtn) {
    closeFiltersBtn.addEventListener('click', function() {
      if (filtersPanel) {
        filtersPanel.classList.toggle('is-open');
      }
    });
  }

  // Filters form submission
  if (filtersForm) {
    filtersForm.addEventListener('submit', function(e) {
      e.preventDefault();
      applyFilters();
    });
  }

  // Clear filters
  const resetButtons = document.querySelectorAll('button[type="reset"]');
  resetButtons.forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      clearFilters();
    });
  });

  // Sort selection
  const sortSelect = document.getElementById('sortSelect');
  if (sortSelect) {
    sortSelect.addEventListener('change', function() {
      const form = document.querySelector('.opp-search-form');
      if (form) {
        const sortInput = form.querySelector('input[name="sort"]');
        if (sortInput) {
          sortInput.value = this.value;
          form.submit();
        }
      }
    });
  }

  // Save opportunity buttons (list page)
  const saveButtons = document.querySelectorAll('.opp-card__save-btn');
  saveButtons.forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      handleSaveOpportunity(this);
    });
  });

  // Save button on detail page
  const saveBtn = document.getElementById('saveBtn');
  if (saveBtn) {
    saveBtn.addEventListener('click', function(e) {
      e.preventDefault();
      handleSaveOpportunity(this);
    });
  }

  // Theme toggle
  const themeToggle = document.getElementById('themeToggle');
  if (themeToggle) {
    themeToggle.addEventListener('click', toggleTheme);
  }

  // Close mobile filters when clicking outside
  if (filtersPanel && window.innerWidth <= 1024) {
    document.addEventListener('click', function(e) {
      if (!filtersPanel.contains(e.target) && !toggleFiltersBtn.contains(e.target)) {
        if (filtersPanel.classList.contains('is-open')) {
          filtersPanel.classList.remove('is-open');
        }
      }
    });
  }
}

function closeSidebar() {
  const sidebar = document.getElementById('sidebar');
  const sidebarOverlay = document.getElementById('sidebarOverlay');
  if (sidebar) sidebar.classList.remove('is-open');
  if (sidebarOverlay) sidebarOverlay.classList.remove('is-visible');
}

function applyFilters() {
  const form = document.querySelector('.opp-search-form');
  if (!form) return;

  const filtersForm = document.getElementById('filtersForm');
  if (!filtersForm) return;

  // Get selected filters
  const typeCheckboxes = filtersForm.querySelectorAll('input[name="type"]:checked');
  const programmeCheckboxes = filtersForm.querySelectorAll('input[name="programme"]:checked');
  const provinceCheckboxes = filtersForm.querySelectorAll('input[name="province"]:checked');
  const cityCheckboxes = filtersForm.querySelectorAll('input[name="city"]:checked');
  const arrangementCheckboxes = filtersForm.querySelectorAll('input[name="work_arrangement"]:checked');

  // Update hidden inputs in search form
  const typeInput = form.querySelector('input[name="type"]');
  const programmeInput = form.querySelector('input[name="programme"]');
  const provinceInput = form.querySelector('input[name="province"]');
  const cityInput = form.querySelector('input[name="city"]');
  const arrangementInput = form.querySelector('input[name="work_arrangement"]');

  if (typeInput) {
    typeInput.value = Array.from(typeCheckboxes).map(c => c.value).join(',');
  }
  if (programmeInput) {
    programmeInput.value = Array.from(programmeCheckboxes).map(c => c.value).join(',');
  }
  if (provinceInput) {
    provinceInput.value = Array.from(provinceCheckboxes).map(c => c.value).join(',');
  }
  if (cityInput) {
    cityInput.value = Array.from(cityCheckboxes).map(c => c.value).join(',');
  }
  if (arrangementInput) {
    arrangementInput.value = Array.from(arrangementCheckboxes).map(c => c.value).join(',');
  }

  // Reset to page 1
  const pageInput = form.querySelector('input[name="page"]');
  if (pageInput) {
    pageInput.value = '1';
  }

  // Submit the search form
  form.submit();
}

function clearFilters() {
  // Reset all checkboxes
  const filtersForm = document.getElementById('filtersForm');
  if (filtersForm) {
    filtersForm.reset();
  }

  // Clear all hidden inputs in search form
  const form = document.querySelector('.opp-search-form');
  if (form) {
    form.querySelector('input[name="type"]').value = '';
    form.querySelector('input[name="programme"]').value = '';
    form.querySelector('input[name="province"]').value = '';
    form.querySelector('input[name="city"]').value = '';
    form.querySelector('input[name="work_arrangement"]').value = '';
    form.querySelector('input[name="page"]').value = '1';
    form.submit();
  }
}

function handleSaveOpportunity(btn) {
  const oppId = btn.getAttribute('data-opp-id');
  const isSaved = btn.getAttribute('data-saved') === '1';
  const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

  const endpoint = isSaved ? '/candidate/api/unsave_opportunity.php' : '/candidate/api/save_opportunity.php';

  fetch(window.APP_URL + endpoint, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': csrfToken,
    },
    body: JSON.stringify({
      opportunity_id: oppId,
    }),
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // Toggle button state
      const isSavedNow = data.saved;
      btn.setAttribute('data-saved', isSavedNow ? '1' : '0');

      if (btn.classList.contains('opp-card__save-btn')) {
        // List page button
        btn.classList.toggle('is-saved');
      } else if (btn.classList.contains('opp-detail__save-btn')) {
        // Detail page button
        btn.classList.toggle('is-saved');
        btn.innerHTML = (isSavedNow ? '<i class="fas fa-bookmark"></i> Saved' : '<i class="fas fa-bookmark"></i> Save');
      }

      // Show success message
      showNotification(
        isSavedNow ? 'Opportunity saved!' : 'Opportunity removed from saved',
        'success'
      );
    } else {
      showNotification(data.message || 'An error occurred', 'error');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    showNotification('Unable to save opportunity. Please try again.', 'error');
  });
}

function showNotification(message, type = 'info') {
  // Create notification element
  const notification = document.createElement('div');
  notification.className = `notification notification--${type}`;
  notification.innerHTML = `
    <div class="notification__content">
      <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
      <span>${message}</span>
    </div>
    <button class="notification__close" aria-label="Close"><i class="fas fa-times"></i></button>
  `;

  document.body.appendChild(notification);

  // Auto-remove after 5 seconds
  const timeout = setTimeout(() => {
    notification.remove();
  }, 5000);

  // Remove on close button click
  const closeBtn = notification.querySelector('.notification__close');
  if (closeBtn) {
    closeBtn.addEventListener('click', () => {
      clearTimeout(timeout);
      notification.remove();
    });
  }
}

function toggleTheme() {
  const body = document.body;
  const isDarkMode = body.classList.contains('dark-mode');

  if (isDarkMode) {
    body.classList.remove('dark-mode');
    localStorage.setItem('theme', 'light');
  } else {
    body.classList.add('dark-mode');
    localStorage.setItem('theme', 'dark');
  }
}

// Restore theme on page load
window.addEventListener('load', function() {
  const theme = localStorage.getItem('theme') || 'light';
  if (theme === 'dark') {
    document.body.classList.add('dark-mode');
  }
});

// Handle responsive filter panel
window.addEventListener('resize', function() {
  const filtersPanel = document.getElementById('filtersPanel');
  if (filtersPanel && window.innerWidth > 1024) {
    filtersPanel.classList.remove('is-open');
  }
});
