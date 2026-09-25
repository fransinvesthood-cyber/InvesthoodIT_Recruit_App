<?php

/**
 * ============================================================
 * INVESTHOOD IT - Programme Manager Cohorts
 * ============================================================
 * File: programme/cohorts.php
 * Role: Programme Manager
 *
 * Displays cohorts belonging to programmes assigned to the
 * currently logged-in Programme Manager.
 *
 * Features:
 *   - Cohort cards (clickable → open modal)
 *   - Supervisor Oversight modal (nested)
 *   - Search + status + supervisor filters
 * ============================================================
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('programme_manager');
require_once __DIR__ . '/_helpers.php';

$user        = current_user();
$flashes     = render_flashes();
$conn        = Database::getConnection();
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

$search           = trim((string) ($_GET['search'] ?? ''));
$status           = trim((string) ($_GET['status'] ?? ''));
$supervisorFilter = (int)  ($_GET['supervisor_id'] ?? 0);

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
        c.supervisor_id,
        sup.first_name  AS supervisor_first_name,
        sup.last_name   AS supervisor_last_name,
        sup.email       AS supervisor_email,
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
    LEFT JOIN users sup     ON sup.id = c.supervisor_id
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

if ($supervisorFilter > 0) {
    $sql .= " AND c.supervisor_id = ?";
    $types  .= 'i';
    $params[] = $supervisorFilter;
}

$sql .= "
    GROUP BY
        c.id, c.name, c.programme_id, c.start_date, c.end_date,
        c.location, c.province, c.delivery_mode, c.max_capacity,
        c.supervisor_id, sup.first_name, sup.last_name, sup.email,
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

$totalCohorts             = count($cohorts);
$totalCandidates          = 0;
$totalActiveCandidates    = 0;
$totalCompletedCandidates = 0;
$totalWithdrawnCandidates = 0;
$activeCohorts            = 0;
$completedCohorts         = 0;

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
 | JSON helper (NO htmlspecialchars — safe for <script type="application/json">)
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

    return $json;
}

/* ------------------------------------------------------------------
 | Build JSON payload for the cohort modal
 * ------------------------------------------------------------------ */

$cohortModalData = [];

foreach ($cohorts as $cohort) {
    $cid = (int) ($cohort['cohort_id'] ?? 0);

    $supervisorId    = (int)    ($cohort['supervisor_id'] ?? 0);
    $supervisorName  = trim(
        (string) ($cohort['supervisor_first_name'] ?? '')
        . ' ' .
        (string) ($cohort['supervisor_last_name'] ?? '')
    );

    $cohortModalData[$cid] = [
        'cohort_id'                 => $cid,
        'cohort_name'               => (string) ($cohort['cohort_name'] ?? 'Cohort'),
        'programme_id'              => (int)    ($cohort['programme_id'] ?? 0),
        'programme_name'            => (string) ($cohort['programme_name'] ?? ''),
        'programme_type'            => (string) ($cohort['programme_type'] ?? ''),
        'programme_status'          => (string) ($cohort['programme_status'] ?? ''),
        'cohort_status'             => (string) ($cohort['cohort_status'] ?? 'unknown'),
        'location'                  => (string) ($cohort['location'] ?? ''),
        'province'                  => (string) ($cohort['province'] ?? ''),
        'delivery_mode'             => (string) ($cohort['delivery_mode'] ?? ''),
        'max_capacity'              => (int)    ($cohort['max_capacity'] ?? 0),
        'start_date'                => (string) ($cohort['start_date'] ?? ''),
        'end_date'                  => (string) ($cohort['end_date'] ?? ''),
        'progress'                  => (int)    ($cohort['progress'] ?? 0),
        'candidate_count'           => (int)    ($cohort['candidate_count'] ?? 0),
        'active_candidate_count'    => (int)    ($cohort['active_candidate_count'] ?? 0),
        'completed_candidate_count' => (int)    ($cohort['completed_candidate_count'] ?? 0),
        'withdrawn_candidate_count' => (int)    ($cohort['withdrawn_candidate_count'] ?? 0),
        'supervisor_id'             => $supervisorId,
        'supervisor_name'           => $supervisorName,
        'supervisor_email'          => (string) ($cohort['supervisor_email'] ?? ''),
        'view_url'                  => url('programme/cohort_view.php?id=' . $cid),
    ];
}

$cohortModalJson = pm_cohorts_json($cohortModalData);

/* ------------------------------------------------------------------
 | Build JSON payload for the Supervisor Oversight modal
 |
 | For every supervisor assigned to at least one of this manager's
 | cohorts, gather their info, their cohorts, and their candidates.
 |
 | All queries scoped by programmes.programme_manager_id.
 * ------------------------------------------------------------------ */

