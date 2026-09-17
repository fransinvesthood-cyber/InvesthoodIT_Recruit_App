<?php
/**
 * ================================================
 * INVESTHOOD IT - Programme Manager Dashboard
 * ================================================
 * Role: Programme Manager
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('programme_manager');

$user = current_user();

$conn = Database::getConnection();
$currentPage = 'dashboard';
/*
|--------------------------------------------------------------------------
| Current Programme Manager
|--------------------------------------------------------------------------
|
| current_user() returns:
|
| user_id
| username
| fullname
| email
| role
| role_name
|
| Your current Programme Manager has user_id = 2.
|
*/
$managerId = (int) ($user['user_id'] ?? $user['id'] ?? 0);
/*
|--------------------------------------------------------------------------
| Initialise Dashboard Statistics
|--------------------------------------------------------------------------
*/
$totalProgrammes      = 0;
$activeProgrammes     = 0;
$draftProgrammes      = 0;
$pausedProgrammes     = 0;
$completedProgrammes  = 0;
$totalCohorts         = 0;
$totalCandidates      = 0;
$activeCandidates     = 0;
$completedCandidates  = 0;
$withdrawnCandidates  = 0;
/*
|--------------------------------------------------------------------------
| Manager Validation
|--------------------------------------------------------------------------
*/
if ($managerId <= 0) {
    die('Unable to determine the logged-in Programme Manager.');
}
/*
|--------------------------------------------------------------------------
| Programme Statistics
|--------------------------------------------------------------------------
|
| These statistics are based ONLY on programmes assigned
| to the logged-in Programme Manager.
|
*/
$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_programmes,
        SUM(
            CASE
                WHEN status = 'active'
                THEN 1
                ELSE 0
            END
        ) AS active_programmes,
        SUM(
            CASE
                WHEN status = 'draft'
                THEN 1
                ELSE 0
            END
        ) AS draft_programmes,
        SUM(
            CASE
                WHEN status = 'paused'
                THEN 1
                ELSE 0
            END
        ) AS paused_programmes,
        SUM(
            CASE
                WHEN status = 'completed'
                THEN 1
                ELSE 0
            END
        ) AS completed_programmes
    FROM programmes
    WHERE programme_manager_id = ?
