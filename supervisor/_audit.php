<?php
// ============================================================================
// Author: Vincent
// Date:   2026-10-02
// Shared Supervisor audit-trail helpers. Include from any supervisor page with:
//     require_once __DIR__ . '/_audit.php';
// Safe to include alongside activity_log.php (every function is guarded).
// ============================================================================
if(!function_exists('sv_audit_categories')){
    function sv_audit_categories():array{
        return [
            'candidate_status'=>['label'=>'Candidate Status Changes','icon'=>'fa-user-check','color'=>'#2563eb','keywords'=>['status','approve','reject','suspend','activate','deactivate','withdraw','graduate','enrol']],
            'progress'=>['label'=>'Progress Updates','icon'=>'fa-chart-line','color'=>'#16a34a','keywords'=>['progress','milestone','score','evaluation','assessment','grade','completion']],
            'assignment'=>['label'=>'Candidate Assignments','icon'=>'fa-user-plus','color'=>'#7c3aed','keywords'=>['assign','reassign','unassign','allocate','transfer']],
            'notes'=>['label'=>'Notes','icon'=>'fa-note-sticky','color'=>'#d97706','keywords'=>['note','comment','remark','feedback']],
            'cohort'=>['label'=>'Cohort Actions','icon'=>'fa-people-group','color'=>'#0891b2','keywords'=>['cohort','batch','intake']],
            'report'=>['label'=>'Report Generation','icon'=>'fa-file-lines','color'=>'#dc2626','keywords'=>['report','export','download','generate']],
        ];
    }
}
if(!function_exists('sv_audit_category')){
    function sv_audit_category(string $action):string{
        $action=strtolower($action);
        $cats=sv_audit_categories();
        // Priority order so that e.g. "candidate_assigned_to_cohort" counts as an assignment
        foreach(['report','notes','assignment','progress','cohort','candidate_status'] as $key){
            foreach($cats[$key]['keywords'] as $kw){
                if(strpos($action,$kw)!==false)return $key;
            }
        }
        return 'other';
    }
}
if(!function_exists('sv_audit_log')){
    /**
     * Record a supervisor action. Silently does nothing if the table is missing
     * or the insert fails, so it can never break the page that calls it.
     * Example: sv_audit_log($supervisorId,'candidate_assigned','Assigned John Doe to Cohort 4','candidate',15);
     */
    function sv_audit_log(int $supervisorId,string $action,string $description,?string $entityType=null,?int $entityId=null):bool{
        static $available=null;
        try{
            if($supervisorId<=0||$action==='')return false;
            if($available===null){
                $chk=Database::getConnection()->query("SHOW TABLES LIKE 'supervisor_activity_log'");
                $available=($chk&&$chk->num_rows>0);
            }
            if(!$available)return false;
            $ip=substr((string)($_SERVER['REMOTE_ADDR']??''),0,45);
            $description=function_exists('mb_substr')?mb_substr($description,0,1000):substr($description,0,1000);
            $stmt=Database::prepare(
                "INSERT INTO supervisor_activity_log (supervisor_id,action,description,entity_type,entity_id,ip_address,created_at) VALUES (?,?,?,?,?,?,NOW())",
                'isssis',
                [$supervisorId,$action,$description,$entityType,$entityId,$ip]
            );
            if($stmt)$stmt->close();
            return true;
        }catch(Throwable $ex){
            error_log('sv_audit_log failed: '.$ex->getMessage());
            return false;
        }
    }
}
if(!function_exists('sv_audit_change')){
    /** Records a before/after change, e.g. sv_audit_change($sid,'candidate_status_changed','candidate',15,'Status','Pending','Active'); */
    function sv_audit_change(int $supervisorId,string $action,string $entityType,int $entityId,string $label,string $from,string $to):bool{
        return sv_audit_log($supervisorId,$action,$label.' changed from "'.$from.'" to "'.$to.'"',$entityType,$entityId);
    }
}
