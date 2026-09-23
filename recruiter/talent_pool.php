<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('recruiter');

$user        = current_user();
$flashes     = render_flashes();
$currentPage = 'talent_pool';
$pageTitle   = 'Talent Pool';

require_once __DIR__ . '/_helpers.php';

$conn = Database::getConnection();

/*
|--------------------------------------------------------------------------
| Recruiter
|--------------------------------------------------------------------------
*/

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
| Talent Pool
|--------------------------------------------------------------------------
|
| Confirmed schema:
|
| skills
| - id
| - name
| - category
| - description
| - is_active
|
| candidate_skills
| - id
| - user_id
| - skill_id
| - proficiency
| - verification_status
| - verified_at
|
| There is NO:
| - skills.is_scarce
| - candidate_skills.candidate_id
|
*/

$rows = [];

$stats = [
    'skills'              => 0,
    'verified_candidates' => 0,
    'verified_records'    => 0,
    'active_candidates'   => 0,
];

/*
|--------------------------------------------------------------------------
| Active Candidate Count
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT u.id) AS total

    FROM users u

    INNER JOIN roles r
        ON r.id = u.role_id

    WHERE r.slug = 'candidate'
      AND u.status = 'active'
");

if (!$stmt) {
    throw new RuntimeException(
        'Candidate count query failed: '
        . $conn->error
    );
}

$stmt->execute();

$result = $stmt->get_result();

$row = $result->fetch_assoc();

$stats['active_candidates'] = (int)(
    $row['total']
    ?? 0
);

$stmt->close();

/*
|--------------------------------------------------------------------------
| Skill Supply
|--------------------------------------------------------------------------
*/

if (
    rc_has_table($conn, 'skills') &&
    rc_has_table($conn, 'candidate_skills')
) {

    $sql = "
        SELECT
            s.id,
            s.name,
            s.category,
            s.description,
            s.is_active,

            COUNT(
                DISTINCT CASE
                    WHEN cs.verification_status = 'verified'
                     AND u.id IS NOT NULL
                    THEN cs.user_id
                END
            ) AS verified_candidates,

            COUNT(
                DISTINCT CASE
                    WHEN cs.verification_status = 'pending'
                     AND u.id IS NOT NULL
                    THEN cs.user_id
                END
            ) AS pending_candidates,

            COUNT(
                DISTINCT CASE
                    WHEN cs.verification_status = 'unverified'
                     AND u.id IS NOT NULL
                    THEN cs.user_id
                END
            ) AS unverified_candidates,

            COUNT(
                DISTINCT CASE
                    WHEN cs.verification_status = 'failed'
                     AND u.id IS NOT NULL
                    THEN cs.user_id
                END
            ) AS failed_candidates,

            COUNT(
                CASE
                    WHEN cs.verification_status = 'verified'
                     AND u.id IS NOT NULL
                    THEN cs.id
                END
            ) AS verified_records,

            MAX(
                CASE
                    WHEN cs.verification_status = 'verified'
                     AND u.id IS NOT NULL
                    THEN cs.verified_at
                END
            ) AS latest_verification

        FROM skills s

        LEFT JOIN candidate_skills cs
            ON cs.skill_id = s.id

        LEFT JOIN users u
            ON u.id = cs.user_id
           AND u.status = 'active'

        LEFT JOIN roles r
            ON r.id = u.role_id
           AND r.slug = 'candidate'

        WHERE s.is_active = 1

        GROUP BY
            s.id,
            s.name,
            s.category,
            s.description,
            s.is_active

        ORDER BY
            verified_candidates DESC,
            s.name ASC
    ";

    $result = $conn->query($sql);

    if (!$result) {
        throw new RuntimeException(
            'Talent pool query failed: '
            . $conn->error
        );
    }

    while ($skill = $result->fetch_assoc()) {

        $rows[] = $skill;

        $stats['verified_records'] +=
            (int)(
                $skill['verified_records']
                ?? 0
            );
    }

    $result->free();

    $stats['skills'] = count($rows);
}

/*
|--------------------------------------------------------------------------
| Distinct Candidates With Verified Skills
|--------------------------------------------------------------------------
*/

