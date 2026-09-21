<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('recruiter');

$user        = current_user();
$flashes     = render_flashes();
$currentPage = 'search';
$pageTitle   = 'Find Candidates';

require_once __DIR__ . '/_helpers.php';

$conn = Database::getConnection();

$recruiterId = (int)($user['id'] ?? $user['user_id'] ?? 0);

if ($recruiterId <= 0) {
    http_response_code(403);
    exit('Invalid Recruiter account.');
}

/*
|--------------------------------------------------------------------------
| Search Filters
|--------------------------------------------------------------------------
*/

$skill         = trim((string)($_GET['skill'] ?? ''));
$province      = trim((string)($_GET['province'] ?? ''));
$city          = trim((string)($_GET['city'] ?? ''));
$profileStatus = trim((string)($_GET['profile_status'] ?? ''));

$allowedProfileStatuses = [
    '',
    'complete',
    'incomplete',
    'active'
];

if (!in_array($profileStatus, $allowedProfileStatuses, true)) {
    $profileStatus = '';
}

$taxonomy = rc_taxonomy_version($conn);

$rows = [];

/*
|--------------------------------------------------------------------------
| Table Availability
|--------------------------------------------------------------------------
*/

$hasProfiles = rc_has_table(
    $conn,
    'candidate_profiles'
);

$hasConsents = rc_has_table(
    $conn,
    'candidate_consents'
);

$hasSkills =
    rc_has_table($conn, 'candidate_skills') &&
    rc_has_table($conn, 'skills');

/*
|--------------------------------------------------------------------------
| Candidate Scope
|--------------------------------------------------------------------------
|
| Candidate accounts are stored in users.
|
| We do NOT require:
| - an application
| - a candidate_profiles row
| - candidate consent
| - candidate skills
|
| to recognise someone as a Candidate.
|
*/

$where = [
    "r.slug = 'candidate'",
    "u.status = 'active'"
];

$params = [];
$types  = '';

/*
|--------------------------------------------------------------------------
| Province Filter
|--------------------------------------------------------------------------
|
| Province is stored in users.
|
*/

if ($province !== '') {

    $where[] = "u.province = ?";

    $params[] = $province;
    $types   .= 's';
}

/*
|--------------------------------------------------------------------------
| City Filter
|--------------------------------------------------------------------------
|
| City is stored in candidate_profiles.
|
*/

if ($city !== '') {

    if ($hasProfiles) {

        $where[] = "cp.city = ?";

        $params[] = $city;
        $types   .= 's';

    } else {

        $where[] = "1 = 0";
    }
}

/*
|--------------------------------------------------------------------------
| Profile Status Filter
|--------------------------------------------------------------------------
*/

if ($profileStatus === 'complete') {

    if ($hasProfiles) {

        $where[] = "
            cp.id IS NOT NULL
            AND cp.completion_percent = 100
        ";

    } else {

        $where[] = "1 = 0";
    }

} elseif ($profileStatus === 'incomplete') {

    if ($hasProfiles) {

        $where[] = "
            (
                cp.id IS NULL
                OR cp.completion_percent < 100
            )
        ";

    }

} elseif ($profileStatus === 'active') {

    if ($hasProfiles) {

        $where[] = "
            cp.id IS NOT NULL
            AND cp.is_active = 1
        ";

    } else {

        $where[] = "1 = 0";
    }
}

/*
|--------------------------------------------------------------------------
| Skill Filter
|--------------------------------------------------------------------------
|
| Confirmed schema:
|
| candidate_skills.user_id
| candidate_skills.skill_id
| candidate_skills.proficiency
| candidate_skills.verification_status
|
| skills.id
| skills.name
| skills.category
| skills.description
| skills.is_active
|
*/

if ($skill !== '') {

    if ($hasSkills) {

        $where[] = "
            EXISTS (
                SELECT 1

                FROM candidate_skills cs_search

                INNER JOIN skills s_search
                    ON s_search.id = cs_search.skill_id

                WHERE cs_search.user_id = u.id
                  AND s_search.is_active = 1
                  AND s_search.name LIKE ?
            )
        ";

        $params[] = '%' . $skill . '%';
        $types   .= 's';

    } else {

        $where[] = "1 = 0";
    }
}

