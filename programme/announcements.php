<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('programme_manager');
require_once __DIR__ . '/_helpers.php';

$user = current_user();
$conn = Database::getConnection();
$managerId = (int)($user['id'] ?? $user['user_id'] ?? 0);
$currentPage = 'announcements';
$pageTitle = 'Communication Centre';
$flashes = render_flashes();
$errors = [];
$tableReady = pm_table_exists($conn, 'programme_announcements');
if (!$tableReady) $errors[] = 'The announcement database table is not installed yet. Run database/programme_communication_centre.sql in your Investhood platform database before publishing announcements.';
$editing = null;
$allowedRoles = ['all', 'candidate', 'supervisor', 'programme_officer', 'recruiter', 'assessor', 'finance_officer', 'information_officer', 'programme_manager'];
$allowedCategories = ['general', 'deadline', 'event', 'programme_update', 'assessment', 'interview', 'policy'];
$allowedPriorities = ['normal', 'important', 'urgent'];

if ($managerId <= 0) { http_response_code(403); exit('Invalid Programme Manager account.'); }

$programmes = [];
$stmt = $conn->prepare('SELECT id, name FROM programmes WHERE programme_manager_id = ? ORDER BY name');
if ($stmt) { $stmt->bind_param('i', $managerId); $stmt->execute(); $r = $stmt->get_result(); while ($row = $r->fetch_assoc()) $programmes[] = $row; $stmt->close(); }

$cohorts = [];
$stmt = $conn->prepare('SELECT c.id, c.name, c.programme_id, p.name AS programme_name FROM cohorts c INNER JOIN programmes p ON p.id=c.programme_id WHERE p.programme_manager_id=? ORDER BY p.name,c.name');
if ($stmt) { $stmt->bind_param('i', $managerId); $stmt->execute(); $r = $stmt->get_result(); while ($row = $r->fetch_assoc()) $cohorts[] = $row; $stmt->close(); }

