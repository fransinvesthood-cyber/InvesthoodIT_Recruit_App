<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();
$user = current_user();
$role = (string)(current_role() ?? '');
$userId = (int)($user['id'] ?? $user['user_id'] ?? 0);
$allowed = ['admin','programme_manager','programme_officer','recruiter','supervisor','candidate','assessor','finance_officer','information_officer'];
if (!in_array($role, $allowed, true)) { http_response_code(403); exit('You do not have permission to view announcements.'); }
$conn = Database::getConnection();
$currentPage = 'announcements';
$pageTitle = 'Announcements';
$roleLabel = ucwords(str_replace('_',' ',$role));
$where = ["a.status='published'", "(a.target_role='all' OR a.target_role=?)"];
$params = [$role]; $types = 's';
if ($role === 'programme_manager') {
    $where[] = '(a.programme_id IS NULL OR p.programme_manager_id=?)'; $params[]=$userId; $types.='i';
} elseif ($role === 'candidate') {
    $where[] = '(a.programme_id IS NULL OR EXISTS (SELECT 1 FROM cohort_participants cp JOIN cohorts cx ON cx.id=cp.cohort_id WHERE cp.user_id=? AND cx.programme_id=a.programme_id AND (a.cohort_id IS NULL OR cx.id=a.cohort_id)))'; $params[]=$userId; $types.='i';
} elseif ($role === 'supervisor') {
    $where[] = '(a.programme_id IS NULL OR EXISTS (SELECT 1 FROM cohorts sx WHERE sx.supervisor_id=? AND sx.programme_id=a.programme_id AND (a.cohort_id IS NULL OR sx.id=a.cohort_id)))'; $params[]=$userId; $types.='i';
} elseif ($role === 'programme_officer') {
    // The current database schema does not assign officers directly to programmes; use role-targeting and published status.
    $where[] = '(a.programme_id IS NULL OR p.id IS NOT NULL)';
} elseif ($role === 'admin') {
    // Administrators can review all published announcements.
} else {
    // Other roles see organisation-wide or role-targeted announcements. Programme-specific targeting is intentional.
}
$sql = "SELECT a.id,a.title,a.message,a.category,a.priority,a.target_role,a.published_at,p.name AS programme_name,c.name AS cohort_name,u.first_name,u.last_name FROM programme_announcements a LEFT JOIN programmes p ON p.id=a.programme_id LEFT JOIN cohorts c ON c.id=a.cohort_id LEFT JOIN users u ON u.id=a.author_id WHERE " . implode(' AND ', $where) . " ORDER BY FIELD(a.priority,'urgent','important','normal'),a.published_at DESC,a.id DESC";
$items=[];
$stmt=$conn->prepare($sql);
if($stmt){$stmt->bind_param($types,...$params);$stmt->execute();$r=$stmt->get_result();while($row=$r->fetch_assoc())$items[]=$row;$stmt->close();}
function comm_e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=comm_e($pageTitle)?> | Investhood IT</title><script>(function(){try{var t=localStorage.getItem('investhood-unified-theme')||localStorage.getItem('investhood-programme-manager-theme');document.documentElement.setAttribute('data-theme',t==='dark'?'dark':'light')}catch(e){document.documentElement.setAttribute('data-theme','light')}})();</script><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"><link rel="stylesheet" href="<?=url('css/styles.css')?>"><link rel="stylesheet" href="<?=url('css/unified_portal.css')?>?v=1"><?php if($role==='recruiter'):?><link rel="stylesheet" href="<?=url('css/recruiter.css')?>"><link rel="stylesheet" href="<?=url('css/styles_original_pm.css')?>"><link rel="stylesheet" href="<?=url('css/original_pm_sidebar_compat.css')?>"><?php endif;?><style>
.comm-audience{--ca-card:#fff;--ca-border:#e2e8f0;--ca-text:#334155;--ca-heading:#0f172a;--ca-muted:#64748b;--ca-hero-bg:linear-gradient(120deg,#eff6ff,#f8fafc);--ca-hero-border:#dbeafe;--ca-accent:#1d4ed8;--ca-tag-bg:#eff6ff;--ca-tag-fg:#1d4ed8;--ca-urgent-bg:#fee2e2;--ca-urgent-fg:#991b1b;--ca-imp-bg:#fef3c7;--ca-imp-fg:#92400e;--ca-icon:#94a3b8}
html[data-theme="dark"] .comm-audience{--ca-card:#1e293b;--ca-border:#334155;--ca-text:#cbd5e1;--ca-heading:#f1f5f9;--ca-muted:#94a3b8;--ca-hero-bg:linear-gradient(120deg,#172554,#1e293b);--ca-hero-border:#334155;--ca-accent:#93c5fd;--ca-tag-bg:rgba(59,130,246,.18);--ca-tag-fg:#93c5fd;--ca-urgent-bg:rgba(239,68,68,.18);--ca-urgent-fg:#fca5a5;--ca-imp-bg:rgba(245,158,11,.18);--ca-imp-fg:#fcd34d;--ca-icon:#64748b}
.comm-audience{max-width:1200px;margin:0 auto;padding:1.5rem;color:var(--ca-text)}
.comm-audience__hero{padding:1.6rem;border-radius:18px;background:var(--ca-hero-bg);border:1px solid var(--ca-hero-border);margin-bottom:1.25rem}
.comm-audience__hero span{font-size:.75rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:var(--ca-accent)}
.comm-audience__hero h2{margin:.5rem 0;font-size:clamp(1.5rem,3vw,2.1rem);color:var(--ca-heading)}
.comm-audience__hero p{margin:0;max-width:760px;line-height:1.6;color:var(--ca-text)}
.comm-audience__list{display:grid;gap:1rem}
.comm-audience__item{background:var(--ca-card);border:1px solid var(--ca-border);border-radius:16px;padding:1.25rem;min-width:0}
.comm-audience__top{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem}
.comm-audience__item h3{margin:0;font-size:1.1rem;line-height:1.45;overflow-wrap:anywhere;color:var(--ca-heading)}
.comm-audience__item p{white-space:pre-wrap;overflow-wrap:anywhere;line-height:1.7;margin:1rem 0;color:var(--ca-text)}
.comm-audience__item small{color:var(--ca-muted)}
.comm-audience__meta{display:flex;flex-wrap:wrap;gap:.45rem;margin-top:.65rem}
.comm-audience__tag{display:inline-flex;padding:.25rem .6rem;border-radius:999px;font-size:.72rem;font-weight:800;background:var(--ca-tag-bg);color:var(--ca-tag-fg)}
.comm-audience__tag--urgent{background:var(--ca-urgent-bg);color:var(--ca-urgent-fg)}
.comm-audience__tag--important{background:var(--ca-imp-bg);color:var(--ca-imp-fg)}
.comm-audience__empty{text-align:center;padding:3rem 1rem;background:var(--ca-card);border:1px solid var(--ca-border);border-radius:16px;color:var(--ca-muted)}
.comm-audience__empty i{font-size:2rem;color:var(--ca-icon)}
.comm-audience__empty h3{color:var(--ca-heading)}
@media(max-width:600px){.comm-audience{padding:1rem}.comm-audience__top{flex-direction:column}}
</style></head><body class="ux-body"><div class="ux-app"><?php
$sidebarPath = null;
if ($role==='programme_manager' && is_file(__DIR__.'/../programme/sidebar.php')) $sidebarPath=__DIR__.'/../programme/sidebar.php';
elseif ($role==='programme_officer' && is_file(__DIR__.'/../programme_officer/sidebar.php')) $sidebarPath=__DIR__.'/../programme_officer/sidebar.php';
elseif ($role==='supervisor' && is_file(__DIR__.'/../supervisor/sidebar.php')) $sidebarPath=__DIR__.'/../supervisor/sidebar.php';
elseif ($role==='recruiter' && is_file(__DIR__.'/../recruiter/sidebar.php')) $sidebarPath=__DIR__.'/../recruiter/sidebar.php';
elseif ($role==='candidate' && is_file(__DIR__.'/../candidate/sidebar.php')) $sidebarPath=__DIR__.'/../candidate/sidebar.php';
elseif ($role==='assessor' && is_file(__DIR__.'/../assessor/sidebar.php')) $sidebarPath=__DIR__.'/../assessor/sidebar.php';
elseif ($role==='finance_officer' && is_file(__DIR__.'/../finance/sidebar.php')) $sidebarPath=__DIR__.'/../finance/sidebar.php';
if($sidebarPath){require $sidebarPath;} elseif($role==='candidate'){ ?><aside class="sidebar" id="sidebar"><div class="sidebar__header"><a href="<?=url('candidate/dashboard.php')?>" class="logo"><span class="logo__icon"><i class="fas fa-code"></i></span><span class="logo__text">Investhood <span class="logo__accent">IT</span></span></a></div><nav class="sidebar__nav"><div class="sidebar__section-label">Candidate</div><ul class="sidebar__menu"><li><a class="sidebar__link" href="<?=url('candidate/dashboard.php')?>"><i class="fas fa-th-large"></i> Dashboard</a></li><li><a class="sidebar__link active" href="<?=url('communications/announcements.php')?>"><i class="fas fa-bullhorn"></i> Announcements</a></li></ul></nav><div class="sidebar__footer"><a href="<?=url('auth/logout.php')?>" class="sidebar__logout"><i class="fas fa-sign-out-alt"></i> Sign Out</a></div></aside><div class="sidebar-overlay" id="sidebarOverlay"></div><?php } ?><main class="ux-main"><header class="ux-navbar"><div class="ux-navbar__left"><a class="ux-navbar__menu" href="<?=url(role_dashboard($role) ?? 'index.php')?>" aria-label="Back to portal"><i class="fas fa-arrow-left"></i></a><div class="ux-navbar__heading"><span>Investhood IT Workspace</span><h1>Announcement Centre</h1></div></div><div class="ux-navbar__right"><span class="ux-navbar__user-copy"><strong><?=comm_e(trim(($user['first_name']??'').' '.($user['last_name']??'')) ?: $roleLabel)?></strong><span><?=comm_e($roleLabel)?></span></span><?php if($role==='programme_manager'):?><a class="ux-navbar__icon-button" href="<?=url('programme/announcements.php')?>" title="Manage announcements"><i class="fas fa-pen-to-square"></i></a><?php endif;?></div></header><div class="comm-audience"><section class="comm-audience__hero"><span>Programme communications</span><h2>Announcements for you</h2><p>Important programme notices, deadlines, events and updates published by authorised programme staff. Only announcements matching your role and permitted programme or cohort scope are shown.</p></section><div class="comm-audience__list"><?php if(!$items):?><div class="comm-audience__empty"><i class="fas fa-bullhorn"></i><h3>No announcements available</h3><p>There are no published announcements matching your current audience and programme scope.</p></div><?php else:foreach($items as $a):?><article class="comm-audience__item"><div class="comm-audience__top"><div><h3><?=comm_e($a['title'])?></h3><div class="comm-audience__meta"><span class="comm-audience__tag"><?=comm_e(ucwords(str_replace('_',' ',$a['category'])))?></span><span class="comm-audience__tag <?= $a['priority']==='urgent'?'comm-audience__tag--urgent':($a['priority']==='important'?'comm-audience__tag--important':'')?>"><?=comm_e(ucfirst($a['priority']))?> priority</span><?php if(!empty($a['programme_name'])):?><span class="comm-audience__tag"><?=comm_e($a['programme_name'])?></span><?php endif;?><?php if(!empty($a['cohort_name'])):?><span class="comm-audience__tag"><?=comm_e($a['cohort_name'])?></span><?php endif;?></div></div><small><?=comm_e(!empty($a['published_at'])?date('d M Y, H:i',strtotime($a['published_at'])):'Published')?></small></div><p><?=comm_e($a['message'])?></p><small>Published by <?=comm_e(trim(($a['first_name']??'').' '.($a['last_name']??'')) ?: 'Programme staff')?></small></article><?php endforeach;endif;?></div></div></main></div><?php if($role==='recruiter'):?><script src="<?=url('js/unified_portal.js')?>?v=1"></script><script src="<?=url('js/original_pm_sidebar.js')?>"></script><?php endif;?></body></html>
