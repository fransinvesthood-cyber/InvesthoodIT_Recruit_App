<?php
/**
 * ================================================
 * INVESTHOOD IT - File Uploader Helper
 * ================================================
 * Secure file handling for profile pictures and
 * documents. Validates MIME type, size, generates
 * safe random filenames, prevents executable files,
 * and stores files outside the web root.
 *
 * Physical files live in a private storage directory
 * (uploads_private/) which is NOT web-accessible.
 * They are served only through authenticated endpoints.
 */

class FileUploader
{
    /**
     * Private storage root (outside the web document root).
     * __DIR__ is the helpers/ folder, so ../uploads_private
     * resolves to the project root's uploads_private folder.
     */
    private const STORAGE_ROOT = __DIR__ . '/../uploads_private';

    /**
     * Upload a profile image.
     *
     * @param array $file  $_FILES['profile_picture']
     * @return array ['success' => bool, 'message' => string, 'stored_filename' => string|null]
     */
    public static function uploadProfileImage(array $file): array
    {
        // Validate the upload
        $validation = self::validateUpload($file, ALLOWED_PROFILE_IMAGE_MIMES, MAX_PROFILE_IMAGE_SIZE);
        if (!$validation['success']) {
            return $validation;
        }

        $mime = $validation['mime'];
        $ext  = self::extensionForMime($mime);

        $stored = self::store($file, 'avatars', $ext);
        if (!$stored['success']) {
            return $stored;
        }

        return [
            'success'         => true,
            'message'         => 'Profile picture uploaded successfully.',
            'stored_filename' => $stored['stored_filename'],
        ];
    }

    /**
     * Upload a document (CV, qualification, supporting).
     *
     * @param array $file  $_FILES['document']
     * @return array ['success' => bool, 'message' => string, 'stored_filename' => string|null,
     *                'original_filename' => string|null, 'mime' => string|null, 'size' => int|null,
     *                'checksum' => string|null]
     */
    public static function uploadDocument(array $file): array
    {
        $validation = self::validateUpload($file, ALLOWED_DOCUMENT_MIMES, MAX_DOCUMENT_SIZE);
        if (!$validation['success']) {
            return $validation;
        }

        $mime = $validation['mime'];
        $ext  = self::extensionForMime($mime);

        $stored = self::store($file, 'documents', $ext);
        if (!$stored['success']) {
            return $stored;
        }

        $originalName = self::safeOriginalName($file['name'] ?? 'document');
        $checksum     = hash_file('sha256', self::STORAGE_ROOT . '/documents/' . $stored['stored_filename']);

        return [
            'success'           => true,
            'message'           => 'Document uploaded successfully.',
            'stored_filename'   => $stored['stored_filename'],
            'original_filename' => $originalName,
            'mime'              => $mime,
            'size'              => (int) ($file['size'] ?? 0),
            'checksum'          => $checksum,
        ];
    }

