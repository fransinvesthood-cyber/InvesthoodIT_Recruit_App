<?php
/**
 * ================================================================
 * INVESTHOOD IT - Programme Manager Candidates
 * ================================================================
 * File:
 *     programme/candidates.php
 *
 * Role:
 *     Programme Manager
 *
 * Purpose:
 *     Displays candidates participating in programmes managed
 *     by the currently logged-in Programme Manager.
 *
 * Database structure:
 *     users
 *     programmes
 *     cohorts
 *     cohort_participants
 * ================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('programme_manager');
$user = current_user();

/*
|--------------------------------------------------------------------------
| Recruitment Stage Helpers (inline)
|--------------------------------------------------------------------------
*/

if (!function_exists('recruitment_stages')) {
    function recruitment_stages(): array
    {
        return [
            'submitted' => [
                'label' => 'Submitted',
                'short' => 'Submitted',
                'icon' => 'fa-paper-plane',
                'colour' => '#6366f1',
                'description' => 'Application received and awaiting review.',
            ],
            'eligibility_review' => [
                'label' => 'Eligibility Review',
                'short' => 'Eligibility',
                'icon' => 'fa-clipboard-check',
                'colour' => '#0ea5e9',
                'description' => 'Checking minimum eligibility criteria.',
            ],
            'screened' => [
                'label' => 'Screened',
                'short' => 'Screened',
                'icon' => 'fa-filter',
                'colour' => '#14b8a6',
                'description' => 'Application screened against programme requirements.',
            ],
            'assessment' => [
                'label' => 'Assessment',
                'short' => 'Assessment',
                'icon' => 'fa-file-pen',
                'colour' => '#f59e0b',
                'description' => 'Candidate is completing an assessment.',
            ],
            'interview' => [
                'label' => 'Interview',
                'short' => 'Interview',
                'icon' => 'fa-comments',
                'colour' => '#8b5cf6',
                'description' => 'Interview stage with the selection panel.',
            ],
            'waitlisted' => [
                'label' => 'Waitlisted',
                'short' => 'Waitlisted',
                'icon' => 'fa-hourglass-half',
                'colour' => '#f97316',
                'description' => 'Held on the waiting list pending capacity.',
            ],
            'selected' => [
                'label' => 'Selected',
                'short' => 'Selected',
                'icon' => 'fa-circle-check',
                'colour' => '#22c55e',
                'description' => 'Candidate has been selected for the programme.',
            ],
            'rejected' => [
                'label' => 'Rejected',
                'short' => 'Rejected',
                'icon' => 'fa-circle-xmark',
                'colour' => '#ef4444',
                'description' => 'Application was not successful.',
            ],
        ];
    }
}

if (!function_exists('recruitment_stage_order')) {
    function recruitment_stage_order(): array
    {
        return array_keys(recruitment_stages());
    }
}

if (!function_exists('recruitment_stage_index')) {
    function recruitment_stage_index(string $stage): int
    {
        $index = array_search($stage, recruitment_stage_order(), true);

        return $index === false ? -1 : (int) $index;
    }
}

if (!function_exists('normalise_candidate_stage')) {
    function normalise_candidate_stage(?string $status): string
    {
        $status = strtolower(trim((string) $status));

        $map = [
            'submitted' => 'submitted',
            'applied' => 'submitted',
            'pending' => 'submitted',

            'eligibility' => 'eligibility_review',
            'eligibility_review' => 'eligibility_review',
            'eligibility review' => 'eligibility_review',

            'screened' => 'screened',
            'screening' => 'screened',

            'assessment' => 'assessment',
            'assessed' => 'assessment',

            'interview' => 'interview',
            'interviewed' => 'interview',

            'waitlisted' => 'waitlisted',
            'waitlist' => 'waitlisted',
            'waiting' => 'waitlisted',

            'selected' => 'selected',
            'onboarded' => 'selected',
            'active' => 'selected',
            'completed' => 'selected',
            'accepted' => 'selected',

            'rejected' => 'rejected',
            'declined' => 'rejected',
            'withdrawn' => 'rejected',
        ];

        return $map[$status] ?? 'submitted';
    }
}

if (!function_exists('candidate_stage_label')) {
    function candidate_stage_label(string $stage): string
    {
        $stages = recruitment_stages();

        return $stages[$stage]['label']
            ?? ucwords(str_replace('_', ' ', $stage));
    }
}

if (!function_exists('candidate_stage_icon')) {
    function candidate_stage_icon(string $stage): string
    {
        $stages = recruitment_stages();

        return $stages[$stage]['icon'] ?? 'fa-circle';
    }
}

if (!function_exists('candidate_stage_colour')) {
    function candidate_stage_colour(string $stage): string
    {
        $stages = recruitment_stages();

        return $stages[$stage]['colour'] ?? '#6b7280';
    }
}

if (!function_exists('candidate_progress_percentage')) {
    function candidate_progress_percentage(string $stage): int
    {
        if ($stage === 'rejected') {
            return 100;
        }

        $order = recruitment_stage_order();
        $total = count($order) - 1;

        $index = recruitment_stage_index($stage);

        if ($index < 0 || $total <= 0) {
            return 0;
        }

        return (int) round(($index / $total) * 100);
    }
}

$flashes = render_flashes();
$conn = Database::getConnection();
$currentPage = 'candidates';
$pageTitle = 'Candidates';

/*
|--------------------------------------------------------------------------
| Current Programme Manager
|--------------------------------------------------------------------------
*/
$managerId = (int) ($user['id'] ?? $user['user_id'] ?? 0);

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/
$search = trim($_GET['search'] ?? '');
$programmeId = (int) ($_GET['programme_id'] ?? 0);
$cohortId = (int) ($_GET['cohort_id'] ?? 0);
$status = trim($_GET['status'] ?? '');

