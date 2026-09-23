<?php

/**
 * ============================================================
 * INVESTHOOD IT - Programme Manager Cohorts
 * ============================================================
 * File: programme/cohorts.php
 * Role: Programme Manager
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('programme_manager');
require_once __DIR__ . '/_helpers.php';

$user       = current_user();
$flashes    = render_flashes();
$conn       = Database::getConnection();
$currentPage = 'cohorts';
$pageTitle   = 'Cohorts';

/* ------------------------------------------------------------------
 | Current Programme Manager
 * ------------------------------------------------------------------ */

$managerId = (int) ($user['id'] ?? $user['user_id'] ?? 0);

if ($managerId <= 0) {
    http_response_code(403);
    exit('Invalid Programme Manager account.');
}

/* ------------------------------------------------------------------
 | Filters
 * ------------------------------------------------------------------ */

$search = trim((string) ($_GET['search'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));

$allowedStatuses = ['draft', 'open', 'closed', 'active', 'completed', 'archived'];

if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
    $status = '';
}

/* ------------------------------------------------------------------
 | Detect cohorts.status column
 * ------------------------------------------------------------------ */

$cohortHasStatus = false;

if ($columnCheck = $conn->query("SHOW COLUMNS FROM cohorts LIKE 'status'")) {
    $cohortHasStatus = $columnCheck->num_rows > 0;
}

/* ------------------------------------------------------------------
 | Fetch Cohorts (scoped to the current manager)
 * ------------------------------------------------------------------ */

$cohorts            = [];
$cohortStatusSelect = $cohortHasStatus
    ? 'c.status AS cohort_status,'
    : 'p.status AS cohort_status,';

$sql = "
    SELECT
        c.id            AS cohort_id,
        c.name          AS cohort_name,
        c.programme_id,
        c.start_date,
        c.end_date,
        c.location,
        c.province,
        c.delivery_mode,
        c.max_capacity,
        {$cohortStatusSelect}
        p.name          AS programme_name,
        p.type          AS programme_type,
        p.status        AS programme_status,
        COUNT(DISTINCT CASE WHEN cp.status <> 'withdrawn' THEN cp.user_id END) AS candidate_count,
        COUNT(DISTINCT CASE WHEN cp.status IN ('selected','onboarded','active') THEN cp.user_id END) AS active_candidate_count,
        COUNT(DISTINCT CASE WHEN cp.status = 'completed' THEN cp.user_id END) AS completed_candidate_count,
        COUNT(DISTINCT CASE WHEN cp.status = 'withdrawn' THEN cp.user_id END) AS withdrawn_candidate_count
    FROM cohorts c
    INNER JOIN programmes p ON p.id = c.programme_id
    LEFT JOIN cohort_participants cp ON cp.cohort_id = c.id
    WHERE p.programme_manager_id = ?
";

$types  = 'i';
$params = [$managerId];

if ($search !== '') {
    $sql .= " AND (c.name LIKE ? OR p.name LIKE ? OR p.type LIKE ?)";
    $searchValue = '%' . $search . '%';
    $types  .= 'sss';
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}

if ($status !== '' && $cohortHasStatus) {
    $sql .= " AND c.status = ?";
    $types  .= 's';
    $params[] = $status;
}

$sql .= "
    GROUP BY c.id, c.name, c.programme_id, c.start_date, c.end_date,
             c.location, c.province, c.delivery_mode, c.max_capacity,
             p.name, p.type, p.status
";

if ($cohortHasStatus) {
    $sql .= ", c.status";
}

$sql .= " ORDER BY c.id DESC";

$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $candidateCount = (int) ($row['candidate_count'] ?? 0);
        $completedCount = (int) ($row['completed_candidate_count'] ?? 0);

        $progress = $candidateCount > 0
            ? (int) round(($completedCount / $candidateCount) * 100)
            : 0;

        $row['progress'] = max(0, min(100, $progress));

        if (empty($row['cohort_status'])) {
            $row['cohort_status'] = $row['programme_status'] ?? 'unknown';
        }

        $cohorts[] = $row;
    }

    $stmt->close();
}

/* ------------------------------------------------------------------
 | Aggregate statistics
 * ------------------------------------------------------------------ */

$totalCohorts            = count($cohorts);
$totalCandidates         = 0;
$totalActiveCandidates   = 0;
$totalCompletedCandidates = 0;
$totalWithdrawnCandidates = 0;
$activeCohorts           = 0;
$completedCohorts        = 0;

