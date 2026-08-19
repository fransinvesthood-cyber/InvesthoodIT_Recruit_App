/* ================================================
   INVESTHOOD IT - Dashboard JavaScript
   ================================================ */

'use strict';

document.addEventListener('DOMContentLoaded', function () {

  var dashContent = document.getElementById('dashContent');
  if (!dashContent) return; // Not a dashboard page

  // ============================================
  // 1. SIDEBAR TOGGLE & NAVIGATION
  // ============================================
  var sidebar = document.getElementById('sidebar');
  var sidebarToggle = document.getElementById('sidebarToggle');
  var sidebarClose = document.getElementById('sidebarClose');
  var sidebarOverlay = document.getElementById('sidebarOverlay');

  function openSidebar() {
    if (sidebar) sidebar.classList.add('open');
    if (sidebarOverlay) sidebarOverlay.classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function closeSidebar() {
    if (sidebar) sidebar.classList.remove('open');
    if (sidebarOverlay) sidebarOverlay.classList.remove('open');
    document.body.style.overflow = '';
  }

  if (sidebarToggle) sidebarToggle.addEventListener('click', openSidebar);
  if (sidebarClose) sidebarClose.addEventListener('click', closeSidebar);
  if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && sidebar && sidebar.classList.contains('open')) closeSidebar();
  });

  // Sidebar active link highlight on scroll
  var sidebarLinks = document.querySelectorAll('.sidebar__link');
  var dashSections = document.querySelectorAll('.dash-section');

  function highlightDashNav() {
    var scrollPos = window.scrollY + 100;
    var currentId = '';
    dashSections.forEach(function (sec) {
      var top = sec.offsetTop;
      var height = sec.offsetHeight;
      if (scrollPos >= top && scrollPos < top + height) currentId = sec.getAttribute('id');
    });
    sidebarLinks.forEach(function (link) {
      link.classList.remove('active');
      if (link.getAttribute('href') === '#' + currentId) link.classList.add('active');
    });
  }
  window.addEventListener('scroll', highlightDashNav, { passive: true });