/*
|--------------------------------------------------------------------------
| Candidate Profile SELECT / JOIN
|--------------------------------------------------------------------------
*/

if ($hasProfiles) {

    $profileSelect = "
        cp.id AS profile_id,
        cp.professional_title AS profile_professional_title,
        cp.professional_summary,
        cp.career_interests,
        cp.employment_status AS profile_employment_status,
        cp.availability_status_id,
        cp.availability_date,
        cp.address,
        cp.city,
        cp.profile_picture AS candidate_profile_picture,
        cp.completion_percent,
        cp.is_active AS profile_is_active,
        cp.created_at AS profile_created_at,
        cp.updated_at AS profile_updated_at
    ";

    $profileJoin = "
        LEFT JOIN candidate_profiles cp
            ON cp.user_id = u.id
    ";

} else {

    $profileSelect = "
        NULL AS profile_id,
        NULL AS profile_professional_title,
        NULL AS professional_summary,
        NULL AS career_interests,
        NULL AS profile_employment_status,
        NULL AS availability_status_id,
        NULL AS availability_date,
        NULL AS address,
        NULL AS city,
        NULL AS candidate_profile_picture,
        0 AS completion_percent,
        0 AS profile_is_active,
        NULL AS profile_created_at,
        NULL AS profile_updated_at
    ";

    $profileJoin = "";
}

/*
|--------------------------------------------------------------------------
| Recruiter Consent SELECT / JOIN
|--------------------------------------------------------------------------
*/

if ($hasConsents) {

    $consentSelect = "
        MAX(
            CASE
                WHEN cc.consent_type = 'recruiter_discovery'
                 AND cc.status = 'consented'
                 AND (
                        cc.expires_at IS NULL
                        OR cc.expires_at > NOW()
                     )
                THEN 1
                ELSE 0
            END
        ) AS recruiter_consent
    ";

    $consentJoin = "
        LEFT JOIN candidate_consents cc
            ON cc.candidate_id = u.id
    ";

} else {

    $consentSelect = "
        0 AS recruiter_consent
    ";

    $consentJoin = "";
}

/*
|--------------------------------------------------------------------------
| Skills SELECT / JOIN
|--------------------------------------------------------------------------
*/

if ($hasSkills) {

    $skillsSelect = "
        GROUP_CONCAT(
            DISTINCT CASE
                WHEN cs.verification_status = 'verified'
                 AND sk.is_active = 1
                THEN sk.name
            END
            ORDER BY sk.name
            SEPARATOR ', '
        ) AS verified_skills,

        COUNT(
            DISTINCT CASE
                WHEN cs.verification_status = 'verified'
                 AND sk.is_active = 1
                THEN cs.skill_id
            END
        ) AS verified_skill_count,

        COUNT(
            DISTINCT CASE
                WHEN cs.verification_status = 'pending'
                 AND sk.is_active = 1
                THEN cs.skill_id
            END
        ) AS pending_skill_count,

        COUNT(
            DISTINCT CASE
                WHEN cs.verification_status = 'unverified'
                 AND sk.is_active = 1
                THEN cs.skill_id
            END
        ) AS unverified_skill_count
    ";

    $skillsJoin = "
        LEFT JOIN candidate_skills cs
            ON cs.user_id = u.id

        LEFT JOIN skills sk
            ON sk.id = cs.skill_id
    ";

} else {

    $skillsSelect = "
        NULL AS verified_skills,
        0 AS verified_skill_count,
        0 AS pending_skill_count,
        0 AS unverified_skill_count
    ";

    $skillsJoin = "";
}

/*
|--------------------------------------------------------------------------
| GROUP BY
|--------------------------------------------------------------------------
*/

$groupBy = "
    u.id,
    u.first_name,
    u.last_name,
    u.email,
    u.phone,
    u.province,
    u.employment_status,
    u.qualification_level,
    u.professional_title,
    u.profile_picture
";

if ($hasProfiles) {

    $groupBy .= ",
        cp.id,
        cp.professional_title,
        cp.professional_summary,
        cp.career_interests,
        cp.employment_status,
        cp.availability_status_id,
        cp.availability_date,
        cp.address,
        cp.city,
        cp.profile_picture,
        cp.completion_percent,
        cp.is_active,
        cp.created_at,
        cp.updated_at
    ";
}