    /**
     * Validate an uploaded file.
     *
     * @param array  $file
     * @param array  $allowedMimes
     * @param int    $maxSize
     * @return array
     */
    private static function validateUpload(array $file, array $allowedMimes, int $maxSize): array
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['success' => false, 'message' => 'Invalid upload.'];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $messages = [
                UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the server file size limit.',
                UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the allowed file size limit.',
                UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded.',
                UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'The server is missing a temporary upload folder.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write the file to disk.',
                UPLOAD_ERR_EXTENSION  => 'A server extension stopped the upload.',
            ];
            return ['success' => false, 'message' => $messages[$file['error']] ?? 'Upload failed.'];
        }

        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'message' => 'Invalid upload source.'];
        }

        // Size check
        if ((int) $file['size'] > $maxSize) {
            $mb = round($maxSize / (1024 * 1024), 1);
            return ['success' => false, 'message' => "The file is too large. Maximum allowed size is {$mb} MB."];
        }

        if ((int) $file['size'] <= 0) {
            return ['success' => false, 'message' => 'The uploaded file is empty.'];
        }

        // MIME type check (finfo inspects the actual file content)
        $mime = self::detectMime($file['tmp_name']);
        if (!in_array($mime, $allowedMimes, true)) {
            return ['success' => false, 'message' => 'This file type is not allowed. Please upload an approved file type.'];
        }

        // Double-check the extension is not rejected (defence in depth)
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (in_array($ext, ['php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar', 'cgi', 'pl', 'py', 'sh', 'exe', 'bat', 'cmd', 'htaccess'], true)) {
            return ['success' => false, 'message' => 'This file type is not allowed.'];
        }

        return ['success' => true, 'message' => 'Valid', 'mime' => $mime];
    }

    /**
     * Detect the actual MIME type using finfo (content-based).
     *
     * @param string $tmpPath
     * @return string
     */
    private static function detectMime(string $tmpPath): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $tmpPath);
            finfo_close($finfo);
            return $mime ?: '';
        }
        // Fallback
        return (string) mime_content_type($tmpPath);
    }

    /**
     * Map a MIME type to a safe extension.
     *
     * @param string $mime
     * @return string
     */
    private static function extensionForMime(string $mime): string
    {
        $map = [
            'image/jpeg'   => 'jpg',
            'image/png'    => 'png',
            'image/webp'   => 'webp',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'text/plain'   => 'txt',
        ];
        return $map[$mime] ?? 'bin';
    }

    /**
     * Store the uploaded file into the private storage with a safe random name.
     *
     * @param array  $file
     * @param string $subdir
     * @param string $ext
     * @return array
     */
    private static function store(array $file, string $subdir, string $ext): array
    {
        $dir = self::STORAGE_ROOT . '/' . $subdir;
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0750, true) && !is_dir($dir)) {
                return ['success' => false, 'message' => 'Storage directory could not be created.'];
            }
        }

        // Generate a random, collision-resistant filename
        $storedFilename = bin2hex(random_bytes(16)) . '.' . $ext;

        $dest = $dir . '/' . $storedFilename;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return ['success' => false, 'message' => 'Failed to store the uploaded file.'];
        }

        // Harden permissions (no group/other write)
        @chmod($dest, 0640);

        return ['success' => true, 'stored_filename' => $storedFilename];
    }

    /**
     * Get the absolute path for a stored file in a subdirectory.
     *
     * @param string $subdir
     * @param string $storedFilename
     * @return string|null  null if the file does not exist
     */
    public static function path(string $subdir, string $storedFilename): ?string
    {
        // Prevent path traversal
        if (str_contains($storedFilename, '..') || str_contains($storedFilename, '/') || str_contains($storedFilename, '\\')) {
            return null;
        }
        $path = self::STORAGE_ROOT . '/' . $subdir . '/' . $storedFilename;
        return is_file($path) ? $path : null;
    }

    /**
     * Delete a stored file.
     *
     * @param string $subdir
     * @param string $storedFilename
     * @return bool
     */
    public static function delete(string $subdir, string $storedFilename): bool
    {
        if (str_contains($storedFilename, '..') || str_contains($storedFilename, '/') || str_contains($storedFilename, '\\')) {
            return false;
        }
        $path = self::STORAGE_ROOT . '/' . $subdir . '/' . $storedFilename;
        if (is_file($path)) {
            return @unlink($path);
        }
        return false;
    }

    /**
     * Sanitise an original filename for safe display.
     *
     * @param string $name
     * @return string
     */
    private static function safeOriginalName(string $name): string
    {
        $name = basename($name);
        $name = preg_replace('/[^\w.\- ]+/u', '_', $name);
        $name = trim($name);
        if ($name === '' || $name === '.') {
            $name = 'document';
        }
        return mb_substr($name, 0, 255);
    }

    /**
     * Ensure the private storage directory exists (idempotent).
     */
    public static function ensureStorage(): void
    {
        foreach (['', '/avatars', '/documents'] as $sub) {
            $dir = self::STORAGE_ROOT . $sub;
            if (!is_dir($dir)) {
                @mkdir($dir, 0750, true);
            }
        }
        // Block web access as an extra safety net if the folder is ever exposed
        $htaccess = self::STORAGE_ROOT . '/.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "Require all denied\n");
        }
    }
}
