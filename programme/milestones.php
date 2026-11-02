<?php
/**
 * Programme Manager - Milestone & Deadline Tracking
 * Milestones are scoped to programmes assigned to the authenticated manager.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('programme_manager');
require_once __DIR__ . '/_helpers.php';

$user = current_user();
$conn = Database::getConnection();
$managerId = (int)($user['id'] ?? $user['user_id'] ?? 0);
if ($managerId <= 0) { http_response_code(403); exit('Invalid Programme Manager account.'); }
$currentPage = 'milestones';
$pageTitle = 'Milestones & Deadlines';
$flashes = render_flashes();
$errors = [];
$tableReady = pm_table_exists($conn, 'programme_milestones');
$statuses = ['planned' => 'Planned', 'in_progress' => 'In Progress', 'completed' => 'Completed', 'on_hold' => 'On Hold'];
$priorities = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];

$programmes = [];
$stmt = $conn->prepare('SELECT id, name FROM programmes WHERE programme_manager_id = ? ORDER BY name');
if ($stmt) { $stmt->bind_param('i', $managerId); $stmt->execute(); $programmes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close(); }
$cohorts = [];
$stmt = $conn->prepare('SELECT c.id, c.name, c.programme_id, p.name AS programme_name FROM cohorts c INNER JOIN programmes p ON p.id=c.programme_id WHERE p.programme_manager_id=? ORDER BY p.name, c.name');
if ($stmt) { $stmt->bind_param('i', $managerId); $stmt->execute(); $cohorts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close(); }

function pm_milestone_owned(mysqli $conn, int $milestoneId, int $managerId): ?array {
    $stmt = $conn->prepare('SELECT m.* FROM programme_milestones m INNER JOIN programmes p ON p.id=m.programme_id WHERE m.id=? AND p.programme_manager_id=? LIMIT 1');
    if (!$stmt) return null;
    $stmt->bind_param('ii', $milestoneId, $managerId); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
    return $row ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!function_exists('validate_csrf') || !validate_csrf()) {
        $errors[] = 'Your session validation failed. Refresh the page and try again.';
    } elseif (!$tableReady) {
        $errors[] = 'The milestone database table is not installed. Import database/programme_milestones.sql first.';
    } else {
        $action = (string)($_POST['action'] ?? 'save');
        $id = (int)($_POST['milestone_id'] ?? 0);
        if ($action === 'delete') {
            if (!$id || !pm_milestone_owned($conn, $id, $managerId)) {
                $errors[] = 'The milestone was not found in your permitted programme scope.';
            } else {
                $stmt = $conn->prepare('DELETE m FROM programme_milestones m INNER JOIN programmes p ON p.id=m.programme_id WHERE m.id=? AND p.programme_manager_id=?');
                if ($stmt) { $stmt->bind_param('ii', $id, $managerId); $stmt->execute(); $stmt->close(); set_flash('success', 'Milestone deleted', 'The milestone was removed successfully.'); safe_redirect('programme/milestones.php'); }
                $errors[] = 'The milestone could not be deleted.';
            }
        } else {
            $title = trim((string)($_POST['title'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $programmeId = (int)($_POST['programme_id'] ?? 0);
            $cohortId = (int)($_POST['cohort_id'] ?? 0);
            $dueDate = trim((string)($_POST['due_date'] ?? ''));
            $status = (string)($_POST['status'] ?? 'planned');
            $priority = (string)($_POST['priority'] ?? 'normal');
            $progress = filter_var($_POST['progress'] ?? 0, FILTER_VALIDATE_INT);
            if ($title === '' || mb_strlen($title) > 180) $errors[] = 'Enter a milestone title (maximum 180 characters).';
            if (mb_strlen($description) > 5000) $errors[] = 'The description must be 5,000 characters or fewer.';
            if ($programmeId <= 0) $errors[] = 'Select a programme.';
            if (!isset($statuses[$status])) $errors[] = 'Choose a valid milestone status.';
            if (!isset($priorities[$priority])) $errors[] = 'Choose a valid priority.';
            if ($progress === false || $progress < 0 || $progress > 100) $errors[] = 'Progress must be a number from 0 to 100.';
            $dateObj = DateTime::createFromFormat('!Y-m-d', $dueDate);
            if (!$dateObj || $dateObj->format('Y-m-d') !== $dueDate) $errors[] = 'Choose a valid due date.';
            $stmt = $conn->prepare('SELECT id FROM programmes WHERE id=? AND programme_manager_id=? LIMIT 1');
            $validProgramme = false;
            if ($stmt) { $stmt->bind_param('ii', $programmeId, $managerId); $stmt->execute(); $validProgramme = (bool)$stmt->get_result()->fetch_assoc(); $stmt->close(); }
            if (!$validProgramme) $errors[] = 'You can only manage milestones for programmes assigned to your account.';
            if ($cohortId > 0) {
                $stmt = $conn->prepare('SELECT c.id FROM cohorts c INNER JOIN programmes p ON p.id=c.programme_id WHERE c.id=? AND c.programme_id=? AND p.programme_manager_id=? LIMIT 1');
                $validCohort = false;
                if ($stmt) { $stmt->bind_param('iii', $cohortId, $programmeId, $managerId); $stmt->execute(); $validCohort = (bool)$stmt->get_result()->fetch_assoc(); $stmt->close(); }
                if (!$validCohort) $errors[] = 'Select a cohort belonging to the chosen programme.';
            }
            $existing = $id > 0 ? pm_milestone_owned($conn, $id, $managerId) : null;
            if ($id > 0 && !$existing) $errors[] = 'You cannot edit this milestone.';
            if ($status === 'completed') $progress = 100;
            elseif ($progress === 100) $status = 'completed';
            if (!$errors) {
                $cohortValue = $cohortId > 0 ? $cohortId : null;
                if ($id > 0) {
                    $stmt = $conn->prepare('UPDATE programme_milestones SET programme_id=?, cohort_id=?, title=?, description=?, due_date=?, status=?, priority=?, progress=?, updated_at=NOW() WHERE id=?');
                    if ($stmt) { $stmt->bind_param('iisssssii', $programmeId, $cohortValue, $title, $description, $dueDate, $status, $priority, $progress, $id); }
                } else {
                    $stmt = $conn->prepare('INSERT INTO programme_milestones (programme_id, cohort_id, created_by, title, description, due_date, status, priority, progress) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                    if ($stmt) { $stmt->bind_param('iiisssssi', $programmeId, $cohortValue, $managerId, $title, $description, $dueDate, $status, $priority, $progress); }
                }
                if (isset($stmt) && $stmt) {
                    $ok = $stmt->execute(); $stmt->close();
                    if ($ok) { set_flash('success', $id > 0 ? 'Milestone updated' : 'Milestone created', 'Milestone details have been saved.'); safe_redirect('programme/milestones.php'); }
                }
                $errors[] = 'The milestone could not be saved. Please check the database migration and try again.';
            }
        }
    }
}

$editing = ['id'=>0,'title'=>'','description'=>'','programme_id'=>0,'cohort_id'=>0,'due_date'=>'','status'=>'planned','priority'=>'normal','progress'=>0];
$editId = (int)($_GET['edit'] ?? 0);
if ($editId > 0 && $tableReady) {
    $found = pm_milestone_owned($conn, $editId, $managerId);
    if ($found) $editing = $found;
}
$milestones = [];
$stats = ['total'=>0,'completed'=>0,'overdue'=>0,'upcoming'=>0,'in_progress'=>0];
if ($tableReady) {
    $stmt = $conn->prepare("SELECT m.*, p.name AS programme_name, c.name AS cohort_name, CASE WHEN m.status <> 'completed' AND m.due_date < CURDATE() THEN 1 ELSE 0 END AS is_overdue, CASE WHEN m.status <> 'completed' AND m.due_date >= CURDATE() AND m.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END AS is_upcoming FROM programme_milestones m INNER JOIN programmes p ON p.id=m.programme_id LEFT JOIN cohorts c ON c.id=m.cohort_id WHERE p.programme_manager_id=? ORDER BY (m.status='completed') ASC, m.due_date ASC, m.id DESC");
    if ($stmt) { $stmt->bind_param('i', $managerId); $stmt->execute(); $milestones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close(); }
    foreach ($milestones as $m) { $stats['total']++; if ($m['status']==='completed') $stats['completed']++; if ((int)$m['is_overdue']===1) $stats['overdue']++; if ((int)$m['is_upcoming']===1) $stats['upcoming']++; if ($m['status']==='in_progress') $stats['in_progress']++; }
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Milestones &amp; Deadlines | Investhood IT</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"><link rel="stylesheet" href="<?=url('css/styles.css')?>"><link rel="stylesheet" href="<?=url('css/programme_manager_enhancements.css')?>?v=20261025"><link rel="stylesheet" href="<?=url('css/programme_milestones.css')?>?v=20261025"></head>
<body class="dashboard-page"><div class="dashboard"><?php require __DIR__.'/sidebar.php'; ?><main class="dashboard__main"><?php require __DIR__.'/navbar.php'; ?><div class="pm-milestones-page">
<?=$flashes?>
<section class="pm-milestones-hero"><div><span class="pm-milestones-eyebrow">Programme delivery</span><h2>Milestones &amp; Deadlines</h2><p>Plan key activities, track completion, and identify approaching or overdue deadlines across your assigned programmes and cohorts.</p></div><a class="pm-milestone-button pm-milestone-button--primary" href="#milestoneForm"><i class="fas fa-plus"></i> Add milestone</a></section>
<?php foreach($errors as $error):?><div class="pm-milestone-alert"><i class="fas fa-circle-exclamation"></i> <?=e($error)?></div><?php endforeach;?>
<?php if(!$tableReady):?><div class="pm-milestone-alert"><strong>Database setup required:</strong> Import <code>database/programme_milestones.sql</code> into the application's active database before using this feature.</div><?php endif;?>
<section class="pm-milestone-stats"><article><span class="pm-milestone-stat-icon"><i class="fas fa-flag-checkered"></i></span><div><small>Total milestones</small><strong><?=number_format($stats['total'])?></strong></div></article><article><span class="pm-milestone-stat-icon is-green"><i class="fas fa-check"></i></span><div><small>Completed</small><strong><?=number_format($stats['completed'])?></strong></div></article><article><span class="pm-milestone-stat-icon is-red"><i class="fas fa-triangle-exclamation"></i></span><div><small>Overdue</small><strong><?=number_format($stats['overdue'])?></strong></div></article><article><span class="pm-milestone-stat-icon is-amber"><i class="fas fa-calendar-day"></i></span><div><small>Due in 7 days</small><strong><?=number_format($stats['upcoming'])?></strong></div></article></section>
<div class="pm-milestones-layout"><section class="pm-milestone-panel" id="milestoneForm"><div class="pm-milestone-panel__head"><div><h3><?=$editing['id']?'Edit milestone':'Create a milestone'?></h3><p>Define an activity and its deadline.</p></div></div>
<form method="post" class="pm-milestone-form" action="<?=url('programme/milestones.php'.($editing['id']?'?edit='.(int)$editing['id']:''))?>"><?=csrf_field()?><input type="hidden" name="action" value="save"><input type="hidden" name="milestone_id" value="<?=(int)$editing['id']?>">
<div class="pm-milestone-field"><label for="milestoneTitle">Milestone title</label><input id="milestoneTitle" name="title" maxlength="180" required value="<?=e((string)$editing['title'])?>" placeholder="e.g. Complete candidate screening"></div>
<div class="pm-milestone-field"><label for="milestoneDescription">Description</label><textarea id="milestoneDescription" name="description" maxlength="5000" rows="3" placeholder="Describe the activity or expected outcome"><?=e((string)($editing['description']??''))?></textarea></div>
<div class="pm-milestone-field"><label for="milestoneProgramme">Programme</label><select id="milestoneProgramme" name="programme_id" required><option value="">Select a programme</option><?php foreach($programmes as $p):?><option value="<?=(int)$p['id']?>" <?=((int)$editing['programme_id']===(int)$p['id'])?'selected':''?>><?=e($p['name'])?></option><?php endforeach;?></select></div>
<div class="pm-milestone-field"><label for="milestoneCohort">Cohort <span>(optional)</span></label><select id="milestoneCohort" name="cohort_id"><option value="0">Programme-wide milestone</option><?php foreach($cohorts as $c):?><option value="<?=(int)$c['id']?>" data-programme="<?=(int)$c['programme_id']?>" <?=((int)($editing['cohort_id']??0)===(int)$c['id'])?'selected':''?>><?=e($c['programme_name'].' — '.$c['name'])?></option><?php endforeach;?></select></div>
<div class="pm-milestone-form-grid"><div class="pm-milestone-field"><label for="milestoneDue">Due date</label><input id="milestoneDue" type="date" name="due_date" required value="<?=e((string)$editing['due_date'])?>"></div><div class="pm-milestone-field"><label for="milestonePriority">Priority</label><select id="milestonePriority" name="priority"><?php foreach($priorities as $key=>$label):?><option value="<?=e($key)?>" <?=$editing['priority']===$key?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></div></div>
<div class="pm-milestone-form-grid"><div class="pm-milestone-field"><label for="milestoneStatus">Status</label><select id="milestoneStatus" name="status"><?php foreach($statuses as $key=>$label):?><option value="<?=e($key)?>" <?=$editing['status']===$key?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></div><div class="pm-milestone-field"><label for="milestoneProgress">Progress (%)</label><input id="milestoneProgress" type="number" name="progress" min="0" max="100" step="1" required value="<?=(int)$editing['progress']?>"></div></div>
<div class="pm-milestone-form-actions"><button class="pm-milestone-button pm-milestone-button--primary" type="submit" <?=$tableReady?'':'disabled'?>> <i class="fas fa-floppy-disk"></i> <?=$editing['id']?'Save changes':'Create milestone'?></button><?php if($editing['id']):?><a class="pm-milestone-button pm-milestone-button--soft" href="<?=url('programme/milestones.php')?>">Cancel</a><?php endif;?></div></form></section>
<section class="pm-milestone-panel"><div class="pm-milestone-panel__head"><div><h3>Programme milestones</h3><p><?=count($milestones)?> milestone<?=count($milestones)===1?'':'s'?> in your assigned programmes</p></div></div><div class="pm-milestone-list"><?php if(!$tableReady):?><div class="pm-milestone-empty"><i class="fas fa-database"></i><strong>Waiting for database setup</strong><span>Import the milestone migration to begin tracking activities.</span></div><?php elseif(!$milestones):?><div class="pm-milestone-empty"><i class="fas fa-calendar-check"></i><strong>No milestones yet</strong><span>Create a milestone to start tracking programme deadlines.</span></div><?php else:foreach($milestones as $m):$overdue=(int)$m['is_overdue']===1;$upcoming=(int)$m['is_upcoming']===1;?><article class="pm-milestone-item <?=$overdue?'is-overdue':''?>"><div class="pm-milestone-item__top"><div><h4><?=e($m['title'])?></h4><p><?=e($m['programme_name'])?><?=!empty($m['cohort_name'])?' · '.e($m['cohort_name']):' · Programme-wide'?></p></div><span class="pm-milestone-status status-<?=e($m['status'])?>"><?=e($statuses[$m['status']]??ucfirst($m['status']))?></span></div><?php if(!empty($m['description'])):?><p class="pm-milestone-description"><?=e($m['description'])?></p><?php endif;?><div class="pm-milestone-meta"><span class="priority-<?=e($m['priority'])?>"><i class="fas fa-flag"></i> <?=e($priorities[$m['priority']]??ucfirst($m['priority']))?> priority</span><span class="<?=$overdue?'is-overdue-text':($upcoming?'is-upcoming-text':'')?>"><i class="fas fa-calendar"></i> Due <?=e(date('d M Y',strtotime($m['due_date'])))?></span><?php if($overdue):?><strong class="pm-milestone-alert-label">Overdue</strong><?php elseif($upcoming):?><strong class="pm-milestone-upcoming-label">Due soon</strong><?php endif;?></div><div class="pm-milestone-progress"><div class="pm-milestone-progress__label"><span>Progress</span><strong><?=(int)$m['progress']?>%</strong></div><div class="pm-milestone-progress__track"><span style="width:<?=max(0,min(100,(int)$m['progress']))?>%"></span></div></div><div class="pm-milestone-actions"><a class="pm-milestone-button pm-milestone-button--soft" href="<?=url('programme/milestones.php?edit='.(int)$m['id'])?>"><i class="fas fa-pen"></i> Edit</a><form method="post" onsubmit="return confirm('Delete this milestone? This action cannot be undone.');"><?=csrf_field()?><input type="hidden" name="action" value="delete"><input type="hidden" name="milestone_id" value="<?=(int)$m['id']?>"><button type="submit" class="pm-milestone-button pm-milestone-button--danger"><i class="fas fa-trash"></i> Delete</button></form></div></article><?php endforeach;endif;?></div></section></div>
</div></main></div><script src="<?=url('js/programme_manager_enhancements.js')?>?v=20261025"></script><script src="<?=url('js/original_pm_sidebar.js')?>?v=20261025"></script><script>(function(){var p=document.getElementById('milestoneProgramme'),c=document.getElementById('milestoneCohort');if(!p||!c)return;function filter(){Array.prototype.forEach.call(c.options,function(o){if(!o.value)return;var visible=p.value!==''&&o.getAttribute('data-programme')===p.value;o.hidden=!visible;if(!visible&&o.selected)c.value='0';});}p.addEventListener('change',filter);filter();})();</script></body></html>
