<?php
/**
 * ================================================
 * INVESTHOOD IT - Application Form Controller
 * ================================================
 * Business logic for the multi-step application form.
 * Handles loading form data, validating ownership,
 * saving responses, and profile readiness checks.
 *
 * Stage 4: Secure per-application document management.
 * Documents are tied to the application via
 * application_documents and stored securely outside
 * the web root (uploads_private/documents/).
 */

class ApplicationFormController
{
    /**
     * Load all data needed to render the application form.
     *
     * Performs server-side ownership and status checks:
     *  - Authenticated candidate
     *  - Application belongs to candidate
     *  - Application belongs to requested opportunity
     *  - Application is still Draft
     *
     * @param int $candidateId
     * @param int $applicationId
     * @return array|null  Form data or null if not found/not owned
     */
    public static function loadForm(int $candidateId, int $applicationId): ?array
    {
        // Ownership enforced in the query itself
        $application = Application::findForCandidate($candidateId, $applicationId);

        if (!$application) {
            return null;
        }

        // Only Draft applications can be edited through this form
        if ($application['status'] !== 'draft') {
            return [
                'application' => $application,
                'error'       => 'This application has already been submitted and can no longer be edited.',
                'readonly'    => true,
            ];
        }

        $opportunityId = (int) $application['opportunity_id'];

        // Verify opportunity still exists
        $opportunity = Opportunity::find($opportunityId);
        if (!$opportunity) {
            return [
                'application' => $application,
                'error'       => 'The opportunity for this application could not be found.',
                'readonly'    => true,
            ];
        }

        // Check if applications are still open
        $today = date('Y-m-d');
        $closed = false;

        if (!empty($opportunity['application_close_date']) && $opportunity['application_close_date'] < $today) {
            $closed = true;
        }

        if (($opportunity['status'] ?? '') === 'closed' || ($opportunity['status'] ?? '') === 'archived') {
            $closed = true;
        }

        if ($closed) {
            return [
                'application' => $application,
                'opportunity' => $opportunity,
                'error'       => 'Applications for this opportunity are now closed.',
                'readonly'    => true,
            ];
        }

        // Load candidate profile data
        $user = User::find($candidateId);
        $profile = CandidateProfile::findByUser($candidateId);

        // Load qualifications, skills, work experience
        $qualifications = Qualification::forUser($candidateId);
        $skills = Skill::forUser($candidateId);
        $experiences = WorkExperience::forUser($candidateId);

        // Load questions for this opportunity
        $questions = ApplicationQuestion::forOpportunity($opportunityId);

        // Load existing responses
        $responses = ApplicationResponse::forApplication($applicationId);

        // Load eligibility requirements from programme/cohort/opportunity
        $eligibility = self::loadEligibilityRequirements($opportunity);

        // Load document configuration for this opportunity + existing uploads
        $documentData = self::loadDocumentData($candidateId, $applicationId, $opportunityId);

        return [
            'application'    => $application,
            'opportunity'    => $opportunity,
            'user'           => $user,
            'profile'        => $profile,
            'qualifications' => $qualifications,
            'skills'         => $skills,
            'experiences'    => $experiences,
            'questions'      => $questions,
            'responses'      => $responses,
            'eligibility'    => $eligibility,
            'documents'      => $documentData,
            'readonly'       => false,
            'error'          => null,
        ];
    }

