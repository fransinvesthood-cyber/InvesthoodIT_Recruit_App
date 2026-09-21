<?php
/**
 * ================================================
 * INVESTHOOD IT - Admin Offer Detail Page (Stage 11)
 * ================================================
 * Displays a single offer with its association to the
 * candidate, application, opportunity, programme and cohort,
 * its audit trail, and the status actions allowed by the
 * existing offer-status workflow. Accessible only by
 * administrators.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// Protect — only Administrator
require_role('admin');

$user    = current_user();
$offerId = (int) ($_GET['id'] ?? 0);

if ($offerId <= 0) {
    set_flash('error', 'Invalid Offer', 'No offer ID was provided.');
    safe_redirect('admin/offers.php');
}

$offer = Selection::findOffer($offerId);
if (!$offer) {
    set_flash('error', 'Offer Not Found', 'The requested offer does not exist.');
    safe_redirect('admin/offers.php');
}

$applicationId = (int) $offer['application_id'];
$record        = Selection::findRecord($applicationId);
$decision      = Selection::findDecision($applicationId);
$offerHistory  = Selection::offerHistory($offerId);
$statusHistory = Application::statusHistory($applicationId);

// Allowed next statuses for the current offer status (existing
// offer-status workflow — never bypassed from the browser).
$allowedStatuses = Selection::OFFER_TRANSITIONS[$offer['status']] ?? [];

$offerTone = Selection::offerStatusBadgeTone($offer['status']);

// Application timeline steps for this application.
$flowStepOrder = ['draft' => 0, 'submitted' => 0, 'under_review' => 1, 'shortlisted' => 2, 'assessment' => 3,
                  'interview_scheduled' => 4, 'interview_completed' => 5, 'selected' => 5, 'offer_sent' => 5,
                  'offer_accepted' => 6, 'offer_declined' => 5, 'on_hold' => 5, 'rejected' => 5, 'withdrawn' => 5];
$currentStep = $flowStepOrder[$record['status'] ?? '' ] ?? 6;

$flashes = render_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Offer Detail - Investhood IT Administrator">
  <title>Offer — <?= e($offer['application_reference'] ?? ('#' . $offerId)) ?> | Investhood IT Admin</title>
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

    <!-- ===== MAIN ===== -->
    <main class="dashboard__main">

      <header class="dash-header admin-dash-header">
        <div class="dash-header__left">
          <button class="dash-header__toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="fas fa-bars"></i></button>
          <a href="<?= url('admin/offers.php') ?>" class="btn btn--ghost btn--sm"><i class="fas fa-arrow-left"></i> Offer Management</a>
          <a href="<?= url('admin/selection.php') ?>" class="btn btn--ghost btn--sm"><i class="fas fa-user-check"></i> Selection</a>
        </div>
        <div class="dash-header__right">
          <?php if ($offer['status'] === 'draft'): ?>
            <a href="<?= url('admin/offer_form.php?id=' . (int) $offerId) ?>" class="btn btn--primary btn--sm"><i class="fas fa-edit"></i> Edit Draft</a>
          <?php endif; ?>
          <button class="dash-header__icon-btn" id="themeToggle" aria-label="Toggle dark mode"><i class="fas fa-moon"></i></button>
          <div class="dash-header__user">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'Admin+User') ?>&background=1a56db&color=fff&size=80" alt="Profile" class="dash-header__avatar">
          </div>
        </div>
      </header>

      <div class="dash-content">
        <?= $flashes ?>

        <!-- ===== HERO ===== -->
        <section class="app-hero">
          <div class="app-hero__inner">
            <div>
              <span class="section__badge">Offer Management</span>
              <h1 class="app-hero__title"><?= e($offer['title'] ?? 'Offer') ?></h1>
              <p class="app-hero__subtitle">
                <?= e($offer['candidate_name'] ?? '—') ?> &middot; <?= e($offer['application_reference'] ?? '') ?>
                &middot; <?= e($offer['opportunity_title'] ?? '') ?>
              </p>
            </div>
            <div class="app-hero__actions">
              <span class="sel-decision-badge sel-decision-badge--<?= e($offerTone) ?>" style="font-size:0.8rem;">
                <i class="fas fa-circle" style="font-size:0.5rem;"></i> <?= e(Selection::offerStatusLabel($offer['status'])) ?>
              </span>
            </div>
          </div>
        </section>

        <!-- ===== WORKFLOW STRIP ===== -->
        <div class="sel-flow">
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
          foreach ($flowSteps as $index => $step):
              $stepClass = 'sel-flow__step';
              if ($index < $currentStep) {
                  $stepClass .= ' sel-flow__step--done';
              } elseif ($index === $currentStep) {
                  $stepClass .= ' sel-flow__step--current';
              } else {
                  $stepClass .= ' sel-flow__step--pending';
              }
          ?>
            <?php if ($index > 0): ?><i class="fas fa-chevron-right sel-flow__arrow"></i><?php endif; ?>
            <span class="<?= $stepClass ?>">
              <i class="fas <?= $index <= $currentStep ? 'fa-check' : e($step['icon']) ?>"></i>
              <?= e($step['label']) ?>
            </span>
          <?php endforeach; ?>
        </div>

        <div class="sel-grid">

          <!-- ===== LEFT: OFFER INFORMATION ===== -->
          <div class="sel-detail__main">

            <section class="sel-card">
              <div class="sel-card__header">
                <h2><i class="fas fa-file-invoice"></i> Offer Information</h2>
              </div>
              <div class="sel-card__row"><span class="label">Offer ID</span><span class="value">#<?= (int) $offerId ?></span></div>
              <div class="sel-card__row"><span class="label">Application Reference</span><span class="value">
                <a href="<?= url('admin/application.php?id=' . $applicationId) ?>" class="app-ref-link"><?= e($offer['application_reference'] ?? '—') ?></a>
              </span></div>
              <div class="sel-card__row"><span class="label">Candidate</span><span class="value"><strong><?= e($offer['candidate_name'] ?? '—') ?></strong> &middot; <?= e($offer['candidate_email'] ?? '') ?></span></div>
              <div class="sel-card__row"><span class="label">Opportunity</span><span class="value"><?= e($offer['opportunity_title'] ?? '—') ?></span></div>
              <div class="sel-card__row"><span class="label">Programme</span><span class="value"><?= e($offer['programme_name'] ?? '—') ?></span></div>
              <div class="sel-card__row"><span class="label">Cohort</span><span class="value"><?= e($offer['cohort_name'] ?? '—') ?></span></div>
              <div class="sel-card__row"><span class="label">Position</span><span class="value"><?= e($offer['position'] ?? '—') ?></span></div>
              <div class="sel-card__row"><span class="label">Start Date</span><span class="value"><?= e(format_date($offer['start_date'], 'd M Y')) ?></span></div>
              <div class="sel-card__row"><span class="label">End Date</span><span class="value"><?= e(format_date($offer['end_date'], 'd M Y')) ?></span></div>
              <div class="sel-card__row"><span class="label">Location</span><span class="value"><?= e($offer['location'] ?? '—') ?></span></div>
              <div class="sel-card__row"><span class="label">Stipend / Salary</span><span class="value"><?= e($offer['compensation'] ?? '—') ?></span></div>
              <div class="sel-card__row"><span class="label">Offer Status</span><span class="value">
                <span class="sel-decision-badge sel-decision-badge--<?= e($offerTone) ?>"><?= e(Selection::offerStatusLabel($offer['status'])) ?></span>
              </span></div>
              <div class="sel-card__row"><span class="label">Application Status</span><span class="value">
                <span class="app-status-badge app-status-badge--<?= e(Application::badgeTone($offer['application_status'] ?? null)) ?>"><?= e(Application::label($offer['application_status'] ?? null)) ?></span>
              </span></div>
              <div class="sel-card__row"><span class="label">Created Date</span><span class="value"><?= e(format_date($offer['created_at'], 'd M Y, H:i')) ?></span></div>
              <div class="sel-card__row"><span class="label">Expiry Date</span><span class="value"><?= e(format_date($offer['expiry_date'], 'd M Y')) ?></span></div>
              <div class="sel-card__row"><span class="label">Issued By</span><span class="value"><?= e($offer['issued_by_name'] ?? '—') ?><?= !empty($offer['issued_at']) ? ' &middot; ' . e(format_date($offer['issued_at'], 'd M Y, H:i')) : '' ?></span></div>
              <div class="sel-card__row"><span class="label">Last Updated</span><span class="value"><?= e(format_date($offer['updated_at'], 'd M Y, H:i')) ?></span></div>
            </section>

            <section class="sel-card">
              <div class="sel-card__header">
                <h2><i class="fas fa-file-alt"></i> Terms / Additional Information</h2>
              </div>
              <?php if (!empty($offer['terms'])): ?>
                <div class="sel-note sel-note--neutral"><?= e($offer['terms']) ?></div>
              <?php else: ?>
                <p style="color:var(--text-muted);font-size:0.85rem;">No additional terms were recorded for this offer.</p>
              <?php endif; ?>
            </section>

            <!-- ===== OFFER AUDIT TRAIL (existing offer status history) ===== -->
            <section class="sel-card">
              <div class="sel-card__header">
                <h2><i class="fas fa-clipboard-list"></i> Offer Audit Trail</h2>
              </div>
              <?php if (empty($offerHistory)): ?>
                <p style="color:var(--text-muted);font-size:0.85rem;">No offer status history recorded yet.</p>
              <?php else: ?>
                <div class="app-timeline">
                  <?php foreach ($offerHistory as $index => $history): ?>
                    <div class="app-timeline__item <?= $index === count($offerHistory) - 1 ? 'app-timeline__item--current' : '' ?>">
                      <div class="app-timeline__marker">
                        <i class="fas fa-<?= $index === count($offerHistory) - 1 ? 'circle' : 'check-circle' ?>"></i>
                      </div>
                      <div class="app-timeline__content">
                        <div class="app-timeline__title">
                          <?= e(Selection::offerStatusLabel($history['new_status'])) ?>
                          <?php if (!empty($history['previous_status'])): ?>
                            <span style="font-weight:500;color:var(--text-muted);font-size:0.78rem;">
                              (from <?= e(Selection::offerStatusLabel($history['previous_status'])) ?>)
                            </span>
                          <?php endif; ?>
                        </div>
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

            <!-- ===== APPLICATION TIMELINE (existing status history) ===== -->
            <section class="sel-card">
              <div class="sel-card__header">
                <h2><i class="fas fa-history"></i> Application Timeline</h2>
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

          <!-- ===== RIGHT: OFFER ACTIONS ===== -->
          <div>

            <?php if (!empty($allowedStatuses)): ?>
              <section class="sel-card">
                <div class="sel-card__header">
                  <h3><i class="fas fa-exchange-alt"></i> Offer Status Actions</h3>
                </div>
                <form class="sel-form" method="post" action="<?= url('admin/offer_action.php') ?>"
                      data-confirm-title="Update the offer status?"
                      data-confirm-message="The offer status will change and the change is recorded on the offer audit trail. Continue?"
                      data-confirm-label="Update Status"
                      <?= (in_array('declined', $allowedStatuses, true) || in_array('withdrawn', $allowedStatuses, true)) ? 'data-confirm-danger' : '' ?>>
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="status">
                  <input type="hidden" name="offer_id" value="<?= (int) $offerId ?>">
                  <input type="hidden" name="application_id" value="<?= (int) $applicationId ?>">

                  <div class="form-group">
                    <label for="offerStatus">New Offer Status</label>
                    <select id="offerStatus" name="status" required>
                      <?php foreach ($allowedStatuses as $next): ?>
                        <option value="<?= e($next) ?>"><?= e(Selection::offerStatusLabel($next)) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="form-group">
                    <label for="statusReason">Reason / Reference <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
                    <input type="text" id="statusReason" name="reason" maxlength="200" placeholder="e.g. Offer issued to candidate for response">
                  </div>

                  <div class="sel-form__actions">
                    <button type="submit" class="btn btn--primary btn--sm"><i class="fas fa-check"></i> Update Status</button>
                  </div>
                </form>

                <div class="sel-note sel-note--neutral">
                  <div class="sel-note__title"><i class="fas fa-info-circle"></i> Allowed transitions</div>
                  <?php
                  $transitionLabels = [];
                  foreach ($allowedStatuses as $next) {
                      $transitionLabels[] = Selection::offerStatusLabel($next);
                  }
                  ?>
                  <?= e(Selection::offerStatusLabel($offer['status'])) ?> &rarr; <?= e(implode(' / ', $transitionLabels)) ?>
                </div>
              </section>
            <?php else: ?>
              <section class="sel-card">
                <div class="sel-card__header">
                  <h3><i class="fas fa-lock"></i> Offer Status Actions</h3>
                </div>
                <p style="color:var(--text-muted);font-size:0.85rem;margin:0;">
                  This offer is &ldquo;<?= e(Selection::offerStatusLabel($offer['status'])) ?>&rdquo; — no further status
                  changes are available through the offer workflow.
                </p>
              </section>
            <?php endif; ?>

            <?php if ($offer['status'] === 'draft'): ?>
              <section class="sel-card">
                <div class="sel-card__header">
                  <h3><i class="fas fa-edit"></i> Draft Offer</h3>
                </div>
                <p style="color:var(--text-muted);font-size:0.85rem;">
                  Draft offers can still be edited. Once an offer is issued its details are locked so the
                  historical record cannot be changed silently.
                </p>
                <div style="margin-top:0.8rem;">
                  <a href="<?= url('admin/offer_form.php?id=' . (int) $offerId) ?>" class="btn btn--primary btn--sm"><i class="fas fa-edit"></i> Edit Draft Offer</a>
                </div>
              </section>
            <?php endif; ?>

            <section class="sel-card">
              <div class="sel-card__header">
                <h3><i class="fas fa-gavel"></i> Selection Decision</h3>
              </div>
              <?php if ($record): ?>
                <div class="sel-card__row"><span class="label">Application</span><span class="value"><?= e($record['application_reference']) ?></span></div>
                <div class="sel-card__row"><span class="label">Decision</span><span class="value">
                  <?php if (!empty($record['decision'])): ?>
                    <span class="sel-decision-badge sel-decision-badge--<?= e(Selection::decisionBadgeTone($record['decision'])) ?>"><?= e(Selection::decisionLabel($record['decision'])) ?></span>
                  <?php else: ?>
                    <span class="sel-decision-badge sel-decision-badge--muted">Not recorded</span>
                  <?php endif; ?>
                </span></div>
                <div class="sel-card__row"><span class="label">Application Status</span><span class="value">
                  <span class="app-status-badge app-status-badge--<?= e(Application::badgeTone($record['status'])) ?>"><?= e(Application::label($record['status'])) ?></span>
                </span></div>
                <div style="margin-top:0.8rem;">
                  <a href="<?= url('admin/selection_decision.php?id=' . $applicationId) ?>" class="btn btn--ghost btn--sm"><i class="fas fa-clipboard-check"></i> Selection Record</a>
                </div>
              <?php else: ?>
                <p style="color:var(--text-muted);font-size:0.85rem;margin:0;">The selection workflow record for this application is not available.</p>
              <?php endif; ?>

              <?php if (!empty($decision['decision_note'])): ?>
                <div class="sel-note">
                  <div class="sel-note__title"><i class="fas fa-lock"></i> Internal Selection Note (Admin Only)</div>
                  <?= e($decision['decision_note']) ?>
                </div>
              <?php endif; ?>
            </section>

            <section class="sel-card">
              <div class="sel-card__header">
                <h3><i class="fas fa-info-circle"></i> Workflow Note</h3>
              </div>
              <p style="color:var(--text-muted);font-size:0.82rem;line-height:1.6;margin:0;">
                Offer status and application status are separate concepts. Issuing an offer moves the application
                to &ldquo;<?= e(Application::label('offer_sent')) ?>&rdquo;. Candidate-side offer acceptance is handled in a
                later stage.
              </p>
            </section>

          </div>
        </div>

      </div>
    </main>
  </div>

  <script src="<?= url('js/admin_dashboard.js') ?>"></script>
  <script src="<?= url('js/admin_selection.js') ?>"></script>
</body>
</html>

