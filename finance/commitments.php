<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('finance_officer');
$user=current_user(); $flashes=render_flashes(); require_once __DIR__.'/_helpers.php';
$conn=Database::getConnection(); $uid=(int)($user['id']??$user['user_id']??0);
if($uid<1){http_response_code(403);exit('Invalid Finance Officer account.');}
$currentPage='commitments'; $pageTitle='Financial Commitments & Purchase Orders';
function fc_e($v):string{return htmlspecialchars((string)($v??''),ENT_QUOTES,'UTF-8');}
function fc_money($v):string{return 'R '.number_format((float)$v,2);}
function fc_hist(mysqli $db,int $id,int $uid,string $action,string $note='',?float $amount=null):void{
 $s=$db->prepare('INSERT INTO finance_commitment_history(commitment_id,action,acted_by,action_note,amount) VALUES(?,?,?,?,?)');
 if(!$s)throw new RuntimeException('Could not prepare history record.');
 $s->bind_param('iissd',$id,$action,$uid,$note,$amount);
 if(!$s->execute())throw new RuntimeException('Could not save history record.');$s->close();
}
function fc_redirect():never{header('Location: '.url('finance/commitments.php'));exit;}
$tables=['finance_commitments','finance_commitment_history','finance_commitment_payments'];
$missing=array_filter($tables,fn($t)=>!fo_has_table($conn,$t));
if($missing){require __DIR__.'/_layout_start.php';?>
<div class="fo-page-header"><div><span>Finance / Procurement</span><h2>Financial Commitments &amp; Purchase Orders</h2><p>Monitor approved purchase orders, supplier commitments and outstanding obligations.</p></div></div>
<div class="fo-alert fo-alert--warning"><i class="fas fa-database"></i><div>Database tables are not installed. Import <strong>finance_commitments_migration.sql</strong> from this package and reload.</div></div>
<?php require __DIR__.'/_layout_end.php';exit;}

