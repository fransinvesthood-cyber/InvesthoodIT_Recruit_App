/* ================================================
   INVESTHOOD IT - Admin Dashboard JavaScript
   ================================================ */

'use strict';

document.addEventListener('DOMContentLoaded', function () {

  var adminContent = document.getElementById('adminDashContent');
  if (!adminContent) return; // Not an admin dashboard page

  // ============================================
  // 1. SIDEBAR TOGGLE & NAVIGATION
  // ============================================
  var sidebar = document.getElementById('adminSidebar');
  var sidebarToggle = document.getElementById('sidebarToggle');
  var sidebarClose = document.getElementById('sidebarClose');
  var sidebarOverlay = document.getElementById('sidebarOverlay');

function openSidebar() {
    if (sidebar) sidebar.classList.add('open');
    if (sidebarOverlay) sidebarOverlay.classList.add('open');
    document.body.style.overflow = 'hidden';
    document.body.classList.add('sidebar-open');
  }

  function closeSidebar() {
    if (sidebar) sidebar.classList.remove('open');
    if (sidebarOverlay) sidebarOverlay.classList.remove('open');
    document.body.style.overflow = '';
    document.body.classList.remove('sidebar-open');
  }

  if (sidebarToggle) sidebarToggle.addEventListener('click', openSidebar);
  if (sidebarClose) sidebarClose.addEventListener('click', closeSidebar);
  if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && sidebar && sidebar.classList.contains('open')) closeSidebar();
  });

  // Sidebar active link highlight on scroll
  var sidebarLinks = document.querySelectorAll('.sidebar__link');
  var adminSections = document.querySelectorAll('.admin-section');

  function highlightAdminNav() {
    var scrollPos = window.scrollY + 100;
    var currentId = '';
    adminSections.forEach(function (sec) {
      var top = sec.offsetTop;
      var height = sec.offsetHeight;
      if (scrollPos >= top && scrollPos < top + height) currentId = sec.getAttribute('id');
    });
    sidebarLinks.forEach(function (link) {
      link.classList.remove('active');
      if (link.getAttribute('href') === '#' + currentId) link.classList.add('active');
    });
  }
  window.addEventListener('scroll', highlightAdminNav, { passive: true });