");
if ($stmt) {
    $stmt->bind_param('i', $managerId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $totalProgrammes = (int) (
            $row['total_programmes'] ?? 0
        );
        $activeProgrammes = (int) (
            $row['active_programmes'] ?? 0
        );
        $draftProgrammes = (int) (
            $row['draft_programmes'] ?? 0
        );
        $pausedProgrammes = (int) (
            $row['paused_programmes'] ?? 0
        );
        $completedProgrammes = (int) (
            $row['completed_programmes'] ?? 0
        );
    }
    $stmt->close();
}
/*
|--------------------------------------------------------------------------
| Cohort Statistics
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT
        COUNT(DISTINCT c.id) AS total_cohorts
    FROM cohorts c
    INNER JOIN programmes p
        ON p.id = c.programme_id
    WHERE p.programme_manager_id = ?
");
if ($stmt) {
    $stmt->bind_param('i', $managerId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $totalCohorts = (int) (
            $row['total_cohorts'] ?? 0
        );
    }
    $stmt->close();
}
/*
|--------------------------------------------------------------------------
| Total Candidates
|--------------------------------------------------------------------------
|
| DISTINCT user_id prevents the same candidate from being
| counted more than once if they appear in multiple records.
|
*/
$stmt = $conn->prepare("
    SELECT
        COUNT(DISTINCT cp.user_id) AS total_candidates
    FROM cohort_participants cp
    INNER JOIN cohorts c
        ON c.id = cp.cohort_id
    INNER JOIN programmes p
        ON p.id = c.programme_id
    WHERE p.programme_manager_id = ?
");
if ($stmt) {
    $stmt->bind_param('i', $managerId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $totalCandidates = (int) (
            $row['total_candidates'] ?? 0
        );
    }
    $stmt->close();
}
/*
|--------------------------------------------------------------------------
| Active Candidates
|--------------------------------------------------------------------------
|
| Active lifecycle statuses currently used by your system:
|
| selected
| onboarded
| active
|
*/
$stmt = $conn->prepare("
    SELECT
        COUNT(DISTINCT cp.user_id) AS active_candidates
    FROM cohort_participants cp
    INNER JOIN cohorts c
        ON c.id = cp.cohort_id
    INNER JOIN programmes p
        ON p.id = c.programme_id
    WHERE p.programme_manager_id = ?
      AND cp.status IN (
          'selected',
          'onboarded',
          'active'
      )
");
if ($stmt) {
    $stmt->bind_param('i', $managerId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $activeCandidates = (int) (
            $row['active_candidates'] ?? 0
        );
    }
    $stmt->close();
}
/*
|--------------------------------------------------------------------------
| Completed Candidates
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT
        COUNT(DISTINCT cp.user_id) AS completed_candidates
    FROM cohort_participants cp
    INNER JOIN cohorts c
        ON c.id = cp.cohort_id
    INNER JOIN programmes p
        ON p.id = c.programme_id
    WHERE p.programme_manager_id = ?
      AND cp.status = 'completed'
");
if ($stmt) {
    $stmt->bind_param('i', $managerId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $completedCandidates = (int) (
            $row['completed_candidates'] ?? 0
        );
    }
    $stmt->close();
}
/*
|--------------------------------------------------------------------------
| Withdrawn Candidates
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT
        COUNT(DISTINCT cp.user_id) AS withdrawn_candidates
    FROM cohort_participants cp
    INNER JOIN cohorts c
        ON c.id = cp.cohort_id
    INNER JOIN programmes p
        ON p.id = c.programme_id
    WHERE p.programme_manager_id = ?
      AND cp.status = 'withdrawn'
");
if ($stmt) {
    $stmt->bind_param('i', $managerId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $withdrawnCandidates = (int) (
            $row['withdrawn_candidates'] ?? 0
        );
    }
    $stmt->close();
}
/*
|--------------------------------------------------------------------------
| Overall Completion Percentage
|--------------------------------------------------------------------------
*/
$overallProgress = 0;
if ($totalCandidates > 0) {
    $overallProgress = round(
        ($completedCandidates / $totalCandidates) * 100
    );
}
$overallProgress = min(
    100,
    max(
        0,
        $overallProgress
    )
);
$overallProgressWidth = $overallProgress;
/*
|--------------------------------------------------------------------------
| Fetch Programme Portfolio
|--------------------------------------------------------------------------
|
| This is the main management table.
|
*/
$programmes = [];
$stmt = $conn->prepare("
    SELECT
        p.id,
        p.name,
        p.type,
        p.description,
        p.objectives,
        p.duration,
        p.start_date,
        p.end_date,
        p.status,
        p.programme_manager_id,
        p.created_at,
        COUNT(DISTINCT c.id) AS cohort_count,
        COUNT(
            DISTINCT CASE
                WHEN cp.status <> 'withdrawn'
                THEN cp.user_id
            END
        ) AS candidate_count,
        COUNT(
            DISTINCT CASE
                WHEN cp.status IN (
                    'selected',
                    'onboarded',
                    'active'
                )
                THEN cp.user_id
            END
        ) AS active_candidate_count,
        COUNT(
            DISTINCT CASE
                WHEN cp.status = 'completed'
                THEN cp.user_id
            END
        ) AS completed_candidate_count,
        COUNT(
            DISTINCT CASE
                WHEN cp.status = 'withdrawn'
                THEN cp.user_id
            END
        ) AS withdrawn_candidate_count
    FROM programmes p
    LEFT JOIN cohorts c
        ON c.programme_id = p.id
    LEFT JOIN cohort_participants cp
        ON cp.cohort_id = c.id
    WHERE p.programme_manager_id = ?
    GROUP BY
        p.id,
        p.name,
        p.type,
        p.description,
        p.objectives,
        p.duration,
        p.start_date,
        p.end_date,
        p.status,
        p.programme_manager_id,
        p.created_at
    ORDER BY
        CASE
            WHEN p.status = 'active' THEN 1
            WHEN p.status = 'draft' THEN 2
            WHEN p.status = 'paused' THEN 3
            WHEN p.status = 'completed' THEN 4
            WHEN p.status = 'archived' THEN 5
            ELSE 6
        END,
        p.start_date ASC,
        p.id ASC
");
if ($stmt) {
    $stmt->bind_param('i', $managerId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $candidateCount = (int) (
            $row['candidate_count'] ?? 0
        );
        $completedCount = (int) (
            $row['completed_candidate_count'] ?? 0
        );
        $progress = 0;
        if ($candidateCount > 0) {
            $progress = round(
                ($completedCount / $candidateCount) * 100
            );
        }
        $progress = min(
            100,
            max(
                0,
                $progress
            )
        );
        /*
        |--------------------------------------------------------------------------
        | Precalculate Progress Width
        |--------------------------------------------------------------------------
        |
        | We deliberately calculate this in PHP instead of putting
        | min()/casts directly inside HTML attributes.
        |
        | This avoids the "identifier expected" issue you encountered.
        |
        */
        $row['progress'] = $progress;
        $row['progress_width'] = $progress;
        $programmes[] = $row;
    }
    $stmt->close();
}
/*
|--------------------------------------------------------------------------
| Upcoming Programmes
|--------------------------------------------------------------------------
*/
$upcomingProgrammes = [];
$stmt = $conn->prepare("
    SELECT
        id,
        name,
        type,
        start_date,
        end_date,
        status
    FROM programmes
    WHERE programme_manager_id = ?
      AND start_date IS NOT NULL
    ORDER BY start_date ASC
    LIMIT 5
");
if ($stmt) {
    $stmt->bind_param('i', $managerId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $upcomingProgrammes[] = $row;
    }
    $stmt->close();
}
/*
|--------------------------------------------------------------------------
| Notification Bell (session-backed, no DB changes)
|--------------------------------------------------------------------------
*/

if (!function_exists('pm_notifications')) {
    /**
     * Return the current Programme Manager's notifications.
     * Seeds the session store once so the bell has something to show.
     */
    function pm_notifications(int $managerId): array
    {
        $key = 'pm_notifications_' . $managerId;

        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                [
                    'id'         => 1,
                    'title'      => 'New candidate assigned',
                    'message'    => 'A new candidate has been assigned to one of your cohorts.',
                    'type'       => 'info',
                    'created_at' => date('Y-m-d H:i:s', strtotime('-15 minutes')),
                    'read'       => false,
                    'url'        => url('programme/cohorts.php'),
                ],
                [
                    'id'         => 2,
                    'title'      => 'Programme status updated',
                    'message'    => 'One of your programmes has been marked as active.',
                    'type'       => 'success',
                    'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                    'read'       => false,
                    'url'        => url('programme/programme_view.php'),
                ],
                [
                    'id'         => 3,
                    'title'      => 'Cohort starting soon',
                    'message'    => 'A cohort in your portfolio starts within the next 7 days.',
                    'type'       => 'warning',
                    'created_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
                    'read'       => false,
                    'url'        => url('programme/cohorts.php'),
                ],
                [
                    'id'         => 4,
                    'title'      => 'Candidate completed',
                    'message'    => 'A candidate has successfully completed their programme.',
                    'type'       => 'success',
                    'created_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
                    'read'       => true,
                    'url'        => url('programme/candidates.php'),
                ],
                [
                    'id'         => 5,
                    'title'      => 'Weekly summary ready',
                    'message'    => 'Your weekly programme performance summary is available.',
                    'type'       => 'info',
                    'created_at' => date('Y-m-d H:i:s', strtotime('-4 days')),
                    'read'       => true,
                    'url'        => url('programme/reports.php'),
                ],
            ];
        }

        return $_SESSION[$key];
    }
}

$pmNotifications      = pm_notifications($managerId);
$pmUnreadCount        = 0;
$pmRecentNotifications = array_slice($pmNotifications, 0, 5);

foreach ($pmNotifications as $n) {
    if (empty($n['read'])) {
        $pmUnreadCount++;
    }
}

/**
 * Small helper for relative time in the dropdown.
 */
if (!function_exists('pm_time_ago')) {
    function pm_time_ago(string $datetime): string
    {
        $ts   = strtotime($datetime);
        $diff = time() - $ts;

        if ($diff < 60)        return 'Just now';
        if ($diff < 3600)      return floor($diff / 60) . 'm ago';
        if ($diff < 86400)     return floor($diff / 3600) . 'h ago';
        if ($diff < 604800)    return floor($diff / 86400) . 'd ago';

        return date('d M Y', $ts);
    }
}
/*
|--------------------------------------------------------------------------
| Helper Values
|--------------------------------------------------------------------------
*/
$managerName = $user['fullname'] ?? 'Programme Manager';
if (trim($managerName) === '') {
    $managerName = 'Programme Manager';
}

$flashes = render_flashes();

?>
<!DOCTYPE html>
<html lang="en">
<head>
<<<<<<< HEAD
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >
    <title>
        Programme Manager Dashboard | Investhood IT
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
        .pm-stat-grid {
            display: grid;
            grid-template-columns: repeat(
                auto-fit,
                minmax(220px, 1fr)
            );
            gap: 1rem;
            margin-top: 2rem;
        }
        .pm-section {
            margin-top: 2rem;
        }
        .pm-section-header {
            margin-bottom: 1rem;
        }
        .pm-section-header h2 {
            margin-bottom: 0.35rem;
        }
        .pm-section-header p {
            margin: 0;
        }
        .pm-programme-grid {
            display: grid;
            grid-template-columns: repeat(
                auto-fit,
                minmax(300px, 1fr)
            );
            gap: 1rem;
        }
        .pm-programme-card {
            background: #fff;
            border-radius: 14px;
            padding: 1.25rem;
            box-shadow: 0 4px 18px rgba(
                0,
                0,
                0,
                0.06
            );
        }
        .pm-programme-card__header {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            align-items: flex-start;
        }
        .pm-programme-card__title {
            font-size: 1.05rem;
            font-weight: 700;
            line-height: 1.4;
        }
        .pm-programme-card__meta {
            margin-top: 0.5rem;
            color: #6b7280;
            font-size: 0.9rem;
        }
        .pm-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.65rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            white-space: nowrap;
        }
        .pm-badge--active {
            background: #dcfce7;
            color: #166534;
        }
        .pm-badge--draft {
            background: #fef3c7;
            color: #92400e;
        }
        .pm-badge--paused {
            background: #e0e7ff;
            color: #3730a3;
        }
        .pm-badge--completed {
            background: #dbeafe;
            color: #1e40af;
        }
        .pm-badge--archived {
            background: #e5e7eb;
            color: #374151;
        }
        .pm-progress {
            margin-top: 1rem;
        }
        .pm-progress__header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.45rem;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .pm-progress__track {
            width: 100%;
            height: 8px;
            background: #e5e7eb;
            border-radius: 999px;
            overflow: hidden;
        }
        .pm-progress__bar {
            height: 100%;
            background: #1a56db;
            border-radius: 999px;
        }
        .pm-programme-stats {
            display: grid;
            grid-template-columns: repeat(
                2,
                1fr
            );
            gap: 0.75rem;
            margin-top: 1rem;
        }
        .pm-programme-stat {
            background: #f8fafc;
            border-radius: 10px;
            padding: 0.75rem;
        }
        .pm-programme-stat strong {
            display: block;
            font-size: 1.1rem;
        }
        .pm-programme-stat span {
            color: #6b7280;
            font-size: 0.78rem;
        }
        .pm-action-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        .pm-action {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.6rem 0.85rem;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            background: #1a56db;
            color: #fff;
        }
        .pm-action--secondary {
            background: #f3f4f6;
            color: #374151;
        }
        .pm-upcoming-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .pm-upcoming-item:last-child {
            border-bottom: 0;
        }
        .pm-date {
            font-weight: 700;
            color: #1a56db;
            white-space: nowrap;
        }
        .pm-empty {
            padding: 2rem;
            text-align: center;
            color: #6b7280;
        }
        @media (max-width: 700px) {
            .pm-programme-grid {
                grid-template-columns: 1fr;
            }
            .pm-programme-card__header {
                flex-direction: column;
            }
            .pm-upcoming-item {
                align-items: flex-start;
                flex-direction: column;
            }
        }

 /* =========================================================
   NOTIFICATION BELL
========================================================= */
.pm-notif {
    position: relative;
    margin-right: 0.75rem;
}

.pm-notif__btn {
    position: relative;
    width: 42px;
    height: 42px;
    border-radius: 50%;
    border: 1px solid #e5e7eb;
    background: #fff;
    color: #374151;
    font-size: 1.05rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}

.pm-notif__btn:hover {
    background: #f3f4f6;
    border-color: #d1d5db;
}

.pm-notif__badge {
    position: absolute;
    top: -4px;
    right: -4px;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    border-radius: 999px;
    background: #ef4444;
    color: #fff;
    font-size: 0.68rem;
    font-weight: 700;
    line-height: 18px;
    text-align: center;
    box-shadow: 0 0 0 2px #fff;
}

.pm-notif__dropdown {
    position: absolute;
    top: calc(100% + 10px);
    right: 0;
    width: 360px;
    max-width: calc(100vw - 2rem);
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    box-shadow: 0 18px 40px rgba(0, 0, 0, 0.12);
    opacity: 0;
    visibility: hidden;
    transform: translateY(-6px);
    transition: opacity 0.18s ease, transform 0.18s ease, visibility 0.18s;
    z-index: 999;
    overflow: hidden;
}

.pm-notif.is-open .pm-notif__dropdown {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.pm-notif__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.9rem 1rem;
    border-bottom: 1px solid #f1f5f9;
    font-size: 0.95rem;
}

.pm-notif__count {
    font-size: 0.75rem;
    font-weight: 700;
    color: #1a56db;
    background: #e0e7ff;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
}

.pm-notif__list {
    max-height: 340px;
    overflow-y: auto;
}

.pm-notif__item {
    display: flex;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    text-decoration: none;
    color: inherit;
    border-bottom: 1px solid #f8fafc;
    transition: background 0.15s ease;
}

.pm-notif__item:hover {
    background: #f8fafc;
}

.pm-notif__item.is-unread {
    background: #f5f8ff;
}

.pm-notif__item.is-unread:hover {
    background: #eef4ff;
}

.pm-notif__icon {
    flex-shrink: 0;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    background: #e0e7ff;
    color: #3730a3;
}

.pm-notif__item--success .pm-notif__icon {
    background: #dcfce7;
    color: #166534;
}

.pm-notif__item--warning .pm-notif__icon {
    background: #fef3c7;
    color: #92400e;
}

.pm-notif__body {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.pm-notif__title {
    font-size: 0.88rem;
    font-weight: 700;
    color: #111827;
}

.pm-notif__msg {
    font-size: 0.8rem;
    color: #6b7280;
    line-height: 1.35;
}

.pm-notif__time {
    font-size: 0.72rem;
    color: #9ca3af;
    margin-top: 0.15rem;
}

.pm-notif__empty {
    padding: 2rem 1rem;
    text-align: center;
    color: #6b7280;
}

.pm-notif__empty i {
    font-size: 1.6rem;
    margin-bottom: 0.5rem;
    display: block;
    color: #cbd5e1;
}

.pm-notif__empty p {
    margin: 0;
    font-size: 0.85rem;
}

.pm-notif__footer {
    border-top: 1px solid #f1f5f9;
    padding: 0.6rem;
    background: #fafbfc;
}

.pm-notif__viewall {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    width: 100%;
    padding: 0.6rem;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 700;
    color: #1a56db;
    text-decoration: none;
    transition: background 0.15s ease;
}

.pm-notif__viewall:hover {
    background: #eef2ff;
}
/* =========================================================
   ALL NOTIFICATIONS MODAL
========================================================= */
.pm-modal {
    position: fixed;
    inset: 0;
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.2s ease, visibility 0.2s;
}

.pm-modal.is-open {
    opacity: 1;
    visibility: visible;
}

.pm-modal__backdrop {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(2px);
}

.pm-modal__dialog {
    position: relative;
    width: 100%;
    max-width: 640px;
    max-height: calc(100vh - 2rem);
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.25);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    transform: translateY(12px) scale(0.98);
    transition: transform 0.22s ease;
}

.pm-modal.is-open .pm-modal__dialog {
    transform: translateY(0) scale(1);
}

/* ---------- Header ---------- */
.pm-modal__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.15rem 1.25rem;
    border-bottom: 1px solid #f1f5f9;
    background: #fff;
}

