<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('recruiter');

$user        = current_user();
$flashes     = render_flashes();
$currentPage = 'reports';
$pageTitle   = 'Recruiter Reports';

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
| Statistics
|--------------------------------------------------------------------------
*/

$stats = [
    'shortlists' => 0,
    'candidates' => 0,
];

/*
|--------------------------------------------------------------------------
| Report Data
|--------------------------------------------------------------------------
*/

$shortlists = [];
$shortlistedCandidates = [];

/*
|--------------------------------------------------------------------------
| Recruiter Shortlists
|--------------------------------------------------------------------------
*/

if (rc_has_table($conn, 'recruiter_shortlists')) {

    $hasShortlistCandidates =
        rc_has_table(
            $conn,
            'recruiter_shortlist_candidates'
        );

    if ($hasShortlistCandidates) {

        $sql = "
            SELECT
                rs.id,
                rs.name,
                rs.client_name,
                rs.status,
                rs.taxonomy_version,
                rs.created_at,
                rs.updated_at,

                COUNT(
                    DISTINCT rsc.candidate_id
                ) AS candidate_count

            FROM recruiter_shortlists rs

            LEFT JOIN recruiter_shortlist_candidates rsc
                ON rsc.shortlist_id = rs.id

            WHERE rs.recruiter_id = ?

            GROUP BY
                rs.id,
                rs.name,
                rs.client_name,
                rs.status,
                rs.taxonomy_version,
                rs.created_at,
                rs.updated_at

            ORDER BY
                rs.updated_at DESC,
                rs.id DESC
        ";

    } else {

        $sql = "
            SELECT
                rs.id,
                rs.name,
                rs.client_name,
                rs.status,
                rs.taxonomy_version,
                rs.created_at,
                rs.updated_at,

                0 AS candidate_count

            FROM recruiter_shortlists rs

            WHERE rs.recruiter_id = ?

            ORDER BY
                rs.updated_at DESC,
                rs.id DESC
        ";
    }

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException(
            'Recruiter shortlist report query failed: '
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
        $shortlists[] = $row;
    }

    $stmt->close();
}

$stats['shortlists'] = count($shortlists);

/*
|--------------------------------------------------------------------------
| Unique Shortlisted Candidates
|--------------------------------------------------------------------------
*/

