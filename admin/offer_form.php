<?php
/**
 * ================================================
 * INVESTHOOD IT - Admin Offer Form (Stage 11)
 * ================================================
 * Creates a Draft offer for a SELECTED candidate, or edits an
 * existing Draft offer. Issued (and later) offers are locked —
 * use admin/offer.php to progress their status.
 *
 * POSTs to admin/offer_action.php (CSRF protected, admin only).
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// Protect — only Administrator
require_role('admin');

$user = current_user();

$offerId       = (int) ($_GET['id'] ?? 0);
$applicationId = (int) ($_GET['application'] ?? 0);
$isEdit        = $offerId > 0;

$offer    = null;
$record   = null;
$decision = null;

if ($isEdit) {
    $offer = Selection::findOffer($offerId);
    if (!$offer) {
        set_flash('error', 'Offer Not Found', 'The requested offer does not exist.');
        safe_redirect('admin/offers.php');
    }
    if (($offer['status'] ?? '') !== 'draft') {
        set_flash('warning', 'Offer Locked', 'Only draft offers can be edited. Use the offer page to progress or withdraw an issued offer.');
        safe_redirect('admin/offer.php?id=' . $offerId);
    }
    $applicationId = (int) $offer['application_id'];
}

if ($applicationId <= 0) {
    set_flash('error', 'Invalid Application', 'No application was provided for this offer.');
    safe_redirect('admin/selection.php');
}

$record   = Selection::findRecord($applicationId);
$decision = Selection::findDecision($applicationId);

// ------------------------------------------------------------
// SERVER-SIDE ELIGIBILITY (requirement 10)
// Never trust the application id sent from the browser.
// ------------------------------------------------------------
$blockers = [];
if (!$record) {
    $blockers[] = 'This application is not part of the selection workflow (the interview stage has not been completed).';
} else {
    if (($record['status'] ?? '') !== 'selected') {
        $blockers[] = 'Only a candidate with an application status of "Selected" can receive an offer. Current status: '
            . Application::label($record['status'] ?? null) . '.';
    }
    if (($record['decision'] ?? '') !== 'selected') {
        $blockers[] = 'No "Selected" selection decision has been recorded for this application.';
    }
    $recordOfferId = (int) ($record['offer_id'] ?? 0);
    if ($recordOfferId > 0 && (!$isEdit || $recordOfferId !== $offerId)) {
        $blockers[] = 'An offer already exists for this application — duplicate offers are prevented.';
    } elseif ($recordOfferId === 0 && Selection::activeOfferExists($applicationId)) {
        $blockers[] = 'An active offer already exists for this application — duplicate offers are prevented.';
    }
}
$canSubmit = empty($blockers);

// ------------------------------------------------------------
// FORM VALUES — reuse opportunity / programme information where
// possible so the administrator only completes what is unique
// to the offer itself.
// ------------------------------------------------------------
$defaults = [
    'title'        => 'Offer of Employment',
    'position'     => $record['opportunity_title'] ?? '',
    'location'     => '',
    'start_date'   => '',
    'end_date'     => '',
    'compensation' => '',
    'expiry_date'  => date('Y-m-d', strtotime('+14 days')),
    'terms'        => '',
];

if (!empty($record['opportunity_id'])) {
    try {
        $opp = Database::fetchOne(
            "SELECT city, province FROM opportunities WHERE id = ?",
            'i',
            [(int) $record['opportunity_id']]
        );
        if ($opp) {
            $defaults['location'] = trim(implode(', ', array_filter([
                trim((string) ($opp['city'] ?? '')),
                trim((string) ($opp['province'] ?? '')),
            ])));
        }
    } catch (Exception $ex) {
        error_log('[Offer Form] Opportunity location lookup failed: ' . $ex->getMessage());
    }
}

$v = [
    'title'        => $offer['title']        ?? $defaults['title'],
    'position'     => $offer['position']     ?? $defaults['position'],
    'location'     => $offer['location']     ?? $defaults['location'],
    'start_date'   => $offer['start_date']   ?? $defaults['start_date'],
    'end_date'     => $offer['end_date']     ?? $defaults['end_date'],
    'compensation' => $offer['compensation'] ?? $defaults['compensation'],
    'expiry_date'  => $offer['expiry_date']  ?? $defaults['expiry_date'],
    'terms'        => $offer['terms']        ?? $defaults['terms'],
];

$flashes = render_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= $isEdit ? 'Edit Offer' : 'Create Offer' ?> - Investhood IT Administrator">
  <title><?= $isEdit ? 'Edit Offer' : 'Create Offer' ?> | Investhood IT Admin</title>
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
          <a href="<?= url('admin/selection.php') ?>" class="btn btn--ghost btn--sm"><i class="fas fa-arrow-left"></i> Selection</a>
          <a href="<?= url('admin/offers.php') ?>" class="btn btn--ghost btn--sm"><i class="fas fa-file-signature"></i> Offer Management</a>
        </div>
        <div class="dash-header__right">
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
              <span class="section__badge">Selection &amp; Offers</span>
              <h1 class="app-hero__title"><?= $isEdit ? 'Edit Draft Offer' : 'Create Offer' ?></h1>
              <p class="app-hero__subtitle">
                <?php if ($record): ?>
                  <?= e($record['candidate_name'] ?? 'Candidate') ?> &middot; <?= e($record['application_reference'] ?? '') ?>
                  &middot; <?= e($record['opportunity_title'] ?? '') ?>
                <?php else: ?>
                  An offer can only be created for a candidate whose application status is &ldquo;Selected&rdquo;.
                <?php endif; ?>
              </p>
            </div>
            <div class="app-hero__actions">
              <a href="<?= url('admin/offers.php') ?>" class="btn btn--ghost btn--sm" style="background:rgba(255,255,255,0.15);border-color:rgba(255,255,255,0.35);color:#fff;">
                <i class="fas fa-list"></i> All Offers
              </a>
            </div>
          </div>
        </section>

        <div class="sel-grid">
          <!-- ===== LEFT: OFFER CONTEXT ===== -->
          <div class="sel-detail__main">
            <section class="sel-card">
              <div class="sel-card__header">
                <h3><i class="fas fa-file-contract"></i> Offer Context</h3>
              </div>
              <?php if ($record): ?>
                <div class="sel-card__row"><span class="label">Candidate</span><span class="value"><strong><?= e($record['candidate_name']) ?></strong> &middot; <?= e($record['candidate_email'] ?? '') ?></span></div>
                <div class="sel-card__row"><span class="label">Application</span><span class="value"><a href="<?= url('admin/application.php?id=' . (int) $applicationId) ?>" class="app-ref-link"><?= e($record['application_reference']) ?></a></span></div>
                <div class="sel-card__row"><span class="label">Opportunity</span><span class="value"><?= e($record['opportunity_title'] ?? '—') ?></span></div>
                <div class="sel-card__row"><span class="label">Programme</span><span class="value"><?= e($record['programme_name'] ?? '—') ?></span></div>
                <div class="sel-card__row"><span class="label">Cohort</span><span class="value"><?= e($record['cohort_name'] ?? '—') ?></span></div>
                <div class="sel-card__row"><span class="label">Application Status</span><span class="value">
                  <span class="app-status-badge app-status-badge--<?= e(Application::badgeTone($record['status'])) ?>"><?= e(Application::label($record['status'])) ?></span>
                </span></div>
                <div class="sel-card__row"><span class="label">Selection Decision</span><span class="value">
                  <?php if (!empty($record['decision'])): ?>
                    <span class="sel-decision-badge sel-decision-badge--<?= e(Selection::decisionBadgeTone($record['decision'])) ?>"><?= e(Selection::decisionLabel($record['decision'])) ?></span>
                  <?php else: ?>
                    <span class="sel-decision-badge sel-decision-badge--muted">Not recorded</span>
                  <?php endif; ?>
                </span></div>
              <?php else: ?>
                <p style="color:var(--text-muted);font-size:0.85rem;">No selection workflow record was found for this application.</p>
              <?php endif; ?>
            </section>

            <?php if (!empty($decision['decision_note'])): ?>
              <section class="sel-card">
                <div class="sel-card__header">
                  <h3><i class="fas fa-lock"></i> Internal Selection Note</h3>
                </div>
                <div class="sel-note">
                  <div class="sel-note__title"><i class="fas fa-lock"></i> Admin Only — never visible to candidates</div>
                  <?= e($decision['decision_note']) ?>
                </div>
              </section>
            <?php endif; ?>
          </div>

          <!-- ===== RIGHT: OFFER FORM ===== -->
          <div>
            <?php if (!$canSubmit): ?>
              <section class="sel-card">
                <div class="sel-card__header">
                  <h3><i class="fas fa-ban" style="color:#dc2626;"></i> Offer Not Available</h3>
                </div>
                <div class="alert alert--error">
                  <i class="fas fa-exclamation-circle"></i>
                  This application cannot currently be given an offer.
                </div>
                <ul style="margin:0.5rem 0 0;padding-left:1.2rem;font-size:0.83rem;color:var(--text-muted,#6b7280);line-height:1.7;">
                  <?php foreach ($blockers as $blocker): ?>
                    <li><?= e($blocker) ?></li>
                  <?php endforeach; ?>
                </ul>
                <div style="margin-top:0.9rem;">
                  <a href="<?= url('admin/selection.php') ?>" class="btn btn--ghost btn--sm"><i class="fas fa-arrow-left"></i> Back to Selection</a>
                </div>
              </section>
            <?php else: ?>
              <section class="sel-card">
                <div class="sel-card__header">
                  <h3><i class="fas fa-file-signature"></i> <?= $isEdit ? 'Edit Offer Details' : 'Offer Details' ?></h3>
                </div>

                <form class="sel-form" method="post" action="<?= url('admin/offer_action.php') ?>"
                      data-confirm-title="<?= $isEdit ? 'Save offer changes?' : 'Create this draft offer?' ?>"
                      data-confirm-message="<?= $isEdit
                          ? 'The draft offer details will be updated. The change is recorded on the offer audit trail.'
                          : 'A Draft offer will be created for this selected candidate. You can review it and issue it afterwards.' ?>"
                      data-confirm-label="<?= $isEdit ? 'Save Changes' : 'Create Offer' ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
                  <input type="hidden" name="application_id" value="<?= (int) $applicationId ?>">
                  <?php if ($isEdit): ?>
                    <input type="hidden" name="offer_id" value="<?= (int) $offerId ?>">
                  <?php endif; ?>

                  <div class="form-group">
                    <label for="offerTitle">Offer Title <span style="color:#dc2626;">*</span></label>
                    <input type="text" id="offerTitle" name="title" maxlength="150" required value="<?= e($v['title']) ?>" placeholder="e.g. Offer of Employment — 2026 Graduate Programme">
                  </div>

                  <div class="form-group">
                    <label for="offerPosition">Position <span style="color:#dc2626;">*</span></label>
                    <input type="text" id="offerPosition" name="position" maxlength="150" required value="<?= e($v['position']) ?>" placeholder="e.g. Junior Software Developer">
                  </div>

                  <div class="form-group">
                    <label for="offerLocation">Location</label>
                    <input type="text" id="offerLocation" name="location" maxlength="255" value="<?= e($v['location']) ?>" placeholder="e.g. Johannesburg, Gauteng">
                  </div>

                  <div class="form-group">
                    <label for="offerStart">Start Date</label>
                    <input type="date" id="offerStart" name="start_date" value="<?= e($v['start_date']) ?>">
                  </div>

                  <div class="form-group">
                    <label for="offerEnd">End Date</label>
                    <input type="date" id="offerEnd" name="end_date" value="<?= e($v['end_date']) ?>">
                  </div>

                  <div class="form-group">
                    <label for="offerCompensation">Stipend / Salary</label>
                    <input type="text" id="offerCompensation" name="compensation" maxlength="255" value="<?= e($v['compensation']) ?>" placeholder="e.g. R8 500 per month">
                  </div>

                  <div class="form-group">
                    <label for="offerExpiry">Offer Expiry Date <span style="color:#dc2626;">*</span></label>
                    <input type="date" id="offerExpiry" name="expiry_date" required value="<?= e($v['expiry_date']) ?>">
                    <div class="form-hint">The date by which the candidate must respond to the offer.</div>
                  </div>

                  <div class="form-group">
                    <label for="offerTerms">Terms / Additional Information</label>
                    <textarea id="offerTerms" name="terms" maxlength="5000" rows="6" placeholder="Additional terms and information to include with the offer."><?= e($v['terms']) ?></textarea>
                  </div>

                  <div class="sel-form__actions">
                    <button type="submit" class="btn btn--primary btn--sm">
                      <i class="fas fa-<?= $isEdit ? 'save' : 'plus' ?>"></i> <?= $isEdit ? 'Save Changes' : 'Create Draft Offer' ?>
                    </button>
                    <a href="<?= $isEdit ? url('admin/offer.php?id=' . (int) $offerId) : url('admin/selection.php') ?>" class="btn btn--ghost btn--sm"><i class="fas fa-times"></i> Cancel</a>
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