if (
    rc_has_table($conn, 'skills') &&
    rc_has_table($conn, 'candidate_skills')
) {

    $sql = "
        SELECT
            COUNT(DISTINCT cs.user_id) AS total

        FROM candidate_skills cs

        INNER JOIN skills s
            ON s.id = cs.skill_id
           AND s.is_active = 1

        INNER JOIN users u
            ON u.id = cs.user_id
           AND u.status = 'active'

        INNER JOIN roles r
            ON r.id = u.role_id
           AND r.slug = 'candidate'

        WHERE cs.verification_status = 'verified'
    ";

    $result = $conn->query($sql);

    if (!$result) {
        throw new RuntimeException(
            'Verified candidate count query failed: '
            . $conn->error
        );
    }

    $row = $result->fetch_assoc();

    $stats['verified_candidates'] =
        (int)(
            $row['total']
            ?? 0
        );

    $result->free();
}

/*
|--------------------------------------------------------------------------
| Audit
|--------------------------------------------------------------------------
*/

rc_activity(
    $conn,
    $recruiterId,
    'talent_pool_viewed',
    'Viewed recruiter talent pool intelligence.',
    'talent_pool',
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

.rc-talent-stats {
    display: grid;
    grid-template-columns:
        repeat(4, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 22px;
}

.rc-talent-stat {
    padding: 20px;
    border-radius: 18px;
    background: var(--rc-card, #fff);
    border: 1px solid rgba(127, 127, 127, .14);
}

.rc-talent-stat i {
    display: block;
    margin-bottom: 12px;
    font-size: 20px;
}

.rc-talent-stat strong {
    display: block;
    font-size: 26px;
    line-height: 1;
}

.rc-talent-stat span {
    display: block;
    margin-top: 8px;
    font-size: 13px;
    opacity: .7;
}

.rc-talent-skill {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.rc-talent-skill small {
    max-width: 420px;
    opacity: .65;
}

.rc-talent-count {
    font-weight: 700;
}

.rc-talent-zero {
    opacity: .5;
}

.rc-talent-status {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.rc-talent-empty {
    padding: 55px 25px;
    text-align: center;
}

.rc-talent-empty i {
    display: block;
    margin-bottom: 12px;
    font-size: 34px;
    opacity: .45;
}

.rc-talent-empty strong {
    display: block;
    margin-bottom: 7px;
}

body.dark-mode .rc-talent-stat,
html[data-theme="dark"] .rc-talent-stat {
    background: #111827;
    color: #f8fafc;
}

@media (max-width: 1000px) {

    .rc-talent-stats {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }

}

@media (max-width: 600px) {

    .rc-talent-stats {
        grid-template-columns: 1fr;
    }

}

</style>


<!-- ================================================================
     PAGE HEADER
================================================================ -->

<div class="rc-page-header">

    <span class="rc-eyebrow">
        <i class="fas fa-chart-line"></i>
        Talent Intelligence
    </span>

    <h2>
        Talent Pool
    </h2>

    <p>
        Candidate skill supply across the active
        recruitment taxonomy.
    </p>

</div>


<!-- ================================================================
     SUMMARY CARDS
================================================================ -->

<section class="rc-talent-stats">

    <article class="rc-talent-stat">

        <i class="fas fa-users"></i>

        <strong>
            <?= number_format(
                $stats['active_candidates']
            ) ?>
        </strong>

        <span>
            Active Candidates
        </span>

    </article>


    <article class="rc-talent-stat">

        <i class="fas fa-code"></i>

        <strong>
            <?= number_format(
                $stats['skills']
            ) ?>
        </strong>

        <span>
            Active Skills
        </span>

    </article>


    <article class="rc-talent-stat">

        <i class="fas fa-user-check"></i>

        <strong>
            <?= number_format(
                $stats['verified_candidates']
            ) ?>
        </strong>

        <span>
            Candidates With Verified Skills
        </span>

    </article>


    <article class="rc-talent-stat">

        <i class="fas fa-certificate"></i>

        <strong>
            <?= number_format(
                $stats['verified_records']
            ) ?>
        </strong>

        <span>
            Verified Skill Records
        </span>

    </article>

</section>


<!-- ================================================================
     TALENT POOL TABLE
================================================================ -->

<div class="rc-card">

    <div class="rc-card__header">

        <div>

            <h3>
                Skill Supply
            </h3>

            <p>
                Verified and pending candidate supply
                for each active skill.
            </p>

        </div>

        <a
            class="rc-btn rc-btn--primary"
            href="<?= url(
                'recruiter/search.php'
            ) ?>"
        >
            <i class="fas fa-magnifying-glass"></i>
            Find Candidates
        </a>

    </div>


    <?php if (!$rows): ?>

        <div class="rc-talent-empty">

            <i class="fas fa-code"></i>

            <strong>
                No skill data available
            </strong>

            <span>
                The active skill taxonomy does not
                currently contain skill records.
            </span>

        </div>

    <?php else: ?>

        <div class="rc-table-wrap">

            <table class="rc-table">

                <thead>

                    <tr>

                        <th>
                            Skill
                        </th>

                        <th>
                            Category
                        </th>

                        <th>
                            Verified
                        </th>

                        <th>
                            Pending
                        </th>

                        <th>
                            Unverified
                        </th>

                        <th>
                            Latest Verification
                        </th>

                        <th>
                            Action
                        </th>

                    </tr>

                </thead>


                <tbody>

                    <?php foreach (
                        $rows
                        as $skill
                    ): ?>

                        <?php

                        $verifiedCount =
                            (int)(
                                $skill[
                                    'verified_candidates'
                                ]
                                ?? 0
                            );

                        $pendingCount =
                            (int)(
                                $skill[
                                    'pending_candidates'
                                ]
                                ?? 0
                            );

                        $unverifiedCount =
                            (int)(
                                $skill[
                                    'unverified_candidates'
                                ]
                                ?? 0
                            );

                        ?>

                        <tr>

                            <!-- Skill -->

                            <td>

                                <div class="rc-talent-skill">

                                    <strong>
                                        <?= e(
                                            $skill['name']
                                        ) ?>
                                    </strong>

                                    <?php if (
                                        !empty(
                                            $skill[
                                                'description'
                                            ]
                                        )
                                    ): ?>

                                        <small>
                                            <?= e(
                                                $skill[
                                                    'description'
                                                ]
                                            ) ?>
                                        </small>

                                    <?php endif; ?>

                                </div>

                            </td>


                            <!-- Category -->

                            <td>

                                <span
                                    class="rc-status
                                    <?= (
                                        $skill['category']
                                        === 'technical'
                                    )
                                        ? 'rc-status--blue'
                                        : 'rc-status--purple' ?>"
                                >

                                    <?= e(
                                        rc_label(
                                            $skill[
                                                'category'
                                            ]
                                        )
                                    ) ?>

                                </span>

                            </td>


                            <!-- Verified -->

                            <td>

                                <span
                                    class="
                                        rc-talent-count
                                        <?= $verifiedCount === 0
                                            ? 'rc-talent-zero'
                                            : '' ?>
                                    "
                                >
                                    <?= number_format(
                                        $verifiedCount
                                    ) ?>
                                </span>

                            </td>


                            <!-- Pending -->

                            <td>

                                <span
                                    class="
                                        rc-talent-count
                                        <?= $pendingCount === 0
                                            ? 'rc-talent-zero'
                                            : '' ?>
                                    "
                                >
                                    <?= number_format(
                                        $pendingCount
                                    ) ?>
                                </span>

                            </td>


                            <!-- Unverified -->

                            <td>

                                <span
                                    class="
                                        rc-talent-count
                                        <?= $unverifiedCount === 0
                                            ? 'rc-talent-zero'
                                            : '' ?>
                                    "
                                >
                                    <?= number_format(
                                        $unverifiedCount
                                    ) ?>
                                </span>

                            </td>


                            <!-- Latest Verification -->

                            <td>

                                <?php if (
                                    !empty(
                                        $skill[
                                            'latest_verification'
                                        ]
                                    )
                                ): ?>

                                    <?= e(
                                        rc_date(
                                            $skill[
                                                'latest_verification'
                                            ]
                                        )
                                    ) ?>

                                <?php else: ?>

                                    <span class="rc-talent-zero">
                                        No verification
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- Action -->

                            <td>

                                <a
                                    class="rc-btn rc-btn--secondary"
                                    href="<?= url(
                                        'recruiter/search.php?skill='
                                        . rawurlencode(
                                            $skill['name']
                                        )
                                    ) ?>"
                                >

                                    <i class="fas fa-search"></i>

                                    Candidates

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
     INFORMATION
================================================================ -->

<div class="rc-card rc-space">

    <div class="rc-card__body rc-info">

        <i class="fas fa-circle-info"></i>

        <div>

            <strong>
                Skill verification is candidate-specific.
            </strong>

            <p>
                Verified counts represent active Candidate
                accounts whose candidate-skill record has a
                verification status of verified. Candidate
                profile access remains subject to recruiter
                discovery consent.
            </p>

        </div>

    </div>

</div>


<?php

require __DIR__ . '/_layout_end.php';

?>