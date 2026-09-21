<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('recruiter');

$user        = current_user();
$flashes     = render_flashes();
$currentPage = 'shortlists';
$pageTitle   = 'Shortlists';

require_once __DIR__ . '/_helpers.php';

$conn = Database::getConnection();

$uid = (int)($user['id'] ?? $user['user_id'] ?? 0);

$candidateId = (int)(
    $_GET['candidate_id']
    ?? $_POST['candidate_id']
    ?? 0
);

if ($uid <= 0) {
    http_response_code(403);
    exit('Invalid Recruiter account.');
}

/*
|--------------------------------------------------------------------------
| Required Tables
|--------------------------------------------------------------------------
*/

$hasShortlists = rc_has_table(
    $conn,
    'recruiter_shortlists'
);

$hasShortlistCandidates = rc_has_table(
    $conn,
    'recruiter_shortlist_candidates'
);

$hasConsents = rc_has_table(
    $conn,
    'candidate_consents'
);

if (!$hasShortlists || !$hasShortlistCandidates) {
    http_response_code(500);
    exit('Recruiter shortlist tables are not available.');
}

/*
|--------------------------------------------------------------------------
| Helper: Validate Candidate
|--------------------------------------------------------------------------
|
| Candidate identity comes from:
|
| users -> roles
|
| It does NOT depend on candidate_profiles.
|
*/

function shortlist_candidate_exists(
    mysqli $conn,
    int $candidateId
): bool {

    if ($candidateId <= 0) {
        return false;
    }

    $stmt = $conn->prepare("
        SELECT u.id

        FROM users u

        INNER JOIN roles r
            ON r.id = u.role_id

        WHERE u.id = ?
          AND r.slug = 'candidate'
          AND u.status = 'active'

        LIMIT 1
    ");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param(
        'i',
        $candidateId
    );

    $stmt->execute();

    $exists = (bool)$stmt
        ->get_result()
        ->fetch_assoc();

    $stmt->close();

    return $exists;
}

/*
|--------------------------------------------------------------------------
| Helper: Recruiter Discovery Consent
|--------------------------------------------------------------------------
*/

function shortlist_candidate_has_consent(
    mysqli $conn,
    int $candidateId,
    bool $hasConsents
): bool {

    if (
        !$hasConsents ||
        $candidateId <= 0
    ) {
        return false;
    }

    $stmt = $conn->prepare("
        SELECT 1

        FROM candidate_consents

        WHERE candidate_id = ?
          AND consent_type = 'recruiter_discovery'
          AND status = 'consented'
          AND (
                expires_at IS NULL
                OR expires_at > NOW()
              )

        LIMIT 1
    ");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param(
        'i',
        $candidateId
    );

    $stmt->execute();

    $valid = (bool)$stmt
        ->get_result()
        ->fetch_assoc();

    $stmt->close();

    return $valid;
}

/*
|--------------------------------------------------------------------------
| Helper: Add Candidate
|--------------------------------------------------------------------------
*/

function add_candidate_to_shortlist(
    mysqli $conn,
    int $shortlistId,
    int $candidateId,
    int $recruiterId
): bool {

    if (
        $shortlistId <= 0 ||
        $candidateId <= 0 ||
        $recruiterId <= 0
    ) {
        return false;
    }

    $stmt = $conn->prepare("
        INSERT IGNORE INTO recruiter_shortlist_candidates
        (
            shortlist_id,
            candidate_id,
            added_by
        )
        VALUES (?, ?, ?)
    ");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param(
        'iii',
        $shortlistId,
        $candidateId,
        $recruiterId
    );

    $stmt->execute();

    $success =
        $stmt->affected_rows >= 0;

    $stmt->close();

    return $success;
}

