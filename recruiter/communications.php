<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('recruiter');

$user        = current_user();
$flashes     = render_flashes();
$currentPage = 'communications';
$pageTitle   = 'Communication & Follow-Up History';

require_once __DIR__ . '/_helpers.php';

$conn = Database::getConnection();

$recruiterId = (int)($user['id'] ?? $user['user_id'] ?? 0);

if ($recruiterId <= 0) {
    http_response_code(403);
    exit('Invalid Recruiter account.');
}

/* ==================================================================
 | Constants
 * ==================================================================*/

const CM_CHANNELS = [
    'email'     => 'Email',
    'phone'     => 'Phone call',
    'sms'       => 'SMS',
    'whatsapp'  => 'WhatsApp',
    'video'     => 'Video call',
    'in_person' => 'In person',
    'linkedin'  => 'LinkedIn message',
    'other'     => 'Other',
];

const CM_CHANNEL_ICONS = [
    'email'     => 'fa-envelope',
    'phone'     => 'fa-phone',
    'sms'       => 'fa-comment-dots',
    'whatsapp'  => 'fa-comments',
    'video'     => 'fa-video',
    'in_person' => 'fa-handshake',
    'linkedin'  => 'fa-user-group',
    'other'     => 'fa-ellipsis',
];

const CM_DIRECTIONS = [
    'outbound' => 'Outbound (we contacted the candidate)',
    'inbound'  => 'Inbound (the candidate contacted us)',
];

const CM_DIRECTION_SHORT = [
    'outbound' => 'Outbound',
    'inbound'  => 'Inbound',
];

const CM_OUTCOMES = [
    'interested'         => 'Interested / positive',
    'info_provided'      => 'Information shared',
    'callback_requested' => 'Callback requested',
    'interview_arranged' => 'Interview arranged',
    'offer_discussed'    => 'Offer discussed',
    'left_message'       => 'Left message',
    'no_response'        => 'No response',
    'not_interested'     => 'Not interested',
    'unreachable'        => 'Unreachable / wrong details',
    'other'              => 'Other',
];

const CM_OUTCOME_GOOD = ['interested', 'interview_arranged', 'offer_discussed'];
const CM_OUTCOME_BAD  = ['not_interested', 'unreachable'];

const CM_FOLLOW_STATUSES = [
    'pending'   => 'Pending',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
    'none'      => 'No follow-up',
];

const CM_PER_PAGE = 20;

// Applications in these states are finished (or not yet submitted): no engagement needed.
const CM_CLOSED_APP_STATUSES = ['draft', 'offer_accepted', 'offer_declined', 'rejected', 'withdrawn'];
const CM_STALE_OPTIONS       = [3, 7, 14, 30];
const CM_NEEDS_LIMIT         = 25;

/* ==================================================================
 | Helpers (page-local, all prefixed cm_)
 * ==================================================================*/

function cm_ensure_tables(mysqli $conn, ?string &$error): bool
{
    try {
        if (!rc_has_table($conn, 'recruiter_candidate_communications')) {
            $ok = $conn->query(
                "CREATE TABLE IF NOT EXISTS recruiter_candidate_communications (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    recruiter_id INT UNSIGNED NOT NULL,
                    candidate_id INT UNSIGNED NOT NULL,
                    application_id INT UNSIGNED NULL,
                    channel VARCHAR(20) NOT NULL DEFAULT 'email',
                    direction VARCHAR(10) NOT NULL DEFAULT 'outbound',
                    subject VARCHAR(160) NOT NULL DEFAULT '',
                    summary TEXT NOT NULL,
                    outcome VARCHAR(30) NOT NULL DEFAULT 'other',
                    occurred_at DATETIME NOT NULL,
                    follow_up_at DATETIME NULL,
                    follow_up_note VARCHAR(255) NOT NULL DEFAULT '',
                    follow_up_status VARCHAR(10) NOT NULL DEFAULT 'none',
                    follow_up_closed_at DATETIME NULL,
                    follow_up_close_note VARCHAR(255) NOT NULL DEFAULT '',
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_rcc_candidate (candidate_id, occurred_at),
                    KEY idx_rcc_recruiter (recruiter_id, occurred_at),
                    KEY idx_rcc_followup (recruiter_id, follow_up_status, follow_up_at),
                    KEY idx_rcc_application (application_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            if (!$ok) {
                $error = $conn->error;
                return false;
            }
        }
    } catch (Throwable $t) {
        $error = $t->getMessage();
        return false;
    }

    return true;
}

/** Runs a prepared SELECT and returns every row as an associative array. */
function cm_fetch_all(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException('Query failed: ' . $conn->error);
    }

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $res  = $stmt->get_result();
    $rows = [];

    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }

    $stmt->close();

    return $rows;
}

/**
 * SQL condition for "a recruiter may work with this candidate":
 * valid recruiter-discovery consent OR a submitted (non-draft) application.
 * Returns null when neither table exists, so nothing is ever exposed.
 */
function cm_eligibility_sql(mysqli $conn, string $alias): ?string
{
    $parts = [];

    if (rc_has_table($conn, 'candidate_consents')) {
        $parts[] = "EXISTS (SELECT 1 FROM candidate_consents cc
                            WHERE cc.candidate_id = {$alias}.id
                              AND cc.consent_type = 'recruiter_discovery'
                              AND cc.status = 'consented'
                              AND (cc.expires_at IS NULL OR cc.expires_at > NOW()))";
    }

    if (rc_has_table($conn, 'applications')) {
        $parts[] = "EXISTS (SELECT 1 FROM applications ap
                            WHERE ap.candidate_id = {$alias}.id
                              AND ap.status <> 'draft')";
    }

    return $parts ? '(' . implode(' OR ', $parts) . ')' : null;
}

/** Active candidates a recruiter may log communication for (optionally just one). */
function cm_candidate_list(mysqli $conn, ?int $onlyId = null): array
{
    $elig = cm_eligibility_sql($conn, 'u');

    if ($elig === null) {
        return [];
    }

    $sql = "SELECT u.id, u.first_name, u.last_name, u.email, u.phone
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            WHERE r.slug = 'candidate'
              AND u.status = 'active'
              AND {$elig}";

    $types  = '';
    $params = [];

    if ($onlyId !== null) {
        $sql    .= ' AND u.id = ?';
        $types   = 'i';
        $params  = [$onlyId];
    }

    $sql .= ' ORDER BY u.first_name ASC, u.last_name ASC LIMIT 1000';

    return cm_fetch_all($conn, $sql, $types, $params);
}

/** Parses an <input type="datetime-local"> value. Returns Y-m-d H:i:s or null. */
function cm_parse_datetime(string $raw): ?string
{
    $raw = trim($raw);

    foreach (['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i', 'Y-m-d H:i:s'] as $fmt) {
        $dt  = DateTime::createFromFormat($fmt, $raw);
        $err = DateTime::getLastErrors();

        if ($dt && (!$err || ($err['warning_count'] === 0 && $err['error_count'] === 0))) {
            return $dt->format('Y-m-d H:i:s');
        }
    }

    return null;
}

function cm_fmt(?string $dt, string $format = 'D, d M Y · H:i'): string
{
    if (!$dt) {
        return '—';
    }

    $ts = strtotime($dt);

    return $ts ? date($format, $ts) : '—';
}

function cm_person(?string $first, ?string $last, string $fallback): string
{
    $name = trim((string)$first . ' ' . (string)$last);

    return $name !== '' ? $name : $fallback;
}

/** Returns [css class, label] describing how due a pending follow-up is. */
function cm_due_state(string $dueAt, string $now): array
{
    $due = strtotime($dueAt);
    $cur = strtotime($now);

    if (!$due || !$cur) {
        return ['cm-pill--muted', 'Scheduled'];
    }

    if ($due < $cur) {
        $days = (int)floor(($cur - $due) / 86400);

        return [
            'cm-pill--danger',
            $days >= 1 ? 'Overdue by ' . $days . ($days === 1 ? ' day' : ' days') : 'Overdue',
        ];
    }

    $dayDiff = (int)round(
        (strtotime(date('Y-m-d', $due)) - strtotime(date('Y-m-d', $cur))) / 86400
    );

    if ($dayDiff <= 0) {
        return ['cm-pill--warn', 'Due today'];
    }

    if ($dayDiff === 1) {
        return ['cm-pill--warn', 'Due tomorrow'];
    }

    return ['cm-pill--ok', 'Due in ' . $dayDiff . ' days'];
}

function cm_like(string $term): string
{
    return '%' . addcslashes($term, '%_\\') . '%';
}

/* ==================================================================
 | Schema + shared state
 * ==================================================================*/

$schemaError = null;
$schemaReady = cm_ensure_tables($conn, $schemaError);

$hasApps = rc_has_table($conn, 'applications');
$hasOpps = $hasApps && rc_has_table($conn, 'opportunities');

