<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('recruiter');

$user        = current_user();
$flashes     = render_flashes();
$currentPage = 'candidate_view';
$pageTitle   = 'Candidate Profile';

require_once __DIR__ . '/_helpers.php';

$conn = Database::getConnection();

$recruiterId = (int)(
    $user['id']
    ?? $user['user_id']
    ?? 0
);

$candidateId = (int)(
    $_GET['id']
    ?? 0
);

if ($recruiterId <= 0 || $candidateId <= 0) {
    http_response_code(400);
    exit('Invalid request.');
}

/*
|--------------------------------------------------------------------------
| Required Tables
|--------------------------------------------------------------------------
*/

if (
    !rc_has_table($conn, 'users') ||
    !rc_has_table($conn, 'roles')
) {
    http_response_code(500);
    exit('Required candidate account tables are unavailable.');
}

if (!rc_has_table($conn, 'candidate_consents')) {
    http_response_code(500);
    exit('Candidate consent table is unavailable.');
}

/*
|--------------------------------------------------------------------------
| Candidate + Recruiter Discovery Consent
|--------------------------------------------------------------------------
|
| Candidate identity:
| users
|
| Candidate role:
| roles
|
| Detailed profile:
| candidate_profiles
|
| Recruiter access:
| candidate_consents
|
*/

$hasProfile = rc_has_table(
    $conn,
    'candidate_profiles'
);

if ($hasProfile) {

    $sql = "
        SELECT
            u.id,
            u.first_name,
            u.last_name,
            u.email,
            u.phone,
            u.province,
            u.employment_status,
            u.qualification_level,
            u.professional_title AS user_professional_title,
            u.status AS user_status,

            cp.id AS profile_id,
            cp.professional_title AS profile_professional_title,
            cp.professional_summary,
            cp.career_interests,
            cp.employment_status AS profile_employment_status,
            cp.availability_status_id,
            cp.availability_date,
            cp.address,
            cp.city,
            cp.profile_picture,
            cp.completion_percent,
            cp.is_active AS profile_active,
            cp.created_at AS profile_created_at,
            cp.updated_at AS profile_updated_at,

            cc.id AS consent_id,
            cc.status AS consent_status,
            cc.granted_at,
            cc.expires_at,
            cc.revoked_at,
            cc.consent_source

        FROM users u

        INNER JOIN roles r
            ON r.id = u.role_id

        INNER JOIN candidate_consents cc
            ON cc.candidate_id = u.id
           AND cc.consent_type = 'recruiter_discovery'
           AND cc.status = 'consented'
           AND (
                cc.expires_at IS NULL
                OR cc.expires_at > NOW()
           )

        LEFT JOIN candidate_profiles cp
            ON cp.user_id = u.id

        WHERE u.id = ?
          AND r.slug = 'candidate'
          AND u.status = 'active'

        ORDER BY cc.granted_at DESC

        LIMIT 1
    ";

} else {

    $sql = "
        SELECT
            u.id,
            u.first_name,
            u.last_name,
            u.email,
            u.phone,
            u.province,
            u.employment_status,
            u.qualification_level,
            u.professional_title AS user_professional_title,
            u.status AS user_status,

            NULL AS profile_id,
            NULL AS profile_professional_title,
            NULL AS professional_summary,
            NULL AS career_interests,
            NULL AS profile_employment_status,
            NULL AS availability_status_id,
            NULL AS availability_date,
            NULL AS address,
            NULL AS city,
            NULL AS profile_picture,
            0 AS completion_percent,
            0 AS profile_active,
            NULL AS profile_created_at,
            NULL AS profile_updated_at,

            cc.id AS consent_id,
            cc.status AS consent_status,
            cc.granted_at,
            cc.expires_at,
            cc.revoked_at,
            cc.consent_source

        FROM users u

        INNER JOIN roles r
            ON r.id = u.role_id

        INNER JOIN candidate_consents cc
            ON cc.candidate_id = u.id
           AND cc.consent_type = 'recruiter_discovery'
           AND cc.status = 'consented'
           AND (
                cc.expires_at IS NULL
                OR cc.expires_at > NOW()
           )

        WHERE u.id = ?
          AND r.slug = 'candidate'
          AND u.status = 'active'

        ORDER BY cc.granted_at DESC

        LIMIT 1
    ";
}

