<?php
/**
 * ================================================
 * INVESTHOOD IT - Programme Manager Candidate View
 * ================================================
 * File: programme/candidate_view.php
 * Role: Programme Manager
 *
 * Displays a candidate's:
 * - Profile
 * - Programme
 * - Cohort
 * - Participation status
 * - Participation timeline
 *
 * Data sources:
 * - users
 * - programmes
 * - cohorts
 * - cohort_participants
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('programme_manager');
$user = current_user();
$flashes = render_flashes();
$conn = Database::getConnection();
$currentPage = 'candidates';
/*
|--------------------------------------------------------------------------
| Current Programme Manager
|--------------------------------------------------------------------------
*/
$managerId = (int) ($user['user_id'] ?? 0);
/*
|--------------------------------------------------------------------------
| Candidate ID
|--------------------------------------------------------------------------
*/
$candidateId = (int) ($_GET['id'] ?? 0);
if ($candidateId <= 0) {
    header(
        'Location: ' . url('programme/candidates.php')
    );
    exit;
}
/*
|--------------------------------------------------------------------------
| Candidate Data
|--------------------------------------------------------------------------
|
| Important:
| The candidate must belong to a programme assigned
| to the currently logged-in Programme Manager.
|
*/
$candidate = null;
$stmt = $conn->prepare("
    SELECT
        cp.id AS participant_id,
        cp.user_id,
        cp.cohort_id,
        cp.status AS participation_status,
        cp.selected_at,
        cp.onboarded_at,
        cp.completed_at,
        cp.created_at AS participation_created_at,
        cp.updated_at AS participation_updated_at,
        u.first_name,
        u.last_name,
        u.email,
        u.phone,
        u.status AS user_status,
        c.name AS cohort_name,
        p.id AS programme_id,
        p.name AS programme_name,
        p.type AS programme_type,
        p.description AS programme_description,
        p.objectives AS programme_objectives,
        p.duration AS programme_duration,
        p.start_date AS programme_start_date,
        p.end_date AS programme_end_date,
        p.status AS programme_status
    FROM cohort_participants cp
    INNER JOIN users u
        ON u.id = cp.user_id
    INNER JOIN cohorts c
        ON c.id = cp.cohort_id
    INNER JOIN programmes p
        ON p.id = c.programme_id
    WHERE cp.user_id = ?
      AND p.programme_manager_id = ?
    LIMIT 1
");
if ($stmt) {
    $stmt->bind_param(
        'ii',
        $candidateId,
        $managerId
    );
    $stmt->execute();
    $result = $stmt->get_result();
    $candidate = $result->fetch_assoc();
    $stmt->close();
}
/*
|--------------------------------------------------------------------------
| Candidate Not Found
|--------------------------------------------------------------------------
*/
if (!$candidate) {
    $_SESSION['flash_error'] =
        'Candidate not found or you do not have permission to view this candidate.';
    header(
        'Location: ' . url('programme/candidates.php')
    );
    exit;
}
/*
|--------------------------------------------------------------------------
| Candidate Full Name
|--------------------------------------------------------------------------
*/
$candidateName = trim(
    ($candidate['first_name'] ?? '') .
    ' ' .
    ($candidate['last_name'] ?? '')
);
if ($candidateName === '') {
    $candidateName = 'Candidate';
}
/*
|--------------------------------------------------------------------------
| Status
|--------------------------------------------------------------------------
*/
$participationStatus =
    $candidate['participation_status'] ?? '';
$displayStatus = ucwords(
    str_replace(
        '_',
        ' ',
        $participationStatus
    )
);
/*
|--------------------------------------------------------------------------
| Status Class
|--------------------------------------------------------------------------
*/
$statusClass = 'primary';
switch ($participationStatus) {
    case 'active':
    case 'onboarded':
        $statusClass = 'cyan';
        break;
    case 'completed':
        $statusClass = 'primary';
        break;
    case 'withdrawn':
        $statusClass = 'amber';
        break;
    case 'selected':
        $statusClass = 'cyan';
        break;
    default:
        $statusClass = 'primary';
        break;
}
/*
|--------------------------------------------------------------------------
| Helper - Format Date
|--------------------------------------------------------------------------
*/
function format_candidate_date($date)
{
    if (empty($date)) {
        return 'Not recorded';
    }
    $timestamp = strtotime($date);
    if (!$timestamp) {
        return 'Not recorded';
    }
    return date(
        'd M Y',
        $timestamp
    );
}
/*
|--------------------------------------------------------------------------
| Timeline
|--------------------------------------------------------------------------
*/
$timeline = [];
/*
|--------------------------------------------------------------------------
| Selected
|--------------------------------------------------------------------------
*/
if (!empty($candidate['selected_at'])) {
    $timeline[] = [
        'title' => 'Candidate Selected',
        'description' =>
            'Candidate was selected for the programme.',
        'date' =>
            $candidate['selected_at'],
        'icon' =>
            'fa-user-check',
        'class' =>
            'cyan'
    ];
}
/*
|--------------------------------------------------------------------------
| Onboarded
|--------------------------------------------------------------------------
*/
if (!empty($candidate['onboarded_at'])) {
    $timeline[] = [
        'title' => 'Candidate Onboarded',
        'description' =>
            'Candidate was successfully onboarded into the programme.',
        'date' =>
            $candidate['onboarded_at'],
        'icon' =>
            'fa-user-plus',
        'class' =>
            'cyan'
    ];
}
/*
|--------------------------------------------------------------------------
| Completed
|--------------------------------------------------------------------------
*/
if (!empty($candidate['completed_at'])) {
    $timeline[] = [
        'title' => 'Programme Completed',
        'description' =>
            'Candidate completed the programme.',
        'date' =>
            $candidate['completed_at'],
        'icon' =>
            'fa-graduation-cap',
        'class' =>
            'primary'
    ];
}
/*
|--------------------------------------------------------------------------
| Withdrawn
|--------------------------------------------------------------------------
*/
if ($participationStatus === 'withdrawn') {
    $timeline[] = [
        'title' => 'Candidate Withdrawn',
        'description' =>
            'Candidate is no longer participating in the programme.',
        'date' =>
            $candidate['participation_updated_at'] ??
            $candidate['participation_created_at'],
        'icon' =>
            'fa-user-minus',
        'class' =>
            'amber'
    ];
}
/*
|--------------------------------------------------------------------------
| Participation Created
|--------------------------------------------------------------------------
*/
if (
    !empty($candidate['participation_created_at']) &&
    empty($candidate['selected_at']) &&
    empty($candidate['onboarded_at']) &&
    empty($candidate['completed_at'])
) {
    $timeline[] = [
        'title' => 'Candidate Added',
        'description' =>
            'Candidate was added to the programme cohort.',
        'date' =>
            $candidate['participation_created_at'],
        'icon' =>
            'fa-user',
        'class' =>
            'primary'
    ];
}
/*
|--------------------------------------------------------------------------
| Reverse Timeline
|--------------------------------------------------------------------------
*/
usort(
    $timeline,
    function ($a, $b) {
        return strtotime($a['date'])
            <=> strtotime($b['date']);
    }
);
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
        <?= e($candidateName) ?>
        | Candidate | Investhood IT
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
        <header class="dash-header">
            <div class="dash-header__left">
                <h1 class="dash-header__title">
                    Candidate Profile
                </h1>
            </div>
            <div class="dash-header__right">
                <div class="dash-header__user">
                    <img
                        src="https://ui-avatars.com/api/?name=<?= urlencode($candidateName) ?>&background=1a56db&color=fff&size=80"
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
            <?= $flashes ?>
            <!-- =================================================
                 BACK NAVIGATION
            ================================================== -->
            <div
                style="
                    display:flex;
                    flex-wrap:wrap;
                    gap:0.75rem;
                    margin-bottom:1.5rem;
                "
            >
                <a
                    href="<?= url('programme/candidates.php') ?>"
                    class="sidebar__link"
                    style="
                        display:inline-flex;
                        align-items:center;
                        gap:0.5rem;
                    "
                >
                    <i class="fas fa-arrow-left"></i>
                    Back to Candidates
                </a>
                <a
                    href="<?= url(
                        'programme/programme_view.php?id=' .
                        (int) $candidate['programme_id']
                    ) ?>"
                    class="sidebar__link"
                    style="
                        display:inline-flex;
                        align-items:center;
                        gap:0.5rem;
                    "
                >
                    <i class="fas fa-graduation-cap"></i>
                    View Programme
                </a>
                <a
                    href="<?= url(
                        'programme/cohort_view.php?id=' .
                        (int) $candidate['cohort_id']
                    ) ?>"
                    class="sidebar__link"
                    style="
                        display:inline-flex;
                        align-items:center;
                        gap:0.5rem;
                    "
                >
                    <i class="fas fa-users"></i>
                    View Cohort
                </a>
            </div>
            <!-- =================================================
                 PROFILE HEADER
            ================================================== -->
            <div class="welcome-card">
                <div class="welcome-card__bg"></div>
                <div class="welcome-card__content">
                    <div
                        style="
                            display:flex;
                            align-items:center;
                            gap:1.25rem;
                            flex-wrap:wrap;
                        "
                    >
                        <img
                            src="https://ui-avatars.com/api/?name=<?= urlencode($candidateName) ?>&background=1a56db&color=fff&size=120"
                            alt="<?= e($candidateName) ?>"
                            style="
                                width:90px;
                                height:90px;
                                border-radius:50%;
                            "
                        >
                        <div>
                            <h1
                                class="welcome-card__greeting"
                                style="margin-bottom:0.35rem;"
                            >
                                <?= e($candidateName) ?>
                            </h1>
                            <p style="margin:0;">
                                <?= e(
                                    $candidate['email'] ??
                                    'No email available'
                                ) ?>
                            </p>
                            <?php if (!empty($candidate['phone'])): ?>
                                <p
                                    style="
                                        margin:0.35rem 0 0;
                                    "
                                >
                                    <i class="fas fa-phone"></i>
                                    <?= e(
                                        $candidate['phone']
                                    ) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <!-- =================================================
                 SUMMARY
            ================================================== -->
            <div
                class="overview-grid"
                style="margin-top:2rem;"
            >
                <!-- Status -->
                <div class="overview-card">
                    <div
                        class="overview-card__icon overview-card__icon--<?= e($statusClass) ?>"
                    >
                        <i class="fas fa-circle-check"></i>
                    </div>
                    <div class="overview-card__info">
                        <span class="overview-card__number">
                            <?= e($displayStatus) ?>
                        </span>
                        <span class="overview-card__label">
                            Participation Status
                        </span>
                    </div>
                </div>
                <!-- Programme -->
                <div class="overview-card">
                    <div
                        class="overview-card__icon overview-card__icon--primary"
                    >
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="overview-card__info">
                        <span
                            class="overview-card__label"
                            style="
                                font-size:1rem;
                                font-weight:700;
                            "
                        >
                            <?= e(
                                $candidate['programme_name']
                            ) ?>
                        </span>
                        <span class="overview-card__label">
                            Programme
                        </span>
                    </div>
                </div>
                <!-- Cohort -->
                <div class="overview-card">
                    <div
                        class="overview-card__icon overview-card__icon--cyan"
                    >
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="overview-card__info">
                        <span
                            class="overview-card__label"
                            style="
                                font-size:1rem;
                                font-weight:700;
                            "
                        >
                            <?= e(
                                $candidate['cohort_name']
                            ) ?>
                        </span>
                        <span class="overview-card__label">
                            Cohort
                        </span>
                    </div>
                </div>
                <!-- User Status -->
                <div class="overview-card">
                    <div
                        class="overview-card__icon overview-card__icon--primary"
                    >
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="overview-card__info">
                        <span
                            class="overview-card__label"
                            style="
                                font-size:1rem;
                                font-weight:700;
                            "
                        >
                            <?= e(
                                ucfirst(
                                    $candidate['user_status'] ??
                                    'Unknown'
                                )
                            ) ?>
                        </span>
                        <span class="overview-card__label">
                            Account Status
                        </span>
                    </div>
                </div>
            </div>
            <!-- =================================================
                 CANDIDATE INFORMATION
            ================================================== -->
            <div
                style="
                    display:grid;
                    grid-template-columns:
                        repeat(auto-fit,minmax(320px,1fr));
                    gap:1.5rem;
                    margin-top:2rem;
                "
            >
                <!-- =================================================
                     PERSONAL INFORMATION
                ================================================== -->
                <div class="welcome-card">
                    <div class="welcome-card__content">
                        <h2
                            style="
                                margin-bottom:1.25rem;
                            "
                        >
                            <i class="fas fa-user"></i>
                            Candidate Information
                        </h2>
                        <div style="display:grid;gap:1rem;">
                            <div>
                                <strong>
                                    Full Name
                                </strong>
                                <div>
                                    <?= e($candidateName) ?>
                                </div>
                            </div>
                            <div>
                                <strong>
                                    Email
                                </strong>
                                <div>
                                    <?= e(
                                        $candidate['email'] ??
                                        'Not provided'
                                    ) ?>
                                </div>
                            </div>
                            <div>
                                <strong>
                                    Phone
                                </strong>
                                <div>
                                    <?= e(
                                        $candidate['phone'] ??
                                        'Not provided'
                                    ) ?>
                                </div>
                            </div>
                            <div>
                                <strong>
                                    Account Status
                                </strong>
                                <div>
                                    <?= e(
                                        ucfirst(
                                            $candidate['user_status'] ??
                                            'Unknown'
                                        )
                                    ) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- =================================================
                     PROGRAMME INFORMATION
                ================================================== -->
                <div class="welcome-card">
                    <div class="welcome-card__content">
                        <h2
                            style="
                                margin-bottom:1.25rem;
                            "
                        >
                            <i class="fas fa-graduation-cap"></i>
                            Programme Information
                        </h2>
                        <div style="display:grid;gap:1rem;">
                            <div>
                                <strong>
                                    Programme
                                </strong>
                                <div>
                                    <?= e(
                                        $candidate['programme_name']
                                    ) ?>
                                </div>
                            </div>
                            <div>
                                <strong>
                                    Programme Type
                                </strong>
                                <div>
                                    <?= e(
                                        ucwords(
                                            str_replace(
                                                '_',
                                                ' ',
                                                $candidate[
                                                    'programme_type'
                                                ] ?? ''
                                            )
                                        )
                                    ) ?>
                                </div>
                            </div>
                            <div>
                                <strong>
                                    Cohort
                                </strong>
                                <div>
                                    <?= e(
                                        $candidate['cohort_name']
                                    ) ?>
                                </div>
                            </div>
                            <div>
                                <strong>
                                    Programme Status
                                </strong>
                                <div>
                                    <?= e(
                                        ucfirst(
                                            $candidate[
                                                'programme_status'
                                            ] ?? ''
                                        )
                                    ) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- =================================================
                 PROGRAMME DATES
            ================================================== -->
            <div
                class="welcome-card"
                style="margin-top:1.5rem;"
            >
                <div class="welcome-card__content">
                    <h2
                        style="
                            margin-bottom:1.25rem;
                        "
                    >
                        <i class="fas fa-calendar"></i>
                        Programme Schedule
                    </h2>
                    <div
                        style="
                            display:grid;
                            grid-template-columns:
                                repeat(
                                    auto-fit,
                                    minmax(180px,1fr)
                                );
                            gap:1.5rem;
                        "
                    >
                        <div>
                            <strong>
                                Start Date
                            </strong>
                            <div style="margin-top:0.35rem;">
                                <?= e(
                                    format_candidate_date(
                                        $candidate[
                                            'programme_start_date'
                                        ]
                                    )
                                ) ?>
                            </div>
                        </div>
                        <div>
                            <strong>
                                End Date
                            </strong>
                            <div style="margin-top:0.35rem;">
                                <?= e(
                                    format_candidate_date(
                                        $candidate[
                                            'programme_end_date'
                                        ]
                                    )
                                ) ?>
                            </div>
                        </div>
                        <div>
                            <strong>
                                Duration
                            </strong>
                            <div style="margin-top:0.35rem;">
                                <?= e(
                                    $candidate[
                                        'programme_duration'
                                    ] ??
                                    'Not specified'
                                ) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- =================================================
                 PARTICIPATION TIMELINE
            ================================================== -->
            <div
                class="welcome-card"
                style="margin-top:1.5rem;"
            >
                <div class="welcome-card__content">
                    <h2
                        style="
                            margin-bottom:1.5rem;
                        "
                    >
                        <i class="fas fa-clock"></i>
                        Participation Timeline
                    </h2>
                    <?php if (empty($timeline)): ?>
                        <p>
                            No participation timeline events
                            have been recorded yet.
                        </p>
                    <?php else: ?>
                        <div
                            style="
                                display:flex;
                                flex-direction:column;
                                gap:1.25rem;
                            "
                        >
                            <?php foreach ($timeline as $event): ?>
                                <div
                                    style="
                                        display:flex;
                                        gap:1rem;
                                        align-items:flex-start;
                                    "
                                >
                                    <div
                                        class="overview-card__icon overview-card__icon--<?= e($event['class']) ?>"
                                        style="
                                            flex-shrink:0;
                                            width:45px;
                                            height:45px;
                                        "
                                    >
                                        <i
                                            class="fas <?= e($event['icon']) ?>"
                                        ></i>
                                    </div>
                                    <div>
                                        <strong
                                            style="
                                                font-size:1rem;
                                            "
                                        >
                                            <?= e(
                                                $event['title']
                                            ) ?>
                                        </strong>
                                        <div
                                            style="
                                                margin-top:0.25rem;
                                            "
                                        >
                                            <?= e(
                                                $event['description']
                                            ) ?>
                                        </div>
                                        <small
                                            style="
                                                display:block;
                                                margin-top:0.35rem;
                                            "
                                        >
                                            <i class="fas fa-calendar"></i>
                                            <?= e(
                                                format_candidate_date(
                                                    $event['date']
                                                )
                                            ) ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <!-- =================================================
                 DESCRIPTION
            ================================================== -->
            <?php if (
                !empty(
                    $candidate['programme_description']
                )
            ): ?>
                <div
                    class="welcome-card"
                    style="margin-top:1.5rem;"
                >
                    <div class="welcome-card__content">
                        <h2
                            style="
                                margin-bottom:1rem;
                            "
                        >
                            <i class="fas fa-info-circle"></i>
                            Programme Description
                        </h2>
                        <p>
                            <?= nl2br(
                                e(
                                    $candidate[
                                        'programme_description'
                                    ]
                                )
                            ) ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>
            <!-- =================================================
                 OBJECTIVES
            ================================================== -->
            <?php if (
                !empty(
                    $candidate['programme_objectives']
                )
            ): ?>
                <div
                    class="welcome-card"
                    style="margin-top:1.5rem;"
                >
                    <div class="welcome-card__content">
                        <h2
                            style="
                                margin-bottom:1rem;
                            "
                        >
                            <i class="fas fa-bullseye"></i>
                            Programme Objectives
                        </h2>
                        <p>
                            <?= nl2br(
                                e(
                                    $candidate[
                                        'programme_objectives'
                                    ]
                                )
                            ) ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>
            <!-- =================================================
                 ACTIONS
            ================================================== -->
            <div
                style="
                    display:flex;
                    flex-wrap:wrap;
                    gap:0.75rem;
                    margin-top:2rem;
                "
            >
                <a
                    href="<?= url('programme/candidates.php') ?>"
                    class="sidebar__link"
                    style="
                        display:inline-flex;
                        align-items:center;
                        gap:0.5rem;
                    "
                >
                    <i class="fas fa-arrow-left"></i>
                    Back to Candidates
                </a>
                <a
                    href="<?= url(
                        'programme/cohort_view.php?id=' .
                        (int) $candidate['cohort_id']
                    ) ?>"
                    class="sidebar__link"
                    style="
                        display:inline-flex;
                        align-items:center;
                        gap:0.5rem;
                    "
                >
                    <i class="fas fa-users"></i>
                    View Cohort
                </a>
                <a
                    href="<?= url(
                        'programme/programme_view.php?id=' .
                        (int) $candidate['programme_id']
                    ) ?>"
                    class="sidebar__link"
                    style="
                        display:inline-flex;
                        align-items:center;
                        gap:0.5rem;
                    "
                >
                    <i class="fas fa-graduation-cap"></i>
                    View Programme
                </a>
            </div>
        </div>
    </main>
</div>
</body>
</html>