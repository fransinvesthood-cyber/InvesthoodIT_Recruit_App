<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('commercial_manager');
$user = current_user();
$flashes = render_flashes();
require_once __DIR__ . '/_helpers.php';

$conn = Database::getConnection();
$managerId = (int)($user['id'] ?? $user['user_id'] ?? 0);
if ($managerId < 1) { http_response_code(403); exit('Invalid Commercial Manager account.'); }

$currentPage = 'contracts';
$pageTitle = 'Contracts & Commercial Performance';
function cpm_e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function cpm_money($amount, string $currency='ZAR'): string {
    $symbol = $currency === 'ZAR' ? 'R ' : ($currency . ' ');
    return $symbol . number_format((float)$amount, 2);
}
function cpm_valid_date(string $v, bool $nullable=false): bool {
    if ($nullable && $v === '') return true;
    $d = DateTime::createFromFormat('Y-m-d', $v);
    return $d && $d->format('Y-m-d') === $v;
}
function cpm_redirect(?int $id=null): never {
    $url = url('commercial_manager/contracts.php' . ($id ? '?contract=' . $id : ''));
    header('Location: ' . $url); exit;
}
function cpm_owns_contract(mysqli $db, int $contractId, int $managerId): bool {
    $st=$db->prepare('SELECT id FROM commercial_contracts WHERE id=? AND commercial_manager_id=? LIMIT 1');
    $st->bind_param('ii',$contractId,$managerId);$st->execute();
    $ok=(bool)$st->get_result()->fetch_assoc();$st->close();return $ok;
}
if (!cm_table($conn,'commercial_contracts') || !cm_table($conn,'commercial_contract_milestones') || !cm_table($conn,'commercial_contract_activity')) {
    require __DIR__ . '/_layout_start.php'; ?>
    <div class="cm-page-head"><div><span>Commercial Operations</span><h2>Contracts &amp; Performance</h2><p>Manage agreements, deliverables, milestones, renewals and commercial risks.</p></div></div>
    <div class="cm-alert cm-alert--warning"><i class="fas fa-database"></i><div>Contract monitoring tables are not installed. Import <strong>commercial_contract_performance_migration.sql</strong> and reload this page.</div></div>
    <?php require __DIR__ . '/_layout_end.php'; exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cm_verify_csrf();
    $action=(string)($_POST['action']??'');
    try {
        if ($action === 'create_contract') {
            $client=trim((string)($_POST['client_name']??''));
            $title=trim((string)($_POST['contract_title']??''));
            $start=trim((string)($_POST['start_date']??''));
            $end=trim((string)($_POST['end_date']??''));
            $renewal=trim((string)($_POST['renewal_date']??''));
            $value=round((float)($_POST['contract_value']??0),2);
            $currency=strtoupper(trim((string)($_POST['currency']??'ZAR')));
            $risk=(string)($_POST['risk_level']??'medium');
            $description=trim((string)($_POST['description']??''));
            if($client===''||$title===''||!cpm_valid_date($start)||!cpm_valid_date($end)||!cpm_valid_date($renewal,true)||$end<$start||$value<0) throw new RuntimeException('Enter valid client, contract, dates and contract value.');
            if(!in_array($currency,['ZAR','USD','EUR','GBP'],true)) throw new RuntimeException('Select a supported contract currency.');
            if(!in_array($risk,['low','medium','high','critical'],true)) throw new RuntimeException('Select a valid risk level.');
            if($renewal!=='' && $renewal<$start) throw new RuntimeException('Renewal date cannot be earlier than the contract start date.');
            $ref='CTR-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(3)));
            $st=$conn->prepare("INSERT INTO commercial_contracts(commercial_manager_id,contract_ref,client_name,contract_title,start_date,end_date,renewal_date,contract_value,currency,status,risk_level,description) VALUES(?,?,?,?,?,?,NULLIF(?,''),?,?,'draft',?,?)");
            $st->bind_param('issssssdsss',$managerId,$ref,$client,$title,$start,$end,$renewal,$value,$currency,$risk,$description);
            if(!$st->execute()) throw new RuntimeException('Contract could not be saved.');
            $contractId=(int)$st->insert_id;$st->close();
            cm_activity($conn,$managerId,'contract_created','Created contract '.$ref,'contract',$contractId);
            $st=$conn->prepare("INSERT INTO commercial_contract_activity(contract_id,commercial_manager_id,action,details) VALUES(?,?,'created',?)");
            $details='Contract record created.';$st->bind_param('iis',$contractId,$managerId,$details);$st->execute();$st->close();
            cm_flash('success','Contract created successfully.');
            cpm_redirect($contractId);
        }
        $contractId=max(0,(int)($_POST['contract_id']??0));
        if($contractId<1||!cpm_owns_contract($conn,$contractId,$managerId)) throw new RuntimeException('Contract not found or access denied.');

        if($action==='add_milestone'){
            $milestone=trim((string)($_POST['milestone_title']??''));
            $due=trim((string)($_POST['due_date']??''));
            $deliverable=trim((string)($_POST['deliverable']??''));
            $owner=trim((string)($_POST['responsible_party']??''));
            $weight=max(0,min(100,(float)($_POST['weight_percent']??0)));
            if($milestone===''||!cpm_valid_date($due)) throw new RuntimeException('Enter a milestone name and valid due date.');
            $st=$conn->prepare("INSERT INTO commercial_contract_milestones(contract_id,milestone_title,deliverable,responsible_party,due_date,weight_percent,status) VALUES(?,?,?,?,?,?,'pending')");
            $st->bind_param('issssd',$contractId,$milestone,$deliverable,$owner,$due,$weight);
            if(!$st->execute()) throw new RuntimeException('Milestone could not be added.');
            $milestoneId=(int)$st->insert_id;$st->close();
            $details='Added milestone: '.$milestone;
            $st=$conn->prepare("INSERT INTO commercial_contract_activity(contract_id,commercial_manager_id,action,details) VALUES(?,?,'milestone_added',?)");
            $st->bind_param('iis',$contractId,$managerId,$details);$st->execute();$st->close();
            cm_activity($conn,$managerId,'contract_milestone_added',$details,'contract_milestone',$milestoneId);
            cm_flash('success','Milestone / deliverable added.');
        } elseif($action==='update_milestone'){
            $milestoneId=max(0,(int)($_POST['milestone_id']??0));
            $status=(string)($_POST['status']??'pending');
            $note=trim((string)($_POST['completion_note']??''));
            if(!in_array($status,['pending','in_progress','completed','at_risk','cancelled'],true)) throw new RuntimeException('Invalid milestone status.');
            $st=$conn->prepare('SELECT id FROM commercial_contract_milestones WHERE id=? AND contract_id=?');
            $st->bind_param('ii',$milestoneId,$contractId);$st->execute();$exists=(bool)$st->get_result()->fetch_assoc();$st->close();
            if(!$exists) throw new RuntimeException('Milestone not found.');
            $completedDate=$status==='completed'?date('Y-m-d'):null;
            $st=$conn->prepare('UPDATE commercial_contract_milestones SET status=?,completed_date=?,completion_note=? WHERE id=? AND contract_id=?');
            $st->bind_param('sssii',$status,$completedDate,$note,$milestoneId,$contractId);
            if(!$st->execute()) throw new RuntimeException('Milestone update failed.');$st->close();
            $details='Milestone '.$milestoneId.' updated to '.$status.'. '. $note;
            $st=$conn->prepare("INSERT INTO commercial_contract_activity(contract_id,commercial_manager_id,action,details) VALUES(?,?,'milestone_updated',?)");
            $st->bind_param('iis',$contractId,$managerId,$details);$st->execute();$st->close();
            cm_activity($conn,$managerId,'contract_milestone_updated',$details,'contract_milestone',$milestoneId);
            cm_flash('success','Milestone status updated.');
        } elseif($action==='update_contract'){
            $status=(string)($_POST['contract_status']??'draft');
            $risk=(string)($_POST['risk_level']??'medium');
            $renewal=trim((string)($_POST['renewal_date']??''));
            $notes=trim((string)($_POST['risk_notes']??''));
            if(!in_array($status,['draft','active','under_review','completed','expired','terminated'],true)||!in_array($risk,['low','medium','high','critical'],true)||!cpm_valid_date($renewal,true)) throw new RuntimeException('Invalid contract status, risk level or renewal date.');
            $st=$conn->prepare("UPDATE commercial_contracts SET status=?,risk_level=?,renewal_date=NULLIF(?,''),risk_notes=? WHERE id=? AND commercial_manager_id=?");
            $st->bind_param('ssssii',$status,$risk,$renewal,$notes,$contractId,$managerId);
            if(!$st->execute()) throw new RuntimeException('Contract update failed.');$st->close();
            $details='Contract status changed to '.$status.'; risk set to '.$risk.'. '.$notes;
            $st=$conn->prepare("INSERT INTO commercial_contract_activity(contract_id,commercial_manager_id,action,details) VALUES(?,?,'contract_updated',?)");
            $st->bind_param('iis',$contractId,$managerId,$details);$st->execute();$st->close();
            cm_activity($conn,$managerId,'contract_updated',$details,'contract',$contractId);
            cm_flash('success','Contract details updated.');
        } else {
            throw new RuntimeException('Unsupported contract action.');
        }
        cpm_redirect($contractId);
    } catch(Throwable $e) {
        cm_flash('error',$e->getMessage());
        cpm_redirect(isset($contractId)&&$contractId>0?$contractId:null);
    }
}

