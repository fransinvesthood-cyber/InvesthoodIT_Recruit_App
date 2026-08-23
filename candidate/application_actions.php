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

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// CSRF protection
if (!validate_csrf()) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page and try again.']);
    exit;
}

$candidateId = (int) current_user_id();
$action = $_POST['action'] ?? '';
$applicationId = (int) ($_POST['application_id'] ?? 0);

header('Content-Type: application/json');

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
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        break;
}