<?php
require_once __DIR__ . '/../includes/bootstrap.php';require_role('supervisor');require_once __DIR__ . '/_helpers.php';

// ============================================================================
// Author: Vincent
// Date:   2026-10-02
// Supervisor Activity & Audit Trail - helper functions
//  - sv_audit_categories() : the audit categories shown to the supervisor
//  - sv_audit_category()   : maps a recorded action to a category
//  - sv_audit_log()        : call this from any supervisor page to record an
//                            action (status change, progress update, assignment,
//                            note, cohort action, report generation)
//  - sv_audit_change()     : convenience wrapper that records "from -> to"
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
$user=current_user();$flashes=render_flashes();$currentPage='activity_log';$pageTitle='Activity Log';$supervisorId=(int)($user['id']??$user['user_id']??0);if($supervisorId<=0){http_response_code(403);exit('Invalid Supervisor account.');}
$conn=Database::getConnection();$tableAvailable=false;$check=$conn->query("SHOW TABLES LIKE 'supervisor_activity_log'");if($check&&$check->num_rows>0)$tableAvailable=true;
$rows=[];$search=trim((string)($_GET['search']??''));$action=trim((string)($_GET['action']??''));$page=max(1,(int)($_GET['page']??1));$perPage=15;$total=0;$actions=[];
if($tableAvailable){$stmt=Database::prepare("SELECT DISTINCT action FROM supervisor_activity_log WHERE supervisor_id=? ORDER BY action",'i',[$supervisorId]);$r=$stmt->get_result();while($x=$r->fetch_assoc())$actions[]=$x['action'];$stmt->close();$where=['supervisor_id=?'];$types='i';$params=[$supervisorId];if($search!==''){$where[]='(action LIKE ? OR description LIKE ?)';$like='%'.$search.'%';$types.='ss';$params[]=$like;$params[]=$like;}if($action!==''){$where[]='action=?';$types.='s';$params[]=$action;}$stmt=Database::prepare("SELECT COUNT(*) total FROM supervisor_activity_log WHERE ".implode(' AND ',$where),$types,$params);$total=(int)(($stmt->get_result()->fetch_assoc()['total']??0));$stmt->close();$offset=($page-1)*$perPage;$query="SELECT id,action,description,entity_type,entity_id,ip_address,created_at FROM supervisor_activity_log WHERE ".implode(' AND ',$where)." ORDER BY created_at DESC,id DESC LIMIT ".$perPage." OFFSET ".$offset;$stmt=Database::prepare($query,$types,$params);$r=$stmt->get_result();while($x=$r->fetch_assoc())$rows[]=$x;$stmt->close();}
$pages=max(1,(int)ceil($total/$perPage));