// Sidebar link smooth scroll
  // Only intercept hash links (#...) for smooth scrolling. Real URL links
  // (e.g. admin/programmes.php) must be allowed to navigate normally.
  sidebarLinks.forEach(function (link) {
    link.addEventListener('click', function (e) {
      var targetId = this.getAttribute('href');
      if (targetId && targetId.startsWith('#')) {
        e.preventDefault();
        var target = document.querySelector(targetId);
        if (target) {
          closeSidebar();
          var offset = 80;
          var pos = target.getBoundingClientRect().top + window.scrollY - offset;
          window.scrollTo({ top: pos, behavior: 'smooth' });
          sidebarLinks.forEach(function (l) { l.classList.remove('active'); });
          this.classList.add('active');
        }
      }
    });
  });

  // ============================================
  // 2. DARK MODE TOGGLE
  // ============================================
  var themeToggle = document.getElementById('themeToggle');
  if (themeToggle) {
    themeToggle.addEventListener('click', function () {
      document.body.classList.toggle('dark-mode');
      var icon = this.querySelector('i');
      if (document.body.classList.contains('dark-mode')) {
        icon.className = 'fas fa-sun';
      } else {
        icon.className = 'fas fa-moon';
      }
    });
  }

  // ============================================
  // 3. OVERVIEW COUNTER ANIMATION
  // ============================================
  function animateNumber(el) {
    var target = parseInt(el.getAttribute('data-count'), 10);
    if (isNaN(target)) return;
    var duration = 1500, start = performance.now();
    function update(time) {
      var p = Math.min((time - start) / duration, 1);
      var eased = 1 - Math.pow(1 - p, 3);
      el.textContent = Math.floor(eased * target);
      if (p < 1) requestAnimationFrame(update);
      else el.textContent = target;
    }
    requestAnimationFrame(update);
  }

  var adminObs = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        animateNumber(entry.target);
        adminObs.unobserve(entry.target);
      }
    });
  }, { threshold: 0.3 });

  document.querySelectorAll('.admin-exec-card__number[data-count], .admin-stat-chip__value[data-count], .admin-analytics-stat__number[data-count]').forEach(function (n) {
    adminObs.observe(n);
  });

  // ============================================
  // 4. ADMIN ALERT DISMISS
  // ============================================
  document.querySelectorAll('.admin-alert-card__action').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      var card = this.closest('.admin-alert-card');
      if (card) {
        card.style.transition = 'all 0.3s ease';
        card.style.opacity = '0';
        card.style.transform = 'translateX(30px)';
        setTimeout(function () { if (card.parentNode) card.remove(); }, 300);
      }
    });
  });

  // ============================================
  // 5. ADMIN DASHBOARD SEARCH
  // ============================================
  var adminGlobalSearch = document.getElementById('adminGlobalSearch');
  if (adminGlobalSearch) {
    adminGlobalSearch.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        var q = this.value.trim().toLowerCase();
        if (!q) return;
        // Search through sections
        var found = null;
        adminSections.forEach(function (sec) {
          if (sec.textContent.toLowerCase().indexOf(q) !== -1) {
            found = sec;
          }
        });
        if (found) {
          var offset = 80;
          var pos = found.getBoundingClientRect().top + window.scrollY - offset;
          window.scrollTo({ top: pos, behavior: 'smooth' });
          found.style.transition = 'background 0.5s ease';
          found.style.background = 'rgba(26,86,219,0.05)';
          setTimeout(function () { found.style.background = ''; }, 1500);
        } else if (window.InvesthoodNotifications) {
          window.InvesthoodNotifications.show('info', 'No Results', 'No sections matched "' + q + '". Try a different search term.');
        }
      }
    });
  }

  // ============================================
  // 6. MOBILE: HEADER SEARCH TOGGLE
  // ============================================
  var adminDashSearch = document.getElementById('adminDashSearch');
  if (adminDashSearch) {
    var searchInput = adminDashSearch.querySelector('.dash-header__search-input');
    function toggleSearch(e) {
      if (adminDashSearch.classList.contains('mobile-expanded') && e.target === searchInput) return;
      e.stopPropagation();
      var isExpanded = adminDashSearch.classList.contains('mobile-expanded');
      adminDashSearch.classList.remove('mobile-collapsed', 'mobile-expanded');
      if (!isExpanded) {
        adminDashSearch.classList.add('mobile-expanded');
        setTimeout(function () { if (searchInput) searchInput.focus(); }, 100);
      } else {
        adminDashSearch.classList.add('mobile-collapsed');
      }
    }
    adminDashSearch.addEventListener('click', toggleSearch);
    document.addEventListener('click', function (e) {
      if (adminDashSearch.classList.contains('mobile-expanded') && !adminDashSearch.contains(e.target)) {
        adminDashSearch.classList.remove('mobile-expanded');
        adminDashSearch.classList.add('mobile-collapsed');
      }
    });
  }

  // ============================================
  // 7. HEADER NOTIFICATION BUTTON
  // ============================================
  var adminNotifBtn = document.getElementById('adminNotifBtn');
  if (adminNotifBtn) {
    adminNotifBtn.addEventListener('click', function () {
      var alertsSection = document.getElementById('admin-alerts');
      if (alertsSection) {
        var offset = 80;
        var pos = alertsSection.getBoundingClientRect().top + window.scrollY - offset;
        window.scrollTo({ top: pos, behavior: 'smooth' });
      }
    });
  }

  var adminAlertsBtn = document.getElementById('adminAlertsBtn');
  if (adminAlertsBtn) {
    adminAlertsBtn.addEventListener('click', function () {
      var alertsSection = document.getElementById('admin-alerts');
      if (alertsSection) {
        var offset = 80;
        var pos = alertsSection.getBoundingClientRect().top + window.scrollY - offset;
        window.scrollTo({ top: pos, behavior: 'smooth' });
      }
    });
  }

  // ============================================
  // 8. REPORT CATEGORY FILTER
  // ============================================
  document.querySelectorAll('.admin-report-cat').forEach(function (cat) {
    cat.addEventListener('click', function () {
      document.querySelectorAll('.admin-report-cat').forEach(function (c) { c.classList.remove('active'); });
      this.classList.add('active');
      var reportType = this.getAttribute('data-report');
      if (window.InvesthoodNotifications) {
        window.InvesthoodNotifications.show('info', 'Loading Report', 'Loading ' + this.textContent.trim() + ' report data...');
      }
    });
  });

  // ============================================
  // 9. CHART.JS ANALYTICS INITIALIZATION
  // ============================================
  if (typeof Chart !== 'undefined') {
    var chartDefaults = {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false }
      }
    };

    // Shared colors
    var colors = {
      primary: 'rgba(26,86,219,0.7)',
      primaryBorder: 'rgba(26,86,219,1)',
      secondary: 'rgba(6,182,212,0.7)',
      secondaryBorder: 'rgba(6,182,212,1)',
      accent: 'rgba(245,158,11,0.7)',
      success: 'rgba(16,185,129,0.7)',
      purple: 'rgba(139,92,246,0.7)',
      red: 'rgba(239,68,68,0.7)',
      indigo: 'rgba(99,102,241,0.7)',
      gray: 'rgba(148,163,184,0.5)',
      grid: 'rgba(0,0,0,0.04)'
    };

    // Helper to get computed CSS variable
    function getCSSVar(name, fallback) {
      return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;
    }

    // --- Executive: Candidate Growth (Bar) ---
    var execCandidateCtx = document.getElementById('execCandidateChart');
    if (execCandidateCtx) {
      new Chart(execCandidateCtx, {
        type: 'bar',
        data: {
          labels: ['Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
          datasets: [{
            label: 'New Candidates',
            data: [180, 210, 240, 195, 275, 310],
            backgroundColor: [colors.primary, colors.secondary, colors.accent, colors.success, colors.primary, colors.secondary],
            borderRadius: 3,
            borderSkipped: false
          }]
        },
        options: Object.assign({}, chartDefaults, {
          scales: {
            y: { beginAtZero: true, grid: { color: colors.grid }, ticks: { font: { size: 9 } } },
            x: { grid: { display: false }, ticks: { font: { size: 9 } } }
          }
        })
      });
    }

    // --- Executive: Applications Trend (Line) ---
    var execAppCtx = document.getElementById('execApplicationChart');
    if (execAppCtx) {
      new Chart(execAppCtx, {
        type: 'line',
        data: {
          labels: ['W1', 'W2', 'W3', 'W4', 'W5', 'W6'],
          datasets: [{
            label: 'Applications',
            data: [45, 52, 38, 65, 58, 72],
            borderColor: colors.primaryBorder,
            backgroundColor: 'rgba(26,86,219,0.1)',
            fill: true,
            tension: 0.4,
            pointRadius: 2,
            pointBackgroundColor: colors.primaryBorder
          }]
        },
        options: Object.assign({}, chartDefaults, {
          scales: {
            y: { beginAtZero: true, grid: { color: colors.grid }, ticks: { font: { size: 9 } } },
            x: { grid: { display: false }, ticks: { font: { size: 9 } } }
          }
        })
      });
    }

    // --- Executive: Placement Success (Doughnut) ---
    var execPlaceCtx = document.getElementById('execPlacementChart');
    if (execPlaceCtx) {
      new Chart(execPlaceCtx, {
        type: 'doughnut',
        data: {
          labels: ['Successful', 'In Progress', 'At Risk'],
          datasets: [{
            data: [68, 24, 8],
            backgroundColor: [colors.success, colors.primary, colors.red],
            borderWidth: 0,
            hoverOffset: 4
          }]
        },
        options: Object.assign({}, chartDefaults, {
          cutout: '65%',
          plugins: {
            legend: {
              display: true,
              position: 'bottom',
              labels: { boxWidth: 8, padding: 6, font: { size: 8 } }
            }
          }
        })
      });
    }

    // --- Analytics: Programme Performance (Horizontal Bar) ---
    var progAnalyticsCtx = document.getElementById('analyticsProgrammeChart');
    if (progAnalyticsCtx) {
      new Chart(progAnalyticsCtx, {
        type: 'bar',
        data: {
          labels: ['Software Eng', 'Cloud Comp', 'Cyber Sec', 'Data Sci', 'DevOps', 'BA'],
          datasets: [
            { label: 'Enrolled', data: [120, 85, 65, 95, 55, 45], backgroundColor: colors.primary, borderRadius: 3 },
            { label: 'Completed', data: [95, 55, 40, 70, 30, 35], backgroundColor: colors.success, borderRadius: 3 }
          ]
        },
        options: Object.assign({}, chartDefaults, {
          indexAxis: 'y',
          scales: {
            x: { beginAtZero: true, stacked: false, grid: { color: colors.grid }, ticks: { font: { size: 9 } } },
            y: { grid: { display: false }, ticks: { font: { size: 9 } } }
          },
          plugins: {
            legend: {
              display: true,
              position: 'top',
              labels: { boxWidth: 10, padding: 8, font: { size: 9 } }
            }
          }
        })
      });
    }

    // --- Analytics: Application Stats (Bar) ---
    var appStatsCtx = document.getElementById('analyticsApplicationChart');
    if (appStatsCtx) {
      new Chart(appStatsCtx, {
        type: 'bar',
        data: {
          labels: ['Submitted', 'Review', 'Assessment', 'Interview', 'Selected', 'Rejected'],
          datasets: [{
            label: 'Applications',
            data: [156, 89, 42, 28, 18, 24],
            backgroundColor: [colors.primary, colors.secondary, colors.accent, colors.success, colors.green, colors.red],
            borderRadius: 3
          }]
        },
        options: Object.assign({}, chartDefaults, {
          scales: {
            y: { beginAtZero: true, grid: { color: colors.grid }, ticks: { font: { size: 9 } } },
            x: { grid: { display: false }, ticks: { font: { size: 8 }, maxRotation: 45 } }
          }
        })
      });
    }

    // --- Analytics: Placement Stats (Doughnut) ---
    var placementStatsCtx = document.getElementById('analyticsPlacementChart');
    if (placementStatsCtx) {
      new Chart(placementStatsCtx, {
        type: 'doughnut',
        data: {
          labels: ['Active', 'Completed', 'At Risk', 'Upcoming'],
          datasets: [{
            data: [248, 1056, 18, 64],
            backgroundColor: [colors.primary, colors.success, colors.red, colors.accent],
            borderWidth: 0
          }]
        },
        options: Object.assign({}, chartDefaults, {
          cutout: '60%',
          plugins: {
            legend: {
              display: true,
              position: 'bottom',
              labels: { boxWidth: 8, padding: 6, font: { size: 8 } }
            }
          }
        })
      });
    }

    // --- Analytics: Talent Pool (Radar) ---
    var talentCtx = document.getElementById('analyticsTalentChart');
    if (talentCtx) {
      new Chart(talentCtx, {
        type: 'radar',
        data: {
          labels: ['Software', 'Cloud', 'Cyber', 'Data', 'DevOps', 'AI/ML'],
          datasets: [
            {
              label: 'Available Talent',
              data: [420, 280, 185, 320, 160, 140],
              backgroundColor: 'rgba(26,86,219,0.15)',
              borderColor: colors.primaryBorder,
              borderWidth: 2,
              pointBackgroundColor: colors.primaryBorder
            }
          ]
        },
        options: Object.assign({}, chartDefaults, {
          scales: {
            r: {
              beginAtZero: true,
              ticks: { font: { size: 8 }, backdropColor: 'transparent' },
              grid: { color: colors.grid }
            }
          },
          plugins: {
            legend: { display: true, position: 'bottom', labels: { boxWidth: 8, font: { size: 8 } } }
          }
        })
      });
    }

    // --- Analytics: Skills Supply & Demand (Bar) ---
    var skillsCtx = document.getElementById('analyticsSkillsChart');
    if (skillsCtx) {
      new Chart(skillsCtx, {
        type: 'bar',
        data: {
          labels: ['JavaScript', 'Python', 'AWS', 'React', 'Node.js', 'SQL', 'Docker'],
          datasets: [
            { label: 'Supply', data: [450, 380, 210, 320, 280, 500, 150], backgroundColor: colors.primary, borderRadius: 3 },
            { label: 'Demand', data: [520, 410, 380, 360, 310, 480, 290], backgroundColor: colors.accent, borderRadius: 3 }
          ]
        },
        options: Object.assign({}, chartDefaults, {
          scales: {
            y: { beginAtZero: true, grid: { color: colors.grid }, ticks: { font: { size: 9 } } },
            x: { grid: { display: false }, ticks: { font: { size: 8 }, maxRotation: 45 } }
          },
          plugins: {
            legend: { display: true, position: 'top', labels: { boxWidth: 10, padding: 8, font: { size: 9 } } }
          }
        })
      });
    }

    // --- Analytics: Attendance (Line) ---
    var attendanceCtx = document.getElementById('analyticsAttendanceChart');
    if (attendanceCtx) {
      new Chart(attendanceCtx, {
        type: 'line',
        data: {
          labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
          datasets: [{
            label: 'Attendance %',
            data: [94, 91, 96, 89, 93],
            borderColor: colors.success,
            backgroundColor: 'rgba(16,185,129,0.1)',
            fill: true,
            tension: 0.4,
            pointRadius: 3,
            pointBackgroundColor: colors.success
          }]
        },
        options: Object.assign({}, chartDefaults, {
          scales: {
            y: { min: 80, max: 100, grid: { color: colors.grid }, ticks: { font: { size: 9 } } },
            x: { grid: { display: false }, ticks: { font: { size: 9 } } }
          }
        })
      });
    }

    // --- Analytics: Candidate Growth (Bar) ---
    var growthCtx = document.getElementById('analyticsGrowthChart');
    if (growthCtx) {
      new Chart(growthCtx, {
        type: 'bar',
        data: {
          labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
          datasets: [{
            label: 'New Candidates',
            data: [120, 145, 168, 190, 210, 245],
            backgroundColor: colors.secondary,
            borderRadius: 3
          }]
        },
        options: Object.assign({}, chartDefaults, {
          scales: {
            y: { beginAtZero: true, grid: { color: colors.grid }, ticks: { font: { size: 9 } } },
            x: { grid: { display: false }, ticks: { font: { size: 9 } } }
          }
        })
      });
    }

    // --- Analytics: Learning & Assessment (Doughnut) ---
    var learningCtx = document.getElementById('analyticsLearningChart');
    if (learningCtx) {
      new Chart(learningCtx, {
        type: 'doughnut',
        data: {
          labels: ['Completed', 'In Progress', 'Not Started'],
          datasets: [{
            data: [35, 45, 20],
            backgroundColor: [colors.success, colors.primary, colors.gray],
            borderWidth: 0
          }]
        },
        options: Object.assign({}, chartDefaults, {
          cutout: '65%',
          plugins: {
            legend: { display: true, position: 'bottom', labels: { boxWidth: 8, padding: 6, font: { size: 8 } } }
          }
        })
      });
    }

    // --- Analytics: Employment Outcomes (Bar) ---
    var outcomeCtx = document.getElementById('analyticsOutcomeChart');
    if (outcomeCtx) {
      new Chart(outcomeCtx, {
        type: 'bar',
        data: {
          labels: ['Employed', 'Studying', 'Seeking', 'Other'],
          datasets: [{
            label: 'Outcomes',
            data: [65, 18, 12, 5],
            backgroundColor: [colors.success, colors.primary, colors.accent, colors.gray],
            borderRadius: 3
          }]
        },
        options: Object.assign({}, chartDefaults, {
          scales: {
            y: { beginAtZero: true, max: 100, grid: { color: colors.grid }, ticks: { font: { size: 9 }, callback: function(v) { return v + '%'; } } },
            x: { grid: { display: false }, ticks: { font: { size: 9 } } }
          }
        })
      });
    }

    // --- Analytics: Recruitment Funnel (Horizontal Bar) ---
    var funnelCtx = document.getElementById('analyticsFunnelChart');
    if (funnelCtx) {
      new Chart(funnelCtx, {
        type: 'bar',
        data: {
          labels: ['Applications', 'Screening', 'Assessment', 'Interview', 'Offer', 'Hired'],
          datasets: [{
            label: 'Funnel',
            data: [892, 520, 310, 185, 68, 42],
            backgroundColor: [
              'rgba(26,86,219,0.9)',
              'rgba(26,86,219,0.75)',
              'rgba(26,86,219,0.6)',
              'rgba(26,86,219,0.45)',
              'rgba(26,86,219,0.3)',
              'rgba(26,86,219,0.15)'
            ],
            borderRadius: 3
          }]
        },
        options: Object.assign({}, chartDefaults, {
          indexAxis: 'y',
          scales: {
            x: { beginAtZero: true, grid: { color: colors.grid }, ticks: { font: { size: 8 } } },
            y: { grid: { display: false }, ticks: { font: { size: 8 } } }
          },
          plugins: {
            legend: { display: false }
          }
        })
      });
    }

  } // end Chart check

