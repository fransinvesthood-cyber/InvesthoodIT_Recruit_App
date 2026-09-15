<?php
/**
 * ================================================
 * INVESTHOOD IT - Admin: Candidate Profile View
 * ================================================
 * Read-only detailed profile of a single candidate
 * for the Talent Intelligence Hub.
 *
 * Role: Administrator only.
 *
 * Security:
 *  - require_role('admin') rejects non-administrators.
 *  - The viewed user must hold the 'candidate' role and
 *    be an active platform account (IDOR protection).
 *  - All output is escaped via e().
 *
 * GET params:
 *   user_id - the candidate user ID (required)
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('admin');

$admin      = current_user();
$adminName  = $admin['fullname'] ?? 'Admin User';
$adminRole  = $admin['role_name'] ?? 'Platform Administrator';
$flashes    = render_flashes();

$candidateId = (int) ($_GET['user_id'] ?? 0);
$notFound    = false;

$user         = null;
$role         = null;
$profile      = null;
$status       = '';
$fullName     = '';
$initials     = '';
$email        = '';
$phone        = '';
$username     = '';
$title        = 'Candidate';
$city         = '';
$province     = '';
$address      = '';
$summary      = '';
$employment   = '';
$available    = '';
$availableOn  = '';
$completion   = 0;
$breakdown    = [];
$careerList   = [];
$techSkills   = [];
$softSkills   = [];
$quals        = [];
$certs        = [];
$experience   = [];
$docs         = [];
$apps         = [];
$cohorts      = [];
$registered   = '';
$lastLogin    = '';
$verified     = false;

if ($candidateId <= 0) {
    $notFound = true;
} else {
    $user = User::find($candidateId);
    if (!$user) {
        $notFound = true;
    } else {
        // --- IDOR guard: only candidate-role accounts may be viewed here.
        $roleId = (int) ($user['role_id'] ?? 0);
        $role = Database::fetchOne(
            "SELECT slug FROM roles WHERE id = ? LIMIT 1",
            'i',
            [$roleId]
        );
        if (($role['slug'] ?? '') !== 'candidate') {
            $notFound = true;
        } elseif (strtolower((string) ($user['status'] ?? '')) === 'disabled') {
            $notFound = true;
        }
    }
}

if (!$notFound && $user) {
    $profile   = CandidateProfile::findByUser($candidateId);
    $status    = strtolower((string) ($user['status'] ?? 'pending'));
    $fullName  = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    $initial   = strtoupper(substr((string) ($user['first_name'] ?? ''), 0, 1) . substr((string) ($user['last_name'] ?? ''), 0, 1));
    $initials  = $initial !== '' ? $initial : 'C';
    $email     = (string) ($user['email'] ?? '');
    $phone     = (string) ($user['phone'] ?? '');
    $username  = (string) ($user['username'] ?? '');
    $title     = trim((string) (($profile['professional_title'] ?? '') !== '' ? $profile['professional_title'] : ($user['professional_title'] ?? '')));
    if ($title === '') {
        $title = 'Candidate';
    }
    $city      = trim((string) ($profile['city'] ?? ''));
    $province  = trim((string) ($user['province'] ?? ''));
    $address   = trim((string) ($profile['address'] ?? ''));
    $summary   = trim((string) ($profile['professional_summary'] ?? ''));
    $employment = trim((string) ($profile['employment_status'] ?? ''));
    if ($employment === '') {
        $employment = trim((string) ($user['employment_status'] ?? ''));
    }
    $available = trim((string) ($profile['availability_label'] ?? ''));
    $availableOn = (string) ($profile['availability_date'] ?? '');
    $completion = (int) ($profile['completion_percent'] ?? 0);
    $breakdown = CandidateProfile::completionBreakdown($candidateId);
    $registered = (string) ($user['created_at'] ?? '');
    $lastLogin = (string) ($user['last_login'] ?? '');
    $verified  = !empty($user['email_verified_at']);

    // Career interests become individual tags.
    $rawCareer = trim((string) ($profile['career_interests'] ?? ''));
    if ($rawCareer !== '') {
        foreach (explode(',', $rawCareer) as $interest) {
            $interest = trim($interest);
            if ($interest !== '') {
                $careerList[] = $interest;
            }
        }
    }

    // Skills, split by catalogue category.
    try {
        $allSkills = Skill::forUser($candidateId);
    } catch (Exception $ex) {
        error_log('[ADMIN-CANDIDATE] Skills for user ' . $candidateId . ': ' . $ex->getMessage());
        $allSkills = [];
    }
    foreach ($allSkills as $skill) {
        if (strtolower((string) ($skill['category'] ?? '')) === 'soft') {
            $softSkills[] = $skill;
        } else {
            $techSkills[] = $skill;
        }
    }

    // Education, certifications, experience, documents.
    try {
        $quals = Qualification::forUser($candidateId);
    } catch (Exception $ex) {
        error_log('[ADMIN-CANDIDATE] Qualifications for user ' . $candidateId . ': ' . $ex->getMessage());
        $quals = [];
    }
    try {
        $certs = Certification::forUser($candidateId);
    } catch (Exception $ex) {
        error_log('[ADMIN-CANDIDATE] Certifications for user ' . $candidateId . ': ' . $ex->getMessage());
        $certs = [];
    }
    try {
        $experience = WorkExperience::forUser($candidateId);
    } catch (Exception $ex) {
        error_log('[ADMIN-CANDIDATE] Experience for user ' . $candidateId . ': ' . $ex->getMessage());
        $experience = [];
    }
    try {
        $docs = Document::forUser($candidateId);
    } catch (Exception $ex) {
        error_log('[ADMIN-CANDIDATE] Documents for user ' . $candidateId . ': ' . $ex->getMessage());
        $docs = [];
    }

    // Applications (opportunity history) and programme participation.
    try {
        $apps = Application::forCandidate($candidateId);
    } catch (Exception $ex) {
        error_log('[ADMIN-CANDIDATE] Applications for user ' . $candidateId . ': ' . $ex->getMessage());
        $apps = [];
    }
    try {
        $cohorts = Database::fetchAll(
            "SELECT cp.id, cp.cohort_id, cp.status AS participant_status,
                    cp.selected_at, cp.onboarded_at, cp.completed_at,
                    c.name AS cohort_name, c.status AS cohort_status,
                    c.start_date, c.end_date, c.delivery_mode, c.location,
                    p.id AS programme_id, p.name AS programme_name, p.status AS programme_status
             FROM cohort_participants cp
             INNER JOIN cohorts c ON c.id = cp.cohort_id
             INNER JOIN programmes p ON p.id = c.programme_id
             WHERE cp.user_id = ?
             ORDER BY cp.created_at DESC",
             'i',
            [$candidateId]
        );
    } catch (Exception $ex) {
        error_log('[ADMIN-CANDIDATE] Cohorts for user ' . $candidateId . ': ' . $ex->getMessage());
        $cohorts = [];
    }
}

$displayName = $fullName !== '' ? $fullName : ($username !== '' ? $username : 'Candidate');

// Reusable read-only empty-state text.
function admin_profile_empty(string $section): string
{
    return '<div class="admin-empty-state admin-empty-state--compact">'
        . '<div class="admin-empty-state__icon"><i class="fas fa-inbox"></i></div>'
        . '<h3>No information provided</h3>'
        . '<p>' . e($section) . ' details have not been added to this profile yet.</p>'
        . '</div>';
}

$statusLabel = 'Candidate';
if ($status === 'active') {
    $statusLabel = 'Active';
} elseif ($status === 'suspended') {
    $statusLabel = 'Suspended';
} elseif ($status === 'pending') {
    $statusLabel = 'Pending';
} elseif ($status === 'disabled') {
    $statusLabel = 'Disabled';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Candidate profile detail - Investhood IT Administration">
  <title>Candidate Profile | Investhood IT Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
  <link rel="stylesheet" href="<?= url('css/admin_candidate_profile.css') ?>">
  <style>
    .admin-profile-hero__main { flex: 1 1 220px; min-width: 0; }
    .admin-profile-hero__name { font-size: 1.35rem; font-weight: 800; color: var(--dark); margin: 0 0 0.2rem; }
    .admin-profile-hero__title { font-size: 0.95rem; color: var(--text-light); margin: 0 0 0.65rem; }
    .admin-profile-hero__meta { display: flex; flex-wrap: wrap; gap: 0.45rem 1rem; font-size: 0.8rem; color: var(--text-light); }
    .admin-profile-hero__meta i { color: var(--primary); margin-right: 0.35rem; }
    .admin-profile-hero__side { flex: 0 1 auto; display: flex; flex-direction: column; gap: 0.6rem; align-items: stretch; min-width: 210px; }
    .admin-profile-progress { font-size: 0.75rem; color: var(--text-light); }
    .admin-profile-progress__bar { height: 8px; border-radius: 999px; background: rgba(148,163,184,0.25); overflow: hidden; margin-top: 0.35rem; }
    .admin-profile-progress__fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg, var(--primary), var(--success)); }
  </style>
  <script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
  <style>
    .admin-profile-hero {
      display: flex;
      flex-wrap: wrap;
      gap: 1.25rem;
      align-items: center;
      background: var(--surface, #fff);
      border: 1px solid var(--border, #e2e8f0);
      border-radius: 1rem;
      padding: 1.5rem;
      box-shadow: var(--shadow-sm);
    }
    .admin-profile-hero__avatar {
      width: 96px;
      height: 96px;
      border-radius: 50%;
      overflow: hidden;
      flex: 0 0 auto;
      background: var(--primary-bg);
      color: var(--primary);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2rem;
      font-weight: 800;
    }
    .admin-profile-hero__avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .admin-profile-hero__main { flex: 1 1 220px; min-width: 0; }
    .admin-profile-hero__name { font-size: 1.35rem; font-weight: 800; color: var(--dark); margin: 0 0 0.2rem; }
    .admin-profile-hero__title { font-size: 0.95rem; color: var(--text-light); margin: 0 0 0.65rem; }
    .admin-profile-hero__meta { display: flex; flex-wrap: wrap; gap: 0.45rem 1rem; font-size: 0.8rem; color: var(--text-light); }
    .admin-profile-hero__meta i { color: var(--primary); margin-right: 0.35rem; }
    .admin-profile-hero__side { flex: 0 1 auto; display: flex; flex-direction: column; gap: 0.6rem; align-items: stretch; min-width: 210px; }
    .admin-profile-progress { font-size: 0.75rem; color: var(--text-light); }
    .admin-profile-progress__bar { height: 8px; border-radius: 999px; background: rgba(148,163,184,0.25); overflow: hidden; margin-top: 0.35rem; }
    .admin-profile-progress__fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg, var(--primary), var(--success)); }
    .admin-profile-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-top: 1.5rem; align-items: start; }
    .admin-profile-card {
      background: var(--surface, #fff);
      border: 1px solid var(--border, #e2e8f0);
      border-radius: 1rem;
      padding: 1.25rem 1.35rem;
      box-shadow: var(--shadow-sm);
      margin-bottom: 1.5rem;
    }
    .admin-profile-card__head { display: flex; align-items: center; gap: 0.65rem; margin-bottom: 1rem; }
    .admin-profile-card__head h3 { font-size: 1rem; font-weight: 800; color: var(--dark); margin: 0; }
    .admin-profile-card__head i { color: var(--primary); }
    .admin-profile-kv { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.9rem 1.25rem; }
    .admin-profile-kv__label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-lighter); margin-bottom: 0.2rem; }
    .admin-profile-kv__value { font-size: 0.88rem; color: var(--dark); word-break: break-word; }
    .admin-profile-summary { font-size: 0.9rem; line-height: 1.65; color: var(--text); white-space: pre-line; margin: 0; }
    .admin-skill-tags { display: flex; flex-wrap: wrap; gap: 0.45rem; }
    .admin-skill-tag {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      font-size: 0.75rem;
      font-weight: 600;
      padding: 0.35rem 0.7rem;
      border-radius: 999px;
      background: var(--primary-bg);
      color: var(--primary);
      border: 1px solid rgba(26,86,219,0.18);
    }
    .admin-skill-tag--soft { background: rgba(139,92,246,0.1); color: #7c3aed; border-color: rgba(139,92,246,0.2); }
    .admin-skill-tag small { font-weight: 500; opacity: 0.8; }
    .admin-timeline { display: flex; flex-direction: column; gap: 1rem; }
    .admin-timeline-item { border: 1px solid var(--border, #e2e8f0); border-radius: 0.85rem; padding: 0.9rem 1rem; background: var(--bg-soft, #f8fafc); }
    .admin-timeline-item__title { font-size: 0.92rem; font-weight: 800; color: var(--dark); margin: 0 0 0.2rem; }
    .admin-timeline-item__sub { font-size: 0.8rem; color: var(--text-light); margin: 0 0 0.35rem; }
    .admin-timeline-item__meta { font-size: 0.75rem; color: var(--text-lighter); display: flex; flex-wrap: wrap; gap: 0.3rem 0.9rem; margin-bottom: 0.35rem; }
    .admin-timeline-item__meta i { color: var(--primary); margin-right: 0.3rem; }
    .admin-timeline-item__desc { font-size: 0.83rem; color: var(--text); line-height: 1.6; margin: 0.35rem 0 0; white-space: pre-line; }
    .admin-doc-row, .admin-app-row, .admin-cohort-row {
      display: flex;
      align-items: center;
      gap: 0.85rem;
      border: 1px solid var(--border, #e2e8f0);
      border-radius: 0.85rem;
      padding: 0.8rem 0.95rem;
      margin-bottom: 0.7rem;
      background: var(--bg-soft, #f8fafc);
    }
    .admin-doc-row:last-child, .admin-app-row:last-child, .admin-cohort-row:last-child { margin-bottom: 0; }
    .admin-doc-row__icon, .admin-app-row__icon, .admin-cohort-row__icon {
      width: 40px; height: 40px; border-radius: 0.7rem; flex: 0 0 auto;
      display: flex; align-items: center; justify-content: center;
      background: var(--primary-bg); color: var(--primary);
    }
    .admin-doc-row__body, .admin-app-row__body, .admin-cohort-row__body { flex: 1 1 auto; min-width: 0; }
    .admin-doc-row__title, .admin-app-row__title, .admin-cohort-row__title { font-size: 0.86rem; font-weight: 700; color: var(--dark); }
    .admin-doc-row__sub, .admin-app-row__sub, .admin-cohort-row__sub { font-size: 0.74rem; color: var(--text-light); }
    .admin-profile-actions { display: flex; flex-wrap: wrap; gap: 0.6rem; margin: 1.25rem 0 0; }
    .admin-empty-state--compact { padding: 1.25rem 1rem; }
    .admin-profile-note {
      font-size: 0.75rem;
      color: var(--text-light);
      display: flex;
      align-items: center;
      gap: 0.45rem;
      margin-top: 1rem;
    }
    .admin-profile-note i { color: var(--primary); }
    .admin-status-pill {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      font-size: 0.72rem;
      font-weight: 700;
      padding: 0.28rem 0.7rem;
      border-radius: 999px;
    }
    .admin-status-pill--active { background: rgba(16,185,129,0.12); color: var(--success); }
    .admin-status-pill--pending { background: rgba(245,158,11,0.14); color: var(--accent); }
    .admin-status-pill--suspended { background: rgba(239,68,68,0.12); color: #ef4444; }
    .admin-status-pill--disabled { background: rgba(148,163,184,0.16); color: var(--text-light); }
    @media (max-width: 1024px) {
      .admin-profile-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 640px) {
      .admin-profile-hero { padding: 1.1rem; }
      .admin-profile-hero__avatar { width: 76px; height: 76px; font-size: 1.5rem; }
      .admin-profile-hero__side { width: 100%; }
      .admin-profile-kv { grid-template-columns: 1fr; }
      .admin-profile-hero__side .btn { width: 100%; justify-content: center; }
    }
  </style>
  <script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
</head>
<body class="dashboard-page admin-dashboard">

  <div class="dashboard">

    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar admin-sidebar" id="adminSidebar">
      <div class="sidebar__header">
        <a href="<?= url('index.php') ?>" class="logo">
          <span class="logo__icon"><i class="fas fa-code"></i></span>
          <span class="logo__text">Investhood <span class="logo__accent">IT</span></span>
        </a>
        <button class="sidebar__close" id="sidebarClose" aria-label="Close sidebar">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <nav class="sidebar__nav">
        <div class="sidebar__section-label">Talent</div>
        <ul class="sidebar__menu">
          <li><a href="<?= url('admin/dashboard.php') ?>#admin-talent-hub" class="sidebar__link active" data-section="talent-hub"><i class="fas fa-users"></i> Talent Intelligence Hub</a></li>
        </ul>

        <div class="sidebar__section-label">Management</div>
        <ul class="sidebar__menu">
          <li><a href="<?= url('admin/dashboard.php') ?>#admin-programmes" class="sidebar__link" data-section="programmes"><i class="fas fa-graduation-cap"></i> Programmes</a></li>
          <li><a href="<?= url('admin/dashboard.php') ?>#admin-opportunities" class="sidebar__link" data-section="opportunities"><i class="fas fa-briefcase"></i> Opportunities</a></li>
          <li><a href="<?= url('admin/dashboard.php') ?>#admin-applications" class="sidebar__link" data-section="applications"><i class="fas fa-file-alt"></i> Applications</a></li>
          <li><a href="<?= url('admin/dashboard.php') ?>#admin-talent-pool" class="sidebar__link" data-section="talent-pool"><i class="fas fa-database"></i> Talent Pools</a></li>
        </ul>
      </nav>

      <div class="sidebar__footer">
        <div class="sidebar__user">
          <div class="sidebar__user-avatar">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($adminName) ?>&background=1a56db&color=fff&size=80" alt="Profile">
          </div>
          <div class="sidebar__user-info">
            <span class="sidebar__user-name"><?= e($adminName) ?></span>
            <span class="sidebar__user-role"><?= e($adminRole) ?></span>
          </div>
        </div>
        <a href="<?= url('auth/logout.php') ?>" class="sidebar__logout" id="sidebarLogoutBtn">
          <i class="fas fa-sign-out-alt"></i> Sign Out
        </a>
      </div>
    </aside>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="dashboard__main">

      <header class="dash-header admin-dash-header" id="adminDashHeader">
        <div class="dash-header__left">
          <button class="dash-header__toggle" id="sidebarToggle" aria-label="Toggle sidebar">
            <i class="fas fa-bars"></i>
          </button>
        </div>
        <div class="dash-header__right">
          <button class="dash-header__icon-btn" id="themeToggle" aria-label="Toggle dark mode">
            <i class="fas fa-moon"></i>
          </button>
          <div class="dash-header__user">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($adminName) ?>&background=1a56db&color=fff&size=80" alt="Profile" class="dash-header__avatar">
          </div>
        </div>
      </header>

      <div class="dash-content" id="adminDashContent">

        <section class="dash-section admin-section" id="admin-candidate-profile" data-section="candidate-profile">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <span class="section__badge">Talent Intelligence</span>
            <h2 class="section__title" style="font-size:1.5rem;">Candidate <span class="text-gradient">Profile</span></h2>
            <p class="section__text" style="font-size:0.9rem;">Read-only view of live candidate information.</p>
          </div>

          <?php if ($notFound || !$user): ?>
            <div class="admin-empty-state" style="grid-column:1/-1;">
              <div class="admin-empty-state__icon"><i class="fas fa-user-slash"></i></div>
              <h3>Candidate not found</h3>
              <p>The requested candidate profile could not be found. It may have been removed, or the link may be incorrect.</p>
              <div class="admin-profile-actions" style="justify-content:center;">
                <a href="<?= url('admin/dashboard.php') ?>#admin-talent-hub" class="btn btn--primary btn--sm"><i class="fas fa-arrow-left"></i> Back to Talent Intelligence Hub</a>
              </div>
            </div>
          <?php else: ?>
            <div class="admin-profile-hero">
              <div class="admin-profile-hero__avatar">
                <img src="<?= url('admin/avatar.php') ?>?user_id=<?= (int) $candidateId ?>" alt="Candidate profile picture" onerror="this.onerror=null;this.remove();">
                <span><?= e($initials) ?></span>
              </div>
              <div class="admin-profile-hero__main">
                <h2 class="admin-profile-hero__name"><?= e($displayName) ?></h2>
                <p class="admin-profile-hero__title"><?= e($title) ?></p>
                <div class="admin-profile-hero__meta">
                  <span><i class="fas fa-envelope"></i><?= e($email !== '' ? $email : 'No information provided') ?></span>
                  <?php if ($phone !== ''): ?>
                    <span><i class="fas fa-phone"></i><?= e($phone) ?></span>
                  <?php endif; ?>
                  <?php if ($city !== '' || $province !== ''): ?>
                    <span><i class="fas fa-map-marker-alt"></i><?= e(trim($city . ($city !== '' && $province !== '' ? ', ' : '') . $province)) ?></span>
                  <?php endif; ?>
                  <span><i class="fas fa-circle-check"></i><?= e($statusLabel) ?> account</span>
                </div>
              </div>
              <div class="admin-profile-hero__side">
                <span class="admin-status-pill admin-status-pill--<?= e($status === 'active' ? 'active' : ($status === 'suspended' ? 'suspended' : 'pending')) ?>"><?= e($statusLabel) ?></span>
                <div class="admin-profile-progress">
                  <span><?= (int) $completion ?>% profile complete</span>
                  <div class="admin-profile-progress__bar"><div class="admin-profile-progress__fill" style="width:<?= (int) $completion ?>%"></div></div>
                </div>
                <a href="<?= url('admin/dashboard.php') ?>#admin-talent-hub" class="btn btn--outline btn--sm"><i class="fas fa-arrow-left"></i> Back to Talent Intelligence Hub</a>
              </div>
            </div>

            <div class="admin-profile-grid">
              <div>
                <div class="admin-profile-card">
                  <div class="admin-profile-card__head"><i class="fas fa-address-card"></i><h3>Profile Information</h3></div>
                  <div class="admin-profile-kv">
                    <div><div class="admin-profile-kv__label">Full Name</div><div class="admin-profile-kv__value"><?= e($fullName !== '' ? $fullName : 'No information provided') ?></div></div>
                    <div><div class="admin-profile-kv__label">Username</div><div class="admin-profile-kv__value"><?= e($username !== '' ? $username : 'No information provided') ?></div></div>
                    <div><div class="admin-profile-kv__label">Email</div><div class="admin-profile-kv__value"><?= e($email !== '' ? $email : 'No information provided') ?></div></div>
                    <div><div class="admin-profile-kv__label">Phone</div><div class="admin-profile-kv__value"><?= e($phone !== '' ? $phone : 'No information provided') ?></div></div>
                    <div><div class="admin-profile-kv__label">Professional Title</div><div class="admin-profile-kv__value"><?= e($title) ?></div></div>
                    <div><div class="admin-profile-kv__label">Employment Status</div><div class="admin-profile-kv__value"><?= e($employment !== '' ? ucfirst(str_replace('_', ' ', $employment)) : 'No information provided') ?></div></div>
                    <div><div class="admin-profile-kv__label">City</div><div class="admin-profile-kv__value"><?= e($city !== '' ? $city : 'No information provided') ?></div></div>
                    <div><div class="admin-profile-kv__label">Province</div><div class="admin-profile-kv__value"><?= e($province !== '' ? $province : 'No information provided') ?></div></div>
                    <div><div class="admin-profile-kv__label">Address</div><div class="admin-profile-kv__value"><?= e($address !== '' ? $address : 'No information provided') ?></div></div>
                    <div><div class="admin-profile-kv__label">Email Verified</div><div class="admin-profile-kv__value"><?= $verified ? 'Verified' : 'Not verified' ?></div></div>
                  </div>
                </div>

                <div class="admin-profile-card">
                  <div class="admin-profile-card__head"><i class="fas fa-align-left"></i><h3>Professional Summary</h3></div>
                  <?php if ($summary !== ''): ?>
                    <p class="admin-profile-summary"><?= e($summary) ?></p>
                  <?php else: ?>
                    <?= admin_profile_empty('Professional summary') ?>
                  <?php endif; ?>
                </div>

                <div class="admin-profile-card">
                  <div class="admin-profile-card__head"><i class="fas fa-code"></i><h3>Skills</h3></div>
                  <?php if (!empty($techSkills) || !empty($softSkills)): ?>
                    <?php if (!empty($techSkills)): ?>
                      <div class="admin-profile-kv__label" style="margin-bottom:0.45rem;">Technical</div>
                      <div class="admin-skill-tags" style="margin-bottom:0.9rem;">
                        <?php foreach ($techSkills as $skill): ?>
                          <span class="admin-skill-tag"><?= e($skill['name'] ?? 'Skill') ?><?php if (!empty($skill['proficiency'])): ?><small><?= e(ucfirst((string) $skill['proficiency'])) ?></small><?php endif; ?></span>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                    <?php if (!empty($softSkills)): ?>
                      <div class="admin-profile-kv__label" style="margin-bottom:0.45rem;">Soft</div>
                      <div class="admin-skill-tags">
                        <?php foreach ($softSkills as $skill): ?>
                          <span class="admin-skill-tag admin-skill-tag--soft"><?= e($skill['name'] ?? 'Skill') ?><?php if (!empty($skill['proficiency'])): ?><small><?= e(ucfirst((string) $skill['proficiency'])) ?></small><?php endif; ?></span>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                  <?php else: ?>
                    <?= admin_profile_empty('Skills') ?>
                  <?php endif; ?>
                </div>

                <div class="admin-profile-card">
                  <div class="admin-profile-card__head"><i class="fas fa-graduation-cap"></i><h3>Qualifications &amp; Education</h3></div>
                  <?php if (!empty($quals)): ?>
                    <div class="admin-timeline">
                      <?php foreach ($quals as $qual): ?>
                        <div class="admin-timeline-item">
                          <p class="admin-timeline-item__title"><?= e($qual['name'] ?? 'Qualification') ?></p>
                          <p class="admin-timeline-item__sub">
                            <?= e($qual['institution'] ?? 'No information provided') ?>
                            <?php if (!empty($qual['level'])): ?>
                              &middot; <?= e(ucfirst(str_replace('_', ' ', (string) $qual['level']))) ?>
                            <?php endif; ?>
                          </p>
                          <div class="admin-timeline-item__meta">
                            <?php if (!empty($qual['year_completed'])): ?>
                              <span><i class="fas fa-calendar"></i>Completed <?= e((string) $qual['year_completed']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($qual['verification_status'])): ?>
                              <span><i class="fas fa-shield-halved"></i><?= e(ucfirst((string) $qual['verification_status'])) ?></span>
                            <?php endif; ?>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php else: ?>
                    <?= admin_profile_empty('Qualifications') ?>
                  <?php endif; ?>
                </div>

                <div class="admin-profile-card">
                  <div class="admin-profile-card__head"><i class="fas fa-briefcase"></i><h3>Work Experience</h3></div>
                  <?php if (!empty($experience)): ?>
                    <div class="admin-timeline">
                      <?php foreach ($experience as $job): ?>
                        <div class="admin-timeline-item">
                          <p class="admin-timeline-item__title"><?= e($job['job_title'] ?? 'Position') ?></p>
                          <p class="admin-timeline-item__sub"><?= e($job['company'] ?? 'No information provided') ?></p>
                          <div class="admin-timeline-item__meta">
                            <?php if (!empty($job['start_date'])): ?>
                              <span><i class="fas fa-calendar"></i>
                                <?= e(date('M Y', strtotime((string) $job['start_date']))) ?>
                                &ndash;
                                <?= !empty($job['end_date']) ? e(date('M Y', strtotime((string) $job['end_date']))) : 'Present' ?>
                              </span>
                            <?php endif; ?>
                            <?php if (!empty($job['is_current'])): ?>
                              <span><i class="fas fa-circle-dot"></i>Current role</span>
                            <?php endif; ?>
                          </div>
                          <?php if (!empty(trim((string) ($job['description'] ?? '')))): ?>
                            <p class="admin-timeline-item__desc"><?= e((string) $job['description']) ?></p>
                          <?php endif; ?>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php else: ?>
                    <?= admin_profile_empty('Work experience') ?>
                  <?php endif; ?>
                </div>

                <div class="admin-profile-card">
                  <div class="admin-profile-card__head"><i class="fas fa-certificate"></i><h3>Certifications</h3></div>
                  <?php if (!empty($certs)): ?>
                    <div class="admin-timeline">
                      <?php foreach ($certs as $cert): ?>
                        <div class="admin-timeline-item">
                          <p class="admin-timeline-item__title"><?= e($cert['name'] ?? 'Certification') ?></p>
                          <p class="admin-timeline-item__sub"><?= e($cert['issuing_organisation'] ?? 'No information provided') ?></p>
                          <div class="admin-timeline-item__meta">
                            <?php if (!empty($cert['year_obtained'])): ?>
                              <span><i class="fas fa-calendar"></i>Obtained <?= e((string) $cert['year_obtained']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($cert['expiry_date'])): ?>
                              <span><i class="fas fa-hourglass-half"></i>Expires <?= e(date('M j, Y', strtotime((string) $cert['expiry_date']))) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($cert['credential_id'])): ?>
                              <span><i class="fas fa-id-card"></i><?= e((string) $cert['credential_id']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($cert['verification_status'])): ?>
                              <span><i class="fas fa-shield-halved"></i><?= e(ucfirst((string) $cert['verification_status'])) ?></span>
                            <?php endif; ?>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php else: ?>
                    <?= admin_profile_empty('Certifications') ?>
                  <?php endif; ?>
                </div>

                <div class="admin-profile-card">
                  <div class="admin-profile-card__head"><i class="fas fa-users"></i><h3>Programme &amp; Cohort Participation</h3></div>
                  <?php if (!empty($cohorts)): ?>
                    <?php foreach ($cohorts as $row): ?>
                      <div class="admin-cohort-row">
                        <div class="admin-cohort-row__icon"><i class="fas fa-graduation-cap"></i></div>
                        <div class="admin-cohort-row__body">
                          <div class="admin-cohort-row__title"><?= e($row['programme_name'] ?? 'Programme') ?></div>
                          <div class="admin-cohort-row__sub">
                            <?= e($row['cohort_name'] ?? 'Cohort') ?>
                            <?php if (!empty($row['participant_status'])): ?>
                              &middot; <?= e(ucfirst((string) $row['participant_status'])) ?>
                            <?php endif; ?>
                            <?php if (!empty($row['start_date'])): ?>
                              &middot; <?= e(date('M Y', strtotime((string) $row['start_date']))) ?>
                            <?php endif; ?>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <?= admin_profile_empty('Programme participation') ?>
                  <?php endif; ?>
                </div>

                <div class="admin-profile-card">
                  <div class="admin-profile-card__head"><i class="fas fa-briefcase"></i><h3>Applications &amp; Opportunities</h3></div>
                  <?php if (!empty($apps)): ?>
                    <?php foreach ($apps as $app): ?>
                      <div class="admin-app-row">
                        <div class="admin-app-row__icon"><i class="fas fa-file-alt"></i></div>
                        <div class="admin-app-row__body">
                          <div class="admin-app-row__title"><?= e($app['opportunity_title'] ?? 'Opportunity') ?></div>
                          <div class="admin-app-row__sub">
                            Ref <?= e($app['application_reference'] ?? '—') ?>
                            <?php if (!empty($app['status'])): ?>
                              &middot; <?= e(ucfirst(str_replace('_', ' ', (string) $app['status']))) ?>
                            <?php endif; ?>
                            <?php if (!empty($app['programme_name'])): ?>
                              &middot; <?= e((string) $app['programme_name']) ?>
                            <?php endif; ?>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <?= admin_profile_empty('Applications') ?>
                  <?php endif; ?>
                </div>
              </div>

              <div>
                <div class="admin-profile-card">
                  <div class="admin-profile-card__head"><i class="fas fa-heart"></i><h3>Career Interests</h3></div>
                  <?php if (!empty($careerList)): ?>
                    <div class="admin-skill-tags">
                      <?php foreach ($careerList as $interest): ?>
                        <span class="admin-skill-tag admin-skill-tag--soft"><?= e($interest) ?></span>
                      <?php endforeach; ?>
                    </div>
                  <?php else: ?>
                    <?= admin_profile_empty('Career interests') ?>
                  <?php endif; ?>
                </div>

                <div class="admin-profile-card">
                  <div class="admin-profile-card__head"><i class="fas fa-clock"></i><h3>Availability</h3></div>
                  <div class="admin-profile-kv">
                    <div><div class="admin-profile-kv__label">Status</div><div class="admin-profile-kv__value"><?= e($available !== '' ? $available : 'No information provided') ?></div></div>
                    <div><div class="admin-profile-kv__label">Available From</div><div class="admin-profile-kv__value"><?= $availableOn !== '' ? e(date('M j, Y', strtotime($availableOn))) : 'No information provided' ?></div></div>
                  </div>
                </div>

                <div class="admin-profile-card">
                  <div class="admin-profile-card__head"><i class="fas fa-check-circle"></i><h3>Profile Completion</h3></div>
                  <div class="admin-profile-kv">
                    <div><div class="admin-profile-kv__label">Completion</div><div class="admin-profile-kv__value"><?= (int) $completion ?>%</div></div>
                    <div><div class="admin-profile-kv__label">Registered</div><div class="admin-profile-kv__value"><?= $registered !== '' ? e(date('M j, Y', strtotime($registered))) : 'No information provided' ?></div></div>
                    <div><div class="admin-profile-kv__label">Last Login</div><div class="admin-profile-kv__value"><?= $lastLogin !== '' ? e(date('M j, Y', strtotime($lastLogin))) : 'No information provided' ?></div></div>
                    <div><div class="admin-profile-kv__label">Account ID</div><div class="admin-profile-kv__value">#<?= (int) $candidateId ?></div></div>
                  </div>
                  <?php if (!empty($breakdown)): ?>
                    <div style="margin-top:1rem;">
                      <?php foreach ($breakdown as $item): ?>
                        <div style="display:flex;align-items:center;gap:0.5rem;font-size:0.8rem;color:var(--text-light);margin-bottom:0.4rem;">
                          <i class="fas <?= !empty($item['done']) ? 'fa-check-circle' : 'fa-circle' ?>" style="color:<?= !empty($item['done']) ? 'var(--success)' : 'var(--text-lighter)' ?>;"></i>
                          <?= e($item['label'] ?? 'Section') ?> &mdash; <?= e($item['detail'] ?? '') ?>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </div>

                <div class="admin-profile-card">
                  <div class="admin-profile-card__head"><i class="fas fa-file-alt"></i><h3>Documents</h3></div>
                  <?php if (!empty($docs)): ?>
                    <?php foreach ($docs as $doc): ?>
                      <div class="admin-doc-row">
                        <div class="admin-doc-row__icon"><i class="fas fa-file"></i></div>
                        <div class="admin-doc-row__body">
                          <div class="admin-doc-row__title"><?= e($doc['original_filename'] ?? $doc['document_type'] ?? 'Document') ?></div>
                          <div class="admin-doc-row__sub">
                            <?= e(ucfirst(str_replace('_', ' ', (string) ($doc['document_type'] ?? 'document')))) ?>
                            <?php if (!empty($doc['verification_status'])): ?>
                              &middot; <?= e(ucfirst((string) $doc['verification_status'])) ?>
                            <?php endif; ?>
                            <?php if (!empty($doc['created_at'])): ?>
                              &middot; <?= e(date('M j, Y', strtotime((string) $doc['created_at']))) ?>
                            <?php endif; ?>
                            <?php if (!empty($doc['file_size'])): ?>
                              &middot; <?= e(round($doc['file_size'] / 1024, 1)) ?> KB
                            <?php endif; ?>
                          </div>
                        </div>
                        <div class="admin-doc-row__actions" style="display:flex;gap:0.35rem;">
                          <a class="btn btn--outline btn--sm" href="<?= url('admin/download_document.php?id=' . (int) $doc['id'] . '&action=view') ?>" target="_blank" title="View document">
                            <i class="fas fa-eye"></i> View
                          </a>
                          <a class="btn btn--primary btn--sm" href="<?= url('admin/download_document.php?id=' . (int) $doc['id'] . '&action=download') ?>" title="Download document">
                            <i class="fas fa-download"></i> Download
                          </a>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <?= admin_profile_empty('Documents') ?>
                  <?php endif; ?>
                </div>

                <div class="admin-profile-note">
                  <i class="fas fa-lock"></i>
                  <span>Read-only view. Administrators cannot edit candidate details from this page.</span>
                </div>
              </div>
            </div>

            <div class="admin-profile-actions">
              <a href="<?= url('admin/dashboard.php') ?>#admin-talent-hub" class="btn btn--primary btn--sm"><i class="fas fa-arrow-left"></i> Back to Talent Intelligence Hub</a>
            </div>
          <?php endif; ?>
        </section>

        </div><!-- // dash-content -->
      </main>
    </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
  <script src="<?= url('js/script.js') ?>"></script>
  <script src="<?= url('js/admin_dashboard.js') ?>"></script>
  <?= $flashes ?>
</body>
</html>