.pm-modal__title-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 0;
}

.pm-modal__title {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 700;
    color: #111827;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.pm-modal__title i {
    color: #1a56db;
}

.pm-modal__subtitle {
    font-size: 0.82rem;
    color: #6b7280;
}

.pm-modal__close {
    flex-shrink: 0;
    width: 36px;
    height: 36px;
    border: 1px solid #e5e7eb;
    border-radius: 50%;
    background: #fff;
    color: #4b5563;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}

.pm-modal__close:hover {
    background: #f3f4f6;
    color: #111827;
    border-color: #d1d5db;
}

/* ---------- Tabs ---------- */
.pm-modal__tabs {
    display: flex;
    gap: 0.4rem;
    padding: 0.75rem 1.25rem 0;
    border-bottom: 1px solid #f1f5f9;
    background: #fff;
    overflow-x: auto;
    scrollbar-width: thin;
}

.pm-modal__tab {
    border: 0;
    background: transparent;
    padding: 0.55rem 0.8rem;
    border-radius: 8px 8px 0 0;
    font-size: 0.85rem;
    font-weight: 600;
    color: #6b7280;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border-bottom: 2px solid transparent;
    white-space: nowrap;
    transition: color 0.15s ease, border-color 0.15s ease, background 0.15s ease;
}

.pm-modal__tab:hover {
    color: #1a56db;
    background: #f8fafc;
}

.pm-modal__tab.is-active {
    color: #1a56db;
    border-bottom-color: #1a56db;
}

.pm-modal__tab-count {
    background: #e5e7eb;
    color: #374151;
    border-radius: 999px;
    padding: 0.05rem 0.45rem;
    font-size: 0.72rem;
    font-weight: 700;
    min-width: 22px;
    text-align: center;
}

.pm-modal__tab.is-active .pm-modal__tab-count {
    background: #e0e7ff;
    color: #3730a3;
}

/* ---------- Scrollable body ---------- */
.pm-modal__body {
    flex: 1 1 auto;
    overflow-y: auto;
    min-height: 180px;
    max-height: 60vh;
    padding: 0.4rem 0;
    background: #fff;
}

.pm-modal__body::-webkit-scrollbar {
    width: 8px;
}

.pm-modal__body::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 8px;
}

