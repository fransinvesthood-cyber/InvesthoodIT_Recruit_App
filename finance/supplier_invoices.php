<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('finance_officer');
$user=current_user(); $flashes=render_flashes(); require_once __DIR__.'/_helpers.php';
$conn=Database::getConnection(); $uid=(int)($user['id']??$user['user_id']??0);
if($uid<1){http_response_code(403);exit('Invalid Finance Officer account.');}
$currentPage='supplier_invoices'; $pageTitle='Supplier Invoice Management & Accounts Payable';
function ap_e($v):string{return htmlspecialchars((string)($v??''),ENT_QUOTES,'UTF-8');}
function ap_money($v):string{return 'R '.number_format((float)$v,2);}
function ap_redirect():never{header('Location: '.url('finance/supplier_invoices.php'));exit;}
function ap_history(mysqli $db,int $id,int $uid,string $action,string $note='',?float $amount=null):void{
 $s=$db->prepare('INSERT INTO finance_supplier_invoice_history(invoice_id,action,acted_by,action_note,amount) VALUES(?,?,?,?,?)');
 if(!$s)throw new RuntimeException('Could not prepare invoice history.');
 $s->bind_param('iissd',$id,$action,$uid,$note,$amount);
 if(!$s->execute())throw new RuntimeException('Could not save invoice history.');$s->close();
}
$required=['finance_supplier_invoices','finance_supplier_invoice_history','finance_supplier_invoice_payments'];
if(array_filter($required,fn($t)=>!fo_has_table($conn,$t))){
 require __DIR__.'/_layout_start.php';?>
 <div class="fo-page-header"><div><span>Finance / Accounts Payable</span><h2>Supplier Invoice Management</h2><p>Capture, verify, approve and monitor supplier invoices and payment liabilities.</p></div></div>
 <div class="fo-alert fo-alert--warning"><i class="fas fa-database"></i><div>Invoice database tables are not installed. Import <strong>finance_supplier_invoices_migration.sql</strong> from this package, then reload.</div></div>
 <?php require __DIR__.'/_layout_end.php';exit;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
 fo_verify_csrf();$action=(string)($_POST['action']??'');
 try{
  if($action==='create'){
   $supplier=trim((string)($_POST['supplier_name']??''));$supplierTax=trim((string)($_POST['supplier_tax_number']??''));
   $invoiceNo=trim((string)($_POST['supplier_invoice_no']??''));$po=trim((string)($_POST['purchase_order_no']??''));
   $commitment=max(0,(int)($_POST['commitment_id']??0));$programme=max(0,(int)($_POST['programme_id']??0));$cohort=max(0,(int)($_POST['cohort_id']??0));
   $department=trim((string)($_POST['department']??''));$invoiceDate=(string)($_POST['invoice_date']??'');$dueDate=(string)($_POST['due_date']??'');
   $subtotal=(float)($_POST['subtotal']??0);$tax=(float)($_POST['tax_amount']??0);$total=(float)($_POST['total_amount']??0);
   $desc=trim((string)($_POST['description']??''));$attachment=trim((string)($_POST['attachment_path']??''));
   if($supplier===''||$invoiceNo===''||$desc===''||$total<=0||$subtotal<0||$tax<0||abs(($subtotal+$tax)-$total)>0.02||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$invoiceDate)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$dueDate)||$dueDate<$invoiceDate)throw new RuntimeException('Enter supplier, invoice number, description, valid dates and totals where subtotal plus tax equals invoice total.');
   $commitmentId=$commitment?:null;$programmeId=$programme?:null;$cohortId=$cohort?:null;
   $ref='INV-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(3)));
   $s=$conn->prepare("INSERT INTO finance_supplier_invoices(invoice_ref,supplier_name,supplier_tax_number,supplier_invoice_no,purchase_order_no,commitment_id,programme_id,cohort_id,department,invoice_date,due_date,subtotal,tax_amount,total_amount,description,attachment_path,status,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'draft',?)");
   if(!$s)throw new RuntimeException('Unable to prepare supplier invoice.');
   $s->bind_param('sssssiiisssdddsssi',$ref,$supplier,$supplierTax,$invoiceNo,$po,$commitmentId,$programmeId,$cohortId,$department,$invoiceDate,$dueDate,$subtotal,$tax,$total,$desc,$attachment,$uid);
   if(!$s->execute()){$err=$s->error;$s->close();throw new RuntimeException(str_contains($err,'Duplicate')?'This supplier invoice number has already been captured.':'Unable to save supplier invoice.');}
   $id=(int)$s->insert_id;$s->close();ap_history($conn,$id,$uid,'created','Invoice captured as draft.',$total);
   fo_activity($conn,$uid,'supplier_invoice_created','Captured supplier invoice '.$invoiceNo,'supplier_invoice',$id);
   fo_flash('success','Supplier invoice captured as draft.');
  }else{
   $id=max(0,(int)($_POST['invoice_id']??0));if(!$id)throw new RuntimeException('Invalid invoice reference.');
   $conn->begin_transaction();$s=$conn->prepare('SELECT * FROM finance_supplier_invoices WHERE id=? FOR UPDATE');$s->bind_param('i',$id);$s->execute();$row=$s->get_result()->fetch_assoc();$s->close();
   if(!$row)throw new RuntimeException('Supplier invoice not found.');
   $note=trim((string)($_POST['action_note']??''));
   if(in_array($action,['return','reject','cancel'],true)&&$note==='')throw new RuntimeException('A reason is required for this action.');
   if($action==='submit'&&in_array($row['status'],['draft','returned'],true)){
    $s=$conn->prepare("UPDATE finance_supplier_invoices SET status='submitted',submitted_at=NOW() WHERE id=?");$s->bind_param('i',$id);$s->execute();$s->close();ap_history($conn,$id,$uid,'submitted',$note?:'Submitted for invoice verification.');
   }elseif($action==='verify'&&$row['status']==='submitted'){
    if((int)$row['created_by']===$uid)throw new RuntimeException('The invoice creator cannot verify their own invoice.');
    $s=$conn->prepare("UPDATE finance_supplier_invoices SET status='verified',verified_by=?,verified_at=NOW() WHERE id=? AND status='submitted'");$s->bind_param('ii',$uid,$id);$s->execute();if($s->affected_rows!==1)throw new RuntimeException('Invoice status changed; reload and retry.');$s->close();ap_history($conn,$id,$uid,'verified',$note?:'Invoice details and supporting reference verified.');
   }elseif($action==='approve'&&$row['status']==='verified'){
    if((int)$row['created_by']===$uid||(int)$row['verified_by']===$uid)throw new RuntimeException('Approval must be completed by someone other than the creator and verifier.');
    $s=$conn->prepare("UPDATE finance_supplier_invoices SET status='approved',approved_by=?,approved_at=NOW() WHERE id=? AND status='verified'");$s->bind_param('ii',$uid,$id);$s->execute();if($s->affected_rows!==1)throw new RuntimeException('Invoice status changed; reload and retry.');$s->close();ap_history($conn,$id,$uid,'approved',$note?:'Invoice approved for payment.');
   }elseif($action==='return'&&in_array($row['status'],['submitted','verified'],true)){
    $s=$conn->prepare("UPDATE finance_supplier_invoices SET status='returned' WHERE id=?");$s->bind_param('i',$id);$s->execute();$s->close();ap_history($conn,$id,$uid,'returned',$note);
   }elseif($action==='reject'&&in_array($row['status'],['submitted','verified'],true)){
    $s=$conn->prepare("UPDATE finance_supplier_invoices SET status='rejected' WHERE id=?");$s->bind_param('i',$id);$s->execute();$s->close();ap_history($conn,$id,$uid,'rejected',$note);
   }elseif($action==='payment'&&in_array($row['status'],['approved','partially_paid'],true)){
    $amount=(float)($_POST['payment_amount']??0);$paymentRef=trim((string)($_POST['payment_reference']??''));$paymentDate=(string)($_POST['payment_date']??date('Y-m-d'));
    $outstanding=(float)$row['total_amount']-(float)$row['paid_amount'];
    if($amount<=0||$amount>$outstanding+0.001||$paymentRef===''||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$paymentDate))throw new RuntimeException('Enter a valid payment reference, date and amount no greater than the outstanding invoice balance.');
    $ps=$conn->prepare('INSERT INTO finance_supplier_invoice_payments(invoice_id,payment_reference,payment_date,amount,recorded_by,notes) VALUES(?,?,?,?,?,?)');$ps->bind_param('issdis',$id,$paymentRef,$paymentDate,$amount,$uid,$note);
    if(!$ps->execute()){$ps->close();throw new RuntimeException('Payment reference already exists or payment could not be recorded.');}$ps->close();
    $newPaid=(float)$row['paid_amount']+$amount;$newStatus=($newPaid+0.001>=(float)$row['total_amount'])?'paid':'partially_paid';
    $us=$conn->prepare('UPDATE finance_supplier_invoices SET paid_amount=?,status=? WHERE id=?');$us->bind_param('dsi',$newPaid,$newStatus,$id);$us->execute();$us->close();
    ap_history($conn,$id,$uid,'payment_recorded',$note?:'Payment recorded: '.$paymentRef,$amount);
   }elseif($action==='cancel'&&in_array($row['status'],['draft','returned','submitted'],true)&&(float)$row['paid_amount']<=0){
    $s=$conn->prepare("UPDATE finance_supplier_invoices SET status='cancelled' WHERE id=?");$s->bind_param('i',$id);$s->execute();$s->close();ap_history($conn,$id,$uid,'cancelled',$note);
   }else throw new RuntimeException('This action is not permitted for the current invoice status.');
   $conn->commit();fo_activity($conn,$uid,'supplier_invoice_'.$action,'Performed '.$action.' on invoice '.$row['supplier_invoice_no'],'supplier_invoice',$id);fo_flash('success','Supplier invoice updated.');
  }
 }catch(Throwable $e){try{$conn->rollback();}catch(Throwable $ignore){}error_log('Supplier invoice workflow: '.$e->getMessage());fo_flash('danger',$e->getMessage());}
 ap_redirect();
}

