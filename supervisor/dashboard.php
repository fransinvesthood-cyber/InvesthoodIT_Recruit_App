<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('supervisor');
$user = current_user();
$flashes = render_flashes();
$currentPage = 'dashboard';
/*
|--------------------------------------------------------------------------
| Supervisor
|--------------------------------------------------------------------------
*/
$supervisorId = (int)(
    $user['id']
    ?? $user['user_id']
    ?? 0
);
if ($supervisorId <= 0) {
    http_response_code(403);
    exit(
        'Invalid Supervisor account.'
    );
}
/*
|--------------------------------------------------------------------------
| Supervisor Name
|--------------------------------------------------------------------------
*/
$firstName = trim(
    (string)(
        $user['first_name']
        ?? ''
    )
);
$lastName = trim(
    (string)(
        $user['last_name']
        ?? ''
    )
);
$welcomeName =
    $firstName !== ''
        ? $firstName
        : 'Supervisor';
/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/
function supervisorDashboardDate(
    ?string $date
): string {
    if (
        !$date
        ||
        $date === '0000-00-00'
    ) {
        return 'Not set';
    }
    $timestamp =
        strtotime(
            $date
        );
    if (!$timestamp) {
        return 'Not set';
    }
    return date(
        'd M Y',
        $timestamp
    );
}
function supervisorDashboardLabel(
    ?string $status
): string {
    $status = trim(
        (string)$status
    );
    if ($status === '') {
        return 'Unknown';
    }
    return ucwords(
        str_replace(
            '_',
            ' ',
            $status
        )
    );
}
function supervisorDashboardStatusClass(
    ?string $status
): string {
    $status = strtolower(
        trim(
            (string)$status
        )
    );
    return match ($status) {
        'active' =>
            'active',
        'completed' =>
            'completed',
        'withdrawn' =>
            'withdrawn',
        'pending',
        'upcoming' =>
            'pending',
        default =>
            'neutral'
    };
}
function supervisorDashboardInitials(
    string $firstName,
    string $lastName
): string {
    $initials = '';
    if ($firstName !== '') {
        $initials .= strtoupper(
            substr(
                $firstName,
                0,
                1
            )
        );
    }
    if ($lastName !== '') {
        $initials .= strtoupper(
            substr(
                $lastName,
                0,
                1
            )
        );
    }
    return $initials !== ''
        ? $initials
        : 'C';
}
function supervisorDashboardCompletionRate(
    int $current,
    int $completed
): int {
    if ($current <= 0) {
        return 0;
    }
    return min(
        100,
        max(
            0,
            (int)round(
                (
                    $completed
                    /
                    $current
                )
                *
                100
            )
        )
    );
}
/*
|--------------------------------------------------------------------------
| Dashboard Statistics
|--------------------------------------------------------------------------
*/
$stmt = Database::prepare(
    "
        SELECT
            COUNT(
                DISTINCT c.id
            ) AS assigned_cohorts,
            COUNT(
                DISTINCT CASE
                    WHEN c.status = 'active'
                    THEN c.id
                END
            ) AS active_cohorts,
            COUNT(
                DISTINCT CASE
                    WHEN cp.status <> 'withdrawn'
                    THEN cp.user_id
                END
            ) AS current_candidates,
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
        FROM cohorts c
        LEFT JOIN cohort_participants cp
            ON cp.cohort_id = c.id
        WHERE c.supervisor_id = ?
    ",
    'i',
    [
        $supervisorId
    ]
);
$stats =
    $stmt
        ->get_result()
        ->fetch_assoc()
    ?? [];
$stmt->close();
$assignedCohorts =
    (int)(
        $stats['assigned_cohorts']
        ?? 0
    );
$activeCohorts =
    (int)(
        $stats['active_cohorts']
        ?? 0
    );
$currentCandidates =
    (int)(
        $stats['current_candidates']
        ?? 0
    );
$activeCandidates =
    (int)(
        $stats['active_candidates']
        ?? 0
    );