if (
    rc_has_table($conn, 'recruiter_shortlists') &&
    rc_has_table($conn, 'recruiter_shortlist_candidates')
) {

    $hasProfiles =
        rc_has_table(
            $conn,
            'candidate_profiles'
        );

    $hasConsents =
        rc_has_table(
            $conn,
            'candidate_consents'
        );

    /*
    |--------------------------------------------------------------------------
    | Dynamic SELECT
    |--------------------------------------------------------------------------
    */

    $profileSelect = $hasProfiles
        ? "
            cp.professional_title AS profile_title,
            cp.city,
            cp.completion_percent,
        "
        : "
            NULL AS profile_title,
            NULL AS city,
            0 AS completion_percent,
        ";

    $consentSelect = $hasConsents
        ? "
            MAX(
                CASE
                    WHEN cc.status = 'consented'
                     AND (
                        cc.expires_at IS NULL
                        OR cc.expires_at > NOW()
                     )
                    THEN 1
                    ELSE 0
                END
            ) AS consent_valid,
        "
        : "
            0 AS consent_valid,
        ";

    $profileJoin = $hasProfiles
        ? "
            LEFT JOIN candidate_profiles cp
                ON cp.user_id = u.id
        "
        : "";

    $consentJoin = $hasConsents
        ? "
            LEFT JOIN candidate_consents cc
                ON cc.candidate_id = u.id
               AND cc.consent_type = 'recruiter_discovery'
        "
        : "";

    $profileGroup = $hasProfiles
        ? ",
            cp.professional_title,
            cp.city,
            cp.completion_percent
        "
        : "";

    /*
    |--------------------------------------------------------------------------
    | Candidate Report Query
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            u.id,
            u.first_name,
            u.last_name,
            u.professional_title,
            u.province,
            u.status,

            {$profileSelect}

            {$consentSelect}

            COUNT(
                DISTINCT rs.id
            ) AS shortlist_count,

            MAX(
                rsc.added_at
            ) AS last_shortlisted_at

        FROM recruiter_shortlists rs

        INNER JOIN recruiter_shortlist_candidates rsc
            ON rsc.shortlist_id = rs.id

        INNER JOIN users u
            ON u.id = rsc.candidate_id

        INNER JOIN roles r
            ON r.id = u.role_id

        {$profileJoin}

        {$consentJoin}

        WHERE rs.recruiter_id = ?
          AND r.slug = 'candidate'

        GROUP BY
            u.id,
            u.first_name,
            u.last_name,
            u.professional_title,
            u.province,
            u.status
            {$profileGroup}

        ORDER BY
            shortlist_count DESC,
            last_shortlisted_at DESC,
            u.first_name ASC,
            u.last_name ASC
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException(
            'Shortlisted candidate report query failed: '
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
        $shortlistedCandidates[] = $row;
    }

    $stmt->close();
}

$stats['candidates'] =
    count($shortlistedCandidates);

/*
|--------------------------------------------------------------------------
| Audit
|--------------------------------------------------------------------------
*/

rc_activity(
    $conn,
    $recruiterId,
    'recruiter_reports_viewed',
    'Viewed recruiter reports.',
    'report',
    null
);

/*
|--------------------------------------------------------------------------
| Layout
|--------------------------------------------------------------------------
*/

require __DIR__ . '/_layout_start.php';

?>

<style>

.rc-report-card {
    position: relative;
    cursor: pointer;
    transition:
        transform .2s ease,
        box-shadow .2s ease;
}

.rc-report-card:hover {
    transform: translateY(-4px);
}

.rc-report-card:focus-visible {
    outline: 3px solid currentColor;
    outline-offset: 3px;
}

.rc-report-card small {
    display: block;
    margin-top: 7px;
    font-size: 11px;
    opacity: .65;
}


/* ================================================================
   REPORT TABLE HEADER
================================================================ */

.rc-report-section-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
}

.rc-report-section-header p {
    margin: 5px 0 0;
    opacity: .7;
}


/* ================================================================
   REPORT MODAL
================================================================ */

.rc-report-modal {
    position: fixed;
    inset: 0;
    z-index: 10000;

    display: none;
    align-items: center;
    justify-content: center;

    padding: 24px;
}

.rc-report-modal.is-open {
    display: flex;
}

.rc-report-modal__backdrop {
    position: absolute;
    inset: 0;

    background: rgba(15, 23, 42, .70);
    backdrop-filter: blur(5px);
}

.rc-report-modal__dialog {
    position: relative;
    z-index: 1;

    width: min(800px, 100%);
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

.rc-report-modal__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;

    gap: 20px;

    padding: 24px 26px 18px;

    border-bottom:
        1px solid
        rgba(127, 127, 127, .16);
}

.rc-report-modal__header h3 {
    margin: 5px 0;
}

.rc-report-modal__header p {
    margin: 0;
    opacity: .7;
}

.rc-report-modal__close {
    width: 42px;
    height: 42px;

    flex: 0 0 42px;

    display: grid;
    place-items: center;

    border: 0;
    border-radius: 50%;

    cursor: pointer;

    color: inherit;

    background:
        rgba(127, 127, 127, .10);
}

.rc-report-modal__body {
    flex: 1;
    overflow-y: auto;

    padding: 20px 26px;
}

.rc-report-modal__footer {
    display: flex;
    justify-content: flex-end;

    padding: 16px 26px;

    border-top:
        1px solid
        rgba(127, 127, 127, .16);
}

.rc-report-panel {
    display: none;
}

.rc-report-panel.is-active {
    display: block;
}


/* ================================================================
   MODAL ROW
================================================================ */

.rc-report-row {
    display: flex;
    align-items: center;

    gap: 14px;

    padding: 15px 4px;

    border-bottom:
        1px solid
        rgba(127, 127, 127, .13);
}

