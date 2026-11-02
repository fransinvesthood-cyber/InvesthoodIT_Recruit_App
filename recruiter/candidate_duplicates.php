<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('recruiter');

$user        = current_user();
$flashes     = render_flashes();
$currentPage = 'candidate_duplicates';
$pageTitle   = 'Duplicate Candidate Detection';

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

/*
|--------------------------------------------------------------------------
| Search Filter
|--------------------------------------------------------------------------
*/

$filterSearch = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100);

/*
|--------------------------------------------------------------------------
| Fetch Candidates With a Shared Email
|--------------------------------------------------------------------------
|
| Group active candidates by their normalised email address. Any email
| that appears on more than one account is a confirmed duplicate.
| We do the grouping in SQL so only duplicate email groups hit PHP.
|
*/

$hasProfile = rc_has_table($conn, 'candidate_profiles');

if ($hasProfile) {

    $sql = "
        SELECT
            u.id,
            u.first_name,
            u.last_name,
            u.email,
            u.province,
            u.qualification_level,
            u.professional_title AS user_professional_title,

            COALESCE(
                cp.professional_title,
                u.professional_title
            ) AS professional_title,

            cp.city,
            cp.completion_percent,
            cp.created_at AS profile_created_at

        FROM users u

        INNER JOIN roles r
            ON r.id = u.role_id

        LEFT JOIN candidate_profiles cp
            ON cp.user_id = u.id

        WHERE r.slug    = 'candidate'
          AND u.status  = 'active'
          AND u.email  != ''
          AND u.email IS NOT NULL

          /* only rows whose email is shared by another active candidate */
          AND LOWER(TRIM(u.email)) IN (
                SELECT LOWER(TRIM(u2.email))
                FROM   users u2
                INNER JOIN roles r2 ON r2.id = u2.role_id
                WHERE  r2.slug   = 'candidate'
                  AND  u2.status = 'active'
                  AND  u2.email != ''
                  AND  u2.email IS NOT NULL
                GROUP BY LOWER(TRIM(u2.email))
                HAVING COUNT(*) > 1
          )

        ORDER BY
            LOWER(TRIM(u.email)) ASC,
            u.id                 ASC
    ";

} else {

    $sql = "
        SELECT
            u.id,
            u.first_name,
            u.last_name,
            u.email,
            u.province,
            u.qualification_level,
            u.professional_title AS user_professional_title,

            u.professional_title AS professional_title,

            NULL AS city,
            0    AS completion_percent,
            NULL AS profile_created_at

        FROM users u

        INNER JOIN roles r
            ON r.id = u.role_id

        WHERE r.slug    = 'candidate'
          AND u.status  = 'active'
          AND u.email  != ''
          AND u.email IS NOT NULL

          AND LOWER(TRIM(u.email)) IN (
                SELECT LOWER(TRIM(u2.email))
                FROM   users u2
                INNER JOIN roles r2 ON r2.id = u2.role_id
                WHERE  r2.slug   = 'candidate'
                  AND  u2.status = 'active'
                  AND  u2.email != ''
                  AND  u2.email IS NOT NULL
                GROUP BY LOWER(TRIM(u2.email))
                HAVING COUNT(*) > 1
          )

        ORDER BY
            LOWER(TRIM(u.email)) ASC,
            u.id                 ASC
    ";
}

$result = $conn->query($sql);

if (!$result) {
    throw new RuntimeException(
        'Duplicate candidate query failed: ' . $conn->error
    );
}

/*
|--------------------------------------------------------------------------
| Group Rows by Email
|--------------------------------------------------------------------------
*/

$groupsByEmail = [];

while ($row = $result->fetch_assoc()) {
    $key                    = strtolower(trim((string)$row['email']));
    $groupsByEmail[$key][]  = $row;
}

$totalGroups     = count($groupsByEmail);
$totalCandidates = 0;

foreach ($groupsByEmail as $members) {
    $totalCandidates += count($members);
}

/*
|--------------------------------------------------------------------------
| Groups To Display
|--------------------------------------------------------------------------
|
| No search: show only the duplicate groups found above.
|
| With a search: show EVERY active candidate matching the name or email,
| including candidates who are not duplicates. For each match we load all
| accounts sharing that email, so a duplicate group is always shown in
| full. A group with only one account is "not a duplicate".
|
*/

$filteredGroups = $groupsByEmail;