.pm-modal__body::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}

/* ---------- Items ---------- */
.pm-modal__item {
    display: flex;
    gap: 0.9rem;
    padding: 0.95rem 1.25rem;
    text-decoration: none;
    color: inherit;
    border-bottom: 1px solid #f8fafc;
    transition: background 0.15s ease;
}

.pm-modal__item:last-child {
    border-bottom: 0;
}

.pm-modal__item:hover {
    background: #f8fafc;
}

.pm-modal__item.is-unread {
    background: #f5f8ff;
}

.pm-modal__item.is-unread:hover {
    background: #eef4ff;
}

.pm-modal__item.is-read {
    opacity: 0.88;
}

.pm-modal__item-icon {
    flex-shrink: 0;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    background: #e0e7ff;
    color: #3730a3;
}

.pm-modal__item--success .pm-modal__item-icon {
    background: #dcfce7;
    color: #166534;
}

.pm-modal__item--warning .pm-modal__item-icon {
    background: #fef3c7;
    color: #92400e;
}

.pm-modal__item-body {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 0;
    flex: 1;
}

.pm-modal__item-title {
    font-size: 0.92rem;
    font-weight: 700;
    color: #111827;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.pm-modal__dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #1a56db;
    flex-shrink: 0;
}

.pm-modal__item-msg {
    font-size: 0.83rem;
    color: #6b7280;
    line-height: 1.45;
}