foreach ($cohorts as $cohort) {
    $totalCandidates          += (int) ($cohort['candidate_count'] ?? 0);
    $totalActiveCandidates    += (int) ($cohort['active_candidate_count'] ?? 0);
    $totalCompletedCandidates += (int) ($cohort['completed_candidate_count'] ?? 0);
    $totalWithdrawnCandidates += (int) ($cohort['withdrawn_candidate_count'] ?? 0);

    $cs = strtolower(trim((string) ($cohort['cohort_status'] ?? '')));

    if ($cs === 'active')    { $activeCohorts++; }
    if ($cs === 'completed') { $completedCohorts++; }
}

$overallProgress = $totalCandidates > 0
    ? (int) round(($totalCompletedCandidates / $totalCandidates) * 100)
    : 0;

$overallProgress = max(0, min(100, $overallProgress));

/* ------------------------------------------------------------------
 | Build JSON payload for the modal
 * ------------------------------------------------------------------ */

function pm_cohorts_json(array $data): string
{
    $json = json_encode(
        $data,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
    );

    if ($json === false) {
        $json = '{}';
    }

    return $json;   // <-- NO htmlspecialchars()
}

$cohortModalData = [];

foreach ($cohorts as $cohort) {
    $cid = (int) ($cohort['cohort_id'] ?? 0);

    $cohortModalData[$cid] = [
        'cohort_id'                => $cid,
        'cohort_name'              => (string) ($cohort['cohort_name'] ?? 'Cohort'),
        'programme_id'             => (int)    ($cohort['programme_id'] ?? 0),
        'programme_name'           => (string) ($cohort['programme_name'] ?? ''),
        'programme_type'           => (string) ($cohort['programme_type'] ?? ''),
        'programme_status'         => (string) ($cohort['programme_status'] ?? ''),
        'cohort_status'            => (string) ($cohort['cohort_status'] ?? 'unknown'),
        'location'                 => (string) ($cohort['location'] ?? ''),
        'province'                 => (string) ($cohort['province'] ?? ''),
        'delivery_mode'            => (string) ($cohort['delivery_mode'] ?? ''),
        'max_capacity'             => (int)    ($cohort['max_capacity'] ?? 0),
        'start_date'               => (string) ($cohort['start_date'] ?? ''),
        'end_date'                 => (string) ($cohort['end_date'] ?? ''),
        'progress'                 => (int)    ($cohort['progress'] ?? 0),
        'candidate_count'          => (int)    ($cohort['candidate_count'] ?? 0),
        'active_candidate_count'   => (int)    ($cohort['active_candidate_count'] ?? 0),
        'completed_candidate_count'=> (int)    ($cohort['completed_candidate_count'] ?? 0),
        'withdrawn_candidate_count'=> (int)    ($cohort['withdrawn_candidate_count'] ?? 0),
        'view_url'                 => url('programme/cohort_view.php?id=' . $cid),
    ];
}

