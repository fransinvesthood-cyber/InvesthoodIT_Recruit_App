<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_role('finance_officer');
$user=current_user(); $conn=Database::getConnection(); require_once __DIR__.'/_helpers.php';
$currentPage='audit_trail'; $pageTitle='Financial Audit Trail & Transaction History';
$uid=(int)($user['id']??$user['user_id']??0);
$from=trim((string)($_GET['from']??'')); $to=trim((string)($_GET['to']??''));
$action=trim((string)($_GET['action']??'')); $entity=trim((string)($_GET['entity']??'')); $search=trim((string)($_GET['q']??''));
$available=fo_has_table($conn,'finance_audit_trail'); $rows=[]; $total=0; $summary=['events'=>0,'users'=>0,'transactions'=>0]; $page=max(1,(int)($_GET['page']??1)); $perPage=50; $offset=($page-1)*$perPage;
if($available){
 $where=[];$params=[];$types='';
 if($from!=='' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)){$where[]='a.created_at>=?';$params[]=$from.' 00:00:00';$types.='s';}
 if($to!=='' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)){$where[]='a.created_at<=?';$params[]=$to.' 23:59:59';$types.='s';}
 if($action!==''){$where[]='a.action=?';$params[]=$action;$types.='s';}
 if($entity!==''){$where[]='a.entity_type=?';$params[]=$entity;$types.='s';}
 if($search!==''){$where[]='(a.description LIKE ? OR a.reference_no LIKE ? OR CAST(a.entity_id AS CHAR) LIKE ?)';$like='%'.$search.'%';array_push($params,$like,$like,$like);$types.='sss';}
 $clause=$where?' WHERE '.implode(' AND ',$where):'';
 $st=$conn->prepare('SELECT COUNT(*) n FROM finance_audit_trail a'.$clause);if($st){if($types)$st->bind_param($types,...$params);$st->execute();$total=(int)$st->get_result()->fetch_assoc()['n'];$st->close();}
 if(isset($_GET['export'])&&$_GET['export']==='csv'){
  $sql='SELECT a.created_at,a.user_id,CONCAT(COALESCE(u.first_name,\'\'),\' \',COALESCE(u.last_name,\'\')) actor,a.action,a.description,a.entity_type,a.entity_id,a.amount,a.reference_no,a.ip_address FROM finance_audit_trail a LEFT JOIN users u ON u.id=a.user_id'.$clause.' ORDER BY a.created_at DESC,a.id DESC';
  $st=$conn->prepare($sql);if($st){if($types)$st->bind_param($types,...$params);$st->execute();$res=$st->get_result();header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename=financial_audit_trail_'.date('Ymd_His').'.csv');$out=fopen('php://output','w');fputcsv($out,['Timestamp','User ID','Actor','Action','Description','Entity','Record ID','Amount (ZAR)','Reference','IP Address']);while($r=$res->fetch_assoc())fputcsv($out,$r);fclose($out);$st->close();exit;}
 }
 $sql='SELECT a.*,CONCAT(COALESCE(u.first_name,\'\'),\' \',COALESCE(u.last_name,\'\')) actor FROM finance_audit_trail a LEFT JOIN users u ON u.id=a.user_id'.$clause.' ORDER BY a.created_at DESC,a.id DESC LIMIT ? OFFSET ?';
 $st=$conn->prepare($sql);if($st){$allTypes=$types.'ii';$allParams=$params;$allParams[]=$perPage;$allParams[]=$offset;$st->bind_param($allTypes,...$allParams);$st->execute();$rows=$st->get_result()->fetch_all(MYSQLI_ASSOC);$st->close();}
 $r=$conn->query('SELECT COUNT(*) events,COUNT(DISTINCT user_id) users,COUNT(DISTINCT CONCAT(entity_type,\':\',entity_id)) transactions FROM finance_audit_trail');if($r)$summary=$r->fetch_assoc();
}
$actions=[];$entities=[];if($available){$r=$conn->query('SELECT DISTINCT action FROM finance_audit_trail ORDER BY action');while($r&&$x=$r->fetch_assoc())$actions[]=$x['action'];$r=$conn->query('SELECT DISTINCT entity_type FROM finance_audit_trail WHERE entity_type IS NOT NULL AND entity_type<>\'\' ORDER BY entity_type');while($r&&$x=$r->fetch_assoc())$entities[]=$x['entity_type'];}
$flashes=render_flashes(); require __DIR__.'/_layout_start.php';
?>
<div id="finance-audit-redesign" class="finance-audit-redesign">
<div class="fo-page-header"><div><span>Governance · Accountability</span><h2>Financial Audit Trail &amp; Transaction History</h2><p>Chronological record of financial operations, approvals, payment verification, reconciliation and budget activity.</p></div><?php if($available):?><a class="fo-btn fo-btn--primary" href="<?= e(url('finance/audit_trail.php?'.http_build_query(array_merge($_GET,['export'=>'csv','page'=>null])))) ?>"><i class="fas fa-file-csv"></i> Export CSV</a><?php endif;?></div>
<?php if(!$available):?><div class="fo-alert fo-alert--warning"><i class="fas fa-database"></i><div><strong>Audit database setup required.</strong> Import <code>finance/financial_audit_trail.sql</code> into the <code>investhood_platform</code> database, then refresh this page.</div></div><?php else:?>
<div class="fo-dashboard-grid"><article class="fo-card"><span class="fo-muted">Recorded events</span><h2><?= number_format((int)$summary['events']) ?></h2></article><article class="fo-card"><span class="fo-muted">Users represented</span><h2><?= number_format((int)$summary['users']) ?></h2></article><article class="fo-card"><span class="fo-muted">Financial records</span><h2><?= number_format((int)$summary['transactions']) ?></h2></article></div>
<section class="fo-card"><form method="get" class="fat-filters"><label>From<input type="date" name="from" value="<?= e($from) ?>"></label><label>To<input type="date" name="to" value="<?= e($to) ?>"></label><label>Action<select name="action"><option value="">All actions</option><?php foreach($actions as $v):?><option value="<?= e($v) ?>" <?= $action===$v?'selected':'' ?>><?= e(fo_label($v)) ?></option><?php endforeach;?></select></label><label>Record type<select name="entity"><option value="">All types</option><?php foreach($entities as $v):?><option value="<?= e($v) ?>" <?= $entity===$v?'selected':'' ?>><?= e(fo_label($v)) ?></option><?php endforeach;?></select></label><label class="fat-search">Search<input name="q" value="<?= e($search) ?>" placeholder="Description, reference or record ID"></label><button class="fo-btn fo-btn--primary">Filter</button><a class="fo-btn fo-btn--secondary" href="<?= e(url('finance/audit_trail.php')) ?>">Reset</a></form></section>
<section class="fo-card"><div class="fo-card-header"><h3><i class="fas fa-list-check"></i> Transaction history</h3><span class="fo-muted"><?= number_format($total) ?> matching events</span></div><div class="fo-table-wrap"><table><thead><tr><th>Date &amp; time</th><th>Performed by</th><th>Activity</th><th>Record</th><th>Amount</th><th>Reference</th><th>IP address</th></tr></thead><tbody><?php foreach($rows as $x):?><tr><td><?= e(fo_datetime($x['created_at'])) ?></td><td><?= e(trim($x['actor']??'' )?:('User #'.(int)$x['user_id'])) ?></td><td><strong><?= e(fo_label($x['action'])) ?></strong><div class="fat-description"><?= e($x['description']) ?></div></td><td><?= e(fo_label($x['entity_type']??'')) ?><?= $x['entity_id']?' #'.(int)$x['entity_id']:'' ?></td><td><?= $x['amount']!==null?e(fo_money($x['amount'])):'—' ?></td><td><?= e($x['reference_no']?:'—') ?></td><td><?= e($x['ip_address']?:'—') ?></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="7">No audit events match these filters.</td></tr><?php endif;?></tbody></table></div><div class="fat-pagination"><span>Page <?= $page ?> of <?= max(1,(int)ceil($total/$perPage)) ?></span><?php if($page>1):?><a class="fo-btn fo-btn--secondary" href="?<?= e(http_build_query(array_merge($_GET,['page'=>$page-1]))) ?>">Previous</a><?php endif;?><?php if($offset+$perPage<$total):?><a class="fo-btn fo-btn--secondary" href="?<?= e(http_build_query(array_merge($_GET,['page'=>$page+1]))) ?>">Next</a><?php endif;?></div></section>
<?php endif;?>
</div>
<style>
/* Dedicated, isolated Financial Audit Trail layout */
#finance-audit-redesign{box-sizing:border-box;width:100%;max-width:100%;min-width:0;display:flex;flex-direction:column;gap:22px;color:var(--text-primary,var(--text-color,#172033));}
#finance-audit-redesign *,#finance-audit-redesign *:before,#finance-audit-redesign *:after{box-sizing:border-box;}
#finance-audit-redesign .fo-page-header{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;flex-wrap:wrap;margin:0;padding:4px 0 8px;}
#finance-audit-redesign .fo-page-header>div{min-width:0;flex:1 1 520px;}
#finance-audit-redesign .fo-page-header>div>span{display:inline-block;font-size:13px;font-weight:750;letter-spacing:.09em;text-transform:uppercase;color:var(--primary,#315caa);}
#finance-audit-redesign .fo-page-header h2{font-size:clamp(26px,2.4vw,36px);line-height:1.2;font-weight:800;letter-spacing:-.025em;margin:8px 0 10px;}
#finance-audit-redesign .fo-page-header p{font-size:15px;line-height:1.65;max-width:850px;margin:0;color:var(--text-muted,#64748b);}
#finance-audit-redesign .fo-page-header>.fo-btn{flex:0 0 auto;min-height:44px;display:inline-flex;align-items:center;gap:9px;}
#finance-audit-redesign .fo-dashboard-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px;margin:0;}
#finance-audit-redesign .fo-dashboard-grid>.fo-card,#finance-audit-redesign>section.fo-card{min-width:0;padding:24px;border:1px solid var(--border-color,var(--border,#e2e8f0));border-radius:16px;background:var(--surface,var(--card-bg,#fff));color:inherit;box-shadow:0 5px 18px rgba(15,35,70,.055);}
#finance-audit-redesign .fo-dashboard-grid .fo-muted{display:block;font-size:14px;font-weight:700;line-height:1.4;}
#finance-audit-redesign .fo-dashboard-grid h2{font-size:clamp(28px,2.4vw,36px);line-height:1.2;font-weight:800;margin:12px 0 0;}
#finance-audit-redesign .fat-filters{display:grid;grid-template-columns:repeat(4,minmax(130px,1fr)) minmax(190px,1.5fr) auto auto;gap:13px;align-items:end;margin:0;}
#finance-audit-redesign .fat-filters label{display:grid;gap:7px;min-width:0;font-size:14px;line-height:1.4;font-weight:700;color:inherit;}
#finance-audit-redesign .fat-filters input,#finance-audit-redesign .fat-filters select{display:block;width:100%;min-width:0;min-height:46px;padding:10px 12px;border:1px solid var(--border-color,var(--border,#cbd5e1));border-radius:9px;background:var(--input-bg,var(--surface,#fff));color:var(--text-primary,var(--text-color,#172033));font:inherit;font-size:14px;}
#finance-audit-redesign .fat-filters input:focus,#finance-audit-redesign .fat-filters select:focus{outline:3px solid rgba(49,92,170,.18);border-color:var(--primary,#315caa);}
#finance-audit-redesign .fat-filters .fat-search{grid-column:auto;}
#finance-audit-redesign .fat-filters .fo-btn{min-height:46px;padding:10px 16px;display:inline-flex;align-items:center;justify-content:center;white-space:nowrap;font-size:14px;}
#finance-audit-redesign .fo-card-header{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:0 0 18px;}
#finance-audit-redesign .fo-card-header h3{font-size:20px;line-height:1.35;font-weight:800;margin:0;}
#finance-audit-redesign .fo-card-header .fo-muted{font-size:14px;}
#finance-audit-redesign .fo-table-wrap{width:100%;max-width:100%;overflow-x:auto;overscroll-behavior-x:contain;border:1px solid var(--border-color,var(--border,#e2e8f0));border-radius:11px;}
#finance-audit-redesign .fo-table-wrap table{width:100%;min-width:1050px;border-collapse:collapse;font-size:14px;line-height:1.55;}
#finance-audit-redesign .fo-table-wrap th{padding:15px 14px;text-align:left;white-space:nowrap;font-size:12px;line-height:1.4;font-weight:800;letter-spacing:.055em;text-transform:uppercase;background:var(--table-head-bg,#f1f5f9);color:var(--text-primary,var(--text-color,#172033));border-bottom:1px solid var(--border-color,var(--border,#e2e8f0));}
#finance-audit-redesign .fo-table-wrap td{padding:15px 14px;vertical-align:top;text-align:left;font-size:14px;line-height:1.55;border-bottom:1px solid var(--border-color,var(--border,#e2e8f0));color:inherit;overflow-wrap:anywhere;}
#finance-audit-redesign .fo-table-wrap tbody tr:last-child td{border-bottom:0;}
#finance-audit-redesign .fo-table-wrap tbody tr:hover{background:var(--row-hover,rgba(100,116,139,.07));}
#finance-audit-redesign .fat-description{max-width:420px;margin-top:5px;font-size:13px;line-height:1.55;color:var(--text-muted,#64748b);overflow-wrap:anywhere;}
#finance-audit-redesign .fat-pagination{display:flex;align-items:center;justify-content:flex-end;gap:10px;flex-wrap:wrap;padding-top:18px;font-size:14px;}
#finance-audit-redesign .fo-alert{font-size:15px;line-height:1.65;padding:18px;border-radius:12px;}
html[data-theme="dark"] #finance-audit-redesign{color:var(--text-primary,#e5e7eb);}
html[data-theme="dark"] #finance-audit-redesign .fo-dashboard-grid>.fo-card,html[data-theme="dark"] #finance-audit-redesign>section.fo-card{background:var(--surface,#172033);border-color:var(--border-color,#344054);color:var(--text-primary,#e5e7eb);}
html[data-theme="dark"] #finance-audit-redesign .fat-filters input,html[data-theme="dark"] #finance-audit-redesign .fat-filters select{background:var(--input-bg,#111827);border-color:var(--border-color,#475569);color:var(--text-primary,#e5e7eb);color-scheme:dark;}
html[data-theme="dark"] #finance-audit-redesign .fo-table-wrap{border-color:var(--border-color,#344054);}
html[data-theme="dark"] #finance-audit-redesign .fo-table-wrap th{background:#202b3d;color:#f1f5f9;border-color:#344054;}
html[data-theme="dark"] #finance-audit-redesign .fo-table-wrap td{border-color:#344054;color:#e5e7eb;}
@media(max-width:1250px){#finance-audit-redesign .fat-filters{grid-template-columns:repeat(3,minmax(0,1fr));}#finance-audit-redesign .fat-filters .fat-search{grid-column:span 1;}}
@media(max-width:760px){#finance-audit-redesign{gap:16px;}#finance-audit-redesign .fo-dashboard-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;}#finance-audit-redesign .fo-dashboard-grid>.fo-card,#finance-audit-redesign>section.fo-card{padding:17px;}#finance-audit-redesign .fat-filters{grid-template-columns:repeat(2,minmax(0,1fr));gap:11px;}#finance-audit-redesign .fat-filters label{font-size:13px;}#finance-audit-redesign .fat-filters input,#finance-audit-redesign .fat-filters select{font-size:14px;min-height:44px;}#finance-audit-redesign .fo-page-header h2{font-size:26px;}#finance-audit-redesign .fo-card-header h3{font-size:18px;}}
@media(max-width:460px){#finance-audit-redesign .fo-dashboard-grid{grid-template-columns:1fr 1fr;gap:9px;}#finance-audit-redesign .fo-dashboard-grid>.fo-card{padding:14px;}#finance-audit-redesign .fo-dashboard-grid h2{font-size:25px;}#finance-audit-redesign .fat-filters{grid-template-columns:1fr;}#finance-audit-redesign .fat-filters .fat-search{grid-column:auto;}#finance-audit-redesign .fat-filters .fo-btn{width:100%;}#finance-audit-redesign .fo-page-header{align-items:flex-start;}#finance-audit-redesign .fo-page-header>.fo-btn{width:100%;justify-content:center;}#finance-audit-redesign .fat-pagination{justify-content:space-between;}}
</style>
<?php require __DIR__.'/_layout_end.php';?>
