<?php
/**
 * ================================================================
 * INVESTHOOD IT - Programme Manager Report Details
 * ================================================================
 * Returns report-card details for the logged-in Programme Manager.
 * Every query is scoped to programmes assigned to that manager.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('programme_manager');

header('Content-Type: application/json; charset=utf-8');

$user = current_user();
$conn = Database::getConnection();
$managerId = (int) ($user['id'] ?? $user['user_id'] ?? 0);
$report = trim((string) ($_GET['report'] ?? ''));

$allowedReports = [
    'total_programmes',
    'active_programmes',
    'completed_programmes',
    'paused_programmes',
    'total_cohorts',
    'total_candidates',
    'completed_candidates',
    'completion_rate',
    'active_candidates',
    'withdrawn_candidates',
];

if ($managerId <= 0 || !in_array($report, $allowedReports, true)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid report request.',
    ]);
    exit;
}

function pm_report_success(array $data): void
{
    echo json_encode([
        'success' => true,
        'data' => $data,
    ]);
    exit;
}

function pm_report_error(string $message, int $status = 500): void
{
    http_response_code($status);
    echo json_encode([
        'success' => false,
        'message' => $message,
    ]);
    exit;
}

function pm_report_type_label(string $type): string
{
    return ucwords(str_replace('_', ' ', $type));
}

function pm_report_status_label(string $status): string
{
    return ucwords(str_replace('_', ' ', $status));
}

if (in_array($report, [
    'total_programmes',
    'active_programmes',
    'completed_programmes',
    'paused_programmes',
], true)) {
    $status = match ($report) {
        'active_programmes' => 'active',
        'completed_programmes' => 'completed',
        'paused_programmes' => 'paused',
        default => null,
    };

    if ($status === null) {
        $sql = "
            SELECT
                p.id,
                p.name,
                p.type,
                p.status,
                p.start_date,
                p.end_date,
                COUNT(DISTINCT c.id) AS cohort_count,
                COUNT(DISTINCT cp.user_id) AS candidate_count
            FROM programmes p
            LEFT JOIN cohorts c
                ON c.programme_id = p.id
            LEFT JOIN cohort_participants cp
                ON cp.cohort_id = c.id
            WHERE p.programme_manager_id = ?
            GROUP BY p.id, p.name, p.type, p.status, p.start_date, p.end_date
            ORDER BY p.start_date ASC, p.id ASC
        ";
    } else {
        $sql = "
            SELECT
                p.id,
                p.name,
                p.type,
                p.status,
                p.start_date,
                p.end_date,
                COUNT(DISTINCT c.id) AS cohort_count,
                COUNT(DISTINCT cp.user_id) AS candidate_count
            FROM programmes p
            LEFT JOIN cohorts c
                ON c.programme_id = p.id
            LEFT JOIN cohort_participants cp
                ON cp.cohort_id = c.id
            WHERE p.programme_manager_id = ?
              AND p.status = ?
            GROUP BY p.id, p.name, p.type, p.status, p.start_date, p.end_date
            ORDER BY p.start_date ASC, p.id ASC
        ";
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        pm_report_error('Unable to prepare the programme report.');
    }

    if ($status === null) {
        $stmt->bind_param('i', $managerId);
    } else {
        $stmt->bind_param('is', $managerId, $status);
    }

    if (!$stmt->execute()) {
        $stmt->close();
        pm_report_error('Unable to retrieve programme report details.');
    }

    $result = $stmt->get_result();
    $items = [];

    while ($row = $result->fetch_assoc()) {
        $items[] = [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'type' => pm_report_type_label((string) $row['type']),
            'status' => pm_report_status_label((string) $row['status']),
            'start_date' => $row['start_date'] ?: null,
            'end_date' => $row['end_date'] ?: null,
            'cohort_count' => (int) $row['cohort_count'],
            'candidate_count' => (int) $row['candidate_count'],
        ];
    }
    $stmt->close();

    pm_report_success([
        'title' => match ($report) {
            'active_programmes' => 'Active Programmes',
            'completed_programmes' => 'Completed Programmes',
            'paused_programmes' => 'Paused Programmes',
            default => 'Total Programmes',
        },
        'subtitle' => 'Programmes assigned to the logged-in Programme Manager.',
        'items' => $items,
    ]);
}

if ($report === 'total_cohorts') {
    $stmt = $conn->prepare("
        SELECT
            c.id,
            c.name,
            c.status,
            c.start_date,
            c.end_date,
            p.name AS programme_name,
            COUNT(DISTINCT cp.user_id) AS candidate_count
        FROM cohorts c
        INNER JOIN programmes p
            ON p.id = c.programme_id
        LEFT JOIN cohort_participants cp
            ON cp.cohort_id = c.id
        WHERE p.programme_manager_id = ?
        GROUP BY
            c.id,
            c.name,
            c.status,
            c.start_date,
            c.end_date,
            p.name
        ORDER BY c.start_date ASC, c.id ASC
    ");

    if (!$stmt) {
        pm_report_error('Unable to prepare the cohort report.');
    }

    $stmt->bind_param('i', $managerId);
    if (!$stmt->execute()) {
        $stmt->close();
        pm_report_error('Unable to retrieve cohort report details.');
    }

    $result = $stmt->get_result();
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'programme_name' => (string) $row['programme_name'],
            'status' => pm_report_status_label((string) $row['status']),
            'start_date' => $row['start_date'] ?: null,
            'end_date' => $row['end_date'] ?: null,
            'candidate_count' => (int) $row['candidate_count'],
        ];
    }
    $stmt->close();

    pm_report_success([
        'title' => 'Total Cohorts',
        'subtitle' => 'Cohorts belonging to programmes assigned to you.',
        'items' => $items,
    ]);
}

if (in_array($report, [
    'total_candidates',
    'completed_candidates',
    'active_candidates',
    'withdrawn_candidates',
], true)) {
    $statusCondition = match ($report) {
        'completed_candidates' => "AND cp.status = 'completed'",
        'active_candidates' => "AND cp.status IN ('selected', 'onboarded', 'active')",
        'withdrawn_candidates' => "AND cp.status = 'withdrawn'",
        default => '',
    };

    $stmt = $conn->prepare("\n        SELECT\n            cp.user_id AS candidate_id,\n            CONCAT_WS(' ', u.first_name, u.last_name) AS candidate_name,\n            u.email,\n            c.name AS cohort_name,\n            p.name AS programme_name,\n            cp.status AS participant_status\n        FROM cohort_participants cp\n        INNER JOIN cohorts c\n            ON c.id = cp.cohort_id\n        INNER JOIN programmes p\n            ON p.id = c.programme_id\n        INNER JOIN users u\n            ON u.id = cp.user_id\n        WHERE p.programme_manager_id = ?\n          {$statusCondition}\n        ORDER BY p.name ASC, c.name ASC, u.last_name ASC, u.first_name ASC\n    ");

    if (!$stmt) {
        pm_report_error('Unable to prepare the candidate report.');
    }

    $stmt->bind_param('i', $managerId);
    if (!$stmt->execute()) {
        $stmt->close();
        pm_report_error('Unable to retrieve candidate report details.');
    }

    $result = $stmt->get_result();
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = [
            'candidate_id' => (int) $row['candidate_id'],
            'candidate_name' => trim((string) $row['candidate_name']) ?: 'Unnamed Candidate',
            'email' => (string) ($row['email'] ?? ''),
            'cohort_name' => (string) $row['cohort_name'],
            'programme_name' => (string) $row['programme_name'],
            'participant_status' => pm_report_status_label((string) $row['participant_status']),
        ];
    }
    $stmt->close();

    pm_report_success([
        'title' => match ($report) {
            'completed_candidates' => 'Completed Candidates',
            'active_candidates' => 'Active Candidates',
            'withdrawn_candidates' => 'Withdrawn Candidates',
            default => 'Total Candidates',
        },
        'subtitle' => 'Candidate participation records within your assigned programmes.',
        'items' => $items,
    ]);
}

if ($report === 'completion_rate') {
    $stmt = $conn->prepare("\n        SELECT\n            COUNT(DISTINCT cp.user_id) AS total_candidates,\n            COUNT(DISTINCT CASE WHEN cp.status = 'completed' THEN cp.user_id END) AS completed_candidates,\n            COUNT(DISTINCT CASE WHEN cp.status = 'withdrawn' THEN cp.user_id END) AS withdrawn_candidates\n        FROM cohort_participants cp\n        INNER JOIN cohorts c\n            ON c.id = cp.cohort_id\n        INNER JOIN programmes p\n            ON p.id = c.programme_id\n        WHERE p.programme_manager_id = ?\n    ");

    if (!$stmt) {
        pm_report_error('Unable to prepare the completion report.');
    }

    $stmt->bind_param('i', $managerId);
    if (!$stmt->execute()) {
        $stmt->close();
        pm_report_error('Unable to retrieve completion report details.');
    }

    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    $total = (int) ($row['total_candidates'] ?? 0);
    $completed = (int) ($row['completed_candidates'] ?? 0);
    $withdrawn = (int) ($row['withdrawn_candidates'] ?? 0);
    $eligible = max(0, $total - $withdrawn);
    $rate = $eligible > 0 ? round(($completed / $eligible) * 100) : 0;

    pm_report_success([
        'title' => 'Completion Rate',
        'subtitle' => 'Completion performance across your assigned programmes.',
        'summary' => [
            ['label' => 'Completion Rate', 'value' => $rate . '%'],
            ['label' => 'Completed Candidates', 'value' => $completed],
            ['label' => 'Eligible Candidates', 'value' => $eligible],
            ['label' => 'Withdrawn Candidates', 'value' => $withdrawn],
        ],
    ]);
}

pm_report_error('Report type not implemented.', 400);
