<?php



require_once __DIR__ . '/../includes/bootstrap.php';



require_role('programme_officer');



require_once __DIR__ . '/_helpers.php';



/*

|--------------------------------------------------------------------------

| Current User

|--------------------------------------------------------------------------

*/



$user = current_user();



$flashes = render_flashes();



$currentPage = 'dashboard';



$pageTitle = 'Programme Officer Dashboard';



$conn = Database::getConnection();





/*

|--------------------------------------------------------------------------

| Programme Officer

|--------------------------------------------------------------------------

*/



$officerId = (int) (

    $user['id']

    ?? $user['user_id']

    ?? 0

);



if ($officerId <= 0) {



    http_response_code(403);



    exit('Invalid Programme Officer account.');

}





/*

|--------------------------------------------------------------------------

| Programme Officer Scope

|--------------------------------------------------------------------------

*/



$scope = po_scope(

    $conn,

    'p',

    'c'

);



$scopeMode = po_scope_mode($scope);



$scopeCondition = po_scope_condition($scope);





/*

|--------------------------------------------------------------------------

| Dashboard Statistics

|--------------------------------------------------------------------------

*/



$stats = [

    'programmes' => 0,

    'cohorts' => 0,

    'candidates' => 0,

    'active' => 0,

    'completed' => 0,

];



if ($scopeMode !== 'none') {



    $sql = "

        SELECT



            COUNT(

                DISTINCT p.id

            ) AS programmes,



            COUNT(

                DISTINCT c.id

            ) AS cohorts,



            COUNT(

                DISTINCT CASE

                    WHEN cp.status <> 'withdrawn'

                    THEN cp.user_id

                END

            ) AS candidates,



            COUNT(

                DISTINCT CASE

                    WHEN cp.status = 'active'

                    THEN cp.user_id

                END

            ) AS active,



            COUNT(

                DISTINCT CASE

                    WHEN cp.status = 'completed'

                    THEN cp.user_id

                END

            ) AS completed



        FROM programmes p



        LEFT JOIN cohorts c

            ON c.programme_id = p.id



        LEFT JOIN cohort_participants cp

            ON cp.cohort_id = c.id



        WHERE {$scopeCondition}

    ";



    $stmt = $conn->prepare($sql);



    if ($stmt) {



        $stmt->bind_param(

            'i',

            $officerId

        );



        $stmt->execute();



        $row = $stmt

            ->get_result()

            ->fetch_assoc();



        if ($row) {



            $stats = [

                'programmes' =>

                    (int) ($row['programmes'] ?? 0),



                'cohorts' =>

                    (int) ($row['cohorts'] ?? 0),



                'candidates' =>

                    (int) ($row['candidates'] ?? 0),



                'active' =>

                    (int) ($row['active'] ?? 0),



                'completed' =>

                    (int) ($row['completed'] ?? 0),

            ];

        }



        $stmt->close();

    }

}





/*

|--------------------------------------------------------------------------

| Completion Rate

|--------------------------------------------------------------------------

*/



$completionRate = po_completion_rate(

    $stats['candidates'],

    $stats['completed']

);





/*

|--------------------------------------------------------------------------

| Programme Portfolio

|--------------------------------------------------------------------------

*/



$programmes = [];



if ($scopeMode !== 'none') {



    $sql = "

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



        WHERE {$scopeCondition}



        GROUP BY

            p.id,

            p.name,

            p.type,

            p.status,

            p.start_date,

            p.end_date



        ORDER BY



            CASE

                WHEN p.status = 'active'

                THEN 0

                ELSE 1

            END,



            p.start_date DESC,

            p.id DESC



        LIMIT 5

    ";



    $stmt = $conn->prepare($sql);



    if ($stmt) {



        $stmt->bind_param(

            'i',

            $officerId

        );



        $stmt->execute();



        $result = $stmt->get_result();



        while (

            $row = $result->fetch_assoc()

        ) {



            $programmes[] = $row;

        }



        $stmt->close();

    }

}





/*

|--------------------------------------------------------------------------

| Recent Candidate Activity

|--------------------------------------------------------------------------

*/



$recentCandidates = [];



if ($scopeMode !== 'none') {



    $sql = "

        SELECT



            u.id AS candidate_id,



            u.first_name,

            u.last_name,

            u.email,



            cp.status AS participant_status,



            cp.selected_at,

            cp.onboarded_at,

            cp.completed_at,



            c.id AS cohort_id,

            c.name AS cohort_name,



            p.id AS programme_id,

            p.name AS programme_name



        FROM cohort_participants cp



        INNER JOIN cohorts c

            ON c.id = cp.cohort_id



        INNER JOIN programmes p

            ON p.id = c.programme_id



        INNER JOIN users u

            ON u.id = cp.user_id



        WHERE {$scopeCondition}



        ORDER BY



            COALESCE(

                cp.completed_at,

                cp.onboarded_at,

                cp.selected_at

            ) DESC,



            cp.id DESC



        LIMIT 6

    ";



    $stmt = $conn->prepare($sql);



    if ($stmt) {



        $stmt->bind_param(

            'i',

            $officerId

        );



        $stmt->execute();



        $result = $stmt->get_result();



        while (

            $row = $result->fetch_assoc()

        ) {



            $recentCandidates[] = $row;

        }



        $stmt->close();

    }

}







/*

|--------------------------------------------------------------------------

| Dashboard Modal Data

|--------------------------------------------------------------------------

| Each modal uses the same Programme Officer scope as the dashboard.

*/



$modalProgrammes = [];

$modalCohorts = [];

$modalCandidates = [];

$modalActiveCandidates = [];

$modalCompletedCandidates = [];



if ($scopeMode !== 'none') {



    // Programmes

    $sql = "

        SELECT

            p.id,

            p.name,

            p.type,

            p.status,

            p.start_date,

            p.end_date

        FROM programmes p

        LEFT JOIN cohorts c

            ON c.programme_id = p.id

        WHERE {$scopeCondition}

        GROUP BY

            p.id, p.name, p.type, p.status, p.start_date, p.end_date

        ORDER BY

            CASE WHEN p.status = 'active' THEN 0 ELSE 1 END,

            p.name ASC

    ";



    $stmt = $conn->prepare($sql);



    if ($stmt) {

        $stmt->bind_param('i', $officerId);

        $stmt->execute();

        $result = $stmt->get_result();



        while ($row = $result->fetch_assoc()) {

            $modalProgrammes[] = $row;

        }



        $stmt->close();

    }



    // Cohorts

    $sql = "

        SELECT DISTINCT

            c.id,

            c.name,

            c.status,

            c.start_date,

            c.end_date,

            c.location,

            c.province,

            p.id AS programme_id,

            p.name AS programme_name

        FROM cohorts c

        INNER JOIN programmes p

            ON p.id = c.programme_id

        WHERE {$scopeCondition}

        ORDER BY

            CASE WHEN c.status = 'active' THEN 0 ELSE 1 END,

            c.start_date DESC,

            c.name ASC

    ";



    $stmt = $conn->prepare($sql);



    if ($stmt) {

        $stmt->bind_param('i', $officerId);

        $stmt->execute();

        $result = $stmt->get_result();



        while ($row = $result->fetch_assoc()) {

            $modalCohorts[] = $row;

        }



        $stmt->close();

    }



    // Candidates

    $sql = "

        SELECT DISTINCT

            cp.id AS participant_id,

            cp.status AS participant_status,

            cp.selected_at,

            cp.onboarded_at,

            cp.completed_at,

            u.id AS candidate_id,

            u.first_name,

            u.last_name,

            u.email,

            c.id AS cohort_id,

            c.name AS cohort_name,

            p.id AS programme_id,

            p.name AS programme_name

        FROM cohort_participants cp

        INNER JOIN cohorts c

            ON c.id = cp.cohort_id

        INNER JOIN programmes p

            ON p.id = c.programme_id

        INNER JOIN users u

            ON u.id = cp.user_id

        WHERE {$scopeCondition}

          AND cp.status <> 'withdrawn'

        ORDER BY

            u.first_name ASC,

            u.last_name ASC,

            cp.id DESC

    ";



    $stmt = $conn->prepare($sql);



    if ($stmt) {

        $stmt->bind_param('i', $officerId);

        $stmt->execute();

        $result = $stmt->get_result();



        while ($row = $result->fetch_assoc()) {

            $modalCandidates[] = $row;



            if (($row['participant_status'] ?? '') === 'active') {

                $modalActiveCandidates[] = $row;

            }



            if (($row['participant_status'] ?? '') === 'completed') {

                $modalCompletedCandidates[] = $row;

            }

        }



        $stmt->close();

    }

}