$supervisorOversight = [];

$supSql = "
    SELECT DISTINCT
        u.id            AS supervisor_id,
        u.first_name,
        u.last_name,
        u.email,
        u.phone
    FROM users u
    INNER JOIN roles r      ON r.id = u.role_id
    INNER JOIN cohorts c    ON c.supervisor_id = u.id
    INNER JOIN programmes p ON p.id = c.programme_id
    WHERE r.slug = 'supervisor'
      AND p.programme_manager_id = ?
    ORDER BY u.first_name ASC, u.last_name ASC
";

$supStmt = $conn->prepare($supSql);

if ($supStmt) {
    $supStmt->bind_param('i', $managerId);
    $supStmt->execute();
    $supRes = $supStmt->get_result();

    while ($sup = $supRes->fetch_assoc()) {
        $sid = (int) $sup['supervisor_id'];

        /* ---- Cohorts supervised by this supervisor ---- */
        $cohortsForSup = [];

        $cSql = "
            SELECT
                c.id            AS cohort_id,
                c.name          AS cohort_name,
                c.start_date,
                c.end_date,
                c.status        AS cohort_status,
                p.name          AS programme_name,
                COUNT(DISTINCT CASE WHEN cp.status <> 'withdrawn' THEN cp.user_id END) AS candidate_count,
                COUNT(DISTINCT CASE WHEN cp.status = 'active'    THEN cp.user_id END) AS active_candidate_count,
                COUNT(DISTINCT CASE WHEN cp.status = 'completed' THEN cp.user_id END) AS completed_candidate_count,
                COUNT(DISTINCT CASE WHEN cp.status = 'withdrawn' THEN cp.user_id END) AS withdrawn_candidate_count
            FROM cohorts c
            INNER JOIN programmes p ON p.id = c.programme_id
            LEFT JOIN cohort_participants cp ON cp.cohort_id = c.id
            WHERE c.supervisor_id = ?
              AND p.programme_manager_id = ?
            GROUP BY c.id, c.name, c.start_date, c.end_date, c.status, p.name
            ORDER BY c.name ASC
        ";

        $cStmt = $conn->prepare($cSql);

        if ($cStmt) {
            $cStmt->bind_param('ii', $sid, $managerId);
            $cStmt->execute();
            $cRes = $cStmt->get_result();

            while ($cRow = $cRes->fetch_assoc()) {
                $cc = (int) ($cRow['candidate_count'] ?? 0);
                $cp = (int) ($cRow['completed_candidate_count'] ?? 0);

                $cRow['progress'] = $cc > 0
                    ? (int) round(($cp / $cc) * 100)
                    : 0;

                $cohortsForSup[] = [
                    'cohort_id'                 => (int)    ($cRow['cohort_id'] ?? 0),
                    'cohort_name'               => (string) ($cRow['cohort_name'] ?? ''),
                    'programme_name'            => (string) ($cRow['programme_name'] ?? ''),
                    'cohort_status'             => (string) ($cRow['cohort_status'] ?? ''),
                    'start_date'                => (string) ($cRow['start_date'] ?? ''),
                    'end_date'                  => (string) ($cRow['end_date'] ?? ''),
                    'progress'                  => (int)    $cRow['progress'],
                    'candidate_count'           => $cc,
                    'active_candidate_count'    => (int) ($cRow['active_candidate_count'] ?? 0),
                    'completed_candidate_count' => $cp,
                    'withdrawn_candidate_count' => (int) ($cRow['withdrawn_candidate_count'] ?? 0),
                    'view_url'                  => url('programme/cohort_view.php?id=' . (int) $cRow['cohort_id']),
                ];
            }

            $cStmt->close();
        }

        /* ---- Candidates under this supervisor ---- */
        $candsForSup = [];

        $pSql = "
            SELECT
                u.id            AS user_id,
                u.first_name,
                u.last_name,
                u.email,
                u.phone,
                cp.status       AS participation_status,
                cp.onboarded_at,
                cp.completed_at,
                c.id            AS cohort_id,
                c.name          AS cohort_name,
                p.name          AS programme_name
            FROM cohort_participants cp
            INNER JOIN users u      ON u.id = cp.user_id
            INNER JOIN cohorts c    ON c.id = cp.cohort_id
            INNER JOIN programmes p ON p.id = c.programme_id
            WHERE c.supervisor_id = ?
              AND p.programme_manager_id = ?
              AND cp.status <> 'withdrawn'
            ORDER BY u.first_name ASC, u.last_name ASC, c.name ASC
        ";

        $pStmt = $conn->prepare($pSql);

        if ($pStmt) {
            $pStmt->bind_param('ii', $sid, $managerId);
            $pStmt->execute();
            $pRes = $pStmt->get_result();

            while ($pRow = $pRes->fetch_assoc()) {
                $candsForSup[] = [
                    'user_id'              => (int)    ($pRow['user_id'] ?? 0),
                    'candidate_name'       => trim(
                        (string) ($pRow['first_name'] ?? '')
                        . ' ' .
                        (string) ($pRow['last_name'] ?? '')
                    ),
                    'email'                => (string) ($pRow['email'] ?? ''),
                    'phone'                => (string) ($pRow['phone'] ?? ''),
                    'participation_status' => (string) ($pRow['participation_status'] ?? ''),
                    'cohort_id'            => (int)    ($pRow['cohort_id'] ?? 0),
                    'cohort_name'          => (string) ($pRow['cohort_name'] ?? ''),
                    'programme_name'       => (string) ($pRow['programme_name'] ?? ''),
                    'onboarded_at'         => (string) ($pRow['onboarded_at'] ?? ''),
                    'completed_at'         => (string) ($pRow['completed_at'] ?? ''),
                ];
            }

            $pStmt->close();
        }

        /* ---- Aggregate stats ---- */
        $totalCands = count($candsForSup);
        $completed  = 0;
        $active     = 0;

        foreach ($candsForSup as $cand) {
            if (($cand['participation_status'] ?? '') === 'completed') $completed++;
            if (($cand['participation_status'] ?? '') === 'active')    $active++;
        }

        $supervisorOversight[$sid] = [
            'supervisor_id'   => $sid,
            'name'            => trim(
                (string) ($sup['first_name'] ?? '')
                . ' ' .
                (string) ($sup['last_name'] ?? '')
            ),
            'email'           => (string) ($sup['email'] ?? ''),
            'phone'           => (string) ($sup['phone'] ?? ''),
            'cohort_count'    => count($cohortsForSup),
            'candidate_count' => $totalCands,
            'active_count'    => $active,
            'completed_count' => $completed,
            'progress'        => $totalCands > 0
                ? (int) round(($completed / $totalCands) * 100)
                : 0,
            'cohorts'         => $cohortsForSup,
            'candidates'      => $candsForSup,
        ];
    }

    $supStmt->close();
}

