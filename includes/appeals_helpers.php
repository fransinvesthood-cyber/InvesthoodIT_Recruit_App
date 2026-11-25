<?php
declare(strict_types=1);

/* -----------------------------------------------------------------
 * Load the shared helper that defines as_has_table(), as_label(),
 * as_datetime(), as_initials(), as_class(), etc.
 *
 * as_has_table() normally lives in assessor/_helpers.php (or a shared
 * lib). We probe the common locations and include the first file that
 * exists, so this helper works from any entry point (assessor/,
 * candidate/, admin/) without the caller having to remember.
 *
 * If your project keeps as_has_table() somewhere else, add that path
 * to the array below. Run this once from the project root to confirm:
 *
 *     grep -rn "function as_has_table" --include="*.php" .
 * ----------------------------------------------------------------- */
if (!function_exists('as_has_table')) {
    foreach ([
        __DIR__ . '/_helpers.php',                  // includes/_helpers.php
        __DIR__ . '/../assessor/_helpers.php',      // assessor/_helpers.php   ← most likely
        __DIR__ . '/../includes/_helpers.php',      // redundant but harmless
        __DIR__ . '/../admin/_helpers.php',         // admin/_helpers.php
        __DIR__ . '/../lib/helpers.php',            // lib/helpers.php
    ] as $__appealsHelperFile) {
        if (is_file($__appealsHelperFile)) {
            require_once $__appealsHelperFile;
            break;
        }
    }
    unset($__appealsHelperFile);
}

if (!function_exists('as_has_table')) {
    throw new RuntimeException(
        'as_has_table() is not defined. Locate the file that defines it ' .
        '(grep -rn "function as_has_table" .) and add its path to the ' .
        'loop at the top of includes/appeals_helpers.php.'
    );
}

/* =================================================================
 * Appeals module constants
 * ============================================================== */
const APPEAL_WINDOW_DAYS       = 20;                      // candidate may appeal within N days of finalisation
const APPEAL_REF_PREFIX        = 'APL';
const APPEAL_UPLOAD_DIR        = __DIR__ . '/../uploads/appeals';
const APPEAL_MAX_UPLOAD_BYTES  = 10 * 1024 * 1024;        // 10 MB

/* =================================================================
 * Presentation helpers
 * ============================================================== */
function appeal_status_label(string $s): string {
    return [
        'submitted'              => 'Submitted',
        'under_review'           => 'Under review',
        'info_requested'         => 'Info requested',
        'reassessment_scheduled' => 'Reassessment scheduled',
        'upheld'                 => 'Upheld',
        'overturned'             => 'Overturned',
        'withdrawn'              => 'Withdrawn',
        'closed'                 => 'Closed',
    ][$s] ?? ucfirst(str_replace('_', ' ', $s));
}

function appeal_status_class(string $s): string {
    return [
        'submitted'              => 'info',
        'under_review'           => 'warning',
        'info_requested'         => 'warning',
        'reassessment_scheduled' => 'info',
        'upheld'                 => 'success',
        'overturned'             => 'success',
        'withdrawn'              => 'muted',
        'closed'                 => 'muted',
    ][$s] ?? 'muted';
}

function appeal_outcome_label(string $o): string {
    return [
        'reassessment' => 'Arrange a reassessment',
        'remark'       => 'Re-mark by a different assessor',
        'overturn'     => 'Overturn the original result',
        'feedback'     => 'Request further feedback only',
    ][$o] ?? $o;
}

function appeal_is_terminal(string $s): bool {
    return in_array($s, ['upheld', 'overturned', 'withdrawn', 'closed'], true);
}

/* =================================================================
 * Reference generation
 * ============================================================== */
function appeal_next_reference(mysqli $conn): string {
    do {
        $ref = sprintf('%s-%s-%04d', APPEAL_REF_PREFIX, date('Ymd'), random_int(0, 9999));
        $s = $conn->prepare('SELECT 1 FROM assessment_appeals WHERE reference = ? LIMIT 1');
        $s->bind_param('s', $ref);
        $s->execute();
        $exists = (bool) $s->get_result()->fetch_row();
        $s->close();
    } while ($exists);
    return $ref;
}

