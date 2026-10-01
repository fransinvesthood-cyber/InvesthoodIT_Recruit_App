/* ============================================================
   INVESTHOOD IT - Admin Dashboard Analytics (dynamic)
   ============================================================
   Connects the "Dashboard Analytics" section to the real database
   through admin/ajax_analytics.php. Every chart and statistic is
   rendered from the fetched payload - there are no hard-coded
   values. Supports loading / empty / error states and re-queries
   automatically whenever a filter or date range changes.
   ============================================================ */

(function () {
  'use strict';

  var section = document.getElementById('admin-analytics');
  if (!section) return;

  var cfg      = window.InvesthoodAnalyticsConfig || {};
  var endpoint = cfg.endpoint || ((window.APP_URL || '') + '/admin/ajax_analytics.php');
  var fullOptions = cfg.options || { cohorts: [], opportunities: [] };

  // Chart.js instances keyed by canvas id.
  var charts = {};
  var currentData = null;

  // ---- Palette (mirrors the dashboard chart tokens) ----
  var palette = {
    primary: 'rgba(26,86,219,0.85)',
    cyan:    'rgba(6,182,212,0.85)',
    success: 'rgba(16,185,129,0.85)',
    amber:   'rgba(245,158,11,0.85)',
    danger:  'rgba(239,68,68,0.85)',
    purple:  'rgba(124,58,237,0.85)',
    muted:   'rgba(148,163,184,0.85)',
    grid:    'rgba(127,127,127,0.18)',
    text:    '#64748b'
  };
  var doughnutPalette = [
    palette.primary, palette.cyan, palette.amber, palette.success,
    palette.purple, palette.danger, palette.muted, '#0ea5e9', '#f97316', '#14b8a6'
  ];

  var chartDefaults = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } }
  };

  function byId(id) { return document.getElementById(id); }

  function num(value) {
    var n = Number(value);
    return isNaN(n) ? 0 : n;
  }

  function formatNum(value) {
    return num(value).toLocaleString();
  }

  function hasData(values) {
    if (!values || !values.length) return false;
    for (var i = 0; i < values.length; i++) {
      if (num(values[i]) > 0) return true;
    }
    return false;
  }

  /* ---------------- Card state (loading / empty / error) --------------- */
  function cardState(canvasId, state, message) {
    var canvas = byId(canvasId);
    if (!canvas || !canvas.parentNode) return;
    var container = canvas.parentNode;
    var overlay = container.querySelector('.adm-analytics-state');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.className = 'adm-analytics-state';
      container.appendChild(overlay);
    }
    if (state === 'none') {
      overlay.style.display = 'none';
      return;
    }
    overlay.className = 'adm-analytics-state adm-analytics-state--' + state;
    overlay.style.display = 'flex';
    overlay.innerHTML = message || (state === 'empty' ? 'No data available' : '');
  }

  function setBusy(busy) {
    section.classList.toggle('is-busy', !!busy);
    var status = byId('analyticsStatus');
    if (status) {
      if (busy) {
        status.style.display = 'flex';
        status.className = 'adm-analytics-status adm-analytics-status--loading';
        status.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading analytics from the database...';
      } else {
        status.style.display = 'none';
      }
    }
    var controls = section.querySelectorAll('.admin-analytics-filters select, .admin-analytics-filters input, .admin-analytics-filters button');
    Array.prototype.forEach.call(controls, function (c) { c.disabled = !!busy; });
  }

  function showError(message) {
    var status = byId('analyticsStatus');
    if (status) {
      status.style.display = 'flex';
      status.className = 'adm-analytics-status adm-analytics-status--error';
      status.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + (message || 'Unable to load analytics.');
    }
  }

  /* ---------------- Chart upsert ---------------- */
  function upsertChart(canvasId, config) {
    var canvas = byId(canvasId);
    if (!canvas || typeof Chart === 'undefined') return;
    if (charts[canvasId]) {
      charts[canvasId].data = config.data;
      charts[canvasId].update('none');
      return;
    }
    charts[canvasId] = new Chart(canvas, config);
  }

  /* ---------------- Filter query params ---------------- */
  function buildQuery() {
    var params = new URLSearchParams();
    var period = byId('analyticsPeriod');
    var from   = byId('analyticsDateFrom');
    var to     = byId('analyticsDateTo');
    var prog   = byId('analyticsProgrammeFilter');
    var coh    = byId('analyticsCohortFilter');
    var opp    = byId('analyticsOpportunityFilter');
    var status = byId('analyticsStatusFilter');

    params.set('period', period ? period.value : '30d');
    if (from && from.value) params.set('date_from', from.value);
    if (to && to.value) params.set('date_to', to.value);
    if (prog && prog.value) params.set('programme_id', prog.value);
    if (coh && coh.value) params.set('cohort_id', coh.value);
    if (opp && opp.value) params.set('opportunity_id', opp.value);
    if (status && status.value) params.set('status', status.value);
    return params;
  }

  function fetchAnalytics() {
    var params = buildQuery();
    setBusy(true);
    return fetch(endpoint + '?' + params.toString(), {
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin'
    })
      .then(function (res) {
        return res.json().then(function (json) {
          return { ok: res.ok, json: json };
        });
      })
      .then(function (result) {
        if (!result.ok || !result.json || result.json.error) {
          throw new Error((result.json && result.json.error) || 'Analytics request failed.');
        }
        currentData = result.json.data;
        render(currentData);
      })
      .catch(function (err) {
        console.error('[Analytics]', err);
        showError('Analytics could not be loaded: ' + (err && err.message ? err.message : 'unknown error'));
      })
      .then(function () { setBusy(false); });
  }

  /* ---------------- Stat cards ---------------- */
  function setStat(numberId, trendId, value, suffix, trendHtml) {
    var numberEl = byId(numberId);
    if (numberEl) {
      numberEl.innerHTML = formatNum(value) +
        (suffix ? '<span class="admin-analytics-stat__suffix">' + suffix + '</span>' : '');
    }
    if (trendId) {
      var trendEl = byId(trendId);
      if (trendEl && trendHtml) trendEl.innerHTML = trendHtml;
    }
  }

  function renderStats(data) {
    var s = data.stats || {};
    setStat('analyticsStatTotalCandidates', 'analyticsStatTotalCandidatesTrend',
      s.total_candidates, '', '<i class="fas fa-users"></i> ' + formatNum(s.new_candidates) + ' new this period');
    setStat('analyticsStatTotalApplications', 'analyticsStatTotalApplicationsTrend',
      s.total_applications, '', '<i class="fas fa-file-alt"></i> ' + formatNum(s.new_applications) + ' this period');
    setStat('analyticsStatUnderReview', 'analyticsStatUnderReviewTrend',
      s.under_review, '', '<i class="fas fa-search"></i> submitted + reviewing');
    setStat('analyticsStatShortlisted', 'analyticsStatShortlistedTrend',
      s.shortlisted, '', '<i class="fas fa-star"></i> candidates');
    setStat('analyticsStatSelected', 'analyticsStatSelectedTrend',
      s.selected, '', '<i class="fas fa-check-circle"></i> candidates');
    setStat('analyticsStatWaitlisted', 'analyticsStatWaitlistedTrend',
      s.waitlisted, '', '<i class="fas fa-pause-circle"></i> on hold');
    setStat('analyticsStatRejected', 'analyticsStatRejectedTrend',
      s.rejected, '', '<i class="fas fa-times-circle"></i> applications');
    setStat('analyticsStatHiredPlaced', 'analyticsStatHiredPlacedTrend',
      s.hired_placed, '', '<i class="fas fa-handshake"></i> placed candidates');
    setStat('analyticsStatActiveProgrammes', 'analyticsStatActiveProgrammesTrend',
      s.active_programmes, '', '<i class="fas fa-check"></i> ' + formatNum(s.total_programmes) + ' total');
    setStat('analyticsStatActivePlacements', 'analyticsStatActivePlacementsTrend',
      s.active_placements, '', '<i class="fas fa-hourglass-half"></i> ' + formatNum(s.pending_placements) + ' pending');
    setStat('analyticsStatTalentPool', 'analyticsStatTalentPoolTrend',
      s.talent_pool, '', '<i class="fas fa-user-plus"></i> ' + formatNum(s.new_candidates) + ' new');
    setStat('analyticsStatCompletionRate', 'analyticsStatCompletionRateTrend',
      s.completion_rate, '%', '<i class="fas fa-flag-checkered"></i> placements completed');
    setStat('analyticsStatProfileCompleteness', 'analyticsStatProfileCompletenessTrend',
      s.profile_completeness, '%', '<i class="fas fa-id-card"></i> average completeness');
  }

  /* ---------------- Charts ---------------- */
  function renderCharts(data) {
    var c = data.charts || {};

    // 1. Programme Performance (horizontal bar)
    var prog = c.programme || { labels: [], values: [] };
    upsertChart('analyticsProgrammeChart', {
      type: 'bar',
      data: {
        labels: prog.labels,
        datasets: [{ label: 'Applications', data: prog.values, backgroundColor: palette.primary, borderRadius: 4, barThickness: 14 }]
      },
      options: Object.assign({}, chartDefaults, {
        indexAxis: 'y',
        scales: {
          x: { beginAtZero: true, grid: { color: palette.grid }, ticks: { font: { size: 9 } } },
          y: { grid: { display: false }, ticks: { font: { size: 9 } } }
        }
      })
    });
    cardState('analyticsProgrammeChart', hasData(prog.values) ? 'none' : 'empty', 'No applications linked to programmes yet');

    // 2. Application Statistics (doughnut)
    var app = c.application_status || { labels: [], values: [] };
    upsertChart('analyticsApplicationChart', {
      type: 'doughnut',
      data: {
        labels: app.labels,
        datasets: [{ data: app.values, backgroundColor: doughnutPalette, borderWidth: 0, hoverOffset: 6 }]
      },
      options: Object.assign({}, chartDefaults, {
        cutout: '60%',
        plugins: { legend: { display: true, position: 'bottom', labels: { boxWidth: 10, padding: 8, font: { size: 9 } } } }
      })
    });
    cardState('analyticsApplicationChart', hasData(app.values) ? 'none' : 'empty', 'No applications recorded yet');

    // 3. Placement Statistics (doughnut)
    var place = c.placement || { labels: [], values: [] };
    upsertChart('analyticsPlacementChart', {
      type: 'doughnut',
      data: {
        labels: place.labels,
        datasets: [{ data: place.values, backgroundColor: doughnutPalette, borderWidth: 0, hoverOffset: 6 }]
      },
      options: Object.assign({}, chartDefaults, {
        cutout: '65%',
        plugins: { legend: { display: true, position: 'bottom', labels: { boxWidth: 10, padding: 8, font: { size: 9 } } } }
      })
    });
    cardState('analyticsPlacementChart', hasData(place.values) ? 'none' : 'empty', 'No placements recorded yet');

    // 4. Talent Pool Analytics (radar - top candidate cities)
    var talent = c.talent || { labels: [], values: [] };
    upsertChart('analyticsTalentChart', {
      type: 'radar',
      data: {
        labels: talent.labels,
        datasets: [{
          label: 'Candidates',
          data: talent.values,
          backgroundColor: 'rgba(26,86,219,0.18)',
          borderColor: palette.primary,
          borderWidth: 2,
          pointBackgroundColor: palette.primary,
          pointRadius: 2
        }]
      },
      options: Object.assign({}, chartDefaults, {
        scales: {
          r: {
            beginAtZero: true,
            grid: { color: palette.grid },
            angleLines: { color: palette.grid },
            pointLabels: { font: { size: 9 } },
            ticks: { font: { size: 8 }, backdropColor: 'transparent' }
          }
        }
      })
    });
    cardState('analyticsTalentChart', hasData(talent.values) ? 'none' : 'empty', 'No candidate locations yet');

    // 5. Skills Supply & Demand (grouped bar)
    var skills = c.skills || { labels: [], supply: [], demand: [] };
    upsertChart('analyticsSkillsChart', {
      type: 'bar',
      data: {
        labels: skills.labels,
        datasets: [
          { label: 'Supply', data: skills.supply || [], backgroundColor: palette.primary, borderRadius: 3 },
          { label: 'Demand', data: skills.demand || [], backgroundColor: palette.amber, borderRadius: 3 }
        ]
      },
      options: Object.assign({}, chartDefaults, {
        plugins: { legend: { display: true, position: 'bottom', labels: { boxWidth: 10, padding: 8, font: { size: 9 } } } },
        scales: {
          x: { grid: { display: false }, ticks: { font: { size: 8 } } },
          y: { beginAtZero: true, grid: { color: palette.grid }, ticks: { font: { size: 9 } } }
        }
      })
    });
    cardState('analyticsSkillsChart', (hasData(skills.supply) || hasData(skills.demand)) ? 'none' : 'empty', 'No skills data yet');

    // 6. Application Trend (line - time based; replaces the former mock
    //    "Attendance Statistics" card - no attendance table exists).
    var trend = c.trend || { labels: [], values: [] };
    upsertChart('analyticsTrendChart', {
      type: 'line',
      data: {
        labels: trend.labels,
        datasets: [{
          label: 'Applications',
          data: trend.values,
          borderColor: palette.primary,
          backgroundColor: 'rgba(26,86,219,0.12)',
          borderWidth: 2,
          fill: true,
          tension: 0.35,
          pointRadius: 2
        }]
      },
      options: Object.assign({}, chartDefaults, {
        scales: {
          x: { grid: { display: false }, ticks: { font: { size: 8 }, maxRotation: 0, autoSkip: true } },
          y: { beginAtZero: true, grid: { color: palette.grid }, ticks: { font: { size: 9 } } }
        }
      })
    });
    cardState('analyticsTrendChart', hasData(trend.values) ? 'none' : 'empty', 'No application activity in this period');

    // 7. Candidate Growth (bar)
    var growth = c.growth || { labels: [], values: [] };
    upsertChart('analyticsGrowthChart', {
      type: 'bar',
      data: {
        labels: growth.labels,
        datasets: [{ label: 'New candidates', data: growth.values, backgroundColor: palette.cyan, borderRadius: 4 }]
      },
      options: Object.assign({}, chartDefaults, {
        scales: {
          x: { grid: { display: false }, ticks: { font: { size: 8 }, maxRotation: 0, autoSkip: true } },
          y: { beginAtZero: true, grid: { color: palette.grid }, ticks: { font: { size: 9 } } }
        }
      })
    });
    cardState('analyticsGrowthChart', hasData(growth.values) ? 'none' : 'empty', 'No new candidate registrations in this period');

    // 8. Learning & Assessment (doughnut - real pipeline stages)
    var learning = c.learning || { labels: [], values: [] };
    upsertChart('analyticsLearningChart', {
      type: 'doughnut',
      data: {
        labels: learning.labels,
        datasets: [{ data: learning.values, backgroundColor: doughnutPalette, borderWidth: 0, hoverOffset: 6 }]
      },
      options: Object.assign({}, chartDefaults, {
        cutout: '65%',
        plugins: { legend: { display: true, position: 'bottom', labels: { boxWidth: 10, padding: 8, font: { size: 9 } } } }
      })
    });
    cardState('analyticsLearningChart', hasData(learning.values) ? 'none' : 'empty', 'No assessment activity yet');

    // 9. Employment Outcomes (bar - placements by status)
    var outcome = c.outcome || { labels: [], values: [] };
    upsertChart('analyticsOutcomeChart', {
      type: 'bar',
      data: {
        labels: outcome.labels,
        datasets: [{ label: 'Placements', data: outcome.values, backgroundColor: palette.success, borderRadius: 4 }]
      },
      options: Object.assign({}, chartDefaults, {
        scales: {
          x: { grid: { display: false }, ticks: { font: { size: 8 }, maxRotation: 0, autoSkip: true } },
          y: { beginAtZero: true, grid: { color: palette.grid }, ticks: { font: { size: 9 } } }
        }
      })
    });
    cardState('analyticsOutcomeChart', hasData(outcome.values) ? 'none' : 'empty', 'No employment outcomes yet');

    // 10. Recruitment Funnel (horizontal bar - real pipeline counts)
    var funnel = c.funnel || { labels: [], values: [] };
    upsertChart('analyticsFunnelChart', {
      type: 'bar',
      data: {
        labels: funnel.labels,
        datasets: [{ label: 'Candidates', data: funnel.values, backgroundColor: [palette.primary, palette.cyan, palette.amber, palette.purple, palette.success, palette.success], borderRadius: 4, barThickness: 12 }]
      },
      options: Object.assign({}, chartDefaults, {
        indexAxis: 'y',
        scales: {
          x: { beginAtZero: true, grid: { color: palette.grid }, ticks: { font: { size: 9 } } },
          y: { grid: { display: false }, ticks: { font: { size: 9 } } }
        }
      })
    });
    cardState('analyticsFunnelChart', hasData(funnel.values) ? 'none' : 'empty', 'No recruitment activity yet');

    // 11. Opportunity Activity (bar - applications per opportunity)
    var opp = c.opportunity || { labels: [], values: [] };
    upsertChart('analyticsOpportunityChart', {
      type: 'bar',
      data: {
        labels: opp.labels,
        datasets: [{ label: 'Applications', data: opp.values, backgroundColor: palette.amber, borderRadius: 4 }]
      },
      options: Object.assign({}, chartDefaults, {
        indexAxis: 'y',
        scales: {
          x: { beginAtZero: true, grid: { color: palette.grid }, ticks: { font: { size: 9 } } },
          y: { grid: { display: false }, ticks: { font: { size: 9 } } }
        }
      })
    });
    cardState('analyticsOpportunityChart', hasData(opp.values) ? 'none' : 'empty', 'No opportunity applications yet');
    renderOpportunityList(data);

    // 12. Interview Analytics (bar - interviews by status)
    var iv = c.interview || { labels: [], values: [] };
    upsertChart('analyticsInterviewChart', {
      type: 'bar',
      data: {
        labels: iv.labels,
        datasets: [{ label: 'Interviews', data: iv.values, backgroundColor: palette.cyan, borderRadius: 4 }]
      },
      options: Object.assign({}, chartDefaults, {
        scales: {
          x: { grid: { display: false }, ticks: { font: { size: 8 }, maxRotation: 0, autoSkip: true } },
          y: { beginAtZero: true, grid: { color: palette.grid }, ticks: { font: { size: 9 } } }
        }
      })
    });
    cardState('analyticsInterviewChart', hasData(iv.values) ? 'none' : 'empty', 'No interviews recorded yet');
    renderInterviewSummary(data);

    // 13. Selection & Offers (bar)
    var sel = c.selection || { labels: [], values: [] };
    upsertChart('analyticsSelectionChart', {
      type: 'bar',
      data: {
        labels: sel.labels,
        datasets: [{ label: 'Candidates', data: sel.values, backgroundColor: palette.purple, borderRadius: 4 }]
      },
      options: Object.assign({}, chartDefaults, {
        scales: {
          x: { grid: { display: false }, ticks: { font: { size: 8 }, maxRotation: 0, autoSkip: true } },
          y: { beginAtZero: true, grid: { color: palette.grid }, ticks: { font: { size: 9 } } }
        }
      })
    });
    cardState('analyticsSelectionChart', hasData(sel.values) ? 'none' : 'empty', 'No selection decisions yet');

    // 14. Placements by Programme (horizontal bar)
    var pp = c.placement_programme || { labels: [], values: [] };
    upsertChart('analyticsPlacementProgrammeChart', {
      type: 'bar',
      data: {
        labels: pp.labels,
        datasets: [{ label: 'Placements', data: pp.values, backgroundColor: palette.success, borderRadius: 4, barThickness: 14 }]
      },
      options: Object.assign({}, chartDefaults, {
        indexAxis: 'y',
        scales: {
          x: { beginAtZero: true, grid: { color: palette.grid }, ticks: { font: { size: 9 } } },
          y: { grid: { display: false }, ticks: { font: { size: 9 } } }
        }
      })
    });
    cardState('analyticsPlacementProgrammeChart', hasData(pp.values) ? 'none' : 'empty', 'No placements linked to programmes yet');

    // 15. Placements by Cohort (horizontal bar)
    var pc = c.placement_cohort || { labels: [], values: [] };
    upsertChart('analyticsPlacementCohortChart', {
      type: 'bar',
      data: {
        labels: pc.labels,
        datasets: [{ label: 'Placements', data: pc.values, backgroundColor: palette.primary, borderRadius: 4, barThickness: 14 }]
      },
      options: Object.assign({}, chartDefaults, {
        indexAxis: 'y',
        scales: {
          x: { beginAtZero: true, grid: { color: palette.grid }, ticks: { font: { size: 9 } } },
          y: { grid: { display: false }, ticks: { font: { size: 9 } } }
        }
      })
    });
    cardState('analyticsPlacementCohortChart', hasData(pc.values) ? 'none' : 'empty', 'No placements linked to cohorts yet');
  }

  /* ---------------- Lists under cards ---------------- */
  function escHtml(value) {
    return String(value === null || value === undefined ? '' : value)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function renderOpportunityList(data) {
    var box = byId('analyticsOpportunityList');
    if (!box) return;
    var opp = (data && data.opportunity) || {};
    var rows = opp.recent || [];
    var s = (data && data.stats) || {};
    var head = '<div class="adm-analytics-list__head"><span>'
      + formatNum(s.total_opportunities || 0) + ' total · '
      + formatNum(s.active_opportunities || 0) + ' active · '
      + formatNum(s.closed_opportunities || 0) + ' closed</span></div>';
    if (!rows.length) {
      box.innerHTML = head + '<div class="adm-analytics-list__empty">No opportunities yet</div>';
      return;
    }
    var html = head + '<ul class="adm-analytics-list__items">';
    rows.slice(0, 5).forEach(function (r) {
      html += '<li><span class="adm-analytics-list__title">' + escHtml(r.title || 'Untitled')
        + '</span><span class="adm-analytics-list__meta">' + escHtml(r.status || '')
        + ' · ' + formatNum(r.applications || 0) + ' apps</span></li>';
    });
    box.innerHTML = html + '</ul>';
  }

  function renderInterviewSummary(data) {
    var box = byId('analyticsInterviewSummary');
    if (!box) return;
    var s = (data && data.stats) || {};
    box.innerHTML = '<div class="adm-analytics-list__head"><span>'
      + formatNum(s.total_interviews || 0) + ' total · '
      + formatNum(s.upcoming_interviews || 0) + ' upcoming · '
      + formatNum(s.completed_interviews || 0) + ' completed · '
      + formatNum(s.cancelled_interviews || 0) + ' cancelled/rescheduled</span></div>';
  }

  /* ---------------- Dependent filter selects ---------------- */
  function populateCohorts() {
    var prog = byId('analyticsProgrammeFilter');
    var cohortSel = byId('analyticsCohortFilter');
    if (!cohortSel) return;
    var progId = prog ? prog.value : '';
    var prev = cohortSel.value;

    cohortSel.innerHTML = '<option value="">All Cohorts</option>';
    (fullOptions.cohorts || []).forEach(function (c) {
      if (progId && String(c.programme_id) !== String(progId)) return;
      var opt = document.createElement('option');
      opt.value = c.id;
      opt.textContent = c.name;
      cohortSel.appendChild(opt);
    });
    if (prev) cohortSel.value = prev;
  }

  function populateOpportunities() {
    var prog = byId('analyticsProgrammeFilter');
    var cohortSel = byId('analyticsCohortFilter');
    var oppSel = byId('analyticsOpportunityFilter');
    if (!oppSel) return;
    var progId = prog ? prog.value : '';
    var cohortId = cohortSel ? cohortSel.value : '';
    var prev = oppSel.value;

    oppSel.innerHTML = '<option value="">All Opportunities</option>';
    (fullOptions.opportunities || []).forEach(function (o) {
      if (progId && String(o.programme_id) !== String(progId)) return;
      if (cohortId && String(o.cohort_id) !== String(cohortId)) return;
      var opt = document.createElement('option');
      opt.value = o.id;
      opt.textContent = o.title;
      oppSel.appendChild(opt);
    });
    if (prev) oppSel.value = prev;
  }

  function toggleCustomDates() {
    var period = byId('analyticsPeriod');
    var wrap = byId('analyticsCustomRange');
    if (!period || !wrap) return;
    wrap.style.display = (period.value === 'custom') ? 'flex' : 'none';
  }

  /* Category select filters which analytics cards are visible. */
  function applyCategory() {
    var cat = byId('analyticsCategory');
    var value = cat ? cat.value : 'all';
    var map = {
      programmes:   ['analyticsProgrammeChart', 'analyticsPlacementProgrammeChart', 'analyticsPlacementCohortChart'],
      applications: ['analyticsApplicationChart', 'analyticsTrendChart', 'analyticsGrowthChart', 'analyticsLearningChart', 'analyticsFunnelChart', 'analyticsOpportunityChart', 'analyticsInterviewChart', 'analyticsSelectionChart'],
      placements:   ['analyticsPlacementChart', 'analyticsOutcomeChart', 'analyticsPlacementProgrammeChart', 'analyticsPlacementCohortChart'],
      talent:       ['analyticsTalentChart'],
      skills:       ['analyticsSkillsChart']
    };
    var cards = section.querySelectorAll('.admin-analytics-card');
    Array.prototype.forEach.call(cards, function (card) {
      var canvas = card.querySelector('canvas');
      if (!canvas) return;
      var show = (value === 'all') || (map[value] && map[value].indexOf(canvas.id) !== -1);
      card.style.display = show ? '' : 'none';
    });
    Object.keys(charts).forEach(function (key) {
      if (charts[key] && typeof charts[key].resize === 'function') charts[key].resize();
    });
  }

  /* ---------------- Render ---------------- */
  function render(data) {
    renderStats(data);
    renderCharts(data);
  }

  /* ---------------- CSV export (real data only) ---------------- */
  function exportCsv() {
    if (!currentData) {
      if (window.InvesthoodNotifications) {
        window.InvesthoodNotifications.show('info', 'Nothing to export', 'Analytics are still loading.');
      }
      return;
    }
    var s = currentData.summary || {};
    var st = currentData.stats || {};
    var rows = [['Metric', 'Value']];

    var summary = [
      ['Total Candidates', s.total_candidates],
      ['New Candidates (period)', s.new_candidates],
      ['Total Applications', s.total_applications],
      ['New Applications (' + currentData.range.from + ' to ' + currentData.range.to + ')', s.new_applications],
      ['Under Review', s.under_review],
      ['Shortlisted', s.shortlisted],
      ['Selected', s.selected],
      ['Waitlisted', s.waitlisted],
      ['Rejected', s.rejected],
      ['Hired / Placed', s.hired_placed],
      ['Total Programmes', st.total_programmes],
      ['Active Programmes', st.active_programmes],
      ['Completed Programmes', st.completed_programmes],
      ['Total Cohorts', st.total_cohorts],
      ['Active Cohorts', st.active_cohorts],
      ['Enrolled Participants', st.enrolled],
      ['Total Opportunities', st.total_opportunities],
      ['Active Opportunities', st.active_opportunities],
      ['Closed Opportunities', st.closed_opportunities],
      ['Total Interviews', st.total_interviews],
      ['Upcoming Interviews', st.upcoming_interviews],
      ['Completed Interviews', st.completed_interviews],
      ['Cancelled/Rescheduled Interviews', st.cancelled_interviews],
      ['Offers Generated', st.offers_generated],
      ['Offers Accepted', st.offers_accepted],
      ['Offers Declined', st.offers_declined],
      ['Pending Offers', st.offers_pending],
      ['Total Placements', st.total_placements],
      ['Active Placements', st.active_placements],
      ['Pending Placements', st.pending_placements],
      ['Completed Placements', st.completed_placements],
      ['Awaiting Placement', st.awaiting_placement],
      ['Completion Rate (%)', st.completion_rate],
      ['Profile Completeness (%)', st.profile_completeness]
    ];
    summary.forEach(function (r) { rows.push(r); });

    var chartMap = [
      ['Applications by Status', currentData.charts.application_status],
      ['Applications by Programme', currentData.charts.programme],
      ['Applications by Opportunity', currentData.charts.opportunity],
      ['Interviews by Status', currentData.charts.interview],
      ['Selection & Offers', currentData.charts.selection],
      ['Placements by Status', currentData.charts.placement],
      ['Placements by Programme', currentData.charts.placement_programme],
      ['Placements by Cohort', currentData.charts.placement_cohort],
      ['Candidates by City', currentData.charts.talent],
      ['Application Trend', currentData.charts.trend],
      ['Candidate Growth', currentData.charts.growth]
    ];
    chartMap.forEach(function (entry) {
      var series = entry[1] || {};
      var labels = series.labels || [];
      var values = series.values || [];
      rows.push([]);
      rows.push([entry[0], '']);
      labels.forEach(function (label, i) { rows.push([label, num(values[i])]); });
    });

    var csv = rows.map(function (r) {
      return r.map(function (cell) {
        var v = (cell === null || cell === undefined) ? '' : String(cell);
        return '"' + v.replace(/"/g, '""') + '"';
      }).join(',');
    }).join('\r\n');

    var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    var link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'investhood-analytics-' + currentData.range.from + '_to_' + currentData.range.to + '.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(link.href);

    if (window.InvesthoodNotifications) {
      window.InvesthoodNotifications.show('success', 'Export Complete', 'Analytics exported to CSV.');
    }
  }

  /* ---------------- Init + wiring ---------------- */
  function init() {
    populateCohorts();
    populateOpportunities();
    toggleCustomDates();
    applyCategory();

    function on(id, evt, fn) {
      var el = byId(id);
      if (el) el.addEventListener(evt, fn);
    }

    on('analyticsPeriod', 'change', function () { toggleCustomDates(); fetchAnalytics(); });
    on('analyticsDateFrom', 'change', fetchAnalytics);
    on('analyticsDateTo', 'change', fetchAnalytics);
    on('analyticsProgrammeFilter', 'change', function () { populateCohorts(); populateOpportunities(); fetchAnalytics(); });
    on('analyticsCohortFilter', 'change', function () { populateOpportunities(); fetchAnalytics(); });
    on('analyticsOpportunityFilter', 'change', fetchAnalytics);
    on('analyticsStatusFilter', 'change', fetchAnalytics);
    on('analyticsCategory', 'change', applyCategory);
    on('analyticsRefreshBtn', 'click', fetchAnalytics);
    on('analyticsExportBtn', 'click', exportCsv);

    // Initial load.
    fetchAnalytics();

    // Periodic refresh so the section automatically tracks live data.
    setInterval(function () {
      if (document.hidden) return;
      if (section.classList.contains('is-busy')) return;
      fetchAnalytics();
    }, 60000);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
