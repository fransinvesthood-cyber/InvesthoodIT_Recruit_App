<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('programme_manager');
require_once __DIR__ . '/_helpers.php';

$user = current_user();
$flashes = render_flashes();

$conn = Database::getConnection();

$currentPage = 'dashboard';

$managerId = (int) (
    $user['id']
    ?? $user['user_id']
    ?? 0
);


/*
|--------------------------------------------------------------------------
| Dashboard Statistics
|--------------------------------------------------------------------------
*/

$activeProgrammes = 0;
$activeCohorts = 0;
$totalCandidates = 0;
$completionRate = 0;

$totalParticipants = 0;
$completedParticipants = 0;


/*
|--------------------------------------------------------------------------
| Modal Data
|--------------------------------------------------------------------------
*/

$modalProgrammes = [];
$modalCohorts = [];
$modalCandidates = [];
$programmes = [];


/*
|--------------------------------------------------------------------------
| Active Programmes
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total
    FROM programmes
    WHERE programme_manager_id = ?
      AND status = 'active'
");

if ($stmt) {

    $stmt->bind_param(
        'i',
        $managerId
    );

    $stmt->execute();

    $result = $stmt->get_result();

    $row = $result->fetch_assoc();

    $activeProgrammes =
        (int) ($row['total'] ?? 0);

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Active Programme Names
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        p.id,
        p.name,
        p.type,
        p.start_date,
        p.end_date,
        p.status
    FROM programmes p
    WHERE p.programme_manager_id = ?
      AND p.status = 'active'
    ORDER BY p.name ASC
");

if ($stmt) {

    $stmt->bind_param(
        'i',
        $managerId
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while (
        $row = $result->fetch_assoc()
    ) {

        $modalProgrammes[] = [
            'id' => (int) ($row['id'] ?? 0),

            'name' => (string) (
                $row['name']
                ?? 'Programme'
            ),

            'type' => (string) (
                $row['type']
                ?? ''
            ),

            'start_date' => (string) (
                $row['start_date']
                ?? ''
            ),

            'end_date' => (string) (
                $row['end_date']
                ?? ''
            ),

            'status' => (string) (
                $row['status']
                ?? 'active'
            )
        ];
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Active Cohorts
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total
    FROM cohorts c
    INNER JOIN programmes p
        ON p.id = c.programme_id
    WHERE p.programme_manager_id = ?
      AND p.status = 'active'
      AND c.status = 'active'
");

if ($stmt) {

    $stmt->bind_param(
        'i',
        $managerId
    );

    $stmt->execute();

    $result = $stmt->get_result();

    $row = $result->fetch_assoc();

    $activeCohorts =
        (int) ($row['total'] ?? 0);

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Active Cohort Names
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        c.id,
        c.name,
        c.start_date,
        c.end_date,
        c.status,

        p.id AS programme_id,
        p.name AS programme_name

    FROM cohorts c

    INNER JOIN programmes p
        ON p.id = c.programme_id

    WHERE p.programme_manager_id = ?
      AND p.status = 'active'
      AND c.status = 'active'

    ORDER BY
        p.name ASC,
        c.name ASC
");

if ($stmt) {

    $stmt->bind_param(
        'i',
        $managerId
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while (
        $row = $result->fetch_assoc()
    ) {

        $modalCohorts[] = [
            'id' => (int) (
                $row['id']
                ?? 0
            ),

            'name' => (string) (
                $row['name']
                ?? 'Cohort'
            ),

            'programme_id' => (int) (
                $row['programme_id']
                ?? 0
            ),

            'programme_name' => (string) (
                $row['programme_name']
                ?? ''
            ),

            'start_date' => (string) (
                $row['start_date']
                ?? ''
            ),

            'end_date' => (string) (
                $row['end_date']
                ?? ''
            ),

            'status' => (string) (
                $row['status']
                ?? 'active'
            )
        ];
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Candidate Count
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        COUNT(DISTINCT cp.user_id) AS total

    FROM cohort_participants cp

    INNER JOIN cohorts c
        ON c.id = cp.cohort_id

    INNER JOIN programmes p
        ON p.id = c.programme_id

    WHERE p.programme_manager_id = ?
      AND p.status = 'active'
      AND cp.status <> 'withdrawn'
");

if ($stmt) {

    $stmt->bind_param(
        'i',
        $managerId
    );

    $stmt->execute();

    $result = $stmt->get_result();

    $row = $result->fetch_assoc();

    $totalCandidates =
        (int) ($row['total'] ?? 0);

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Candidate Names
|--------------------------------------------------------------------------
|
| users:
|   id
|   first_name
|   last_name
|   email
|
| cohort_participants:
|   cohort_id
|   user_id
|   status
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT DISTINCT

        u.id,

        u.first_name,

        u.last_name,

        CONCAT(
            TRIM(u.first_name),
            ' ',
            TRIM(u.last_name)
        ) AS candidate_name,

        u.email,

        cp.status AS participant_status,

        c.id AS cohort_id,

        c.name AS cohort_name,

        p.id AS programme_id,

        p.name AS programme_name

    FROM cohort_participants cp

    INNER JOIN users u
        ON u.id = cp.user_id

    INNER JOIN cohorts c
        ON c.id = cp.cohort_id

    INNER JOIN programmes p
        ON p.id = c.programme_id

    WHERE p.programme_manager_id = ?

      AND p.status = 'active'

      AND cp.status <> 'withdrawn'

    ORDER BY
        u.first_name ASC,
        u.last_name ASC
");

if ($stmt) {

    $stmt->bind_param(
        'i',
        $managerId
    );

    $stmt->execute();

    $result =
        $stmt->get_result();

    while (
        $row = $result->fetch_assoc()
    ) {

        $candidateName =
            trim(
                (string) (
                    $row['candidate_name']
                    ?? ''
                )
            );

        if ($candidateName === '') {

            $candidateName =
                trim(
                    (string) (
                        $row['first_name']
                        ?? ''
                    )
                    . ' '
                    .
                    (string) (
                        $row['last_name']
                        ?? ''
                    )
                );
        }

        if ($candidateName === '') {
            $candidateName =
                'Candidate';
        }

        $modalCandidates[] = [

            'id' => (int) (
                $row['id']
                ?? 0
            ),

            'candidate_name' =>
                $candidateName,

            'first_name' => (string) (
                $row['first_name']
                ?? ''
            ),

            'last_name' => (string) (
                $row['last_name']
                ?? ''
            ),

            'email' => (string) (
                $row['email']
                ?? ''
            ),

            'participant_status' =>
                (string) (
                    $row['participant_status']
                    ?? ''
                ),

            'cohort_id' => (int) (
                $row['cohort_id']
                ?? 0
            ),

            'cohort_name' => (string) (
                $row['cohort_name']
                ?? ''
            ),

            'programme_id' => (int) (
                $row['programme_id']
                ?? 0
            ),

            'programme_name' =>
                (string) (
                    $row['programme_name']
                    ?? ''
                )
        ];
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Completion Rate
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT

        COUNT(*) AS total_participants,

        SUM(
            CASE
                WHEN cp.status = 'completed'
                THEN 1
                ELSE 0
            END
        ) AS completed_participants

    FROM cohort_participants cp

    INNER JOIN cohorts c
        ON c.id = cp.cohort_id

    INNER JOIN programmes p
        ON p.id = c.programme_id

    WHERE p.programme_manager_id = ?

      AND p.status = 'active'

      AND cp.status <> 'withdrawn'
");

if ($stmt) {

    $stmt->bind_param(
        'i',
        $managerId
    );

    $stmt->execute();

    $result =
        $stmt->get_result();

    $row =
        $result->fetch_assoc();

    $totalParticipants =
        (int) (
            $row['total_participants']
            ?? 0
        );

    $completedParticipants =
        (int) (
            $row['completed_participants']
            ?? 0
        );

    if ($totalParticipants > 0) {

        $completionRate =
            round(
                (
                    $completedParticipants
                    /
                    $totalParticipants
                )
                * 100
            );
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Programme Progress
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT

        p.id,

        p.name,

        p.type,

        p.status,

        p.start_date,

        p.end_date,

        COUNT(
            DISTINCT c.id
        ) AS cohort_count,

        COUNT(
            DISTINCT CASE

                WHEN cp.status <> 'withdrawn'

                THEN cp.user_id

            END
        ) AS candidate_count,

        COUNT(
            DISTINCT CASE

                WHEN cp.status = 'completed'

                THEN cp.user_id

            END
        ) AS completed_count

    FROM programmes p

    LEFT JOIN cohorts c
        ON c.programme_id = p.id

    LEFT JOIN cohort_participants cp
        ON cp.cohort_id = c.id

    WHERE p.programme_manager_id = ?

      AND p.status = 'active'

    GROUP BY

        p.id,

        p.name,

        p.type,

        p.status,

        p.start_date,

        p.end_date

    ORDER BY

        p.start_date ASC,

        p.id ASC
");

if ($stmt) {

    $stmt->bind_param(
        'i',
        $managerId
    );

    $stmt->execute();

    $result =
        $stmt->get_result();

    while (
        $row =
            $result->fetch_assoc()
    ) {

        $candidateCount =
            (int) (
                $row['candidate_count']
                ?? 0
            );

        $completedCount =
            (int) (
                $row['completed_count']
                ?? 0
            );

        $progress = 0;

        if ($candidateCount > 0) {

            $progress =
                round(
                    (
                        $completedCount
                        /
                        $candidateCount
                    )
                    * 100
                );
        }

        $row['progress'] =
            $progress;

        $programmes[] =
            $row;
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Completion Progress Modal Data
|--------------------------------------------------------------------------
*/