$stmt = $conn->prepare($sql);

if (!$stmt) {
    throw new RuntimeException(
        'Candidate profile query failed: '
        . $conn->error
    );
}

$stmt->bind_param(
    'i',
    $candidateId
);

$stmt->execute();

$candidate = $stmt
    ->get_result()
    ->fetch_assoc();

$stmt->close();

if (!$candidate) {
    http_response_code(403);

    exit(
        'Candidate not found or recruiter-discovery consent is not valid.'
    );
}

/*
|--------------------------------------------------------------------------
| Candidate Skills
|--------------------------------------------------------------------------
|
| Confirmed schema:
|
| candidate_skills:
|   user_id
|   skill_id
|   proficiency
|   verification_status
|   verified_at
|
| skills:
|   id
|   name
|   category
|   description
|   is_active
|
*/

$skills = [];

if (
    rc_has_table($conn, 'candidate_skills') &&
    rc_has_table($conn, 'skills')
) {

    $sql = "
        SELECT
            cs.id AS candidate_skill_id,
            cs.skill_id,
            cs.proficiency,
            cs.verification_status,
            cs.verified_at,
            cs.created_at AS skill_added_at,

            s.name,
            s.category,
            s.description,
            s.is_active

        FROM candidate_skills cs

        INNER JOIN skills s
            ON s.id = cs.skill_id

        WHERE cs.user_id = ?
          AND s.is_active = 1

        ORDER BY
            CASE cs.verification_status
                WHEN 'verified' THEN 1
                WHEN 'pending' THEN 2
                WHEN 'unverified' THEN 3
                WHEN 'failed' THEN 4
                ELSE 5
            END,
            s.name ASC
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException(
            'Candidate skills query failed: '
            . $conn->error
        );
    }

    $stmt->bind_param(
        'i',
        $candidateId
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $skills[] = $row;
    }

    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| Candidate Name
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

$candidateName = trim(
    $firstName . ' ' . $lastName
);

if ($candidateName === '') {
    $candidateName = 'Candidate';
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
| Location
|--------------------------------------------------------------------------
*/

$locationParts = [];

$city = trim(
    (string)(
        $candidate['city']
        ?? ''
    )
);

$province = trim(
    (string)(
        $candidate['province']
        ?? ''
    )
);

if ($city !== '') {
    $locationParts[] = $city;
}

if ($province !== '') {
    $locationParts[] = $province;
}

$location = implode(
    ', ',
    array_unique($locationParts)
);

if ($location === '') {
    $location = 'Location not provided';
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
            $candidate['employment_status']
            ?? ''
        )
    );
}

/*
|--------------------------------------------------------------------------
| Profile Completion
|--------------------------------------------------------------------------
*/

$completionPercent = max(
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
| Skill Statistics
|--------------------------------------------------------------------------
*/

$totalSkills = count($skills);

$verifiedSkills = 0;
$pendingSkills  = 0;

foreach ($skills as $skill) {

    $verificationStatus =
        strtolower(
            trim(
                (string)(
                    $skill['verification_status']
                    ?? ''
                )
            )
        );

    if ($verificationStatus === 'verified') {
        $verifiedSkills++;
    }

    if ($verificationStatus === 'pending') {
        $pendingSkills++;
    }
}

/*
|--------------------------------------------------------------------------
| Audit Recruiter Access
|--------------------------------------------------------------------------
*/

rc_activity(
    $conn,
    $recruiterId,
    'candidate_profile_viewed',
    'Viewed a recruiter-consented candidate profile.',
    'candidate',
    $candidateId
);

/*
|--------------------------------------------------------------------------
| Layout
|--------------------------------------------------------------------------
*/

require __DIR__ . '/_layout_start.php';

?>


<!-- ================================================================
     PAGE-SPECIFIC STYLES
================================================================ -->

<style>

.rc-profile-completion {
    margin-top: 10px;
}

.rc-profile-completion progress {
    display: block;
    width: 100%;
    height: 9px;
    margin-top: 7px;
}

.rc-profile-completion small {
    display: block;
    margin-top: 6px;
    opacity: .7;
}

.rc-profile-summary {
    line-height: 1.7;
}

.rc-profile-summary p {
    margin: 0;
}

.rc-profile-summary--empty {
    opacity: .65;
}

.rc-skill-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 8px;
}