$completedCandidates =
    (int)(
        $stats['completed_candidates']
        ?? 0
    );
$withdrawnCandidates =
    (int)(
        $stats['withdrawn_candidates']
        ?? 0
    );
$completionRate =
    supervisorDashboardCompletionRate(
        $currentCandidates,
        $completedCandidates
    );
/*
|--------------------------------------------------------------------------
| Assigned Cohorts
|--------------------------------------------------------------------------
*/
$stmt = Database::prepare(
    "
        SELECT
            c.id,
            c.name
                AS cohort_name,
            c.status
                AS cohort_status,
            c.start_date,
            c.end_date,
            p.name
                AS programme_name,
            p.type
                AS programme_type,
            COUNT(
                DISTINCT CASE
                    WHEN cp.status <> 'withdrawn'
                    THEN cp.user_id
                END
            ) AS current_candidates,
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
            ) AS completed_candidates
        FROM cohorts c
        INNER JOIN programmes p
            ON p.id = c.programme_id
        LEFT JOIN cohort_participants cp
            ON cp.cohort_id = c.id
        WHERE c.supervisor_id = ?
        GROUP BY
            c.id,
            c.name,
            c.status,
            c.start_date,
            c.end_date,
            p.name,
            p.type
        ORDER BY
            CASE
                WHEN c.status = 'active'
                THEN 1
                ELSE 2
            END,
            c.start_date DESC,
            c.id DESC
        LIMIT 5
    ",
    'i',
    [
        $supervisorId
    ]
);
$result =
    $stmt->get_result();
$cohorts = [];
while (
    $row =
        $result->fetch_assoc()
) {
    $current =
        (int)(
            $row[
                'current_candidates'
            ]
            ?? 0
        );
    $completed =
        (int)(
            $row[
                'completed_candidates'
            ]
            ?? 0
        );
    $row['completion_rate'] =
        supervisorDashboardCompletionRate(
            $current,
            $completed
        );
    $cohorts[] = $row;
}
$stmt->close();
/*
|--------------------------------------------------------------------------
| Recent Candidates
|--------------------------------------------------------------------------
*/
$stmt = Database::prepare(
    "
        SELECT
            u.id
                AS candidate_id,
            u.first_name,
            u.last_name,
            u.email,
            cp.status
                AS participant_status,
            cp.selected_at,
            cp.onboarded_at,
            cp.completed_at,
            c.id
                AS cohort_id,
            c.name
                AS cohort_name,
            p.name
                AS programme_name
        FROM cohort_participants cp
        INNER JOIN cohorts c
            ON c.id = cp.cohort_id
        INNER JOIN programmes p
            ON p.id = c.programme_id
        INNER JOIN users u
            ON u.id = cp.user_id
        WHERE c.supervisor_id = ?
        ORDER BY
            COALESCE(
                cp.completed_at,
                cp.onboarded_at,
                cp.selected_at
            ) DESC,
            cp.id DESC
        LIMIT 6
    ",
    'i',
    [
        $supervisorId
    ]
);
$result =
    $stmt->get_result();
$recentCandidates = [];
while (
    $row =
        $result->fetch_assoc()
) {
    $recentCandidates[] =
        $row;
}
$stmt->close();
/*
|--------------------------------------------------------------------------
| Attention Needed
|--------------------------------------------------------------------------
*/
$stmt = Database::prepare(
    "
        SELECT
            c.id
                AS cohort_id,
            c.name
                AS cohort_name,
            c.end_date,
            p.name
                AS programme_name,
            COUNT(
                DISTINCT cp.user_id
            ) AS active_candidates
        FROM cohorts c
        INNER JOIN programmes p
            ON p.id = c.programme_id
        INNER JOIN cohort_participants cp
            ON cp.cohort_id = c.id
        WHERE c.supervisor_id = ?
          AND cp.status = 'active'
          AND c.end_date IS NOT NULL
          AND c.end_date >= CURDATE()
          AND c.end_date <= DATE_ADD(
              CURDATE(),
              INTERVAL 30 DAY
          )
        GROUP BY
            c.id,
            c.name,
            c.end_date,
            p.name
        ORDER BY
            c.end_date ASC
        LIMIT 4
    ",
    'i',
    [
        $supervisorId
    ]
);
$result =
    $stmt->get_result();