$progressModalItems = [];

foreach (
    $programmes
    as
    $programme
) {

    $progressModalItems[] = [

        'id' => (int) (
            $programme['id']
            ?? 0
        ),

        'name' => (string) (
            $programme['name']
            ?? 'Programme'
        ),

        'progress' => (int) (
            $programme['progress']
            ?? 0
        ),

        'candidate_count' => (int) (
            $programme['candidate_count']
            ?? 0
        ),

        'completed_count' => (int) (
            $programme['completed_count']
            ?? 0
        ),

        'cohort_count' => (int) (
            $programme['cohort_count']
            ?? 0
        )
    ];
}


/*
|--------------------------------------------------------------------------
| JSON Helper
|--------------------------------------------------------------------------
*/

function pm_dashboard_json(
    array $data
): string {

    $json =
        json_encode(
            $data,
            JSON_UNESCAPED_UNICODE
            |
            JSON_UNESCAPED_SLASHES
            |
            JSON_HEX_TAG
            |
            JSON_HEX_AMP
            |
            JSON_HEX_APOS
            |
            JSON_HEX_QUOT
        );

    if ($json === false) {
        $json = '[]';
    }

    return htmlspecialchars(
        $json,
        ENT_QUOTES,
        'UTF-8'
    );
}


$programmeModalJson =
    pm_dashboard_json(
        $modalProgrammes
    );

$cohortModalJson =
    pm_dashboard_json(
        $modalCohorts
    );

$candidateModalJson =
    pm_dashboard_json(
        $modalCandidates
    );

$progressModalJson =
    pm_dashboard_json(
        $progressModalItems
    );


/*
|--------------------------------------------------------------------------
| Display Name
|--------------------------------------------------------------------------
*/

$displayName =
    trim(
        (string) (
            $user['fullname']
            ??
            $user['full_name']
            ??
            ''
        )
    );

if ($displayName === '') {

    $displayName =
        trim(
            (string) (
                $user['first_name']
                ?? ''
            )
            . ' '
            .
            (string) (
                $user['last_name']
                ?? ''
            )
        );
}