/*
|--------------------------------------------------------------------------
| ORDER BY
|--------------------------------------------------------------------------
*/

if ($hasProfiles) {

    $orderBy = "
        CASE
            WHEN cp.id IS NULL THEN 1
            ELSE 0
        END,
        cp.completion_percent DESC,
        u.first_name ASC,
        u.last_name ASC
    ";

} else {

    $orderBy = "
        u.first_name ASC,
        u.last_name ASC
    ";
}

/*
|--------------------------------------------------------------------------
| Final Candidate Search
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT

        u.id AS user_id,
        u.first_name,
        u.last_name,
        u.email,
        u.phone,
        u.province AS user_province,
        u.employment_status AS user_employment_status,
        u.qualification_level,
        u.professional_title AS user_professional_title,
        u.profile_picture AS user_profile_picture,

        {$profileSelect},

        {$consentSelect},

        {$skillsSelect}

    FROM users u

    INNER JOIN roles r
        ON r.id = u.role_id

    {$profileJoin}

    {$consentJoin}

    {$skillsJoin}

    WHERE " . implode(' AND ', $where) . "

    GROUP BY {$groupBy}

    ORDER BY {$orderBy}
";

/*
|--------------------------------------------------------------------------
| Prepare
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare($sql);

if (!$stmt) {

    throw new RuntimeException(
        'Candidate search query failed: '
        . $conn->error
    );
}

/*
|--------------------------------------------------------------------------
| Bind Filters
|--------------------------------------------------------------------------
*/

if ($types !== '') {

    $stmt->bind_param(
        $types,
        ...$params
    );
}

/*
|--------------------------------------------------------------------------
| Execute
|--------------------------------------------------------------------------
*/

$stmt->execute();

$result = $stmt->get_result();

while ($candidate = $result->fetch_assoc()) {

    $rows[] = $candidate;
}

$stmt->close();

/*
|--------------------------------------------------------------------------
| Audit Search
|--------------------------------------------------------------------------
*/

rc_activity(
    $conn,
    $recruiterId,
    'candidate_search',
    'Performed candidate talent search.',
    'candidate_search',
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

.rc-search-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 12px;
}

.rc-search-meta span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    background: rgba(127, 127, 127, .10);
}

.rc-profile-progress {
    margin-top: 18px;
}

.rc-profile-progress__header {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 7px;
    font-size: 12px;
}

.rc-profile-progress__track {
    width: 100%;
    height: 7px;
    overflow: hidden;
    border-radius: 999px;
    background: rgba(127, 127, 127, .15);
}

.rc-profile-progress__fill {
    height: 100%;
    border-radius: inherit;
    background: currentColor;
}

.rc-result-summary {
    margin-bottom: 18px;
}

.rc-result-summary p {
    margin: 6px 0 0;
    line-height: 1.65;
}

.rc-consent-ok {
    font-weight: 700;
}

.rc-consent-locked {
    font-weight: 700;
    opacity: .75;
}