$selectedId=max(0,(int)($_GET['contract']??0));
$selected=null;$milestones=[];$activity=[];
if($selectedId && cpm_owns_contract($conn,$selectedId,$managerId)){
    $st=$conn->prepare('SELECT * FROM commercial_contracts WHERE id=? AND commercial_manager_id=?');
    $st->bind_param('ii',$selectedId,$managerId);$st->execute();$selected=$st->get_result()->fetch_assoc();$st->close();
    if($selected){
        $st=$conn->prepare('SELECT * FROM commercial_contract_milestones WHERE contract_id=? ORDER BY due_date,id');
        $st->bind_param('i',$selectedId);$st->execute();$milestones=$st->get_result()->fetch_all(MYSQLI_ASSOC);$st->close();
        $st=$conn->prepare('SELECT a.*,CONCAT(COALESCE(u.first_name,"")," ",COALESCE(u.last_name,"")) actor_name FROM commercial_contract_activity a LEFT JOIN users u ON u.id=a.commercial_manager_id WHERE a.contract_id=? ORDER BY a.created_at DESC LIMIT 15');
        $st->bind_param('i',$selectedId);$st->execute();$activity=$st->get_result()->fetch_all(MYSQLI_ASSOC);$st->close();
    }
}
$contracts=[];
$st=$conn->prepare("SELECT c.*,(SELECT COUNT(*) FROM commercial_contract_milestones m WHERE m.contract_id=c.id) milestone_count,(SELECT SUM(status='completed') FROM commercial_contract_milestones m WHERE m.contract_id=c.id) completed_count,(SELECT SUM(status IN ('at_risk')) FROM commercial_contract_milestones m WHERE m.contract_id=c.id) risk_count FROM commercial_contracts c WHERE c.commercial_manager_id=? ORDER BY FIELD(c.status,'active','under_review','draft','completed','expired','terminated'),c.end_date ASC");
$st->bind_param('i',$managerId);$st->execute();$contracts=$st->get_result()->fetch_all(MYSQLI_ASSOC);$st->close();