if ($displayName === '') {
    $displayName =
        'Programme Manager';
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
        Programme Manager Dashboard | Investhood IT
    </title>


    <!-- =====================================================
         FONT
         ===================================================== -->

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


    <!-- =====================================================
         ICONS
         ===================================================== -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
        crossorigin="anonymous"
    >


    <!-- =====================================================
         EXISTING PROJECT CSS
         ===================================================== -->

    <link
        rel="stylesheet"
        href="<?= url('css/styles.css') ?>"
    >

    <link
        rel="stylesheet"
        href="<?= url(
            'css/programme_manager_enhancements.css'
        ) ?>?v=20260919names"
    >

    <link
        rel="stylesheet"
        href="<?= url(
            'css/unified_portal.css'
        ) ?>"
    >

    <link
        rel="stylesheet"
        href="<?= url(
            'css/styles_original_pm.css'
        ) ?>"
    >

    <link
        rel="stylesheet"
        href="<?= url(
            'css/original_pm_sidebar_compat.css'
        ) ?>"
    >

    <link
        rel="stylesheet"
        href="<?= url(
            'css/pm_dashboard_cards_fix.css'
        ) ?>?v=20260919names"
    >


    <!-- =====================================================
         LOAD THEME
         ===================================================== -->

    <script>

        (function () {

            try {

                const saved =
                    localStorage.getItem(
                        'investhood-programme-manager-theme'
                    );

                const systemDark =
                    window.matchMedia
                    &&
                    window.matchMedia(
                        '(prefers-color-scheme: dark)'
                    ).matches;

                const theme =
                    saved === 'dark'
                    ||
                    saved === 'light'

                        ? saved

                        : (
                            systemDark
                                ? 'dark'
                                : 'light'
                        );

                document.documentElement
                    .setAttribute(
                        'data-theme',
                        theme
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


    <!-- =====================================================
         DASHBOARD MODAL CSS
         ===================================================== -->

    <style>

        /* ====================================================
           CLICKABLE CARDS
           ==================================================== */

        .pm-dashboard-modal-card {
            cursor: pointer;

            position: relative;

            transition:
                transform .18s ease,
                box-shadow .18s ease,
                border-color .18s ease;
        }

        .pm-dashboard-modal-card:hover {
            transform:
                translateY(-3px);
        }

        .pm-dashboard-modal-card:focus-visible {
            outline:
                3px solid
                rgba(37, 99, 235, .25);

            outline-offset:
                3px;
        }


        /* ====================================================
           MODAL
           ==================================================== */

        .pm-dashboard-modal {
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

            transition:
                opacity .2s ease,
                visibility .2s ease;
        }

        .pm-dashboard-modal.is-open {
            visibility: visible;

            opacity: 1;

            pointer-events: auto;
        }


        /* ====================================================
           BACKDROP
           ==================================================== */

        .pm-dashboard-modal__backdrop {
            position: absolute;

            inset: 0;

            background:
                rgba(
                    15,
                    23,
                    42,
                    .72
                );

            backdrop-filter:
                blur(5px);
        }


        /* ====================================================
           DIALOG
           ==================================================== */

        .pm-dashboard-modal__dialog {
            position: relative;

            z-index: 2;

            width:
                min(
                    700px,
                    100%
                );

            max-height:
                calc(
                    100vh - 40px
                );

            display: flex;

            flex-direction: column;

            overflow: hidden;

            background:
                #ffffff;

            border:
                1px solid
                #e4e7ec;

            border-radius:
                20px;

            box-shadow:
                0 30px 90px
                rgba(
                    0,
                    0,
                    0,
                    .35
                );

            transform:
                translateY(15px)
                scale(.98);

            transition:
                transform .2s ease;
        }

        .pm-dashboard-modal.is-open
        .pm-dashboard-modal__dialog {
            transform:
                translateY(0)
                scale(1);
        }


        /* ====================================================
           HEADER
           ==================================================== */

        .pm-dashboard-modal__header {
            padding:
                20px 22px;

            display: flex;

            align-items:
                flex-start;

            justify-content:
                space-between;

            gap: 20px;

            border-bottom:
                1px solid
                #e4e7ec;
        }

        .pm-dashboard-modal__eyebrow {
            display: block;

            margin-bottom:
                4px;

            color:
                #2563eb;

            font-size:
                10px;

            font-weight:
                800;

            text-transform:
                uppercase;

            letter-spacing:
                .08em;
        }

        .pm-dashboard-modal__title {
            margin: 0;

            color:
                #101828;

            font-size:
                20px;

            font-weight:
                800;
        }


        /* ====================================================
           CLOSE
           ==================================================== */

        .pm-dashboard-modal__close {
            width: 38px;

            height: 38px;

            flex:
                0 0 38px;

            display: grid;

            place-items:
                center;

            border:
                1px solid
                #e4e7ec;

            border-radius:
                10px;

            background:
                #f8fafc;

            color:
                #475467;

            cursor: pointer;
        }

        .pm-dashboard-modal__close:hover {
            background:
                #eff6ff;

            color:
                #2563eb;
        }


        /* ====================================================
           BODY
           ==================================================== */

        .pm-dashboard-modal__body {
            padding: 20px;

            overflow-y: auto;
        }


        /* ====================================================
           SUMMARY
           ==================================================== */

        .pm-modal-summary {
            margin-bottom:
                16px;

            display: flex;

            align-items:
                flex-start;

            justify-content:
                space-between;

            gap: 20px;
        }

        .pm-modal-summary__text {
            min-width: 0;
        }

        .pm-modal-summary__text strong {
            display: block;

            color:
                #101828;

            font-size:
                15px;

            font-weight:
                800;
        }

        .pm-modal-summary__text span {
            display: block;

            margin-top:
                4px;

            color:
                #667085;

            font-size:
                12px;

            line-height:
                1.5;
        }

        .pm-modal-summary__count {
            flex:
                0 0 auto;

            padding:
                7px 11px;

            border-radius:
                999px;

            background:
                #eff6ff;

            color:
                #2563eb;

            font-size:
                11px;

            font-weight:
                800;
        }


        /* ====================================================
           LIST
           ==================================================== */

        .pm-modal-record-list {
            display: flex;

            flex-direction:
                column;

            gap: 9px;
        }


        /* ====================================================
           RECORD
           ==================================================== */

        .pm-modal-record {
            padding:
                13px 14px;

            display: flex;

            align-items:
                center;

            gap: 12px;

            border:
                1px solid
                #e4e7ec;

            border-radius:
                12px;

            background:
                #ffffff;
        }

        .pm-modal-record__icon,
        .pm-modal-record__avatar {
            width: 42px;

            height: 42px;

            flex:
                0 0 42px;

            display: grid;

            place-items:
                center;

            border-radius:
                11px;

            background:
                #eff6ff;

            color:
                #2563eb;

            font-size:
                14px;

            font-weight:
                800;
        }

        .pm-modal-record__avatar {
            border-radius:
                50%;

            background:
                #eef2ff;

            color:
                #4f46e5;
        }

        .pm-modal-record__icon--cyan {
            background:
                #ecfeff;

            color:
                #0891b2;
        }

        .pm-modal-record__content {
            min-width: 0;

            flex: 1;
        }

        .pm-modal-record__content strong {
            display: block;

            overflow: hidden;

            color:
                #101828;

            font-size:
                13px;

            font-weight:
                700;

            text-overflow:
                ellipsis;

            white-space:
                nowrap;
        }

        .pm-modal-record__content span {
            display: block;

            margin-top:
                3px;

            overflow: hidden;

            color:
                #667085;

            font-size:
                11px;

            line-height:
                1.45;

            text-overflow:
                ellipsis;

            white-space:
                nowrap;
        }

        .pm-modal-record__status {
            flex:
                0 0 auto;

            padding:
                5px 8px;

            border-radius:
                999px;

            background:
                #ecfdf3;

            color:
                #027a48;

            font-size:
                10px;

            font-weight:
                700;

            text-transform:
                capitalize;
        }


        /* ====================================================
           PROGRESS
           ==================================================== */

        .pm-modal-progress {
            padding: 15px;

            border:
                1px solid
                #e4e7ec;

            border-radius:
                12px;

            background:
                #ffffff;
        }

        .pm-modal-progress__head {
            display: flex;

            align-items:
                flex-start;

            justify-content:
                space-between;

            gap: 15px;
        }

        .pm-modal-progress__info {
            min-width: 0;
        }

        .pm-modal-progress__info strong {
            display: block;

            color:
                #101828;

            font-size:
                13px;

            font-weight:
                700;
        }

        .pm-modal-progress__info span {
            display: block;

            margin-top:
                4px;

            color:
                #667085;

            font-size:
                11px;
        }

        .pm-modal-progress__percentage {
            flex:
                0 0 auto;

            color:
                #2563eb;

            font-size:
                16px;

            font-weight:
                800;
        }

        .pm-modal-progress__track {
            width: 100%;

            height: 7px;

            margin-top:
                13px;

            overflow: hidden;

            border-radius:
                999px;

            background:
                #e5e7eb;
        }

        .pm-modal-progress__bar {
            display: block;

            height: 100%;

            border-radius:
                inherit;

            background:
                #2563eb;
        }

        .pm-modal-progress__meta {
            margin-top:
                10px;

            display: flex;

            flex-wrap: wrap;

            gap: 14px;
        }

        .pm-modal-progress__meta span {
            color:
                #667085;

            font-size:
                10px;
        }

        .pm-modal-progress__meta i {
            margin-right:
                4px;

            color:
                #2563eb;
        }


        /* ====================================================
           EMPTY
           ==================================================== */

        .pm-modal-empty {
            min-height:
                210px;

            display: flex;

            flex-direction:
                column;

            align-items:
                center;

            justify-content:
                center;

            text-align:
                center;
        }

        .pm-modal-empty__icon {
            width: 54px;

            height: 54px;

            margin-bottom:
                12px;

            display: grid;

            place-items:
                center;

            border-radius:
                50%;

            background:
                #eff6ff;

            color:
                #2563eb;

            font-size:
                20px;
        }

        .pm-modal-empty strong {
            color:
                #101828;

            font-size:
                14px;
        }

        .pm-modal-empty span {
            max-width:
                330px;

            margin-top:
                6px;

            color:
                #667085;

            font-size:
                12px;

            line-height:
                1.5;
        }


        /* ====================================================
           FOOTER
           ==================================================== */

        .pm-dashboard-modal__footer {
            padding:
                16px 20px;

            display: flex;

            align-items:
                center;

            justify-content:
                flex-end;

            gap: 10px;

            border-top:
                1px solid
                #e4e7ec;
        }

        .pm-dashboard-modal__cancel,
        .pm-dashboard-modal__details {
            min-height:
                40px;

            padding:
                0 15px;

            display: inline-flex;

            align-items:
                center;

            justify-content:
                center;

            gap: 7px;

            border-radius:
                10px;

            font-size:
                12px;

            font-weight:
                700;

            cursor: pointer;
        }

        .pm-dashboard-modal__cancel {
            border:
                1px solid
                #d0d5dd;

            background:
                #ffffff;

            color:
                #344054;
        }

        .pm-dashboard-modal__details {
            border:
                1px solid
                #2563eb;

            background:
                #2563eb;

            color:
                #ffffff;

            text-decoration:
                none;
        }

        .pm-dashboard-modal__details:hover {
            background:
                #1d4ed8;

            color:
                #ffffff;
        }


        /* ====================================================
           DARK MODE
           ==================================================== */

        html[data-theme="dark"]
        .pm-dashboard-modal__dialog {
            background:
                #1e293b;

            border-color:
                #334155;
        }

        html[data-theme="dark"]
        .pm-dashboard-modal__header,

        html[data-theme="dark"]
        .pm-dashboard-modal__footer {
            border-color:
                #334155;
        }

        html[data-theme="dark"]
        .pm-dashboard-modal__title,

        html[data-theme="dark"]
        .pm-modal-summary__text strong,

        html[data-theme="dark"]
        .pm-modal-record__content strong,

        html[data-theme="dark"]
        .pm-modal-progress__info strong,

        html[data-theme="dark"]
        .pm-modal-empty strong {
            color:
                #f8fafc;
        }

        html[data-theme="dark"]
        .pm-modal-summary__text span,

        html[data-theme="dark"]
        .pm-modal-record__content span,

        html[data-theme="dark"]
        .pm-modal-progress__info span,

        html[data-theme="dark"]
        .pm-modal-progress__meta span,

        html[data-theme="dark"]
        .pm-modal-empty span {
            color:
                #94a3b8;
        }

        html[data-theme="dark"]
        .pm-modal-record,

        html[data-theme="dark"]
        .pm-modal-progress {
            background:
                #111827;

            border-color:
                #334155;
        }

        html[data-theme="dark"]
        .pm-modal-progress__track {
            background:
                #334155;
        }

        html[data-theme="dark"]
        .pm-dashboard-modal__close,

        html[data-theme="dark"]
        .pm-dashboard-modal__cancel {
            background:
                #111827;

            color:
                #e2e8f0;

            border-color:
                #475569;
        }


        /* ====================================================
           SCROLL LOCK
           ==================================================== */

        body.pm-dashboard-modal-open {
            overflow:
                hidden !important;
        }


        /* ====================================================
           MOBILE
           ==================================================== */

        @media (
            max-width: 620px
        ) {

            .pm-dashboard-modal {
                padding: 12px;
            }

            .pm-dashboard-modal__dialog {
                max-height:
                    calc(
                        100vh - 24px
                    );

                border-radius:
                    16px;
            }

            .pm-dashboard-modal__header {
                padding: 16px;
            }

            .pm-dashboard-modal__body {
                padding: 15px;
            }

            .pm-modal-summary {
                flex-direction:
                    column;

                gap: 9px;
            }

            .pm-modal-record {
                align-items:
                    flex-start;
            }

            .pm-modal-record__status {
                display: none;
            }

            .pm-dashboard-modal__footer {
                padding:
                    14px 15px;

                flex-direction:
                    column-reverse;
            }

            .pm-dashboard-modal__cancel,
            .pm-dashboard-modal__details {
                width: 100%;

                box-sizing:
                    border-box;
            }
        }

    </style>

</head>


<body class="dashboard-page">


<div class="dashboard">


    <!-- =====================================================
         SIDEBAR
         ===================================================== -->

    <?php
    require __DIR__ . '/sidebar.php';
    ?>


    <!-- =====================================================
         MAIN
         ===================================================== -->

    <main class="dashboard__main">


        <!-- =================================================
             NAVBAR
             ================================================= -->

        <?php
        require __DIR__ . '/navbar.php';
        ?>


        <!-- =================================================
             CONTENT
             ================================================= -->

        <div class="dash-content">


            <?= $flashes ?>


            <!-- =================================================
                 WELCOME
                 ================================================= -->

            <div class="welcome-card">

                <div
                    class="welcome-card__bg"
                ></div>

                <div
                    class="welcome-card__content"
                >

                    <h1
                        class="
                            welcome-card__greeting
                        "
                    >

                        Welcome,

                        <span
                            class="text-gradient"
                        >

                            <?= e(
                                $displayName
                            ) ?>

                        </span>

                    </h1>

                    <p>

                        Manage your assigned programmes,
                        monitor candidate progress,
                        and generate reports.

                    </p>

                </div>

            </div>


            <!-- =================================================
                 OVERVIEW CARDS
                 ================================================= -->

            <div
                class="overview-grid"
                style="margin-top:2rem;"
            >


                <!-- =============================================
                     ACTIVE PROGRAMMES
                     ============================================= -->

                <div
                    class="
                        overview-card
                        pm-dashboard-modal-card
                    "

                    role="button"

                    tabindex="0"

                    aria-haspopup="dialog"

                    data-pm-type="programmes"

                    data-pm-title="Active Programmes"

                    data-pm-description="Active programmes currently assigned to you."

                    data-pm-url="<?= e(
                        url(
                            'programme/programmes.php'
                        )
                    ) ?>"

                    data-pm-items="<?= $programmeModalJson ?>"
                >

                    <div
                        class="
                            overview-card__icon
                            overview-card__icon--primary
                        "
                    >

                        <i
                            class="
                                fas
                                fa-graduation-cap
                            "
                        ></i>

                    </div>


                    <div
                        class="
                            overview-card__info
                        "
                    >

                        <span
                            class="
                                overview-card__number
                            "
                        >

                            <?= number_format(
                                $activeProgrammes
                            ) ?>

                        </span>

                        <span
                            class="
                                overview-card__label
                            "
                        >

                            Active Programmes

                        </span>

                    </div>

                </div>


                <!-- =============================================
                     ACTIVE COHORTS
                     ============================================= -->

                <div
                    class="
                        overview-card
                        pm-dashboard-modal-card
                    "

                    role="button"

                    tabindex="0"

                    aria-haspopup="dialog"

                    data-pm-type="cohorts"

                    data-pm-title="Active Cohorts"

                    data-pm-description="Active cohorts belonging to your programmes."

                    data-pm-url="<?= e(
                        url(
                            'programme/cohorts.php'
                        )
                    ) ?>"

                    data-pm-items="<?= $cohortModalJson ?>"
                >

                    <div
                        class="
                            overview-card__icon
                            overview-card__icon--cyan
                        "
                    >

                        <i
                            class="
                                fas
                                fa-layer-group
                            "
                        ></i>

                    </div>


                    <div
                        class="
                            overview-card__info
                        "
                    >

                        <span
                            class="
                                overview-card__number
                            "
                        >

                            <?= number_format(
                                $activeCohorts
                            ) ?>

                        </span>

                        <span
                            class="
                                overview-card__label
                            "
                        >

                            Active Cohorts

                        </span>

                    </div>

                </div>


                <!-- =============================================
                     CANDIDATES
                     ============================================= -->

                <div
                    class="
                        overview-card
                        pm-dashboard-modal-card
                    "

                    role="button"

                    tabindex="0"

                    aria-haspopup="dialog"

                    data-pm-type="candidates"

                    data-pm-title="Candidates"

                    data-pm-description="Candidates participating in your active programmes."

                    data-pm-url="<?= e(
                        url(
                            'programme/candidates.php'
                        )
                    ) ?>"

                    data-pm-items="<?= $candidateModalJson ?>"
                >

                    <div
                        class="
                            overview-card__icon
                            overview-card__icon--amber
                        "
                    >

                        <i
                            class="
                                fas
                                fa-users
                            "
                        ></i>

                    </div>


                    <div
                        class="
                            overview-card__info
                        "
                    >

                        <span
                            class="
                                overview-card__number
                            "
                        >

                            <?= number_format(
                                $totalCandidates
                            ) ?>

                        </span>

                        <span
                            class="
                                overview-card__label
                            "
                        >

                            Candidates

                        </span>

                    </div>

                </div>


                <!-- =============================================
                     COMPLETION RATE
                     ============================================= -->

                <div
                    class="
                        overview-card
                        pm-dashboard-modal-card
                    "

                    role="button"

                    tabindex="0"

                    aria-haspopup="dialog"

                    data-pm-type="progress"

                    data-pm-title="Completion Progress"

                    data-pm-description="Completion progress across your active programmes."

                    data-pm-url="<?= e(
                        url(
                            'programme/reports.php'
                        )
                    ) ?>"

                    data-pm-items="<?= $progressModalJson ?>"
                >

                    <div
                        class="
                            overview-card__icon
                            overview-card__icon--primary
                        "
                    >

                        <i
                            class="
                                fas
                                fa-chart-line
                            "
                        ></i>

                    </div>


                    <div
                        class="
                            overview-card__info
                        "
                    >

                        <span
                            class="
                                overview-card__number
                            "
                        >

                            <?= (int)
                                $completionRate
                            ?>%

                        </span>

                        <span
                            class="
                                overview-card__label
                            "
                        >

                            Completion Rate

                        </span>

                    </div>

                </div>


            </div>


            <!-- =================================================
                 PROGRAMME PROGRESS
                 ================================================= -->

            <div
                style="
                    margin-top:
                    2rem;
                "
            >

                <div class="welcome-card">

                    <div
                        class="
                            welcome-card__content
                        "
                    >

                        <h2
                            style="
                                margin-bottom:
                                .5rem;
                            "
                        >

                            Programme Progress

                        </h2>

                        <p>

                            Monitor the progress of
                            your active programmes.

                        </p>

                    </div>

                </div>

            </div>


            <?php if (
                empty($programmes)
            ): ?>


                <div
                    class="welcome-card"
                    style="
                        margin-top:
                        1rem;
                    "
                >

                    <div
                        class="
                            welcome-card__content
                        "
                    >

                        <h3>

                            No Active Programmes

                        </h3>

                        <p>

                            You currently have no
                            active programmes
                            assigned to you.

                        </p>

                    </div>

                </div>


            <?php else: ?>


                <div
                    class="overview-grid"

                    style="
                        margin-top:
                        1rem;
                    "
                >


                    <?php foreach (
                        $programmes
                        as
                        $programme
                    ): ?>


                        <?php

                        $programmeId =
                            (int) (
                                $programme['id']
                                ?? 0
                            );

                        $programmeName =
                            (string) (
                                $programme['name']
                                ?? 'Programme'
                            );

                        $candidateCount =
                            (int) (
                                $programme[
                                    'candidate_count'
                                ]
                                ?? 0
                            );

                        $cohortCount =
                            (int) (
                                $programme[
                                    'cohort_count'
                                ]
                                ?? 0
                            );

                        $completedCount =
                            (int) (
                                $programme[
                                    'completed_count'
                                ]
                                ?? 0
                            );

                        $progress =
                            (int) (
                                $programme[
                                    'progress'
                                ]
                                ?? 0
                            );


                        $singleProgrammeJson =
                            pm_dashboard_json(
                                [
                                    [
                                        'id' =>
                                            $programmeId,

                                        'name' =>
                                            $programmeName,

                                        'progress' =>
                                            $progress,

                                        'candidate_count' =>
                                            $candidateCount,

                                        'completed_count' =>
                                            $completedCount,

                                        'cohort_count' =>
                                            $cohortCount
                                    ]
                                ]
                            );

                        ?>


                        <div
                            class="
                                overview-card
                                pm-dashboard-modal-card
                            "

                            role="button"

                            tabindex="0"

                            aria-haspopup="dialog"

                            data-pm-type="progress"

                            data-pm-title="<?= e(
                                $programmeName
                            ) ?>"

                            data-pm-description="Programme completion details."

                            data-pm-url="<?= e(
                                url(
                                    'programme/programme_view.php?id='
                                    .
                                    $programmeId
                                )
                            ) ?>"

                            data-pm-items="<?= $singleProgrammeJson ?>"
                        >

                            <div
                                class="
                                    overview-card__icon
                                    overview-card__icon--primary
                                "
                            >

                                <i
                                    class="
                                        fas
                                        fa-graduation-cap
                                    "
                                ></i>

                            </div>


                            <div
                                class="
                                    overview-card__info
                                "
                            >

                                <span
                                    class="
                                        overview-card__label
                                    "

                                    style="
                                        font-weight:
                                        600;
                                    "
                                >

                                    <?= e(
                                        $programmeName
                                    ) ?>

                                </span>


                                <span
                                    class="
                                        overview-card__label
                                    "

                                    style="
                                        margin-top:
                                        .35rem;
                                    "
                                >

                                    <?= number_format(
                                        $candidateCount
                                    ) ?>

                                    Candidates

                                    ·

                                    <?= number_format(
                                        $cohortCount
                                    ) ?>

                                    Cohorts

                                </span>


                                <span
                                    class="
                                        overview-card__number
                                    "

                                    style="
                                        font-size:
                                        1.5rem;

                                        margin-top:
                                        .5rem;
                                    "
                                >

                                    <?= $progress ?>%

                                </span>


                                <span
                                    class="
                                        overview-card__label
                                    "
                                >

                                    Completion Progress

                                </span>

                            </div>

                        </div>


                    <?php endforeach; ?>


                </div>


            <?php endif; ?>


        </div>

    </main>