function pm_announcement_owned(mysqli $conn, int $announcementId, int $managerId): ?array {
    $stmt = $conn->prepare('SELECT a.* FROM programme_announcements a LEFT JOIN programmes p ON p.id=a.programme_id WHERE a.id=? AND a.author_id=? AND (a.programme_id IS NULL OR p.programme_manager_id=?) LIMIT 1');
    if (!$stmt) return null;
    $stmt->bind_param('iii', $announcementId, $managerId, $managerId); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); $stmt->close(); return $row ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (!function_exists('validate_csrf') || !validate_csrf()) {
        $errors[] = 'Your session validation failed. Refresh the page and try again.';
    } else {
        $action = (string)($_POST['action'] ?? 'save');
        $id = (int)($_POST['announcement_id'] ?? 0);
        if ($action === 'archive' || $action === 'publish') {
            $owned = $id > 0 ? pm_announcement_owned($conn, $id, $managerId) : null;
            if (!$owned) { $errors[] = 'The announcement could not be found in your permitted scope.'; }
            else {
                $status = $action === 'archive' ? 'archived' : 'published';
                $stmt = $conn->prepare("UPDATE programme_announcements SET status=?, published_at=CASE WHEN ?='published' THEN COALESCE(published_at,NOW()) ELSE published_at END, updated_at=NOW() WHERE id=? AND author_id=?");
                if ($stmt) { $stmt->bind_param('ssii', $status, $status, $id, $managerId); $stmt->execute(); $stmt->close(); set_flash('success', 'Announcement updated', $status === 'published' ? 'The announcement is now published.' : 'The announcement has been archived.'); safe_redirect('programme/announcements.php'); }
                $errors[] = 'The announcement could not be updated.';
            }
        } else {
            $title = trim((string)($_POST['title'] ?? ''));
            $message = trim((string)($_POST['message'] ?? ''));
            $category = (string)($_POST['category'] ?? 'general');
            $priority = (string)($_POST['priority'] ?? 'normal');
            $targetRole = (string)($_POST['target_role'] ?? 'all');
            $programmeId = (int)($_POST['programme_id'] ?? 0);
            $cohortId = (int)($_POST['cohort_id'] ?? 0);
            $status = (string)($_POST['status'] ?? 'draft');
            if ($title === '' || mb_strlen($title) > 180) $errors[] = 'Enter a title of no more than 180 characters.';
            if ($message === '' || mb_strlen($message) > 10000) $errors[] = 'Enter a message of no more than 10,000 characters.';
            if (!in_array($category, $allowedCategories, true)) $errors[] = 'Choose a valid announcement category.';
            if (!in_array($priority, $allowedPriorities, true)) $errors[] = 'Choose a valid priority.';
            if (!in_array($targetRole, $allowedRoles, true)) $errors[] = 'Choose a valid audience.';
            if (!in_array($status, ['draft','published'], true)) $errors[] = 'Choose a valid publication status.';
            if ($programmeId > 0) {
                $stmt = $conn->prepare('SELECT id FROM programmes WHERE id=? AND programme_manager_id=? LIMIT 1');
                $stmt->bind_param('ii', $programmeId, $managerId); $stmt->execute(); $validProgramme = (bool)$stmt->get_result()->fetch_assoc(); $stmt->close();
                if (!$validProgramme) $errors[] = 'You can only target programmes assigned to your account.';
            }
            if ($cohortId > 0) {
                $stmt = $conn->prepare('SELECT c.id FROM cohorts c INNER JOIN programmes p ON p.id=c.programme_id WHERE c.id=? AND p.programme_manager_id=? AND (?=0 OR p.id=?) LIMIT 1');
                $stmt->bind_param('iiii', $cohortId, $managerId, $programmeId, $programmeId); $stmt->execute(); $validCohort = (bool)$stmt->get_result()->fetch_assoc(); $stmt->close();
                if (!$validCohort) $errors[] = 'Choose a cohort that belongs to one of your assigned programmes.';
            }
            if ($cohortId > 0 && $programmeId === 0) $errors[] = 'Select a programme before targeting a specific cohort.';
            $owned = $id > 0 ? pm_announcement_owned($conn, $id, $managerId) : null;
            if ($id > 0 && !$owned) $errors[] = 'You cannot edit this announcement.';
            if (!$errors) {
                $programmeValue = $programmeId > 0 ? $programmeId : null;
                $cohortValue = $cohortId > 0 ? $cohortId : null;
                $publishedAt = $status === 'published' ? (($owned['published_at'] ?? '') ?: date('Y-m-d H:i:s')) : null;
                if ($id > 0) {
                    $stmt = $conn->prepare('UPDATE programme_announcements SET title=?, message=?, category=?, priority=?, target_role=?, programme_id=?, cohort_id=?, status=?, published_at=?, updated_at=NOW() WHERE id=? AND author_id=?');
                    if ($stmt) { $stmt->bind_param('sssssiissii', $title, $message, $category, $priority, $targetRole, $programmeValue, $cohortValue, $status, $publishedAt, $id, $managerId); }
                } else {
                    $stmt = $conn->prepare('INSERT INTO programme_announcements (author_id,title,message,category,priority,target_role,programme_id,cohort_id,status,published_at) VALUES (?,?,?,?,?,?,?,?,?,?)');
                    if ($stmt) { $stmt->bind_param('isssssiiss', $managerId, $title, $message, $category, $priority, $targetRole, $programmeValue, $cohortValue, $status, $publishedAt); }
                }
                if ($stmt) {
                    if ($stmt->execute()) { $stmt->close(); set_flash('success', 'Announcement saved', $status === 'published' ? 'Your announcement has been published.' : 'Your announcement has been saved as a draft.'); safe_redirect('programme/announcements.php'); }
                    error_log('[Announcements] Save failed: '.$stmt->error); $stmt->close(); $errors[] = 'The announcement could not be saved. Please check the database migration and try again.';
                } else { $errors[] = 'The announcement could not be prepared for saving.'; }
            }
            $editing = ['id'=>$id,'title'=>$title,'message'=>$message,'category'=>$category,'priority'=>$priority,'target_role'=>$targetRole,'programme_id'=>$programmeId,'cohort_id'=>$cohortId,'status'=>$status];
        }
    }
}

if (!$editing && isset($_GET['edit'])) $editing = pm_announcement_owned($conn, (int)$_GET['edit'], $managerId);
if (!$editing) $editing = ['id'=>0,'title'=>'','message'=>'','category'=>'general','priority'=>'normal','target_role'=>'all','programme_id'=>0,'cohort_id'=>0,'status'=>'draft'];