    /**
     * Load the document configuration for an opportunity together with:
     *  - documents already uploaded against this application
     *  - the candidate's reusable profile documents (CV, qualifications, etc.)
     *
     * @param int $candidateId
     * @param int $applicationId
     * @param int $opportunityId
     * @return array
     */
    public static function loadDocumentData(int $candidateId, int $applicationId, int $opportunityId): array
    {
        // Configured requirements for the opportunity (document_name + is_required)
        $requirements = OpportunityDocument::forOpportunity($opportunityId);

        // Already-uploaded documents for this application
        $appDocuments = ApplicationDocument::forApplication($applicationId);

        // Candidate's own profile documents (for re-use)
        $profileDocuments = Document::forUser($candidateId);

        // Build a lookup of application documents keyed by document_type
        $appDocByType = [];
        foreach ($appDocuments as $doc) {
            $appDocByType[$doc['document_type']] = $doc;
        }

        // Build a lookup of reusable candidate documents keyed by the
        // same normalised document type used for application documents.
        $reusableByType = [];
        foreach ($profileDocuments as $profileDoc) {
            $type = self::normaliseDocumentType((string) $profileDoc['document_type']);
            if (!isset($reusableByType[$type])) {
                $reusableByType[$type] = $profileDoc;
            }
        }

        // Merge into a single array of document items
        $items = [];
        foreach ($requirements as $req) {
            $docType = self::normaliseDocumentType((string) $req['document_name']);
            $appDoc = $appDocByType[$docType] ?? null;
            $candidateDoc = $reusableByType[$docType] ?? null;

            $items[] = [
                'document_name'     => (string) $req['document_name'],
                'document_type'     => $docType,
                'is_required'       => (bool) $req['is_required'],
                'application_doc'   => $appDoc,
                'candidate_doc'     => $candidateDoc,
                'has_upload'        => $appDoc !== null,
                'can_reuse'         => $candidateDoc !== null && $appDoc === null,
            ];
        }

        return [
            'requirements'      => $requirements,
            'application_docs'  => $appDocuments,
            'profile_docs'      => $profileDocuments,
            'items'             => $items,
        ];
    }

    /**
     * Validate that all required documents for an application's opportunity
     * have been uploaded or reused.
     *
     * @param int $candidateId
     * @param int $applicationId
     * @return array ['valid' => bool, 'missing' => string[]]
     */
    public static function validateRequiredDocuments(int $candidateId, int $applicationId): array
    {
        $application = Application::findForCandidate($candidateId, $applicationId);
        if (!$application) {
            return ['valid' => false, 'missing' => ['Application not found.']];
        }

        $oppId = (int) $application['opportunity_id'];
        $data = self::loadDocumentData($candidateId, $applicationId, $oppId);

        $missing = [];
        foreach ($data['items'] as $item) {
            if ($item['is_required'] && !$item['has_upload']) {
                $missing[] = $item['document_name'];
            }
        }

        return [
            'valid'   => empty($missing),
            'missing' => $missing,
        ];
    }