</div>


<!-- =========================================================
     DASHBOARD MODAL
     ========================================================= -->

<div
    id="pmDashboardModal"

    class="pm-dashboard-modal"

    aria-hidden="true"
>

    <div
        class="
            pm-dashboard-modal__backdrop
        "

        data-pm-close
    ></div>


    <section
        class="
            pm-dashboard-modal__dialog
        "

        role="dialog"

        aria-modal="true"

        aria-labelledby="pmDashboardModalTitle"
    >

        <header
            class="
                pm-dashboard-modal__header
            "
        >

            <div>

                <span
                    class="
                        pm-dashboard-modal__eyebrow
                    "
                >

                    Programme Manager

                </span>


                <h2
                    id="pmDashboardModalTitle"

                    class="
                        pm-dashboard-modal__title
                    "
                >

                    Dashboard Details

                </h2>

            </div>


            <button
                type="button"

                class="
                    pm-dashboard-modal__close
                "

                data-pm-close

                aria-label="Close"
            >

                <i
                    class="
                        fas
                        fa-times
                    "
                ></i>

            </button>

        </header>


        <div
            id="pmDashboardModalBody"

            class="
                pm-dashboard-modal__body
            "
        ></div>


        <footer
            class="
                pm-dashboard-modal__footer
            "
        >

            <button
                type="button"

                class="
                    pm-dashboard-modal__cancel
                "

                data-pm-close
            >

                Close

            </button>


            <a
                id="pmDashboardModalDetails"

                href="#"

                class="
                    pm-dashboard-modal__details
                "
            >

                View Details

                <i
                    class="
                        fas
                        fa-arrow-right
                    "
                ></i>

            </a>

        </footer>

    </section>

