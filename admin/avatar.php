<?php
/**
 * ================================================
 * INVESTHOOD IT - Admin: Candidate Avatar Endpoint
 * ================================================
 * Serves a candidate's profile picture to
 * authenticated administrators for the Talent
 * Intelligence Hub "View Profile" pages.
 *
 * Role: Administrator only.
 *
 * Avatars live in private storage and are only
 * served through this authenticated endpoint.
 * Falls back to a generated avatar when the
 * candidate has not uploaded a picture.
 *
 * GET params:
 *   user_id  - the candidate user ID (required)
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('admin');

$candidateId = (int) ($_GET['user_id'] ?? 0);
if ($candidateId <= 0) {
    http_response_code(400);
    exit('Invalid candidate.');
}

$candidate = User::find($candidateId);
if (!$candidate) {
    http_response_code(404);
    exit('Candidate not found.');
}

if (strtolower((string) ($candidate['status'] ?? '')) === 'disabled') {
    http_response_code(404);
    exit('Candidate not found.');
}

$profile = CandidateProfile::findByUser($candidateId);
$stored  = $profile['profile_picture']
    ?? $candidate['profile_picture']
    ?? null;

if (!$stored) {
    // Fall back to a generated avatar rather than a broken image.
    $name = trim(($candidate['first_name'] ?? '') . ' ' . ($candidate['last_name'] ?? ''));
    if ($name === '') {
        $name = 'Candidate';
    }
    header('Location: https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=1a56db&color=fff&size=200');
    exit;
}

$path = FileUploader::path('avatars', (string) $stored);
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