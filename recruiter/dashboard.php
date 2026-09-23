<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('recruiter');

$user        = current_user();
$flashes     = render_flashes();
$currentPage = 'dashboard';
$pageTitle   = 'Recruiter Dashboard';

require_once __DIR__ . '/_helpers.php';

$conn = Database::getConnection();

$recruiterId = (int)($user['id'] ?? $user['user_id'] ?? 0);

if ($recruiterId <= 0) {
    http_response_code(403);
    exit('Invalid Recruiter account.');
}

/*
|--------------------------------------------------------------------------
| Dashboard Statistics
|--------------------------------------------------------------------------
*/

$stats = [
    'candidates'     => 0,
    'complete'       => 0,
    'consented'      => 0,
    'verifiedSkills' => 0,
    'gauteng'        => 0,
    'opportunities'  => 0,
    'applications'   => 0,
    'shortlists'     => 0,
];

/*
|--------------------------------------------------------------------------
| Modal Data
|--------------------------------------------------------------------------
*/

$modalData = [
    'candidates'     => [],
    'complete'       => [],
    'consented'      => [],
    'verifiedSkills' => [],
    'gauteng'        => [],
    'opportunities'  => [],
    'applications'   => [],
    'shortlists'     => [],
];

/*
|--------------------------------------------------------------------------
| Active Candidate Accounts
|--------------------------------------------------------------------------
|
| Candidate accounts are stored in users and identified through roles.
| candidate_profiles is optional.
|
*/

$hasCandidateProfiles = rc_has_table($conn, 'candidate_profiles');

if ($hasCandidateProfiles) {

    $sql = "
        SELECT
            u.id,
            u.first_name,
            u.last_name,
            u.professional_title,
            u.province,
            u.qualification_level,
            u.employment_status,

            cp.professional_title AS profile_title,
            cp.city,
            cp.completion_percent,
            cp.is_active AS profile_active

        FROM users u

        INNER JOIN roles r
            ON r.id = u.role_id

        LEFT JOIN candidate_profiles cp
            ON cp.user_id = u.id

        WHERE r.slug = 'candidate'
          AND u.status = 'active'

        ORDER BY
            u.first_name ASC,
            u.last_name ASC
    ";

} else {

    $sql = "
        SELECT
            u.id,
            u.first_name,
            u.last_name,
            u.professional_title,
            u.province,
            u.qualification_level,
            u.employment_status,

            NULL AS profile_title,
            NULL AS city,
            0 AS completion_percent,
            0 AS profile_active

        FROM users u

        INNER JOIN roles r
            ON r.id = u.role_id

        WHERE r.slug = 'candidate'
          AND u.status = 'active'

        ORDER BY
            u.first_name ASC,
            u.last_name ASC
    ";
}

$result = $conn->query($sql);

if (!$result) {
    throw new RuntimeException(
        'Candidate dashboard query failed: ' . $conn->error
    );
}

while ($row = $result->fetch_assoc()) {

    $modalData['candidates'][] = $row;

    /*
    |--------------------------------------------------------------------------
    | Complete Profile
    |--------------------------------------------------------------------------
    */

    if ((int)($row['completion_percent'] ?? 0) >= 100) {
        $modalData['complete'][] = $row;
    }

    /*
    |--------------------------------------------------------------------------
    | Gauteng Candidate
    |--------------------------------------------------------------------------
    |
    | Province belongs to users.
    |
    */

    if (
        strtolower(
            trim((string)($row['province'] ?? ''))
        ) === 'gauteng'
    ) {
        $modalData['gauteng'][] = $row;
    }
}

$result->free();

$stats['candidates'] = count($modalData['candidates']);
$stats['complete']   = count($modalData['complete']);
$stats['gauteng']    = count($modalData['gauteng']);

/*
|--------------------------------------------------------------------------
| Consent Eligible Candidates
|--------------------------------------------------------------------------
*/

if (rc_has_table($conn, 'candidate_consents')) {

    if ($hasCandidateProfiles) {

        $sql = "
            SELECT DISTINCT
                u.id,
                u.first_name,
                u.last_name,
                u.professional_title,
                u.province,

                cp.professional_title AS profile_title,
                cp.city,
                cp.completion_percent,

                cc.granted_at,
                cc.expires_at

            FROM candidate_consents cc

            INNER JOIN users u
                ON u.id = cc.candidate_id

            INNER JOIN roles r
                ON r.id = u.role_id

            LEFT JOIN candidate_profiles cp
                ON cp.user_id = u.id

            WHERE r.slug = 'candidate'
              AND u.status = 'active'
              AND cc.consent_type = 'recruiter_discovery'
              AND cc.status = 'consented'
              AND (
                    cc.expires_at IS NULL
                    OR cc.expires_at > NOW()
                  )

            ORDER BY
                u.first_name ASC,
                u.last_name ASC
        ";

    } else {

        $sql = "
            SELECT DISTINCT
                u.id,
                u.first_name,
                u.last_name,
                u.professional_title,
                u.province,

                NULL AS profile_title,
                NULL AS city,
                0 AS completion_percent,

                cc.granted_at,
                cc.expires_at

            FROM candidate_consents cc

            INNER JOIN users u
                ON u.id = cc.candidate_id

            INNER JOIN roles r
                ON r.id = u.role_id

            WHERE r.slug = 'candidate'
              AND u.status = 'active'
              AND cc.consent_type = 'recruiter_discovery'
              AND cc.status = 'consented'
              AND (
                    cc.expires_at IS NULL
                    OR cc.expires_at > NOW()
                  )

            ORDER BY
                u.first_name ASC,
                u.last_name ASC
        ";
    }

    $result = $conn->query($sql);

    if (!$result) {
        throw new RuntimeException(
            'Consent dashboard query failed: ' . $conn->error
        );
    }

    while ($row = $result->fetch_assoc()) {
        $modalData['consented'][] = $row;
    }

    $result->free();
}

