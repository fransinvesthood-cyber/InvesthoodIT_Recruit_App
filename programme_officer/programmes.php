<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_role('programme_officer');

$user=current_user();
$flashes=render_flashes();
$currentPage='programmes';
$pageTitle='My Programmes';

require_once __DIR__.'/_helpers.php';

$conn=Database::getConnection();
$officerId=(int)($user['id']??$user['user_id']??0);

if($officerId<=0){
    http_response_code(403);
    exit('Invalid Programme Officer account.');
}

$scope=po_scope($conn,'p','c');
$scopeMode=po_scope_mode($scope);
$scopeCondition=po_scope_condition($scope);

$search=trim((string)($_GET['search']??''));
$status=trim((string)($_GET['status']??''));

$where=[$scopeCondition];
$params=[$officerId];
$types='i';

if($search!==''){
    $where[]='(p.name LIKE ? OR p.type LIKE ?)';
    $like='%'.$search.'%';
    $params[]=$like;
    $params[]=$like;
    $types.='ss';
}

if($status!==''){
    $where[]='p.status = ?';
    $params[]=$status;
    $types.='s';
}

$rows=[];

if($scopeMode!=='none'){
    $sql="
        SELECT
            p.id,
            p.name,
            p.type,
            p.status,
            p.start_date,
            p.end_date,
            COUNT(DISTINCT c.id) cohort_count,
            COUNT(DISTINCT CASE WHEN cp.status<>'withdrawn' THEN cp.user_id END) candidate_count,
            COUNT(DISTINCT CASE WHEN cp.status='completed' THEN cp.user_id END) completed_count
        FROM programmes p
        LEFT JOIN cohorts c
            ON c.programme_id=p.id
        LEFT JOIN cohort_participants cp
            ON cp.cohort_id=c.id
        WHERE ".implode(' AND ',$where)."
        GROUP BY
            p.id,p.name,p.type,p.status,p.start_date,p.end_date
        ORDER BY
            CASE WHEN p.status='active' THEN 0 ELSE 1 END,
            p.start_date DESC,
            p.id DESC
    ";

    $stmt=$conn->prepare($sql);

    if($stmt){
        $stmt->bind_param($types,...$params);
        $stmt->execute();
        $result=$stmt->get_result();

        while($row=$result->fetch_assoc()){
            $rows[]=$row;
        }

        $stmt->close();
    }
}

$assignmentCount=po_assignment_count($conn,$officerId);

require __DIR__.'/_layout_start.php';
?>
<div class="po-page-header">
    <div>
        <span class="po-page-header__eyebrow">Programme Management</span>
        <h2>My Programmes</h2>
        <p>Track delivery, cohorts and candidate progress across programmes assigned to your Programme Officer account.</p>
    </div>
</div>

<?php if($scopeMode==='none'):?>
<div class="po-alert">
    <i class="fas fa-triangle-exclamation"></i>
    <div>
        <strong>Programme Officer assignment relationship is not configured.</strong>
        <span>Add programmes.programme_officer_id (recommended) or cohorts.programme_officer_id.</span>
    </div>
</div>
<?php elseif($assignmentCount===0):?>
<div class="po-alert">
    <i class="fas fa-user-lock"></i>
    <div>
        <strong>No programmes are available in the programme portfolio.</strong>
        <span>The portal detected the assignment relationship correctly, but there are no records linked to the currently logged-in user. Create or import programme records to populate this portal.</span>
    </div>
</div>
<?php endif;?>

<form class="po-filter" method="get">
    <div class="po-field po-field--grow">
        <label>Search</label>
        <div class="po-input-icon">
            <i class="fas fa-magnifying-glass"></i>
            <input name="search" value="<?= e($search) ?>" placeholder="Programme name or type">
        </div>
    </div>

    <div class="po-field">
        <label>Status</label>
        <select name="status">
            <option value="">All statuses</option>
            <?php foreach(['active','inactive','completed','upcoming'] as $s):?>
                <option value="<?= e($s) ?>" <?= $status===$s?'selected':'' ?>>
                    <?= e(po_status_label($s)) ?>
                </option>
            <?php endforeach;?>
        </select>
    </div>

    <div class="po-filter__actions">
        <button class="po-btn po-btn--primary">
            <i class="fas fa-filter"></i> Apply
        </button>
        <a class="po-btn po-btn--secondary" href="<?= url('programme_officer/programmes.php') ?>">Reset</a>
    </div>
</form>

<?php if(!$rows):?>
<div class="po-card">
    <div class="po-empty">
        <div class="po-empty__icon"><i class="fas fa-diagram-project"></i></div>
        <strong>No programmes found</strong>
        <span>
            <?= $assignmentCount===0
                ? 'No programme is currently assigned to your Programme Officer account.'
                : 'Try changing your filters.' ?>
        </span>
    </div>
</div>
<?php else:?>
<div class="po-programme-grid">
    <?php foreach($rows as $p):
        $total=(int)$p['candidate_count'];
        $done=(int)$p['completed_count'];
        $rate=po_completion_rate($total,$done);
    ?>
    <article class="po-programme-card">
        <div class="po-programme-card__top">
            <span class="po-programme-card__icon"><i class="fas fa-diagram-project"></i></span>
            <span class="po-status po-status--<?= e(po_status_class($p['status'])) ?>">
                <?= e(po_status_label($p['status'])) ?>
            </span>
        </div>

        <h3><?= e($p['name']) ?></h3>
        <p><?= e((string)($p['type']??'Programme')) ?></p>

        <div class="po-programme-card__meta">
            <span><i class="fas fa-layer-group"></i> <?= number_format((int)$p['cohort_count']) ?> cohorts</span>
            <span><i class="fas fa-users"></i> <?= number_format($total) ?> candidates</span>
        </div>

        <div class="po-progress">
            <div class="po-progress__labels">
                <span>Completion</span>
                <strong><?= $rate ?>%</strong>
            </div>
            <div class="po-progress__track">
                <span style="width:<?= $rate ?>%"></span>
            </div>
        </div>

        <div class="po-programme-card__dates">
            <span><small>Start</small><?= e(po_date($p['start_date'])) ?></span>
            <span><small>End</small><?= e(po_date($p['end_date'])) ?></span>
        </div>

        <a class="po-btn po-btn--secondary po-btn--block"
           href="<?= url('programme_officer/cohorts.php?programme_id='.(int)$p['id']) ?>">
            View Programme Cohorts <i class="fas fa-arrow-right"></i>
        </a>
    </article>
    <?php endforeach;?>
</div>
<?php endif;?>

<?php require __DIR__.'/_layout_end.php';?>
