<?php
/**
 * ================================================
 * INVESTHOOD IT - Application Status Update Handler
 * ================================================
 * AJAX endpoint for updating application status.
 * Validates admin role, CSRF token, and status transition.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// Set JSON response header
header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// Protect — only Administrator
require_role('admin');

// Validate CSRF token
if (!validate_csrf()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page and try again.']);
    exit;
}

// Get and validate input
$applicationId = (int) ($_POST['application_id'] ?? 0);
$newStatus = trim($_POST['new_status'] ?? '');
$reason = trim($_POST['reason'] ?? '');
$adminId = (int) current_user_id();

// Validate application ID
if ($applicationId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid application ID.']);
    exit;
}

// Validate status
if (empty($newStatus)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please select a status.']);
    exit;
}

// Verify application exists
$app = Application::find($applicationId);
if (!$app) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Application not found.']);
    exit;
}

// Attempt status update
try {
    $result = Application::updateStatus($applicationId, $newStatus, $adminId, $reason);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Status updated successfully from "' . Application::label($app['status']) . '" to "' . Application::label($newStatus) . '".',
            'new_status' => $newStatus,
            'new_status_label' => Application::label($newStatus),
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Status has not changed.',
        ]);
    }
} catch (RuntimeException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Exception $e) {
    error_log('[Admin Application Status] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred while updating the status. Please try again.']);
}