<?php
$currentPage = $currentPage ?? '';
if (!isset($user) || !is_array($user)) $user = current_user();
$nf = trim((string)($user['first_name'] ?? ''));
$nl = trim((string)($user['last_name'] ?? ''));
$nn = trim((string)($user['full_name'] ?? $user['fullname'] ?? ($nf . ' ' . $nl)));
if ($nn === '') $nn = 'Supervisor';
$ni = sv_initials($nf, $nl);
$titles = [
 'dashboard'=>'Dashboard','cohorts'=>'My Cohorts','cohort_view'=>'Cohort Details','candidates'=>'Candidates',
 'candidate_view'=>'Candidate Details','update_candidate_status'=>'Update Candidate','reports'=>'Cohort Progress','activity_log'=>'Activity Log'
];
$pageTitle = $titles[$currentPage] ?? 'Supervisor Portal';
?>
<header class="supervisor-navbar">
  <div class="sv-navbar-left">
    <button class="sv-menu-btn" id="supervisorMenuButton" type="button" aria-label="Open navigation"><i class="fas fa-bars"></i></button>
    <div class="sv-navbar-title"><span>Supervisor Workspace</span><h1><?= e($pageTitle) ?></h1></div>
  </div>
  <div class="sv-navbar-right">
    <button class="sv-icon-btn" type="button" data-supervisor-theme-toggle aria-label="Toggle theme"><i class="fas fa-moon" data-supervisor-theme-icon></i></button>
    <div class="sv-dropdown-wrap">
      <button class="sv-icon-btn" id="supervisorNotificationButton" type="button" aria-label="Notifications"><i class="fas fa-bell"></i></button>
      <div class="sv-dropdown" id="supervisorNotificationDropdown">
        <div class="sv-dropdown__header"><strong>Notifications</strong><span>Supervisor updates</span></div>
        <div class="sv-dropdown__empty"><i class="fas fa-bell"></i><strong>You're all caught up</strong><span>New cohort and candidate notifications will appear here.</span></div>
      </div>
    </div>
    <div class="sv-navbar-user"><div class="sv-navbar-avatar"><?= e($ni) ?></div><div><strong><?= e($nn) ?></strong><span>Supervisor</span></div></div>
  </div>
</header>