    /**
     * Upload a new document against the application.
     *
     * Verifies:
     *  - Authenticated candidate (checked by caller)
     *  - Application exists & belongs to candidate
     *  - Application is Draft
     *  - Opportunity is open
     *  - Document type is allowed for the opportunity
     *  - Upload is valid via FileUploader (MIME, size, secure name)
     *
     * If the document type already has a record, it is replaced
     * (old file deleted only after the new upload succeeds).
     *
     * @param int    $candidateId
     * @param int    $applicationId
     * @param string $documentType  e.g. CV, QUALIFICATION, TRANSCRIPT, ID, COVER_LETTER, SUPPORTING_DOCUMENT
     * @param array  $file          $_FILES['document']
     * @param array  $errors        (by reference)
     * @return int|null
     */
    public static function uploadDocument(int $candidateId, int $applicationId, string $documentType, array $file, array &$errors): ?int
    {
        $application = Application::findForCandidate($candidateId, $applicationId);
        if (!$application) {
            $errors['general'] = 'Application not found.';
            return null;
        }

        if ($application['status'] !== 'draft') {
            $errors['general'] = 'This application can no longer be edited.';
            return null;
        }

        $oppId = (int) $application['opportunity_id'];
        $data = self::loadDocumentData($candidateId, $applicationId, $oppId);

        // Verify the document type is allowed for this opportunity
        $allowedTypes = array_map(
            fn($req) => self::normaliseDocumentType((string) $req['document_name']),
            $data['requirements']
        );
        if (!in_array(strtoupper($documentType), $allowedTypes, true)) {
            $errors['document_type'] = 'This document type is not required for this opportunity.';
            return null;
        }

        // Secure upload via existing FileUploader helper
        $result = FileUploader::uploadDocument($file);
        if (!$result['success']) {
            $errors['document'] = self::friendlyUploadMessage($result['message'] ?? 'Unable to upload the document.');
            return null;
        }

        $storedFilename = $result['stored_filename'];
        $originalName   = $result['original_filename'];
        $mime           = $result['mime'];
        $size           = (int) $result['size'];
        $checksum       = $result['checksum'];

        // Check whether we are replacing an existing record
        $existing = ApplicationDocument::findForApplication($applicationId, $documentType);

        if ($existing) {
            // Update metadata first; keep the old file until we are sure the new one is stored
            $updated = ApplicationDocument::update((int) $existing['id'], $applicationId, [
                'original_filename' => $originalName,
                'stored_filename'   => $storedFilename,
                'mime_type'         => $mime,
                'file_size'         => $size,
                'file_checksum'     => $checksum,
            ]);

            if (!$updated) {
                // Roll back the new physical file if metadata update fails
                FileUploader::delete('documents', $storedFilename);
                $errors['general'] = 'Unable to save the document. Please try again.';
                return null;
            }

            // Delete the old physical file only after the new record is saved
            FileUploader::delete('documents', $existing['stored_filename']);

            return (int) $existing['id'];
        }

        // New record
        $id = ApplicationDocument::create($applicationId, [
            'document_type'     => $documentType,
            'original_filename' => $originalName,
            'stored_filename'   => $storedFilename,
            'mime_type'         => $mime,
            'file_size'         => $size,
            'file_checksum'     => $checksum,
        ]);

        if (!$id) {
            FileUploader::delete('documents', $storedFilename);
            $errors['general'] = 'Unable to save the document. Please try again.';
            return null;
        }

        // Touch updated_at
        Database::execute(
            "UPDATE applications SET updated_at = NOW() WHERE id = ? AND candidate_id = ?",
            'ii',
            [$applicationId, $candidateId]
        );

        return $id;
    }

    /**
     * Replace an existing application document with a new upload.
     *
     * The existing document is only removed after the new upload
     * has been validated and persisted.
     *
     * @param int   $candidateId
     * @param int   $applicationId
     * @param int   $appDocId
     * @param array $file         $_FILES['document']
     * @param array $errors       (by reference)
     * @return bool
     */
    public static function replaceDocument(int $candidateId, int $applicationId, int $appDocId, array $file, array &$errors): bool
    {
        // Verify the application belongs to the candidate and is draft
        $application = Application::findForCandidate($candidateId, $applicationId);
        if (!$application) {
            $errors['general'] = 'Application not found.';
            return false;
        }
        if ($application['status'] !== 'draft') {
            $errors['general'] = 'This application can no longer be edited.';
            return false;
        }

        // Verify the document record belongs to this application
        $appDoc = ApplicationDocument::findOwned($appDocId, $applicationId);
        if (!$appDoc) {
            $errors['general'] = 'Document not found.';
            return false;
        }

        // Re-verify the type is allowed
        $oppId = (int) $application['opportunity_id'];
        $data = self::loadDocumentData($candidateId, $applicationId, $oppId);
        $allowedTypes = array_map(
            fn($req) => self::normaliseDocumentType((string) $req['document_name']),
            $data['requirements']
        );
        if (!in_array(strtoupper((string) $appDoc['document_type']), $allowedTypes, true)) {
            $errors['document_type'] = 'This document type is not required for this opportunity.';
            return false;
        }

        // Secure upload the new file first
        $result = FileUploader::uploadDocument($file);
        if (!$result['success']) {
            $errors['document'] = self::friendlyUploadMessage($result['message'] ?? 'Unable to upload the document.');
            return false;
        }

        // Update the metadata record (keeps the same application_documents row)
        $updated = ApplicationDocument::update($appDocId, $applicationId, [
            'original_filename' => $result['original_filename'],
            'stored_filename'   => $result['stored_filename'],
            'mime_type'         => $result['mime'],
            'file_size'         => (int) $result['size'],
            'file_checksum'     => $result['checksum'],
        ]);

        if (!$updated) {
            // Roll back the new physical file
            FileUploader::delete('documents', $result['stored_filename']);
            $errors['general'] = 'Unable to save the document. Please try again.';
            return false;
        }

        // Delete the old physical file only after the new one is persisted
        FileUploader::delete('documents', $appDoc['stored_filename']);

        Database::execute(
            "UPDATE applications SET updated_at = NOW() WHERE id = ? AND candidate_id = ?",
            'ii',
            [$applicationId, $candidateId]
        );

        return true;
    }

