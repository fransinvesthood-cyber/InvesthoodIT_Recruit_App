<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('recruiter');

$user        = current_user();
$flashes     = render_flashes();
$currentPage = 'activity_log';
$pageTitle   = 'Activity Log';

require_once __DIR__ . '/_helpers.php';

$conn = Database::getConnection();

$recruiterId = (int)(
    $user['id']
    ?? $user['user_id']
    ?? 0
);

if ($recruiterId <= 0) {
    http_response_code(403);
    exit('Invalid Recruiter account.');
}

/*
|--------------------------------------------------------------------------
| Activity Log Availability
|--------------------------------------------------------------------------
*/

$activityLogExists = rc_has_table(
    $conn,
    'recruiter_activity_log'
);

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$search = trim(
    (string)(
        $_GET['q']
        ?? ''
    )
);

$actionFilter = trim(
    (string)(
        $_GET['action']
        ?? ''
    )
);

$entityFilter = trim(
    (string)(
        $_GET['entity_type']
        ?? ''
    )
);

/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$stats = [
    'total'      => 0,
    'today'      => 0,
    'candidates' => 0,
    'shortlists' => 0,
];

/*
|--------------------------------------------------------------------------
| Modal / Summary Data
|--------------------------------------------------------------------------
*/

$todayRows     = [];
$candidateRows = [];
$shortlistRows = [];

/*
|--------------------------------------------------------------------------
| Available Filters
|--------------------------------------------------------------------------
*/

$availableActions = [];
$availableEntities = [];

/*
|--------------------------------------------------------------------------
| Load Statistics
|--------------------------------------------------------------------------
*/

