<?php
/**
 * ================================================
 * INVESTHOOD IT - Save Opportunity API Endpoint
 * ================================================
 * AJAX endpoint for candidates to save opportunities.
 * POST /candidate/api/save_opportunity.php
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

// Verify opportunity exists and is published
$opportunity = Opportunity::find($oppId);
if (!$opportunity || $opportunity['status'] !== 'published') {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Opportunity not found or not available']);
    exit;
}

// Check if already saved
if (SavedOpportunity::isSaved($userId, $oppId)) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Opportunity already saved']);
    exit;
}

// Save the opportunity
$result = SavedOpportunity::save($userId, $oppId);

if ($result) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Opportunity saved',
        'saved' => true,
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save opportunity']);
}

exit;
