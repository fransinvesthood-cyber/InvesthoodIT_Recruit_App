<?php

/**
 * ================================================
 * INVESTHOOD IT - My Programmes
 * ================================================
 * Role: Programme Manager
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('programme_manager');

require_once __DIR__ . '/_helpers.php';

$user = current_user();

$flashes = render_flashes();

$conn = Database::getConnection();

$currentPage = 'programmes';

$pageTitle = 'Programmes';


/*
|--------------------------------------------------------------------------
| Programme Manager
|--------------------------------------------------------------------------
*/

$managerId = (int) (
    $user['id']
    ?? $user['user_id']
    ?? 0
);

if ($managerId <= 0) {

    http_response_code(403);

    exit(
        'Invalid Programme Manager account.'
    );
}


/*
|--------------------------------------------------------------------------
| Fetch Assigned Programmes
|--------------------------------------------------------------------------
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

        p.created_at,

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

        p.created_at

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
        My Programmes | Investhood IT
    </title>


    <!-- =====================================================
         FONTS
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
         PROGRAMME MANAGER STYLES
         ===================================================== -->

    <link
        rel="stylesheet"
        href="<?= url(
            'css/styles.css'
        ) ?>"
    >

    <link
        rel="stylesheet"
        href="<?= url(
            'css/programme_manager_enhancements.css'
        ) ?>?v=20260920"
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


    <!-- =====================================================
         APPLY SAVED THEME BEFORE PAGE RENDERS
         ===================================================== -->

    <script>

        (function () {

            try {

                const savedTheme =
                    localStorage.getItem(
                        'investhood-programme-manager-theme'
                    );


                const prefersDark =
                    window.matchMedia
                    &&
                    window.matchMedia(
                        '(prefers-color-scheme: dark)'
                    ).matches;


                const theme =
                    savedTheme === 'dark'
                    ||
                    savedTheme === 'light'

                        ? savedTheme

                        : (
                            prefersDark
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
         PAGE STYLES
         ===================================================== -->

    <style>

        /*
        |--------------------------------------------------------------------------
        | Programme Page Header
        |--------------------------------------------------------------------------
        */

        .pm-programmes-header {
            margin-bottom: 22px;
        }

        .pm-programmes-header h2 {
            margin: 0;

            color:
                var(
                    --text-dark,
                    #0f172a
                );

            font-size: 1.35rem;

            font-weight: 700;
        }

        .pm-programmes-header p {
            margin:
                6px 0 0;

            color:
                var(
                    --text-light,
                    #64748b
                );

            font-size: .84rem;
        }


        /*
        |--------------------------------------------------------------------------
        | Programme Grid
        |--------------------------------------------------------------------------
        */

        .pm-programmes-grid {
            display: grid;

            grid-template-columns:
                repeat(
                    3,
                    minmax(0, 1fr)
                );

            gap: 18px;

            margin-top: 20px;
        }


        /*
        |--------------------------------------------------------------------------
        | Programme Card
        |--------------------------------------------------------------------------
        */

        .pm-programme-card {
            min-width: 0;

            padding: 20px;

            display: flex;

            flex-direction: column;

            background:
                var(
                    --bg-white,
                    #ffffff
                );

            border:
                1px solid
                var(
                    --border,
                    #e2e8f0
                );

            border-radius: 16px;

            transition:
                transform .18s ease,
                box-shadow .18s ease,
                border-color .18s ease;
        }

        .pm-programme-card:hover {
            transform:
                translateY(-3px);

            box-shadow:
                0 12px 35px
                rgba(
                    15,
                    23,
                    42,
                    .08
                );
        }


        /*
        |--------------------------------------------------------------------------
        | Card Top
        |--------------------------------------------------------------------------
        */

        .pm-programme-card__top {
            display: flex;

            align-items: flex-start;

            gap: 13px;
        }

        .pm-programme-card__icon {
            width: 44px;

            height: 44px;

            flex:
                0 0 44px;

            display: grid;

            place-items: center;

            border-radius: 12px;

            background: #eff6ff;

            color: #2563eb;

            font-size: 17px;
        }

        .pm-programme-card__heading {
            min-width: 0;

            flex: 1;
        }

        .pm-programme-card__heading h3 {
            margin: 0;

            overflow: hidden;

            color:
                var(
                    --text-dark,
                    #0f172a
                );

            font-size: .95rem;

            font-weight: 700;

            line-height: 1.4;

            text-overflow: ellipsis;
        }

        .pm-programme-card__heading span {
            display: block;

            margin-top: 4px;

            color:
                var(
                    --text-light,
                    #64748b
                );

            font-size: .72rem;
        }


        /*
        |--------------------------------------------------------------------------
        | Status
        |--------------------------------------------------------------------------
        */

        .pm-programme-status {
            flex: 0 0 auto;

            padding:
                5px 8px;

            border-radius: 999px;

            font-size: .65rem;

            font-weight: 700;

            text-transform: capitalize;
        }

        .pm-programme-status--active {
            background: #ecfdf3;

            color: #027a48;
        }

        .pm-programme-status--draft {
            background: #f2f4f7;

            color: #475467;
        }

        .pm-programme-status--completed {
            background: #eff6ff;

            color: #2563eb;
        }

        .pm-programme-status--inactive {
            background: #fff7ed;

            color: #c2410c;
        }


        /*
        |--------------------------------------------------------------------------
        | Description
        |--------------------------------------------------------------------------
        */

        .pm-programme-card__description {
            min-height: 42px;

            margin:
                15px 0 0;

            display: -webkit-box;

            overflow: hidden;

            color:
                var(
                    --text-light,
                    #64748b
                );

            font-size: .77rem;

            line-height: 1.55;

            -webkit-line-clamp: 2;

            -webkit-box-orient: vertical;
        }


        /*
        |--------------------------------------------------------------------------
        | Statistics
        |--------------------------------------------------------------------------
        */

        .pm-programme-card__stats {
            margin-top: 17px;

            display: grid;

            grid-template-columns:
                repeat(
                    3,
                    minmax(0, 1fr)
                );

            gap: 8px;
        }

        .pm-programme-stat {
            padding:
                10px 8px;

            border-radius: 10px;

            background:
                #f8fafc;

            text-align: center;
        }

        .pm-programme-stat strong {
            display: block;

            color:
                var(
                    --text-dark,
                    #0f172a
                );

            font-size: .9rem;

            font-weight: 800;
        }

        .pm-programme-stat span {
            display: block;

            margin-top: 2px;

            color:
                var(
                    --text-light,
                    #64748b
                );

            font-size: .62rem;
        }


        /*
        |--------------------------------------------------------------------------
        | Progress
        |--------------------------------------------------------------------------
        */

        .pm-programme-progress {
            margin-top: 17px;
        }

        .pm-programme-progress__head {
            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 10px;

            margin-bottom: 7px;
        }

        .pm-programme-progress__head span {
            color:
                var(
                    --text-light,
                    #64748b
                );

            font-size: .7rem;
        }

        .pm-programme-progress__head strong {
            color: #2563eb;

            font-size: .75rem;
        }

        .pm-programme-progress__track {
            width: 100%;

            height: 7px;

            overflow: hidden;

            border-radius: 999px;

            background: #e2e8f0;
        }

        .pm-programme-progress__bar {
            height: 100%;

            display: block;

            border-radius: inherit;

            background: #2563eb;
        }


        /*
        |--------------------------------------------------------------------------
        | Dates
        |--------------------------------------------------------------------------
        */

        .pm-programme-card__dates {
            margin-top: 14px;

            display: flex;

            align-items: center;

            gap: 7px;

            color:
                var(
                    --text-light,
                    #64748b
                );

            font-size: .69rem;
        }

        .pm-programme-card__dates i {
            color: #94a3b8;
        }


        /*
        |--------------------------------------------------------------------------
        | Footer
        |--------------------------------------------------------------------------
        */

        .pm-programme-card__footer {
            margin-top: auto;

            padding-top: 18px;
        }

        .pm-programme-view {
            width: 100%;

            min-height: 40px;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 7px;

            border-radius: 10px;

            background: #2563eb;

            color: #ffffff;

            font-size: .75rem;

            font-weight: 700;

            text-decoration: none;

            transition:
                background .18s ease,
                transform .18s ease;
        }

        .pm-programme-view:hover {
            background: #1d4ed8;

            color: #ffffff;

            transform:
                translateY(-1px);
        }


        /*
        |--------------------------------------------------------------------------
        | Empty State
        |--------------------------------------------------------------------------
        */

        .pm-programmes-empty {
            margin-top: 20px;

            padding:
                55px 20px;

            display: flex;

            flex-direction: column;

            align-items: center;

            justify-content: center;

            text-align: center;

            background:
                var(
                    --bg-white,
                    #ffffff
                );

            border:
                1px solid
                var(
                    --border,
                    #e2e8f0
                );

            border-radius: 16px;
        }

        .pm-programmes-empty__icon {
            width: 58px;

            height: 58px;

            margin-bottom: 13px;

            display: grid;

            place-items: center;

            border-radius: 50%;

            background: #eff6ff;

            color: #2563eb;

            font-size: 21px;
        }

        .pm-programmes-empty h3 {
            margin: 0;

            color:
                var(
                    --text-dark,
                    #0f172a
                );

            font-size: 1rem;
        }

        .pm-programmes-empty p {
            max-width: 400px;

            margin:
                7px 0 0;

            color:
                var(
                    --text-light,
                    #64748b
                );

            font-size: .8rem;

            line-height: 1.5;
        }


        /*
        |--------------------------------------------------------------------------
        | Dark Mode
        |--------------------------------------------------------------------------
        */

        html[data-theme="dark"]
        .pm-programme-card,

        html[data-theme="dark"]
        .pm-programmes-empty {
            background: #1e293b;

            border-color: #334155;
        }

        html[data-theme="dark"]
        .pm-programmes-header h2,

        html[data-theme="dark"]
        .pm-programme-card__heading h3,

        html[data-theme="dark"]
        .pm-programme-stat strong,

        html[data-theme="dark"]
        .pm-programmes-empty h3 {
            color: #f8fafc;
        }

        html[data-theme="dark"]
        .pm-programmes-header p,

        html[data-theme="dark"]
        .pm-programme-card__heading span,

        html[data-theme="dark"]
        .pm-programme-card__description,

        html[data-theme="dark"]
        .pm-programme-stat span,

        html[data-theme="dark"]
        .pm-programme-progress__head span,

        html[data-theme="dark"]
        .pm-programme-card__dates,

        html[data-theme="dark"]
        .pm-programmes-empty p {
            color: #94a3b8;
        }

        html[data-theme="dark"]
        .pm-programme-stat {
            background: #111827;
        }

        html[data-theme="dark"]
        .pm-programme-progress__track {
            background: #334155;
        }


        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media (
            max-width: 1200px
        ) {

            .pm-programmes-grid {
                grid-template-columns:
                    repeat(
                        2,
                        minmax(0, 1fr)
                    );
            }

        }


        @media (
            max-width: 700px
        ) {

            .pm-programmes-grid {
                grid-template-columns:
                    1fr;
            }

            .pm-programme-card {
                padding: 16px;
            }

        }

    </style>