/*
|--------------------------------------------------------------------------
| Programme Action Centre
|--------------------------------------------------------------------------
| The Action Centre is intentionally built from tables that exist in the
| current application schema. Each query is scoped through programmes
| visible to this Programme Officer. Optional modules (for example,
| interviews or candidate profiles) fail safely if their tables are not
| available in an older local database.
*/
$actionItems = [];

function po_add_action(array &$items, string $type, string $priority, string $title, string $description, string $meta, string $url, string $icon): void
{
    $items[] = [
        'type' => $type,
        'priority' => $priority,
        'title' => $title,
        'description' => $description,
        'meta' => $meta,
        'url' => $url,
        'icon' => $icon,
    ];
}

/* Candidates selected but not yet onboarded. */
$sql = "
    SELECT
        cp.user_id AS candidate_id,
        CONCAT_WS(' ', u.first_name, u.last_name) AS candidate_name,
        c.id AS cohort_id,
        c.name AS cohort_name,
        p.name AS programme_name
    FROM cohort_participants cp
    INNER JOIN users u ON u.id = cp.user_id
    INNER JOIN cohorts c ON c.id = cp.cohort_id
    INNER JOIN programmes p ON p.id = c.programme_id
    WHERE {$scopeCondition}
      AND cp.status = 'selected'
      AND cp.onboarded_at IS NULL
    ORDER BY cp.updated_at ASC
    LIMIT 6
";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('i', $officerId);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            po_add_action(
                $actionItems,
                'onboarding',
                'high',
                'Candidate onboarding required',
                $row['candidate_name'] . ' is selected but has not been onboarded.',
                $row['programme_name'] . ' · ' . $row['cohort_name'],
                url('programme_officer/candidate_view.php?id=' . (int) $row['candidate_id'] . '&cohort_id=' . (int) $row['cohort_id']),
                'fa-user-plus'
            );
        }
    }
    $stmt->close();
}

/* Applications waiting at assessment stage. */
$sql = "
    SELECT
        a.id AS application_id,
        a.candidate_id,
        a.application_reference,
        CONCAT_WS(' ', u.first_name, u.last_name) AS candidate_name,
        c.id AS cohort_id,
        c.name AS cohort_name,
        p.name AS programme_name
    FROM applications a
    INNER JOIN users u ON u.id = a.candidate_id
    INNER JOIN opportunities o ON o.id = a.opportunity_id
    INNER JOIN cohorts c ON c.id = o.cohort_id
    INNER JOIN programmes p ON p.id = c.programme_id
    WHERE {$scopeCondition}
      AND a.status = 'assessment'
    ORDER BY a.updated_at ASC
    LIMIT 6
";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('i', $officerId);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            po_add_action(
                $actionItems,
                'assessment',
                'high',
                'Assessment requires attention',
                $row['candidate_name'] . ' has an application at the assessment stage.',
                $row['programme_name'] . ' · ' . $row['cohort_name'] . ' · ' . $row['application_reference'],
                url('programme_officer/candidate_view.php?id=' . (int) $row['candidate_id'] . '&cohort_id=' . (int) $row['cohort_id']),
                'fa-clipboard-check'
            );
        }
    }
    $stmt->close();
}

/* Interviews due now or within the next three days. */
$sql = "
    SELECT
        a.candidate_id,
        i.interview_date,
        i.start_time,
        i.status AS interview_status,
        CONCAT_WS(' ', u.first_name, u.last_name) AS candidate_name,
        c.id AS cohort_id,
        c.name AS cohort_name,
        p.name AS programme_name
    FROM interviews i
    INNER JOIN applications a ON a.id = i.application_id
    INNER JOIN users u ON u.id = a.candidate_id
    INNER JOIN opportunities o ON o.id = a.opportunity_id
    INNER JOIN cohorts c ON c.id = o.cohort_id
    INNER JOIN programmes p ON p.id = c.programme_id
    WHERE {$scopeCondition}
      AND i.status IN ('scheduled', 'confirmed', 'rescheduled')
      AND i.interview_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
    ORDER BY i.interview_date ASC, i.start_time ASC
    LIMIT 6
";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('i', $officerId);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $priority = $row['interview_date'] < date('Y-m-d') ? 'high' : 'medium';
            po_add_action(
                $actionItems,
                'interview',
                $priority,
                $row['interview_date'] < date('Y-m-d') ? 'Interview follow-up overdue' : 'Interview requires attention',
                $row['candidate_name'] . ' has an interview ' . ($row['interview_date'] < date('Y-m-d') ? 'that is overdue.' : 'scheduled soon.'),
                $row['programme_name'] . ' · ' . $row['cohort_name'] . ' · ' . date('d M Y', strtotime($row['interview_date'])) . ' ' . substr((string) $row['start_time'], 0, 5),
                url('programme_officer/candidate_view.php?id=' . (int) $row['candidate_id'] . '&cohort_id=' . (int) $row['cohort_id']),
                'fa-calendar-check'
            );
        }
    }
    $stmt->close();
}

/* Candidate profiles with incomplete information. */
$sql = "
    SELECT DISTINCT
        u.id AS candidate_id,
        CONCAT_WS(' ', u.first_name, u.last_name) AS candidate_name,
        c.id AS cohort_id,
        c.name AS cohort_name,
        p.name AS programme_name,
        COALESCE(cp.completion_percent, 0) AS completion_percent
    FROM cohort_participants participant
    INNER JOIN users u ON u.id = participant.user_id
    INNER JOIN cohorts c ON c.id = participant.cohort_id
    INNER JOIN programmes p ON p.id = c.programme_id
    LEFT JOIN candidate_profiles cp ON cp.user_id = u.id
    WHERE {$scopeCondition}
      AND participant.status <> 'withdrawn'
      AND COALESCE(cp.completion_percent, 0) < 100
    ORDER BY COALESCE(cp.completion_percent, 0) ASC, u.last_name ASC
    LIMIT 6
";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('i', $officerId);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            po_add_action(
                $actionItems,
                'candidate_info',
                'medium',
                'Candidate information incomplete',
                $row['candidate_name'] . ' has a profile completion level of ' . (int) $row['completion_percent'] . '%.',
                $row['programme_name'] . ' · ' . $row['cohort_name'],
                url('programme_officer/candidate_view.php?id=' . (int) $row['candidate_id'] . '&cohort_id=' . (int) $row['cohort_id']),
                'fa-user-pen'
            );
        }
    }
    $stmt->close();
}

