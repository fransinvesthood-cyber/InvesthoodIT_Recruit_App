<?php
/**
 * ================================================
 * INVESTHOOD IT - Document Controller
 * ================================================
 * Handles secure upload, replace, download, and
 * delete of candidate documents. Files are stored
 * in private storage (outside web root); only safe
 * metadata is kept in the database.
 */

class DocumentController
{
    /**
     * Upload a document for a candidate.
     *
     * @param int    $userId
     * @param array  $file    $_FILES['document']
     * @param string $docType e.g. cv | qualification | supporting
     * @param int    $uploadedBy
     * @param array  $errors (by reference)
     * @return int|null  New document id
     */
    public static function upload(int $userId, array $file, string $docType, int $uploadedBy, array &$errors): ?int
    {
        // Validate document type
        if (!in_array($docType, ['cv', 'qualification', 'supporting'], true)) {
            $errors['document_type'] = 'Please select a valid document type.';
            return null;
        }

        // Secure upload via FileUploader
        $result = FileUploader::uploadDocument($file);
        if (!$result['success']) {
            $errors['document'] = $result['message'];
            return null;
        }

        // If replacing an existing CV, delete the old one
        if ($docType === 'cv') {
            self::replaceExistingCv($userId);
        }

        $id = Document::create($userId, [
            'document_type'     => $docType,
            'original_filename' => $result['original_filename'],
            'stored_filename'   => $result['stored_filename'],
            'mime_type'         => $result['mime'],
            'file_size'         => $result['size'],
            'file_checksum'     => $result['checksum'],
            'expiry_date'       => null,
            'verification_status' => VERIFY_UNVERIFIED,
            'uploaded_by'       => $uploadedBy,
        ]);

        AuditLog::log($userId, 'document_uploaded', 'document', $id, 'Document uploaded: ' . $result['original_filename']);
        CandidateProfile::refreshCompletion($userId);

        return $id;
    }

    /**
     * Replace an existing document (new file, same metadata record).
     *
     * @param int    $userId
     * @param int    $docId
     * @param array  $file
     * @param int    $uploadedBy
     * @param array  $errors (by reference)
     * @return bool
     */
    public static function replace(int $userId, int $docId, array $file, int $uploadedBy, array &$errors): bool
    {
        $owned = Document::findOwned($docId, $userId);
        if (!$owned) {
            $errors['general'] = 'Document not found.';
            return false;
        }

        $result = FileUploader::uploadDocument($file);
        if (!$result['success']) {
            $errors['document'] = $result['message'];
            return false;
        }

        // Delete the old physical file
        FileUploader::delete('documents', $owned['stored_filename']);

        Document::update($docId, $userId, [
            'document_type'     => $owned['document_type'],
            'original_filename' => $result['original_filename'],
            'stored_filename'   => $result['stored_filename'],
            'mime_type'         => $result['mime'],
            'file_size'         => $result['size'],
            'file_checksum'     => $result['checksum'],
            'verification_status' => VERIFY_UNVERIFIED, // replaced file invalidates verification
            'uploaded_by'       => $uploadedBy,
        ]);

        AuditLog::log($userId, 'document_replaced', 'document', $docId, 'Document replaced: ' . $result['original_filename']);
        CandidateProfile::refreshCompletion($userId);

        return true;
    }

    /**
     * Stream a document to the browser for download (ownership scoped).
     *
     * @param int $userId
     * @param int $docId
     */
    public static function download(int $userId, int $docId): void
    {
        $owned = Document::findOwned($docId, $userId);
        if (!$owned) {
            http_response_code(404);
            exit('Document not found.');
        }

        $path = FileUploader::path('documents', $owned['stored_filename']);
        if (!$path) {
            http_response_code(404);
            exit('Document file not found.');
        }

        // Serve with content-disposition (download) and safe headers
        header('Content-Type: ' . $owned['mime_type']);
        header('Content-Disposition: attachment; filename="' . rawurlencode($owned['original_filename']) . '"');
        header('Content-Length: ' . (int) $owned['file_size']);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');

        readfile($path);
        exit;
    }

    /**
     * Preview a document inline where the browser supports it.
     *
     * @param int $userId
     * @param int $docId
     */
    public static function preview(int $userId, int $docId): void
    {
        $owned = Document::findOwned($docId, $userId);
        if (!$owned) {
            http_response_code(404);
            exit('Document not found.');
        }

        $path = FileUploader::path('documents', $owned['stored_filename']);
        if (!$path) {
            http_response_code(404);
            exit('Document file not found.');
        }

        header('Content-Type: ' . $owned['mime_type']);
        header('Content-Disposition: inline; filename="' . rawurlencode($owned['original_filename']) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');

        readfile($path);
        exit;
    }

    /**
     * Delete a document (metadata + physical file), ownership scoped.
     *
     * @param int $userId
     * @param int $docId
     * @return bool
     */
    public static function delete(int $userId, int $docId): bool
    {
        $owned = Document::findOwned($docId, $userId);
        if (!$owned) {
            return false;
        }

        Document::delete($docId, $userId);
        FileUploader::delete('documents', $owned['stored_filename']);

        AuditLog::log($userId, 'document_deleted', 'document', $docId, 'Document deleted: ' . $owned['original_filename']);
        CandidateProfile::refreshCompletion($userId);

        return true;
    }

    /**
     * When uploading a new CV, remove any previously uploaded CV
     * so the candidate has exactly one active CV.
     *
     * @param int $userId
     */
    private static function replaceExistingCv(int $userId): void
    {
        $existing = Document::forUser($userId);
        foreach ($existing as $doc) {
            if ($doc['document_type'] === 'cv') {
                Document::delete((int) $doc['id'], $userId);
                FileUploader::delete('documents', $doc['stored_filename']);
            }
        }
    }
}
