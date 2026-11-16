<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('supervisor');
require_once __DIR__ . '/_helpers.php';

$user = current_user();
$supervisorId = (int)($user['id'] ?? $user['user_id'] ?? 0);
if ($supervisorId <= 0) { http_response_code(403); exit('Invalid Supervisor account.'); }
$currentPage = 'training_monitoring';
$pageTitle = 'Training & Learning Progress';
$flashes = render_flashes();
$error = '';
$success = '';

function tm_redirect(string $query = ''): never {
    header('Location: ' . url('supervisor/training_monitoring.php' . ($query !== '' ? '?' . $query : '')));
    exit;
}
function tm_post_string(string $key, int $max = 5000): string {
    $value = trim((string)($_POST[$key] ?? ''));
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}
function tm_cohort_allowed(int $cohortId, int $supervisorId): bool {
    $st = Database::prepare('SELECT id FROM cohorts WHERE id=? AND supervisor_id=? LIMIT 1', 'ii', [$cohortId, $supervisorId]);
    $ok = (bool)$st->get_result()->fetch_assoc(); $st->close(); return $ok;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!sv_verify_csrf((string)($_POST['csrf_token'] ?? ''))) {
        $error = 'Your session token is invalid. Refresh the page and try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'create_session') {
            $cohortId = (int)($_POST['cohort_id'] ?? 0);
            $title = tm_post_string('title', 190);
            $type = (string)($_POST['session_type'] ?? 'academic');
            $date = (string)($_POST['session_date'] ?? '');
            $mode = (string)($_POST['delivery_mode'] ?? 'on_site');
            $allowedTypes = ['academic','practical','workshop','assessment','mentoring','other'];
            $allowedModes = ['on_site','remote','hybrid'];
            if (!tm_cohort_allowed($cohortId, $supervisorId)) $error = 'Select one of your assigned cohorts.';
            elseif ($title === '' || !in_array($type, $allowedTypes, true) || !in_array($mode, $allowedModes, true) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $error = 'Complete the required session details with valid values.';
            else {
                $description = tm_post_string('description');
                $objectives = tm_post_string('learning_objectives');
                $venue = tm_post_string('venue', 190);
                $start = (string)($_POST['start_time'] ?? ''); $end = (string)($_POST['end_time'] ?? '');
                $start = preg_match('/^\d{2}:\d{2}$/', $start) ? $start : null;
                $end = preg_match('/^\d{2}:\d{2}$/', $end) ? $end : null;
                $st = Database::prepare('INSERT INTO supervisor_training_sessions (cohort_id,supervisor_id,title,session_type,description,session_date,start_time,end_time,venue,delivery_mode,learning_objectives,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,\'scheduled\')', 'iisssssssss', [$cohortId,$supervisorId,$title,$type,$description,$date,$start,$end,$venue,$mode,$objectives]);
                $st->close(); tm_redirect('created=1');
            }
        } elseif ($action === 'save_evaluation') {
            $sessionId = (int)($_POST['session_id'] ?? 0);
            $candidateId = (int)($_POST['candidate_id'] ?? 0);
            $st = Database::prepare('SELECT s.id,s.cohort_id FROM supervisor_training_sessions s WHERE s.id=? AND s.supervisor_id=? LIMIT 1', 'ii', [$sessionId,$supervisorId]);
            $session = $st->get_result()->fetch_assoc(); $st->close();
            if (!$session) $error = 'Training session not found in your assigned cohorts.';
            else {
                $cohortId = (int)$session['cohort_id'];
                $member = Database::prepare("SELECT cp.user_id FROM cohort_participants cp WHERE cp.cohort_id=? AND cp.user_id=? AND cp.status NOT IN ('withdrawn') LIMIT 1", 'ii', [$cohortId,$candidateId]);
                $isMember = (bool)$member->get_result()->fetch_assoc(); $member->close();
                $attendance = (string)($_POST['attendance_status'] ?? 'present');
                $participation = (string)($_POST['participation_level'] ?? 'satisfactory');
                $progress = (string)($_POST['learning_progress'] ?? 'on_track');
                $support = (string)($_POST['support_type'] ?? 'none');
                $followStatus = (string)($_POST['follow_up_status'] ?? 'pending');
                $valid = [
                    'attendance_status'=>['present','late','absent','excused'],
                    'participation_level'=>['excellent','good','satisfactory','limited','not_participated'],
                    'learning_progress'=>['exceeding','on_track','developing','at_risk'],
                    'support_type'=>['none','academic','practical','academic_and_practical','wellbeing_or_referral'],
                    'follow_up_status'=>['not_required','pending','in_progress','resolved']
                ];
                foreach ($valid as $key=>$values) if (!in_array((string)($_POST[$key] ?? ''),$values,true)) $error = 'One or more evaluation selections are invalid.';
                $academic = ($_POST['academic_score'] ?? '') === '' ? null : (float)$_POST['academic_score'];
                $practical = ($_POST['practical_score'] ?? '') === '' ? null : (float)$_POST['practical_score'];
                if (!$isMember) $error = 'This candidate is not an active participant in this cohort.';
                elseif ($academic !== null && ($academic < 0 || $academic > 100)) $error = 'Academic score must be between 0 and 100.';
                elseif ($practical !== null && ($practical < 0 || $practical > 100)) $error = 'Practical score must be between 0 and 100.';
                if ($error === '') {
                    $overall = ($academic !== null && $practical !== null) ? round(($academic+$practical)/2,2) : ($academic ?? $practical);
                    $strengths=tm_post_string('strengths'); $gaps=tm_post_string('learning_gaps'); $supportNotes=tm_post_string('support_notes'); $notes=tm_post_string('evaluator_notes');
                    $followDate=(string)($_POST['follow_up_date'] ?? ''); $followDate=preg_match('/^\d{4}-\d{2}-\d{2}$/',$followDate)?$followDate:null;
                    $st = Database::prepare('INSERT INTO supervisor_training_evaluations (session_id,cohort_id,candidate_id,supervisor_id,attendance_status,participation_level,academic_score,practical_score,overall_score,learning_progress,strengths,learning_gaps,support_type,support_notes,follow_up_date,follow_up_status,evaluator_notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE attendance_status=VALUES(attendance_status),participation_level=VALUES(participation_level),academic_score=VALUES(academic_score),practical_score=VALUES(practical_score),overall_score=VALUES(overall_score),learning_progress=VALUES(learning_progress),strengths=VALUES(strengths),learning_gaps=VALUES(learning_gaps),support_type=VALUES(support_type),support_notes=VALUES(support_notes),follow_up_date=VALUES(follow_up_date),follow_up_status=VALUES(follow_up_status),evaluator_notes=VALUES(evaluator_notes),supervisor_id=VALUES(supervisor_id)', 'iiiissdddssssssss', [$sessionId,$cohortId,$candidateId,$supervisorId,$attendance,$participation,$academic,$practical,$overall,$progress,$strengths,$gaps,$support,$supportNotes,$followDate,$followStatus,$notes]);
                    $st->close(); tm_redirect('session_id='.$sessionId.'&saved=1');
                }
            }
        } elseif ($action === 'update_session_status') {
            $sessionId=(int)($_POST['session_id']??0); $status=(string)($_POST['status']??'');
            if (!in_array($status,['scheduled','completed','cancelled'],true)) $error='Invalid session status.';
            else { $st=Database::prepare('UPDATE supervisor_training_sessions SET status=? WHERE id=? AND supervisor_id=?','sii',[$status,$sessionId,$supervisorId]); $st->close(); tm_redirect('session_id='.$sessionId); }
        }
    }
}