$status=(string)($_GET['status']??'');$supplierFilter=trim((string)($_GET['supplier']??''));$q=trim((string)($_GET['q']??''));
$where=[];$types='';$params=[];
if($status!==''){$where[]='i.status=?';$types.='s';$params[]=$status;}
if($supplierFilter!==''){$where[]='i.supplier_name LIKE ?';$types.='s';$params[]='%'.$supplierFilter.'%';}
if($q!==''){$where[]='(i.supplier_invoice_no LIKE ? OR i.invoice_ref LIKE ? OR i.purchase_order_no LIKE ? OR i.description LIKE ?)';$types.='ssss';$v='%'.$q.'%';array_push($params,$v,$v,$v,$v);}
$sql="SELECT i.*,p.name programme_name,c.name cohort_name,cu.first_name maker_first,cu.last_name maker_last FROM finance_supplier_invoices i LEFT JOIN programmes p ON p.id=i.programme_id LEFT JOIN cohorts c ON c.id=i.cohort_id LEFT JOIN users cu ON cu.id=i.created_by".($where?' WHERE '.implode(' AND ',$where):'')." ORDER BY FIELD(i.status,'submitted','verified','approved','partially_paid','returned','draft','rejected','paid','cancelled'),i.due_date ASC LIMIT 500";
$st=$conn->prepare($sql);$rows=[];if($st){if($types)$st->bind_param($types,...$params);$st->execute();$rows=$st->get_result()->fetch_all(MYSQLI_ASSOC);$st->close();}
$programmes=[];$pr=$conn->query('SELECT id,name FROM programmes ORDER BY name');if($pr)$programmes=$pr->fetch_all(MYSQLI_ASSOC);
$stats=$conn->query("SELECT COUNT(*) invoice_count,
 SUM(status IN ('approved','partially_paid')) payable_count,
 SUM(CASE WHEN status IN ('approved','partially_paid') THEN GREATEST(total_amount-paid_amount,0) ELSE 0 END) outstanding,
 SUM(CASE WHEN status IN ('approved','partially_paid') AND due_date<CURDATE() THEN GREATEST(total_amount-paid_amount,0) ELSE 0 END) overdue,
 SUM(status='submitted') awaiting_verification,
 SUM(status='verified') awaiting_approval
 FROM finance_supplier_invoices")->fetch_assoc();
$history=[];$payments=[];
if($rows){$ids=implode(',',array_map(fn($r)=>(int)$r['id'],$rows));
 $hr=$conn->query("SELECT h.*,u.first_name,u.last_name FROM finance_supplier_invoice_history h LEFT JOIN users u ON u.id=h.acted_by WHERE h.invoice_id IN ($ids) ORDER BY h.created_at DESC");while($hr&&$h=$hr->fetch_assoc())$history[(int)$h['invoice_id']][]=$h;
 $pay=$conn->query("SELECT * FROM finance_supplier_invoice_payments WHERE invoice_id IN ($ids) ORDER BY payment_date DESC,id DESC");while($pay&&$p=$pay->fetch_assoc())$payments[(int)$p['invoice_id']][]=$p;
}
$supplierHistory=[];$sh=$conn->query("SELECT supplier_name,COUNT(*) invoice_count,SUM(total_amount) invoiced,SUM(paid_amount) paid,SUM(CASE WHEN status IN ('approved','partially_paid') THEN GREATEST(total_amount-paid_amount,0) ELSE 0 END) outstanding,MAX(invoice_date) last_invoice FROM finance_supplier_invoices GROUP BY supplier_name ORDER BY supplier_name");
while($sh&&$x=$sh->fetch_assoc())$supplierHistory[]=$x;
require __DIR__.'/_layout_start.php';
?>
<style>
.ap-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:16px 0}.ap-card{background:var(--surface,#fff);border:1px solid var(--border,#e5e7eb);border-radius:13px;padding:16px;min-width:0}.ap-card small{display:block;color:var(--muted,#64748b)}.ap-card strong{display:block;font-size:21px;margin-top:6px;overflow-wrap:anywhere}.ap-form{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:11px}.ap-form label{display:block;font-size:12px;font-weight:700;margin-bottom:5px}.ap-form input,.ap-form select,.ap-form textarea,.ap-filter input,.ap-filter select,.ap-actions input{box-sizing:border-box;width:100%;padding:9px;border:1px solid var(--border,#cbd5e1);border-radius:8px;background:var(--surface,#fff);color:inherit}.ap-span2{grid-column:span 2}.ap-span4{grid-column:1/-1}.ap-btn{padding:8px 10px;border:1px solid var(--border,#cbd5e1);border-radius:8px;background:var(--surface,#fff);color:inherit;cursor:pointer;font-weight:650}.ap-btn.primary{background:var(--primary,#1d4ed8);color:white}.ap-btn.danger{color:#b91c1c}.ap-toolbar,.ap-actions,.ap-inline{display:flex;gap:7px;align-items:center;flex-wrap:wrap}.ap-table-wrap{overflow:auto}.ap-table{width:100%;min-width:1100px;border-collapse:collapse}.ap-table th,.ap-table td{padding:10px;text-align:left;border-bottom:1px solid var(--border,#e5e7eb);font-size:13px;vertical-align:top}.ap-table th{font-size:11px;color:var(--muted,#64748b);text-transform:uppercase}.ap-muted{font-size:12px;color:var(--muted,#64748b)}.ap-pill{display:inline-block;padding:4px 8px;border-radius:20px;background:var(--surface-secondary,#f1f5f9);font-size:11px;text-transform:capitalize}.ap-details{margin-top:7px;padding:9px;background:var(--surface-secondary,#f8fafc);border-radius:8px}.ap-details summary{cursor:pointer;font-weight:700}.ap-section{margin:18px 0}.ap-overdue{color:#b91c1c;font-weight:700}@media(max-width:850px){.ap-grid{grid-template-columns:repeat(2,1fr)}.ap-form{grid-template-columns:repeat(2,1fr)}}@media(max-width:560px){.ap-grid,.ap-form{grid-template-columns:1fr}.ap-span2,.ap-span4{grid-column:auto}}
</style>
<div class="fo-page-header"><div><span>Finance / Accounts Payable</span><h2>Supplier Invoice Management</h2><p>Capture, verify, approve and monitor supplier invoices, liabilities, due dates and supplier payment histories.</p></div><a class="ap-btn" href="<?=ap_e(url('finance/commitments.php'))?>"><i class="fas fa-file-contract"></i> Purchase Order Commitments</a></div>
<div class="ap-grid">
 <div class="ap-card"><small>Total invoices</small><strong><?=number_format((int)$stats['invoice_count'])?></strong></div>
 <div class="ap-card"><small>Awaiting verification</small><strong><?=number_format((int)$stats['awaiting_verification'])?></strong></div>
 <div class="ap-card"><small>Awaiting approval</small><strong><?=number_format((int)$stats['awaiting_approval'])?></strong></div>
 <div class="ap-card"><small>Outstanding liabilities</small><strong><?=ap_money($stats['outstanding'])?></strong></div>
 <div class="ap-card"><small>Overdue liabilities</small><strong class="ap-overdue"><?=ap_money($stats['overdue'])?></strong></div>
 <div class="ap-card"><small>Payable invoices</small><strong><?=number_format((int)$stats['payable_count'])?></strong></div>
</div>
<section class="ap-card ap-section"><h3><i class="fas fa-file-circle-plus"></i> Capture supplier invoice</h3>
<form method="post" class="ap-form">
<input type="hidden" name="csrf_token" value="<?=ap_e(fo_csrf_token())?>"><input type="hidden" name="action" value="create">
<div><label>Supplier name *</label><input name="supplier_name" required maxlength="180"></div>
<div><label>Supplier invoice number *</label><input name="supplier_invoice_no" required maxlength="120"></div>
<div><label>Supplier tax number</label><input name="supplier_tax_number" maxlength="80"></div>
<div><label>Purchase order number</label><input name="purchase_order_no" maxlength="100"></div>
<div><label>Commitment ID (optional)</label><input type="number" name="commitment_id" min="1"></div>
<div><label>Programme</label><select name="programme_id"><option value="0">Select programme</option><?php foreach($programmes as $p):?><option value="<?=(int)$p['id']?>"><?=ap_e($p['name'])?></option><?php endforeach;?></select></div>
<div><label>Cohort ID (optional)</label><input type="number" name="cohort_id" min="1"></div>
<div><label>Department</label><input name="department" maxlength="150"></div>
<div><label>Invoice date *</label><input type="date" name="invoice_date" value="<?=date('Y-m-d')?>" required></div>
<div><label>Payment due date *</label><input type="date" name="due_date" required></div>
<div><label>Subtotal (ZAR) *</label><input type="number" name="subtotal" min="0" step=".01" required></div>
<div><label>Tax / VAT amount *</label><input type="number" name="tax_amount" min="0" step=".01" value="0.00" required></div>
<div><label>Total invoice amount *</label><input type="number" name="total_amount" min=".01" step=".01" required></div>
<div><label>Attachment reference / path</label><input name="attachment_path" maxlength="500" placeholder="Optional document reference"></div>
<div class="ap-span2"><label>Description / goods or services *</label><textarea name="description" rows="2" required></textarea></div>
<div class="ap-span4"><button class="ap-btn primary"><i class="fas fa-save"></i> Save invoice as draft</button><span class="ap-muted">The invoice must be submitted, verified and approved before payment recording is enabled.</span></div>
</form></section>
<section class="ap-section"><div class="ap-toolbar" style="justify-content:space-between;margin-bottom:10px"><h3>Invoice register &amp; payment queue</h3>
<form method="get" class="ap-toolbar ap-filter"><input name="q" value="<?=ap_e($q)?>" placeholder="Search invoice, PO or description"><input name="supplier" value="<?=ap_e($supplierFilter)?>" placeholder="Supplier"><select name="status"><option value="">All statuses</option><?php foreach(['draft','submitted','verified','approved','partially_paid','returned','rejected','paid','cancelled'] as $x):?><option value="<?=$x?>" <?=$status===$x?'selected':''?>><?=ucwords(str_replace('_',' ',$x))?></option><?php endforeach;?></select><button class="ap-btn primary">Filter</button><a class="ap-btn" href="<?=ap_e(url('finance/supplier_invoices.php'))?>">Reset</a></form></div>
<div class="ap-card ap-table-wrap"><table class="ap-table"><thead><tr><th>Invoice / PO</th><th>Supplier / programme</th><th>Invoice date</th><th>Due date</th><th>Invoice total</th><th>Paid</th><th>Outstanding</th><th>Status</th><th>Workflow / records</th></tr></thead><tbody>
<?php if(!$rows):?><tr><td colspan="9" class="ap-muted">No supplier invoices found.</td></tr><?php endif;?>
<?php foreach($rows as $r):$out=max(0,(float)$r['total_amount']-(float)$r['paid_amount']);$late=in_array($r['status'],['approved','partially_paid'],true)&&$out>0&&$r['due_date']<date('Y-m-d');?>
<tr><td><strong><?=ap_e($r['supplier_invoice_no'])?></strong><div class="ap-muted"><?=ap_e($r['invoice_ref'])?> · PO <?=ap_e($r['purchase_order_no']?:'—')?></div></td>
<td><strong><?=ap_e($r['supplier_name'])?></strong><div class="ap-muted"><?=ap_e($r['programme_name']?:'No programme')?> / <?=ap_e($r['cohort_name']?:'No cohort')?></div></td>
<td><?=ap_e(fo_date($r['invoice_date']))?></td><td class="<?=$late?'ap-overdue':''?>"><?=ap_e(fo_date($r['due_date']))?><?php if($late):?><div class="ap-muted ap-overdue">OVERDUE</div><?php endif;?></td>
<td><?=ap_money($r['total_amount'])?></td><td><?=ap_money($r['paid_amount'])?></td><td><strong><?=ap_money($out)?></strong></td><td><span class="ap-pill"><?=ap_e(str_replace('_',' ',$r['status']))?></span></td><td>
<div class="ap-inline">
<?php if(in_array($r['status'],['draft','returned'],true)):?><form method="post"><input type="hidden" name="csrf_token" value="<?=ap_e(fo_csrf_token())?>"><input type="hidden" name="invoice_id" value="<?=(int)$r['id']?>"><input type="hidden" name="action" value="submit"><button class="ap-btn primary">Submit</button></form><?php endif;?>
<?php if($r['status']==='submitted'&&(int)$r['created_by']!==$uid):?><form method="post"><input type="hidden" name="csrf_token" value="<?=ap_e(fo_csrf_token())?>"><input type="hidden" name="invoice_id" value="<?=(int)$r['id']?>"><input type="hidden" name="action" value="verify"><button class="ap-btn primary">Verify</button></form><?php endif;?>
<?php if($r['status']==='verified'&&(int)$r['created_by']!==$uid&&(int)$r['verified_by']!==$uid):?><form method="post"><input type="hidden" name="csrf_token" value="<?=ap_e(fo_csrf_token())?>"><input type="hidden" name="invoice_id" value="<?=(int)$r['id']?>"><input type="hidden" name="action" value="approve"><button class="ap-btn primary">Approve</button></form><?php endif;?>
<?php if(in_array($r['status'],['submitted','verified'],true)):?><?php foreach(['return'=>'Return','reject'=>'Reject'] as $a=>$label):?><form method="post"><input type="hidden" name="csrf_token" value="<?=ap_e(fo_csrf_token())?>"><input type="hidden" name="invoice_id" value="<?=(int)$r['id']?>"><input type="hidden" name="action" value="<?=$a?>"><input name="action_note" placeholder="<?=$label?> reason" required><button class="ap-btn"><?=$label?></button></form><?php endforeach;endif;?>
<?php if(in_array($r['status'],['approved','partially_paid'],true)&&$out>0):?><details class="ap-details"><summary>Record payment</summary><form method="post" class="ap-actions" style="margin-top:8px"><input type="hidden" name="csrf_token" value="<?=ap_e(fo_csrf_token())?>"><input type="hidden" name="invoice_id" value="<?=(int)$r['id']?>"><input type="hidden" name="action" value="payment"><input name="payment_reference" placeholder="Payment reference" required><input type="date" name="payment_date" value="<?=date('Y-m-d')?>" required><input type="number" name="payment_amount" min=".01" max="<?=ap_e(number_format($out,2,'.',''))?>" step=".01" placeholder="Amount" required><button class="ap-btn primary">Record</button></form></details><?php endif;?>
<?php if(in_array($r['status'],['draft','returned','submitted'],true)):?><form method="post"><input type="hidden" name="csrf_token" value="<?=ap_e(fo_csrf_token())?>"><input type="hidden" name="invoice_id" value="<?=(int)$r['id']?>"><input type="hidden" name="action" value="cancel"><input type="hidden" name="action_note" value="Cancelled by Finance Officer"><button class="ap-btn danger" onclick="return confirm('Cancel this invoice?')">Cancel</button></form><?php endif;?>
</div>
<details class="ap-details"><summary>Payment history (<?=count($payments[(int)$r['id']]??[])?>)</summary><?php foreach($payments[(int)$r['id']]??[] as $p):?><div class="ap-muted"><?=ap_e(fo_date($p['payment_date']))?> · <?=ap_e($p['payment_reference'])?> · <strong><?=ap_money($p['amount'])?></strong> · User #<?=(int)$p['recorded_by']?></div><?php endforeach;?><?php if(empty($payments[(int)$r['id']])):?><div class="ap-muted">No payments recorded.</div><?php endif;?></details>
<details class="ap-details"><summary>Decision history (<?=count($history[(int)$r['id']]??[])?>)</summary><?php foreach($history[(int)$r['id']]??[] as $h):?><div class="ap-muted" style="padding:5px 0"><strong><?=ap_e(ucwords(str_replace('_',' ',$h['action'])))?></strong> · <?=ap_e(trim(($h['first_name']??'').' '.($h['last_name']??''))?:'User #'.$h['acted_by'])?> · <?=ap_e(fo_datetime($h['created_at']))?> · <?=ap_e($h['action_note'])?></div><?php endforeach;?></details>
</td></tr><?php endforeach;?></tbody></table></div></section>
<section class="ap-section"><h3>Supplier payment history &amp; exposure</h3><div class="ap-card ap-table-wrap"><table class="ap-table" style="min-width:700px"><thead><tr><th>Supplier</th><th>Invoices</th><th>Total invoiced</th><th>Total paid</th><th>Outstanding</th><th>Last invoice</th></tr></thead><tbody>
<?php if(!$supplierHistory):?><tr><td colspan="6" class="ap-muted">Supplier history will appear when invoices are captured.</td></tr><?php endif;?>
<?php foreach($supplierHistory as $s):?><tr><td><strong><?=ap_e($s['supplier_name'])?></strong></td><td><?=number_format((int)$s['invoice_count'])?></td><td><?=ap_money($s['invoiced'])?></td><td><?=ap_money($s['paid'])?></td><td><?=ap_money($s['outstanding'])?></td><td><?=ap_e(fo_date($s['last_invoice']))?></td></tr><?php endforeach;?>
</tbody></table></div></section>
<div class="ap-card ap-muted"><strong>Segregation of duties:</strong> The invoice creator cannot verify their own invoice. The creator and verifier cannot approve it. Payments can only be recorded after approval and are capped at the unpaid balance. Payment records are captured for tracking; this module does not initiate bank transfers. The route retains the existing Finance Officer role gate.</div>
<?php require __DIR__.'/_layout_end.php';?>
