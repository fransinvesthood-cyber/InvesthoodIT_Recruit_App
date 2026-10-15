<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Placement Details | Investhood IT</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
  <link rel="stylesheet" href="<?= url('css/applications.css') ?>">
</head>
<body class="dashboard-page application-detail-page">
  <div class="dashboard">
    <aside class="sidebar" id="sidebar">
      <div class="sidebar__header">
        <a href="<?= url('index.php') ?>" class="logo">
          <span class="logo__icon"><i class="fas fa-code"></i></span>
          <span class="logo__text">Investhood <span class="logo__accent">IT</span></span>
        </a>
      </div>
      <nav class="sidebar__nav">
        <div class="sidebar__section-label">Main</div>
        <ul class="sidebar__menu">
          <li><a href="<?= url('candidate/dashboard.php') ?>" class="sidebar__link"><i class="fas fa-th-large"></i> Dashboard</a></li>
          <li><a href="<?= url('candidate/dashboard.php#dashboard-placements') ?>" class="sidebar__link active"><i class="fas fa-briefcase"></i> Placements</a></li>
          <li><a href="<?= url('candidate/applications.php') ?>" class="sidebar__link"><i class="fas fa-file-alt"></i> Applications</a></li>
        </ul>
      </nav>
      <div class="sidebar__footer">
        <div class="sidebar__user">
          <div class="sidebar__user-info">
            <span class="sidebar__user-name"><?= e($user['fullname'] ?? 'Candidate') ?></span>
            <span class="sidebar__user-role">Candidate</span>
          </div>
        </div>
        <a href="<?= url('auth/logout.php') ?>" class="sidebar__logout"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
      </div>
    </aside>
    <main class="dashboard__main">
      <header class="dash-header">
        <div class="dash-header__left">
          <a href="<?= url('candidate/dashboard.php#dashboard-placements') ?>" class="dash-header__back"><i class="fas fa-chevron-left"></i> Back to Placements</a>
        </div>
      </header>
      <div class="dash-content">
        <?= $flashes ?>
        <section class="app-detail__section">
          <div class="app-detail__header">
            <div>
              <div class="app-detail__header-badges">
                <span class="badge badge--success"><?= e($statusLabel) ?></span>
                <span class="app-detail__ref"><i class="fas fa-hashtag"></i> <?= e((string)($placement['placement_reference'] ?? '')) ?></span>
              </div>
              <h1 class="app-detail__title"><?= e($role !== null ? $role : 'Placement') ?></h1>
              <p class="app-detail__programme"><i class="fas fa-building"></i> <?= e($org !== null ? $org : '—') ?></p>
            </div>
          </div>
          <div class="app-detail__grid">
            <div class="app-info-card"><span class="app-info-card__label">Organisation</span><span class="app-info-card__value"><?= e($org !== null ? $org : '—') ?></span></div>
            <div class="app-info-card"><span class="app-info-card__label">Role</span><span class="app-info-card__value"><?= e($role !== null ? $role : '—') ?></span></div>
            <div class="app-info-card"><span class="app-info-card__label">Programme</span><span class="app-info-card__value"><?= e((string)($placement['programme_name'] ?? '—')) ?></span></div>
            <div class="app-info-card"><span class="app-info-card__label">Cohort</span><span class="app-info-card__value"><?= e($plCohort !== '' ? $plCohort : '—') ?></span></div>
            <div class="app-info-card"><span class="app-info-card__label">Location</span><span class="app-info-card__value"><?= e($plLoc !== '' ? $plLoc : '—') ?></span></div>
            <div class="app-info-card"><span class="app-info-card__label">Department</span><span class="app-info-card__value"><?= e($plDept !== '' ? $plDept : '—') ?></span></div>
            <div class="app-info-card"><span class="app-info-card__label">Start Date</span><span class="app-info-card__value"><?= e($start !== '' ? CandidatePlacement::formatDay($start) : '—') ?></span></div>
            <div class="app-info-card"><span class="app-info-card__label">End Date</span><span class="app-info-card__value"><?= e($end !== '' ? CandidatePlacement::formatDay($end) : '—') ?></span></div>
            <div class="app-info-card"><span class="app-info-card__label">Status</span><span class="app-info-card__value"><?= e($statusLabel) ?></span></div>
          </div>
          <p style="margin-top:1rem;color:var(--text-light);"><?= e($msg['message']) ?></p>
        </section>
      </div>
    </main>
  </div>
</body>
</html>
