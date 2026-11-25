<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
require_role('supervisor');
require_once __DIR__.'/_helpers.php';
$user=current_user();$sid=(int)($user['id']??$user['user_id']??0);
if($sid<1){http_response_code(403);exit('Invalid Supervisor account.');}
$currentPage='development_plans';$pageTitle='Individual Candidate Development Plans';$error='';
function idp_txt(string $k,int $n=5000):string{$v=trim((string)($_POST[$k]??''));return function_exists('mb_substr')?mb_substr($v,0,$n):substr($v,0,$n);}
function idp_dt(string $k):?string{$v=(string)($_POST[$k]??'');return preg_match('/^\d{4}-\d{2}-\d{2}$/',$v)?$v:null;}
function idp_redirect(int $id=0):never{header('Location: '.url('supervisor/development_plans.php'.($id?'?plan_id='.$id:'')));exit;}
function idp_owned(int $id,int $sid):bool{$s=Database::prepare('SELECT id FROM supervisor_candidate_development_plans WHERE id=? AND supervisor_id=?','ii',[$id,$sid]);$ok=(bool)$s->get_result()->fetch_assoc();$s->close();return $ok;}
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!sv_verify_csrf((string)($_POST['csrf_token']??'')))$error='Your session token is invalid. Refresh and try again.';
 else{$a=(string)($_POST['action']??'');$pid=(int)($_POST['plan_id']??0);
  if($a==='create_plan'){
   $cid=(int)($_POST['cohort_id']??0);$uid=(int)($_POST['candidate_id']??0);$title=idp_txt('title',190);$start=idp_dt('start_date');$due=idp_dt('target_date');
   $m=Database::prepare("SELECT cp.user_id FROM cohort_participants cp JOIN cohorts c ON c.id=cp.cohort_id WHERE cp.cohort_id=? AND cp.user_id=? AND cp.status<>'withdrawn' AND c.supervisor_id=? LIMIT 1",'iii',[$cid,$uid,$sid]);$ok=(bool)$m->get_result()->fetch_assoc();$m->close();
   if(!$ok)$error='Select an active candidate from one of your assigned cohorts.';
   elseif($title===''||!$start||!$due||$due<$start)$error='Enter a plan title and valid start and target dates.';
   else{$s=Database::prepare('INSERT INTO supervisor_candidate_development_plans(cohort_id,candidate_id,supervisor_id,title,baseline_assessment,target_outcome,start_date,target_date,status) VALUES(?,?,?,?,?,?,?,?,?)','iiissssss',[$cid,$uid,$sid,$title,idp_txt('baseline_assessment'),idp_txt('target_outcome'),$start,$due,(string)($_POST['status']??'active')]);$new=(int)$s->insert_id;$s->close();idp_redirect($new);}
  }elseif($a==='update_plan'&&idp_owned($pid,$sid)){
   $title=idp_txt('title',190);$start=idp_dt('start_date');$due=idp_dt('target_date');$status=(string)($_POST['status']??'active');
   if($title===''||!$start||!$due||$due<$start||!in_array($status,['draft','active','on_hold','completed','cancelled'],true))$error='Check plan title, dates and status.';
   else{$s=Database::prepare('UPDATE supervisor_candidate_development_plans SET title=?,baseline_assessment=?,target_outcome=?,start_date=?,target_date=?,status=? WHERE id=? AND supervisor_id=?','ssssssii',[$title,idp_txt('baseline_assessment'),idp_txt('target_outcome'),$start,$due,$status,$pid,$sid]);$s->close();idp_redirect($pid);}
  }elseif($a==='add_goal'&&idp_owned($pid,$sid)){
   $skill=idp_txt('skill_area',150);$obj=idp_txt('objective');$due=idp_dt('goal_date');$priority=(string)($_POST['priority']??'medium');
   if($skill===''||$obj===''||!$due||!in_array($priority,['low','medium','high','critical'],true))$error='Enter skill area, objective, priority and target date.';
   else{$s=Database::prepare('INSERT INTO supervisor_development_goals(plan_id,skill_area,gap_description,objective,priority,target_date) VALUES(?,?,?,?,?,?)','isssss',[$pid,$skill,idp_txt('gap_description'),$obj,$priority,$due]);$s->close();idp_redirect($pid);}
  }elseif($a==='update_goal'&&idp_owned($pid,$sid)){
   $gid=(int)($_POST['goal_id']??0);$status=(string)($_POST['goal_status']??'not_started');$pct=(int)($_POST['progress_percent']??0);
   if(!in_array($status,['not_started','in_progress','achieved','deferred'],true)||$pct<0||$pct>100)$error='Invalid objective progress.';
   else{$s=Database::prepare('UPDATE supervisor_development_goals SET status=?,progress_percent=? WHERE id=? AND plan_id=?','siii',[$status,$pct,$gid,$pid]);$s->close();idp_redirect($pid);}
  }elseif($a==='add_activity'&&idp_owned($pid,$sid)){
   $title=idp_txt('activity_title',190);$due=idp_dt('activity_due');$gid=(int)($_POST['goal_id']??0);$gid=$gid?:null;$assignee=(int)($_POST['assigned_to']??0);$assignee=$assignee?:null;
   if($title===''||!$due)$error='Enter activity title and due date.';
   else{$s=Database::prepare('INSERT INTO supervisor_development_activities(plan_id,goal_id,title,details,assigned_to,due_date) VALUES(?,?,?,?,?,?)','iissis',[$pid,$gid,$title,idp_txt('activity_details'),$assignee,$due]);$s->close();idp_redirect($pid);}
  }elseif($a==='update_activity'&&idp_owned($pid,$sid)){
   $aid=(int)($_POST['activity_id']??0);$status=(string)($_POST['activity_status']??'pending');
   if(!in_array($status,['pending','in_progress','completed','cancelled'],true))$error='Invalid activity status.';
   else{$s=Database::prepare('UPDATE supervisor_development_activities SET status=?,completion_notes=? WHERE id=? AND plan_id=?','ssii',[$status,idp_txt('completion_notes'),$aid,$pid]);$s->close();idp_redirect($pid);}
  }elseif($a==='add_review'&&idp_owned($pid,$sid)){
   $rating=(string)($_POST['progress_rating']??'on_track');$date=idp_dt('review_date')??date('Y-m-d');
   if(!in_array($rating,['exceeding','on_track','needs_attention','at_risk'],true))$error='Invalid progress rating.';
   else{$s=Database::prepare('INSERT INTO supervisor_development_reviews(plan_id,reviewed_by,review_date,progress_rating,review_notes,next_steps) VALUES(?,?,?,?,?,?)','iissss',[$pid,$sid,$date,$rating,idp_txt('review_notes'),idp_txt('next_steps')]);$s->close();idp_redirect($pid);}
  }else{$error='The requested plan action is unavailable or the plan was not found.';}
 }
}
$cohorts=[];$s=Database::prepare('SELECT c.id,c.name,p.name programme_name FROM cohorts c JOIN programmes p ON p.id=c.programme_id WHERE c.supervisor_id=? ORDER BY c.start_date DESC','i',[$sid]);$r=$s->get_result();while($r&&$x=$r->fetch_assoc())$cohorts[(int)$x['id']]=$x;$s->close();
$candidates=[];foreach($cohorts as $cid=>$c){$s=Database::prepare("SELECT u.id,u.first_name,u.last_name,u.email FROM cohort_participants cp JOIN users u ON u.id=cp.user_id WHERE cp.cohort_id=? AND cp.status<>'withdrawn' ORDER BY u.first_name,u.last_name",'i',[$cid]);$r=$s->get_result();while($r&&$x=$r->fetch_assoc())$candidates[$cid][]=$x;$s->close();}
$filter=(int)($_GET['cohort_id']??0);if($filter&&!isset($cohorts[$filter]))$filter=0;
$sql='SELECT d.*,u.first_name,u.last_name,c.name cohort_name,(SELECT COUNT(*) FROM supervisor_development_goals g WHERE g.plan_id=d.id) goal_count,(SELECT ROUND(AVG(g.progress_percent)) FROM supervisor_development_goals g WHERE g.plan_id=d.id) avg_progress FROM supervisor_candidate_development_plans d JOIN users u ON u.id=d.candidate_id JOIN cohorts c ON c.id=d.cohort_id WHERE d.supervisor_id=?';$params=[$sid];$types='i';if($filter){$sql.=' AND d.cohort_id=?';$params[]=$filter;$types.='i';}$sql.=' ORDER BY d.updated_at DESC LIMIT 150';$s=Database::prepare($sql,$types,$params);$r=$s->get_result();$plans=[];while($r&&$x=$r->fetch_assoc())$plans[]=$x;$s->close();
$planId=(int)($_GET['plan_id']??0);$selected=null;foreach($plans as $p)if((int)$p['id']===$planId)$selected=$p;
$goals=$activities=$reviews=[];if($selected){
 $s=Database::prepare('SELECT * FROM supervisor_development_goals WHERE plan_id=? ORDER BY target_date,id','i',[$planId]);$r=$s->get_result();while($r&&$x=$r->fetch_assoc())$goals[]=$x;$s->close();
 $s=Database::prepare('SELECT a.*,g.skill_area FROM supervisor_development_activities a LEFT JOIN supervisor_development_goals g ON g.id=a.goal_id WHERE a.plan_id=? ORDER BY a.due_date','i',[$planId]);$r=$s->get_result();while($r&&$x=$r->fetch_assoc())$activities[]=$x;$s->close();
 $s=Database::prepare('SELECT r.*,u.first_name,u.last_name FROM supervisor_development_reviews r JOIN users u ON u.id=r.reviewed_by WHERE r.plan_id=? ORDER BY r.review_date DESC','i',[$planId]);$r=$s->get_result();while($r&&$x=$r->fetch_assoc())$reviews[]=$x;$s->close();
}
require __DIR__.'/_layout_start.php';
?>
<style>
.idp{display:grid;gap:16px;color:var(--sv-text,#111827)}.idp-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.idp-card,.idp-panel,.idp-block{background:var(--sv-card,#fff);border:1px solid var(--sv-border,#e5e7eb);border-radius:13px;padding:16px;color:var(--sv-text,#111827)}.idp-card strong{display:block;font-size:24px}.idp-muted{color:var(--sv-muted,#667085);font-size:13px}.idp-form{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.idp-field label{display:block;font-size:13px;font-weight:600;margin-bottom:5px}.idp-field input,.idp-field select,.idp-field textarea{width:100%;box-sizing:border-box;padding:9px 10px;border:1px solid var(--sv-border,#d0d5dd);border-radius:8px;background:var(--sv-input-bg,var(--sv-card,#fff));color:var(--sv-text,#111827)}.idp-field textarea{min-height:68px}.idp-btn{border:0;border-radius:8px;padding:9px 13px;background:#1d4ed8;color:white;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block}.idp-alt{background:transparent;color:var(--sv-text,#111827);border:1px solid var(--sv-border,#d0d5dd)}.idp-table{width:100%;border-collapse:collapse;min-width:700px}.idp-table th,.idp-table td{text-align:left;padding:9px;border-bottom:1px solid var(--sv-border,#e5e7eb);font-size:13px;vertical-align:top}.idp-table th{color:var(--sv-muted,#667085)}.idp-scroll{overflow-x:auto}.idp-block{margin-top:12px}.idp-alert{padding:11px;border-radius:9px;background:#fff7ed;color:#9a3412}@media(max-width:900px){.idp-grid{grid-template-columns:repeat(2,1fr)}.idp-form{grid-template-columns:repeat(2,1fr)}}@media(max-width:600px){.idp-grid,.idp-form{grid-template-columns:1fr}}

/* Full-page dark-mode coverage for Individual Development Plans */
html[data-theme="dark"] .idp, body[data-theme="dark"] .idp,
html.dark-mode .idp, body.dark-mode .idp, body.dark .idp, .dark-mode .idp,
[data-bs-theme="dark"] .idp { color:var(--sv-text,var(--bs-body-color,#e5e7eb)) !important; }

html[data-theme="dark"] .idp .idp-card, html[data-theme="dark"] .idp .idp-panel, html[data-theme="dark"] .idp .idp-block,
body[data-theme="dark"] .idp .idp-card, body[data-theme="dark"] .idp .idp-panel, body[data-theme="dark"] .idp .idp-block,
html.dark-mode .idp .idp-card, html.dark-mode .idp .idp-panel, html.dark-mode .idp .idp-block,
body.dark-mode .idp .idp-card, body.dark-mode .idp .idp-panel, body.dark-mode .idp .idp-block,
body.dark .idp .idp-card, body.dark .idp .idp-panel, body.dark .idp .idp-block,
.dark-mode .idp .idp-card, .dark-mode .idp .idp-panel, .dark-mode .idp .idp-block,
[data-bs-theme="dark"] .idp .idp-card, [data-bs-theme="dark"] .idp .idp-panel, [data-bs-theme="dark"] .idp .idp-block {
 background:var(--sv-card,var(--bs-tertiary-bg,#1f2937)) !important;
 color:var(--sv-text,var(--bs-body-color,#e5e7eb)) !important;
 border-color:var(--sv-border,var(--bs-border-color,#374151)) !important;
 box-shadow:none !important;
}
html[data-theme="dark"] .idp h2, html[data-theme="dark"] .idp h3, html[data-theme="dark"] .idp h4,
html[data-theme="dark"] .idp p, html[data-theme="dark"] .idp strong, html[data-theme="dark"] .idp label,
html[data-theme="dark"] .idp td, body[data-theme="dark"] .idp h2, body[data-theme="dark"] .idp h3,
body[data-theme="dark"] .idp h4, body[data-theme="dark"] .idp p, body[data-theme="dark"] .idp strong,
body[data-theme="dark"] .idp label, body[data-theme="dark"] .idp td,
html.dark-mode .idp h2, html.dark-mode .idp h3, html.dark-mode .idp h4, html.dark-mode .idp p,
html.dark-mode .idp strong, html.dark-mode .idp label, html.dark-mode .idp td,
body.dark-mode .idp h2, body.dark-mode .idp h3, body.dark-mode .idp h4, body.dark-mode .idp p,
body.dark-mode .idp strong, body.dark-mode .idp label, body.dark-mode .idp td,
body.dark .idp h2, body.dark .idp h3, body.dark .idp h4, body.dark .idp p, body.dark .idp strong,
body.dark .idp label, body.dark .idp td, .dark-mode .idp h2, .dark-mode .idp h3, .dark-mode .idp h4,
.dark-mode .idp p, .dark-mode .idp strong, .dark-mode .idp label, .dark-mode .idp td,
[data-bs-theme="dark"] .idp h2, [data-bs-theme="dark"] .idp h3, [data-bs-theme="dark"] .idp h4,
[data-bs-theme="dark"] .idp p, [data-bs-theme="dark"] .idp strong, [data-bs-theme="dark"] .idp label,
[data-bs-theme="dark"] .idp td { color:var(--sv-text,var(--bs-body-color,#e5e7eb)) !important; }

html[data-theme="dark"] .idp input, html[data-theme="dark"] .idp select, html[data-theme="dark"] .idp textarea,
body[data-theme="dark"] .idp input, body[data-theme="dark"] .idp select, body[data-theme="dark"] .idp textarea,
html.dark-mode .idp input, html.dark-mode .idp select, html.dark-mode .idp textarea,
body.dark-mode .idp input, body.dark-mode .idp select, body.dark-mode .idp textarea,
body.dark .idp input, body.dark .idp select, body.dark .idp textarea,
.dark-mode .idp input, .dark-mode .idp select, .dark-mode .idp textarea,
[data-bs-theme="dark"] .idp input, [data-bs-theme="dark"] .idp select, [data-bs-theme="dark"] .idp textarea {
 background:var(--sv-input-bg,#111827) !important; color:var(--sv-text,#f3f4f6) !important;
 border-color:var(--sv-border,#4b5563) !important; color-scheme:dark;
}
html[data-theme="dark"] .idp input::placeholder, html[data-theme="dark"] .idp textarea::placeholder,
body[data-theme="dark"] .idp input::placeholder, body[data-theme="dark"] .idp textarea::placeholder,
html.dark-mode .idp input::placeholder, html.dark-mode .idp textarea::placeholder,
body.dark-mode .idp input::placeholder, body.dark-mode .idp textarea::placeholder,
body.dark .idp input::placeholder, body.dark .idp textarea::placeholder,
.dark-mode .idp input::placeholder, .dark-mode .idp textarea::placeholder,
[data-bs-theme="dark"] .idp input::placeholder, [data-bs-theme="dark"] .idp textarea::placeholder {
 color:#9ca3af !important; opacity:1;
}
html[data-theme="dark"] .idp .idp-table th, html[data-theme="dark"] .idp .idp-table td,
body[data-theme="dark"] .idp .idp-table th, body[data-theme="dark"] .idp .idp-table td,
html.dark-mode .idp .idp-table th, html.dark-mode .idp .idp-table td,
body.dark-mode .idp .idp-table th, body.dark-mode .idp .idp-table td,
body.dark .idp .idp-table th, body.dark .idp .idp-table td,
.dark-mode .idp .idp-table th, .dark-mode .idp .idp-table td,
[data-bs-theme="dark"] .idp .idp-table th, [data-bs-theme="dark"] .idp .idp-table td {
 background:transparent !important; border-color:var(--sv-border,#374151) !important;
}
html[data-theme="dark"] .idp .idp-muted, body[data-theme="dark"] .idp .idp-muted,
html.dark-mode .idp .idp-muted, body.dark-mode .idp .idp-muted, body.dark .idp .idp-muted,
.dark-mode .idp .idp-muted, [data-bs-theme="dark"] .idp .idp-muted,
html[data-theme="dark"] .idp .idp-table th, body[data-theme="dark"] .idp .idp-table th,
html.dark-mode .idp .idp-table th, body.dark-mode .idp .idp-table th, body.dark .idp .idp-table th,
.dark-mode .idp .idp-table th, [data-bs-theme="dark"] .idp .idp-table th {
 color:var(--sv-muted,#aab4c3) !important;
}
html[data-theme="dark"] .idp .idp-alt, body[data-theme="dark"] .idp .idp-alt,
html.dark-mode .idp .idp-alt, body.dark-mode .idp .idp-alt, body.dark .idp .idp-alt,
.dark-mode .idp .idp-alt, [data-bs-theme="dark"] .idp .idp-alt {
 background:transparent !important; color:var(--sv-text,#e5e7eb) !important;
 border-color:var(--sv-border,#4b5563) !important;
}
html[data-theme="dark"] .idp .idp-alert, body[data-theme="dark"] .idp .idp-alert,
html.dark-mode .idp .idp-alert, body.dark-mode .idp .idp-alert, body.dark .idp .idp-alert,
.dark-mode .idp .idp-alert, [data-bs-theme="dark"] .idp .idp-alert {
 background:#422006 !important; color:#fed7aa !important; border:1px solid #92400e !important;
}
html[data-theme="dark"] .idp small, body[data-theme="dark"] .idp small, html.dark-mode .idp small,
body.dark-mode .idp small, body.dark .idp small, .dark-mode .idp small, [data-bs-theme="dark"] .idp small {
 color:#cbd5e1 !important;
}

</style>
<div class="sv-page-header"><div><span class="sv-eyebrow">Candidate Development</span><h2>Individual Candidate Development Plans</h2><p>Create personalised objectives, assign development activities and evaluate progress throughout the programme.</p></div><div class="sv-actions"><a class="sv-btn sv-btn--secondary" href="<?=e(url('supervisor/dashboard.php'))?>">Dashboard</a></div></div>
<div class="idp">
<?php if($error):?><div class="idp-alert"><?=e($error)?></div><?php endif;?>
<div class="idp-grid"><div class="idp-card"><strong><?=count($plans)?></strong><span class="idp-muted">Plans shown</span></div><div class="idp-card"><strong><?=count(array_filter($plans,fn($p)=>$p['status']==='active'))?></strong><span class="idp-muted">Active</span></div><div class="idp-card"><strong><?=count(array_filter($plans,fn($p)=>$p['status']==='completed'))?></strong><span class="idp-muted">Completed</span></div><div class="idp-card"><strong><?=count(array_filter($plans,fn($p)=>$p['status']==='on_hold'))?></strong><span class="idp-muted">On hold</span></div></div>
<section class="idp-panel"><h3>Create development plan</h3><form method="post"><input type="hidden" name="csrf_token" value="<?=e(sv_csrf_token())?>"><input type="hidden" name="action" value="create_plan"><div class="idp-form">
<div class="idp-field"><label>Cohort *</label><select name="cohort_id" id="idpCohort" required><option value="">Select cohort</option><?php foreach($cohorts as $cid=>$c):?><option value="<?=$cid?>" <?=$filter===$cid?'selected':''?>><?=e($c['name'].' · '.$c['programme_name'])?></option><?php endforeach;?></select></div>
<div class="idp-field"><label>Candidate *</label><select name="candidate_id" id="idpCandidate" required><option value="">Select candidate</option><?php foreach($candidates as $cid=>$list):foreach($list as $c):?><option data-cohort="<?=$cid?>" value="<?=(int)$c['id']?>"><?=e($c['first_name'].' '.$c['last_name'].' · '.$c['email'])?></option><?php endforeach;endforeach;?></select></div>
<div class="idp-field"><label>Plan title *</label><input name="title" required maxlength="190"></div><div class="idp-field"><label>Start date *</label><input type="date" name="start_date" required></div><div class="idp-field"><label>Target date *</label><input type="date" name="target_date" required></div><div class="idp-field"><label>Status</label><select name="status"><option value="active">Active</option><option value="draft">Draft</option><option value="on_hold">On hold</option></select></div>
<div class="idp-field" style="grid-column:1/-1"><label>Baseline assessment / skills gaps</label><textarea name="baseline_assessment"></textarea></div><div class="idp-field" style="grid-column:1/-1"><label>Target outcome</label><textarea name="target_outcome"></textarea></div></div><p><button class="idp-btn">Create plan</button></p></form></section>
<section class="idp-panel"><div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap"><h3>Development plans</h3><form method="get"><select name="cohort_id" onchange="this.form.submit()"><option value="0">All assigned cohorts</option><?php foreach($cohorts as $cid=>$c):?><option value="<?=$cid?>" <?=$filter===$cid?'selected':''?>><?=e($c['name'])?></option><?php endforeach;?></select></form></div><div class="idp-scroll"><table class="idp-table"><thead><tr><th>Candidate</th><th>Plan</th><th>Cohort</th><th>Target date</th><th>Objectives</th><th>Progress</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($plans as $p):?><tr><td><?=e($p['first_name'].' '.$p['last_name'])?></td><td><?=e($p['title'])?></td><td><?=e($p['cohort_name'])?></td><td><?=e(sv_date($p['target_date']))?></td><td><?=(int)$p['goal_count']?></td><td><?=(int)($p['avg_progress']??0)?>%</td><td><?=e(ucwords(str_replace('_',' ',$p['status'])))?></td><td><a class="idp-btn idp-alt" href="<?=e(url('supervisor/development_plans.php?plan_id='.(int)$p['id']))?>">Manage</a></td></tr><?php endforeach;?><?php if(!$plans):?><tr><td colspan="8">No development plans created yet.</td></tr><?php endif;?></tbody></table></div></section>
<?php if($selected):?><section class="idp-panel"><h3>Manage: <?=e($selected['title'])?> — <?=e($selected['first_name'].' '.$selected['last_name'])?></h3>
<form method="post"><input type="hidden" name="csrf_token" value="<?=e(sv_csrf_token())?>"><input type="hidden" name="action" value="update_plan"><input type="hidden" name="plan_id" value="<?=$planId?>"><div class="idp-form"><div class="idp-field"><label>Plan title</label><input name="title" value="<?=e($selected['title'])?>" required></div><div class="idp-field"><label>Start date</label><input type="date" name="start_date" value="<?=e($selected['start_date'])?>"></div><div class="idp-field"><label>Target date</label><input type="date" name="target_date" value="<?=e($selected['target_date'])?>"></div><div class="idp-field"><label>Status</label><select name="status"><?php foreach(['draft','active','on_hold','completed','cancelled'] as $v):?><option value="<?=$v?>" <?=$selected['status']===$v?'selected':''?>><?=e(ucwords(str_replace('_',' ',$v)))?></option><?php endforeach;?></select></div><div class="idp-field"><label>Baseline assessment</label><textarea name="baseline_assessment"><?=e($selected['baseline_assessment'])?></textarea></div><div class="idp-field"><label>Target outcome</label><textarea name="target_outcome"><?=e($selected['target_outcome'])?></textarea></div></div><p><button class="idp-btn">Update plan</button></p></form>
<div class="idp-block"><h3>Skills gaps & improvement objectives</h3><form method="post"><input type="hidden" name="csrf_token" value="<?=e(sv_csrf_token())?>"><input type="hidden" name="action" value="add_goal"><input type="hidden" name="plan_id" value="<?=$planId?>"><div class="idp-form"><div class="idp-field"><label>Skill area *</label><input name="skill_area" required></div><div class="idp-field"><label>Priority</label><select name="priority"><option>low</option><option selected>medium</option><option>high</option><option>critical</option></select></div><div class="idp-field"><label>Target date *</label><input type="date" name="goal_date" required></div><div class="idp-field"><label>Skills gap</label><textarea name="gap_description"></textarea></div><div class="idp-field"><label>Measurable objective *</label><textarea name="objective" required></textarea></div></div><p><button class="idp-btn">Add objective</button></p></form>
<div class="idp-scroll"><table class="idp-table"><thead><tr><th>Skill / gap</th><th>Objective</th><th>Priority</th><th>Target</th><th>Progress update</th></tr></thead><tbody><?php foreach($goals as $g):?><tr><td><?=e($g['skill_area'])?><br><small><?=e($g['gap_description'])?></small></td><td><?=e($g['objective'])?></td><td><?=e(ucfirst($g['priority']))?></td><td><?=e(sv_date($g['target_date']))?></td><td><form method="post"><input type="hidden" name="csrf_token" value="<?=e(sv_csrf_token())?>"><input type="hidden" name="action" value="update_goal"><input type="hidden" name="plan_id" value="<?=$planId?>"><input type="hidden" name="goal_id" value="<?=(int)$g['id']?>"><select name="goal_status"><?php foreach(['not_started','in_progress','achieved','deferred'] as $v):?><option value="<?=$v?>" <?=$g['status']===$v?'selected':''?>><?=e(ucwords(str_replace('_',' ',$v)))?></option><?php endforeach;?></select><input type="number" name="progress_percent" min="0" max="100" value="<?=(int)$g['progress_percent']?>" style="width:65px"><button class="idp-btn">Save</button></form></td></tr><?php endforeach;?><?php if(!$goals):?><tr><td colspan="5">No objectives recorded.</td></tr><?php endif;?></tbody></table></div></div>
<div class="idp-block"><h3>Development activities</h3><form method="post"><input type="hidden" name="csrf_token" value="<?=e(sv_csrf_token())?>"><input type="hidden" name="action" value="add_activity"><input type="hidden" name="plan_id" value="<?=$planId?>"><div class="idp-form"><div class="idp-field"><label>Activity *</label><input name="activity_title" required></div><div class="idp-field"><label>Related objective</label><select name="goal_id"><option value="0">General</option><?php foreach($goals as $g):?><option value="<?=(int)$g['id']?>"><?=e($g['skill_area'])?></option><?php endforeach;?></select></div><div class="idp-field"><label>Due date *</label><input type="date" name="activity_due" required></div><div class="idp-field"><label>Assigned user ID (optional)</label><input type="number" name="assigned_to" min="1"></div><div class="idp-field"><label>Instructions / resources</label><textarea name="activity_details"></textarea></div></div><p><button class="idp-btn">Assign activity</button></p></form>
<div class="idp-scroll"><table class="idp-table"><thead><tr><th>Activity</th><th>Objective</th><th>Due</th><th>Status / completion notes</th></tr></thead><tbody><?php foreach($activities as $a):?><tr><td><?=e($a['title'])?><br><small><?=e($a['details'])?></small></td><td><?=e($a['skill_area']??'General')?></td><td><?=e(sv_date($a['due_date']))?></td><td><form method="post"><input type="hidden" name="csrf_token" value="<?=e(sv_csrf_token())?>"><input type="hidden" name="action" value="update_activity"><input type="hidden" name="plan_id" value="<?=$planId?>"><input type="hidden" name="activity_id" value="<?=(int)$a['id']?>"><select name="activity_status"><?php foreach(['pending','in_progress','completed','cancelled'] as $v):?><option value="<?=$v?>" <?=$a['status']===$v?'selected':''?>><?=e(ucwords(str_replace('_',' ',$v)))?></option><?php endforeach;?></select><input name="completion_notes" placeholder="Completion notes" value="<?=e($a['completion_notes'])?>"><button class="idp-btn">Save</button></form></td></tr><?php endforeach;?><?php if(!$activities):?><tr><td colspan="4">No activities assigned.</td></tr><?php endif;?></tbody></table></div></div>
<div class="idp-block"><h3>Progress reviews</h3><form method="post"><input type="hidden" name="csrf_token" value="<?=e(sv_csrf_token())?>"><input type="hidden" name="action" value="add_review"><input type="hidden" name="plan_id" value="<?=$planId?>"><div class="idp-form"><div class="idp-field"><label>Review date</label><input type="date" name="review_date" value="<?=date('Y-m-d')?>"></div><div class="idp-field"><label>Progress rating</label><select name="progress_rating"><option value="exceeding">Exceeding</option><option value="on_track">On track</option><option value="needs_attention">Needs attention</option><option value="at_risk">At risk</option></select></div><div class="idp-field"><label>Review notes</label><textarea name="review_notes"></textarea></div><div class="idp-field"><label>Agreed next steps</label><textarea name="next_steps"></textarea></div></div><p><button class="idp-btn">Record review</button></p></form>
<div class="idp-scroll"><table class="idp-table"><thead><tr><th>Date</th><th>Rating</th><th>Review</th><th>Next steps</th><th>Supervisor</th></tr></thead><tbody><?php foreach($reviews as $rv):?><tr><td><?=e(sv_date($rv['review_date']))?></td><td><?=e(ucwords(str_replace('_',' ',$rv['progress_rating'])))?></td><td><?=e($rv['review_notes'])?></td><td><?=e($rv['next_steps'])?></td><td><?=e($rv['first_name'].' '.$rv['last_name'])?></td></tr><?php endforeach;?><?php if(!$reviews):?><tr><td colspan="5">No reviews recorded.</td></tr><?php endif;?></tbody></table></div></div></section><?php endif;?>
</div>
<script>(function(){const c=document.getElementById('idpCohort'),u=document.getElementById('idpCandidate');if(!c||!u)return;const opts=[...u.querySelectorAll('option[data-cohort]')];function f(){opts.forEach(o=>{o.hidden=!!c.value&&o.dataset.cohort!==c.value;if(o.hidden&&o.selected)u.value='';});}c.addEventListener('change',f);f();})();</script>
<?php require __DIR__.'/_layout_end.php'; ?>
