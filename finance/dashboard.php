<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('finance_officer');

$user        = current_user();
$flashes     = render_flashes();
$currentPage = 'dashboard';
$pageTitle   = 'Finance Officer Dashboard';

require_once __DIR__ . '/_helpers.php';

$conn = Database::getConnection();

$uid = (int)(
    $user['id']
    ?? $user['user_id']
    ?? 0
);

if ($uid <= 0) {
    http_response_code(403);
    exit('Invalid Finance Officer account.');
}

/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$stats = [
    'attendance' => 0,
    'draft'      => 0,
    'pending'    => 0,
    'approved'   => 0,
];

/*
|--------------------------------------------------------------------------
| Modal Data
|--------------------------------------------------------------------------
*/

$approvedAttendance = [];
$draftSchedules     = [];
$pendingSchedules   = [];
$approvedSchedules  = [];
$recent             = [];

/*
|--------------------------------------------------------------------------
| Approved Attendance
|--------------------------------------------------------------------------
*/

if (fo_has_table($conn, 'finance_attendance_approved')) {

    /*
    | Count
    */

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM finance_attendance_approved
        WHERE approval_status = 'approved'
    ");

    if (!$stmt) {
        throw new RuntimeException(
            'Approved attendance count query failed: '
            . $conn->error
        );
    }

    $stmt->execute();

    $row = $stmt
        ->get_result()
        ->fetch_assoc();

    $stats['attendance'] = (int)(
        $row['total']
        ?? 0
    );

    $stmt->close();


    /*
    | Modal rows
    */

    $stmt = $conn->prepare("
        SELECT
            id,
            participant_id,
            programme_name,
            cohort_name,
            attendance_date,
            approved_minutes,
            approval_status,
            approved_by,
            approved_at,
            source_attendance_id,
            created_at
        FROM finance_attendance_approved
        WHERE approval_status = 'approved'
        ORDER BY
            attendance_date DESC,
            approved_at DESC,
            id DESC
        LIMIT 100
    ");

    if (!$stmt) {
        throw new RuntimeException(
            'Approved attendance query failed: '
            . $conn->error
        );
    }

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $approvedAttendance[] = $row;
    }

    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| Stipend Schedule Statistics
|--------------------------------------------------------------------------
*/

if (fo_has_table($conn, 'stipend_schedules')) {

    $stmt = $conn->prepare("
        SELECT
            COALESCE(
                SUM(status = 'draft'),
                0
            ) AS draft,

            COALESCE(
                SUM(status = 'awaiting_approval'),
                0
            ) AS pending,

            COALESCE(
                SUM(status = 'approved'),
                0
            ) AS approved

        FROM stipend_schedules

        WHERE created_by = ?
    ");

    if (!$stmt) {
        throw new RuntimeException(
            'Finance dashboard statistics query failed: '
            . $conn->error
        );
    }

    $stmt->bind_param(
        'i',
        $uid
    );

    $stmt->execute();

    $row = $stmt
        ->get_result()
        ->fetch_assoc();

    $stats['draft'] = (int)(
        $row['draft']
        ?? 0
    );

    $stats['pending'] = (int)(
        $row['pending']
        ?? 0
    );

    $stats['approved'] = (int)(
        $row['approved']
        ?? 0
    );

    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| Recent Schedules
|--------------------------------------------------------------------------
*/

if (fo_has_table($conn, 'stipend_schedules')) {

    $stmt = $conn->prepare("
        SELECT
            id,
            schedule_reference,
            period_start,
            period_end,
            status,
            participant_count,
            total_amount

        FROM stipend_schedules

        WHERE created_by = ?

        ORDER BY
            created_at DESC,
            id DESC

        LIMIT 5
    ");

    if (!$stmt) {
        throw new RuntimeException(
            'Recent schedules query failed: '
            . $conn->error
        );
    }

    $stmt->bind_param(
        'i',
        $uid
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $recent[] = $row;
    }

    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| Schedule Modal Data
|--------------------------------------------------------------------------
*/

if (fo_has_table($conn, 'stipend_schedules')) {

    $statuses = [
        'draft'             => &$draftSchedules,
        'awaiting_approval' => &$pendingSchedules,
        'approved'          => &$approvedSchedules,
    ];

    foreach (
        $statuses as $status => &$target
    ) {

        $stmt = $conn->prepare("
            SELECT
                id,
                schedule_reference,
                period_start,
                period_end,
                status,
                participant_count,
                total_amount

            FROM stipend_schedules

            WHERE created_by = ?
              AND status = ?

            ORDER BY
                created_at DESC,
                id DESC

            LIMIT 100
        ");

        if (!$stmt) {
            throw new RuntimeException(
                'Schedule modal query failed: '
                . $conn->error
            );
        }

        $stmt->bind_param(
            'is',
            $uid,
            $status
        );

        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $target[] = $row;
        }

        $stmt->close();
    }

    unset($target);
}

/*
|--------------------------------------------------------------------------
| Finance Configuration
|--------------------------------------------------------------------------
*/

$cfg = fo_config($conn);

$first = trim(
    (string)(
        $user['first_name']
        ?? 'Finance Officer'
    )
);

/*
|--------------------------------------------------------------------------
| Layout
|--------------------------------------------------------------------------
*/

require __DIR__ . '/_layout_start.php';

?>

<style>

/* ================================================================
   INTERACTIVE STAT CARDS
================================================================ */

.fo-stat--interactive {
    cursor: pointer;

    position: relative;

    transition:
        transform .18s ease,
        box-shadow .18s ease,
        border-color .18s ease;
}

.fo-stat--interactive:hover {
    transform: translateY(-4px);
}

.fo-stat--interactive:focus-visible {
    outline: 3px solid currentColor;
    outline-offset: 3px;
}

.fo-stat__hint {
    display: block;

    margin-top: 6px;

    font-size: 10px;

    opacity: .55;
}


/* ================================================================
   MODAL
================================================================ */

.fo-dashboard-modal {
    position: fixed;
    inset: 0;

    z-index: 99999;

    display: none;
    align-items: center;
    justify-content: center;

    padding: 20px;
}

.fo-dashboard-modal.is-open {
    display: flex;
}

.fo-dashboard-modal__backdrop {
    position: absolute;
    inset: 0;

    background:
        rgba(15, 23, 42, .72);

    backdrop-filter: blur(5px);
}

.fo-dashboard-modal__dialog {
    position: relative;
    z-index: 1;

    width: min(850px, 100%);
    max-height: 86vh;

    display: flex;
    flex-direction: column;

    overflow: hidden;

    border-radius: 24px;

    background: #ffffff;

    box-shadow:
        0 30px 90px
        rgba(0, 0, 0, .30);

    animation:
        foDashboardModalOpen
        .2s ease-out;
}

@keyframes foDashboardModalOpen {

    from {
        opacity: 0;

        transform:
            translateY(14px)
            scale(.98);
    }

    to {
        opacity: 1;

        transform:
            translateY(0)
            scale(1);
    }
}


/* ================================================================
   MODAL HEADER
================================================================ */

.fo-dashboard-modal__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;

    gap: 18px;

    padding:
        22px 24px 18px;

    border-bottom:
        1px solid
        rgba(127, 127, 127, .14);
}

.fo-dashboard-modal__heading {
    display: flex;
    align-items: center;

    gap: 13px;
}

.fo-dashboard-modal__icon {
    width: 48px;
    height: 48px;

    flex: 0 0 48px;

    display: grid;
    place-items: center;

    border-radius: 15px;

    background:
        rgba(37, 99, 235, .10);

    font-size: 19px;
}

.fo-dashboard-modal__header h3 {
    margin: 0;
}

.fo-dashboard-modal__header p {
    margin:
        5px 0 0;

    font-size: 13px;

    opacity: .65;
}

.fo-dashboard-modal__close {
    width: 42px;
    height: 42px;

    flex: 0 0 42px;

    display: grid;
    place-items: center;

    border: 0;
    border-radius: 50%;

    background:
        rgba(127, 127, 127, .10);

    color: inherit;

    cursor: pointer;
}


/* ================================================================
   MODAL BODY
================================================================ */

.fo-dashboard-modal__body {
    flex: 1;

    overflow-y: auto;

    padding: 20px 24px;

    overscroll-behavior: contain;
}

.fo-dashboard-panel {
    display: none;
}

.fo-dashboard-panel.is-active {
    display: block;
}


/* ================================================================
   MODAL SUMMARY
================================================================ */

.fo-modal-summary {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 15px;

    margin-bottom: 16px;

    padding: 14px 16px;

    border-radius: 14px;

    background:
        rgba(127, 127, 127, .06);
}

.fo-modal-summary strong {
    display: block;

    font-size: 20px;
}

.fo-modal-summary span {
    display: block;

    margin-top: 3px;

    font-size: 12px;

    opacity: .65;
}


/* ================================================================
   MODAL ROW
================================================================ */

.fo-modal-list {
    display: flex;
    flex-direction: column;
}

.fo-modal-row {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 18px;

    padding: 15px 4px;

    border-bottom:
        1px solid
        rgba(127, 127, 127, .12);
}

.fo-modal-row:last-child {
    border-bottom: 0;
}

.fo-modal-row__main {
    flex: 1;

    min-width: 0;
}

.fo-modal-row__main strong {
    display: block;
}

.fo-modal-row__main span {
    display: block;

    margin-top: 4px;

    font-size: 12px;

    opacity: .67;
}

.fo-modal-row__side {
    flex: 0 0 auto;

    text-align: right;
}

.fo-modal-row__side strong,
.fo-modal-row__side span {
    display: block;
}

.fo-modal-row__side span {
    margin-top: 4px;

    font-size: 11px;

    opacity: .6;
}


/* ================================================================
   ATTENDANCE
================================================================ */

.fo-attendance-minutes {
    display: inline-flex;
    align-items: center;

    gap: 5px;

    font-weight: 700;
}


/* ================================================================
   EMPTY
================================================================ */

.fo-modal-empty {
    min-height: 260px;

    display: flex;
    flex-direction: column;

    align-items: center;
    justify-content: center;

    gap: 8px;

    text-align: center;
}

.fo-modal-empty i {
    font-size: 32px;

    opacity: .45;
}

.fo-modal-empty span {
    font-size: 13px;

    opacity: .65;
}


/* ================================================================
   FOOTER
================================================================ */

.fo-dashboard-modal__footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;

    gap: 10px;

    padding:
        14px 24px;

    border-top:
        1px solid
        rgba(127, 127, 127, .14);
}


/* ================================================================
   DARK MODE
================================================================ */

body.dark-mode
.fo-dashboard-modal__dialog,
html[data-theme="dark"]
.fo-dashboard-modal__dialog {
    background: #111827;
    color: #f8fafc;
}

body.dark-mode
.fo-dashboard-modal__close,
html[data-theme="dark"]
.fo-dashboard-modal__close {
    background:
        rgba(255, 255, 255, .08);
}

body.dark-mode
.fo-modal-summary,
html[data-theme="dark"]
.fo-modal-summary {
    background:
        rgba(255, 255, 255, .05);
}

body.fo-dashboard-modal-open {
    overflow: hidden;
}


/* ================================================================
   MOBILE
================================================================ */

@media (max-width: 650px) {

    .fo-dashboard-modal {
        align-items: flex-end;

        padding: 0;
    }

    .fo-dashboard-modal__dialog {
        width: 100%;
        max-height: 92vh;

        border-radius:
            22px 22px 0 0;
    }

    .fo-dashboard-modal__header {
        padding:
            18px 17px 15px;
    }

    .fo-dashboard-modal__body {
        padding:
            16px 17px;
    }

    .fo-dashboard-modal__footer {
        padding:
            13px 17px;
    }

    .fo-modal-row {
        align-items: flex-start;

        flex-direction: column;
    }

    .fo-modal-row__side {
        text-align: left;
    }

}

</style>


<!-- ================================================================
     HERO
================================================================ -->

<section class="fo-hero">

    <div>

        <span class="fo-eyebrow">

            <i class="fas fa-money-check-dollar"></i>

            Stipend Operations

        </span>


        <h2>
            Accurate schedules from approved attendance,
            <?= e($first) ?>.
        </h2>


        <p>
            Prepare stipend schedules using approved attendance,
            expose every calculation basis and exception, preserve
            maker/checker segregation, and retain immutable approved
            history.
        </p>


        <div class="fo-hero__actions">

            <a
                class="fo-btn fo-btn--light"
                href="<?= url(
                    'finance_officer/attendance.php'
                ) ?>"
            >

                <i class="fas fa-calendar-check"></i>

                Approved Attendance

            </a>


            <a
                class="fo-btn fo-btn--glass"
                href="<?= url(
                    'finance_officer/schedules.php'
                ) ?>"
            >

                <i class="fas fa-file-invoice-dollar"></i>

                Schedules

            </a>

        </div>

    </div>


    <div class="fo-orb">

        <i class="fas fa-user-shield"></i>

        <strong>
            <?= (int)(
                $cfg['maker_checker_enabled']
                ?? 0
            ) === 1
                ? 'ON'
                : 'OFF' ?>
        </strong>

        <span>
            maker/checker segregation
        </span>

    </div>

</section>


<!-- ================================================================
     STAT CARDS
================================================================ -->

<section class="fo-stats">


    <!-- APPROVED ATTENDANCE -->

    <article
        class="fo-stat--interactive"
        role="button"
        tabindex="0"
        data-finance-modal="attendance"
    >

        <i class="fas fa-calendar-check"></i>

        <strong>
            <?= number_format(
                $stats['attendance']
            ) ?>
        </strong>

        <span>
            Approved Attendance
        </span>

        <small class="fo-stat__hint">
            Click to view
        </small>

    </article>


    <!-- DRAFT -->

    <article
        class="fo-stat--interactive"
        role="button"
        tabindex="0"
        data-finance-modal="draft"
    >

        <i class="fas fa-file-pen"></i>

        <strong>
            <?= number_format(
                $stats['draft']
            ) ?>
        </strong>

        <span>
            Draft Schedules
        </span>

        <small class="fo-stat__hint">
            Click to view
        </small>

    </article>


    <!-- PENDING -->

    <article
        class="fo-stat--interactive"
        role="button"
        tabindex="0"
        data-finance-modal="pending"
    >

        <i class="fas fa-user-check"></i>

        <strong>
            <?= number_format(
                $stats['pending']
            ) ?>
        </strong>

        <span>
            Awaiting Approval
        </span>

        <small class="fo-stat__hint">
            Click to view
        </small>

    </article>


    <!-- APPROVED -->

    <article
        class="fo-stat--interactive"
        role="button"
        tabindex="0"
        data-finance-modal="approved"
    >

        <i class="fas fa-circle-check"></i>

        <strong>
            <?= number_format(
                $stats['approved']
            ) ?>
        </strong>

        <span>
            Approved Schedules
        </span>

        <small class="fo-stat__hint">
            Click to view
        </small>

    </article>

</section>


<!-- ================================================================
     MAIN GRID
================================================================ -->

<div class="fo-grid">


    <!-- RECENT SCHEDULES -->

    <section class="fo-card">

        <div class="fo-card__header">

            <h3>
                Recent Schedules
            </h3>

        </div>


        <?php if (!$recent): ?>

            <div class="fo-empty">

                <i class="fas fa-file-invoice-dollar"></i>

                <strong>
                    No schedules yet
                </strong>

                <span>
                    Prepare one from approved attendance.
                </span>

            </div>

        <?php else: ?>

            <div class="fo-list">

                <?php foreach ($recent as $schedule): ?>

                    <a
                        href="<?= url(
                            'finance_officer/schedule_view.php?id='
                            . (int)$schedule['id']
                        ) ?>"
                    >

                        <div>

                            <strong>
                                <?= e(
                                    $schedule[
                                        'schedule_reference'
                                    ]
                                ) ?>
                            </strong>

                            <span>

                                <?= e(
                                    fo_date(
                                        $schedule[
                                            'period_start'
                                        ]
                                    )
                                ) ?>

                                -

                                <?= e(
                                    fo_date(
                                        $schedule[
                                            'period_end'
                                        ]
                                    )
                                ) ?>

                                ·

                                <?= e(
                                    fo_money(
                                        $schedule[
                                            'total_amount'
                                        ]
                                    )
                                ) ?>

                            </span>

                        </div>


                        <span
                            class="
                                fo-status
                                fo-status--<?= e(
                                    fo_status_class(
                                        $schedule['status']
                                    )
                                ) ?>
                            "
                        >

                            <?= e(
                                fo_label(
                                    $schedule['status']
                                )
                            ) ?>

                        </span>

                    </a>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>


    <!-- CONTROL FRAMEWORK -->

    <section class="fo-card">

        <div class="fo-card__header">

            <h3>
                Control Framework
            </h3>

        </div>


        <div class="fo-principles">

            <div>

                <i class="fas fa-calculator"></i>

                <strong>
                    Visible calculation basis
                </strong>

                <span>
                    Attendance, rate, eligible days
                    and exceptions stay visible.
                </span>

            </div>


            <div>

                <i class="fas fa-user-shield"></i>

                <strong>
                    Maker cannot self-approve
                </strong>

                <span>
                    Segregation blocks final approval
                    by the maker.
                </span>

            </div>


            <div>

                <i class="fas fa-lock"></i>

                <strong>
                    Approved history is immutable
                </strong>

                <span>
                    Corrections create revisions instead
                    of silent edits.
                </span>

            </div>

        </div>

    </section>