$cohortModalJson = pm_cohorts_json($cohortModalData);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cohorts | Programme Manager | Investhood IT</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">

    <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
    <link rel="stylesheet" href="<?= url('css/programme_manager_enhancements.css') ?>?v=20260920">

    <style>
        /* ---------- Clickable cohort card ---------- */
        .pm-cohort-card {
            cursor: pointer;
            position: relative;
            transition: transform .18s ease, box-shadow .18s ease;
        }
        .pm-cohort-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,0,0,.08);
        }
        .pm-cohort-card:focus-visible {
            outline: 3px solid rgba(37,99,235,.25);
            outline-offset: 3px;
        }
        .pm-cohort-card__hint {
            margin-top: .75rem;
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            font-size: .75rem;
            font-weight: 600;
            color: #2563eb;
        }

        /* ---------- Modal ---------- */
        .pm-cohort-modal {
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
            box-sizing: border-box;
            transition: opacity .2s ease, visibility .2s ease;
        }
        .pm-cohort-modal.is-open {
            visibility: visible;
            opacity: 1;
            pointer-events: auto;
        }
        .pm-cohort-modal__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15,23,42,.72);
            backdrop-filter: blur(5px);
        }
        .pm-cohort-modal__dialog {
            position: relative;
            z-index: 2;
            width: min(760px, 100%);
            max-height: calc(100vh - 40px);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: #ffffff;
            border: 1px solid #e4e7ec;
            border-radius: 20px;
            box-shadow: 0 30px 90px rgba(0,0,0,.35);
            transform: translateY(15px) scale(.98);
            transition: transform .2s ease;
        }
        .pm-cohort-modal.is-open .pm-cohort-modal__dialog {
            transform: translateY(0) scale(1);
        }
        .pm-cohort-modal__header {
            padding: 20px 22px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            border-bottom: 1px solid #e4e7ec;
        }
        .pm-cohort-modal__eyebrow {
            display: block;
            margin-bottom: 4px;
            color: #2563eb;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .08em;
        }
        .pm-cohort-modal__title {
            margin: 0;
            color: #101828;
            font-size: 20px;
            font-weight: 800;
        }
        .pm-cohort-modal__close {
            width: 38px; height: 38px;
            flex: 0 0 38px;
            display: grid;
            place-items: center;
            border: 1px solid #e4e7ec;
            border-radius: 10px;
            background: #f8fafc;
            color: #475467;
            cursor: pointer;
        }
        .pm-cohort-modal__close:hover {
            background: #eff6ff;
            color: #2563eb;
        }
        .pm-cohort-modal__body {
            padding: 20px;
            overflow-y: auto;
        }
        .pm-cohort-summary {
            margin-bottom: 18px;
            padding: 16px;
            border: 1px solid #e4e7ec;
            border-radius: 14px;
            background: #f8fafc;
        }
        .pm-cohort-summary__label {
            color: #667085;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
        }
        .pm-cohort-summary__value {
            display: block;
            margin-top: 4px;
            color: #101828;
            font-size: 14px;
            font-weight: 700;
        }
        .pm-cohort-summary__meta {
            margin-top: 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .pm-cohort-summary__pill {
            padding: 5px 10px;
            border-radius: 999px;
            background: #eff6ff;
            color: #2563eb;
            font-size: 10px;
            font-weight: 700;
            text-transform: capitalize;
        }
        .pm-cohort-meta-grid {
            margin-bottom: 18px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
        }
        .pm-cohort-meta {
            padding: 12px 14px;
            border: 1px solid #e4e7ec;
            border-radius: 12px;
            background: #ffffff;
        }
        .pm-cohort-meta__label {
            display: block;
            color: #667085;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
        }
        .pm-cohort-meta__value {
            display: block;
            margin-top: 4px;
            color: #101828;
            font-size: 13px;
            font-weight: 700;
        }
        .pm-cohort-stats {
            margin-bottom: 18px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 10px;
        }
        .pm-cohort-stat {
            padding: 13px 14px;
            border: 1px solid #e4e7ec;
            border-radius: 12px;
            background: #ffffff;
        }
        .pm-cohort-stat__value {
            display: block;
            color: #101828;
            font-size: 20px;
            font-weight: 800;
        }
        .pm-cohort-stat__label {
            display: block;
            margin-top: 3px;
            color: #667085;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .05em;
        }
        .pm-cohort-progress {
            padding: 15px;
            border: 1px solid #e4e7ec;
            border-radius: 12px;
            background: #ffffff;
        }
        .pm-cohort-progress__head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 15px;
        }
        .pm-cohort-progress__info strong {
            display: block;
            color: #101828;
            font-size: 13px;
            font-weight: 700;
        }
        .pm-cohort-progress__info span {
            display: block;
            margin-top: 4px;
            color: #667085;
            font-size: 11px;
        }
        .pm-cohort-progress__percentage {
            flex: 0 0 auto;
            color: #2563eb;
            font-size: 16px;
            font-weight: 800;
        }
        .pm-cohort-progress__track {
            width: 100%;
            height: 7px;
            margin-top: 13px;
            overflow: hidden;
            border-radius: 999px;
            background: #e5e7eb;
        }
        .pm-cohort-progress__bar {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: #2563eb;
        }
        .pm-cohort-modal__footer {
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            border-top: 1px solid #e4e7ec;
        }
        .pm-cohort-modal__cancel,
        .pm-cohort-modal__details {
            min-height: 40px;
            padding: 0 15px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }
        .pm-cohort-modal__cancel {
            border: 1px solid #d0d5dd;
            background: #ffffff;
            color: #344054;
        }
        .pm-cohort-modal__details {
            border: 1px solid #2563eb;
            background: #2563eb;
            color: #ffffff;
            text-decoration: none;
        }
        .pm-cohort-modal__details:hover {
            background: #1d4ed8;
            color: #ffffff;
        }
        body.pm-cohort-modal-open { overflow: hidden !important; }

        @media (max-width: 620px) {
            .pm-cohort-modal { padding: 12px; }
            .pm-cohort-modal__dialog { max-height: calc(100vh - 24px); border-radius: 16px; }
            .pm-cohort-modal__header { padding: 16px; }
            .pm-cohort-modal__body { padding: 15px; }
            .pm-cohort-modal__footer { padding: 14px 15px; flex-direction: column-reverse; }
            .pm-cohort-modal__cancel,
            .pm-cohort-modal__details { width: 100%; box-sizing: border-box; }
        }
    </style>