$announcements = [];
$stmt = $conn->prepare("SELECT a.*, p.name AS programme_name, c.name AS cohort_name FROM programme_announcements a LEFT JOIN programmes p ON p.id=a.programme_id LEFT JOIN cohorts c ON c.id=a.cohort_id WHERE a.author_id=? AND (a.programme_id IS NULL OR p.programme_manager_id=?) ORDER BY FIELD(a.status,'draft','published','archived'), COALESCE(a.published_at,a.updated_at) DESC, a.id DESC");
if ($stmt) { $stmt->bind_param('ii',$managerId,$managerId); $stmt->execute(); $r=$stmt->get_result(); while($row=$r->fetch_assoc())$announcements[]=$row; $stmt->close(); }
$roleLabels=['all'=>'All relevant stakeholders','candidate'=>'Candidates','supervisor'=>'Supervisors','programme_officer'=>'Programme Officers','recruiter'=>'Recruiters','assessor'=>'Assessors','finance_officer'=>'Finance Officers','information_officer'=>'Information Officers','programme_manager'=>'Programme Managers'];
function pm_announcement_label(string $value): string { return ucwords(str_replace('_',' ',$value)); }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($pageTitle)?> | Investhood IT</title>
<script>(function(){try{var t=localStorage.getItem('investhood-programme-manager-theme');document.documentElement.setAttribute('data-theme',t==='dark'?'dark':'light')}catch(e){document.documentElement.setAttribute('data-theme','light')}})();</script>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"><link rel="stylesheet" href="<?=url('css/styles.css')?>"><link rel="stylesheet" href="<?=url('css/programme_manager_enhancements.css')?>?v=20261021"><link rel="stylesheet" href="<?=url('css/original_pm_sidebar_compat.css')?>">
<style>
.pm-comm-page{padding:1.5rem;max-width:1500px;margin:auto}.pm-comm-hero{display:flex;justify-content:space-between;align-items:flex-start;gap:1.5rem;margin-bottom:1.5rem}.pm-comm-eyebrow{font-size:.75rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#2563eb}.pm-comm-hero h2{font-size:clamp(1.55rem,3vw,2.15rem);margin:.35rem 0}.pm-comm-hero p{max-width:720px;color:var(--text-light,#64748b);margin:0;line-height:1.6}.pm-comm-layout{display:grid;grid-template-columns:minmax(300px,.82fr) minmax(0,1.4fr);gap:1.25rem;align-items:start}.pm-comm-panel{background:var(--bg-white,#fff);border:1px solid var(--border,#e5e7eb);border-radius:18px;box-shadow:0 10px 30px rgba(15,23,42,.05);overflow:hidden;min-width:0}.pm-comm-panel__head{padding:1.2rem 1.35rem;border-bottom:1px solid var(--border,#e5e7eb)}.pm-comm-panel__head h3{margin:0;font-size:1rem}.pm-comm-panel__head p{margin:.35rem 0 0;color:var(--text-light,#64748b);font-size:.84rem;line-height:1.5}.pm-comm-form{padding:1.25rem;display:grid;gap:1rem}.pm-comm-field{display:grid;gap:.4rem;min-width:0}.pm-comm-field label{font-weight:700;font-size:.83rem}.pm-comm-field input,.pm-comm-field select,.pm-comm-field textarea{width:100%;min-width:0;border:1px solid var(--border,#cbd5e1);border-radius:10px;padding:.78rem .85rem;background:var(--bg-white,#fff);color:var(--text,#0f172a);font:inherit;box-sizing:border-box}.pm-comm-field textarea{min-height:150px;resize:vertical;line-height:1.6}.pm-comm-two{display:grid;grid-template-columns:1fr 1fr;gap:.8rem}.pm-comm-actions{display:flex;flex-wrap:wrap;gap:.6rem}.pm-comm-btn{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;border:1px solid transparent;border-radius:10px;padding:.75rem 1rem;font:inherit;font-size:.85rem;font-weight:800;text-decoration:none;cursor:pointer}.pm-comm-btn--primary{background:#1d4ed8;color:#fff}.pm-comm-btn--soft{background:var(--bg,#f1f5f9);border-color:var(--border,#e2e8f0);color:var(--text,#0f172a)}.pm-comm-alert{padding:.8rem 1rem;margin:0 0 .7rem;border-radius:10px;background:#fff1f2;color:#9f1239;border:1px solid #fecdd3}.pm-comm-list{padding:1rem;display:grid;gap:.85rem}.pm-comm-item{padding:1rem;border:1px solid var(--border,#e2e8f0);border-radius:14px;min-width:0}.pm-comm-item__top{display:flex;align-items:flex-start;justify-content:space-between;gap:.8rem}.pm-comm-item h4{margin:0;font-size:1rem;line-height:1.4;overflow-wrap:anywhere}.pm-comm-item p{white-space:pre-wrap;overflow-wrap:anywhere;line-height:1.6;color:var(--text-light,#64748b);margin:.65rem 0}.pm-comm-meta{display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.6rem}.pm-comm-tag{display:inline-flex;align-items:center;border-radius:999px;padding:.25rem .55rem;font-size:.7rem;font-weight:800;background:#eff6ff;color:#1d4ed8}.pm-comm-tag--urgent{background:#fee2e2;color:#991b1b}.pm-comm-tag--important{background:#fef3c7;color:#92400e}.pm-comm-tag--draft{background:#f1f5f9;color:#475569}.pm-comm-tag--archived{background:#e5e7eb;color:#374151}.pm-comm-item__actions{display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.75rem}.pm-comm-item__actions form{margin:0}.pm-comm-empty{text-align:center;padding:2.4rem 1rem;color:var(--text-light,#64748b)}.pm-comm-empty i{font-size:1.6rem;margin-bottom:.65rem;color:#94a3b8}html[data-theme="dark"] .pm-comm-panel{background:#1e293b;border-color:#334155}html[data-theme="dark"] .pm-comm-field input,html[data-theme="dark"] .pm-comm-field select,html[data-theme="dark"] .pm-comm-field textarea{background:#0f172a;color:#f1f5f9;border-color:#475569}html[data-theme="dark"] .pm-comm-tag--draft{background:#334155;color:#e2e8f0}@media(max-width:1000px){.pm-comm-layout{grid-template-columns:1fr}.pm-comm-hero{flex-direction:column}}@media(max-width:540px){.pm-comm-page{padding:1rem}.pm-comm-two{grid-template-columns:1fr}.pm-comm-item__top{flex-direction:column}}
</style></head><body class="dashboard-page"><div class="dashboard"><?php require __DIR__.'/sidebar.php'; ?><main class="dashboard__main"><?php require __DIR__.'/navbar.php'; ?><div class="pm-comm-page">
<?= $flashes ?>
<div class="pm-comm-hero"><div><span class="pm-comm-eyebrow">Programme communications</span><h2>Communication &amp; Announcement Centre</h2><p>Publish programme updates, deadlines, event notices and important instructions to the right audience. Programme and cohort targeting is restricted to your assigned programmes.</p></div><a class="pm-comm-btn pm-comm-btn--soft" href="<?=url('communications/announcements.php')?>"><i class="fas fa-arrow-up-right-from-square"></i> View audience centre</a></div>
<?php foreach($errors as $error): ?><div class="pm-comm-alert"><i class="fas fa-circle-exclamation"></i> <?=e($error)?></div><?php endforeach; ?>
<div class="pm-comm-layout"><section class="pm-comm-panel"><div class="pm-comm-panel__head"><h3><?=!empty($editing['id'])?'Edit announcement':'Create announcement'?></h3><p>Draft an update or publish it immediately.</p></div>
<form class="pm-comm-form" method="post" action="<?=url('programme/announcements.php')?>"><?php if(function_exists('csrf_token')):?><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><?php endif; ?><input type="hidden" name="action" value="save"><input type="hidden" name="announcement_id" value="<?= (int)$editing['id'] ?>">
<div class="pm-comm-field"><label for="announcementTitle">Announcement title</label><input id="announcementTitle" name="title" maxlength="180" required value="<?=e((string)$editing['title'])?>" placeholder="e.g. Assessment schedule update"></div>
<div class="pm-comm-field"><label for="announcementMessage">Message</label><textarea id="announcementMessage" name="message" maxlength="10000" required placeholder="Write the update and any action required from recipients..."><?=e((string)$editing['message'])?></textarea></div>
<div class="pm-comm-two"><div class="pm-comm-field"><label for="announcementCategory">Category</label><select id="announcementCategory" name="category"><?php foreach($allowedCategories as $v):?><option value="<?=e($v)?>" <?=$editing['category']===$v?'selected':''?>><?=e(pm_announcement_label($v))?></option><?php endforeach;?></select></div><div class="pm-comm-field"><label for="announcementPriority">Priority</label><select id="announcementPriority" name="priority"><?php foreach($allowedPriorities as $v):?><option value="<?=e($v)?>" <?=$editing['priority']===$v?'selected':''?>><?=e(ucfirst($v))?></option><?php endforeach;?></select></div></div>
<div class="pm-comm-field"><label for="announcementAudience">Audience</label><select id="announcementAudience" name="target_role"><?php foreach($roleLabels as $v=>$label):?><option value="<?=e($v)?>" <?=$editing['target_role']===$v?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></div>
<div class="pm-comm-field"><label for="announcementProgramme">Programme (optional)</label><select id="announcementProgramme" name="programme_id"><option value="0">All programmes / organisation-wide</option><?php foreach($programmes as $pr):?><option value="<?=(int)$pr['id']?>" <?=(int)$editing['programme_id']===(int)$pr['id']?'selected':''?>><?=e($pr['name'])?></option><?php endforeach;?></select></div>
<div class="pm-comm-field"><label for="announcementCohort">Cohort (optional)</label><select id="announcementCohort" name="cohort_id"><option value="0">All cohorts in selected programme</option><?php foreach($cohorts as $co):?><option value="<?=(int)$co['id']?>" data-programme="<?=(int)$co['programme_id']?>" <?=(int)$editing['cohort_id']===(int)$co['id']?'selected':''?>><?=e($co['programme_name'].' — '.$co['name'])?></option><?php endforeach;?></select></div>
<div class="pm-comm-field"><label for="announcementStatus">Save as</label><select id="announcementStatus" name="status"><option value="draft" <?=$editing['status']==='draft'?'selected':''?>>Draft (not visible to recipients)</option><option value="published" <?=$editing['status']==='published'?'selected':''?>>Published (visible to selected audience)</option></select></div>
<div class="pm-comm-actions"><button class="pm-comm-btn pm-comm-btn--primary" type="submit" <?=$tableReady?'':'disabled title="Run the database migration first"'?>><i class="fas fa-paper-plane"></i> <?=!empty($editing['id'])?'Save changes':'Save announcement'?></button><?php if(!empty($editing['id'])):?><a class="pm-comm-btn pm-comm-btn--soft" href="<?=url('programme/announcements.php')?>">Cancel edit</a><?php endif;?></div></form></section>
<section class="pm-comm-panel"><div class="pm-comm-panel__head"><h3>Your announcements <span class="pm-comm-tag"><?=count($announcements)?></span></h3><p>Manage drafts, published updates and archived announcements.</p></div><div class="pm-comm-list"><?php if(!$announcements):?><div class="pm-comm-empty"><i class="fas fa-bullhorn"></i><h3>No announcements yet</h3><span>Create your first programme update using the form.</span></div><?php else:foreach($announcements as $a):?><article class="pm-comm-item"><div class="pm-comm-item__top"><h4><?=e($a['title'])?></h4><span class="pm-comm-tag <?=$a['priority']==='urgent'?'pm-comm-tag--urgent':($a['priority']==='important'?'pm-comm-tag--important':'')?>"><?=e(ucfirst($a['priority']))?></span></div><div class="pm-comm-meta"><span class="pm-comm-tag <?=e('pm-comm-tag--'.$a['status'])?>"><?=e(ucfirst($a['status']))?></span><span class="pm-comm-tag"><?=e($roleLabels[$a['target_role']]??pm_announcement_label($a['target_role']))?></span><span class="pm-comm-tag"><?=e(pm_announcement_label($a['category']))?></span></div><p><?=e(mb_strlen($a['message'])>360?mb_substr($a['message'],0,360).'…':$a['message'])?></p><small><?=e($a['programme_name']??'Organisation-wide')?><?=!empty($a['cohort_name'])?' · '.e($a['cohort_name']):''?> · Updated <?=e(date('d M Y, H:i',strtotime($a['updated_at'])))?></small><div class="pm-comm-item__actions"><a class="pm-comm-btn pm-comm-btn--soft" href="<?=url('programme/announcements.php?edit='.(int)$a['id'])?>"><i class="fas fa-pen"></i> Edit</a><?php if($a['status']!=='published'):?><form method="post"><?php if(function_exists('csrf_token')):?><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><?php endif;?><input type="hidden" name="action" value="publish"><input type="hidden" name="announcement_id" value="<?=(int)$a['id']?>"><button class="pm-comm-btn pm-comm-btn--primary" type="submit"><i class="fas fa-bullhorn"></i> Publish</button></form><?php endif;?><?php if($a['status']!=='archived'):?><form method="post" onsubmit="return confirm('Archive this announcement?')"><?php if(function_exists('csrf_token')):?><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><?php endif;?><input type="hidden" name="action" value="archive"><input type="hidden" name="announcement_id" value="<?=(int)$a['id']?>"><button class="pm-comm-btn pm-comm-btn--soft" type="submit"><i class="fas fa-box-archive"></i> Archive</button></form><?php endif;?></div></article><?php endforeach;endif;?></div></section></div></div></main></div>
<script src="<?=url('js/programme_manager_enhancements.js')?>?v=20261021"></script><script src="<?=url('js/original_pm_sidebar.js')?>?v=20261021"></script><script>
(function(){const programme=document.getElementById('announcementProgramme');const cohort=document.getElementById('announcementCohort');if(!programme||!cohort)return;function filter(){const pid=programme.value;Array.from(cohort.options).forEach(function(o){if(!o.value)return;const show=!pid||pid==='0'||o.dataset.programme===pid;o.hidden=!show;if(!show&&o.selected)cohort.value='0';});}programme.addEventListener('change',filter);filter();})();
</script></body></html>