.rc-report-row:last-child {
    border-bottom: 0;
}

.rc-report-row__icon {
    width: 44px;
    height: 44px;

    flex: 0 0 44px;

    display: grid;
    place-items: center;

    border-radius: 13px;

    background:
        rgba(127, 127, 127, .10);
}

.rc-report-row__content {
    flex: 1;
    min-width: 0;
}

.rc-report-row__content strong,
.rc-report-row__content span,
.rc-report-row__content small {
    display: block;
}

.rc-report-row__content span {
    margin-top: 3px;
}

.rc-report-row__content small {
    margin-top: 4px;
    opacity: .65;
}

.rc-report-row__action {
    width: 40px;
    height: 40px;

    flex: 0 0 40px;

    display: grid;
    place-items: center;

    border-radius: 11px;

    text-decoration: none;

    background:
        rgba(127, 127, 127, .09);
}


/* ================================================================
   EMPTY
================================================================ */

.rc-report-empty {
    min-height: 220px;

    display: flex;
    flex-direction: column;

    align-items: center;
    justify-content: center;

    gap: 9px;

    text-align: center;
}

.rc-report-empty i {
    font-size: 34px;
    opacity: .5;
}

.rc-report-empty span {
    max-width: 430px;
    opacity: .7;
}


/* ================================================================
   DARK MODE
================================================================ */

body.dark-mode .rc-report-modal__dialog,
html[data-theme="dark"] .rc-report-modal__dialog {
    background: #111827;
    color: #f8fafc;
}

body.dark-mode .rc-report-row__icon,
html[data-theme="dark"] .rc-report-row__icon,
body.dark-mode .rc-report-row__action,
html[data-theme="dark"] .rc-report-row__action,
body.dark-mode .rc-report-modal__close,
html[data-theme="dark"] .rc-report-modal__close {
    background:
        rgba(255, 255, 255, .08);
}

body.rc-report-modal-open {
    overflow: hidden;
}


/* ================================================================
   MOBILE
================================================================ */

@media (max-width: 768px) {

    .rc-report-modal {
        padding: 12px;
    }

    .rc-report-modal__dialog {
        width: 100%;
        max-height: 92vh;
        border-radius: 18px;
    }

    .rc-report-modal__header,
    .rc-report-modal__body,
    .rc-report-modal__footer {
        padding-left: 18px;
        padding-right: 18px;
    }

    .rc-report-section-header {
        flex-direction: column;
    }

}

</style>


<!-- ================================================================
     HEADER
================================================================ -->

<div class="rc-page-header">

    <span class="rc-eyebrow">

        <i class="fas fa-chart-column"></i>

        Recruitment Insights

    </span>

    <h2>
        Reports
    </h2>

    <p>
        Review your shortlist output and unique
        shortlisted candidates.
    </p>

</div>


<!-- ================================================================
     REPORT CARDS
================================================================ -->

<section class="rc-stats rc-stats--2">


    <!-- Shortlists -->

    <article
        class="rc-report-card"
        role="button"
        tabindex="0"
        data-report-modal="shortlists"
    >

        <i class="fas fa-list-check"></i>

        <strong>
            <?= number_format(
                (int)$stats['shortlists']
            ) ?>
        </strong>

        <span>
            Shortlists
        </span>

        <small>
            Click to view
        </small>

    </article>


    <!-- Unique Candidates -->

    <article
        class="rc-report-card"
        role="button"
        tabindex="0"
        data-report-modal="candidates"
    >

        <i class="fas fa-users"></i>

        <strong>
            <?= number_format(
                (int)$stats['candidates']
            ) ?>
        </strong>

        <span>
            Unique Shortlisted
        </span>

        <small>
            Click to view
        </small>

    </article>

</section>


<!-- ================================================================
     SHORTLIST REPORT TABLE
================================================================ -->