$now        = date('Y-m-d H:i:s');
$nowLocal   = date('Y-m-d\TH:i');
$tomorrow   = date('Y-m-d 00:00:00', strtotime('+1 day'));
$thirtyAgo  = date('Y-m-d H:i:s', strtotime('-30 days'));

/* ==================================================================
 | POST handling (post → redirect → get)
 * ==================================================================*/

if ($schemaReady && $_SERVER['REQUEST_METHOD'] === 'POST') {

    rc_verify_csrf();

    $postAction = trim((string)($_POST['action'] ?? ''));
    $focusPost  = (int)($_POST['focus_id'] ?? 0);
    $redirect   = url('recruiter/communications.php' . ($focusPost > 0 ? '?candidate_id=' . $focusPost : ''));

    try {

        /* -------------------- Log an interaction -------------------- */
        if ($postAction === 'log') {

            $candidateId   = (int)($_POST['candidate_id'] ?? 0);
            $applicationId = (int)($_POST['application_id'] ?? 0);
            $channel       = (string)($_POST['channel'] ?? '');
            $direction     = (string)($_POST['direction'] ?? '');
            $outcome       = (string)($_POST['outcome'] ?? '');
            $subject       = trim((string)($_POST['subject'] ?? ''));
            $summary       = trim((string)($_POST['summary'] ?? ''));
            $occurredAt    = cm_parse_datetime((string)($_POST['occurred_at'] ?? ''));
            $followRaw     = trim((string)($_POST['follow_up_at'] ?? ''));
            $followNote    = trim((string)($_POST['follow_up_note'] ?? ''));
            $resolveOld    = !empty($_POST['resolve_pending']);
            $followAt      = null;

            $errors = [];

            $candidateRows = $candidateId > 0 ? cm_candidate_list($conn, $candidateId) : [];

            if (!$candidateRows) {
                $errors[] = 'Please choose a valid candidate.';
            }

            if ($applicationId > 0) {
                $validApp = false;

                if ($hasApps && $candidateRows) {
                    $found = cm_fetch_all(
                        $conn,
                        "SELECT id FROM applications WHERE id = ? AND candidate_id = ? AND status <> 'draft' LIMIT 1",
                        'ii',
                        [$applicationId, $candidateId]
                    );
                    $validApp = (bool)$found;
                }

                if (!$validApp) {
                    $errors[] = 'The selected application does not belong to this candidate.';
                }
            }

            if (!isset(CM_CHANNELS[$channel]))     $errors[] = 'Please choose a communication channel.';
            if (!isset(CM_DIRECTIONS[$direction])) $errors[] = 'Please choose who initiated the contact.';
            if (!isset(CM_OUTCOMES[$outcome]))     $errors[] = 'Please choose an outcome.';

            if ($occurredAt === null) {
                $errors[] = 'Please enter a valid date and time for the interaction.';
            } elseif ($occurredAt > $now) {
                $errors[] = 'The interaction date cannot be in the future.';
            }

            if ($summary === '')                    $errors[] = 'Please add a summary of the conversation.';
            if (mb_strlen($summary) > 5000)         $errors[] = 'The summary is too long (5000 characters max).';
            if (mb_strlen($subject) > 160)          $errors[] = 'The subject is too long (160 characters max).';
            if (mb_strlen($followNote) > 255)       $errors[] = 'The follow-up note is too long (255 characters max).';

            if ($followRaw !== '') {
                $followAt = cm_parse_datetime($followRaw);

                if ($followAt === null) {
                    $errors[] = 'Please enter a valid follow-up date and time.';
                } elseif ($followAt <= $now) {
                    $errors[] = 'The follow-up must be scheduled in the future.';
                }
            } elseif ($followNote !== '') {
                $errors[] = 'Add a follow-up date or clear the follow-up note.';
            }

            if ($errors) {
                rc_flash('danger', implode(' ', $errors));
            } else {
                $followStatus = $followAt !== null ? 'pending' : 'none';
                $appIdValue   = $applicationId > 0 ? $applicationId : null;
                $followNote   = $followAt !== null ? $followNote : '';

                $conn->begin_transaction();

                try {
                    $stmt = $conn->prepare(
                        'INSERT INTO recruiter_candidate_communications
                            (recruiter_id, candidate_id, application_id, channel, direction,
                             subject, summary, outcome, occurred_at,
                             follow_up_at, follow_up_note, follow_up_status)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );

                    if (!$stmt) {
                        throw new RuntimeException('Prepare failed: ' . $conn->error);
                    }

                    $stmt->bind_param(
                        'iiisssssssss',
                        $recruiterId, $candidateId, $appIdValue, $channel, $direction,
                        $subject, $summary, $outcome, $occurredAt,
                        $followAt, $followNote, $followStatus
                    );
                    $stmt->execute();
                    $newId = (int)$conn->insert_id;
                    $stmt->close();

                    if ($resolveOld) {
                        $closeNote = 'Closed by interaction #' . $newId;

                        $stmt = $conn->prepare(
                            "UPDATE recruiter_candidate_communications
                             SET follow_up_status = 'completed',
                                 follow_up_closed_at = ?,
                                 follow_up_close_note = ?
                             WHERE recruiter_id = ?
                               AND candidate_id = ?
                               AND follow_up_status = 'pending'
                               AND id <> ?"
                        );

                        if (!$stmt) {
                            throw new RuntimeException('Prepare failed: ' . $conn->error);
                        }

                        $stmt->bind_param('ssiii', $now, $closeNote, $recruiterId, $candidateId, $newId);
                        $stmt->execute();
                        $stmt->close();
                    }

                    $conn->commit();
                } catch (Throwable $t) {
                    $conn->rollback();
                    throw $t;
                }

                rc_activity(
                    $conn, $recruiterId, 'candidate_communication_logged',
                    'Logged a ' . strtolower(CM_CHANNELS[$channel]) . ' interaction with candidate #' . $candidateId,
                    'candidate_communication', $newId
                );

                $msg = 'Interaction logged.';

                if ($followAt !== null) {
                    $msg .= ' Follow-up scheduled for ' . cm_fmt($followAt) . '.';
                }

                rc_flash('success', $msg);
            }
        }

        /* -------------------- Complete / cancel a follow-up -------------------- */
        elseif ($postAction === 'complete_follow_up' || $postAction === 'cancel_follow_up') {

            $id       = (int)($_POST['follow_up_id'] ?? 0);
            $note     = trim((string)($_POST['close_note'] ?? ''));
            $complete = $postAction === 'complete_follow_up';

            $owned = $id > 0 ? cm_fetch_all(
                $conn,
                "SELECT id, candidate_id FROM recruiter_candidate_communications
                 WHERE id = ? AND recruiter_id = ? AND follow_up_status = 'pending' LIMIT 1",
                'ii',
                [$id, $recruiterId]
            ) : [];

            if (!$owned) {
                rc_flash('danger', 'That follow-up was not found, is not yours, or is already closed.');
            } elseif (mb_strlen($note) > 255) {
                rc_flash('danger', 'The note is too long (255 characters max).');
            } else {
                $newStatus = $complete ? 'completed' : 'cancelled';

                $stmt = $conn->prepare(
                    "UPDATE recruiter_candidate_communications
                     SET follow_up_status = ?, follow_up_closed_at = ?, follow_up_close_note = ?
                     WHERE id = ? AND recruiter_id = ? AND follow_up_status = 'pending'"
                );

                if (!$stmt) {
                    throw new RuntimeException('Prepare failed: ' . $conn->error);
                }

                $stmt->bind_param('sssii', $newStatus, $now, $note, $id, $recruiterId);
                $stmt->execute();
                $stmt->close();

                rc_activity(
                    $conn, $recruiterId,
                    $complete ? 'candidate_follow_up_completed' : 'candidate_follow_up_cancelled',
                    ($complete ? 'Completed' : 'Cancelled') . ' follow-up for candidate #' . (int)$owned[0]['candidate_id'],
                    'candidate_communication', $id
                );

                rc_flash('success', $complete ? 'Follow-up marked as completed.' : 'Follow-up cancelled.');
            }
        }

        /* -------------------- Reschedule a follow-up -------------------- */
        elseif ($postAction === 'reschedule_follow_up') {

            $id     = (int)($_POST['follow_up_id'] ?? 0);
            $when   = cm_parse_datetime((string)($_POST['follow_up_at'] ?? ''));
            $note   = trim((string)($_POST['follow_up_note'] ?? ''));

            $owned = $id > 0 ? cm_fetch_all(
                $conn,
                "SELECT id, candidate_id FROM recruiter_candidate_communications
                 WHERE id = ? AND recruiter_id = ? AND follow_up_status = 'pending' LIMIT 1",
                'ii',
                [$id, $recruiterId]
            ) : [];

            if (!$owned) {
                rc_flash('danger', 'That follow-up was not found, is not yours, or is already closed.');
            } elseif ($when === null) {
                rc_flash('danger', 'Please enter a valid follow-up date and time.');
            } elseif ($when <= $now) {
                rc_flash('danger', 'The follow-up must be scheduled in the future.');
            } elseif (mb_strlen($note) > 255) {
                rc_flash('danger', 'The follow-up note is too long (255 characters max).');
            } else {
                $stmt = $conn->prepare(
                    "UPDATE recruiter_candidate_communications
                     SET follow_up_at = ?, follow_up_note = ?
                     WHERE id = ? AND recruiter_id = ? AND follow_up_status = 'pending'"
                );

                if (!$stmt) {
                    throw new RuntimeException('Prepare failed: ' . $conn->error);
                }

                $stmt->bind_param('ssii', $when, $note, $id, $recruiterId);
                $stmt->execute();
                $stmt->close();

                rc_activity(
                    $conn, $recruiterId, 'candidate_follow_up_rescheduled',
                    'Rescheduled follow-up for candidate #' . (int)$owned[0]['candidate_id'] . ' to ' . $when,
                    'candidate_communication', $id
                );

                rc_flash('success', 'Follow-up rescheduled to ' . cm_fmt($when) . '.');
            }
        }

        else {
            rc_flash('danger', 'Unknown action.');
        }

    } catch (Throwable $t) {
        error_log('communications.php: ' . $t->getMessage());
        rc_flash('danger', 'Something went wrong while saving. Please try again.');
    }

    header('Location: ' . $redirect);
    exit;
}

