<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['admin', 'programme_manager', 'programme_officer']);
$user = current_user();

$search = trim((string) ($_GET['search'] ?? ''));
$status = strtolower(trim((string) ($_GET['status'] ?? 'all')));
$assignmentStatus = strtolower(trim((string) ($_GET['assignment_status'] ?? 'all')));
$programmeId = (int) ($_GET['programme_id'] ?? 0);

$stats = LearningProgramme::adminStats();
$programmes = LearningProgramme::allForAdmin(['search' => $search, 'status' => $status]);
$assignments = LearningProgramme::assignmentsForAdmin([
    'status' => $assignmentStatus,
    'programme_id' => $programmeId,
]);

$candidates = Database::fetchAll(
    "SELECT u.id, u.first_name, u.last_name, u.email
     FROM users u INNER JOIN roles r ON r.id = u.role_id
     WHERE r.slug IN ('candidate', 'consultant') AND u.status = 'active'
     ORDER BY u.first_name, u.last_name"
);
$flashes = render_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Learning &amp; Skills | Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('css/styles.css') ?>">
<link rel="stylesheet" href="<?= url('css/admin_programmes.css') ?>">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<style>
.admin-main{max-width:1480px;padding-top:1.5rem;padding-bottom:3rem}
.learning-admin__hero{display:flex;align-items:center;justify-content:space-between;gap:2rem;margin-bottom:1.75rem;padding:1.6rem 1.75rem;background:#f8fbff;border:1px solid #dbeafe;border-radius:14px;box-shadow:0 8px 24px rgba(30,64,175,.06)}
.learning-admin__eyebrow{display:inline-flex;align-items:center;gap:.45rem;color:#2563eb;font-size:.7rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase}
.learning-admin__hero h1{margin:.4rem 0 .45rem;color:#172554;font-size:clamp(1.65rem,3vw,2.25rem);font-weight:800;letter-spacing:-.035em;line-height:1.15}
.learning-admin__hero p{max-width:700px;margin:0;color:#64748b;font-size:.9rem;line-height:1.55}
.learning-admin__header-label{display:flex;align-items:center;gap:.55rem;color:#0f172a;font-size:.9rem;font-weight:700}
.learning-admin__header-label i{color:#2563eb}
.learning-admin__stats .pm-stat--in_progress .pm-stat__icon{background:#d97706}
.learning-admin__stats .pm-stat--completed .pm-stat__icon{background:#2563eb}
.learning-admin__stats .pm-stat--overdue .pm-stat__icon{background:#6b7280}
.learning-admin__stats .pm-stat--programmes .pm-stat__icon{background:#1a56db}
.learning-admin__stats .pm-stat--assignments .pm-stat__icon{background:#059669}
.learning-admin__stats .pm-stat--learners .pm-stat__icon{background:#7c3aed}
.learning-admin__stats{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:1rem;margin-bottom:1.75rem}
.learning-admin__stat{position:relative;overflow:hidden;min-height:124px;padding:1.15rem 1.2rem;background:#fff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 4px 14px rgba(15,23,42,.04);transition:transform .2s,box-shadow .2s}
.learning-admin__stat:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(15,23,42,.08)}
.learning-admin__stat:after{content:"";position:absolute;right:-28px;bottom:-32px;width:90px;height:90px;border-radius:50%;background:#eff6ff}
.learning-admin__stat i{position:relative;z-index:1;display:grid;place-items:center;width:34px;height:34px;margin-bottom:.8rem;border-radius:9px;background:#eff6ff;color:#2563eb}
.learning-admin__stat strong{position:relative;z-index:1;display:block;color:#0f172a;font-size:1.7rem;font-weight:800;line-height:1.1}
.learning-admin__stat span{position:relative;z-index:1;display:block;margin-top:.35rem;color:#64748b;font-size:.76rem;font-weight:600}
.learning-admin__split{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(310px,.55fr);gap:1.35rem;align-items:stretch}
.learning-admin__split .admin-card,.learning-admin__progress-card{min-width:0;background:#fff;border:1px solid #e2e8f0;border-radius:14px;box-shadow:0 5px 18px rgba(15,23,42,.045)}
.learning-admin__split .admin-card__header,.learning-admin__progress-card .admin-card__header{display:flex;align-items:center;justify-content:space-between;gap:1rem;min-height:64px;padding:1.15rem 1.35rem;border-bottom:1px solid #eef2f7}
.learning-admin__split .admin-card__title,.learning-admin__progress-card .admin-card__title{display:flex;align-items:center;gap:.65rem;margin:0;color:#172033;font-size:1rem;font-weight:750}
.learning-admin__card-icon{display:grid;place-items:center;width:34px;height:34px;flex:0 0 34px;border-radius:9px;background:#eff6ff;color:#2563eb;font-size:.85rem}
.learning-admin__split .admin-form,.learning-admin__progress-card .admin-form{padding:1.25rem 1.35rem 0}
.learning-admin__toolbar{display:grid;grid-template-columns:minmax(0,1fr) minmax(180px,.48fr) auto;gap:.85rem;align-items:end;margin:0 1.35rem 1.2rem;padding:1rem;background:#f8fafc;border:1px solid #e8eef5;border-radius:10px}
.learning-admin__toolbar .admin-form__field{min-width:0;margin:0}
.learning-admin__toolbar .btn{height:44px;white-space:nowrap}
.learning-admin__field-label{display:flex;align-items:center;gap:.45rem;margin:0 0 .45rem;color:#334155;font-size:.75rem;font-weight:750;line-height:1.2}
.learning-admin__field-label i{width:15px;color:#2563eb;font-size:.75rem;text-align:center}
.learning-admin__control{position:relative}
.learning-admin__control>i{position:absolute;left:.85rem;top:50%;z-index:1;color:#94a3b8;font-size:.78rem;pointer-events:none;transform:translateY(-50%)}
.learning-admin__control .form-input,.learning-admin__control .form-select{box-sizing:border-box;width:100%;height:44px;padding:0 2.7rem 0 2.35rem;border:1px solid #cbd5e1;border-radius:9px;background:#fff!important;color:#172033!important;color-scheme:light;font-size:.82rem;transition:border-color .2s,box-shadow .2s}
.learning-admin__control .form-select{appearance:none;-webkit-appearance:none;cursor:pointer}
.learning-admin__control:after{content:"\f078";position:absolute;right:.9rem;top:50%;color:#2563eb;font-family:"Font Awesome 6 Free";font-size:.65rem;font-weight:900;pointer-events:none;transform:translateY(-50%)}
.learning-admin__control:focus-within .form-input,.learning-admin__control:focus-within .form-select{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.12);outline:none}
.learning-admin__control:focus-within>i{color:#2563eb}
.learning-admin__control .form-input::placeholder{color:#94a3b8}
body.dark-mode .learning-admin__control .form-input,body.dark-mode .learning-admin__control .form-select,body.dark-mode .learning-admin__control .form-select option{background:#fff!important;color:#172033!important;border-color:#cbd5e1}
.learning-admin__filter-button{display:inline-flex;align-items:center;justify-content:center;gap:.45rem}
.learning-admin__hint{display:flex;align-items:flex-start;gap:.45rem;margin:0;padding:1rem 1.35rem 1.25rem;color:#64748b;font-size:.75rem;line-height:1.45}
.learning-admin__hint i{margin-top:.15rem;color:#2563eb}
.learning-admin__progress-card{margin-top:1.35rem}
.learning-admin__progress-card .learning-admin__toolbar{grid-template-columns:minmax(0,1fr) minmax(220px,.7fr) auto;margin-top:1.25rem}
.learning-admin__empty{text-align:center!important;padding:2.75rem 1rem!important;color:#475569}
.learning-admin__empty i{display:block;margin-bottom:.7rem;color:#94a3b8;font-size:1.7rem}
.learning-admin__empty strong{font-size:.88rem;color:#334155}
.learning-admin__empty .text-muted{font-size:.78rem}
.learning-admin__table-title{display:inline-flex;align-items:center;gap:.45rem;white-space:nowrap}
.learning-admin__table-title i{color:#2563eb;font-size:.76rem}
.learning-admin__progress-cell{min-width:140px}
.learning-admin__progress-cell .progress-bar{height:7px;margin-bottom:.35rem;border-radius:999px;background:#e8eef7}
.learning-admin__progress-cell .progress-bar__fill{border-radius:999px;background:#2563eb}
.learning-admin__progress-card .admin-table-container,.learning-admin__split .admin-table-container{overflow-x:auto}
.learning-admin__split .admin-table,.learning-admin__progress-card .admin-table{min-width:680px}
.learning-admin__split .admin-table th,.learning-admin__progress-card .admin-table th{padding:1rem .85rem;color:#64748b;background:#f8fafc;font-size:.7rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase;white-space:nowrap}
.learning-admin__split .admin-table td,.learning-admin__progress-card .admin-table td{padding:.95rem .85rem;color:#334155;font-size:.8rem;vertical-align:middle;border-bottom:1px solid #eef2f7}
.learning-admin__split .admin-table tbody tr,.learning-admin__progress-card .admin-table tbody tr{transition:background .15s}
.learning-admin__split .admin-table tbody tr:hover,.learning-admin__progress-card .admin-table tbody tr:hover{background:#f8fbff}
.learning-admin__split .admin-table td strong{color:#172033;font-weight:750}
.learning-admin__split .admin-table .btn{white-space:nowrap}
@media(max-width:1180px){.learning-admin__stats{grid-template-columns:repeat(3,minmax(0,1fr))}.learning-admin__split{grid-template-columns:minmax(0,1.25fr) minmax(280px,.75fr)}}
@media(max-width:900px){.learning-admin__hero{align-items:flex-start;flex-direction:column}.learning-admin__split{grid-template-columns:1fr}.learning-admin__progress-card{margin-top:1.25rem}}
@media(max-width:700px){.admin-main{padding:1rem .85rem 2rem}.learning-admin__hero{padding:1.25rem}.learning-admin__hero .btn{width:100%}.learning-admin__stats{grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem}.learning-admin__stat{min-height:112px;padding:1rem}.learning-admin__toolbar,.learning-admin__progress-card .learning-admin__toolbar{grid-template-columns:1fr;margin-right:.9rem;margin-left:.9rem}.learning-admin__toolbar .btn{width:100%}.learning-admin__split .admin-card__header,.learning-admin__progress-card .admin-card__header{padding-right:1rem;padding-left:1rem}.learning-admin__split .admin-form,.learning-admin__progress-card .admin-form{padding-right:1rem;padding-left:1rem}.learning-admin__hint{padding-right:1rem;padding-left:1rem}}
@media(max-width:420px){.learning-admin__stats{grid-template-columns:1fr}.learning-admin__stat{min-height:auto}}
</style>
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
      <li><a href="<?= url('admin/dashboard.php#admin-analytics') ?>" class="sidebar__link"><i class="fas fa-chart-pie"></i> Analytics</a></li>
      <li><a href="<?= url('admin/dashboard.php#admin-notifications') ?>" class="sidebar__link"><i class="fas fa-bell"></i> Notifications</a></li>
    </ul>
    <div class="sidebar__section-label">Management</div>
    <ul class="sidebar__menu">
      <li><a href="<?= url('admin/learning.php') ?>" class="sidebar__link active"><i class="fas fa-graduation-cap"></i> Learning &amp; Skills</a></li>
      <li><a href="<?= url('admin/programmes.php') ?>" class="sidebar__link"><i class="fas fa-layer-group"></i> Programmes</a></li>
      <li><a href="<?= url('admin/opportunities.php') ?>" class="sidebar__link"><i class="fas fa-briefcase"></i> Opportunities</a></li>
      <li><a href="<?= url('admin/applications.php') ?>" class="sidebar__link"><i class="fas fa-file-alt"></i> Applications</a></li>
      <li><a href="<?= url('admin/placements.php') ?>" class="sidebar__link"><i class="fas fa-handshake"></i> Placements</a></li>
      <li><a href="<?= url('admin/interviews.php') ?>" class="sidebar__link"><i class="fas fa-calendar-check"></i> Interviews</a></li>
    </ul>
    <div class="sidebar__section-label">Talent</div>
    <ul class="sidebar__menu">
      <li><a href="<?= url('admin/dashboard.php#admin-talent-hub') ?>" class="sidebar__link"><i class="fas fa-users"></i> Talent Intelligence Hub</a></li>
      <li><a href="<?= url('admin/users.php') ?>" class="sidebar__link"><i class="fas fa-user-shield"></i> User Management</a></li>
    </ul>
    <div class="sidebar__section-label">Operations</div>
    <ul class="sidebar__menu">
      <li><a href="<?= url('admin/dashboard.php#admin-reporting') ?>" class="sidebar__link"><i class="fas fa-file-export"></i> Reporting &amp; Analytics</a></li>
      <li><a href="<?= url('admin/settings.php') ?>" class="sidebar__link"><i class="fas fa-cog"></i> System Settings</a></li>
    </ul>
  </nav>
  <div class="sidebar__footer">
    <div class="sidebar__user"><div class="sidebar__user-avatar"><img src="https://ui-avatars.com/api/?name=<?= urlencode(current_user()['fullname'] ?? 'Admin User') ?>&background=1a56db&color=fff&size=80" alt="Profile"></div><div class="sidebar__user-info"><span class="sidebar__user-name"><?= e(current_user()['fullname'] ?? 'Admin User') ?></span><span class="sidebar__user-role"><?= e(current_user()['role_name'] ?? 'Platform Administrator') ?></span></div></div>
    <a href="<?= url('auth/logout.php') ?>" class="sidebar__logout"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
  </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<main class="dashboard__main">
<header class="dash-header admin-dash-header">
  <div class="dash-header__left">
    <button class="dash-header__toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="fas fa-bars"></i></button>
    <div class="dash-header__search"><i class="fas fa-search"></i><input type="text" class="dash-header__search-input" placeholder="Search learning programmes..." aria-label="Search"></div>
  </div>
  <div class="dash-header__right">
    <button class="dash-header__icon-btn" id="themeToggle" aria-label="Toggle dark mode"><i class="fas fa-moon"></i></button>
    <a href="<?= url('admin/learning_programme_form.php') ?>" class="btn btn--primary btn--sm"><i class="fas fa-plus"></i> New Programme</a>
    <div class="dash-header__user"><img src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'Admin User') ?>&background=1a56db&color=fff&size=80" alt="Profile" class="dash-header__avatar"></div>
  </div>
</header>
<div class="dash-content" id="adminDashContent">
<div class="container admin-main">
<?= $flashes ?>
<section class="pm-hero">
  <div class="pm-hero__inner">
    <div class="pm-hero__text">
      <span class="section__badge">Learning Management</span>
      <h1 class="pm-hero__title">Learning &amp; Skills</h1>
      <p class="pm-hero__subtitle">Manage programmes, assignments, progress, skills and learning outcomes from one shared record.</p>
    </div>
    <a href="<?= url('admin/learning_programme_form.php') ?>" class="btn btn--primary"><i class="fas fa-plus"></i> New Programme</a>
  </div>
</section>

<div class="pm-stats learning-admin__stats">
<?php foreach ([
    ['programmes', 'Active programmes', 'fa-book-open'],
    ['assignments', 'Total assignments', 'fa-user-graduate'],
    ['learners', 'Assigned learners', 'fa-users'],
    ['in_progress', 'In progress', 'fa-spinner'],
    ['completed', 'Completed', 'fa-check-circle'],
    ['overdue', 'Overdue', 'fa-triangle-exclamation'],
] as [$key, $label, $icon]): ?>
<div class="pm-stat pm-stat--<?= e($key) ?>"><div class="pm-stat__icon"><i class="fas <?= $icon ?>"></i></div><div><span class="pm-stat__value"><?= (int) ($stats[$key] ?? 0) ?></span><span class="pm-stat__label"><?= $label ?></span></div></div>
<?php endforeach; ?>
</div>

<div class="learning-admin__split">
<section class="admin-card">
<div class="admin-card__header"><h2 class="admin-card__title"><span class="learning-admin__card-icon"><i class="fas fa-list" aria-hidden="true"></i></span><span>Programme catalogue</span></h2>
<a class="btn btn--primary" href="<?= url('admin/learning_programme_form.php') ?>"><i class="fas fa-plus"></i> New programme</a></div>
<form method="get" class="learning-admin__toolbar admin-form">
<div class="admin-form__field"><label class="learning-admin__field-label" for="search"><i class="fas fa-search"></i> Find a programme</label><div class="learning-admin__control"><i class="fas fa-magnifying-glass"></i><input class="form-input" id="search" name="search" value="<?= e($search) ?>" placeholder="Title, category or provider"></div></div>
<div class="admin-form__field"><label class="learning-admin__field-label" for="status"><i class="fas fa-filter"></i> Catalogue status</label><div class="learning-admin__control"><i class="fas fa-circle-check"></i><select class="form-select" id="status" name="status"><option value="all">All programmes</option><option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Published</option><option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archived</option></select></div></div>
<button class="btn btn--secondary learning-admin__filter-button" type="submit"><i class="fas fa-sliders"></i> Apply filters</button>
</form>
<div class="admin-table-container"><table class="admin-table"><thead><tr><th><span class="learning-admin__table-title"><i class="fas fa-book-open"></i> Programme</span></th><th><span class="learning-admin__table-title"><i class="fas fa-tags"></i> Category</span></th><th><span class="learning-admin__table-title"><i class="fas fa-list-check"></i> Lessons</span></th><th><span class="learning-admin__table-title"><i class="fas fa-users"></i> Assignments</span></th><th><span class="learning-admin__table-title"><i class="fas fa-circle-check"></i> Status</span></th><th></th></tr></thead><tbody>
<?php foreach ($programmes as $programme): ?><tr>
<td><strong><?= e($programme['title']) ?></strong><br><span class="text-muted"><?= e($programme['provider'] ?: 'Internal') ?></span></td>
<td><?= e($programme['category']) ?></td><td><?= (int) $programme['lesson_count'] ?></td><td><?= (int) $programme['assignment_count'] ?></td>
<td><span class="badge badge--<?= $programme['status'] === 'active' ? 'success' : 'muted' ?>"><?= e(ucfirst($programme['status'])) ?></span></td>
<td><a class="btn btn--small btn--secondary" href="<?= url('admin/learning_programme_form.php?id=' . (int) $programme['id']) ?>">Manage</a></td>
</tr><?php endforeach; ?>
<?php if (!$programmes): ?><tr><td colspan="6" class="learning-admin__empty"><i class="fas fa-book-open"></i><strong>No programmes found</strong><br><span class="text-muted">Try adjusting the filters or create a new learning programme.</span></td></tr><?php endif; ?>
</tbody></table></div>
</section>

<section class="admin-card">
<div class="admin-card__header"><h2 class="admin-card__title"><span class="learning-admin__card-icon"><i class="fas fa-user-plus" aria-hidden="true"></i></span><span>Assign training</span></h2></div>
<form method="post" action="<?= url('admin/learning_actions.php') ?>" class="admin-form">
<?= csrf_field() ?><input type="hidden" name="action" value="assign">
<div class="admin-form__field"><label class="learning-admin__field-label" for="programme_id"><i class="fas fa-book-open"></i> Learning programme</label><div class="learning-admin__control"><i class="fas fa-book"></i><select class="form-select" id="programme_id" name="programme_id" required><option value="">Choose a published programme</option><?php foreach ($programmes as $p): if ($p['status'] !== 'active') continue; ?><option value="<?= (int) $p['id'] ?>"><?= e($p['title']) ?></option><?php endforeach; ?></select></div></div>
<div class="admin-form__field"><label class="learning-admin__field-label" for="candidate_id"><i class="fas fa-user-graduate"></i> Learner</label><div class="learning-admin__control"><i class="fas fa-user"></i><select class="form-select" id="candidate_id" name="candidate_id" required><option value="">Choose a candidate or consultant</option><?php foreach ($candidates as $candidate): ?><option value="<?= (int) $candidate['id'] ?>"><?= e($candidate['first_name'] . ' ' . $candidate['last_name'] . ' (' . $candidate['email'] . ')') ?></option><?php endforeach; ?></select></div></div>
<div class="admin-form__field"><label class="learning-admin__field-label" for="due_date"><i class="fas fa-calendar-days"></i> Completion deadline</label><div class="learning-admin__control"><i class="fas fa-calendar-check"></i><input class="form-input" type="date" id="due_date" name="due_date" min="<?= date('Y-m-d') ?>"></div></div>
<button class="btn btn--primary learning-admin__filter-button" type="submit"><i class="fas fa-paper-plane"></i> Assign training</button>
</form>
<p class="learning-admin__hint"><i class="fas fa-shield-check"></i><span>Assignments are linked to the learner record, and duplicates are prevented automatically.</span></p>
</section>
</div>

<section class="admin-card learning-admin__progress-card">
<div class="admin-card__header"><h2 class="admin-card__title"><span class="learning-admin__card-icon"><i class="fas fa-chart-line" aria-hidden="true"></i></span><span>Learner progress</span></h2></div>
<form method="get" class="learning-admin__toolbar admin-form">
<div class="admin-form__field"><label class="learning-admin__field-label" for="assignment_status"><i class="fas fa-chart-line"></i> Progress status</label><div class="learning-admin__control"><i class="fas fa-signal"></i><select class="form-select" id="assignment_status" name="assignment_status"><option value="all">All progress statuses</option><?php foreach (LearningProgramme::ALL_STATUSES as $option): ?><option value="<?= $option ?>" <?= $assignmentStatus === $option ? 'selected' : '' ?>><?= e(LearningProgramme::statusLabel($option)) ?></option><?php endforeach; ?></select></div></div>
<div class="admin-form__field"><label class="learning-admin__field-label" for="programme_filter"><i class="fas fa-book-open"></i> Programme</label><div class="learning-admin__control"><i class="fas fa-layer-group"></i><select class="form-select" id="programme_filter" name="programme_id"><option value="0">All programmes</option><?php foreach ($programmes as $p): ?><option value="<?= (int) $p['id'] ?>" <?= $programmeId === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['title']) ?></option><?php endforeach; ?></select></div></div>
<button class="btn btn--secondary learning-admin__filter-button" type="submit"><i class="fas fa-filter"></i> Filter progress</button>
</form>
<div class="admin-table-container"><table class="admin-table"><thead><tr><th><span class="learning-admin__table-title"><i class="fas fa-user-graduate"></i> Learner</span></th><th><span class="learning-admin__table-title"><i class="fas fa-book-open"></i> Programme</span></th><th><span class="learning-admin__table-title"><i class="fas fa-signal"></i> Status</span></th><th><span class="learning-admin__table-title"><i class="fas fa-chart-simple"></i> Progress</span></th><th><span class="learning-admin__table-title"><i class="fas fa-calendar-days"></i> Due date</span></th></tr></thead><tbody>
<?php foreach ($assignments as $assignment): ?><tr><td><?= e($assignment['first_name'] . ' ' . $assignment['last_name']) ?><br><span class="text-muted"><?= e($assignment['email']) ?></span></td><td><?= e($assignment['title']) ?></td><td><span class="badge badge--<?= LearningProgramme::statusTone($assignment['computed_status']) ?>"><?= e(LearningProgramme::statusLabel($assignment['computed_status'])) ?></span></td><td class="learning-admin__progress-cell"><div class="progress-bar"><div class="progress-bar__fill" style="width:<?= (int) $assignment['progress'] ?>%"></div></div><span class="text-muted"><?= (int) $assignment['progress'] ?>% complete</span></td><td><?= $assignment['due_date'] ? e(format_date($assignment['due_date'], 'M d, Y')) : 'No deadline' ?></td></tr><?php endforeach; ?>
<?php if (!$assignments): ?><tr><td colspan="5" class="learning-admin__empty"><i class="fas fa-chart-line"></i><strong>No learner progress found</strong><br><span class="text-muted">Assignments matching your selected filters will appear here.</span></td></tr><?php endif; ?>
</tbody></table></div>
</section>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var sidebar = document.getElementById('adminSidebar');
  var toggle = document.getElementById('sidebarToggle');
  var close = document.getElementById('sidebarClose');
  var overlay = document.getElementById('sidebarOverlay');
  function closeSidebar() {
    if (sidebar) sidebar.classList.remove('open');
    if (overlay) overlay.classList.remove('open');
    document.body.classList.remove('sidebar-open');
    document.body.style.overflow = '';
  }
  if (toggle) toggle.addEventListener('click', function () {
    if (sidebar) sidebar.classList.add('open');
    if (overlay) overlay.classList.add('open');
    document.body.classList.add('sidebar-open');
    document.body.style.overflow = 'hidden';
  });
  if (close) close.addEventListener('click', closeSidebar);
  if (overlay) overlay.addEventListener('click', closeSidebar);
});
</script>
<script src="<?= url('js/script.js') ?>"></script>
<script src="<?= url('js/admin_dashboard.js') ?>"></script>
<script src="<?= url('js/admin_programmes.js') ?>"></script>
</div>
</main>
</div>
</body>
</html>