$supervisorOversightJson = pm_cohorts_json($supervisorOversight);

/* ------------------------------------------------------------------
 | Supervisors available for the filter dropdown (in-scope)
 * ------------------------------------------------------------------ */

$filterSupervisors = [];

$fsSql = "
    SELECT DISTINCT
        u.id            AS supervisor_id,
        u.first_name,
        u.last_name
    FROM users u
    INNER JOIN roles r      ON r.id = u.role_id
    INNER JOIN cohorts c    ON c.supervisor_id = u.id
    INNER JOIN programmes p ON p.id = c.programme_id
    WHERE r.slug = 'supervisor'
      AND p.programme_manager_id = ?
    ORDER BY u.first_name ASC, u.last_name ASC
";

$fsStmt = $conn->prepare($fsSql);

if ($fsStmt) {
    $fsStmt->bind_param('i', $managerId);
    $fsStmt->execute();
    $fsRes = $fsStmt->get_result();

    while ($fsRow = $fsRes->fetch_assoc()) {
        $filterSupervisors[] = $fsRow;
    }

    $fsStmt->close();
}

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
    <link rel="stylesheet" href="<?= url('css/programme_manager_enhancements.css') ?>?v=20260925">

    <style>
        /* ============================================================
           Clickable cohort card
           ============================================================ */
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

        /* ============================================================
           Modal shared container
           ============================================================ */
        .pm-cohort-modal {
            position: fixed;
            inset: 0;
            z-index: 2147483646;
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
        /* Supervisor modal must sit above the cohort modal */
        #pmSupervisorModal { z-index: 2147483647; }

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

        /* ============================================================
           Supervisor modal inner content
           ============================================================ */
        .pm-sup-hero {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            margin-bottom: 18px;
            border: 1px solid #e4e7ec;
            border-radius: 14px;
            background: #f8fafc;
        }
        .pm-sup-hero__avatar {
            width: 56px;
            height: 56px;
            flex: 0 0 56px;
            border-radius: 50%;
            background: #eef2ff;
            color: #4f46e5;
            display: grid;
            place-items: center;
            font-weight: 800;
            font-size: 18px;
        }
        .pm-sup-hero__name {
            font-size: 16px;
            font-weight: 800;
            color: #101828;
            margin: 0;
        }
        .pm-sup-hero__meta {
            color: #667085;
            font-size: 12px;
            margin: 3px 0 0;
        }

        .pm-sup-section-title {
            margin: 22px 0 10px;
            font-size: 13px;
            font-weight: 800;
            color: #101828;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .pm-sup-table-mini {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #e4e7ec;
            border-radius: 12px;
            overflow: hidden;
            font-size: 12px;
        }
        .pm-sup-table-mini th {
            text-align: left;
            padding: 9px 12px;
            background: #f8fafc;
            color: #475467;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .04em;
            border-bottom: 1px solid #e4e7ec;
        }
        .pm-sup-table-mini td {
            padding: 9px 12px;
            color: #344054;
            border-bottom: 1px solid #f1f5f9;
        }
        .pm-sup-table-mini tr:last-child td { border-bottom: 0; }

        .pm-status-pill {
            display: inline-flex;
            align-items: center;
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            text-transform: capitalize;
        }
        .pm-status-pill--selected  { background:#eff6ff; color:#2563eb; }
        .pm-status-pill--onboarded { background:#ecfeff; color:#0891b2; }
        .pm-status-pill--active    { background:#ecfdf3; color:#027a48; }
        .pm-status-pill--completed { background:#f0fdf4; color:#166534; }
        .pm-status-pill--withdrawn { background:#fef2f2; color:#b91c1c; }

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

                        <?php if (!empty($filterSupervisors)): ?>
                            <div style="min-width:220px;">
                                <label for="supervisor_id" style="display:block;margin-bottom:0.4rem;font-weight:600;">Supervisor</label>
                                <select id="supervisor_id" name="supervisor_id" style="width:100%;padding:0.75rem;border:1px solid #d1d5db;border-radius:8px;">
                                    <option value="">All Supervisors</option>
                                    <?php foreach ($filterSupervisors as $fs): ?>
                                        <?php
                                        $fsName = trim(
                                            (string) ($fs['first_name'] ?? '')
                                            . ' ' .
                                            (string) ($fs['last_name'] ?? '')
                                        );
                                        ?>
                                        <option value="<?= (int) $fs['supervisor_id'] ?>"
                                            <?= $supervisorFilter === (int) $fs['supervisor_id'] ? 'selected' : '' ?>>
                                            <?= e($fsName !== '' ? $fsName : 'Supervisor') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div>
                            <button type="submit" class="sidebar__link" style="border:0;cursor:pointer;display:inline-flex;align-items:center;gap:0.5rem;">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>

                        <?php if ($search !== '' || $status !== '' || $supervisorFilter > 0): ?>
                            <div>
                                <a href="<?= url('programme/cohorts.php') ?>" class="sidebar__link" style="display:inline-flex;align-items:center;gap:0.5rem;">
                                    <i class="fas fa-times"></i> Reset
                                </a>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Cohort list header -->
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
                        <p><?= ($search !== '' || $status !== '' || $supervisorFilter > 0)
                            ? 'No cohorts match the selected search criteria.'
                            : 'There are currently no cohorts associated with your programmes.' ?></p>
                    </div>
                </div>
            <?php else: ?>
                <div class="overview-grid" style="margin-top:1rem;">
                    <?php foreach ($cohorts as $cohort): ?>
                        <?php
                        $cohortId        = (int) ($cohort['cohort_id'] ?? 0);
                        $progressWidth   = max(0, min(100, (int) ($cohort['progress'] ?? 0)));
                        $candidateCount  = (int) ($cohort['candidate_count'] ?? 0);
                        $activeCandidate = (int) ($cohort['active_candidate_count'] ?? 0);
                        $completedCount  = (int) ($cohort['completed_candidate_count'] ?? 0);
                        $withdrawnCount  = (int) ($cohort['withdrawn_candidate_count'] ?? 0);
                        $cohortStatus    = strtolower(trim((string) ($cohort['cohort_status'] ?? 'unknown')));

                        $supFirst = trim((string) ($cohort['supervisor_first_name'] ?? ''));
                        $supLast  = trim((string) ($cohort['supervisor_last_name']  ?? ''));
                        $supName  = trim($supFirst . ' ' . $supLast);
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
                                    <i class="fas fa-user-tie"></i>
                                    Supervisor:
                                    <?= $supName !== '' ? e($supName) : '<em style="color:#94a3b8;">Unassigned</em>' ?>
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

<!-- ==================== SUPERVISOR OVERSIGHT MODAL ==================== -->
<div id="pmSupervisorModal" class="pm-cohort-modal" aria-hidden="true">
    <div class="pm-cohort-modal__backdrop" data-pm-sup-close></div>

    <section class="pm-cohort-modal__dialog" role="dialog" aria-modal="true"
             aria-labelledby="pmSupervisorModalTitle"
             style="width:min(880px,100%);">
        <header class="pm-cohort-modal__header">
            <div>
                <span class="pm-cohort-modal__eyebrow">Supervisor Oversight</span>
                <h2 id="pmSupervisorModalTitle" class="pm-cohort-modal__title">Supervisor</h2>
            </div>
            <button type="button" class="pm-cohort-modal__close" data-pm-sup-close aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
        </header>

        <div id="pmSupervisorModalBody" class="pm-cohort-modal__body"></div>

        <footer class="pm-cohort-modal__footer">
            <button type="button" class="pm-cohort-modal__cancel" data-pm-sup-close>Close</button>
        </footer>
    </section>
</div>

<!-- Data tags (raw JSON, no HTML escaping) -->
<script type="application/json" id="pmCohortModalData"><?= $cohortModalJson ?></script>
<script type="application/json" id="pmSupervisorOversightData"><?= $supervisorOversightJson ?></script>

<!-- Programme Manager base script -->
<script src="<?= url('js/programme_manager_enhancements.js') ?>?v=20260925"></script>

<!-- Cohort + Supervisor modal logic -->
<script>
(function () {
    'use strict';

    var COHORT_DATA     = {};
    var SUPERVISOR_DATA = {};

    function getEl(id) { return document.getElementById(id); }

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
        if (!v) return '\u2014';
        var d = new Date(v);
        if (isNaN(d.getTime())) return String(v);
        return d.toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' });
    }

    function supInitials(name) {
        var parts = String(name || '').trim().split(/\s+/).filter(Boolean);
        if (!parts.length) return '?';
        if (parts.length === 1) return parts[0].substring(0,2).toUpperCase();
        return (parts[0][0] + parts[parts.length-1][0]).toUpperCase();
    }

    /* =========================================================
       COHORT MODAL
       ========================================================= */
     function renderCohort(c) {
        var modalTitle = getEl('pmCohortModalTitle');
        var modalBody  = getEl('pmCohortModalBody');
        var detailsBtn = getEl('pmCohortModalDetails');
        if (!modalTitle || !modalBody || !detailsBtn) return;

        var progress  = clamp(c.progress, 0, 100);
        var total     = Number(c.candidate_count || 0);
        var active    = Number(c.active_candidate_count || 0);
        var completed = Number(c.completed_candidate_count || 0);
        var withdrawn = Number(c.withdrawn_candidate_count || 0);
        var capacity  = Number(c.max_capacity || 0);

        /* --- Pills --- */
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

        /* --- Location --- */
        var locationParts = [];
        if (c.location) locationParts.push(c.location);
        if (c.province) locationParts.push(titleCase(c.province));
        var location = locationParts.join(', ');
        if (location === '') location = '\u2014';

        /* --- Supervisor name --- */
        var supNameHtml;
        if (c.supervisor_name) {
            supNameHtml = escapeHtml(c.supervisor_name);
        } else {
            supNameHtml = '<em style="color:#94a3b8;">Unassigned</em>';
        }

        /* --- Supervisor email --- */
        var supEmailHtml = '';
        if (c.supervisor_email) {
            supEmailHtml = '<span style="display:block;margin-top:2px;color:#667085;font-size:11px;">'
                + escapeHtml(c.supervisor_email)
                + '</span>';
        }

        /* --- Oversight button --- */
        var oversightBtnHtml = '';
        if (c.supervisor_id) {
            oversightBtnHtml = '<button type="button" '
                + 'class="pm-cohort-modal__cancel" '
                + 'style="min-height:34px;padding:0 12px;font-size:11px;" '
                + 'data-pm-open-supervisor="' + c.supervisor_id + '">'
                + '<i class="fas fa-user-tie"></i> View Oversight'
                + '</button>';
        }

        /* --- Meta tiles --- */
        var capacityHtml = '\u2014';
        if (capacity > 0) capacityHtml = String(capacity);

        var metaHtml = ''
            + '<div class="pm-cohort-meta-grid">'
            +   '<div class="pm-cohort-meta">'
            +     '<span class="pm-cohort-meta__label">Start date</span>'
            +     '<span class="pm-cohort-meta__value">' + escapeHtml(formatDate(c.start_date)) + '</span>'
            +   '</div>'
            +   '<div class="pm-cohort-meta">'
            +     '<span class="pm-cohort-meta__label">End date</span>'
            +     '<span class="pm-cohort-meta__value">' + escapeHtml(formatDate(c.end_date)) + '</span>'
            +   '</div>'
            +   '<div class="pm-cohort-meta">'
            +     '<span class="pm-cohort-meta__label">Location</span>'
            +     '<span class="pm-cohort-meta__value">' + escapeHtml(location) + '</span>'
            +   '</div>'
            +   '<div class="pm-cohort-meta">'
            +     '<span class="pm-cohort-meta__label">Capacity</span>'
            +     '<span class="pm-cohort-meta__value">' + escapeHtml(capacityHtml) + '</span>'
            +   '</div>'
            + '</div>';

        /* --- Supervisor tile --- */
        var supervisorHtml = ''
            + '<div class="pm-cohort-meta-grid" style="margin-top:-8px;">'
            +   '<div class="pm-cohort-meta" style="grid-column:1 / -1; display:flex; justify-content:space-between; align-items:center; gap:12px;">'
            +     '<div>'
            +       '<span class="pm-cohort-meta__label">Supervisor</span>'
            +       '<span class="pm-cohort-meta__value">' + supNameHtml + '</span>'
            +       supEmailHtml
            +     '</div>'
            +     oversightBtnHtml
            +   '</div>'
            + '</div>';

        /* --- Stats --- */
        var statsHtml = ''
            + '<div class="pm-cohort-stats">'
            +   '<div class="pm-cohort-stat"><span class="pm-cohort-stat__value">' + total     + '</span><span class="pm-cohort-stat__label">Candidates</span></div>'
            +   '<div class="pm-cohort-stat"><span class="pm-cohort-stat__value">' + active    + '</span><span class="pm-cohort-stat__label">Active</span></div>'
            +   '<div class="pm-cohort-stat"><span class="pm-cohort-stat__value">' + completed + '</span><span class="pm-cohort-stat__label">Completed</span></div>'
            +   '<div class="pm-cohort-stat"><span class="pm-cohort-stat__value">' + withdrawn + '</span><span class="pm-cohort-stat__label">Withdrawn</span></div>'
            + '</div>';

        /* --- Progress --- */
        var progressHtml = ''
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

        /* --- Summary card --- */
        var summaryHtml = ''
            + '<div class="pm-cohort-summary">'
            +   '<span class="pm-cohort-summary__label">Programme</span>'
            +   '<span class="pm-cohort-summary__value">' + escapeHtml(c.programme_name || '\u2014') + '</span>'
            +   '<div class="pm-cohort-summary__meta">' + pills + '</div>'
            + '</div>';

        /* --- Write --- */
        modalTitle.textContent = c.cohort_name || 'Cohort';

        modalBody.innerHTML = summaryHtml
            + metaHtml
            + supervisorHtml
            + statsHtml
            + progressHtml;

        detailsBtn.href = c.view_url || '#';
    }

    function openCohortModalById(id) {
        var modal = getEl('pmCohortModal');
        if (!modal) {
            console.warn('[pm-cohort-modal] cohort modal missing');
            return;
        }

        var c = COHORT_DATA[String(id)];
        if (!c) {
            console.warn('[pm-cohort-modal] no data for cohort', id);
            return;
        }

        renderCohort(c);
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('pm-cohort-modal-open');

        var closeBtn = modal.querySelector('.pm-cohort-modal__close');
        if (closeBtn) { try { closeBtn.focus(); } catch (e) {} }
    }

    function closeCohortModal() {
        var modal = getEl('pmCohortModal');
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');

        var supModal = getEl('pmSupervisorModal');
        if (!supModal || !supModal.classList.contains('is-open')) {
            document.body.classList.remove('pm-cohort-modal-open');
        }
    }

    /* =========================================================
       SUPERVISOR MODAL
       ========================================================= */
    function renderSupervisor(sup) {
        var modal   = getEl('pmSupervisorModal');
        var titleEl = getEl('pmSupervisorModalTitle');
        var bodyEl  = getEl('pmSupervisorModalBody');
        if (!modal || !titleEl || !bodyEl) return;

        titleEl.textContent = sup.name || 'Supervisor';

        var hero = ''
            + '<div class="pm-sup-hero">'
            +   '<div class="pm-sup-hero__avatar">' + escapeHtml(supInitials(sup.name)) + '</div>'
            +   '<div>'
            +     '<p class="pm-sup-hero__name">' + escapeHtml(sup.name || 'Supervisor') + '</p>'
            +     (sup.email ? '<p class="pm-sup-hero__meta"><i class="fas fa-envelope"></i> ' + escapeHtml(sup.email) + '</p>' : '')
            +     (sup.phone ? '<p class="pm-sup-hero__meta"><i class="fas fa-phone"></i> ' + escapeHtml(sup.phone) + '</p>' : '')
            +   '</div>'
            + '</div>';

        var stats = ''
            + '<div class="pm-cohort-stats">'
            +   '<div class="pm-cohort-stat"><span class="pm-cohort-stat__value">' + sup.cohort_count    + '</span><span class="pm-cohort-stat__label">Cohorts</span></div>'
            +   '<div class="pm-cohort-stat"><span class="pm-cohort-stat__value">' + sup.candidate_count + '</span><span class="pm-cohort-stat__label">Candidates</span></div>'
            +   '<div class="pm-cohort-stat"><span class="pm-cohort-stat__value">' + sup.active_count    + '</span><span class="pm-cohort-stat__label">Active</span></div>'
            +   '<div class="pm-cohort-stat"><span class="pm-cohort-stat__value">' + sup.completed_count + '</span><span class="pm-cohort-stat__label">Completed</span></div>'
            + '</div>';

        var cohortsHtml = '<h3 class="pm-sup-section-title"><i class="fas fa-layer-group"></i> Supervised Cohorts</h3>';
        if (!sup.cohorts || !sup.cohorts.length) {
            cohortsHtml += '<p style="color:#667085;font-size:12px;">No cohorts assigned.</p>';
        } else {
            cohortsHtml += '<table class="pm-sup-table-mini"><thead><tr>'
                + '<th>Cohort</th><th>Programme</th><th>Candidates</th><th>Completed</th><th>Progress</th>'
                + '</tr></thead><tbody>';
            sup.cohorts.forEach(function (co) {
                var p = Math.max(0, Math.min(100, Number(co.progress) || 0));
                cohortsHtml += '<tr>'
                    + '<td><strong>' + escapeHtml(co.cohort_name) + '</strong></td>'
                    + '<td>' + escapeHtml(co.programme_name) + '</td>'
                    + '<td>' + co.candidate_count + '</td>'
                    + '<td>' + co.completed_candidate_count + '</td>'
                    + '<td><div style="display:flex;align-items:center;gap:8px;">'
                    +   '<div style="flex:1;min-width:60px;height:5px;background:#e5e7eb;border-radius:999px;overflow:hidden;">'
                    +     '<div style="width:' + p + '%;height:100%;background:#1a56db;"></div>'
                    +   '</div>'
                    +   '<span style="font-weight:700;font-size:11px;">' + p + '%</span>'
                    + '</div></td>'
                    + '</tr>';
            });
            cohortsHtml += '</tbody></table>';
        }

        var candsHtml = '<h3 class="pm-sup-section-title"><i class="fas fa-users"></i> Candidates Under Supervision</h3>';
        if (!sup.candidates || !sup.candidates.length) {
            candsHtml += '<p style="color:#667085;font-size:12px;">No candidates currently assigned.</p>';
        } else {
            candsHtml += '<table class="pm-sup-table-mini"><thead><tr>'
                + '<th>Candidate</th><th>Cohort</th><th>Programme</th><th>Status</th><th>Onboarded</th><th>Completed</th>'
                + '</tr></thead><tbody>';
            sup.candidates.forEach(function (cand) {
                var status = String(cand.participation_status || '').toLowerCase();
                var cls    = 'pm-status-pill--' + status.replace(/[^a-z]/g, '');
                candsHtml += '<tr>'
                    + '<td><strong>' + escapeHtml(cand.candidate_name || 'Candidate') + '</strong><br>'
                    +   '<span style="color:#667085;font-size:11px;">' + escapeHtml(cand.email || '') + '</span></td>'
                    + '<td>' + escapeHtml(cand.cohort_name) + '</td>'
                    + '<td>' + escapeHtml(cand.programme_name) + '</td>'
                    + '<td><span class="pm-status-pill ' + cls + '">' + escapeHtml(status) + '</span></td>'
                    + '<td>' + escapeHtml(formatDate(cand.onboarded_at)) + '</td>'
                    + '<td>' + escapeHtml(formatDate(cand.completed_at)) + '</td>'
                    + '</tr>';
            });
            candsHtml += '</tbody></table>';
        }

        bodyEl.innerHTML = hero + stats + cohortsHtml + candsHtml;
    }

    function openSupervisorModalById(id) {
        var sup = SUPERVISOR_DATA[String(id)];
        if (!sup) {
            console.warn('[pm-cohort-modal] no oversight data for supervisor', id);
            return;
        }
        renderSupervisor(sup);
        var supModal = getEl('pmSupervisorModal');
        if (!supModal) return;
        supModal.classList.add('is-open');
        supModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('pm-cohort-modal-open');
    }

    function closeSupervisorModal() {
        var supModal = getEl('pmSupervisorModal');
        if (!supModal) return;
        supModal.classList.remove('is-open');
        supModal.setAttribute('aria-hidden', 'true');

        var cohortModal = getEl('pmCohortModal');
        if (!cohortModal || !cohortModal.classList.contains('is-open')) {
            document.body.classList.remove('pm-cohort-modal-open');
        }
    }

    /* Expose globally for inline onclick fallback */
    window.pmOpenCohortModal      = openCohortModalById;
    window.pmCloseCohortModal     = closeCohortModal;
    window.pmOpenSupervisorModal  = openSupervisorModalById;
    window.pmCloseSupervisorModal = closeSupervisorModal;

    /* =========================================================
       Attach handlers DIRECTLY to each card
       ========================================================= */
    function attachCardHandlers() {
        var cards = document.querySelectorAll('.pm-cohort-card');
        console.log('[pm-cohort-modal] attaching handlers to', cards.length, 'cards');

        var i;
        for (i = 0; i < cards.length; i++) {
            (function (card) {
                if (card.getAttribute('data-pm-bound') === '1') return;
                card.setAttribute('data-pm-bound', '1');

                card.style.cursor = 'pointer';

                card.addEventListener('click', function (e) {
                    if (e.target.closest('a, button, input, select, textarea')) return;

                    e.preventDefault();
                    e.stopPropagation();

                    var cid = Number(card.getAttribute('data-pm-cohort-id') || 0);
                    console.log('[pm-cohort-modal] card clicked, cohort id =', cid);

                    if (cid > 0) {
                        try {
                            openCohortModalById(cid);
                        } catch (err) {
                            console.error('[pm-cohort-modal] open failed:', err);
                        }
                    }
                }, false);

                card.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        var cid = Number(card.getAttribute('data-pm-cohort-id') || 0);
                        if (cid > 0) openCohortModalById(cid);
                    }
                }, false);
            })(cards[i]);
        }
    }

    /* =========================================================
       Attach handlers to close/oversight buttons directly
       ========================================================= */
    function attachModalHandlers() {
        /* Close triggers — cohort modal */
        var closeCohortEls = document.querySelectorAll('#pmCohortModal [data-pm-cohort-close]');
        var i;
        for (i = 0; i < closeCohortEls.length; i++) {
            (function (el) {
                el.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    closeCohortModal();
                }, false);
            })(closeCohortEls[i]);
        }

        /* Close triggers — supervisor modal */
        var closeSupEls = document.querySelectorAll('#pmSupervisorModal [data-pm-sup-close]');
        for (i = 0; i < closeSupEls.length; i++) {
            (function (el) {
                el.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    closeSupervisorModal();
                }, false);
            })(closeSupEls[i]);
        }

        /* Delegated handler for dynamic "View Oversight" button */
        var cohortBody = getEl('pmCohortModalBody');
        if (cohortBody) {
            cohortBody.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-pm-open-supervisor]');
                if (!btn) return;
                e.preventDefault();
                e.stopPropagation();
                var sid = btn.getAttribute('data-pm-open-supervisor');
                if (sid) openSupervisorModalById(sid);
            }, false);
        }
    }

    /* =========================================================
       Load JSON
       ========================================================= */
    function loadData() {
        var tag = getEl('pmCohortModalData');
        if (tag) {
            try {
                COHORT_DATA = JSON.parse(tag.textContent || '{}') || {};
            } catch (err) {
                console.error('[pm-cohort-modal] cohort JSON parse error:', err);
                COHORT_DATA = {};
            }
        }

        var supTag = getEl('pmSupervisorOversightData');
        if (supTag) {
            try {
                SUPERVISOR_DATA = JSON.parse(supTag.textContent || '{}') || {};
            } catch (err) {
                console.error('[pm-cohort-modal] supervisor JSON parse error:', err);
                SUPERVISOR_DATA = {};
            }
        }
    }

    /* =========================================================
       Escape key closes topmost modal
       ========================================================= */
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;

        var supModal = getEl('pmSupervisorModal');
        if (supModal && supModal.classList.contains('is-open')) {
            e.preventDefault();
            closeSupervisorModal();
            return;
        }
        var modal = getEl('pmCohortModal');
        if (modal && modal.classList.contains('is-open')) {
            e.preventDefault();
            closeCohortModal();
        }
    });

    /* =========================================================
       Init
       ========================================================= */
    function init() {
        try {
            loadData();

            console.log('[pm-cohort-modal] Ready. Cards:',
                document.querySelectorAll('.pm-cohort-card').length,
                'Cohorts:', Object.keys(COHORT_DATA).length,
                'Supervisors:', Object.keys(SUPERVISOR_DATA).length);

            if (Object.keys(COHORT_DATA).length > 0) {
                var sampleId = Object.keys(COHORT_DATA)[0];
                console.log('[pm-cohort-modal] sample record for id', sampleId, '=', COHORT_DATA[sampleId]);
            }

            attachCardHandlers();
            attachModalHandlers();
        } catch (err) {
            console.error('[pm-cohort-modal] init failed:', err);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.addEventListener('load', function () {
        try { attachCardHandlers(); } catch (e) {}
    });
})();
</script>

</body>
</html>