<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('recruiter');

$user        = current_user();
$flashes     = render_flashes();
$currentPage = 'shortlist_view';
$pageTitle   = 'Shortlist Details';

require_once __DIR__ . '/_helpers.php';

$conn = Database::getConnection();

$uid = (int)($user['id'] ?? $user['user_id'] ?? 0);
$sid = (int)($_GET['id'] ?? 0);

if ($uid <= 0) {
    http_response_code(403);
    exit('Invalid recruiter account.');
}

if ($sid <= 0) {
    http_response_code(400);
    exit('Invalid shortlist.');
}

/*
|--------------------------------------------------------------------------
| Load Shortlist
|--------------------------------------------------------------------------
|
| Important:
| A recruiter may only view their own shortlist.
|
*/

$stmt = $conn->prepare("
    SELECT
        id,
        recruiter_id,
        name,
        client_name,
        search_criteria_json,
        taxonomy_version,
        status,
        created_at,
        updated_at
    FROM recruiter_shortlists
    WHERE id = ?
      AND recruiter_id = ?
    LIMIT 1
");

if (!$stmt) {
    throw new RuntimeException(
        'Unable to prepare shortlist query: ' . $conn->error
    );
}

$stmt->bind_param('ii', $sid, $uid);
$stmt->execute();

$sl = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$sl) {
    http_response_code(403);
    exit('Shortlist not found or access denied.');
}

/*
|--------------------------------------------------------------------------
| Available Supporting Tables
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
| Candidate Profile
|--------------------------------------------------------------------------
*/

if ($hasProfiles) {

    $profileSelect = "
        cp.id AS profile_id,
        cp.professional_title AS profile_professional_title,
        cp.professional_summary,
        cp.employment_status AS profile_employment_status,
        cp.city,
        cp.completion_percent,
        cp.is_active AS profile_is_active,
        cp.availability_date
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
        NULL AS profile_employment_status,
        NULL AS city,
        0 AS completion_percent,
        0 AS profile_is_active,
        NULL AS availability_date
    ";

    $profileJoin = "";
}

/*
|--------------------------------------------------------------------------
| Recruiter Consent
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
        ) AS recruiter_consent,

        MAX(
            CASE
                WHEN cc.consent_type = 'recruiter_discovery'
                THEN cc.expires_at
                ELSE NULL
            END
        ) AS consent_expires_at
    ";

    $consentJoin = "
        LEFT JOIN candidate_consents cc
            ON cc.candidate_id = u.id
    ";

} else {

    $consentSelect = "
        0 AS recruiter_consent,
        NULL AS consent_expires_at
    ";

    $consentJoin = "";
}

/*
|--------------------------------------------------------------------------
| Candidate Skills
|--------------------------------------------------------------------------
|
| Confirmed:
|
| candidate_skills.user_id
| candidate_skills.skill_id
| candidate_skills.proficiency
| candidate_skills.verification_status
|
| skills.id
| skills.name
| skills.category
| skills.is_active
|
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
        ) AS verified_skill_count
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
        0 AS verified_skill_count
    ";

    $skillsJoin = "";
}

/*
|--------------------------------------------------------------------------
| GROUP BY
|--------------------------------------------------------------------------
*/

$groupBy = "
    rsc.id,
    rsc.added_at,
    rsc.candidate_id,

    u.id,
    u.first_name,
    u.last_name,
    u.email,
    u.province,
    u.employment_status,
    u.qualification_level,
    u.professional_title,
    u.status
";

if ($hasProfiles) {

    $groupBy .= ",
        cp.id,
        cp.professional_title,
        cp.professional_summary,
        cp.employment_status,
        cp.city,
        cp.completion_percent,
        cp.is_active,
        cp.availability_date
    ";
}

/*
|--------------------------------------------------------------------------
| Load Shortlisted Candidates
|--------------------------------------------------------------------------
|
| Start from recruiter_shortlist_candidates -> users.
|
| We do NOT inner join candidate_profiles because a candidate may have
| been shortlisted before completing their full profile.
|
*/

$sql = "
    SELECT

        rsc.id AS shortlist_candidate_id,
        rsc.added_at,
        rsc.candidate_id AS user_id,

        u.first_name,
        u.last_name,
        u.email,
        u.province AS user_province,
        u.employment_status AS user_employment_status,
        u.qualification_level,
        u.professional_title AS user_professional_title,
        u.status AS user_status,

        {$profileSelect},

        {$consentSelect},

        {$skillsSelect}

    FROM recruiter_shortlist_candidates rsc

    INNER JOIN users u
        ON u.id = rsc.candidate_id

    {$profileJoin}

    {$consentJoin}

    {$skillsJoin}

    WHERE rsc.shortlist_id = ?

    GROUP BY {$groupBy}

    ORDER BY rsc.added_at DESC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    throw new RuntimeException(
        'Unable to prepare shortlisted candidates query: '
        . $conn->error
    );
}

$stmt->bind_param('i', $sid);
$stmt->execute();

$result = $stmt->get_result();