if ($filterSearch !== '') {

    $like = '%' . addcslashes($filterSearch, '%_\\') . '%';

    $profileCols = $hasProfile
        ? "COALESCE(cp.professional_title, u.professional_title) AS professional_title,
           cp.city,
           cp.completion_percent"
        : "u.professional_title AS professional_title,
           NULL AS city,
           0    AS completion_percent";

    $profileJoin = $hasProfile
        ? "LEFT JOIN candidate_profiles cp ON cp.user_id = u.id"
        : "";

    $searchSql = "
        SELECT
            u.id,
            u.first_name,
            u.last_name,
            u.email,
            u.province,
            u.qualification_level,
            {$profileCols}

        FROM users u

        INNER JOIN roles r
            ON r.id = u.role_id

        {$profileJoin}

        WHERE r.slug   = 'candidate'
          AND u.status = 'active'
          AND u.email IS NOT NULL
          AND u.email != ''

          AND LOWER(TRIM(u.email)) IN (
                SELECT LOWER(TRIM(u3.email))
                FROM   users u3
                INNER JOIN roles r3 ON r3.id = u3.role_id
                WHERE  r3.slug   = 'candidate'
                  AND  u3.status = 'active'
                  AND  u3.email IS NOT NULL
                  AND  u3.email != ''
                  AND (
                        u3.first_name LIKE ?
                     OR u3.last_name  LIKE ?
                     OR CONCAT_WS(' ', u3.first_name, u3.last_name) LIKE ?
                     OR u3.email      LIKE ?
                  )
          )

        ORDER BY
            LOWER(TRIM(u.email)) ASC,
            u.id                 ASC
    ";

    $stmt = $conn->prepare($searchSql);

    if (!$stmt) {
        throw new RuntimeException(
            'Candidate search query failed: ' . $conn->error
        );
    }

    $stmt->bind_param('ssss', $like, $like, $like, $like);
    $stmt->execute();
    $searchResult = $stmt->get_result();

    $filteredGroups = [];

    while ($row = $searchResult->fetch_assoc()) {
        $key = strtolower(trim((string)$row['email']));
        $filteredGroups[$key][] = $row;
    }

    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| Consent Status (batch, one query)
|--------------------------------------------------------------------------
*/

$consentMap  = [];
$candidateIds = [];

foreach ($filteredGroups as $members) {
    foreach ($members as $m) {
        $candidateIds[] = (int)$m['id'];
    }
}

if (
    !empty($candidateIds) &&
    rc_has_table($conn, 'candidate_consents')
) {
    $ids    = implode(',', $candidateIds);
    $conSql = "
        SELECT
            candidate_id,
            status,
            expires_at
        FROM candidate_consents
        WHERE candidate_id IN ({$ids})
          AND consent_type = 'recruiter_discovery'
        ORDER BY
            (status = 'consented') DESC,
            (expires_at IS NULL)   DESC,
            expires_at DESC
    ";

    $conResult = $conn->query($conSql);

    if ($conResult) {
        while ($row = $conResult->fetch_assoc()) {
            $cid = (int)$row['candidate_id'];
            if (isset($consentMap[$cid])) {
                continue;
            }
            $expired = (
                !empty($row['expires_at']) &&
                strtotime((string)$row['expires_at']) <= time()
            );
            $consentMap[$cid] = [
                'status' => (string)$row['status'],
                'valid'  => ($row['status'] === 'consented') && !$expired,
            ];
        }
    }
}

$displayCount = count($filteredGroups);

/*
|--------------------------------------------------------------------------
| Audit
|--------------------------------------------------------------------------
*/

rc_activity(
    $conn,
    $recruiterId,
    'duplicate_detection_viewed',
    'Viewed the Candidate Duplicate Detection page.',
    'recruiter',
    $recruiterId
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

.rc-dd-hero-stats {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 20px;
}

.rc-dd-stat {
    display: flex;
    flex-direction: column;
    gap: 4px;
    background: rgba(127,127,127,.07);
    border-radius: 10px;
    padding: 14px 20px;
    min-width: 120px;
}

.rc-dd-stat strong {
    font-size: 1.6rem;
    font-weight: 700;
    line-height: 1;
}

.rc-dd-stat span {
    font-size: .8rem;
    opacity: .65;
}

.rc-dd-stat--alert strong { color: #d24b4b; }

.rc-dd-filters {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
}

.rc-dd-filters form {
    display: contents;
}

.rc-dd-filters input[type="search"] {
    flex: 1 1 200px;
    min-width: 0;
}

.rc-dd-group {
    border: 1px solid rgba(210,75,75,.30);
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 16px;
    background: rgba(127,127,127,.05);
    color: inherit;
}

.rc-dd-group__header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 13px 18px;
    background: rgba(210,75,75,.06);
    border-bottom: 1px solid rgba(210,75,75,.15);
    flex-wrap: wrap;
}

.rc-dd-group--ok {
    border-color: rgba(34,160,90,.35);
}

.rc-dd-group--ok .rc-dd-group__header {
    background: rgba(34,160,90,.07);
    border-bottom-color: rgba(34,160,90,.18);
}

.rc-dd-group--ok .rc-dd-group__header i {
    color: #22a05a;
}

.rc-dd-group__note {
    font-size: .8rem;
    opacity: .65;
}

/* Dark mode: stronger tints and brighter accents so red/green stay visible */
[data-theme="dark"] .rc-dd-group,
[data-bs-theme="dark"] .rc-dd-group,
html.dark .rc-dd-group,
body.dark .rc-dd-group,
body.dark-mode .rc-dd-group,
body.dark-theme .rc-dd-group,
body.ux-dark .rc-dd-group {
    border-color: rgba(255,120,120,.45);
    background: rgba(255,255,255,.04);
}

[data-theme="dark"] .rc-dd-group__header,
[data-bs-theme="dark"] .rc-dd-group__header,
html.dark .rc-dd-group__header,
body.dark .rc-dd-group__header,
body.dark-mode .rc-dd-group__header,
body.dark-theme .rc-dd-group__header,
body.ux-dark .rc-dd-group__header {
    background: rgba(255,120,120,.14);
    border-bottom-color: rgba(255,120,120,.28);
}

[data-theme="dark"] .rc-dd-group__header i,
[data-bs-theme="dark"] .rc-dd-group__header i,
html.dark .rc-dd-group__header i,
body.dark .rc-dd-group__header i,
body.dark-mode .rc-dd-group__header i,
body.dark-theme .rc-dd-group__header i,
body.ux-dark .rc-dd-group__header i {
    color: #ff7b7b;
}

[data-theme="dark"] .rc-dd-group--ok,
[data-bs-theme="dark"] .rc-dd-group--ok,
html.dark .rc-dd-group--ok,
body.dark .rc-dd-group--ok,
body.dark-mode .rc-dd-group--ok,
body.dark-theme .rc-dd-group--ok,
body.ux-dark .rc-dd-group--ok {
    border-color: rgba(70,200,130,.5);
}

[data-theme="dark"] .rc-dd-group--ok .rc-dd-group__header,
[data-bs-theme="dark"] .rc-dd-group--ok .rc-dd-group__header,
html.dark .rc-dd-group--ok .rc-dd-group__header,
body.dark .rc-dd-group--ok .rc-dd-group__header,
body.dark-mode .rc-dd-group--ok .rc-dd-group__header,
body.dark-theme .rc-dd-group--ok .rc-dd-group__header,
body.ux-dark .rc-dd-group--ok .rc-dd-group__header {
    background: rgba(70,200,130,.16);
    border-bottom-color: rgba(70,200,130,.3);
}

[data-theme="dark"] .rc-dd-group--ok .rc-dd-group__header i,
[data-bs-theme="dark"] .rc-dd-group--ok .rc-dd-group__header i,
html.dark .rc-dd-group--ok .rc-dd-group__header i,
body.dark .rc-dd-group--ok .rc-dd-group__header i,
body.dark-mode .rc-dd-group--ok .rc-dd-group__header i,
body.dark-theme .rc-dd-group--ok .rc-dd-group__header i,
body.ux-dark .rc-dd-group--ok .rc-dd-group__header i {
    color: #4cd48c;
}

[data-theme="dark"] .rc-dd-stat--alert strong,
[data-bs-theme="dark"] .rc-dd-stat--alert strong,
html.dark .rc-dd-stat--alert strong,
body.dark .rc-dd-stat--alert strong,
body.dark-mode .rc-dd-stat--alert strong,
body.dark-theme .rc-dd-stat--alert strong,
body.ux-dark .rc-dd-stat--alert strong {
    color: #ff7b7b;
}

.rc-dd-group__header strong {
    flex: 1 1 auto;
    font-size: .9rem;
    word-break: break-all;
}

.rc-dd-group__header i {
    color: #d24b4b;
    font-size: 1rem;
}

.rc-dd-members {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 0;
}

.rc-dd-member {
    padding: 16px 18px;
    border-right: 1px solid rgba(127,127,127,.10);
    border-bottom: 1px solid rgba(127,127,127,.10);
}

.rc-dd-member:last-child {
    border-right: none;
}

.rc-dd-member__name {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
}

.rc-dd-member__name div {
    min-width: 0;
}

.rc-dd-member__name strong {
    display: block;
    font-size: .92rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.rc-dd-member__name span {
    display: block;
    font-size: .78rem;
    opacity: .6;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.rc-dd-meta {
    display: flex;
    flex-direction: column;
    gap: 5px;
    font-size: .82rem;
}

.rc-dd-meta div {
    display: flex;
    align-items: baseline;
    gap: 8px;
}

.rc-dd-meta span {
    opacity: .55;
    width: 86px;
    flex-shrink: 0;
    font-size: .78rem;
}

.rc-dd-meta strong {
    font-weight: 600;
    word-break: break-word;
}

.rc-dd-group__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 11px 18px;
    border-top: 1px solid rgba(127,127,127,.10);
    background: rgba(127,127,127,.03);
}

.rc-dd-empty {
    text-align: center;
    padding: 56px 24px;
    opacity: .6;
}

.rc-dd-empty i {
    font-size: 2.4rem;
    display: block;
    margin-bottom: 14px;
    opacity: .45;
}

.rc-dd-empty strong {
    display: block;
    font-size: 1.05rem;
    margin-bottom: 6px;
}

.rc-dd-empty span {
    font-size: .87rem;
}

</style>


<!-- ================================================================
     HERO
================================================================ -->

<div class="rc-profile-hero">

    <div class="rc-avatar rc-avatar--xl">
        <i class="fas fa-user-group"></i>
    </div>

    <div class="rc-profile-copy">

        <span class="rc-eyebrow">
            <i class="fas fa-copy"></i>
            Duplicate Detection
        </span>

        <h2>
            Duplicate Candidate Detection
        </h2>

        <p>
            Candidate accounts sharing the same email address are
            shown here. Each group represents a confirmed duplicate.
        </p>

        <div class="rc-dd-hero-stats">

            <div class="rc-dd-stat <?= $totalGroups > 0 ? 'rc-dd-stat--alert' : '' ?>">
                <strong><?= number_format($totalGroups) ?></strong>
                <span>Duplicate <?= $totalGroups === 1 ? 'group' : 'groups' ?></span>
            </div>

            <div class="rc-dd-stat">
                <strong><?= number_format($totalCandidates) ?></strong>
                <span>Affected accounts</span>
            </div>

        </div>

    </div>

</div>


<!-- ================================================================
     SEARCH FILTER
================================================================ -->

<div class="rc-dd-filters">

    <form
        method="get"
        action="<?= url('recruiter/candidate_duplicates.php') ?>"
    >

        <input
            type="search"
            name="q"
            class="rc-input"
            placeholder="Search by name or email&hellip;"
            value="<?= e($filterSearch) ?>"
            aria-label="Search duplicate groups"
        >

        <button
            type="submit"
            class="rc-btn rc-btn--primary"
        >
            <i class="fas fa-magnifying-glass"></i>
            Search
        </button>

        <?php if ($filterSearch !== ''): ?>

            <a
                class="rc-btn rc-btn--secondary"
                href="<?= url('recruiter/candidate_duplicates.php') ?>"
            >
                <i class="fas fa-xmark"></i>
                Clear
            </a>

        <?php endif; ?>

    </form>

</div>


<!-- ================================================================
     RESULTS
================================================================ -->

<?php if ($filterSearch !== ''): ?>

    <p style="margin-bottom:12px; opacity:.7; font-size:.87rem;">
        <?= number_format($displayCount) ?>
        <?= $displayCount === 1 ? 'result' : 'results' ?>
        matching
        <strong>&ldquo;<?= e($filterSearch) ?>&rdquo;</strong>
        &mdash; candidates with a unique email are marked
        <span class="rc-status rc-status--success">No duplicate</span>
    </p>

<?php else: ?>

    <p style="margin-bottom:12px; opacity:.7; font-size:.87rem;">
        Showing duplicate accounts only. Search by name or email to
        check any candidate, including those who are not duplicates.
    </p>

<?php endif; ?>


<?php if ($displayCount === 0 && $filterSearch === ''): ?>

    <div class="rc-card rc-space">
        <div class="rc-dd-empty">
            <i class="fas fa-circle-check"></i>
            <strong>No duplicate emails found</strong>
            <span>
                Every active candidate account has a unique email address.
            </span>
        </div>
    </div>

<?php elseif ($displayCount === 0): ?>

    <div class="rc-card rc-space">
        <div class="rc-dd-empty">
            <i class="fas fa-magnifying-glass"></i>
            <strong>No candidates match your search</strong>
            <span>Try a different name or email address.</span>
        </div>
    </div>

<?php else: ?>

    <?php foreach ($filteredGroups as $email => $members):

        $isDuplicate = count($members) > 1;

    ?>

        <div class="rc-dd-group <?= $isDuplicate ? '' : 'rc-dd-group--ok' ?>">


            <!-- Group header -->

            <div class="rc-dd-group__header">

                <?php if ($isDuplicate): ?>

                    <i class="fas fa-triangle-exclamation"></i>

                    <strong><?= e($email) ?></strong>

                    <span class="rc-status rc-status--danger">
                        Duplicate &middot; <?= count($members) ?> accounts
                    </span>

                <?php else: ?>

                    <i class="fas fa-circle-check"></i>

                    <strong><?= e($email) ?></strong>

                    <span class="rc-dd-group__note">
                        This email is used by only one account.
                    </span>

                    <span class="rc-status rc-status--success">
                        No duplicate
                    </span>

                <?php endif; ?>

            </div>


            <!-- Member profiles -->

            <div class="rc-dd-members">

                <?php foreach ($members as $m):

                    $mid  = (int)$m['id'];

                    $name = trim(
                        (string)($m['first_name'] ?? '') . ' ' .
                        (string)($m['last_name']  ?? '')
                    );

                    if ($name === '') {
                        $name = 'Candidate #' . $mid;
                    }

                    $title = trim((string)($m['professional_title'] ?? ''));

                    $consent = $consentMap[$mid] ?? ['valid' => false, 'status' => 'none'];

                ?>

                    <div class="rc-dd-member">

                        <div class="rc-dd-member__name">

                            <div class="rc-avatar">
                                <?= e(
                                    rc_initials(
                                        (string)($m['first_name'] ?? ''),
                                        (string)($m['last_name']  ?? '')
                                    )
                                ) ?>
                            </div>

                            <div>
                                <strong><?= e($name) ?></strong>
                                <span>
                                    <?= e($title !== '' ? $title : 'No title') ?>
                                </span>
                            </div>

                        </div>

                        <div class="rc-dd-meta">

                            <div>
                                <span>Candidate ID</span>
                                <strong>#<?= $mid ?></strong>
                            </div>

                            <div>
                                <span>Province</span>
                                <strong>
                                    <?= e(
                                        !empty($m['province'])
                                            ? rc_label($m['province'])
                                            : 'Not provided'
                                    ) ?>
                                </strong>
                            </div>

                            <div>
                                <span>Qualification</span>
                                <strong>
                                    <?= e(
                                        !empty($m['qualification_level'])
                                            ? rc_label($m['qualification_level'])
                                            : 'Not provided'
                                    ) ?>
                                </strong>
                            </div>

                            <?php if ($hasProfile && !empty($m['completion_percent'])): ?>

                                <div>
                                    <span>Profile</span>
                                    <strong>
                                        <?= number_format((int)$m['completion_percent']) ?>% complete
                                    </strong>
                                </div>

                            <?php endif; ?>

                            <div>
                                <span>Consent</span>
                                <strong>
                                    <span class="rc-status <?= $consent['valid'] ? 'rc-status--success' : 'rc-status--danger' ?>">
                                        <?= $consent['valid'] ? 'Valid' : 'Unavailable' ?>
                                    </span>
                                </strong>
                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>


            <!-- Actions -->

            <div class="rc-dd-group__actions">

                <?php foreach ($members as $m):

                    $mid  = (int)$m['id'];
                    $name = trim(
                        (string)($m['first_name'] ?? '') . ' ' .
                        (string)($m['last_name']  ?? '')
                    );

                    if ($name === '') {
                        $name = 'Candidate #' . $mid;
                    }

                    $consent = $consentMap[$mid] ?? ['valid' => false, 'status' => 'none'];

                    if (!$consent['valid']) {
                        continue;
                    }

                ?>

                    <a
                        class="rc-btn rc-btn--secondary rc-btn--small"
                        href="<?= url('recruiter/candidate_view.php?id=' . $mid) ?>"
                    >
                        <i class="fas fa-user"></i>
                        View <?= e($name) ?>
                    </a>

                <?php endforeach; ?>

            </div>

        </div>

    <?php endforeach; ?>

<?php endif; ?>


<?php

require __DIR__ . '/_layout_end.php';

?>
