<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_role('programme_officer');
require_once __DIR__.'/_helpers.php';

$user=current_user();
$conn=Database::getConnection();

$officerId=(int)($user['id']??$user['user_id']??0);
$scope=po_scope($conn,'p','c');
$scopeMode=po_scope_mode($scope);
$assignmentCount=po_assignment_count($conn,$officerId);

header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Programme Officer Assignment Check</title>
<style>
body{font-family:Arial,sans-serif;background:#f7f8fa;color:#111827;padding:30px}
.card{max-width:900px;margin:auto;background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:24px}
table{width:100%;border-collapse:collapse;margin-top:16px}
th,td{border-bottom:1px solid #e5e7eb;text-align:left;padding:10px}
code{background:#f3f4f6;padding:2px 5px;border-radius:5px}
.ok{color:#15803d}.warn{color:#b45309}
</style>
</head>
<body>
<div class="card">
<h2>Programme Officer Assignment Check</h2>
<p><strong>Logged-in user ID:</strong> <?= (int)$officerId ?></p>
<p><strong>Detected scope:</strong> <?= e($scopeMode) ?></p>
<p><strong>Assignments found:</strong> <?= number_format($assignmentCount) ?></p>

<?php if($scopeMode==='programme'):?>
<p class="<?= $assignmentCount>0?'ok':'warn' ?>">
The portal is using <code>programmes.programme_officer_id</code>.
</p>
<table>
<thead><tr><th>ID</th><th>Programme</th><th>Officer ID</th><th>Status</th></tr></thead>
<tbody>
<?php
$stmt=$conn->prepare("SELECT id,name,programme_officer_id,status FROM programmes WHERE programme_officer_id=? ORDER BY id DESC");
$stmt->bind_param('i',$officerId);
$stmt->execute();
$r=$stmt->get_result();
while($row=$r->fetch_assoc()):
?>
<tr>
<td><?= (int)$row['id'] ?></td>
<td><?= e($row['name']) ?></td>
<td><?= (int)$row['programme_officer_id'] ?></td>
<td><?= e($row['status']) ?></td>
</tr>
<?php endwhile; $stmt->close(); ?>
</tbody>
</table>
<?php elseif($scopeMode==='cohort'):?>
<p class="<?= $assignmentCount>0?'ok':'warn' ?>">
The portal is using <code>cohorts.programme_officer_id</code>.
</p>
<?php else:?>
<p class="warn">No supported Programme Officer assignment column was detected.</p>
<?php endif;?>
</div>
</body>
</html>
