<?php
/**
 * ================================================
 * INVESTHOOD IT - Supervisor Candidates
 * ================================================
 * Role: Supervisor
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('supervisor');
$user = current_user();
$flashes = render_flashes();
/*
|--------------------------------------------------------------------------
| Supervisor
|--------------------------------------------------------------------------
*/
$supervisorId = (int) ($user['id'] ?? 0);
/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/
$statusFilter = trim($_GET['status'] ?? '');
$cohortFilter = (int) ($_GET['cohort_id'] ?? 0);
$search = trim($_GET['search'] ?? '');
/*
|--------------------------------------------------------------------------
| Valid Candidate Statuses
|--------------------------------------------------------------------------
*/
$allowedStatuses = [
    'selected',
    'onboarded',
    'active',
    'completed',
    'withdrawn'
];
if ($statusFilter !== '' && !in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}
/*
|--------------------------------------------------------------------------
| Assigned Cohorts
|--------------------------------------------------------------------------
*/
$cohorts = [];
$stmt = Database::prepare("
    SELECT
        c.id,
        c.name,
        p.name AS programme_name
    FROM cohorts c
    INNER JOIN programmes p
        ON p.id = c.programme_id
    WHERE c.supervisor_id = ?
    ORDER BY c.start_date ASC, c.id ASC
", 'i', [$supervisorId]);
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $cohorts[] = $row;
}
$stmt->close();
/*
|--------------------------------------------------------------------------
| Candidate Query
|--------------------------------------------------------------------------
*/
$candidates = [];
$sql = "
    SELECT
        cp.id AS participation_id,
        cp.user_id,
        cp.cohort_id,
        cp.status AS participation_status,
        cp.selected_at,
        cp.onboarded_at,
        cp.completed_at,
        u.first_name,
        u.last_name,
        u.email,
        u.phone,
        c.name AS cohort_name,
        c.start_date AS cohort_start_date,
        c.end_date AS cohort_end_date,
        c.status AS cohort_status,
        p.id AS programme_id,
        p.name AS programme_name,
        p.type AS programme_type
    FROM cohort_participants cp
    INNER JOIN cohorts c
        ON c.id = cp.cohort_id
    INNER JOIN programmes p
        ON p.id = c.programme_id
    INNER JOIN users u
        ON u.id = cp.user_id
    WHERE c.supervisor_id = ?
";
$params = [$supervisorId];
$types = 'i';
/*
|--------------------------------------------------------------------------
| Status Filter
|--------------------------------------------------------------------------
*/
if ($statusFilter !== '') {
    $sql .= " AND cp.status = ?";
    $types .= 's';
    $params[] = $statusFilter;
}
/*
|--------------------------------------------------------------------------
| Cohort Filter
|--------------------------------------------------------------------------
*/
if ($cohortFilter > 0) {
    $sql .= " AND c.id = ?";
    $types .= 'i';
    $params[] = $cohortFilter;
}
/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/
if ($search !== '') {
    $sql .= "
        AND (
            u.first_name LIKE ?
            OR u.last_name LIKE ?
            OR u.email LIKE ?
            OR c.name LIKE ?
            OR p.name LIKE ?
        )
    ";
    $searchValue = '%' . $search . '%';
    $types .= 'sssss';
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}
/*
|--------------------------------------------------------------------------
| Ordering
|--------------------------------------------------------------------------
*/
$sql .= "
    ORDER BY
        c.start_date ASC,
        u.first_name ASC,
        u.last_name ASC