$cohorts=[];
$st=Database::prepare('SELECT c.id,c.name,p.name AS programme_name FROM cohorts c JOIN programmes p ON p.id=c.programme_id WHERE c.supervisor_id=? ORDER BY c.start_date DESC,c.id DESC','i',[$supervisorId]);
$r=$st->get_result(); while($r&&$row=$r->fetch_assoc()) $cohorts[(int)$row['id']]=$row; $st->close();
$cohortFilter=(int)($_GET['cohort_id']??0); if($cohortFilter>0&&!isset($cohorts[$cohortFilter]))$cohortFilter=0;
$sessionId=(int)($_GET['session_id']??0);
$sessions=[];
$sql='SELECT s.*,c.name cohort_name,p.name programme_name,(SELECT COUNT(*) FROM cohort_participants cp WHERE cp.cohort_id=s.cohort_id AND cp.status<>\'withdrawn\') participant_count,(SELECT COUNT(*) FROM supervisor_training_evaluations ev WHERE ev.session_id=s.id) evaluated_count FROM supervisor_training_sessions s JOIN cohorts c ON c.id=s.cohort_id JOIN programmes p ON p.id=c.programme_id WHERE s.supervisor_id=?';
$params=[$supervisorId];$types='i'; if($cohortFilter>0){$sql.=' AND s.cohort_id=?';$params[]=$cohortFilter;$types.='i';}$sql.=' ORDER BY s.session_date DESC,s.start_time DESC,s.id DESC LIMIT 100';
$st=Database::prepare($sql,$types,$params);$r=$st->get_result();while($r&&$row=$r->fetch_assoc())$sessions[]=$row;$st->close();
$selected=null; foreach($sessions as $s)if((int)$s['id']===$sessionId)$selected=$s;
$candidates=[];
if($selected){$st=Database::prepare("SELECT u.id candidate_id,u.first_name,u.last_name,u.email,cp.status participant_status,ev.id evaluation_id,ev.attendance_status,ev.participation_level,ev.academic_score,ev.practical_score,ev.learning_progress,ev.strengths,ev.learning_gaps,ev.support_type,ev.support_notes,ev.follow_up_date,ev.follow_up_status,ev.evaluator_notes,ev.updated_at FROM cohort_participants cp JOIN users u ON u.id=cp.user_id LEFT JOIN supervisor_training_evaluations ev ON ev.session_id=? AND ev.candidate_id=u.id WHERE cp.cohort_id=? AND cp.status<>'withdrawn' ORDER BY u.first_name,u.last_name",'ii',[$sessionId,(int)$selected['cohort_id']]);$r=$st->get_result();while($r&&$row=$r->fetch_assoc())$candidates[]=$row;$st->close();}