/* Active candidates whose participation record has not been updated recently. */
$sql = "
    SELECT
        cp.user_id AS candidate_id,
        CONCAT_WS(' ', u.first_name, u.last_name) AS candidate_name,
        c.id AS cohort_id,
        c.name AS cohort_name,
        p.name AS programme_name,
        cp.updated_at
    FROM cohort_participants cp
    INNER JOIN users u ON u.id = cp.user_id
    INNER JOIN cohorts c ON c.id = cp.cohort_id
    INNER JOIN programmes p ON p.id = c.programme_id
    WHERE {$scopeCondition}
      AND cp.status = 'active'
      AND cp.updated_at < DATE_SUB(NOW(), INTERVAL 14 DAY)
    ORDER BY cp.updated_at ASC
    LIMIT 6
";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('i', $officerId);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            po_add_action(
                $actionItems,
                'progress',
                'medium',
                'Progress update may be due',
                $row['candidate_name'] . ' has not had a participation update for more than 14 days.',
                $row['programme_name'] . ' · ' . $row['cohort_name'],
                url('programme_officer/candidate_view.php?id=' . (int) $row['candidate_id'] . '&cohort_id=' . (int) $row['cohort_id']),
                'fa-chart-line'
            );
        }
    }
    $stmt->close();
}

/* Cohorts with an application closing date within seven days. */
$sql = "
    SELECT
        c.id AS cohort_id,
        c.name AS cohort_name,
        p.name AS programme_name,
        c.application_close_date
    FROM cohorts c
    INNER JOIN programmes p ON p.id = c.programme_id
    WHERE {$scopeCondition}
      AND c.application_close_date IS NOT NULL
      AND c.application_close_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
      AND c.status IN ('open', 'active')
    ORDER BY c.application_close_date ASC
    LIMIT 6
";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('i', $officerId);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            po_add_action(
                $actionItems,
                'cohort_activity',
                'medium',
                'Cohort application window closing',
                $row['cohort_name'] . ' is approaching its application closing date.',
                $row['programme_name'] . ' · closes ' . date('d M Y', strtotime($row['application_close_date'])),
                url('programme_officer/cohort_view.php?id=' . (int) $row['cohort_id']),
                'fa-clock'
            );
        }
    }
    $stmt->close();
}

$priorityOrder = ['high' => 0, 'medium' => 1, 'low' => 2];
usort($actionItems, static function (array $a, array $b) use ($priorityOrder): int {
    return ($priorityOrder[$a['priority']] ?? 9) <=> ($priorityOrder[$b['priority']] ?? 9);
});
$actionItems = array_slice($actionItems, 0, 12);
$actionCount = count($actionItems);


/*

|--------------------------------------------------------------------------

| First Name

|--------------------------------------------------------------------------

*/



$firstName = trim(

    (string) (

        $user['first_name']

        ?? 'Programme Officer'

    )

);





/*

|--------------------------------------------------------------------------

| Layout

|--------------------------------------------------------------------------

*/



require __DIR__ . '/_layout_start.php';



?>



<link

    rel="stylesheet"

    href="<?= url('css/dashboard_card_modals.css') ?>"

>





<?php if ($scopeMode === 'portfolio'): ?>



    <div class="po-alert">



        <i class="fas fa-circle-info"></i>



        <div>



            <strong>

                Programme portfolio access

            </strong>



            <span>

                No Programme Officer assignment column exists

                in the current database schema. Your account is

                therefore displaying the real programme portfolio.

            </span>



        </div>



    </div>



<?php endif; ?>





<!-- =========================================================

     HERO

========================================================= -->



<section class="po-hero">



    <div class="po-hero__content">



        <span class="po-hero__eyebrow">



            <i class="fas fa-sparkles"></i>



            Programme Operations



        </span>



        <h2>

            Welcome back,

            <?= e(

                $firstName !== ''

                    ? $firstName

                    : 'Programme Officer'

            ) ?>.

        </h2>



        <p>

            Manage programmes, monitor cohort delivery,

            review candidate movement and keep programme

            operations on track from one workspace.

        </p>



        <div class="po-hero__actions">



            <a

                href="<?= url(

                    'programme_officer/programmes.php'

                ) ?>"

                class="po-btn po-btn--light"

            >



                <i class="fas fa-diagram-project"></i>



                My Programmes



            </a>



            <a

                href="<?= url(

                    'programme_officer/candidates.php'

                ) ?>"

                class="po-btn po-btn--glass"

            >



                <i class="fas fa-users"></i>



                Review Candidates



            </a>



        </div>



    </div>





    <div class="po-hero__metric">



        <div

            class="po-ring"

            style="--progress:<?= $completionRate ?>"

        >



            <strong>

                <?= $completionRate ?>%

            </strong>



        </div>



        <span>

            Candidate completion

        </span>



    </div>



</section>





<!-- =========================================================

     REAL DATABASE STATISTICS

========================================================= -->



<section class="po-stats po-stats--5 po-dashboard-stats">



    <article

        class="po-stat po-stat--interactive"

        tabindex="0"

        role="button"

        data-po-modal="programmes"

        aria-label="View my programmes"

    >

        <span class="po-stat__icon po-stat__icon--blue">

            <i class="fas fa-diagram-project"></i>

        </span>

        <strong><?= number_format($stats['programmes']) ?></strong>

        <span>My Programmes</span>

        <span class="po-stat__hint">

            View details <i class="fas fa-arrow-up-right-from-square"></i>

        </span>

    </article>



    <article

        class="po-stat po-stat--interactive"

        tabindex="0"

        role="button"

        data-po-modal="cohorts"

        aria-label="View cohorts"

    >

        <span class="po-stat__icon po-stat__icon--purple">

            <i class="fas fa-layer-group"></i>

        </span>

        <strong><?= number_format($stats['cohorts']) ?></strong>

        <span>Cohorts</span>

        <span class="po-stat__hint">

            View details <i class="fas fa-arrow-up-right-from-square"></i>

        </span>

    </article>



    <article

        class="po-stat po-stat--interactive"

        tabindex="0"

        role="button"

        data-po-modal="candidates"

        aria-label="View current candidates"

    >

        <span class="po-stat__icon po-stat__icon--orange">

            <i class="fas fa-users"></i>

        </span>

        <strong><?= number_format($stats['candidates']) ?></strong>

        <span>Current Candidates</span>

        <span class="po-stat__hint">

            View details <i class="fas fa-arrow-up-right-from-square"></i>

        </span>

    </article>



    <article

        class="po-stat po-stat--interactive"

        tabindex="0"

        role="button"

        data-po-modal="active"

        aria-label="View active candidates"

    >

        <span class="po-stat__icon po-stat__icon--cyan">

            <i class="fas fa-person-running"></i>

        </span>

        <strong><?= number_format($stats['active']) ?></strong>

        <span>Active</span>

        <span class="po-stat__hint">

            View details <i class="fas fa-arrow-up-right-from-square"></i>

        </span>

    </article>



    <article

        class="po-stat po-stat--interactive"

        tabindex="0"

        role="button"

        data-po-modal="completed"

        aria-label="View completed candidates"

    >

        <span class="po-stat__icon po-stat__icon--green">

            <i class="fas fa-circle-check"></i>

        </span>

        <strong><?= number_format($stats['completed']) ?></strong>

        <span>Completed</span>

        <span class="po-stat__hint">

            View details <i class="fas fa-arrow-up-right-from-square"></i>

        </span>

    </article>



</section>



<!-- =========================================================
     PROGRAMME ACTION CENTRE