// ============================================================================
// Author: Vincent
// Date:   2026-10-02
// Supervisor Activity & Audit Trail - data layer
// Own query-string keys (audit_cat, audit_from, audit_to, audit_page,
// audit_export) so it never interferes with the existing search/action filter.
// Everything is scoped to the logged-in supervisor (supervisor_id=?).
// ============================================================================
$audCats=sv_audit_categories();
$audCat=trim((string)($_GET['audit_cat']??''));if(!isset($audCats[$audCat]))$audCat='';
$audFrom=trim((string)($_GET['audit_from']??''));if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$audFrom))$audFrom='';
$audTo=trim((string)($_GET['audit_to']??''));if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$audTo))$audTo='';
$audPage=max(1,(int)($_GET['audit_page']??1));$audPerPage=10;
$audCounts=array_fill_keys(array_keys($audCats),0);$audCounts['other']=0;
$audRows=[];$audTotal=0;$audPages=1;$audLast=null;
if($tableAvailable){
    // 1) Counts per category (all time) + action->category map
    $audActionCat=[];
    $stmt=Database::prepare("SELECT action,COUNT(*) c,MAX(created_at) last_at FROM supervisor_activity_log WHERE supervisor_id=? GROUP BY action",'i',[$supervisorId]);
    $r=$stmt->get_result();
    while($x=$r->fetch_assoc()){
        $k=sv_audit_category((string)$x['action']);
        $audActionCat[$x['action']]=$k;
        $audCounts[$k]+=(int)$x['c'];
        if($audLast===null||strtotime((string)$x['last_at'])>strtotime((string)$audLast))$audLast=$x['last_at'];
    }
    $stmt->close();

    // 2) Filtered query
    $audWhere=['supervisor_id=?'];$audTypes='i';$audParams=[$supervisorId];
    if($audCat!==''){
        $in=[];foreach($audActionCat as $act=>$k){if($k===$audCat)$in[]=$act;}
        if($in){$audWhere[]='action IN ('.implode(',',array_fill(0,count($in),'?')).')';$audTypes.=str_repeat('s',count($in));foreach($in as $a)$audParams[]=$a;}
        else{$audWhere[]='1=0';}
    }
    if($audFrom!==''){$audWhere[]='created_at>=?';$audTypes.='s';$audParams[]=$audFrom.' 00:00:00';}
    if($audTo!==''){$audWhere[]='created_at<=?';$audTypes.='s';$audParams[]=$audTo.' 23:59:59';}
    $audWhereSql=implode(' AND ',$audWhere);

    $stmt=Database::prepare("SELECT COUNT(*) total FROM supervisor_activity_log WHERE ".$audWhereSql,$audTypes,$audParams);
    $audTotal=(int)(($stmt->get_result()->fetch_assoc()['total']??0));$stmt->close();
    $audPages=max(1,(int)ceil($audTotal/$audPerPage));if($audPage>$audPages)$audPage=$audPages;

    // 3) CSV export of the filtered audit trail (handled before any HTML output)
    if(($_GET['audit_export']??'')==='csv'){
        $stmt=Database::prepare("SELECT id,action,description,entity_type,entity_id,ip_address,created_at FROM supervisor_activity_log WHERE ".$audWhereSql." ORDER BY created_at DESC,id DESC LIMIT 5000",$audTypes,$audParams);
        $r=$stmt->get_result();$exp=[];while($x=$r->fetch_assoc())$exp[]=$x;$stmt->close();
        sv_audit_log($supervisorId,'audit_report_exported','Exported audit trail to CSV ('.count($exp).' records)','audit_log',null);
        while(ob_get_level()>0)ob_end_clean();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="supervisor_audit_trail_'.date('Ymd_His').'.csv"');
        $out=fopen('php://output','w');
        fwrite($out,"\xEF\xBB\xBF");
        fputcsv($out,['ID','Category','Action','Description','Entity Type','Entity ID','IP Address','Date']);
        $safe=function($v){$v=(string)$v;return($v!==''&&strpos("=+-@\t\r",$v[0])!==false)?"'".$v:$v;}; // CSV formula-injection guard
        foreach($exp as $x){
            $k=sv_audit_category((string)$x['action']);
            fputcsv($out,[$x['id'],$audCats[$k]['label']??'Other',$safe($x['action']),$safe($x['description']),$safe($x['entity_type']),$x['entity_id'],$x['ip_address'],$x['created_at']]);
        }
        fclose($out);exit;
    }

    // 4) Current page of the audit trail
    $audOffset=($audPage-1)*$audPerPage;
    $stmt=Database::prepare("SELECT id,action,description,entity_type,entity_id,ip_address,created_at FROM supervisor_activity_log WHERE ".$audWhereSql." ORDER BY created_at DESC,id DESC LIMIT ".$audPerPage." OFFSET ".$audOffset,$audTypes,$audParams);
    $r=$stmt->get_result();while($x=$r->fetch_assoc())$audRows[]=$x;$stmt->close();
}
require __DIR__ . '/_layout_start.php';
?>
<div class="sv-page-header"><div><span class="sv-eyebrow">Audit & Accountability</span><h2>Activity Log</h2><p>Review actions recorded against your Supervisor account.</p></div></div>
<?php if(!$tableAvailable): ?><div class="sv-security" style="background:var(--sv-orange-soft);border-color:rgba(217,119,6,.18)"><i class="fas fa-triangle-exclamation" style="color:var(--sv-orange)"></i><div><strong>Activity table not installed</strong><p>Run the included <code>supervisor_portal.sql</code> migration. The page is intentionally showing this message instead of failing with a database exception.</p></div></div><?php else: ?>
<section class="sv-stats"><article class="sv-stat"><div class="sv-stat__icon sv-stat__icon--blue"><i class="fas fa-clock-rotate-left"></i></div><strong><?= $total ?></strong><span>Matching Activities</span></article><article class="sv-stat"><div class="sv-stat__icon sv-stat__icon--green"><i class="fas fa-calendar-day"></i></div><strong><?= count(array_filter($rows,fn($x)=>date('Y-m-d',strtotime($x['created_at']))===date('Y-m-d'))) ?></strong><span>Shown Today</span></article><article class="sv-stat"><div class="sv-stat__icon sv-stat__icon--purple"><i class="fas fa-filter"></i></div><strong><?= count($actions) ?></strong><span>Action Types</span></article><article class="sv-stat"><div class="sv-stat__icon sv-stat__icon--orange"><i class="fas fa-shield-halved"></i></div><strong>Scoped</strong><span>Supervisor Audit</span></article></section>
<form class="sv-filter" method="get"><div class="sv-filter-grid"><div class="sv-field"><label>Search</label><input class="sv-input" name="search" value="<?= e($search) ?>" placeholder="Action or description"></div><div class="sv-field"><label>Action</label><select class="sv-select" name="action"><option value="">All actions</option><?php foreach($actions as $a): ?><option value="<?= e($a) ?>" <?= $action===$a?'selected':'' ?>><?= e(sv_status_label($a)) ?></option><?php endforeach; ?></select></div><div class="sv-filter-actions"><button class="sv-btn sv-btn--primary"><i class="fas fa-filter"></i> Apply</button><a class="sv-btn sv-btn--secondary" href="<?= url('supervisor/activity_log.php') ?>">Reset</a></div></div></form>
<section class="sv-card"><div class="sv-card__header"><div><h3>Recorded Activity</h3><p>Newest activity appears first.</p></div></div><?php if(!$rows): ?><div class="sv-empty"><i class="fas fa-clock-rotate-left"></i><strong>No activity found</strong><span>No records match the current filters.</span></div><?php else: ?><div class="sv-table-wrap"><table class="sv-table"><thead><tr><th>Action</th><th>Description</th><th>Entity</th><th>IP</th><th>Date</th></tr></thead><tbody><?php foreach($rows as $row): ?><tr><td><strong><?= e(sv_status_label($row['action'])) ?></strong></td><td><?= e($row['description']) ?></td><td><?= e((string)($row['entity_type']??'—')) ?><?= !empty($row['entity_id'])?' #'.(int)$row['entity_id']:'' ?></td><td><?= e((string)($row['ip_address']??'—')) ?></td><td><?= e(sv_datetime($row['created_at'])) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?><?php if($pages>1): ?><div class="sv-pagination"><span>Page <?= $page ?> of <?= $pages ?></span><div class="sv-pagination__actions"><?php if($page>1): ?><a class="sv-btn sv-btn--secondary" href="<?= e(sv_query_url(['page'=>$page-1])) ?>">Previous</a><?php endif; ?><?php if($page<$pages): ?><a class="sv-btn sv-btn--secondary" href="<?= e(sv_query_url(['page'=>$page+1])) ?>">Next</a><?php endif; ?></div></div><?php endif; ?></section>

