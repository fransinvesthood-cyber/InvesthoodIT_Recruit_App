<?php
/**
 * ================================================
 * INVESTHOOD IT - Create Programme Page
 * ================================================
 * Admin-only programme creation form.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('admin');

$user  = current_user();
$old   = $_SESSION['programme_form_old'] ?? [];
$errors = $_SESSION['programme_form_errors'] ?? [];
unset($_SESSION['programme_form_old'], $_SESSION['programme_form_errors']);

$flashes = render_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Create Programme - Investhood IT Administrator">
  <title>Create Programme | Investhood IT Admin</title>
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
      <div class="sidebar__header">
        <a href="<?= url('index.php') ?>" class="logo"><span class="logo__icon"><i class="fas fa-code"></i></span><span class="logo__text">Investhood <span class="logo__accent">IT</span></span></a>
        <button class="sidebar__close" id="sidebarClose" aria-label="Close sidebar"><i class="fas fa-times"></i></button>
      </div>
      <nav class="sidebar__nav">
        <div class="sidebar__section-label">Command Centre</div>
        <ul class="sidebar__menu">
          <li><a href="<?= url('admin/dashboard.php') ?>" class="sidebar__link"><i class="fas fa-th-large"></i> Executive Overview</a></li>
        </ul>
        <div class="sidebar__section-label">Management</div>
        <ul class="sidebar__menu">
          <li><a href="<?= url('admin/programmes.php') ?>" class="sidebar__link active"><i class="fas fa-graduation-cap"></i> Programmes</a></li>
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
          <a href="<?= url('admin/programmes.php') ?>" class="btn btn--ghost btn--sm"><i class="fas fa-arrow-left"></i> Back to Programmes</a>
        </div>
        <div class="dash-header__right">
          <a href="<?= url('admin/programmes.php') ?>" class="btn btn--primary btn--sm"><i class="fas fa-th-large"></i> Manage Programmes</a>
          <div class="dash-header__user"><img src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'Admin+User') ?>&background=1a56db&color=fff&size=80" alt="Profile" class="dash-header__avatar"></div>
        </div>
      </header>

      <div class="dash-content">
        <section class="pm-hero">
          <div class="pm-hero__inner">
            <div class="pm-hero__text">
              <span class="section__badge">Programme Management</span>
              <h1 class="pm-hero__title">Create Programme</h1>
              <p class="pm-hero__subtitle">Set up a new programme. You can save it as a draft and publish it later once full details are in place.</p>
            </div>
          </div>
        </section>

        <?php if (!empty($errors['general'])): ?>
          <div class="pm-alert pm-alert--error"><i class="fas fa-exclamation-circle"></i> <?= e($errors['general']) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= url('admin/programme_actions.php') ?>" class="pm-form" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="create">

          <section class="pm-form-section">
            <div class="pm-form-section__header"><span class="pm-form-section__icon"><i class="fas fa-info-circle"></i></span><div><h3>Basic Information</h3><p>Core details about this programme.</p></div></div>
            <div class="pm-form-section__body">
              <div class="pm-form-row">
                <div class="pm-form-field pm-form-field--full">
                  <label class="pm-form-label" for="name">Programme Name <span class="pm-req">*</span></label>
                  <input type="text" id="name" name="name" class="form-input" value="<?= e($old['name'] ?? '') ?>" required>
                  <?= field_error($errors, 'name') ?>
                </div>
              </div>
              <div class="pm-form-row">
                <div class="pm-form-field">
                  <label class="pm-form-label" for="type">Programme Type <span class="pm-req">*</span></label>
                  <select id="type" name="type" class="form-input form-input--select">
                    <?php foreach (PROGRAMME_TYPE_LABELS as $slug => $label): ?>
                      <option value="<?= e($slug) ?>" <?= ($old['type'] ?? '') === $slug ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <?= field_error($errors, 'type') ?>
                </div>
                <div class="pm-form-field">
                  <label class="pm-form-label" for="duration">Programme Duration</label>
                  <input type="text" id="duration" name="duration" class="form-input" value="<?= e($old['duration'] ?? '') ?>" placeholder="e.g. 12 months">
                  <?= field_error($errors, 'duration') ?>
                </div>
              </div>
              <div class="pm-form-row">
                <div class="pm-form-field pm-form-field--full">
                  <label class="pm-form-label" for="description">Description <span class="pm-req">*</span></label>
                  <textarea id="description" name="description" class="form-input" rows="4" required><?= e($old['description'] ?? '') ?></textarea>
                  <?= field_error($errors, 'description') ?>
                </div>
              </div>
              <div class="pm-form-row">
                <div class="pm-form-field pm-form-field--full">
                  <label class="pm-form-label" for="objectives">Programme Objectives</label>
                  <textarea id="objectives" name="objectives" class="form-input" rows="4" placeholder="Outline the key objectives of this programme..."><?= e($old['objectives'] ?? '') ?></textarea>
                  <?= field_error($errors, 'objectives') ?>
                </div>
              </div>
            </div>
          </section>

          <section class="pm-form-section">
            <div class="pm-form-section__header"><span class="pm-form-section__icon"><i class="fas fa-calendar-alt"></i></span><div><h3>Dates</h3><p>Programme start and end dates.</p></div></div>
            <div class="pm-form-section__body">
              <div class="pm-form-row">
                <div class="pm-form-field">
                  <label class="pm-form-label" for="start_date">Programme Start Date</label>
                  <input type="date" id="start_date" name="start_date" class="form-input" value="<?= e($old['start_date'] ?? '') ?>">
                  <?= field_error($errors, 'start_date') ?>
                </div>
                <div class="pm-form-field">
                  <label class="pm-form-label" for="end_date">Programme End Date</label>
                  <input type="date" id="end_date" name="end_date" class="form-input" value="<?= e($old['end_date'] ?? '') ?>">
                  <?= field_error($errors, 'end_date') ?>
                </div>
              </div>
            </div>
          </section>

          <section class="pm-form-section">
            <div class="pm-form-section__header"><span class="pm-form-section__icon"><i class="fas fa-flag"></i></span><div><h3>Status</h3><p>Set the programme lifecycle status.</p></div></div>
            <div class="pm-form-section__body">
              <div class="pm-form-row">
                <div class="pm-form-field">
                  <label class="pm-form-label" for="status">Programme Status <span class="pm-req">*</span></label>
                  <select id="status" name="status" class="form-input form-input--select">
                    <?php foreach (PROGRAMME_STATUS_LABELS as $slug => $label): ?>
                      <option value="<?= e($slug) ?>" <?= ($old['status'] ?? 'draft') === $slug ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <?= field_error($errors, 'status') ?>
                </div>
              </div>
            </div>
          </section>

          <div class="pm-form-actions">
            <a href="<?= url('admin/programmes.php') ?>" class="btn btn--ghost"><i class="fas fa-times"></i> Cancel</a>
            <button type="submit" class="btn btn--primary"><i class="fas fa-save"></i> Create Programme</button>
          </div>
        </form>
      </div>

      <footer class="dash-footer admin-footer">
        <div class="container"><div class="dash-footer__inner"><p>&copy; 2025 Investhood IT. All rights reserved.</p></div></div>
      </footer>
    </main>
  </div>

  <script src="<?= url('js/script.js') ?>"></script>
  <script src="<?= url('js/admin_dashboard.js') ?>"></script>
  <script src="<?= url('js/admin_programmes.js') ?>"></script>
  <?= $flashes ?>
</body>
</html>