$stats['consented'] = count($modalData['consented']);

/*
|--------------------------------------------------------------------------
| Candidates With Verified Skills
|--------------------------------------------------------------------------
|
| Confirmed schema:
| candidate_skills.user_id
| candidate_skills.skill_id
| candidate_skills.proficiency
| candidate_skills.verification_status
| candidate_skills.verified_at
|
| skills.name
| skills.category
| skills.is_active
|
*/

if (
    rc_has_table($conn, 'candidate_skills') &&
    rc_has_table($conn, 'skills')
) {

    $sql = "
        SELECT
            u.id,
            u.first_name,
            u.last_name,
            u.professional_title,
            u.province,

            COUNT(DISTINCT cs.skill_id)
                AS verified_skill_count,

            GROUP_CONCAT(
                DISTINCT s.name
                ORDER BY s.name ASC
                SEPARATOR ', '
            ) AS verified_skills

        FROM candidate_skills cs

        INNER JOIN skills s
            ON s.id = cs.skill_id
           AND s.is_active = 1

        INNER JOIN users u
            ON u.id = cs.user_id

        INNER JOIN roles r
            ON r.id = u.role_id

        WHERE cs.verification_status = 'verified'
          AND r.slug = 'candidate'
          AND u.status = 'active'

        GROUP BY
            u.id,
            u.first_name,
            u.last_name,
            u.professional_title,
            u.province

        ORDER BY
            verified_skill_count DESC,
            u.first_name ASC,
            u.last_name ASC
    ";

    $result = $conn->query($sql);

    if (!$result) {
        throw new RuntimeException(
            'Verified skills dashboard query failed: ' . $conn->error
        );
    }

    while ($row = $result->fetch_assoc()) {
        $modalData['verifiedSkills'][] = $row;
    }

    $result->free();
}

$stats['verifiedSkills'] = count($modalData['verifiedSkills']);

/*
|--------------------------------------------------------------------------
| Published Opportunities
|--------------------------------------------------------------------------
*/

if (rc_has_table($conn, 'opportunities')) {

    $sql = "
        SELECT
            id,
            title,
            organisation,
            type,
            province,
            city,
            application_open_date,
            application_close_date,
            available_positions,
            status

        FROM opportunities

        WHERE status IN (
            'published',
            'closing_soon'
        )

        ORDER BY
            application_close_date ASC,
            id DESC
    ";

    $result = $conn->query($sql);

    if (!$result) {
        throw new RuntimeException(
            'Opportunity dashboard query failed: ' . $conn->error
        );
    }

    while ($row = $result->fetch_assoc()) {
        $modalData['opportunities'][] = $row;
    }

    $result->free();
}

$stats['opportunities'] = count($modalData['opportunities']);

/*
|--------------------------------------------------------------------------
| Submitted Applications
|--------------------------------------------------------------------------
*/

if (rc_has_table($conn, 'applications')) {

    $sql = "
        SELECT
            a.id,
            a.application_reference,
            a.candidate_id,
            a.opportunity_id,
            a.status,
            a.created_at,
            a.submitted_at,

            u.first_name,
            u.last_name,

            o.title AS opportunity_title

        FROM applications a

        LEFT JOIN users u
            ON u.id = a.candidate_id

        LEFT JOIN opportunities o
            ON o.id = a.opportunity_id

        WHERE a.status <> 'draft'

        ORDER BY
            COALESCE(a.submitted_at, a.created_at) DESC,
            a.id DESC
    ";

    $result = $conn->query($sql);

    if (!$result) {
        throw new RuntimeException(
            'Application dashboard query failed: ' . $conn->error
        );
    }

    while ($row = $result->fetch_assoc()) {
        $modalData['applications'][] = $row;
    }

    $result->free();
}

$stats['applications'] = count($modalData['applications']);

/*
|--------------------------------------------------------------------------
| Recruiter's Shortlists
|--------------------------------------------------------------------------
*/

if (rc_has_table($conn, 'recruiter_shortlists')) {

    $hasShortlistCandidates =
        rc_has_table($conn, 'recruiter_shortlist_candidates');

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
                    DISTINCT rsc.id
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
            'Shortlist dashboard query failed: ' . $conn->error
        );
    }

    $stmt->bind_param(
        'i',
        $recruiterId
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $modalData['shortlists'][] = $row;
    }

    $stmt->close();
}