$stats=['count'=>count($contracts),'value'=>0.0,'active'=>0,'renewal'=>0,'high_risk'=>0];
$today=date('Y-m-d');$renewalLimit=date('Y-m-d',strtotime('+90 days'));
foreach($contracts as $c){
    if(in_array($c['status'],['active','under_review'],true))if($c['currency']==='ZAR')$stats['value']+=(float)$c['contract_value'];
    if($c['status']==='active')$stats['active']++;
    if($c['renewal_date'] && $c['renewal_date']>=$today && $c['renewal_date']<=$renewalLimit)$stats['renewal']++;
    if(in_array($c['risk_level'],['high','critical'],true))$stats['high_risk']++;
}
require __DIR__ . '/_layout_start.php';
?>
<style>
.cpm-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin:6px 0 20px}.cpm-head h2{margin:3px 0 5px;font-size:25px}.cpm-head p,.cpm-muted{color:var(--muted);font-size:13px}
.cpm-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:18px 0}.cpm-stat,.cpm-panel{background:var(--surface);border:1px solid var(--line);border-radius:15px;padding:16px;min-width:0}.cpm-stat span{display:block;color:var(--muted);font-size:12px}.cpm-stat strong{display:block;font-size:23px;margin-top:7px}
.cpm-panel{margin:16px 0}.cpm-panel h3{font-size:17px;margin:0 0 14px}.cpm-form{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;align-items:end}.cpm-field label{display:block;font-size:12px;font-weight:700;margin-bottom:5px}.cpm-field input,.cpm-field select,.cpm-field textarea{width:100%;padding:10px;border:1px solid var(--line);border-radius:9px;background:var(--surface);color:var(--text);font:inherit}.cpm-field textarea{min-height:76px;resize:vertical}.cpm-span2{grid-column:span 2}.cpm-btn{border:0;border-radius:9px;padding:10px 14px;background:var(--primary);color:white;font-weight:700;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:7px}.cpm-btn.alt{background:var(--surface2);color:var(--text);border:1px solid var(--line)}.cpm-btn.danger{background:#b42318}
.cpm-table-wrap{overflow:auto}table.cpm-table{width:100%;border-collapse:collapse;min-width:850px} .cpm-table th,.cpm-table td{padding:11px;border-bottom:1px solid var(--line);text-align:left;font-size:13px;vertical-align:middle}.cpm-table th{font-size:11px;text-transform:uppercase;color:var(--muted)}.cpm-pill{display:inline-block;border-radius:20px;padding:4px 8px;font-size:11px;font-weight:700;background:var(--surface2);color:var(--text)}.cpm-pill.good{background:#dcfce7;color:#166534}.cpm-pill.warn{background:#fef3c7;color:#92400e}.cpm-pill.bad{background:#fee2e2;color:#991b1b}.cpm-link{font-weight:700;color:var(--primary);text-decoration:none}.cpm-two{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(300px,.8fr);gap:16px}.cpm-progress{height:8px;border-radius:20px;background:var(--surface2);overflow:hidden;min-width:90px}.cpm-progress span{display:block;height:100%;background:var(--primary);border-radius:20px}.cpm-actions{display:flex;gap:8px;flex-wrap:wrap}.cpm-milestone{padding:12px 0;border-bottom:1px solid var(--line)}.cpm-milestone:last-child{border:0}
@media(max-width:1100px){.cpm-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.cpm-form{grid-template-columns:repeat(2,minmax(0,1fr))}.cpm-two{grid-template-columns:1fr}}
@media(max-width:650px){.cpm-stats,.cpm-form{grid-template-columns:1fr}.cpm-span2{grid-column:span 1}.cpm-head{display:block}}
</style>
<div class="cpm-head"><div><span class="cpm-muted">Commercial Operations / Contract Management</span><h2>Contracts &amp; Commercial Performance</h2><p>Manage client agreements, obligations, deliverables, renewal dates and commercial risks.</p></div><a class="cpm-btn" href="#new-contract"><i class="fas fa-plus"></i> Add contract</a></div>
<section class="cpm-stats">
 <article class="cpm-stat"><span>Contracts managed</span><strong><?= (int)$stats['count'] ?></strong></article>
 <article class="cpm-stat"><span>Active contract value (ZAR)</span><strong><?= cpm_money($stats['value']) ?></strong></article>
 <article class="cpm-stat"><span>Renewals in next 90 days</span><strong><?= (int)$stats['renewal'] ?></strong></article>
 <article class="cpm-stat"><span>High / critical risk</span><strong><?= (int)$stats['high_risk'] ?></strong></article>
</section>

<section class="cpm-panel" id="new-contract">
 <h3>Register a client agreement</h3>
 <form method="post" class="cpm-form">
  <input type="hidden" name="csrf_token" value="<?= cpm_e(cm_csrf()) ?>"><input type="hidden" name="action" value="create_contract">
  <div class="cpm-field"><label>Client / customer</label><input name="client_name" maxlength="180" required placeholder="Client organisation"></div>
  <div class="cpm-field"><label>Contract title</label><input name="contract_title" maxlength="180" required placeholder="Agreement name"></div>
  <div class="cpm-field"><label>Start date</label><input type="date" name="start_date" required></div>
  <div class="cpm-field"><label>End date</label><input type="date" name="end_date" required></div>
  <div class="cpm-field"><label>Renewal / review date</label><input type="date" name="renewal_date"></div>
  <div class="cpm-field"><label>Contract value</label><input type="number" name="contract_value" min="0" step="0.01" required value="0"></div>
  <div class="cpm-field"><label>Currency</label><select name="currency"><option value="ZAR">ZAR — South African Rand</option><option value="USD">USD</option><option value="EUR">EUR</option><option value="GBP">GBP</option></select></div>
  <div class="cpm-field"><label>Initial risk rating</label><select name="risk_level"><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option><option value="critical">Critical</option></select></div>
  <div class="cpm-field cpm-span2"><label>Scope / key obligations</label><textarea name="description" placeholder="Summarise the agreement scope and key contractual obligations"></textarea></div>
  <div><button class="cpm-btn" type="submit">Save contract</button></div>
 </form>
</section>

<section class="cpm-panel">
 <h3>Contract portfolio</h3>
 <div class="cpm-table-wrap"><table class="cpm-table"><thead><tr><th>Contract / client</th><th>Value</th><th>Term end</th><th>Renewal</th><th>Milestones</th><th>Risk</th><th>Status</th><th></th></tr></thead><tbody>
 <?php foreach($contracts as $c): $total=(int)$c['milestone_count'];$done=(int)$c['completed_count'];$pct=$total?round($done/$total*100):0;$riskClass=in_array($c['risk_level'],['high','critical'])?'bad':($c['risk_level']==='medium'?'warn':'good'); ?>
 <tr><td><strong><?= cpm_e($c['contract_title']) ?></strong><div class="cpm-muted"><?= cpm_e($c['client_name']) ?> · <?= cpm_e($c['contract_ref']) ?></div></td><td><?= cpm_money($c['contract_value'],$c['currency']) ?></td><td><?= cpm_e(date('d M Y',strtotime($c['end_date']))) ?></td><td><?= $c['renewal_date']?cpm_e(date('d M Y',strtotime($c['renewal_date']))):'—' ?></td><td><div><?= $done ?>/<?= $total ?> completed</div><div class="cpm-progress"><span style="width:<?= $pct ?>%"></span></div></td><td><span class="cpm-pill <?= $riskClass ?>"><?= cpm_e(ucfirst($c['risk_level'])) ?></span></td><td><span class="cpm-pill"><?= cpm_e(ucwords(str_replace('_',' ',$c['status']))) ?></span></td><td><a class="cpm-link" href="<?= cpm_e(url('commercial_manager/contracts.php?contract='.(int)$c['id'])) ?>">Manage</a></td></tr>
 <?php endforeach; if(!$contracts): ?><tr><td colspan="8">No contracts registered yet. Add your first client agreement above.</td></tr><?php endif; ?>
 </tbody></table></div>
</section>

<?php if($selected): ?>
<section class="cpm-panel">
 <div class="cpm-head"><div><span class="cpm-muted"><?= cpm_e($selected['contract_ref']) ?> · <?= cpm_e($selected['client_name']) ?></span><h2><?= cpm_e($selected['contract_title']) ?></h2><p><?= cpm_e($selected['description']) ?></p></div><a class="cpm-btn alt" href="<?= cpm_e(url('commercial_manager/contracts.php')) ?>">Close details</a></div>
 <div class="cpm-two">
  <div>
   <h3>Contract deliverables &amp; milestones</h3>
   <?php foreach($milestones as $m): $late=($m['status']!=='completed'&&$m['status']!=='cancelled'&&$m['due_date']<$today); ?>
   <div class="cpm-milestone"><div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap"><strong><?= cpm_e($m['milestone_title']) ?></strong><span class="cpm-pill <?= $late?'bad':($m['status']==='completed'?'good':($m['status']==='at_risk'?'warn':'')) ?>"><?= $late?'Overdue':cpm_e(ucwords(str_replace('_',' ',$m['status']))) ?></span></div><div class="cpm-muted"><?= cpm_e($m['deliverable']) ?> · Due <?= cpm_e(date('d M Y',strtotime($m['due_date']))) ?> · Owner <?= cpm_e($m['responsible_party']?:'Not assigned') ?></div>
    <form method="post" class="cpm-actions" style="margin-top:8px"><input type="hidden" name="csrf_token" value="<?= cpm_e(cm_csrf()) ?>"><input type="hidden" name="action" value="update_milestone"><input type="hidden" name="contract_id" value="<?= (int)$selectedId ?>"><input type="hidden" name="milestone_id" value="<?= (int)$m['id'] ?>"><select name="status" style="padding:8px;border:1px solid var(--line);border-radius:8px;background:var(--surface);color:var(--text)"><?php foreach(['pending','in_progress','completed','at_risk','cancelled'] as $v): ?><option value="<?= $v ?>" <?= $m['status']===$v?'selected':'' ?>><?= ucwords(str_replace('_',' ',$v)) ?></option><?php endforeach; ?></select><input name="completion_note" placeholder="Update note" style="padding:8px;border:1px solid var(--line);border-radius:8px;background:var(--surface);color:var(--text);min-width:150px"><button class="cpm-btn alt" type="submit">Update</button></form>
   </div>
   <?php endforeach; if(!$milestones): ?><p class="cpm-muted">No milestones recorded. Add the first deliverable using the form.</p><?php endif; ?>
   <h3 style="margin-top:20px">Add milestone / deliverable</h3>
   <form method="post" class="cpm-form"><input type="hidden" name="csrf_token" value="<?= cpm_e(cm_csrf()) ?>"><input type="hidden" name="action" value="add_milestone"><input type="hidden" name="contract_id" value="<?= (int)$selectedId ?>">
    <div class="cpm-field"><label>Milestone title</label><input name="milestone_title" required maxlength="180"></div><div class="cpm-field"><label>Due date</label><input type="date" name="due_date" required></div><div class="cpm-field"><label>Responsible party</label><input name="responsible_party" maxlength="160"></div><div class="cpm-field"><label>Weight (%)</label><input type="number" name="weight_percent" min="0" max="100" step="0.01" value="0"></div><div class="cpm-field cpm-span2"><label>Expected deliverable / acceptance criteria</label><textarea name="deliverable"></textarea></div><div><button class="cpm-btn" type="submit">Add deliverable</button></div>
   </form>
  </div>
  <div>
   <h3>Commercial risk &amp; contract status</h3>
   <form method="post" class="cpm-form" style="grid-template-columns:1fr"><input type="hidden" name="csrf_token" value="<?= cpm_e(cm_csrf()) ?>"><input type="hidden" name="action" value="update_contract"><input type="hidden" name="contract_id" value="<?= (int)$selectedId ?>">
    <div class="cpm-field"><label>Contract status</label><select name="contract_status"><?php foreach(['draft','active','under_review','completed','expired','terminated'] as $v): ?><option value="<?= $v ?>" <?= $selected['status']===$v?'selected':'' ?>><?= ucwords(str_replace('_',' ',$v)) ?></option><?php endforeach; ?></select></div>
    <div class="cpm-field"><label>Risk rating</label><select name="risk_level"><?php foreach(['low','medium','high','critical'] as $v): ?><option value="<?= $v ?>" <?= $selected['risk_level']===$v?'selected':'' ?>><?= ucfirst($v) ?></option><?php endforeach; ?></select></div>
    <div class="cpm-field"><label>Renewal / review date</label><input type="date" name="renewal_date" value="<?= cpm_e($selected['renewal_date']) ?>"></div>
    <div class="cpm-field"><label>Risk notes / mitigation action</label><textarea name="risk_notes"><?= cpm_e($selected['risk_notes']) ?></textarea></div><button class="cpm-btn" type="submit">Save contract controls</button>
   </form>
   <h3 style="margin-top:22px">Recent contract activity</h3>
   <?php foreach($activity as $a): ?><div class="cpm-milestone"><strong><?= cpm_e(ucwords(str_replace('_',' ',$a['action']))) ?></strong><div class="cpm-muted"><?= cpm_e($a['details']) ?></div><div class="cpm-muted"><?= cpm_e($a['created_at']) ?></div></div><?php endforeach; if(!$activity): ?><p class="cpm-muted">No activity recorded yet.</p><?php endif; ?>
  </div>
 </div>
</section>
<?php endif; ?>
<?php require __DIR__ . '/_layout_end.php'; ?>
