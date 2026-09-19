<?php
/**
 * INVESTHOOD IT - Selection filter diagnostics (READ-ONLY, admin only).
 * Visit: admin/selection_debug.php?status=selected
 * Delete this file after debugging.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');
header('Content-Type: text/plain; charset=utf-8');

function dbg($label, $v) {
    echo "=== $label ===\n";
    if (is_string($v)) { echo $v . "\n\n"; return; }
    echo print_r($v, true) . "\n";
}

dbg('GET', $_GET);

// Replicate selection.php normalisation
$fStatusRaw = strtolower(trim((string) ($_GET['status'] ?? '')));
$fStatus    = $fStatusRaw === 'waitlisted' ? 'on_hold' : $fStatusRaw;
dbg('normalised status', ['raw' => $_GET['status'] ?? null, 'normalised' => $fStatus]);

// 1. What statuses actually exist?
try {
    dbg('applications GROUP BY status', Database::fetchAll("SELECT status, COUNT(*) c FROM applications GROUP BY status"));
} catch (Throwable $e) { dbg('applications GROUP BY ERROR', $e->getMessage()); }

// 2. The selected rows themselves + their owner role
try {
    dbg("selected rows + owner role", Database::fetchAll(
        "SELECT a.id, a.application_reference, a.status, a.candidate_id, u.first_name, u.last_name, u.role_id, r.slug AS role_slug, r.name AS role_name
         FROM applications a LEFT JOIN users u ON u.id=a.candidate_id LEFT JOIN roles r ON r.id=u.role_id
         WHERE a.status='selected' LIMIT 20"
    ));
} catch (Throwable $e) { dbg('selected rows ERROR', $e->getMessage()); }

// 3. Roles table (is slug exactly 'candidate'?)
try {
    dbg('roles', Database::fetchAll("SELECT id, name, slug FROM roles"));
} catch (Throwable $e) { dbg('roles ERROR', $e->getMessage()); }

// 4. Do helper tables exist?
foreach (['interviews','interview_feedback','selection_decisions','offers'] as $t) {
    try {
        $r = Database::fetchOne("SHOW TABLES LIKE '$t'");
        dbg("table $t exists?", $r ? 'YES' : 'NO');
    } catch (Throwable $e) { dbg("table $t ERROR", $e->getMessage()); }
}

// 5. What does the model build?
try {
    $m = new ReflectionMethod('Selection', 'buildListFilters');
    $m->setAccessible(true);
    [$where, $types, $params] = $m->invoke(null, ['status' => $fStatus]);
    dbg('buildListFilters(status='.$fStatus.')', ['where' => $where, 'types' => $types, 'params' => $params]);
} catch (Throwable $e) { dbg('buildListFilters ERROR', $e->getMessage()); }

// 6. Count query WITHOUT role join vs WITH role join (isolates role-join cause)
$countBase = "(a.status IN ('interview_completed','selected','offer_sent','offer_accepted','offer_declined','on_hold','waitlisted') OR (a.status='rejected' AND EXISTS (SELECT 1 FROM interviews ie WHERE ie.application_id=a.id AND ie.status='completed')))";
foreach ([
    'no-role-join selected' => ["SELECT COUNT(*) c FROM applications a WHERE $countBase AND a.status='selected'", '', []],
    'with-role-join selected' => ["SELECT COUNT(*) c FROM applications a INNER JOIN users u ON u.id=a.candidate_id INNER JOIN roles ur ON ur.id=u.role_id AND ur.slug='candidate' WHERE $countBase AND a.status='selected'", '', []],
) as $label => [$sql, $ty, $pa]) {
    try {
        dbg($label, Database::fetchOne($sql, $ty, $pa));
    } catch (Throwable $e) { dbg($label.' ERROR', $e->getMessage()); }
}

// 7. Full model result
try {
    dbg('Selection::adminList', Selection::adminList(['status' => $fStatus], 1, 20));
} catch (Throwable $e) { dbg('adminList ERROR', $e->getMessage()); }

echo "DONE. Delete admin/selection_debug.php when finished.\n";
