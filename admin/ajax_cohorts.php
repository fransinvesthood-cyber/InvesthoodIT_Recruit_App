<?php
/**
 * ================================================
 * INVESTHOOD IT - AJAX: Cohorts for a Programme
 * ================================================
 * Returns cohorts belonging to a given programme
 * as JSON. Used by the Opportunity create/edit form
 * to dynamically populate the cohort dropdown.
 *
 * Role: Administrator only.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('admin');

// Only accept JSON requests
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$programmeId = (int) ($_GET['programme_id'] ?? 0);

if ($programmeId <= 0 || !Programme::find($programmeId)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid programme.']);
    exit;
}

$cohorts = Cohort::forProgramme($programmeId);

$data = [];
foreach ($cohorts as $c) {
    $data[] = [
        'id'          => (int) $c['id'],
        'name'        => $c['name'],
        'status'      => $c['status'],
        'start_date'  => $c['start_date'] ?? null,
        'end_date'    => $c['end_date'] ?? null,
        'application_open_date'  => $c['application_open_date'] ?? null,
        'application_close_date' => $c['application_close_date'] ?? null,
    ];
}

header('Content-Type: application/json');
echo json_encode(['cohorts' => $data]);
exit;
