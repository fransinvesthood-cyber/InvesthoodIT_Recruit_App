<?php
/**
 * ================================================
 * INVESTHOOD IT - Candidate Application Actions
 * ================================================
 * AJAX endpoint for saving application form responses
 * and managing draft-application documents.
 *
 * All operations verify ownership and draft status server-side.
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

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

// CSRF protection
if (!validate_csrf()) {
    json_response(['success' => false, 'message' => 'Invalid security token. Please refresh the page and try again.'], 403);
}

$candidateId = (int) current_user_id();
$action = $_POST['action'] ?? '';
$applicationId = (int) ($_POST['application_id'] ?? 0);

header('Content-Type: application/json');

try {
switch ($action) {
    case 'save_step':
        // Collect responses from POST
        $responses = [];
        if (!empty($_POST['responses']) && is_array($_POST['responses'])) {
            foreach ($_POST['responses'] as $questionId => $response) {
                $questionId = (int) $questionId;
                if ($questionId <= 0) {
                    continue;
                }

                // Handle checkbox arrays
                if (is_array($response)) {
                    $response = implode(',', array_map('trim', $response));
                }

                $responses[$questionId] = (string) $response;
            }
        }

        $result = ApplicationFormController::saveResponses($candidateId, $applicationId, $responses);

        echo json_encode($result);
        break;

    case 'save_review_confirmation':
        // Stage 5: persist the candidate's temporary declaration/consent to
        // the session only. The permanent consent record is created when the
        // application is successfully submitted in Stage 6.
        $declaration = !empty($_POST['declaration']) && $_POST['declaration'] === '1';
        $consentPurposes = [];

        if (!empty($_POST['consent_purposes']) && is_array($_POST['consent_purposes'])) {
            foreach ($_POST['consent_purposes'] as $purpose) {
                $purpose = trim((string) $purpose);
                if ($purpose !== '') {
                    $consentPurposes[] = $purpose;
                }
            }
        }

        $result = ApplicationFormController::saveReviewConfirmation(
            $candidateId,
            $applicationId,
            $declaration,
            $consentPurposes
        );

        echo json_encode($result);
        break;

    case 'validate_submission_readiness':
        // Stage 5: server-side validation performed before the candidate
        // proceeds to the Stage 6 submission entry point. Does NOT submit.
        $result = ApplicationFormController::validateSubmissionReadiness($candidateId, $applicationId);
        echo json_encode($result);
        break;

    case 'document_upload':
        // Upload a new document (or replace if one already exists for that type)
        $documentType = trim((string) ($_POST['document_type'] ?? ''));
        $errors = [];

        if ($documentType === '') {
            echo json_encode(['success' => false, 'message' => 'Please select a file.']);
            break;
        }

        $id = ApplicationFormController::uploadDocument(
            $candidateId,
            $applicationId,
            $documentType,
            $_FILES['document'] ?? [],
            $errors
        );

        if ($id) {
            echo json_encode([
                'success' => true,
                'message' => 'Your document was uploaded successfully.',
                'id'      => $id,
            ]);
        } else {
            $message = $errors['document'] ?? $errors['document_type'] ?? $errors['general'] ?? 'Unable to upload the document.';
            echo json_encode(['success' => false, 'message' => $message, 'errors' => $errors]);
        }
        break;

    case 'document_replace':
        // Replace an existing application document with a new file
        $appDocId = (int) ($_POST['document_id'] ?? 0);
        $errors = [];

        if ($appDocId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Please select a file.']);
            break;
        }

        if (ApplicationFormController::replaceDocument($candidateId, $applicationId, $appDocId, $_FILES['document'] ?? [], $errors)) {
            echo json_encode(['success' => true, 'message' => 'Your document was replaced successfully.']);
        } else {
            $message = $errors['document'] ?? $errors['document_type'] ?? $errors['general'] ?? 'We couldn\'t upload your document. Please try again.';
            echo json_encode(['success' => false, 'message' => $message, 'errors' => $errors]);
        }
        break;

    case 'document_remove':
        // Remove a document from the application (confirmation handled client-side)
        $appDocId = (int) ($_POST['document_id'] ?? 0);

        if ($appDocId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid document.']);
            break;
        }

        if (ApplicationFormController::removeDocument($candidateId, $applicationId, $appDocId)) {
            echo json_encode(['success' => true, 'message' => 'Document removed.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Could not remove the document.']);
        }
        break;

    case 'document_reuse':
        // Reuse an existing candidate profile document for this application
        $profileDocId = (int) ($_POST['candidate_document_id'] ?? 0);

        if ($profileDocId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid document.']);
            break;
        }

        $result = ApplicationFormController::reuseCandidateDocument($candidateId, $applicationId, $profileDocId);
        echo json_encode($result);
        break;

    default:
        json_response(['success' => false, 'message' => 'Invalid action.'], 400);
}
} catch (Throwable $ex) {
    error_log('[AJAX] ' . $ex->getMessage() . ' @ ' . $ex->getFile() . ':' . $ex->getLine());

    $message = APP_ENV === 'development'
        ? 'System error: ' . $ex->getMessage()
        : 'A system error occurred. Please try again later.';

    json_response(['success' => false, 'message' => $message], 500);
}
