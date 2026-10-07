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
function pm_chat_reply(mysqli $c,int $id,string $q):string{
 $programmes=pm_chat_count($c,"SELECT COUNT(*) total FROM programmes WHERE programme_manager_id=? AND status='active'",$id);
 $cohorts=pm_chat_count($c,"SELECT COUNT(*) total FROM cohorts c JOIN programmes p ON p.id=c.programme_id WHERE p.programme_manager_id=? AND p.status='active' AND c.status='active'",$id);
 $candidates=pm_chat_count($c,"SELECT COUNT(DISTINCT cp.user_id) total FROM cohort_participants cp JOIN cohorts c ON c.id=cp.cohort_id JOIN programmes p ON p.id=c.programme_id WHERE p.programme_manager_id=? AND p.status='active' AND cp.status<>'withdrawn'",$id);
 $completion=pm_chat_completion($c,$id);
 if(str_contains($q,'overview')||str_contains($q,'summary')||str_contains($q,'programme')) return "You currently have {$programmes} active programme(s), {$cohorts} active cohort(s), and {$candidates} participating candidate(s). Your overall completion rate is {$completion}%.";
 if(str_contains($q,'cohort')) return "You currently have {$cohorts} active cohort(s) across your active programmes.";
 if(str_contains($q,'candidate')) return "You currently have {$candidates} participating candidate(s) across your active programmes.";
 if(str_contains($q,'completion')||str_contains($q,'progress')) return "Your current overall programme completion rate is {$completion}%.";
 if(str_contains($q,'how many')||str_contains($q,'count')) return "Your current totals are: {$programmes} active programmes, {$cohorts} active cohorts, and {$candidates} participating candidates.";
 return "I can help with your assigned programme data. Try asking: 'Give me an overview', 'How many active cohorts do I have?', 'How many candidates do I have?', or 'Give me my completion rate.'";
}
try{echo json_encode(['success'=>true,'reply'=>pm_chat_reply($conn,$managerId,$q)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}catch(Throwable $e){http_response_code(500);echo json_encode(['success'=>false,'message'=>'I could not access the programme data right now.']);}
