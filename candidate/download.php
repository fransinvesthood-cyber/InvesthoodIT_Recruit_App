<?php
/**
 * ================================================
 * INVESTHOOD IT - Secure Document Download
 * ================================================
 * Streams a candidate's own document to the browser
 * (download or inline preview). The authenticated
 * user's ID is always verified server-side so a
 * candidate can never access another user's file.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();

$userId = (int) current_user_id();
$docId  = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$mode   = isset($_GET['mode']) ? $_GET['mode'] : 'download';

if ($docId <= 0) {
    http_response_code(400);
    exit('Invalid document.');
}

if ($mode === 'preview') {
    DocumentController::preview($userId, $docId);
}

DocumentController::download($userId, $docId);
