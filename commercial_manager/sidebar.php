<?php
$currentPage=$currentPage??'';
if(!isset($user)||!is_array($user))$user=current_user();
$f=trim((string)($user['first_name']??''));$l=trim((string)($user['last_name']??''));
$n=trim((string)($user['full_name']??$user['fullname']??($f.' '.$l))); if($n==='')$n='Commercial Manager';
$i=cm_initials($f,$l);
if(!function_exists('cm_nav')){function cm_nav(string $c,$p):string{$p=is_array($p)?$p:[$p];return in_array($c,$p,true)?'cm-nav__item--active':'';}}
?>
<aside class="cm-sidebar" id="cmSidebar">
<div class="cm-sidebar__brand"><div class="cm-sidebar__logo"><i class="fas fa-briefcase"></i></div><div><strong>Investhood IT</strong><span>Commercial Intelligence</span></div><button id="cmSidebarClose"><i class="fas fa-xmark"></i></button></div>
<div class="cm-profile"><div class="cm-avatar"><?= e($i) ?></div><div><strong><?= e($n) ?></strong><span>Commercial Manager</span></div></div>
<nav class="cm-nav">
<div class="cm-nav__label">Main</div><a class="cm-nav__item <?= cm_nav($currentPage,'dashboard') ?>" href="<?= url('commercial_manager/dashboard.php') ?>"><i class="fas fa-grid-2"></i><span>Dashboard</span></a>
<div class="cm-nav__label">Capability Packs</div><a class="cm-nav__item <?= cm_nav($currentPage,['saved_searches','saved_search_view']) ?>" href="<?= url('commercial_manager/saved_searches.php') ?>"><i class="fas fa-bookmark"></i><span>Saved Searches</span></a>
<a class="cm-nav__item <?= cm_nav($currentPage,['capability_packs','generate_pack','pack_view']) ?>" href="<?= url('commercial_manager/capability_packs.php') ?>"><i class="fas fa-file-shield"></i><span>Capability Packs</span></a>
<div class="cm-nav__label">Governance</div><a class="cm-nav__item <?= cm_nav($currentPage,'privacy_rules') ?>" href="<?= url('commercial_manager/privacy_rules.php') ?>"><i class="fas fa-user-secret"></i><span>Privacy Rules</span></a>
<a class="cm-nav__item <?= cm_nav($currentPage,'reports') ?>" href="<?= url('commercial_manager/reports.php') ?>"><i class="fas fa-chart-column"></i><span>Reports</span></a>
<a class="cm-nav__item <?= cm_nav($currentPage,'activity_log') ?>" href="<?= url('commercial_manager/activity_log.php') ?>"><i class="fas fa-clock-rotate-left"></i><span>Activity Log</span></a>
<div class="cm-nav__label">Appearance</div><button class="cm-theme" data-cm-theme-toggle><i class="fas fa-moon" data-cm-theme-icon></i><span>Dark Mode</span></button>
</nav>
<div class="cm-sidebar__footer"><div class="cm-secure"><i class="fas fa-user-secret"></i><div><strong>Privacy by Design</strong><span>Direct identifiers and small groups are suppressed.</span></div></div><a class="cm-logout" href="<?= url('auth/logout.php') ?>"><i class="fas fa-arrow-right-from-bracket"></i> Sign Out</a></div>
</aside><div class="cm-overlay" id="cmOverlay"></div>