// ============================================
  // 10. PROGRAMME MANAGEMENT (server-rendered cards)
  // ============================================
  // The programme cards are rendered server-side from real DB data.
  // This provides a lightweight client-side filter over those cards.
  var adminProgGrid = document.getElementById('adminProgGrid');

  // Expose a global filter so the inline oninput handler can call it.
  window.filterDashProgrammes = function (query) {
    if (!adminProgGrid) return;
    var q = (query || '').toString().toLowerCase().trim();
    var cards = adminProgGrid.querySelectorAll('.admin-programme-card[data-search]');
    var visible = 0;
    cards.forEach(function (card) {
      var haystack = (card.getAttribute('data-search') || '').toLowerCase();
      var match = !q || haystack.indexOf(q) !== -1;
      card.style.display = match ? '' : 'none';
      if (match) visible++;
    });
    // Show/hide an empty state message if no cards match.
    var empty = adminProgGrid.querySelector('.admin-empty-state');
    if (empty) {
      empty.style.display = (visible === 0 && cards.length > 0) ? '' : 'none';
    }
    if (cards.length === 0 && empty) {
      empty.style.display = '';
    }
  };

  var progSearchInput = document.getElementById('progSearchInput');
  if (progSearchInput) {
    progSearchInput.addEventListener('input', function () {
      window.filterDashProgrammes(this.value);
    });
  }

