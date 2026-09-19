<?php
/**
 * Investhood IT - Placements Actions API (Stage 12)
 */

require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

session_start();
$currentUser = $_SESSION['user'] ?? null;
if (!$currentUser) { http_response_code(401); echo json_encode(['success' => false, 'message' => 'Auth required']); exit; }

$roleSlug = $currentUser['role_slug'] ?? '';
if (!in_array($roleSlug, ['admin', 'programme_manager', 'programme_officer'])) {
    http_response_code(403); echo json_encode(['success' => false, 'message' => 'Access denied']); exit;
}

$currentUserId = (int)($currentUser['id'] ?? 0);
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

function sendResponse($success, $data = null, $message = '', $httpCode = 200) {
    http_response_code($httpCode);
    $response = ['success' => $success];
    if ($data !== null) $response['data'] = $data;
    if ($message) $response['message'] = $message;
    echo json_encode($response); exit;
}

function logAction($pid, $field, $prev, $new, $by, $reason = null) {
    dbExecute("INSERT INTO placement_status_history (placement_id, field_name, previous_value, new_value, changed_by, change_reason) VALUES (?, ?, ?, ?, ?, ?)",
        [$pid, $field, $prev, $new, $by, $reason], 'iissss');
}

function createNotif($pid, $cid, $sid, $type, $title, $msg) {
    dbExecute("INSERT INTO placement_notifications (placement_id, candidate_id, sender_id, notification_type, title, message) VALUES (?, ?, ?, ?, ?, ?)",
        [$pid, $cid, $sid, $type, $title, $msg], 'iissss');
}

// ============================================================
// CREATE PLACEMENT
// ============================================================
if ($action === 'create') {
    if ($method !== 'POST') sendResponse(false, null, 'POST required', 405);

    $candidateId = (int)($input['candidate_id'] ?? 0);
    $programmeId = (int)($input['programme_id'] ?? 0);
    $cohortId = (int)($input['cohort_id'] ?? 0);
    $department = trim($input['department'] ?? '');
    $location = trim($input['location'] ?? '');
    $supervisorId = (int)($input['supervisor_id'] ?? 0);
    $startDate = $input['start_date'] ?? '';
    $endDate = $input['end_date'] ?? '';
    $notes = trim($input['notes'] ?? '');
    $offerId = (int)($input['offer_id'] ?? 0);

    $errors = [];
    if (!$candidateId) $errors[] = 'Candidate required';
    if (!$programmeId) $errors[] = 'Programme required';
    if (!$cohortId) $errors[] = 'Cohort required';
    if (!$startDate) $errors[] = 'Start date required';
    if (!$endDate) $errors[] = 'End date required';
    if ($startDate && $endDate && $endDate < $startDate) $errors[] = 'End date before start date';

    if (!empty($errors)) sendResponse(false, null, implode('; ', $errors), 400);

    $appCheck = dbFetchOne("SELECT ap.id FROM applications ap JOIN offers o ON o.application_id = ap.id WHERE ap.candidate_id = ? AND ap.status = 'offer_accepted' AND o.status = 'accepted'", [$candidateId], 'i');
    if (!$appCheck) sendResponse(false, null, 'Candidate not eligible (no accepted offer found)', 400);

    $existing = dbFetchOne("SELECT id FROM placements WHERE candidate_id = ? AND status NOT IN ('cancelled', 'withdrawn')", [$candidateId], 'i');
    if ($existing) sendResponse(false, null, 'Candidate already has an active placement', 400);

    $ref = '';
    do {
        $ref = 'PLAC-' . date('Y') . '-' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        $cnt = (int)dbFetchValue("SELECT COUNT(*) FROM placements WHERE placement_reference = ?", [$ref], 's');
    } while ($cnt > 0);

    $affected = dbExecute(
        "INSERT INTO placements (placement_reference, candidate_id, application_id, offer_id, programme_id, cohort_id, department, location, supervisor_id, start_date, end_date, status, notes, created_by)
         SELECT ?, ?, ap.id, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_placement', ?, ?
         FROM applications ap JOIN offers o ON o.application_id = ap.id
         WHERE ap.candidate_id = ? AND o.id = ? AND ap.status = 'offer_accepted' AND o.status = 'accepted'",
        [$ref, $candidateId, $offerId, $programmeId, $cohortId, $department, $location, $supervisorId, $startDate, $endDate, $notes, $currentUserId, $candidateId, $offerId],
        'sssssssssssiss'
    );

    if (!$affected) sendResponse(false, null, 'Failed to create placement', 500);

    $placementId = (int)dbFetchValue("SELECT LAST_INSERT_ID()");
    $progName = dbFetchValue("SELECT name FROM programmes WHERE id = ?", [$programmeId], 'i');

    logAction($placementId, 'status', null, 'pending_placement', $currentUserId, 'Placement created');

    createNotif($placementId, $candidateId, $currentUserId, 'placement_created',
        'Placement Created',
        "Your placement has been created. Programme: $progName. Start: $startDate. End: $endDate.");

    dbExecute("INSERT INTO audit_logs (user_id, action, record_type, record_id, reason, ip_address) VALUES (?, 'Placement Created', 'placement', ?, 'Placement created', ?)",
        [$currentUserId, $placementId, $_SERVER['REMOTE_ADDR'] ?? '::1'], 'iiss');

    sendResponse(true, ['id' => $placementId, 'placement_reference' => $ref], 'Placement created successfully', 201);
}
