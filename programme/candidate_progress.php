<?php
/**
 * INVESTHOOD IT - Programme Manager
 * Candidate Progress Tracking: Programme -> Cohort -> Candidate
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('programme_manager');

$user = current_user();
$flashes = render_flashes();
$conn = Database::getConnection();
$currentPage = 'candidate_progress';
$pageTitle = 'Candidate Progress';

$managerId = (int) ($user['id'] ?? $user['user_id'] ?? 0);
$programmeId = (int) ($_GET['programme_id'] ?? 0);
$cohortId = (int) ($_GET['cohort_id'] ?? 0);
$candidateId = (int) ($_GET['candidate_id'] ?? 0);

$programmes = [];
$stmt = $conn->prepare("SELECT id,name,status FROM programmes WHERE programme_manager_id=? ORDER BY name ASC");
if ($stmt) {
    $stmt->bind_param('i',$managerId); $stmt->execute();
    $res=$stmt->get_result(); while($r=$res->fetch_assoc()) $programmes[]=$r; $stmt->close();
}
if ($programmeId > 0) {
    $valid = false;
    foreach ($programmes as $p) if ((int)$p['id'] === $programmeId) {$valid=true;break;}
    if (!$valid) {$programmeId=0;$cohortId=0;$candidateId=0;}
}

$selectedProgramme = null;
foreach ($programmes as $p) if ((int)$p['id']===$programmeId) {$selectedProgramme=$p;break;}

$cohorts=[];
if ($programmeId>0) {
    $stmt=$conn->prepare("SELECT id,name,status,start_date,end_date FROM cohorts WHERE programme_id=? ORDER BY start_date DESC,name ASC");
    if($stmt){$stmt->bind_param('i',$programmeId);$stmt->execute();$res=$stmt->get_result();while($r=$res->fetch_assoc())$cohorts[]=$r;$stmt->close();}
}
if ($cohortId>0) {
    $valid=false; foreach($cohorts as $c) if((int)$c['id']===$cohortId){$valid=true;break;}
    if(!$valid){$cohortId=0;$candidateId=0;}
}
$selectedCohort=null;
foreach($cohorts as $c) if((int)$c['id']===$cohortId){$selectedCohort=$c;break;}

$candidates=[];
if($cohortId>0){
    $sql="
        SELECT
            cp.user_id AS candidate_id,
            cp.status AS participant_status,
            CONCAT_WS(' ',u.first_name,u.last_name) AS candidate_name,
            u.email,
            a.id AS application_id,
            a.application_reference,
            a.status AS application_status,
            a.updated_at AS application_updated_at
        FROM cohort_participants cp
        INNER JOIN users u ON u.id=cp.user_id
        LEFT JOIN opportunities o ON o.cohort_id=cp.cohort_id
        LEFT JOIN applications a
          ON a.candidate_id=cp.user_id AND a.opportunity_id=o.id
          AND NOT EXISTS (
              SELECT 1
              FROM applications a2
              INNER JOIN opportunities o2 ON o2.id=a2.opportunity_id
              WHERE a2.candidate_id=cp.user_id
                AND o2.cohort_id=cp.cohort_id
                AND (
                    a2.updated_at > a.updated_at
                    OR (a2.updated_at=a.updated_at AND a2.id>a.id)
                )
          )
        WHERE cp.cohort_id=? AND cp.status<>'withdrawn'
        ORDER BY u.first_name ASC,u.last_name ASC
    ";
    $stmt=$conn->prepare($sql);
    if($stmt){$stmt->bind_param('i',$cohortId);$stmt->execute();$res=$stmt->get_result();while($r=$res->fetch_assoc())$candidates[]=$r;$stmt->close();}
}

$selectedCandidate=null;
if($candidateId>0 && $cohortId>0){
    foreach($candidates as $c) if((int)$c['candidate_id']===$candidateId){$selectedCandidate=$c;break;}
}

$stages=[
    'submitted'=>'Submitted',
    'under_review'=>'Eligibility Review',
    'shortlisted'=>'Screened',
    'assessment'=>'Assessment',
    'interview_scheduled'=>'Interview',
    'interview_completed'=>'Interview',
    'on_hold'=>'Waitlisted',
    'selected'=>'Selected',
    'offer_sent'=>'Selected',
    'offer_accepted'=>'Selected',
    'rejected'=>'Rejected',
    'withdrawn'=>'Withdrawn'
];
$stageOrder=['submitted','under_review','shortlisted','assessment','interview_scheduled','on_hold','selected'];
$currentStage=$selectedCandidate['application_status']??'';
$timelineStage=match($currentStage){
    'interview_completed' => 'interview_scheduled',
    'offer_sent','offer_accepted' => 'selected',
    default => $currentStage,
};
$stageIndex=array_search($timelineStage,$stageOrder,true);
if($stageIndex===false) $stageIndex=-1;
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?=e($pageTitle)?> | Investhood IT</title>
<link rel="stylesheet" href="<?=url('css/styles.css')?>">
<link rel="stylesheet" href="<?=url('css/programme_manager_enhancements.css')?>?v=20261006">
<style>
.cp-page{padding-bottom:2rem}.cp-selectors{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem;margin:1.2rem 0}.cp-field label{display:block;font-weight:800;font-size:.78rem;margin-bottom:.4rem}.cp-field select{width:100%;padding:.75rem;border:1px solid var(--border,#e5e7eb);border-radius:10px;background:var(--bg-white,#fff);color:var(--text,#111827)}
.cp-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem;margin:1.2rem 0}.cp-card{display:block;text-decoration:none;padding:1.1rem;border:1px solid var(--border,#e5e7eb);border-radius:14px;background:var(--bg-white,#fff);color:var(--text,#111827)}.cp-card:hover{border-color:#2563eb;transform:translateY(-2px)}.cp-card.active{border-color:#2563eb;box-shadow:0 0 0 2px rgba(37,99,235,.12)}.cp-card h3{margin:0 0 .4rem;font-size:.95rem}.cp-card p{margin:.2rem 0;color:var(--text-light,#64748b);font-size:.78rem}.cp-table-wrap{overflow:auto;background:var(--bg-white,#fff);border:1px solid var(--border,#e5e7eb);border-radius:14px}.cp-table{width:100%;border-collapse:collapse;min-width:820px}.cp-table th,.cp-table td{padding:.9rem 1rem;text-align:left;border-bottom:1px solid var(--border-light,#eef2f7);font-size:.82rem}.cp-table th{background:var(--bg,#f8fafc);font-size:.7rem;text-transform:uppercase;color:var(--text-light,#64748b)}.cp-status{display:inline-flex;padding:.3rem .55rem;border-radius:999px;background:#eef2ff;color:#3730a3;font-weight:800;font-size:.7rem}.cp-action{color:#2563eb;text-decoration:none;font-weight:800}.cp-timeline{display:grid;grid-template-columns:repeat(7,1fr);gap:0;margin:1.5rem 0 1rem}.cp-stage{text-align:center;position:relative}.cp-stage:before{content:"";position:absolute;top:13px;left:-50%;right:50%;height:3px;background:#e5e7eb;z-index:0}.cp-stage:first-child:before{display:none}.cp-stage__dot{position:relative;z-index:1;width:28px;height:28px;margin:0 auto 8px;border-radius:50%;display:grid;place-items:center;background:#e5e7eb;color:#64748b;font-size:.65rem}.cp-stage.done .cp-stage__dot,.cp-stage.current .cp-stage__dot{background:#2563eb;color:#fff}.cp-stage.done:before,.cp-stage.current:before{background:#2563eb}.cp-stage span{display:block;font-size:.68rem;font-weight:800;color:#64748b}.cp-stage.current span{color:#2563eb}.cp-candidate-detail{margin-top:1.2rem;padding:1.2rem;border:1px solid var(--border,#e5e7eb);border-radius:14px;background:var(--bg-white,#fff)}.cp-candidate-detail h2{margin:0}.cp-meta{color:var(--text-light,#64748b);font-size:.8rem;margin-top:.3rem}
html[data-theme="dark"] .cp-card,html[data-theme="dark"] .cp-table-wrap,html[data-theme="dark"] .cp-candidate-detail,html[data-theme="dark"] .cp-field select{background:#1e293b;border-color:#334155;color:#f1f5f9}html[data-theme="dark"] .cp-table th{background:#0f172a}.cp-empty{padding:2.5rem;text-align:center;color:#64748b}
@media(max-width:800px){.cp-selectors{grid-template-columns:1fr}.cp-grid{grid-template-columns:1fr 1fr}.cp-timeline{grid-template-columns:1fr;gap:.65rem}.cp-stage{text-align:left;display:flex;align-items:center;gap:.7rem}.cp-stage:before{display:none}.cp-stage__dot{margin:0}.cp-stage span{font-size:.75rem}}
@media(max-width:520px){.cp-grid{grid-template-columns:1fr}}
</style>
</head><body class="dashboard-page"><div class="dashboard"><?php require __DIR__.'/sidebar.php'; ?><main class="dashboard__main"><?php require __DIR__.'/navbar.php'; ?><div class="dash-content cp-page">
<?=$flashes?>
<div class="welcome-card"><div class="welcome-card__content"><h1 class="welcome-card__greeting">Candidate <span class="text-gradient">Progress</span></h1><p>Follow the structured Programme → Cohort → Candidate workflow using the live recruitment application stage.</p></div></div>

<form class="cp-selectors" method="get">
<div class="cp-field"><label for="programme_id">1. Select Programme</label><select id="programme_id" name="programme_id" onchange="this.form.submit()"><option value="0">Choose a programme...</option><?php foreach($programmes as $p):?><option value="<?=$p['id']?>" <?=$programmeId===(int)$p['id']?'selected':''?>><?=e($p['name'])?></option><?php endforeach;?></select></div>
<div class="cp-field"><label for="cohort_id">2. Select Cohort</label><select id="cohort_id" name="cohort_id" <?=$programmeId>0?'':'disabled'?> onchange="this.form.submit()"><option value="0">Choose a cohort...</option><?php foreach($cohorts as $c):?><option value="<?=$c['id']?>" <?=$cohortId===(int)$c['id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach;?></select></div>
</form>

<?php if($programmeId>0):?><div class="cp-card"><h3><?=e($selectedProgramme['name'])?></h3><p>Programme selected. Choose a cohort to continue to candidate tracking.</p></div><?php endif;?>

<?php if($programmeId>0 && $cohorts):?>
<div class="cp-grid">
<?php foreach($cohorts as $c):?><a class="cp-card <?=$cohortId===(int)$c['id']?'active':''?>" href="<?=url('programme/candidate_progress.php?programme_id='.$programmeId.'&cohort_id='.(int)$c['id'])?>"><h3><?=e($c['name'])?></h3><p><?=e(ucwords(str_replace('_',' ',$c['status'])))?> · <?=e((string)$c['start_date'])?></p><p>Click to view candidates</p></a><?php endforeach;?>
</div>
<?php endif;?>

<?php if($cohortId>0):?>
<div class="cp-table-wrap"><table class="cp-table"><thead><tr><th>Candidate</th><th>Application</th><th>Current Stage</th><th>Participation</th><th></th></tr></thead><tbody>
<?php if(!$candidates):?><tr><td colspan="5" class="cp-empty">No candidates are currently assigned to this cohort.</td></tr>
<?php else: foreach($candidates as $c):?><tr><td><strong><?=e($c['candidate_name'])?></strong><div style="font-size:.72rem;color:#64748b"><?=e($c['email'])?></div></td><td><?=e($c['application_reference']??'No application')?></td><td><span class="cp-status"><?=e($stages[$c['application_status']??'']??ucwords(str_replace('_',' ',(string)($c['application_status']??'No application'))))?></span></td><td><?=e(ucwords(str_replace('_',' ',(string)$c['participant_status'])))?></td><td><a class="cp-action" href="<?=url('programme/candidate_progress.php?programme_id='.$programmeId.'&cohort_id='.$cohortId.'&candidate_id='.(int)$c['candidate_id'])?>">View progress</a></td></tr><?php endforeach; endif;?>
</tbody></table></div>
<?php endif;?>

<?php if($selectedCandidate):?>
<div class="cp-candidate-detail"><h2><?=e($selectedCandidate['candidate_name'])?></h2><div class="cp-meta"><?=e($selectedCandidate['application_reference']??'No application')?> · <?=e($selectedCandidate['email'])?></div>
<?php if($currentStage==='rejected'):?><div style="margin-top:1rem;font-weight:800;color:#b91c1c">Current stage: Rejected</div>
<?php elseif($currentStage==='withdrawn'):?><div style="margin-top:1rem;font-weight:800;color:#b91c1c">Current stage: Withdrawn</div>
<?php else:?><div class="cp-timeline"><?php foreach($stageOrder as $i=>$stage):?><div class="cp-stage <?=$i<$stageIndex?'done ':''?><?=$i===$stageIndex?'current':''?>"><div class="cp-stage__dot"><i class="fas <?=$i<=$stageIndex?'fa-check':'fa-circle'?>"></i></div><span><?=e($stages[$stage])?></span></div><?php endforeach;?></div><?php endif;?>
<p class="cp-meta">Application stage is read from the existing <code>applications.status</code> workflow. Participant status is shown separately so recruitment progress is not confused with programme participation.</p>
</div>
<?php endif;?>
</div></main></div><script src="<?=url('js/programme_manager_enhancements.js')?>?v=20261006"></script></body></html>