.pm-modal__item-time {
    font-size: 0.75rem;
    color: #9ca3af;
    margin-top: 0.15rem;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

/* ---------- Empty state ---------- */
.pm-modal__empty {
    text-align: center;
    padding: 3rem 1.5rem;
    color: #6b7280;
}

.pm-modal__empty i {
    font-size: 2.2rem;
    color: #cbd5e1;
    margin-bottom: 0.75rem;
    display: block;
}

.pm-modal__empty h3 {
    margin: 0 0 0.35rem;
    color: #374151;
    font-size: 1rem;
}

.pm-modal__empty p {
    margin: 0;
    font-size: 0.85rem;
}

/* ---------- Footer ---------- */
.pm-modal__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.85rem 1.25rem;
    border-top: 1px solid #f1f5f9;
    background: #fafbfc;
}

.pm-modal__footer-note {
    font-size: 0.8rem;
    color: #6b7280;
}

.pm-modal__footer-btn {
    border: 1px solid #e5e7eb;
    background: #fff;
    color: #374151;
    font-size: 0.85rem;
    font-weight: 600;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.15s ease, border-color 0.15s ease;
}

.pm-modal__footer-btn:hover {
    background: #f3f4f6;
    border-color: #d1d5db;
}

/* ---------- Hidden filter state ---------- */
.pm-modal__item[hidden] {
    display: none;
}

/* ---------- Responsive ---------- */
@media (max-width: 640px) {
    .pm-modal {
        padding: 0;
        align-items: flex-end;
    }

    .pm-modal__dialog {
        max-width: 100%;
        max-height: 92vh;
        border-radius: 18px 18px 0 0;
    }

    .pm-modal__body {
        max-height: none;
    }

    .pm-modal__item {
        padding: 0.9rem 1rem;
    }

    .pm-modal__header,
    .pm-modal__tabs,
    .pm-modal__footer {
        padding-left: 1rem;
        padding-right: 1rem;
    }
}

/* =========================================================
   DARK MODE
========================================================= */
.dark-mode .pm-modal__dialog,
[data-theme="dark"] .pm-modal__dialog {
    background: #111827;
    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.6);
}

.dark-mode .pm-modal__header,
.dark-mode .pm-modal__tabs,
.dark-mode .pm-modal__body,
[data-theme="dark"] .pm-modal__header,
[data-theme="dark"] .pm-modal__tabs,
[data-theme="dark"] .pm-modal__body {
    background: #111827;
}

.dark-mode .pm-modal__header,
.dark-mode .pm-modal__tabs,
.dark-mode .pm-modal__footer,
[data-theme="dark"] .pm-modal__header,
[data-theme="dark"] .pm-modal__tabs,
[data-theme="dark"] .pm-modal__footer {
    border-color: #1f2937;
}

.dark-mode .pm-modal__title,
[data-theme="dark"] .pm-modal__title {
    color: #f9fafb;
}

.dark-mode .pm-modal__subtitle,
.dark-mode .pm-modal__footer-note,
[data-theme="dark"] .pm-modal__subtitle,
[data-theme="dark"] .pm-modal__footer-note {
    color: #9ca3af;
}

.dark-mode .pm-modal__close,
[data-theme="dark"] .pm-modal__close {
    background: #1f2937;
    border-color: #374151;
    color: #e5e7eb;
}

.dark-mode .pm-modal__close:hover,
[data-theme="dark"] .pm-modal__close:hover {
    background: #374151;
}

.dark-mode .pm-modal__tab,
[data-theme="dark"] .pm-modal__tab {
    color: #9ca3af;
}

.dark-mode .pm-modal__tab:hover,
[data-theme="dark"] .pm-modal__tab:hover {
    color: #93c5fd;
    background: #1f2937;
}

.dark-mode .pm-modal__tab.is-active,
[data-theme="dark"] .pm-modal__tab.is-active {
    color: #93c5fd;
    border-bottom-color: #3b82f6;
}

.dark-mode .pm-modal__tab-count,
[data-theme="dark"] .pm-modal__tab-count {
    background: #374151;
    color: #e5e7eb;
}

.dark-mode .pm-modal__tab.is-active .pm-modal__tab-count,
[data-theme="dark"] .pm-modal__tab.is-active .pm-modal__tab-count {
    background: #1e3a8a;
    color: #bfdbfe;
}

.dark-mode .pm-modal__body::-webkit-scrollbar-thumb,
[data-theme="dark"] .pm-modal__body::-webkit-scrollbar-thumb {
    background: #374151;
}

.dark-mode .pm-modal__item,
[data-theme="dark"] .pm-modal__item {
    color: #e5e7eb;
    border-bottom-color: #1f2937;
}

.dark-mode .pm-modal__item:hover,
[data-theme="dark"] .pm-modal__item:hover {
    background: #1f2937;
}

.dark-mode .pm-modal__item.is-unread,
[data-theme="dark"] .pm-modal__item.is-unread {
    background: #1e293b;
}