$stats['shortlists'] = count($modalData['shortlists']);

/*
|--------------------------------------------------------------------------
| Recruiter / Taxonomy
|--------------------------------------------------------------------------
*/

$firstName = trim(
    (string)($user['first_name'] ?? 'Recruiter')
);

$taxonomy = rc_taxonomy_version($conn);

/*
|--------------------------------------------------------------------------
| Layout
|--------------------------------------------------------------------------
*/

require __DIR__ . '/_layout_start.php';

?>


<!-- ================================================================
     DASHBOARD MODAL + CARD STYLES
================================================================ -->

<style>

.rc-stat-card--interactive {
    position: relative;
    cursor: pointer;
    transition:
        transform .2s ease,
        box-shadow .2s ease,
        border-color .2s ease;
}

.rc-stat-card--interactive:hover {
    transform: translateY(-4px);
}

.rc-stat-card--interactive:focus-visible {
    outline: 3px solid currentColor;
    outline-offset: 3px;
}

.rc-stat-card--interactive small {
    display: block;
    margin-top: 7px;
    font-size: 11px;
    opacity: .65;
}


/* ================================================================
   MODAL
================================================================ */

.rc-dashboard-modal {
    position: fixed;
    inset: 0;
    z-index: 10000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 24px;
}

.rc-dashboard-modal.is-open {
    display: flex;
}

.rc-dashboard-modal__backdrop {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, .70);
    backdrop-filter: blur(5px);
}

.rc-dashboard-modal__dialog {
    position: relative;
    z-index: 1;

    width: min(780px, 100%);
    max-height: min(84vh, 850px);

    display: flex;
    flex-direction: column;

    overflow: hidden;

    border-radius: 24px;

    background: #ffffff;

    box-shadow:
        0 25px 80px
        rgba(0, 0, 0, .28);
}

.rc-dashboard-modal__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;

    gap: 20px;

    padding: 24px 26px 18px;

    border-bottom:
        1px solid
        rgba(127, 127, 127, .16);
}

.rc-dashboard-modal__header h3 {
    margin: 5px 0 4px;
}

.rc-dashboard-modal__header p {
    margin: 0;
    opacity: .70;
}

.rc-dashboard-modal__close {
    width: 42px;
    height: 42px;

    flex: 0 0 42px;

    display: grid;
    place-items: center;

    border: 0;
    border-radius: 50%;

    cursor: pointer;

    background:
        rgba(15, 23, 42, .07);

    color: inherit;
}

.rc-dashboard-modal__body {
    flex: 1;

    padding: 20px 26px;

    overflow-y: auto;
}

.rc-dashboard-modal__footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;

    padding: 16px 26px;

    border-top:
        1px solid
        rgba(127, 127, 127, .16);
}

.rc-modal-panel {
    display: none;
}

.rc-modal-panel.is-active {
    display: block;
}


/* ================================================================
   MODAL ROWS
================================================================ */

.rc-modal-row {
    display: flex;
    align-items: center;

    gap: 14px;

    padding: 15px 4px;

    border-bottom:
        1px solid
        rgba(127, 127, 127, .13);
}

.rc-modal-row:last-child {
    border-bottom: 0;
}

.rc-modal-row__icon {
    width: 44px;
    height: 44px;

    flex: 0 0 44px;

    display: grid;
    place-items: center;

    border-radius: 13px;

    background:
        rgba(127, 127, 127, .10);
}

.rc-modal-row__content {
    flex: 1;
    min-width: 0;
}

.rc-modal-row__content strong,
.rc-modal-row__content span,
.rc-modal-row__content small {
    display: block;
}

