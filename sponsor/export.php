<?php
declare(strict_types=1);
require_once __DIR__.'/_common.php';
$dataset=(string)($_GET['dataset']??'summary');
$exports=[
 'programmes'=>['filename'=>'sponsor_programmes.csv','sql'=>"SELECT p.name AS programme,p.type,p.status,p.duration,p.start_date,p.end_date,COUNT(DISTINCT c.id) AS cohort_count,COALESCE(SUM(c.max_capacity),0) AS planned_capacity,COALESCE(SUM(c.applications_count),0) AS cohort_application_count FROM programmes p LEFT JOIN cohorts c ON c.programme_id=p.id GROUP BY p.id,p.name,p.type,p.status,p.duration,p.start_date,p.end_date ORDER BY p.name"],
 'cohorts'=>['filename'=>'sponsor_cohorts.csv','sql'=>"SELECT c.name AS cohort,p.name AS programme,c.status,c.max_capacity,c.applications_count,c.delivery_mode,c.location,c.province,c.start_date,c.end_date FROM cohorts c LEFT JOIN programmes p ON p.id=c.programme_id ORDER BY p.name,c.name"],
 'placements'=>['filename'=>'sponsor_placements.csv','sql'=>"SELECT pl.placement_reference,pr.name AS programme,co.name AS cohort,pl.status,pl.department,pl.location,pl.start_date,pl.end_date FROM placements pl LEFT JOIN programmes pr ON pr.id=pl.programme_id LEFT JOIN cohorts co ON co.id=pl.cohort_id ORDER BY pl.created_at DESC"],
 'participants'=>['filename'=>'sponsor_participant_summary.csv','sql'=>"SELECT CONCAT(u.first_name,' ',u.last_name) AS candidate_name,cp.professional_title,u.province,u.qualification_level,u.employment_status,cp.completion_percent AS profile_completion_percent,u.status AS account_status,u.created_at AS account_created FROM users u JOIN roles r ON r.id=u.role_id LEFT JOIN candidate_profiles cp ON cp.user_id=u.id WHERE r.slug='candidate' ORDER BY u.created_at DESC LIMIT 500"],
 'summary'=>['filename'=>'sponsor_portfolio_summary.csv','sql'=>null],
 'applications'=>['filename'=>'sponsor_applications_summary.csv','sql'=>"SELECT status,COUNT(*) AS total FROM applications GROUP BY status ORDER BY total DESC"],
 'attendance'=>['filename'=>'sponsor_attendance_last_30_days.csv','sql'=>"SELECT session_date,status,COUNT(*) AS record_count FROM candidate_attendance WHERE session_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY session_date,status ORDER BY session_date DESC,status"]
];
if(!isset($exports[$dataset])){http_response_code(400);exit('Unsupported export dataset.');}
header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="'.$exports[$dataset]['filename'].'"');header('X-Content-Type-Options: nosniff');$out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");
if($dataset==='summary'){
 fputcsv($out,['Area','Indicator','Current value','Definition']);
 $summary=[['Portfolio','Programmes recorded',"SELECT COUNT(*) total FROM programmes",'All programme statuses.'],['Portfolio','Active programmes',"SELECT COUNT(*) total FROM programmes WHERE status='active'",'Programmes marked active.'],['Delivery','Cohorts / intakes',"SELECT COUNT(*) total FROM cohorts",'All cohort records.'],['Capacity','Planned cohort capacity',"SELECT COALESCE(SUM(max_capacity),0) total FROM cohorts",'Sum of planned cohort capacity.'],['Participation','Active candidate accounts',"SELECT COUNT(*) total FROM users u JOIN roles r ON r.id=u.role_id WHERE r.slug='candidate' AND u.status='active'",'Active accounts, not confirmed enrolment.'],['Recruitment','Non-draft applications',"SELECT COUNT(*) total FROM applications WHERE status <> 'draft'",'Applications excluding drafts.'],['Outcomes','Current or completed placements',"SELECT COUNT(*) total FROM placements WHERE status IN ('placed','active','placement_in_progress','completed')",'Placed, active, in progress or completed records.'],['Outcomes','Completed placements',"SELECT COUNT(*) total FROM placements WHERE status='completed'",'Explicitly completed placement records.'],['Delivery','Attendance records · last 30 days',"SELECT COUNT(*) total FROM candidate_attendance WHERE session_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",'Attendance records in last 30 days.']];
 foreach($summary as $s)fputcsv($out,[$s[0],$s[1],sponsor_count($conn,$s[2]),$s[3]]);
}else{
 $result=$conn->query($exports[$dataset]['sql']);if($result){$fields=$result->fetch_fields();fputcsv($out,array_map(fn($f)=>$f->name,$fields));while($row=$result->fetch_assoc()){foreach($row as &$v){if(is_string($v) && preg_match('/^[=+@\-\t\r]/',$v))$v="'".$v;}unset($v);fputcsv($out,array_values($row));}$result->free();}else{error_log('[Sponsor Export] '.$conn->error);fputcsv($out,['Export unavailable','The database query could not be completed.']);}
}
fclose($out);exit;