</head>

<body class="dashboard-page">
<div class="dashboard">

    <?php require __DIR__ . '/sidebar.php'; ?>

    <main class="dashboard__main">

        <?php require __DIR__ . '/navbar.php'; ?>

        <div class="dash-content">

            <?= $flashes ?>

            <!-- Welcome -->
            <div class="welcome-card">
                <div class="welcome-card__bg"></div>
                <div class="welcome-card__content">
                    <h1 class="welcome-card__greeting">
                        Programme <span class="text-gradient">Cohorts</span>
                    </h1>
                    <p>View and monitor cohorts belonging to your assigned programmes. Click any cohort card for a quick overview.</p>
                </div>
            </div>

            <!-- Summary -->
            <div class="overview-grid" style="margin-top:2rem;">
                <div class="overview-card">
                    <div class="overview-card__icon overview-card__icon--primary"><i class="fas fa-users"></i></div>
                    <div class="overview-card__info">
                        <span class="overview-card__number"><?= number_format($totalCohorts) ?></span>
                        <span class="overview-card__label">Total Cohorts</span>
                    </div>
                </div>
                <div class="overview-card">
                    <div class="overview-card__icon overview-card__icon--cyan"><i class="fas fa-play-circle"></i></div>
                    <div class="overview-card__info">
                        <span class="overview-card__number"><?= number_format($activeCohorts) ?></span>
                        <span class="overview-card__label">Active Cohorts</span>
                    </div>
                </div>
                <div class="overview-card">
                    <div class="overview-card__icon overview-card__icon--primary"><i class="fas fa-check-circle"></i></div>
                    <div class="overview-card__info">
                        <span class="overview-card__number"><?= number_format($completedCohorts) ?></span>
                        <span class="overview-card__label">Completed Cohorts</span>
                    </div>
                </div>
                <div class="overview-card">
                    <div class="overview-card__icon overview-card__icon--amber"><i class="fas fa-user-graduate"></i></div>
                    <div class="overview-card__info">
                        <span class="overview-card__number"><?= number_format($totalCandidates) ?></span>
                        <span class="overview-card__label">Candidates</span>
                    </div>
                </div>
            </div>

            <!-- Overall progress -->
            <div class="welcome-card" style="margin-top:2rem;">
                <div class="welcome-card__content">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
                        <div>
                            <h2 style="margin-bottom:0.35rem;">Overall Cohort Progress</h2>
                            <p style="margin:0;">Completed candidates across your managed cohorts.</p>
                        </div>
                        <strong style="font-size:1.5rem;"><?= (int) $overallProgress ?>%</strong>
                    </div>
                    <div style="width:100%;height:10px;background:#e5e7eb;border-radius:999px;overflow:hidden;margin-top:1rem;">
                        <div style="width:<?= (int) $overallProgress ?>%;height:100%;background:#1a56db;border-radius:999px;"></div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="welcome-card" style="margin-top:2rem;">
                <div class="welcome-card__content">
                    <h2 style="margin-bottom:1rem;"><i class="fas fa-filter"></i> Find Cohorts</h2>
                    <form method="GET" action="<?= url('programme/cohorts.php') ?>" style="display:flex;flex-wrap:wrap;gap:1rem;align-items:end;">
                        <div style="flex:1;min-width:250px;">
                            <label for="search" style="display:block;margin-bottom:0.4rem;font-weight:600;">Search</label>
                            <input type="text" id="search" name="search" value="<?= e($search) ?>" placeholder="Search cohorts or programmes..." style="width:100%;padding:0.75rem;border:1px solid #d1d5db;border-radius:8px;">
                        </div>
                        <?php if ($cohortHasStatus): ?>
                            <div style="min-width:200px;">
                                <label for="status" style="display:block;margin-bottom:0.4rem;font-weight:600;">Status</label>
                                <select id="status" name="status" style="width:100%;padding:0.75rem;border:1px solid #d1d5db;border-radius:8px;">
                                    <option value="">All Statuses</option>
                                    <?php foreach ($allowedStatuses as $s): ?>
                                        <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>>
                                            <?= e(ucwords(str_replace('_', ' ', $s))) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <div><button type="submit" class="sidebar__link" style="border:0;cursor:pointer;display:inline-flex;align-items:center;gap:0.5rem;"><i class="fas fa-search"></i> Search</button></div>
                        <?php if ($search !== '' || $status !== ''): ?>
                            <div><a href="<?= url('programme/cohorts.php') ?>" class="sidebar__link" style="display:inline-flex;align-items:center;gap:0.5rem;"><i class="fas fa-times"></i> Reset</a></div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Cohort list -->
            <div class="welcome-card" style="margin-top:2rem;">
                <div class="welcome-card__content">
                    <h2 style="margin-bottom:0.5rem;"><i class="fas fa-layer-group"></i> Cohort List</h2>
                    <p>Cohorts currently associated with your programmes. Click any cohort card for a quick overview.</p>
                </div>
            </div>

            <?php if (empty($cohorts)): ?>
                <div class="welcome-card" style="margin-top:1rem;">
                    <div class="welcome-card__content">
                        <h3><i class="fas fa-users-slash"></i> No Cohorts Found</h3>
                        <p><?= ($search !== '' || $status !== '') ? 'No cohorts match the selected search criteria.' : 'There are currently no cohorts associated with your programmes.' ?></p>
                    </div>
                </div>
            <?php else: ?>
                <div class="overview-grid" style="margin-top:1rem;">
                    <?php foreach ($cohorts as $cohort): ?>
                        <?php
                        $cohortId          = (int) ($cohort['cohort_id'] ?? 0);
                        $progressWidth     = max(0, min(100, (int) ($cohort['progress'] ?? 0)));
                        $candidateCount    = (int) ($cohort['candidate_count'] ?? 0);
                        $activeCandidate   = (int) ($cohort['active_candidate_count'] ?? 0);
                        $completedCount    = (int) ($cohort['completed_candidate_count'] ?? 0);
                        $withdrawnCount    = (int) ($cohort['withdrawn_candidate_count'] ?? 0);
                        $cohortStatus      = strtolower(trim((string) ($cohort['cohort_status'] ?? 'unknown')));
                        ?>

                        <div
                            class="overview-card pm-cohort-card"
                            role="button"
                            tabindex="0"
                            aria-haspopup="dialog"
                            aria-label="View details for <?= e($cohort['cohort_name']) ?>"
                            data-pm-cohort-id="<?= $cohortId ?>"
                        >
                            <div class="overview-card__icon overview-card__icon--primary">
                                <i class="fas fa-users"></i>
                            </div>

                            <div class="overview-card__info">
                                <span class="overview-card__label" style="font-size:1rem;font-weight:700;">
                                    <?= e($cohort['cohort_name']) ?>
                                </span>

                                <span class="overview-card__label" style="margin-top:0.5rem;">
                                    <i class="fas fa-graduation-cap"></i>
                                    <?= e($cohort['programme_name']) ?>
                                </span>

                                <span class="overview-card__label" style="margin-top:0.35rem;">
                                    <i class="fas fa-tag"></i>
                                    <?= e(ucwords(str_replace('_', ' ', (string) ($cohort['programme_type'] ?? '')))) ?>
                                </span>

                                <span class="overview-card__label" style="margin-top:0.35rem;">
                                    <i class="fas fa-circle"></i>
                                    Status: <?= e(ucwords(str_replace('_', ' ', $cohortStatus))) ?>
                                </span>

                                <span class="overview-card__label" style="margin-top:0.6rem;">
                                    <i class="fas fa-user-graduate"></i>
                                    <?= number_format($candidateCount) ?> Candidates
                                </span>

                                <span class="overview-card__label" style="margin-top:0.35rem;">Active: <?= number_format($activeCandidate) ?></span>
                                <span class="overview-card__label" style="margin-top:0.35rem;">Completed: <?= number_format($completedCount) ?></span>
                                <span class="overview-card__label" style="margin-top:0.35rem;">Withdrawn: <?= number_format($withdrawnCount) ?></span>

                                <span class="overview-card__number" style="font-size:1.5rem;margin-top:0.75rem;">
                                    <?= (int) $progressWidth ?>%
                                </span>
                                <span class="overview-card__label">Completion Progress</span>

                                <div style="width:100%;height:8px;background:#e5e7eb;border-radius:999px;overflow:hidden;margin-top:0.5rem;">
                                    <div style="width:<?= (int) $progressWidth ?>%;height:100%;background:#1a56db;border-radius:999px;"></div>
                                </div>

                                <span class="pm-cohort-card__hint">
                                    <i class="fas fa-mouse-pointer"></i>
                                    Click for quick overview
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </main>
</div>