$attentionRows = [];
while (
    $row =
        $result->fetch_assoc()
) {
    $attentionRows[] =
        $row;
}
$stmt->close();
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
        Supervisor Dashboard | Investhood IT
    </title>
    <!-- ============================================================
         EARLY THEME
    ============================================================= -->
    <script>
        (function () {
            try {
                const savedTheme =
                    localStorage.getItem(
                        'investhood-supervisor-theme'
                    );
                document.documentElement
                    .setAttribute(
                        'data-theme',
                        savedTheme === 'dark'
                            ? 'dark'
                            : 'light'
                    );
            } catch (error) {
                document.documentElement
                    .setAttribute(
                        'data-theme',
                        'light'
                    );
            }
        })();
    </script>
    <!-- ============================================================
         FONT
    ============================================================= -->
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
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >
    <!-- ============================================================
         ICONS
    ============================================================= -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    >
    <!-- ============================================================
         GLOBAL CSS
    ============================================================= -->
    <link
        rel="stylesheet"
        href="<?= url(
            'css/styles.css'
        ) ?>"
    >
    <!-- ============================================================
         SUPERVISOR CSS
    ============================================================= -->
    <link
        rel="stylesheet"
        href="<?= url(
            'css/supervisor.css'
        ) ?>"
    >
</head>
<body class="dashboard-page">
<div class="dashboard">
    <!-- ============================================================
         SIDEBAR
    ============================================================= -->
    <?php
    require_once __DIR__ . '/sidebar.php';
    ?>
    <!-- ============================================================
         MAIN
    ============================================================= -->
    <main class="dashboard__main">
        <!-- ========================================================
             NAVBAR
        ========================================================= -->
        <?php
        require_once __DIR__ . '/navbar.php';
        ?>
        <!-- ========================================================
             PAGE
        ========================================================= -->
        <div class="sv-page">
            <!-- ====================================================
                 FLASH MESSAGES
            ===================================================== -->
            <?= $flashes ?>
            <!-- ====================================================
                 HERO
            ===================================================== -->
            <section class="sv-hero">
                <div class="sv-hero__content">
                    <span class="sv-hero__eyebrow">
                        <i class="fas fa-sparkles"></i>
                        Supervisor Workspace
                    </span>
                    <h1>
                        Welcome back,
                        <?= e(
                            $welcomeName
                        ) ?>.
                    </h1>
                    <p>
                        Monitor your assigned cohorts,
                        review candidate progress and
                        focus on activities that need
                        your attention.
                    </p>
                </div>
                <div class="sv-hero__metric">
                    <strong>
                        <?= number_format(
                            $completionRate
                        ) ?>%
                    </strong>
                    <span>
                        Overall Completion
                    </span>
                </div>
            </section>
            <!-- ====================================================
                 STATISTICS
            ===================================================== -->
            <section class="sv-stats">
                <!-- ASSIGNED COHORTS -->
                <article class="sv-stat">
                    <div
                        class="
                            sv-stat__icon
                            sv-stat__icon--blue
                        "
                    >
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <strong class="sv-stat__value">
                        <?= number_format(
                            $assignedCohorts
                        ) ?>
                    </strong>
                    <span class="sv-stat__label">
                        Assigned Cohorts
                    </span>
                </article>
                <!-- ACTIVE COHORTS -->
                <article class="sv-stat">
                    <div
                        class="
                            sv-stat__icon
                            sv-stat__icon--purple
                        "
                    >
                        <i class="fas fa-bolt"></i>
                    </div>
                    <strong class="sv-stat__value">
                        <?= number_format(
                            $activeCohorts
                        ) ?>
                    </strong>
                    <span class="sv-stat__label">
                        Active Cohorts
                    </span>
                </article>
                <!-- CURRENT CANDIDATES -->
                <article class="sv-stat">
                    <div
                        class="
                            sv-stat__icon
                            sv-stat__icon--orange
                        "
                    >
                        <i class="fas fa-users"></i>
                    </div>
                    <strong class="sv-stat__value">
                        <?= number_format(
                            $currentCandidates
                        ) ?>
                    </strong>
                    <span class="sv-stat__label">
                        Current Candidates
                    </span>
                </article>
                <!-- COMPLETED -->
                <article class="sv-stat">
                    <div
                        class="
                            sv-stat__icon
                            sv-stat__icon--green
                        "
                    >
                        <i class="fas fa-circle-check"></i>
                    </div>
                    <strong class="sv-stat__value">
                        <?= number_format(
                            $completedCandidates
                        ) ?>
                    </strong>
                    <span class="sv-stat__label">
                        Completed
                    </span>
                </article>
                <!-- WITHDRAWN -->
                <article class="sv-stat">
                    <div
                        class="
                            sv-stat__icon
                            sv-stat__icon--red
                        "
                    >
                        <i class="fas fa-user-minus"></i>
                    </div>
                    <strong class="sv-stat__value">
                        <?= number_format(
                            $withdrawnCandidates
                        ) ?>
                    </strong>
                    <span class="sv-stat__label">
                        Withdrawn
                    </span>
                </article>
            </section>
            <!-- ====================================================
                 QUICK ACTIONS
            ===================================================== -->
            <section class="sv-quick-actions">
                <!-- COHORTS -->
                <a
                    href="<?= url(
                        'supervisor/cohorts.php'
                    ) ?>"
                    class="sv-quick-action"
                >
                    <div class="sv-quick-action__icon">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div>
                        <strong>
                            My Cohorts
                        </strong>
                        <span>
                            Review assigned cohorts
                            and progress
                        </span>
                    </div>
                </a>
                <!-- CANDIDATES -->
                <a
                    href="<?= url(
                        'supervisor/candidates.php'
                    ) ?>"
                    class="sv-quick-action"
                >
                    <div class="sv-quick-action__icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <strong>
                            Candidates
                        </strong>
                        <span>
                            Review candidate
                            participation
                        </span>
                    </div>
                </a>
                <!-- REPORTS -->
                <a
                    href="<?= url(
                        'supervisor/reports.php'
                    ) ?>"
                    class="sv-quick-action"
                >
                    <div class="sv-quick-action__icon">
                        <i class="fas fa-chart-column"></i>
                    </div>
                    <div>
                        <strong>
                            Cohort Progress
                        </strong>
                        <span>
                            View programme and
                            cohort reporting
                        </span>
                    </div>
                </a>
            </section>
            <!-- ====================================================
                 COHORTS + ATTENTION
            ===================================================== -->
            <div class="sv-grid sv-grid--main">
                <!-- =================================================
                     MY COHORTS
                ================================================== -->
                <section class="sv-card">
                    <div class="sv-card__header">
                        <div class="sv-card__heading">
                            <h2>
                                My Cohorts
                            </h2>
                            <p>
                                Progress across your
                                assigned cohorts.
                            </p>
                        </div>
                        <a
                            href="<?= url(
                                'supervisor/cohorts.php'
                            ) ?>"
                            class="sv-card__link"
                        >
                            View all
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    <?php if (
                        empty($cohorts)
                    ): ?>
                        <div class="sv-empty">
                            <div class="sv-empty__icon">
                                <i class="fas fa-layer-group"></i>
                            </div>
                            <strong>
                                No cohorts assigned
                            </strong>
                            <span>
                                Your assigned cohorts
                                will appear here.
                            </span>
                        </div>
                    <?php else: ?>
                        <div class="sv-cohort-list">
                            <?php foreach (
                                $cohorts
                                as $cohort
                            ): ?>
                                <?php
                                $statusClass =
                                    supervisorDashboardStatusClass(
                                        $cohort[
                                            'cohort_status'
                                        ]
                                    );
                                $completion =
                                    (int)(
                                        $cohort[
                                            'completion_rate'
                                        ]
                                        ?? 0
                                    );
                                ?>
                                <article class="sv-cohort-row">
                                    <!-- COHORT -->
                                    <div class="sv-cohort">
                                        <div class="sv-cohort__icon">
                                            <i class="fas fa-layer-group"></i>
                                        </div>
                                        <div class="sv-cohort__copy">
                                            <strong>
                                                <?= e(
                                                    $cohort[
                                                        'cohort_name'
                                                    ]
                                                ) ?>
                                            </strong>
                                            <span>
                                                <?= e(
                                                    $cohort[
                                                        'programme_name'
                                                    ]
                                                ) ?>
                                                ·
                                                <?= number_format(
                                                    (int)$cohort[
                                                        'current_candidates'
                                                    ]
                                                ) ?>
                                                candidates
                                            </span>
                                        </div>
                                    </div>
                                    <!-- STATUS -->
                                    <span
                                        class="
                                            sv-status
                                            sv-status--<?= e(
                                                $statusClass
                                            ) ?>
                                        "
                                    >
                                        <?= e(
                                            supervisorDashboardLabel(
                                                $cohort[
                                                    'cohort_status'
                                                ]
                                            )
                                        ) ?>
                                    </span>
                                    <!-- PROGRESS -->
                                    <div class="sv-progress">
                                        <div class="sv-progress__meta">
                                            <span>
                                                Completion
                                            </span>
                                            <strong>
                                                <?= $completion ?>%
                                            </strong>
                                        </div>
                                        <div class="sv-progress__track">
                                            <div
                                                class="sv-progress__bar"
                                                style="
                                                    width:
                                                    <?= $completion ?>%;
                                                "
                                            ></div>
                                        </div>
                                    </div>
                                    <!-- ACTION -->
                                    <a
                                        href="<?= url(
                                            'supervisor/cohort_view.php?id=' .
                                            (int)$cohort[
                                                'id'
                                            ]
                                        ) ?>"
                                        class="sv-icon-btn"
                                        title="View cohort"
                                        aria-label="View cohort"
                                    >
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
                <!-- =================================================
                     ATTENTION NEEDED
                ================================================== -->
                <section class="sv-card">
                    <div class="sv-card__header">
                        <div class="sv-card__heading">
                            <h2>
                                Attention Needed
                            </h2>
                            <p>
                                Cohorts approaching
                                their end dates.
                            </p>
                        </div>
                        <i
                            class="fas fa-triangle-exclamation"
                            style="
                                color:
                                var(--sv-orange);
                            "
                        ></i>
                    </div>
                    <div class="sv-attention">
                        <?php if (
                            empty($attentionRows)
                        ): ?>
                            <div class="sv-empty">
                                <div class="sv-empty__icon">
                                    <i class="fas fa-circle-check"></i>
                                </div>
                                <strong>
                                    Everything looks good
                                </strong>
                                <span>
                                    No cohorts currently
                                    require urgent attention.
                                </span>
                            </div>
                        <?php else: ?>
                            <?php foreach (
                                $attentionRows
                                as $attention
                            ): ?>
                                <article class="sv-attention__item">
                                    <div class="sv-attention__header">
                                        <strong>
                                            <?= e(
                                                $attention[
                                                    'cohort_name'
                                                ]
                                            ) ?>
                                        </strong>
                                        <span class="sv-attention__count">
                                            <?= number_format(
                                                (int)$attention[
                                                    'active_candidates'
                                                ]
                                            ) ?>
                                            active
                                        </span>
                                    </div>
                                    <p>
                                        This cohort ends on
                                        <strong>
                                            <?= e(
                                                supervisorDashboardDate(
                                                    $attention[
                                                        'end_date'
                                                    ]
                                                )
                                            ) ?>
                                        </strong>
                                        and still has
                                        active candidates.
                                    </p>
                                    <a
                                        href="<?= url(
                                            'supervisor/cohort_view.php?id=' .
                                            (int)$attention[
                                                'cohort_id'
                                            ]
                                        ) ?>"
                                    >
                                        Review cohort
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
            <!-- ====================================================
                 RECENT CANDIDATES
            ===================================================== -->
            <section class="sv-card">
                <div class="sv-card__header">
                    <div class="sv-card__heading">
                        <h2>
                            Recent Candidates
                        </h2>
                        <p>
                            Recent candidate activity
                            in your assigned cohorts.
                        </p>
                    </div>
                    <a
                        href="<?= url(
                            'supervisor/candidates.php'
                        ) ?>"
                        class="sv-card__link"
                    >
                        View candidates
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                <?php if (
                    empty($recentCandidates)
                ): ?>
                    <div class="sv-empty">
                        <div class="sv-empty__icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <strong>
                            No candidates found
                        </strong>
                        <span>
                            Candidate activity will
                            appear here once candidates
                            are available in your cohorts.
                        </span>
                    </div>
                <?php else: ?>
                    <div class="sv-candidate-grid">
                        <?php foreach (
                            $recentCandidates
                            as $candidate
                        ): ?>
                            <?php
                            $candidateFirstName =
                                trim(
                                    (string)(
                                        $candidate[
                                            'first_name'
                                        ]
                                        ?? ''
                                    )
                                );
                            $candidateLastName =
                                trim(
                                    (string)(
                                        $candidate[
                                            'last_name'
                                        ]
                                        ?? ''
                                    )
                                );
                            $candidateName =
                                trim(
                                    $candidateFirstName .
                                    ' ' .
                                    $candidateLastName
                                );
                            if (
                                $candidateName === ''
                            ) {
                                $candidateName =
                                    'Candidate';
                            }
                            $candidateStatus =
                                supervisorDashboardStatusClass(
                                    $candidate[
                                        'participant_status'
                                    ]
                                );
                            ?>
                            <article class="sv-candidate-card">
                                <div class="sv-candidate-card__header">
                                    <div class="sv-avatar">
                                        <?= e(
                                            supervisorDashboardInitials(
                                                $candidateFirstName,
                                                $candidateLastName
                                            )
                                        ) ?>
                                    </div>
                                    <div class="sv-candidate-card__identity">
                                        <strong>
                                            <?= e(
                                                $candidateName
                                            ) ?>
                                        </strong>
                                        <span>
                                            <?= e(
                                                $candidate[
                                                    'programme_name'
                                                ]
                                            ) ?>
                                        </span>
                                    </div>
                                    <span
                                        class="
                                            sv-status
                                            sv-status--<?= e(
                                                $candidateStatus
                                            ) ?>
                                        "
                                    >
                                        <?= e(
                                            supervisorDashboardLabel(
                                                $candidate[
                                                    'participant_status'
                                                ]
                                            )
                                        ) ?>
                                    </span>
                                </div>
                                <div class="sv-candidate-card__footer">
                                    <span class="sv-candidate-card__cohort">
                                        <i class="fas fa-layer-group"></i>
                                        <?= e(
                                            $candidate[
                                                'cohort_name'
                                            ]
                                        ) ?>
                                    </span>
                                    <a
                                        href="<?= url(
                                            'supervisor/candidate_view.php?id=' .
                                            (int)$candidate[
                                                'candidate_id'
                                            ] .
                                            '&cohort_id=' .
                                            (int)$candidate[
                                                'cohort_id'
                                            ]
                                        ) ?>"
                                        class="sv-candidate-card__link"
                                    >
                                        View
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
            <!-- ====================================================
                 SECURITY
            ===================================================== -->
            <div class="sv-security">
                <div class="sv-security__icon">
                    <i class="fas fa-shield-halved"></i>
                </div>
                <div>
                    <strong>
                        Supervisor-scoped workspace
                    </strong>
                    <p>
                        Dashboard information is restricted
                        to cohorts assigned to your Supervisor
                        account and candidates participating
                        in those cohorts. Administrative
                        programme management and candidate
                        assignment remain restricted.
                    </p>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>