</div>


<!-- =========================================================
     EXISTING PM JAVASCRIPT
     ========================================================= -->

<script
    src="<?= url(
        'js/programme_manager_enhancements.js'
    ) ?>?v=20260919names"
></script>

<script
    src="<?= url(
        'js/original_pm_sidebar.js'
    ) ?>?v=20260919names"
></script>


<!-- =========================================================
     MODAL JAVASCRIPT
     ========================================================= -->

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        'use strict';


        const modal =
            document.getElementById(
                'pmDashboardModal'
            );

        const modalTitle =
            document.getElementById(
                'pmDashboardModalTitle'
            );

        const modalBody =
            document.getElementById(
                'pmDashboardModalBody'
            );

        const detailsButton =
            document.getElementById(
                'pmDashboardModalDetails'
            );

        let activeCard = null;


        /* =====================================================
           ESCAPE HTML
           ===================================================== */

        function escapeHtml(value) {

            const element =
                document.createElement(
                    'div'
                );

            element.textContent =
                String(
                    value ?? ''
                );

            return element.innerHTML;

        }


        /* =====================================================
           INITIALS
           ===================================================== */

        function initials(name) {

            const words =
                String(
                    name || ''
                )
                .trim()
                .split(/\s+/)
                .filter(Boolean);


            if (!words.length) {
                return '?';
            }


            if (words.length === 1) {

                return words[0]
                    .substring(0, 2)
                    .toUpperCase();
            }


            return (
                words[0][0]
                +
                words[
                    words.length - 1
                ][0]
            ).toUpperCase();

        }


        /* =====================================================
           PARSE JSON
           ===================================================== */

        function getItems(card) {

            const raw =
                card.getAttribute(
                    'data-pm-items'
                )
                || '[]';


            try {

                const data =
                    JSON.parse(raw);


                if (
                    Array.isArray(data)
                ) {

                    return data;
                }

            } catch (error) {

                console.error(
                    'Dashboard modal JSON error:',
                    error,
                    raw
                );

            }


            return [];

        }


        /* =====================================================
           EMPTY STATE
           ===================================================== */

        function emptyHtml() {

            return `

                <div
                    class="pm-modal-empty"
                >

                    <div
                        class="
                            pm-modal-empty__icon
                        "
                    >

                        <i
                            class="
                                fas
                                fa-inbox
                            "
                        ></i>

                    </div>


                    <strong>

                        No records found

                    </strong>


                    <span>

                        There are currently no
                        records available for
                        this section.

                    </span>

                </div>

            `;

        }


        /* =====================================================
           PROGRAMMES
           ===================================================== */

        function programmesHtml(
            items
        ) {

            if (!items.length) {
                return emptyHtml();
            }


            return items.map(
                function (
                    programme
                ) {

                    const name =
                        String(
                            programme.name
                            || 'Programme'
                        );


                    const type =
                        String(
                            programme.type
                            || 'Programme'
                        );


                    return `

                        <article
                            class="
                                pm-modal-record
                            "
                        >

                            <div
                                class="
                                    pm-modal-record__icon
                                "
                            >

                                <i
                                    class="
                                        fas
                                        fa-graduation-cap
                                    "
                                ></i>

                            </div>


                            <div
                                class="
                                    pm-modal-record__content
                                "
                            >

                                <strong>

                                    ${escapeHtml(
                                        name
                                    )}

                                </strong>


                                <span>

                                    ${escapeHtml(
                                        type
                                    )}

                                </span>

                            </div>


                            <span
                                class="
                                    pm-modal-record__status
                                "
                            >

                                Active

                            </span>

                        </article>

                    `;

                }
            ).join('');

        }


        /* =====================================================
           COHORTS
           ===================================================== */

        function cohortsHtml(
            items
        ) {

            if (!items.length) {
                return emptyHtml();
            }


            return items.map(
                function (
                    cohort
                ) {

                    const cohortName =
                        String(
                            cohort.name
                            || 'Cohort'
                        );


                    const programmeName =
                        String(
                            cohort.programme_name
                            || 'Programme'
                        );


                    return `

                        <article
                            class="
                                pm-modal-record
                            "
                        >

                            <div
                                class="
                                    pm-modal-record__icon
                                    pm-modal-record__icon--cyan
                                "
                            >

                                <i
                                    class="
                                        fas
                                        fa-layer-group
                                    "
                                ></i>

                            </div>


                            <div
                                class="
                                    pm-modal-record__content
                                "
                            >

                                <strong>

                                    ${escapeHtml(
                                        cohortName
                                    )}

                                </strong>


                                <span>

                                    Programme:
                                    ${escapeHtml(
                                        programmeName
                                    )}

                                </span>

                            </div>


                            <span
                                class="
                                    pm-modal-record__status
                                "
                            >

                                ${escapeHtml(
                                    cohort.status
                                    || 'active'
                                )}

                            </span>

                        </article>

                    `;

                }
            ).join('');

        }


        /* =====================================================
           CANDIDATES
           ===================================================== */

        function candidatesHtml(
            items
        ) {

            if (!items.length) {
                return emptyHtml();
            }


            return items.map(
                function (
                    candidate
                ) {

                    /*
                     * IMPORTANT:
                     *
                     * We use candidate_name from SQL first.
                     * It is:
                     *
                     * CONCAT(
                     *   users.first_name,
                     *   ' ',
                     *   users.last_name
                     * )
                     */

                    let candidateName =
                        String(
                            candidate
                                .candidate_name
                            || ''
                        ).trim();


                    if (
                        candidateName === ''
                    ) {

                        candidateName =
                            (
                                String(
                                    candidate
                                        .first_name
                                    || ''
                                )
                                +
                                ' '
                                +
                                String(
                                    candidate
                                        .last_name
                                    || ''
                                )
                            ).trim();
                    }


                    if (
                        candidateName === ''
                    ) {

                        candidateName =
                            'Candidate';
                    }


                    const email =
                        String(
                            candidate.email
                            || ''
                        );


                    const programmeName =
                        String(
                            candidate
                                .programme_name
                            || ''
                        );


                    const cohortName =
                        String(
                            candidate
                                .cohort_name
                            || ''
                        );


                    const status =
                        String(
                            candidate
                                .participant_status
                            || 'selected'
                        );


                    let assignmentText =
                        programmeName;


                    if (
                        programmeName !== ''
                        &&
                        cohortName !== ''
                    ) {

                        assignmentText +=
                            ' · '
                            +
                            cohortName;

                    } else if (
                        cohortName !== ''
                    ) {

                        assignmentText =
                            cohortName;
                    }


                    return `

                        <article
                            class="
                                pm-modal-record
                            "
                        >

                            <div
                                class="
                                    pm-modal-record__avatar
                                "
                            >

                                ${escapeHtml(
                                    initials(
                                        candidateName
                                    )
                                )}

                            </div>


                            <div
                                class="
                                    pm-modal-record__content
                                "
                            >

                                <strong>

                                    ${escapeHtml(
                                        candidateName
                                    )}

                                </strong>


                                ${
                                    email !== ''

                                    ? `

                                        <span>

                                            ${escapeHtml(
                                                email
                                            )}

                                        </span>

                                    `

                                    : ''
                                }


                                ${
                                    assignmentText !== ''

                                    ? `

                                        <span>

                                            ${escapeHtml(
                                                assignmentText
                                            )}

                                        </span>

                                    `

                                    : ''
                                }

                            </div>


                            <span
                                class="
                                    pm-modal-record__status
                                "
                            >

                                ${escapeHtml(
                                    status
                                )}

                            </span>

                        </article>

                    `;

                }
            ).join('');

        }


        /* =====================================================
           PROGRESS
           ===================================================== */

        function progressHtml(
            items
        ) {

            if (!items.length) {
                return emptyHtml();
            }


            return items.map(
                function (
                    programme
                ) {

                    const name =
                        String(
                            programme.name
                            || 'Programme'
                        );


                    let progress =
                        Number(
                            programme.progress
                            || 0
                        );


                    if (
                        !Number.isFinite(
                            progress
                        )
                    ) {

                        progress = 0;
                    }


                    progress =
                        Math.max(
                            0,
                            Math.min(
                                100,
                                progress
                            )
                        );


                    const candidates =
                        Number(
                            programme
                                .candidate_count
                            || 0
                        );


                    const completed =
                        Number(
                            programme
                                .completed_count
                            || 0
                        );


                    const cohorts =
                        Number(
                            programme
                                .cohort_count
                            || 0
                        );


                    return `

                        <article
                            class="
                                pm-modal-progress
                            "
                        >

                            <div
                                class="
                                    pm-modal-progress__head
                                "
                            >

                                <div
                                    class="
                                        pm-modal-progress__info
                                    "
                                >

                                    <strong>

                                        ${escapeHtml(
                                            name
                                        )}

                                    </strong>


                                    <span>

                                        ${completed}
                                        of
                                        ${candidates}
                                        candidates completed

                                    </span>

                                </div>


                                <strong
                                    class="
                                        pm-modal-progress__percentage
                                    "
                                >

                                    ${progress}%

                                </strong>

                            </div>


                            <div
                                class="
                                    pm-modal-progress__track
                                "
                            >

                                <span
                                    class="
                                        pm-modal-progress__bar
                                    "

                                    style="
                                        width:
                                        ${progress}%;
                                    "
                                ></span>

                            </div>


                            <div
                                class="
                                    pm-modal-progress__meta
                                "
                            >

                                <span>

                                    <i
                                        class="
                                            fas
                                            fa-users
                                        "
                                    ></i>

                                    ${candidates}

                                    Candidates

                                </span>


                                <span>

                                    <i
                                        class="
                                            fas
                                            fa-layer-group
                                        "
                                    ></i>

                                    ${cohorts}

                                    Cohorts

                                </span>

                            </div>

                        </article>

                    `;

                }
            ).join('');

        }


        /* =====================================================
           BUILD CONTENT
           ===================================================== */

        function buildModal(card) {

            const type =
                card.getAttribute(
                    'data-pm-type'
                )
                || '';


            const title =
                card.getAttribute(
                    'data-pm-title'
                )
                || 'Dashboard Details';


            const description =
                card.getAttribute(
                    'data-pm-description'
                )
                || '';


            const url =
                card.getAttribute(
                    'data-pm-url'
                )
                || '';


            const items =
                getItems(card);


            let records = '';


            if (
                type === 'programmes'
            ) {

                records =
                    programmesHtml(
                        items
                    );

            } else if (
                type === 'cohorts'
            ) {

                records =
                    cohortsHtml(
                        items
                    );

            } else if (
                type === 'candidates'
            ) {

                records =
                    candidatesHtml(
                        items
                    );

            } else if (
                type === 'progress'
            ) {

                records =
                    progressHtml(
                        items
                    );

            } else {

                records =
                    emptyHtml();
            }


            modalTitle.textContent =
                title;


            modalBody.innerHTML = `

                <div
                    class="
                        pm-modal-summary
                    "
                >

                    <div
                        class="
                            pm-modal-summary__text
                        "
                    >

                        <strong>

                            ${escapeHtml(
                                title
                            )}

                        </strong>


                        ${
                            description !== ''

                            ? `

                                <span>

                                    ${escapeHtml(
                                        description
                                    )}

                                </span>

                            `

                            : ''
                        }

                    </div>


                    <div
                        class="
                            pm-modal-summary__count
                        "
                    >

                        ${items.length}

                        ${
                            items.length === 1
                                ? 'record'
                                : 'records'
                        }

                    </div>

                </div>


                <div
                    class="
                        pm-modal-record-list
                    "
                >

                    ${records}

                </div>

            `;


            if (
                url !== ''
                &&
                url !== '#'
            ) {

                detailsButton.href =
                    url;

                detailsButton.style
                    .display =
                    'inline-flex';

            } else {

                detailsButton.style
                    .display =
                    'none';

                detailsButton
                    .removeAttribute(
                        'href'
                    );
            }

        }


        /* =====================================================
           OPEN
           ===================================================== */

        function openModal(card) {

            activeCard =
                card;


            buildModal(
                card
            );


            modal.classList.add(
                'is-open'
            );


            modal.setAttribute(
                'aria-hidden',
                'false'
            );


            document.body
                .classList
                .add(
                    'pm-dashboard-modal-open'
                );


            const closeButton =
                modal.querySelector(
                    '.pm-dashboard-modal__close'
                );


            if (closeButton) {

                requestAnimationFrame(
                    function () {

                        closeButton.focus();

                    }
                );
            }

        }


        /* =====================================================
           CLOSE
           ===================================================== */

        function closeModal() {

            modal.classList.remove(
                'is-open'
            );


            modal.setAttribute(
                'aria-hidden',
                'true'
            );


            document.body
                .classList
                .remove(
                    'pm-dashboard-modal-open'
                );


            if (
                activeCard
                &&
                typeof activeCard.focus
                === 'function'
            ) {

                activeCard.focus();
            }


            activeCard = null;

        }


        /* =====================================================
           CLICK HANDLER
           ===================================================== */

        document.addEventListener(
            'click',
            function (event) {

                const close =
                    event.target.closest(
                        '[data-pm-close]'
                    );


                if (
                    close
                    &&
                    modal.contains(close)
                ) {

                    event.preventDefault();

                    closeModal();

                    return;
                }


                const card =
                    event.target.closest(
                        '.pm-dashboard-modal-card'
                    );


                if (!card) {
                    return;
                }


                event.preventDefault();

                event.stopPropagation();


                openModal(
                    card
                );

            },
            true
        );


        /* =====================================================
           KEYBOARD
           ===================================================== */

        document.addEventListener(
            'keydown',
            function (event) {

                if (
                    event.key === 'Escape'
                    &&
                    modal.classList
                        .contains(
                            'is-open'
                        )
                ) {

                    event.preventDefault();

                    closeModal();

                    return;
                }


                if (
                    event.key !== 'Enter'
                    &&
                    event.key !== ' '
                ) {

                    return;
                }


                const card =
                    event.target.closest(
                        '.pm-dashboard-modal-card'
                    );


                if (!card) {
                    return;
                }


                event.preventDefault();


                openModal(
                    card
                );

            }
        );

    }
);

</script>


</body>

</html>