.rc-modal-row__content strong {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.rc-modal-row__content span {
    margin-top: 3px;

    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.rc-modal-row__content small {
    margin-top: 4px;
    opacity: .65;
}

.rc-modal-row__action {
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

.rc-modal-row__action:hover {
    transform: translateX(2px);
}


/* ================================================================
   EMPTY STATE
================================================================ */

.rc-modal-empty {
    min-height: 230px;

    display: flex;
    flex-direction: column;

    align-items: center;
    justify-content: center;

    gap: 10px;

    text-align: center;
}

.rc-modal-empty > i {
    font-size: 36px;
    opacity: .55;
}

.rc-modal-empty strong {
    font-size: 17px;
}

.rc-modal-empty span {
    max-width: 430px;
    opacity: .70;
}


/* ================================================================
   BADGES
================================================================ */

.rc-modal-badge {
    display: inline-flex !important;
    align-items: center;

    width: fit-content;

    margin-top: 6px !important;

    padding: 4px 8px;

    border-radius: 999px;

    font-size: 11px;

    background:
        rgba(127, 127, 127, .10);
}


/* ================================================================
   BODY LOCK
================================================================ */

body.rc-modal-open {
    overflow: hidden;
}


/* ================================================================
   DARK MODE
================================================================ */

body.dark-mode .rc-dashboard-modal__dialog,
html[data-theme="dark"] .rc-dashboard-modal__dialog {
    background: #111827;
    color: #f8fafc;
}

body.dark-mode .rc-dashboard-modal__close,
html[data-theme="dark"] .rc-dashboard-modal__close {
    background:
        rgba(255, 255, 255, .08);

    color: #f8fafc;
}

body.dark-mode .rc-modal-row__icon,
html[data-theme="dark"] .rc-modal-row__icon,
body.dark-mode .rc-modal-row__action,
html[data-theme="dark"] .rc-modal-row__action,
body.dark-mode .rc-modal-badge,
html[data-theme="dark"] .rc-modal-badge {
    background:
        rgba(255, 255, 255, .08);
}


/* ================================================================
   RESPONSIVE
================================================================ */

@media (max-width: 768px) {

    .rc-dashboard-modal {
        padding: 12px;
    }

    .rc-dashboard-modal__dialog {
        width: 100%;
        max-height: 91vh;
        border-radius: 18px;
    }

    .rc-dashboard-modal__header {
        padding: 19px;
    }

    .rc-dashboard-modal__body {
        padding: 14px 19px;
    }

    .rc-dashboard-modal__footer {
        padding: 14px 19px;
    }

    .rc-modal-row {
        gap: 11px;
    }

    .rc-modal-row__icon {
        width: 40px;
        height: 40px;
        flex-basis: 40px;
    }

}

</style>


<!-- ================================================================
     HERO
================================================================ -->

<section class="rc-hero">

    <div>

        <span class="rc-eyebrow">
            <i class="fas fa-bolt"></i>
            Talent Discovery
        </span>

        <h2>
            Find the right talent faster,
            <?= e($firstName ?: 'Recruiter') ?>.
        </h2>

        <p>
            Discover active candidate accounts, review consented
            talent profiles, identify verified skills and manage
            recruitment shortlists.
        </p>

        <div class="rc-hero__actions">

            <a
                class="rc-btn rc-btn--light"
                href="<?= url('recruiter/search.php') ?>"
            >
                <i class="fas fa-magnifying-glass"></i>
                Find Candidates
            </a>

            <a
                class="rc-btn rc-btn--glass"
                href="<?= url('recruiter/shortlists.php') ?>"
            >
                <i class="fas fa-list-check"></i>
                Open Shortlists
            </a>

        </div>

    </div>


    <div class="rc-taxonomy">

        <span>Active Taxonomy</span>

        <strong>
            <?= e($taxonomy) ?>
        </strong>

        <small>
            Matching decisions remain reproducible.
        </small>

    </div>

</section>


<!-- ================================================================
     DASHBOARD STATISTICS
================================================================ -->

<section class="rc-stats">

    <!-- Candidate Pool -->

    <article
        class="rc-stat-card--interactive"
        role="button"
        tabindex="0"
        data-rc-modal="candidates"
    >

        <i class="fas fa-users"></i>

        <strong>
            <?= number_format($stats['candidates']) ?>
        </strong>

        <span>Candidate Pool</span>

        <small>Click to view</small>

    </article>


    <!-- Complete Profiles -->

    <article
        class="rc-stat-card--interactive"
        role="button"
        tabindex="0"
        data-rc-modal="complete"
    >

        <i class="fas fa-circle-check"></i>

        <strong>
            <?= number_format($stats['complete']) ?>
        </strong>

        <span>Complete Profiles</span>

        <small>Click to view</small>

    </article>


    <!-- Consent Eligible -->

    <article
        class="rc-stat-card--interactive"
        role="button"
        tabindex="0"
        data-rc-modal="consented"
    >

        <i class="fas fa-user-shield"></i>

        <strong>
            <?= number_format($stats['consented']) ?>
        </strong>

        <span>Consent Eligible</span>

        <small>Click to view</small>

    </article>


    <!-- Verified Skills -->

    <article
        class="rc-stat-card--interactive"
        role="button"
        tabindex="0"
        data-rc-modal="verifiedSkills"
    >

        <i class="fas fa-certificate"></i>

        <strong>
            <?= number_format($stats['verifiedSkills']) ?>
        </strong>

        <span>Verified Skill Profiles</span>

        <small>Click to view</small>

    </article>


    <!-- Gauteng -->

    <article
        class="rc-stat-card--interactive"
        role="button"
        tabindex="0"
        data-rc-modal="gauteng"
    >

        <i class="fas fa-location-dot"></i>

        <strong>
            <?= number_format($stats['gauteng']) ?>
        </strong>

        <span>Gauteng Candidates</span>

        <small>Click to view</small>

    </article>


    <!-- Opportunities -->

    <article
        class="rc-stat-card--interactive"
        role="button"
        tabindex="0"
        data-rc-modal="opportunities"
    >

        <i class="fas fa-briefcase"></i>

        <strong>
            <?= number_format($stats['opportunities']) ?>
        </strong>

        <span>Published Opportunities</span>

        <small>Click to view</small>

    </article>


    <!-- Applications -->

    <article
        class="rc-stat-card--interactive"
        role="button"
        tabindex="0"
        data-rc-modal="applications"
    >

        <i class="fas fa-file-lines"></i>

        <strong>
            <?= number_format($stats['applications']) ?>
        </strong>

        <span>Applications</span>

        <small>Click to view</small>

    </article>


    <!-- Shortlists -->

    <article
        class="rc-stat-card--interactive"
        role="button"
        tabindex="0"
        data-rc-modal="shortlists"
    >

        <i class="fas fa-list-check"></i>

        <strong>
            <?= number_format($stats['shortlists']) ?>
        </strong>

        <span>My Shortlists</span>

        <small>Click to view</small>

    </article>

</section>


<!-- ================================================================
     QUICK ACTIONS
================================================================ -->

<div class="rc-grid">

    <a
        class="rc-action"
        href="<?= url('recruiter/search.php') ?>"
    >

        <i class="fas fa-users"></i>

        <div>

            <strong>Candidate Pool</strong>

            <span>
                <?= number_format($stats['candidates']) ?>
                active candidates
            </span>

        </div>

    </a>


    <a
        class="rc-action"
        href="<?= url(
            'recruiter/search.php?skill=Java&province=Gauteng'
        ) ?>"
    >

        <i class="fab fa-java"></i>

        <div>

            <strong>Java in Gauteng</strong>

            <span>
                Search Java candidates in Gauteng
            </span>

        </div>

    </a>


    <a
        class="rc-action"
        href="<?= url(
            'recruiter/search.php?skill=AWS&province=Gauteng'
        ) ?>"
    >

        <i class="fas fa-cloud"></i>

        <div>

            <strong>Cloud Talent</strong>

            <span>
                Search cloud candidates
            </span>

        </div>

    </a>


    <a
        class="rc-action"
        href="<?= url('recruiter/shortlists.php') ?>"
    >

        <i class="fas fa-list-check"></i>

        <div>

            <strong>Client Shortlists</strong>

            <span>
                Open stored search contexts
            </span>

        </div>

    </a>

</div>


<!-- ================================================================
     PRIVACY / CONSENT
================================================================ -->

<div class="rc-card">

    <div class="rc-card__body rc-info">

        <i class="fas fa-shield-halved"></i>

        <div>

            <strong>
                Candidate discovery respects profile consent.
            </strong>

            <p>
                Candidate accounts can appear in aggregate
                recruitment statistics, while detailed recruiter
                profile access remains restricted to candidates
                with valid recruiter-discovery consent.
            </p>

        </div>

    </div>

</div>


<!-- ================================================================
     DASHBOARD MODAL
================================================================ -->

<div
    class="rc-dashboard-modal"
    id="rcDashboardModal"
    aria-hidden="true"
>

    <div
        class="rc-dashboard-modal__backdrop"
        data-rc-modal-close
    ></div>


    <div
        class="rc-dashboard-modal__dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="rcDashboardModalTitle"
    >

        <!-- Header -->

        <div class="rc-dashboard-modal__header">

            <div>

                <span class="rc-eyebrow">
                    Recruiter Dashboard
                </span>

                <h3 id="rcDashboardModalTitle">
                    Details
                </h3>

                <p id="rcDashboardModalDescription">
                    Dashboard information.
                </p>

            </div>


            <button
                type="button"
                class="rc-dashboard-modal__close"
                data-rc-modal-close
                aria-label="Close"
            >
                <i class="fas fa-xmark"></i>
            </button>

        </div>


        <!-- Body -->

        <div class="rc-dashboard-modal__body">


            <!-- ====================================================
                 CANDIDATE POOL
            ===================================================== -->

            <section
                class="rc-modal-panel"
                data-rc-panel="candidates"
            >

                <?php if (!$modalData['candidates']): ?>

                    <div class="rc-modal-empty">

                        <i class="fas fa-users"></i>

                        <strong>
                            No candidates
                        </strong>

                        <span>
                            No active Candidate accounts are
                            currently available.
                        </span>

                    </div>

                <?php else: ?>

                    <?php foreach (
                        $modalData['candidates']
                        as $candidate
                    ): ?>

                        <?php

                        $candidateName = trim(
                            (string)$candidate['first_name']
                            . ' '
                            . (string)$candidate['last_name']
                        );

                        $candidateTitle = trim(
                            (string)(
                                $candidate['profile_title']
                                ?: $candidate['professional_title']
                                ?: 'Candidate'
                            )
                        );

                        $location = trim(
                            (string)($candidate['city'] ?? '')
                            . ', '
                            . (string)($candidate['province'] ?? ''),
                            ' ,'
                        );

                        ?>

                        <div class="rc-modal-row">

                            <div class="rc-modal-row__icon">
                                <i class="fas fa-user"></i>
                            </div>

                            <div class="rc-modal-row__content">

                                <strong>
                                    <?= e(
                                        $candidateName
                                        ?: 'Candidate'
                                    ) ?>
                                </strong>

                                <span>
                                    <?= e($candidateTitle) ?>
                                </span>

                                <small>
                                    <?= e(
                                        $location
                                        ?: 'Location not provided'
                                    ) ?>
                                </small>

                            </div>


                            <a
                                class="rc-modal-row__action"
                                href="<?= url(
                                    'recruiter/search.php'
                                ) ?>"
                                title="Open Candidate Search"
                            >
                                <i class="fas fa-arrow-right"></i>
                            </a>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </section>


            <!-- ====================================================
                 COMPLETE PROFILES
            ===================================================== -->

            <section
                class="rc-modal-panel"
                data-rc-panel="complete"
            >

                <?php if (!$modalData['complete']): ?>

                    <div class="rc-modal-empty">

                        <i class="fas fa-circle-check"></i>

                        <strong>
                            No complete profiles
                        </strong>

                        <span>
                            No active candidate profile has
                            reached 100% completion.
                        </span>

                    </div>

                <?php else: ?>

                    <?php foreach (
                        $modalData['complete']
                        as $candidate
                    ): ?>

                        <div class="rc-modal-row">

                            <div class="rc-modal-row__icon">
                                <i class="fas fa-circle-check"></i>
                            </div>

                            <div class="rc-modal-row__content">

                                <strong>
                                    <?= e(
                                        trim(
                                            (string)$candidate['first_name']
                                            . ' '
                                            . (string)$candidate['last_name']
                                        )
                                    ) ?>
                                </strong>

                                <span>
                                    Complete Candidate Profile
                                </span>

                                <small>
                                    <?= number_format(
                                        (int)$candidate[
                                            'completion_percent'
                                        ]
                                    ) ?>% complete
                                </small>

                            </div>


                            <a
                                class="rc-modal-row__action"
                                href="<?= url(
                                    'recruiter/search.php?profile_status=complete'
                                ) ?>"
                            >
                                <i class="fas fa-arrow-right"></i>
                            </a>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </section>


            <!-- ====================================================
                 CONSENT ELIGIBLE
            ===================================================== -->

            <section
                class="rc-modal-panel"
                data-rc-panel="consented"
            >

                <?php if (!$modalData['consented']): ?>

                    <div class="rc-modal-empty">

                        <i class="fas fa-user-shield"></i>

                        <strong>
                            No consent-eligible candidates
                        </strong>

                        <span>
                            No active Candidate currently has
                            valid recruiter-discovery consent.
                        </span>

                    </div>

                <?php else: ?>

                    <?php foreach (
                        $modalData['consented']
                        as $candidate
                    ): ?>

                        <div class="rc-modal-row">

                            <div class="rc-modal-row__icon">
                                <i class="fas fa-user-shield"></i>
                            </div>

                            <div class="rc-modal-row__content">

                                <strong>
                                    <?= e(
                                        trim(
                                            (string)$candidate['first_name']
                                            . ' '
                                            . (string)$candidate['last_name']
                                        )
                                    ) ?>
                                </strong>

                                <span>
                                    Recruiter discovery consent
                                </span>

                                <small>
                                    Granted:
                                    <?= e(
                                        rc_date(
                                            $candidate['granted_at']
                                            ?? null
                                        )
                                    ) ?>
                                </small>

                            </div>


                            <a
                                class="rc-modal-row__action"
                                href="<?= url(
                                    'recruiter/candidate_view.php?id='
                                    . (int)$candidate['id']
                                ) ?>"
                                title="View Candidate"
                            >
                                <i class="fas fa-arrow-right"></i>
                            </a>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </section>


            <!-- ====================================================
                 VERIFIED SKILLS
            ===================================================== -->

            <section
                class="rc-modal-panel"
                data-rc-panel="verifiedSkills"
            >

                <?php if (!$modalData['verifiedSkills']): ?>

                    <div class="rc-modal-empty">

                        <i class="fas fa-certificate"></i>

                        <strong>
                            No verified skill profiles
                        </strong>

                        <span>
                            No active Candidate currently has
                            a verified skill.
                        </span>

                    </div>

                <?php else: ?>

                    <?php foreach (
                        $modalData['verifiedSkills']
                        as $candidate
                    ): ?>

                        <div class="rc-modal-row">

                            <div class="rc-modal-row__icon">
                                <i class="fas fa-certificate"></i>
                            </div>

                            <div class="rc-modal-row__content">

                                <strong>
                                    <?= e(
                                        trim(
                                            (string)$candidate['first_name']
                                            . ' '
                                            . (string)$candidate['last_name']
                                        )
                                    ) ?>
                                </strong>

                                <span>
                                    <?= number_format(
                                        (int)$candidate[
                                            'verified_skill_count'
                                        ]
                                    ) ?>
                                    verified
                                    <?= (int)$candidate[
                                        'verified_skill_count'
                                    ] === 1
                                        ? 'skill'
                                        : 'skills' ?>
                                </span>

                                <small>
                                    <?= e(
                                        (string)(
                                            $candidate[
                                                'verified_skills'
                                            ]
                                            ?: 'No skill names available'
                                        )
                                    ) ?>
                                </small>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </section>


            <!-- ====================================================
                 GAUTENG CANDIDATES
            ===================================================== -->

            <section
                class="rc-modal-panel"
                data-rc-panel="gauteng"
            >

                <?php if (!$modalData['gauteng']): ?>

                    <div class="rc-modal-empty">

                        <i class="fas fa-location-dot"></i>

                        <strong>
                            No Gauteng candidates
                        </strong>

                        <span>
                            No active Candidate account is
                            currently registered in Gauteng.
                        </span>

                    </div>

                <?php else: ?>

                    <?php foreach (
                        $modalData['gauteng']
                        as $candidate
                    ): ?>

                        <div class="rc-modal-row">

                            <div class="rc-modal-row__icon">
                                <i class="fas fa-location-dot"></i>
                            </div>

                            <div class="rc-modal-row__content">

                                <strong>
                                    <?= e(
                                        trim(
                                            (string)$candidate['first_name']
                                            . ' '
                                            . (string)$candidate['last_name']
                                        )
                                    ) ?>
                                </strong>

                                <span>
                                    <?= e(
                                        (string)(
                                            $candidate['profile_title']
                                            ?: $candidate[
                                                'professional_title'
                                            ]
                                            ?: 'Candidate'
                                        )
                                    ) ?>
                                </span>

                                <small>
                                    <?= e(
                                        trim(
                                            (string)($candidate['city'] ?? '')
                                            . ', '
                                            . (string)$candidate['province'],
                                            ' ,'
                                        )
                                        ?: 'Gauteng'
                                    ) ?>
                                </small>

                            </div>


                            <a
                                class="rc-modal-row__action"
                                href="<?= url(
                                    'recruiter/search.php?province=Gauteng'
                                ) ?>"
                            >
                                <i class="fas fa-arrow-right"></i>
                            </a>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </section>


            <!-- ====================================================
                 OPPORTUNITIES
            ===================================================== -->

            <section
                class="rc-modal-panel"
                data-rc-panel="opportunities"
            >

                <?php if (!$modalData['opportunities']): ?>

                    <div class="rc-modal-empty">

                        <i class="fas fa-briefcase"></i>

                        <strong>
                            No published opportunities
                        </strong>

                        <span>
                            No opportunities are currently
                            marked as published.
                        </span>

                    </div>

                <?php else: ?>

                    <?php foreach (
                        $modalData['opportunities']
                        as $opportunity
                    ): ?>

                        <?php

                        $opportunityLocation = trim(
                            (string)($opportunity['city'] ?? '')
                            . ', '
                            . (string)($opportunity['province'] ?? ''),
                            ' ,'
                        );

                        ?>

                        <div class="rc-modal-row">

                            <div class="rc-modal-row__icon">
                                <i class="fas fa-briefcase"></i>
                            </div>

                            <div class="rc-modal-row__content">

                                <strong>
                                    <?= e(
                                        (string)$opportunity['title']
                                    ) ?>
                                </strong>

                                <span>
                                    <?= e(
                                        (string)(
                                            $opportunity['organisation']
                                            ?: 'Organisation not specified'
                                        )
                                    ) ?>
                                </span>

                                <small>

                                    <?= number_format(
                                        (int)(
                                            $opportunity[
                                                'available_positions'
                                            ]
                                            ?? 0
                                        )
                                    ) ?>
                                    positions

                                    <?php if (
                                        $opportunityLocation !== ''
                                    ): ?>

                                        ·
                                        <?= e($opportunityLocation) ?>

                                    <?php endif; ?>

                                </small>

                                <span class="rc-modal-badge">
                                    <?= e(
                                        rc_label(
                                            $opportunity['status']
                                        )
                                    ) ?>
                                </span>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </section>


            <!-- ====================================================
                 APPLICATIONS
            ===================================================== -->

            <section
                class="rc-modal-panel"
                data-rc-panel="applications"
            >

                <?php if (!$modalData['applications']): ?>

                    <div class="rc-modal-empty">

                        <i class="fas fa-file-lines"></i>

                        <strong>
                            No submitted applications
                        </strong>

                        <span>
                            No non-draft applications are
                            currently recorded.
                        </span>

                    </div>

                <?php else: ?>

                    <?php foreach (
                        $modalData['applications']
                        as $application
                    ): ?>

                        <?php

                        $applicationCandidate = trim(
                            (string)(
                                $application['first_name']
                                ?? ''
                            )
                            . ' '
                            . (string)(
                                $application['last_name']
                                ?? ''
                            )
                        );

                        ?>

                        <div class="rc-modal-row">

                            <div class="rc-modal-row__icon">
                                <i class="fas fa-file-lines"></i>
                            </div>

                            <div class="rc-modal-row__content">

                                <strong>
                                    <?= e(
                                        $applicationCandidate
                                        ?: 'Candidate'
                                    ) ?>
                                </strong>

                                <span>
                                    <?= e(
                                        (string)(
                                            $application[
                                                'opportunity_title'
                                            ]
                                            ?: 'Opportunity'
                                        )
                                    ) ?>
                                </span>

                                <small>

                                    <?php if (
                                        !empty(
                                            $application[
                                                'application_reference'
                                            ]
                                        )
                                    ): ?>

                                        <?= e(
                                            $application[
                                                'application_reference'
                                            ]
                                        ) ?>

                                        ·

                                    <?php endif; ?>

                                    <?= e(
                                        rc_label(
                                            $application['status']
                                        )
                                    ) ?>

                                </small>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </section>


            <!-- ====================================================
                 SHORTLISTS
            ===================================================== -->

            <section
                class="rc-modal-panel"
                data-rc-panel="shortlists"
            >

                <?php if (!$modalData['shortlists']): ?>

                    <div class="rc-modal-empty">

                        <i class="fas fa-list-check"></i>

                        <strong>
                            No shortlists
                        </strong>

                        <span>
                            You have not created a Recruiter
                            shortlist yet.
                        </span>

                    </div>

                <?php else: ?>

                    <?php foreach (
                        $modalData['shortlists']
                        as $shortlist
                    ): ?>

                        <div class="rc-modal-row">

                            <div class="rc-modal-row__icon">
                                <i class="fas fa-list-check"></i>
                            </div>

                            <div class="rc-modal-row__content">

                                <strong>
                                    <?= e(
                                        (string)$shortlist['name']
                                    ) ?>
                                </strong>

                                <span>
                                    <?= e(
                                        (string)(
                                            $shortlist['client_name']
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
                                            $shortlist['status']
                                        )
                                    ) ?>
                                </small>

                            </div>


                            <a
                                class="rc-modal-row__action"
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


        </div>


        <!-- Footer -->

        <div class="rc-dashboard-modal__footer">

            <button
                type="button"
                class="rc-btn rc-btn--secondary"
                data-rc-modal-close
            >
                <i class="fas fa-xmark"></i>
                Close
            </button>

        </div>

    </div>

