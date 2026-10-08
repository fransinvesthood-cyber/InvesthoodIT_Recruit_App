<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Offer Details - Investhood IT">
  <title>Offer Details | Investhood IT</title>
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
          <a href="<?= url('candidate/offers.php') ?>" class="dash-header__back"><i class="fas fa-chevron-left"></i> Back to Offers</a>
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
              <span class="section__badge">My Offers</span>
              <h1 class="opp-hero__title"><?= e($offer['title'] ?? $offer['position'] ?? 'Offer') ?></h1>
              <p class="opp-hero__subtitle"><?= e($offer['opportunity_title'] ?? '') ?><?= !empty($offer['organisation']) ? ' &middot; ' . e($offer['organisation']) : '' ?></p>
            </div>
            <div class="app-hero__actions">
              <span class="tag tag--<?= e($offerTagTone) ?>" style="font-size:.85rem;"><?= e($offerLabel) ?></span>
            </div>
          </div>
        </section>
        <?= $flashes ?>
        <?php $expiryTop = trim((string) ($offer['expiry_date'] ?? '')); ?>
        <?php if ($offerRespondable): ?>
          <div class="offer-notice offer-notice--action">
            <i class="fas fa-hourglass-half"></i>
            <div><strong>Your response is required<?= $expiryTop !== '' ? ' by ' . e(format_date($expiryTop, 'd M Y')) : '' ?>.</strong>
            <span>Review the full offer below, then accept or decline.</span></div>
          </div>
        <?php elseif ($respondableMessage !== null): ?>
          <div class="offer-notice offer-notice--muted">
            <i class="fas fa-info-circle"></i><div><?= e($respondableMessage) ?></div>
          </div>
        <?php endif; ?>

        <section class="app-section">
          <div class="interview-detail__grid">
            <div class="interview-detail__card">
              <div class="interview-detail__card--header">
                <div>
                  <h2 class="section__title" style="font-size:1.1rem;margin:0;">Offer Summary</h2>
                  <div class="interview-detail__meta">Ref: <?= e($offer['application_reference'] ?? ('APP-' . (int)($offer['application_id'] ?? 0))) ?></div>
                </div>
                <span class="tag tag--<?= e($offerTagTone) ?>"><?= e($offerLabel) ?></span>
              </div>
              <div class="interview-detail__row"><span class="label">Programme</span><span class="value"><?= e($offer['programme_name'] ?? '—') ?></span></div>
              <div class="interview-detail__row"><span class="label">Cohort</span><span class="value"><?= e($offer['cohort_name'] ?? '—') ?></span></div>
              <div class="interview-detail__row"><span class="label">Opportunity</span><span class="value"><?= e($offer['opportunity_title'] ?? $offer['position'] ?? '—') ?></span></div>
              <div class="interview-detail__row"><span class="label">Organisation</span><span class="value"><?= e($offer['organisation'] ?? '—') ?></span></div>
              <div class="interview-detail__row"><span class="label">Offer date</span><span class="value"><?= trim((string)($offer['issued_at'] ?? '')) !== '' ? e(format_date($offer['issued_at'], 'd M Y')) : '—' ?></span></div>
              <div class="interview-detail__row"><span class="label">Deadline</span><span class="value"><?= $expiryTop !== '' ? e(format_date($expiryTop, 'd M Y')) : '—' ?></span></div>
              <div class="interview-detail__row"><span class="label">Start date</span><span class="value"><?= trim((string)($offer['start_date'] ?? '')) !== '' ? e(format_date($offer['start_date'], 'd M Y')) : '—' ?></span></div>
            </div>
            <div class="interview-detail__card">
              <h2 class="section__title" style="font-size:1.1rem;margin:0 0 .75rem;">Placement &amp; Terms</h2>
              <?php if (trim((string)($offer['placement_details'] ?? '')) !== ''): ?>
                <div class="interview-detail__instructions" style="margin-bottom:1rem;"><p><?= nl2br(e($offer['placement_details'])) ?></p></div>
              <?php endif; ?>
              <?php if (trim((string)($offer['terms'] ?? '')) !== ''): ?>
                <div class="interview-detail__instructions" style="margin-bottom:1rem;"><p><strong>Terms</strong></p><p><?= nl2br(e($offer['terms'])) ?></p></div>
              <?php endif; ?>
              <?php if (!empty($adminNotes)): ?>
                <h3 style="font-size:.9rem;margin:0 0 .5rem;">Notes from the programme team</h3>
                <?php foreach ($adminNotes as $note): ?>
                  <div class="offer-history__item">
                    <div class="offer-history__label"><?= e($note['label']) ?><?= !empty($note['at']) ? ' &middot; ' . e(format_date($note['at'], 'd M Y, H:i')) : '' ?></div>
                    <div class="offer-history__reason"><?= e($note['reason']) ?></div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
              <?php if (($offer['status'] ?? '') === 'accepted'): ?>
                <div class="offer-notice offer-notice--success" style="margin-top:1rem;">
                  <i class="fas fa-check-circle"></i>
                  <div>Offer accepted. Watch <a href="<?= url('candidate/dashboard.php#dashboard-placements') ?>">Placements</a> for next steps.</div>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </section>
        <section class="app-section offer-response" id="offer-response">
          <h2 class="section__title" style="font-size:1.15rem;">Your Response</h2>
          <?php if ($offerRespondable): ?>
            <div class="offer-response__actions">
              <form method="post" action="<?= url('candidate/offer_action.php') ?>" data-offer-confirm="accept" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="offer_id" value="<?= (int)$offer['id'] ?>">
                <input type="hidden" name="response" value="accepted">
                <input type="hidden" name="return_to" value="detail">
                <button type="submit" class="btn btn--primary"><i class="fas fa-check"></i> Accept Offer</button>
              </form>
              <form method="post" action="<?= url('candidate/offer_action.php') ?>" data-offer-confirm="decline" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="offer_id" value="<?= (int)$offer['id'] ?>">
                <input type="hidden" name="response" value="declined">
                <input type="hidden" name="decline_reason" value="">
                <input type="hidden" name="return_to" value="detail">
                <button type="submit" class="btn btn--outline"><i class="fas fa-times"></i> Decline Offer</button>
              </form>
            </div>
            <p class="offer-response__hint">Confirmation is required before submitting. The programme team is notified immediately.</p>
          <?php else: ?>
            <div class="empty-state empty-state--sm">
              <div class="empty-state__icon"><i class="fas fa-lock"></i></div>
              <h3>Response closed</h3>
              <p><?= e($respondableMessage ?? 'This offer can no longer be responded to.') ?></p>
              <a href="<?= url('candidate/offers.php') ?>" class="btn btn--outline btn--sm"><i class="fas fa-arrow-left"></i> Back to Offers</a>
            </div>
          <?php endif; ?>
        </section>
        <?php if (!empty($offerHistory)): ?>
          <section class="app-section">
            <h2 class="section__title" style="font-size:1.15rem;">Offer Timeline</h2>
            <div class="offer-history">
              <?php foreach ($offerHistory as $entry): ?>
                <div class="offer-history__item">
                  <div class="offer-history__label"><?= e(CandidateOffersController::statusLabel((string)($entry['new_status'] ?? ''))) ?><?= !empty($entry['created_at']) ? ' &middot; ' . e(format_date($entry['created_at'], 'd M Y, H:i')) : '' ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>
      </div>
    </main>
  </div>
  <script src="<?= url('js/candidate_offers.js') ?>"></script>
</body>
</html>