.dark-mode .pm-modal__item.is-unread:hover,
[data-theme="dark"] .pm-modal__item.is-unread:hover {
    background: #243449;
}

.dark-mode .pm-modal__item-title,
[data-theme="dark"] .pm-modal__item-title {
    color: #f9fafb;
}

.dark-mode .pm-modal__item-msg,
[data-theme="dark"] .pm-modal__item-msg {
    color: #9ca3af;
}

.dark-mode .pm-modal__item-time,
[data-theme="dark"] .pm-modal__item-time {
    color: #6b7280;
}

.dark-mode .pm-modal__footer,
[data-theme="dark"] .pm-modal__footer {
    background: #0f172a;
    border-top-color: #1f2937;
}

.dark-mode .pm-modal__footer-btn,
[data-theme="dark"] .pm-modal__footer-btn {
    background: #1f2937;
    border-color: #374151;
    color: #e5e7eb;
}

.dark-mode .pm-modal__footer-btn:hover,
[data-theme="dark"] .pm-modal__footer-btn:hover {
    background: #374151;
}

.dark-mode .pm-modal__empty h3,
[data-theme="dark"] .pm-modal__empty h3 {
    color: #e5e7eb;
}

.dark-mode .pm-modal__empty,
[data-theme="dark"] .pm-modal__empty {
    color: #9ca3af;
}

/* =========================================================
   DARK MODE SUPPORT
========================================================= */
body.dark-mode .pm-notif__btn,
html.dark-mode .pm-notif__btn {
    background: #1f2937;
    border-color: #374151;
    color: #e5e7eb;
}

body.dark-mode .pm-notif__btn:hover,
html.dark-mode .pm-notif__btn:hover {
    background: #374151;
}

body.dark-mode .pm-notif__dropdown,
html.dark-mode .pm-notif__dropdown {
    background: #111827;
    border-color: #374151;
    box-shadow: 0 18px 40px rgba(0, 0, 0, 0.55);
}

body.dark-mode .pm-notif__header,
html.dark-mode .pm-notif__header {
    border-bottom-color: #1f2937;
}

body.dark-mode .pm-notif__item,
html.dark-mode .pm-notif__item {
    border-bottom-color: #1f2937;
    color: #e5e7eb;
}

body.dark-mode .pm-notif__item:hover,
html.dark-mode .pm-notif__item:hover {
    background: #1f2937;
}

body.dark-mode .pm-notif__item.is-unread,
html.dark-mode .pm-notif__item.is-unread {
    background: #1e293b;
}

body.dark-mode .pm-notif__item.is-unread:hover,
html.dark-mode .pm-notif__item.is-unread:hover {
    background: #243449;
}

body.dark-mode .pm-notif__title,
html.dark-mode .pm-notif__title {
    color: #f9fafb;
}

body.dark-mode .pm-notif__msg,
html.dark-mode .pm-notif__msg {
    color: #9ca3af;
}

body.dark-mode .pm-notif__time,
html.dark-mode .pm-notif__time {
    color: #6b7280;
}

body.dark-mode .pm-notif__footer,
html.dark-mode .pm-notif__footer {
    background: #0f172a;
    border-top-color: #1f2937;
}

body.dark-mode .pm-notif__viewall,
html.dark-mode .pm-notif__viewall {
    color: #93c5fd;
}

body.dark-mode .pm-notif__viewall:hover,
html.dark-mode .pm-notif__viewall:hover {
    background: #1f2937;
}

body.dark-mode .pm-notif__count,
html.dark-mode .pm-notif__count {
    background: #1e3a8a;
    color: #bfdbfe;
}
.pm-progress-fill {
    height: 100%;
    background: #1a56db;
    border-radius: 999px;
}

/* Dark mode, if applicable */
.dark-mode .pm-progress-fill,
[data-theme="dark"] .pm-progress-fill {
    background: #3b82f6;
}


    </style>
=======
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Programme Manager Dashboard | Investhood IT</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
>>>>>>> 69ee3b9c4b9c5032917843f6a058edc276589649
</head>
<body class="dashboard-page">
  <div class="dashboard">
    <aside class="sidebar">
      <div class="sidebar__header">
        <a href="<?= url('index.php') ?>" class="logo">
          <span class="logo__icon"><i class="fas fa-code"></i></span>
          <span class="logo__text">Investhood <span class="logo__accent">IT</span></span>
        </a>
      </div>
      <nav class="sidebar__nav">
        <div class="sidebar__section-label">Programme Manager</div>
        <ul class="sidebar__menu">
          <li><a href="#" class="sidebar__link active"><i class="fas fa-th-large"></i> Dashboard</a></li>
          <li><a href="#" class="sidebar__link"><i class="fas fa-graduation-cap"></i> My Programmes</a></li>
          <li><a href="#" class="sidebar__link"><i class="fas fa-users"></i> Candidates</a></li>
          <li><a href="#" class="sidebar__link"><i class="fas fa-chart-line"></i> Reports</a></li>
        </ul>
      </nav>
      <div class="sidebar__footer">
        <div class="sidebar__user">
          <div class="sidebar__user-avatar"><img src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'PM+User') ?>&background=1a56db&color=fff&size=80" alt=""></div>
          <div class="sidebar__user-info">
            <span class="sidebar__user-name"><?= e($user['fullname'] ?? 'Programme Manager') ?></span>
            <span class="sidebar__user-role">Programme Manager</span>
          </div>
        </div>
        <a href="<?= url('auth/logout.php') ?>" class="sidebar__logout"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
      </div>
    </aside>
    <main class="dashboard__main">