</div>


<!-- ================================================================
     SHARED MODAL
================================================================ -->

<div
    class="fo-dashboard-modal"
    id="financeDashboardModal"
    aria-hidden="true"
>

    <div
        class="fo-dashboard-modal__backdrop"
        data-finance-close
    ></div>


    <section
        class="fo-dashboard-modal__dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="financeDashboardModalTitle"
    >


        <!-- HEADER -->

        <header class="fo-dashboard-modal__header">

            <div class="fo-dashboard-modal__heading">

                <span class="fo-dashboard-modal__icon">

                    <i
                        class="fas fa-chart-column"
                        id="financeDashboardModalIcon"
                    ></i>

                </span>


                <div>

                    <h3 id="financeDashboardModalTitle">
                        Finance Details
                    </h3>

                    <p id="financeDashboardModalDescription">
                        Finance Officer dashboard details.
                    </p>

                </div>

            </div>


            <button
                type="button"
                class="fo-dashboard-modal__close"
                data-finance-close
                aria-label="Close"
            >

                <i class="fas fa-xmark"></i>

            </button>

        </header>


        <!-- BODY -->

        <div class="fo-dashboard-modal__body">


            <!-- ====================================================
                 APPROVED ATTENDANCE
            ===================================================== -->

            <section
                class="fo-dashboard-panel"
                data-finance-panel="attendance"
            >

                <div class="fo-modal-summary">

                    <div>

                        <strong>
                            <?= number_format(
                                $stats['attendance']
                            ) ?>
                        </strong>

                        <span>
                            Approved attendance records
                        </span>

                    </div>

                    <a
                        class="fo-btn fo-btn--primary"
                        href="<?= url(
                            'finance_officer/attendance.php'
                        ) ?>"
                    >
                        View Attendance
                    </a>

                </div>


                <?php if (!$approvedAttendance): ?>

                    <div class="fo-modal-empty">

                        <i class="fas fa-calendar-check"></i>

                        <strong>
                            No approved attendance
                        </strong>

                        <span>
                            Approved attendance records
                            will appear here.
                        </span>

                    </div>

                <?php else: ?>

                    <div class="fo-modal-list">

                        <?php foreach (
                            $approvedAttendance
                            as $attendance
                        ): ?>

                            <div class="fo-modal-row">

                                <div class="fo-modal-row__main">

                                    <strong>

                                        Participant
                                        #<?= number_format(
                                            (int)$attendance[
                                                'participant_id'
                                            ]
                                        ) ?>

                                    </strong>


                                    <span>

                                        <?= e(
                                            $attendance[
                                                'programme_name'
                                            ]
                                            ?: 'Programme not specified'
                                        ) ?>

                                        <?php if (
                                            !empty(
                                                $attendance[
                                                    'cohort_name'
                                                ]
                                            )
                                        ): ?>

                                            ·
                                            <?= e(
                                                $attendance[
                                                    'cohort_name'
                                                ]
                                            ) ?>

                                        <?php endif; ?>

                                    </span>


                                    <span>

                                        Attendance:

                                        <?= e(
                                            fo_date(
                                                $attendance[
                                                    'attendance_date'
                                                ]
                                            )
                                        ) ?>

                                    </span>

                                </div>


                                <div class="fo-modal-row__side">

                                    <strong class="fo-attendance-minutes">

                                        <i class="far fa-clock"></i>

                                        <?= number_format(
                                            (int)$attendance[
                                                'approved_minutes'
                                            ]
                                        ) ?>

                                        min

                                    </strong>


                                    <span>

                                        <?php if (
                                            !empty(
                                                $attendance[
                                                    'approved_at'
                                                ]
                                            )
                                        ): ?>

                                            Approved
                                            <?= e(
                                                fo_date(
                                                    $attendance[
                                                        'approved_at'
                                                    ]
                                                )
                                            ) ?>

                                        <?php else: ?>

                                            Approved

                                        <?php endif; ?>

                                    </span>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </section>


            <!-- ====================================================
                 DRAFT SCHEDULES
            ===================================================== -->

            <section
                class="fo-dashboard-panel"
                data-finance-panel="draft"
            >

                <div class="fo-modal-summary">

                    <div>

                        <strong>
                            <?= number_format(
                                $stats['draft']
                            ) ?>
                        </strong>

                        <span>
                            Draft stipend schedules
                        </span>

                    </div>

                    <a
                        class="fo-btn fo-btn--primary"
                        href="<?= url(
                            'finance_officer/schedules.php'
                        ) ?>"
                    >
                        View Schedules
                    </a>

                </div>


                <?php
                $modalSchedules =
                    $draftSchedules;
                ?>


                <?php if (!$modalSchedules): ?>

                    <div class="fo-modal-empty">

                        <i class="fas fa-file-pen"></i>

                        <strong>
                            No draft schedules
                        </strong>

                        <span>
                            Draft stipend schedules will
                            appear here.
                        </span>

                    </div>

                <?php else: ?>

                    <div class="fo-modal-list">

                        <?php foreach (
                            $modalSchedules
                            as $schedule
                        ): ?>

                            <a
                                class="fo-modal-row"
                                href="<?= url(
                                    'finance_officer/schedule_view.php?id='
                                    . (int)$schedule['id']
                                ) ?>"
                            >

                                <div class="fo-modal-row__main">

                                    <strong>
                                        <?= e(
                                            $schedule[
                                                'schedule_reference'
                                            ]
                                        ) ?>
                                    </strong>

                                    <span>

                                        <?= e(
                                            fo_date(
                                                $schedule[
                                                    'period_start'
                                                ]
                                            )
                                        ) ?>

                                        -

                                        <?= e(
                                            fo_date(
                                                $schedule[
                                                    'period_end'
                                                ]
                                            )
                                        ) ?>

                                    </span>

                                </div>


                                <div class="fo-modal-row__side">

                                    <strong>
                                        <?= e(
                                            fo_money(
                                                $schedule[
                                                    'total_amount'
                                                ]
                                            )
                                        ) ?>
                                    </strong>

                                    <span>
                                        <?= number_format(
                                            (int)$schedule[
                                                'participant_count'
                                            ]
                                        ) ?>
                                        participants
                                    </span>

                                </div>

                            </a>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </section>


            <!-- ====================================================
                 AWAITING APPROVAL
            ===================================================== -->

            <section
                class="fo-dashboard-panel"
                data-finance-panel="pending"
            >

                <div class="fo-modal-summary">

                    <div>

                        <strong>
                            <?= number_format(
                                $stats['pending']
                            ) ?>
                        </strong>

                        <span>
                            Schedules awaiting approval
                        </span>

                    </div>

                    <a
                        class="fo-btn fo-btn--primary"
                        href="<?= url(
                            'finance_officer/schedules.php'
                        ) ?>"
                    >
                        View Schedules
                    </a>

                </div>


                <?php
                $modalSchedules =
                    $pendingSchedules;
                ?>


                <?php if (!$modalSchedules): ?>

                    <div class="fo-modal-empty">

                        <i class="fas fa-user-check"></i>

                        <strong>
                            Nothing awaiting approval
                        </strong>

                        <span>
                            Submitted schedules will
                            appear here.
                        </span>

                    </div>

                <?php else: ?>

                    <div class="fo-modal-list">

                        <?php foreach (
                            $modalSchedules
                            as $schedule
                        ): ?>

                            <a
                                class="fo-modal-row"
                                href="<?= url(
                                    'finance_officer/schedule_view.php?id='
                                    . (int)$schedule['id']
                                ) ?>"
                            >

                                <div class="fo-modal-row__main">

                                    <strong>
                                        <?= e(
                                            $schedule[
                                                'schedule_reference'
                                            ]
                                        ) ?>
                                    </strong>

                                    <span>

                                        <?= e(
                                            fo_date(
                                                $schedule[
                                                    'period_start'
                                                ]
                                            )
                                        ) ?>

                                        -

                                        <?= e(
                                            fo_date(
                                                $schedule[
                                                    'period_end'
                                                ]
                                            )
                                        ) ?>

                                    </span>

                                </div>


                                <div class="fo-modal-row__side">

                                    <strong>
                                        <?= e(
                                            fo_money(
                                                $schedule[
                                                    'total_amount'
                                                ]
                                            )
                                        ) ?>
                                    </strong>

                                    <span>
                                        <?= number_format(
                                            (int)$schedule[
                                                'participant_count'
                                            ]
                                        ) ?>
                                        participants
                                    </span>

                                </div>

                            </a>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </section>


            <!-- ====================================================
                 APPROVED SCHEDULES
            ===================================================== -->

            <section
                class="fo-dashboard-panel"
                data-finance-panel="approved"
            >

                <div class="fo-modal-summary">

                    <div>

                        <strong>
                            <?= number_format(
                                $stats['approved']
                            ) ?>
                        </strong>

                        <span>
                            Approved stipend schedules
                        </span>

                    </div>

                    <a
                        class="fo-btn fo-btn--primary"
                        href="<?= url(
                            'finance_officer/schedules.php'
                        ) ?>"
                    >
                        View Schedules
                    </a>

                </div>


                <?php
                $modalSchedules =
                    $approvedSchedules;
                ?>


                <?php if (!$modalSchedules): ?>

                    <div class="fo-modal-empty">

                        <i class="fas fa-circle-check"></i>

                        <strong>
                            No approved schedules
                        </strong>

                        <span>
                            Approved stipend schedules
                            will appear here.
                        </span>

                    </div>

                <?php else: ?>

                    <div class="fo-modal-list">

                        <?php foreach (
                            $modalSchedules
                            as $schedule
                        ): ?>

                            <a
                                class="fo-modal-row"
                                href="<?= url(
                                    'finance_officer/schedule_view.php?id='
                                    . (int)$schedule['id']
                                ) ?>"
                            >

                                <div class="fo-modal-row__main">

                                    <strong>
                                        <?= e(
                                            $schedule[
                                                'schedule_reference'
                                            ]
                                        ) ?>
                                    </strong>

                                    <span>

                                        <?= e(
                                            fo_date(
                                                $schedule[
                                                    'period_start'
                                                ]
                                            )
                                        ) ?>

                                        -

                                        <?= e(
                                            fo_date(
                                                $schedule[
                                                    'period_end'
                                                ]
                                            )
                                        ) ?>

                                    </span>

                                </div>


                                <div class="fo-modal-row__side">

                                    <strong>
                                        <?= e(
                                            fo_money(
                                                $schedule[
                                                    'total_amount'
                                                ]
                                            )
                                        ) ?>
                                    </strong>

                                    <span>
                                        <?= number_format(
                                            (int)$schedule[
                                                'participant_count'
                                            ]
                                        ) ?>
                                        participants
                                    </span>

                                </div>

                            </a>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </section>

        </div>


        <!-- FOOTER -->

        <footer class="fo-dashboard-modal__footer">

            <button
                type="button"
                class="fo-btn fo-btn--secondary"
                data-finance-close
            >

                <i class="fas fa-xmark"></i>

                Close

            </button>

        </footer>

    </section>