========================================================= -->
<section class="po-card po-action-centre">
    <div class="po-card__header po-action-centre__header">
        <div>
            <h3>Programme Action Centre</h3>
            <p>Outstanding programme items that may require your attention.</p>
        </div>
        <span class="po-action-centre__count"><?= number_format($actionCount) ?> action<?= $actionCount === 1 ? '' : 's' ?></span>
    </div>

    <?php if (!$actionItems): ?>
        <div class="po-action-centre__empty">
            <span class="po-action-centre__empty-icon"><i class="fas fa-circle-check"></i></span>
            <div>
                <strong>You're all caught up</strong>
                <span>No outstanding programme actions were identified from the available programme, cohort, candidate, application and interview data.</span>
            </div>
        </div>
    <?php else: ?>
        <div class="po-action-centre__list">
            <?php foreach ($actionItems as $action): ?>
                <a class="po-action-item" href="<?= e($action['url']) ?>">
                    <span class="po-action-item__icon po-action-item__icon--<?= e($action['priority']) ?>">
                        <i class="fas <?= e($action['icon']) ?>"></i>
                    </span>
                    <span class="po-action-item__body">
                        <strong><?= e($action['title']) ?></strong>
                        <span><?= e($action['description']) ?></span>
                        <small><?= e($action['meta']) ?></small>
                    </span>
                    <span class="po-action-item__priority po-action-item__priority--<?= e($action['priority']) ?>">
                        <?= e(ucfirst($action['priority'])) ?>
                    </span>
                    <i class="fas fa-chevron-right po-action-item__arrow"></i>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>


<!-- =========================================================

     PROGRAMME PORTFOLIO / QUICK ACTIONS

========================================================= -->



<div class="po-grid po-grid--main">



    <section class="po-card">



        <div class="po-card__header">



            <div>



                <h3>

                    Programme Portfolio

                </h3>



                <p>

                    Real programme and participant

                    performance from the database.

                </p>



            </div>



            <a

                class="po-link"

                href="<?= url(

                    'programme_officer/programmes.php'

                ) ?>"

            >



                View all



                <i class="fas fa-arrow-right"></i>



            </a>



        </div>





        <?php if (!$programmes): ?>



            <div class="po-empty">



                <div class="po-empty__icon">



                    <i class="fas fa-diagram-project"></i>



                </div>



                <strong>

                    No programmes available

                </strong>



                <span>

                    No programme records are available

                    for this Programme Officer scope.

                </span>



            </div>



        <?php else: ?>



            <div class="po-list">



                <?php foreach (

                    $programmes as $programme

                ): ?>



                    <?php



                    $candidateTotal =

                        (int) $programme[

                            'candidate_count'

                        ];



                    $completedTotal =

                        (int) $programme[

                            'completed_count'

                        ];



                    $programmeRate =

                        po_completion_rate(

                            $candidateTotal,

                            $completedTotal

                        );



                    ?>



                    <a

                        href="<?= url(

                            'programme_officer/cohorts.php'

                            . '?programme_id='

                            . (int) $programme['id']

                        ) ?>"

                        class="po-list-item"

                    >



                        <div class="po-list-item__icon">



                            <i

                                class="

                                    fas

                                    fa-diagram-project

                                "

                            ></i>



                        </div>





                        <div class="po-list-item__main">



                            <div

                                class="

                                    po-list-item__title

                                "

                            >



                                <strong>

                                    <?= e(

                                        $programme['name']

                                    ) ?>

                                </strong>



                                <span

                                    class="

                                        po-status

                                        po-status--<?= e(

                                            po_status_class(

                                                $programme[

                                                    'status'

                                                ]

                                            )

                                        ) ?>

                                    "

                                >

                                    <?= e(

                                        po_status_label(

                                            $programme[

                                                'status'

                                            ]

                                        )

                                    ) ?>

                                </span>



                            </div>





                            <span>



                                <?= e(

                                    (string) (

                                        $programme['type']

                                        ?? 'Programme'

                                    )

                                ) ?>



                                ·



                                <?= number_format(

                                    (int) $programme[

                                        'cohort_count'

                                    ]

                                ) ?>

                                cohorts



                                ·



                                <?= number_format(

                                    $candidateTotal

                                ) ?>

                                candidates



                            </span>





                            <div class="po-progress">



                                <div

                                    class="

                                        po-progress__track

                                    "

                                >



                                    <span

                                        style="

                                            width:

                                            <?= $programmeRate ?>%

                                        "

                                    ></span>



                                </div>



                                <small>

                                    <?= $programmeRate ?>%

                                    completed

                                </small>



                            </div>



                        </div>





                        <i

                            class="

                                fas

                                fa-chevron-right

                                po-list-item__arrow

                            "

                        ></i>



                    </a>



                <?php endforeach; ?>



            </div>



        <?php endif; ?>



    </section>





    <!-- QUICK ACTIONS -->



    <section class="po-card">



        <div class="po-card__header">



            <div>



                <h3>

                    Quick Actions

                </h3>



                <p>

                    Frequently used programme operations.

                </p>



            </div>



        </div>





        <div class="po-quick-actions">



            <a

                href="<?= url(

                    'programme_officer/cohorts.php'

                ) ?>"

                class="po-quick-action"

            >



                <span class="po-quick-action__icon">

                    <i class="fas fa-layer-group"></i>

                </span>



                <div>



                    <strong>

                        Review Cohorts

                    </strong>



                    <span>

                        Monitor cohort delivery and

                        timelines.

                    </span>



                </div>



            </a>





            <a

                href="<?= url(

                    'programme_officer/candidates.php'

                ) ?>"

                class="po-quick-action"

            >



                <span class="po-quick-action__icon">

                    <i class="fas fa-users"></i>

                </span>



                <div>



                    <strong>

                        Candidate Overview

                    </strong>



                    <span>

                        View participants across your

                        programmes.

                    </span>



                </div>



            </a>





            <a

                href="<?= url(

                    'programme_officer/reports.php'

                ) ?>"

                class="po-quick-action"

            >



                <span class="po-quick-action__icon">

                    <i class="fas fa-chart-line"></i>

                </span>



                <div>



                    <strong>

                        Programme Reports

                    </strong>



                    <span>

                        Review progress and completion

                        metrics.

                    </span>



                </div>



            </a>



        </div>



    </section>



</div>





<!-- =========================================================

     RECENT REAL CANDIDATE ACTIVITY

========================================================= -->



<section class="po-card">



    <div class="po-card__header">



        <div>



            <h3>

                Recent Candidate Activity

            </h3>



            <p>

                Latest candidate movement in your

                programme portfolio.

            </p>



        </div>



        <a

            class="po-link"

            href="<?= url(

                'programme_officer/candidates.php'

            ) ?>"

        >



            View candidates



            <i class="fas fa-arrow-right"></i>



        </a>



    </div>





    <?php if (!$recentCandidates): ?>



        <div class="po-empty">



            <div class="po-empty__icon">



                <i class="fas fa-users"></i>



            </div>



            <strong>

                No candidate activity yet

            </strong>



            <span>

                No cohort participant records are

                currently available.

            </span>



        </div>



    <?php else: ?>



        <div class="po-candidate-grid">



            <?php foreach (

                $recentCandidates as $candidate

            ): ?>



                <?php



                $candidateFirst =

                    trim(

                        (string) (

                            $candidate['first_name']

                            ?? ''

                        )

                    );



                $candidateLast =

                    trim(

                        (string) (

                            $candidate['last_name']

                            ?? ''

                        )

                    );



                $candidateName =

                    trim(

                        $candidateFirst

                        . ' '

                        . $candidateLast

                    );



                if ($candidateName === '') {

                    $candidateName = 'Candidate';

                }



                ?>



                <article class="po-person-card">



                    <div class="po-person-card__top">



                        <div class="po-avatar">



                            <?= e(

                                po_initials(

                                    $candidateFirst,

                                    $candidateLast

                                )

                            ) ?>



                        </div>





                        <div

                            class="

                                po-person-card__identity

                            "

                        >



                            <strong>

                                <?= e($candidateName) ?>

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

                                po-status

                                po-status--<?= e(

                                    po_status_class(

                                        $candidate[

                                            'participant_status'

                                        ]

                                    )

                                ) ?>

                            "

                        >



                            <?= e(

                                po_status_label(

                                    $candidate[

                                        'participant_status'

                                    ]

                                )

                            ) ?>



                        </span>



                    </div>





                    <div

                        class="

                            po-person-card__footer

                        "

                    >



                        <span>



                            <i

                                class="

                                    fas

                                    fa-layer-group

                                "

                            ></i>



                            <?= e(

                                $candidate[

                                    'cohort_name'

                                ]

                            ) ?>



                        </span>





                        <a

                            href="<?= url(

                                'programme_officer/'

                                . 'candidate_view.php'

                                . '?id='

                                . (int) $candidate[

                                    'candidate_id'

                                ]

                                . '&cohort_id='

                                . (int) $candidate[

                                    'cohort_id'

                                ]

                            ) ?>"

                        >



                            View



                            <i

                                class="

                                    fas

                                    fa-arrow-right

                                "

                            ></i>



                        </a>



                    </div>



                </article>



            <?php endforeach; ?>



        </div>



    <?php endif; ?>



