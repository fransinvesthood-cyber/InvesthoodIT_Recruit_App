<?php
/**
 * Investhood IT - Placements API (Stage 12)
 * Handles all placement-related API requests
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

session_start();
$currentUser = $_SESSION['user'] ?? null;

if (!$currentUser) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$roleSlug = $currentUser['role_slug'] ?? '';
$allowedRoles = ['admin', 'programme_manager', 'programme_officer'];
if (!in_array($roleSlug, $allowedRoles)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$currentUserId = (int)($currentUser['id'] ?? 0);

function sendResponse($success, $data = null, $message = '', $httpCode = 200) {
    http_response_code($httpCode);
    $response = ['success' => $success];
    if ($data !== null) $response['data'] = $data;
    if ($message) $response['message'] = $message;
    echo json_encode($response);
    exit;
}

function logPlacementAction($placementId, $fieldName, $previousValue, $newValue, $changedById, $reason = null) {
    return dbExecute(
        "INSERT INTO placement_status_history (placement_id, field_name, previous_value, new_value, changed_by, change_reason) VALUES (?, ?, ?, ?, ?, ?)",
        [$placementId, $fieldName, $previousValue, $newValue, $changedById, $reason],
        'iissss'
    );
}

function createPlacementNotification($placementId, $candidateId, $senderId, $type, $title, $message) {
    return dbExecute(
        "INSERT INTO placement_notifications (placement_id, candidate_id, sender_id, notification_type, title, message) VALUES (?, ?, ?, ?, ?, ?)",
        [$placementId, $candidateId, $senderId, $type, $title, $message],
        'iissss'
    );
}

// ============================================================
// ACTION: LIST
// ============================================================
if ($action === 'list') {
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';
    $programmeId = $_GET['programme_id'] ?? '';
    $cohortId = $_GET['cohort_id'] ?? '';
    $department = $_GET['department'] ?? '';
    $location = $_GET['location'] ?? '';
    $supervisorId = $_GET['supervisor_id'] ?? '';
    $startDateFrom = $_GET['start_date_from'] ?? '';
    $startDateTo = $_GET['start_date_to'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(100, max(10, (int)($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $perPage;

    $conditions = [];
    $params = [];
    $types = '';

    if ($search) {
        $s = "%$search%";
        $conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR p.placement_reference LIKE ? OR p.department LIKE ?)";
        foreach ([$s, $s, $s, $s, $s] as $v) { $params[] = $v; $types .= 's'; }
    }
    if ($status) { $conditions[] = "p.status = ?"; $params[] = $status; $types .= 's'; }
       $conditions[] = "p.department LIKE ?"; $params[] = "%$department%"; $types .= 's'; }
    if ($location) { $conditions[] = "p.location LIKE ?"; $params[] = "%$location%"; $types .= 's'; }
    if ($supervisorId) { $conditions[] = "p.supervisor_id = ?"; $params[] = $supervisorId; $types .= 'i'; }
    if ($startDateFrom) { $conditions[] = "p.start_date >= ?"; $params[] = $startDateFrom; $types .= 's'; }
    if ($startDateTo) { $conditions[] = "p.start_date <= ?"; $params[] = $startDateTo; $types .= 's'; }

    $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

    // Count
    $total = (int)dbFetchValue("SELECT COUNT(*) as total FROM placements p JOIN users u ON p.candidate_id = u.id $whereClause", $params, $types);

    // Fetch
    $params[] = $perPage; $types .= 'i';
    $params[] = $offset; $types .= 'i';
    $rows = dbQuery("SELECT p.*, u.first_name, u.last_name, u.email, u.phone,
        pr.name as programme_name, c.name as cohort_name,
        IFNULL(sup.first_name, '') as sup_first, IFNULL(sup.last_name, '') as sup_last, IFNULL(sup.email, '') as sup_email
        FROM placements p
        JOIN users u ON p.candidate_id = u.id
        LEFT JOIN programmes pr ON p.programme_id = pr.id
        LEFT JOIN cohorts c ON p.cohort_id = c.id
        LEFT JOIN users sup ON p.supervisor_id = sup.id
        $whereClause
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?", $params, $types);

    $placements = [];
    foreach ($rows as $r) {
        $placements[] = [
            'id' => $r['id'],
            'placement_reference' => $r['placement_reference'],
            'candidate_id' => $r['candidate_id'],
            'candidate_name' => $r['first_name'] . ' ' . $r['last_name'],
            'candidate_email' => $r['email'],
            'candidate_phone' => $r['phone'],
            'programme_id' => $r['programme_id'],
            'programme_name' => $r['programme_name'],
            'cohort_id' => $r['cohort_id'],
            'cohort_name' => $r['cohort_name'],
            'department' => $r['department'],
            'location' => $r['location'],
            'supervisor_id' => $r['supervisor_id'],
            'supervisor_name' => trim($r['sup_first'] . ' ' . $r['sup_last']),
            'supervisor_email' => $r['sup_email'],
            'start_date' => $r['start_date'],
// ============================================================
// ACTION: GET ELIGIBLE CANDIDATES
// ============================================================
if ($action === 'eligible-candidates') {
    $rows = dbQuery("
        SELECT DISTINCT u.id, u.first_name, u.last_name, u.email, u.phone,
               u.profile_picture, ap.application_reference, ap.status as app_status,
               o.id as offer_id, o.status as offer_status, o.amount as offer_amount,
               pr.name as programme_name, c.name as cohort_name
        FROM users u
        INNER JOIN applications ap ON ap.candidate_id = u.id
        INNER JOIN offers o ON o.application_id = ap.id
        LEFT JOIN programmes pr ON o.programme_id = pr.id
        LEFT JOIN cohorts c ON o.cohort_id = c.id
        WHERE ap.status = 'offer_accepted'
          AND o.status = 'accepted'
          AND u.role_id = 9
          AND u.is_active = 1
          AND u.id NOT IN (SELECT candidate_id FROM placements WHERE status NOT IN ('cancelled', 'withdrawn'))
        ORDER BY u.created_at DESC
    ");

    $candidates = [];
    foreach ($rows as $r) {
        $candidates[] = [
            'id' => $r['id'],
            'full_name' => $r['first_name'] . ' ' . $r['last_name'],
            'email' => $r['email'],
            'phone' => $r['phone'],
            'profile_picture' => $r['profile_picture'],
            'application_reference' => $r['application_reference'],
            'offer_id' => $r['offer_id'],
            'programme_name' => $r['programme_name'],
            'cohort_name' => $r['cohort_name']
        ];
    }
    sendResponse(true, ['candidates' => $candidates, 'count' => count($candidates)]);
}

// ============================================================
// ACTION: GET SUPERVISORS
// ============================================================
if ($action === 'supervisors') {
    $rows = dbQuery("
        SELECT u.id, u.first_name, u.last_name, u.email
        FROM users u
        WHERE u.is_active = 1 AND u.id != 9
        ORDER BY u.last_name, u.first_name
    ");
    $supervisors = [];
    foreach ($rows as $r) {
        $supervisors[] = [
            'id' => $r['id'],
            'full_name' => $r['first_name'] . ' ' . $r['last_name'],
            'email' => $r['email']
        ];
    }
    sendResponse(true, ['supervisors' => $supervisors]);
}

// ============================================================
// ACTION: GET DETAILS
// ============================================================
if ($action === 'details') {
    $placementId = (int)($_GET['id'] ?? 0);
    if (!$placementId) sendResponse(false, null, 'Placement ID required', 400);

    $row = dbFetchOne("
        SELECT p.*, u.first_name, u.last_name, u.email, u.phone, u.profile_picture,
               ap.application_reference, ap.status as app_status,
               o.id as offer_id, o.amount, o.status as offer_status, o.issued_at, o.expiry_date,
               pr.name as programme_name,
               c.name as cohort_name, c.start_date as cohort_start, c.end_date as cohort_end,
               sup.first_name as sup_first, sup.last_name as sup_last, sup.email as sup_email
        FROM placements p
        JOIN users u ON p.candidate_id = u.id
        JOIN applications ap ON p.application_id = ap.id
        JOIN offers o ON p.offer_id = o.id
        JOIN programmes pr ON p.programme_id = pr.id
        JOIN cohorts c ON p.cohort_id = c.id
        LEFT JOIN users sup ON p.supervisor_id = sup.id
        WHERE p.id = ?
    ", [$placementId], 'i');

    if (!$row) sendResponse(false, null, 'Placement not found', 404);

    $data = [
        'id' => $row['id'],
        'placement_reference' => $row['placement_reference'],
        'candidate' => [
            'id' => $row['candidate_id'],
            'full_name' => $row['first_name'] . ' ' . $row['last_name'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'profile_picture' => $row['profile_picture']
        ],
        'application' => ['id' => $row['application_id'], 'reference' => $row['application_reference'], 'status' => $row['app_status']],
        'offer' => ['id' => $row['offer_id'], 'amount' => $row['amount'], 'status' => $row['offer_status'], 'issued_at' => $row['issued_at'], 'expiry_date' => $row['expiry_date']],
        'programme' => ['id' => $row['programme_id'], 'name' => $row['programme_name']],
        'cohort' => ['id' => $row['cohort_id'], 'name' => $row['cohort_name'], 'start_date' => $row['cohort_start'], 'end_date' => $row['cohort_end']],
        'supervisor' => $row['supervisor_id'] ? ['id' => $row['supervisor_id'], 'full_name' => $row['sup_first'] . ' ' . $row['sup_last'], 'email' => $row['sup_email']] : null,
        'department' => $row['department'],
        'location' => $row['location'],
        'start_date' => $row['start_date'],
        'end_date' => $row['end_date'],
        'status' => $row['status'],
        'notes' => $row['notes'],
        'created_by' => $row['created_by'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at']
    ];

    $history = dbQuery("SELECT psh.*, u.first_name, u.last_name FROM placement_status_history psh LEFT JOIN users u ON psh.changed_by = u.id WHERE psh.placement_id = ? ORDER BY psh.created_at DESC", [$placementId], 'i');
    $data['history'] = [];
    foreach ($history as $h) {
        $data['history'][] = [
            'field_name' => $h['field_name'],
            'previous_value' => $h['previous_value'],
            'new_value' => $h['new_value'],
            'changed_by_name' => $h['first_name'] ? $h['first_name'] . ' ' . $h['last_name'] : 'System',
            'created_at' => $h['created_at']
        ];
    }

    sendResponse(true, $data);
}

            'end_date' => $r['end_date'],
            'status' => $r['status'],
            'notes' => $r['notes'],
            'created_by' => $r['created_by'],
            'created_at' => $r['created_at'],
            'updated_at' => $r['updated_at']
        ];
    }

    // Stats
    $stats = dbFetchOne("SELECT COUNT(*) as total,
        SUM(CASE WHEN status = 'pending_placement' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status IN ('placement_in_progress', 'active') THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status IN ('withdrawn', 'cancelled') THEN 1 ELSE 0 END) as withdrawn
        FROM placements") ?: ['total' => 0, 'pending' => 0, 'active' => 0, 'completed' => 0, 'withdrawn' => 0];

    sendResponse(true, [
        'placements' => $placements,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => ceil($total / $perPage),
        'stats' => $stats
    ]);
}

    if ($programmeId) { $conditions[] = "p.programme_id = ?"; $params[] = $programmeId; $types .= 'i'; }
    if ($cohortId) { $conditions[] = "p.cohort_id = ?"; $params[] = $cohortId; $types .= 'i'; }
    if ($department) { $c