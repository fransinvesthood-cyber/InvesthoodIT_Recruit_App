<?php
/**
 * ================================================
 * INVESTHOOD IT - Create Cohort Page
 * ================================================
 * Admin-only cohort creation form.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('admin');

$programme_id = (int) ($_GET['programme_id'] ?? 0);
$programme = $programme_id ? Programme::find($programme_id) : null;
if (!$programme && $programme_id > 0) {
    set_flash('error', 'Not Found', 'The selected programme does not exist.');
    safe_redirect('admin/programmes.php');
}

$user = current_user();
$old = $_SESSION['cohort_form_old'] ?? [];
$errors = $_SESSION['cohort_form_errors'] ?? [];
unset($_SESSION['cohort_form_old'], $_SESSION['cohort_form_errors']);

$programmes = Programme::all();
$flashes = render_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Create Cohort - Investhood IT Administrator">
  <title>Create Cohort | Investhood IT Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
  <link rel="stylesheet" href="<?= url('css/admin_programmes.css') ?>">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
</head>
<body class="dashboard-page admin-dashboard pm-module">

  <div class="dashboard">
    <aside class="sidebar admin-sidebar" id="adminSidebar">
      <div class="sidebar__header"><a href="<?= url('index.php') ?>" class="logo"><span class="logo__icon"><i class="fas fa-code"></i></span><span class="logo__text">Investhood <span class="logo__accent">IT</span></span></a><button class="sidebar__close" id="sidebarClose" aria-label="Close sidebar"><i class="fas fa-times"></i></button></div>
      <nav class="sidebar__nav">
        <div class="sidebar__section-label">Command Centre</div>
        <ul class="sidebar__menu"><li><a href="<?= url('admin/dashboard.php') ?>" class="sidebar__link"><i class="fas fa-th-large"></i> Executive Overview</a></li></ul>
        <div class="sidebar__section-label">Management</div>
        <ul class="sidebar__menu"><li><a href="<?= url('admin/programmes.php') ?>" class="sidebar__link active"><i class="fas fa-graduation-cap"></i> Programmes</a></li></ul>
      </nav>
      <div class="sidebar__footer">
        <div class="sidebar__user"><div class="sidebar__user-avatar"><img src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'Admin+User') ?>&background=1a56db&color=fff&size=80" alt="Profile"></div><div class="sidebar__user-info"><span class="sidebar__user-name"><?= e($user['fullname'] ?? 'Admin') ?></span><span class="sidebar__user-role">Administrator</span></div></div>
        <a href="<?= url('auth/logout.php') ?>" class="sidebar__logout"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
      </div>
    </aside>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="dashboard__main">
      <header class="dash-header admin-dash-header">
        <div class="dash-header__left"><button class="dash-header__toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="fas fa-bars"></i></button><a href="<?= $programme ? url('admin/programme_detail.php?id=' . $programme_id) : url('admin/programmes.php') ?>" class="btn btn--ghost btn--sm"><i class="fas fa-arrow-left"></i> Back</a></div>
        <div class="dash-header__right"><div class="dash-header__user"><img src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'Admin+User') ?>&background=1a56db&color=fff&size=80" alt="Profile" class="dash-header__avatar"></div></div>
      </header>

      <div class="dash-content">
        <section class="pm-hero">
          <div class="pm-hero__inner">
            <div class="pm-hero__text">
              <span class="section__badge">Cohort Management</span>
              <h1 class="pm-hero__title">Create Cohort</h1>
              <p class="pm-hero__subtitle">Add a new cohort intake under a programme. Cohort dates are validated against the parent programme.</p>
            </div>
          </div>
        </section>

        <?php if (!empty($errors['general'])): ?>
          <div class="pm-alert pm-alert--error"><i class="fas fa-exclamation-circle"></i> <?= e($errors['general']) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= url('admin/cohort_actions.php') ?>" class="pm-form" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="create">

          <section class="pm-form-section">
            <div class="pm-form-section__header"><span class="pm-form-section__icon"><i class="fas fa-layer-group"></i></span><div><h3>Cohort Details</h3></div></div>
            <div class="pm-form-section__body">
              <div class="pm-form-row">
                <div class="pm-form-field">
                  <label class="pm-form-label" for="programme_id">Parent Programme <span class="pm-req">*</span></label>
                  <select id="programme_id" name="programme_id" class="form-input form-input--select" required>
                    <option value="">Select a programme</option>
                    <?php foreach ($programmes as $p): ?>
                      <option value="<?= (int) $p['id'] ?>" <?= ((int)($old['programme_id'] ?? $programme_id)) === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <?= field_error($errors, 'programme_id') ?>
                </div>
                <div class="pm-form-field">
                  <label class="pm-form-label" for="name">Cohort Name <span class="pm-req">*</span></label>
                  <input type="text" id="name" name="name" class="form-input" value="<?= e($old['name'] ?? '') ?>" placeholder="e.g. 2026 Software Development Cohort" required>
                  <?= field_error($errors, 'name') ?>
                </div>
              </div>
              <div class="pm-form-row">
                <div class="pm-form-field pm-form-field--full">
                  <label class="pm-form-label" for="description">Cohort Description</label>
                  <textarea id="description" name="description" class="form-input" rows="3"><?= e($old['description'] ?? '') ?></textarea>
                </div>
              </div>
            </div>
          </section>

          <section class="pm-form-section">
            <div class="pm-form-section__header"><span class="pm-form-section__icon"><i class="fas fa-calendar-alt"></i></span><div><h3>Dates &amp; Applications</h3><p>Application dates control when candidates can apply.</p></div></div>
            <div class="pm-form-section__body">
              <div class="pm-form-row">
                <div class="pm-form-field"><label class="pm-form-label" for="start_date">Start Date</label><input type="date" id="start_date" name="start_date" class="form-input" value="<?= e($old['start_date'] ?? '') ?>"><?= field_error($errors, 'start_date') ?></div>
                <div class="pm-form-field"><label class="pm-form-label" for="end_date">End Date</label><input type="date" id="end_date" name="end_date" class="form-input" value="<?= e($old['end_date'] ?? '') ?>"><?= field_error($errors, 'end_date') ?></div>
              </div>
              <div class="pm-form-row">
                <div class="pm-form-field"><label class="pm-form-label" for="application_open_date">Application Opening Date</label><input type="date" id="application_open_date" name="application_open_date" class="form-input" value="<?= e($old['application_open_date'] ?? '') ?>"><?= field_error($errors, 'application_open_date') ?></div>
                <div class="pm-form-field"><label class="pm-form-label" for="application_close_date">Application Closing Date</label><input type="date" id="application_close_date" name="application_close_date" class="form-input" value="<?= e($old['application_close_date'] ?? '') ?>"><?= field_error($errors, 'application_close_date') ?></div>
              </div>
            </div>
          </section>

          <section class="pm-form-section">
            <div class="pm-form-section__header"><span class="pm-form-section__icon"><i class="fas fa-users"></i></span><div><h3>Capacity &amp; Location</h3></div></div>
            <div class="pm-form-section__body">
              <div class="pm-form-row">
                <div class="pm-form-field">
                  <label class="pm-form-label" for="max_capacity">Maximum Capacity <span class="pm-req">*</span></label>
                  <input type="number" id="max_capacity" name="max_capacity" class="form-input" min="1" value="<?= e($old['max_capacity'] ?? '50') ?>" required>
                  <?= field_error($errors, 'max_capacity') ?>
                </div>
                <div class="pm-form-field">
                  <label class="pm-form-label" for="delivery_mode">Delivery Mode</label>
                  <select id="delivery_mode" name="delivery_mode" class="form-input form-input--select">
                    <?php foreach (COHORT_DELIVERY_LABELS as $slug => $label): ?>
                      <option value="<?= e($slug) ?>" <?= ($old['delivery_mode'] ?? 'hybrid') === $slug ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="pm-form-row">
                <div class="pm-form-field">
                  <label class="pm-form-label" for="location">Location</label>
                  <input type="text" id="location" name="location" class="form-input" value="<?= e($old['location'] ?? '') ?>" placeholder="e.g. Sandton Campus">
                </div>
                <div class="pm-form-field">
                  <label class="pm-form-label" for="province">Province</label>
                  <select id="province" name="province" class="form-input form-input--select">
                    <option value="">Select province</option>
                    <?php foreach (ALLOWED_PROVINCES as $pr): ?>
                      <option value="<?= e($pr) ?>" <?= ($old['province'] ?? '') === $pr ? 'selected' : '' ?>><?= e(ucwords(str_replace('-', ' ', $pr))) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <?= field_error($errors, 'province') ?>
                </div>
              </div>
            </div>
          </section>

          <section class="pm-form-section">
            <div class="pm-form-section__header"><span class="pm-form-section__icon"><i class="fas fa-flag"></i></span><div><h3>Status</h3></div></div>
            <div class="pm-form-section__body">
              <div class="pm-form-row">
                <div class="pm-form-field">
                  <label class="pm-form-label" for="status">Cohort Status <span class="pm-req">*</span></label>
                  <select id="status" name="status" class="form-input form-input--select">
                    <?php foreach (COHORT_STATUS_LABELS as $slug => $label): ?>
                      <option value="<?= e($slug) ?>" <?= ($old['status'] ?? 'draft') === $slug ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <?= field_error($errors, 'status') ?>
                </div>
              </div>
            </div>
          </section>

          <div class="pm-form-actions">
            <a href="<?= $programme ? url('admin/programme_detail.php?id=' . $programme_id) : url('admin/programmes.php') ?>" class="btn btn--ghost"><i class="fas fa-times"></i> Cancel</a>
            <button type="submit" class="btn btn--primary"><i class="fas fa-save"></i> Create Cohort</button>
          </div>
        </form>
      </div>

      <footer class="dash-footer admin-footer"><div class="container"><div class="dash-footer__inner"><p>&copy; 2025 Investhood IT. All rights reserved.</p></div></div></footer>
    </main>
  </div>

  <script src="<?= url('js/script.js') ?>"></script>
  <script src="<?= url('js/admin_dashboard.js') ?>"></script>
  <script src="<?= url('js/admin_programmes.js') ?>"></script>
  <?= $flashes ?>
</body>
</html>
