<?php
/**
 * ================================================
 * INVESTHOOD IT - Admin Selection Decision Page (Stage 11)
 * ================================================
 * Shows the candidate's full workflow state after interviews and
 * lets the administrator record the selection decision
 * (Selected / Waitlisted / Not Selected) with an internal
 * admin-only note. POSTs to admin/selection_action.php.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// Protect — only Administrator
require_role('admin');

$user = current_user();
$applicationId = (int) ($_GET['id'] ?? 0);

if ($applicationId <= 0) {
    set_flash('error', 'Invalid Application', 'No application ID was provided.');
    safe_redirect('admin/selection.php');
}

$record = Selection::findRecord($applicationId);
if (!$record) {
    set_flash('error', 'Not In Selection Workflow', 'The requested application is not part of the selection workflow (it may not have completed the interview stage).');
    safe_redirect('admin/selection.php');
}

$eligibility = Selection::eligibility($applicationId, $record);
$decision    = Selection::findDecision($applicationId);
$statusHistory = Application::statusHistory($applicationId);

// Latest interview detail + feedback (for the workflow summary)
$interviewDetail = null;
$interviewFeedback = null;
if (!empty($record['interview_id'])) {
    $interviewDetail = Interview::find((int) $record['interview_id']);
    $interviewFeedback = Interview::getFeedback((int) $record['interview_id']);
}

$assessmentStage = Selection::assessmentStage($record['status']);

// Workflow strip steps: submitted → screening → shortlisted → assessment → interview → selection → offer
$flowSteps = [
    ['label' => 'Application',  'icon' => 'fa-file-alt'],
    ['label' => 'Screening',    'icon' => 'fa-search'],
    ['label' => 'Shortlisted',  'icon' => 'fa-list-check'],
    ['label' => 'Assessment',   'icon' => 'fa-clipboard-check'],
    ['label' => 'Interview',    'icon' => 'fa-comments'],
    ['label' => 'Selection',    'icon' => 'fa-user-check'],
    ['label' => 'Offer',        'icon' => 'fa-file-signature'],
];
$flowStepOrder = ['draft' => 0, 'submitted' => 0, 'under_review' => 1, 'shortlisted' => 2, 'assessment' => 3,
                  'interview_scheduled' => 4, 'interview_completed' => 5, 'selected' => 5, 'offer_sent' => 5,
                  'offer_accepted' => 6, 'offer_declined' => 5, 'on_hold' => 5, 'rejected' => 5, 'withdrawn' => 5];
$currentStep = $flowStepOrder[$record['status']] ?? 5;

$flashes = render_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Selection Decision - Investhood IT Administrator">
  <title>Selection — <?= e($record['application_reference']) ?> | Investhood IT Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
  <link rel="stylesheet" href="<?= url('css/admin_programmes.css') ?>">
  <link rel="stylesheet" href="<?= url('css/admin_applications.css') ?>">
  <link rel="stylesheet" href="<?= url('css/admin_selection.css') ?>">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
</head>
<body class="dashboard-page admin-dashboard app-module sel-module">

  <div class="dashboard">

    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar admin-sidebar" id="adminSidebar">
      <div class="sidebar__header">
        <a href="<?= url('index.php') ?>" class="logo">
          <span class="logo__icon"><i class="fas fa-code"></i></span>
          <span class="logo__text">Investhood <span class="logo__accent">IT</span></span>
        </a>
        <button class="sidebar__close" id="sidebarClose" aria-label="Close sidebar"><i class="fas fa-times"></i></button>
      </div>
      <nav class="sidebar__nav">
        <div class="sidebar__section-label">Command Centre</div>
        <ul class="sidebar__menu">
          <li><a href="<?= url('admin/dashboard.php') ?>" class="sidebar__link"><i class="fas fa-th-large"></i> Executive Overview</a></li>
        </ul>
        <div class="sidebar__section-label">Management</div>
        <ul class="sidebar__menu">
          <li><a href="<?= url('admin/programmes.php') ?>" class="sidebar__link"><i class="fas fa-graduation-cap"></i> Programmes</a></li>
          <li><a href="<?= url('admin/opportunities.php') ?>" class="sidebar__link"><i class="fas fa-briefcase"></i> Opportunities</a></li>
          <li><a href="<?= url('admin/applications.php') ?>" class="sidebar__link"><i class="fas fa-file-alt"></i> Applications</a></li>
          <li><a href="<?= url('admin/interviews.php') ?>" class="sidebar__link"><i class="fas fa-calendar-check"></i> Interviews</a></li>
          <li><a href="<?= url('admin/selection.php') ?>" class="sidebar__link active"><i class="fas fa-user-check"></i> Selection &amp; Offers</a></li>
        </ul>
        <div class="sidebar__section-label">Operations</div>
        <ul class="sidebar__menu">
          <li><a href="<?= url('admin/dashboard.php') ?>" class="sidebar__link"><i class="fas fa-history"></i> Audit Log</a></li>
        </ul>
      </nav>
      <div class="sidebar__footer">
        <div class="sidebar__user">
          <div class="sidebar__user-avatar">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'Admin+User') ?>&background=1a56db&color=fff&size=40" alt="Admin">
          </div>
          <div class="sidebar__user-info">
            <span class="sidebar__user-name"><?= e($user['fullname'] ?? 'Admin User') ?></span>
            <span class="sidebar__user-role">Administrator</span>
          </div>
        </div>
        <a href="<?= url('auth/logout.php') ?>" class="sidebar__logout"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
      </div>
    </aside>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="dashboard__main">

      <!-- ===== HEADER ===== -->
      <header class="dash-header admin-dash-header">
        <div class="dash-header__left">
          <a href="<?= url('admin/selection.php') ?>" class="btn btn--ghost btn--sm"><i class="fas fa-arrow-left"></i> Selection</a>
        </div>
        <div class="dash-header__right">
          <a href="<?= url('admin/application.php?id=' . (int) $applicationId) ?>" class="btn btn--ghost btn--sm"><i class="fas fa-eye"></i> View Application</a>
          <button class="dash-header__icon-btn" id="themeToggle" aria-label="Toggle dark mode"><i class="fas fa-moon"></i></button>
          <div class="dash-header__user">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'Admin+User') ?>&background=1a56db&color=fff&size=80" alt="Profile" class="dash-header__avatar">
          </div>
        </div>
      </header>

      <!-- ===== CONTENT ===== -->
      <div class="dash-content">
        <?= $flashes ?>

        <!-- ===== HERO ===== -->
        <section class="app-hero">
          <div class="app-hero__inner">
            <div>
              <span class="section__badge">Selection Decision</span>
              <h1 class="app-hero__title"><?= e($record['candidate_name']) ?></h1>
              <p class="app-hero__subtitle">
                <?= e($record['application_reference']) ?> &middot;
                <?= e($record['opportunity_title'] ?? '—') ?> &middot;
                <?= e($record['programme_name'] ?? '—') ?><?= !empty($record['cohort_name']) ? ' &middot; ' . e($record['cohort_name']) : '' ?>
              </p>
            </div>
            <div class="app-hero__actions">
              <span class="app-status-badge app-status-badge--<?= e(Application::badgeTone($record['status'])) ?>" style="font-size:0.9rem;padding:0.4rem 1rem;">
                <?= e(Application::label($record['status'])) ?>
              </span>
              <?php if (!empty($decision['decision'])): ?>
                <span class="sel-decision-badge sel-decision-badge--<?= e(Selection::decisionBadgeTone($decision['decision'])) ?>" style="font-size:0.9rem;padding:0.4rem 1rem;">
                  Decision: <?= e(Selection::decisionLabel($decision['decision'])) ?>
                </span>
              <?php endif; ?>
            </div>
          </div>
        </section>

        <!-- ===== WORKFLOW STRIP ===== -->
        <div class="sel-flow" aria-label="Recruitment workflow">
          <?php
          $flowSteps = [
              ['label' => 'Application', 'icon' => 'fa-file-alt'],
              ['label' => 'Screening',   'icon' => 'fa-search'],
              ['label' => 'Shortlisted', 'icon' => 'fa-list-check'],
              ['label' => 'Assessment',  'icon' => 'fa-clipboard-check'],
              ['label' => 'Interview',   'icon' => 'fa-comments'],
              ['label' => 'Selection',   'icon' => 'fa-user-check'],
              ['label' => 'Offer',       'icon' => 'fa-file-signature'],
          ];
          $flowStepOrder = ['draft' => 0, 'submitted' => 0, 'under_review' => 1, 'shortlisted' => 2, 'assessment' => 3,
                            'interview_scheduled' => 4, 'interview_completed' => 5, 'selected' => 5, 'offer_sent' => 5,
                            'offer_accepted' => 6, 'offer_declined' => 5, 'on_hold' => 5, 'rejected' => 5, 'withdrawn' => 5];
          $currentStep = $flowStepOrder[$record['status']] ?? 5;
          foreach ($flowSteps as $index => $step): ?>
            <?php if ($index > 0): ?><i class="fas fa-chevron-right sel-flow__arrow"></i><?php endif; ?>
            <?php
            $stepClass = 'sel-flow__step';
            if ($index < $currentStep) {
                $stepClass .= ' sel-flow__step--done';
            } elseif ($index === $currentStep) {
                $stepClass .= ' sel-flow__step--current';
            } else {
                $stepClass .= ' sel-flow__step--pending';
            }
            ?>
            <span class="<?= $stepClass ?>">
              <i class="fas <?= $index <= $currentStep ? 'fa-check' : e($step['icon']) ?>"></i>
              <?= e($step['label']) ?>
            </span>
          <?php endforeach; ?>
        </div>

        <!-- ===== DETAIL GRID ===== -->
        <div class="sel-grid">

          <!-- ===== LEFT: WORKFLOW SUMMARY ===== -->
          <div class="sel-detail__main">

            <section class="sel-card">
              <div class="sel-card__header">
                <h3><i class="fas fa-stream"></i> Workflow Summary</h3>
              </div>
              <div class="sel-card__row"><span class="label">Candidate</span><span class="value"><strong><?= e($record['candidate_name']) ?></strong> &middot; <?= e($record['candidate_email']) ?></span></div>
              <div class="sel-card__row"><span class="label">Application Reference</span><span class="value"><a href="<?= url('admin/application.php?id=' . (int) $applicationId) ?>" class="app-ref-link"><?= e($record['application_reference']) ?></a></span></div>
              <div class="sel-card__row"><span class="label">Opportunity</span><span class="value"><?= e($record['opportunity_title'] ?? '—') ?></span></div>
              <div class="sel-card__row"><span class="label">Programme</span><span class="value"><?= e($record['programme_name'] ?? '—') ?></span></div>
              <div class="sel-card__row"><span class="label">Cohort</span><span class="value"><?= e($record['cohort_name'] ?? '—') ?></span></div>
              <div class="sel-card__row"><span class="label">Assessment</span><span class="value">
                <?php if ($assessmentStage === 'completed'): ?>
                  <span class="sel-decision-badge sel-decision-badge--success"><i class="fas fa-check"></i> Completed</span>
                <?php else: ?>
                  <span class="sel-decision-badge sel-decision-badge--muted">Not completed</span>
                <?php endif; ?>
              </span></div>
              <div class="sel-card__row"><span class="label">Interview</span><span class="value">
                <?php if (!empty($record['interview_status'])): ?>
                  <span class="sel-decision-badge sel-decision-badge--<?= e($record['interview_status'] === 'completed' ? 'success' : 'primary') ?>">
                    <?= e(Interview::label((string) $record['interview_status'])) ?>
                  </span>
                  <?php if (!empty($record['interview_date'])): ?>
                    &nbsp;<span style="color:var(--text-muted);"><?= e(format_date($record['interview_date'], 'd M Y')) ?></span>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="sel-decision-badge sel-decision-badge--muted">No interview record</span>
                <?php endif; ?>
              </span></div>
              <?php if (isset($record['overall_rating']) || !empty($record['interview_recommendation'])): ?>
                <div class="sel-card__row"><span class="label">Interview Feedback</span><span class="value">
                  <?php if (isset($record['overall_rating'])): ?>
                    Rating: <strong><?= e($record['overall_rating']) ?>/5</strong>
                  <?php endif; ?>
                  <?php if (!empty($record['interview_recommendation'])): ?>
                    &middot; <?= e(Interview::recommendationLabel((string) $record['interview_recommendation'])) ?>
                  <?php endif; ?>
                </span></div>
              <?php endif; ?>
              <div class="sel-card__row"><span class="label">Current Application Status</span><span class="value"><span class="app-status-badge app-status-badge--<?= e(Application::badgeTone($record['status'])) ?>"><?= e(Application::label($record['status'])) ?></span></span></div>
              <div class="sel-card__row"><span class="label">Last Updated</span><span class="value"><?= e(format_date($record['updated_at'], 'd M Y, H:i')) ?></span></div>
            </section>

            <!-- ===== APPLICATION TIMELINE (existing history mechanism) ===== -->
            <section class="sel-card">
              <div class="sel-card__header">
                <h3><i class="fas fa-history"></i> Application Timeline</h3>
              </div>
              <?php if (empty($statusHistory)): ?>
                <p style="color:var(--text-muted);font-size:0.85rem;">No status history recorded yet.</p>
              <?php else: ?>
                <div class="app-timeline">
                  <?php foreach ($statusHistory as $index => $history): ?>
                    <div class="app-timeline__item <?= $index === count($statusHistory) - 1 ? 'app-timeline__item--current' : '' ?>">
                      <div class="app-timeline__marker">
                        <i class="fas fa-<?= $index === count($statusHistory) - 1 ? 'circle' : 'check-circle' ?>"></i>
                      </div>
                      <div class="app-timeline__content">
                        <div class="app-timeline__title"><?= e(Application::label($history['new_status'])) ?></div>
                        <div class="app-timeline__date"><?= e(format_date($history['created_at'], 'd M Y, H:i')) ?></div>
                        <?php if (!empty($history['changed_by_name'])): ?>
                          <div class="app-timeline__by">by <?= e($history['changed_by_name']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($history['change_reason'])): ?>
                          <div class="app-timeline__reason"><?= e($history['change_reason']) ?></div>
                        <?php endif; ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </section>

          </div>

          <!-- ===== RIGHT: DECISION FORM ===== -->
          <div>

            <?php if (!$eligibility['ok']): ?>
              <!-- ===== INELIGIBILITY NOTICE ===== -->
              <section class="sel-card">
                <div class="sel-card__header">
                  <h3><i class="fas fa-ban" style="color:#dc2626;"></i> Selection Not Available</h3>
                </div>
                <div class="alert alert--error">
                  <i class="fas fa-exclamation-circle"></i>
                  This application cannot currently be processed for selection.
                </div>
                <ul style="margin:0.5rem 0 0;padding-left:1.2rem;font-size:0.83rem;color:var(--text-muted,#6b7280);line-height:1.7;">
                  <?php foreach ($eligibility['reasons'] as $reason): ?>
                    <li><?= e($reason) ?></li>
                  <?php endforeach; ?>
                </ul>
              </section>
            <?php endif; ?>

            <!-- ===== CURRENT DECISION ===== -->
            <?php if (!empty($decision['decision'])): ?>
              <section class="sel-card">
                <div class="sel-card__header">
                  <h3><i class="fas fa-clipboard-check"></i> Recorded Decision</h3>
                </div>
                <div class="sel-card__row"><span class="label">Decision</span><span class="value">
                  <span class="sel-decision-badge sel-decision-badge--<?= e(Selection::decisionBadgeTone($decision['decision'])) ?>">
                    <?= e(Selection::decisionLabel($decision['decision'])) ?>
                  </span>
                </span></div>
                <div class="sel-card__row"><span class="label">Decided By</span><span class="value"><?= e($decision['decided_by_name'] ?? '—') ?></span></div>
                <div class="sel-card__row"><span class="label">Decided At</span><span class="value"><?= e(format_date($decision['decided_at'], 'd M Y, H:i')) ?></span></div>
                <?php if (!empty($decision['decision_note'])): ?>
                  <div class="sel-note">
                    <div class="sel-note__title"><i class="fas fa-lock"></i> Internal Selection Note (Admin Only)</div>
                    <?= e($decision['decision_note']) ?>
                  </div>
                <?php endif; ?>
                <?php if ($decision['decision'] === 'selected'): ?>
                  <div style="margin-top:0.9rem;display:flex;gap:0.6rem;flex-wrap:wrap;">
                    <?php if (!empty($record['offer_id'])): ?>
                      <a href="<?= url('admin/offer.php?id=' . (int) $record['offer_id']) ?>" class="btn btn--primary btn--sm">
                        <i class="fas fa-file-contract"></i> View Offer
                      </a>
                    <?php else: ?>
                      <a href="<?= url('admin/offer_form.php?application=' . (int) $applicationId) ?>" class="btn btn--primary btn--sm">
                        <i class="fas fa-file-signature"></i> Create Offer
                      </a>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </section>
            <?php endif; ?>

            <!-- ===== SELECTION DECISION FORM ===== -->
            <?php if ($eligibility['ok']): ?>
              <section class="sel-card">
                <div class="sel-card__header">
                  <h3><i class="fas fa-gavel"></i> Selection Decision</h3>
                </div>
                <form class="sel-form" method="post" action="<?= url('admin/selection_action.php') ?>"
                      data-confirm-title="Record selection decision?"
                      data-confirm-message="<?= e(($record['status'] ?? '') === 'selected'
                          ? 'The application is already in the Selected status. The decision will be recorded with an audit trail; the status itself will not change.'
                          : 'The application status will be updated and the decision recorded with an audit trail. This action is logged and traceable.') ?>"
                      data-confirm-label="Record Decision">
                  <?= csrf_field() ?>
                  <input type="hidden" name="application_id" value="<?= (int) $applicationId ?>">

                  <div class="decision-options">
                    <label class="decision-option decision-option--selected">
                      <input type="radio" name="decision" value="selected" <?= ($decision['decision'] ?? '') === 'selected' ? 'checked' : '' ?>>
                      <span class="decision-option__body">
                        <span class="decision-option__title"><i class="fas fa-user-check"></i> Selected</span>
                        <?php if (($record['status'] ?? '') === 'selected'): ?>
                          <span class="decision-option__hint">The application is already in the &ldquo;Selected&rdquo; status. Recording this decision documents it (status unchanged) and enables offer creation.</span>
                        <?php else: ?>
                          <span class="decision-option__hint">Application status becomes &ldquo;Selected&rdquo;. An offer can then be created for this candidate.</span>
                        <?php endif; ?>
                      </span>
                    </label>
                    <label class="decision-option decision-option--waitlisted">
                      <input type="radio" name="decision" value="waitlisted" <?= ($decision['decision'] ?? '') === 'waitlisted' ? 'checked' : '' ?>>
                      <span class="decision-option__body">
                        <span class="decision-option__title"><i class="fas fa-user-clock"></i> Waitlisted</span>
                        <span class="decision-option__hint">The candidate is placed on hold for this intake (status &ldquo;On Hold&rdquo;). No offer is generated until they are explicitly selected.</span>
                      </span>
                    </label>
                    <label class="decision-option decision-option--not-selected">
                      <input type="radio" name="decision" value="not_selected" <?= ($decision['decision'] ?? '') === 'not_selected' ? 'checked' : '' ?>>
                      <span class="decision-option__body">
                        <span class="decision-option__title"><i class="fas fa-user-slash"></i> Not Selected</span>
                        <span class="decision-option__hint">Application status becomes &ldquo;Rejected&rdquo;. This is final and cannot be undone.</span>
                      </span>
                    </label>
                  </div>

                  <div class="form-group">
                    <label for="decisionReason">Reason <span style="font-weight:400;color:var(--text-muted);">(shown on the status history)</span></label>
                    <input type="text" id="decisionReason" name="reason" maxlength="200" placeholder="e.g. Strong technical performance across all stages">
                  </div>

                  <div class="form-group">
                    <label for="decisionNote">Internal Selection Note <span style="font-weight:400;color:var(--text-muted);">(admin only — never visible to candidates)</span></label>
                    <textarea id="decisionNote" name="note" maxlength="2000" placeholder="e.g. Candidate demonstrated strong technical knowledge and performed well during the interview."><?= e($decision['decision_note'] ?? '') ?></textarea>
                    <div class="form-hint">Stored with the decision record. Visible to administrators only.</div>
                  </div>

                  <div class="sel-form__actions">
                    <button type="submit" class="btn btn--primary btn--sm">
                      <i class="fas fa-check"></i> Record Decision
                    </button>
                    <a href="<?= url('admin/selection.php') ?>" class="btn btn--ghost btn--sm"><i class="fas fa-times"></i> Cancel</a>
                  </div>
                </form>
              </section>
            <?php endif; ?>

          </div>
        </div>

      </div>
    </main>
  </div>

  <script src="<?= url('js/admin_dashboard.js') ?>"></script>
  <script src="<?= url('js/admin_selection.js') ?>"></script>
</body>
</html>