<div class="rc-card">

    <div class="rc-card__header rc-report-section-header">

        <div>

            <h3>
                Shortlist Report
            </h3>

            <p>
                Shortlists created by your Recruiter account.
            </p>

        </div>

        <a
            class="rc-btn rc-btn--primary"
            href="<?= url(
                'recruiter/shortlists.php'
            ) ?>"
        >

            <i class="fas fa-list-check"></i>

            Manage Shortlists

        </a>

    </div>


    <?php if (!$shortlists): ?>

        <div class="rc-empty">

            <i class="fas fa-list-check"></i>

            <strong>
                No shortlist data
            </strong>

            <span>
                You have not created a recruiter
                shortlist yet.
            </span>

        </div>

    <?php else: ?>

        <div class="rc-table-wrap">

            <table class="rc-table">

                <thead>

                    <tr>

                        <th>
                            Shortlist
                        </th>

                        <th>
                            Client
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Candidates
                        </th>

                        <th>
                            Created
                        </th>

                        <th>
                            Action
                        </th>

                    </tr>

                </thead>


                <tbody>

                    <?php foreach (
                        $shortlists
                        as $shortlist
                    ): ?>

                        <tr>

                            <td>

                                <strong>
                                    <?= e(
                                        $shortlist['name']
                                    ) ?>
                                </strong>

                            </td>


                            <td>

                                <?= e(
                                    (string)(
                                        $shortlist[
                                            'client_name'
                                        ]
                                        ?: '—'
                                    )
                                ) ?>

                            </td>


                            <td>

                                <span class="rc-status">

                                    <?= e(
                                        rc_label(
                                            $shortlist[
                                                'status'
                                            ]
                                        )
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <strong>
                                    <?= number_format(
                                        (int)$shortlist[
                                            'candidate_count'
                                        ]
                                    ) ?>
                                </strong>

                            </td>


                            <td>

                                <?= e(
                                    rc_date(
                                        $shortlist[
                                            'created_at'
                                        ]
                                    )
                                ) ?>

                            </td>


                            <td>

                                <a
                                    class="rc-btn rc-btn--secondary"
                                    href="<?= url(
                                        'recruiter/shortlist_view.php?id='
                                        . (int)$shortlist['id']
                                    ) ?>"
                                >

                                    <i class="fas fa-eye"></i>

                                    View

                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</div>


<!-- ================================================================
     UNIQUE SHORTLISTED CANDIDATES
================================================================ -->

<div class="rc-card rc-space">

    <div class="rc-card__header">

        <div>

            <h3>
                Unique Shortlisted Candidates
            </h3>

            <p>
                Candidate accounts appearing in one or more
                of your shortlists.
            </p>

        </div>

    </div>


    <?php if (!$shortlistedCandidates): ?>

        <div class="rc-empty">

            <i class="fas fa-users"></i>

            <strong>
                No shortlisted candidates
            </strong>

            <span>
                Add candidates to a shortlist to begin
                generating recruiter report data.
            </span>

        </div>

    <?php else: ?>

        <div class="rc-table-wrap">

            <table class="rc-table">

                <thead>

                    <tr>

                        <th>Candidate</th>
                        <th>Location</th>
                        <th>Shortlists</th>
                        <th>Profile</th>
                        <th>Consent</th>
                        <th>Last Shortlisted</th>

                    </tr>

                </thead>


                <tbody>

                    <?php foreach (
                        $shortlistedCandidates
                        as $candidate
                    ): ?>

                        <?php

                        $candidateName = trim(
                            (string)$candidate[
                                'first_name'
                            ]
                            . ' '
                            . (string)$candidate[
                                'last_name'
                            ]
                        );

                        $candidateTitle = trim(
                            (string)(
                                $candidate[
                                    'profile_title'
                                ]
                                ?: $candidate[
                                    'professional_title'
                                ]
                                ?: 'Candidate'
                            )
                        );

                        $location = trim(
                            (string)(
                                $candidate['city']
                                ?? ''
                            )
                            . ', '
                            . (string)(
                                $candidate['province']
                                ?? ''
                            ),
                            ' ,'
                        );

                        ?>

                        <tr>

                            <td>

                                <strong>
                                    <?= e(
                                        $candidateName
                                        ?: 'Candidate'
                                    ) ?>
                                </strong>

                                <small>
                                    <?= e(
                                        $candidateTitle
                                    ) ?>
                                </small>

                            </td>


                            <td>

                                <?= e(
                                    $location
                                    ?: 'Not provided'
                                ) ?>

                            </td>


                            <td>

                                <strong>
                                    <?= number_format(
                                        (int)$candidate[
                                            'shortlist_count'
                                        ]
                                    ) ?>
                                </strong>

                            </td>


                            <td>

                                <?= number_format(
                                    (int)$candidate[
                                        'completion_percent'
                                    ]
                                ) ?>%

                            </td>


                            <td>

                                <?php if (
                                    (int)$candidate[
                                        'consent_valid'
                                    ] === 1
                                ): ?>

                                    <span
                                        class="
                                            rc-status
                                            rc-status--green
                                        "
                                    >
                                        Valid
                                    </span>

                                <?php else: ?>

                                    <span class="rc-status">
                                        Unavailable
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?= e(
                                    rc_datetime(
                                        $candidate[
                                            'last_shortlisted_at'
                                        ]
                                    )
                                ) ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</div>