/*
|--------------------------------------------------------------------------
| POST Actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    rc_verify_csrf();

    $action = trim(
        (string)($_POST['action'] ?? '')
    );

    /*
    |--------------------------------------------------------------------------
    | Create Shortlist
    |--------------------------------------------------------------------------
    */

    if ($action === 'create') {

        $name = trim(
            (string)($_POST['name'] ?? '')
        );

        $client = trim(
            (string)($_POST['client_name'] ?? '')
        );

        $criteria = trim(
            (string)($_POST['criteria'] ?? '{}')
        );

        $taxonomyVersion = trim(
            (string)(
                $_POST['taxonomy_version']
                ?? ''
            )
        );

        if ($taxonomyVersion === '') {
            $taxonomyVersion =
                rc_taxonomy_version($conn);
        }

        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        if ($name === '') {

            rc_flash(
                'danger',
                'Shortlist name is required.'
            );

            header(
                'Location: '
                . url('recruiter/shortlists.php')
            );

            exit;
        }

        /*
         * Validate JSON before storing.
         */

        $decodedCriteria = json_decode(
            $criteria,
            true
        );

        if (!is_array($decodedCriteria)) {
            $decodedCriteria = [];
        }

        $criteria = json_encode(
            $decodedCriteria,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
        );

        /*
        |--------------------------------------------------------------------------
        | Insert Shortlist
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            INSERT INTO recruiter_shortlists
            (
                recruiter_id,
                name,
                client_name,
                search_criteria_json,
                taxonomy_version
            )
            VALUES (?, ?, ?, ?, ?)
        ");

        if (!$stmt) {

            throw new RuntimeException(
                'Unable to prepare shortlist creation: '
                . $conn->error
            );
        }

        $stmt->bind_param(
            'issss',
            $uid,
            $name,
            $client,
            $criteria,
            $taxonomyVersion
        );

        $stmt->execute();

        $shortlistId =
            (int)$stmt->insert_id;

        $stmt->close();

        /*
        |--------------------------------------------------------------------------
        | Add Candidate During Creation
        |--------------------------------------------------------------------------
        */

        if ($candidateId > 0) {

            $candidateExists =
                shortlist_candidate_exists(
                    $conn,
                    $candidateId
                );

            $validConsent =
                shortlist_candidate_has_consent(
                    $conn,
                    $candidateId,
                    $hasConsents
                );

            if (
                $candidateExists &&
                $validConsent
            ) {

                add_candidate_to_shortlist(
                    $conn,
                    $shortlistId,
                    $candidateId,
                    $uid
                );

            } else {

                rc_flash(
                    'warning',
                    'The shortlist was created, but the candidate was not added because the candidate is unavailable or recruiter-discovery consent is not valid.'
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Audit
        |--------------------------------------------------------------------------
        */

        rc_activity(
            $conn,
            $uid,
            'shortlist_created',
            'Created shortlist: ' . $name,
            'shortlist',
            $shortlistId
        );

        rc_flash(
            'success',
            'Shortlist created successfully.'
        );

        header(
            'Location: '
            . url(
                'recruiter/shortlist_view.php?id='
                . $shortlistId
            )
        );

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Add Candidate to Existing Shortlist
    |--------------------------------------------------------------------------
    */

    if ($action === 'add') {

        $shortlistId = (int)(
            $_POST['shortlist_id']
            ?? 0
        );

        /*
        |--------------------------------------------------------------------------
        | Verify Shortlist Ownership
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            SELECT id, name

            FROM recruiter_shortlists

            WHERE id = ?
              AND recruiter_id = ?

            LIMIT 1
        ");

        if (!$stmt) {

            throw new RuntimeException(
                'Unable to verify shortlist ownership: '
                . $conn->error
            );
        }

        $stmt->bind_param(
            'ii',
            $shortlistId,
            $uid
        );

        $stmt->execute();

        $shortlist = $stmt
            ->get_result()
            ->fetch_assoc();

        $stmt->close();

        if (!$shortlist) {

            http_response_code(403);

            exit(
                'Shortlist not found or access denied.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Candidate
        |--------------------------------------------------------------------------
        */

        if (
            !shortlist_candidate_exists(
                $conn,
                $candidateId
            )
        ) {

            rc_flash(
                'danger',
                'Candidate not found or candidate account is inactive.'
            );

            header(
                'Location: '
                . url(
                    'recruiter/shortlists.php'
                )
            );

            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | Verify Consent
        |--------------------------------------------------------------------------
        */

        if (
            !shortlist_candidate_has_consent(
                $conn,
                $candidateId,
                $hasConsents
            )
        ) {

            rc_flash(
                'danger',
                'The candidate cannot be shortlisted because recruiter-discovery consent is not currently valid.'
            );

            header(
                'Location: '
                . url(
                    'recruiter/shortlists.php'
                    . '?candidate_id='
                    . $candidateId
                )
            );

            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | Insert Candidate
        |--------------------------------------------------------------------------
        */

        add_candidate_to_shortlist(
            $conn,
            $shortlistId,
            $candidateId,
            $uid
        );

        rc_activity(
            $conn,
            $uid,
            'candidate_shortlisted',
            'Added candidate to shortlist: '
            . (string)$shortlist['name'],
            'candidate',
            $candidateId
        );

        rc_flash(
            'success',
            'Candidate added to shortlist successfully.'
        );

        header(
            'Location: '
            . url(
                'recruiter/shortlist_view.php?id='
                . $shortlistId
            )
        );

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Load Recruiter's Shortlists
|--------------------------------------------------------------------------
*/

$rows = [];

$stmt = $conn->prepare("
    SELECT
        rs.id,
        rs.recruiter_id,
        rs.name,
        rs.client_name,
        rs.search_criteria_json,
        rs.taxonomy_version,
        rs.status,
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
        rs.recruiter_id,
        rs.name,
        rs.client_name,
        rs.search_criteria_json,
        rs.taxonomy_version,
        rs.status,
        rs.created_at,
        rs.updated_at

    ORDER BY rs.updated_at DESC
");

if (!$stmt) {

    throw new RuntimeException(
        'Unable to load shortlists: '
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
    $rows[] = $row;
}

$stmt->close();

/*
|--------------------------------------------------------------------------
| Current Search Criteria
|--------------------------------------------------------------------------
|
| This now matches the corrected candidate search.
|
| Removed:
| - verification
| - max_age_days
| - scarce_only
|
*/

$criteria = [
    'skill' => trim(
        (string)($_GET['skill'] ?? '')
    ),

    'province' => trim(
        (string)($_GET['province'] ?? '')
    ),

    'city' => trim(
        (string)($_GET['city'] ?? '')
    ),

    'profile_status' => trim(
        (string)(
            $_GET['profile_status']
            ?? ''
        )
    )
];

$taxonomyVersion = trim(
    (string)(
        $_GET['taxonomy_version']
        ?? ''
    )
);

if ($taxonomyVersion === '') {
    $taxonomyVersion =
        rc_taxonomy_version($conn);
}

/*
|--------------------------------------------------------------------------
| Candidate Information
|--------------------------------------------------------------------------
|
| Only used when arriving from Find Candidates.
|
*/

$selectedCandidate = null;
$selectedCandidateConsent = false;

if ($candidateId > 0) {

    $stmt = $conn->prepare("
        SELECT
            u.id,
            u.first_name,
            u.last_name,
            u.professional_title,
            u.province

        FROM users u

        INNER JOIN roles r
            ON r.id = u.role_id

        WHERE u.id = ?
          AND r.slug = 'candidate'
          AND u.status = 'active'

        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param(
            'i',
            $candidateId
        );

        $stmt->execute();

        $selectedCandidate = $stmt
            ->get_result()
            ->fetch_assoc();

        $stmt->close();
    }

    if ($selectedCandidate) {

        $selectedCandidateConsent =
            shortlist_candidate_has_consent(
                $conn,
                $candidateId,
                $hasConsents
            );
    }
}

/*
|--------------------------------------------------------------------------
| Layout
|--------------------------------------------------------------------------
*/

require __DIR__ . '/_layout_start.php';

?>


<!-- ================================================================
     PAGE HEADER
================================================================ -->

<div class="rc-page-header">

    <span class="rc-eyebrow">
        Client Response
    </span>

    <h2>
        Shortlists
    </h2>

    <p>
        Create recruiter-owned candidate shortlists while
        preserving the search criteria and taxonomy version
        used during candidate discovery.
    </p>

</div>


<!-- ================================================================
     SELECTED CANDIDATE
================================================================ -->

<?php if ($candidateId > 0): ?>

    <section class="rc-card rc-space">

        <div class="rc-card__header">

            <div>

                <h3>
                    Selected Candidate
                </h3>

                <p>
                    Candidate selected from talent search.
                </p>

            </div>

        </div>


        <div class="rc-card__body">

            <?php if ($selectedCandidate): ?>

                <?php

                $selectedFirstName = trim(
                    (string)(
                        $selectedCandidate[
                            'first_name'
                        ]
                        ?? ''
                    )
                );

                $selectedLastName = trim(
                    (string)(
                        $selectedCandidate[
                            'last_name'
                        ]
                        ?? ''
                    )
                );

                $selectedName = trim(
                    $selectedFirstName
                    . ' '
                    . $selectedLastName
                );

                if ($selectedName === '') {
                    $selectedName = 'Candidate';
                }

                ?>

                <div class="rc-trust">

                    <div>

                        <i class="fas fa-user"></i>

                        <div>

                            <strong>
                                <?= e($selectedName) ?>
                            </strong>

                            <span>

                                <?= e(
                                    (string)(
                                        $selectedCandidate[
                                            'professional_title'
                                        ]
                                        ?: 'Candidate'
                                    )
                                ) ?>

                            </span>

                        </div>

                    </div>


                    <div>

                        <?php if (
                            $selectedCandidateConsent
                        ): ?>

                            <i
                                class="
                                    fas
                                    fa-user-shield
                                "
                            ></i>

                            <div>

                                <strong>
                                    Recruiter Access
                                </strong>

                                <span>
                                    Consent valid
                                </span>

                            </div>

                        <?php else: ?>

                            <i
                                class="
                                    fas
                                    fa-lock
                                "
                            ></i>

                            <div>

                                <strong>
                                    Recruiter Access
                                </strong>

                                <span>
                                    Consent unavailable
                                </span>

                            </div>

                        <?php endif; ?>

                    </div>

                </div>

            <?php else: ?>

                <div class="rc-empty">

                    <i
                        class="
                            fas
                            fa-triangle-exclamation
                        "
                    ></i>

                    <strong>
                        Candidate unavailable
                    </strong>

                    <span>
                        The selected Candidate account
                        could not be found or is inactive.
                    </span>

                </div>

            <?php endif; ?>

        </div>

    </section>

<?php endif; ?>


<!-- ================================================================
     ADD TO EXISTING SHORTLIST
================================================================ -->

<?php if (
    $candidateId > 0 &&
    $selectedCandidate &&
    $selectedCandidateConsent &&
    $rows
): ?>

<section class="rc-card rc-space">

    <div class="rc-card__header">

        <div>

            <h3>
                Add Candidate to Existing Shortlist
            </h3>

            <p>
                Select one of your existing shortlists.
            </p>

        </div>

    </div>


    <div class="rc-choice">

        <?php foreach ($rows as $shortlist): ?>

            <form method="post">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(rc_csrf()) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="add"
                >

                <input
                    type="hidden"
                    name="candidate_id"
                    value="<?= (int)$candidateId ?>"
                >

                <input
                    type="hidden"
                    name="shortlist_id"
                    value="<?= (int)$shortlist['id'] ?>"
                >


                <div>

                    <strong>
                        <?= e($shortlist['name']) ?>
                    </strong>

                    <span>

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

                        · Taxonomy

                        <?= e(
                            $shortlist[
                                'taxonomy_version'
                            ]
                        ) ?>

                    </span>

                </div>


                <button
                    type="submit"
                    class="
                        rc-btn
                        rc-btn--secondary
                        rc-btn--small
                    "
                >

                    <i class="fas fa-plus"></i>

                    Add

                </button>

            </form>

        <?php endforeach; ?>

    </div>

</section>

<?php endif; ?>


<!-- ================================================================
     CONSENT WARNING
================================================================ -->

<?php if (
    $candidateId > 0 &&
    $selectedCandidate &&
    !$selectedCandidateConsent
): ?>

<section class="rc-card rc-space">

    <div class="rc-card__body">

        <div class="rc-empty">

            <i class="fas fa-lock"></i>

            <strong>
                Candidate cannot currently be shortlisted
            </strong>

            <span>
                Valid recruiter-discovery consent is required
                before this candidate can be added to a
                recruiter shortlist.
            </span>

        </div>

    </div>

</section>

<?php endif; ?>


<!-- ================================================================
     CREATE SHORTLIST
================================================================ -->

<section class="rc-card rc-space">

    <div class="rc-card__header">

        <div>

            <h3>
                Create New Shortlist
            </h3>

            <p>
                Create a new client shortlist using the
                current candidate-search context.
            </p>

        </div>

    </div>


    <form
        method="post"
        class="rc-card__body rc-form-3"
    >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= e(rc_csrf()) ?>"
        >

        <input
            type="hidden"
            name="action"
            value="create"
        >

        <input
            type="hidden"
            name="candidate_id"
            value="<?= (int)$candidateId ?>"
        >

        <input
            type="hidden"
            name="criteria"
            value="<?= e(
                json_encode(
                    $criteria,
                    JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                )
            ) ?>"
        >

        <input
            type="hidden"
            name="taxonomy_version"
            value="<?= e(
                $taxonomyVersion
            ) ?>"
        >


        <!-- Name -->

        <div class="rc-field">

            <label>
                Shortlist Name
            </label>

            <input
                type="text"
                name="name"
                required
                maxlength="150"
                placeholder="Java Cloud - Gauteng"
            >

        </div>


        <!-- Client -->

        <div class="rc-field">

            <label>
                Client
            </label>

            <input
                type="text"
                name="client_name"
                maxlength="150"
                placeholder="Client name"
            >

        </div>


        <!-- Taxonomy -->

        <div class="rc-field">

            <label>
                Taxonomy
            </label>

            <input
                type="text"
                value="<?= e(
                    $taxonomyVersion
                ) ?>"
                readonly
            >

        </div>


        <button
            type="submit"
            class="rc-btn rc-btn--primary"
        >

            <i class="fas fa-plus"></i>

            Create Shortlist

        </button>

    </form>

</section>


<!-- ================================================================
     EXISTING SHORTLISTS
================================================================ -->

<?php if (!$rows): ?>

<section class="rc-card">

    <div class="rc-card__body">

        <div class="rc-empty">

            <i class="fas fa-list-check"></i>

            <strong>
                No shortlists yet
            </strong>

            <span>
                Create your first recruiter shortlist
                using the form above.
            </span>

        </div>

    </div>

</section>


<?php else: ?>


<div class="rc-shortlist-grid">

<?php foreach ($rows as $shortlist): ?>

    <?php

    $candidateCount = (int)(
        $shortlist['candidate_count']
        ?? 0
    );

    ?>

    <article class="rc-shortlist-card">

        <i class="fas fa-list-check"></i>

        <h3>
            <?= e($shortlist['name']) ?>
        </h3>

        <p>
            <?= e(
                (string)(
                    $shortlist['client_name']
                        ?: 'No client specified'
                )
            ) ?>
        </p>


        <div>

            <span>

                <?= number_format(
                    $candidateCount
                ) ?>

                <?= $candidateCount === 1
                    ? 'candidate'
                    : 'candidates' ?>

            </span>


            <span>

                Taxonomy

                <?= e(
                    $shortlist[
                        'taxonomy_version'
                    ]
                ) ?>

            </span>

        </div>


        <div class="rc-chips">

            <b>
                <?= e(
                    rc_label(
                        (string)$shortlist[
                            'status'
                        ]
                    )
                ) ?>
            </b>

        </div>


        <a
            class="rc-btn rc-btn--secondary"
            href="<?= url(
                'recruiter/shortlist_view.php?id='
                . (int)$shortlist['id']
            ) ?>"
        >

            <i class="fas fa-folder-open"></i>

            Open Shortlist

        </a>

    </article>

<?php endforeach; ?>

</div>

<?php endif; ?>


<?php

require __DIR__ . '/_layout_end.php';

?>