<!-- ==================== COHORT MODAL ==================== -->
<div id="pmCohortModal" class="pm-cohort-modal" aria-hidden="true">
    <div class="pm-cohort-modal__backdrop" data-pm-cohort-close></div>

    <section class="pm-cohort-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="pmCohortModalTitle">
        <header class="pm-cohort-modal__header">
            <div>
                <span class="pm-cohort-modal__eyebrow">Cohort Overview</span>
                <h2 id="pmCohortModalTitle" class="pm-cohort-modal__title">Cohort Details</h2>
            </div>
            <button type="button" class="pm-cohort-modal__close" data-pm-cohort-close aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
        </header>

        <div id="pmCohortModalBody" class="pm-cohort-modal__body"></div>

        <footer class="pm-cohort-modal__footer">
            <button type="button" class="pm-cohort-modal__cancel" data-pm-cohort-close>Close</button>
            <a id="pmCohortModalDetails" href="#" class="pm-cohort-modal__details">
                View Full Cohort <i class="fas fa-arrow-right"></i>
            </a>
        </footer>
    </section>
</div>

<!-- Cohort data (JSON) -->
<script type="application/json" id="pmCohortModalData"><?= $cohortModalJson ?></script>

<!-- Programme Manager base script -->
<script src="<?= url('js/programme_manager_enhancements.js') ?>?v=20260920"></script>

