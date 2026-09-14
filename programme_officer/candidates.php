<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('programme_officer');
require_once __DIR__ . '/_helpers.php';

$user = current_user();
$flashes = render_flashes();
$currentPage = 'candidates';
$pageTitle = 'Candidates';
$conn = Database::getConnection();

$programmeOfficerId = (int)($user['id'] ?? $user['user_id'] ?? 0);
if ($programmeOfficerId <= 0) {
    http_response_code(403);
    exit('Invalid Programme Officer account.');
}

$scope = po_scope($conn, 'p', 'c');
$scopeCondition = $scope['condition'] ?? '1 = 0';
$scopeMode = $scope['mode'] ?? 'none';

$mode = trim((string)($_GET['mode'] ?? 'list'));
$search = trim((string)($_GET['search'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$programmeId = (int)($_GET['programme_id'] ?? 0);
$cohortId = (int)($_GET['cohort_id'] ?? 0);

/*
 * HOTFIX HEADER ONLY:
 * Keep the rest of your existing candidates.php code below this point.
 * Use $scopeMode instead of directly accessing $scope['mode'].
 */