// ============================================
  // 11. OPPORTUNITY MANAGEMENT (server-rendered cards)
  // ============================================
  // The opportunity cards are rendered server-side from real DB data.
  // This provides a lightweight client-side filter over those cards.
  var adminOppGrid = document.getElementById('adminOppGrid');

  // Expose a global filter so the inline oninput handler can call it.
  window.filterDashOpportunities = function (query) {
    if (!adminOppGrid) return;
    var q = (query || '').toString().toLowerCase().trim();
    var cards = adminOppGrid.querySelectorAll('.admin-opp-card[data-search]');
    var visible = 0;
    cards.forEach(function (card) {
      var haystack = (card.getAttribute('data-search') || '').toLowerCase();
      var match = !q || haystack.indexOf(q) !== -1;
      card.style.display = match ? '' : 'none';
      if (match) visible++;
    });
    // Show/hide an empty state message if no cards match.
    var empty = adminOppGrid.querySelector('.admin-empty-state');
    if (empty) {
      empty.style.display = (visible === 0 && cards.length > 0) ? '' : 'none';
    }
    if (cards.length === 0 && empty) {
      empty.style.display = '';
    }
  };

  var adminOppSearch = document.getElementById('adminOppSearch');
  if (adminOppSearch) {
    adminOppSearch.addEventListener('input', function () {
      window.filterDashOpportunities(this.value);
    });
  }

  // ============================================
  // 12. APPLICATION TABLE DATA & RENDER
  // ============================================
  var adminAppTable = document.getElementById('adminAppTable');
  var applications = [
    { id: '#APP-001', name: 'John Doe', position: 'Junior Software Developer', date: '15 May 2025', status: 'Interview', score: 88, stage: 'interview' },
    { id: '#APP-002', name: 'Priya Singh', position: 'Cloud Engineering Intern', date: '10 Apr 2025', status: 'Screening', score: 75, stage: 'review' },
    { id: '#APP-003', name: 'Thabo Molefe', position: 'Data Analytics Graduate', date: '20 Mar 2025', status: 'Selected', score: 94, stage: 'selected' },
    { id: '#APP-004', name: 'Sarah Nkosi', position: 'IT Support Learnership', date: '05 Jun 2025', status: 'Submitted', score: 68, stage: 'submitted' },
    { id: '#APP-005', name: 'Mike Johnson', position: 'Software Dev Graduate', date: '12 Jun 2025', status: 'Assessment', score: 82, stage: 'assessment' },
    { id: '#APP-006', name: 'Lebohang Mokoena', position: 'Data Analytics Graduate', date: '18 May 2025', status: 'Waitlisted', score: 72, stage: 'waitlisted' }
  ];

  function renderAppTable(data) {
    if (!adminAppTable) return;
    if (!data.length) {
      adminAppTable.innerHTML = '<div style="padding:2rem;text-align:center;color:var(--text-light);">No applications found.</div>';
      return;
    }
    adminAppTable.innerHTML = '<table class="admin-table" style="width:100%;border-collapse:collapse;min-width:700px;">' +
      '<thead><tr style="border-bottom:1px solid var(--border);background:var(--bg);">' +
        '<th style="padding:0.75rem 1rem;font-size:0.75rem;font-weight:700;color:var(--text-lighter);text-transform:uppercase;text-align:left;">ID</th>' +
        '<th style="padding:0.75rem 1rem;font-size:0.75rem;font-weight:700;color:var(--text-lighter);text-transform:uppercase;text-align:left;">Candidate</th>' +
        '<th style="padding:0.75rem 1rem;font-size:0.75rem;font-weight:700;color:var(--text-lighter);text-transform:uppercase;text-align:left;">Position</th>' +
        '<th style="padding:0.75rem 1rem;font-size:0.75rem;font-weight:700;color:var(--text-lighter);text-transform:uppercase;text-align:left;">Date</th>' +
        '<th style="padding:0.75rem 1rem;font-size:0.75rem;font-weight:700;color:var(--text-lighter);text-transform:uppercase;text-align:left;">Score</th>' +
        '<th style="padding:0.75rem 1rem;font-size:0.75rem;font-weight:700;color:var(--text-lighter);text-transform:uppercase;text-align:left;">Status</th>' +
        '<th style="padding:0.75rem 1rem;font-size:0.75rem;font-weight:700;color:var(--text-lighter);text-transform:uppercase;text-align:center;">Actions</th>' +
      '</tr></thead><tbody>' +
      data.map(function (a) {
        var statusTag = a.stage === 'selected' ? 'tag--green' : a.stage === 'interview' ? 'tag--cyan' : a.stage === 'assessment' ? 'tag--amber' : a.stage === 'waitlisted' ? 'tag--purple' : a.stage === 'rejected' ? 'tag--primary' : 'tag--primary';
        return '<tr style="border-bottom:1px solid var(--border-light);transition:background var(--transition);">' +
          '<td style="padding:0.75rem 1rem;font-size:0.8rem;font-weight:600;color:var(--text-lighter);">' + a.id + '</td>' +
          '<td style="padding:0.75rem 1rem;font-size:0.85rem;font-weight:600;color:var(--text);">' + a.name + '</td>' +
          '<td style="padding:0.75rem 1rem;font-size:0.8rem;color:var(--text-light);">' + a.position + '</td>' +
          '<td style="padding:0.75rem 1rem;font-size:0.8rem;color:var(--text-light);">' + a.date + '</td>' +
          '<td style="padding:0.75rem 1rem;font-size:0.85rem;font-weight:700;color:' + (a.score >= 80 ? 'var(--success)' : a.score >= 70 ? 'var(--accent)' : 'var(--text)') + ';">' + a.score + '%</td>' +
          '<td style="padding:0.75rem 1rem;"><span class="tag ' + statusTag + '" style="font-size:0.65rem;">' + a.status + '</span></td>' +
          '<td style="padding:0.75rem 1rem;text-align:center;"><a href="#" class="btn btn--ghost btn--sm" style="font-size:0.7rem;">View</a></td>' +
        '</tr>';
      }).join('') +
      '</tbody></table>';
  }
  renderAppTable(applications);

  // Application search
  var appSearchInput = document.getElementById('appSearchInput');
  if (appSearchInput) {
    appSearchInput.addEventListener('input', function () {
      var q = this.value.toLowerCase().trim();
      var filtered = !q ? applications : applications.filter(function (a) {
        return a.name.toLowerCase().indexOf(q) !== -1 || a.position.toLowerCase().indexOf(q) !== -1 || a.id.toLowerCase().indexOf(q) !== -1;
      });
      renderAppTable(filtered);
    });
  }

  // ============================================
  // 13. PLACEMENT GRID DATA & RENDER
  // ============================================
  var adminPlacementGrid = document.getElementById('adminPlacementGrid');
  var placements = [
    { id: 1, candidate: 'John Doe', org: 'TechCorp SA', role: 'Jr Developer', supervisor: 'Sarah Mokoena', pct: 65, status: 'Active', location: 'JHB (Hybrid)' },
    { id: 2, candidate: 'Priya Singh', org: 'CloudNet SA', role: 'Cloud Intern', supervisor: 'Mike Johnson', pct: 42, status: 'Active', location: 'CPT (Remote)' },
    { id: 3, candidate: 'Thabo Molefe', org: 'DataFlow Inc.', role: 'Data Analyst', supervisor: 'Dr. Paulina Moeng', pct: 88, status: 'Active', location: 'JHB (On-site)' },
    { id: 4, candidate: 'Sarah Nkosi', org: 'IT Solutions Ltd', role: 'Support Technician', supervisor: 'Lerato Dlamini', pct: 30, status: 'At Risk', location: 'DBN (On-site)' }
  ];

  function renderPlacements(data) {
    if (!adminPlacementGrid) return;
    adminPlacementGrid.innerHTML = data.map(function (p) {
      return '<div class="placement-card" style="margin-bottom:0;">' +
        '<div class="placement-card__header">' +
          '<div class="placement-card__status" style="color:' + (p.status === 'Active' ? 'var(--success)' : 'var(--accent)') + ';"><i class="fas fa-circle"></i> ' + p.status + '</div>' +
          '<div class="placement-card__progress"><span class="placement-card__progress-label">' + p.pct + '%</span><div class="progress-bar"><div class="progress-bar__fill" style="width:' + p.pct + '%"></div></div></div>' +
        '</div>' +
        '<div class="placement-card__body"><div class="placement-card__info">' +
          '<div class="placement-card__item"><span class="placement-card__label">Candidate</span><span class="placement-card__value">' + p.candidate + '</span></div>' +
          '<div class="placement-card__item"><span class="placement-card__label">Organisation</span><span class="placement-card__value">' + p.org + '</span></div>' +
          '<div class="placement-card__item"><span class="placement-card__label">Role</span><span class="placement-card__value">' + p.role + '</span></div>' +
          '<div class="placement-card__item"><span class="placement-card__label">Supervisor</span><span class="placement-card__value">' + p.supervisor + '</span></div>' +
          '<div class="placement-card__item"><span class="placement-card__label">Location</span><span class="placement-card__value">' + p.location + '</span></div>' +
          '<div class="placement-card__item"><span class="placement-card__label">Progress</span><span class="placement-card__value" style="color:' + (p.pct >= 60 ? 'var(--success)' : p.pct >= 40 ? 'var(--accent)' : '#ef4444') + ';">' + p.pct + '% Complete</span></div>' +
        '</div></div>' +
        '<div class="placement-card__footer"><a href="#" class="btn btn--primary btn--sm">Manage</a><a href="#" class="btn btn--ghost btn--sm">Feedback</a><a href="#" class="btn btn--ghost btn--sm">Log Activity</a></div>';
    }).join('');
  }
  renderPlacements(placements);

  // ============================================
  // 14. INTERVIEW GRID DATA & RENDER
  // ============================================
  var adminInterviewGrid = document.getElementById('adminInterviewGrid');
  var adminInterviews = [
    { id: 1, title: 'Technical Interview - J. Doe', candidate: 'John Doe', type: 'Technical', dt: '24 Jul 2025, 10:00', with: 'TechCorp SA', mode: 'Video Call', cd: '2 days', status: 'Scheduled' },
    { id: 2, title: 'Skills Assessment - P. Singh', candidate: 'Priya Singh', type: 'Assessment', dt: '25 Jul 2025, 09:00', with: 'CloudNet SA', mode: 'Online', cd: '3 days', status: 'Scheduled' },
    { id: 3, title: 'Behavioural - T. Molefe', candidate: 'Thabo Molefe', type: 'Behavioural', dt: '28 Jul 2025, 14:00', with: 'DataFlow Inc.', mode: 'In-Person', cd: '6 days', status: 'Pending' },
    { id: 4, title: 'Technical - L. Mokoena', candidate: 'Lebohang Mokoena', type: 'Technical', dt: '30 Jul 2025, 11:00', with: 'Investhood IT', mode: 'Video Call', cd: '8 days', status: 'Pending' },
    { id: 5, title: 'Final Interview - S. Nkosi', candidate: 'Sarah Nkosi', type: 'Final', dt: '01 Aug 2025, 10:00', with: 'IT Solutions Ltd', mode: 'In-Person', cd: '10 days', status: 'Scheduled' },
    { id: 6, title: 'Panel Interview - M. Johnson', candidate: 'Mike Johnson', type: 'Panel', dt: '22 Jul 2025, 15:00', with: 'Investhood IT', mode: 'Video Call', cd: 'Today', status: 'Confirmed' }
  ];

  function renderAdminInterviews(data) {
    if (!adminInterviewGrid) return;
    adminInterviewGrid.innerHTML = data.map(function (iv) {
      return '<div class="interview-card">' +
        '<div class="interview-card__header"><h3 class="interview-card__title">' + iv.title + '</h3><span class="interview-card__type">' + iv.type + '</span></div>' +
        '<div class="interview-card__countdown"><i class="fas fa-clock"></i><span class="interview-card__countdown-time">' + iv.cd + '</span></div>' +
        '<div class="interview-card__details">' +
          '<div class="interview-card__detail"><i class="fas fa-user"></i> ' + iv.candidate + '</div>' +
          '<div class="interview-card__detail"><i class="fas fa-calendar"></i> ' + iv.dt + '</div>' +
          '<div class="interview-card__detail"><i class="fas fa-building"></i> ' + iv.with + '</div>' +
          '<div class="interview-card__detail"><i class="fas fa-video"></i> ' + iv.mode + '</div>' +
        '</div>' +
        '<div class="interview-card__actions">' +
          '<a href="#" class="btn btn--primary btn--sm"><i class="fas fa-check"></i> Confirm</a>' +
          '<a href="#" class="btn btn--outline btn--sm"><i class="fas fa-clock"></i> Reschedule</a>' +
          '<a href="#" class="btn btn--ghost btn--sm">Details</a>' +
        '</div>';
    }).join('');
  }
  renderAdminInterviews(adminInterviews);

  // ============================================
  // 15. AUDIT LOG TABLE DATA & RENDER
  // ============================================
  var adminAuditTable = document.getElementById('adminAuditTable');
  var auditLogs = [
    { id: 1, user: 'Admin User', action: 'Login', target: 'Admin Dashboard', timestamp: '24 Jul 2025, 08:45:12', type: 'auth' },
    { id: 2, user: 'Admin User', action: 'Created Programme', target: 'Software Engineering Graduate', timestamp: '24 Jul 2025, 09:12:30', type: 'programme' },
    { id: 3, user: 'Admin User', action: 'Updated Application', target: 'APP-003 - Thabo Molefe', timestamp: '24 Jul 2025, 10:05:18', type: 'candidate' },
    { id: 4, user: 'Admin User', action: 'Exported Report', target: 'Programme Performance Q2', timestamp: '24 Jul 2025, 11:30:00', type: 'exports' },
    { id: 5, user: 'System', action: 'Generated Report', target: 'Monthly Compliance Report', timestamp: '24 Jul 2025, 12:00:00', type: 'reports' },
    { id: 6, user: 'Admin User', action: 'Modified Permissions', target: 'Role: Programme Manager', timestamp: '23 Jul 2025, 16:20:45', type: 'permissions' },
    { id: 7, user: 'Admin User', action: 'Sent Bulk Notification', target: '185 candidates notified', timestamp: '23 Jul 2025, 14:15:00', type: 'notifications' },
    { id: 8, user: 'Admin User', action: 'Updated User Role', target: 'john.doe@example.com', timestamp: '23 Jul 2025, 13:00:22', type: 'admin' }
  ];

  function renderAuditLog(data) {
    if (!adminAuditTable) return;
    if (!data.length) {
      adminAuditTable.innerHTML = '<div style="padding:2rem;text-align:center;color:var(--text-light);">No audit entries found.</div>';
      return;
    }
    adminAuditTable.innerHTML = '<table class="admin-table" style="width:100%;border-collapse:collapse;min-width:700px;">' +
      '<thead><tr style="border-bottom:1px solid var(--border);background:var(--bg);">' +
        '<th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:700;color:var(--text-lighter);text-transform:uppercase;">User</th>' +
        '<th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:700;color:var(--text-lighter);text-transform:uppercase;">Action</th>' +
        '<th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:700;color:var(--text-lighter);text-transform:uppercase;">Target</th>' +
        '<th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:700;color:var(--text-lighter);text-transform:uppercase;">Timestamp</th>' +
      '</tr></thead><tbody>' +
      data.map(function (log) {
        return '<tr style="border-bottom:1px solid var(--border-light);">' +
          '<td style="padding:0.75rem 1rem;font-size:0.8rem;font-weight:600;color:var(--text);">' + log.user + '</td>' +
          '<td style="padding:0.75rem 1rem;font-size:0.8rem;color:var(--text);">' + log.action + '</td>' +
          '<td style="padding:0.75rem 1rem;font-size:0.8rem;color:var(--text-light);">' + log.target + '</td>' +
          '<td style="padding:0.75rem 1rem;font-size:0.75rem;color:var(--text-lighter);">' + log.timestamp + '</td>' +
        '</tr>';
      }).join('') +
      '</tbody></table>';
  }
  renderAuditLog(auditLogs);

  // Audit Log Filtering
  var auditFilterAction = document.getElementById('auditFilterAction');
  var auditFilterDate = document.getElementById('auditFilterDate');
  var auditSearch = document.getElementById('auditSearch');

  function filterAudit() {
    var actionVal = auditFilterAction ? auditFilterAction.value : 'all';
    var q = auditSearch ? auditSearch.value.toLowerCase().trim() : '';
    var filtered = auditLogs.filter(function (log) {
      var ma = actionVal === 'all' || log.type === actionVal;
      var mq = !q || log.user.toLowerCase().indexOf(q) !== -1 || log.action.toLowerCase().indexOf(q) !== -1 || log.target.toLowerCase().indexOf(q) !== -1;
      return ma && mq;
    });
    renderAuditLog(filtered);
  }

  if (auditFilterAction) auditFilterAction.addEventListener('change', filterAudit);
  if (auditFilterDate) auditFilterDate.addEventListener('change', filterAudit);
  if (auditSearch) auditSearch.addEventListener('input', filterAudit);

  // ============================================
  // 16. USER TABLE DATA & RENDER
  // ============================================
  var adminUserTable = document.getElementById('adminUserTable');
  var users = [
    { id: 1, name: 'Admin User', email: 'admin@investhoodit.co.za', role: 'Super Admin', status: 'Active', lastActive: 'Now', mfa: true },
    { id: 2, name: 'John Doe', email: 'john.doe@example.com', role: 'Candidate', status: 'Active', lastActive: '2 hours ago', mfa: false },
    { id: 3, name: 'Sarah Mokoena', email: 'sarah.m@techcorp.co.za', role: 'Programme Manager', status: 'Active', lastActive: '1 hour ago', mfa: true },
    { id: 4, name: 'Dr. Jane Mokoena', email: 'jane.m@investhoodit.co.za', role: 'Senior Mentor', status: 'Active', lastActive: '30 min ago', mfa: true },
    { id: 5, name: 'Mike Johnson', email: 'mike.j@cloudnet.co.za', role: 'Employer', status: 'Active', lastActive: '3 hours ago', mfa: false },
    { id: 6, name: 'Priya Singh', email: 'priya.s@example.com', role: 'Candidate', status: 'Suspended', lastActive: '1 week ago', mfa: false }
  ];

  function renderUserTable(data) {
    if (!adminUserTable) return;
    adminUserTable.innerHTML = '<table class="admin-table" style="width:100%;border-collapse:collapse;min-width:700px;">' +
      '<thead><tr style="border-bottom:1px solid var(--border);background:var(--bg);">' +
        '<th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:700;color:var(--text-lighter);text-transform:uppercase;">Name</th>' +
        '<th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:700;color:var(--text-lighter);text-transform:uppercase;">Email</th>' +
        '<th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:700;color:var(--text-lighter);text-transform:uppercase;">Role</th>' +
        '<th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:700;color:var(--text-lighter);text-transform:uppercase;">Status</th>' +
        '<th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:700;color:var(--text-lighter);text-transform:uppercase;">MFA</th>' +
        '<th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:700;color:var(--text-lighter);text-transform:uppercase;">Actions</th>' +
      '</tr></thead><tbody>' +
      data.map(function (u) {
        var roleTag = u.role === 'Super Admin' ? 'tag--primary' : u.role === 'Programme Manager' ? 'tag--cyan' : u.role === 'Senior Mentor' ? 'tag--purple' : u.role === 'Employer' ? 'tag--amber' : 'tag--green';
        return '<tr style="border-bottom:1px solid var(--border-light);">' +
          '<td style="padding:0.75rem 1rem;font-size:0.85rem;font-weight:600;color:var(--text);">' + u.name + '</td>' +
          '<td style="padding:0.75rem 1rem;font-size:0.8rem;color:var(--text-light);">' + u.email + '</td>' +
          '<td style="padding:0.75rem 1rem;"><span class="tag ' + roleTag + '" style="font-size:0.6rem;">' + u.role + '</span></td>' +
          '<td style="padding:0.75rem 1rem;font-size:0.8rem;color:' + (u.status === 'Active' ? 'var(--success)' : '#ef4444') + ';font-weight:600;">' + u.status + '</td>' +
          '<td style="padding:0.75rem 1rem;font-size:0.8rem;">' + (u.mfa ? '<span style="color:var(--success);"><i class="fas fa-shield-alt"></i> Enabled</span>' : '<span style="color:var(--text-lighter);">Disabled</span>') + '</td>' +
          '<td style="padding:0.75rem 1rem;"><a href="#" class="btn btn--ghost btn--sm" style="font-size:0.65rem;">Manage</a></td>' +
        '</tr>';
      }).join('') +
      '</tbody></table>';
  }
  renderUserTable(users);

  // User search
  var userSearchInput = document.getElementById('userSearchInput');
  if (userSearchInput) {
    userSearchInput.addEventListener('input', function () {
      var q = this.value.toLowerCase().trim();
      var filtered = !q ? users : users.filter(function (u) {
        return u.name.toLowerCase().indexOf(q) !== -1 || u.email.toLowerCase().indexOf(q) !== -1 || u.role.toLowerCase().indexOf(q) !== -1;
      });
      renderUserTable(filtered);
    });
  }

