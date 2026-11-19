<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('commercial_manager');
$user=current_user();
$flashes=render_flashes();
require_once __DIR__.'/_helpers.php';
$conn=Database::getConnection();
$managerId=(int)($user['id']??$user['user_id']??0);
if($managerId<1){http_response_code(403);exit('Invalid Commercial Manager account.');}
$currentPage='client_relationships';
$pageTitle='Client Relationships & Account Growth';

function crm_e($v): string { return htmlspecialchars((string)($v??''),ENT_QUOTES,'UTF-8'); }
function crm_date_ok(string $v,bool $optional=false): bool {
    if($optional && $v==='') return true;
    $d=DateTime::createFromFormat('Y-m-d',$v);
    return $d && $d->format('Y-m-d')===$v;
}
function crm_money($v,string $currency='ZAR'): string {
    return ($currency==='ZAR'?'R ':$currency.' ').number_format((float)$v,2);
}
function crm_owned(mysqli $db,int $id,int $manager): bool {
    $s=$db->prepare('SELECT id FROM commercial_clients WHERE id=? AND commercial_manager_id=? LIMIT 1');
    $s->bind_param('ii',$id,$manager);$s->execute();$ok=(bool)$s->get_result()->fetch_assoc();$s->close();return $ok;
}
function crm_go(?int $id=null): never {
    header('Location: '.url('commercial_manager/client_relationships.php'.($id?'?client='.$id:'')));exit;
}
$required=['commercial_clients','commercial_client_activities','commercial_client_opportunities'];
$missing=[];foreach($required as $t)if(!cm_table($conn,$t))$missing[]=$t;
if($missing){
    require __DIR__.'/_layout_start.php';?>
    <div class="crm-head"><div><span>Client Management</span><h2>Client Relationships &amp; Account Growth</h2><p>Manage client accounts, engagement, satisfaction and growth opportunities.</p></div></div>
    <div class="cm-alert cm-alert--warning"><i class="fas fa-database"></i><div>Client management tables are not installed. Import <strong>commercial_client_relationships_migration.sql</strong>, then reload this page.</div></div>
    <?php require __DIR__.'/_layout_end.php';exit;
}
if($_SERVER['REQUEST_METHOD']==='POST'){
    cm_verify_csrf();
    $action=(string)($_POST['action']??'');
    $clientId=max(0,(int)($_POST['client_id']??0));
    try{
        if($action==='create_client'){
            $name=trim((string)($_POST['client_name']??''));
            $industry=trim((string)($_POST['industry']??''));
            $contact=trim((string)($_POST['primary_contact']??''));
            $email=trim((string)($_POST['email']??''));
            $phone=trim((string)($_POST['phone']??''));
            $accountType=(string)($_POST['account_type']??'prospect');
            $status=(string)($_POST['account_status']??'active');
            $value=round((float)($_POST['annual_account_value']??0),2);
            $currency=(string)($_POST['currency']??'ZAR');
            $next=trim((string)($_POST['next_review_date']??''));
            $notes=trim((string)($_POST['account_notes']??''));
            if($name===''||$value<0||!in_array($accountType,['prospect','customer','strategic_partner'],true)||!in_array($status,['active','at_risk','inactive','onboarding'],true)||!in_array($currency,['ZAR','USD','EUR','GBP'],true)||!crm_date_ok($next,true))throw new RuntimeException('Complete the required account details and check the selected values.');
            if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid email address.');
            $ref='CLI-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(3)));
            $s=$conn->prepare("INSERT INTO commercial_clients(commercial_manager_id,client_ref,client_name,industry,primary_contact,email,phone,account_type,account_status,annual_account_value,currency,satisfaction_score,next_review_date,account_notes) VALUES(?,?,?,?,?,?,?,?,?,?,?,NULL,NULLIF(?,''),?)");
            $s->bind_param('issssssssdsss',$managerId,$ref,$name,$industry,$contact,$email,$phone,$accountType,$status,$value,$currency,$next,$notes);
            if(!$s->execute())throw new RuntimeException('Unable to create client account.');$clientId=(int)$s->insert_id;$s->close();
            cm_activity($conn,$managerId,'client_account_created','Created client account '.$ref,'client',$clientId);
            $d='Client account created: '.$name;
            $s=$conn->prepare("INSERT INTO commercial_client_activities(client_id,commercial_manager_id,activity_type,subject,details,activity_date) VALUES(?,?,'account_created',?,?,NOW())");
            $s->bind_param('iiss',$clientId,$managerId,$name,$d);$s->execute();$s->close();
            cm_flash('success','Client account created.');
            crm_go($clientId);
        }
        if($clientId<1||!crm_owned($conn,$clientId,$managerId))throw new RuntimeException('Client account not found or access denied.');
        if($action==='update_client'){
            $industry=trim((string)($_POST['industry']??''));$contact=trim((string)($_POST['primary_contact']??''));$email=trim((string)($_POST['email']??''));$phone=trim((string)($_POST['phone']??''));
            $type=(string)($_POST['account_type']??'customer');$status=(string)($_POST['account_status']??'active');$value=round((float)($_POST['annual_account_value']??0),2);$currency=(string)($_POST['currency']??'ZAR');$next=trim((string)($_POST['next_review_date']??''));$notes=trim((string)($_POST['account_notes']??''));
            if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid email address.');
            if($value<0||!in_array($type,['prospect','customer','strategic_partner'],true)||!in_array($status,['active','at_risk','inactive','onboarding'],true)||!in_array($currency,['ZAR','USD','EUR','GBP'],true)||!crm_date_ok($next,true))throw new RuntimeException('Invalid account details.');
            $s=$conn->prepare("UPDATE commercial_clients SET industry=?,primary_contact=?,email=?,phone=?,account_type=?,account_status=?,annual_account_value=?,currency=?,next_review_date=NULLIF(?,''),account_notes=? WHERE id=? AND commercial_manager_id=?");
            $s->bind_param('ssssssdsssii',$industry,$contact,$email,$phone,$type,$status,$value,$currency,$next,$notes,$clientId,$managerId);
            if(!$s->execute())throw new RuntimeException('Unable to update client account.');$s->close();
            cm_activity($conn,$managerId,'client_account_updated','Updated client account details','client',$clientId);
            cm_flash('success','Client account updated.');
        }elseif($action==='record_engagement'){
            $type=(string)($_POST['activity_type']??'meeting');$subject=trim((string)($_POST['subject']??''));$details=trim((string)($_POST['details']??''));$date=trim((string)($_POST['activity_date']??date('Y-m-d')));
            if(!in_array($type,['meeting','call','email','site_visit','review','presentation','other'],true)||$subject===''||!crm_date_ok($date))throw new RuntimeException('Enter a subject, valid engagement type and date.');
            $s=$conn->prepare("INSERT INTO commercial_client_activities(client_id,commercial_manager_id,activity_type,subject,details,activity_date) VALUES(?,?,?,?,?,?)");
            $s->bind_param('iissss',$clientId,$managerId,$type,$subject,$details,$date);
            if(!$s->execute())throw new RuntimeException('Unable to save engagement activity.');$activityId=(int)$s->insert_id;$s->close();
            cm_activity($conn,$managerId,'client_engagement_recorded',$subject,'client_activity',$activityId);
            cm_flash('success','Engagement activity recorded.');
        }elseif($action==='record_satisfaction'){
            $score=(int)($_POST['satisfaction_score']??0);$feedback=trim((string)($_POST['satisfaction_feedback']??''));$date=trim((string)($_POST['survey_date']??date('Y-m-d')));
            if($score<1||$score>5||!crm_date_ok($date))throw new RuntimeException('Choose a satisfaction score from 1 to 5 and a valid date.');
            $s=$conn->prepare('UPDATE commercial_clients SET satisfaction_score=?,satisfaction_feedback=?,satisfaction_date=? WHERE id=? AND commercial_manager_id=?');
            $s->bind_param('issii',$score,$feedback,$date,$clientId,$managerId);
            if(!$s->execute())throw new RuntimeException('Unable to update satisfaction record.');$s->close();
            $d='Client satisfaction updated to '.$score.'/5. '.$feedback;
            $s=$conn->prepare("INSERT INTO commercial_client_activities(client_id,commercial_manager_id,activity_type,subject,details,activity_date) VALUES(?,?,'satisfaction_review','Client satisfaction review',?,?)");
            $s->bind_param('iiss',$clientId,$managerId,$d,$date);$s->execute();$s->close();
            cm_activity($conn,$managerId,'client_satisfaction_updated',$d,'client',$clientId);
            cm_flash('success','Client satisfaction updated.');
        }elseif($action==='create_opportunity'){
            $kind=(string)($_POST['opportunity_type']??'renewal');$title=trim((string)($_POST['opportunity_title']??''));$description=trim((string)($_POST['opportunity_description']??''));$value=round((float)($_POST['estimated_value']??0),2);$currency=(string)($_POST['currency']??'ZAR');$stage=(string)($_POST['opportunity_stage']??'identified');$due=trim((string)($_POST['target_date']??''));
            if(!in_array($kind,['renewal','service_expansion','cross_sell','upsell','new_business'],true)||$title===''||$value<0||!in_array($currency,['ZAR','USD','EUR','GBP'],true)||!in_array($stage,['identified','qualifying','proposal','negotiation','won','lost'],true)||!crm_date_ok($due,true))throw new RuntimeException('Enter valid opportunity details.');
            $s=$conn->prepare("INSERT INTO commercial_client_opportunities(client_id,commercial_manager_id,opportunity_type,title,description,estimated_value,currency,stage,target_date) VALUES(?,?,?,?,?,?,?,?,NULLIF(?,''))");
            $s->bind_param('iisssdsss',$clientId,$managerId,$kind,$title,$description,$value,$currency,$stage,$due);
            if(!$s->execute())throw new RuntimeException('Unable to save opportunity.');$oppId=(int)$s->insert_id;$s->close();
            cm_activity($conn,$managerId,'client_growth_opportunity_created',$title,'client_opportunity',$oppId);
            cm_flash('success','Growth opportunity created.');
        }elseif($action==='update_opportunity'){
            $oppId=max(0,(int)($_POST['opportunity_id']??0));$stage=(string)($_POST['opportunity_stage']??'identified');
            if(!in_array($stage,['identified','qualifying','proposal','negotiation','won','lost'],true))throw new RuntimeException('Invalid opportunity stage.');
            $s=$conn->prepare('UPDATE commercial_client_opportunities SET stage=? WHERE id=? AND client_id=? AND commercial_manager_id=?');
            $s->bind_param('siii',$stage,$oppId,$clientId,$managerId);if(!$s->execute()||$s->affected_rows<0)throw new RuntimeException('Unable to update opportunity.');$s->close();
            cm_activity($conn,$managerId,'client_growth_opportunity_updated','Opportunity stage updated to '.$stage,'client_opportunity',$oppId);
            cm_flash('success','Opportunity stage updated.');
        }else throw new RuntimeException('Unsupported client management action.');
        crm_go($clientId);
    }catch(Throwable $e){cm_flash('error',$e->getMessage());crm_go($clientId>0?$clientId:null);}
}

