<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('programme_manager');

require_once __DIR__ . '/_helpers.php';


/*
|--------------------------------------------------------------------------
| Current User
|--------------------------------------------------------------------------
*/

$user = current_user();

$flashes = render_flashes();

$conn = Database::getConnection();


/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

$currentPage = 'activity_log';

$pageTitle = 'Activity Log';


/*
|--------------------------------------------------------------------------
| Programme Manager
|--------------------------------------------------------------------------
*/

$userId = (int) (
    $user['id']
    ?? $user['user_id']
    ?? 0
);

if ($userId <= 0) {

    http_response_code(403);

    exit(
        'Invalid Programme Manager account.'
    );
}


/*
|--------------------------------------------------------------------------
| Activity Log
|--------------------------------------------------------------------------
*/

$activityRows = [];

$hasAuditTable = false;


/*
|--------------------------------------------------------------------------
| Check Audit Table
|--------------------------------------------------------------------------
*/

$check = $conn->query(
    "SHOW TABLES LIKE 'audit_logs'"
);

if (
    $check
    &&
    $check->num_rows > 0
) {

    $hasAuditTable = true;
}


/*
|--------------------------------------------------------------------------
| Load Activity
|--------------------------------------------------------------------------
*/

if ($hasAuditTable) {

    $stmt = $conn->prepare("
        SELECT

            id,

            action,

            record_type,

            record_id,

            reason,

            ip_address,

            created_at

        FROM audit_logs

        WHERE user_id = ?

        ORDER BY
            created_at DESC,
            id DESC

        LIMIT 100
    ");

    if ($stmt) {

        $stmt->bind_param(
            'i',
            $userId
        );

        $stmt->execute();

        $result =
            $stmt->get_result();

        while (
            $row =
                $result->fetch_assoc()
        ) {

            $activityRows[] =
                $row;
        }

        $stmt->close();
    }
}


/*
|--------------------------------------------------------------------------
| User Display Name
|--------------------------------------------------------------------------
*/

$firstName =
    trim(
        (string) (
            $user['first_name']
            ?? ''
        )
    );

$lastName =
    trim(
        (string) (
            $user['last_name']
            ?? ''
        )
    );

$displayName =
    trim(
        (string) (
            $user['full_name']
            ??
            $user['fullname']
            ??
            ''
        )
    );

if ($displayName === '') {

    $displayName =
        trim(
            $firstName
            . ' '
            . $lastName
        );
}

if ($displayName === '') {

    $displayName =
        'Programme Manager';
}


/*
|--------------------------------------------------------------------------
| Activity Icon Helper
|--------------------------------------------------------------------------
*/

if (
    !function_exists(
        'pm_activity_icon'
    )
) {

    function pm_activity_icon(
        string $action
    ): string {

        $action =
            strtolower(
                trim($action)
            );

        if (
            str_contains(
                $action,
                'login'
            )
        ) {

            return 'fa-right-to-bracket';
        }

        if (
            str_contains(
                $action,
                'assign'
            )
        ) {

            return 'fa-user-check';
        }

        if (
            str_contains(
                $action,
                'update'
            )
            ||
            str_contains(
                $action,
                'edit'
            )
        ) {

            return 'fa-pen-to-square';
        }

        if (
            str_contains(
                $action,
                'create'
            )
            ||
            str_contains(
                $action,
                'add'
            )
        ) {

            return 'fa-circle-plus';
        }

        if (
            str_contains(
                $action,
                'delete'
            )
            ||
            str_contains(
                $action,
                'remove'
            )
        ) {

            return 'fa-trash';
        }

        if (
            str_contains(
                $action,
                'status'
            )
        ) {

            return 'fa-arrow-right-arrow-left';
        }

        return 'fa-clock-rotate-left';
    }
}


/*
|--------------------------------------------------------------------------
| Activity Title Helper
|--------------------------------------------------------------------------
*/

if (
    !function_exists(
        'pm_activity_title'
    )
) {

    function pm_activity_title(
        string $action
    ): string {

        $action =
            str_replace(
                [
                    '_',
                    '-'
                ],
                ' ',
                $action
            );

        return ucwords(
            trim($action)
        );
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
        Activity Log | Investhood IT
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
    >


    <!-- =====================================================
         SHARED PROGRAMME MANAGER CSS
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
         LOAD SAVED THEME BEFORE PAGE RENDERS
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


    <style>

        /* ====================================================
           ACTIVITY PAGE HEADER
           ==================================================== */

        .pm-activity-header {
            margin-bottom: 22px;
        }

        .pm-activity-header h2 {
            margin: 0;

            color:
                var(
                    --text-dark,
                    #0f172a
                );

            font-size: 1.35rem;

            font-weight: 700;
        }

        .pm-activity-header p {
            margin:
                6px 0 0;

            color:
                var(
                    --text-light,
                    #64748b
                );

            font-size: .84rem;
        }


        /* ====================================================
           SUMMARY
           ==================================================== */

        .pm-activity-summary {
            margin-bottom: 20px;

            display: grid;

            grid-template-columns:
                repeat(
                    3,
                    minmax(0, 1fr)
                );

            gap: 14px;
        }

        .pm-activity-summary__card {
            padding: 16px;

            display: flex;

            align-items: center;

            gap: 13px;

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

            border-radius: 14px;
        }

        .pm-activity-summary__icon {
            width: 42px;

            height: 42px;

            flex:
                0 0 42px;

            display: grid;

            place-items: center;

            border-radius: 12px;

            background: #eff6ff;

            color: #2563eb;
        }

        .pm-activity-summary__copy strong {
            display: block;

            color:
                var(
                    --text-dark,
                    #0f172a
                );

            font-size: 1.1rem;

            font-weight: 800;
        }

        .pm-activity-summary__copy span {
            display: block;

            margin-top: 2px;

            color:
                var(
                    --text-light,
                    #64748b
                );

            font-size: .72rem;
        }


        /* ====================================================
           ACTIVITY LIST
           ==================================================== */

        .pm-log {
            display: grid;

            gap: 12px;
        }

        .pm-log__item {
            padding: 16px;

            display: grid;

            grid-template-columns:
                42px
                minmax(0, 1fr)
                auto;

            gap: 14px;

            align-items: start;

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

            border-radius: 14px;

            transition:
                transform .18s ease,
                box-shadow .18s ease,
                border-color .18s ease;
        }

        .pm-log__item:hover {
            transform:
                translateY(-2px);

            box-shadow:
                0 10px 30px
                rgba(
                    15,
                    23,
                    42,
                    .06
                );
        }

        .pm-log__icon {
            width: 42px;

            height: 42px;

            display: grid;

            place-items: center;

            border-radius: 12px;

            background: #eff6ff;

            color: #2563eb;
        }

        .pm-log__content {
            min-width: 0;
        }

        .pm-log__content h3 {
            margin:
                0 0 5px;

            color:
                var(
                    --text-dark,
                    #0f172a
                );

            font-size: .9rem;

            font-weight: 700;
        }

        .pm-log__content p {
            margin: 0;

            color:
                var(
                    --text-light,
                    #64748b
                );

            font-size: .8rem;

            line-height: 1.55;
        }

        .pm-log__record {
            display: inline-flex;

            margin-left: 5px;

            padding:
                2px 6px;

            border-radius: 5px;

            background: #f1f5f9;

            color: #475569;

            font-size: .68rem;

            font-weight: 600;
        }

        .pm-log__meta {
            min-width: 125px;

            color:
                var(
                    --text-light,
                    #64748b
                );

            font-size: .72rem;

            line-height: 1.6;

            text-align: right;
        }

        .pm-log__meta i {
            width: 14px;

            margin-right: 3px;

            color: #94a3b8;
        }


        /* ====================================================
           EMPTY STATE
           ==================================================== */

        .pm-activity-empty {
            padding:
                45px 20px;

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

            border-radius: 14px;
        }

        .pm-activity-empty__icon {
            width: 56px;

            height: 56px;

            margin-bottom: 13px;

            display: grid;

            place-items: center;

            border-radius: 50%;

            background: #eff6ff;

            color: #2563eb;

            font-size: 20px;
        }

        .pm-activity-empty h3 {
            margin: 0;

            color:
                var(
                    --text-dark,
                    #0f172a
                );

            font-size: .95rem;
        }

        .pm-activity-empty p {
            max-width: 420px;

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


        /* ====================================================
           DARK MODE
           ==================================================== */

        html[data-theme="dark"]
        .pm-activity-header h2,

        html[data-theme="dark"]
        .pm-activity-summary__copy strong,

        html[data-theme="dark"]
        .pm-log__content h3,

        html[data-theme="dark"]
        .pm-activity-empty h3 {
            color: #f8fafc;
        }

        html[data-theme="dark"]
        .pm-activity-header p,

        html[data-theme="dark"]
        .pm-activity-summary__copy span,

        html[data-theme="dark"]
        .pm-log__content p,

        html[data-theme="dark"]
        .pm-log__meta,

        html[data-theme="dark"]
        .pm-activity-empty p {
            color: #94a3b8;
        }

        html[data-theme="dark"]
        .pm-activity-summary__card,

        html[data-theme="dark"]
        .pm-log__item,

        html[data-theme="dark"]
        .pm-activity-empty {
            background: #1e293b;

            border-color: #334155;
        }

        html[data-theme="dark"]
        .pm-log__record {
            background: #334155;

            color: #cbd5e1;
        }


        /* ====================================================
           TABLET
           ==================================================== */

        @media (
            max-width: 900px
        ) {

            .pm-activity-summary {
                grid-template-columns:
                    repeat(
                        2,
                        minmax(0, 1fr)
                    );
            }

        }


        /* ====================================================
           MOBILE
           ==================================================== */

        @media (
            max-width: 650px
        ) {

            .pm-activity-summary {
                grid-template-columns: 1fr;
            }

            .pm-log__item {
                grid-template-columns:
                    38px
                    minmax(0, 1fr);

                gap: 11px;
            }

            .pm-log__icon {
                width: 38px;

                height: 38px;
            }

            .pm-log__meta {
                grid-column: 2;

                min-width: 0;

                text-align: left;
            }

        }

    </style>

</head>


<body class="dashboard-page">


<div class="dashboard">


    <!-- =====================================================
         SAME SIDEBAR AS DASHBOARD
         ===================================================== -->

    <?php
    require __DIR__ . '/sidebar.php';
    ?>


    <!-- =====================================================
         MAIN
         ===================================================== -->

    <main class="dashboard__main">


        <!-- =================================================
             SAME NAVBAR AS DASHBOARD
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

            <div class="pm-activity-header">

                <h2>
                    Activity Log
                </h2>

                <p>
                    Your latest audited Programme Manager actions.
                </p>

            </div>


            <!-- =================================================
                 SUMMARY
                 ================================================= -->

            <div class="pm-activity-summary">


                <div
                    class="
                        pm-activity-summary__card
                    "
                >

                    <div
                        class="
                            pm-activity-summary__icon
                        "
                    >

                        <i
                            class="
                                fas
                                fa-clock-rotate-left
                            "
                        ></i>

                    </div>

                    <div
                        class="
                            pm-activity-summary__copy
                        "
                    >

                        <strong>

                            <?= number_format(
                                count(
                                    $activityRows
                                )
                            ) ?>

                        </strong>

                        <span>
                            Recent audited actions
                        </span>

                    </div>

                </div>


                <div
                    class="
                        pm-activity-summary__card
                    "
                >

                    <div
                        class="
                            pm-activity-summary__icon
                        "
                    >

                        <i
                            class="
                                fas
                                fa-user-shield
                            "
                        ></i>

                    </div>

                    <div
                        class="
                            pm-activity-summary__copy
                        "
                    >

                        <strong>

                            <?= e(
                                $displayName
                            ) ?>

                        </strong>

                        <span>
                            Programme Manager
                        </span>

                    </div>

                </div>


                <div
                    class="
                        pm-activity-summary__card
                    "
                >

                    <div
                        class="
                            pm-activity-summary__icon
                        "
                    >

                        <i
                            class="
                                fas
                                fa-database
                            "
                        ></i>

                    </div>

                    <div
                        class="
                            pm-activity-summary__copy
                        "
                    >

                        <strong>

                            <?= $hasAuditTable
                                ? 'Active'
                                : 'Unavailable'
                            ?>

                        </strong>

                        <span>
                            Audit logging
                        </span>

                    </div>

                </div>


            </div>


            <!-- =================================================
                 NO AUDIT TABLE
                 ================================================= -->

            <?php if (
                !$hasAuditTable
            ): ?>


                <div
                    class="
                        pm-activity-empty
                    "
                >

                    <div
                        class="
                            pm-activity-empty__icon
                        "
                    >

                        <i
                            class="
                                fas
                                fa-database
                            "
                        ></i>

                    </div>

                    <h3>
                        Audit log unavailable
                    </h3>

                    <p>

                        The audit_logs table is not installed.
                        Activity records cannot be displayed
                        until audit logging is available.

                    </p>

                </div>


            <!-- =================================================
                 NO ACTIVITY
                 ================================================= -->

            <?php elseif (
                empty(
                    $activityRows
                )
            ): ?>


                <div
                    class="
                        pm-activity-empty
                    "
                >

                    <div
                        class="
                            pm-activity-empty__icon
                        "
                    >

                        <i
                            class="
                                fas
                                fa-clock-rotate-left
                            "
                        ></i>

                    </div>

                    <h3>
                        No activity recorded
                    </h3>

                    <p>

                        Audited Programme Manager
                        actions will appear here
                        after they are recorded
                        by the platform.

                    </p>

                </div>


            <!-- =================================================
                 ACTIVITY RECORDS
                 ================================================= -->

            <?php else: ?>


                <div class="pm-log">


                    <?php foreach (
                        $activityRows
                        as
                        $activity
                    ): ?>


                        <?php

                        $action =
                            (string) (
                                $activity['action']
                                ?? 'activity'
                            );

                        $reason =
                            trim(
                                (string) (
                                    $activity['reason']
                                    ?? ''
                                )
                            );

                        if ($reason === '') {

                            $reason =
                                'Activity recorded by the platform.';
                        }


                        $recordType =
                            trim(
                                (string) (
                                    $activity['record_type']
                                    ?? ''
                                )
                            );

                        $recordId =
                            (int) (
                                $activity['record_id']
                                ?? 0
                            );

                        $ipAddress =
                            trim(
                                (string) (
                                    $activity['ip_address']
                                    ?? ''
                                )
                            );

                        $createdAt =
                            (string) (
                                $activity['created_at']
                                ?? ''
                            );

                        ?>


                        <article
                            class="pm-log__item"
                        >


                            <!-- Icon -->

                            <div
                                class="
                                    pm-log__icon
                                "
                            >

                                <i
                                    class="
                                        fas
                                        <?= e(
                                            pm_activity_icon(
                                                $action
                                            )
                                        ) ?>
                                    "
                                ></i>

                            </div>


                            <!-- Activity -->

                            <div
                                class="
                                    pm-log__content
                                "
                            >

                                <h3>

                                    <?= e(
                                        pm_activity_title(
                                            $action
                                        )
                                    ) ?>

                                </h3>


                                <p>

                                    <?= e(
                                        $reason
                                    ) ?>


                                    <?php if (
                                        $recordType !== ''
                                    ): ?>


                                        <span
                                            class="
                                                pm-log__record
                                            "
                                        >

                                            <?= e(
                                                ucwords(
                                                    str_replace(
                                                        '_',
                                                        ' ',
                                                        $recordType
                                                    )
                                                )
                                            ) ?>


                                            <?php if (
                                                $recordId > 0
                                            ): ?>

                                                #<?= $recordId ?>

                                            <?php endif; ?>


                                        </span>


                                    <?php endif; ?>


                                </p>

                            </div>


                            <!-- Metadata -->

                            <div
                                class="
                                    pm-log__meta
                                "
                            >


                                <?php if (
                                    $createdAt !== ''
                                ): ?>

                                    <div>

                                        <i
                                            class="
                                                far
                                                fa-calendar
                                            "
                                        ></i>

                                        <?= e(
                                            date(
                                                'd M Y, H:i',
                                                strtotime(
                                                    $createdAt
                                                )
                                            )
                                        ) ?>

                                    </div>

                                <?php endif; ?>


                                <?php if (
                                    $ipAddress !== ''
                                ): ?>

                                    <div>

                                        <i
                                            class="
                                                fas
                                                fa-network-wired
                                            "
                                        ></i>

                                        <?= e(
                                            $ipAddress
                                        ) ?>

                                    </div>

                                <?php endif; ?>


                            </div>


                        </article>


                    <?php endforeach; ?>


                </div>


            <?php endif; ?>


        </div>

    </main>

</div>


<!-- =========================================================
     SAME PROGRAMME MANAGER JS AS DASHBOARD
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