<?php
/**
 * ================================================
 * INVESTHOOD IT - Public Programme Details
 * ================================================
 * Public-facing programme details page for visitors.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$programmeId = (int) ($_GET['id'] ?? 0);

if (!$programmeId) {
  redirect(url('index.php'));
}

// Fetch programme with cohort and opportunity counts
$programme = Database::fetchOne(
  "SELECT p.id, p.name, p.type, p.description, p.objectives, p.duration, 
          p.start_date, p.end_date, p.status,
          COUNT(DISTINCT c.id) AS cohort_count,
          COUNT(DISTINCT o.id) AS opportunity_count
   FROM programmes p
   LEFT JOIN cohorts c ON c.programme_id = p.id
   LEFT JOIN opportunities o ON o.programme_id = p.id AND o.status = 'published'
   WHERE p.id = ? AND p.status = 'active'
   GROUP BY p.id",
  'i',
  [$programmeId]
);

if (!$programme) {
  redirect(url('index.php'));
}

// Fetch open opportunities for this programme
$opportunities = Database::fetchAll(
  "SELECT o.id, o.title, o.type, o.organisation, o.short_description,
          o.province, o.city, o.work_arrangement, o.application_close_date,
          o.available_positions,
          c.name AS cohort_name
   FROM opportunities o
   LEFT JOIN cohorts c ON c.id = o.cohort_id
   WHERE o.programme_id = ? AND o.status = 'published'
   ORDER BY o.created_at DESC",
  'i',
  [$programmeId]
);

$typeLabel = [
  'graduate_programme' => 'Graduate Programme',
  'learnership' => 'Learnership',
  'internship' => 'Internship',
  'wil' => 'Work Integrated Learning',
  'skills_development' => 'Skills Development',
  'other' => 'Programme'
][$programme['type']] ?? 'Programme';

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= e($programme['name']) ?> - Investhood IT Programme & Scarce Skills Platform">
  <title><?= e($programme['name']) ?> | Investhood IT</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
  <link rel="stylesheet" href="<?= url('css/programme_manager_enhancements.css') ?>?v=20260919">
</head>
<body>

  <!-- ===== NAVIGATION ===== -->
  <header class="header" id="header">
    <div class="container header__container">
      <a href="<?= url('index.php') ?>" class="logo">
        <span class="logo__icon"><i class="fas fa-code"></i></span>
        <span class="logo__text">Investhood <span class="logo__accent">IT</span></span>
      </a>
      <nav class="nav" id="nav">
        <ul class="nav__list">
          <li><a href="<?= url('index.php#home') ?>" class="nav__link">Home</a></li>
          <li><a href="<?= url('index.php#programmes') ?>" class="nav__link">Programmes</a></li>
          <li><a href="<?= url('index.php#opportunities') ?>" class="nav__link">Opportunities</a></li>
          <li><a href="<?= url('index.php#talent') ?>" class="nav__link">Talent Community</a></li>
        </ul>
        <div class="nav__actions">
          <?php if (!is_logged_in()): ?>
            <a href="<?= url('login.php') ?>" class="btn btn--outline btn--sm">Login</a>
            <a href="<?= url('register.php') ?>" class="btn btn--primary btn--sm">Register</a>
          <?php else: ?>
            <a href="<?= url('candidate/dashboard.php') ?>" class="btn btn--primary btn--sm">Dashboard</a>
          <?php endif; ?>
        </div>
      </nav>
      <button class="hamburger" id="hamburger" aria-label="Toggle menu">
        <span class="hamburger__line"></span>
        <span class="hamburger__line"></span>
        <span class="hamburger__line"></span>
      </button>
    </div>
  </header>

  <!-- ===== PAGE CONTENT ===== -->
  <section class="section" style="padding-top: 4rem; margin-top: 2rem;">
    <div class="container">
      <div style="margin-bottom: 2rem;">
        <a href="<?= url('index.php#programmes') ?>" style="color: var(--primary); text-decoration: none; font-size: 0.875rem; display: flex; align-items: center; gap: 0.5rem;">
          <i class="fas fa-chevron-left"></i> Back to Programmes
        </a>
      </div>

      <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-bottom: 3rem;">
        <!-- Main Content -->
        <div>
          <div style="margin-bottom: 2rem;">
            <span style="background: var(--primary-bg); color: var(--primary); padding: 0.5rem 1rem; border-radius: var(--radius); font-size: 0.8125rem; font-weight: 600; display: inline-block;"><?= e($typeLabel) ?></span>
            <h1 style="font-size: 2.5rem; font-weight: 800; margin: 1rem 0; color: var(--dark);"><?= e($programme['name']) ?></h1>
            <p style="font-size: 1.125rem; color: var(--text); line-height: 1.6; margin: 0;"><?= e($programme['description']) ?></p>
          </div>

          <?php if (!empty($programme['objectives'])): ?>
            <div style="margin-bottom: 2rem;">
              <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1rem; color: var(--dark);">Programme Objectives</h2>
              <p style="color: var(--text); line-height: 1.6; margin: 0; white-space: pre-wrap;"><?= e($programme['objectives']) ?></p>
            </div>
          <?php endif; ?>

          <div style="background: var(--bg); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 1.5rem; margin-bottom: 2rem;">
            <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 1rem; color: var(--dark);">Programme Details</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div>
                <p style="color: var(--text-light); font-size: 0.875rem; margin: 0 0 0.25rem;">Duration</p>
                <p style="font-weight: 600; color: var(--dark); margin: 0;"><?= e($programme['duration'] ?? 'TBD') ?></p>
              </div>
              <div>
                <p style="color: var(--text-light); font-size: 0.875rem; margin: 0 0 0.25rem;">Start Date</p>
                <p style="font-weight: 600; color: var(--dark); margin: 0;"><?= !empty($programme['start_date']) ? e(format_date($programme['start_date'])) : 'TBD' ?></p>
              </div>
              <div>
                <p style="color: var(--text-light); font-size: 0.875rem; margin: 0 0 0.25rem;">End Date</p>
                <p style="font-weight: 600; color: var(--dark); margin: 0;"><?= !empty($programme['end_date']) ? e(format_date($programme['end_date'])) : 'TBD' ?></p>
              </div>
              <div>
                <p style="color: var(--text-light); font-size: 0.875rem; margin: 0 0 0.25rem;">Status</p>
                <p style="font-weight: 600; color: var(--dark); margin: 0;"><?= e(ucfirst($programme['status'])) ?></p>
              </div>
            </div>
          </div>
        </div>

        <!-- Sidebar -->
        <div>
          <div style="background: var(--bg-white); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 1.5rem; position: sticky; top: 2rem;">
            <div style="text-align: center; margin-bottom: 1.5rem;">
              <div style="font-size: 2rem; font-weight: 800; color: var(--primary); margin-bottom: 0.25rem;"><?= e($programme['opportunity_count'] ?? 0) ?></div>
              <p style="color: var(--text-light); font-size: 0.875rem; margin: 0;">Open Opportunities</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Opportunities Section -->
      <?php if (!empty($opportunities)): ?>
        <div style="margin-top: 3rem;">
          <h2 style="font-size: 1.75rem; font-weight: 700; margin-bottom: 1.5rem; color: var(--dark);">Available Opportunities</h2>
          <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem;">
            <?php foreach ($opportunities as $opp):
              $daysLeft = 0;
              if (!empty($opp['application_close_date'])) {
                $closeDate = new DateTime($opp['application_close_date']);
                $today = new DateTime();
                $diff = $closeDate->diff($today);
                $daysLeft = $diff->invert ? $diff->days : -1;
              }
            ?>
              <article style="background: var(--bg-white); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
                <div>
                  <h3 style="font-size: 1rem; font-weight: 700; color: var(--dark); margin: 0 0 0.5rem;"><i class="fas fa-briefcase" style="color: var(--primary); margin-right: 0.375rem; font-size: 0.875rem;"></i><?= e($opp['title']) ?></h3>
                  <p style="font-size: 0.8125rem; color: var(--text-light); margin: 0;"><i class="fas fa-building" style="margin-right: 0.375rem; font-size: 0.75rem;"></i><?= e($opp['organisation'] ?? 'TBD') ?></p>
                  <?php if (!empty($opp['cohort_name'])): ?>
                    <p style="font-size: 0.8125rem; color: var(--text-light); margin: 0;"><i class="fas fa-users" style="margin-right: 0.375rem; font-size: 0.75rem;"></i><?= e($opp['cohort_name']) ?></p>
                  <?php endif; ?>
                </div>
                <p style="font-size: 0.875rem; color: var(--text); line-height: 1.5; margin: 0;"><?= e(substr($opp['short_description'] ?? '', 0, 100)) . '...' ?></p>
                <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; font-size: 0.75rem; color: var(--text-light);">
                  <?php if (!empty($opp['city']) || !empty($opp['province'])): ?>
                    <span style="display: inline-flex; align-items: center; gap: 0.25rem;"><i class="fas fa-map-marker-alt" style="color: var(--primary);"></i> <?= e($opp['city'] ?? $opp['province'] ?? 'Location TBD') ?></span>
                  <?php endif; ?>
                  <?php if (!empty($opp['application_close_date'])): ?>
                    <span style="display: inline-flex; align-items: center; gap: 0.25rem;"><i class="far fa-calendar-alt" style="color: var(--accent);"></i> Closes <?= e(format_date($opp['application_close_date'], 'd M Y')) ?></span>
                  <?php endif; ?>
                </div>
                <div style="border-top: 1px solid var(--border); padding-top: 1rem; display: flex; gap: 0.75rem;">
                  <?php if (is_logged_in()): ?>
                    <a href="<?= url('candidate/start_application.php') ?>?opportunity_id=<?= (int)$opp['id'] ?>" class="btn btn--primary btn--sm" style="flex: 1;">Apply</a>
                  <?php else: ?>
                    <a href="<?= url('register.php') ?>" class="btn btn--primary btn--sm" style="flex: 1;">Apply</a>
                  <?php endif; ?>
                  <a href="<?= url('candidate/opportunity_detail.php?id=' . (int)$opp['id']) ?>" class="btn btn--ghost btn--sm" style="flex: 1;">View</a>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ===== CTA ===== -->
  <section class="section cta" style="margin-top: 3rem;">
    <div class="container">
      <div class="cta__content">
        <h2 class="cta__title">Ready to Join? <span class="text-gradient">Apply Today.</span></h2>
        <p class="cta__text">Start your career journey with Investhood IT and unlock your potential.</p>
        <div class="cta__actions">
          <?php if (!is_logged_in()): ?>
            <a href="<?= url('register.php') ?>" class="btn btn--primary btn--lg">Register Now <i class="fas fa-user-plus"></i></a>
            <a href="<?= url('login.php') ?>" class="btn btn--outline btn--lg">Login</a>
          <?php else: ?>
            <a href="<?= url('candidate/opportunities.php') ?>" class="btn btn--primary btn--lg">View Opportunities <i class="fas fa-arrow-right"></i></a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== FOOTER ===== -->
  <footer class="footer" id="contact">
    <div class="container">
      <div class="footer__grid">
        <div class="footer__col footer__brand">
          <a href="#" class="logo">
            <span class="logo__icon"><i class="fas fa-code"></i></span>
            <span class="logo__text">Investhood <span class="logo__accent">IT</span></span>
          </a>
          <p>Empowering tomorrow's talent today through innovative programmes, skills development, and career opportunities.</p>
          <div class="footer__social">
            <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
            <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
            <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
            <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
            <a href="#" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
          </div>
        </div>
        <div class="footer__col">
          <h4>Quick Links</h4>
          <ul>
            <li><a href="#home">Home</a></li>
            <li><a href="#about">About Us</a></li>
            <li><a href="#programmes">Programmes</a></li>
            <li><a href="#opportunities">Opportunities</a></li>
            <li><a href="#talent">Talent Community</a></li>
          </ul>
        </div>
        <div class="footer__col">
          <h4>Programmes</h4>
          <ul>
            <li><a href="#">Graduate Programmes</a></li>
            <li><a href="#">Learnerships</a></li>
            <li><a href="#">Internships</a></li>
            <li><a href="#">Work Integrated Learning</a></li>
            <li><a href="#">Skills Development</a></li>
          </ul>
        </div>
        <div class="footer__col">
          <h4>Contact Us</h4>
          <ul class="footer__contact">
            <li><i class="fas fa-envelope"></i> info@investhoodit.co.za</li>
            <li><i class="fas fa-phone"></i> +27 (11) 234 5678</li>
            <li><i class="fas fa-map-marker-alt"></i> Johannesburg, South Africa</li>
          </ul>
        </div>
        <div class="footer__col footer__newsletter">
          <h4>Newsletter</h4>
          <p>Stay updated with the latest opportunities and programme news.</p>
          <form class="footer__form">
            <input type="email" placeholder="Your email address" required>
            <button type="submit" class="btn btn--primary btn--sm">Subscribe</button>
          </form>
        </div>
      </div>
      <div class="footer__bottom">
        <div class="footer__legal">
          <a href="#">Privacy Policy</a>
          <a href="#">Terms & Conditions</a>
        </div>
        <p class="footer__copy">&copy; 2025 Investhood IT. All rights reserved.</p>
      </div>
    </div>
  </footer>

  <script src="<?= url('js/script.js') ?>"></script>

<script src="<?= url('js/programme_manager_enhancements.js') ?>?v=20260919"></script>
</body>
</html>