// Sidebar link smooth scroll
  sidebarLinks.forEach(function (link) {
    link.addEventListener('click', function (e) {
      // Explicit external/page links (e.g. Settings) must navigate reliably.
      if (this.hasAttribute('data-page-link')) {
        var pageHref = this.getAttribute('href');
        e.preventDefault();
        if (pageHref && pageHref !== '#') {
          window.location.href = pageHref;
        }
        return;
      }
      var targetId = this.getAttribute('href');
      // Only intercept in-page (#...) links; external/page links navigate normally
      if (!targetId || !targetId.startsWith('#')) return;
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

  var overviewObs = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        animateNumber(entry.target);
        overviewObs.unobserve(entry.target);
      }
    });
  }, { threshold: 0.3 });

  document.querySelectorAll('.overview-card__number').forEach(function (n) {
    overviewObs.observe(n);
  });

  // ============================================
  // 4. DASHBOARD OPPORTUNITIES
  // ============================================
  var dashOpps = [
    { id: 1, title: 'Junior Software Developer', type: 'graduate', typeLabel: 'Graduate Programme', location: 'Johannesburg, Gauteng', province: 'gauteng', closingDate: '30 Jun 2025', employmentType: 'Full-time', qualification: "Bachelor's Degree in CS or related", match: 95 },
    { id: 2, title: 'IT Support Learnership', type: 'learnership', typeLabel: 'Learnership', location: 'Cape Town, Western Cape', province: 'western-cape', closingDate: '15 Jul 2025', employmentType: 'Fixed-term', qualification: 'Grade 12 + NQF Level 4', match: 88 },
    { id: 3, title: 'Cloud Engineering Intern', type: 'internship', typeLabel: 'Internship', location: 'Durban, KZN', province: 'kwazulu-natal', closingDate: '31 Aug 2025', employmentType: 'Internship', qualification: "Bachelor's in IT or Engineering", match: 92 },
    { id: 4, title: 'Data Analytics Graduate', type: 'graduate', typeLabel: 'Graduate Programme', location: 'Johannesburg, Gauteng', province: 'gauteng', closingDate: '30 Jun 2025', employmentType: 'Full-time', qualification: 'Honours in Data Science', match: 78 },
    { id: 5, title: 'Cyber Security Learnership', type: 'learnership', typeLabel: 'Learnership', location: 'Pretoria, Gauteng', province: 'gauteng', closingDate: '15 Sep 2025', employmentType: 'Fixed-term', qualification: 'NQF Level 5 Cyber Security', match: 85 },
    { id: 6, title: 'WIL - IT Placement', type: 'wil', typeLabel: 'Work Integrated Learning', location: 'Port Elizabeth, EC', province: 'eastern-cape', closingDate: '30 Jul 2025', employmentType: 'Contract', qualification: '3rd Year IT Degree', match: 90 }
  ];

  var dashRecOpps = [
    { id: 7, title: 'Full-Stack Developer Graduate', type: 'graduate', typeLabel: 'Graduate', location: 'Johannesburg', closingDate: '15 Aug 2025', match: 97 },
    { id: 8, title: 'DevOps Engineering Intern', type: 'internship', typeLabel: 'Internship', location: 'Cape Town', closingDate: '30 Sep 2025', match: 93 },
    { id: 9, title: 'AI/ML Learnership', type: 'learnership', typeLabel: 'Learnership', location: 'Durban', closingDate: '31 Oct 2025', match: 89 }
  ];

  var oppGrid = document.getElementById('oppGrid');
  var oppRecGrid = document.getElementById('oppRecommendedGrid');

  function renderOpps(data) {
    if (!oppGrid) return;
    if (!data.length) {
      oppGrid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:2rem;color:var(--text-light);">No opportunities match your criteria.</div>';
      return;
    }
    oppGrid.innerHTML = data.map(function (o) {
      return '<div class="opp-card">' +
        '<div class="opp-card__header">' +
          '<h3 class="opp-card__title">' + o.title + '</h3>' +
          '<div><span class="opp-card__type">' + o.typeLabel + '</span><span class="opp-card__match"><i class="fas fa-star"></i> ' + o.match + '%</span></div>' +
        '</div>' +
        '<div class="opp-card__details">' +
          '<div class="opp-card__detail"><i class="fas fa-map-marker-alt"></i> ' + o.location + '</div>' +
          '<div class="opp-card__detail"><i class="fas fa-calendar-alt"></i> Closes: ' + o.closingDate + '</div>' +
          '<div class="opp-card__detail"><i class="fas fa-briefcase"></i> ' + o.employmentType + '</div>' +
          '<div class="opp-card__detail"><i class="fas fa-check-circle"></i> ' + o.typeLabel + '</div>' +
        '</div>' +
        '<div class="opp-card__qual"><i class="fas fa-graduation-cap"></i> ' + o.qualification + '</div>' +
        '<div class="opp-card__actions">' +
          '<a href="#" class="btn btn--primary btn--sm">Apply Now</a>' +
          '<button class="opp-card__save" data-id="' + o.id + '" aria-label="Save"><i class="far fa-bookmark"></i></button>' +
        '</div>';
    }).join('');
    oppGrid.querySelectorAll('.opp-card__save').forEach(function (btn) {
      btn.addEventListener('click', function () {
        this.classList.toggle('saved');
        this.querySelector('i').className = this.classList.contains('saved') ? 'fas fa-bookmark' : 'far fa-bookmark';
        if (this.classList.contains('saved') && window.InvesthoodNotifications) {
          window.InvesthoodNotifications.show('success', 'Saved!', 'Opportunity added to your saved list.');
        }
      });
    });
  }

  function renderRecOpps() {
    if (!oppRecGrid) return;
    oppRecGrid.innerHTML = dashRecOpps.map(function (o) {
      return '<div class="opp-card">' +
        '<div class="opp-card__header">' +
          '<h3 class="opp-card__title">' + o.title + '</h3>' +
          '<span class="opp-card__match"><i class="fas fa-star"></i> ' + o.match + '%</span>' +
        '</div>' +
        '<div class="opp-card__details">' +
          '<div class="opp-card__detail"><i class="fas fa-map-marker-alt"></i> ' + o.location + '</div>' +
          '<div class="opp-card__detail"><i class="fas fa-calendar-alt"></i> Closes: ' + o.closingDate + '</div>' +
        '</div>' +
        '<div class="opp-card__actions">' +
          '<a href="#" class="btn btn--primary btn--sm">Apply</a>' +
          '<button class="opp-card__save" data-id="r' + o.id + '" aria-label="Save"><i class="far fa-bookmark"></i></button>' +
        '</div>';
    }).join('');
    oppRecGrid.querySelectorAll('.opp-card__save').forEach(function (btn) {
      btn.addEventListener('click', function () {
        this.classList.toggle('saved');
        this.querySelector('i').className = this.classList.contains('saved') ? 'fas fa-bookmark' : 'far fa-bookmark';
      });
    });
  }

  renderOpps(dashOpps);
  renderRecOpps();

  // Filtering
  var oppSearch = document.getElementById('oppSearchInput');
  var oppFilterType = document.getElementById('oppFilterType');
  var oppFilterLoc = document.getElementById('oppFilterLocation');
  var oppFilterEmp = document.getElementById('oppFilterEmployment');

  function filterDashOpps() {
    var q = oppSearch ? oppSearch.value.toLowerCase().trim() : '';
    var t = oppFilterType ? oppFilterType.value : 'all';
    var l = oppFilterLoc ? oppFilterLoc.value : 'all';
    var e = oppFilterEmp ? oppFilterEmp.value : 'all';
    var filtered = dashOpps.filter(function (o) {
      var mq = !q || o.title.toLowerCase().indexOf(q) !== -1 || o.location.toLowerCase().indexOf(q) !== -1;
      var mt = t === 'all' || o.type === t;
      var ml = l === 'all' || o.province === l;
      var me = e === 'all' || o.employmentType.toLowerCase().indexOf(e.replace('-', ' ')) !== -1;
      return mq && mt && ml && me;
    });
    renderOpps(filtered);
  }

  if (oppSearch) oppSearch.addEventListener('input', filterDashOpps);
  if (oppFilterType) oppFilterType.addEventListener('change', filterDashOpps);
  if (oppFilterLoc) oppFilterLoc.addEventListener('change', filterDashOpps);
  if (oppFilterEmp) oppFilterEmp.addEventListener('change', filterDashOpps);

  // ============================================
  // 5. APPLICATION TRACKER
  // ============================================
  var appTracker = document.getElementById('appTracker');
  var appStatuses = ['Draft', 'Submitted', 'Eligibility Review', 'Screening', 'Assessment', 'Interview', 'Selected'];
  var appsData = [
    { title: 'Junior Software Developer', date: '15 May 2025', idx: 5, status: 'Interview' },
    { title: 'Cloud Engineering Intern', date: '10 Apr 2025', idx: 3, status: 'Screening' },
    { title: 'Data Analytics Graduate', date: '20 Mar 2025', idx: 6, status: 'Selected' }
  ];

  function renderApps() {
    if (!appTracker) return;
    appTracker.innerHTML = appsData.map(function (a) {
      var tl = appStatuses.map(function (s, i) {
        var cls = i < a.idx ? 'timeline-step--done' : i === a.idx ? 'timeline-step--current' : 'timeline-step--waiting';
        var icon = i < a.idx ? '<i class="fas fa-check"></i>' : i === a.idx ? '<i class="fas fa-circle"></i>' : '';
        var line = i < appStatuses.length - 1 ? '<div class="timeline-step__line"></div>' : '';
        return '<div class="timeline-step ' + cls + '"><div class="timeline-step__dot">' + icon + '</div>' + line + '<span class="timeline-step__label">' + s + '</span></div>';
      }).join('');
      var pct = Math.round((a.idx + 1) / appStatuses.length * 100);
      return '<div class="app-card">' +
        '<div class="app-card__header"><h3 class="app-card__title">' + a.title + '</h3><span class="app-card__date"><i class="fas fa-calendar-alt"></i> ' + a.date + '</span></div>' +
        '<div class="app-card__timeline">' + tl + '</div>' +
        '<div class="app-card__progress"><div class="app-card__progress-bar"><div class="app-card__progress-fill" style="width:' + pct + '%"></div><span class="app-card__progress-label">Current: <strong>' + a.status + '</strong> &middot; ' + pct + '% complete</span></div>' +
        '<div class="app-card__footer"><a href="#" class="btn btn--primary btn--sm">View Details</a><a href="#" class="btn btn--ghost btn--sm">Withdraw</a></div>';
    }).join('');
  }
  renderApps();

  // ============================================
  // 6. PROGRAMME MANAGEMENT
  // ============================================
  var progGrid = document.getElementById('progGrid');
  var progs = [
    { title: 'Software Engineering Graduate', type: 'Graduate', tc: 'graduate', cohort: 'Cohort 2025-A', mentor: 'Dr. Jane Mokoena', pct: 60, acts: '8 upcoming', tl: 'Jan 2025 - Dec 2025' },
    { title: 'Cloud Computing Learnership', type: 'Learnership', tc: 'learnership', cohort: 'Cohort 2025-B', mentor: 'Thabo Nkosi', pct: 35, acts: '5 upcoming', tl: 'Mar 2025 - Aug 2026' },
    { title: 'IT Internship Programme', type: 'Internship', tc: 'internship', cohort: 'Cohort 2025-A', mentor: 'Sarah Mokoena', pct: 80, acts: '3 upcoming', tl: 'Jan 2025 - Jun 2025' }
  ];

  function renderProgs() {
    if (!progGrid) return;
    progGrid.innerHTML = progs.map(function (p) {
      return '<div class="prog-card">' +
        '<div class="prog-card__header"><h3 class="prog-card__title">' + p.title + '</h3><span class="prog-card__type prog-card__type--' + p.tc + '">' + p.type + '</span></div>' +
        '<div class="prog-card__progress"><div class="prog-card__bar"><div class="prog-card__bar-fill" style="width:' + p.pct + '%"></div><span class="prog-card__percent">' + p.pct + '% Complete</span></div>' +
        '<div class="prog-card__details">' +
          '<div class="prog-card__detail"><i class="fas fa-users"></i> ' + p.cohort + '</div>' +
          '<div class="prog-card__detail"><i class="fas fa-user-tie"></i> Mentor: ' + p.mentor + '</div>' +
          '<div class="prog-card__detail"><i class="fas fa-calendar-alt"></i> ' + p.tl + '</div>' +
          '<div class="prog-card__detail"><i class="fas fa-tasks"></i> ' + p.acts + '</div>' +
        '</div>' +
        '<div class="prog-card__actions">' +
          '<a href="#" class="btn btn--primary btn--sm">View Details</a>' +
          '<a href="#" class="btn btn--ghost btn--sm">Tasks</a>' +
          '<a href="#" class="btn btn--ghost btn--sm">Documents</a>' +
          '<a href="#" class="btn btn--ghost btn--sm">Progress</a>' +
        '</div>';
    }).join('');
  }
  renderProgs();

  // ============================================
  // 7. INTERVIEW MANAGEMENT
  // ============================================
  var interviewGrid = document.getElementById('interviewGrid');
  var interviews = [
    { title: 'Technical Interview', type: 'Interview', dt: '24 Jul 2025, 10:00 AM', with: 'TechCorp SA - Hiring Team', mode: 'Video Call (Zoom)', cd: '5 days 3h 15m' },
    { title: 'Skills Assessment', type: 'Assessment', dt: '28 Jul 2025, 09:00 AM', with: 'Investhood IT - Assessment Centre', mode: 'Online Platform', cd: '9 days 2h 15m' },
    { title: 'Mentoring Session', type: 'Mentoring', dt: '15 Jul 2025, 14:00 PM', with: 'Thabo Moloi - Senior Mentor', mode: 'In-Person (JHB)', cd: 'Today @ 14:00' }
  ];

  function renderInterviews() {
    if (!interviewGrid) return;
    interviewGrid.innerHTML = interviews.map(function (iv) {
      return '<div class="interview-card">' +
        '<div class="interview-card__header"><h3 class="interview-card__title">' + iv.title + '</h3><span class="interview-card__type">' + iv.type + '</span></div>' +
        '<div class="interview-card__countdown"><i class="fas fa-clock"></i><span class="interview-card__countdown-time">' + iv.cd + '</span></div>' +
        '<div class="interview-card__details">' +
          '<div class="interview-card__detail"><i class="fas fa-calendar"></i> ' + iv.dt + '</div>' +
          '<div class="interview-card__detail"><i class="fas fa-user"></i> ' + iv.with + '</div>' +
          '<div class="interview-card__detail"><i class="fas fa-video"></i> ' + iv.mode + '</div>' +
        '</div>' +
        '<div class="interview-card__actions">' +
          '<a href="#" class="btn btn--primary btn--sm"><i class="fas fa-check"></i> Accept</a>' +
          '<a href="#" class="btn btn--outline btn--sm"><i class="fas fa-clock"></i> Reschedule</a>' +
          '<a href="#" class="btn btn--ghost btn--sm">Details</a>' +
        '</div>';
    }).join('');
  }
  renderInterviews();

  // ============================================
  // 8. NOTIFICATION CENTRE
  // ============================================
  var notifList = document.getElementById('notifList');
  var notifs = [
    { id: 1, type: 'opportunity', icon: 'opportunity', title: 'New Opportunity Matched', msg: 'A "Full-Stack Developer Graduate" position matches your profile.', time: '2 hours ago', unread: true },
    { id: 2, type: 'programme', icon: 'programme', title: 'Programme Update', msg: 'Your Software Engineering programme has a new module available.', time: '5 hours ago', unread: true },
    { id: 3, type: 'application', icon: 'application', title: 'Application Status Changed', msg: 'Your application moved to Interview stage.', time: '1 day ago', unread: true },
    { id: 4, type: 'interview', icon: 'interview', title: 'Interview Scheduled', msg: 'Technical Interview confirmed for 24 July 2025.', time: '2 days ago', unread: true },
    { id: 5, type: 'learning', icon: 'learning', title: 'Learning Milestone', msg: 'You completed "Agile & Scrum Fundamentals"!', time: '3 days ago', unread: false },
    { id: 6, type: 'announcement', icon: 'announcement', title: 'Platform Announcement', msg: 'New mentorship opportunities are now available.', time: '5 days ago', unread: false }
  ];

  var notifIconMap = {
    opportunity: 'fa-briefcase',
    programme: 'fa-graduation-cap',
    application: 'fa-file-alt',
    interview: 'fa-calendar-check',
    learning: 'fa-book',
    announcement: 'fa-bullhorn'
  };

  function renderNotifs(filter) {
    if (!notifList) return;
    filter = filter || 'all';
    var filtered = filter === 'all' ? notifs : notifs.filter(function (n) { return n.type === filter; });
    if (!filtered.length) {
      notifList.innerHTML = '<div style="padding:2rem;text-align:center;color:var(--text-light);">No notifications in this category.</div>';
      return;
    }
    notifList.innerHTML = filtered.map(function (n) {
      return '<div class="notif-item' + (n.unread ? ' notif-item--unread' : '') + '" data-id="' + n.id + '">' +
        '<div class="notif-item__icon notif-item__icon--' + n.icon + '"><i class="fas ' + (notifIconMap[n.icon] || 'fa-bell') + '"></i></div>' +
        '<div class="notif-item__content">' +
          '<div class="notif-item__title">' + n.title + '</div>' +
          '<div class="notif-item__message">' + n.msg + '</div>' +
          '<div class="notif-item__time">' + n.time + '</div>' +
        '</div>' +
        '<div class="notif-item__actions">' +
          (n.unread ? '<button class="notif-item__mark-read" aria-label="Mark as read"><i class="fas fa-check"></i></button>' : '') +
        '</div>';
    }).join('');

    // Mark as read
    notifList.querySelectorAll('.notif-item__mark-read').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var item = this.closest('.notif-item');
        var id = parseInt(item.getAttribute('data-id'), 10);
        var found = notifs.find(function (n) { return n.id === id; });
        if (found) found.unread = false;
        item.classList.remove('notif-item--unread');
        this.remove();
      });
    });
  }

  renderNotifs('all');

  // Filter buttons
  document.querySelectorAll('.notif-centre__filter').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.notif-centre__filter').forEach(function (b) { b.classList.remove('active'); });
      this.classList.add('active');
      renderNotifs(this.getAttribute('data-filter'));
    });
  });

  // Mark all read
  var markAllBtn = document.getElementById('markAllRead');
  if (markAllBtn) {
    markAllBtn.addEventListener('click', function () {
      notifs.forEach(function (n) { n.unread = false; });
      notifList.querySelectorAll('.notif-item--unread').forEach(function (item) {
        item.classList.remove('notif-item--unread');
        var markBtn = item.querySelector('.notif-item__mark-read');
        if (markBtn) markBtn.remove();
      });
      if (window.InvesthoodNotifications) {
        window.InvesthoodNotifications.show('success', 'Done!', 'All notifications marked as read.');
      }
    });
  }

  // ============================================
  // 9. CHART.JS ANALYTICS
  // ============================================
  if (typeof Chart !== 'undefined') {
    var chartDefaults = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } };

    var appsCtx = document.getElementById('applicationsChart');
    if (appsCtx) {
      new Chart(appsCtx, {
        type: 'bar',
        data: {
          labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
          datasets: [{ label: 'Applications', data: [0, 1, 2, 3, 3, 4], backgroundColor: 'rgba(26,86,219,0.7)', borderRadius: 4 }]
        },
        options: Object.assign({}, chartDefaults, { scales: { y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } }, x: { grid: { display: false } } } })
      });
    }

    var skillsCtx = document.getElementById('skillsChart');
    if (skillsCtx) {
      new Chart(skillsCtx, {
        type: 'radar',
        data: {
          labels: ['JavaScript', 'React', 'Python', 'Node.js', 'AWS', 'SQL'],
          datasets: [{ label: 'Skill Level', data: [90, 75, 70, 65, 50, 80], backgroundColor: 'rgba(26,86,219,0.2)', borderColor: 'rgba(26,86,219,0.8)', borderWidth: 2, pointBackgroundColor: 'rgba(26,86,219,1)' }]
        },
        options: Object.assign({}, chartDefaults, { scales: { r: { min: 0, max: 100, ticks: { stepSize: 20 } } } })
      });
    }

    var progCtx = document.getElementById('programmeChart');
    if (progCtx) {
      new Chart(progCtx, {
        type: 'doughnut',
        data: {
          labels: ['Completed', 'In Progress', 'Not Started'],
          datasets: [{ data: [25, 45, 30], backgroundColor: ['#10b981', '#1a56db', '#e2e8f0'], borderWidth: 0, hoverOffset: 6 }]
        },
        options: Object.assign({}, chartDefaults, { cutout: '70%', plugins: { legend: { display: true, position: 'bottom', labels: { boxWidth: 10, padding: 10, font: { size: 11 } } } } })
      });
    }

    var careerCtx = document.getElementById('careerChart');
    if (careerCtx) {
      new Chart(careerCtx, {
        type: 'line',
        data: {
          labels: ['Month 1', 'Month 2', 'Month 3', 'Month 4', 'Month 5', 'Month 6'],
          datasets: [
            { label: 'Growth Score', data: [20, 35, 50, 60, 70, 85], borderColor: 'rgba(6,182,212,1)', backgroundColor: 'rgba(6,182,212,0.1)', fill: true, tension: 0.4, pointRadius: 3, pointBackgroundColor: 'rgba(6,182,212,1)' }
          ]
        },
        options: Object.assign({}, chartDefaults, { scales: { y: { beginAtZero: true, max: 100, grid: { color: 'rgba(0,0,0,0.05)' } }, x: { grid: { display: false } } } })
      });
    }
  }

  // ============================================
  // 10. NOTIFICATION HEADER BELL CLICK
  // ============================================
  var notifHeaderBtn = document.getElementById('notifHeaderBtn');
  if (notifHeaderBtn) {
    notifHeaderBtn.addEventListener('click', function () {
      var target = document.querySelector('[data-section="notifications"]');
      if (!target) target = document.getElementById('dashboard-notifications');
      if (target) {
        var offset = 80;
        var pos = target.getBoundingClientRect().top + window.scrollY - offset;
        window.scrollTo({ top: pos, behavior: 'smooth' });
      }
    });
  }

  // ============================================
  // 11. DASHBOARD SEARCH (header)
  // ============================================
  var dashSearchInput = document.querySelector('.dash-header__search-input');
  if (dashSearchInput) {
    dashSearchInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        var q = this.value.trim();
        if (q) {
          var oppSection = document.getElementById('dashboard-opportunities');
          if (oppSection) {
            var oppSearchField = document.getElementById('oppSearchInput');
            if (oppSearchField) oppSearchField.value = q;
            var offset = 80;
            var pos = oppSection.getBoundingClientRect().top + window.scrollY - offset;
            window.scrollTo({ top: pos, behavior: 'smooth' });
            setTimeout(function () { if (oppSearchField) oppSearchField.dispatchEvent(new Event('input')); }, 500);
          }
        }
      }
    });
  }