";
/*
|--------------------------------------------------------------------------
| Execute
|--------------------------------------------------------------------------
*/
$stmt = Database::prepare($sql, $types, $params);
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $candidates[] = $row;
}
$stmt->close();
/*
|--------------------------------------------------------------------------
| Status Badge Helper
|--------------------------------------------------------------------------
*/
function supervisor_status_class(string $status): string
{
    return match ($status) {
        'selected' => 'status-badge status-badge--warning',
        'onboarded' => 'status-badge status-badge--info',
        'active' => 'status-badge status-badge--success',
        'completed' => 'status-badge status-badge--primary',
        'withdrawn' => 'status-badge status-badge--danger',
        default => 'status-badge'
    };
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
        Candidates | Supervisor | Investhood IT
    </title>
    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >
    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >
    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
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
        .page-card {
            background: #fff;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }
        .filter-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 1rem;
            align-items: end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }
        .filter-group label {
            font-size: 0.85rem;
            font-weight: 600;
        }
        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 0.75rem 0.85rem;
            border: 1px solid #d9dee8;
            border-radius: 8px;
            font-family: inherit;
            background: #fff;
        }
        .filter-actions {
            display: flex;
            gap: 0.5rem;
        }
        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
            padding: 0.75rem 1rem;
            border: 0;
            border-radius: 8px;
            background: #1a56db;
            color: #fff;
            text-decoration: none;
            cursor: pointer;
            font-family: inherit;
            font-weight: 600;
        }
        .btn-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            background: #f3f4f6;
            color: #374151;
            text-decoration: none;
            font-weight: 600;
        }
        .table-wrapper {
            overflow-x: auto;
        }
        .candidate-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        .candidate-table th,
        .candidate-table td {
            padding: 0.9rem;
            text-align: left;
            border-bottom: 1px solid #edf0f5;
            white-space: nowrap;
        }
        .candidate-table th {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #6b7280;
        }
        .candidate-name {
            font-weight: 600;
        }
        .candidate-email {
            color: #6b7280;
            font-size: 0.85rem;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.65rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: capitalize;
            background: #f3f4f6;
            color: #374151;
        }
        .status-badge--warning {
            background: #fff7ed;
            color: #c2410c;
        }
        .status-badge--info {
            background: #eff6ff;
            color: #1d4ed8;
        }
        .status-badge--success {
            background: #ecfdf5;
            color: #047857;
        }
        .status-badge--primary {
            background: #eef2ff;
            color: #4338ca;
        }
        .status-badge--danger {
            background: #fef2f2;
            color: #b91c1c;
        }
        .results-summary {
            margin-top: 1rem;
            color: #6b7280;
            font-size: 0.9rem;
        }
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6b7280;
        }
        .empty-state i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        @media (max-width: 900px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }
            .filter-actions {
                width: 100%;
            }
        }
    </style>