</section>







<!-- =========================================================

     DASHBOARD DETAIL MODAL

========================================================= -->



<div

    class="po-dashboard-modal"

    id="poDashboardModal"

    aria-hidden="true"

>

    <div class="po-dashboard-modal__backdrop" data-po-modal-close></div>



    <section

        class="po-dashboard-modal__dialog"

        role="dialog"

        aria-modal="true"

        aria-labelledby="poDashboardModalTitle"

    >

        <header class="po-dashboard-modal__header">

            <div>

                <span class="po-dashboard-modal__eyebrow">

                    Programme Officer Dashboard

                </span>

                <h3 id="poDashboardModalTitle">Details</h3>

                <p id="poDashboardModalSubtitle"></p>

            </div>



            <button

                type="button"

                class="po-dashboard-modal__close"

                data-po-modal-close

                aria-label="Close modal"

            >

                <i class="fas fa-xmark"></i>

            </button>

        </header>



        <div class="po-dashboard-modal__body">



            <div class="po-dashboard-modal__panel" data-po-panel="programmes">

                <?php if (!$modalProgrammes): ?>

                    <div class="po-dashboard-modal__empty">

                        <i class="fas fa-diagram-project"></i>

                        <strong>No programmes available</strong>

                        <span>No programmes are assigned to this Programme Officer.</span>

                    </div>

                <?php else: ?>

                    <div class="po-dashboard-modal__list">

                        <?php foreach ($modalProgrammes as $programme): ?>

                            <a

                                class="po-dashboard-modal__row"

                                href="<?= url(

                                    'programme_officer/cohorts.php?programme_id='

                                    . (int) $programme['id']

                                ) ?>"

                            >

                                <span class="po-dashboard-modal__row-icon">

                                    <i class="fas fa-diagram-project"></i>

                                </span>



                                <span class="po-dashboard-modal__row-main">

                                    <strong><?= e($programme['name']) ?></strong>

                                    <small>

                                        <?= e(po_status_label($programme['type'] ?? 'programme')) ?>

                                        ·

                                        <?= e(po_date($programme['start_date'] ?? null)) ?>

                                        –

                                        <?= e(po_date($programme['end_date'] ?? null)) ?>

                                    </small>

                                </span>



                                <span class="po-status po-status--<?= e(

                                    po_status_class($programme['status'] ?? '')

                                ) ?>">

                                    <?= e(po_status_label($programme['status'] ?? '')) ?>

                                </span>



                                <i class="fas fa-chevron-right"></i>

                            </a>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </div>



            <div class="po-dashboard-modal__panel" data-po-panel="cohorts">

                <?php if (!$modalCohorts): ?>

                    <div class="po-dashboard-modal__empty">

                        <i class="fas fa-layer-group"></i>

                        <strong>No cohorts available</strong>

                        <span>No cohorts are available in your assigned programmes.</span>

                    </div>

                <?php else: ?>

                    <div class="po-dashboard-modal__list">

                        <?php foreach ($modalCohorts as $cohort): ?>

                            <a

                                class="po-dashboard-modal__row"

                                href="<?= url(

                                    'programme_officer/cohort_view.php?id='

                                    . (int) $cohort['id']

                                ) ?>"

                            >

                                <span class="po-dashboard-modal__row-icon">

                                    <i class="fas fa-layer-group"></i>

                                </span>



                                <span class="po-dashboard-modal__row-main">

                                    <strong><?= e($cohort['name']) ?></strong>

                                    <small>

                                        <?= e($cohort['programme_name']) ?>

                                        <?php if (!empty($cohort['location'])): ?>

                                            · <?= e($cohort['location']) ?>

                                        <?php endif; ?>

                                    </small>

                                </span>



                                <span class="po-status po-status--<?= e(

                                    po_status_class($cohort['status'] ?? '')

                                ) ?>">

                                    <?= e(po_status_label($cohort['status'] ?? '')) ?>

                                </span>



                                <i class="fas fa-chevron-right"></i>

                            </a>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </div>



            <?php

            $candidatePanels = [

                'candidates' => $modalCandidates,

                'active' => $modalActiveCandidates,

                'completed' => $modalCompletedCandidates,

            ];

            ?>



            <?php foreach ($candidatePanels as $panelName => $panelCandidates): ?>

                <div

                    class="po-dashboard-modal__panel"

                    data-po-panel="<?= e($panelName) ?>"

                >

                    <?php if (!$panelCandidates): ?>

                        <div class="po-dashboard-modal__empty">

                            <i class="fas fa-users"></i>

                            <strong>No candidates available</strong>

                            <span>

                                There are no <?= e($panelName) ?> candidate records

                                in your current Programme Officer scope.

                            </span>

                        </div>

                    <?php else: ?>

                        <div class="po-dashboard-modal__list">

                            <?php foreach ($panelCandidates as $candidate): ?>

                                <?php

                                $candidateFirst = trim((string)($candidate['first_name'] ?? ''));

                                $candidateLast = trim((string)($candidate['last_name'] ?? ''));

                                $candidateName = trim($candidateFirst . ' ' . $candidateLast);



                                if ($candidateName === '') {

                                    $candidateName = 'Candidate';

                                }

                                ?>



                                <a

                                    class="po-dashboard-modal__row"

                                    href="<?= url(

                                        'programme_officer/candidate_view.php?id='

                                        . (int) $candidate['candidate_id']

                                        . '&cohort_id='

                                        . (int) $candidate['cohort_id']

                                    ) ?>"

                                >

                                    <span class="po-dashboard-modal__avatar">

                                        <?= e(po_initials($candidateFirst, $candidateLast)) ?>

                                    </span>



                                    <span class="po-dashboard-modal__row-main">

                                        <strong><?= e($candidateName) ?></strong>

                                        <small>

                                            <?= e($candidate['programme_name']) ?>

                                            ·

                                            <?= e($candidate['cohort_name']) ?>

                                        </small>

                                    </span>



                                    <span class="po-status po-status--<?= e(

                                        po_status_class(

                                            $candidate['participant_status'] ?? ''

                                        )

                                    ) ?>">

                                        <?= e(po_status_label(

                                            $candidate['participant_status'] ?? ''

                                        )) ?>

                                    </span>



                                    <i class="fas fa-chevron-right"></i>

                                </a>

                            <?php endforeach; ?>

                        </div>

                    <?php endif; ?>

                </div>

            <?php endforeach; ?>



        </div>



        <footer class="po-dashboard-modal__footer">

            <button

                type="button"

                class="po-btn po-btn--secondary"

                data-po-modal-close

            >

                Close

            </button>



            <a

                href="<?= url('programme_officer/programmes.php') ?>"

                class="po-btn po-btn--primary"

                id="poDashboardModalAction"

            >

                View all

                <i class="fas fa-arrow-right"></i>

            </a>

        </footer>

    </section>