<!-- ================================================================
     REPORTING FOUNDATION
================================================================ -->

<div class="rc-card rc-space">

    <div class="rc-card__body rc-info">

        <i class="fas fa-chart-column"></i>

        <div>

            <strong>
                Recruiter reporting foundation
            </strong>

            <p>
                Shortlist and unique-candidate reporting is
                based on the current recruitment workflow.
                Client response, placement and conversion
                metrics can be added when those workflow
                records are available.
            </p>

        </div>

    </div>

</div>


<!-- ================================================================
     CARD MODAL
================================================================ -->

<div
    class="rc-report-modal"
    id="rcReportModal"
    aria-hidden="true"
>

    <div
        class="rc-report-modal__backdrop"
        data-report-close
    ></div>


    <div
        class="rc-report-modal__dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="rcReportModalTitle"
    >

        <div class="rc-report-modal__header">

            <div>

                <span class="rc-eyebrow">
                    Recruitment Insights
                </span>

                <h3 id="rcReportModalTitle">
                    Report Details
                </h3>

                <p id="rcReportModalDescription">
                    Recruiter report information.
                </p>

            </div>


            <button
                type="button"
                class="rc-report-modal__close"
                data-report-close
                aria-label="Close"
            >
                <i class="fas fa-xmark"></i>
            </button>

        </div>


        <div class="rc-report-modal__body">


            <!-- Shortlists -->

            <section
                class="rc-report-panel"
                data-report-panel="shortlists"
            >

                <?php if (!$shortlists): ?>

                    <div class="rc-report-empty">

                        <i class="fas fa-list-check"></i>

                        <strong>
                            No shortlists
                        </strong>

                        <span>
                            You have not created any
                            recruiter shortlists yet.
                        </span>

                    </div>

                <?php else: ?>

                    <?php foreach (
                        $shortlists
                        as $shortlist
                    ): ?>

                        <div class="rc-report-row">

                            <div class="rc-report-row__icon">

                                <i class="fas fa-list-check"></i>

                            </div>


                            <div class="rc-report-row__content">

                                <strong>
                                    <?= e(
                                        $shortlist['name']
                                    ) ?>
                                </strong>

                                <span>
                                    <?= e(
                                        (string)(
                                            $shortlist[
                                                'client_name'
                                            ]
                                            ?: 'No client specified'
                                        )
                                    ) ?>
                                </span>

                                <small>

                                    <?= number_format(
                                        (int)$shortlist[
                                            'candidate_count'
                                        ]
                                    ) ?>

                                    <?= (int)$shortlist[
                                        'candidate_count'
                                    ] === 1
                                        ? 'candidate'
                                        : 'candidates' ?>

                                    ·

                                    <?= e(
                                        rc_label(
                                            $shortlist[
                                                'status'
                                            ]
                                        )
                                    ) ?>

                                </small>

                            </div>


                            <a
                                class="rc-report-row__action"
                                href="<?= url(
                                    'recruiter/shortlist_view.php?id='
                                    . (int)$shortlist['id']
                                ) ?>"
                                title="Open Shortlist"
                            >

                                <i class="fas fa-arrow-right"></i>

                            </a>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </section>


            <!-- Candidates -->

            <section
                class="rc-report-panel"
                data-report-panel="candidates"
            >

                <?php if (
                    !$shortlistedCandidates
                ): ?>

                    <div class="rc-report-empty">

                        <i class="fas fa-users"></i>

                        <strong>
                            No shortlisted candidates
                        </strong>

                        <span>
                            Candidates will appear here after
                            they have been added to one of
                            your shortlists.
                        </span>

                    </div>

                <?php else: ?>

                    <?php foreach (
                        $shortlistedCandidates
                        as $candidate
                    ): ?>

                        <?php

                        $modalCandidateName = trim(
                            (string)$candidate[
                                'first_name'
                            ]
                            . ' '
                            . (string)$candidate[
                                'last_name'
                            ]
                        );

                        ?>

                        <div class="rc-report-row">

                            <div class="rc-report-row__icon">

                                <i class="fas fa-user"></i>

                            </div>


                            <div class="rc-report-row__content">

                                <strong>
                                    <?= e(
                                        $modalCandidateName
                                        ?: 'Candidate'
                                    ) ?>
                                </strong>

                                <span>

                                    <?= number_format(
                                        (int)$candidate[
                                            'shortlist_count'
                                        ]
                                    ) ?>

                                    <?= (int)$candidate[
                                        'shortlist_count'
                                    ] === 1
                                        ? 'shortlist'
                                        : 'shortlists' ?>

                                </span>

                                <small>

                                    <?php if (
                                        (int)$candidate[
                                            'consent_valid'
                                        ] === 1
                                    ): ?>

                                        Recruiter consent valid

                                    <?php else: ?>

                                        Detailed profile access unavailable

                                    <?php endif; ?>

                                </small>

                            </div>


                            <?php if (
                                (int)$candidate[
                                    'consent_valid'
                                ] === 1
                            ): ?>

                                <a
                                    class="rc-report-row__action"
                                    href="<?= url(
                                        'recruiter/candidate_view.php?id='
                                        . (int)$candidate['id']
                                    ) ?>"
                                    title="View Candidate"
                                >

                                    <i class="fas fa-arrow-right"></i>

                                </a>

                            <?php endif; ?>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </section>

        </div>


        <div class="rc-report-modal__footer">

            <button
                type="button"
                class="rc-btn rc-btn--secondary"
                data-report-close
            >

                <i class="fas fa-xmark"></i>
                Close

            </button>

        </div>

    </div>

</div>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const modal =
            document.getElementById(
                'rcReportModal'
            );

        if (!modal) {
            return;
        }

        const cards =
            document.querySelectorAll(
                '[data-report-modal]'
            );

        const panels =
            modal.querySelectorAll(
                '[data-report-panel]'
            );

        const closeButtons =
            modal.querySelectorAll(
                '[data-report-close]'
            );

        const title =
            document.getElementById(
                'rcReportModalTitle'
            );

        const description =
            document.getElementById(
                'rcReportModalDescription'
            );

        let lastTrigger = null;


        const config = {

            shortlists: {
                title: 'My Shortlists',
                description:
                    'Shortlists owned by your Recruiter account.'
            },

            candidates: {
                title: 'Unique Shortlisted Candidates',
                description:
                    'Candidates appearing in one or more of your shortlists.'
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
                        panel.dataset.reportPanel
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
                'rc-report-modal-open'
            );

            const closeButton =
                modal.querySelector(
                    '.rc-report-modal__close'
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
                'rc-report-modal-open'
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
                            card.dataset.reportModal,
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
                                card.dataset.reportModal,
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