// ============================================
  // 18. PLACEMENT SEARCH
  // ============================================
  var placementSearch = document.getElementById('placementSearch');
  if (placementSearch) {
    placementSearch.addEventListener('input', function () {
      var q = this.value.toLowerCase().trim();
      var filtered = !q ? placements : placements.filter(function (p) {
        return p.candidate.toLowerCase().indexOf(q) !== -1 || p.org.toLowerCase().indexOf(q) !== -1 || p.role.toLowerCase().indexOf(q) !== -1;
      });
      renderPlacements(filtered);
    });
  }

  // ============================================
  // 19. INTERVIEW SEARCH
  // ============================================
  var interviewSearch = document.getElementById('interviewSearch');
  if (interviewSearch) {
    interviewSearch.addEventListener('input', function () {
      var q = this.value.toLowerCase().trim();
      var filtered = !q ? adminInterviews : adminInterviews.filter(function (iv) {
        return iv.title.toLowerCase().indexOf(q) !== -1 || iv.candidate.toLowerCase().indexOf(q) !== -1 || iv.with.toLowerCase().indexOf(q) !== -1;
      });
      renderAdminInterviews(filtered);
    });
  }

  // ============================================
  // 20. TALENT SEARCH, POOL SEARCH, ATTENDANCE DATA
  // ============================================
  var poolSearch = document.getElementById('poolSearch');
  if (poolSearch) {
    poolSearch.addEventListener('input', function () {
      if (window.InvesthoodNotifications) {
        window.InvesthoodNotifications.show('info', 'Searching', 'Searching talent pools for "' + this.value + '"...');
      }
    });
  }

  // ============================================
  // 21. INTERVIEW DATE FILTER
  // ============================================
  var interviewFilterDate = document.getElementById('interviewFilterDate');
  if (interviewFilterDate) {
    interviewFilterDate.addEventListener('change', function () {
      if (window.InvesthoodNotifications) {
        window.InvesthoodNotifications.show('info', 'Filter Applied', 'Showing interviews for: ' + this.options[this.selectedIndex].text);
      }
    });
  }

  // ============================================
  // 22. PLACEMENT STATUS FILTER
  // ============================================
  var placementFilterStatus = document.getElementById('placementFilterStatus');
  if (placementFilterStatus) {
    placementFilterStatus.addEventListener('change', function () {
      var val = this.value;
      var filtered = val === 'all' ? placements : placements.filter(function (p) { return p.status.toLowerCase() === val; });
      renderPlacements(filtered);
    });
  }

