<?php
/**
 * Investhood IT - Create Placement (Stage 12)
 */

require_once __DIR__ . '/../php/config/database.php';

session_start();
$user = $_SESSION['user'] ?? null;
if (!$user) { header('Location: /login.php'); exit; }

$roleSlug = $user['role_slug'] ?? '';
if (!in_array($roleSlug, ['admin', 'programme_manager', 'programme_officer'])) {
    header('Location: /unauthorized.php'); exit;
}

$currentUserId = (int)$user['id'];

// Fetch eligible candidates (offer_accepted)
$eligibleCandidates = dbQuery("
    SELECT DISTINCT u.id, u.first_name, u.last_name, u.email, u.phone, u.profile_picture,
           ap.application_reference, o.id as offer_id, o.amount,
           pr.name as programme_name, c.name as cohort_name
    FROM users u
    INNER JOIN applications ap ON ap.candidate_id = u.id
    INNER JOIN offers o ON o.application_id = ap.id
    LEFT JOIN programmes pr ON o.programme_id = pr.id
    LEFT JOIN cohorts c ON o.cohort_id = c.id
    WHERE ap.status = 'offer_accepted' AND o.status = 'accepted' AND u.role_id = 9 AND u.is_active = 1
    AND u.id NOT IN (SELECT candidate_id FROM placements WHERE status NOT IN ('cancelled', 'withdrawn'))
    ORDER BY u.created_at DESC
");

$programmes = dbQuery("SELECT id, name FROM programmes WHERE status = 'active' ORDER BY name");
$supervisors = dbQuery("SELECT id, first_name, last_name, email FROM users WHERE is_active = 1 AND id != 9 ORDER BY last_name, first_name");

$selectedCandidateId = (int)($_GET['candidate_id'] ?? 0);
$preSelectedCandidate = null;
if ($selectedCandidateId > 0) {
    foreach ($eligibleCandidates as $c) {
        if ($c['id'] === $selectedCandidateId) {
            $preSelectedCandidate = $c;
            break;
        }
    }
}

$errors = [];
$success = false;
$createdPlacement = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $candidateId = (int)($_POST['candidate_id'] ?? 0);
    $programmeId = (int)($_POST['programme_id'] ?? 0);
    $cohortId = (int)($_POST['cohort_id'] ?? 0);
    $department = trim($_POST['department'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $supervisorId = (int)($_POST['supervisor_id'] ?? 0);
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    $offerId = (int)($_POST['offer_id'] ?? 0);

    if (!$candidateId) $errors[] = 'Please select a candidate.';
    if (!$programmeId) $errors[] = 'Please select a programme.';
    if (!$cohortId) $errors[] = 'Please select a cohort.';
    if (!$startDate) $errors[] = 'Start date is required.';
    if (!$endDate) $errors[] = 'End date is required.';
    if ($startDate && $endDate && $endDate < $startDate) $errors[] = 'End date cannot be before start date.';
    if (!$offerId) $errors[] = 'Please select an offer.';

    if (empty($errors)) {
        $appCheck = dbFetchOne("SELECT ap.id FROM applications ap JOIN offers o ON o.application_id = ap.id WHERE ap.candidate_id = ? AND ap.status = 'offer_accepted' AND o.status = 'accepted'", [$candidateId], 'i');
        if (!$appCheck) {
            $errors[] = 'Candidate is no longer eligible.';
        } else {
            $existing = dbFetchOne("SELECT id FROM placements WHERE candidate_id = ? AND status NOT IN ('cancelled', 'withdrawn')", [$candidateId], 'i');
            if ($existing) {
                $errors[] = 'Candidate already has an active placement.';
            } else {
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

                if ($affected) {
                    $placementId = (int)dbFetchValue("SELECT LAST_INSERT_ID()");
                    $progName = dbFetchValue("SELECT name FROM programmes WHERE id = ?", [$programmeId], 'i');

                    dbExecute("INSERT INTO placement_status_history (placement_id, field_name, previous_value, new_value, changed_by, change_reason) VALUES (?, 'status', NULL, 'pending_placement', ?, 'Placement created')", [$placementId, $currentUserId], 'is');
                    dbExecute("INSERT INTO placement_notifications (placement_id, candidate_id, sender_id, notification_type, title, message) VALUES (?, ?, ?, 'placement_created', 'Placement Created', ?)", [$placementId, $candidateId, $currentUserId, "Your placement has been created. Programme: $progName. Start: $startDate. End: $endDate."], 'iisss');
                    dbExecute("INSERT INTO audit_logs (user_id, action, record_type, record_id, reason, ip_address) VALUES (?, 'Placement Created', 'placement', ?, 'Placement created', ?)", [$currentUserId, $placementId, $_SERVER['REMOTE_ADDR'] ?? '::1'], 'iiss');

                    $success = true;
                    $createdPlacement = ['id' => $placementId, 'reference' => $ref];
                    $_POST = [];
                } else {
                    $errors[] = 'Failed to create placement. Please try again.';
                }
            }
        }
    }
}
