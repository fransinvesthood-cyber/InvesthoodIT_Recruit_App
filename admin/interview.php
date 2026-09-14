<?php
/**
 * ================================================
 * INVESTHOOD IT - Admin Interview Detail Page
 * ================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

$user = current_user();
$interviewId = (int) ($_GET['id'] ?? 0);

if ($interviewId <= 0) {
    set_flash('error', 'Invalid Interview', 'No interview ID was provided.');
    safe_redirect('admin/interviews.php');
}

$interview = Interview::find($interviewId);

if (!$interview) {
    set_flash('error', 'Interview Not Found', 'The requested interview does not exist.');
    safe_redirect('admin/interviews.php');
}

$statusHistory = Interview::statusHistory($interviewId);
$feedback = Interview::getFeedback($interviewId);
$flashes = render_flashes();

function int_field($value, $placeholder = '—') {
    return !empty($value) ? e($value) : '<span class="text-muted">' . $placeholder . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Interview #<?= (int) $interviewId ?> | Investhood IT Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
  <link rel="stylesheet" href="<?= url('css/admin_programmes.css') ?>">
  <link rel="stylesheet" href="<?= url('css/admin_applications.css') ?>">
  <link rel="stylesheet" href="<?= url('css/admin_interviews.css') ?>">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
</head>
<body class="dashboard-page admin-dashboard app-module">
  <div class="dashboard">
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
        </ul>
        <div class="sidebar__section-label">Operations</div>
        <ul class="sidebar__menu">
          <li><a href="<?= url('admin/interviews.php') ?>" class="sidebar__link active"><i class="fas fa-calendar-check"></i> Interviews</a></li>
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

    <main class="dashboard__main">
      <header class="dash-header admin-dash-header">
        <div class="dash-header__left">
          <a href="<?= url('admin/interviews.php') ?>" class="btn btn--ghost btn--sm"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
        <div class="dash-header__right">
          <?php if (!in_array($interview['status'], ['completed', 'cancelled'])): ?>
            <a href="<?= url('admin/interview_schedule.php?id=' . $interviewId) ?>" class="btn btn--primary btn--sm"><i class="fas fa-edit"></i> Reschedule</a>
          <?php endif; ?>
          <?php if ($interview['status'] === 'completed' && !$feedback): ?>
            <a href="<?= url('admin/interview.php?id=' . $interviewId . '&action=feedback') ?>" class="btn btn--primary btn--sm"><i class="fas fa-comment-dots"></i> Add Feedback</a>
          <?php endif; ?>
          <button class="dash-header__icon-btn" id="themeToggle" aria-label="Toggle dark mode"><i class="fas fa-moon"></i></button>
          <div class="dash-header__user"><img src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'Admin+User') ?>&background=1a56db&color=fff&size=80" alt="Profile" class="dash-header__avatar"></div>
        </div>
      </header>

      <div class="dash-content">
        <?= $flashes ?>

        <section class="app-hero">
          <div class="app-hero__inner">
            <div>
              <span class="section__badge">Interview Detail</span>
              <h1 class="app-hero__title"><?= e(($interview['candidate_first_name'] ?? '') . ' ' . ($interview['candidate_last_name'] ?? '')) ?></h1>
              <p class="app-hero__subtitle">
                <?= e($interview['application_reference'] ?? '') ?> &middot;
                <?= e($interview['programme_name'] ?? '') ?> &middot;
                <?= e(format_date($interview['interview_date'], 'd M Y')) ?> at <?= e(substr($interview['start_time'], 0, 5)) ?>
              </p>
            </div>
            <div class="app-hero__actions">
              <span class="int-status int-status--<?= e(Interview::badgeTone($interview['status'])) ?>" style="font-size:0.9rem;padding:0.4rem 1rem;">
                <?= e(Interview::label($interview['status'])) ?>
              </span>
            </div>
          </div>
        </section>

        <div class="int-detail-layout">
          <!-- MAIN -->
          <div class="int-detail-main">
            <section class="int-section">
              <h2 class="int-section__title"><i class="fas fa-calendar-check"></i> Interview Information</h2>
              <div class="int-info-row">
                <span class="int-info-row__label">Interview Date</span>
                <span class="int-info-row__value"><?= e(format_date($interview['interview_date'], 'l, d F Y')) ?></span>
              </div>
              <div class="int-info-row">
                <span class="int-info-row__label">Start Time</span>
                <span class="int-info-row__value"><?= e(substr($interview['start_time'], 0, 5)) ?></span>
              </div>
              <div class="int-info-row">
                <span class="int-info-row__label">End Time</span>
                <span class="int-info-row__value"><?= e(substr($interview['end_time'], 0, 5)) ?></span>
              </div>
              <div class="int-info-row">
                <span class="int-info-row__label">Interview Type</span>
                <span class="int-info-row__value"><span class="int-type int-type--<?= e($interview['interview_type']) ?>"><?= e(Interview::typeLabel($interview['interview_type'])) ?></span></span>
              </div>
              <div class="int-info-row">
                <span class="int-info-row__label">Location / Link</span>
                <span class="int-info-row__value"><?= int_field($interview['location']) ?></span>
              </div>
              <div class="int-info-row">
                <span class="int-info-row__label">Interviewer</span>
                <span class="int-info-row__value">
                  <?= !empty($interview['interviewer_first_name']) ? e($interview['interviewer_first_name'] . ' ' . $interview['interviewer_last_name']) : '<span class="text-muted">—</span>' ?>
                </span>
              </div>
              <?php if (!empty($interview['reschedule_count']) && $interview['reschedule_count'] > 0): ?>
                <div class="int-info-row">
                  <span class="int-info-row__label">Previous Date</span>
                  <span class="int-info-row__value">
                    <?= e(format_date($interview['previous_date'], 'd M Y')) ?>
                    (<?= e(substr($interview['previous_start_time'], 0, 5)) ?> – <?= e(substr($interview['previous_end_time'], 0, 5)) ?>)
                  </span>
                </div>
                <div class="int-info-row">
                  <span class="int-info-row__label">Reschedule Count</span>
                  <span class="int-info-row__value"><?= (int) $interview['reschedule_count'] ?></span>
                </div>
              <?php endif; ?>
              <?php if (!empty($interview['notes'])): ?>
                <div class="int-info-row" style="flex-direction:column;align-items:flex-start;gap:0.25rem;">
                  <span class="int-info-row__label">Notes / Instructions</span>
                  <span style="color:#334155;font-size:0.875rem;"><?= nl2br(e($interview['notes'])) ?></span>
                </div>
              <?php endif; ?>
            </section>

            <section class="int-section">
              <h2 class="int-section__title"><i class="fas fa-user"></i> Candidate Information</h2>
              <div class="int-info-row">
                <span class="int-info-row__label">Full Name</span>
                <span class="int-info-row__value"><?= e(($interview['candidate_first_name'] ?? '') . ' ' . ($interview['candidate_last_name'] ?? '')) ?></span>
              </div>
              <div class="int-info-row">
                <span class="int-info-row__label">Email</span>
                <span class="int-info-row__value"><?= e($interview['candidate_email'] ?? '—') ?></span>
              </div>
              <div class="int-info-row">
                <span class="int-info-row__label">Phone</span>
                <span class="int-info-row__value"><?= int_field($interview['candidate_phone']) ?></span>
              </div>
              <div class="int-info-row">
                <span class="int-info-row__label">Programme</span>
                <span class="int-info-row__value"><?= e($interview['programme_name'] ?? '—') ?></span>
              </div>
              <div class="int-info-row">
                <span class="int-info-row__label">Cohort</span>
                <span class="int-info-row__value"><?= e($interview['cohort_name'] ?? '—') ?></span>
              </div>
              <div class="int-info-row">
                <span class="int-info-row__label">Opportunity</span>
                <span class="int-info-row__value"><?= e($interview['opportunity_title'] ?? '—') ?></span>
              </div>
              <div class="int-info-row">
                <span class="int-info-row__label">Application Ref</span>
                <span class="int-info-row__value"><?= e($interview['application_reference'] ?? '—') ?></span>
              </div>
              <div class="int-info-row">
                <span class="int-info-row__label">Application Status</span>
                <span class="int-info-row__value"><?= e(Application::label($interview['application_status'] ?? '')) ?></span>
              </div>
            </section>

            <?php if (!empty($statusHistory)): ?>
              <section class="int-section">
                <h2 class="int-section__title"><i class="fas fa-history"></i> Status History</h2>
                <div class="int-timeline">
                  <?php foreach ($statusHistory as $h): ?>
                    <div class="int-timeline-item">
                      <div class="int-timeline__status">
                        <?= e(Interview::label($h['new_status'])) ?>
                        <?php if (!empty($h['previous_status'])): ?>
                          <span class="text-muted" style="font-weight:400;">(from <?= e(Interview::label($h['previous_status'])) ?>)</span>
                        <?php endif; ?>
                      </div>
                      <div class="int-timeline__date"><?= e(format_date($h['created_at'], 'd M Y, H:i')) ?></div>
                      <?php if (!empty($h['changed_by_first_name'])): ?>
                        <div class="int-timeline__by">by <?= e($h['changed_by_first_name'] . ' ' . $h['changed_by_last_name']) ?></div>
                      <?php endif; ?>
                      <?php if (!empty($h['change_reason'])): ?>
                        <div class="int-timeline__reason"><?= e($h['change_reason']) ?></div>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              </section>
            <?php endif; ?>

            <?php if ($feedback): ?>
              <section class="int-section">
                <h2 class="int-section__title"><i class="fas fa-comment-dots"></i> Interview Feedback</h2>
                <?php if (isset($feedback['overall_rating'])): ?>
                  <div class="int-info-row">
                    <span class="int-info-row__label">Overall Rating</span>
                    <span class="int-info-row__value"><span class="int-rating"><?php for ($s = 1; $s <= 5; $s++): ?><i class="<?= $s <= ($feedback['overall_rating'] ?? 0) ? 'fas' : 'far' ?> fa-star"></i><?php endfor; ?></span></span>
                  </div>
                <?php endif; ?>
                <?php if (!empty($feedback['recommendation'])): ?>
                  <div class="int-info-row">
                    <span class="int-info-row__label">Recommendation</span>
                    <span class="int-info-row__value"><?= e(Interview::recommendationLabel($feedback['recommendation'])) ?></span>
                  </div>
                <?php endif; ?>
                <?php if (!empty($feedback['strengths'])): ?>
                  <div class="int-info-row" style="flex-direction:column;align-items:flex-start;gap:0.25rem;">
                    <span class="int-info-row__label">Strengths</span>
                    <span style="color:#334155;font-size:0.875rem;"><?= nl2br(e($feedback['strengths'])) ?></span>
                  </div>
                <?php endif; ?>
                <?php if (!empty($feedback['areas_for_improvement'])): ?>
                  <div class="int-info-row" style="flex-direction:column;align-items:flex-start;gap:0.25rem;">
                    <span class="int-info-row__label">Areas for Improvement</span>
                    <span style="color:#334155;font-size:0.875rem;"><?= nl2br(e($feedback['areas_for_improvement'])) ?></span>
                  </div>
                <?php endif; ?>
                <?php if (!empty($feedback['general_comments'])): ?>
                  <div class="int-info-row" style="flex-direction:column;align-items:flex-start;gap:0.25rem;">
                    <span class="int-info-row__label">General Comments</span>
                    <span style="color:#334155;font-size:0.875rem;"><?= nl2br(e($feedback['general_comments'])) ?></span>
                  </div>
                <?php endif; ?>
                <div class="int-info-row">
                  <span class="int-info-row__label">Submitted</span>
                  <span class="int-info-row__value"><?= e(format_date($feedback['submitted_at'], 'd M Y, H:i')) ?></span>
                </div>
              </section>
            <?php elseif ($interview['status'] === 'completed'): ?>
              <section class="int-section">
                <h2 class="int-section__title"><i class="fas fa-comment-dots"></i> Interview Feedback</h2>
                <div class="int-empty" style="padding:1.5rem;">
                  <p class="int-empty__text" style="margin-bottom:1rem;">No feedback has been submitted for this interview yet.</p>
                  <a href="<?= url('admin/interview.php?id=' . $interviewId . '&action=feedback') ?>" class="btn btn--primary btn--sm"><i class="fas fa-plus"></i> Add Feedback</a>
                </div>
              </section>
            <?php endif; ?>
          </div>

          <!-- SIDEBAR -->
          <div class="int-detail-sidebar">
            <section class="int-section">
              <h2 class="int-section__title"><i class="fas fa-cog"></i> Actions</h2>
              <?php if (in_array($interview['status'], ['scheduled', 'confirmed', 'rescheduled'])): ?>
                <a href="<?= url('admin/interview_schedule.php?id=' . $interviewId) ?>" class="btn btn--primary btn--block" style="margin-bottom:0.5rem;"><i class="fas fa-edit"></i> Reschedule</a>
                <button type="button" class="btn btn--success btn--block" style="margin-bottom:0.5rem;" onclick="updateInterviewStatus(<?= $interviewId ?>, 'completed')"><i class="fas fa-check"></i> Mark Completed</button>
                <button type="button" class="btn btn--ghost btn--block" style="margin-bottom:0.5rem;color:#f59e0b;" onclick="updateInterviewStatus(<?= $interviewId ?>, 'no_show')"><i class="fas fa-user-slash"></i> Mark No-Show</button>
                <button type="button" class="btn btn--danger btn--block" onclick="showCancelModal(<?= $interviewId ?>)"><i class="fas fa-times"></i> Cancel Interview</button>
              <?php elseif ($interview['status'] === 'completed'): ?>
                <?php if (!$feedback): ?>
                  <a href="<?= url('admin/interview.php?id=' . $interviewId . '&action=feedback') ?>" class="btn btn--primary btn--block" style="margin-bottom:0.5rem;"><i class="fas fa-comment-dots"></i> Add Feedback</a>
                <?php endif; ?>
                <button type="button" class="btn btn--ghost btn--block" disabled><i class="fas fa-check-circle"></i> Interview Completed</button>
              <?php elseif ($interview['status'] === 'cancelled'): ?>
                <div style="text-align:center;padding:0.5rem;">
                  <p style="color:#64748b;font-size:0.875rem;margin:0 0 0.5rem;"><strong>Cancellation Reason:</strong></p>
                  <p style="color:#334155;font-size:0.875rem;margin:0 0 0.5rem;"><?= nl2br(e($interview['cancellation_reason'] ?? 'No reason provided.')) ?></p>
                </div>
                <button type="button" class="btn btn--primary btn--block" onclick="updateInterviewStatus(<?= $interviewId ?>, 'scheduled')"><i class="fas fa-redo"></i> Reschedule</button>
              <?php elseif ($interview['status'] === 'no_show'): ?>
                <button type="button" class="btn btn--primary btn--block" onclick="updateInterviewStatus(<?= $interviewId ?>, 'scheduled')"><i class="fas fa-redo"></i> Reschedule</button>
              <?php endif; ?>
            </section>
            <section class="int-section">
              <h2 class="int-section__title"><i class="fas fa-info"></i> Metadata</h2>
              <div class="int-info-row"><span class="int-info-row__label">Interview ID</span><span class="int-info-row__value">#<?= (int) $interviewId ?></span></div>
              <div class="int-info-row"><span class="int-info-row__label">Application Status</span><span class="int-info-row__value"><?= e(Application::label($interview['application_status'] ?? null)) ?></span></div>
              <div class="int-info-row"><span class="int-info-row__label">Status</span><span class="int-info-row__value"><?= e(Interview::label($interview['status'])) ?></span></div>
              <div class="int-info-row"><span class="int-info-row__label">Created</span><span class="int-info-row__value"><?= e(format_date($interview['created_at'], 'd M Y, H:i')) ?></span></div>
              <div class="int-info-row"><span class="int-info-row__label">Last Updated</span><span class="int-info-row__value"><?= e(format_date($interview['updated_at'], 'd M Y, H:i')) ?></span></div>
            </section>
            <section class="int-section">
              <a href="<?= url('admin/interviews.php') ?>" class="btn btn--primary btn--block"><i class="fas fa-arrow-left"></i> Back to Interviews</a>
            </section>
          </div>
        </div>
      </div>
    </main>
  </div>

  <div id="cancelModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;padding:1.5rem;max-width:480px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
      <h3 style="margin:0 0 0.5rem;font-size:1.1rem;color:#1e293b;"><i class="fas fa-exclamation-triangle" style="color:#ef4444;"></i> Cancel Interview</h3>
      <p style="color:#64748b;font-size:0.875rem;margin:0 0 1rem;">Are you sure? Please provide a reason below.</p>
      <form id="cancelForm" method="POST" action="<?= url('admin/interview_status.php') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="interview_id" id="cancelInterviewId" value="">
        <input type="hidden" name="action" value="cancel">
        <div style="margin-bottom:1rem;">
          <label style="display:block;font-size:0.8rem;font-weight:600;color:#475569;margin-bottom:0.25rem;">Reason *</label>
          <textarea name="reason" rows="3" required style="width:100%;padding:0.5rem;border:1px solid #e2e8f0;border-radius:8px;font-size:0.875rem;resize:vertical;" placeholder="Enter reason..."></textarea>
        </div>
        <div style="display:flex;gap:0.5rem;justify-content:flex-end;">
          <button type="button" class="btn btn--ghost btn--sm" onclick="document.getElementById('cancelModal').style.display='none'">Close</button>
          <button type="submit" class="btn btn--danger btn--sm"><i class="fas fa-times"></i> Confirm</button>
        </div>
      </form>
    </div>
  </div>
  <script src="<?= url('js/admin_dashboard.js') ?>"></script>
  <script src="<?= url('js/admin_interviews.js') ?>"></script>
</body>
</html>
