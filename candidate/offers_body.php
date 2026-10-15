<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Offers | Investhood IT</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
  <link rel="stylesheet" href="<?= url('css/applications.css') ?>">
  <link rel="stylesheet" href="<?= url('css/candidate_offers.css') ?>">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
</head>
<body class="dashboard-page applications-page candidate-offers-page">
  <div class="dashboard">
    <aside class="sidebar" id="sidebar">
      <div class="sidebar__header">
        <a href="<?= url('candidate/dashboard.php') ?>" class="logo">
          <span class="logo__icon"><i class="fas fa-code"></i></span>
          <span class="logo__text">Investhood <span class="logo__accent">IT</span></span>
        </a>
        <button class="sidebar__close" id="sidebarClose" aria-label="Close sidebar"><i class="fas fa-times"></i></button>
      </div>
      <nav class="sidebar__nav">
        <div class="sidebar__section-label">Main</div>
        <ul class="sidebar__menu">
          <li><a href="<?= url('candidate/dashboard.php') ?>" class="sidebar__link"><i class="fas fa-th-large"></i> Dashboard</a></li>
          <li><a href="<?= url('candidate/opportunities.php') ?>" class="sidebar__link"><i class="fas fa-briefcase"></i> Opportunities</a></li>
          <li><a href="<?= url('candidate/applications.php') ?>" class="sidebar__link"><i class="fas fa-file-alt"></i> Applications</a></li>
          <li><a href="<?= url('candidate/interviews.php') ?>" class="sidebar__link"><i class="fas fa-calendar-check"></i> Interviews</a></li>
          <li><a href="<?= url('candidate/offers.php') ?>" class="sidebar__link active"><i class="fas fa-envelope-open-text"></i> Offers</a></li>
          <li><a href="<?= url('candidate/profile.php') ?>" class="sidebar__link"><i class="fas fa-user"></i> My Profile</a></li>
          <li><a href="<?= url('candidate/settings.php') ?>" class="sidebar__link"><i class="fas fa-cog"></i> Settings</a></li>
        </ul>
      </nav>
    </aside>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <main class="dashboard__main">
      <header class="dash-header">
        <div class="dash-header__left">
          <button class="dash-header__toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="fas fa-bars"></i></button>
          <a href="<?= url('candidate/dashboard.php#dashboard-offers') ?>" class="dash-header__back"><i class="fas fa-chevron-left"></i> Dashboard</a>
        </div>
        <div class="dash-header__right">
          <button class="dash-header__icon-btn" id="themeToggle" aria-label="Toggle dark mode"><i class="fas fa-moon"></i></button>
          <div class="dash-header__user"><img src="<?= url('candidate/avatar.php') ?>" alt="Profile" class="dash-header__avatar"></div>
        </div>
      </header>
      <div class="dash-content">
        <section class="opp-hero">
          <div class="opp-hero__inner">
            <div>
              <span class="section__badge">Selection &amp; Offers</span>
              <h1 class="opp-hero__title">My Offers</h1>
              <p class="opp-hero__subtitle">View offers issued by the programme team and accept or decline before the deadline.</p>
            </div>
          </div>
        </section>
        <?= $flashes ?>

        <section class="app-stats">
          <div class="app-stats__grid">
            <div class="stat-card"><span class="stat-card__number" data-count="<?= (int)($summary['stats']['total'] ?? 0) ?>"><?= (int)($summary['stats']['total'] ?? 0) ?></span><span class="stat-card__label">Total Offers</span></div>
            <div class="stat-card"><span class="stat-card__number" data-count="<?= (int)($summary['stats']['pending'] ?? 0) ?>"><?= (int)($summary['stats']['pending'] ?? 0) ?></span><span class="stat-card__label">Pending Response</span></div>
            <div class="stat-card"><span class="stat-card__number" data-count="<?= (int)($summary['stats']['accepted'] ?? 0) ?>"><?= (int)($summary['stats']['accepted'] ?? 0) ?></span><span class="stat-card__label">Accepted</span></div>
            <div class="stat-card"><span class="stat-card__number" data-count="<?= (int)($summary['stats']['expired'] ?? 0) ?>"><?= (int)($summary['stats']['expired'] ?? 0) ?></span><span class="stat-card__label">Expired</span></div>
          </div>
        </section>
        <section class="app-section" id="offers-list">
          <h2 class="section__title" style="font-size:1.25rem;">All Offers (<?= (int)($result['total'] ?? 0) ?>)</h2>
          <?php if (!empty($result['error'])): ?>
            <div class="offer-notice offer-notice--muted"><i class="fas fa-triangle-exclamation"></i><div><?= e($result['error']) ?></div></div>
          <?php endif; ?>
          <?php if (empty($result['records'])): ?>
            <div class="empty-state">
              <div class="empty-state__icon"><i class="fas fa-envelope-open-text"></i></div>
              <h3>No offers available at this time.</h3>
              <p>Once the programme team issues an offer, it will appear here with its deadline and response actions.</p>
              <a href="<?= url('candidate/applications.php') ?>" class="btn btn--outline btn--sm"><i class="fas fa-file-alt"></i> View Applications</a>
            </div>
          <?php else: ?>
            <div class="interview-grid offer-grid">
              <?php foreach ($result['records'] as $off): ?>
                <?php $tone = CandidateOffersController::statusTone($off); ?>
                <?php $tagTone = $tone === 'success' ? 'green' : ($tone === 'danger' ? 'red' : ($tone === 'primary' ? 'primary' : ($tone === 'amber' ? 'amber' : 'muted'))); ?>
                <?php $can = CandidateOffersController::canRespond($off); ?>
                <?php $lbl = CandidateOffersController::statusLabel($off); ?>
                <article class="interview-card offer-card">
                  <div class="interview-card__header">
                    <div>
                      <h3 class="interview-card__title"><?= e($off['title'] ?? $off['position'] ?? 'Offer') ?></h3>
                      <div class="interview-card__programme"><i class="fas fa-briefcase"></i><?= e($off['opportunity_title'] ?? $off['position'] ?? '') ?></div>
                    </div>
                    <span class="tag tag--<?= e($tagTone) ?>"><?= e($lbl) ?></span>
                  </div>
                  <div class="interview-card__details">
                    <div class="interview-card__detail"><i class="fas fa-graduation-cap"></i> <?= e($off['programme_name'] ?? '—') ?><?= !empty($off['cohort_name']) ? ' &middot; ' . e($off['cohort_name']) : '' ?></div>
                    <div class="interview-card__detail"><i class="fas fa-building"></i> <?= e($off['organisation'] ?? '—') ?></div>
                    <div class="interview-card__detail"><i class="fas fa-calendar-alt"></i> Offer date: <?= !empty($off['issued_at']) ? e(format_date($off['issued_at'], 'd M Y')) : '—' ?></div>
                    <div class="interview-card__detail"><i class="fas fa-hourglass-half"></i> Deadline: <?= !empty($off['expiry_date']) ? e(format_date($off['expiry_date'], 'd M Y')) : '—' ?></div>
                  </div>
                  <div class="interview-card__actions">
                    <a href="<?= url('candidate/offer_detail.php?id=' . (int)$off['id']) ?>" class="btn btn--outline btn--sm"><i class="fas fa-eye"></i> View Offer</a>
                  </div>
                  <?php if ($can): ?>
                    <div class="interview-card__actions" style="margin-top:.5rem;">
                      <form method="post" action="<?= url('candidate/offer_action.php') ?>" data-offer-confirm="accept" style="flex:1;display:flex;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="offer_id" value="<?= (int)$off['id'] ?>">
                        <input type="hidden" name="response" value="accepted">
                        <button type="submit" class="btn btn--primary btn--sm" style="flex:1;"><i class="fas fa-check"></i> Accept</button>
                      </form>
                      <form method="post" action="<?= url('candidate/offer_action.php') ?>" data-offer-confirm="decline" style="flex:1;display:flex;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="offer_id" value="<?= (int)$off['id'] ?>">
                        <input type="hidden" name="response" value="declined">
                        <input type="hidden" name="decline_reason" value="">
                        <button type="submit" class="btn btn--outline btn--sm" style="flex:1;"><i class="fas fa-times"></i> Decline</button>
                      </form>
                    </div>
                  <?php endif; ?>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>
      </div>
    </main>
  </div>
  <script src="<?= url('js/candidate_offers.js') ?>"></script>
</body>
</html>