if ($activityLogExists) {

    /*
    |--------------------------------------------------------------------------
    | Total Activity
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM recruiter_activity_log
        WHERE recruiter_id = ?
    ");

    if (!$stmt) {
        throw new RuntimeException(
            'Activity total query failed: '
            . $conn->error
        );
    }

    $stmt->bind_param(
        'i',
        $recruiterId
    );

    $stmt->execute();

    $stats['total'] = (int)(
        $stmt
            ->get_result()
            ->fetch_assoc()['total']
        ?? 0
    );

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Today's Activity
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM recruiter_activity_log
        WHERE recruiter_id = ?
          AND DATE(created_at) = CURDATE()
    ");

    if (!$stmt) {
        throw new RuntimeException(
            'Today activity query failed: '
            . $conn->error
        );
    }

    $stmt->bind_param(
        'i',
        $recruiterId
    );

    $stmt->execute();

    $stats['today'] = (int)(
        $stmt
            ->get_result()
            ->fetch_assoc()['total']
        ?? 0
    );

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Candidate-Related Activity
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM recruiter_activity_log
        WHERE recruiter_id = ?
          AND entity_type = 'candidate'
    ");

    if (!$stmt) {
        throw new RuntimeException(
            'Candidate activity query failed: '
            . $conn->error
        );
    }

    $stmt->bind_param(
        'i',
        $recruiterId
    );

    $stmt->execute();

    $stats['candidates'] = (int)(
        $stmt
            ->get_result()
            ->fetch_assoc()['total']
        ?? 0
    );

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Shortlist-Related Activity
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM recruiter_activity_log
        WHERE recruiter_id = ?
          AND entity_type = 'shortlist'
    ");

    if (!$stmt) {
        throw new RuntimeException(
            'Shortlist activity query failed: '
            . $conn->error
        );
    }

    $stmt->bind_param(
        'i',
        $recruiterId
    );

    $stmt->execute();

    $stats['shortlists'] = (int)(
        $stmt
            ->get_result()
            ->fetch_assoc()['total']
        ?? 0
    );

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Available Actions
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT DISTINCT action
        FROM recruiter_activity_log
        WHERE recruiter_id = ?
          AND action IS NOT NULL
          AND action <> ''
        ORDER BY action ASC
    ");

    if (!$stmt) {
        throw new RuntimeException(
            'Activity actions query failed: '
            . $conn->error
        );
    }

    $stmt->bind_param(
        'i',
        $recruiterId
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $availableActions[] =
            (string)$row['action'];
    }

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Available Entity Types
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT DISTINCT entity_type
        FROM recruiter_activity_log
        WHERE recruiter_id = ?
          AND entity_type IS NOT NULL
          AND entity_type <> ''
        ORDER BY entity_type ASC
    ");

    if (!$stmt) {
        throw new RuntimeException(
            'Activity entity query failed: '
            . $conn->error
        );
    }

    $stmt->bind_param(
        'i',
        $recruiterId
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $availableEntities[] =
            (string)$row['entity_type'];
    }

    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| Load Activity Log
|--------------------------------------------------------------------------
*/

$rows = [];

if ($activityLogExists) {

    $sql = "
        SELECT
            id,
            recruiter_id,
            action,
            description,
            entity_type,
            entity_id,
            ip_address,
            user_agent,
            created_at

        FROM recruiter_activity_log

        WHERE recruiter_id = ?
    ";

    $types  = 'i';
    $params = [$recruiterId];

    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    */

    if ($search !== '') {

        $sql .= "
            AND (
                action LIKE ?
                OR description LIKE ?
                OR entity_type LIKE ?
                OR CAST(entity_id AS CHAR) LIKE ?
            )
        ";

        $like = '%' . $search . '%';

        $types .= 'ssss';

        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    /*
    |--------------------------------------------------------------------------
    | Action Filter
    |--------------------------------------------------------------------------
    */

    if ($actionFilter !== '') {

        $sql .= "
            AND action = ?
        ";

        $types .= 's';
        $params[] = $actionFilter;
    }

    /*
    |--------------------------------------------------------------------------
    | Entity Filter
    |--------------------------------------------------------------------------
    */

    if ($entityFilter !== '') {

        $sql .= "
            AND entity_type = ?
        ";

        $types .= 's';
        $params[] = $entityFilter;
    }

    $sql .= "
        ORDER BY
            created_at DESC,
            id DESC

        LIMIT 200
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException(
            'Activity log query failed: '
            . $conn->error
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Dynamic bind_param
    |--------------------------------------------------------------------------
    */

    $stmt->bind_param(
        $types,
        ...$params
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| Modal Data
|--------------------------------------------------------------------------
*/

if ($activityLogExists) {

    /*
    |--------------------------------------------------------------------------
    | Today's Activity
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT
            id,
            action,
            description,
            entity_type,
            entity_id,
            created_at

        FROM recruiter_activity_log

        WHERE recruiter_id = ?
          AND DATE(created_at) = CURDATE()

        ORDER BY
            created_at DESC,
            id DESC

        LIMIT 100
    ");

    if (!$stmt) {
        throw new RuntimeException(
            'Today modal query failed: '
            . $conn->error
        );
    }

    $stmt->bind_param(
        'i',
        $recruiterId
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $todayRows[] = $row;
    }

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Candidate Activity
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT
            id,
            action,
            description,
            entity_type,
            entity_id,
            created_at

        FROM recruiter_activity_log

        WHERE recruiter_id = ?
          AND entity_type = 'candidate'

        ORDER BY
            created_at DESC,
            id DESC

        LIMIT 100
    ");

    if (!$stmt) {
        throw new RuntimeException(
            'Candidate activity modal query failed: '
            . $conn->error
        );
    }

    $stmt->bind_param(
        'i',
        $recruiterId
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $candidateRows[] = $row;
    }

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Shortlist Activity
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT
            id,
            action,
            description,
            entity_type,
            entity_id,
            created_at

        FROM recruiter_activity_log

        WHERE recruiter_id = ?
          AND entity_type = 'shortlist'

        ORDER BY
            created_at DESC,
            id DESC

        LIMIT 100
    ");

    if (!$stmt) {
        throw new RuntimeException(
            'Shortlist activity modal query failed: '
            . $conn->error
        );
    }

    $stmt->bind_param(
        'i',
        $recruiterId
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $shortlistRows[] = $row;
    }

    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| Layout
|--------------------------------------------------------------------------
*/

require __DIR__ . '/_layout_start.php';

?>

<style>

/* ================================================================
   SUMMARY
================================================================ */

.rc-activity-stats {
    display: grid;
    grid-template-columns:
        repeat(4, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 22px;
}

.rc-activity-stat {
    padding: 20px;

    border-radius: 18px;

    border:
        1px solid
        rgba(127, 127, 127, .14);

    background:
        var(--rc-card, #fff);

    transition:
        transform .2s ease,
        box-shadow .2s ease;
}

.rc-activity-stat--interactive {
    cursor: pointer;
}

.rc-activity-stat--interactive:hover {
    transform: translateY(-4px);
}

.rc-activity-stat--interactive:focus-visible {
    outline: 3px solid currentColor;
    outline-offset: 3px;
}

.rc-activity-stat i {
    display: block;
    margin-bottom: 12px;
    font-size: 20px;
}

.rc-activity-stat strong {
    display: block;
    font-size: 26px;
    line-height: 1;
}

.rc-activity-stat span {
    display: block;
    margin-top: 8px;
    opacity: .7;
}

.rc-activity-stat small {
    display: block;
    margin-top: 7px;
    font-size: 11px;
    opacity: .55;
}


/* ================================================================
   FILTERS
================================================================ */

.rc-activity-filters {
    display: grid;

    grid-template-columns:
        minmax(220px, 2fr)
        minmax(170px, 1fr)
        minmax(170px, 1fr)
        auto;

    gap: 12px;

    align-items: end;

    padding: 18px;

    margin-bottom: 20px;
}

.rc-activity-field {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.rc-activity-field label {
    font-size: 12px;
    font-weight: 700;
    opacity: .75;
}

.rc-activity-field input,
.rc-activity-field select {
    width: 100%;
    min-height: 42px;

    padding: 9px 12px;

    border:
        1px solid
        rgba(127, 127, 127, .25);

    border-radius: 10px;

    background: transparent;
    color: inherit;
}

.rc-activity-filter-actions {
    display: flex;
    gap: 8px;
}


/* ================================================================
   ACTIVITY
================================================================ */

.rc-activity-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 14px;

    margin-top: 8px;

    font-size: 12px;
    opacity: .65;
}

.rc-activity-meta span {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.rc-activity-description {
    margin-bottom: 0;
}


/* ================================================================
   MODAL
================================================================ */

.rc-activity-modal {
    position: fixed;
    inset: 0;
    z-index: 10000;

    display: none;
    align-items: center;
    justify-content: center;

    padding: 24px;
}

.rc-activity-modal.is-open {
    display: flex;
}

.rc-activity-modal__backdrop {
    position: absolute;
    inset: 0;

    background: rgba(15, 23, 42, .7);
    backdrop-filter: blur(5px);
}

.rc-activity-modal__dialog {
    position: relative;
    z-index: 1;

    width: min(780px, 100%);
    max-height: 85vh;

    display: flex;
    flex-direction: column;

    overflow: hidden;

    border-radius: 24px;

    background: #fff;

    box-shadow:
        0 25px 80px
        rgba(0, 0, 0, .28);
}

.rc-activity-modal__header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;

    gap: 20px;

    padding: 24px 26px 18px;

    border-bottom:
        1px solid
        rgba(127, 127, 127, .16);
}

.rc-activity-modal__header h3 {
    margin: 5px 0;
}

.rc-activity-modal__header p {
    margin: 0;
    opacity: .7;
}

.rc-activity-modal__close {
    width: 42px;
    height: 42px;

    flex: 0 0 42px;

    display: grid;
    place-items: center;

    border: 0;
    border-radius: 50%;

    cursor: pointer;

    background:
        rgba(127, 127, 127, .1);

    color: inherit;
}

.rc-activity-modal__body {
    flex: 1;
    overflow-y: auto;

    padding: 20px 26px;
}

.rc-activity-modal__footer {
    display: flex;
    justify-content: flex-end;

    padding: 16px 26px;

    border-top:
        1px solid
        rgba(127, 127, 127, .16);
}

.rc-activity-panel {
    display: none;
}

.rc-activity-panel.is-active {
    display: block;
}

.rc-activity-modal-row {
    display: flex;
    align-items: flex-start;

    gap: 14px;

    padding: 15px 4px;

    border-bottom:
        1px solid
        rgba(127, 127, 127, .13);
}

.rc-activity-modal-row:last-child {
    border-bottom: 0;
}

.rc-activity-modal-row__icon {
    width: 42px;
    height: 42px;

    flex: 0 0 42px;

    display: grid;
    place-items: center;

    border-radius: 12px;

    background:
        rgba(127, 127, 127, .1);
}

.rc-activity-modal-row__content {
    flex: 1;
    min-width: 0;
}

.rc-activity-modal-row__content strong,
.rc-activity-modal-row__content span,
.rc-activity-modal-row__content small {
    display: block;
}

.rc-activity-modal-row__content span {
    margin-top: 3px;
}

.rc-activity-modal-row__content small {
    margin-top: 5px;
    opacity: .65;
}

.rc-activity-empty {
    min-height: 220px;

    display: flex;
    flex-direction: column;

    align-items: center;
    justify-content: center;

    gap: 9px;

    text-align: center;
}

.rc-activity-empty i {
    font-size: 34px;
    opacity: .5;
}


/* ================================================================
   DARK MODE
================================================================ */

body.dark-mode .rc-activity-stat,
html[data-theme="dark"] .rc-activity-stat,
body.dark-mode .rc-activity-modal__dialog,
html[data-theme="dark"] .rc-activity-modal__dialog {
    background: #111827;
    color: #f8fafc;
}

body.dark-mode .rc-activity-modal__close,
html[data-theme="dark"] .rc-activity-modal__close,
body.dark-mode .rc-activity-modal-row__icon,
html[data-theme="dark"] .rc-activity-modal-row__icon {
    background:
        rgba(255, 255, 255, .08);
}

body.dark-mode .rc-activity-field input,
body.dark-mode .rc-activity-field select,
html[data-theme="dark"] .rc-activity-field input,
html[data-theme="dark"] .rc-activity-field select {
    color: #f8fafc;
}

body.rc-activity-modal-open {
    overflow: hidden;
}


/* ================================================================
   RESPONSIVE
================================================================ */

@media (max-width: 1000px) {

    .rc-activity-stats {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }

    .rc-activity-filters {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }

}

@media (max-width: 600px) {

    .rc-activity-stats,
    .rc-activity-filters {
        grid-template-columns: 1fr;
    }

    .rc-activity-filter-actions {
        width: 100%;
    }

    .rc-activity-filter-actions .rc-btn {
        flex: 1;
        justify-content: center;
    }

    .rc-activity-modal {
        padding: 12px;
    }

    .rc-activity-modal__dialog {
        width: 100%;
        max-height: 92vh;
        border-radius: 18px;
    }

    .rc-activity-modal__header,
    .rc-activity-modal__body,
    .rc-activity-modal__footer {
        padding-left: 18px;
        padding-right: 18px;
    }

}

</style>


<!-- ================================================================
     PAGE HEADER
================================================================ -->

<div class="rc-page-header">

    <span class="rc-eyebrow">

        <i class="fas fa-shield-halved"></i>

        Audit & Accountability

    </span>

    <h2>
        Activity Log
    </h2>

    <p>
        Candidate access, shortlist activity and other
        recruiter actions recorded for your account.
    </p>

</div>


<!-- ================================================================
     TABLE WARNING
================================================================ -->

<?php if (!$activityLogExists): ?>

    <div class="rc-alert rc-alert--warning">

        <i class="fas fa-database"></i>

        <div>

            <strong>
                Activity log unavailable
            </strong>

            <p>
                The recruiter_activity_log table does not
                currently exist in this database.
            </p>

        </div>

    </div>

<?php endif; ?>


<!-- ================================================================
     STATISTICS
================================================================ -->

<section class="rc-activity-stats">


    <!-- Total -->

    <article class="rc-activity-stat">

        <i class="fas fa-clock-rotate-left"></i>

        <strong>
            <?= number_format(
                $stats['total']
            ) ?>
        </strong>

        <span>
            Total Activities
        </span>

        <small>
            Latest 200 displayed below
        </small>

    </article>


    <!-- Today -->

    <article
        class="
            rc-activity-stat
            rc-activity-stat--interactive
        "
        role="button"
        tabindex="0"
        data-activity-modal="today"
    >

        <i class="fas fa-calendar-day"></i>

        <strong>
            <?= number_format(
                $stats['today']
            ) ?>
        </strong>

        <span>
            Today
        </span>

        <small>
            Click to view
        </small>

    </article>


    <!-- Candidate -->

    <article
        class="
            rc-activity-stat
            rc-activity-stat--interactive
        "
        role="button"
        tabindex="0"
        data-activity-modal="candidates"
    >

        <i class="fas fa-user-shield"></i>

        <strong>
            <?= number_format(
                $stats['candidates']
            ) ?>
        </strong>

        <span>
            Candidate Activities
        </span>

        <small>
            Click to view
        </small>

    </article>


    <!-- Shortlist -->

    <article
        class="
            rc-activity-stat
            rc-activity-stat--interactive
        "
        role="button"
        tabindex="0"
        data-activity-modal="shortlists"
    >

        <i class="fas fa-list-check"></i>

        <strong>
            <?= number_format(
                $stats['shortlists']
            ) ?>
        </strong>

        <span>
            Shortlist Activities
        </span>

        <small>
            Click to view
        </small>

    </article>

</section>


<!-- ================================================================
     FILTERS
================================================================ -->

<?php if ($activityLogExists): ?>

    <form
        method="get"
        class="rc-card rc-activity-filters"
    >

        <!-- Search -->

        <div class="rc-activity-field">

            <label for="activitySearch">
                Search Activity
            </label>

            <input
                type="search"
                id="activitySearch"
                name="q"
                value="<?= e($search) ?>"
                placeholder="Action, description or entity..."
            >

        </div>


        <!-- Action -->

        <div class="rc-activity-field">

            <label for="activityAction">
                Action
            </label>

            <select
                id="activityAction"
                name="action"
            >

                <option value="">
                    All actions
                </option>

                <?php foreach (
                    $availableActions
                    as $action
                ): ?>

                    <option
                        value="<?= e($action) ?>"
                        <?= $actionFilter === $action
                            ? 'selected'
                            : '' ?>
                    >
                        <?= e(
                            rc_label($action)
                        ) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <!-- Entity -->

        <div class="rc-activity-field">

            <label for="activityEntity">
                Entity Type
            </label>

            <select
                id="activityEntity"
                name="entity_type"
            >

                <option value="">
                    All entities
                </option>

                <?php foreach (
                    $availableEntities
                    as $entity
                ): ?>

                    <option
                        value="<?= e($entity) ?>"
                        <?= $entityFilter === $entity
                            ? 'selected'
                            : '' ?>
                    >
                        <?= e(
                            rc_label($entity)
                        ) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <!-- Actions -->

        <div class="rc-activity-filter-actions">

            <button
                type="submit"
                class="rc-btn rc-btn--primary"
            >

                <i class="fas fa-filter"></i>

                Filter

            </button>


            <a
                class="rc-btn rc-btn--secondary"
                href="<?= url(
                    'recruiter/activity_log.php'
                ) ?>"
            >

                <i class="fas fa-rotate-left"></i>

                Reset

            </a>

        </div>

    </form>

<?php endif; ?>


<!-- ================================================================
     ACTIVITY LIST
================================================================ -->

<div class="rc-card">

    <div class="rc-card__header">

        <div>

            <h3>
                Recruiter Activity
            </h3>

            <p>

                <?php if (
                    $search !== ''
                    || $actionFilter !== ''
                    || $entityFilter !== ''
                ): ?>

                    <?= number_format(
                        count($rows)
                    ) ?>
                    matching activities

                <?php else: ?>

                    Showing the latest
                    <?= number_format(
                        count($rows)
                    ) ?>
                    activities

                <?php endif; ?>

            </p>

        </div>

    </div>


    <div class="rc-activity-list">

        <?php if (!$rows): ?>

            <div class="rc-empty">

                <i class="fas fa-clock-rotate-left"></i>

                <strong>
                    No activity recorded
                </strong>

                <span>

                    <?php if (
                        $search !== ''
                        || $actionFilter !== ''
                        || $entityFilter !== ''
                    ): ?>

                        No activities match the
                        selected filters.

                    <?php else: ?>

                        Recruiter activity will appear
                        here when actions are recorded.

                    <?php endif; ?>

                </span>

            </div>

        <?php else: ?>

            <?php foreach (
                $rows
                as $activity
            ): ?>

                <article>

                    <i class="fas fa-fingerprint"></i>

                    <div>

                        <div>

                            <strong>
                                <?= e(
                                    rc_label(
                                        $activity['action']
                                    )
                                ) ?>
                            </strong>

                            <span>
                                <?= e(
                                    rc_datetime(
                                        $activity[
                                            'created_at'
                                        ]
                                    )
                                ) ?>
                            </span>

                        </div>


                        <p class="rc-activity-description">
                            <?= e(
                                $activity[
                                    'description'
                                ]
                            ) ?>
                        </p>


                        <div class="rc-activity-meta">

                            <?php if (
                                !empty(
                                    $activity[
                                        'entity_type'
                                    ]
                                )
                            ): ?>

                                <span>

                                    <i class="fas fa-tag"></i>

                                    <?= e(
                                        rc_label(
                                            $activity[
                                                'entity_type'
                                            ]
                                        )
                                    ) ?>

                                    <?php if (
                                        !empty(
                                            $activity[
                                                'entity_id'
                                            ]
                                        )
                                    ): ?>

                                        #<?= number_format(
                                            (int)$activity[
                                                'entity_id'
                                            ]
                                        ) ?>

                                    <?php endif; ?>

                                </span>

                            <?php endif; ?>


                            <?php if (
                                !empty(
                                    $activity[
                                        'ip_address'
                                    ]
                                )
                            ): ?>

                                <span>

                                    <i class="fas fa-network-wired"></i>

                                    <?= e(
                                        $activity[
                                            'ip_address'
                                        ]
                                    ) ?>

                                </span>

                            <?php endif; ?>

                        </div>

                    </div>

                </article>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</div>


<!-- ================================================================
     AUDIT INFORMATION
================================================================ -->

<div class="rc-card rc-space">

    <div class="rc-card__body rc-info">

        <i class="fas fa-shield-halved"></i>

        <div>

            <strong>
                Recruiter activity is account scoped.
            </strong>

            <p>
                This page only displays audit records associated
                with the currently authenticated Recruiter
                account. Candidate profile access and shortlist
                actions can therefore be traced back to the
                Recruiter who performed them.
            </p>

        </div>

    </div>

</div>


<!-- ================================================================
     MODAL
================================================================ -->

<div
    class="rc-activity-modal"
    id="rcActivityModal"
    aria-hidden="true"
>

    <div
        class="rc-activity-modal__backdrop"
        data-activity-close
    ></div>


    <div
        class="rc-activity-modal__dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="rcActivityModalTitle"
    >

        <div class="rc-activity-modal__header">

            <div>

                <span class="rc-eyebrow">
                    Audit & Accountability
                </span>

                <h3 id="rcActivityModalTitle">
                    Activity Details
                </h3>

                <p id="rcActivityModalDescription">
                    Recruiter activity.
                </p>

            </div>


            <button
                type="button"
                class="rc-activity-modal__close"
                data-activity-close
                aria-label="Close"
            >

                <i class="fas fa-xmark"></i>

            </button>

        </div>


        <div class="rc-activity-modal__body">


            <!-- TODAY -->

            <section
                class="rc-activity-panel"
                data-activity-panel="today"
            >

                <?php
                $modalRows = $todayRows;
                ?>

                <?php if (!$modalRows): ?>

                    <div class="rc-activity-empty">

                        <i class="fas fa-calendar-day"></i>

                        <strong>
                            No activity today
                        </strong>

                        <span>
                            No Recruiter activity has been
                            recorded today.
                        </span>

                    </div>

                <?php else: ?>

                    <?php foreach (
                        $modalRows
                        as $activity
                    ): ?>

                        <div class="rc-activity-modal-row">

                            <div class="rc-activity-modal-row__icon">

                                <i class="fas fa-fingerprint"></i>

                            </div>

                            <div class="rc-activity-modal-row__content">

                                <strong>
                                    <?= e(
                                        rc_label(
                                            $activity['action']
                                        )
                                    ) ?>
                                </strong>

                                <span>
                                    <?= e(
                                        $activity['description']
                                    ) ?>
                                </span>

                                <small>
                                    <?= e(
                                        rc_datetime(
                                            $activity['created_at']
                                        )
                                    ) ?>
                                </small>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </section>


            <!-- CANDIDATES -->

            <section
                class="rc-activity-panel"
                data-activity-panel="candidates"
            >

                <?php if (!$candidateRows): ?>

                    <div class="rc-activity-empty">

                        <i class="fas fa-user-shield"></i>

                        <strong>
                            No candidate activity
                        </strong>

                        <span>
                            Candidate-related Recruiter activity
                            has not been recorded yet.
                        </span>

                    </div>

                <?php else: ?>

                    <?php foreach (
                        $candidateRows
                        as $activity
                    ): ?>

                        <div class="rc-activity-modal-row">

                            <div class="rc-activity-modal-row__icon">

                                <i class="fas fa-user-shield"></i>

                            </div>

                            <div class="rc-activity-modal-row__content">

                                <strong>
                                    <?= e(
                                        rc_label(
                                            $activity['action']
                                        )
                                    ) ?>
                                </strong>

                                <span>
                                    <?= e(
                                        $activity['description']
                                    ) ?>
                                </span>

                                <small>

                                    <?php if (
                                        !empty(
                                            $activity[
                                                'entity_id'
                                            ]
                                        )
                                    ): ?>

                                        Candidate
                                        #<?= number_format(
                                            (int)$activity[
                                                'entity_id'
                                            ]
                                        ) ?>
                                        ·

                                    <?php endif; ?>

                                    <?= e(
                                        rc_datetime(
                                            $activity['created_at']
                                        )
                                    ) ?>

                                </small>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </section>


            <!-- SHORTLISTS -->

            <section
                class="rc-activity-panel"
                data-activity-panel="shortlists"
            >

                <?php if (!$shortlistRows): ?>

                    <div class="rc-activity-empty">

                        <i class="fas fa-list-check"></i>

                        <strong>
                            No shortlist activity
                        </strong>

                        <span>
                            Shortlist-related Recruiter activity
                            has not been recorded yet.
                        </span>

                    </div>

                <?php else: ?>

                    <?php foreach (
                        $shortlistRows
                        as $activity
                    ): ?>

                        <div class="rc-activity-modal-row">

                            <div class="rc-activity-modal-row__icon">

                                <i class="fas fa-list-check"></i>

                            </div>

                            <div class="rc-activity-modal-row__content">

                                <strong>
                                    <?= e(
                                        rc_label(
                                            $activity['action']
                                        )
                                    ) ?>
                                </strong>

                                <span>
                                    <?= e(
                                        $activity['description']
                                    ) ?>
                                </span>

                                <small>

                                    <?php if (
                                        !empty(
                                            $activity[
                                                'entity_id'
                                            ]
                                        )
                                    ): ?>

                                        Shortlist
                                        #<?= number_format(
                                            (int)$activity[
                                                'entity_id'
                                            ]
                                        ) ?>
                                        ·

                                    <?php endif; ?>

                                    <?= e(
                                        rc_datetime(
                                            $activity['created_at']
                                        )
                                    ) ?>

                                </small>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </section>

        </div>


        <div class="rc-activity-modal__footer">

            <button
                type="button"
                class="rc-btn rc-btn--secondary"
                data-activity-close
            >

                <i class="fas fa-xmark"></i>

                Close

            </button>

        </div>

    </div>

</div>


<!-- ================================================================
     JAVASCRIPT
================================================================ -->

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const modal =
            document.getElementById(
                'rcActivityModal'
            );

        if (!modal) {
            return;
        }

        const cards =
            document.querySelectorAll(
                '[data-activity-modal]'
            );

        const panels =
            modal.querySelectorAll(
                '[data-activity-panel]'
            );

        const closeButtons =
            modal.querySelectorAll(
                '[data-activity-close]'
            );

        const title =
            document.getElementById(
                'rcActivityModalTitle'
            );

        const description =
            document.getElementById(
                'rcActivityModalDescription'
            );

        let lastTrigger = null;


        const config = {

            today: {
                title: 'Today\'s Activity',
                description:
                    'Recruiter actions recorded today.'
            },

            candidates: {
                title: 'Candidate Activity',
                description:
                    'Candidate-related actions recorded for your Recruiter account.'
            },

            shortlists: {
                title: 'Shortlist Activity',
                description:
                    'Shortlist-related actions recorded for your Recruiter account.'
            }

        };


        function openModal(
            type,
            trigger
        ) {

            const selected =
                config[type];

            if (!selected) {
                return;
            }

            lastTrigger =
                trigger || null;

            panels.forEach(
                function (panel) {

                    panel.classList.toggle(
                        'is-active',
                        panel.dataset.activityPanel
                            === type
                    );

                }
            );

            title.textContent =
                selected.title;

            description.textContent =
                selected.description;

            modal.classList.add(
                'is-open'
            );

            modal.setAttribute(
                'aria-hidden',
                'false'
            );

            document.body.classList.add(
                'rc-activity-modal-open'
            );

            const closeButton =
                modal.querySelector(
                    '.rc-activity-modal__close'
                );

            if (closeButton) {
                closeButton.focus();
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
                'rc-activity-modal-open'
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
                            card.dataset.activityModal,
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
                                card.dataset.activityModal,
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