</head>


<body class="dashboard-page">


<div class="dashboard">


    <!-- =====================================================
         SAME SIDEBAR USED BY DASHBOARD
         ===================================================== -->

    <?php
    require __DIR__ . '/sidebar.php';
    ?>


    <!-- =====================================================
         MAIN CONTENT
         ===================================================== -->

    <main class="dashboard__main">


        <!-- =================================================
             EXACT SAME NAVBAR USED BY DASHBOARD
             ================================================= -->

        <?php
        require __DIR__ . '/navbar.php';
        ?>


        <!-- =================================================
             PAGE CONTENT
             ================================================= -->

        <div class="dash-content">


            <?= $flashes ?>


            <!-- =================================================
                 PAGE HEADING
                 ================================================= -->

            <div class="pm-programmes-header">

                <h2>
                    My Programmes
                </h2>

                <p>
                    View and monitor programmes assigned to you.
                </p>

            </div>


            <!-- =================================================
                 PROGRAMMES
                 ================================================= -->

            <?php if (
                empty($programmes)
            ): ?>


                <div class="pm-programmes-empty">

                    <div
                        class="
                            pm-programmes-empty__icon
                        "
                    >

                        <i
                            class="
                                fas
                                fa-graduation-cap
                            "
                        ></i>

                    </div>


                    <h3>
                        No Programmes Assigned
                    </h3>


                    <p>

                        You currently do not have
                        any programmes assigned
                        to you.

                    </p>

                </div>


            <?php else: ?>


                <div class="pm-programmes-grid">


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
                            trim(
                                (string) (
                                    $programme['name']
                                    ?? 'Programme'
                                )
                            );

                        $programmeType =
                            trim(
                                (string) (
                                    $programme['type']
                                    ?? ''
                                )
                            );

                        $programmeStatus =
                            strtolower(
                                trim(
                                    (string) (
                                        $programme['status']
                                        ?? ''
                                    )
                                )
                            );

                        $description =
                            trim(
                                (string) (
                                    $programme['description']
                                    ?? ''
                                )
                            );

                        $candidateCount =
                            (int) (
                                $programme['candidate_count']
                                ?? 0
                            );

                        $cohortCount =
                            (int) (
                                $programme['cohort_count']
                                ?? 0
                            );

                        $completedCount =
                            (int) (
                                $programme['completed_count']
                                ?? 0
                            );

                        $progress =
                            max(
                                0,
                                min(
                                    100,
                                    (int) (
                                        $programme['progress']
                                        ?? 0
                                    )
                                )
                            );


                        $statusClass =
                            match (
                                $programmeStatus
                            ) {

                                'active' =>
                                    'active',

                                'completed' =>
                                    'completed',

                                'draft' =>
                                    'draft',

                                default =>
                                    'inactive'
                            };

                        ?>


                        <article class="pm-programme-card">


                            <!-- =================================
                                 TOP
                                 ================================= -->

                            <div
                                class="
                                    pm-programme-card__top
                                "
                            >


                                <div
                                    class="
                                        pm-programme-card__icon
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
                                        pm-programme-card__heading
                                    "
                                >

                                    <h3>

                                        <?= e(
                                            $programmeName
                                        ) ?>

                                    </h3>


                                    <?php if (
                                        $programmeType !== ''
                                    ): ?>

                                        <span>

                                            <i
                                                class="
                                                    fas
                                                    fa-tag
                                                "
                                            ></i>

                                            <?= e(
                                                ucwords(
                                                    str_replace(
                                                        '_',
                                                        ' ',
                                                        $programmeType
                                                    )
                                                )
                                            ) ?>

                                        </span>

                                    <?php endif; ?>


                                </div>


                                <span
                                    class="
                                        pm-programme-status
                                        pm-programme-status--<?= e(
                                            $statusClass
                                        ) ?>
                                    "
                                >

                                    <?= e(
                                        ucfirst(
                                            $programmeStatus
                                            !== ''
                                                ? $programmeStatus
                                                : 'Unknown'
                                        )
                                    ) ?>

                                </span>


                            </div>


                            <!-- =================================
                                 DESCRIPTION
                                 ================================= -->

                            <p
                                class="
                                    pm-programme-card__description
                                "
                            >

                                <?= e(
                                    $description !== ''
                                        ? $description
                                        : 'No programme description available.'
                                ) ?>

                            </p>


                            <!-- =================================
                                 STATISTICS
                                 ================================= -->

                            <div
                                class="
                                    pm-programme-card__stats
                                "
                            >


                                <div
                                    class="
                                        pm-programme-stat
                                    "
                                >

                                    <strong>

                                        <?= number_format(
                                            $candidateCount
                                        ) ?>

                                    </strong>

                                    <span>
                                        Candidates
                                    </span>

                                </div>


                                <div
                                    class="
                                        pm-programme-stat
                                    "
                                >

                                    <strong>

                                        <?= number_format(
                                            $cohortCount
                                        ) ?>

                                    </strong>

                                    <span>
                                        Cohorts
                                    </span>

                                </div>


                                <div
                                    class="
                                        pm-programme-stat
                                    "
                                >

                                    <strong>

                                        <?= number_format(
                                            $completedCount
                                        ) ?>

                                    </strong>

                                    <span>
                                        Completed
                                    </span>

                                </div>


                            </div>


                            <!-- =================================
                                 PROGRESS
                                 ================================= -->

                            <div
                                class="
                                    pm-programme-progress
                                "
                            >

                                <div
                                    class="
                                        pm-programme-progress__head
                                    "
                                >

                                    <span>
                                        Completion Progress
                                    </span>

                                    <strong>

                                        <?= $progress ?>%

                                    </strong>

                                </div>


                                <div
                                    class="
                                        pm-programme-progress__track
                                    "
                                >

                                    <span
                                        class="
                                            pm-programme-progress__bar
                                        "

                                        style="
                                            width:
                                            <?= $progress ?>%;
                                        "
                                    ></span>

                                </div>


                            </div>


                            <!-- =================================
                                 DATES
                                 ================================= -->

                            <div
                                class="
                                    pm-programme-card__dates
                                "
                            >

                                <i
                                    class="
                                        far
                                        fa-calendar
                                    "
                                ></i>


                                <?php if (
                                    !empty(
                                        $programme['start_date']
                                    )
                                ): ?>

                                    <span>

                                        <?= e(
                                            date(
                                                'd M Y',
                                                strtotime(
                                                    (string)
                                                    $programme['start_date']
                                                )
                                            )
                                        ) ?>

                                        <?php if (
                                            !empty(
                                                $programme['end_date']
                                            )
                                        ): ?>

                                            &nbsp;–&nbsp;

                                            <?= e(
                                                date(
                                                    'd M Y',
                                                    strtotime(
                                                        (string)
                                                        $programme['end_date']
                                                    )
                                                )
                                            ) ?>

                                        <?php endif; ?>

                                    </span>


                                <?php else: ?>


                                    <span>
                                        Start date not set
                                    </span>


                                <?php endif; ?>


                            </div>


                            <!-- =================================
                                 FOOTER
                                 ================================= -->

                            <div
                                class="
                                    pm-programme-card__footer
                                "
                            >

                                <a
                                    href="<?= url(
                                        'programme/programme_view.php?id='
                                        .
                                        $programmeId
                                    ) ?>"

                                    class="
                                        pm-programme-view
                                    "
                                >

                                    <i
                                        class="
                                            fas
                                            fa-eye
                                        "
                                    ></i>

                                    View Programme

                                </a>

                            </div>


                        </article>


                    <?php endforeach; ?>


                </div>


            <?php endif; ?>


        </div>

    </main>

</div>


<!-- =========================================================
     SAME PROGRAMME MANAGER JAVASCRIPT AS DASHBOARD
     ========================================================= -->

<script
    src="<?= url(
        'js/programme_manager_enhancements.js'
    ) ?>?v=20260920"
></script>

<script
    src="<?= url(
        'js/original_pm_sidebar.js'
    ) ?>?v=20260920"
></script>


</body>

</html>