$rows = [];

while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

$stmt->close();

/*
|--------------------------------------------------------------------------
| Stored Search Criteria
|--------------------------------------------------------------------------
*/

$criteria = json_decode(
    (string)($sl['search_criteria_json'] ?? ''),
    true
);

if (!is_array($criteria)) {
    $criteria = [];
}

/*
|--------------------------------------------------------------------------
| Activity Audit
|--------------------------------------------------------------------------
*/

rc_activity(
    $conn,
    $uid,
    'shortlist_view',
    'Viewed recruiter shortlist: ' . (string)$sl['name'],
    'recruiter_shortlist',
    $sid
);

/*
|--------------------------------------------------------------------------
| Layout
|--------------------------------------------------------------------------
*/

require __DIR__ . '/_layout_start.php';

?>


<!-- ================================================================
     SHORTLIST HEADER
================================================================ -->

<div class="rc-detail-hero">

    <span class="rc-eyebrow">
        Client Shortlist
    </span>

    <h2>
        <?= e($sl['name']) ?>
    </h2>

    <p>
        <?= e(
            (string)(
                $sl['client_name']
                    ?: 'No client specified'
            )
        ) ?>
    </p>

    <div class="rc-chips">

        <b>
            Taxonomy
            <?= e($sl['taxonomy_version']) ?>
        </b>

        <b>
            <?= number_format(count($rows)) ?>

            <?= count($rows) === 1
                ? 'candidate'
                : 'candidates' ?>
        </b>

        <b>
            <?= e(
                rc_label(
                    (string)$sl['status']
                )
            ) ?>
        </b>

    </div>

</div>


<!-- ================================================================
     SHORTLIST INFORMATION
================================================================ -->

<div class="rc-grid-2">


    <!-- Stored Criteria -->

    <section class="rc-card">

        <div class="rc-card__header">

            <h3>
                Stored Search Criteria
            </h3>

        </div>


        <div class="rc-card__body rc-details">

            <?php if ($criteria): ?>

                <?php foreach ($criteria as $key => $value): ?>

                    <?php

                    /*
                     * Old shortlist records may contain removed
                     * scarce-skill filters. Do not try to execute them.
                     * We only display the stored historical value.
                     */

                    if (is_array($value)) {
                        $displayValue = implode(', ', $value);
                    } elseif (is_bool($value)) {
                        $displayValue = $value ? 'Yes' : 'No';
                    } else {
                        $displayValue = trim((string)$value);
                    }

                    if ($displayValue === '') {
                        $displayValue = 'Any';
                    }

                    ?>

                    <div>

                        <span>
                            <?= e(rc_label((string)$key)) ?>
                        </span>

                        <strong>
                            <?= e($displayValue) ?>
                        </strong>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div>

                    <span>
                        Criteria
                    </span>

                    <strong>
                        No stored search criteria
                    </strong>

                </div>

            <?php endif; ?>

        </div>

    </section>


    <!-- Reproducibility -->

    <section class="rc-card">

        <div class="rc-card__header">

            <h3>
                Reproducibility
            </h3>

        </div>


        <div class="rc-card__body rc-trust">

            <div>

                <i class="fas fa-code-branch"></i>

                <div>

                    <strong>
                        Taxonomy Version
                    </strong>

                    <span>
                        <?= e($sl['taxonomy_version']) ?>
                    </span>

                </div>

            </div>


            <div>

                <i class="fas fa-calendar"></i>

                <div>

                    <strong>
                        Created
                    </strong>

                    <span>
                        <?= e(
                            rc_datetime(
                                $sl['created_at']
                            )
                        ) ?>
                    </span>

                </div>

            </div>


            <div>

                <i class="fas fa-circle-info"></i>

                <div>

                    <strong>
                        Status
                    </strong>

                    <span>
                        <?= e(
                            rc_label(
                                (string)$sl['status']
                            )
                        ) ?>
                    </span>

                </div>

            </div>

        </div>

    </section>

</div>


<!-- ================================================================
     SHORTLISTED CANDIDATES
================================================================ -->

