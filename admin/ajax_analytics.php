<?php
/**
 * ================================================
 * INVESTHOOD IT - AJAX: Admin Dashboard Analytics
 * ================================================
 * Returns dynamic, database-driven analytics for the
 * Admin Dashboard "Dashboard Analytics" section.
 *
 * Role: Administrator only.
 *
 * GET params (all optional):
 *   period         - 7d | 30d | 90d | 6m | 12m | custom
 *   date_from      - Y-m-d (used when period = custom)
 *   date_to        - Y-m-d (used when period = custom)
 *   programme_id   - filter by programme
 *   cohort_id      - filter by cohort
 *   opportunity_id - filter by opportunity
 *   status         - filter by application status
 *   options        - when "1", also return filter dropdown options
 *
 * Returns JSON: { data: {...} } or { error: "..." }.
 *
 * Every value is aggregated in SQL and the payload contains only the
 * structured numbers/labels the dashboard needs to render - never raw
 * record sets.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('admin');

header('Content-Type: application/json');

// ---- Only accept GET requests ----
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

try {
    // Normalise + whitelist every filter (period/date/programme/cohort/
    // opportunity/status). Invalid values fall back to safe defaults.
    $filters = Analytics::normalizeFilters([
        'period'         => $_GET['period'] ?? '30d',
        'date_from'      => $_GET['date_from'] ?? null,
        'date_to'        => $_GET['date_to'] ?? null,
        'programme_id'   => $_GET['programme_id'] ?? 0,
        'cohort_id'      => $_GET['cohort_id'] ?? 0,
        'opportunity_id' => $_GET['opportunity_id'] ?? 0,
        'status'         => $_GET['status'] ?? '',
    ]);

    $payload = Analytics::build($filters);

    // Filter dropdown options are only returned on demand so the routine
    // refresh payload stays small and fast.
    if ((string) ($_GET['options'] ?? '') === '1') {
        $payload['options'] = Analytics::filterOptions();
    }

    echo json_encode(['data' => $payload]);
} catch (Throwable $ex) {
    error_log('[ANALYTICS] Endpoint failure: ' . $ex->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load analytics data. Please try again.']);
}
exit;
