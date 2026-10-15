<?php
// ============================================================================
// Exportable Supervisor Reports - download endpoint.
// URL: supervisor/reports_export.php?format=csv|xlsx|pdf&programme_id=..&cohort_id=..&status=..
// Uses the same filters, validation and scoping as reports.php, so the file
// contains exactly what the supervisor sees on screen.
// ============================================================================
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('supervisor');
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/_audit.php';
require_once __DIR__ . '/_report_export.php';

$user = current_user();
$supervisorId = (int)($user['id'] ?? $user['user_id'] ?? 0);
if ($supervisorId <= 0) {
    http_response_code(403);
    exit('Invalid Supervisor account.');
}

// ---- Filters (identical rules to reports.php) -------------------------------
$programmeId = (int)($_GET['programme_id'] ?? 0);
$cohortId    = (int)($_GET['cohort_id'] ?? 0);
$status      = trim((string)($_GET['status'] ?? ''));
if ($status !== '' && !in_array($status, ['active', 'completed', 'withdrawn'], true)) {
    $status = '';
}

// ---- Format -----------------------------------------------------------------
$format = strtolower(trim((string)($_GET['format'] ?? 'csv')));
if ($format === 'excel' || $format === 'xls') $format = 'xlsx';
$formats = sv_rx_formats();
if (!isset($formats[$format])) {
    http_response_code(400);
    exit('Unsupported export format.');
}

try {
    $rows   = sv_rx_fetch($supervisorId, $programmeId, $cohortId, $status);
    $labels = sv_rx_filter_labels($supervisorId, $programmeId, $cohortId, $status);

    $by = trim(trim((string)($user['first_name'] ?? '')) . ' ' . trim((string)($user['last_name'] ?? '')));
    if ($by === '') $by = trim((string)($user['full_name'] ?? $user['username'] ?? 'Supervisor'));
    $ds = sv_rx_dataset($rows, $labels, $by !== '' ? $by : 'Supervisor');

    $ext  = $formats[$format][1];
    $mime = $formats[$format][2];

    switch ($format) {
        case 'pdf':
            $body = sv_rx_pdf($ds);
            break;
        case 'xlsx':
            $body = sv_rx_xlsx($ds);
            if ($body === '') {                 // ZipArchive unavailable: Excel XML fallback
                $body = sv_rx_xls($ds);
                $ext  = 'xls';
                $mime = 'application/vnd.ms-excel';
            }
            break;
        default:
            $body = sv_rx_csv($ds);
    }
    if ($body === '') throw new RuntimeException('Empty export body.');
} catch (Throwable $ex) {
    error_log('report export failed: ' . $ex->getMessage());
    http_response_code(500);
    exit('Unable to generate the report right now. Please try again.');
}

// ---- Audit trail (shows under "Report Generation") --------------------------
try {
    sv_audit_log(
        $supervisorId,
        'report_exported',
        'Exported Cohort Progress report as ' . strtoupper($ext) . ' (Programme: ' . $labels['programme'] . '; Cohort: ' . $labels['cohort'] . '; Status: ' . $labels['status'] . ') - '
            . $ds['totals_summary']['cohorts'] . ' cohort(s), ' . $ds['totals_summary']['candidates'] . ' candidate(s)',
        $cohortId > 0 ? 'cohort' : 'report',
        $cohortId > 0 ? $cohortId : null
    );
} catch (Throwable $ex) {
    error_log('audit (report export) failed: ' . $ex->getMessage());
}

// ---- Send file --------------------------------------------------------------
$filename = 'cohort_progress_report_' . date('Ymd_His') . '.' . $ext;
while (ob_get_level() > 0) { @ob_end_clean(); }
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
header('Content-Length: ' . strlen($body));
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
echo $body;
exit;
