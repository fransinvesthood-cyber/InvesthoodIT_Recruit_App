<?php
/**
 * ================================================
 * INVESTHOOD IT - Opportunity Details Page
 * ================================================
 * Management workspace for a single opportunity:
 * overview, description, responsibilities, requirements,
 * skills, qualifications, documents, programme/cohort info,
 * and application statistics.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('admin');

$id = (int) ($_GET['id'] ?? 0);
$opportunity = Opportunity::find($id);
if (!$opportunity) {
    set_flash('error', 'Not Found', 'The requested opportunity does not exist.');
    safe_redirect('admin/opportunities.php');
}

$user = current_user();

// Effective status (auto-close if past closing date)
$today = date('Y-m-d');
$effectiveStatus = $opportunity['status'];
if (in_array($opportunity['status'], ['published', 'closing_soon'], true)
    && !empty($opportunity['application_close_date'])
    && $opportunity['application_close_date'] < $today) {
    $effectiveStatus = 'closed';
}

// Load config
$elig = OpportunityEligibility::forOpportunity($id) ?? [];
$skills = OpportunitySkill::forOpportunity($id);
$docs = OpportunityDocument::forOpportunity($id);
$responsibilities = OpportunityResponsibility::forOpportunity($id);

// Application statistics (zeroed until Application module is built)
$appStats = Opportunity::applicationStats($id);
$selectedCount = (int) $appStats['selected'];
$applicationsReceived = (int) $appStats['applications'];
$availablePositions = (int) $opportunity['available_positions'];
$remainingCapacity = max(0, $availablePositions - $selectedCount);

$flashes = render_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Opportunity Details - Investhood IT Administrator">
  <title><?= e($opportunity['title']) ?> | Investhood IT Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
  <link rel="stylesheet" href="<?= url('css/admin_programmes.css') ?>">
  <link rel="stylesheet" href="<?= url('css/admin_opportunities.css') ?>">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
</head>
<body class="dashboard-page admin-dashboard pm-module opp-module">

  <div class="dashboard">
    <aside class="sidebar admin-sidebar" id="adminSidebar">
      <div class="sidebar__header">
        <a href="<?= url('index.php') ?>" class="logo"><span class="logo__icon"><i class="fas fa-code"></i></span><span class="logo__text">Investhood <span class="logo__accent">IT</span></span></a>
        <button class="sidebar__close" id="sidebarClose" aria-label="Close sidebar"><i class="fas fa-times"></i></button>
      </div>
      <nav class="sidebar__nav">
        <div class="sidebar__section-label">Command Centre</div>
        <ul class="sidebar__menu"><li><a href="<?= url('admin/dashboard.php') ?>" class="sidebar__link"><i class="fas fa-th-large"></i> Executive Overview</a></li></ul>
        <div class="sidebar__section-label">Management</div>
        <ul class="sidebar__menu">
          <li><a href="<?= url('admin/programmes.php') ?>" class="sidebar__link"><i class="fas fa-graduation-cap"></i> Programmes</a></li>
          <li><a href="<?= url('admin/opportunities.php') ?>" class="sidebar__link active"><i class="fas fa-briefcase"></i> Opportunities</a></li>
        </ul>
      </nav>
      <div class="sidebar__footer">
        <div class="sidebar__user">
          <div class="sidebar__user-avatar"><img src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'Admin+User') ?>&background=1a56db&color=fff&size=80" alt="Profile"></div>
          <div class="sidebar__user-info"><span class="sidebar__user-name"><?= e($user['fullname'] ?? 'Admin') ?></span><span class="sidebar__user-role">Administrator</span></div>
        </div>
        <a href="<?= url('auth/logout.php') ?>" class="sidebar__logout"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
      </div>
    </aside>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="dashboard__main">
      <header class="dash-header admin-dash-header">
        <div class="dash-header__left">
          <button class="dash-header__toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="fas fa-bars"></i></button>
          <a href="<?= url('admin/opportunities.php') ?>" class="btn btn--ghost btn--sm"><i class="fas fa-arrow-left"></i> Back to Opportunities</a>
        </div>
        <div class="dash-header__right">
          <a href="<?= url('admin/opportunity_edit.php?id=' . $id) ?>" class="btn btn--ghost btn--sm"><i class="fas fa-edit"></i> Edit</a>
          <a href="<?= url('admin/opportunity_create.php') ?>" class="btn btn--primary btn--sm"><i class="fas fa-plus"></i> Create Opportunity</a>
          <div class="dash-header__user"><img src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'Admin+User') ?>&background=1a56db&color=fff&size=80" alt="Profile" class="dash-header__avatar"></div>
        </div>
      </header>

      <div class="dash-content">

        <!-- ===== OPPORTUNITY HEADER ===== -->
        <section class="pm-detail-header">
          <div class="pm-detail-header__main">
            <div class="pm-detail-header__top">
              <span class="pm-card__type"><?= e(OPPORTUNITY_TYPE_LABELS[$opportunity['type']] ?? ucfirst(str_replace('_', ' ', $opportunity['type']))) ?></span>
              <?= status_badge($effectiveStatus, OPPORTUNITY_STATUS_LABELS[$effectiveStatus] ?? ucfirst(str_replace('_', ' ', $effectiveStatus))) ?>
              <?php if ($effectiveStatus !== $opportunity['status']): ?>
                <span class="tag tag--amber">Auto-closed</span>
              <?php endif; ?>
            </div>
            <h1 class="pm-detail-header__title"><?= e($opportunity['title']) ?></h1>
            <p class="pm-detail-header__dates">
              <i class="fas fa-calendar-alt"></i>
              <?= e(!empty($opportunity['application_open_date']) ? format_date($opportunity['application_open_date'], 'd M Y') : 'Open TBD') ?>
              — <?= e(!empty($opportunity['application_close_date']) ? format_date($opportunity['application_close_date'], 'd M Y') : 'Close TBD') ?>
            </p>
          </div>
          <div class="pm-detail-header__actions">
            <?php if ($effectiveStatus === 'draft'): ?>
              <form method="post" action="<?= url('admin/opportunity_actions.php') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="publish">
                <input type="hidden" name="id" value="<?= (int) $id ?>">
                <button type="submit" class="btn btn--primary"><i class="fas fa-rocket"></i> Publish</button>
              </form>
            <?php elseif (in_array($effectiveStatus, ['published', 'closing_soon'], true)): ?>
              <form method="post" action="<?= url('admin/opportunity_actions.php') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="close">
                <input type="hidden" name="id" value="<?= (int) $id ?>">
                <button type="submit" class="btn btn--primary"><i class="fas fa-times-circle"></i> Close</button>
              </form>
            <?php endif; ?>
            <form method="post" action="<?= url('admin/opportunity_actions.php') ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="duplicate">
              <input type="hidden" name="id" value="<?= (int) $id ?>">
              <button type="submit" class="btn btn--ghost"><i class="fas fa-copy"></i> Duplicate</button>
            </form>
            <?php if (in_array($effectiveStatus, ['draft', 'published', 'closed', 'closing_soon'], true)): ?>
            <form method="post" action="<?= url('admin/opportunity_actions.php') ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="archive">
              <input type="hidden" name="id" value="<?= (int) $id ?>">
              <button type="submit" class="btn btn--ghost"><i class="fas fa-archive"></i> Archive</button>
            </form>
            <?php endif; ?>
          </div>
        </section>

        <!-- ===== SUMMARY CARDS ===== -->
        <section class="pm-summary">
          <div class="pm-summary-card"><span class="pm-summary-card__value"><?= (int) $availablePositions ?></span><span class="pm-summary-card__label">Available Positions</span></div>
          <div class="pm-summary-card"><span class="pm-summary-card__value"><?= (int) $applicationsReceived ?></span><span class="pm-summary-card__label">Applications</span></div>
          <div class="pm-summary-card"><span class="pm-summary-card__value"><?= (int) $selectedCount ?></span><span class="pm-summary-card__label">Selected</span></div>
          <div class="pm-summary-card"><span class="pm-summary-card__value"><?= (int) $remainingCapacity ?></span><span class="pm-summary-card__label">Remaining Capacity</span></div>
        </section>

        <!-- ===== TABS ===== -->
        <section class="pm-workspace">
          <nav class="pm-tabs" id="oppTabs">
            <button class="pm-tab active" data-tab="overview"><i class="fas fa-th-large"></i> Overview</button>
            <button class="pm-tab" data-tab="requirements"><i class="fas fa-clipboard-list"></i> Requirements</button>
            <button class="pm-tab" data-tab="skills"><i class="fas fa-code"></i> Skills <span class="pm-tab__count"><?= (int) count($skills) ?></span></button>
            <button class="pm-tab" data-tab="documents"><i class="fas fa-file-alt"></i> Documents <span class="pm-tab__count"><?= (int) count($docs) ?></span></button>
            <button class="pm-tab" data-tab="applications"><i class="fas fa-file-signature"></i> Applications</button>
            <button class="pm-tab" data-tab="activity"><i class="fas fa-history"></i> Activity</button>
          </nav>

          <div class="pm-tab-panels">

            <!-- OVERVIEW PANEL -->
            <div class="pm-tab-panel is-active" id="tab-overview">
              <div class="pm-overview-grid">
                <div class="pm-card-block pm-card-block--details">
                  <h3 class="pm-card-block__title"><i class="fas fa-info-circle"></i> Opportunity Overview</h3>
                  <p class="pm-card-block__text"><?= nl2br(e($opportunity['full_description'] ?? $opportunity['short_description'] ?? 'No description provided.')) ?></p>
                </div>
                <div class="pm-card-block">
                  <h3 class="pm-card-block__title"><i class="fas fa-briefcase"></i> Programme Information</h3>
                  <dl class="pm-details-list">
                    <div><dt>Programme</dt><dd><?= e($opportunity['programme_name'] ?? '—') ?></dd></div>
                    <div><dt>Type</dt><dd><?= e(programme_type_label($opportunity['programme_type'] ?? $opportunity['type'])) ?></dd></div>
                    <div><dt>Programme Status</dt><dd><?= e(PROGRAMME_STATUS_LABELS[$opportunity['programme_status'] ?? ''] ?? '—') ?></dd></div>
                    <div><dt>Programme Dates</dt><dd><?= e(!empty($opportunity['programme_start']) ? format_date($opportunity['programme_start'], 'd M Y') : '—') ?> → <?= e(!empty($opportunity['programme_end']) ? format_date($opportunity['programme_end'], 'd M Y') : '—') ?></dd></div>
                  </dl>
                </div>
                <div class="pm-card-block">
                  <h3 class="pm-card-block__title"><i class="fas fa-layer-group"></i> Cohort Information</h3>
                  <?php if ($opportunity['cohort_id']): ?>
                  <dl class="pm-details-list">
                    <div><dt>Cohort</dt><dd><?= e($opportunity['cohort_name'] ?? '—') ?></dd></div>
                    <div><dt>Cohort Status</dt><dd><?= e(COHORT_STATUS_LABELS[$opportunity['cohort_status'] ?? ''] ?? '—') ?></dd></div>
                    <div><dt>Cohort Dates</dt><dd><?= e(!empty($opportunity['cohort_start']) ? format_date($opportunity['cohort_start'], 'd M Y') : '—') ?> → <?= e(!empty($opportunity['cohort_end']) ? format_date($opportunity['cohort_end'], 'd M Y') : '—') ?></dd></div>
                  </dl>
                  <?php else: ?>
                  <p class="pm-card-block__text">This opportunity is programme-wide (no specific cohort).</p>
                  <?php endif; ?>
                </div>
                <div class="pm-card-block pm-card-block--details">
                  <h3 class="pm-card-block__title"><i class="fas fa-list"></i> Opportunity Details</h3>
                  <dl class="pm-details-list">
                    <div><dt>Type</dt><dd><?= e(OPPORTUNITY_TYPE_LABELS[$opportunity['type']] ?? ucfirst(str_replace('_', ' ', $opportunity['type']))) ?></dd></div>
                    <div><dt>Organisation</dt><dd><?= e($opportunity['organisation'] ?: '—') ?></dd></div>
                    <div><dt>Status</dt><dd><?= e(OPPORTUNITY_STATUS_LABELS[$effectiveStatus] ?? ucfirst(str_replace('_', ' ', $effectiveStatus))) ?></dd></div>
                    <div><dt>Province</dt><dd><?= e($opportunity['province'] ? ucwords(str_replace('-', ' ', $opportunity['province'])) : '—') ?></dd></div>
                    <div><dt>City</dt><dd><?= e($opportunity['city'] ?: '—') ?></dd></div>
                    <div><dt>Physical Location</dt><dd><?= e($opportunity['physical_location'] ?: '—') ?></dd></div>
                    <div><dt>Work Arrangement</dt><dd><?= e(OPPORTUNITY_WORK_ARRANGEMENT_LABELS[$opportunity['work_arrangement']] ?? ucfirst($opportunity['work_arrangement'])) ?></dd></div>
                    <div><dt>Opening Date</dt><dd><?= e(!empty($opportunity['application_open_date']) ? format_date($opportunity['application_open_date'], 'd M Y') : '—') ?></dd></div>
                    <div><dt>Closing Date</dt><dd><?= e(!empty($opportunity['application_close_date']) ? format_date($opportunity['application_close_date'], 'd M Y') : '—') ?></dd></div>
                    <div><dt>Programme Start</dt><dd><?= e(!empty($opportunity['start_date']) ? format_date($opportunity['start_date'], 'd M Y') : '—') ?></dd></div>
                    <div><dt>Programme End</dt><dd><?= e(!empty($opportunity['end_date']) ? format_date($opportunity['end_date'], 'd M Y') : '—') ?></dd></div>
                    <div><dt>Available Positions</dt><dd><?= (int) $opportunity['available_positions'] ?></dd></div>
                    <div><dt>Age Range</dt><dd><?= e($opportunity['min_age'] ?: '—') ?> – <?= e($opportunity['max_age'] ?: '—') ?></dd></div>
                    <div><dt>Applications</dt><dd><?= (int) $opportunity['applications_count'] ?></dd></div>
                    <div><dt>Created</dt><dd><?= e(format_date($opportunity['created_at'], 'd M Y')) ?></dd></div>
                  </dl>
                </div>
              </div>
            </div>

            <!-- REQUIREMENTS PANEL -->
            <div class="pm-tab-panel" id="tab-requirements">
              <div class="pm-panel-header">
                <h3 class="pm-panel-title">Requirements & Eligibility</h3>
                <p class="pm-panel-sub">Eligibility, responsibilities and duties for this opportunity.</p>
              </div>
              <div class="pm-overview-grid">
                <div class="pm-card-block">
                  <h3 class="pm-card-block__title"><i class="fas fa-clipboard-list"></i> Qualification Requirements</h3>
                  <p class="pm-card-block__text"><?= nl2br(e($elig['qualification_requirements'] ?? 'Not specified.')) ?></p>
                </div>
                <div class="pm-card-block">
                  <h3 class="pm-card-block__title"><i class="fas fa-briefcase"></i> Experience</h3>
                  <p class="pm-card-block__text"><?= nl2br(e($elig['min_experience'] ?? 'Not specified.')) ?></p>
                </div>
                <div class="pm-card-block">
                  <h3 class="pm-card-block__title"><i class="fas fa-check-circle"></i> Required Skills</h3>
                  <p class="pm-card-block__text"><?= nl2br(e($elig['required_skills'] ?? 'Not specified.')) ?></p>
                </div>
                <div class="pm-card-block">
                  <h3 class="pm-card-block__title"><i class="fas fa-star"></i> Preferred Skills</h3>
                  <p class="pm-card-block__text"><?= nl2br(e($elig['preferred_skills'] ?? 'Not specified.')) ?></p>
                </div>
                <div class="pm-card-block">
                  <h3 class="pm-card-block__title"><i class="fas fa-clock"></i> Availability Requirements</h3>
                  <p class="pm-card-block__text"><?= nl2br(e($elig['availability_requirements'] ?? 'Not specified.')) ?></p>
                </div>
                <div class="pm-card-block">
                  <h3 class="pm-card-block__title"><i class="fas fa-info"></i> Other Requirements</h3>
                  <p class="pm-card-block__text"><?= nl2br(e($elig['other_requirements'] ?? 'Not specified.')) ?></p>
                </div>
                <div class="pm-card-block pm-card-block--details">
                  <h3 class="pm-card-block__title"><i class="fas fa-tasks"></i> Responsibilities & Duties</h3>
                  <?php
                  $sections = [
                    'key_responsibilities' => 'Key Responsibilities',
                    'duties'               => 'Duties',
                    'programme_activities' => 'Programme Activities',
                    'learning_outcomes'    => 'Learning Outcomes',
                  ];
                  foreach ($sections as $rk => $rl):
                    $items = $responsibilities[$rk] ?? [];
                  ?>
                    <h4 style="margin:0.75rem 0 0.3rem;font-size:0.9rem;color:var(--text,#111827);"><?= e($rl) ?></h4>
                    <?php if (empty($items)): ?>
                      <p class="pm-muted" style="margin:0 0 0.5rem;">Not specified.</p>
                    <?php else: foreach ($items as $item): ?>
                      <p class="pm-card-block__text">• <?= nl2br(e($item['content'])) ?></p>
                    <?php endforeach; endif; ?>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>

            <!-- SKILLS PANEL -->
            <div class="pm-tab-panel" id="tab-skills">
              <div class="pm-panel-header">
                <h3 class="pm-panel-title">Skills</h3>
                <p class="pm-panel-sub">Required and preferred skills for this opportunity.</p>
              </div>
              <?php if (empty($skills)): ?>
                <p class="pm-muted">No skills have been configured for this opportunity yet.</p>
              <?php else: ?>
                <div class="pm-skills-categories">
                  <?php foreach (OPPORTUNITY_SKILL_CATEGORIES as $catKey => $catLabel):
                    $catSkills = array_values(array_filter($skills, fn($s) => $s['skill_category'] === $catKey));
                  ?>
                  <div class="pm-skills-cat">
                    <label class="pm-form-label"><?= e($catLabel) ?></label>
                    <?php if (empty($catSkills)): ?>
                      <p class="pm-muted">—</p>
                    <?php else: foreach ($catSkills as $sk): ?>
                      <div class="pm-tag-chip"><i class="fas fa-check-circle"></i> <?= e($sk['skill_name']) ?></div>
                    <?php endforeach; endif; ?>
                  </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>

            <!-- DOCUMENTS PANEL -->
            <div class="pm-tab-panel" id="tab-documents">
              <div class="pm-panel-header">
                <h3 class="pm-panel-title">Required Documents</h3>
                <p class="pm-panel-sub">Documents applicants must provide (required or optional).</p>
              </div>
              <?php if (empty($docs)): ?>
                <p class="pm-muted">No document requirements have been configured for this opportunity.</p>
              <?php else: ?>
                <div class="pm-docs-list">
                  <?php foreach ($docs as $d): ?>
                  <div class="pm-doc-item">
                    <span class="pm-doc-item__icon"><i class="fas fa-file-alt"></i></span>
                    <div class="pm-doc-item__info">
                      <strong><?= e($d['document_name']) ?></strong>
                      <div class="pm-doc-item__tags">
                        <span class="tag tag--<?= (int) $d['is_required'] === 1 ? 'green' : 'gray' ?>"><?= (int) $d['is_required'] === 1 ? 'Required' : 'Optional' ?></span>
                      </div>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>

            <!-- APPLICATIONS PANEL -->
            <div class="pm-tab-panel" id="tab-applications">
              <div class="pm-panel-header">
                <h3 class="pm-panel-title">Application Statistics</h3>
                <p class="pm-panel-sub">Live application metrics. The Application module is not yet implemented, so these will populate once candidate applications are enabled.</p>
              </div>
              <div class="pm-summary">
                <div class="pm-summary-card"><span class="pm-summary-card__value"><?= (int) $applicationsReceived ?></span><span class="pm-summary-card__label">Applications</span></div>
                <div class="pm-summary-card"><span class="pm-summary-card__value"><?= (int) $appStats['under_review'] ?></span><span class="pm-summary-card__label">Under Review</span></div>
                <div class="pm-summary-card"><span class="pm-summary-card__value"><?= (int) $appStats['shortlisted'] ?></span><span class="pm-summary-card__label">Shortlisted</span></div>
                <div class="pm-summary-card"><span class="pm-summary-card__value"><?= (int) $appStats['interview'] ?></span><span class="pm-summary-card__label">Interview</span></div>
                <div class="pm-summary-card"><span class="pm-summary-card__value"><?= (int) $appStats['selected'] ?></span><span class="pm-summary-card__label">Selected</span></div>
                <div class="pm-summary-card"><span class="pm-summary-card__value"><?= (int) $appStats['rejected'] ?></span><span class="pm-summary-card__label">Rejected</span></div>
              </div>
              <div class="pm-card-block pm-card-block--details">
                <h3 class="pm-card-block__title"><i class="fas fa-user-check"></i> Capacity</h3>
                <dl class="pm-details-list">
                  <div><dt>Available Positions</dt><dd><?= (int) $availablePositions ?></dd></div>
                  <div><dt>Applications Received</dt><dd><?= (int) $applicationsReceived ?></dd></div>
                  <div><dt>Selected / Onboarded</dt><dd><?= (int) $selectedCount ?></dd></div>
                  <div><dt>Remaining Capacity</dt><dd><?= (int) $remainingCapacity ?></dd></div>
                </dl>
                <div class="pm-capacity__bar-block" style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border,#f3f4f6);">
                  <div class="pm-capacity__bar-head"><span class="pm-capacity__bar-title"><i class="fas fa-fill-drip"></i> Capacity Utilisation</span><span class="pm-capacity__bar-count"><?= (int) $selectedCount ?> / <?= (int) $availablePositions ?></span></div>
                  <div class="pm-progress pm-progress--lg"><div class="pm-progress__bar" style="width:<?= $availablePositions > 0 ? min(100, round((($selectedCount) / $availablePositions) * 100)) : 0 ?>%"></div></div>
                </div>
              </div>
            </div>

            <!-- ACTIVITY PANEL -->
            <div class="pm-tab-panel" id="tab-activity">
              <div class="pm-panel-header">
                <h3 class="pm-panel-title">Activity</h3>
                <p class="pm-panel-sub">Recent actions related to this opportunity.</p>
              </div>
              <div class="pm-audit">
                <?php
                $audit = Database::fetchAll(
                    "SELECT * FROM audit_logs
                     WHERE record_type = 'opportunity' AND record_id = ?
                     ORDER BY created_at DESC LIMIT 25",
                    'i',
                    [$id]
                );
                ?>
                <?php if (empty($audit)): ?>
                  <p class="pm-muted">No activity recorded for this opportunity yet.</p>
                <?php else: foreach ($audit as $a): ?>
                  <div class="pm-audit__item">
                    <span class="pm-audit__dot"></span>
                    <div class="pm-audit__content">
                      <strong><?= e($a['action']) ?></strong>
                      <span><?= e($a['reason'] ?? '') ?> <span class="pm-audit__type">(opportunity #<?= (int) $a['record_id'] ?>)</span></span>
                    </div>
                    <span class="pm-audit__time"><?= e(time_ago($a['created_at'])) ?></span>
                  </div>
                <?php endforeach; endif; ?>
              </div>
            </div>

          </div>
        </section>

      </div>

      <footer class="dash-footer admin-footer">
        <div class="container"><div class="dash-footer__inner"><p>&copy; 2025 Investhood IT. All rights reserved.</p></div></div>
      </footer>
    </main>
  </div>

  <script src="<?= url('js/script.js') ?>"></script>
  <script src="<?= url('js/admin_dashboard.js') ?>"></script>
  <script src="<?= url('js/admin_programmes.js') ?>"></script>
  <script src="<?= url('js/admin_opportunities.js') ?>"></script>
  <?= $flashes ?>
</body>
</html>