<!-- Cohort modal logic -->
<script>
(function () {
    'use strict';

    function init() {
        var modal        = document.getElementById('pmCohortModal');
        var modalTitle   = document.getElementById('pmCohortModalTitle');
        var modalBody    = document.getElementById('pmCohortModalBody');
        var detailsBtn   = document.getElementById('pmCohortModalDetails');
        var dataTag      = document.getElementById('pmCohortModalData');

        if (!modal || !modalTitle || !modalBody || !detailsBtn || !dataTag) {
            console.warn('[pm-cohort-modal] Missing required DOM nodes; modal disabled.');
            return;
        }

        var COHORT_DATA = {};
        try {
            COHORT_DATA = JSON.parse(dataTag.textContent || '{}') || {};
        } catch (err) {
            console.error('[pm-cohort-modal] Failed to parse cohort data:', err);
            COHORT_DATA = {};
        }

        var activeCard = null;

        function escapeHtml(v) {
            var div = document.createElement('div');
            div.textContent = String(v == null ? '' : v);
            return div.innerHTML;
        }

        function titleCase(v) {
            return String(v || '')
                .replace(/_/g, ' ')
                .replace(/\b\w/g, function (c) { return c.toUpperCase(); });
        }

        function clamp(n, min, max) {
            n = Number(n);
            if (!isFinite(n)) n = min;
            return Math.max(min, Math.min(max, n));
        }

        function formatDate(v) {
            if (!v) return '—';
            var d = new Date(v);
            if (isNaN(d.getTime())) return String(v);
            return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
        }

        function render(c) {
            var progress  = clamp(c.progress, 0, 100);
            var total     = Number(c.candidate_count || 0);
            var active    = Number(c.active_candidate_count || 0);
            var completed = Number(c.completed_candidate_count || 0);
            var withdrawn = Number(c.withdrawn_candidate_count || 0);
            var capacity  = Number(c.max_capacity || 0);

            var pills = '';
            if (c.programme_type) {
                pills += '<span class="pm-cohort-summary__pill">' + escapeHtml(titleCase(c.programme_type)) + '</span>';
            }
            if (c.cohort_status) {
                pills += '<span class="pm-cohort-summary__pill">' + escapeHtml(titleCase(c.cohort_status)) + '</span>';
            }
            if (c.delivery_mode) {
                pills += '<span class="pm-cohort-summary__pill">' + escapeHtml(titleCase(c.delivery_mode)) + '</span>';
            }

            function meta(label, value) {
                return '<div class="pm-cohort-meta">'
                    + '<span class="pm-cohort-meta__label">' + escapeHtml(label) + '</span>'
                    + '<span class="pm-cohort-meta__value">' + escapeHtml(value || '—') + '</span>'
                    + '</div>';
            }

            var location = [c.location, c.province ? titleCase(c.province) : '']
                .filter(Boolean).join(', ') || '—';

            modalTitle.textContent = c.cohort_name || 'Cohort';

            modalBody.innerHTML = ''
                + '<div class="pm-cohort-summary">'
                +   '<span class="pm-cohort-summary__label">Programme</span>'
                +   '<span class="pm-cohort-summary__value">' + escapeHtml(c.programme_name || '—') + '</span>'
                +   '<div class="pm-cohort-summary__meta">' + pills + '</div>'
                + '</div>'
                + '<div class="pm-cohort-meta-grid">'
                +   meta('Start date', formatDate(c.start_date))
                +   meta('End date',   formatDate(c.end_date))
                +   meta('Location',   location)
                +   meta('Capacity',   capacity > 0 ? capacity : '—')
                + '</div>'
                + '<div class="pm-cohort-stats">'
                +   '<div class="pm-cohort-stat"><span class="pm-cohort-stat__value">' + total     + '</span><span class="pm-cohort-stat__label">Candidates</span></div>'
                +   '<div class="pm-cohort-stat"><span class="pm-cohort-stat__value">' + active    + '</span><span class="pm-cohort-stat__label">Active</span></div>'
                +   '<div class="pm-cohort-stat"><span class="pm-cohort-stat__value">' + completed + '</span><span class="pm-cohort-stat__label">Completed</span></div>'
                +   '<div class="pm-cohort-stat"><span class="pm-cohort-stat__value">' + withdrawn + '</span><span class="pm-cohort-stat__label">Withdrawn</span></div>'
                + '</div>'
                + '<div class="pm-cohort-progress">'
                +   '<div class="pm-cohort-progress__head">'
                +     '<div class="pm-cohort-progress__info">'
                +       '<strong>Completion Progress</strong>'
                +       '<span>' + completed + ' of ' + total + ' candidates completed</span>'
                +     '</div>'
                +     '<strong class="pm-cohort-progress__percentage">' + progress + '%</strong>'
                +   '</div>'
                +   '<div class="pm-cohort-progress__track">'
                +     '<span class="pm-cohort-progress__bar" style="width:' + progress + '%"></span>'
                +   '</div>'
                + '</div>';

            detailsBtn.href = c.view_url || '#';
        }

        function openModal(card) {
            var id = Number(card.getAttribute('data-pm-cohort-id') || 0);
            var c  = COHORT_DATA[String(id)];
            if (!c) return;

            activeCard = card;
            render(c);

            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('pm-cohort-modal-open');

            var closeBtn = modal.querySelector('.pm-cohort-modal__close');
            if (closeBtn) {
                requestAnimationFrame(function () { closeBtn.focus(); });
            }
        }

        function closeModal() {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('pm-cohort-modal-open');

            if (activeCard && typeof activeCard.focus === 'function') {
                activeCard.focus();
            }
            activeCard = null;
        }

        /* -------- Click handler -------- */
        document.addEventListener('click', function (e) {
            // Close trigger?
            var closeEl = e.target.closest('[data-pm-cohort-close]');
            if (closeEl && modal.contains(closeEl)) {
                e.preventDefault();
                e.stopPropagation();
                closeModal();
                return;
            }

            // Clicked a cohort card?
            var card = e.target.closest('.pm-cohort-card');
            if (!card) return;

            e.preventDefault();
            e.stopPropagation();
            openModal(card);
        }, true);

        /* -------- Keyboard -------- */
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.classList.contains('is-open')) {
                e.preventDefault();
                closeModal();
                return;
            }

            if ((e.key === 'Enter' || e.key === ' ')) {
                var card = e.target.closest && e.target.closest('.pm-cohort-card');
                if (card) {
                    e.preventDefault();
                    openModal(card);
                }
            }
        });

        /* Debug (remove after confirming) */
        console.log('[pm-cohort-modal] Ready. Cards:', document.querySelectorAll('.pm-cohort-card').length, 'Records:', Object.keys(COHORT_DATA).length);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>

</body>
</html>