require __DIR__ . '/_layout_start.php';
?>
<style>
.tm-wrap{display:grid;gap:18px}.tm-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.tm-stat,.tm-panel{background:var(--sv-card,#fff);border:1px solid var(--sv-border,#e5e7eb);border-radius:14px}.tm-stat{padding:16px}.tm-stat strong{display:block;font-size:25px;color:var(--sv-text,#111827)}.tm-stat span,.tm-muted{color:var(--sv-muted,#667085);font-size:13px}.tm-panel{padding:18px}.tm-panel h3{margin:0 0 12px}.tm-formgrid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.tm-field label{display:block;font-size:13px;font-weight:600;margin-bottom:6px}.tm-field input,.tm-field select,.tm-field textarea{width:100%;box-sizing:border-box;padding:10px 11px;border:1px solid var(--sv-border,#d0d5dd);border-radius:9px;background:var(--sv-input-bg,var(--sv-card,#fff));color:var(--sv-text,#111827)}.tm-field textarea{min-height:72px;resize:vertical}.tm-btn{border:0;border-radius:9px;padding:10px 14px;background:#1d4ed8;color:#fff;font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:7px}.tm-btn.secondary{background:transparent;color:var(--sv-text,#111827);border:1px solid var(--sv-border,#d0d5dd)}.tm-tablewrap{overflow-x:auto}.tm-table{width:100%;border-collapse:collapse;min-width:900px}.tm-table th,.tm-table td{padding:11px 9px;border-bottom:1px solid var(--sv-border,#e5e7eb);text-align:left;vertical-align:top;font-size:13px}.tm-table th{color:var(--sv-muted,#667085);font-weight:700}.tm-pill{display:inline-block;padding:4px 8px;border-radius:20px;background:#eef2ff;color:#3730a3;font-size:11px;font-weight:700}.tm-pill.warn{background:#fff7ed;color:#9a3412}.tm-eval{border:1px solid var(--sv-border,#e5e7eb);border-radius:12px;padding:14px;margin:12px 0}.tm-eval h4{margin:0 0 10px}.tm-alert{padding:12px 14px;border-radius:10px;background:#fff7ed;color:#9a3412}.tm-success{padding:12px 14px;border-radius:10px;background:#ecfdf3;color:#166534}.tm-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}@media(max-width:900px){.tm-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.tm-formgrid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:600px){.tm-grid,.tm-formgrid{grid-template-columns:1fr}}
</style>
<div class="sv-page-header"><div><span class="sv-eyebrow">Programme Delivery</span><h2>Training Session Monitoring & Learning Progress</h2><p>Schedule sessions, evaluate participation and identify candidates who need additional academic or practical support.</p></div><div class="sv-actions"><a class="sv-btn sv-btn--secondary" href="<?= e(url('supervisor/dashboard.php')) ?>"><i class="fas fa-arrow-left"></i> Dashboard</a></div></div>
<div class="tm-wrap">
<?php if($error): ?><div class="tm-alert"><?= e($error) ?></div><?php endif; ?>
<?php if(isset($_GET['created'])||isset($_GET['saved'])): ?><div class="tm-success"><?= isset($_GET['created'])?'Training session created successfully.':'Candidate evaluation saved successfully.' ?></div><?php endif; ?>
<div class="tm-grid"><div class="tm-stat"><strong><?= count($sessions) ?></strong><span>Sessions shown</span></div><div class="tm-stat"><strong><?= count(array_filter($sessions,fn($s)=>$s['status']==='scheduled')) ?></strong><span>Scheduled sessions</span></div><div class="tm-stat"><strong><?= count(array_filter($sessions,fn($s)=>$s['status']==='completed')) ?></strong><span>Completed sessions</span></div><div class="tm-stat"><strong><?= count(array_filter($sessions,fn($s)=>(int)$s['evaluated_count']<(int)$s['participant_count'])) ?></strong><span>Sessions with evaluations outstanding</span></div></div>
<section class="tm-panel"><h3><i class="fas fa-calendar-plus"></i> Schedule a training session</h3><form method="post"><input type="hidden" name="csrf_token" value="<?= e(sv_csrf_token()) ?>"><input type="hidden" name="action" value="create_session"><div class="tm-formgrid">
<div class="tm-field"><label>Cohort *</label><select name="cohort_id" required><option value="">Select cohort</option><?php foreach($cohorts as $id=>$c): ?><option value="<?= $id ?>" <?= $cohortFilter===$id?'selected':'' ?>><?= e($c['name'].' · '.$c['programme_name']) ?></option><?php endforeach; ?></select></div>
<div class="tm-field"><label>Session title *</label><input name="title" maxlength="190" required placeholder="e.g. Database Design Practical"></div>
<div class="tm-field"><label>Session category *</label><select name="session_type"><option value="academic">Academic</option><option value="practical">Practical</option><option value="workshop">Workshop</option><option value="assessment">Assessment</option><option value="mentoring">Mentoring</option><option value="other">Other</option></select></div>
<div class="tm-field"><label>Date *</label><input type="date" name="session_date" required></div><div class="tm-field"><label>Start time</label><input type="time" name="start_time"></div><div class="tm-field"><label>End time</label><input type="time" name="end_time"></div>
<div class="tm-field"><label>Delivery mode</label><select name="delivery_mode"><option value="on_site">On-site</option><option value="remote">Remote</option><option value="hybrid">Hybrid</option></select></div><div class="tm-field"><label>Venue / meeting link</label><input name="venue" maxlength="190"></div><div class="tm-field"><label>Description</label><input name="description" maxlength="500"></div>
<div class="tm-field" style="grid-column:1/-1"><label>Learning objectives</label><textarea name="learning_objectives" placeholder="What should candidates know or be able to do after this session?"></textarea></div></div><div class="tm-actions"><button class="tm-btn" type="submit"><i class="fas fa-plus"></i> Create session</button></div></form></section>
<section class="tm-panel"><div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap"><h3><i class="fas fa-calendar-days"></i> Scheduled and previous sessions</h3><form method="get"><select name="cohort_id" onchange="this.form.submit()" style="padding:9px;border-radius:8px"><option value="0">All assigned cohorts</option><?php foreach($cohorts as $id=>$c): ?><option value="<?= $id ?>" <?= $cohortFilter===$id?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></form></div>
<div class="tm-tablewrap"><table class="tm-table"><thead><tr><th>Session</th><th>Cohort</th><th>Date / Time</th><th>Type</th><th>Status</th><th>Evaluations</th><th>Action</th></tr></thead><tbody><?php foreach($sessions as $s): ?><tr><td><strong><?= e($s['title']) ?></strong><br><span class="tm-muted"><?= e($s['venue']??'') ?></span></td><td><?= e($s['cohort_name']) ?></td><td><?= e(sv_date($s['session_date'])) ?><br><span class="tm-muted"><?= e(substr((string)$s['start_time'],0,5)) ?><?= $s['end_time']?' – '.e(substr((string)$s['end_time'],0,5)):'' ?></span></td><td><?= e(ucwords(str_replace('_',' ',$s['session_type']))) ?></td><td><span class="tm-pill <?= $s['status']==='cancelled'?'warn':'' ?>"><?= e(ucwords($s['status'])) ?></span></td><td><?= (int)$s['evaluated_count'] ?> / <?= (int)$s['participant_count'] ?></td><td><a class="tm-btn secondary" href="<?= e(url('supervisor/training_monitoring.php?session_id='.(int)$s['id'])) ?>">Evaluate candidates</a></td></tr><?php endforeach; ?><?php if(!$sessions): ?><tr><td colspan="7" class="tm-muted">No training sessions have been scheduled for your assigned cohorts.</td></tr><?php endif; ?></tbody></table></div></section>
<?php if($selected): ?>
<section class="tm-panel"><div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap"><div><h3><i class="fas fa-clipboard-check"></i> Candidate participation & learning evaluation</h3><p class="tm-muted"><?= e($selected['title'].' · '.$selected['cohort_name'].' · '.sv_date($selected['session_date'])) ?></p></div><form method="post" class="tm-actions"><input type="hidden" name="csrf_token" value="<?= e(sv_csrf_token()) ?>"><input type="hidden" name="action" value="update_session_status"><input type="hidden" name="session_id" value="<?= (int)$selected['id'] ?>"><select name="status" style="padding:8px;border-radius:8px"><option value="scheduled" <?= $selected['status']==='scheduled'?'selected':'' ?>>Scheduled</option><option value="completed" <?= $selected['status']==='completed'?'selected':'' ?>>Completed</option><option value="cancelled" <?= $selected['status']==='cancelled'?'selected':'' ?>>Cancelled</option></select><button class="tm-btn secondary" type="submit">Update status</button></form></div>
<?php foreach($candidates as $candidate): ?><form method="post" class="tm-eval"><input type="hidden" name="csrf_token" value="<?= e(sv_csrf_token()) ?>"><input type="hidden" name="action" value="save_evaluation"><input type="hidden" name="session_id" value="<?= (int)$selected['id'] ?>"><input type="hidden" name="candidate_id" value="<?= (int)$candidate['candidate_id'] ?>"><h4><?= e(trim($candidate['first_name'].' '.$candidate['last_name'])) ?> <span class="tm-muted">· <?= e($candidate['email']) ?></span> <?php if(in_array($candidate['learning_progress'],['developing','at_risk'],true)||($candidate['support_type']??'none')!=='none'): ?><span class="tm-pill warn">Support flagged</span><?php endif; ?></h4>
<div class="tm-formgrid"><div class="tm-field"><label>Attendance</label><select name="attendance_status"><?php foreach(['present','late','absent','excused'] as $v): ?><option value="<?= $v ?>" <?= ($candidate['attendance_status']??'present')===$v?'selected':'' ?>><?= e(ucwords($v)) ?></option><?php endforeach; ?></select></div><div class="tm-field"><label>Participation</label><select name="participation_level"><?php foreach(['excellent','good','satisfactory','limited','not_participated'] as $v): ?><option value="<?= $v ?>" <?= ($candidate['participation_level']??'satisfactory')===$v?'selected':'' ?>><?= e(ucwords(str_replace('_',' ',$v))) ?></option><?php endforeach; ?></select></div><div class="tm-field"><label>Learning progress</label><select name="learning_progress"><?php foreach(['exceeding','on_track','developing','at_risk'] as $v): ?><option value="<?= $v ?>" <?= ($candidate['learning_progress']??'on_track')===$v?'selected':'' ?>><?= e(ucwords(str_replace('_',' ',$v))) ?></option><?php endforeach; ?></select></div>
<div class="tm-field"><label>Academic score (0–100)</label><input type="number" name="academic_score" min="0" max="100" step="0.01" value="<?= e((string)($candidate['academic_score']??'')) ?>"></div><div class="tm-field"><label>Practical score (0–100)</label><input type="number" name="practical_score" min="0" max="100" step="0.01" value="<?= e((string)($candidate['practical_score']??'')) ?>"></div><div class="tm-field"><label>Support required</label><select name="support_type"><?php foreach(['none','academic','practical','academic_and_practical','wellbeing_or_referral'] as $v): ?><option value="<?= $v ?>" <?= ($candidate['support_type']??'none')===$v?'selected':'' ?>><?= e(ucwords(str_replace('_',' ',$v))) ?></option><?php endforeach; ?></select></div>
<div class="tm-field"><label>Strengths observed</label><textarea name="strengths"><?= e($candidate['strengths']??'') ?></textarea></div><div class="tm-field"><label>Learning gaps / challenges</label><textarea name="learning_gaps"><?= e($candidate['learning_gaps']??'') ?></textarea></div><div class="tm-field"><label>Support / intervention notes</label><textarea name="support_notes"><?= e($candidate['support_notes']??'') ?></textarea></div>
<div class="tm-field"><label>Follow-up date</label><input type="date" name="follow_up_date" value="<?= e($candidate['follow_up_date']??'') ?>"></div><div class="tm-field"><label>Follow-up status</label><select name="follow_up_status"><?php foreach(['not_required','pending','in_progress','resolved'] as $v): ?><option value="<?= $v ?>" <?= ($candidate['follow_up_status']??'pending')===$v?'selected':'' ?>><?= e(ucwords(str_replace('_',' ',$v))) ?></option><?php endforeach; ?></select></div><div class="tm-field"><label>Supervisor evaluation notes</label><textarea name="evaluator_notes"><?= e($candidate['evaluator_notes']??'') ?></textarea></div></div><div class="tm-actions"><button class="tm-btn" type="submit"><i class="fas fa-floppy-disk"></i> Save evaluation</button><?php if($candidate['evaluation_id']): ?><span class="tm-muted">Last evaluation saved · <?= e(sv_datetime($candidate['updated_at']??'')) ?></span><?php endif; ?></div></form><?php endforeach; ?>
<?php if(!$candidates): ?><p class="tm-muted">No active candidates are currently linked to this cohort.</p><?php endif; ?></section>
<?php endif; ?>
<section class="tm-panel"><h3><i class="fas fa-life-ring"></i> Candidates requiring additional support</h3><p class="tm-muted">This list is derived from evaluations recorded for your assigned cohorts. Candidates marked Developing / At risk or assigned an academic, practical or referral support type should be followed up.</p><div class="tm-tablewrap"><table class="tm-table"><thead><tr><th>Candidate</th><th>Session</th><th>Progress</th><th>Support area</th><th>Follow-up</th></tr></thead><tbody>
<?php $supportRows=[];$st=Database::prepare("SELECT u.first_name,u.last_name,s.title,s.id session_id,e.learning_progress,e.support_type,e.follow_up_date,e.follow_up_status FROM supervisor_training_evaluations e JOIN supervisor_training_sessions s ON s.id=e.session_id JOIN users u ON u.id=e.candidate_id WHERE e.supervisor_id=? AND (e.learning_progress IN ('developing','at_risk') OR e.support_type<>'none') ORDER BY e.follow_up_date IS NULL,e.follow_up_date ASC,s.session_date DESC LIMIT 50",'i',[$supervisorId]);$r=$st->get_result();while($r&&$row=$r->fetch_assoc())$supportRows[]=$row;$st->close();foreach($supportRows as $row): ?><tr><td><?= e($row['first_name'].' '.$row['last_name']) ?></td><td><a href="<?= e(url('supervisor/training_monitoring.php?session_id='.(int)$row['session_id'])) ?>"><?= e($row['title']) ?></a></td><td><?= e(ucwords(str_replace('_',' ',$row['learning_progress']))) ?></td><td><?= e(ucwords(str_replace('_',' ',$row['support_type']))) ?></td><td><?= e($row['follow_up_date']?sv_date($row['follow_up_date']):'Not scheduled') ?> · <?= e(ucwords(str_replace('_',' ',$row['follow_up_status']))) ?></td></tr><?php endforeach; ?><?php if(!$supportRows): ?><tr><td colspan="5" class="tm-muted">No candidates are currently flagged for additional support.</td></tr><?php endif; ?></tbody></table></div></section>
</div>
<?php require __DIR__ . '/_layout_end.php'; ?>