</div>


<!-- ================================================================
     MODAL JAVASCRIPT
================================================================ -->

<script>

document.addEventListener('DOMContentLoaded', function () {

    const modal =
        document.getElementById('rcDashboardModal');

    if (!modal) {
        return;
    }

    const cards =
        document.querySelectorAll(
            '[data-rc-modal]'
        );

    const panels =
        modal.querySelectorAll(
            '[data-rc-panel]'
        );

    const closeButtons =
        modal.querySelectorAll(
            '[data-rc-modal-close]'
        );

    const title =
        document.getElementById(
            'rcDashboardModalTitle'
        );

    const description =
        document.getElementById(
            'rcDashboardModalDescription'
        );

    let lastTrigger = null;


    /*
    |--------------------------------------------------------------------------
    | Modal Configuration
    |--------------------------------------------------------------------------
    */

    const config = {

        candidates: {
            title: 'Candidate Pool',
            description:
                'Active Candidate accounts available to recruitment.'
        },

        complete: {
            title: 'Complete Profiles',
            description:
                'Candidate profiles that have reached 100% completion.'
        },

        consented: {
            title: 'Consent Eligible Candidates',
            description:
                'Candidates with valid recruiter-discovery consent.'
        },

        verifiedSkills: {
            title: 'Verified Skill Profiles',
            description:
                'Active candidates with one or more verified skills.'
        },

        gauteng: {
            title: 'Gauteng Candidates',
            description:
                'Active Candidate accounts registered in Gauteng.'
        },

        opportunities: {
            title: 'Published Opportunities',
            description:
                'Recruitment opportunities currently marked as published.'
        },

        applications: {
            title: 'Applications',
            description:
                'Submitted applications currently recorded in the platform.'
        },

        shortlists: {
            title: 'My Shortlists',
            description:
                'Shortlists owned by your Recruiter account.'
        }

    };


    /*
    |--------------------------------------------------------------------------
    | Open Modal
    |--------------------------------------------------------------------------
    */

    function openModal(type, trigger) {

        const selected = config[type];

        if (!selected) {
            return;
        }

        lastTrigger = trigger || null;

        panels.forEach(function (panel) {

            panel.classList.toggle(
                'is-active',
                panel.dataset.rcPanel === type
            );

        });

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
            'rc-modal-open'
        );

        const closeButton =
            modal.querySelector(
                '.rc-dashboard-modal__close'
            );

        if (closeButton) {
            closeButton.focus();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Close Modal
    |--------------------------------------------------------------------------
    */

    function closeModal() {

        modal.classList.remove(
            'is-open'
        );

        modal.setAttribute(
            'aria-hidden',
            'true'
        );

        document.body.classList.remove(
            'rc-modal-open'
        );

        panels.forEach(function (panel) {
            panel.classList.remove(
                'is-active'
            );
        });

        if (lastTrigger) {
            lastTrigger.focus();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Card Click
    |--------------------------------------------------------------------------
    */

    cards.forEach(function (card) {

        card.addEventListener(
            'click',
            function () {

                openModal(
                    card.dataset.rcModal,
                    card
                );

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Keyboard Accessibility
        |--------------------------------------------------------------------------
        */

        card.addEventListener(
            'keydown',
            function (event) {

                if (
                    event.key === 'Enter' ||
                    event.key === ' '
                ) {

                    event.preventDefault();

                    openModal(
                        card.dataset.rcModal,
                        card
                    );
                }

            }
        );

    });


    /*
    |--------------------------------------------------------------------------
    | Close Buttons / Backdrop
    |--------------------------------------------------------------------------
    */

    closeButtons.forEach(
        function (button) {

            button.addEventListener(
                'click',
                closeModal
            );

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Escape
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'keydown',
        function (event) {

            if (
                event.key === 'Escape' &&
                modal.classList.contains(
                    'is-open'
                )
            ) {
                closeModal();
            }

        }
    );

});

</script>


<?php

require __DIR__ . '/_layout_end.php';

?>