    /**
     * Remove a document from an application.
     *
     * For required documents this is allowed so the candidate can
     * upload a different file before continuing; the Step 4
     * validation will block Continue until a replacement is added.
     *
     * @param int $candidateId
     * @param int $applicationId
     * @param int $appDocId
     * @return bool
     */
    public static function removeDocument(int $candidateId, int $applicationId, int $appDocId): bool
    {
        $application = Application::findForCandidate($candidateId, $applicationId);
        if (!$application || $application['status'] !== 'draft') {
            return false;
        }

        $appDoc = ApplicationDocument::findOwned($appDocId, $applicationId);
        if (!$appDoc) {
            return false;
        }

        // Delete metadata (ownership scoped)
        $deleted = ApplicationDocument::delete($appDocId, $applicationId);
        if (!$deleted) {
            return false;
        }

        // Delete physical file
        FileUploader::delete('documents', $appDoc['stored_filename']);

        Database::execute(
            "UPDATE applications SET updated_at = NOW() WHERE id = ? AND candidate_id = ?",
            'ii',
            [$applicationId, $candidateId]
        );

        return true;
    }

    /**
     * Reuse a candidate's existing profile document for this application.
     *
     * Creates an application_documents record that points back to the
     * candidate's original stored file (no duplicate file on disk).
     *
     * @param int $candidateId
     * @param int $applicationId
     * @param int $candidateDocId  ID in the candidate's `documents` table
     * @return array ['success' => bool, 'message' => string, 'id' => int|null]
     */
    public static function reuseCandidateDocument(int $candidateId, int $applicationId, int $candidateDocId): array
    {
        $application = Application::findForCandidate($candidateId, $applicationId);
        if (!$application) {
            return ['success' => false, 'message' => 'Application not found.', 'id' => null];
        }
        if ($application['status'] !== 'draft') {
            return ['success' => false, 'message' => 'This application can no longer be edited.', 'id' => null];
        }

        // The candidate must own the profile document
        $profileDoc = Document::findOwned($candidateDocId, $candidateId);
        if (!$profileDoc) {
            return ['success' => false, 'message' => 'Document not found.', 'id' => null];
        }

        // Map the profile document type to an application document type
        $docType = self::normaliseDocumentType((string) $profileDoc['document_type']);

        // Verify the document type is allowed for this opportunity
        $oppId = (int) $application['opportunity_id'];
        $data = self::loadDocumentData($candidateId, $applicationId, $oppId);
        $allowedTypes = array_map(
            fn($req) => self::normaliseDocumentType((string) $req['document_name']),
            $data['requirements']
        );
        if (!in_array(strtoupper($docType), $allowedTypes, true)) {
            return ['success' => false, 'message' => 'This document type is not required for this opportunity.', 'id' => null];
        }

        // If there is already an uploaded doc for this type, do not duplicate
        $existing = ApplicationDocument::findForApplication($applicationId, $docType);
        if ($existing) {
            return ['success' => false, 'message' => 'A document for this type is already attached.', 'id' => (int) $existing['id']];
        }

        // Create the application document record pointing to the same stored file
        $id = ApplicationDocument::create($applicationId, [
            'document_type'     => $docType,
            'original_filename' => $profileDoc['original_filename'],
            'stored_filename'   => $profileDoc['stored_filename'],
            'mime_type'         => $profileDoc['mime_type'],
            'file_size'         => (int) $profileDoc['file_size'],
            'file_checksum'     => $profileDoc['file_checksum'],
        ]);

        if (!$id) {
            return ['success' => false, 'message' => 'Unable to attach the document. Please try again.', 'id' => null];
        }

        Database::execute(
            "UPDATE applications SET updated_at = NOW() WHERE id = ? AND candidate_id = ?",
            'ii',
            [$applicationId, $candidateId]
        );

        return ['success' => true, 'message' => 'Document attached successfully.', 'id' => $id];
    }