/* ==================================================================
 | Read state: filters, candidate focus, queue, history, stats
 * ==================================================================*/

$loadError   = null;
$focusNotice = '';

$focusId = (int)($_GET['candidate_id'] ?? 0);
$focus   = null;

$q          = trim((string)($_GET['q'] ?? ''));
$fChannel   = (string)($_GET['channel'] ?? '');
$fOutcome   = (string)($_GET['outcome'] ?? '');
$fFollow    = (string)($_GET['fu'] ?? '');
$scope      = (string)($_GET['scope'] ?? 'mine');
$page       = max(1, (int)($_GET['page'] ?? 1));
$staleDays  = (int)($_GET['stale'] ?? 7);
if (!in_array($staleDays, CM_STALE_OPTIONS, true)) $staleDays = 7;

if (!isset(CM_CHANNELS[$fChannel]))        $fChannel = '';
if (!isset(CM_OUTCOMES[$fOutcome]))        $fOutcome = '';
if (!isset(CM_FOLLOW_STATUSES[$fFollow]))  $fFollow  = '';
if ($scope !== 'all')                      $scope    = 'mine';

$candidates   = [];
$applications = [];
$queue        = [];
$history      = [];
$needs        = [];
$needsError   = null;
$needsTotal   = 0;
$totalRows    = 0;
$totalPages   = 1;
$stats        = ['overdue' => 0, 'today' => 0, 'upcoming' => 0, 'logged' => 0];

if ($schemaReady) {
    try {

        $candidates = cm_candidate_list($conn);

        if ($focusId > 0) {
            $found = cm_candidate_list($conn, $focusId);
            $focus = $found[0] ?? null;

            if (!$focus) {
                $focusNotice = 'That candidate is not available (inactive, or no valid consent / submitted application).';
                $focusId     = 0;
            }
        }

        $candidateIdSet = [];
        foreach ($candidates as $c) {
            $candidateIdSet[(int)$c['id']] = true;
        }

        if ($hasApps && $candidateIdSet) {
            $appRows = cm_fetch_all(
                $conn,
                "SELECT apl.id, apl.candidate_id, apl.application_reference, apl.status"
                . ($hasOpps ? ", op.title AS opportunity_title" : ", NULL AS opportunity_title")
                . " FROM applications apl"
                . ($hasOpps ? " LEFT JOIN opportunities op ON op.id = apl.opportunity_id" : "")
                . " WHERE apl.status <> 'draft'
                    ORDER BY apl.id DESC
                    LIMIT 2000"
            );

            foreach ($appRows as $a) {
                if (isset($candidateIdSet[(int)$a['candidate_id']])) {
                    $applications[] = $a;
                }
            }
        }

        /* ---------- Candidates needing engagement ---------- */

        if ($hasApps && $focusId <= 0) {
            try {

            $closedSql   = "'" . implode("','", CM_CLOSED_APP_STATUSES) . "'";
            $staleCutoff = date('Y-m-d H:i:s', strtotime('-' . $staleDays . ' days'));

            $needFrom = "FROM applications a
                         INNER JOIN users cu ON cu.id = a.candidate_id
                         INNER JOIN roles r ON r.id = cu.role_id
                         LEFT JOIN recruiter_candidate_communications l
                                ON l.id = (SELECT c2.id
                                           FROM recruiter_candidate_communications c2
                                           WHERE c2.candidate_id = a.candidate_id
                                           ORDER BY c2.occurred_at DESC, c2.id DESC
                                           LIMIT 1)";

            $needWhere = "r.slug = 'candidate'
                          AND cu.status = 'active'
                          AND a.status NOT IN ({$closedSql})
                          AND a.id = (SELECT a2.id
                                      FROM applications a2
                                      WHERE a2.candidate_id = a.candidate_id
                                        AND a2.status NOT IN ({$closedSql})
                                      ORDER BY COALESCE(a2.updated_at, a2.created_at) DESC, a2.id DESC
                                      LIMIT 1)
                          AND NOT EXISTS (SELECT 1
                                          FROM recruiter_candidate_communications p
                                          WHERE p.candidate_id = a.candidate_id
                                            AND p.follow_up_status = 'pending')
                          AND (l.id IS NULL
                               OR (l.outcome <> 'not_interested'
                                   AND (l.occurred_at < ?
                                        OR l.outcome IN ('callback_requested', 'no_response', 'left_message'))))";

            $needCount = cm_fetch_all(
                $conn,
                "SELECT COUNT(*) AS total {$needFrom} WHERE {$needWhere}",
                's',
                [$staleCutoff]
            );

            $needsTotal = $needCount ? (int)$needCount[0]['total'] : 0;

            $needs = cm_fetch_all(
                $conn,
                "SELECT a.id AS app_id, a.candidate_id, a.application_reference AS app_reference,
                        a.status AS app_status, cu.first_name, cu.last_name, cu.email,
                        l.id AS last_id, l.occurred_at AS last_at,
                        l.outcome AS last_outcome, l.channel AS last_channel"
                . ($hasOpps ? ", op.title AS opp_title" : ", NULL AS opp_title")
                . " {$needFrom}"
                . ($hasOpps ? " LEFT JOIN opportunities op ON op.id = a.opportunity_id" : "")
                . " WHERE {$needWhere}
                    ORDER BY CASE
                                 WHEN l.outcome = 'callback_requested' THEN 0
                                 WHEN l.outcome IN ('no_response', 'left_message') THEN 1
                                 WHEN l.id IS NULL THEN 3
                                 ELSE 2
                             END ASC,
                             COALESCE(l.occurred_at, a.submitted_at, a.created_at) ASC
                    LIMIT " . (int)CM_NEEDS_LIMIT,
                's',
                [$staleCutoff]
            );

            } catch (Throwable $t) {
                error_log('communications.php needs-engagement: ' . $t->getMessage());
                $needs       = [];
                $needsTotal  = 0;
                $needsError  = $t->getMessage();
            }
        }

        $elig = cm_eligibility_sql($conn, 'cu');

        if ($elig !== null) {

            $cols = "c.id, c.recruiter_id, c.candidate_id, c.application_id, c.channel, c.direction,
                     c.subject, c.summary, c.outcome, c.occurred_at, c.follow_up_at, c.follow_up_note,
                     c.follow_up_status, c.follow_up_closed_at, c.follow_up_close_note,
                     cu.first_name, cu.last_name, cu.email,
                     ru.first_name AS rec_first, ru.last_name AS rec_last,"
                . ($hasApps ? " apl.application_reference AS app_reference," : " NULL AS app_reference,")
                . ($hasOpps ? " op.title AS opp_title" : " NULL AS opp_title");

            $from = "FROM recruiter_candidate_communications c
                     INNER JOIN users cu ON cu.id = c.candidate_id
                     LEFT JOIN users ru ON ru.id = c.recruiter_id"
                . ($hasApps ? " LEFT JOIN applications apl ON apl.id = c.application_id" : "")
                . ($hasOpps ? " LEFT JOIN opportunities op ON op.id = apl.opportunity_id" : "");

            /* ---------- Stats (my follow-ups) ---------- */

            $statRows = cm_fetch_all(
                $conn,
                "SELECT
                    COALESCE(SUM(c.follow_up_status = 'pending' AND c.follow_up_at < ?), 0) AS overdue,
                    COALESCE(SUM(c.follow_up_status = 'pending' AND c.follow_up_at >= ? AND c.follow_up_at < ?), 0) AS today,
                    COALESCE(SUM(c.follow_up_status = 'pending' AND c.follow_up_at >= ?), 0) AS upcoming,
                    COALESCE(SUM(c.occurred_at >= ?), 0) AS logged
                 FROM recruiter_candidate_communications c
                 INNER JOIN users cu ON cu.id = c.candidate_id
                 WHERE c.recruiter_id = ? AND {$elig}",
                'sssssi',
                [$now, $now, $tomorrow, $tomorrow, $thirtyAgo, $recruiterId]
            );

            if ($statRows) {
                $stats = [
                    'overdue'  => (int)$statRows[0]['overdue'],
                    'today'    => (int)$statRows[0]['today'],
                    'upcoming' => (int)$statRows[0]['upcoming'],
                    'logged'   => (int)$statRows[0]['logged'],
                ];
            }

            /* ---------- Follow-up queue (mine, pending) ---------- */

            $qWhere  = "c.recruiter_id = ? AND c.follow_up_status = 'pending' AND {$elig}";
            $qTypes  = 'i';
            $qParams = [$recruiterId];

            if ($focusId > 0) {
                $qWhere   .= ' AND c.candidate_id = ?';
                $qTypes   .= 'i';
                $qParams[] = $focusId;
            }

            $queue = cm_fetch_all(
                $conn,
                "SELECT {$cols} {$from} WHERE {$qWhere} ORDER BY c.follow_up_at ASC, c.id ASC LIMIT 200",
                $qTypes,
                $qParams
            );

            /* ---------- History ---------- */

            $hWhere  = [$elig];
            $hTypes  = '';
            $hParams = [];

            if ($focusId > 0) {
                $hWhere[]  = 'c.candidate_id = ?';
                $hTypes   .= 'i';
                $hParams[] = $focusId;
            } elseif ($scope === 'mine') {
                $hWhere[]  = 'c.recruiter_id = ?';
                $hTypes   .= 'i';
                $hParams[] = $recruiterId;
            }

            if ($fChannel !== '') {
                $hWhere[]  = 'c.channel = ?';
                $hTypes   .= 's';
                $hParams[] = $fChannel;
            }

            if ($fOutcome !== '') {
                $hWhere[]  = 'c.outcome = ?';
                $hTypes   .= 's';
                $hParams[] = $fOutcome;
            }

            if ($fFollow !== '') {
                $hWhere[]  = 'c.follow_up_status = ?';
                $hTypes   .= 's';
                $hParams[] = $fFollow;
            }

            if ($q !== '') {
                $like      = cm_like($q);
                $hWhere[]  = "(CONCAT(cu.first_name, ' ', cu.last_name) LIKE ?
                               OR cu.email LIKE ?
                               OR c.subject LIKE ?
                               OR c.summary LIKE ?)";
                $hTypes   .= 'ssss';
                array_push($hParams, $like, $like, $like, $like);
            }

            $hWhereSql = implode(' AND ', $hWhere);

            $countRows = cm_fetch_all(
                $conn,
                "SELECT COUNT(*) AS total
                 FROM recruiter_candidate_communications c
                 INNER JOIN users cu ON cu.id = c.candidate_id
                 WHERE {$hWhereSql}",
                $hTypes,
                $hParams
            );

            $totalRows  = $countRows ? (int)$countRows[0]['total'] : 0;
            $totalPages = max(1, (int)ceil($totalRows / CM_PER_PAGE));
            $page       = min($page, $totalPages);
            $offset     = ($page - 1) * CM_PER_PAGE;

            $history = cm_fetch_all(
                $conn,
                "SELECT {$cols} {$from}
                 WHERE {$hWhereSql}
                 ORDER BY c.occurred_at DESC, c.id DESC
                 LIMIT ? OFFSET ?",
                $hTypes . 'ii',
                array_merge($hParams, [CM_PER_PAGE, $offset])
            );
        }

    } catch (Throwable $t) {
        error_log('communications.php load: ' . $t->getMessage());
        $loadError = 'The communication records could not be loaded: ' . $t->getMessage();
    }
}