// ============================================
  // 12. MOBILE: HEADER SEARCH TOGGLE
  // ============================================
  var dashSearch = document.getElementById('dashSearch');

  // On mobile, clicking the search bar (or its icon) toggles expanded state
  if (dashSearch) {
    // Use click on the search bar element itself on mobile
    var searchIcon = dashSearch.querySelector('i');
    var searchInput = dashSearch.querySelector('.dash-header__search-input');

    function toggleSearch(e) {
      // If the input itself was clicked while already expanded, let it focus normally
      if (dashSearch.classList.contains('mobile-expanded') && e.target === searchInput) {
        return;
      }
      e.stopPropagation();
      var isExpanded = dashSearch.classList.contains('mobile-expanded');
      dashSearch.classList.remove('mobile-collapsed', 'mobile-expanded');
      if (!isExpanded) {
        dashSearch.classList.add('mobile-expanded');
        setTimeout(function () {
          if (searchInput) searchInput.focus();
        }, 100);
      } else {
        dashSearch.classList.add('mobile-collapsed');
      }
    }

    dashSearch.addEventListener('click', toggleSearch);

    // Close expanded search when clicking outside
    document.addEventListener('click', function (e) {
      if (dashSearch.classList.contains('mobile-expanded') &&
          !dashSearch.contains(e.target)) {
        dashSearch.classList.remove('mobile-expanded');
        dashSearch.classList.add('mobile-collapsed');
      }
    });
  }

  // ============================================
  // 13. MOBILE: OPPORTUNITY FILTERS TOGGLE
  // ============================================
  var oppFilterToggle = document.getElementById('oppFilterToggle');
  var oppFiltersContainer = document.getElementById('oppFiltersContainer');

  if (oppFilterToggle && oppFiltersContainer) {
    oppFilterToggle.addEventListener('click', function () {
      var isVisible = oppFiltersContainer.classList.contains('mobile-visible');
      oppFiltersContainer.classList.toggle('mobile-visible');
      this.classList.toggle('active');
      var icon = this.querySelector('i');
      if (icon) {
        icon.className = isVisible ? 'fas fa-chevron-down' : 'fas fa-chevron-up';
      }
    });
  }

// ============================================
  // 14. SIGN OUT CONFIRMATION MODAL
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

  console.log('%c Investhood IT Dashboard ', 'background: #06b6d4; color: white; font-size: 14px; font-weight: bold; padding: 6px 10px; border-radius: 4px;');

});