</head>
<body class="dashboard-page">
<div class="dashboard">
    <!-- =====================================================
         SIDEBAR
    ====================================================== -->
    <?php require_once __DIR__ . '/sidebar.php'; ?>
    <!-- =====================================================
         MAIN CONTENT
    ====================================================== -->
    <main class="dashboard__main">
        <!-- =================================================
             HEADER
        ================================================== -->
        <header class="dash-header">
            <div class="dash-header__left">
                <h1 class="dash-header__title">
                    Candidates
                </h1>
            </div>
            <div class="dash-header__right">
                <div class="dash-header__user">
                    <img
                        src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'Supervisor') ?>&background=1a56db&color=fff&size=80"
                        alt=""
                        class="dash-header__avatar"
                    >
                </div>
            </div>
        </header>
        <!-- =================================================
             CONTENT
        ================================================== -->
        <div class="dash-content">
            <!-- =================================================
                 PAGE INTRO
            ================================================== -->
            <div class="welcome-card">
                <div class="welcome-card__bg"></div>
                <div class="welcome-card__content">
                    <h1 class="welcome-card__greeting">
                        My Candidates
                    </h1>
                    <p>
                        View and monitor candidates participating in
                        your assigned cohorts.
                    </p>
                </div>
            </div>
            <!-- =================================================
                 FILTERS
            ================================================== -->
            <div
                class="page-card"
                style="margin-top:2rem;"
            >
                <form
                    method="GET"
                    action=""
                >
                    <div class="filter-grid">
                        <!-- Search -->
                        <div class="filter-group">
                            <label for="search">
                                Search
                            </label>
                            <input
                                type="text"
                                id="search"
                                name="search"
                                value="<?= e($search) ?>"
                                placeholder="Name, email, cohort or programme..."
                            >
                        </div>
                        <!-- Cohort -->
                        <div class="filter-group">
                            <label for="cohort_id">
                                Cohort
                            </label>
                            <select
                                id="cohort_id"
                                name="cohort_id"
                            >
                                <option value="0">
                                    All Cohorts
                                </option>
                                <?php foreach ($cohorts as $cohort): ?>
                                    <option
                                        value="<?= (int) $cohort['id'] ?>"
                                        <?= $cohortFilter === (int) $cohort['id']
                                            ? 'selected'
                                            : '' ?>
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
                            >
                                <option value="">
                                    All Statuses
                                </option>
                                <?php foreach ($allowedStatuses as $status): ?>
                                    <option
                                        value="<?= e($status) ?>"
                                        <?= $statusFilter === $status
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= e(ucfirst($status)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- Actions -->
                        <div class="filter-actions">
                            <button
                                type="submit"
                                class="btn-primary"
                            >
                                <i class="fas fa-filter"></i>
                                Filter
                            </button>
                            <a
                                href="<?= url('supervisor/candidates.php') ?>"
                                class="btn-secondary"
                            >
                                <i class="fas fa-rotate-left"></i>
                                Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
            <!-- =================================================
                 CANDIDATES TABLE
            ================================================== -->
            <div
                class="page-card"
                style="margin-top:1.5rem;"
            >
                <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;">
                    <div>
                        <h2 style="margin:0;">
                            Candidate List
                        </h2>
                        <p
                            style="margin:0.35rem 0 0;color:#6b7280;"
                        >
                            Candidates assigned through your cohorts.
                        </p>
                    </div>
                    <div>
                        <strong>
                            <?= number_format(count($candidates)) ?>
                        </strong>
                        Results
                    </div>
                </div>
                <?php if (empty($candidates)): ?>
                    <div class="empty-state">
                        <i class="fas fa-users-slash"></i>
                        <h3>
                            No Candidates Found
                        </h3>
                        <p>
                            No candidates match the selected filters
                            or you currently have no candidates assigned.
                        </p>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table class="candidate-table">
                            <thead>
                                <tr>
                                    <th>
                                        Candidate
                                    </th>
                                    <th>
                                        Programme
                                    </th>
                                    <th>
                                        Cohort
                                    </th>
                                    <th>
                                        Status
                                    </th>
                                    <th>
                                        Selected
                                    </th>
                                    <th>
                                        Onboarded
                                    </th>
                                    <th>
                                        Completed
                                    </th>
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
                                                    $candidate['first_name']
                                                    . ' '
                                                    . $candidate['last_name']
                                                )
                                            ) ?>
                                        </div>
                                        <div class="candidate-email">
                                            <?= e($candidate['email']) ?>
                                        </div>
                                    </td>
                                    <!-- Programme -->
                                    <td>
                                        <?= e(
                                            $candidate['programme_name']
                                        ) ?>
                                    </td>
                                    <!-- Cohort -->
                                    <td>
                                        <?= e(
                                            $candidate['cohort_name']
                                        ) ?>
                                    </td>
                                    <!-- Status -->
                                    <td>
                                        <span
                                            class="<?= e(
                                                supervisor_status_class(
                                                    $candidate['participation_status']
                                                )
                                            ) ?>"
                                        >
                                            <?= e(
                                                ucfirst(
                                                    $candidate['participation_status']
                                                )
                                            ) ?>
                                        </span>
                                    </td>
                                    <!-- Selected -->
                                    <td>
                                        <?= !empty($candidate['selected_at'])
                                            ? e(
                                                date(
                                                    'd M Y',
                                                    strtotime(
                                                        $candidate['selected_at']
                                                    )
                                                )
                                            )
                                            : '—' ?>
                                    </td>
                                    <!-- Onboarded -->
                                    <td>
                                        <?= !empty($candidate['onboarded_at'])
                                            ? e(
                                                date(
                                                    'd M Y',
                                                    strtotime(
                                                        $candidate['onboarded_at']
                                                    )
                                                )
                                            )
                                            : '—' ?>
                                    </td>
                                    <!-- Completed -->
                                    <td>
                                        <?= !empty($candidate['completed_at'])
                                            ? e(
                                                date(
                                                    'd M Y',
                                                    strtotime(
                                                        $candidate['completed_at']
                                                    )
                                                )
                                            )
                                            : '—' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
</body>
</html>