.rc-skill-meta span {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.rc-profile-contact {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.rc-profile-contact a {
    word-break: break-word;
}

</style>


<!-- ================================================================
     PROFILE HERO
================================================================ -->

<div class="rc-profile-hero">

    <div class="rc-avatar rc-avatar--xl">

        <?= e(
            rc_initials(
                $firstName,
                $lastName
            )
        ) ?>

    </div>


    <div class="rc-profile-copy">

        <span class="rc-eyebrow">

            <i class="fas fa-user-shield"></i>

            Recruiter-Consented Talent Profile

        </span>


        <h2>
            <?= e($candidateName) ?>
        </h2>


        <p>
            <?= e($professionalTitle) ?>
        </p>


        <div class="rc-chips">

            <b>
                <i class="fas fa-location-dot"></i>
                <?= e($location) ?>
            </b>

            <b>
                <i class="fas fa-user-shield"></i>
                Consent valid
            </b>

            <?php if ($completionPercent >= 100): ?>

                <b>
                    <i class="fas fa-circle-check"></i>
                    Profile complete
                </b>

            <?php endif; ?>

        </div>

    </div>


    <a
        class="rc-btn rc-btn--primary"
        href="<?= url(
            'recruiter/shortlists.php?candidate_id='
            . $candidateId
        ) ?>"
    >

        <i class="fas fa-plus"></i>

        Add to Shortlist

    </a>

</div>


<!-- ================================================================
     PROFILE SUMMARY + TRUST SIGNALS
================================================================ -->

<div class="rc-grid-2">


    <!-- Profile Summary -->

    <section class="rc-card">

        <div class="rc-card__header">

            <h3>
                Profile Summary
            </h3>

        </div>


        <div class="rc-card__body rc-details">


            <div>

                <span>
                    Professional Title
                </span>

                <strong>
                    <?= e($professionalTitle) ?>
                </strong>

            </div>


            <div>

                <span>
                    Location
                </span>

                <strong>
                    <?= e($location) ?>
                </strong>

            </div>


            <div>

                <span>
                    Employment Status
                </span>

                <strong>
                    <?= e(
                        $employmentStatus !== ''
                            ? rc_label($employmentStatus)
                            : 'Not provided'
                    ) ?>
                </strong>

            </div>


            <div>

                <span>
                    Qualification Level
                </span>

                <strong>
                    <?= e(
                        !empty(
                            $candidate[
                                'qualification_level'
                            ]
                        )
                            ? rc_label(
                                $candidate[
                                    'qualification_level'
                                ]
                            )
                            : 'Not provided'
                    ) ?>
                </strong>

            </div>


            <div>

                <span>
                    Availability Date
                </span>

                <strong>
                    <?= e(
                        !empty(
                            $candidate[
                                'availability_date'
                            ]
                        )
                            ? rc_date(
                                $candidate[
                                    'availability_date'
                                ]
                            )
                            : 'Not provided'
                    ) ?>
                </strong>

            </div>


            <div>

                <span>
                    Profile Completion
                </span>

                <strong>
                    <?= number_format(
                        $completionPercent
                    ) ?>%
                </strong>

                <div class="rc-profile-completion">

                    <progress
                        value="<?= $completionPercent ?>"
                        max="100"
                    >
                        <?= $completionPercent ?>%
                    </progress>

                </div>

            </div>


            <div>

                <span>
                    Consent Granted
                </span>

                <strong>
                    <?= e(
                        rc_datetime(
                            $candidate['granted_at']
                            ?? null
                        )
                    ) ?>
                </strong>

            </div>


            <div>

                <span>
                    Consent Expires
                </span>

                <strong>
                    <?= e(
                        rc_datetime(
                            $candidate['expires_at']
                            ?? null,
                            'No expiry'
                        )
                    ) ?>
                </strong>

            </div>

        </div>

    </section>


    <!-- Trust Signals -->

    <section class="rc-card">

        <div class="rc-card__header">

            <h3>
                Trust Signals
            </h3>

        </div>


        <div class="rc-card__body">

            <div class="rc-trust">


                <div>

                    <i class="fas fa-user-shield"></i>

                    <div>

                        <strong>
                            Consent Valid
                        </strong>

                        <span>
                            Recruiter-discovery consent was
                            checked when this profile was opened.
                        </span>

                    </div>

                </div>


                <div>

                    <i class="fas fa-certificate"></i>

                    <div>

                        <strong>
                            <?= number_format(
                                $verifiedSkills
                            ) ?>
                            Verified
                            <?= $verifiedSkills === 1
                                ? 'Skill'
                                : 'Skills' ?>
                        </strong>

                        <span>
                            Skill verification is tracked
                            individually.
                        </span>

                    </div>

                </div>


                <div>

                    <i class="fas fa-fingerprint"></i>

                    <div>

                        <strong>
                            Audited Access
                        </strong>

                        <span>
                            Recruiter profile access has been
                            recorded in the activity log.
                        </span>

                    </div>

                </div>


                <div>

                    <i class="fas fa-chart-line"></i>

                    <div>

                        <strong>
                            <?= number_format(
                                $completionPercent
                            ) ?>% Profile Completion
                        </strong>

                        <span>
                            Candidate profile completion
                            recorded by the platform.
                        </span>

                    </div>

                </div>

            </div>

        </div>

    </section>

</div>


<!-- ================================================================
     PROFESSIONAL SUMMARY
================================================================ -->

<section class="rc-card rc-space">

    <div class="rc-card__header">

        <h3>
            Professional Summary
        </h3>

    </div>


    <div class="rc-card__body rc-profile-summary">

        <?php if (
            !empty(
                $candidate[
                    'professional_summary'
                ]
            )
        ): ?>

            <p>
                <?= nl2br(
                    e(
                        $candidate[
                            'professional_summary'
                        ]
                    )
                ) ?>
            </p>

        <?php else: ?>

            <p class="rc-profile-summary--empty">
                No professional summary has been
                provided by this candidate.
            </p>

        <?php endif; ?>

    </div>

</section>


<!-- ================================================================
     CAREER INTERESTS
================================================================ -->

<section class="rc-card rc-space">

    <div class="rc-card__header">

        <h3>
            Career Interests
        </h3>

    </div>


    <div class="rc-card__body rc-profile-summary">

        <?php if (
            !empty(
                $candidate[
                    'career_interests'
                ]
            )
        ): ?>

            <p>
                <?= nl2br(
                    e(
                        $candidate[
                            'career_interests'
                        ]
                    )
                ) ?>
            </p>

        <?php else: ?>

            <p class="rc-profile-summary--empty">
                No career interests have been provided.
            </p>

        <?php endif; ?>

    </div>

</section>


<!-- ================================================================
     CONTACT INFORMATION
================================================================ -->

<section class="rc-card rc-space">

    <div class="rc-card__header">

        <h3>
            Candidate Contact
        </h3>

    </div>


    <div class="rc-card__body rc-details">

        <div>

            <span>Email</span>

            <strong>

                <?php if (
                    !empty($candidate['email'])
                ): ?>

                    <a
                        href="mailto:<?= e(
                            $candidate['email']
                        ) ?>"
                    >
                        <?= e(
                            $candidate['email']
                        ) ?>
                    </a>

                <?php else: ?>

                    Not provided

                <?php endif; ?>

            </strong>

        </div>


        <div>

            <span>Phone</span>

            <strong>

                <?php if (
                    !empty($candidate['phone'])
                ): ?>

                    <a
                        href="tel:<?= e(
                            preg_replace(
                                '/[^0-9+]/',
                                '',
                                (string)$candidate[
                                    'phone'
                                ]
                            )
                        ) ?>"
                    >
                        <?= e(
                            $candidate['phone']
                        ) ?>
                    </a>

                <?php else: ?>

                    Not provided

                <?php endif; ?>

            </strong>

        </div>

    </div>