<section class="rc-card">

    <div class="rc-card__header">

        <div>

            <h3>
                Shortlisted Candidates
            </h3>

            <p>
                Candidates currently included in this shortlist.
            </p>

        </div>

    </div>


    <?php if (!$rows): ?>

        <div class="rc-card__body">

            <div class="rc-empty">

                <i class="fas fa-user-group"></i>

                <strong>
                    No shortlisted candidates
                </strong>

                <span>
                    No candidates have been added to this
                    shortlist yet.
                </span>

            </div>

        </div>


    <?php else: ?>


        <div class="rc-table-wrap">

            <table class="rc-table">

                <thead>

                    <tr>

                        <th>
                            Candidate
                        </th>

                        <th>
                            Location
                        </th>

                        <th>
                            Profile
                        </th>

                        <th>
                            Verified Skills
                        </th>

                        <th>
                            Consent
                        </th>

                        <th>
                            Added
                        </th>

                        <th>
                            Action
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php foreach ($rows as $candidate): ?>

                    <?php

                    /*
                    |--------------------------------------------------------------------------
                    | Name
                    |--------------------------------------------------------------------------
                    */

                    $firstName = trim(
                        (string)(
                            $candidate['first_name']
                            ?? ''
                        )
                    );

                    $lastName = trim(
                        (string)(
                            $candidate['last_name']
                            ?? ''
                        )
                    );

                    $fullName = trim(
                        $firstName . ' ' . $lastName
                    );

                    if ($fullName === '') {
                        $fullName = 'Candidate';
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Professional Title
                    |--------------------------------------------------------------------------
                    */

                    $professionalTitle = trim(
                        (string)(
                            $candidate[
                                'profile_professional_title'
                            ]
                            ?? ''
                        )
                    );

                    if ($professionalTitle === '') {

                        $professionalTitle = trim(
                            (string)(
                                $candidate[
                                    'user_professional_title'
                                ]
                                ?? ''
                            )
                        );
                    }

                    if ($professionalTitle === '') {
                        $professionalTitle = 'Candidate';
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Location
                    |--------------------------------------------------------------------------
                    */

                    $city = trim(
                        (string)(
                            $candidate['city']
                            ?? ''
                        )
                    );

                    $province = trim(
                        (string)(
                            $candidate['user_province']
                            ?? ''
                        )
                    );

                    $locationParts = [];

                    if ($city !== '') {
                        $locationParts[] = $city;
                    }

                    if ($province !== '') {
                        $locationParts[] = $province;
                    }

                    $location = $locationParts
                        ? implode(', ', $locationParts)
                        : 'Not provided';


                    /*
                    |--------------------------------------------------------------------------
                    | Profile
                    |--------------------------------------------------------------------------
                    */

                    $hasProfile = !empty(
                        $candidate['profile_id']
                    );

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
                                $candidate[
                                    'completion_percent'
                                ]
                                ?? 0
                            )
                        )
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | Consent
                    |--------------------------------------------------------------------------
                    */

                    $validConsent =
                        (int)(
                            $candidate[
                                'recruiter_consent'
                            ]
                            ?? 0
                        ) === 1;


                    /*
                    |--------------------------------------------------------------------------
                    | Skills
                    |--------------------------------------------------------------------------
                    */

                    $verifiedSkills = trim(
                        (string)(
                            $candidate[
                                'verified_skills'
                            ]
                            ?? ''
                        )
                    );

                    $verifiedSkillCount =
                        (int)(
                            $candidate[
                                'verified_skill_count'
                            ]
                            ?? 0
                        );

                    ?>

                    <tr>

                        <!-- Candidate -->

                        <td>

                            <strong>
                                <?= e($fullName) ?>
                            </strong>

                            <span>
                                <?= e($professionalTitle) ?>
                            </span>

                        </td>


                        <!-- Location -->

                        <td>
                            <?= e($location) ?>
                        </td>


                        <!-- Profile -->

                        <td>

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
                                    <?= number_format(
                                        $completion
                                    ) ?>%
                                </span>

                            <?php elseif ($hasProfile): ?>

                                <span class="rc-status">
                                    Inactive
                                </span>

                            <?php else: ?>

                                <span class="rc-status">
                                    No Profile
                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- Skills -->

                        <td>

                            <?php if (
                                $verifiedSkillCount > 0
                            ): ?>

                                <strong>
                                    <?= number_format(
                                        $verifiedSkillCount
                                    ) ?>
                                </strong>

                                <span
                                    title="<?= e(
                                        $verifiedSkills
                                    ) ?>"
                                >
                                    verified
                                </span>

                            <?php else: ?>

                                <span>
                                    None
                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- Consent -->

                        <td>

                            <?php if ($validConsent): ?>

                                <span
                                    class="
                                        rc-status
                                        rc-status--success
                                    "
                                >
                                    Valid
                                </span>

                            <?php else: ?>

                                <span
                                    class="
                                        rc-status
                                        rc-status--danger
                                    "
                                >
                                    Unavailable
                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- Added -->

                        <td>

                            <?= e(
                                rc_date(
                                    $candidate['added_at']
                                )
                            ) ?>

                        </td>


                        <!-- Action -->

                        <td>

                            <?php if ($validConsent): ?>

                                <a
                                    class="rc-icon-btn"
                                    href="<?= url(
                                        'recruiter/candidate_view.php?id='
                                        . (int)$candidate['user_id']
                                    ) ?>"
                                    title="View Candidate"
                                    aria-label="View Candidate"
                                >

                                    <i
                                        class="
                                            fas
                                            fa-arrow-right
                                        "
                                    ></i>

                                </a>

                            <?php else: ?>

                                <span
                                    class="rc-icon-btn"
                                    title="
                                        Recruiter-discovery
                                        consent unavailable
                                    "
                                >

                                    <i
                                        class="
                                            fas
                                            fa-lock
                                        "
                                    ></i>

                                </span>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</section>


<?php

require __DIR__ . '/_layout_end.php';

?>