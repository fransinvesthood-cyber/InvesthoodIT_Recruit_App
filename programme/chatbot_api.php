<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('programme_manager');
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'POST required.']); exit; }
$user=current_user(); $managerId=(int)($user['id']??$user['user_id']??0); if($managerId<=0){http_response_code(403);echo json_encode(['success'=>false,'message'=>'Invalid Programme Manager account.']);exit;}
$payload=json_decode(file_get_contents('php://input'),true); $message=trim((string)($payload['message']??'')); if($message===''){echo json_encode(['success'=>false,'message'=>'Please enter a question.']);exit;} if(mb_strlen($message)>1000){echo json_encode(['success'=>false,'message'=>'Question is too long.']);exit;}
$conn=Database::getConnection(); $q=mb_strtolower($message);
function pm_chat_count(mysqli $c,string $sql,int $id):int{$s=$c->prepare($sql);if(!$s)throw new RuntimeException('Database query failed.');$s->bind_param('i',$id);$s->execute();$r=$s->get_result()->fetch_assoc();$s->close();return (int)($r['total']??0);}
function pm_chat_completion(mysqli $c,int $id):int{$s=$c->prepare("SELECT COUNT(*) total,SUM(CASE WHEN cp.status='completed' THEN 1 ELSE 0 END) completed FROM cohort_participants cp JOIN cohorts c ON c.id=cp.cohort_id JOIN programmes p ON p.id=c.programme_id WHERE p.programme_manager_id=? AND p.status='active' AND cp.status<>'withdrawn'");if(!$s)throw new RuntimeException('Database query failed.');$s->bind_param('i',$id);$s->execute();$r=$s->get_result()->fetch_assoc();$s->close();$total=(int)($r['total']??0);return $total>0?(int)round(((int)($r['completed']??0)/$total)*100):0;}
function pm_chat_reply(mysqli $c,int $id,string $q):array{
 $programmes=pm_chat_count($c,"SELECT COUNT(*) total FROM programmes WHERE programme_manager_id=? AND status='active'",$id);
 $cohorts=pm_chat_count($c,"SELECT COUNT(*) total FROM cohorts c JOIN programmes p ON p.id=c.programme_id WHERE p.programme_manager_id=? AND p.status='active' AND c.status='active'",$id);
 $candidates=pm_chat_count($c,"SELECT COUNT(DISTINCT cp.user_id) total FROM cohort_participants cp JOIN cohorts c ON c.id=cp.cohort_id JOIN programmes p ON p.id=c.programme_id WHERE p.programme_manager_id=? AND p.status='active' AND cp.status<>'withdrawn'",$id);
 $completion=pm_chat_completion($c,$id);
 $topics=[
  ['keys'=>['milestone','deadline','due date'], 'reply'=>'Milestones and deadlines let you record programme activities, assign due dates, monitor progress and identify overdue work.', 'label'=>'Open Milestones', 'url'=>'programme/milestones.php'],
  ['keys'=>['budget','expenditure','spending','cost'], 'reply'=>'Budget monitoring helps you review allocations, recorded expenditure, remaining funds and financial performance for your assigned programmes.', 'label'=>'Open Budgets', 'url'=>'programme/budgets.php'],
  ['keys'=>['outcome','impact','employment','placement'], 'reply'=>'Outcome and impact tracking helps you review candidate achievements, programme completion and verified employment outcomes.', 'label'=>'Open Outcomes', 'url'=>'programme/outcomes.php'],
  ['keys'=>['risk','mitigation'], 'reply'=>'The Risk Register records programme risks, likelihood and impact assessments, mitigation actions, owners and due dates.', 'label'=>'Open Risk Register', 'url'=>'programme/risks.php'],
  ['keys'=>['supervisor','workload','attendance'], 'reply'=>'Supervisor monitoring provides visibility into assigned cohorts, candidate workloads, progress updates and attendance activity.', 'label'=>'Open Supervisor Monitoring', 'url'=>'programme/supervisor_monitoring.php'],
  ['keys'=>['resource','allocation','utilisation','shortage'], 'reply'=>'Resource monitoring helps you review available resources, allocations, remaining quantities and potential shortages.', 'label'=>'Open Resources', 'url'=>'programme/resources.php'],
  ['keys'=>['target','performance','achievement','target gap'], 'reply'=>'Target and performance tracking compares planned programme or cohort targets with actual results and highlights achievement gaps.', 'label'=>'Open Targets', 'url'=>'programme/targets.php'],
  ['keys'=>['compliance','document','expired','documentation'], 'reply'=>'Compliance monitoring helps you track required documents, submission status, missing items and expiry dates.', 'label'=>'Open Compliance', 'url'=>'programme/compliance.php'],
  ['keys'=>['report','analytics'], 'reply'=>'Programme Reports provides summaries of programmes, cohorts, candidate participation and completion performance.', 'label'=>'Open Reports', 'url'=>'programme/reports.php'],
  ['keys'=>['announcement','communication','publish'], 'reply'=>'The Communication Centre lets you draft, publish and manage announcements for the appropriate audience, programme or cohort.', 'label'=>'Open Communication Centre', 'url'=>'programme/announcements.php'],
 ];
 foreach($topics as $topic){foreach($topic['keys'] as $key){if(str_contains($q,$key)){return ['reply'=>$topic['reply'],'action'=>['label'=>$topic['label'],'url'=>url($topic['url'])]];}}}
 if(str_contains($q,'overview')||str_contains($q,'summary')||str_contains($q,'programme')) return ['reply'=>"You currently have {$programmes} active programme(s), {$cohorts} active cohort(s), and {$candidates} participating candidate(s). Your overall completion rate is {$completion}%.",'action'=>['label'=>'View Programmes','url'=>url('programme/programmes.php')]];
 if(str_contains($q,'cohort')) return ['reply'=>"You currently have {$cohorts} active cohort(s) across your active programmes.",'action'=>['label'=>'View Cohorts','url'=>url('programme/cohorts.php')]];
 if(str_contains($q,'candidate')) return ['reply'=>"You currently have {$candidates} participating candidate(s) across your active programmes.",'action'=>['label'=>'View Candidates','url'=>url('programme/candidates.php')]];
 if(str_contains($q,'completion')||str_contains($q,'progress')) return ['reply'=>"Your current overall programme completion rate is {$completion}%. You can open Candidate Progress to review individual candidate stages.",'action'=>['label'=>'View Candidate Progress','url'=>url('programme/candidate_progress.php')]];
 if(str_contains($q,'how many')||str_contains($q,'count')) return ['reply'=>"Your current totals are: {$programmes} active programmes, {$cohorts} active cohorts, and {$candidates} participating candidates.",'action'=>['label'=>'Open Dashboard','url'=>url('programme/dashboard.php')]];
 return ['reply'=>'I can assist with your Programme Manager workspace, including programmes, cohorts, candidates, milestones, budgets, outcomes, risks, supervisors, resources, targets, compliance and announcements. Try asking: “Show my active programmes”, “How do I track a risk?”, or “Where can I monitor supervisor workloads?”','action'=>null];
}
try{echo json_encode(array_merge(['success'=>true], pm_chat_reply($conn,$managerId,$q)),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}catch(Throwable $e){http_response_code(500);echo json_encode(['success'=>false,'message'=>'I could not access the programme data right now.']);}
