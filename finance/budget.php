<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('finance_officer');
$user = current_user();
$conn = Database::getConnection();
require_once __DIR__ . '/_helpers.php';
$currentPage = 'budget';
$pageTitle = 'Budget Allocation & Expenditure';
$uid = (int)($user['id'] ?? $user['user_id'] ?? 0);
if ($uid < 1) { http_response_code(403); exit('Invalid Finance Officer account.'); }
if (empty($_SESSION['finance_budget_csrf'])) $_SESSION['finance_budget_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['finance_budget_csrf'];
$notice = '';
function fb_e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function fb_rows(mysqli $db, string $sql): array { $r=$db->query($sql); if(!$r) throw new RuntimeException($db->error); return $r->fetch_all(MYSQLI_ASSOC); }
function fb_log(mysqli $db,int $uid,string $action,string $type,int $id,string $description): void {
 $s=$db->prepare('INSERT INTO finance_budget_audit(user_id,action,entity_type,entity_id,description) VALUES(?,?,?,?,?)');
 $s->bind_param('issis',$uid,$action,$type,$id,$description); $s->execute(); $s->close();
 if (function_exists('fo_has_table') && fo_has_table($db,'finance_activity_log')) {
  $a=$db->prepare('INSERT INTO finance_activity_log(user_id,action,description,entity_type,entity_id,ip_address,user_agent) VALUES(?,?,?,?,?,?,?)');
  if($a){$ip=$_SERVER['REMOTE_ADDR']??'';$ua=substr($_SERVER['HTTP_USER_AGENT']??'',0,255);$a->bind_param('isssiss',$uid,$action,$description,$type,$id,$ip,$ua);$a->execute();$a->close();}
 }
 if (function_exists('fo_audit_event')) fo_audit_event($db,$uid,$action,$description,$type,$id);
}
if ($_SERVER['REQUEST_METHOD']==='POST') {
 try {
  if (!hash_equals($csrf,(string)($_POST['csrf']??''))) throw new RuntimeException('Security token expired. Refresh and try again.');
  $action=(string)($_POST['action']??'');
  if($action==='add_budget'){
   $pid=(int)($_POST['programme_id']??0);$cid=(int)($_POST['cohort_id']??0);$year=trim((string)($_POST['financial_year']??''));$name=trim((string)($_POST['budget_name']??''));$amount=(float)($_POST['allocated_amount']??0);$notes=trim((string)($_POST['notes']??''));
   if($pid<1||$name===''||!preg_match('/^\d{4}\/\d{4}$/',$year)||$amount<=0) throw new RuntimeException('Complete all required budget fields with a valid financial year and amount.');
   $chk=$conn->prepare('SELECT id FROM programmes WHERE id=?');$chk->bind_param('i',$pid);$chk->execute();if(!$chk->get_result()->fetch_assoc())throw new RuntimeException('Selected programme was not found.');$chk->close();
   if($cid>0){$chk=$conn->prepare('SELECT id FROM cohorts WHERE id=? AND programme_id=?');$chk->bind_param('ii',$cid,$pid);$chk->execute();if(!$chk->get_result()->fetch_assoc())throw new RuntimeException('Selected cohort does not belong to the selected programme.');$chk->close();}
   $s=$conn->prepare('INSERT INTO finance_budgets(programme_id,cohort_id,financial_year,budget_name,allocated_amount,notes,created_by) VALUES(?,NULLIF(?,0),?,?,?,?,?)');$s->bind_param('iissdsi',$pid,$cid,$year,$name,$amount,$notes,$uid);$s->execute();$id=(int)$s->insert_id;$s->close();fb_log($conn,$uid,'budget_created','budget',$id,'Created '.$name.' allocation of R'.number_format($amount,2));$notice='Budget allocation saved.';
  } elseif($action==='add_expense'){
   $bid=(int)($_POST['budget_id']??0);$date=(string)($_POST['expense_date']??'');$cat=trim((string)($_POST['category']??''));$desc=trim((string)($_POST['description']??''));$ref=trim((string)($_POST['reference_no']??''));$amount=(float)($_POST['amount']??0);$status=(string)($_POST['status']??'paid');
   if($bid<1||$date===''||$cat===''||$desc===''||$amount<=0||!in_array($status,['committed','paid'],true))throw new RuntimeException('Complete all expenditure fields with a valid amount and status.');
   $s=$conn->prepare('SELECT id FROM finance_budgets WHERE id=? AND status="active"');$s->bind_param('i',$bid);$s->execute();if(!$s->get_result()->fetch_assoc())throw new RuntimeException('Active budget allocation not found.');$s->close();
   $s=$conn->prepare('INSERT INTO finance_expenditures(budget_id,reference_no,expense_date,category,description,amount,status,recorded_by) VALUES(?,?,?,?,?,?,?,?)');$s->bind_param('issssdsi',$bid,$ref,$date,$cat,$desc,$amount,$status,$uid);$s->execute();$id=(int)$s->insert_id;$s->close();fb_log($conn,$uid,'expenditure_recorded','expenditure',$id,'Recorded '.$status.' expenditure of R'.number_format($amount,2));$notice='Expenditure recorded successfully.';
  } else throw new RuntimeException('Unknown action.');
 } catch(Throwable $e){$notice=$e->getMessage();}
}
$programmes=fb_rows($conn,'SELECT id,name FROM programmes ORDER BY name');
$cohorts=fb_rows($conn,'SELECT id,programme_id,name FROM cohorts ORDER BY name');
$budgets=fb_rows($conn,"SELECT b.id,b.budget_name,b.financial_year,b.allocated_amount,b.status,p.name programme_name,c.name cohort_name,COALESCE(SUM(CASE WHEN x.status IN ('paid','committed') THEN x.amount ELSE 0 END),0) spent,COALESCE(SUM(CASE WHEN x.status='committed' THEN x.amount ELSE 0 END),0) committed FROM finance_budgets b JOIN programmes p ON p.id=b.programme_id LEFT JOIN cohorts c ON c.id=b.cohort_id LEFT JOIN finance_expenditures x ON x.budget_id=b.id GROUP BY b.id ORDER BY b.created_at DESC");
$expenditures=fb_rows($conn,"SELECT x.*,b.budget_name,p.name programme_name,c.name cohort_name FROM finance_expenditures x JOIN finance_budgets b ON b.id=x.budget_id JOIN programmes p ON p.id=b.programme_id LEFT JOIN cohorts c ON c.id=b.cohort_id ORDER BY x.expense_date DESC,x.id DESC LIMIT 100");
$tot=['allocated'=>0.0,'spent'=>0.0,'committed'=>0.0,'available'=>0.0];$over=[];
foreach($budgets as $b){$tot['allocated']+=(float)$b['allocated_amount'];$tot['spent']+=(float)$b['spent'];$tot['committed']+=(float)$b['committed'];$available=(float)$b['allocated_amount']-(float)$b['spent'];$tot['available']+=$available;if($available<0)$over[]=$b;}
$util=$tot['allocated']>0?min(100,($tot['spent']/$tot['allocated'])*100):0;
require __DIR__ . '/_layout_start.php';
?>
<div class="finance-page-polished finance-budget-page">
<div class="fo-page-header"><div><span>Finance Operations</span><h2>Budget Allocation &amp; Expenditure</h2><p>Allocate programme and cohort funding, record expenditure and monitor available balances.</p></div><a class="fo-btn fo-btn--secondary" href="<?= e(url('finance/dashboard.php')) ?>"><i class="fas fa-arrow-left"></i> Finance Dashboard</a></div>
<?php if($notice!==''): ?><div class="fo-alert fo-alert--info" role="status"><?= fb_e($notice) ?></div><?php endif; ?>
<div class="fo-dashboard-grid">
 <article class="fo-card"><span class="fo-muted">Total allocated</span><h2><?= e(fo_money($tot['allocated'])) ?></h2></article>
 <article class="fo-card"><span class="fo-muted">Paid expenditure</span><h2><?= e(fo_money($tot['spent']-$tot['committed'])) ?></h2></article>
 <article class="fo-card"><span class="fo-muted">Committed expenditure</span><h2><?= e(fo_money($tot['committed'])) ?></h2></article>
 <article class="fo-card"><span class="fo-muted">Available balance</span><h2><?= e(fo_money($tot['allocated']-$tot['spent'])) ?></h2></article>
 <article class="fo-card"><span class="fo-muted">Overspent allocations</span><h2><?= count($over) ?></h2></article>
</div>
<section class="fo-card"><div class="fo-card-header"><h3><i class="fas fa-chart-pie"></i> Overall budget utilisation</h3><strong><?= number_format($util,1) ?>%</strong></div><div class="fb-progress"><span style="width:<?= number_format($util,2,'.','') ?>%"></span></div><small class="fo-muted">Utilisation is based on paid plus committed expenditure against total allocations.</small></section>
<div class="fo-two-column">
<section class="fo-card"><div class="fo-card-header"><h3><i class="fas fa-circle-plus"></i> Create budget allocation</h3></div><form method="post"><input type="hidden" name="csrf" value="<?= fb_e($csrf) ?>"><input type="hidden" name="action" value="add_budget"><label>Programme *</label><select name="programme_id" id="fbProgramme" required><option value="">Select programme</option><?php foreach($programmes as $p): ?><option value="<?= (int)$p['id'] ?>"><?= fb_e($p['name']) ?></option><?php endforeach; ?></select><label>Cohort (optional)</label><select name="cohort_id" id="fbCohort"><option value="0">Programme-wide allocation</option><?php foreach($cohorts as $c): ?><option value="<?= (int)$c['id'] ?>" data-programme="<?= (int)$c['programme_id'] ?>"><?= fb_e($c['name']) ?></option><?php endforeach; ?></select><label>Financial year *</label><input name="financial_year" placeholder="2026/2027" pattern="\d{4}/\d{4}" required><label>Budget name *</label><input name="budget_name" maxlength="180" required placeholder="e.g. Training and learner support"><label>Allocated amount (ZAR) *</label><input type="number" name="allocated_amount" min="0.01" step="0.01" required><label>Notes</label><textarea name="notes" rows="2"></textarea><button class="fo-btn fo-btn--primary" type="submit">Save allocation</button></form></section>
<section class="fo-card"><div class="fo-card-header"><h3><i class="fas fa-receipt"></i> Record expenditure</h3></div><form method="post"><input type="hidden" name="csrf" value="<?= fb_e($csrf) ?>"><input type="hidden" name="action" value="add_expense"><label>Budget allocation *</label><select name="budget_id" required><option value="">Select active allocation</option><?php foreach($budgets as $b): if($b['status']!=='active')continue; ?><option value="<?= (int)$b['id'] ?>"><?= fb_e($b['programme_name'].' / '.($b['cohort_name']?:'Programme-wide').' — '.$b['budget_name'].' ('.fo_money($b['allocated_amount']).')') ?></option><?php endforeach; ?></select><label>Expense date *</label><input type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required><label>Category *</label><select name="category" required><option value="">Select category</option><?php foreach(['Stipends','Training','Transport','Accommodation','Equipment','Administration','Venue','Other'] as $v): ?><option><?= fb_e($v) ?></option><?php endforeach; ?></select><label>Description *</label><input name="description" maxlength="500" required><label>Reference / invoice number</label><input name="reference_no" maxlength="80"><label>Amount (ZAR) *</label><input type="number" name="amount" min="0.01" step="0.01" required><label>Type *</label><select name="status"><option value="paid">Paid</option><option value="committed">Committed / unpaid</option></select><button class="fo-btn fo-btn--primary" type="submit">Record expenditure</button></form></section>
</div>
<section class="fo-card"><div class="fo-card-header"><h3><i class="fas fa-scale-balanced"></i> Programme and cohort budget position</h3><span class="fo-muted">Available = allocated − paid − committed</span></div><div class="table-responsive"><table class="table"><thead><tr><th>Programme / cohort</th><th>Financial year</th><th>Allocation</th><th>Paid</th><th>Committed</th><th>Available</th><th>Variance</th><th>Utilisation</th></tr></thead><tbody><?php if(!$budgets): ?><tr><td colspan="8">No budget allocations yet.</td></tr><?php endif; ?><?php foreach($budgets as $b): $alloc=(float)$b['allocated_amount'];$spent=(float)$b['spent'];$comm=(float)$b['committed'];$paid=$spent-$comm;$avail=$alloc-$spent;$variance=$alloc-$spent;$pct=$alloc>0?($spent/$alloc)*100:0;$bad=$avail<0; ?><tr><td><strong><?= fb_e($b['programme_name']) ?></strong><br><small><?= fb_e($b['cohort_name']?:'Programme-wide') ?> · <?= fb_e($b['budget_name']) ?></small></td><td><?= fb_e($b['financial_year']) ?></td><td><?= e(fo_money($alloc)) ?></td><td><?= e(fo_money($paid)) ?></td><td><?= e(fo_money($comm)) ?></td><td class="<?= $bad?'fb-danger':'' ?>"><?= e(fo_money($avail)) ?></td><td class="<?= $bad?'fb-danger':'fb-good' ?>"><?= $bad?'Overspent ':'Under budget ' ?><?= e(fo_money(abs($variance))) ?></td><td><?= number_format($pct,1) ?>%</td></tr><?php endforeach; ?></tbody></table></div></section>
<?php if($over): ?><section class="fo-card"><div class="fo-card-header"><h3 class="fb-danger"><i class="fas fa-triangle-exclamation"></i> Overspending watchlist</h3><span class="fo-status fo-status--warning"><?= count($over) ?> flagged</span></div><?php foreach($over as $b): ?><div class="fo-alert fo-alert--danger"><strong><?= fb_e($b['programme_name'].' / '.($b['cohort_name']?:'Programme-wide')) ?></strong> — overspent by <?= e(fo_money((float)$b['spent']-(float)$b['allocated_amount'])) ?> (allocation <?= e(fo_money($b['allocated_amount'])) ?>).</div><?php endforeach; ?></section><?php endif; ?>
<section class="fo-card"><div class="fo-card-header"><h3><i class="fas fa-list"></i> Recent expenditure</h3></div><div class="table-responsive"><table class="table"><thead><tr><th>Date</th><th>Programme / cohort</th><th>Category</th><th>Description</th><th>Reference</th><th>Amount</th><th>Status</th></tr></thead><tbody><?php if(!$expenditures): ?><tr><td colspan="7">No expenditure recorded.</td></tr><?php endif; ?><?php foreach($expenditures as $x): ?><tr><td><?= fb_e($x['expense_date']) ?></td><td><?= fb_e($x['programme_name'].' / '.($x['cohort_name']?:'Programme-wide')) ?></td><td><?= fb_e($x['category']) ?></td><td><?= fb_e($x['description']) ?></td><td><?= fb_e($x['reference_no']?:'—') ?></td><td><?= e(fo_money($x['amount'])) ?></td><td><?= fb_e(ucfirst($x['status'])) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<style>.fo-dashboard-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(175px,1fr));gap:1rem;margin-bottom:1rem}.fo-dashboard-grid h2{font-size:1.5rem;margin:.5rem 0}.fo-two-column{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,350px),1fr));gap:1rem;margin:1rem 0}.fo-card-header{display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:1rem}.fo-card form{display:grid;gap:.5rem}.fo-card input,.fo-card select,.fo-card textarea{width:100%;padding:.65rem;border:1px solid var(--border-color,#d1d5db);border-radius:8px;background:var(--surface,#fff);color:inherit;margin-bottom:.5rem}.fb-progress{height:12px;border-radius:20px;background:#e5e7eb;overflow:hidden;margin:.75rem 0}.fb-progress span{display:block;height:100%;background:#2563eb;border-radius:20px}.fb-danger{color:#dc2626!important;font-weight:700}.fb-good{color:#15803d}.fo-card .table{margin-bottom:0}</style>
<script>document.getElementById('fbProgramme')?.addEventListener('change',function(){const id=this.value;document.querySelectorAll('#fbCohort option[data-programme]').forEach(o=>{o.hidden=!!id&&o.dataset.programme!==id;if(o.hidden&&o.selected)o.selected=false;});});</script>

<style>
.finance-page-polished{font-family:Inter,system-ui,sans-serif;font-size:14px;line-height:1.5;max-width:100%;color:var(--text-color,inherit)}
.finance-page-polished *{box-sizing:border-box}
.finance-page-polished .fo-page-header{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;flex-wrap:wrap;margin:0 0 20px;padding:0 0 16px;border-bottom:1px solid var(--border-color,#e5e7eb)}
.finance-page-polished .fo-page-header h2{font-size:clamp(1.35rem,2vw,1.75rem);line-height:1.25;font-weight:700;margin:5px 0 7px;letter-spacing:-.025em}
.finance-page-polished .fo-page-header p{font-size:13px;line-height:1.55;margin:0;color:var(--muted,#64748b);max-width:760px}
.finance-page-polished .fo-card{padding:18px;border-radius:12px;margin-bottom:16px;min-width:0}
.finance-page-polished .fo-card-header h3{font-size:15px;line-height:1.4;font-weight:700;margin:0}
.finance-page-polished .fo-muted,.finance-page-polished small{font-size:12px;line-height:1.45}
.finance-page-polished .fo-dashboard-grid{grid-template-columns:repeat(auto-fit,minmax(min(100%,175px),1fr));gap:14px}
.finance-page-polished .fo-dashboard-grid h2{font-size:clamp(1.2rem,1.8vw,1.5rem);line-height:1.25;font-weight:700;overflow-wrap:anywhere}
.finance-page-polished .fo-two-column{grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
.finance-page-polished .fo-card form{gap:7px}
.finance-page-polished .fo-card label{font-size:12px;font-weight:600;margin-top:3px}
.finance-page-polished .fo-card input,.finance-page-polished .fo-card select,.finance-page-polished .fo-card textarea{font:inherit;font-size:13px;min-height:40px;padding:9px 11px;margin:0 0 5px;border-radius:8px}
.finance-page-polished .fo-btn{font-size:13px;line-height:1.3;padding:9px 13px;border-radius:8px}
.finance-page-polished table{font-size:12px;line-height:1.45;min-width:760px}
.finance-page-polished th{font-size:11px;font-weight:700;white-space:nowrap}
.finance-page-polished td,.finance-page-polished th{padding:10px 11px;vertical-align:middle}
.finance-page-polished .table-responsive{width:100%;overflow-x:auto}
@media(max-width:760px){.finance-page-polished .fo-two-column{grid-template-columns:1fr}.finance-page-polished .fo-card{padding:14px}.finance-page-polished .fo-page-header{gap:10px}}
</style>
</div>
<?php require __DIR__ . '/_layout_end.php'; ?>