</div>



<style>

.po-stat--interactive {

    position: relative;

    cursor: pointer;

    transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;

}

.po-stat--interactive:hover,

.po-stat--interactive:focus-visible {

    transform: translateY(-3px);

    border-color: var(--po-primary);

    box-shadow: var(--po-shadow-lg);

    outline: none;

}

.po-stat__hint {

    margin-top: 12px !important;

    display: flex !important;

    align-items: center;

    gap: 6px;

    color: var(--po-primary) !important;

    font-size: .75rem !important;

    font-weight: 700;

}

.po-stat__hint i { font-size: .7rem; }



.po-dashboard-modal {

    position: fixed;

    inset: 0;

    z-index: 99999;

    display: none;

    align-items: center;

    justify-content: center;

    padding: 24px;

}

.po-dashboard-modal.is-open { display: flex; }

.po-dashboard-modal__backdrop {

    position: absolute;

    inset: 0;

    background: rgba(2, 6, 23, .66);

    backdrop-filter: blur(4px);

}

.po-dashboard-modal__dialog {

    width: min(820px, 100%);

    max-height: min(760px, calc(100vh - 48px));

    position: relative;

    z-index: 1;

    display: flex;

    flex-direction: column;

    overflow: hidden;

    border: 1px solid var(--po-border);

    border-radius: 20px;

    background: var(--po-surface);

    box-shadow: 0 30px 80px rgba(2, 6, 23, .3);

}

.po-dashboard-modal__header {

    padding: 20px 22px;

    display: flex;

    align-items: flex-start;

    justify-content: space-between;

    gap: 20px;

    border-bottom: 1px solid var(--po-border);

}

.po-dashboard-modal__eyebrow {

    display: block;

    margin-bottom: 4px;

    color: var(--po-primary);

    font-size: .6875rem !important;

    font-weight: 800;

    letter-spacing: .08em;

    text-transform: uppercase;

}

.po-dashboard-modal__header h3 {

    margin: 0;

    color: var(--po-text);

    font-size: 1.125rem !important;

}

.po-dashboard-modal__header p {

    margin: 5px 0 0;

    color: var(--po-muted);

    font-size: .8125rem !important;

}

.po-dashboard-modal__close {

    width: 38px;

    height: 38px;

    flex: 0 0 38px;

    display: grid;

    place-items: center;

    border: 1px solid var(--po-border);

    border-radius: 10px;

    background: var(--po-soft);

    color: var(--po-muted);

    cursor: pointer;

}

.po-dashboard-modal__body {

    min-height: 180px;

    padding: 12px 20px;

    overflow-y: auto;

}

.po-dashboard-modal__panel { display: none; }

.po-dashboard-modal__panel.is-active { display: block; }

.po-dashboard-modal__list { display: grid; }

.po-dashboard-modal__row {

    min-width: 0;

    padding: 14px 4px;

    display: flex;

    align-items: center;

    gap: 12px;

    border-bottom: 1px solid var(--po-border-light);

    color: var(--po-text);

    text-decoration: none;

}

.po-dashboard-modal__row:last-child { border-bottom: 0; }

.po-dashboard-modal__row:hover .po-dashboard-modal__row-main strong {

    color: var(--po-primary);

}

.po-dashboard-modal__row-icon,

.po-dashboard-modal__avatar {

    width: 42px;

    height: 42px;

    flex: 0 0 42px;

    display: grid;

    place-items: center;

    border-radius: 11px;

    background: var(--po-primary-soft);

    color: var(--po-primary);

    font-size: .8125rem !important;

    font-weight: 800;

}