if($_SERVER['REQUEST_METHOD']==='POST'){
 fo_verify_csrf();$action=(string)($_POST['action']??'');
 try{
  if($action==='create'){
   $po=trim((string)($_POST['purchase_order_no']??''));$supplier=trim((string)($_POST['supplier_name']??''));
   $desc=trim((string)($_POST['description']??''));$date=(string)($_POST['order_date']??'');
   $delivery=(string)($_POST['expected_delivery_date']??'');$cost=(float)($_POST['expected_cost']??0);
   $programme=max(0,(int)($_POST['programme_id']??0));$cohort=max(0,(int)($_POST['cohort_id']??0));$budget=max(0,(int)($_POST['budget_id']??0));
   $dept=trim((string)($_POST['department']??''));$supplierRef=trim((string)($_POST['supplier_reference']??''));
   if($po===''||$supplier===''||$desc===''||$cost<=0||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))throw new RuntimeException('Complete the PO number, supplier, description, valid order date and positive expected cost.');
   $ref='FC-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(3)));$pid=$programme?:null;$cid=$cohort?:null;$bid=$budget?:null;
   $s=$conn->prepare("INSERT INTO finance_commitments(commitment_no,purchase_order_no,supplier_name,supplier_reference,programme_id,cohort_id,budget_id,department,description,order_date,expected_delivery_date,expected_cost,status,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,NULLIF(?,''),?,'draft',?)");
   if(!$s)throw new RuntimeException('Unable to prepare commitment.');
   $s->bind_param('ssssiiissssdi',$ref,$po,$supplier,$supplierRef,$pid,$cid,$bid,$dept,$desc,$date,$delivery,$cost,$uid);
   if(!$s->execute()){$err=$s->error;$s->close();throw new RuntimeException(str_contains($err,'Duplicate')?'PO number already exists.':'Unable to save commitment.');}
   $id=(int)$s->insert_id;$s->close();fc_hist($conn,$id,$uid,'created','Commitment saved as draft.',$cost);
   fo_activity($conn,$uid,'commitment_created','Created PO commitment '.$po,'finance_commitment',$id);
   fo_flash('success','Commitment saved as draft.');
  }else{
   $id=max(0,(int)($_POST['commitment_id']??0));if(!$id)throw new RuntimeException('Invalid commitment.');
   $conn->begin_transaction();$s=$conn->prepare('SELECT * FROM finance_commitments WHERE id=? FOR UPDATE');$s->bind_param('i',$id);$s->execute();$row=$s->get_result()->fetch_assoc();$s->close();
   if(!$row)throw new RuntimeException('Commitment not found.');
   $note=trim((string)($_POST['action_note']??''));
   if(in_array($action,['return','reject','cancel'],true)&&$note==='')throw new RuntimeException('A reason is required.');
   if($action==='submit'&&in_array($row['status'],['draft','returned'],true)){
    $s=$conn->prepare("UPDATE finance_commitments SET status='submitted',submitted_at=NOW() WHERE id=?");$s->bind_param('i',$id);$s->execute();$s->close();fc_hist($conn,$id,$uid,'submitted',$note?:'Submitted for approval.');
   }elseif($action==='approve'&&$row['status']==='submitted'){
    if((int)$row['created_by']===$uid)throw new RuntimeException('Maker/checker control prevents self-approval.');
    $s=$conn->prepare("UPDATE finance_commitments SET status='approved',approved_by=?,approved_at=NOW() WHERE id=? AND status='submitted'");$s->bind_param('ii',$uid,$id);$s->execute();if($s->affected_rows!==1)throw new RuntimeException('Status changed; reload and retry.');$s->close();fc_hist($conn,$id,$uid,'approved',$note?:'Purchase order commitment approved.');
   }elseif($action==='return'&&$row['status']==='submitted'){
    $s=$conn->prepare("UPDATE finance_commitments SET status='returned' WHERE id=?");$s->bind_param('i',$id);$s->execute();$s->close();fc_hist($conn,$id,$uid,'returned',$note);
   }elseif($action==='reject'&&$row['status']==='submitted'){
    $s=$conn->prepare("UPDATE finance_commitments SET status='rejected' WHERE id=?");$s->bind_param('i',$id);$s->execute();$s->close();fc_hist($conn,$id,$uid,'rejected',$note);
   }elseif($action==='payment'&&$row['status']==='approved'){
    $amount=(float)($_POST['payment_amount']??0);$ref=trim((string)($_POST['payment_reference']??''));$date=(string)($_POST['payment_date']??date('Y-m-d'));
    $remaining=(float)$row['expected_cost']-(float)$row['paid_amount'];
    if($amount<=0||$amount>$remaining+0.001||$ref===''||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))throw new RuntimeException('Enter a valid payment reference, date and amount not exceeding the outstanding commitment.');
    $ps=$conn->prepare('INSERT INTO finance_commitment_payments(commitment_id,payment_reference,payment_date,amount,recorded_by,notes) VALUES(?,?,?,?,?,?)');$ps->bind_param('issdis',$id,$ref,$date,$amount,$uid,$note);if(!$ps->execute())throw new RuntimeException('Payment reference may already exist or payment could not be recorded.');$ps->close();
    $paid=(float)$row['paid_amount']+$amount;$us=$conn->prepare('UPDATE finance_commitments SET paid_amount=? WHERE id=?');$us->bind_param('di',$paid,$id);$us->execute();$us->close();fc_hist($conn,$id,$uid,'payment_recorded',$note?:'Payment recorded: '.$ref,$amount);
   }elseif($action==='close'&&$row['status']==='approved'&&((float)$row['expected_cost']-(float)$row['paid_amount'])<=0.001){
    $s=$conn->prepare("UPDATE finance_commitments SET status='closed' WHERE id=?");$s->bind_param('i',$id);$s->execute();$s->close();fc_hist($conn,$id,$uid,'closed','Fully settled and closed.');
   }elseif($action==='cancel'&&in_array($row['status'],['draft','returned','submitted','approved'],true)&&(float)$row['paid_amount']<=0){
    $s=$conn->prepare("UPDATE finance_commitments SET status='cancelled' WHERE id=?");$s->bind_param('i',$id);$s->execute();$s->close();fc_hist($conn,$id,$uid,'cancelled',$note);
   }else throw new RuntimeException('Action not allowed for this commitment status.');
   $conn->commit();fo_activity($conn,$uid,'commitment_'.$action,'Performed '.$action.' on PO '.$row['purchase_order_no'],'finance_commitment',$id);fo_flash('success','Commitment updated.');
  }
 }catch(Throwable $e){try{$conn->rollback();}catch(Throwable $ignore){}error_log('Commitments module: '.$e->getMessage());fo_flash('danger',$e->getMessage());}
 fc_redirect();
}
$status=(string)($_GET['status']??'');$programmeFilter=max(0,(int)($_GET['programme_id']??0));$q=trim((string)($_GET['q']??''));
$where=[];$types='';$params=[];
if($status!==''){$where[]='fc.status=?';$types.='s';$params[]=$status;}
if($programmeFilter){$where[]='fc.programme_id=?';$types.='i';$params[]=$programmeFilter;}
if($q!==''){$where[]='(fc.purchase_order_no LIKE ? OR fc.commitment_no LIKE ? OR fc.supplier_name LIKE ? OR fc.description LIKE ?)';$types.='ssss';$v='%'.$q.'%';array_push($params,$v,$v,$v,$v);}
$sql="SELECT fc.*,p.name programme_name,c.name cohort_name,u.first_name maker_first,u.last_name maker_last FROM finance_commitments fc LEFT JOIN programmes p ON p.id=fc.programme_id LEFT JOIN cohorts c ON c.id=fc.cohort_id LEFT JOIN users u ON u.id=fc.created_by".($where?' WHERE '.implode(' AND ',$where):'')." ORDER BY FIELD(fc.status,'submitted','approved','returned','draft','rejected','closed','cancelled'),fc.order_date DESC LIMIT 500";
$st=$conn->prepare($sql);$rows=[];if($st){if($types)$st->bind_param($types,...$params);$st->execute();$rows=$st->get_result()->fetch_all(MYSQLI_ASSOC);$st->close();}
$programmes=[];$pr=$conn->query('SELECT id,name FROM programmes ORDER BY name');if($pr)$programmes=$pr->fetch_all(MYSQLI_ASSOC);
$tot=$conn->query("SELECT COUNT(*) total,SUM(status='submitted') submitted,SUM(status='approved') approved,SUM(CASE WHEN status IN ('approved','submitted','returned') THEN GREATEST(expected_cost-paid_amount,0) ELSE 0 END) outstanding FROM finance_commitments")->fetch_assoc();
$history=[];if($rows){$ids=implode(',',array_map(fn($r)=>(int)$r['id'],$rows));$hr=$conn->query("SELECT h.*,u.first_name,u.last_name FROM finance_commitment_history h LEFT JOIN users u ON u.id=h.acted_by WHERE h.commitment_id IN ($ids) ORDER BY h.created_at DESC");while($hr&&$h=$hr->fetch_assoc())$history[(int)$h['commitment_id']][]=$h;}
require __DIR__.'/_layout_start.php';
?>
<style>
.fc-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:16px 0}.fc-card{background:var(--surface,#fff);border:1px solid var(--border,#e5e7eb);border-radius:13px;padding:16px}.fc-card small{display:block;color:var(--muted,#64748b)}.fc-card strong{display:block;font-size:21px;margin-top:6px}.fc-form{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:11px}.fc-form label{display:block;font-size:12px;font-weight:700;margin-bottom:5px}.fc-form input,.fc-form select,.fc-form textarea,.fc-filter input,.fc-filter select,.fc-actions input{box-sizing:border-box;width:100%;padding:9px;border:1px solid var(--border,#cbd5e1);border-radius:8px;background:var(--surface,#fff);color:inherit}.fc-span2{grid-column:span 2}.fc-span4{grid-column:1/-1}.fc-btn{padding:8px 10px;border:1px solid var(--border,#cbd5e1);border-radius:8px;background:var(--surface,#fff);color:inherit;cursor:pointer;font-weight:650}.fc-btn.primary{background:var(--primary,#1d4ed8);color:white}.fc-btn.danger{color:#b91c1c}.fc-toolbar,.fc-actions,.fc-inline{display:flex;gap:7px;align-items:center;flex-wrap:wrap}.fc-table-wrap{overflow:auto}.fc-table{width:100%;min-width:1000px;border-collapse:collapse}.fc-table th,.fc-table td{padding:10px;text-align:left;border-bottom:1px solid var(--border,#e5e7eb);font-size:13px;vertical-align:top}.fc-table th{font-size:11px;color:var(--muted,#64748b);text-transform:uppercase}.fc-muted{font-size:12px;color:var(--muted,#64748b)}.fc-pill{display:inline-block;padding:4px 8px;border-radius:20px;background:var(--surface-secondary,#f1f5f9);font-size:11px;text-transform:capitalize}.fc-details{margin-top:7px;padding:9px;background:var(--surface-secondary,#f8fafc);border-radius:8px}.fc-details summary{cursor:pointer;font-weight:700}@media(max-width:850px){.fc-grid{grid-template-columns:repeat(2,1fr)}.fc-form{grid-template-columns:repeat(2,1fr)}}@media(max-width:560px){.fc-grid,.fc-form{grid-template-columns:1fr}.fc-span2,.fc-span4{grid-column:auto}}
</style>
<div class="fo-page-header"><div><span>Finance / Procurement &amp; Commitments</span><h2>Financial Commitments &amp; Purchase Orders</h2><p>Record supplier commitments, monitor approved purchase orders and track outstanding obligations before payments are processed.</p></div><a class="fc-btn" href="<?=fc_e(url('finance/reports.php'))?>"><i class="fas fa-chart-pie"></i> Management Reports</a></div>
<div class="fc-grid">
 <div class="fc-card"><small>Total commitments</small><strong><?=number_format((int)$tot['total'])?></strong></div>
 <div class="fc-card"><small>Awaiting approval</small><strong><?=number_format((int)$tot['submitted'])?></strong></div>
 <div class="fc-card"><small>Approved purchase orders</small><strong><?=number_format((int)$tot['approved'])?></strong></div>
 <div class="fc-card"><small>Outstanding obligations</small><strong><?=fc_money($tot['outstanding'])?></strong></div>
</div>
<section class="fc-card" style="margin:16px 0"><h3><i class="fas fa-file-circle-plus"></i> Record a financial commitment</h3>
<form method="post" class="fc-form">
<input type="hidden" name="csrf_token" value="<?=fc_e(fo_csrf_token())?>"><input type="hidden" name="action" value="create">
<div><label>Purchase order number *</label><input name="purchase_order_no" required maxlength="100"></div>
<div><label>Supplier name *</label><input name="supplier_name" required maxlength="180"></div>
<div><label>Supplier reference</label><input name="supplier_reference" maxlength="120"></div>
<div><label>Department</label><input name="department" maxlength="150"></div>
<div><label>Programme</label><select name="programme_id"><option value="0">Select programme</option><?php foreach($programmes as $p):?><option value="<?=(int)$p['id']?>"><?=fc_e($p['name'])?></option><?php endforeach;?></select></div>
<div><label>Cohort ID (optional)</label><input type="number" min="1" name="cohort_id" placeholder="Database ID"></div>
<div><label>Budget ID (optional)</label><input type="number" min="1" name="budget_id" placeholder="Database ID"></div>
<div><label>Order date *</label><input type="date" name="order_date" required value="<?=date('Y-m-d')?>"></div>
<div><label>Expected delivery date</label><input type="date" name="expected_delivery_date"></div>
<div><label>Expected cost (ZAR) *</label><input type="number" name="expected_cost" min=".01" step=".01" required></div>
<div class="fc-span2"><label>Description / goods or services *</label><textarea name="description" rows="2" required></textarea></div>
<div class="fc-span4"><button class="fc-btn primary"><i class="fas fa-save"></i> Save draft commitment</button> <span class="fc-muted">Drafts are not counted as approved obligations until approved.</span></div>
</form></section>
<section style="margin:18px 0"><div class="fc-toolbar" style="justify-content:space-between;margin-bottom:10px"><h3>Commitment register</h3>
<form method="get" class="fc-toolbar fc-filter"><input name="q" value="<?=fc_e($q)?>" placeholder="Search PO, supplier or description"><select name="status"><option value="">All statuses</option><?php foreach(['draft','submitted','approved','returned','rejected','closed','cancelled'] as $x):?><option value="<?=$x?>" <?=$status===$x?'selected':''?>><?=ucfirst($x)?></option><?php endforeach;?></select><select name="programme_id"><option value="0">All programmes</option><?php foreach($programmes as $p):?><option value="<?=(int)$p['id']?>" <?=$programmeFilter===(int)$p['id']?'selected':''?>><?=fc_e($p['name'])?></option><?php endforeach;?></select><button class="fc-btn primary">Filter</button><a class="fc-btn" href="<?=fc_e(url('finance/commitments.php'))?>">Reset</a></form></div>
<div class="fc-card fc-table-wrap"><table class="fc-table"><thead><tr><th>PO / Commitment</th><th>Supplier / programme</th><th>Expected</th><th>Paid</th><th>Outstanding</th><th>Status</th><th>Actions / history</th></tr></thead><tbody>
<?php if(!$rows):?><tr><td colspan="7" class="fc-muted">No purchase order commitments found.</td></tr><?php endif;?>
<?php foreach($rows as $r):$out=max(0,(float)$r['expected_cost']-(float)$r['paid_amount']);$review=$r['status']==='submitted'&&(int)$r['created_by']!==$uid;?>
<tr><td><strong><?=fc_e($r['purchase_order_no'])?></strong><div class="fc-muted"><?=fc_e($r['commitment_no'])?></div></td><td><strong><?=fc_e($r['supplier_name'])?></strong><div class="fc-muted"><?=fc_e($r['programme_name']?:'No programme')?> / <?=fc_e($r['cohort_name']?:'No cohort')?></div></td><td><?=fc_money($r['expected_cost'])?></td><td><?=fc_money($r['paid_amount'])?></td><td><strong><?=fc_money($out)?></strong></td><td><span class="fc-pill"><?=fc_e(str_replace('_',' ',$r['status']))?></span><div class="fc-muted"><?=fc_e(fo_date($r['order_date']))?></div></td><td><div class="fc-inline">
<?php if(in_array($r['status'],['draft','returned'],true)):?><form method="post"><input type="hidden" name="csrf_token" value="<?=fc_e(fo_csrf_token())?>"><input type="hidden" name="commitment_id" value="<?=(int)$r['id']?>"><input type="hidden" name="action" value="submit"><button class="fc-btn primary">Submit</button></form><?php endif;?>
<?php if($review):?><form method="post"><input type="hidden" name="csrf_token" value="<?=fc_e(fo_csrf_token())?>"><input type="hidden" name="commitment_id" value="<?=(int)$r['id']?>"><input type="hidden" name="action" value="approve"><button class="fc-btn primary">Approve</button></form>
<?php foreach(['return'=>'Return','reject'=>'Reject'] as $a=>$label):?><form method="post"><input type="hidden" name="csrf_token" value="<?=fc_e(fo_csrf_token())?>"><input type="hidden" name="commitment_id" value="<?=(int)$r['id']?>"><input type="hidden" name="action" value="<?=$a?>"><input name="action_note" placeholder="<?=$label?> reason" required><button class="fc-btn"><?=$label?></button></form><?php endforeach;endif;?>
<?php if($r['status']==='approved'&&$out>0):?><details class="fc-details"><summary>Record payment</summary><form method="post" class="fc-actions"><input type="hidden" name="csrf_token" value="<?=fc_e(fo_csrf_token())?>"><input type="hidden" name="commitment_id" value="<?=(int)$r['id']?>"><input type="hidden" name="action" value="payment"><input name="payment_reference" placeholder="Payment reference" required><input type="date" name="payment_date" value="<?=date('Y-m-d')?>"><input type="number" name="payment_amount" min=".01" max="<?=fc_e(number_format($out,2,'.',''))?>" step=".01" placeholder="Amount" required><button class="fc-btn primary">Record</button></form></details><?php elseif($r['status']==='approved'&&$out<=0):?><form method="post"><input type="hidden" name="csrf_token" value="<?=fc_e(fo_csrf_token())?>"><input type="hidden" name="commitment_id" value="<?=(int)$r['id']?>"><input type="hidden" name="action" value="close"><button class="fc-btn">Close</button></form><?php endif;?>
<?php if(in_array($r['status'],['draft','returned','submitted','approved'],true)&&(float)$r['paid_amount']<=0):?><form method="post"><input type="hidden" name="csrf_token" value="<?=fc_e(fo_csrf_token())?>"><input type="hidden" name="commitment_id" value="<?=(int)$r['id']?>"><input type="hidden" name="action" value="cancel"><input type="hidden" name="action_note" value="Cancelled by Finance Officer"><button class="fc-btn danger" onclick="return confirm('Cancel this commitment?')">Cancel</button></form><?php endif;?>
</div><details class="fc-details"><summary>Decision history (<?=count($history[(int)$r['id']]??[])?>)</summary><?php foreach($history[(int)$r['id']]??[] as $h):?><div style="padding:6px 0;border-bottom:1px solid var(--border,#ddd)"><strong><?=fc_e(ucwords(str_replace('_',' ',$h['action'])))?></strong> · <?=fc_e(trim(($h['first_name']??'').' '.($h['last_name']??''))?:'User #'.$h['acted_by'])?><div class="fc-muted"><?=fc_e(fo_datetime($h['created_at']))?> · <?=fc_e($h['action_note'])?><?php if($h['amount']!==null):?> · <?=fc_money($h['amount'])?><?php endif;?></div></div><?php endforeach;?></details></td></tr>
<?php endforeach;?></tbody></table></div></section>
<div class="fc-card fc-muted"><strong>Control:</strong> A creator cannot approve their own PO. Payments can only be recorded against approved commitments and are capped at the outstanding expected cost. This module records payments; it does not initiate bank transfers. The page uses the existing Finance Officer role gate.</div>
<?php require __DIR__.'/_layout_end.php';?>