</div>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const modal =
            document.getElementById(
                'financeDashboardModal'
            );

        if (!modal) {
            return;
        }


        const cards =
            document.querySelectorAll(
                '[data-finance-modal]'
            );

        const panels =
            modal.querySelectorAll(
                '[data-finance-panel]'
            );

        const closeButtons =
            modal.querySelectorAll(
                '[data-finance-close]'
            );

        const title =
            document.getElementById(
                'financeDashboardModalTitle'
            );

        const description =
            document.getElementById(
                'financeDashboardModalDescription'
            );

        const icon =
            document.getElementById(
                'financeDashboardModalIcon'
            );


        let lastTrigger = null;


        const modalConfig = {

            attendance: {

                title:
                    'Approved Attendance',

                description:
                    'Attendance records approved for stipend processing.',

                icon:
                    'fas fa-calendar-check'

            },

            draft: {

                title:
                    'Draft Schedules',

                description:
                    'Stipend schedules currently being prepared.',

                icon:
                    'fas fa-file-pen'

            },

            pending: {

                title:
                    'Awaiting Approval',

                description:
                    'Schedules submitted for checker approval.',

                icon:
                    'fas fa-user-check'

            },

            approved: {

                title:
                    'Approved Schedules',

                description:
                    'Approved stipend schedules retained in finance history.',

                icon:
                    'fas fa-circle-check'

            }

        };


        function openModal(
            type,
            trigger
        ) {

            const config =
                modalConfig[type];

            if (!config) {
                return;
            }


            lastTrigger =
                trigger || null;


            panels.forEach(
                function (panel) {

                    panel.classList.toggle(
                        'is-active',
                        panel.dataset.financePanel
                            === type
                    );

                }
            );


            title.textContent =
                config.title;


            description.textContent =
                config.description;


            icon.className =
                config.icon;


            modal.classList.add(
                'is-open'
            );


            modal.setAttribute(
                'aria-hidden',
                'false'
            );


            document.body.classList.add(
                'fo-dashboard-modal-open'
            );


            const close =
                modal.querySelector(
                    '.fo-dashboard-modal__close'
                );


            if (close) {
                close.focus();
            }

        }


        function closeModal() {

            modal.classList.remove(
                'is-open'
            );


            modal.setAttribute(
                'aria-hidden',
                'true'
            );


            document.body.classList.remove(
                'fo-dashboard-modal-open'
            );


            panels.forEach(
                function (panel) {

                    panel.classList.remove(
                        'is-active'
                    );

                }
            );


            if (lastTrigger) {
                lastTrigger.focus();
            }

        }


        cards.forEach(
            function (card) {

                card.addEventListener(
                    'click',
                    function () {

                        openModal(
                            card.dataset.financeModal,
                            card
                        );

                    }
                );


                card.addEventListener(
                    'keydown',
                    function (event) {

                        if (
                            event.key === 'Enter'
                            ||
                            event.key === ' '
                        ) {

                            event.preventDefault();

                            openModal(
                                card.dataset.financeModal,
                                card
                            );

                        }

                    }
                );

            }
        );


        closeButtons.forEach(
            function (button) {

                button.addEventListener(
                    'click',
                    closeModal
                );

            }
        );


        document.addEventListener(
            'keydown',
            function (event) {

                if (
                    event.key === 'Escape'
                    &&
                    modal.classList.contains(
                        'is-open'
                    )
                ) {

                    closeModal();

                }

            }
        );

    }
);

</script>


<?php

require __DIR__ . '/_layout_end.php';

?>