.po-dashboard-modal__avatar {

    background: linear-gradient(135deg, #2563eb, #7c3aed);

    color: #fff;

}

.po-dashboard-modal__row-main {

    min-width: 0;

    flex: 1;

}

.po-dashboard-modal__row-main strong {

    display: block;

    overflow: hidden;

    color: var(--po-text);

    font-size: .875rem !important;

    white-space: nowrap;

    text-overflow: ellipsis;

}

.po-dashboard-modal__row-main small {

    display: block;

    margin-top: 4px;

    overflow: hidden;

    color: var(--po-muted);

    font-size: .75rem !important;

    white-space: nowrap;

    text-overflow: ellipsis;

}

.po-dashboard-modal__row > .fa-chevron-right {

    color: var(--po-subtle);

    font-size: .75rem;

}

.po-dashboard-modal__empty {

    padding: 48px 20px;

    display: grid;

    justify-items: center;

    text-align: center;

}

.po-dashboard-modal__empty > i {

    width: 52px;

    height: 52px;

    margin-bottom: 12px;

    display: grid;

    place-items: center;

    border-radius: 14px;

    background: var(--po-primary-soft);

    color: var(--po-primary);

}

.po-dashboard-modal__empty strong {

    color: var(--po-text);

    font-size: .875rem !important;

}

.po-dashboard-modal__empty span {

    max-width: 420px;

    margin-top: 5px;

    color: var(--po-muted);

    font-size: .8125rem !important;

}

.po-dashboard-modal__footer {

    padding: 14px 20px;

    display: flex;

    align-items: center;

    justify-content: flex-end;

    gap: 9px;

    border-top: 1px solid var(--po-border);

    background: var(--po-soft);

}

body.po-dashboard-modal-open { overflow: hidden; }



@media (max-width: 640px) {

    .po-dashboard-modal {

        padding: 10px;

        align-items: flex-end;

    }

    .po-dashboard-modal__dialog {

        max-height: calc(100vh - 20px);

        border-radius: 18px;

    }

    .po-dashboard-modal__header {

        padding: 16px;

    }

    .po-dashboard-modal__body {

        padding: 8px 14px;

    }

    .po-dashboard-modal__row {

        align-items: flex-start;

        flex-wrap: wrap;

    }

    .po-dashboard-modal__row-main {

        flex: 1 1 calc(100% - 58px);

    }

    .po-dashboard-modal__row .po-status {

        margin-left: 54px;

    }

    .po-dashboard-modal__footer {

        padding: 12px 14px;

    }

}

.po-action-centre{margin-bottom:1.5rem}.po-action-centre__header{display:flex;align-items:center;justify-content:space-between;gap:1rem}.po-action-centre__count{display:inline-flex;align-items:center;padding:.35rem .65rem;border-radius:999px;background:var(--po-primary-soft);color:var(--po-primary);font-size:.72rem;font-weight:800}.po-action-centre__list{display:grid}.po-action-item{display:flex;align-items:center;gap:.85rem;padding:1rem 1.1rem;border-top:1px solid var(--po-border-light);text-decoration:none;color:var(--po-text);transition:background .15s ease}.po-action-item:hover{background:var(--po-soft)}.po-action-item__icon{width:40px;height:40px;flex:0 0 40px;display:grid;place-items:center;border-radius:11px;background:var(--po-primary-soft);color:var(--po-primary)}.po-action-item__icon--high{background:#fee2e2;color:#b91c1c}.po-action-item__icon--medium{background:#fef3c7;color:#92400e}.po-action-item__body{min-width:0;flex:1}.po-action-item__body strong{display:block;font-size:.84rem}.po-action-item__body span{display:block;margin-top:3px;color:var(--po-muted);font-size:.76rem;line-height:1.45}.po-action-item__body small{display:block;margin-top:4px;color:var(--po-subtle);font-size:.68rem}.po-action-item__priority{padding:.25rem .5rem;border-radius:999px;font-size:.64rem;font-weight:800}.po-action-item__priority--high{background:#fee2e2;color:#b91c1c}.po-action-item__priority--medium{background:#fef3c7;color:#92400e}.po-action-item__priority--low{background:#e2e8f0;color:#475569}.po-action-item__arrow{color:var(--po-subtle);font-size:.7rem}.po-action-centre__empty{padding:1.5rem;display:flex;align-items:center;gap:1rem;color:var(--po-muted)}.po-action-centre__empty-icon{width:42px;height:42px;display:grid;place-items:center;border-radius:12px;background:#dcfce7;color:#166534;flex:0 0 42px}.po-action-centre__empty strong{display:block;color:var(--po-text);font-size:.84rem}.po-action-centre__empty span{display:block;margin-top:3px;font-size:.76rem}@media(max-width:640px){.po-action-item{align-items:flex-start}.po-action-item__priority{display:none}.po-action-centre__header{align-items:flex-start}}
</style>



<script>

(function () {

    'use strict';



    const modal = document.getElementById('poDashboardModal');



    if (!modal) {

        return;

    }



    const title = document.getElementById('poDashboardModalTitle');

    const subtitle = document.getElementById('poDashboardModalSubtitle');

    const action = document.getElementById('poDashboardModalAction');

    const cards = document.querySelectorAll('[data-po-modal]');

    const panels = modal.querySelectorAll('[data-po-panel]');

    const closeButtons = modal.querySelectorAll('[data-po-modal-close]');



    const config = {

        programmes: {

            title: 'My Programmes',

            subtitle: 'Programmes assigned to your Programme Officer account.',

            url: <?= json_encode(url('programme_officer/programmes.php')) ?>

        },

        cohorts: {

            title: 'Cohorts',

            subtitle: 'Cohorts within your assigned programmes.',

            url: <?= json_encode(url('programme_officer/cohorts.php')) ?>

        },

        candidates: {

            title: 'Current Candidates',

            subtitle: 'Current non-withdrawn candidates across your programme portfolio.',

            url: <?= json_encode(url('programme_officer/candidates.php')) ?>

        },

        active: {

            title: 'Active Candidates',

            subtitle: 'Candidates whose current participant status is Active.',

            url: <?= json_encode(url('programme_officer/candidates.php?status=active')) ?>

        },

        completed: {

            title: 'Completed Candidates',

            subtitle: 'Candidates whose current participant status is Completed.',

            url: <?= json_encode(url('programme_officer/candidates.php?status=completed')) ?>

        }

    };



    let lastTrigger = null;



    function openModal(key, trigger) {

        const item = config[key];



        if (!item) {

            return;

        }



        lastTrigger = trigger || null;



        panels.forEach(function (panel) {

            panel.classList.toggle(

                'is-active',

                panel.getAttribute('data-po-panel') === key

            );

        });



        title.textContent = item.title;

        subtitle.textContent = item.subtitle;

        action.href = item.url;



        modal.classList.add('is-open');

        modal.setAttribute('aria-hidden', 'false');

        document.body.classList.add('po-dashboard-modal-open');



        const closeButton = modal.querySelector('.po-dashboard-modal__close');



        if (closeButton) {

            closeButton.focus();

        }

    }



    function closeModal() {

        modal.classList.remove('is-open');

        modal.setAttribute('aria-hidden', 'true');

        document.body.classList.remove('po-dashboard-modal-open');



        if (lastTrigger) {

            lastTrigger.focus();

        }

    }



    cards.forEach(function (card) {

        card.addEventListener('click', function () {

            openModal(card.getAttribute('data-po-modal'), card);

        });



        card.addEventListener('keydown', function (event) {

            if (event.key === 'Enter' || event.key === ' ') {

                event.preventDefault();

                openModal(card.getAttribute('data-po-modal'), card);

            }

        });

    });



    closeButtons.forEach(function (button) {

        button.addEventListener('click', closeModal);

    });



    document.addEventListener('keydown', function (event) {

        if (event.key === 'Escape' && modal.classList.contains('is-open')) {

            closeModal();

        }

    });

})();

</script>





<!-- =========================================================

     ACCESS INFORMATION

========================================================= -->



<div class="po-security">



    <i class="fas fa-shield-halved"></i>



    <div>



        <strong>

            Programme Officer scoped access

        </strong>



        <span>



            <?php if ($scopeMode === 'programme'): ?>



                Information is restricted using

                programmes.programme_officer_id.



            <?php elseif ($scopeMode === 'cohort'): ?>



                Information is restricted using

                cohorts.programme_officer_id.



            <?php elseif ($scopeMode === 'portfolio'): ?>



                The current database has no Programme Officer

                assignment column, so this account is operating

                with portfolio-level Programme Officer access.



            <?php else: ?>



                No Programme Officer data scope is currently

                available.



            <?php endif; ?>



        </span>



    </div>



</div>


<style>
.po-chatbot-launcher{position:fixed;right:22px;bottom:22px;width:58px;height:58px;border:0;border-radius:18px;display:grid;place-items:center;background:var(--po-primary,#2563eb);color:#fff;box-shadow:0 16px 35px rgba(15,23,42,.25);cursor:pointer;z-index:100000;font-size:1.25rem}.po-chatbot-launcher:hover{transform:translateY(-2px)}
.po-chatbot{position:fixed;inset:0;z-index:100001;display:none;padding:22px;align-items:flex-end;justify-content:flex-end}.po-chatbot.is-open{display:flex}.po-chatbot__backdrop{position:absolute;inset:0;background:rgba(2,6,23,.58);backdrop-filter:blur(3px)}
.po-chatbot__dialog{position:relative;z-index:1;width:min(430px,100%);height:min(690px,calc(100vh - 44px));display:flex;flex-direction:column;overflow:hidden;border:1px solid var(--po-border,#e4e7ec);border-radius:22px;background:var(--po-surface,#fff);box-shadow:0 30px 90px rgba(2,6,23,.32)}
.po-chatbot__header{min-height:72px;padding:14px 16px;display:flex;align-items:center;gap:12px;border-bottom:1px solid var(--po-border,#e4e7ec);background:var(--po-surface,#fff)}.po-chatbot__avatar{width:42px;height:42px;flex:0 0 42px;border-radius:13px;display:grid;place-items:center;background:var(--po-primary,#2563eb);color:#fff}.po-chatbot__heading{min-width:0;flex:1}.po-chatbot__heading strong{display:block;color:var(--po-text,#101828);font-size:.94rem}.po-chatbot__heading span{display:block;margin-top:2px;color:var(--po-muted,#667085);font-size:.72rem}
.po-chatbot__close{width:44px;height:44px;min-width:44px;flex:0 0 44px;padding:0;border:1px solid var(--po-border,#d0d5dd);border-radius:11px;background:var(--po-soft,#f8fafc);color:var(--po-muted,#475467);display:grid;place-items:center;cursor:pointer;font-size:17px}.po-chatbot__messages{flex:1;min-height:0;overflow-y:auto;padding:18px 15px;display:flex;flex-direction:column;gap:12px;background:var(--po-bg,#f8fafc)}.po-chatbot__message{max-width:88%;padding:11px 13px;border-radius:15px;font-size:.82rem;line-height:1.5;white-space:pre-wrap;word-break:break-word}.po-chatbot__message--bot{align-self:flex-start;border:1px solid var(--po-border,#e4e7ec);background:var(--po-surface,#fff);color:var(--po-text,#344054);border-top-left-radius:5px}.po-chatbot__message--user{align-self:flex-end;background:var(--po-primary,#2563eb);color:#fff;border-top-right-radius:5px}
.po-chatbot__action{display:inline-flex;align-items:center;gap:7px;margin-top:10px;padding:9px 12px;border-radius:10px;background:var(--po-primary,#2563eb);color:#fff!important;text-decoration:none!important;font-size:.75rem;font-weight:800}.po-chatbot__quick{padding:10px 13px 0;display:flex;gap:7px;flex-wrap:wrap;background:var(--po-surface,#fff)}.po-chatbot__quick button{border:1px solid var(--po-border,#d0d5dd);border-radius:999px;padding:7px 10px;background:var(--po-soft,#f8fafc);color:var(--po-text,#344054);font-size:.7rem;font-weight:700;cursor:pointer}.po-chatbot__composer{padding:12px;border-top:1px solid var(--po-border,#e4e7ec);background:var(--po-surface,#fff);display:flex;gap:8px;align-items:flex-end}.po-chatbot__input{flex:1;min-height:44px;max-height:110px;resize:none;border:1px solid var(--po-border,#d0d5dd);border-radius:12px;padding:11px 12px;background:var(--po-bg,#fff);color:var(--po-text,#101828);font:inherit;font-size:.8rem}.po-chatbot__send{width:44px;height:44px;flex:0 0 44px;border:0;border-radius:12px;background:var(--po-primary,#2563eb);color:#fff;cursor:pointer}.po-chatbot__send:disabled{opacity:.55}.po-chatbot__typing{font-size:.72rem;color:var(--po-muted,#667085);font-style:italic}
@media(max-width:620px){.po-chatbot{padding:0;align-items:stretch;justify-content:stretch}.po-chatbot__dialog{width:100%;height:100%;max-height:none;border-radius:0;border:0}.po-chatbot__header{min-height:68px;padding:11px 12px 11px 14px}.po-chatbot__close{width:46px;height:46px;min-width:46px;flex-basis:46px;border-radius:12px}.po-chatbot-launcher{right:14px;bottom:14px;width:54px;height:54px}}@media(max-width:360px){.po-chatbot__close{width:44px;height:44px;min-width:44px;flex-basis:44px}}
html[data-theme="dark"] .po-chatbot__dialog,body.dark-mode .po-chatbot__dialog{background:#101828;border-color:#344054}html[data-theme="dark"] .po-chatbot__header,body.dark-mode .po-chatbot__header,html[data-theme="dark"] .po-chatbot__quick,body.dark-mode .po-chatbot__quick,html[data-theme="dark"] .po-chatbot__composer,body.dark-mode .po-chatbot__composer{background:#101828;border-color:#344054}html[data-theme="dark"] .po-chatbot__messages,body.dark-mode .po-chatbot__messages{background:#0b1220}html[data-theme="dark"] .po-chatbot__message--bot,body.dark-mode .po-chatbot__message--bot{background:#182230;border-color:#344054;color:#e5e7eb}html[data-theme="dark"] .po-chatbot__input,body.dark-mode .po-chatbot__input{background:#182230;border-color:#475467;color:#f2f4f7}html[data-theme="dark"] .po-chatbot__close,body.dark-mode .po-chatbot__close,html[data-theme="dark"] .po-chatbot__quick button,body.dark-mode .po-chatbot__quick button{background:#182230;border-color:#475467;color:#e5e7eb}
</style>
<button type="button" class="po-chatbot-launcher" id="poChatbotLauncher" aria-label="Open Programme Officer Assistant" title="Programme Officer Assistant"><i class="fas fa-robot"></i></button>
<div class="po-chatbot" id="poChatbot" aria-hidden="true"><div class="po-chatbot__backdrop" data-po-chatbot-close></div><section class="po-chatbot__dialog" role="dialog" aria-modal="true" aria-labelledby="poChatbotTitle"><header class="po-chatbot__header"><div class="po-chatbot__avatar"><i class="fas fa-robot"></i></div><div class="po-chatbot__heading"><strong id="poChatbotTitle">Programme Officer Assistant</strong><span>Scoped to your programme portfolio</span></div><button type="button" class="po-chatbot__close" data-po-chatbot-close aria-label="Close chatbot"><i class="fas fa-xmark"></i></button></header><div class="po-chatbot__messages" id="poChatbotMessages"><div class="po-chatbot__message po-chatbot__message--bot">Hello! I can help you check your programmes, cohorts, candidates and completion progress.</div></div><div class="po-chatbot__quick"><button type="button" data-po-prompt="Show my programmes">My Programmes</button><button type="button" data-po-prompt="Show my cohorts">My Cohorts</button><button type="button" data-po-prompt="Show candidates">Candidates</button><button type="button" data-po-prompt="What is the completion rate?">Completion</button></div><form class="po-chatbot__composer" id="poChatbotForm"><textarea class="po-chatbot__input" id="poChatbotInput" rows="1" placeholder="Ask about your programme portfolio..."></textarea><button class="po-chatbot__send" id="poChatbotSend" type="submit" aria-label="Send"><i class="fas fa-paper-plane"></i></button></form></section></div>
<script>
(function(){'use strict';const launcher=document.getElementById('poChatbotLauncher'),chatbot=document.getElementById('poChatbot'),form=document.getElementById('poChatbotForm'),input=document.getElementById('poChatbotInput'),messages=document.getElementById('poChatbotMessages'),send=document.getElementById('poChatbotSend');if(!launcher||!chatbot||!form||!input||!messages||!send)return;const endpoint=<?= json_encode(url('programme_officer/chatbot_api.php')) ?>;let opener=null;function openChat(){opener=launcher;chatbot.classList.add('is-open');chatbot.setAttribute('aria-hidden','false');document.body.classList.add('po-chatbot-open');setTimeout(()=>input.focus(),30)}function closeChat(){chatbot.classList.remove('is-open');chatbot.setAttribute('aria-hidden','true');document.body.classList.remove('po-chatbot-open');if(opener)opener.focus()}function addMessage(text,role,action){const box=document.createElement('div');box.className='po-chatbot__message po-chatbot__message--'+role;box.textContent=text;if(action&&action.url){const a=document.createElement('a');a.className='po-chatbot__action';a.href=action.url;a.textContent=(action.label||'Open details')+' →';box.appendChild(a)}messages.appendChild(box);messages.scrollTop=messages.scrollHeight}function typing(){const t=document.createElement('div');t.className='po-chatbot__typing';t.id='poChatbotTyping';t.textContent='Assistant is checking your portfolio...';messages.appendChild(t);messages.scrollTop=messages.scrollHeight}function stopTyping(){const t=document.getElementById('poChatbotTyping');if(t)t.remove()}async function ask(question){const q=question.trim();if(!q)return;addMessage(q,'user');input.value='';send.disabled=true;typing();try{const res=await fetch(endpoint,{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({message:q})});const data=await res.json();stopTyping();if(!res.ok||!data.ok)throw new Error(data.message||'Unable to process the request.');addMessage(data.message||'I could not find an answer.','bot',data.action||null)}catch(err){stopTyping();addMessage(err.message||'Unable to contact the assistant.','bot')}finally{send.disabled=false;input.focus()}}launcher.addEventListener('click',openChat);chatbot.querySelectorAll('[data-po-chatbot-close]').forEach(b=>b.addEventListener('click',closeChat));document.querySelectorAll('[data-po-prompt]').forEach(b=>b.addEventListener('click',()=>ask(b.getAttribute('data-po-prompt')||'')));form.addEventListener('submit',e=>{e.preventDefault();ask(input.value)});input.addEventListener('keydown',e=>{if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();form.requestSubmit()}});document.addEventListener('keydown',e=>{if(e.key==='Escape'&&chatbot.classList.contains('is-open'))closeChat()})})();
</script>

<?php



require __DIR__ . '/_layout_end.php';