    /**
     * Normalise a user-facing document name into a canonical
     * application_documents.document_type value.
     *
     * @param string $name
     * @return string
     */
    public static function normaliseDocumentType(string $name): string
    {
        $map = [
            'cv'                 => 'CV',
            'cv/resume'          => 'CV',
            'resume'             => 'CV',
            'qualification'      => 'QUALIFICATION',
            'qualification certificate' => 'QUALIFICATION',
            'certificate'        => 'QUALIFICATION',
            'academic transcript' => 'TRANSCRIPT',
            'transcript'         => 'TRANSCRIPT',
            'id'                 => 'ID',
            'cover letter'       => 'COVER_LETTER',
            'supporting'         => 'SUPPORTING_DOCUMENT',
            'supporting document' => 'SUPPORTING_DOCUMENT',
            'other supporting documents' => 'SUPPORTING_DOCUMENT',
        ];

        $key = strtolower(trim($name));
        return $map[$key] ?? strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $key));
    }

    /**
     * Convert technical uploader messages into friendly messages
     * that do not leak server details.
     *
     * @param string $message
     * @return string
     */
    private static function friendlyUploadMessage(string $message): string
    {
        $lower = strtolower($message);

        if (str_contains($lower, 'too large') || str_contains($lower, 'exceeds')) {
            return 'The file is too large. Please upload a smaller file.';
        }
        if (str_contains($lower, 'empty')) {
            return 'The uploaded file is empty.';
        }
        if (str_contains($lower, 'type') || str_contains($lower, 'extension')) {
            return 'Please upload a PDF, DOC, or DOCX file.';
        }
        if (str_contains($lower, 'partial')) {
            return 'The file was only partially uploaded. Please try again.';
        }
        if (str_contains($lower, 'no file')) {
            return 'Please select a file.';
        }
        return 'Unable to upload the document. Please try again.';
    }

    /**
     * Load eligibility requirements from programme, cohort, and opportunity.
     *
     * @param array $opportunity
     * @return array
     */
    private static function loadEligibilityRequirements(array $opportunity): array
    {
        $requirements = [];

        // Programme-level eligibility
        $programmeId = (int) ($opportunity['programme_id'] ?? 0);
        if ($programmeId > 0) {
            $programmeElig = ProgrammeEligibility::forProgramme($programmeId);
            if ($programmeElig) {
                $requirements['programme'] = $programmeElig;
            }
        }

        // Cohort-level eligibility
        $cohortId = (int) ($opportunity['cohort_id'] ?? 0);
        if ($cohortId > 0) {
            $cohortElig = Database::fetchOne(
                "SELECT * FROM cohort_eligibility WHERE cohort_id = ? LIMIT 1",
                'i',
                [$cohortId]
            );
            if ($cohortElig) {
                $requirements['cohort'] = $cohortElig;
            }
        }

        // Opportunity-level eligibility
        $oppElig = OpportunityEligibility::forOpportunity((int) $opportunity['id']);
        if ($oppElig) {
            $requirements['opportunity'] = $oppElig;
        }

        return $requirements;
    }

    /**
     * Save responses for a step of the application form.
     *
     * @param int   $candidateId
     * @param int   $applicationId
     * @param array $responses  [question_id => response]
     * @return array ['success' => bool, 'message' => string]
     */
    public static function saveResponses(int $candidateId, int $applicationId, array $responses): array
    {
        // Verify ownership and draft status
        $application = Application::findForCandidate($candidateId, $applicationId);
        if (!$application) {
            return ['success' => false, 'message' => 'Application not found.'];
        }

        if ($application['status'] !== 'draft') {
            return ['success' => false, 'message' => 'This application can no longer be edited.'];
        }

        // Verify opportunity still exists and is open
        $opportunity = Opportunity::find((int) $application['opportunity_id']);
        if (!$opportunity) {
            return ['success' => false, 'message' => 'The opportunity for this application could not be found.'];
        }

        $today = date('Y-m-d');
        if (!empty($opportunity['application_close_date']) && $opportunity['application_close_date'] < $today) {
            return ['success' => false, 'message' => 'Applications for this opportunity are now closed.'];
        }

        if (($opportunity['status'] ?? '') === 'closed' || ($opportunity['status'] ?? '') === 'archived') {
            return ['success' => false, 'message' => 'Applications for this opportunity are now closed.'];
        }

        // Sanitize responses
        $cleanResponses = [];
        foreach ($responses as $questionId => $response) {
            $questionId = (int) $questionId;
            if ($questionId <= 0) {
                continue;
            }

            // Verify the question belongs to this opportunity
            $question = ApplicationQuestion::find($questionId);
            if (!$question || (int) $question['opportunity_id'] !== (int) $application['opportunity_id']) {
                continue;
            }

            // Sanitize based on question type
            $cleanResponses[$questionId] = self::sanitizeResponse($question, $response);
        }

        // Save responses
        $saved = ApplicationResponse::saveMany($applicationId, $cleanResponses);

        if (!$saved) {
            return ['success' => false, 'message' => 'Your responses could not be saved. Please try again.'];
        }

        // Update the application's updated_at timestamp
        Database::execute(
            "UPDATE applications SET updated_at = NOW() WHERE id = ? AND candidate_id = ?",
            'ii',
            [$applicationId, $candidateId]
        );

        return ['success' => true, 'message' => 'Application saved.'];
    }

    /**
     * Sanitize a response value based on question type.
     *
     * @param array  $question
     * @param mixed  $response
     * @return string
     */
    private static function sanitizeResponse(array $question, $response): string
    {
        $type = (string) ($question['question_type'] ?? 'text');
        $value = trim((string) $response);

        switch ($type) {
            case 'number':
                return is_numeric($value) ? $value : '';
            case 'date':
                $d = DateTime::createFromFormat('Y-m-d', $value);
                return ($d && $d->format('Y-m-d') === $value) ? $value : '';
            case 'yes_no':
                return in_array($value, ['yes', 'no'], true) ? $value : '';
            case 'radio':
            case 'dropdown':
                // Validate against configured options
                $options = $question['options'] ?? null;
                if (is_array($options) && !in_array($value, $options, true)) {
                    return '';
                }
                return $value;
            case 'checkbox':
                // Checkbox can be comma-separated values
                $options = $question['options'] ?? null;
                if (is_array($options)) {
                    $selected = array_filter(array_map('trim', explode(',', $value)));
                    $valid = array_intersect($selected, $options);
                    return implode(',', $valid);
                }
                return $value;
            case 'textarea':
                return Sanitizer::stripTags($value);
            default:
                return Sanitizer::stripTags($value);
        }
    }

    /**
     * Validate required personal information for Step 1.
     *
     * @param array $user
     * @param array $profile
     * @return array  ['valid' => bool, 'errors' => array, 'missing' => array]
     */
    public static function validatePersonalInfo(array $user, ?array $profile): array
    {
        $errors = [];
        $missing = [];

        // Required fields
        if (empty($user['first_name']) || empty($user['last_name'])) {
            $errors['full_name'] = 'Please enter your full name.';
            $missing[] = 'full_name';
        }

        if (empty($user['email'])) {
            $errors['email'] = 'Please enter your email address.';
            $missing[] = 'email';
        }

        if (empty($user['phone'])) {
            $errors['phone'] = 'Please enter your phone number.';
            $missing[] = 'phone';
        }

        if (empty($user['province'])) {
            $errors['province'] = 'Please select your province.';
            $missing[] = 'province';
        }

        if (empty($user['qualification_level'])) {
            $errors['qualification_level'] = 'Please specify your qualification level.';
            $missing[] = 'qualification_level';
        }

        return [
            'valid'   => empty($errors),
            'errors'  => $errors,
            'missing' => $missing,
        ];
    }

    /**
     * Check if a candidate meets eligibility requirements.
     * Returns a list of requirements with match status.
     *
     * @param array $candidateData  ['user' => array, 'profile' => array, 'qualifications' => array, 'skills' => array]
     * @param array $eligibility    Eligibility requirements from programme/cohort/opportunity
     * @return array  ['requirements' => [...], 'all_met' => bool]
     */
    public static function checkEligibility(array $candidateData, array $eligibility): array
    {
        $requirements = [];
        $allMet = true;

        $user = $candidateData['user'] ?? [];
        $profile = $candidateData['profile'] ?? [];
        $qualifications = $candidateData['qualifications'] ?? [];
        $skills = $candidateData['skills'] ?? [];

        // Helper to add a requirement
        $add = function (string $label, string $candidateValue, bool $met, string $detail = '') use (&$requirements, &$allMet) {
            $requirements[] = [
                'label'           => $label,
                'candidate_value' => $candidateValue,
                'met'             => $met,
                'detail'          => $detail,
            ];
            if (!$met) {
                $allMet = false;
            }
        };

        // Check programme-level requirements
        if (!empty($eligibility['programme'])) {
            $pe = $eligibility['programme'];

            if (!empty($pe['qualification_level'])) {
                $candidateQual = $user['qualification_level'] ?? '';
                $met = $candidateQual !== '' && $candidateQual === $pe['qualification_level'];
                $add(
                    'Qualification',
                    $candidateQual !== '' ? qualification_label($candidateQual) : 'Not specified',
                    $met,
                    'Required: ' . qualification_label($pe['qualification_level'])
                );
            }

            if (!empty($pe['field_of_study'])) {
                $candidateField = !empty($qualifications) ? ($qualifications[0]['name'] ?? '') : '';
                $met = $candidateField !== '' && stripos($candidateField, $pe['field_of_study']) !== false;
                $add(
                    'Field of Study',
                    $candidateField !== '' ? $candidateField : 'Not specified',
                    $met,
                    'Required: ' . $pe['field_of_study']
                );
            }

            if (!empty($pe['province'])) {
                $candidateProvince = $user['province'] ?? '';
                $met = $candidateProvince !== '' && $candidateProvince === $pe['province'];
                $add(
                    'Location',
                    $candidateProvince !== '' ? ucwords(str_replace('-', ' ', $candidateProvince)) : 'Not specified',
                    $met,
                    'Required: ' . ucwords(str_replace('-', ' ', $pe['province']))
                );
            }

            if (!empty($pe['min_experience'])) {
                $expCount = count($candidateData['experiences'] ?? []);
                $met = $expCount >= (int) $pe['min_experience'];
                $add(
                    'Work Experience',
                    $expCount > 0 ? $expCount . ' record(s)' : 'None',
                    $met,
                    'Minimum: ' . $pe['min_experience'] . ' year(s)'
                );
            }
        }

        // Check cohort-level requirements (overrides programme)
        if (!empty($eligibility['cohort'])) {
            $ce = $eligibility['cohort'];

            if (!empty($ce['qualification_level'])) {
                $candidateQual = $user['qualification_level'] ?? '';
                $met = $candidateQual !== '' && $candidateQual === $ce['qualification_level'];
                $add(
                    'Qualification',
                    $candidateQual !== '' ? qualification_label($candidateQual) : 'Not specified',
                    $met,
                    'Required: ' . qualification_label($ce['qualification_level'])
                );
            }

            if (!empty($ce['field_of_study'])) {
                $candidateField = !empty($qualifications) ? ($qualifications[0]['name'] ?? '') : '';
                $met = $candidateField !== '' && stripos($candidateField, $ce['field_of_study']) !== false;
                $add(
                    'Field of Study',
                    $candidateField !== '' ? $candidateField : 'Not specified',
                    $met,
                    'Required: ' . $ce['field_of_study']
                );
            }

            if (!empty($ce['province'])) {
                $candidateProvince = $user['province'] ?? '';
                $met = $candidateProvince !== '' && $candidateProvince === $ce['province'];
                $add(
                    'Location',
                    $candidateProvince !== '' ? ucwords(str_replace('-', ' ', $candidateProvince)) : 'Not specified',
                    $met,
                    'Required: ' . ucwords(str_replace('-', ' ', $ce['province']))
                );
            }
        }

        // Check opportunity-level requirements
        if (!empty($eligibility['opportunity'])) {
            $oe = $eligibility['opportunity'];

            if (!empty($oe['qualification_requirements'])) {
                $candidateQual = $user['qualification_level'] ?? '';
                $met = $candidateQual !== '' && stripos($oe['qualification_requirements'], $candidateQual) !== false;
                $add(
                    'Qualification',
                    $candidateQual !== '' ? qualification_label($candidateQual) : 'Not specified',
                    $met,
                    'Required: ' . $oe['qualification_requirements']
                );
            }

            if (!empty($oe['required_skills'])) {
                $candidateSkillNames = array_column($skills, 'name');
                $requiredSkills = array_filter(array_map('trim', explode(',', $oe['required_skills'])));
                $missingSkills = array_diff($requiredSkills, $candidateSkillNames);
                $met = empty($missingSkills);
                $add(
                    'Required Skills',
                    !empty($candidateSkillNames) ? implode(', ', $candidateSkillNames) : 'None',
                    $met,
                    'Required: ' . implode(', ', $requiredSkills)
                );
            }

            if (!empty($oe['min_experience'])) {
                $expCount = count($candidateData['experiences'] ?? []);
                $met = $expCount >= (int) $oe['min_experience'];
                $add(
                    'Work Experience',
                    $expCount > 0 ? $expCount . ' record(s)' : 'None',
                    $met,
                    'Minimum: ' . $oe['min_experience'] . ' year(s)'
                );
            }

            if (!empty($oe['availability_requirements'])) {
                $availability = $profile['availability_label'] ?? '';
                $met = $availability !== '';
                $add(
                    'Availability',
                    $availability !== '' ? $availability : 'Not specified',
                    $met,
                    'Required: ' . $oe['availability_requirements']
                );
            }
        }

        return [
            'requirements' => $requirements,
            'all_met'      => $allMet,
        ];
    }

    /**
     * Check if a knockout question response indicates ineligibility.
     *
     * @param array $question
     * @param string $response
     * @return bool  True if the response may affect eligibility
     */
    public static function isKnockoutViolation(array $question, string $response): bool
    {
        if (empty($question['is_knockout'])) {
            return false;
        }

        $type = (string) ($question['question_type'] ?? 'text');
        $value = strtolower(trim($response));

        // For yes_no questions, "no" typically indicates ineligibility
        if ($type === 'yes_no') {
            return $value === 'no';
        }

        // For radio/dropdown, check against configured options
        $options = $question['options'] ?? null;
        if (is_array($options) && !empty($options)) {
            // If the response is the last option (usually "No" or "None"), flag it
            $lastOption = strtolower(trim((string) end($options)));
            return $value === $lastOption;
        }

        return false;
    }
}