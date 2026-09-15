<?php
/**
 * Temporary diagnostic script: exercises ApplicationDocument::create
 * exactly as ApplicationFormController::uploadDocument does (no
 * source_document_id / is_reused keys -> null / 0).
 */
require_once __DIR__ . '/includes/bootstrap.php';

$out = '';

try {
    // Pick any candidate-owned application (the code path only needs an id).
    $app = Database::fetchOne(
        "SELECT a.id FROM applications a
         JOIN users u ON u.id = a.candidate_id
         LIMIT 1"
    );
    if (!$app) {
        $out .= "NO APPLICATION FOUND\n";
        file_put_contents(__DIR__ . '/upload_test_result.txt', $out);
        exit;
    }

    $appId = (int) $app['id'];

    // Clean up any previous test row
    Database::execute(
        "DELETE FROM application_documents WHERE document_type = ? AND application_id = ?",
        'si',
        ['__DIAG_TEST__', $appId]
    );

    $id = ApplicationDocument::create($appId, [
        'document_type'     => '__DIAG_TEST__',
        'original_filename' => 'diag.pdf',
        'stored_filename'   => 'diag.pdf',
        'mime_type'         => 'application/pdf',
        'file_size'         => 1234,
        'file_checksum'     => str_repeat('a', 64),
    ]);

    $out .= "INSERT OK, new id = " . var_export($id, true) . "\n";

    // Clean up
    Database::execute(
        "DELETE FROM application_documents WHERE document_type = ? AND application_id = ?",
        'si',
        ['__DIAG_TEST__', $appId]
    );
} catch (Throwable $e) {
    $out .= "EXCEPTION: " . get_class($e) . ": " . $e->getMessage() . "\n";
    $out .= "AT: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

$out .= "PHP_VERSION=" . PHP_VERSION . "\n";
file_put_contents(__DIR__ . '/upload_test_result.txt', $out);