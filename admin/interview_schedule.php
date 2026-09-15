<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');
$user = current_user();
$interviewId = (int) ($_GET['id'] ?? 0);
$isEdit = $interviewId > 0;
$errors = [];
$interview = null;
if ($isEdit) {
    $interview = Interview::find($interviewId);
    if (!$interview) { set_flash('error', 'Not Found', 'Interview does not exist.'); safe_redirect('admin/interviews.php'); }
}

$form = [
    'application_id' => $_POST['application_id'] ?? ($interview['application_id'] ?? ''),
    'interviewer_id' => $_POST['interviewer_id'] ?? ($interview['interviewer_id'] ?? ''),
    'interview_date' => $_POST['interview_date'] ?? ($interview['interview_date'] ?? ''),
    'start_time' => $_POST['start_time'] ?? ($interview['start_time'] ?? ''),
    'end_time' => $_POST['end_time'] ?? ($interview['end_time'] ?? ''),
    'interview_type' => $_POST['interview_type'] ?? ($interview['interview_type'] ?? 'online'),
    'location' => $_POST['location'] ?? ($interview['location'] ?? ''),
    'notes' => $_POST['notes'] ?? ($interview['notes'] ?? ''),
    'reschedule_reason' => $_POST['reschedule_reason'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (empty($form['application_id'])) { $errors['application_id'] = 'Please select an application.'; }
    // Enforce: only SHORTLISTED applications can be scheduled for an interview
    if (empty($errors['application_id'])) {
        $appRow = Database::fetchOne("SELECT id, status FROM applications WHERE id = ? LIMIT 1", 'i', [(int) $form['application_id']]);
        if (!$appRow) {
            $errors['application_id'] = 'The selected application does not exist.';
        } elseif ($isEdit) {
            // Rescheduling keeps the original application — it cannot be swapped
            if ((int) $appRow['id'] !== (int) ($interview['application_id'] ?? 0)) {
                $errors['application_id'] = 'The linked application cannot be changed when rescheduling.';
            }
        } elseif ($appRow['status'] !== 'shortlisted') {
            $errors['application_id'] = 'Only shortlisted applications can be scheduled for an interview.';
        }
    }
    if (empty($form['interview_date'])) { $errors['interview_date'] = 'Interview date is required.'; }
    if (empty($form['start_time'])) { $errors['start_time'] = 'Start time is required.'; }
    if (empty($form['end_time'])) { $errors['end_time'] = 'End time is required.'; }
    if ($form['start_time'] && $form['end_time'] && $form['end_time'] <= $form['start_time']) { $errors['end_time'] = 'End time must be after start time.'; }
    if (empty($form['interview_type'])) { $errors['interview_type'] = 'Please select an interview type.'; }
    if (empty($errors) && !empty($form['interviewer_id']) && !empty($form['interview_date']) && !empty($form['start_time']) && !empty($form['end_time'])) {
        if (Interview::hasConflict((int) $form['interviewer_id'], $form['interview_date'], $form['start_time'], $form['end_time'], $isEdit ? $interviewId : null)) {
            $errors['interviewer_id'] = 'This interviewer has a conflicting interview at this time.';
        }
    }
    if ($isEdit && empty($form['reschedule_reason'])) { $errors['reschedule_reason'] = 'Please provide a reason for rescheduling.'; }
    if (empty($errors)) {
        try {
            if ($isEdit) {
                Interview::reschedule($interviewId, $form, (int) current_user_id());
                set_flash('success', 'Rescheduled', 'The interview has been rescheduled successfully.');
            } else {
                $data = $form;
                $data['application_id'] = (int) $form['application_id'];
                $data['interviewer_id'] = $form['interviewer_id'] ?: null;
                $data['status'] = 'scheduled';
                $data['created_by'] = (int) current_user_id();
                $newId = Interview::create($data);
                set_flash('success', 'Scheduled', 'The interview has been scheduled successfully.');
            }
            safe_redirect('admin/interview.php?id=' . ($isEdit ? $interviewId : $newId));
        } catch (Exception $e) { $errors['general'] = $e->getMessage(); }
    }
}

// Only SHORTLISTED applications may be scheduled for an interview
$applications = Database::fetchAll("SELECT a.id, a.application_reference, u.first_name, u.last_name, o.title AS opportunity_title, p.name AS programme_name FROM applications a INNER JOIN users u ON u.id = a.candidate_id INNER JOIN opportunities o ON o.id = a.opportunity_id INNER JOIN programmes p ON p.id = o.programme_id WHERE a.status = 'shortlisted' ORDER BY a.updated_at DESC");
$interviewers = Interview::availableInterviewers();

// Upcoming scheduled interviews listed below the form
$upcoming = Interview::upcoming(6);
$flashes = render_flashes();
$pageTitle = $isEdit ? 'Reschedule Interview' : 'Schedule Interview';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?> | Investhood IT Admin</title>
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
        <a href="<?= url('index.php') ?>" class="logo"><span class="logo__icon"><i class="fas fa-code"></i></span><span class="logo__text">Investhood <span class="logo__accent">IT</span></span></a>
        <button class="sidebar__close" id="sidebarClose" aria-label="Close sidebar"><i class="fas fa-times"></i></button>
      </div>
      <nav class="sidebar__nav">
        <div class="sidebar__section-label">Command Centre</div>
        <ul class="sidebar__menu"><li><a href="<?= url('admin/dashboard.php') ?>" class="sidebar__link"><i class="fas fa-th-large"></i> Executive Overview</a></li></ul>
        <div class="sidebar__section-label">Management</div>
        <ul class="sidebar__menu">
          <li><a href="<?= url('admin/programmes.php') ?>" class="sidebar__link"><i class="fas fa-graduation-cap"></i> Programmes</a></li>
          <li><a href="<?= url('admin/opportunities.php') ?>" class="sidebar__link"><i class="fas fa-briefcase"></i> Opportunities</a></li>
          <li><a href="<?= url('admin/applications.php') ?>" class="sidebar__link"><i class="fas fa-file-alt"></i> Applications</a></li>
        </ul>
        <div class="sidebar__section-label">Operations</div>
        <ul class="sidebar__menu"><li><a href="<?= url('admin/interviews.php') ?>" class="sidebar__link active"><i class="fas fa-calendar-check"></i> Interviews</a></li></ul>
      </nav>
      <div class="sidebar__footer">
        <div class="sidebar__user">
          <div class="sidebar__user-avatar"><img src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'Admin+User') ?>&background=1a56db&color=fff&size=40" alt="Admin"></div>
          <div class="sidebar__user-info"><span class="sidebar__user-name"><?= e($user['fullname'] ?? 'Admin User') ?></span><span class="sidebar__user-role">Administrator</span></div>
        </div>
        <a href="<?= url('auth/logout.php') ?>" class="sidebar__logout"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
      </div>
    </aside>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="dashboard__main">
      <header class="dash-header admin-dash-header">
        <div class="dash-header__left">
          <a href="<?= $isEdit ? url('admin/interview.php?id=' . $interviewId) : url('admin/interviews.php') ?>" class="btn btn--ghost btn--sm"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
        <div class="dash-header__right">
          <button class="dash-header__icon-btn" id="themeToggle" aria-label="Toggle dark mode"><i class="fas fa-moon"></i></button>
          <div class="dash-header__user"><img src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'Admin+User') ?>&background=1a56db&color=fff&size=80" alt="Profile" class="dash-header__avatar"></div>
        </div>
      </header>

      <div class="dash-content">
        <?= $flashes ?>

        <section class="app-hero">
          <div class="app-hero__inner">
            <div>
              <span class="section__badge"><?= $isEdit ? 'Reschedule Interview' : 'Schedule Interview' ?></span>
              <h1 class="app-hero__title"><?= $pageTitle ?></h1>
              <p class="app-hero__subtitle"><?= $isEdit ? 'Update the interview details below.' : 'Fill in the details below to schedule a new interview.' ?></p>
            </div>
          </div>
        </section>

        <?php if (!empty($errors['general'])): ?>
          <div class="notification notification--error" style="margin-bottom:1rem;">
            <div class="notification__icon"><i class="fas fa-exclamation-circle"></i></div>
            <div class="notification__content"><div class="notification__message"><?= e($errors['general']) ?></div></div>
          </div>
        <?php endif; ?>

        <div class="int-detail-layout">
          <div class="int-detail-main">
            <form method="POST" action="" class="int-form">
              <?= csrf_field() ?>

              <section class="int-section">
                <h2 class="int-section__title"><i class="fas fa-file-alt"></i> Application</h2>
                <div class="int-form-group">
                  <label for="application_id" class="int-form-group__label">Candidate Application <span class="int-form-group__req">*</span></label>
                  <div class="int-input-wrap">
                    <i class="fas fa-id-badge"></i>
                    <select name="application_id" id="application_id" class="int-input int-input--select <?= isset($errors['application_id']) ? 'int-input--error' : '' ?>" <?= $isEdit ? 'disabled' : '' ?> required>
                      <option value="">Select an application...</option>
                      <?php if ($isEdit && $interview): ?>
                        <option value="<?= (int) $interview['application_id'] ?>" selected>
                          <?= e($interview['application_reference'] ?? '') ?> — <?= e(($interview['candidate_first_name'] ?? '') . ' ' . ($interview['candidate_last_name'] ?? '')) ?> (<?= e($interview['programme_name'] ?? '') ?>)
                        </option>
                      <?php endif; ?>
                      <?php foreach ($applications as $app): ?>
                        <option value="<?= (int) $app['id'] ?>" <?= ($form['application_id'] == $app['id']) ? 'selected' : '' ?>>
                          <?= e($app['application_reference']) ?> — <?= e($app['first_name'] . ' ' . $app['last_name']) ?> (<?= e($app['programme_name']) ?>)
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <p class="int-form-hint">
                    <?php if ($isEdit): ?>
                      The linked application cannot be changed when rescheduling.
                    <?php else: ?>
                      Only applications with the <strong>Shortlisted</strong> status are listed.
                    <?php endif; ?>
                  </p>
                  <?= field_error($errors, 'application_id') ?>
                  <?php if (!$isEdit && empty($applications)): ?>
                    <div class="int-form-note"><i class="fas fa-info-circle"></i><span>No shortlisted applications available yet. Shortlist an application from the <a href="<?= url('admin/applications.php') ?>"><strong>Applications</strong></a> page before scheduling an interview.</span></div>
                  <?php endif; ?>
                  <?php if ($isEdit): ?><input type="hidden" name="application_id" value="<?= e($form['application_id']) ?>"><?php endif; ?>
                </div>
              </section>

              <section class="int-section">
                <h2 class="int-section__title"><i class="fas fa-calendar"></i> Schedule</h2>
                <div class="int-form__grid">
                  <div class="int-form-group">
                    <label for="interview_date" class="int-form-group__label">Interview Date <span class="int-form-group__req">*</span></label>
                    <div class="int-input-wrap">
                      <i class="fas fa-calendar-day"></i>
                      <input type="date" name="interview_date" id="interview_date" class="int-input <?= isset($errors['interview_date']) ? 'int-input--error' : '' ?>" value="<?= e($form['interview_date']) ?>" required>
                    </div>
                    <?= field_error($errors, 'interview_date') ?>
                  </div>
                  <div class="int-form-group">
                    <label for="interview_type" class="int-form-group__label">Interview Type <span class="int-form-group__req">*</span></label>
                    <div class="int-input-wrap">
                      <i class="fas fa-video"></i>
                      <select name="interview_type" id="interview_type" class="int-input int-input--select <?= isset($errors['interview_type']) ? 'int-input--error' : '' ?>" required>
                        <?php foreach (Interview::TYPES as $t): ?>
                          <option value="<?= $t ?>" <?= $form['interview_type'] === $t ? 'selected' : '' ?>><?= e(Interview::typeLabel($t)) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <p class="int-form-hint">Include a meeting link below for online interviews.</p>
                    <?= field_error($errors, 'interview_type') ?>
                  </div>
                </div>
                <div class="int-form__grid">
                  <div class="int-form-group">
                    <label for="start_time" class="int-form-group__label">Start Time <span class="int-form-group__req">*</span></label>
                    <div class="int-input-wrap">
                      <i class="fas fa-clock"></i>
                      <input type="time" name="start_time" id="start_time" class="int-input <?= isset($errors['start_time']) ? 'int-input--error' : '' ?>" value="<?= e($form['start_time']) ?>" required>
                    </div>
                    <?= field_error($errors, 'start_time') ?>
                  </div>
                  <div class="int-form-group">
                    <label for="end_time" class="int-form-group__label">End Time <span class="int-form-group__req">*</span></label>
                    <div class="int-input-wrap">
                      <i class="fas fa-flag-checkered"></i>
                      <input type="time" name="end_time" id="end_time" class="int-input <?= isset($errors['end_time']) ? 'int-input--error' : '' ?>" value="<?= e($form['end_time']) ?>" required>
                    </div>
                    <p class="int-form-hint">Must be after the start time.</p>
                    <?= field_error($errors, 'end_time') ?>
                  </div>
                </div>
              </section>

              <section class="int-section">
                <h2 class="int-section__title"><i class="fas fa-user-tie"></i> Interviewer & Location</h2>
                <div class="int-form__grid">
                  <div class="int-form-group">
                    <label for="interviewer_id" class="int-form-group__label">Interviewer <span class="int-form-group__opt">Optional</span></label>
                    <div class="int-input-wrap">
                      <i class="fas fa-user-tie"></i>
                      <select name="interviewer_id" id="interviewer_id" class="int-input int-input--select <?= isset($errors['interviewer_id']) ? 'int-input--error' : '' ?>">
                        <option value="">Select an interviewer...</option>
                        <?php foreach ($interviewers as $i): ?>
                          <option value="<?= (int) $i['id'] ?>" <?= ($form['interviewer_id'] == $i['id']) ? 'selected' : '' ?>>
                            <?= e($i['first_name'] . ' ' . $i['last_name']) ?> (<?= e($i['role_name']) ?>)
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <p class="int-form-hint">Leave empty to assign an interviewer later.</p>
                    <?= field_error($errors, 'interviewer_id') ?>
                  </div>
                  <div class="int-form-group">
                    <label for="location" class="int-form-group__label">Location / Meeting Link <span class="int-form-group__opt">Optional</span></label>
                    <div class="int-input-wrap">
                      <i class="fas fa-location-dot"></i>
                      <input type="text" name="location" id="location" class="int-input" value="<?= e($form['location']) ?>" placeholder="Room 201, https://zoom.us/j/123456789, or phone number">
                    </div>
                    <p class="int-form-hint">Room, meeting link, or phone number.</p>
                  </div>
                </div>
              </section>

              <section class="int-section">
                <h2 class="int-section__title"><i class="fas fa-sticky-note"></i> Notes</h2>
                <div class="int-form-group">
                  <label for="notes" class="int-form-group__label">Notes / Instructions <span class="int-form-group__opt">Optional</span></label>
                  <textarea name="notes" id="notes" class="int-input int-input--textarea" rows="4" placeholder="Notes for interviewer or candidate..."><?= e($form['notes']) ?></textarea>
                </div>
                <?php if ($isEdit): ?>
                  <div class="int-form-group">
                    <label for="reschedule_reason" class="int-form-group__label">Reschedule Reason <span class="int-form-group__req">*</span></label>
                    <textarea name="reschedule_reason" id="reschedule_reason" class="int-input int-input--textarea <?= isset($errors['reschedule_reason']) ? 'int-input--error' : '' ?>" rows="3" placeholder="Explain why this interview is being rescheduled..." required><?= e($form['reschedule_reason']) ?></textarea>
                    <p class="int-form-hint">Recorded in the interview status history for audit purposes.</p>
                    <?= field_error($errors, 'reschedule_reason') ?>
                  </div>
                <?php endif; ?>
              </section>

              <div class="int-form-actions">
                <span class="int-form-actions__meta"><i class="fas fa-asterisk"></i> Required field</span>
                <a href="<?= $isEdit ? url('admin/interview.php?id=' . $interviewId) : url('admin/interviews.php') ?>" class="btn btn--ghost">Cancel</a>
                <button type="submit" class="btn btn--primary"><i class="fas fa-save"></i> <?= $isEdit ? 'Reschedule Interview' : 'Schedule Interview' ?></button>
              </div>
            </form>
          </div>

          <div class="int-detail-sidebar">
            <section class="int-section">
              <h2 class="int-section__title"><i class="fas fa-info-circle"></i> Guidelines</h2>
              <ul style="padding-left:1.25rem;font-size:0.85rem;color:#475569;line-height:1.8;">
                <li>Only applications with the <strong>Shortlisted</strong> status can be scheduled.</li>
                <li>Ensure no interviewer conflict at the chosen time.</li>
                <li>End time must be after start time.</li>
                <li>Include meeting link in location for online interviews.</li>
              </ul>
            </section>
          </div>
        </div>

        <!-- ===== UPCOMING SCHEDULED INTERVIEWS ===== -->
        <section class="int-table-section int-upcoming-section">
          <div class="int-table-header">
            <h2 class="int-table-title"><i class="fas fa-calendar-check"></i> Scheduled Interviews <span class="int-table-count">(<?= count($upcoming) ?>)</span></h2>
            <a href="<?= url('admin/interviews.php') ?>" class="btn btn--ghost btn--sm"><i class="fas fa-list"></i> View All</a>
          </div>
          <?php if (empty($upcoming)): ?>
            <div class="int-empty">
              <div class="int-empty__icon"><i class="fas fa-calendar-check"></i></div>
              <h3 class="int-empty__title">No interviews scheduled yet</h3>
              <p class="int-empty__text">Upcoming interviews will appear here as soon as you schedule them with the form above.</p>
            </div>
          <?php else: ?>
            <div class="int-table-wrap">
              <table class="int-table">
                <thead>
                  <tr>
                    <th>Candidate</th>
                    <th>Programme</th>
                    <th>Date &amp; Time</th>
                    <th>Type</th>
                    <th>Interviewer</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($upcoming as $iv): ?>
                    <?php
                    $ivInterviewer = !empty($iv['interviewer_first_name']) ? e($iv['interviewer_first_name'] . ' ' . $iv['interviewer_last_name']) : '<span class="text-muted">&mdash;</span>';
                    ?>
                    <tr>
                      <td>
                        <div class="int-candidate">
                          <div class="int-candidate__avatar">
                            <?= e(strtoupper(substr($iv['candidate_first_name'] ?? 'A', 0, 1) . substr($iv['candidate_last_name'] ?? 'U', 0, 1))) ?>
                          </div>
                          <div class="int-candidate__info">
                            <span class="int-candidate__name"><?= e(($iv['candidate_first_name'] ?? '') . ' ' . ($iv['candidate_last_name'] ?? '')) ?></span>
                            <span class="int-candidate__email"><?= e($iv['candidate_email'] ?? '') ?></span>
                          </div>
                        </div>
                      </td>
                      <td><?= e($iv['programme_name'] ?? '&mdash;') ?></td>
                      <td>
                        <div class="int-datetime">
                          <span class="int-datetime__date"><i class="fas fa-calendar"></i> <?= e(format_date($iv['interview_date'], 'd M Y')) ?></span>
                          <span class="int-datetime__time"><i class="fas fa-clock"></i> <?= e(substr((string) $iv['start_time'], 0, 5)) ?> &ndash; <?= e(substr((string) $iv['end_time'], 0, 5)) ?></span>
                        </div>
                      </td>
                      <td><span class="int-type int-type--<?= e($iv['interview_type']) ?>"><?= e(Interview::typeLabel($iv['interview_type'])) ?></span></td>
                      <td><?= $ivInterviewer ?></td>
                      <td><span class="int-status int-status--<?= e(Interview::badgeTone($iv['status'])) ?>"><?= e(Interview::label($iv['status'])) ?></span></td>
                      <td>
                        <div class="int-actions">
                          <a href="<?= url('admin/interview.php?id=' . (int) $iv['id']) ?>" class="btn btn--ghost btn--sm" title="View"><i class="fas fa-eye"></i></a>
                          <a href="<?= url('admin/interview_schedule.php?id=' . (int) $iv['id']) ?>" class="btn btn--ghost btn--sm" title="Reschedule"><i class="fas fa-edit"></i></a>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </section>
      </div>
    </main>
  </div>
  <script src="<?= url('js/admin_dashboard.js') ?>"></script>
  <script src="<?= url('js/admin_interviews.js') ?>"></script>
</body>
</html>