<?php
// ============================================================================
// Author: Vincent
// Date:   2026-10-02
// Supervisor Activity & Audit Trail - presentation layer
// Category overview, category/date filters, traceable timeline and CSV export.
// ============================================================================
$audBase=array_diff_key($_GET,['audit_page'=>1,'audit_export'=>1]);
$audExportUrl='?'.http_build_query(array_merge($audBase,['audit_export'=>'csv']));
$audPageUrl=function(int $p) use ($audBase){return '?'.http_build_query(array_merge($audBase,['audit_page'=>$p]));};
?>
<section class="sv-card" id="audit-trail" style="margin-top:24px">
    <div class="sv-card__header" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
        <div>
            <h3>Supervisor Audit Trail</h3>
            <p>Traceable record of candidate status changes, progress updates, assignments, notes, cohort actions and report generation.<?php if($audLast): ?> Last recorded activity: <strong><?= e(sv_datetime($audLast)) ?></strong>.<?php endif; ?></p>
        </div>
        <a class="sv-btn sv-btn--secondary" href="<?= e($audExportUrl) ?>"><i class="fas fa-file-csv"></i> Export CSV</a>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;padding:0 20px 16px">
        <?php foreach($audCats as $key=>$cat): ?>
            <a href="?<?= e(http_build_query(array_merge($audBase,['audit_cat'=>$key]))) ?>#audit-trail" style="text-decoration:none;color:inherit;border:1px solid rgba(100,116,139,.25);border-left:4px solid <?= e($cat['color']) ?>;border-radius:10px;padding:12px 14px;display:block;<?= $audCat===$key?'background:rgba(100,116,139,.08);':'' ?>">
                <div style="display:flex;align-items:center;gap:8px;font-size:.85rem"><i class="fas <?= e($cat['icon']) ?>" style="color:<?= e($cat['color']) ?>"></i><span><?= e($cat['label']) ?></span></div>
                <strong style="font-size:1.4rem;display:block;margin-top:4px"><?= (int)$audCounts[$key] ?></strong>
            </a>
        <?php endforeach; ?>
    </div>

    <form class="sv-filter" method="get" style="padding:0 20px 16px">
        <?php foreach(['search','action','page'] as $keep): if(isset($_GET[$keep])&&$_GET[$keep]!==''): ?><input type="hidden" name="<?= e($keep) ?>" value="<?= e((string)$_GET[$keep]) ?>"><?php endif; endforeach; ?>
        <div class="sv-filter-grid">
            <div class="sv-field"><label>Category</label>
                <select class="sv-select" name="audit_cat">
                    <option value="">All categories</option>
                    <?php foreach($audCats as $key=>$cat): ?><option value="<?= e($key) ?>" <?= $audCat===$key?'selected':'' ?>><?= e($cat['label']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="sv-field"><label>From</label><input class="sv-input" type="date" name="audit_from" value="<?= e($audFrom) ?>"></div>
            <div class="sv-field"><label>To</label><input class="sv-input" type="date" name="audit_to" value="<?= e($audTo) ?>"></div>
            <div class="sv-filter-actions">
                <button class="sv-btn sv-btn--primary"><i class="fas fa-filter"></i> Filter Audit Trail</button>
                <a class="sv-btn sv-btn--secondary" href="<?= url('supervisor/activity_log.php') ?>#audit-trail">Reset</a>
            </div>
        </div>
    </form>

    <?php if(!$audRows): ?>
        <div class="sv-empty"><i class="fas fa-shield-halved"></i><strong>No audit records found</strong><span>No audit entries match the selected category or date range.</span></div>
    <?php else: ?>
        <div class="sv-table-wrap">
            <table class="sv-table">
                <thead><tr><th>Date &amp; Time</th><th>Category</th><th>Action</th><th>Details</th><th>Reference</th><th>IP</th></tr></thead>
                <tbody>
                <?php foreach($audRows as $ar): $k=sv_audit_category((string)$ar['action']);$c=$audCats[$k]??['label'=>'Other','icon'=>'fa-circle-info','color'=>'#64748b']; ?>
                    <tr>
                        <td><?= e(sv_datetime($ar['created_at'])) ?></td>
                        <td><span style="display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:999px;font-size:.78rem;background:<?= e($c['color']) ?>1a;color:<?= e($c['color']) ?>"><i class="fas <?= e($c['icon']) ?>"></i><?= e($c['label']) ?></span></td>
                        <td><strong><?= e(sv_status_label($ar['action'])) ?></strong></td>
                        <td><?= e((string)$ar['description']) ?></td>
                        <td><?= e((string)($ar['entity_type']??'—')) ?><?= !empty($ar['entity_id'])?' #'.(int)$ar['entity_id']:'' ?></td>
                        <td><?= e((string)($ar['ip_address']??'—')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if($audPages>1): ?>
        <div class="sv-pagination">
            <span>Page <?= $audPage ?> of <?= $audPages ?> (<?= $audTotal ?> records)</span>
            <div class="sv-pagination__actions">
                <?php if($audPage>1): ?><a class="sv-btn sv-btn--secondary" href="<?= e($audPageUrl($audPage-1)) ?>#audit-trail">Previous</a><?php endif; ?>
                <?php if($audPage<$audPages): ?><a class="sv-btn sv-btn--secondary" href="<?= e($audPageUrl($audPage+1)) ?>#audit-trail">Next</a><?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php require __DIR__ . '/_layout_end.php'; ?>