/*
|--------------------------------------------------------------------------
| Allowed Candidate Statuses
|--------------------------------------------------------------------------
*/
$allowedStatuses = [
    'selected',
    'onboarded',
    'active',
    'completed',
    'withdrawn'
];

if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
    $status = '';
}

/*
|--------------------------------------------------------------------------
| Manager's Programmes
|--------------------------------------------------------------------------
*/
$programmes = [];
$stmt = $conn->prepare("
    SELECT
        id,
        name,
        type,
        status
    FROM programmes
    WHERE programme_manager_id = ?
    ORDER BY name ASC
");
if ($stmt) {
    $stmt->bind_param('i', $managerId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $programmes[] = $row;
    }
    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| Manager's Cohorts
|--------------------------------------------------------------------------
*/
$cohorts = [];
$stmt = $conn->prepare("
    SELECT
        c.id,
        c.name,
        c.programme_id,
        p.name AS programme_name
    FROM cohorts c
    INNER JOIN programmes p
        ON p.id = c.programme_id
    WHERE p.programme_manager_id = ?
    ORDER BY
        p.name ASC,
        c.name ASC
");
if ($stmt) {
    $stmt->bind_param('i', $managerId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $cohorts[] = $row;
    }
    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| Candidate Statistics
|--------------------------------------------------------------------------
*/
$totalCandidates = 0;
$selectedCandidates = 0;
$onboardedCandidates = 0;
$activeCandidates = 0;
$completedCandidates = 0;
$withdrawnCandidates = 0;

/*
|--------------------------------------------------------------------------
| Base Statistics Query
|--------------------------------------------------------------------------
*/
$statsSql = "
    SELECT
        COUNT(DISTINCT cp.user_id) AS total_candidates,
        COUNT(
            DISTINCT CASE
                WHEN cp.status = 'selected'
                THEN cp.user_id
            END
        ) AS selected_candidates,
        COUNT(
            DISTINCT CASE
                WHEN cp.status = 'onboarded'
                THEN cp.user_id
            END
        ) AS onboarded_candidates,
        COUNT(
            DISTINCT CASE
                WHEN cp.status = 'active'
                THEN cp.user_id
            END
        ) AS active_candidates,
        COUNT(
            DISTINCT CASE
                WHEN cp.status = 'completed'
                THEN cp.user_id
            END
        ) AS completed_candidates,
        COUNT(
            DISTINCT CASE
                WHEN cp.status = 'withdrawn'
                THEN cp.user_id
            END
        ) AS withdrawn_candidates
    FROM cohort_participants cp
    INNER JOIN cohorts c
        ON c.id = cp.cohort_id
    INNER JOIN programmes p
        ON p.id = c.programme_id
    INNER JOIN users u
        ON u.id = cp.user_id
    WHERE p.programme_manager_id = ?
";
$statsTypes = 'i';
$statsParams = [$managerId];

if ($programmeId > 0) {
    $statsSql .= " AND p.id = ? ";
    $statsTypes .= 'i';
    $statsParams[] = $programmeId;
}

if ($cohortId > 0) {
    $statsSql .= " AND c.id = ? ";
    $statsTypes .= 'i';
    $statsParams[] = $cohortId;
}

if ($status !== '') {
    $statsSql .= " AND cp.status = ? ";
    $statsTypes .= 's';
    $statsParams[] = $status;
}

if ($search !== '') {
    $statsSql .= "
        AND (
            u.first_name LIKE ?
            OR u.last_name LIKE ?
            OR u.email LIKE ?
            OR u.phone LIKE ?
        )
    ";
    $searchValue = '%' . $search . '%';
    $statsTypes .= 'ssss';
    $statsParams[] = $searchValue;
    $statsParams[] = $searchValue;
    $statsParams[] = $searchValue;
    $statsParams[] = $searchValue;
}

$stmt = Database::prepare(
    $statsSql,
    $statsTypes,
    $statsParams
);
if ($stmt) {
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $totalCandidates = (int) ($row['total_candidates'] ?? 0);
        $selectedCandidates = (int) ($row['selected_candidates'] ?? 0);
        $onboardedCandidates = (int) ($row['onboarded_candidates'] ?? 0);
        $activeCandidates = (int) ($row['active_candidates'] ?? 0);
        $completedCandidates = (int) ($row['completed_candidates'] ?? 0);
        $withdrawnCandidates = (int) ($row['withdrawn_candidates'] ?? 0);
    }
    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| Fetch Candidates
|--------------------------------------------------------------------------
*/
$candidates = [];
$sql = "
    SELECT
        cp.id AS participant_id,
        cp.user_id,
        cp.cohort_id,
        cp.status AS participation_status,
        cp.selected_at,
        cp.onboarded_at,
        cp.completed_at,
        cp.created_at,
        cp.updated_at,
        u.first_name,
        u.last_name,
        u.email,
        u.phone,
        u.status AS user_status,
        c.name AS cohort_name,
        p.id AS programme_id,
        p.name AS programme_name,
        p.type AS programme_type,
        p.status AS programme_status
    FROM cohort_participants cp
    INNER JOIN users u
        ON u.id = cp.user_id
    INNER JOIN cohorts c
        ON c.id = cp.cohort_id
    INNER JOIN programmes p
        ON p.id = c.programme_id
    WHERE p.programme_manager_id = ?
";
$types = 'i';
$params = [$managerId];

if ($programmeId > 0) {
    $sql .= " AND p.id = ? ";
    $types .= 'i';
    $params[] = $programmeId;
}

if ($cohortId > 0) {
    $sql .= " AND c.id = ? ";
    $types .= 'i';
    $params[] = $cohortId;
}

if ($status !== '') {
    $sql .= " AND cp.status = ? ";
    $types .= 's';
    $params[] = $status;
}

if ($search !== '') {
    $sql .= "
        AND (
            u.first_name LIKE ?
            OR u.last_name LIKE ?
            OR u.email LIKE ?
            OR u.phone LIKE ?
        )
    ";
    $searchValue = '%' . $search . '%';
    $types .= 'ssss';
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}

$sql .= "
    ORDER BY
        u.first_name ASC,
        u.last_name ASC,
        p.name ASC,
        c.name ASC
";

$stmt = Database::prepare(
    $sql,
    $types,
    $params
);
if ($stmt) {
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $candidates[] = $row;
    }
    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| Decorate Candidates With Recruitment Stage Info
|--------------------------------------------------------------------------
*/
foreach ($candidates as &$candidateRow) {
    $stage = normalise_candidate_stage(
        $candidateRow['participation_status'] ?? 'submitted'
    );

    $candidateRow['recruitment_stage'] = $stage;
    $candidateRow['stage_label'] = candidate_stage_label($stage);
    $candidateRow['stage_icon'] = candidate_stage_icon($stage);
    $candidateRow['stage_colour'] = candidate_stage_colour($stage);
    $candidateRow['stage_percentage'] = candidate_progress_percentage($stage);
}
unset($candidateRow);

/*
|--------------------------------------------------------------------------
| Recruitment Stage Statistics
|--------------------------------------------------------------------------
*/
$stageStats = [];

foreach (recruitment_stage_order() as $stageSlug) {
    $stageStats[$stageSlug] = 0;
}

foreach ($candidates as $candidateRow) {
    $slug = $candidateRow['recruitment_stage'] ?? 'submitted';
    if (isset($stageStats[$slug])) {
        $stageStats[$slug]++;
    }
}

/*
|--------------------------------------------------------------------------
| Helper - Status Label
|--------------------------------------------------------------------------
*/
function candidate_status_label($status)
{
    return ucwords(
        str_replace(
            '_',
            ' ',
            $status
        )
    );
}

/*
|--------------------------------------------------------------------------
| Helper - Status Class
|--------------------------------------------------------------------------
*/
function candidate_status_class($status)
{
    switch ($status) {
        case 'active':
            return 'status-active';
        case 'onboarded':
            return 'status-onboarded';
        case 'selected':
            return 'status-selected';
        case 'completed':
            return 'status-completed';
        case 'withdrawn':
            return 'status-withdrawn';
        default:
            return 'status-default';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >
    <title>
        Candidates | Programme Manager | Investhood IT
    </title>
    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >
    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
        crossorigin
    >
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet"
    >
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
        crossorigin="anonymous"
    >
    <link
        rel="stylesheet"
        href="<?= url('css/styles.css') ?>"
    >
    <style>
        .candidate-filters {
            display: grid;
            grid-template-columns:
                minmax(220px, 1fr)
                minmax(180px, 220px)
                minmax(180px, 220px)
                minmax(180px, 220px)
                auto;
            gap: 1rem;
            align-items: end;
        }
        .filter-group label {
            display: block;
            margin-bottom: .45rem;
            font-size: .85rem;
            font-weight: 600;
        }
        .filter-control {
            width: 100%;
            padding: .75rem .85rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: #fff;
            font-family: inherit;
        }
        .candidate-table-wrapper {
            margin-top: 1rem;
            overflow-x: auto;
            background: #fff;
            border-radius: 12px;
        }
        .candidate-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1150px;
        }
        .candidate-table th {
            text-align: left;
            padding: 1rem;
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
            font-size: .8rem;
            text-transform: uppercase;
            letter-spacing: .03em;
        }
        .candidate-table td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .candidate-table tr:hover {
            background: #f8fafc;
        }
        .candidate-name {
            font-weight: 700;
        }
        .candidate-contact {
            font-size: .8rem;
            color: #6b7280;
            margin-top: .2rem;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: .35rem .7rem;
            border-radius: 999px;
            font-size: .75rem;
            font-weight: 700;
        }
        .status-active {
            background: #dcfce7;
            color: #166534;
        }
        .status-onboarded {
            background: #dbeafe;
            color: #1e40af;
        }
        .status-selected {
            background: #fef3c7;
            color: #92400e;
        }
        .status-completed {
            background: #e0e7ff;
            color: #3730a3;
        }
        .status-withdrawn {
            background: #fee2e2;
            color: #991b1b;
        }
        .status-default {
            background: #f3f4f6;
            color: #374151;
        }
        .candidate-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            text-decoration: none;
            background: #eff6ff;
            color: #1a56db;
        }
        .candidate-action:hover {
            background: #dbeafe;
        }
        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
        }
        .empty-state i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: #9ca3af;
        }
        @media (max-width: 1100px) {
            .candidate-filters {
                grid-template-columns: 1fr 1fr;
            }
        }
        @media (max-width: 650px) {
            .candidate-filters {
                grid-template-columns: 1fr;
            }
        }

        /* ============================================================
           RECRUITMENT PROGRESS TRACKING
           ============================================================ */

        .stage-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.75rem;
        }

        .stage-summary__item {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            padding: 0.75rem 0.85rem;
            border-radius: 10px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
        }

        .stage-summary__icon {
            width: 34px;
            height: 34px;
            flex: 0 0 34px;
            display: grid;
            place-items: center;
            border-radius: 9px;
            color: #fff;
            font-size: 0.8rem;
        }

        .stage-summary__info {
            min-width: 0;
            flex: 1;
        }

        .stage-summary__count {
            display: block;
            font-size: 1.1rem;
            font-weight: 800;
            color: #111827;
            line-height: 1.1;
        }

        .stage-summary__label {
            display: block;
            margin-top: 2px;
            font-size: 0.7rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-weight: 600;
        }

        .candidate-stage-cell {
            min-width: 200px;
        }

        .candidate-stage-cell__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            margin-bottom: 0.4rem;
        }

        .candidate-stage-cell__label {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.78rem;
            font-weight: 700;
        }

        .candidate-stage-cell__bar {
            width: 100%;
            height: 6px;
            border-radius: 999px;
            background: #e5e7eb;
            overflow: hidden;
        }

        .candidate-stage-cell__fill {
            display: block;
            height: 100%;
            border-radius: inherit;
            transition: width 0.3s ease;
        }

        .candidate-stage-cell__percentage {
            font-size: 0.7rem;
            font-weight: 700;
            color: #6b7280;
        }

        /* Modal */
        .candidate-progress-modal {
            position: fixed;
            inset: 0;
            z-index: 2147483647;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            visibility: hidden;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease, visibility 0.2s ease;
        }

        .candidate-progress-modal.is-open {
            visibility: visible;
            opacity: 1;
            pointer-events: auto;
        }

        .candidate-progress-modal__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.72);
            backdrop-filter: blur(5px);
        }

        .candidate-progress-modal__dialog {
            position: relative;
            z-index: 2;
            width: min(760px, 100%);
            max-height: calc(100vh - 40px);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: #fff;
            border: 1px solid #e4e7ec;
            border-radius: 20px;
            box-shadow: 0 30px 90px rgba(0, 0, 0, 0.35);
            transform: translateY(15px) scale(0.98);
            transition: transform 0.2s ease;
        }

        .candidate-progress-modal.is-open .candidate-progress-modal__dialog {
            transform: translateY(0) scale(1);
        }

        .candidate-progress-modal__header {
            padding: 20px 22px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            border-bottom: 1px solid #e4e7ec;
        }

        .candidate-progress-modal__eyebrow {
            display: block;
            margin-bottom: 4px;
            color: #2563eb;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .candidate-progress-modal__title {
            margin: 0;
            color: #101828;
            font-size: 20px;
            font-weight: 800;
        }

        .candidate-progress-modal__close {
            width: 38px;
            height: 38px;
            flex: 0 0 38px;
            display: grid;
            place-items: center;
            border: 1px solid #e4e7ec;
            border-radius: 10px;
            background: #f8fafc;
            color: #475467;
            cursor: pointer;
        }

        .candidate-progress-modal__close:hover {
            background: #eff6ff;
            color: #2563eb;
        }

        .candidate-progress-modal__body {
            padding: 22px;
            overflow-y: auto;
        }

        .candidate-progress-modal__footer {
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            border-top: 1px solid #e4e7ec;
        }

        .candidate-progress-summary {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px;
            border-radius: 14px;
            background: #f8fafc;
            border: 1px solid #e4e7ec;
            margin-bottom: 20px;
        }

        .candidate-progress-summary__avatar {
            width: 52px;
            height: 52px;
            flex: 0 0 52px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: #eef2ff;
            color: #4f46e5;
            font-size: 1rem;
            font-weight: 800;
        }

        .candidate-progress-summary__info {
            min-width: 0;
            flex: 1;
        }

        .candidate-progress-summary__info strong {
            display: block;
            color: #101828;
            font-size: 15px;
            font-weight: 800;
        }

        .candidate-progress-summary__info span {
            display: block;
            margin-top: 3px;
            color: #667085;
            font-size: 12px;
        }

        .candidate-progress-summary__badge {
            flex: 0 0 auto;
            padding: 6px 12px;
            border-radius: 999px;
            color: #fff;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .candidate-progress-timeline {
            display: flex;
            flex-direction: column;
            gap: 0;
            margin-top: 4px;
        }

        .candidate-progress-timeline__item {
            position: relative;
            display: flex;
            gap: 16px;
            padding-bottom: 22px;
        }

        .candidate-progress-timeline__item:last-child {
            padding-bottom: 0;
        }

        .candidate-progress-timeline__item::before {
            content: '';
            position: absolute;
            left: 19px;
            top: 40px;
            bottom: 0;
            width: 2px;
            background: #e5e7eb;
        }

        .candidate-progress-timeline__item:last-child::before {
            display: none;
        }

        .candidate-progress-timeline__item.is-complete::before {
            background: var(--stage-colour, #2563eb);
        }

        .candidate-progress-timeline__marker {
            position: relative;
            z-index: 1;
            width: 40px;
            height: 40px;
            flex: 0 0 40px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: #f3f4f6;
            color: #9ca3af;
            border: 3px solid #fff;
            box-shadow: 0 0 0 1px #e5e7eb;
            font-size: 0.85rem;
        }

        .candidate-progress-timeline__item.is-complete
            .candidate-progress-timeline__marker {
            background: var(--stage-colour, #2563eb);
            color: #fff;
            box-shadow: 0 0 0 1px var(--stage-colour, #2563eb);
        }

        .candidate-progress-timeline__item.is-current
            .candidate-progress-timeline__marker {
            background: var(--stage-colour, #2563eb);
            color: #fff;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.18);
        }

        .candidate-progress-timeline__item.is-rejected
            .candidate-progress-timeline__marker {
            background: #ef4444;
            color: #fff;
            box-shadow: 0 0 0 1px #ef4444;
        }

        .candidate-progress-timeline__content {
            flex: 1;
            min-width: 0;
            padding-top: 6px;
        }

        .candidate-progress-timeline__content strong {
            display: block;
            color: #101828;
            font-size: 13px;
            font-weight: 700;
        }

        .candidate-progress-timeline__content span {
            display: block;
            margin-top: 3px;
            color: #667085;
            font-size: 12px;
            line-height: 1.5;
        }

        .candidate-progress-timeline__status {
            display: inline-block;
            margin-top: 6px;
            padding: 3px 8px;
            border-radius: 999px;
            background: #ecfdf3;
            color: #027a48;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .candidate-progress-timeline__status--current {
            background: #eff6ff;
            color: #1d4ed8;
        }

        .candidate-progress-timeline__status--rejected {
            background: #fef2f2;
            color: #b91c1c;
        }

        /* Dark mode */
        html[data-theme="dark"] .candidate-progress-modal__dialog {
            background: #1e293b;
            border-color: #334155;
        }

        html[data-theme="dark"] .candidate-progress-modal__header,
        html[data-theme="dark"] .candidate-progress-modal__footer {
            border-color: #334155;
        }

        html[data-theme="dark"] .candidate-progress-modal__title,
        html[data-theme="dark"] .candidate-progress-summary__info strong,
        html[data-theme="dark"] .candidate-progress-timeline__content strong {
            color: #f8fafc;
        }

        html[data-theme="dark"] .candidate-progress-summary {
            background: #111827;
            border-color: #334155;
        }

        html[data-theme="dark"] .stage-summary__item {
            background: #111827;
            border-color: #334155;
        }

        html[data-theme="dark"] .stage-summary__count {
            color: #f8fafc;
        }

        html[data-theme="dark"] .candidate-progress-modal__close {
            background: #111827;
            color: #e2e8f0;
            border-color: #475569;
        }

        body.candidate-progress-modal-open {
            overflow: hidden !important;
        }

        @media (max-width: 620px) {
            .candidate-progress-modal {
                padding: 12px;
            }

            .candidate-progress-modal__dialog {
                max-height: calc(100vh - 24px);
                border-radius: 16px;
            }

            .candidate-progress-summary {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
    <link rel="stylesheet" href="<?= url('css/programme_manager_enhancements.css') ?>?v=20260920">
</head>
<body class="dashboard-page">
<div class="dashboard">

    <!-- =====================================================
         SIDEBAR
    ====================================================== -->
    <?php require __DIR__ . '/sidebar.php'; ?>

    <!-- =====================================================
         MAIN CONTENT
    ====================================================== -->
    <main class="dashboard__main">

        <!-- =================================================
             HEADER
        ================================================== -->
        <?php require __DIR__ . '/navbar.php'; ?>

        <!-- =================================================
             CONTENT
        ================================================== -->
        <div class="dash-content">

            <?= $flashes ?>

            <!-- =================================================
                 WELCOME
            ================================================== -->
            <div class="welcome-card">
                <div class="welcome-card__bg"></div>
                <div class="welcome-card__content">
                    <h1 class="welcome-card__greeting">
                        Programme
                        <span class="text-gradient">
                            Candidates
                        </span>
                    </h1>
                    <p>
                        Monitor candidates participating in programmes
                        managed by you.
                    </p>
                </div>
            </div>

            <!-- =================================================
                 STATISTICS
            ================================================== -->
            <div
                class="overview-grid"
                style="margin-top:2rem;"
            >
                <!-- Total -->
                <div class="overview-card">
                    <div class="overview-card__icon overview-card__icon--primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="overview-card__info">
                        <span class="overview-card__number">
                            <?= number_format($totalCandidates) ?>
                        </span>
                        <span class="overview-card__label">
                            Total Candidates
                        </span>
                    </div>
                </div>
                <!-- Selected -->
                <div class="overview-card">
                    <div class="overview-card__icon overview-card__icon--amber">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <div class="overview-card__info">
                        <span class="overview-card__number">
                            <?= number_format($selectedCandidates) ?>
                        </span>
                        <span class="overview-card__label">
                            Selected
                        </span>
                    </div>
                </div>
                <!-- Onboarded -->
                <div class="overview-card">
                    <div class="overview-card__icon overview-card__icon--cyan">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="overview-card__info">
                        <span class="overview-card__number">
                            <?= number_format($onboardedCandidates) ?>
                        </span>
                        <span class="overview-card__label">
                            Onboarded
                        </span>
                    </div>
                </div>
                <!-- Active -->
                <div class="overview-card">
                    <div class="overview-card__icon overview-card__icon--cyan">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="overview-card__info">
                        <span class="overview-card__number">
                            <?= number_format($activeCandidates) ?>
                        </span>
                        <span class="overview-card__label">
                            Active
                        </span>
                    </div>
                </div>
                <!-- Completed -->
                <div class="overview-card">
                    <div class="overview-card__icon overview-card__icon--primary">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="overview-card__info">
                        <span class="overview-card__number">
                            <?= number_format($completedCandidates) ?>
                        </span>
                        <span class="overview-card__label">
                            Completed
                        </span>
                    </div>
                </div>
                <!-- Withdrawn -->
                <div class="overview-card">
                    <div class="overview-card__icon overview-card__icon--amber">
                        <i class="fas fa-user-minus"></i>
                    </div>
                    <div class="overview-card__info">
                        <span class="overview-card__number">
                            <?= number_format($withdrawnCandidates) ?>
                        </span>
                        <span class="overview-card__label">
                            Withdrawn
                        </span>
                    </div>
                </div>
            </div>

            <!-- =================================================
                 RECRUITMENT PIPELINE
            ================================================== -->
            <div
                class="welcome-card"
                style="margin-top:2rem;"
            >
                <div class="welcome-card__content">
                    <h2 style="margin-bottom:1rem;">
                        <i class="fas fa-diagram-project"></i>
                        Recruitment Pipeline
                    </h2>

                    <div class="stage-summary">
                        <?php foreach (recruitment_stages() as $slug => $stage): ?>
                            <div class="stage-summary__item">
                                <div
                                    class="stage-summary__icon"
                                    style="background: <?= e($stage['colour']) ?>;"
                                >
                                    <i class="fas <?= e($stage['icon']) ?>"></i>
                                </div>
                                <div class="stage-summary__info">
                                    <span class="stage-summary__count">
                                        <?= number_format($stageStats[$slug] ?? 0) ?>
                                    </span>
                                    <span class="stage-summary__label">
                                        <?= e($stage['short']) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- =================================================
                 FILTERS
            ================================================== -->
            <div
                class="welcome-card"
                style="margin-top:2rem;"
            >
                <div class="welcome-card__content">
                    <h2 style="margin-bottom:1rem;">
                        <i class="fas fa-filter"></i>
                        Find Candidates
                    </h2>
                    <form
                        method="GET"
                        action="<?= url('programme/candidates.php') ?>"
                    >
                        <div class="candidate-filters">
                            <!-- Search -->
                            <div class="filter-group">
                                <label for="search">
                                    Search Candidate
                                </label>
                                <input
                                    type="text"
                                    id="search"
                                    name="search"
                                    class="filter-control"
                                    value="<?= e($search) ?>"
                                    placeholder="Name, email or phone..."
                                >
                            </div>
                            <!-- Programme -->
                            <div class="filter-group">
                                <label for="programme_id">
                                    Programme
                                </label>
                                <select
                                    id="programme_id"
                                    name="programme_id"
                                    class="filter-control"
                                >
                                    <option value="">
                                        All Programmes
                                    </option>
                                    <?php foreach ($programmes as $programme): ?>
                                        <option
                                            value="<?= (int) $programme['id'] ?>"
                                            <?= $programmeId === (int) $programme['id'] ? 'selected' : '' ?>
                                        >
                                            <?= e($programme['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <!-- Cohort -->
                            <div class="filter-group">
                                <label for="cohort_id">
                                    Cohort
                                </label>
                                <select
                                    id="cohort_id"
                                    name="cohort_id"
                                    class="filter-control"
                                >
                                    <option value="">
                                        All Cohorts
                                    </option>
                                    <?php foreach ($cohorts as $cohort): ?>
                                        <option
                                            value="<?= (int) $cohort['id'] ?>"
                                            <?= $cohortId === (int) $cohort['id'] ? 'selected' : '' ?>
                                        >
                                            <?= e($cohort['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <!-- Status -->
                            <div class="filter-group">
                                <label for="status">
                                    Status
                                </label>
                                <select
                                    id="status"
                                    name="status"
                                    class="filter-control"
                                >
                                    <option value="">
                                        All Statuses
                                    </option>
                                    <?php foreach ($allowedStatuses as $candidateStatus): ?>
                                        <option
                                            value="<?= e($candidateStatus) ?>"
                                            <?= $status === $candidateStatus ? 'selected' : '' ?>
                                        >
                                            <?= e(
                                                candidate_status_label(
                                                    $candidateStatus
                                                )
                                            ) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <!-- Actions -->
                            <div>
                                <button
                                    type="submit"
                                    class="sidebar__link"
                                    style="
                                        border:0;
                                        cursor:pointer;
                                        display:inline-flex;
                                        align-items:center;
                                        gap:.5rem;
                                    "
                                >
                                    <i class="fas fa-search"></i>
                                    Search
                                </button>
                            </div>
                        </div>
                    </form>
                    <?php if (
                        $search !== ''
                        || $programmeId > 0
                        || $cohortId > 0
                        || $status !== ''
                    ): ?>
                        <div style="margin-top:1rem;">
                            <a
                                href="<?= url('programme/candidates.php') ?>"
                                class="sidebar__link"
                                style="
                                    display:inline-flex;
                                    align-items:center;
                                    gap:.5rem;
                                "
                            >
                                <i class="fas fa-times"></i>
                                Clear Filters
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- =================================================
                 CANDIDATE LIST HEADER
            ================================================== -->
            <div
                class="welcome-card"
                style="margin-top:2rem;"
            >
                <div class="welcome-card__content">
                    <h2 style="margin-bottom:.5rem;">
                        <i class="fas fa-users"></i>
                        Candidate List
                    </h2>
                    <p>
                        <?= number_format(count($candidates)) ?>
                        candidate(s) found.
                    </p>
                </div>
            </div>

            <!-- =================================================
                 EMPTY STATE
            ================================================== -->
            <?php if (empty($candidates)): ?>
                <div
                    class="welcome-card"
                    style="margin-top:1rem;"
                >
                    <div class="welcome-card__content empty-state">
                        <i class="fas fa-users"></i>
                        <h3>
                            No Candidates Found
                        </h3>
                        <?php if (
                            $search !== ''
                            || $programmeId > 0
                            || $cohortId > 0
                            || $status !== ''
                        ): ?>
                            <p>
                                No candidates match the selected
                                filters.
                            </p>
                            <a
                                href="<?= url('programme/candidates.php') ?>"
                                class="sidebar__link"
                                style="
                                    display:inline-flex;
                                    align-items:center;
                                    gap:.5rem;
                                    margin-top:1rem;
                                "
                            >
                                <i class="fas fa-refresh"></i>
                                Clear Filters
                            </a>
                        <?php else: ?>
                            <p>
                                There are currently no candidates
                                assigned to your programmes.
                            </p>
                            <p style="color:#6b7280;font-size:.9rem;">
                                Candidates will appear here once they
                                are assigned to a cohort.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- =================================================
                     CANDIDATE TABLE
                ================================================== -->
                <div class="candidate-table-wrapper">
                    <table class="candidate-table">
                        <thead>
                            <tr>
                                <th>Candidate</th>
                                <th>Programme</th>
                                <th>Cohort</th>
                                <th>Recruitment Stage</th>
                                <th>Status</th>
                                <th>Selected</th>
                                <th>Onboarded</th>
                                <th>Completed</th>
                                <th style="text-align:left;padding:1rem;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($candidates as $candidate): ?>
                                <tr>
                                    <!-- Candidate -->
                                    <td>
                                        <div class="candidate-name">
                                            <?= e(
                                                trim(
                                                    ($candidate['first_name'] ?? '')
                                                    . ' '
                                                    . ($candidate['last_name'] ?? '')
                                                )
                                            ) ?>
                                        </div>
                                        <div class="candidate-contact">
                                            <?= e(
                                                $candidate['email'] ?? ''
                                            ) ?>
                                        </div>
                                        <?php if (
                                            !empty($candidate['phone'])
                                        ): ?>
                                            <div class="candidate-contact">
                                                <?= e(
                                                    $candidate['phone']
                                                ) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <!-- Programme -->
                                    <td>
                                        <strong>
                                            <?= e(
                                                $candidate['programme_name']
                                            ) ?>
                                        </strong>
                                        <?php if (
                                            !empty(
                                                $candidate['programme_type']
                                            )
                                        ): ?>
                                            <div class="candidate-contact">
                                                <?= e(
                                                    ucwords(
                                                        str_replace(
                                                            '_',
                                                            ' ',
                                                            $candidate[
                                                                'programme_type'
                                                            ]
                                                        )
                                                    )
                                                ) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <!-- Cohort -->
                                    <td>
                                        <?= e(
                                            $candidate['cohort_name']
                                        ) ?>
                                    </td>
                                    <!-- Recruitment Stage -->
                                    <td class="candidate-stage-cell">
                                        <?php
                                        $stageSlug = $candidate['recruitment_stage'] ?? 'submitted';
                                        $stageColour = $candidate['stage_colour'] ?? '#2563eb';
                                        $stagePercentage = (int) ($candidate['stage_percentage'] ?? 0);
                                        ?>
                                        <div class="candidate-stage-cell__head">
                                            <span class="candidate-stage-cell__label">
                                                <i
                                                    class="fas <?= e($candidate['stage_icon'] ?? 'fa-circle') ?>"
                                                    style="color: <?= e($stageColour) ?>;"
                                                ></i>
                                                <?= e($candidate['stage_label'] ?? 'Submitted') ?>
                                            </span>
                                            <span class="candidate-stage-cell__percentage">
                                                <?= $stagePercentage ?>%
                                            </span>
                                        </div>
                                        <div class="candidate-stage-cell__bar">
                                            <span
                                                class="candidate-stage-cell__fill"
                                                style="width: <?= $stagePercentage ?>%; background: <?= e($stageColour) ?>;"
                                            ></span>
                                        </div>
                                    </td>
                                    <!-- Status -->
                                    <td>
                                        <span
                                            class="status-badge <?= e(
                                                candidate_status_class(
                                                    $candidate[
                                                        'participation_status'
                                                    ]
                                                )
                                            ) ?>"
                                        >
                                            <?= e(
                                                candidate_status_label(
                                                    $candidate[
                                                        'participation_status'
                                                    ]
                                                )
                                            ) ?>
                                        </span>
                                    </td>
                                    <!-- Selected -->
                                    <td>
                                        <?php if (
                                            !empty(
                                                $candidate['selected_at']
                                            )
                                        ): ?>
                                            <?= e(
                                                date(
                                                    'd M Y',
                                                    strtotime(
                                                        $candidate[
                                                            'selected_at'
                                                        ]
                                                    )
                                                )
                                            ) ?>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <!-- Onboarded -->
                                    <td>
                                        <?php if (
                                            !empty(
                                                $candidate['onboarded_at']
                                            )
                                        ): ?>
                                            <?= e(
                                                date(
                                                    'd M Y',
                                                    strtotime(
                                                        $candidate[
                                                            'onboarded_at'
                                                        ]
                                                    )
                                                )
                                            ) ?>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <!-- Completed -->
                                    <td>
                                        <?php if (
                                            !empty(
                                                $candidate['completed_at']
                                            )
                                        ): ?>
                                            <?= e(
                                                date(
                                                    'd M Y',
                                                    strtotime(
                                                        $candidate[
                                                            'completed_at'
                                                        ]
                                                    )
                                                )
                                            ) ?>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <!-- Actions -->
                                    <td style="padding:1rem;">
                                        <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                                            <button
                                                type="button"
                                                class="sidebar__link candidate-progress-trigger"
                                                style="
                                                    border:0;
                                                    cursor:pointer;
                                                    display:inline-flex;
                                                    align-items:center;
                                                    gap:0.5rem;
                                                "
                                                data-candidate-name="<?= e(
                                                    trim(
                                                        ($candidate['first_name'] ?? '')
                                                        . ' '
                                                        . ($candidate['last_name'] ?? '')
                                                    )
                                                ) ?>"
                                                data-candidate-email="<?= e($candidate['email'] ?? '') ?>"
                                                data-candidate-programme="<?= e($candidate['programme_name'] ?? '') ?>"
                                                data-candidate-cohort="<?= e($candidate['cohort_name'] ?? '') ?>"
                                                data-candidate-stage="<?= e($candidate['recruitment_stage'] ?? 'submitted') ?>"
                                            >
                                                <i class="fas fa-chart-simple"></i>
                                                Track Progress
                                            </button>
                                            <a
                                                href="<?= url(
                                                    'programme/candidate_view.php?id=' .
                                                    (int) $candidate['user_id']
                                                ) ?>"
                                                class="sidebar__link"
                                                style="
                                                    display:inline-flex;
                                                    align-items:center;
                                                    gap:0.5rem;
                                                "
                                            >
                                                <i class="fas fa-eye"></i>
                                                View
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- =========================================================
     CANDIDATE PROGRESS MODAL
     ========================================================= -->
<div
    id="candidateProgressModal"
    class="candidate-progress-modal"
    aria-hidden="true"
>
    <div
        class="candidate-progress-modal__backdrop"
        data-progress-close
    ></div>

    <section
        class="candidate-progress-modal__dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="candidateProgressModalTitle"
    >
        <header class="candidate-progress-modal__header">
            <div>
                <span class="candidate-progress-modal__eyebrow">
                    Candidate Progress
                </span>
                <h2
                    id="candidateProgressModalTitle"
                    class="candidate-progress-modal__title"
                >
                    Recruitment Journey
                </h2>
            </div>

            <button
                type="button"
                class="candidate-progress-modal__close"
                data-progress-close
                aria-label="Close"
            >
                <i class="fas fa-times"></i>
            </button>
        </header>

        <div
            id="candidateProgressModalBody"
            class="candidate-progress-modal__body"
        ></div>

        <footer class="candidate-progress-modal__footer">
            <button
                type="button"
                class="pm-dashboard-modal__cancel"
                data-progress-close
            >
                Close
            </button>
        </footer>
    </section>
</div>

<script src="<?= url('js/programme_manager_enhancements.js') ?>?v=20260920"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const stages = <?= json_encode(
        recruitment_stages(),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?>;

    const order = <?= json_encode(
        recruitment_stage_order(),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?>;

    const modal = document.getElementById('candidateProgressModal');
    const modalBody = document.getElementById('candidateProgressModalBody');

    if (!modal || !modalBody) return;

    let activeTrigger = null;

    function escapeHtml(value) {
        const el = document.createElement('div');
        el.textContent = String(value ?? '');
        return el.innerHTML;
    }

    function initials(name) {
        const words = String(name || '').trim().split(/\s+/).filter(Boolean);
        if (!words.length) return '?';
        if (words.length === 1) return words[0].substring(0, 2).toUpperCase();
        return (words[0][0] + words[words.length - 1][0]).toUpperCase();
    }

    function buildTimeline(stageSlug) {
        const currentIndex = order.indexOf(stageSlug);
        const isRejected = stageSlug === 'rejected';

        return order.map(function (slug, index) {
            const stage = stages[slug];
            const isCurrent = slug === stageSlug;
            const isComplete = !isRejected && index < currentIndex;
            const isRejectedStep = isRejected && slug === 'rejected';

            let itemClass = 'candidate-progress-timeline__item';
            if (isComplete) itemClass += ' is-complete';
            if (isCurrent) itemClass += ' is-current';
            if (isRejectedStep) itemClass += ' is-rejected';

            let statusLabel = 'Pending';
            if (isComplete) statusLabel = 'Completed';
            if (isCurrent) statusLabel = 'Current Stage';
            if (isRejectedStep) statusLabel = 'Rejected';

            let statusClass = 'candidate-progress-timeline__status';
            if (isCurrent) statusClass += ' candidate-progress-timeline__status--current';
            if (isRejectedStep) statusClass += ' candidate-progress-timeline__status--rejected';

            return `
                <div class="${itemClass}" style="--stage-colour: ${escapeHtml(stage.colour)};">
                    <div class="candidate-progress-timeline__marker">
                        <i class="fas ${escapeHtml(stage.icon)}"></i>
                    </div>
                    <div class="candidate-progress-timeline__content">
                        <strong>${escapeHtml(stage.label)}</strong>
                        <span>${escapeHtml(stage.description)}</span>
                        <span class="${statusClass}">${statusLabel}</span>
                    </div>
                </div>
            `;
        }).join('');
    }

    function openModal(trigger) {
        activeTrigger = trigger;

        const name = trigger.getAttribute('data-candidate-name') || 'Candidate';
        const email = trigger.getAttribute('data-candidate-email') || '';
        const programme = trigger.getAttribute('data-candidate-programme') || '';
        const cohort = trigger.getAttribute('data-candidate-cohort') || '';
        const stageSlug = trigger.getAttribute('data-candidate-stage') || 'submitted';
        const stage = stages[stageSlug] || stages.submitted;

        const assignment = [programme, cohort].filter(Boolean).join(' · ');

        modalBody.innerHTML = `
            <div class="candidate-progress-summary">
                <div class="candidate-progress-summary__avatar">
                    ${escapeHtml(initials(name))}
                </div>
                <div class="candidate-progress-summary__info">
                    <strong>${escapeHtml(name)}</strong>
                    ${email ? `<span>${escapeHtml(email)}</span>` : ''}
                    ${assignment ? `<span>${escapeHtml(assignment)}</span>` : ''}
                </div>
                <span class="candidate-progress-summary__badge"
                      style="background: ${escapeHtml(stage.colour)};">
                    ${escapeHtml(stage.label)}
                </span>
            </div>
            <div class="candidate-progress-timeline">
                ${buildTimeline(stageSlug)}
            </div>
        `;

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('candidate-progress-modal-open');

        const closeBtn = modal.querySelector('.candidate-progress-modal__close');
        if (closeBtn) requestAnimationFrame(() => closeBtn.focus());
    }

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('candidate-progress-modal-open');

        if (activeTrigger && typeof activeTrigger.focus === 'function') {
            activeTrigger.focus();
        }
        activeTrigger = null;
    }

    document.addEventListener('click', function (event) {
        const close = event.target.closest('[data-progress-close]');
        if (close && modal.contains(close)) {
            event.preventDefault();
            closeModal();
            return;
        }

        const trigger = event.target.closest('.candidate-progress-trigger');
        if (!trigger) return;

        event.preventDefault();
        openModal(trigger);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            event.preventDefault();
            closeModal();
        }
    });
});
</script>

</body>
</html>