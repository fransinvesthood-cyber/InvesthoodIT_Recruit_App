<?php
declare(strict_types=1);
require_once __DIR__.'/_common.php';
$sponsorId=(int)($user['id']??0);
if(!sponsor_table_exists($conn,'sponsor_programme_access')){http_response_code(503);exit('Sponsorship reporting migration is not installed.');}
$stmt=$conn->prepare("SELECT programme_id FROM sponsor_programme_access WHERE sponsor_user_id=? AND access_status='active'");
$allowed=[];if($stmt){$stmt->bind_param('i',$sponsorId);$stmt->execute();$res=$stmt->get_result();while($row=$res->fetch_assoc())$allowed[]=(int)$row['programme_id'];$stmt->close();}
$requested=(int)($_GET['programme_id']??0);if($requested && !in_array($requested,$allowed,true)){$requested=0;}
$scope=$requested?[$requested]:$allowed;$in=$scope?implode(',',array_map('intval',$scope)):'0';
header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="sponsorship_performance_impact_'.date('Ymd_His').'.csv"');header('X-Content-Type-Options: nosniff');
$out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");
fputcsv($out,['Sponsorship Performance & Impact Report','Generated',date('Y-m-d H:i:s')]);
fputcsv($out,['Scope','Assigned programmes only','Sponsor user ID',$sponsorId]);
fputcsv($out,[]);
if($in==='0'){fputcsv($out,['No programme access is currently assigned.']);fclose($out);exit;}
fputcsv($out,['Programme delivery']);fputcsv($out,['Programme','Cohorts','Planned capacity','Distinct participants','Placements recorded','Completed placements']);
$res=$conn->query("SELECT p.id,p.name FROM programmes p WHERE p.id IN ({$in}) ORDER BY p.name");if($res){while($pr=$res->fetch_assoc()){$pid=(int)$pr['id'];$a=sponsor_rows($conn,"SELECT COUNT(DISTINCT c.id) cohorts,COALESCE(SUM(c.max_capacity),0) capacity FROM cohorts c WHERE c.programme_id={$pid}");$b=sponsor_rows($conn,"SELECT COUNT(DISTINCT cp.user_id) participants FROM cohort_participants cp JOIN cohorts c ON c.id=cp.cohort_id WHERE c.programme_id={$pid} AND cp.status<>'withdrawn'");$c=sponsor_rows($conn,"SELECT COUNT(*) total,SUM(status='completed') completed FROM placements WHERE programme_id={$pid} AND status NOT IN ('withdrawn','cancelled')");fputcsv($out,[$pr['name'],$a[0]['cohorts']??0,$a[0]['capacity']??0,$b[0]['participants']??0,$c[0]['total']??0,$c[0]['completed']??0]);}$res->free();}
fputcsv($out,[]);
if(sponsor_table_exists($conn,'sponsor_contributions')){fputcsv($out,['Contribution register']);fputcsv($out,['Reference','Programme','Cohort','Type','Status','Date','Amount','Currency','Description']);$res=$conn->query("SELECT sc.reference_no,p.name programme,COALESCE(c.name,'') cohort,sc.contribution_type,sc.contribution_status,sc.contribution_date,sc.amount,sc.currency,sc.description FROM sponsor_contributions sc JOIN programmes p ON p.id=sc.programme_id LEFT JOIN cohorts c ON c.id=sc.cohort_id WHERE sc.sponsor_user_id={$sponsorId} AND sc.programme_id IN ({$in}) ORDER BY sc.contribution_date DESC,sc.id DESC");if($res){while($r=$res->fetch_assoc())fputcsv($out,array_values($r));$res->free();}}
fputcsv($out,[]);
if(sponsor_table_exists($conn,'sponsor_impact_indicators')){fputcsv($out,['Impact indicators']);fputcsv($out,['Indicator','Programme','Cohort','Category','Baseline','Target','Actual','Unit','Period start','Period end','Verification','Evidence']);$res=$conn->query("SELECT i.indicator_name,p.name programme,COALESCE(c.name,'') cohort,i.indicator_category,i.baseline_value,i.target_value,i.actual_value,i.unit,i.reporting_period_start,i.reporting_period_end,i.verification_status,i.evidence_reference FROM sponsor_impact_indicators i JOIN programmes p ON p.id=i.programme_id LEFT JOIN cohorts c ON c.id=i.cohort_id WHERE i.sponsor_user_id={$sponsorId} AND i.programme_id IN ({$in}) ORDER BY i.reporting_period_end DESC,i.id DESC");if($res){while($r=$res->fetch_assoc())fputcsv($out,array_values($r));$res->free();}}
fclose($out);exit;