$csrf       = rc_csrf();
$formAction = url('recruiter/communications.php');
$autoOpenLog = isset($_GET['log']) && $_GET['log'] === '1';

/** Query string for filter / pagination links. */
$baseQuery = static function (array $override = []) use ($focusId, $q, $fChannel, $fOutcome, $fFollow, $scope, $staleDays): string {
    $params = [
        'candidate_id' => $focusId > 0 ? $focusId : '',
        'q'            => $q,
        'channel'      => $fChannel,
        'outcome'      => $fOutcome,
        'fu'           => $fFollow,
        'scope'        => $scope === 'all' ? 'all' : '',
        'stale'        => $staleDays !== 7 ? $staleDays : '',
        'page'         => '',
    ];

    foreach ($override as $k => $v) {
        $params[$k] = $v;
    }

    $params = array_filter($params, static fn($v) => $v !== '' && $v !== null && $v !== 0);

    return $params ? '?' . http_build_query($params) : '';
};

$focusName = $focus ? cm_person($focus['first_name'] ?? '', $focus['last_name'] ?? '', 'Candidate') : '';

require __DIR__ . '/_layout_start.php';
?>

<style>
.cm-toolbar{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;margin-bottom:16px}
.cm-filters{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end}
.cm-filters .cm-field{margin:0;min-width:150px}
.cm-field{display:flex;flex-direction:column;gap:6px;margin-bottom:14px}
.cm-field label{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;opacity:.75}
.cm-input,.cm-select,.cm-textarea{width:100%;box-sizing:border-box;padding:10px 12px;border-radius:10px;border:1px solid rgba(127,127,127,.35);background:transparent;color:inherit;font:inherit}
.cm-textarea{min-height:110px;resize:vertical}
.cm-input:focus,.cm-select:focus,.cm-textarea:focus{outline:2px solid #2454d8;outline-offset:1px}
.cm-hint{font-size:12px;opacity:.65}
.cm-row2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media(max-width:600px){.cm-row2{grid-template-columns:1fr}}
.cm-sep{margin:6px 0 16px;padding-top:14px;border-top:1px solid rgba(127,127,127,.18);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;opacity:.75}
.cm-check{display:flex;gap:8px;align-items:flex-start;font-size:14px;margin-bottom:6px}
.cm-check input{margin-top:3px}

.cm-cell small{display:block;opacity:.65;margin-top:3px}
.cm-cell a{color:inherit}
.cm-channel{display:inline-flex;align-items:center;gap:7px;font-weight:600}
.cm-summary{margin-top:6px;line-height:1.5;word-break:break-word}
.cm-summary details summary{cursor:pointer}
.cm-summary details p{margin:6px 0 0}
.cm-actions{display:flex;flex-wrap:wrap;gap:6px}
.cm-tabs{display:flex;flex-wrap:wrap;align-items:center;gap:6px;margin-bottom:16px}
.cm-tabs span{font-size:13px;opacity:.7;margin-right:4px}
.cm-tab{padding:7px 13px;border-radius:999px;text-decoration:none;font-size:13px;font-weight:600;color:inherit;background:rgba(127,127,127,.10)}
.cm-tab.is-active{background:#2454d8;color:#fff}

.cm-pill{display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700;background:rgba(127,127,127,.14)}
.cm-pill--danger{background:rgba(210,75,75,.16);color:#a02727}
.cm-pill--warn{background:rgba(230,162,24,.20);color:#8a5a00}
.cm-pill--ok{background:rgba(20,184,123,.16);color:#0b6b48}
.cm-pill--info{background:rgba(36,84,216,.14);color:#2454d8}
.cm-pill--muted{opacity:.8}
[data-theme="dark"] .cm-pill--danger{color:#fca5a5}
[data-theme="dark"] .cm-pill--warn{color:#fcd34d}
[data-theme="dark"] .cm-pill--ok{color:#6ee7b7}
[data-theme="dark"] .cm-pill--info{color:#93b4ff}

.cm-pager{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:16px;flex-wrap:wrap}
.cm-focus{display:flex;flex-wrap:wrap;gap:18px;align-items:center;justify-content:space-between}
.cm-focus__meta{display:flex;flex-wrap:wrap;gap:14px;opacity:.85;font-size:14px}

.cm-modal{position:fixed;inset:0;z-index:10000;display:none;align-items:center;justify-content:center;padding:24px}
.cm-modal.is-open{display:flex}
.cm-modal__backdrop{position:absolute;inset:0;background:rgba(15,23,42,.70);backdrop-filter:blur(5px)}
.cm-modal__dialog{position:relative;z-index:1;width:min(660px,100%);max-height:90vh;display:flex;flex-direction:column;overflow:hidden;border-radius:22px;background:#fff;color:#172b4d;box-shadow:0 25px 80px rgba(0,0,0,.28)}
.cm-modal__header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:22px 24px 16px;border-bottom:1px solid rgba(127,127,127,.16)}
.cm-modal__header h3{margin:4px 0 2px}
.cm-modal__header p{margin:0;opacity:.7}
.cm-modal__close{width:40px;height:40px;flex:0 0 40px;display:grid;place-items:center;border:0;border-radius:50%;cursor:pointer;background:rgba(15,23,42,.07);color:inherit}
.cm-modal__body{padding:18px 24px;overflow-y:auto}
.cm-modal__footer{display:flex;justify-content:flex-end;gap:10px;padding:14px 24px;border-top:1px solid rgba(127,127,127,.16)}
body.cm-modal-open{overflow:hidden}
html[data-theme="dark"] .cm-modal__dialog,body.dark-mode .cm-modal__dialog{background:#111827;color:#f8fafc}
html[data-theme="dark"] .cm-modal__close,body.dark-mode .cm-modal__close{background:rgba(255,255,255,.08);color:#f8fafc}
html[data-theme="dark"] .cm-select option,body.dark-mode .cm-select option{background:#111827;color:#f8fafc}
@media(max-width:768px){.cm-modal{padding:12px}.cm-modal__dialog{border-radius:16px}}
</style>

<!-- ================================================================
     HERO
================================================================ -->

<section class="rc-hero">
    <div>
        <span class="rc-eyebrow"><i class="fas fa-comments"></i> Candidate engagement</span>
        <h2>Communication &amp; Follow-Up History</h2>
        <p>
            Record every interaction with candidates, schedule follow-ups,
            and track the outcome of each conversation so nothing slips
            through the cracks.
        </p>
        <div class="rc-hero__actions">
            <?php if ($schemaReady && $candidates): ?>
                <button type="button" class="rc-btn rc-btn--light" data-cm-open="cmLogModal">
                    <i class="fas fa-plus"></i> Log interaction
                </button>
            <?php endif; ?>
            <a class="rc-btn rc-btn--glass" href="<?= url('recruiter/follow_up.php') ?>">
                <i class="fas fa-route"></i> Follow-Up Pipeline
            </a>
            <?php if ($focusId > 0): ?>
                <a class="rc-btn rc-btn--glass" href="<?= $formAction ?>">
                    <i class="fas fa-arrow-left"></i> All communications
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if (!$schemaReady): ?>

    <div class="rc-card"><div class="rc-card__body">
        <div class="rc-empty">
            <i class="fas fa-database"></i>
            <strong>Communication table could not be created</strong>
            <span>
                Run <code>database/candidate_communications.sql</code> on your database, then reload this page.
                <?= $schemaError ? '(' . e($schemaError) . ')' : '' ?>
            </span>
        </div>
    </div></div>

<?php else: ?>

<?php if ($focusNotice !== ''): ?>
    <div class="rc-card rc-space"><div class="rc-card__body"><?= e($focusNotice) ?></div></div>
<?php endif; ?>

<?php if ($loadError !== null): ?>
    <div class="rc-card rc-space"><div class="rc-card__body"><?= e($loadError) ?></div></div>
<?php endif; ?>

<?php if ($focus): ?>
<section class="rc-card rc-space">
    <div class="rc-card__body cm-focus">
        <div>
            <span class="rc-eyebrow">Candidate timeline</span>
            <h3 style="margin:4px 0 6px"><?= e($focusName) ?></h3>
            <div class="cm-focus__meta">
                <?php if (!empty($focus['email'])): ?>
                    <span><i class="fas fa-envelope"></i>
                        <a href="mailto:<?= e((string)$focus['email']) ?>"><?= e((string)$focus['email']) ?></a></span>
                <?php endif; ?>
                <?php if (!empty($focus['phone'])): ?>
                    <span><i class="fas fa-phone"></i>
                        <a href="tel:<?= e((string)preg_replace('/[^0-9+]/', '', (string)$focus['phone'])) ?>"><?= e((string)$focus['phone']) ?></a></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="cm-actions">
            <button type="button" class="rc-btn rc-btn--primary rc-btn--small" data-cm-open="cmLogModal">
                <i class="fas fa-plus"></i> Log interaction
            </button>
            <a class="rc-btn rc-btn--secondary rc-btn--small" href="<?= url('recruiter/candidate_view.php?id=' . (int)$focus['id']) ?>">
                <i class="fas fa-user"></i> View candidate
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ================================================================
     STATS
================================================================ -->

<section class="rc-stats">
    <article><i class="fas fa-triangle-exclamation"></i><strong><?= number_format($stats['overdue']) ?></strong><span>My overdue follow-ups</span></article>
    <article><i class="fas fa-calendar-day"></i><strong><?= number_format($stats['today']) ?></strong><span>Due later today</span></article>
    <article><i class="fas fa-calendar-plus"></i><strong><?= number_format($stats['upcoming']) ?></strong><span>Upcoming follow-ups</span></article>
    <article><i class="fas fa-comments"></i><strong><?= number_format($stats['logged']) ?></strong><span>Logged (last 30 days)</span></article>
    <?php if ($focusId <= 0): ?>
    <article><i class="fas fa-user-clock"></i><strong><?= number_format($needsTotal) ?></strong><span>Need engagement</span></article>
    <?php endif; ?>
</section>

<!-- ================================================================
     FOLLOW-UP QUEUE
================================================================ -->

<?php if ($focusId <= 0): ?>
<section class="rc-card rc-space" id="needs-engagement">

    <div class="rc-card__header">
        <div>
            <span class="rc-eyebrow">Engagement</span>
            <h3>Candidates needing engagement</h3>
            <p>
                Active applications with no follow-up scheduled that are awaiting a reply,
                asked for a callback, have had no contact for <?= (int)$staleDays ?>+ days,
                or have never been contacted.
            </p>
        </div>
    </div>

    <div class="rc-card__body">

        <nav class="cm-tabs" aria-label="No-contact threshold">
            <span>No contact for:</span>
            <?php foreach (CM_STALE_OPTIONS as $opt): ?>
                <a class="cm-tab <?= $staleDays === $opt ? 'is-active' : '' ?>"
                   href="<?= $formAction . $baseQuery(['stale' => $opt === 7 ? '' : $opt, 'page' => '']) ?>#needs-engagement"><?= (int)$opt ?>+ days</a>
            <?php endforeach; ?>
        </nav>

        <?php if (!$hasApps): ?>

            <div class="rc-empty">
                <i class="fas fa-database"></i>
                <strong>Applications table not found</strong>
                <span>This list needs the existing <code>applications</code> table.</span>
            </div>

        <?php elseif ($needsError !== null): ?>

            <div class="rc-empty">
                <i class="fas fa-triangle-exclamation"></i>
                <strong>This list could not be loaded</strong>
                <span><?= e($needsError) ?></span>
            </div>

        <?php elseif (!$needs): ?>

            <div class="rc-empty">
                <i class="fas fa-circle-check"></i>
                <strong>Everyone is up to date</strong>
                <span>No active candidate currently needs engagement.</span>
            </div>

        <?php else: ?>

            <?php if ($needsTotal > count($needs)): ?>
                <p class="cm-hint" style="margin:0 0 12px">
                    Showing the <?= count($needs) ?> most urgent of <?= number_format($needsTotal) ?> candidates.
                </p>
            <?php endif; ?>

            <div class="rc-table-wrap">
                <table class="rc-table">
                    <thead>
                        <tr>
                            <th>Candidate</th>
                            <th>Application stage</th>
                            <th>Last contact</th>
                            <th>Why flagged</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($needs as $row):
                        $name        = cm_person($row['first_name'] ?? '', $row['last_name'] ?? '', 'Candidate #' . (int)$row['candidate_id']);
                        $hasLast     = $row['last_id'] !== null;
                        $lastOutcome = (string)($row['last_outcome'] ?? '');
                        $lastChan    = (string)($row['last_channel'] ?? '');
                        $daysAgo     = $hasLast ? max(0, (int)floor((strtotime($now) - (int)strtotime((string)$row['last_at'])) / 86400)) : null;

                        if (!$hasLast) {
                            $reasonClass = 'cm-pill--warn';
                            $reason      = 'Never contacted';
                        } elseif ($lastOutcome === 'callback_requested') {
                            $reasonClass = 'cm-pill--danger';
                            $reason      = 'Callback requested';
                        } elseif (in_array($lastOutcome, ['no_response', 'left_message'], true)) {
                            $reasonClass = 'cm-pill--warn';
                            $reason      = 'Awaiting reply';
                        } else {
                            $reasonClass = 'cm-pill--info';
                            $reason      = 'No contact for ' . $daysAgo . ($daysAgo === 1 ? ' day' : ' days');
                        }
                    ?>
                        <tr>
                            <td class="cm-cell">
                                <strong><a href="<?= $formAction ?>?candidate_id=<?= (int)$row['candidate_id'] ?>"><?= e($name) ?></a></strong>
                                <?php if ((string)$row['email'] !== ''): ?>
                                    <small><?= e((string)$row['email']) ?></small>
                                <?php endif; ?>
                            </td>

                            <td class="cm-cell">
                                <span class="cm-pill cm-pill--info"><?= e(rc_label((string)$row['app_status'])) ?></span>
                                <small><?= e(implode(' · ', array_filter([(string)$row['opp_title'], (string)$row['app_reference']], static fn($v) => $v !== ''))) ?></small>
                            </td>

                            <td class="cm-cell">
                                <?php if ($hasLast): ?>
                                    <span class="cm-channel"><i class="fas <?= e(CM_CHANNEL_ICONS[$lastChan] ?? 'fa-ellipsis') ?>"></i>
                                        <?= e(CM_CHANNELS[$lastChan] ?? rc_label($lastChan)) ?></span>
                                    <small><?= e(cm_fmt((string)$row['last_at'])) ?> · <?= $daysAgo === 0 ? 'today' : (int)$daysAgo . ($daysAgo === 1 ? ' day ago' : ' days ago') ?></small>
                                    <small><?= e(CM_OUTCOMES[$lastOutcome] ?? rc_label($lastOutcome)) ?></small>
                                <?php else: ?>
                                    No contact recorded
                                <?php endif; ?>
                            </td>

                            <td class="cm-cell">
                                <span class="cm-pill <?= e($reasonClass) ?>"><?= e($reason) ?></span>
                                <?php if ($hasLast && $daysAgo >= $staleDays && $reason !== 'No contact for ' . $daysAgo . ($daysAgo === 1 ? ' day' : ' days')): ?>
                                    <small>Also: no contact for <?= (int)$daysAgo ?> <?= $daysAgo === 1 ? 'day' : 'days' ?></small>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if ($candidates): ?>
                                    <div class="cm-actions">
                                        <button type="button" class="rc-btn rc-btn--primary rc-btn--small"
                                                data-cm-open="cmLogModal"
                                                data-cm-candidate="<?= (int)$row['candidate_id'] ?>"
                                                data-cm-application="<?= (int)$row['app_id'] ?>">
                                            <i class="fas fa-plus"></i> Log interaction
                                        </button>
                                        <button type="button" class="rc-btn rc-btn--secondary rc-btn--small"
                                                data-cm-open="cmLogModal"
                                                data-cm-candidate="<?= (int)$row['candidate_id'] ?>"
                                                data-cm-application="<?= (int)$row['app_id'] ?>"
                                                data-cm-focus="follow">
                                            <i class="fas fa-calendar-plus"></i> Schedule follow-up
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<section class="rc-card rc-space" id="follow-ups">

    <div class="rc-card__header">
        <div>
            <span class="rc-eyebrow">Follow-ups</span>
            <h3>My pending follow-ups<?= $focus ? ' — ' . e($focusName) : '' ?></h3>
            <p>Most urgent first. Complete, reschedule or cancel each follow-up.</p>
        </div>
    </div>

    <div class="rc-card__body">

        <?php if (!$queue): ?>

            <div class="rc-empty">
                <i class="fas fa-circle-check"></i>
                <strong>No pending follow-ups</strong>
                <span>Schedule a follow-up when you log an interaction and it will appear here.</span>
            </div>

        <?php else: ?>

            <div class="rc-table-wrap">
                <table class="rc-table">
                    <thead>
                        <tr>
                            <th>Candidate</th>
                            <th>Follow-up due</th>
                            <th>Purpose</th>
                            <th>Last interaction</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($queue as $row):
                        $rid     = (int)$row['id'];
                        $name    = cm_person($row['first_name'] ?? '', $row['last_name'] ?? '', 'Candidate #' . (int)$row['candidate_id']);
                        [$dueClass, $dueText] = cm_due_state((string)$row['follow_up_at'], $now);
                        $chan    = (string)$row['channel'];
                        $whenVal = date('Y-m-d\TH:i', (int)strtotime((string)$row['follow_up_at']));
                    ?>
                        <tr>
                            <td class="cm-cell">
                                <strong><a href="<?= $formAction ?>?candidate_id=<?= (int)$row['candidate_id'] ?>"><?= e($name) ?></a></strong>
                                <?php if ((string)$row['email'] !== ''): ?>
                                    <small><?= e((string)$row['email']) ?></small>
                                <?php endif; ?>
                                <?php if ((string)$row['opp_title'] !== ''): ?>
                                    <small><?= e((string)$row['opp_title']) ?></small>
                                <?php endif; ?>
                            </td>

                            <td class="cm-cell">
                                <span class="cm-pill <?= e($dueClass) ?>"><?= e($dueText) ?></span>
                                <small><?= e(cm_fmt((string)$row['follow_up_at'])) ?></small>
                            </td>

                            <td class="cm-cell">
                                <?= (string)$row['follow_up_note'] !== '' ? e((string)$row['follow_up_note']) : '—' ?>
                            </td>

                            <td class="cm-cell">
                                <span class="cm-channel"><i class="fas <?= e(CM_CHANNEL_ICONS[$chan] ?? 'fa-ellipsis') ?>"></i> <?= e(CM_CHANNELS[$chan] ?? rc_label($chan)) ?></span>
                                <small><?= e(cm_fmt((string)$row['occurred_at'])) ?> · <?= e(CM_OUTCOMES[(string)$row['outcome']] ?? rc_label((string)$row['outcome'])) ?></small>
                            </td>

                            <td>
                                <div class="cm-actions">
                                    <button type="button" class="rc-btn rc-btn--primary rc-btn--small"
                                            data-cm-open="cmCloseModal"
                                            data-cm-mode="complete"
                                            data-cm-id="<?= $rid ?>"
                                            data-cm-label="<?= e($name) ?>">
                                        <i class="fas fa-check"></i> Complete
                                    </button>
                                    <button type="button" class="rc-btn rc-btn--secondary rc-btn--small"
                                            data-cm-open="cmRescheduleModal"
                                            data-cm-id="<?= $rid ?>"
                                            data-cm-label="<?= e($name) ?>"
                                            data-cm-when="<?= e($whenVal) ?>"
                                            data-cm-note="<?= e((string)$row['follow_up_note']) ?>">
                                        <i class="fas fa-calendar-days"></i> Reschedule
                                    </button>
                                    <button type="button" class="rc-btn rc-btn--secondary rc-btn--small"
                                            data-cm-open="cmCloseModal"
                                            data-cm-mode="cancel"
                                            data-cm-id="<?= $rid ?>"
                                            data-cm-label="<?= e($name) ?>">
                                        <i class="fas fa-ban"></i> Cancel
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>
    </div>
</section>

<!-- ================================================================
     HISTORY
================================================================ -->

<section class="rc-card rc-space" id="history">

    <div class="rc-card__header">
        <div>
            <span class="rc-eyebrow">History</span>
            <h3><?= $focus ? 'Communication history — ' . e($focusName) : 'Communication history' ?></h3>
            <p>
                <?= number_format($totalRows) ?> <?= $totalRows === 1 ? 'record' : 'records' ?>
                <?php if ($focusId > 0): ?>
                    · all recruiters
                <?php else: ?>
                    · <?= $scope === 'all' ? 'all recruiters' : 'logged by you' ?>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <div class="rc-card__body">

        <form class="cm-toolbar" method="get" action="<?= $formAction ?>">
            <?php if ($focusId > 0): ?>
                <input type="hidden" name="candidate_id" value="<?= (int)$focusId ?>">
            <?php endif; ?>
            <?php if ($staleDays !== 7): ?>
                <input type="hidden" name="stale" value="<?= (int)$staleDays ?>">
            <?php endif; ?>

            <div class="cm-filters">
                <div class="cm-field">
                    <label for="cmQ">Search</label>
                    <input class="cm-input" id="cmQ" type="search" name="q" value="<?= e($q) ?>" placeholder="Name, email, subject, notes">
                </div>

                <div class="cm-field">
                    <label for="cmFChannel">Channel</label>
                    <select class="cm-select" id="cmFChannel" name="channel">
                        <option value="">All channels</option>
                        <?php foreach (CM_CHANNELS as $k => $label): ?>
                            <option value="<?= e($k) ?>" <?= $fChannel === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="cm-field">
                    <label for="cmFOutcome">Outcome</label>
                    <select class="cm-select" id="cmFOutcome" name="outcome">
                        <option value="">All outcomes</option>
                        <?php foreach (CM_OUTCOMES as $k => $label): ?>
                            <option value="<?= e($k) ?>" <?= $fOutcome === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="cm-field">
                    <label for="cmFFollow">Follow-up</label>
                    <select class="cm-select" id="cmFFollow" name="fu">
                        <option value="">Any</option>
                        <?php foreach (CM_FOLLOW_STATUSES as $k => $label): ?>
                            <option value="<?= e($k) ?>" <?= $fFollow === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($focusId <= 0): ?>
                    <div class="cm-field">
                        <label for="cmFScope">Recruiter</label>
                        <select class="cm-select" id="cmFScope" name="scope">
                            <option value="mine" <?= $scope === 'mine' ? 'selected' : '' ?>>Logged by me</option>
                            <option value="all" <?= $scope === 'all' ? 'selected' : '' ?>>All recruiters</option>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="cm-field">
                    <button class="rc-btn rc-btn--secondary rc-btn--small" type="submit">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                </div>

                <div class="cm-field">
                    <a class="rc-btn rc-btn--secondary rc-btn--small" href="<?= $focusId > 0 ? $formAction . '?candidate_id=' . (int)$focusId : $formAction ?>">Reset</a>
                </div>
            </div>
        </form>

        <?php if (!$history): ?>

            <div class="rc-empty">
                <i class="fas fa-comment-slash"></i>
                <strong>No communication recorded</strong>
                <span>
                    <?= ($q !== '' || $fChannel !== '' || $fOutcome !== '' || $fFollow !== '')
                        ? 'Nothing matches the current filters.'
                        : 'Log your first interaction using the button above.' ?>
                </span>
            </div>

        <?php else: ?>

            <div class="rc-table-wrap">
                <table class="rc-table">
                    <thead>
                        <tr>
                            <th>Candidate</th>
                            <th>Interaction</th>
                            <th>Outcome</th>
                            <th>Follow-up</th>
                            <th>Logged by</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($history as $row):
                        $name    = cm_person($row['first_name'] ?? '', $row['last_name'] ?? '', 'Candidate #' . (int)$row['candidate_id']);
                        $chan    = (string)$row['channel'];
                        $dir     = (string)$row['direction'];
                        $outc    = (string)$row['outcome'];
                        $fStat   = (string)$row['follow_up_status'];
                        $summary = (string)$row['summary'];
                        $long    = mb_strlen($summary) > 160;
                        $outClass = in_array($outc, CM_OUTCOME_GOOD, true) ? 'cm-pill--ok'
                                  : (in_array($outc, CM_OUTCOME_BAD, true) ? 'cm-pill--danger' : 'cm-pill--info');
                    ?>
                        <tr>
                            <td class="cm-cell">
                                <strong><a href="<?= $formAction ?>?candidate_id=<?= (int)$row['candidate_id'] ?>"><?= e($name) ?></a></strong>
                                <?php if ((string)$row['email'] !== ''): ?>
                                    <small><?= e((string)$row['email']) ?></small>
                                <?php endif; ?>
                                <?php if ((string)$row['opp_title'] !== '' || (string)$row['app_reference'] !== ''): ?>
                                    <small><?= e(implode(' · ', array_filter([(string)$row['opp_title'], (string)$row['app_reference']], static fn($v) => $v !== ''))) ?></small>
                                <?php endif; ?>
                            </td>

                            <td class="cm-cell">
                                <span class="cm-channel"><i class="fas <?= e(CM_CHANNEL_ICONS[$chan] ?? 'fa-ellipsis') ?>"></i>
                                    <?= e(CM_CHANNELS[$chan] ?? rc_label($chan)) ?></span>
                                <small><?= e(CM_DIRECTION_SHORT[$dir] ?? rc_label($dir)) ?> · <?= e(cm_fmt((string)$row['occurred_at'])) ?></small>
                                <?php if ((string)$row['subject'] !== ''): ?>
                                    <div class="cm-summary"><strong><?= e((string)$row['subject']) ?></strong></div>
                                <?php endif; ?>
                                <div class="cm-summary">
                                    <?php if ($long): ?>
                                        <details>
                                            <summary><?= e(mb_substr($summary, 0, 160)) ?>…</summary>
                                            <p><?= nl2br(e($summary)) ?></p>
                                        </details>
                                    <?php else: ?>
                                        <?= nl2br(e($summary)) ?>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td class="cm-cell">
                                <span class="cm-pill <?= e($outClass) ?>"><?= e(CM_OUTCOMES[$outc] ?? rc_label($outc)) ?></span>
                            </td>

                            <td class="cm-cell">
                                <?php if ($fStat === 'pending' && $row['follow_up_at']):
                                    [$dueClass, $dueText] = cm_due_state((string)$row['follow_up_at'], $now); ?>
                                    <span class="cm-pill <?= e($dueClass) ?>"><?= e($dueText) ?></span>
                                    <small><?= e(cm_fmt((string)$row['follow_up_at'])) ?></small>
                                    <?php if ((string)$row['follow_up_note'] !== ''): ?>
                                        <small><?= e((string)$row['follow_up_note']) ?></small>
                                    <?php endif; ?>
                                <?php elseif ($fStat === 'completed'): ?>
                                    <span class="cm-pill cm-pill--ok">Completed</span>
                                    <small><?= e(cm_fmt((string)$row['follow_up_closed_at'])) ?></small>
                                    <?php if ((string)$row['follow_up_close_note'] !== ''): ?>
                                        <small><?= e((string)$row['follow_up_close_note']) ?></small>
                                    <?php endif; ?>
                                <?php elseif ($fStat === 'cancelled'): ?>
                                    <span class="cm-pill cm-pill--muted">Cancelled</span>
                                    <small><?= e(cm_fmt((string)$row['follow_up_closed_at'])) ?></small>
                                    <?php if ((string)$row['follow_up_close_note'] !== ''): ?>
                                        <small><?= e((string)$row['follow_up_close_note']) ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>

                            <td class="cm-cell">
                                <?= e(cm_person($row['rec_first'] ?? '', $row['rec_last'] ?? '', 'Recruiter #' . (int)$row['recruiter_id'])) ?>
                                <?php if ((int)$row['recruiter_id'] === $recruiterId): ?><small>You</small><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav class="cm-pager" aria-label="History pages">
                    <span class="cm-hint">Page <?= (int)$page ?> of <?= (int)$totalPages ?></span>
                    <span class="cm-actions">
                        <?php if ($page > 1): ?>
                            <a class="rc-btn rc-btn--secondary rc-btn--small" href="<?= $formAction . $baseQuery(['page' => $page - 1]) ?>#history"><i class="fas fa-chevron-left"></i> Previous</a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a class="rc-btn rc-btn--secondary rc-btn--small" href="<?= $formAction . $baseQuery(['page' => $page + 1]) ?>#history">Next <i class="fas fa-chevron-right"></i></a>
                        <?php endif; ?>
                    </span>
                </nav>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</section>

<!-- ================================================================
     MODAL: LOG INTERACTION
================================================================ -->

<?php if ($candidates): ?>
<div class="cm-modal" id="cmLogModal" aria-hidden="true">
    <div class="cm-modal__backdrop" data-cm-close></div>
    <form class="cm-modal__dialog" method="post" action="<?= $formAction ?>"
          role="dialog" aria-modal="true" aria-labelledby="cmLogTitle">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="log">
        <input type="hidden" name="focus_id" value="<?= (int)$focusId ?>">

        <div class="cm-modal__header">
            <div>
                <span class="rc-eyebrow">New record</span>
                <h3 id="cmLogTitle">Log interaction</h3>
                <p>Record what was discussed and, if needed, schedule a follow-up.</p>
            </div>
            <button type="button" class="cm-modal__close" data-cm-close aria-label="Close"><i class="fas fa-xmark"></i></button>
        </div>

        <div class="cm-modal__body">

            <div class="cm-field">
                <label for="cmCandidate">Candidate</label>
                <select class="cm-select" id="cmCandidate" name="candidate_id" required>
                    <option value="">Select a candidate…</option>
                    <?php foreach ($candidates as $c):
                        $cn = cm_person($c['first_name'] ?? '', $c['last_name'] ?? '', 'Candidate #' . (int)$c['id']);
                    ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $focusId === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= e($cn . ((string)$c['email'] !== '' ? ' — ' . (string)$c['email'] : '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($hasApps): ?>
            <div class="cm-field">
                <label for="cmApplication">Related application (optional)</label>
                <select class="cm-select" id="cmApplication" name="application_id">
                    <option value="">None</option>
                    <?php foreach ($applications as $a): ?>
                        <option value="<?= (int)$a['id'] ?>" data-candidate="<?= (int)$a['candidate_id'] ?>">
                            <?= e(((string)$a['opportunity_title'] !== '' ? (string)$a['opportunity_title'] : 'Opportunity')
                                . ' (' . ((string)$a['application_reference'] !== '' ? (string)$a['application_reference'] : '#' . (int)$a['id'])
                                . ' · ' . rc_label((string)$a['status']) . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="cm-hint">Only this candidate's applications are listed.</span>
            </div>
            <?php endif; ?>

            <div class="cm-row2">
                <div class="cm-field">
                    <label for="cmChannel">Channel</label>
                    <select class="cm-select" id="cmChannel" name="channel" required>
                        <?php foreach (CM_CHANNELS as $k => $label): ?>
                            <option value="<?= e($k) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="cm-field">
                    <label for="cmDirection">Initiated by</label>
                    <select class="cm-select" id="cmDirection" name="direction" required>
                        <?php foreach (CM_DIRECTIONS as $k => $label): ?>
                            <option value="<?= e($k) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="cm-row2">
                <div class="cm-field">
                    <label for="cmOccurred">Date &amp; time</label>
                    <input class="cm-input" id="cmOccurred" type="datetime-local" name="occurred_at"
                           value="<?= e($nowLocal) ?>" max="<?= e($nowLocal) ?>" required>
                </div>

                <div class="cm-field">
                    <label for="cmOutcome">Outcome</label>
                    <select class="cm-select" id="cmOutcome" name="outcome" required>
                        <option value="">Select an outcome…</option>
                        <?php foreach (CM_OUTCOMES as $k => $label): ?>
                            <option value="<?= e($k) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="cm-field">
                <label for="cmSubject">Subject (optional)</label>
                <input class="cm-input" id="cmSubject" type="text" name="subject" maxlength="160" placeholder="e.g. Availability check for Senior Developer role">
            </div>

            <div class="cm-field">
                <label for="cmSummary">Summary</label>
                <textarea class="cm-textarea" id="cmSummary" name="summary" maxlength="5000" required
                          placeholder="What was discussed, agreed or promised?"></textarea>
            </div>

            <div class="cm-sep">Follow-up (optional)</div>

            <div class="cm-row2">
                <div class="cm-field">
                    <label for="cmFollowAt">Follow up on</label>
                    <input class="cm-input" id="cmFollowAt" type="datetime-local" name="follow_up_at" min="<?= e($nowLocal) ?>">
                </div>

                <div class="cm-field">
                    <label for="cmFollowNote">Purpose</label>
                    <input class="cm-input" id="cmFollowNote" type="text" name="follow_up_note" maxlength="255" placeholder="e.g. Chase updated CV">
                </div>
            </div>

            <label class="cm-check">
                <input type="checkbox" name="resolve_pending" value="1">
                <span>Mark my other pending follow-ups for this candidate as completed</span>
            </label>
        </div>

        <div class="cm-modal__footer">
            <button type="button" class="rc-btn rc-btn--secondary" data-cm-close>Cancel</button>
            <button type="submit" class="rc-btn rc-btn--primary"><i class="fas fa-floppy-disk"></i> Save interaction</button>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- ================================================================
     MODAL: COMPLETE / CANCEL FOLLOW-UP
================================================================ -->

<div class="cm-modal" id="cmCloseModal" aria-hidden="true">
    <div class="cm-modal__backdrop" data-cm-close></div>
    <form class="cm-modal__dialog" method="post" action="<?= $formAction ?>"
          role="dialog" aria-modal="true" aria-labelledby="cmCloseTitle" style="width:min(520px,100%)">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="complete_follow_up" data-cm-fill="action">
        <input type="hidden" name="focus_id" value="<?= (int)$focusId ?>">
        <input type="hidden" name="follow_up_id" value="" data-cm-fill="id">

        <div class="cm-modal__header">
            <div>
                <span class="rc-eyebrow">Follow-up</span>
                <h3 id="cmCloseTitle" data-cm-fill="title">Complete follow-up</h3>
                <p data-cm-fill="label"></p>
            </div>
            <button type="button" class="cm-modal__close" data-cm-close aria-label="Close"><i class="fas fa-xmark"></i></button>
        </div>

        <div class="cm-modal__body">
            <div class="cm-field">
                <label for="cmCloseNote">Note (optional)</label>
                <textarea class="cm-textarea" id="cmCloseNote" name="close_note" maxlength="255" style="min-height:84px"
                          placeholder="What happened?"></textarea>
            </div>
        </div>

        <div class="cm-modal__footer">
            <button type="button" class="rc-btn rc-btn--secondary" data-cm-close>Back</button>
            <button type="submit" class="rc-btn rc-btn--primary" data-cm-fill="submit">Mark completed</button>
        </div>
    </form>
</div>

<!-- ================================================================
     MODAL: RESCHEDULE FOLLOW-UP
================================================================ -->

<div class="cm-modal" id="cmRescheduleModal" aria-hidden="true">
    <div class="cm-modal__backdrop" data-cm-close></div>
    <form class="cm-modal__dialog" method="post" action="<?= $formAction ?>"
          role="dialog" aria-modal="true" aria-labelledby="cmRescheduleTitle" style="width:min(520px,100%)">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="reschedule_follow_up">
        <input type="hidden" name="focus_id" value="<?= (int)$focusId ?>">
        <input type="hidden" name="follow_up_id" value="" data-cm-fill="id">

        <div class="cm-modal__header">
            <div>
                <span class="rc-eyebrow">Follow-up</span>
                <h3 id="cmRescheduleTitle">Reschedule follow-up</h3>
                <p data-cm-fill="label"></p>
            </div>
            <button type="button" class="cm-modal__close" data-cm-close aria-label="Close"><i class="fas fa-xmark"></i></button>
        </div>

        <div class="cm-modal__body">
            <div class="cm-field">
                <label for="cmResWhen">New date &amp; time</label>
                <input class="cm-input" id="cmResWhen" type="datetime-local" name="follow_up_at"
                       min="<?= e($nowLocal) ?>" required data-cm-fill="when">
            </div>

            <div class="cm-field">
                <label for="cmResNote">Purpose</label>
                <input class="cm-input" id="cmResNote" type="text" name="follow_up_note" maxlength="255" data-cm-fill="note">
            </div>
        </div>

        <div class="cm-modal__footer">
            <button type="button" class="rc-btn rc-btn--secondary" data-cm-close>Back</button>
            <button type="submit" class="rc-btn rc-btn--primary">Save new date</button>
        </div>
    </form>
</div>

<script>
(function () {
    var openModal = null;

    function closeModal() {
        if (!openModal) return;
        openModal.classList.remove('is-open');
        openModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('cm-modal-open');
        openModal = null;
    }

    function setFill(modal, key, value) {
        var el = modal.querySelector('[data-cm-fill="' + key + '"]');
        if (!el) return;
        if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.tagName === 'SELECT') {
            el.value = value;
        } else {
            el.textContent = value;
        }
    }

    document.querySelectorAll('[data-cm-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var modal = document.getElementById(btn.getAttribute('data-cm-open'));
            if (!modal) return;

            closeModal();

            var d = btn.dataset;

            if (modal.id === 'cmCloseModal') {
                var cancel = d.cmMode === 'cancel';
                setFill(modal, 'action', cancel ? 'cancel_follow_up' : 'complete_follow_up');
                setFill(modal, 'title', cancel ? 'Cancel follow-up' : 'Complete follow-up');
                setFill(modal, 'submit', cancel ? 'Cancel follow-up' : 'Mark completed');
                var noteBox = modal.querySelector('textarea[name="close_note"]');
                if (noteBox) noteBox.value = '';
            }

            if (d.cmId !== undefined)    setFill(modal, 'id', d.cmId);
            if (d.cmLabel !== undefined) setFill(modal, 'label', d.cmLabel);
            if (d.cmWhen !== undefined)  setFill(modal, 'when', d.cmWhen);
            if (d.cmNote !== undefined)  setFill(modal, 'note', d.cmNote);

            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('cm-modal-open');
            openModal = modal;

            if (modal.id === 'cmLogModal' && d.cmCandidate) {
                var candEl = document.getElementById('cmCandidate');
                var appEl  = document.getElementById('cmApplication');
                if (candEl) {
                    candEl.value = d.cmCandidate;
                    candEl.dispatchEvent(new Event('change'));
                    if (appEl && d.cmApplication) appEl.value = d.cmApplication;
                }
            }

            var first = (modal.id === 'cmLogModal' && d.cmFocus === 'follow')
                ? document.getElementById('cmFollowAt')
                : modal.querySelector('input:not([type=hidden]), select, textarea');
            if (first) first.focus();
        });
    });

    document.querySelectorAll('[data-cm-close]').forEach(function (el) {
        el.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') closeModal();
    });

    /* Only list the selected candidate's applications. */
    var candSel = document.getElementById('cmCandidate');
    var appSel  = document.getElementById('cmApplication');

    if (candSel && appSel) {
        var allOpts = Array.prototype.slice.call(appSel.options, 1);

        var syncApplications = function () {
            var cid = candSel.value;
            while (appSel.options.length > 1) appSel.remove(1);
            appSel.value = '';
            if (!cid) return;
            allOpts.forEach(function (opt) {
                if (opt.getAttribute('data-candidate') === cid) appSel.add(opt.cloneNode(true));
            });
        };

        candSel.addEventListener('change', syncApplications);
        syncApplications();
    }

    <?php if ($autoOpenLog && $candidates): ?>
    var autoBtn = document.querySelector('[data-cm-open="cmLogModal"]');
    if (autoBtn) autoBtn.click();
    <?php endif; ?>
})();
</script>

<?php endif; ?>

<?php require __DIR__ . '/_layout_end.php'; ?>