</section>


<!-- ================================================================
     SKILLS
================================================================ -->

<section class="rc-card rc-space">

    <div class="rc-card__header">

        <div>

            <h3>
                Skills & Verification
            </h3>

            <p>
                <?= number_format($totalSkills) ?>
                <?= $totalSkills === 1
                    ? 'skill'
                    : 'skills' ?>
                recorded
                ·
                <?= number_format($verifiedSkills) ?>
                verified
                ·
                <?= number_format($pendingSkills) ?>
                pending
            </p>

        </div>

    </div>


    <?php if (!$skills): ?>

        <div class="rc-empty">

            <i class="fas fa-code"></i>

            <strong>
                No skills recorded
            </strong>

            <span>
                This candidate has not added skills
                to their profile yet.
            </span>

        </div>

    <?php else: ?>

        <div class="rc-skill-list">

            <?php foreach (
                $skills
                as $skill
            ): ?>

                <?php

                $verificationStatus = trim(
                    (string)(
                        $skill[
                            'verification_status'
                        ]
                        ?? 'unverified'
                    )
                );

                ?>

                <article>


                    <!-- Skill -->

                    <div>

                        <strong>
                            <?= e($skill['name']) ?>
                        </strong>

                        <span>

                            <?= e(
                                rc_label(
                                    $skill['category']
                                    ?? 'technical'
                                )
                            ) ?>

                        </span>

                    </div>


                    <!-- Proficiency -->

                    <div>

                        <span>
                            Proficiency
                        </span>

                        <strong>
                            <?= e(
                                rc_label(
                                    $skill['proficiency']
                                    ?? 'intermediate'
                                )
                            ) ?>
                        </strong>

                    </div>


                    <!-- Verification -->

                    <div>

                        <span>
                            Verification
                        </span>

                        <strong>
                            <?= e(
                                rc_label(
                                    $verificationStatus
                                )
                            ) ?>
                        </strong>

                    </div>


                    <!-- Verified At -->

                    <div>

                        <span>
                            Verified
                        </span>

                        <strong>

                            <?php if (
                                $verificationStatus ===
                                'verified' &&
                                !empty(
                                    $skill['verified_at']
                                )
                            ): ?>

                                <?= e(
                                    rc_date(
                                        $skill[
                                            'verified_at'
                                        ]
                                    )
                                ) ?>

                            <?php elseif (
                                $verificationStatus ===
                                'verified'
                            ): ?>

                                Verified

                            <?php else: ?>

                                —

                            <?php endif; ?>

                        </strong>

                    </div>


                    <!-- Description -->

                    <div>

                        <span>
                            Skill Description
                        </span>

                        <strong>
                            <?= e(
                                !empty(
                                    $skill[
                                        'description'
                                    ]
                                )
                                    ? $skill[
                                        'description'
                                    ]
                                    : 'No description'
                            ) ?>
                        </strong>

                    </div>

                </article>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</section>