<<<<<<< HEAD
        <!-- =====================================================
             HEADER
        ====================================================== -->
        <header class="dash-header">
            <div class="dash-header__left">
                <h1 class="dash-header__title">
                    Dashboard
                </h1>
            </div>
            <div class="dash-header__right">

    <!-- =========================================================
         NOTIFICATION BELL
    ========================================================== -->
    <div class="pm-notif" id="pmNotif">
        <button
            type="button"
            class="pm-notif__btn"
            id="pmNotifBtn"
            aria-haspopup="true"
            aria-expanded="false"
            aria-label="Notifications"
        >
            <i class="fas fa-bell"></i>
            <?php if ($pmUnreadCount > 0): ?>
                <span class="pm-notif__badge" id="pmNotifBadge">
                    <?= $pmUnreadCount > 99 ? '99+' : (int) $pmUnreadCount ?>
                </span>
            <?php endif; ?>
        </button>

        <div class="pm-notif__dropdown" id="pmNotifDropdown" role="menu">
            <div class="pm-notif__header">
                <strong>Notifications</strong>
                <?php if ($pmUnreadCount > 0): ?>
                    <span class="pm-notif__count">
                        <?= (int) $pmUnreadCount ?> unread
                    </span>
                <?php endif; ?>
            </div>

            <div class="pm-notif__list">
                <?php if (empty($pmRecentNotifications)): ?>
                    <div class="pm-notif__empty">
                        <i class="fas fa-bell-slash"></i>
                        <p>No notifications yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($pmRecentNotifications as $notif): ?>
                        <?php
                        $typeClass = 'pm-notif__item--' . ($notif['type'] ?? 'info');
                        $unreadClass = empty($notif['read']) ? ' is-unread' : '';
                        ?>
                        <a
                            href="<?= e($notif['url'] ?? '#') ?>"
                            class="pm-notif__item <?= e($typeClass . $unreadClass) ?>"
                            role="menuitem"
                        >
                            <span class="pm-notif__icon">
                                <?php if (($notif['type'] ?? '') === 'success'): ?>
                                    <i class="fas fa-check-circle"></i>
                                <?php elseif (($notif['type'] ?? '') === 'warning'): ?>
                                    <i class="fas fa-exclamation-triangle"></i>
                                <?php else: ?>
                                    <i class="fas fa-info-circle"></i>
                                <?php endif; ?>
                            </span>
                            <span class="pm-notif__body">
                                <span class="pm-notif__title">
                                    <?= e($notif['title'] ?? '') ?>
                                </span>
                                <span class="pm-notif__msg">
                                    <?= e($notif['message'] ?? '') ?>
                                </span>
                                <span class="pm-notif__time">
                                    <?= e(pm_time_ago($notif['created_at'] ?? date('Y-m-d H:i:s'))) ?>
                                </span>
                            </span>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="pm-notif__footer">
               <button
                 type="button"
                 class="pm-notif__viewall"
                  id="pmOpenAllNotifs"
                  >
                   <i class="fas fa-list"></i>
                    View All Notifications
                  </button>
           </div>
=======
      <header class="dash-header">
        <div class="dash-header__left">
          <h1 class="dash-header__title">Programme Manager Dashboard</h1>
>>>>>>> 69ee3b9c4b9c5032917843f6a058edc276589649
        </div>
        <div class="dash-header__right">
          <div class="dash-header__user">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'PM+User') ?>&background=1a56db&color=fff&size=80" alt="" class="dash-header__avatar">
          </div>
        </div>
      </header>
      <div class="dash-content">
        <div class="welcome-card">
          <div class="welcome-card__bg"></div>
          <div class="welcome-card__content">
            <h1 class="welcome-card__greeting">Welcome, <span class="text-gradient"><?= e($user['fullname'] ?? 'Programme Manager') ?></span></h1>
            <p>Manage your assigned programmes, monitor candidate progress, and generate reports.</p>
          </div>
        </div>
        <div class="overview-grid" style="margin-top:2rem;">
          <div class="overview-card">
            <div class="overview-card__icon overview-card__icon--primary"><i class="fas fa-graduation-cap"></i></div>
            <div class="overview-card__info"><span class="overview-card__number">5</span><span class="overview-card__label">Active Programmes</span></div>
          </div>
          <div class="overview-card">
            <div class="overview-card__icon overview-card__icon--cyan"><i class="fas fa-users"></i></div>
            <div class="overview-card__info"><span class="overview-card__number">42</span><span class="overview-card__label">Candidates</span></div>
          </div>
          <div class="overview-card">
            <div class="overview-card__icon overview-card__icon--amber"><i class="fas fa-chart-line"></i></div>
            <div class="overview-card__info"><span class="overview-card__number">87%</span><span class="overview-card__label">Completion Rate</span></div>
          </div>
        </div>
      </div>
    </main>
<<<<<<< HEAD
</div>
<!-- =========================================================
     ALL NOTIFICATIONS MODAL
