<?php
require_once __DIR__ . '/../includes/bootstrap.php';require_role('supervisor');require_once __DIR__ . '/_helpers.php';
// Author: Vincent | Date: 2026-10-02 | Audit trail helpers (sv_audit_log)
require_once __DIR__ . '/_audit.php';
// Progress Update History helpers (sv_ph_record)
require_once __DIR__ . '/_progress_history.php';
$user=current_user();$flashes=render_flashes();$currentPage='update_candidate_status';$pageTitle='Update Candidate';$supervisorId=(int)($user['id']??$user['user_id']??0);$candidateId=(int)($_REQUEST['id']??0);$cohortId=(int)($_REQUEST['cohort_id']??0);if($supervisorId<=0||$candidateId<=0||$cohortId<=0){http_response_code(400);exit('Invalid request.');}
$stmt=Database::prepare("SELECT u.id candidate_id,u.first_name,u.last_name,u.email,cp.id participation_id,cp.status participant_status,c.id cohort_id,c.name cohort_name,p.name programme_name FROM cohort_participants cp JOIN cohorts c ON c.id=cp.cohort_id JOIN programmes p ON p.id=c.programme_id JOIN users u ON u.id=cp.user_id WHERE u.id=? AND c.id=? AND c.supervisor_id=? LIMIT 1",'iii',[$candidateId,$cohortId,$supervisorId]);$candidate=$stmt->get_result()->fetch_assoc();$stmt->close();if(!$candidate){http_response_code(404);exit('Candidate not found in your assigned cohort.');}
$error='';$allowed=['active','completed','withdrawn'];
// ============================================================================
// Author: Vincent | Date: 2026-10-02
// Audit trail: record candidate status changes.
// Registered as a shutdown function so it runs after the existing save logic
// (which redirects and exits) without touching that code. It only logs when the
// new status was valid, differs from the old one AND the database now really
// holds the new status - failed/invalid/unchanged saves are never logged.
// ============================================================================
$auditOldStatus=(string)($candidate['participant_status']??'');$newStatus=null;
register_shutdown_function(function() use (&$newStatus,$auditOldStatus,$allowed,$candidate,$candidateId,$cohortId,$supervisorId){
    try{
        if(($_SERVER['REQUEST_METHOD']??'')!=='POST')return;
        if(!is_string($newStatus)||!in_array($newStatus,$allowed,true)||$newStatus===$auditOldStatus)return;
        $stmt=Database::prepare("SELECT cp.status FROM cohort_participants cp JOIN cohorts c ON c.id=cp.cohort_id WHERE cp.user_id=? AND cp.cohort_id=? AND c.supervisor_id=? LIMIT 1",'iii',[$candidateId,$cohortId,$supervisorId]);
        $now=(string)(($stmt->get_result()->fetch_assoc()['status']??''));$stmt->close();
        if($now!==$newStatus)return;
        $who=trim(trim((string)$candidate['first_name']).' '.trim((string)$candidate['last_name']))?:'Candidate';
        sv_audit_change($supervisorId,'candidate_status_changed','candidate',$candidateId,
            'Participation status of '.$who.' in '.(string)$candidate['cohort_name'],
            sv_status_label($auditOldStatus),sv_status_label($newStatus));
    }catch(Throwable $ex){error_log('audit (status change) failed: '.$ex->getMessage());}
});
// ============================================================================
// Author: Vincent | Date: 2026-10-02
// Audit trail: record candidate PROGRESS updates.
// In the Supervisor Portal a candidate's progress IS their participation status
// (the Cohort Progress report is built from it). When a candidate moves to
// "Completed" a progress-specific entry is logged in addition to the status
// change above, using the action "candidate_progress_completed", which the
// audit trail files under "Progress Updates". Same safeguards as above: it only
// logs when the save really happened (the database now holds "completed") and
// the status was not already "completed".
// ============================================================================
register_shutdown_function(function() use (&$newStatus,$auditOldStatus,$candidate,$candidateId,$cohortId,$supervisorId){
    try{
        if(($_SERVER['REQUEST_METHOD']??'')!=='POST')return;
        if($newStatus!=='completed'||$auditOldStatus==='completed')return;
        $stmt=Database::prepare("SELECT cp.status FROM cohort_participants cp JOIN cohorts c ON c.id=cp.cohort_id WHERE cp.user_id=? AND cp.cohort_id=? AND c.supervisor_id=? LIMIT 1",'iii',[$candidateId,$cohortId,$supervisorId]);
        $now=(string)(($stmt->get_result()->fetch_assoc()['status']??''));$stmt->close();
        if($now!=='completed')return;
        $who=trim(trim((string)$candidate['first_name']).' '.trim((string)$candidate['last_name']))?:'Candidate';
        sv_audit_change($supervisorId,'candidate_progress_completed','candidate',$candidateId,
            'Progress of '.$who.' in '.(string)$candidate['cohort_name'],
            sv_status_label($auditOldStatus),sv_status_label('completed'));
    }catch(Throwable $ex){error_log('audit (progress update) failed: '.$ex->getMessage());}
});
// ============================================================================
// Progress Update History: store a full record of every saved progress update
// (previous/new status, stage, notes, who, when, programme/cohort).
// Runs after the existing save logic (shutdown function), and only records when
// the save really happened (the database now holds the submitted status).
// ============================================================================
register_shutdown_function(function() use (&$newStatus,$auditOldStatus,$allowed,$candidate,$candidateId,$cohortId,$supervisorId,$user){
    try{
        if(($_SERVER['REQUEST_METHOD']??'')!=='POST')return;
        if(!is_string($newStatus)||!in_array($newStatus,$allowed,true))return;
        $stmt=Database::prepare("SELECT cp.status FROM cohort_participants cp JOIN cohorts c ON c.id=cp.cohort_id WHERE cp.user_id=? AND cp.cohort_id=? AND c.supervisor_id=? LIMIT 1",'iii',[$candidateId,$cohortId,$supervisorId]);
        $now=(string)(($stmt->get_result()->fetch_assoc()['status']??''));$stmt->close();
        if($now!==$newStatus)return;
        $stage=trim((string)($_POST['progress_stage']??''));
        $notes=trim((string)($_POST['progress_notes']??''));
        // A save with no status change, stage or notes carries no information - skip it.
        if($newStatus===$auditOldStatus&&$stage===''&&$notes==='')return;
        $phName=trim(trim((string)($user['first_name']??'')).' '.trim((string)($user['last_name']??'')));
        if($phName==='')$phName=trim((string)($user['full_name']??$user['username']??'Supervisor'));
        sv_ph_record($candidateId,$cohortId,$supervisorId,$phName?:'Supervisor',$auditOldStatus,$newStatus,$stage,$notes,(int)($candidate['participation_id']??0)?:null);
    }catch(Throwable $ex){error_log('progress history (status update) failed: '.$ex->getMessage());}
});
if($_SERVER['REQUEST_METHOD']==='POST'){$token=(string)($_POST['csrf_token']??'');if(!sv_verify_csrf($token)){$error='Your session token is invalid. Refresh the page and try again.';}else{$newStatus=(string)($_POST['status']??'');if(!in_array($newStatus,$allowed,true)){$error='Invalid candidate status.';}else{$stmt=Database::prepare("UPDATE cohort_participants cp JOIN cohorts c ON c.id=cp.cohort_id SET cp.status=?,cp.completed_at=CASE WHEN ?='completed' THEN COALESCE(cp.completed_at,NOW()) ELSE cp.completed_at END WHERE cp.user_id=? AND cp.cohort_id=? AND c.supervisor_id=?",'ssiii',[$newStatus,$newStatus,$candidateId,$cohortId,$supervisorId]);$stmt->close();header('Location: '.url('supervisor/candidate_view.php?id='.$candidateId.'&cohort_id='.$cohortId));exit;}}}
$fn=trim((string)$candidate['first_name']);$ln=trim((string)$candidate['last_name']);$name=trim($fn.' '.$ln)?:'Candidate';
require __DIR__ . '/_layout_start.php';
?>
<div class="sv-page-header"><div><span class="sv-eyebrow">Participation Status</span><h2>Update <?= e($name) ?></h2><p>Update participation status within <?= e($candidate['cohort_name']) ?>. This does not move the candidate or alter cohort assignment.</p></div><div class="sv-actions"><a class="sv-btn sv-btn--secondary" href="<?= url('supervisor/candidate_view.php?id='.$candidateId.'&cohort_id='.$cohortId) ?>"><i class="fas fa-arrow-left"></i> Cancel</a></div></div>
<?php if($error!==''): ?><div class="sv-security" style="background:var(--sv-red-soft);border-color:rgba(220,38,38,.18)"><i class="fas fa-triangle-exclamation" style="color:var(--sv-red)"></i><div><strong><?= e($error) ?></strong></div></div><?php endif; ?>
<section class="sv-card" style="max-width:720px"><div class="sv-card__header"><div><h3>Status Update</h3><p>Allowed Supervisor statuses are Active, Completed and Withdrawn.</p></div></div><div class="sv-card__body"><form method="post"><input type="hidden" name="id" value="<?= $candidateId ?>"><input type="hidden" name="cohort_id" value="<?= $cohortId ?>"><input type="hidden" name="csrf_token" value="<?= e(sv_csrf_token()) ?>"><div class="sv-field" style="margin-bottom:16px"><label>Candidate</label><input class="sv-input" value="<?= e($name.' · '.$candidate['programme_name'].' · '.$candidate['cohort_name']) ?>" disabled></div><div class="sv-field" style="margin-bottom:18px"><label>Participation Status</label><select class="sv-select" name="status" required><?php foreach($allowed as $s): ?><option value="<?= e($s) ?>" <?= $candidate['participant_status']===$s?'selected':'' ?>><?= e(sv_status_label($s)) ?></option><?php endforeach; ?></select></div><div class="sv-field" style="margin-bottom:16px"><label>Progress Stage <small style="font-weight:400">(optional)</small></label><select class="sv-select" name="progress_stage"><option value="">Not specified</option><?php foreach(sv_ph_stages() as $phKey=>$phLabel): ?><option value="<?= e($phKey) ?>"><?= e($phLabel) ?></option><?php endforeach; ?></select></div><div class="sv-field" style="margin-bottom:18px"><label>Update Notes <small style="font-weight:400">(optional)</small></label><textarea class="sv-input" name="progress_notes" rows="3" maxlength="2000" placeholder="Describe this progress update..." style="width:100%;box-sizing:border-box;resize:vertical"></textarea></div><div class="sv-actions"><button class="sv-btn sv-btn--primary" type="submit"><i class="fas fa-floppy-disk"></i> Save Status</button><a class="sv-btn sv-btn--secondary" href="<?= url('supervisor/candidate_view.php?id='.$candidateId.'&cohort_id='.$cohortId) ?>">Cancel</a></div></form></div></section>
<div class="sv-security"><i class="fas fa-shield-halved"></i><div><strong>Supervisor permissions</strong><p>This workflow only updates participation status. Candidate assignment, cohort reassignment and Supervisor assignment remain outside this page.</p></div></div>
<?php require __DIR__ . '/_layout_end.php'; ?>
