<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_role('commercial_manager');
$user=current_user();$flashes=render_flashes();
require_once __DIR__.'/_helpers.php';
$conn=Database::getConnection();$managerId=(int)($user['id']??$user['user_id']??0);
if($managerId<1){http_response_code(403);exit('Invalid Commercial Manager account.');}
$currentPage='sales_pipeline';$pageTitle='Sales Pipeline & Opportunities';
function spo_e($v):string{return htmlspecialchars((string)($v??''),ENT_QUOTES,'UTF-8');}
function spo_date(string $v,bool $optional=false):bool{if($optional&&$v==='')return true;$d=DateTime::createFromFormat('Y-m-d',$v);return $d&&$d->format('Y-m-d')===$v;}
function spo_money($v,string $c='ZAR'):string{return ($c==='ZAR'?'R ':$c.' ').number_format((float)$v,2);}
function spo_go(?int $id=null):never{header('Location: '.url('commercial_manager/sales_pipeline.php'.($id?'?opportunity='.$id:'')));exit;}
function spo_owned(mysqli $db,int $id,int $owner):bool{$s=$db->prepare('SELECT id FROM commercial_sales_opportunities WHERE id=? AND commercial_manager_id=?');$s->bind_param('ii',$id,$owner);$s->execute();$ok=(bool)$s->get_result()->fetch_assoc();$s->close();return $ok;}
$required=['commercial_sales_opportunities','commercial_sales_opportunity_history'];$missing=[];foreach($required as $t)if(!cm_table($conn,$t))$missing[]=$t;
if($missing){require __DIR__.'/_layout_start.php';?><div class="cm-alert cm-alert--warning"><i class="fas fa-database"></i><div>Sales pipeline tables are not installed. Import <strong>commercial_sales_pipeline_migration.sql</strong> and reload this page.</div></div><?php require __DIR__.'/_layout_end.php';exit;}
if($_SERVER['REQUEST_METHOD']==='POST'){
 cm_verify_csrf();$action=(string)($_POST['action']??'');$id=max(0,(int)($_POST['opportunity_id']??0));
 try{
  if($action==='create'){
   $title=trim((string)($_POST['title']??''));$clientName=trim((string)($_POST['client_name']??''));$clientId=max(0,(int)($_POST['client_id']??0));$source=(string)($_POST['source_type']??'existing_client');$type=(string)($_POST['opportunity_type']??'new_business');$stage=(string)($_POST['stage']??'identified');$currency=(string)($_POST['currency']??'ZAR');$value=round((float)($_POST['estimated_value']??0),2);$prob=(int)($_POST['probability']??10);$close=trim((string)($_POST['expected_close_date']??''));$contact=trim((string)($_POST['contact_person']??''));$notes=trim((string)($_POST['notes']??''));
   $stages=['identified','qualifying','solution_design','proposal_submitted','negotiation','verbal_commitment','won','lost','on_hold'];
   if($title===''||$clientName===''||!in_array($source,['prospective_client','existing_client'],true)||!in_array($type,['new_business','renewal','cross_sell','upsell','service_expansion'],true)||!in_array($stage,$stages,true)||!in_array($currency,['ZAR','USD','EUR','GBP'],true)||$value<0||$prob<0||$prob>100||!spo_date($close,true))throw new RuntimeException('Check the opportunity details, value, probability, stage and expected close date.');
   if($clientId>0){$s=$conn->prepare('SELECT client_name,account_type FROM commercial_clients WHERE id=? AND commercial_manager_id=?');$s->bind_param('ii',$clientId,$managerId);$s->execute();$cl=$s->get_result()->fetch_assoc();$s->close();if(!$cl)throw new RuntimeException('Selected client account was not found or is not assigned to you.');$clientName=$cl['client_name'];}
   if($source==='prospective_client')$clientId=0;
   $ref='OPP-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(3)));
   $s=$conn->prepare("INSERT INTO commercial_sales_opportunities(commercial_manager_id,opportunity_ref,client_id,client_name,source_type,title,opportunity_type,stage,estimated_value,currency,probability,expected_close_date,contact_person,notes) VALUES(?,?,?,?,?,?,?,?,?,?,?,NULLIF(?,''),?,?)");
   $s->bind_param('isisssssdsisss',$managerId,$ref,$clientId,$clientName,$source,$title,$type,$stage,$value,$currency,$prob,$close,$contact,$notes);
   if(!$s->execute())throw new RuntimeException('Unable to register this opportunity.');$id=(int)$s->insert_id;$s->close();
   $detail='Opportunity registered at stage '.$stage.' with estimated value '.$currency.' '.number_format($value,2);
   $s=$conn->prepare("INSERT INTO commercial_sales_opportunity_history(opportunity_id,commercial_manager_id,event_type,new_stage,details) VALUES(?,?,'created',?,?)");$s->bind_param('iiss',$id,$managerId,$stage,$detail);$s->execute();$s->close();
   cm_activity($conn,$managerId,'sales_opportunity_created','Registered opportunity '.$ref,'sales_opportunity',$id);cm_flash('success','Sales opportunity registered.');spo_go($id);
  }
  if($id<1||!spo_owned($conn,$id,$managerId))throw new RuntimeException('Opportunity not found or access denied.');
  if($action==='update'){
   $stage=(string)($_POST['stage']??'');$prob=(int)($_POST['probability']??-1);$value=round((float)($_POST['estimated_value']??-1),2);$currency=(string)($_POST['currency']??'ZAR');$close=trim((string)($_POST['expected_close_date']??''));$notes=trim((string)($_POST['notes']??''));$contact=trim((string)($_POST['contact_person']??''));
   $stages=['identified','qualifying','solution_design','proposal_submitted','negotiation','verbal_commitment','won','lost','on_hold'];
   if(!in_array($stage,$stages,true)||$prob<0||$prob>100||$value<0||!in_array($currency,['ZAR','USD','EUR','GBP'],true)||!spo_date($close,true))throw new RuntimeException('Check the updated stage, value, probability, currency and expected close date.');
   $s=$conn->prepare('SELECT stage FROM commercial_sales_opportunities WHERE id=? AND commercial_manager_id=?');$s->bind_param('ii',$id,$managerId);$s->execute();$old=$s->get_result()->fetch_assoc();$s->close();$oldStage=$old['stage'];
   $s=$conn->prepare("UPDATE commercial_sales_opportunities SET stage=?,probability=?,estimated_value=?,currency=?,expected_close_date=NULLIF(?,''),contact_person=?,notes=? WHERE id=? AND commercial_manager_id=?");$s->bind_param('sidssssii',$stage,$prob,$value,$currency,$close,$contact,$notes,$id,$managerId);if(!$s->execute())throw new RuntimeException('Opportunity update failed.');$s->close();
   if($oldStage!==$stage){$detail='Stage changed from '.$oldStage.' to '.$stage;$s=$conn->prepare("INSERT INTO commercial_sales_opportunity_history(opportunity_id,commercial_manager_id,event_type,old_stage,new_stage,details) VALUES(?,?,'stage_changed',?,?,?)");$s->bind_param('iisss',$id,$managerId,$oldStage,$stage,$detail);$s->execute();$s->close();}
   cm_activity($conn,$managerId,'sales_opportunity_updated','Updated sales opportunity stage to '.$stage,'sales_opportunity',$id);cm_flash('success','Opportunity details updated.');spo_go($id);
  }
  throw new RuntimeException('Unsupported action.');
 }catch(Throwable $e){cm_flash('error',$e->getMessage());spo_go($id?:null);}
}
$clients=[];if(cm_table($conn,'commercial_clients')){$s=$conn->prepare('SELECT id,client_name,account_type FROM commercial_clients WHERE commercial_manager_id=? ORDER BY client_name');$s->bind_param('i',$managerId);$s->execute();$clients=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();}
$filter=(string)($_GET['stage']??'');$search=trim((string)($_GET['q']??''));$where=' WHERE commercial_manager_id=?';$types='i';$params=[$managerId];
$validFilter=['identified','qualifying','solution_design','proposal_submitted','negotiation','verbal_commitment','won','lost','on_hold'];
if(in_array($filter,$validFilter,true)){$where.=' AND stage=?';$types.='s';$params[]=$filter;}
if($search!==''){$where.=' AND (title LIKE ? OR client_name LIKE ? OR opportunity_ref LIKE ?)';$types.='sss';$like='%'.$search.'%';array_push($params,$like,$like,$like);}
$sql='SELECT * FROM commercial_sales_opportunities'.$where.' ORDER BY COALESCE(expected_close_date,"9999-12-31"),updated_at DESC';
$s=$conn->prepare($sql);$s->bind_param($types,...$params);$s->execute();$rows=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();
$stats=['count'=>0,'open'=>0,'weighted'=>0.0,'won'=>0.0,'due'=>0];$today=date('Y-m-d');
$s=$conn->prepare("SELECT COUNT(*) total, SUM(CASE WHEN stage NOT IN('won','lost') THEN estimated_value ELSE 0 END) open_value, SUM(CASE WHEN stage NOT IN('won','lost') THEN estimated_value*probability/100 ELSE 0 END) weighted_value, SUM(CASE WHEN stage='won' THEN estimated_value ELSE 0 END) won_value, SUM(CASE WHEN stage NOT IN('won','lost') AND expected_close_date BETWEEN ? AND DATE_ADD(?,INTERVAL 30 DAY) THEN 1 ELSE 0 END) due_count FROM commercial_sales_opportunities WHERE commercial_manager_id=? AND currency='ZAR'");$s->bind_param('ssi',$today,$today,$managerId);$s->execute();$st=$s->get_result()->fetch_assoc()?:[];$s->close();
$stats=['count'=>(int)($st['total']??0),'open'=>(float)($st['open_value']??0),'weighted'=>(float)($st['weighted_value']??0),'won'=>(float)($st['won_value']??0),'due'=>(int)($st['due_count']??0)];
$selected=null;$history=[];$id=(int)($_GET['opportunity']??0);if($id>0){foreach($rows as $r){if((int)$r['id']===$id){$selected=$r;break;}}if(!$selected&&spo_owned($conn,$id,$managerId)){$s=$conn->prepare('SELECT * FROM commercial_sales_opportunities WHERE id=? AND commercial_manager_id=?');$s->bind_param('ii',$id,$managerId);$s->execute();$selected=$s->get_result()->fetch_assoc();$s->close();}if($selected){$s=$conn->prepare('SELECT * FROM commercial_sales_opportunity_history WHERE opportunity_id=? AND commercial_manager_id=? ORDER BY created_at DESC LIMIT 30');$s->bind_param('ii',$id,$managerId);$s->execute();$history=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();}}
require __DIR__.'/_layout_start.php';
?>
<style>
.spo-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin:5px 0 18px}.spo-head h2{font-size:25px;margin:4px 0}.spo-muted{color:var(--muted);font-size:12px}.spo-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;margin:16px 0}.spo-stat,.spo-panel{background:var(--surface);border:1px solid var(--line);border-radius:14px;padding:15px;min-width:0}.spo-stat span{display:block;color:var(--muted);font-size:11px}.spo-stat strong{display:block;font-size:21px;margin-top:6px}.spo-panel{margin:14px 0}.spo-panel h3{font-size:16px;margin:0 0 12px}.spo-tablewrap{overflow:auto}.spo-table{width:100%;min-width:880px;border-collapse:collapse}.spo-table th,.spo-table td{padding:10px;border-bottom:1px solid var(--line);text-align:left;font-size:12px;vertical-align:top}.spo-table th{font-size:10px;text-transform:uppercase;color:var(--muted)}.spo-tag{display:inline-block;border-radius:20px;padding:4px 8px;background:var(--surface2);font-size:10px;font-weight:700}.spo-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.spo-field label{display:block;font-size:12px;font-weight:700;margin-bottom:5px}.spo-field input,.spo-field select,.spo-field textarea,.spo-filter input,.spo-filter select{width:100%;padding:9px;border:1px solid var(--line);border-radius:8px;background:var(--surface);color:var(--text);font:inherit}.spo-field textarea{min-height:85px}.spo-wide{grid-column:1/-1}.spo-btn{background:var(--primary);color:#fff;border:0;border-radius:8px;padding:10px 14px;font-weight:700;cursor:pointer}.spo-filter{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}.spo-filter input{max-width:260px}.spo-filter select{max-width:210px}.spo-link{color:var(--primary);font-weight:700;text-decoration:none}.spo-layout{display:grid;grid-template-columns:1.1fr .9fr;gap:14px}.spo-progress{height:7px;background:var(--surface2);border-radius:20px;overflow:hidden;margin-top:5px}.spo-progress span{display:block;height:100%;background:var(--primary)}@media(max-width:1100px){.spo-grid{grid-template-columns:repeat(3,1fr)}}@media(max-width:850px){.spo-layout{grid-template-columns:1fr}}@media(max-width:600px){.spo-grid{grid-template-columns:repeat(2,1fr)}.spo-head{display:block}.spo-form{grid-template-columns:1fr}}
</style>
<div class="spo-head"><div><span class="spo-muted">Commercial Operations · Sales &amp; Growth</span><h2>Sales Pipeline &amp; Opportunity Management</h2><p class="spo-muted">Register prospects, evaluate potential revenue and monitor opportunities through to a win or loss.</p></div></div>
<div class="spo-grid">
 <div class="spo-stat"><span>All opportunities</span><strong><?=$stats['count']?></strong></div>
 <div class="spo-stat"><span>Open pipeline value</span><strong><?=spo_money($stats['open'])?></strong></div>
 <div class="spo-stat"><span>Weighted forecast</span><strong><?=spo_money($stats['weighted'])?></strong></div>
 <div class="spo-stat"><span>Won opportunity value</span><strong><?=spo_money($stats['won'])?></strong></div>
 <div class="spo-stat"><span>Expected to close in 30 days</span><strong><?=$stats['due']?></strong></div>
</div>
<div class="spo-layout">
<section class="spo-panel"><h3>Opportunity register</h3>
<form method="get" class="spo-filter"><input name="q" value="<?=spo_e($search)?>" placeholder="Search client, title or reference"><select name="stage"><option value="">All sales stages</option><?php foreach($validFilter as $v):?><option value="<?=$v?>" <?=$filter===$v?'selected':''?>><?=spo_e(ucwords(str_replace('_',' ',$v)))?></option><?php endforeach;?></select><button class="spo-btn">Filter</button><a class="spo-link" href="<?=url('commercial_manager/sales_pipeline.php')?>">Reset</a></form>
<div class="spo-tablewrap"><table class="spo-table"><thead><tr><th>Opportunity / client</th><th>Stage</th><th>Estimated value</th><th>Probability</th><th>Expected close</th><th>Weighted value</th><th></th></tr></thead><tbody>
<?php foreach($rows as $r):$weighted=(float)$r['estimated_value']*(int)$r['probability']/100;?><tr><td><strong><?=spo_e($r['title'])?></strong><div class="spo-muted"><?=spo_e($r['opportunity_ref'])?> · <?=spo_e($r['client_name'])?></div></td><td><span class="spo-tag"><?=spo_e(ucwords(str_replace('_',' ',$r['stage'])))?></span></td><td><?=spo_money($r['estimated_value'],$r['currency'])?></td><td><?=(int)$r['probability']?>%</td><td><?= $r['expected_close_date']?spo_e(date('d M Y',strtotime($r['expected_close_date']))):'—'?></td><td><?=spo_money($weighted,$r['currency'])?></td><td><a class="spo-link" href="<?=spo_e(url('commercial_manager/sales_pipeline.php?opportunity='.(int)$r['id']))?>">Manage</a></td></tr><?php endforeach;?>
<?php if(!$rows):?><tr><td colspan="7">No opportunities match your filters. Register your first opportunity using the form.</td></tr><?php endif;?>
</tbody></table></div></section>
<section class="spo-panel"><h3><?= $selected?'Update opportunity':'Register an opportunity' ?></h3>
<?php if($selected):?><div class="spo-muted" style="margin-bottom:10px"><?=spo_e($selected['opportunity_ref'])?> · <?=spo_e($selected['client_name'])?></div>
<form method="post" class="spo-form"><?=cm_csrf()?><input type="hidden" name="action" value="update"><input type="hidden" name="opportunity_id" value="<?=(int)$selected['id']?>">
<div class="spo-field"><label>Sales stage</label><select name="stage"><?php foreach($validFilter as $v):?><option value="<?=$v?>" <?=$selected['stage']===$v?'selected':''?>><?=spo_e(ucwords(str_replace('_',' ',$v)))?></option><?php endforeach;?></select></div>
<div class="spo-field"><label>Win probability (%)</label><input type="number" name="probability" min="0" max="100" value="<?=(int)$selected['probability']?>" required></div>
<div class="spo-field"><label>Estimated opportunity value</label><input type="number" step=".01" min="0" name="estimated_value" value="<?=spo_e($selected['estimated_value'])?>" required></div>
<div class="spo-field"><label>Currency</label><select name="currency"><?php foreach(['ZAR','USD','EUR','GBP'] as $c):?><option <?=$selected['currency']===$c?'selected':''?>><?=$c?></option><?php endforeach;?></select></div>
<div class="spo-field"><label>Expected closing date</label><input type="date" name="expected_close_date" value="<?=spo_e($selected['expected_close_date'])?>"></div>
<div class="spo-field"><label>Key contact</label><input name="contact_person" value="<?=spo_e($selected['contact_person'])?>"></div>
<div class="spo-field spo-wide"><label>Progress notes / next steps</label><textarea name="notes"><?=spo_e($selected['notes'])?></textarea></div><div class="spo-wide"><button class="spo-btn">Save opportunity update</button> <a class="spo-link" href="<?=url('commercial_manager/sales_pipeline.php')?>">Close</a></div></form>
<h3 style="margin-top:20px">Opportunity history</h3><?php foreach($history as $h):?><div style="padding:9px 0;border-bottom:1px solid var(--line)"><strong><?=spo_e(ucwords(str_replace('_',' ',$h['event_type'])))?></strong><div class="spo-muted"><?=spo_e($h['created_at'])?></div><div class="spo-muted"><?=spo_e($h['details'])?></div></div><?php endforeach;?>
<?php else:?>
<form method="post" class="spo-form"><?=cm_csrf()?><input type="hidden" name="action" value="create">
<div class="spo-field spo-wide"><label>Opportunity title *</label><input name="title" required maxlength="180" placeholder="e.g. Workforce development programme"></div>
<div class="spo-field"><label>Client type</label><select name="source_type" id="spoSource"><option value="existing_client">Existing client</option><option value="prospective_client">Prospective client</option></select></div>
<div class="spo-field" id="spoClientSelect"><label>Existing client account</label><select name="client_id"><option value="">Select client account</option><?php foreach($clients as $c):?><option value="<?=(int)$c['id']?>"><?=spo_e($c['client_name'])?></option><?php endforeach;?></select></div>
<div class="spo-field" id="spoProspectName" style="display:none"><label>Prospective client name</label><input name="client_name" placeholder="Prospective organisation"></div>
<div class="spo-field"><label>Opportunity category</label><select name="opportunity_type"><?php foreach(['new_business','renewal','cross_sell','upsell','service_expansion'] as $v):?><option value="<?=$v?>"><?=spo_e(ucwords(str_replace('_',' ',$v)))?></option><?php endforeach;?></select></div>
<div class="spo-field"><label>Initial sales stage</label><select name="stage"><?php foreach($validFilter as $v):?><option value="<?=$v?>"><?=spo_e(ucwords(str_replace('_',' ',$v)))?></option><?php endforeach;?></select></div>
<div class="spo-field"><label>Estimated value *</label><input type="number" step=".01" min="0" name="estimated_value" required value="0"></div>
<div class="spo-field"><label>Currency</label><select name="currency"><?php foreach(['ZAR','USD','EUR','GBP'] as $c):?><option><?=$c?></option><?php endforeach;?></select></div>
<div class="spo-field"><label>Win probability (%)</label><input type="number" name="probability" min="0" max="100" value="10" required></div>
<div class="spo-field"><label>Expected closing date</label><input type="date" name="expected_close_date"></div>
<div class="spo-field"><label>Key contact</label><input name="contact_person" maxlength="160"></div>
<div class="spo-field spo-wide"><label>Opportunity notes</label><textarea name="notes"></textarea></div>
<div class="spo-wide"><button class="spo-btn">Register opportunity</button></div></form>
<script>
(function(){const s=document.getElementById('spoSource'),sel=document.getElementById('spoClientSelect'),pro=document.getElementById('spoProspectName');function sync(){const p=s.value==='prospective_client';sel.style.display=p?'none':'';pro.style.display=p?'':'none';sel.querySelector('select').required=!p;pro.querySelector('input').required=p;}s.addEventListener('change',sync);sync();})();
</script>
<?php endif;?>
</section></div>
<?php require __DIR__.'/_layout_end.php'; ?>