/* =================================================================
 * Eligibility check for a new appeal
 * Returns [bool $allowed, string $reason]
 * ============================================================== */
function appeal_can_lodge(mysqli $conn, int $attemptId, int $participantId): array {
    if (!as_has_table($conn, 'assessment_appeals')) {
        return [false, 'Appeals module is not yet installed.'];
    }

    $s = $conn->prepare(
        "SELECT aa.status, aa.final_approved_at, aa.moderated_at, aa.assessed_at,
                aa.assessor_result, aa.participant_id
           FROM assessment_attempts aa WHERE aa.id = ? LIMIT 1"
    );
    if (!$s) return [false, 'Unable to verify attempt.'];
    $s->bind_param('i', $attemptId);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();

    if (!$row)                                    return [false, 'Assessment attempt not found.'];
    if ((int)$row['participant_id'] !== $participantId)
        return [false, 'You do not own this assessment attempt.'];

    $finalisedAt = $row['final_approved_at'] ?: ($row['moderated_at'] ?: $row['assessed_at']);
    if (!$finalisedAt)
        return [false, 'This assessment has not been finalised yet.'];

    $deadline = strtotime($finalisedAt) + (APPEAL_WINDOW_DAYS * 86400);
    if (time() > $deadline)
        return [false, 'The appeal window of ' . APPEAL_WINDOW_DAYS . ' days has closed.'];

    // Block if an active appeal already exists for this attempt.
    $s = $conn->prepare(
        "SELECT id FROM assessment_appeals
          WHERE attempt_id = ?
            AND status NOT IN ('upheld','overturned','withdrawn','closed')
          LIMIT 1"
    );
    $s->bind_param('i', $attemptId);
    $s->execute();
    if ($s->get_result()->fetch_row()) {
        $s->close();
        return [false, 'An appeal against this attempt is already in progress.'];
    }
    $s->close();

    return [true, ''];
}

/* =================================================================
 * Audit logging
 * ============================================================== */
function appeal_log(mysqli $conn, int $appealId, ?int $actorId, ?string $role,
                    string $action, string $details = ''): void {
    if (!as_has_table($conn, 'appeal_activity')) return;
    $s = $conn->prepare(
        "INSERT INTO appeal_activity (appeal_id, actor_id, actor_role, action, details)
         VALUES (?, ?, ?, ?, ?)"
    );
    if (!$s) return;
    $s->bind_param('iisss', $appealId, $actorId, $role, $action, $details);
    $s->execute();
    $s->close();
}

/* =================================================================
 * Safe file upload handler
 * Returns ['file_name'=>..., 'stored_path'=>..., 'mime_type'=>..., 'file_size'=>...]
 * or null on failure.
 * ============================================================== */
function appeal_store_upload(array $file, int $appealId, int $uploaderId, string $role): ?array {
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > APPEAL_MAX_UPLOAD_BYTES) return null;

    $allowed = [
        'application/pdf',
        'image/jpeg', 'image/png', 'image/webp',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];
    $mime = mime_content_type($file['tmp_name']) ?: '';
    if (!in_array($mime, $allowed, true)) return null;

    $dir = APPEAL_UPLOAD_DIR . '/' . $appealId;
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) return null;

    $ext  = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'bin';
    $safe = bin2hex(random_bytes(12)) . '.' . preg_replace('/[^a-z0-9]/i', '', $ext);
    $dest = $dir . '/' . $safe;

    if (!move_uploaded_file($file['tmp_name'], $dest)) return null;

    return [
        'file_name'   => basename($file['name']),
        'stored_path' => 'uploads/appeals/' . $appealId . '/' . $safe,
        'mime_type'   => $mime,
        'file_size'   => (int)$file['size'],
    ];
}