.rc-profile-complete {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.rc-skills-empty {
    opacity: .65;
    font-size: 13px;
}

@media (max-width: 768px) {

    .rc-match__foot {
        flex-direction: column;
        align-items: flex-start;
        gap: 14px;
    }

    .rc-match__foot > div {
        width: 100%;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .rc-match__foot .rc-btn {
        flex: 1;
        justify-content: center;
    }
}

</style>


<!-- ================================================================
     PAGE HEADER
================================================================ -->

<div class="rc-page-header">

    <span class="rc-eyebrow">
        Structured Talent Search
    </span>

    <h2>
        Find Candidates
    </h2>

    <p>
        Search active candidate accounts using their skills,
        province, city and profile information.
    </p>

</div>


<!-- ================================================================
     SEARCH FORM
================================================================ -->

<form
    class="rc-search"
    method="get"
>

    <!-- Skill -->

    <div class="rc-field rc-grow">

        <label>
            Skill / Technology
        </label>

        <input
            type="text"
            name="skill"
            value="<?= e($skill) ?>"
            placeholder="PHP, JavaScript, Python, Java..."
        >

    </div>


    <!-- Province -->

    <div class="rc-field">

        <label>
            Province
        </label>

        <select name="province">

            <option value="">
                Any province
            </option>

            <?php

            $provinces = [
                'Gauteng',
                'Western Cape',
                'KwaZulu-Natal',
                'Eastern Cape',
                'Free State',
                'Limpopo',
                'Mpumalanga',
                'North West',
                'Northern Cape'
            ];

            foreach ($provinces as $value):

            ?>

                <option
                    value="<?= e($value) ?>"
                    <?= strcasecmp(
                        $province,
                        $value
                    ) === 0
                        ? 'selected'
                        : '' ?>
                >
                    <?= e($value) ?>
                </option>

            <?php endforeach; ?>

        </select>

    </div>


    <!-- City -->

    <div class="rc-field">

        <label>
            City
        </label>

        <input
            type="text"
            name="city"
            value="<?= e($city) ?>"
            placeholder="Any city"
        >

    </div>


    <!-- Profile Status -->

    <div class="rc-field">

        <label>
            Profile
        </label>

        <select name="profile_status">

            <option
                value=""
                <?= $profileStatus === ''
                    ? 'selected'
                    : '' ?>
            >
                Any profile
            </option>

            <option
                value="active"
                <?= $profileStatus === 'active'
                    ? 'selected'
                    : '' ?>
            >
                Active Profile
            </option>

            <option
                value="complete"
                <?= $profileStatus === 'complete'
                    ? 'selected'
                    : '' ?>
            >
                100% Complete
            </option>

            <option
                value="incomplete"
                <?= $profileStatus === 'incomplete'
                    ? 'selected'
                    : '' ?>
            >
                Incomplete
            </option>

        </select>

    </div>


    <!-- Search -->

    <button
        type="submit"
        class="rc-btn rc-btn--primary"
    >

        <i class="fas fa-magnifying-glass"></i>

        Search

    </button>


    <!-- Reset -->

    <a
        class="rc-btn rc-btn--secondary"
        href="<?= url('recruiter/search.php') ?>"
    >

        <i class="fas fa-rotate-left"></i>

        Reset

    </a>

</form>


<!-- ================================================================
     RESULT SUMMARY
================================================================ -->

<div class="rc-summary">

    <div>

        <strong>
            <?= number_format(count($rows)) ?>

            <?= count($rows) === 1
                ? 'candidate'
                : 'candidates' ?>
        </strong>

        <span>
            Taxonomy <?= e($taxonomy) ?>
        </span>

    </div>


    <div>

        <?php if ($skill !== ''): ?>

            <b>
                <?= e($skill) ?>
            </b>

        <?php endif; ?>


        <?php if ($province !== ''): ?>

            <b>
                <?= e($province) ?>
            </b>

        <?php endif; ?>


        <?php if ($city !== ''): ?>

            <b>
                <?= e($city) ?>
            </b>

        <?php endif; ?>


        <?php if ($profileStatus !== ''): ?>

            <b>
                <?= e(
                    rc_label(
                        $profileStatus
                    )
                ) ?>
            </b>

        <?php endif; ?>

    </div>

</div>


<!-- ================================================================
     EMPTY
================================================================ -->

<?php if (!$rows): ?>

<div class="rc-card">

    <div class="rc-empty">

        <i class="fas fa-users-viewfinder"></i>

        <strong>
            No candidates found
        </strong>

        <span>
            No active Candidate account matches the
            selected search criteria.
        </span>

    </div>

</div>


<?php else: ?>


<!-- ================================================================
     CANDIDATE RESULTS
================================================================ -->

<div class="rc-results">

<?php foreach ($rows as $candidate): ?>

<?php

/*
|--------------------------------------------------------------------------
| Candidate Name
|--------------------------------------------------------------------------
*/

$firstName = trim(
    (string)($candidate['first_name'] ?? '')
);

$lastName = trim(
    (string)($candidate['last_name'] ?? '')
);

$name = trim(
    $firstName . ' ' . $lastName
);

if ($name === '') {
    $name = 'Candidate';
}


/*
|--------------------------------------------------------------------------
| Professional Title
|--------------------------------------------------------------------------
*/

$professionalTitle = trim(
    (string)(
        $candidate['profile_professional_title']
        ?? ''
    )
);

if ($professionalTitle === '') {

    $professionalTitle = trim(
        (string)(
            $candidate['user_professional_title']
            ?? ''
        )
    );
}

if ($professionalTitle === '') {
    $professionalTitle = 'Candidate';
}


/*
|--------------------------------------------------------------------------
| Employment Status
|--------------------------------------------------------------------------
*/

$employmentStatus = trim(
    (string)(
        $candidate['profile_employment_status']
        ?? ''
    )
);

if ($employmentStatus === '') {

    $employmentStatus = trim(
        (string)(
            $candidate['user_employment_status']
            ?? ''
        )
    );
}


/*
|--------------------------------------------------------------------------
| Location
|--------------------------------------------------------------------------
*/

$cityValue = trim(
    (string)($candidate['city'] ?? '')
);

$provinceValue = trim(
    (string)(
        $candidate['user_province']
        ?? ''
    )
);

$locationParts = [];

if ($cityValue !== '') {
    $locationParts[] = $cityValue;
}

if ($provinceValue !== '') {
    $locationParts[] = $provinceValue;
}

$location = $locationParts
    ? implode(', ', $locationParts)
    : 'Location not provided';


/*
|--------------------------------------------------------------------------
| Profile
|--------------------------------------------------------------------------
*/

$hasProfile =
    !empty($candidate['profile_id']);

$profileActive =
    (int)(
        $candidate['profile_is_active']
        ?? 0
    ) === 1;

$completion = max(
    0,
    min(
        100,
        (int)(
            $candidate['completion_percent']
            ?? 0
        )
    )
);


/*
|--------------------------------------------------------------------------
| Consent
|--------------------------------------------------------------------------
*/

$hasConsent =
    (int)(
        $candidate['recruiter_consent']
        ?? 0
    ) === 1;


/*
|--------------------------------------------------------------------------
| Skills
|--------------------------------------------------------------------------
*/

$skillsText = trim(
    (string)(
        $candidate['verified_skills']
        ?? ''
    )
);

$candidateSkills = [];

if ($skillsText !== '') {

    $candidateSkills = array_filter(
        array_map(
            'trim',
            explode(',', $skillsText)
        )
    );
}

$verifiedSkillCount = (int)(
    $candidate['verified_skill_count']
    ?? 0
);

$pendingSkillCount = (int)(
    $candidate['pending_skill_count']
    ?? 0
);

$unverifiedSkillCount = (int)(
    $candidate['unverified_skill_count']
    ?? 0
);


/*
|--------------------------------------------------------------------------
| Summary
|--------------------------------------------------------------------------
*/

$professionalSummary = trim(
    (string)(
        $candidate['professional_summary']
        ?? ''
    )
);

?>

<article class="rc-match">


    <!-- ============================================================
         HEADER
    ============================================================= -->

    <div class="rc-match__head">

        <div class="rc-avatar rc-avatar--large">

            <?= e(
                rc_initials(
                    $firstName,
                    $lastName
                )
            ) ?>

        </div>


        <div class="rc-match__identity">

            <div>

                <strong>
                    <?= e($name) ?>
                </strong>


                <?php if (
                    $hasProfile &&
                    $profileActive
                ): ?>

                    <span
                        class="
                            rc-status
                            rc-status--success
                        "
                    >
                        Active Profile
                    </span>

                <?php elseif ($hasProfile): ?>

                    <span class="rc-status">
                        Profile Inactive
                    </span>

                <?php else: ?>

                    <span class="rc-status">
                        Profile Not Completed
                    </span>

                <?php endif; ?>

            </div>


            <p>
                <?= e($professionalTitle) ?>
            </p>


            <small>

                <i class="fas fa-location-dot"></i>

                <?= e($location) ?>


                <?php if ($employmentStatus !== ''): ?>

                    &nbsp;·&nbsp;

                    <i class="fas fa-briefcase"></i>

                    <?= e(
                        rc_label(
                            $employmentStatus
                        )
                    ) ?>

                <?php endif; ?>

            </small>

        </div>

    </div>


    <!-- ============================================================
         BODY
    ============================================================= -->

    <div class="rc-match__body">


        <?php if ($professionalSummary !== ''): ?>

            <div class="rc-result-summary">

                <span class="rc-label">
                    Professional Summary
                </span>

                <p>
                    <?= e($professionalSummary) ?>
                </p>

            </div>

        <?php endif; ?>


        <!-- Profile Completion -->

        <div class="rc-profile-progress">

            <div class="rc-profile-progress__header">

                <span>
                    Profile Completion
                </span>

                <strong>
                    <?= number_format(
                        $completion
                    ) ?>%
                </strong>

            </div>


            <div class="rc-profile-progress__track">

            <div
                class="rc-profile-progress__fill"
                style="--profile-completion: <?= (int)$completion ?>%;"
            ></div>

            </div>

        </div>


        <!-- Verified Skills -->

        <div style="margin-top:18px;">

            <span class="rc-label">
                Verified Skills
            </span>


            <div class="rc-chips">

                <?php if ($candidateSkills): ?>

                    <?php foreach (
                        $candidateSkills
                        as $candidateSkill
                    ): ?>

                        <b>
                            <?= e(
                                $candidateSkill
                            ) ?>
                        </b>

                    <?php endforeach; ?>


                <?php else: ?>

                    <span class="rc-skills-empty">
                        No verified skills recorded
                    </span>

                <?php endif; ?>

            </div>

        </div>


        <!-- Skill Counts -->

        <div class="rc-search-meta">

            <span>

                <i class="fas fa-circle-check"></i>

                <?= number_format(
                    $verifiedSkillCount
                ) ?>

                verified

            </span>


            <?php if ($pendingSkillCount > 0): ?>

                <span>

                    <i class="fas fa-clock"></i>

                    <?= number_format(
                        $pendingSkillCount
                    ) ?>

                    pending

                </span>

            <?php endif; ?>


            <?php if (
                $unverifiedSkillCount > 0
            ): ?>

                <span>

                    <i class="fas fa-circle-question"></i>

                    <?= number_format(
                        $unverifiedSkillCount
                    ) ?>

                    unverified

                </span>

            <?php endif; ?>


            <?php if (
                !empty(
                    $candidate[
                        'qualification_level'
                    ]
                )
            ): ?>

                <span>

                    <i class="fas fa-graduation-cap"></i>

                    <?= e(
                        rc_label(
                            $candidate[
                                'qualification_level'
                            ]
                        )
                    ) ?>

                </span>

            <?php endif; ?>


            <?php if (
                !empty(
                    $candidate[
                        'availability_date'
                    ]
                )
            ): ?>

                <span>

                    <i class="fas fa-calendar-check"></i>

                    Available

                    <?= e(
                        rc_date(
                            $candidate[
                                'availability_date'
                            ]
                        )
                    ) ?>

                </span>

            <?php endif; ?>

        </div>

    </div>


    <!-- ============================================================
         FOOTER
    ============================================================= -->

    <div class="rc-match__foot">

        <span>

            <?php if ($hasConsent): ?>

                <i class="fas fa-user-shield"></i>

                <span class="rc-consent-ok">
                    Recruiter-discovery consent valid
                </span>

            <?php else: ?>

                <i class="fas fa-lock"></i>

                <span class="rc-consent-locked">
                    Detailed profile access restricted
                </span>

            <?php endif; ?>

        </span>


        <div>

            <?php if ($hasConsent): ?>

                <!-- View Profile -->

                <a
                    class="
                        rc-btn
                        rc-btn--secondary
                        rc-btn--small
                    "
                    href="<?= url(
                        'recruiter/candidate_view.php?id='
                        . (int)$candidate['user_id']
                    ) ?>"
                >

                    <i class="fas fa-user"></i>

                    View Profile

                </a>


                <!-- Shortlist -->

                <a
                    class="
                        rc-btn
                        rc-btn--primary
                        rc-btn--small
                    "
                    href="<?= url(
                        'recruiter/shortlists.php'
                        . '?candidate_id='
                        . (int)$candidate['user_id']
                        . '&skill='
                        . urlencode($skill)
                        . '&province='
                        . urlencode($province)
                        . '&taxonomy_version='
                        . urlencode($taxonomy)
                    ) ?>"
                >

                    <i class="fas fa-plus"></i>

                    Shortlist

                </a>


            <?php else: ?>

                <button
                    type="button"
                    class="
                        rc-btn
                        rc-btn--secondary
                        rc-btn--small
                    "
                    disabled
                >

                    <i class="fas fa-lock"></i>

                    Profile Restricted

                </button>

            <?php endif; ?>

        </div>

    </div>

</article>

<?php endforeach; ?>

</div>

<?php endif; ?>


<?php

require __DIR__ . '/_layout_end.php';

?>