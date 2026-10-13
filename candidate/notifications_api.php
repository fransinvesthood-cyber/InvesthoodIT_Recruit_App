<?php
/**
 * ================================================
 * INVESTHOOD IT - Candidate Notifications API
 * ================================================
 * AJAX endpoint for the candidate notification feed:
 *   unread_count | recent | list | mark_read | mark_all_read | delete
 *
 * Security rules
 *  - Candidate-only (require_role('candidate')).
 *  - POST-only + CSRF validated on every request.
 *  - Every query is scoped by the authenticated user id, so a
 *    candidate can never view, mark or delete another candidate's
 *    notification by changing an id.
 *  - Errors are returned as generic JSON messages; details are
 *    written to the server log only.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('candidate');

// JSON-safe error handling for this AJAX endpoint.
// PHP warnings/notices must never leak into the JSON body
// (they would break client-side res.json() parsing), and
// any unexpected exception is returned as JSON instead of
// the HTML page produced by the global exception handler.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

/**
 * Send a clean JSON response, discarding any accidental
 * output emitted by included libraries before the JSON.
 */
function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header_remove('Location'); // never allow a redirect to corrupt a JSON response
    header('Content-Type: application/json');
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode($payload);
    exit;
}

// Only accept POST requests (state-changing + read endpoints alike,
// so a GET request can never mutate or leak the feed).
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

// CSRF protection
if (!validate_csrf()) {
    json_response(['success' => false, 'message' => 'Invalid security token. Please refresh the page and try again.'], 403);
}

$candidateId = (int) current_user_id();
$action      = (string) ($_POST['action'] ?? '');

try {
    switch ($action) {
        case 'unread_count':
            json_response([
                'success' => true,
                'unread'  => CandidateNotification::unreadCount($candidateId),
            ]);
            break;

        case 'recent':
            // Latest notifications for the header dropdown.
            $limit   = min(20, max(1, (int) ($_POST['limit'] ?? 5)));
            $records = CandidateNotification::recentForCandidate($candidateId, $limit);
            json_response([
                'success'       => true,
                'unread'        => CandidateNotification::unreadCount($candidateId),
                'notifications' => array_map([CandidateNotification::class, 'payload'], $records),
            ]);
            break;

        case 'list':
            // Paginated feed for the dashboard notification centre.
            $limit    = min(20, max(1, (int) ($_POST['limit'] ?? 8)));
            $page     = max(1, (int) ($_POST['page'] ?? 1));
            $status   = (string) ($_POST['status'] ?? 'all');
            $category = (string) ($_POST['category'] ?? '');
            $result   = CandidateNotification::forCandidate(
                $candidateId,
                ['status' => $status, 'category' => $category],
                $page,
                $limit
            );
            json_response([
                'success'       => true,
                'unread'        => $result['unread'],
                'total'         => $result['total'],
                'page'          => $result['page'],
                'pages'         => $result['pages'],
                'notifications' => array_map([CandidateNotification::class, 'payload'], $result['records']),
            ]);
            break;

        case 'mark_read':
            $notificationId = (int) ($_POST['notification_id'] ?? 0);
            if ($notificationId <= 0) {
                json_response(['success' => false, 'message' => 'Invalid notification.'], 400);
            }
            $record = CandidateNotification::findForCandidate($notificationId, $candidateId);
            if (!$record) {
                json_response(['success' => false, 'message' => 'Notification not found.'], 404);
            }
            CandidateNotification::markRead($notificationId, $candidateId);
            json_response([
                'success' => true,
                'unread'  => CandidateNotification::unreadCount($candidateId),
            ]);
            break;

        case 'mark_all_read':
            $updated = CandidateNotification::markAllRead($candidateId);
            json_response([
                'success' => true,
                'updated' => $updated,
                'unread'  => CandidateNotification::unreadCount($candidateId),
            ]);
            break;

        case 'delete':
            $notificationId = (int) ($_POST['notification_id'] ?? 0);
            if ($notificationId <= 0) {
                json_response(['success' => false, 'message' => 'Invalid notification.'], 400);
            }
            $record = CandidateNotification::findForCandidate($notificationId, $candidateId);
            if (!$record) {
                json_response(['success' => false, 'message' => 'Notification not found.'], 404);
            }
            CandidateNotification::delete($notificationId, $candidateId);
            json_response([
                'success' => true,
                'unread'  => CandidateNotification::unreadCount($candidateId),
            ]);
            break;

        default:
            json_response(['success' => false, 'message' => 'Unknown action.'], 400);
    }
} catch (Throwable $e) {
    error_log('[Candidate Notifications API] Error (' . $action . '): ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'A system error occurred. Please try again later.'], 500);
}

