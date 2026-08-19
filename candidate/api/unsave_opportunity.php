<?php
/**
 * ================================================
 * INVESTHOOD IT - Unsave Opportunity API Endpoint
 * ================================================
 * AJAX endpoint for candidates to remove saved opportunities.
 * POST /candidate/api/unsave_opportunity.php
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

require_role('candidate');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Check CSRF token
if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || $_SERVER['HTTP_X_CSRF_TOKEN'] !== csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF token validation failed']);
    exit;
}

$userId = (int) current_user_id();

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$oppId = (int) ($input['opportunity_id'] ?? 0);

if (!$oppId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid opportunity ID']);
    exit;
}

// Check if saved by this user
if (!SavedOpportunity::isSaved($userId, $oppId)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Opportunity not found in saved list']);
    exit;
}

// Remove from saved
$result = SavedOpportunity::unsave($userId, $oppId);

if ($result) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Opportunity removed from saved',
        'saved' => false,
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to unsave opportunity']);
}

exit;
