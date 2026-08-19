<?php
/**
 * ================================================
 * INVESTHOOD IT - Secure Profile Picture Endpoint
 * ================================================
 * Serves the authenticated candidate's own profile
 * picture. Files are stored in private storage and
 * served only through this authenticated endpoint.
 * Ownership is enforced server-side (only the logged
 * in user may request their own avatar image).
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();

$userId = (int) current_user_id();

// The requested user must be the authenticated user.
$targetId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : $userId;
if ($targetId !== $userId) {
    http_response_code(403);
    exit('Access denied.');
}

$profile = CandidateProfile::findByUser($userId);
$stored  = $profile['profile_picture'] ?? null;

if (!$stored) {
    // Fall back to a generated avatar rather than a broken image.
    $name = current_user()['fullname'] ?? 'Candidate';
    header('Location: https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=1a56db&color=fff&size=200');
    exit;
}

$path = FileUploader::path('avatars', $stored);
if (!$path) {
    http_response_code(404);
    exit('Image not found.');
}

// Determine a safe content type from the filesystem extension.
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mimes = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
];
$mime = $mimes[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Length: ' . (int) filesize($path));
header('Cache-Control: private, max-age=86400');
header('X-Content-Type-Options: nosniff');

readfile($path);
exit;