========================================================== -->
<div class="pm-modal" id="pmNotifModal" aria-hidden="true">
    <div class="pm-modal__backdrop" data-pm-close></div>

    <div
        class="pm-modal__dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="pmNotifModalTitle"
    >
        <!-- Header -->
        <div class="pm-modal__header">
            <div class="pm-modal__title-wrap">
                <h2 id="pmNotifModalTitle" class="pm-modal__title">
                    <i class="fas fa-bell"></i>
                    All Notifications
                </h2>
                <span class="pm-modal__subtitle">
                    <?php if ($pmUnreadCount > 0): ?>
                        <?= (int) $pmUnreadCount ?> unread
                    <?php else: ?>
                        You're all caught up
                    <?php endif; ?>
                </span>
            </div>

            <button
                type="button"
                class="pm-modal__close"
                data-pm-close
                aria-label="Close notifications"
            >
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Filter tabs -->
        <div class="pm-modal__tabs" role="tablist">
            <button
                type="button"
                class="pm-modal__tab is-active"
                data-pm-filter="all"
                role="tab"
                aria-selected="true"
            >
                All
                <span class="pm-modal__tab-count"><?= count($pmNotifications) ?></span>
            </button>
            <button
                type="button"
                class="pm-modal__tab"
                data-pm-filter="unread"
                role="tab"
                aria-selected="false"
            >
                Unread
                <span class="pm-modal__tab-count"><?= (int) $pmUnreadCount ?></span>
            </button>
            <button
                type="button"
                class="pm-modal__tab"
                data-pm-filter="read"
                role="tab"
                aria-selected="false"
            >
                Read
                <span class="pm-modal__tab-count">
                    <?= count($pmNotifications) - (int) $pmUnreadCount ?>
                </span>
            </button>
        </div>

        <!-- Scrollable list -->
        <div class="pm-modal__body" id="pmNotifModalList">
            <?php if (empty($pmNotifications)): ?>
                <div class="pm-modal__empty">
                    <i class="fas fa-bell-slash"></i>
                    <h3>No notifications yet</h3>
                    <p>When something happens in your portfolio, it'll show up here.</p>
                </div>
            <?php else: ?>
                <?php foreach ($pmNotifications as $notif): ?>
                    <?php
                    $isRead      = !empty($notif['read']);
                    $type        = $notif['type'] ?? 'info';
                    $typeClass   = 'pm-modal__item--' . $type;
                    $readClass   = $isRead ? ' is-read' : ' is-unread';
                    $dataRead    = $isRead ? 'read' : 'unread';
                    ?>
                    <a
                        href="<?= e($notif['url'] ?? '#') ?>"
                        class="pm-modal__item <?= e($typeClass . $readClass) ?>"
                        data-pm-read-state="<?= e($dataRead) ?>"
                        data-pm-id="<?= (int) ($notif['id'] ?? 0) ?>"
                    >
                        <span class="pm-modal__item-icon">
                            <?php if ($type === 'success'): ?>
                                <i class="fas fa-check-circle"></i>
                            <?php elseif ($type === 'warning'): ?>
                                <i class="fas fa-exclamation-triangle"></i>
                            <?php else: ?>
                                <i class="fas fa-info-circle"></i>
                            <?php endif; ?>
                        </span>

                        <span class="pm-modal__item-body">
                            <span class="pm-modal__item-title">
                                <?= e($notif['title'] ?? '') ?>
                                <?php if (!$isRead): ?>
                                    <span class="pm-modal__dot" aria-label="Unread"></span>
                                <?php endif; ?>
                            </span>
                            <span class="pm-modal__item-msg">
                                <?= e($notif['message'] ?? '') ?>
                            </span>
                            <span class="pm-modal__item-time">
                                <i class="fas fa-clock"></i>
                                <?= e(pm_time_ago($notif['created_at'] ?? date('Y-m-d H:i:s'))) ?>
                            </span>
                        </span>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="pm-modal__footer">
            <span class="pm-modal__footer-note">
                <?= count($pmNotifications) ?> total notification(s)
            </span>
            <button
                type="button"
                class="pm-modal__footer-btn"
                data-pm-close
            >
                Close
            </button>
        </div>
    </div>
</div>
<script>

(function () {
    /* --------------------------------------------------
       Bell dropdown (existing behaviour)
    -------------------------------------------------- */
    const wrap = document.getElementById('pmNotif');
    const btn  = document.getElementById('pmNotifBtn');

    if (wrap && btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            const open = wrap.classList.toggle('is-open');
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        document.addEventListener('click', function (e) {
            if (!wrap.contains(e.target)) {
                wrap.classList.remove('is-open');
                btn.setAttribute('aria-expanded', 'false');
            }
        });
    }

    /* --------------------------------------------------
       All Notifications modal
    -------------------------------------------------- */
    const modal       = document.getElementById('pmNotifModal');
    const openBtn     = document.getElementById('pmOpenAllNotifs');
    const closeEls    = modal ? modal.querySelectorAll('[data-pm-close]') : [];
    const tabs        = modal ? modal.querySelectorAll('.pm-modal__tab') : [];
    const items       = modal ? modal.querySelectorAll('.pm-modal__item') : [];

    function openModal() {
        if (!modal) return;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        // Close the little dropdown so they don't overlap
        if (wrap) {
            wrap.classList.remove('is-open');
            if (btn) btn.setAttribute('aria-expanded', 'false');
        }
    }

    function closeModal() {
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    if (openBtn) openBtn.addEventListener('click', openModal);
    closeEls.forEach(el => el.addEventListener('click', closeModal));

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal && modal.classList.contains('is-open')) {
            closeModal();
        }
    });

    /* --------------------------------------------------
       Filter tabs (All / Unread / Read)
    -------------------------------------------------- */
    tabs.forEach(tab => {
        tab.addEventListener('click', function () {
            const filter = tab.getAttribute('data-pm-filter');

            tabs.forEach(t => {
                t.classList.toggle('is-active', t === tab);
                t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
            });

            items.forEach(item => {
                const state = item.getAttribute('data-pm-read-state');
                let show = true;

                if (filter === 'unread') show = state === 'unread';
                if (filter === 'read')   show = state === 'read';

                item.hidden = !show;
            });
        });
    });

    /* --------------------------------------------------
       Mark as read on click (visual only — no DB)
       Removes the unread styling + dot when clicked,
       and decrements the bell badge.
    -------------------------------------------------- */
    items.forEach(item => {
        item.addEventListener('click', function () {
            if (item.getAttribute('data-pm-read-state') === 'unread') {
                item.setAttribute('data-pm-read-state', 'read');
                item.classList.remove('is-unread');
                item.classList.add('is-read');

                const dot = item.querySelector('.pm-modal__dot');
                if (dot) dot.remove();

                const badge = document.getElementById('pmNotifBadge');
                if (badge) {
                    const current = parseInt(badge.textContent.replace('+', ''), 10) || 0;
                    const next    = Math.max(0, current - 1);
                    if (next === 0) {
                        badge.remove();
                    } else {
                        badge.textContent = next;
                    }
                }
            }
        });
    });
})();
</script>


=======
  </div>
>>>>>>> 69ee3b9c4b9c5032917843f6a058edc276589649
</body>
</html>