<!-- ================================================================
     CONSENT INFORMATION
================================================================ -->

<section class="rc-card rc-space">

    <div class="rc-card__header">

        <h3>
            Recruiter Discovery Consent
        </h3>

    </div>


    <div class="rc-card__body rc-details">

        <div>

            <span>Status</span>

            <strong>
                <?= e(
                    rc_label(
                        $candidate[
                            'consent_status'
                        ]
                    )
                ) ?>
            </strong>

        </div>


        <div>

            <span>Granted</span>

            <strong>
                <?= e(
                    rc_datetime(
                        $candidate[
                            'granted_at'
                        ]
                    )
                ) ?>
            </strong>

        </div>


        <div>

            <span>Expiry</span>

            <strong>
                <?= e(
                    rc_datetime(
                        $candidate[
                            'expires_at'
                        ],
                        'No expiry'
                    )
                ) ?>
            </strong>

        </div>


        <div>

            <span>Source</span>

            <strong>
                <?= e(
                    !empty(
                        $candidate[
                            'consent_source'
                        ]
                    )
                        ? rc_label(
                            $candidate[
                                'consent_source'
                            ]
                        )
                        : 'Not specified'
                ) ?>
            </strong>

        </div>

    </div>

</section>


<?php

require __DIR__ . '/_layout_end.php';

?>