// ============================================
  // 24. ANALYTICS PERIOD/CATEGORY FILTERS
  // ============================================
  var analyticsPeriod = document.getElementById('analyticsPeriod');
  var analyticsCategory = document.getElementById('analyticsCategory');
  if (analyticsPeriod) {
    analyticsPeriod.addEventListener('change', function () {
      if (window.InvesthoodNotifications) {
        window.InvesthoodNotifications.show('info', 'Period Updated', 'Analytics period changed to: ' + this.options[this.selectedIndex].text);
      }
    });
  }
  if (analyticsCategory) {
    analyticsCategory.addEventListener('change', function () {
      if (window.InvesthoodNotifications) {
        window.InvesthoodNotifications.show('info', 'Category Filtered', 'Analytics category: ' + this.options[this.selectedIndex].text);
      }
    });
  }

  // ============================================
  // 25. TOOLTIP INIT (basic)
  // ============================================
  document.querySelectorAll('[data-tooltip]').forEach(function (el) {
    el.addEventListener('mouseenter', function (e) {
      var tip = document.createElement('div');
      tip.textContent = this.getAttribute('data-tooltip');
      tip.style.cssText = 'position:fixed;background:var(--dark);color:white;font-size:0.75rem;padding:0.375rem 0.75rem;border-radius:var(--radius-sm);z-index:9999;pointer-events:none;white-space:nowrap;';
      tip.id = 'adminTooltip';
      document.body.appendChild(tip);
      var rect = this.getBoundingClientRect();
      tip.style.left = (rect.left + rect.width / 2 - tip.offsetWidth / 2) + 'px';
      tip.style.top = (rect.top - tip.offsetHeight - 8) + 'px';
    });
    el.addEventListener('mouseleave', function () {
      var tip = document.getElementById('adminTooltip');
      if (tip) tip.remove();
    });
  });

  // ============================================
  // 26. SIGN OUT CONFIRMATION MODAL
  // ============================================
  var logoutModal = document.getElementById('logoutModal');
  var logoutCancel = document.getElementById('logoutCancel');

  function openLogoutModal(e) {
    if (e && e.preventDefault) e.preventDefault();
    if (logoutModal) {
      logoutModal.classList.add('active');
      logoutModal.setAttribute('aria-hidden', 'false');
    }
  }

  function closeLogoutModal() {
    if (logoutModal) {
      logoutModal.classList.remove('active');
      logoutModal.setAttribute('aria-hidden', 'true');
    }
  }

  // Bind to the sidebar sign-out button (works together with the inline onclick)
  var sidebarLogoutBtn = document.getElementById('sidebarLogoutBtn');
  if (sidebarLogoutBtn) sidebarLogoutBtn.addEventListener('click', openLogoutModal);
  if (logoutCancel) logoutCancel.addEventListener('click', closeLogoutModal);

  // Close modal on Escape key
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeLogoutModal();
  });

  // ============================================
  // 27. CONSOLE BRANDING
  // ============================================
  console.log('%c Investhood IT Admin Dashboard ', 'background: #1a56db; color: white; font-size: 16px; font-weight: bold; padding: 8px 12px; border-radius: 4px;');
  console.log('%c Programme Management & Talent Intelligence Platform ', 'font-size: 13px; color: #64748b;');

});

