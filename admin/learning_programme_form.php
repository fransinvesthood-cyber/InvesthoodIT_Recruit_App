<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['admin', 'programme_manager', 'programme_officer']);

$id = (int) ($_GET['id'] ?? 0);
$programme = $id > 0 ? LearningProgramme::find($id) : null;
if ($id > 0 && !$programme) {
    set_flash('error', 'Programme not found', 'The requested learning programme does not exist.');
    safe_redirect('admin/learning.php');
}
$lessons = $programme ? LearningProgramme::lessonsForProgramme($id) : [];
$flashes = render_flashes();
$user = current_user();
$isEdit = $programme !== null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $isEdit ? 'Edit' : 'Create' ?> Learning Programme | Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
<link rel="stylesheet" href="<?= url('css/styles.css') ?>">
<link rel="stylesheet" href="<?= url('css/admin_programmes.css') ?>">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<style>
.learning-form-page .admin-main{max-width:1080px;padding-top:1.75rem;padding-bottom:3rem}
.learning-form-page .learning-admin__header-label{display:flex;align-items:center;gap:.55rem;color:#0f172a;font-size:.9rem;font-weight:700}
.learning-form-page .learning-admin__header-label i{color:#2563eb}
.learning-form-page .learning-form__hero{display:flex;align-items:flex-start;justify-content:space-between;gap:2rem;margin-bottom:1.5rem;padding:1.45rem 1.65rem;background:#f8fbff;border:1px solid #dbeafe;border-radius:14px;box-shadow:0 8px 24px rgba(30,64,175,.06)}
.learning-form-page .learning-form__eyebrow{display:flex;align-items:center;gap:.45rem;margin-bottom:.45rem;color:#2563eb;font-size:.7rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase}
.learning-form-page .learning-form__hero h1{margin:0;color:#172554;font-size:clamp(1.55rem,3vw,2.1rem);font-weight:800;letter-spacing:-.03em}
.learning-form-page .learning-form__hero p{margin:.5rem 0 0;color:#64748b;font-size:.88rem;line-height:1.5}
.learning-form-page .learning-form__crumb{display:flex;align-items:center;gap:.45rem;margin-bottom:.9rem;color:#64748b;font-size:.75rem}
.learning-form-page .learning-form__crumb a{color:#2563eb;text-decoration:none}.learning-form-page .learning-form__crumb a:hover{text-decoration:underline}
.learning-form-page .learning-form__crumb i{color:#94a3b8;font-size:.65rem}
.learning-form-page .learning-form__card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;box-shadow:0 6px 20px rgba(15,23,42,.05)}
.learning-form-page .learning-form__section{padding:1.45rem 1.55rem;border-bottom:1px solid #eef2f7}
.learning-form-page .learning-form__section:last-of-type{border-bottom:0}
.learning-form-page .learning-form__section-heading{display:flex;align-items:flex-start;gap:.7rem;margin-bottom:1.2rem}
.learning-form-page .learning-form__section-icon{display:grid;place-items:center;width:34px;height:34px;flex:0 0 34px;border-radius:9px;background:#eff6ff;color:#2563eb}
.learning-form-page .learning-form__section-heading h2{margin:0;color:#172033;font-size:1rem;font-weight:750}
.learning-form-page .learning-form__section-heading p{margin:.25rem 0 0;color:#64748b;font-size:.76rem;line-height:1.4}
.learning-form-page .learning-form__grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1.1rem 1.25rem}
.learning-form-page .learning-form__field{min-width:0}
.learning-form-page .learning-form__field--full{grid-column:1/-1}
.learning-form-page .learning-form__label{display:flex;align-items:center;gap:.45rem;margin:0 0 .45rem;color:#334155;font-size:.76rem;font-weight:750}
.learning-form-page .learning-form__label i{width:15px;color:#2563eb;font-size:.75rem;text-align:center}
.learning-form-page .learning-form__control{position:relative}
.learning-form-page .learning-form__control>i{position:absolute;left:.85rem;top:1rem;z-index:1;color:#94a3b8;font-size:.78rem;pointer-events:none}
.learning-form-page .learning-form__control .form-input,.learning-form-page .learning-form__control .form-select{box-sizing:border-box;width:100%;min-height:44px;padding:.7rem .85rem .7rem 2.3rem;border:1px solid #cbd5e1;border-radius:9px;background:#fff!important;color:#172033!important;color-scheme:light;font:inherit;font-size:.83rem;transition:border-color .2s,box-shadow .2s}
.learning-form-page .learning-form__control textarea.form-input{min-height:118px;resize:vertical;line-height:1.5}
.learning-form-page .learning-form__control textarea[name="objectives"]{min-height:140px}
.learning-form-page .learning-form__control .form-select{appearance:none;-webkit-appearance:none;cursor:pointer;padding-right:2.7rem}
.learning-form-page .learning-form__control:after{content:"\f078";position:absolute;right:.9rem;top:50%;color:#2563eb;font-family:"Font Awesome 6 Free";font-size:.65rem;font-weight:900;pointer-events:none;transform:translateY(-50%)}
.learning-form-page .learning-form__control:focus-within .form-input,.learning-form-page .learning-form__control:focus-within .form-select{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.12);outline:none}
.learning-form-page .learning-form__control:focus-within>i{color:#2563eb}
.learning-form-page .learning-form__support{display:block;margin:.45rem 0 0;color:#64748b;font-size:.72rem;line-height:1.45}
.learning-form-page .learning-form__actions{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1.15rem 1.55rem;background:#f8fafc;border-radius:0 0 14px 14px}
.learning-form-page .learning-form__actions .btn{display:inline-flex;align-items:center;justify-content:center;gap:.45rem;min-height:42px}
.learning-form-page .learning-form__lessons{margin-top:1.35rem}
.learning-form-page .learning-form__lesson-form{padding:1.25rem 1.55rem;border-bottom:1px solid #eef2f7}
.learning-form-page .learning-form__lesson-grid{display:grid;grid-template-columns:minmax(0,1.3fr) minmax(150px,.55fr) minmax(0,1fr);gap:1rem;align-items:end}
.learning-form-page .learning-form__lesson-grid>.learning-form__field>input,.learning-form-page .learning-form__lesson-grid>.learning-form__field>select{box-sizing:border-box;width:100%;height:44px;padding:0 .85rem;border:1px solid #cbd5e1;border-radius:9px;background:#fff!important;color:#172033!important;color-scheme:light;font:inherit;font-size:.83rem}
.learning-form-page .learning-form__lesson-grid>.learning-form__field>select{appearance:none;-webkit-appearance:none;padding-right:2.5rem}
.learning-form-page .learning-form__lesson-grid>.learning-form__field{position:relative}
.learning-form-page .learning-form__lesson-grid>.learning-form__field:has(select):after{content:"\f078";position:absolute;right:.85rem;bottom:1rem;color:#2563eb;font-family:"Font Awesome 6 Free";font-size:.65rem;font-weight:900;pointer-events:none}
.learning-form-page .learning-form__lesson-grid>.learning-form__field>input:focus,.learning-form-page .learning-form__lesson-grid>.learning-form__field>select:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.12);outline:none}
.learning-form-page .learning-form__lesson-table{padding:0 1.55rem 1.35rem;overflow-x:auto}
.learning-form-page .learning-form__lesson-table table{width:100%;min-width:560px;border-collapse:collapse}
.learning-form-page .learning-form__lesson-table th{padding:.8rem .7rem;background:#f8fafc;color:#64748b;font-size:.7rem;text-align:left;text-transform:uppercase;letter-spacing:.04em}
.learning-form-page .learning-form__lesson-table td{padding:.85rem .7rem;border-bottom:1px solid #eef2f7;color:#334155;font-size:.8rem}
.learning-form-page .learning-form__empty{text-align:center;padding:1.5rem;color:#64748b;font-size:.8rem}
.learning-form-page .learning-form__empty i{display:block;margin-bottom:.45rem;color:#94a3b8;font-size:1.4rem}
body.dark-mode .learning-form-page .learning-form__control .form-input,body.dark-mode .learning-form-page .learning-form__control .form-select,body.dark-mode .learning-form-page .learning-form__control .form-select option,body.dark-mode .learning-form-page .learning-form__lesson-grid input,body.dark-mode .learning-form-page .learning-form__lesson-grid select{background:#fff!important;color:#172033!important;border-color:#cbd5e1}
@media(max-width:800px){.learning-form-page .learning-form__hero{flex-direction:column;gap:1rem}.learning-form-page .learning-form__hero .btn{width:100%}.learning-form-page .learning-form__lesson-grid{grid-template-columns:1fr}}
@media(max-width:600px){.learning-form-page .admin-main{padding:1rem .85rem 2rem}.learning-form-page .learning-form__grid{grid-template-columns:1fr}.learning-form-page .learning-form__field--full{grid-column:auto}.learning-form-page .learning-form__section,.learning-form-page .learning-form__lesson-form{padding:1.15rem}.learning-form-page .learning-form__actions{align-items:stretch;flex-direction:column;padding:1rem 1.15rem}.learning-form-page .learning-form__actions .btn{width:100%}.learning-form-page .learning-form__lesson-table{padding-right:1rem;padding-left:1rem}}
</style>
</head>
<body class="dashboard-page admin-dashboard pm-module learning-form-page">
<div class="dashboard">
<aside class="sidebar admin-sidebar" id="adminSidebar">
  <div class="sidebar__header"><a href="<?= url('index.php') ?>" class="logo"><span class="logo__icon"><i class="fas fa-code"></i></span><span class="logo__text">Investhood <span class="logo__accent">IT</span></span></a><button class="sidebar__close" id="sidebarClose" aria-label="Close sidebar"><i class="fas fa-times"></i></button></div>
  <nav class="sidebar__nav">
    <div class="sidebar__section-label">Command Centre</div><ul class="sidebar__menu"><li><a href="<?= url('admin/dashboard.php') ?>" class="sidebar__link"><i class="fas fa-th-large"></i> Executive Overview</a></li><li><a href="<?= url('admin/dashboard.php#admin-analytics') ?>" class="sidebar__link"><i class="fas fa-chart-pie"></i> Analytics</a></li><li><a href="<?= url('admin/dashboard.php#admin-notifications') ?>" class="sidebar__link"><i class="fas fa-bell"></i> Notifications</a></li></ul>
    <div class="sidebar__section-label">Management</div><ul class="sidebar__menu"><li><a href="<?= url('admin/learning.php') ?>" class="sidebar__link active"><i class="fas fa-graduation-cap"></i> Learning &amp; Skills</a></li><li><a href="<?= url('admin/programmes.php') ?>" class="sidebar__link"><i class="fas fa-layer-group"></i> Programmes</a></li><li><a href="<?= url('admin/opportunities.php') ?>" class="sidebar__link"><i class="fas fa-briefcase"></i> Opportunities</a></li><li><a href="<?= url('admin/applications.php') ?>" class="sidebar__link"><i class="fas fa-file-alt"></i> Applications</a></li><li><a href="<?= url('admin/placements.php') ?>" class="sidebar__link"><i class="fas fa-handshake"></i> Placements</a></li><li><a href="<?= url('admin/interviews.php') ?>" class="sidebar__link"><i class="fas fa-calendar-check"></i> Interviews</a></li></ul>
    <div class="sidebar__section-label">Talent</div><ul class="sidebar__menu"><li><a href="<?= url('admin/dashboard.php#admin-talent-hub') ?>" class="sidebar__link"><i class="fas fa-users"></i> Talent Intelligence Hub</a></li><li><a href="<?= url('admin/users.php') ?>" class="sidebar__link"><i class="fas fa-user-shield"></i> User Management</a></li></ul>
    <div class="sidebar__section-label">Operations</div><ul class="sidebar__menu"><li><a href="<?= url('admin/dashboard.php#admin-reporting') ?>" class="sidebar__link"><i class="fas fa-file-export"></i> Reporting &amp; Analytics</a></li><li><a href="<?= url('admin/settings.php') ?>" class="sidebar__link"><i class="fas fa-cog"></i> System Settings</a></li></ul>
  </nav>
  <div class="sidebar__footer"><div class="sidebar__user"><div class="sidebar__user-avatar"><img src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'Admin User') ?>&background=1a56db&color=fff&size=80" alt="Profile"></div><div class="sidebar__user-info"><span class="sidebar__user-name"><?= e($user['fullname'] ?? 'Admin User') ?></span><span class="sidebar__user-role"><?= e($user['role_name'] ?? 'Platform Administrator') ?></span></div></div><a href="<?= url('auth/logout.php') ?>" class="sidebar__logout"><i class="fas fa-sign-out-alt"></i> Sign Out</a></div>
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
    <a href="<?= url('admin/learning.php') ?>" class="btn btn--ghost btn--sm">Learning dashboard</a>
    <div class="dash-header__user"><img src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'Admin User') ?>&background=1a56db&color=fff&size=80" alt="Profile" class="dash-header__avatar"></div>
  </div>
</header>
<div class="dash-content"><div class="container admin-main">
<?= $flashes ?>
<div class="learning-form__crumb"><a href="<?= url('admin/learning.php') ?>">Learning &amp; Skills</a><i class="fas fa-chevron-right"></i><span><?= $isEdit ? 'Edit programme' : 'Create learning programme' ?></span></div>
<section class="pm-hero">
  <div class="pm-hero__inner">
    <div class="pm-hero__text">
      <span class="section__badge">Programme Management</span>
      <h1 class="pm-hero__title"><?= $isEdit ? 'Edit Learning Programme' : 'Create Learning Programme' ?></h1>
      <p class="pm-hero__subtitle">Create and manage learning programmes available to candidates and consultants.</p>
    </div>
    <a class="btn btn--secondary" href="<?= url('admin/learning.php') ?>"><i class="fas fa-arrow-left"></i> Back to Learning &amp; Skills</a>
  </div>
</section>

<section class="learning-form__card">
<form method="post" action="<?= url('admin/learning_actions.php') ?>">
<?= csrf_field() ?><input type="hidden" name="action" value="save_programme"><input type="hidden" name="programme_id" value="<?= $id ?>">
<div class="learning-form__section"><div class="learning-form__section-heading"><span class="learning-form__section-icon"><i class="fas fa-circle-info"></i></span><div><h2>Programme information</h2><p>Set the core details learners will see in the catalogue.</p></div></div>
<div class="learning-form__grid">
<div class="learning-form__field learning-form__field--full"><label class="learning-form__label" for="title"><i class="fas fa-heading"></i> Programme title</label><div class="learning-form__control"><i class="fas fa-book-open"></i><input class="form-input" id="title" name="title" required maxlength="160" placeholder="Enter programme title" value="<?= e($programme['title'] ?? '') ?>"></div></div>
<div class="learning-form__field"><label class="learning-form__label" for="category"><i class="fas fa-tags"></i> Category</label><div class="learning-form__control"><i class="fas fa-layer-group"></i><input class="form-input" id="category" name="category" maxlength="60" placeholder="e.g. Technical skills" value="<?= e($programme['category'] ?? 'general') ?>"></div></div>
<div class="learning-form__field"><label class="learning-form__label" for="provider"><i class="fas fa-building"></i> Training provider</label><div class="learning-form__control"><i class="fas fa-school"></i><input class="form-input" id="provider" name="provider" maxlength="160" placeholder="Enter training provider" value="<?= e($programme['provider'] ?? '') ?>"></div></div>
<div class="learning-form__field"><label class="learning-form__label" for="duration_hours"><i class="fas fa-clock"></i> Duration in hours</label><div class="learning-form__control"><i class="fas fa-stopwatch"></i><input class="form-input" type="number" min="0" id="duration_hours" name="duration_hours" placeholder="0" value="<?= (int) ($programme['duration_hours'] ?? 0) ?>"></div></div>
</div></div>
<div class="learning-form__section"><div class="learning-form__section-heading"><span class="learning-form__section-icon"><i class="fas fa-file-lines"></i></span><div><h2>Programme content</h2><p>Describe the programme and the outcomes learners should achieve.</p></div></div>
<div class="learning-form__grid"><div class="learning-form__field learning-form__field--full"><label class="learning-form__label" for="description"><i class="fas fa-align-left"></i> Description</label><div class="learning-form__control"><i class="fas fa-file-lines"></i><textarea class="form-input" id="description" name="description" rows="4" placeholder="Describe the programme, intended audience and learning experience."><?= e($programme['description'] ?? '') ?></textarea></div></div>
<div class="learning-form__field learning-form__field--full"><label class="learning-form__label" for="objectives"><i class="fas fa-bullseye"></i> Learning objectives</label><div class="learning-form__control"><i class="fas fa-bullseye"></i><textarea class="form-input" id="objectives" name="objectives" rows="5" placeholder="List the knowledge, skills or capabilities learners should gain."><?= e($programme['objectives'] ?? '') ?></textarea></div><span class="learning-form__support">Use clear, measurable outcomes where possible.</span></div></div></div>
<div class="learning-form__section"><div class="learning-form__section-heading"><span class="learning-form__section-icon"><i class="fas fa-eye"></i></span><div><h2>Publication settings</h2><p>Control whether this programme is available for assignment.</p></div></div>
<div class="learning-form__grid"><div class="learning-form__field"><label class="learning-form__label" for="status"><i class="fas fa-toggle-on"></i> Availability</label><div class="learning-form__control"><i class="fas fa-circle-check"></i><select class="form-select" id="status" name="status"><option value="active" <?= ($programme['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Published — available to assign</option><option value="archived" <?= ($programme['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Archived — hidden from assignment</option></select></div></div></div></div>
<div class="learning-form__actions"><a class="btn btn--secondary" href="<?= url('admin/learning.php') ?>">Cancel</a><button class="btn btn--primary" type="submit"><i class="fas fa-save"></i> <?= $isEdit ? 'Save changes' : 'Save programme' ?></button></div>
</form></section>

<?php if ($programme): ?><section class="learning-form__card learning-form__lessons"><div class="learning-form__section-heading" style="padding:1.35rem 1.55rem 0;margin-bottom:0"><span class="learning-form__section-icon"><i class="fas fa-list-check"></i></span><div><h2>Lessons and resources</h2><p>Build the content learners will complete in this programme.</p></div></div>
<form method="post" action="<?= url('admin/learning_actions.php') ?>" class="learning-form__lesson-form"><p class="learning-form__support" style="margin:0 0 1rem">Add PDFs, videos, documents, links or lessons. Candidates only see lessons when the programme is published.</p><?= csrf_field() ?><input type="hidden" name="action" value="add_lesson"><input type="hidden" name="programme_id" value="<?= $id ?>"><div class="learning-form__lesson-grid"><div class="learning-form__field"><label class="learning-form__label" for="lesson_title"><i class="fas fa-heading"></i> Lesson title</label><input class="form-input" id="lesson_title" name="lesson_title" required maxlength="160" placeholder="Enter lesson title"></div><div class="learning-form__field"><label class="learning-form__label" for="lesson_type"><i class="fas fa-file"></i> Resource type</label><select class="form-select" id="lesson_type" name="lesson_type"><?php foreach (LearningProgramme::LESSON_TYPES as $type): ?><option value="<?= $type ?>"><?= e(ucfirst($type)) ?></option><?php endforeach; ?></select></div><div class="learning-form__field"><label class="learning-form__label" for="content_url"><i class="fas fa-link"></i> Resource URL</label><input class="form-input" type="url" id="content_url" name="content_url" maxlength="500" placeholder="https://..."></div></div><button class="btn btn--secondary" type="submit" style="margin-top:1rem"><i class="fas fa-plus"></i> Add lesson</button></form>
<div class="learning-form__lesson-table"><table><thead><tr><th>#</th><th>Lesson title</th><th>Type</th><th>Required</th></tr></thead><tbody><?php foreach ($lessons as $lesson): ?><tr><td><?= (int) $lesson['lesson_order'] ?></td><td><?= e($lesson['title']) ?></td><td><?= e(ucfirst($lesson['type'])) ?></td><td><?= (int) $lesson['is_required'] ? 'Yes' : 'No' ?></td></tr><?php endforeach; ?><?php if (!$lessons): ?><tr><td colspan="4" class="learning-form__empty"><i class="fas fa-inbox"></i>No lessons have been added yet.</td></tr><?php endif; ?></tbody></table></div></section><?php endif; ?>
</div></div></main></div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var sidebar = document.getElementById('adminSidebar'), toggle = document.getElementById('sidebarToggle'), close = document.getElementById('sidebarClose'), overlay = document.getElementById('sidebarOverlay');
  function closeSidebar(){if(sidebar)sidebar.classList.remove('open');if(overlay)overlay.classList.remove('open');document.body.classList.remove('sidebar-open');document.body.style.overflow='';}
  if(toggle)toggle.addEventListener('click',function(){if(sidebar)sidebar.classList.add('open');if(overlay)overlay.classList.add('open');document.body.classList.add('sidebar-open');document.body.style.overflow='hidden';});
  if(close)close.addEventListener('click',closeSidebar);if(overlay)overlay.addEventListener('click',closeSidebar);
});
</script>
<script src="<?= url('js/script.js') ?>"></script>
<script src="<?= url('js/admin_dashboard.js') ?>"></script>
<script src="<?= url('js/admin_programmes.js') ?>"></script>
</body>
</html>