$selectedId=max(0,(int)($_GET['client']??0));$selected=null;$activities=[];$opportunities=[];
if($selectedId&&crm_owned($conn,$selectedId,$managerId)){
    $s=$conn->prepare('SELECT * FROM commercial_clients WHERE id=? AND commercial_manager_id=?');
    $s->bind_param('ii',$selectedId,$managerId);$s->execute();$selected=$s->get_result()->fetch_assoc();$s->close();
    if($selected){
        $s=$conn->prepare('SELECT * FROM commercial_client_activities WHERE client_id=? AND commercial_manager_id=? ORDER BY activity_date DESC,id DESC LIMIT 30');
        $s->bind_param('ii',$selectedId,$managerId);$s->execute();$activities=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();
        $s=$conn->prepare('SELECT * FROM commercial_client_opportunities WHERE client_id=? AND commercial_manager_id=? ORDER BY created_at DESC');
        $s->bind_param('ii',$selectedId,$managerId);$s->execute();$opportunities=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();
    }
}
$clients=[];$s=$conn->prepare("SELECT c.*,(SELECT COUNT(*) FROM commercial_client_activities a WHERE a.client_id=c.id) engagement_count,(SELECT COUNT(*) FROM commercial_client_opportunities o WHERE o.client_id=c.id AND o.stage NOT IN ('won','lost')) open_opportunities FROM commercial_clients c WHERE c.commercial_manager_id=? ORDER BY FIELD(c.account_status,'at_risk','active','onboarding','inactive'),c.client_name");
$s->bind_param('i',$managerId);$s->execute();$clients=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();
$stats=['clients'=>count($clients),'at_risk'=>0,'reviews'=>0,'pipeline'=>0.0];$today=date('Y-m-d');$reviewLimit=date('Y-m-d',strtotime('+30 days'));
foreach($clients as $c){if($c['account_status']==='at_risk')$stats['at_risk']++;if($c['next_review_date']&&$c['next_review_date']>=$today&&$c['next_review_date']<=$reviewLimit)$stats['reviews']++;}
$s=$conn->prepare("SELECT COALESCE(SUM(estimated_value),0) AS pipeline FROM commercial_client_opportunities WHERE commercial_manager_id=? AND stage NOT IN ('won','lost') AND currency='ZAR'");
$s->bind_param('i',$managerId);$s->execute();$stats['pipeline']=(float)($s->get_result()->fetch_assoc()['pipeline']??0);$s->close();
require __DIR__.'/_layout_start.php';
?>
<style>
.crm-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin:6px 0 20px}.crm-head h2{font-size:25px;margin:4px 0}.crm-muted{color:var(--muted);font-size:12px}.crm-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:18px 0}.crm-stat,.crm-panel{background:var(--surface);border:1px solid var(--line);border-radius:15px;padding:16px;min-width:0}.crm-stat span{display:block;color:var(--muted);font-size:12px}.crm-stat strong{display:block;font-size:24px;margin-top:7px}.crm-panel{margin:16px 0}.crm-panel h3{margin:0 0 14px;font-size:17px}.crm-form{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;align-items:end}.crm-field label{display:block;font-size:12px;font-weight:700;margin-bottom:5px}.crm-field input,.crm-field select,.crm-field textarea{width:100%;padding:10px;border:1px solid var(--line);border-radius:9px;background:var(--surface);color:var(--text);font:inherit}.crm-field textarea{min-height:76px;resize:vertical}.crm-span2{grid-column:span 2}.crm-btn{border:0;border-radius:9px;padding:10px 14px;background:var(--primary);color:white;font-weight:700;cursor:pointer;display:inline-flex;gap:7px;align-items:center;text-decoration:none}.crm-btn.alt{background:var(--surface2);color:var(--text);border:1px solid var(--line)}.crm-tablewrap{overflow:auto}.crm-table{width:100%;border-collapse:collapse;min-width:900px}.crm-table th,.crm-table td{padding:11px;border-bottom:1px solid var(--line);text-align:left;font-size:13px;vertical-align:middle}.crm-table th{font-size:11px;text-transform:uppercase;color:var(--muted)}.crm-pill{display:inline-block;padding:4px 8px;border-radius:18px;background:var(--surface2);font-size:11px;font-weight:700}.crm-pill.good{background:#dcfce7;color:#166534}.crm-pill.warn{background:#fef3c7;color:#92400e}.crm-pill.bad{background:#fee2e2;color:#991b1b}.crm-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(300px,.9fr);gap:16px}.crm-item{padding:12px 0;border-bottom:1px solid var(--line)}.crm-item:last-child{border:0}.crm-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.crm-actions select,.crm-actions input{padding:8px;border:1px solid var(--line);border-radius:8px;background:var(--surface);color:var(--text)}.crm-stars{font-size:16px;letter-spacing:2px;color:#d97706}
@media(max-width:1100px){.crm-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.crm-form{grid-template-columns:repeat(2,minmax(0,1fr))}.crm-grid{grid-template-columns:1fr}}
@media(max-width:650px){.crm-stats,.crm-form{grid-template-columns:1fr}.crm-span2{grid-column:span 1}.crm-head{display:block}}
</style>
<div class="crm-head"><div><span class="crm-muted">Commercial Operations / Client Management</span><h2>Client Relationships &amp; Account Growth</h2><p class="crm-muted">Manage client accounts, engagement, satisfaction and commercial growth opportunities.</p></div><a href="#new-client" class="crm-btn"><i class="fas fa-plus"></i> Add client</a></div>
<section class="crm-stats">
 <article class="crm-stat"><span>Client accounts</span><strong><?= (int)$stats['clients'] ?></strong></article>
 <article class="crm-stat"><span>Accounts needing attention</span><strong><?= (int)$stats['at_risk'] ?></strong></article>
 <article class="crm-stat"><span>Reviews due in 30 days</span><strong><?= (int)$stats['reviews'] ?></strong></article>
 <article class="crm-stat"><span>Open growth pipeline (ZAR)</span><strong>R <?= number_format($stats['pipeline'],2) ?></strong></article>
</section>
<section class="crm-panel" id="new-client"><h3>Register client account</h3>
<form method="post" class="crm-form"><input type="hidden" name="csrf_token" value="<?= crm_e(cm_csrf()) ?>"><input type="hidden" name="action" value="create_client">
<div class="crm-field"><label>Client / organisation</label><input name="client_name" maxlength="180" required></div><div class="crm-field"><label>Industry</label><input name="industry" maxlength="120"></div><div class="crm-field"><label>Primary contact</label><input name="primary_contact" maxlength="160"></div><div class="crm-field"><label>Email</label><input type="email" name="email" maxlength="190"></div><div class="crm-field"><label>Phone</label><input name="phone" maxlength="50"></div><div class="crm-field"><label>Account type</label><select name="account_type"><option value="prospect">Prospect</option><option value="customer">Customer</option><option value="strategic_partner">Strategic partner</option></select></div><div class="crm-field"><label>Account status</label><select name="account_status"><option value="active">Active</option><option value="onboarding">Onboarding</option><option value="at_risk">At risk</option><option value="inactive">Inactive</option></select></div><div class="crm-field"><label>Annual account value</label><input type="number" name="annual_account_value" min="0" step=".01" value="0"></div><div class="crm-field"><label>Currency</label><select name="currency"><option>ZAR</option><option>USD</option><option>EUR</option><option>GBP</option></select></div><div class="crm-field"><label>Next account review</label><input type="date" name="next_review_date"></div><div class="crm-field crm-span2"><label>Account notes</label><textarea name="account_notes"></textarea></div><div><button class="crm-btn" type="submit">Save client</button></div>
</form></section>
<section class="crm-panel"><h3>Client portfolio</h3><div class="crm-tablewrap"><table class="crm-table"><thead><tr><th>Client</th><th>Account type</th><th>Annual value</th><th>Satisfaction</th><th>Engagements</th><th>Open opportunities</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach($clients as $c):$score=(int)($c['satisfaction_score']??0);$sc=$c['account_status']==='at_risk'?'bad':($c['account_status']==='active'?'good':'warn');?><tr><td><strong><?=crm_e($c['client_name'])?></strong><div class="crm-muted"><?=crm_e($c['client_ref'])?> · <?=crm_e($c['industry'])?></div></td><td><?=crm_e(ucwords(str_replace('_',' ',$c['account_type'])))?></td><td><?=crm_e(crm_money($c['annual_account_value'],$c['currency']))?></td><td><?php if($score):?><span class="crm-stars"><?=str_repeat('★',$score).str_repeat('☆',5-$score)?></span><div class="crm-muted"><?=$score?> / 5</div><?php else:?>Not rated<?php endif;?></td><td><?=(int)$c['engagement_count']?></td><td><?=(int)$c['open_opportunities']?></td><td><span class="crm-pill <?=$sc?>"><?=crm_e(ucwords(str_replace('_',' ',$c['account_status'])))?></span></td><td><a class="crm-btn alt" href="<?=crm_e(url('commercial_manager/client_relationships.php?client='.(int)$c['id']))?>">Manage</a></td></tr><?php endforeach;?>
<?php if(!$clients):?><tr><td colspan="8">No client accounts yet. Register your first client above.</td></tr><?php endif;?></tbody></table></div></section>
<?php if($selected):?>
<section class="crm-panel"><div class="crm-head"><div><span class="crm-muted"><?=crm_e($selected['client_ref'])?></span><h2><?=crm_e($selected['client_name'])?></h2><p class="crm-muted"><?=crm_e($selected['industry'])?> · <?=crm_e($selected['primary_contact'])?> · <?=crm_e($selected['email'])?></p></div><a class="crm-btn alt" href="<?=crm_e(url('commercial_manager/client_relationships.php'))?>">Close</a></div>
<div class="crm-grid"><div>
<h3>Account profile</h3><form method="post" class="crm-form"><input type="hidden" name="csrf_token" value="<?=crm_e(cm_csrf())?>"><input type="hidden" name="action" value="update_client"><input type="hidden" name="client_id" value="<?=$selectedId?>">
<div class="crm-field"><label>Industry</label><input name="industry" value="<?=crm_e($selected['industry'])?>"></div><div class="crm-field"><label>Primary contact</label><input name="primary_contact" value="<?=crm_e($selected['primary_contact'])?>"></div><div class="crm-field"><label>Email</label><input type="email" name="email" value="<?=crm_e($selected['email'])?>"></div><div class="crm-field"><label>Phone</label><input name="phone" value="<?=crm_e($selected['phone'])?>"></div><div class="crm-field"><label>Account type</label><select name="account_type"><?php foreach(['prospect','customer','strategic_partner'] as $v):?><option value="<?=$v?>" <?=$selected['account_type']===$v?'selected':''?>><?=ucwords(str_replace('_',' ',$v))?></option><?php endforeach;?></select></div><div class="crm-field"><label>Status</label><select name="account_status"><?php foreach(['active','onboarding','at_risk','inactive'] as $v):?><option value="<?=$v?>" <?=$selected['account_status']===$v?'selected':''?>><?=ucwords(str_replace('_',' ',$v))?></option><?php endforeach;?></select></div><div class="crm-field"><label>Annual account value</label><input type="number" min="0" step=".01" name="annual_account_value" value="<?=crm_e($selected['annual_account_value'])?>"></div><div class="crm-field"><label>Currency</label><select name="currency"><?php foreach(['ZAR','USD','EUR','GBP'] as $v):?><option <?=$selected['currency']===$v?'selected':''?>><?=$v?></option><?php endforeach;?></select></div><div class="crm-field"><label>Next review date</label><input type="date" name="next_review_date" value="<?=crm_e($selected['next_review_date'])?>"></div><div class="crm-field crm-span2"><label>Account notes</label><textarea name="account_notes"><?=crm_e($selected['account_notes'])?></textarea></div><div><button class="crm-btn">Save account</button></div></form>
<h3 style="margin-top:22px">Record engagement</h3><form method="post" class="crm-form"><input type="hidden" name="csrf_token" value="<?=crm_e(cm_csrf())?>"><input type="hidden" name="action" value="record_engagement"><input type="hidden" name="client_id" value="<?=$selectedId?>"><div class="crm-field"><label>Activity type</label><select name="activity_type"><?php foreach(['meeting','call','email','site_visit','review','presentation','other'] as $v):?><option value="<?=$v?>"><?=ucwords(str_replace('_',' ',$v))?></option><?php endforeach;?></select></div><div class="crm-field"><label>Date</label><input type="date" name="activity_date" value="<?=date('Y-m-d')?>" required></div><div class="crm-field crm-span2"><label>Subject</label><input name="subject" required maxlength="180"></div><div class="crm-field crm-span2"><label>Notes / outcome / next steps</label><textarea name="details"></textarea></div><div><button class="crm-btn">Save engagement</button></div></form>
</div><div>
<h3>Client satisfaction</h3><div class="crm-item"><?php if((int)$selected['satisfaction_score']):?><span class="crm-stars"><?=str_repeat('★',(int)$selected['satisfaction_score']).str_repeat('☆',5-(int)$selected['satisfaction_score'])?></span><strong> <?= (int)$selected['satisfaction_score']?> / 5</strong><div class="crm-muted">Last reviewed <?=crm_e($selected['satisfaction_date'])?></div><p><?=crm_e($selected['satisfaction_feedback'])?></p><?php else:?><p class="crm-muted">No satisfaction score recorded yet.</p><?php endif;?></div>
<form method="post" class="crm-form" style="grid-template-columns:1fr"><input type="hidden" name="csrf_token" value="<?=crm_e(cm_csrf())?>"><input type="hidden" name="action" value="record_satisfaction"><input type="hidden" name="client_id" value="<?=$selectedId?>"><div class="crm-field"><label>Satisfaction score</label><select name="satisfaction_score" required><option value="">Choose 1–5</option><?php for($i=5;$i>=1;$i--):?><option value="<?=$i?>"><?=$i?> / 5 — <?=['1'=>'Very dissatisfied','2'=>'Dissatisfied','3'=>'Neutral','4'=>'Satisfied','5'=>'Very satisfied'][(string)$i]?></option><?php endfor;?></select></div><div class="crm-field"><label>Survey / review date</label><input type="date" name="survey_date" value="<?=date('Y-m-d')?>"></div><div class="crm-field"><label>Feedback</label><textarea name="satisfaction_feedback"></textarea></div><button class="crm-btn">Save satisfaction</button></form>
<h3 style="margin-top:22px">Growth opportunities</h3><?php foreach($opportunities as $o):?><div class="crm-item"><strong><?=crm_e($o['title'])?></strong><div class="crm-muted"><?=crm_e(ucwords(str_replace('_',' ',$o['opportunity_type'])))?> · <?=crm_e(crm_money($o['estimated_value'],$o['currency']))?> · Target <?=crm_e($o['target_date']?:'Not set')?></div><p class="crm-muted"><?=crm_e($o['description'])?></p><form method="post" class="crm-actions"><input type="hidden" name="csrf_token" value="<?=crm_e(cm_csrf())?>"><input type="hidden" name="action" value="update_opportunity"><input type="hidden" name="client_id" value="<?=$selectedId?>"><input type="hidden" name="opportunity_id" value="<?=(int)$o['id']?>"><select name="opportunity_stage"><?php foreach(['identified','qualifying','proposal','negotiation','won','lost'] as $v):?><option value="<?=$v?>" <?=$o['stage']===$v?'selected':''?>><?=ucwords($v)?></option><?php endforeach;?></select><button class="crm-btn alt">Update stage</button></form></div><?php endforeach;?>
<form method="post" class="crm-form" style="grid-template-columns:1fr"><input type="hidden" name="csrf_token" value="<?=crm_e(cm_csrf())?>"><input type="hidden" name="action" value="create_opportunity"><input type="hidden" name="client_id" value="<?=$selectedId?>"><div class="crm-field"><label>Opportunity type</label><select name="opportunity_type"><?php foreach(['renewal','service_expansion','cross_sell','upsell','new_business'] as $v):?><option value="<?=$v?>"><?=ucwords(str_replace('_',' ',$v))?></option><?php endforeach;?></select></div><div class="crm-field"><label>Opportunity title</label><input name="opportunity_title" required></div><div class="crm-field"><label>Estimated value</label><input type="number" name="estimated_value" min="0" step=".01" value="0"></div><div class="crm-field"><label>Currency</label><select name="currency"><option>ZAR</option><option>USD</option><option>EUR</option><option>GBP</option></select></div><div class="crm-field"><label>Stage</label><select name="opportunity_stage"><?php foreach(['identified','qualifying','proposal','negotiation','won','lost'] as $v):?><option value="<?=$v?>"><?=ucwords($v)?></option><?php endforeach;?></select></div><div class="crm-field"><label>Target close date</label><input type="date" name="target_date"></div><div class="crm-field"><label>Description</label><textarea name="opportunity_description"></textarea></div><button class="crm-btn">Add opportunity</button></form>
</div></div>
<h3 style="margin-top:22px">Engagement history</h3><?php foreach($activities as $a):?><div class="crm-item"><strong><?=crm_e(ucwords(str_replace('_',' ',$a['activity_type'])))?> — <?=crm_e($a['subject'])?></strong><div class="crm-muted"><?=crm_e($a['activity_date'])?></div><p><?=nl2br(crm_e($a['details']))?></p></div><?php endforeach;?><?php if(!$activities):?><p class="crm-muted">No engagement activities recorded.</p><?php endif;?>
</section><?php endif;?>
<?php require __DIR__.'/_layout_end.php';?>
