<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('recruiter');

$user        = current_user();
$flashes     = render_flashes();
$currentPage = 'interview_decisions';
$pageTitle   = 'Interview Results & Decisions';

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

const IDR_OUTCOMES = [
    'recommend' => 'Recommend — move forward',
    'offer'     => 'Make an offer',
    'hold'      => 'Hold — undecided',
    'reject'    => 'Reject — not suitable',
];

const IDR_OUTCOME_LABELS = [
    'pending'   => 'Pending',
    'recommend' => 'Recommended',
    'offer'     => 'Offer',
    'hold'      => 'On hold',
    'reject'    => 'Rejected',
];

const IDR_RECOMMENDATIONS = [
    'strong_hire'    => 'Strong hire',
    'hire'           => 'Hire',
    'no_hire'        => 'No hire',
    'strong_no_hire' => 'Strong no hire',
];
const IDR_POSITIVE_RECS = ['strong_hire', 'hire'];

// Application stages from which a recruiter may still record a next step.
const IDR_ACTIONABLE = ['interview_scheduled', 'interview_completed', 'selected', 'waitlisted', 'rejected', 'on_hold'];
// Stages produced by a selection decision; from these only another decision is allowed.
const IDR_DECISION_STATUSES = ['selected', 'waitlisted', 'rejected'];
// Stages that count as already decided / closed for filtering.
const IDR_DECIDED_STATUSES = ['selected', 'waitlisted', 'rejected', 'offer_sent', 'offer_accepted', 'offer_declined', 'withdrawn'];

const IDR_DECISION_LABELS = ['selected' => 'Selected', 'waitlisted' => 'Waitlisted', 'not_selected' => 'Not selected'];

const IDR_STEPS = [
    'next_round' => [
        'label'    => 'Progress: another interview round',
        'target'   => null,
        'decision' => null,
        'hint'     => 'Keeps the application at its current stage and opens the scheduler for the next round.',
    ],
    'assessment' => [
        'label'    => 'Progress: move to assessment',
        'target'   => 'assessment',
        'decision' => null,
        'hint'     => 'Moves the application to the assessment stage and notifies the candidate.',
    ],
    'on_hold' => [
        'label'    => 'Pause: place on hold',
        'target'   => 'on_hold',
        'decision' => null,
        'hint'     => 'Parks the application internally. The candidate is not notified.',
    ],
    'selected' => [
        'label'    => 'Decide: select candidate',
        'target'   => 'selected',
        'decision' => 'selected',
        'hint'     => 'Records a Selected decision and notifies the candidate. Offers are issued in the existing offer workflow.',
    ],
    'waitlisted' => [
        'label'    => 'Decide: waitlist candidate',
        'target'   => 'waitlisted',
        'decision' => 'waitlisted',
        'hint'     => 'Records a Waitlisted decision and notifies the candidate.',
    ],
    'close' => [
        'label'    => 'Close: not selected',
        'target'   => 'rejected',
        'decision' => 'not_selected',
        'hint'     => 'Closes the application as rejected and notifies the candidate.',
    ],
];

const IDR_DECISION_MESSAGES = [
    'selected'     => 'Congratulations, you have been selected for this opportunity. Further steps will be communicated to you.',
    'waitlisted'   => 'You have been placed on the waitlist for this opportunity. We will contact you if a place becomes available.',
    'not_selected' => 'Thank you for taking part in the interview. Unfortunately, you were not selected for this opportunity.',
];

/* ==================================================================
 | Helpers (page-local, all prefixed idr_)
 * ==================================================================*/

if (!function_exists('idr_ensure_log_table')) {
    /** Audit log of every documented next-step decision. */
    function idr_ensure_log_table(mysqli $conn): bool
    {
        try {
            if (rc_has_table($conn, 'recruiter_interview_decision_log')) return true;

            $ok = $conn->query(
                "CREATE TABLE IF NOT EXISTS recruiter_interview_decision_log (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    recruiter_id INT UNSIGNED NOT NULL,
                    application_id INT UNSIGNED NOT NULL,
                    candidate_id INT UNSIGNED NOT NULL,
                    step VARCHAR(30) NOT NULL,
                    from_status VARCHAR(40) NOT NULL DEFAULT '',
                    to_status VARCHAR(40) NOT NULL DEFAULT '',
                    decision_note TEXT NOT NULL,
                    interviews_held SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                    feedback_received SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                    feedback_expected SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                    avg_rating DECIMAL(3,1) NULL,
                    acknowledged TINYINT(1) NOT NULL DEFAULT 0,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_ridl_application (application_id, created_at),
                    KEY idx_ridl_recruiter (recruiter_id, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            if (!$ok) {
                error_log('interview_decisions.php log table: ' . $conn->error);
                return false;
            }
            return true;
        } catch (Throwable $t) {
            error_log('interview_decisions.php log table: ' . $t->getMessage());
            return false;
        }
    }
}

if (!function_exists('idr_key')) {
    /** Same identity rule as the Interview Workspace, so feedback rows line up. */
    function idr_key(?int $userId, string $name, string $email): string
    {
        if ($userId !== null && $userId > 0) return 'u' . $userId;
        $email = strtolower(trim($email));
        if ($email !== '') return 'e' . mb_substr($email, 0, 190);
        return 'n' . mb_substr(strtolower(trim($name)), 0, 190);
    }
}

if (!function_exists('idr_stars')) {
    function idr_stars(int $n): string
    {
        $n = max(0, min(5, $n));
        return str_repeat('★', $n) . str_repeat('☆', 5 - $n);
    }
}

if (!function_exists('idr_fmt')) {
    function idr_fmt(?string $dt, string $format = 'D, d M Y · H:i'): string
    {
        if (!$dt) return '—';
        $ts = strtotime($dt);
        return $ts ? date($format, $ts) : '—';
    }
}

if (!function_exists('idr_fetch_owned')) {
    function idr_fetch_owned(mysqli $conn, int $id, int $recruiterId): ?array
    {
        $stmt = $conn->prepare(
            'SELECT * FROM recruiter_interviews WHERE id = ? AND recruiter_id = ? LIMIT 1'
        );
        $stmt->bind_param('ii', $id, $recruiterId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }
}

if (!function_exists('idr_app_status_supported')) {
    /** True when applications.status can hold $value (guards against ENUMs that lack it). */
    function idr_app_status_supported(mysqli $conn, string $value): bool
    {
        static $type = null;

        if ($type === null) {
            $type = '';
            $res = $conn->query(
                "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'applications' AND COLUMN_NAME = 'status'
                 LIMIT 1"
            );
            if ($res) {
                $row  = $res->fetch_row();
                $type = strtolower((string)($row[0] ?? ''));
                $res->free();
            }
        }

        if ($type === '') return false;
        if (strpos($type, 'enum(') === 0) {
            return strpos($type, "'" . strtolower($value) . "'") !== false;
        }
        return true;
    }
}

if (!function_exists('idr_sync_status')) {
    /** Moves an application between stages (only from $from). Returns true when changed. */
    function idr_sync_status(
        mysqli $conn, int $applicationId, string $to, array $from,
        ?int $changedBy = null, string $reason = ''
    ): bool {
        if ($applicationId <= 0 || !$from || !idr_app_status_supported($conn, $to)) return false;

        $stmt = $conn->prepare('SELECT status FROM applications WHERE id = ? LIMIT 1');
        if (!$stmt) return false;
        $stmt->bind_param('i', $applicationId);
        $stmt->execute();
        $current = (string)($stmt->get_result()->fetch_row()[0] ?? '');
        $stmt->close();

        if ($current === '' || $current === $to || !in_array($current, $from, true)) return false;

        $stmt = $conn->prepare('UPDATE applications SET status = ? WHERE id = ? AND status = ?');
        if (!$stmt) return false;
        $stmt->bind_param('sis', $to, $applicationId, $current);
        $stmt->execute();
        $changed = $stmt->affected_rows > 0;
        $stmt->close();

        if ($changed && rc_has_table($conn, 'application_status_history')) {
            $reason = mb_substr($reason, 0, 500);
            $stmt = $conn->prepare(
                'INSERT INTO application_status_history
                    (application_id, previous_status, new_status, changed_by, change_reason)
                 VALUES (?, ?, ?, ?, ?)'
            );
            if ($stmt) {
                $stmt->bind_param('issis', $applicationId, $current, $to, $changedBy, $reason);
                $stmt->execute();
                $stmt->close();
            }
        }

        return $changed;
    }
}

if (!function_exists('idr_notify_candidate')) {
    /** In-app notification for the candidate (best effort; never blocks the action). */
    function idr_notify_candidate(
        mysqli $conn, int $candidateId, string $title, string $message, int $interviewId, string $event
    ): void {
        try {
            if ($candidateId <= 0 || !rc_has_table($conn, 'candidate_notifications')) return;
            $category = 'interview';
            $module   = 'interviews';
            $dedupe   = 'interview_' . $interviewId . '_' . $event . '_' . time();
            $stmt = $conn->prepare(
                'INSERT INTO candidate_notifications
                    (user_id, category, title, message, module, record_id, dedupe_key)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            if (!$stmt) return;
            $stmt->bind_param('issssis', $candidateId, $category, $title, $message, $module, $interviewId, $dedupe);
            $stmt->execute();
            $stmt->close();
        } catch (Throwable $t) {
            error_log('interview_decisions.php notify: ' . $t->getMessage());
        }
    }
}

if (!function_exists('idr_collect')) {
    /**
     * Gathers, per application, this recruiter's interviews (cancelled excluded),
     * the interview panels and the interviewer feedback, plus summary numbers.
     */
    function idr_collect(mysqli $conn, int $recruiterId, array $appIds, string $now): array
    {
        $clean = [];
        foreach ($appIds as $v) {
            $v = (int)$v;
            if ($v > 0) $clean[$v] = $v;
        }
        $appIds = array_values($clean);
        if (!$appIds) return [];

        $alist = implode(',', $appIds);
        $out   = [];
        $sums  = [];
        foreach ($appIds as $aid) {
            $out[$aid] = [
                'ivs' => [], 'open' => 0, 'held' => 0, 'no_show' => 0, 'need_outcome' => 0,
                'latest_outcome' => '', 'assigned' => 0, 'received' => 0, 'missing' => [],
                'avg' => null, 'cnt' => 0, 'pos' => 0, 'neg' => 0,
                'consensus' => '', 'class' => '', 'last_at' => '',
            ];
            $sums[$aid] = 0;
        }

        $stmt = $conn->prepare(
            "SELECT * FROM recruiter_interviews
             WHERE recruiter_id = ? AND status <> 'cancelled' AND application_id IN ($alist)
             ORDER BY scheduled_at ASC, id ASC"
        );
        if (!$stmt) return $out;
        $stmt->bind_param('i', $recruiterId);
        $stmt->execute();
        $res  = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        $stmt->close();

        if (!$rows) return $out;

        $ids = [];
        foreach ($rows as $r) $ids[] = (int)$r['id'];
        $ilist = implode(',', $ids);

        $panelMap = [];
        $res = $conn->query(
            "SELECT interview_id, user_id, interviewer_name, interviewer_email
             FROM recruiter_interview_interviewers
             WHERE interview_id IN ($ilist) ORDER BY id ASC"
        );
        if ($res) {
            while ($r = $res->fetch_assoc()) $panelMap[(int)$r['interview_id']][] = $r;
            $res->free();
        }

        $fbMap = [];
        if (rc_has_table($conn, 'recruiter_interview_feedback')) {
            $res = $conn->query(
                "SELECT * FROM recruiter_interview_feedback
                 WHERE interview_id IN ($ilist) ORDER BY updated_at DESC, id DESC"
            );
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $fbMap[(int)$r['interview_id']][(string)$r['reviewer_key']] = $r;
                }
                $res->free();
            }
        }

        foreach ($rows as $r) {
            $aid = (int)$r['application_id'];
            if (!isset($out[$aid])) continue;
            $iid = (int)$r['id'];
            $r['reviewers'] = [];

            if ($r['status'] === 'scheduled') {
                $out[$aid]['open']++;
                if ((string)$r['scheduled_at'] <= $now) $out[$aid]['need_outcome']++;
            } elseif ($r['status'] === 'no_show') {
                $out[$aid]['no_show']++;
            } elseif ($r['status'] === 'completed') {
                $out[$aid]['held']++;
                if ((string)$r['outcome'] !== 'pending') $out[$aid]['latest_outcome'] = (string)$r['outcome'];

                $fbRows = $fbMap[$iid] ?? [];
                $seen   = [];

                foreach (($panelMap[$iid] ?? []) as $p) {
                    $uid  = $p['user_id'] !== null ? (int)$p['user_id'] : null;
                    $name = (string)$p['interviewer_name'];
                    $mail = (string)$p['interviewer_email'];
                    $key  = idr_key($uid, $name, $mail);
                    if (isset($seen[$key])) continue;
                    $seen[$key] = true;

                    $fb = $fbRows[$key] ?? null;
                    $r['reviewers'][] = ['name' => $name, 'email' => $mail, 'assigned' => true, 'fb' => $fb];
                    $out[$aid]['assigned']++;
                    if ($fb) {
                        $out[$aid]['received']++;
                    } else {
                        $out[$aid]['missing'][] = $name . ' (' . (string)$r['round_label'] . ')';
                    }
                }

                foreach ($fbRows as $key => $fb) {
                    if (isset($seen[(string)$key])) continue;
                    $r['reviewers'][] = [
                        'name' => (string)$fb['reviewer_name'], 'email' => (string)$fb['reviewer_email'],
                        'assigned' => false, 'fb' => $fb,
                    ];
                }

                foreach ($fbRows as $fb) {
                    $sums[$aid] += (int)$fb['overall_rating'];
                    $out[$aid]['cnt']++;
                    if (in_array((string)$fb['recommendation'], IDR_POSITIVE_RECS, true)) {
                        $out[$aid]['pos']++;
                    } else {
                        $out[$aid]['neg']++;
                    }
                }
            }

            $out[$aid]['ivs'][]   = $r;
            $out[$aid]['last_at'] = (string)$r['scheduled_at'];
        }

        foreach ($out as $aid => $d) {
            if ($d['cnt'] > 0) {
                $out[$aid]['avg'] = $sums[$aid] / $d['cnt'];
                if ($d['neg'] === 0)     { $out[$aid]['consensus'] = 'Aligned: hire';    $out[$aid]['class'] = 'is-good'; }
                elseif ($d['pos'] === 0) { $out[$aid]['consensus'] = 'Aligned: no hire'; $out[$aid]['class'] = 'is-bad'; }
                else                     { $out[$aid]['consensus'] = 'Mixed views';      $out[$aid]['class'] = 'is-warn'; }
            }
        }

        return $out;
    }
}

if (!function_exists('idr_warnings')) {
    /** Things the recruiter must acknowledge before the chosen step is recorded. */
    function idr_warnings(array $d, string $step): array
    {
        $w       = [];
        $missing = count($d['missing']);
        if ($missing > 0) {
            $w[] = 'Feedback is still outstanding from ' . $missing . ' interviewer' . ($missing === 1 ? '' : 's') . '.';
        }

        $latest = (string)$d['latest_outcome'];

        if (in_array($step, ['next_round', 'assessment', 'selected'], true)) {
            if ($latest === 'reject') $w[] = 'The latest interview outcome was "Reject".';
            if ($d['cnt'] > 0 && $d['pos'] === 0) $w[] = 'Every interviewer recommended no hire.';
        } elseif ($step === 'close') {
            if (in_array($latest, ['offer', 'recommend'], true)) $w[] = 'The latest interview outcome was positive.';
            if ($d['cnt'] > 0 && $d['neg'] === 0) $w[] = 'Every interviewer recommended hire.';
        }

        return $w;
    }
}

/* ==================================================================
 | Schema
 * ==================================================================*/

$schemaReady  = rc_has_table($conn, 'recruiter_interviews')
             && rc_has_table($conn, 'recruiter_interview_interviewers')
             && rc_has_table($conn, 'applications');
$logReady     = $schemaReady && idr_ensure_log_table($conn);
$hasDecisions = $schemaReady && rc_has_table($conn, 'selection_decisions');

$now = date('Y-m-d H:i:s');

/* ==================================================================
 | POST handling (post → redirect → get)
 * ==================================================================*/

if ($schemaReady && $_SERVER['REQUEST_METHOD'] === 'POST') {

    rc_verify_csrf();

    $redirect = url('recruiter/interview_decisions.php');
    $action   = trim((string)($_POST['action'] ?? ''));

    try {

        /* -------------------- Record interview result -------------------- */
        if ($action === 'result') {

            $id        = (int)($_POST['interview_id'] ?? 0);
            $iv        = idr_fetch_owned($conn, $id, $recruiterId);
            $result    = (string)($_POST['result'] ?? '');
            $outcome   = (string)($_POST['outcome'] ?? '');
            $notes     = trim((string)($_POST['outcome_notes'] ?? ''));
            $ratingRaw = trim((string)($_POST['rating'] ?? ''));
            $rating    = $ratingRaw === '' ? null : (int)$ratingRaw;

            $errors = [];
            if (!$iv) {
                $errors[] = 'Interview not found.';
            } else {
                if ($iv['status'] === 'cancelled') {
                    $errors[] = 'A cancelled interview cannot have an outcome.';
                }
                if ($iv['status'] === 'scheduled' && $iv['scheduled_at'] > $now) {
                    $errors[] = 'An outcome can only be recorded once the interview has started.';
                }
            }
            if (!in_array($result, ['completed', 'no_show'], true)) {
                $errors[] = 'Please choose whether the interview took place.';
            }
            if ($result === 'completed') {
                if (!isset(IDR_OUTCOMES[$outcome]))      $errors[] = 'Please choose an outcome.';
                if ($rating !== null && ($rating < 1 || $rating > 5)) $errors[] = 'Rating must be between 1 and 5.';
                if ($notes === '')                       $errors[] = 'Please add interview feedback notes.';
            } else {
                $outcome = 'pending';
                $rating  = null;
            }
            if (mb_strlen($notes) > 4000)                $errors[] = 'Notes are too long (4000 characters max).';

            if ($errors) {
                rc_flash('danger', implode(' ', $errors));
            } else {
                $stmt = $conn->prepare(
                    "UPDATE recruiter_interviews
                     SET status = ?, outcome = ?, rating = ?, outcome_notes = ?,
                         outcome_recorded_at = ?
                     WHERE id = ? AND recruiter_id = ? AND status <> 'cancelled'"
                );
                $stmt->bind_param('ssissii', $result, $outcome, $rating, $notes, $now, $id, $recruiterId);
                $stmt->execute();
                $stmt->close();

                if ($result === 'completed' && $iv) {
                    try {
                        $appId = (int)$iv['application_id'];
                        $stmt  = $conn->prepare(
                            "SELECT COUNT(*) FROM recruiter_interviews
                             WHERE application_id = ? AND status = 'scheduled'"
                        );
                        $stmt->bind_param('i', $appId);
                        $stmt->execute();
                        $remaining = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
                        $stmt->close();

                        if ($remaining === 0) {
                            idr_sync_status($conn, $appId, 'interview_completed', ['interview_scheduled'], $recruiterId, 'Interview completed');
                        }
                    } catch (Throwable $t) {
                        error_log('interview_decisions.php status sync: ' . $t->getMessage());
                    }
                }

                rc_activity(
                    $conn, $recruiterId, 'interview_outcome_recorded',
                    'Recorded ' . ($result === 'no_show' ? 'no-show' : 'outcome "' . $outcome . '"') . ' for interview #' . $id,
                    'recruiter_interview', $id
                );
                rc_flash('success', 'Interview result saved.');
            }
        }

        /* -------------------- Record next step / decision -------------------- */
        elseif ($action === 'progress') {

            $appId = (int)($_POST['application_id'] ?? 0);
            $step  = (string)($_POST['step'] ?? '');
            $note  = trim((string)($_POST['decision_note'] ?? ''));
            $ack   = !empty($_POST['ack']);

            $errors = [];
            $app    = null;
            $data   = null;

            if (!$logReady) {
                $errors[] = 'The decision log is not available on this system.';
            }
            if (!isset(IDR_STEPS[$step])) {
                $errors[] = 'Please choose a next step.';
            }
            if (mb_strlen($note) < 10) {
                $errors[] = 'Please document the reason for this step (at least 10 characters).';
            } elseif (mb_strlen($note) > 2000) {
                $errors[] = 'The documented reason is too long (2000 characters max).';
            }

            if (!$errors) {
                $stmt = $conn->prepare('SELECT id, status, candidate_id, opportunity_id FROM applications WHERE id = ? LIMIT 1');
                $stmt->bind_param('i', $appId);
                $stmt->execute();
                $app = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $collected = $app ? idr_collect($conn, $recruiterId, [$appId], $now) : [];
                $data      = $collected[$appId] ?? null;

                $meta = IDR_STEPS[$step];

                if (!$app) {
                    $errors[] = 'Application not found.';
                } elseif (!$data || count($data['ivs']) === 0) {
                    $errors[] = 'You have no interviews recorded for this application.';
                } elseif (!in_array((string)$app['status'], IDR_ACTIONABLE, true)) {
                    $errors[] = 'This application is at a stage where a next step can no longer be recorded here.';
                } elseif ($data['open'] > 0) {
                    $errors[] = 'Record the result of, or cancel, every scheduled interview first.';
                } elseif ($data['held'] === 0) {
                    $errors[] = 'At least one interview must be held and its result recorded first.';
                } elseif (in_array((string)$app['status'], IDR_DECISION_STATUSES, true) && $meta['decision'] === null) {
                    $errors[] = 'A recorded decision can only be changed to another decision.';
                } elseif ($meta['target'] !== null && !idr_app_status_supported($conn, (string)$meta['target'])) {
                    $errors[] = 'This step is not supported by the application workflow on this system.';
                } else {
                    $warnings = idr_warnings($data, $step);
                    if ($warnings && !$ack) {
                        $errors[] = implode(' ', $warnings) . ' Tick the acknowledgement to proceed anyway.';
                    }
                }
            }

            if ($errors) {
                rc_flash('danger', implode(' ', $errors));
            } else {
                $from          = (string)$app['status'];
                $to            = $meta['target'];
                $dec           = $meta['decision'];
                $candidateId   = (int)$app['candidate_id'];
                $opportunityId = (int)$app['opportunity_id'];
                $lastIv        = end($data['ivs']);
                $lastIvId      = $lastIv ? (int)$lastIv['id'] : 0;

                $prevDecision = '';
                if ($hasDecisions) {
                    $stmt = $conn->prepare('SELECT decision FROM selection_decisions WHERE application_id = ? LIMIT 1');
                    $stmt->bind_param('i', $appId);
                    $stmt->execute();
                    $prevDecision = (string)($stmt->get_result()->fetch_row()[0] ?? '');
                    $stmt->close();
                }

                $ok      = false;
                $failMsg = '';

                $conn->begin_transaction();
                try {
                    $proceed = true;

                    if ($to !== null && $to !== $from) {
                        $stmt = $conn->prepare('UPDATE applications SET status = ? WHERE id = ? AND status = ?');
                        $stmt->bind_param('sis', $to, $appId, $from);
                        $stmt->execute();
                        $changed = $stmt->affected_rows > 0;
                        $stmt->close();

                        if (!$changed) {
                            $proceed = false;
                            $failMsg = 'The application changed while you were working. Please reload and try again.';
                            $conn->rollback();
                        } elseif (rc_has_table($conn, 'application_status_history')) {
                            $reason = mb_substr('Interview decision (' . IDR_STEPS[$step]['label'] . '): ' . $note, 0, 500);
                            $stmt = $conn->prepare(
                                'INSERT INTO application_status_history
                                    (application_id, previous_status, new_status, changed_by, change_reason)
                                 VALUES (?, ?, ?, ?, ?)'
                            );
                            $stmt->bind_param('issis', $appId, $from, $to, $recruiterId, $reason);
                            $stmt->execute();
                            $stmt->close();
                        }
                    }

                    if ($proceed) {
                        if ($dec !== null && $hasDecisions) {
                            $stmt = $conn->prepare(
                                'INSERT INTO selection_decisions
                                    (application_id, candidate_id, opportunity_id, decision, decided_by, decision_note)
                                 VALUES (?, ?, ?, ?, ?, ?)
                                 ON DUPLICATE KEY UPDATE
                                    decision = VALUES(decision), decided_by = VALUES(decided_by),
                                    decision_note = VALUES(decision_note), decided_at = NOW()'
                            );
                            $stmt->bind_param('iiisis', $appId, $candidateId, $opportunityId, $dec, $recruiterId, $note);
                            $stmt->execute();
                            $stmt->close();
                        }

                        $toStatus = $to !== null ? (string)$to : $from;
                        $held     = (int)$data['held'];
                        $received = (int)$data['received'];
                        $expected = (int)$data['assigned'];
                        $avg      = $data['avg'] !== null ? round((float)$data['avg'], 1) : null;
                        $ackInt   = $ack ? 1 : 0;

                        $stmt = $conn->prepare(
                            'INSERT INTO recruiter_interview_decision_log
                                (recruiter_id, application_id, candidate_id, step, from_status, to_status,
                                 decision_note, interviews_held, feedback_received, feedback_expected,
                                 avg_rating, acknowledged)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                        );
                        $stmt->bind_param(
                            'iiissssiiidi',
                            $recruiterId, $appId, $candidateId, $step, $from, $toStatus,
                            $note, $held, $received, $expected, $avg, $ackInt
                        );
                        $stmt->execute();
                        $stmt->close();

                        $conn->commit();
                        $ok = true;
                    }
                } catch (Throwable $t) {
                    $conn->rollback();
                    throw $t;
                }

                if (!$ok) {
                    rc_flash('danger', $failMsg !== '' ? $failMsg : 'The step could not be recorded.');
                } else {
                    if ($dec !== null) {
                        if ($prevDecision !== $dec) {
                            idr_notify_candidate(
                                $conn, $candidateId, 'Application decision',
                                IDR_DECISION_MESSAGES[$dec], $lastIvId, 'decision_' . $dec
                            );
                        }
                    } elseif ($step === 'assessment') {
                        idr_notify_candidate(
                            $conn, $candidateId, 'Application update',
                            'Your application has progressed to the assessment stage. Further details will be shared with you.',
                            $lastIvId, 'assessment'
                        );
                    }

                    rc_activity(
                        $conn, $recruiterId, 'interview_next_step',
                        'Recorded step "' . $step . '" for application #' . $appId,
                        'recruiter_interview', $lastIvId
                    );

                    if ($step === 'next_round') {
                        $redirect = url('recruiter/interview_workspace.php?schedule=' . $appId);
                        rc_flash('success', 'Step documented. Schedule the next interview round below.');
                    } else {
                        rc_flash('success', 'Step recorded: ' . IDR_STEPS[$step]['label'] . '.');
                    }
                }
            }
        }

        else {
            rc_flash('danger', 'Unknown action.');
        }

    } catch (Throwable $t) {
        error_log('interview_decisions.php: ' . $t->getMessage());
        rc_flash('danger', 'Something went wrong while saving. Please try again.');
    }

    header('Location: ' . $redirect);
    exit;
}

/* ==================================================================
 | Data for display
 * ==================================================================*/

$tab = (string)($_GET['tab'] ?? 'all');
if (!in_array($tab, ['all', 'ready', 'progress', 'decided'], true)) $tab = 'all';
$q = trim((string)($_GET['q'] ?? ''));

$cards = [];
$stats = ['ready' => 0, 'progress' => 0, 'decided' => 0, 'feedback' => 0];

if ($schemaReady) {

    $appIds = [];
    $stmt = $conn->prepare(
        "SELECT application_id, MAX(scheduled_at) AS last_at
         FROM recruiter_interviews
         WHERE recruiter_id = ? AND status <> 'cancelled'
         GROUP BY application_id
         ORDER BY last_at DESC
         LIMIT 200"
    );
    if ($stmt) {
        $stmt->bind_param('i', $recruiterId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $appIds[] = (int)$r['application_id'];
        $stmt->close();
    }

    if ($appIds) {

        $alist = implode(',', $appIds);

        $appMap = [];
        $res = $conn->query(
            "SELECT a.id, a.application_reference, a.status, a.candidate_id, a.opportunity_id,
                    u.first_name, u.last_name, u.email, o.title AS opportunity_title
             FROM applications a
             LEFT JOIN users u ON u.id = a.candidate_id
             LEFT JOIN opportunities o ON o.id = a.opportunity_id
             WHERE a.id IN ($alist)"
        );
        if ($res) {
            while ($r = $res->fetch_assoc()) $appMap[(int)$r['id']] = $r;
            $res->free();
        }

        $decisionMap = [];
        if ($hasDecisions) {
            $res = $conn->query(
                "SELECT application_id, decision, decision_note, decided_at
                 FROM selection_decisions WHERE application_id IN ($alist)"
            );
            if ($res) {
                while ($r = $res->fetch_assoc()) $decisionMap[(int)$r['application_id']] = $r;
                $res->free();
            }
        }

        $logMap = [];
        if ($logReady) {
            $stmt = $conn->prepare(
                "SELECT application_id, step, to_status, decision_note, created_at
                 FROM recruiter_interview_decision_log
                 WHERE recruiter_id = ? AND application_id IN ($alist)
                 ORDER BY id DESC LIMIT 300"
            );
            if ($stmt) {
                $stmt->bind_param('i', $recruiterId);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($r = $res->fetch_assoc()) {
                    $aid = (int)$r['application_id'];
                    if (!isset($logMap[$aid]) || count($logMap[$aid]) < 3) $logMap[$aid][] = $r;
                }
                $stmt->close();
            }
        }

        $collected = idr_collect($conn, $recruiterId, $appIds, $now);

        foreach ($appIds as $aid) {
            $app  = $appMap[$aid] ?? null;
            $data = $collected[$aid] ?? null;
            if (!$app || !$data || !$data['ivs']) continue;

            $status   = (string)$app['status'];
            $decision = (string)($decisionMap[$aid]['decision'] ?? '');
            $name     = trim((string)$app['first_name'] . ' ' . (string)$app['last_name']);

            $blockers = [];
            if ($data['open'] > 0) {
                $blockers[] = $data['open'] . ' interview' . ($data['open'] === 1 ? ' is' : 's are')
                            . ' still scheduled. Record the result or cancel it first.';
            }
            if ($data['held'] === 0) {
                $blockers[] = 'No held interview with a recorded result yet.';
            }
            $actionable = in_array($status, IDR_ACTIONABLE, true);
            $decided    = $decision !== '' || in_array($status, IDR_DECIDED_STATUSES, true);
            $canAct     = $actionable && !$blockers;

            if ($decided)      $group = 'decided';
            elseif ($canAct)   $group = 'ready';
            else               $group = 'progress';

            if ($q !== '') {
                $hay = strtolower(implode(' ', [
                    $name, (string)$app['email'], (string)$app['application_reference'], (string)$app['opportunity_title'],
                ]));
                if (!str_contains($hay, strtolower($q))) continue;
            }

            $stats[$group]++;
            $stats['feedback'] += count($data['missing']);

            if ($tab !== 'all' && $tab !== $group) continue;

            $cards[] = [
                'id'         => $aid,
                'app'        => $app,
                'name'       => $name,
                'status'     => $status,
                'decision'   => $decision,
                'data'       => $data,
                'blockers'   => $blockers,
                'actionable' => $actionable,
                'can_act'    => $canAct,
                'group'      => $group,
                'log'        => $logMap[$aid] ?? [],
            ];
        }
    }
}

$csrf   = rc_csrf();
$action = url('recruiter/interview_decisions.php');

$statusLabels = [
    'scheduled' => 'Scheduled',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
    'no_show'   => 'No-show',
];

require __DIR__ . '/_layout_start.php';
?>

<style>
.idr-toolbar{display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;margin-bottom:16px}
.idr-tabs{display:flex;flex-wrap:wrap;gap:6px}
.idr-tab{padding:8px 13px;border-radius:999px;text-decoration:none;font-size:13px;font-weight:600;color:inherit;background:rgba(127,127,127,.10)}
.idr-tab.is-active{background:#2454d8;color:#fff}
.idr-search{display:flex;gap:8px}
.idr-search input{min-width:220px}

.idr-field{display:flex;flex-direction:column;gap:6px;margin-bottom:14px}
.idr-field label{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;opacity:.75}
.idr-input,.idr-select,.idr-textarea{width:100%;box-sizing:border-box;padding:10px 12px;border-radius:10px;border:1px solid rgba(127,127,127,.35);background:transparent;color:inherit;font:inherit}
.idr-textarea{min-height:84px;resize:vertical}
.idr-input:focus,.idr-select:focus,.idr-textarea:focus{outline:2px solid #2454d8;outline-offset:1px}
.idr-hint{font-size:12px;opacity:.7}
.idr-row2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media(max-width:600px){.idr-row2{grid-template-columns:1fr}}

.idr-cell small{display:block;opacity:.65;margin-top:3px}
.idr-actions{display:flex;flex-wrap:wrap;gap:6px}
.idr-stars{letter-spacing:2px;color:#e6a218}

.idr-head{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:12px}
.idr-pill{display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700;background:rgba(127,127,127,.12)}
.idr-pill.is-good{background:rgba(20,184,123,.16);color:#0b6b48}
.idr-pill.is-bad{background:rgba(210,75,75,.16);color:#a02727}
.idr-pill.is-warn{background:rgba(230,162,24,.18);color:#8a5a00}
[data-theme="dark"] .idr-pill.is-good{color:#6ee7b7}
[data-theme="dark"] .idr-pill.is-bad{color:#fca5a5}
[data-theme="dark"] .idr-pill.is-warn{color:#fcd34d}

.idr-rv{display:grid;grid-template-columns:minmax(150px,1fr) minmax(160px,1.2fr);gap:10px;padding:8px 0;border-top:1px solid rgba(127,127,127,.14);align-items:center}
.idr-rv details{grid-column:1 / -1;font-size:13px;line-height:1.5}
.idr-rv details p{margin:4px 0 0}
.idr-rv summary{cursor:pointer;opacity:.8}
@media(max-width:700px){.idr-rv{grid-template-columns:1fr}}

.idr-decide{margin-top:16px;padding:16px;border-radius:14px;border:1px solid rgba(127,127,127,.25);background:rgba(36,84,216,.05)}
.idr-decide h4{margin:0 0 10px}
.idr-notice{padding:10px 12px;border-radius:10px;font-size:13px;margin-bottom:10px;background:rgba(230,162,24,.14)}
.idr-notice ul{margin:6px 0 0;padding-left:18px}
.idr-check{display:flex;gap:8px;align-items:flex-start;font-size:13px;margin-bottom:14px}
.idr-log{margin-top:12px;font-size:12px;opacity:.85}
.idr-log div{margin-top:4px}

.idr-modal{position:fixed;inset:0;z-index:10000;display:none;align-items:center;justify-content:center;padding:24px}
.idr-modal.is-open{display:flex}
.idr-modal__backdrop{position:absolute;inset:0;background:rgba(15,23,42,.70);backdrop-filter:blur(5px)}
.idr-modal__dialog{position:relative;z-index:1;width:min(620px,100%);max-height:90vh;display:flex;flex-direction:column;overflow:hidden;border-radius:22px;background:#fff;color:#172b4d;box-shadow:0 25px 80px rgba(0,0,0,.28)}
.idr-modal__header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:22px 24px 16px;border-bottom:1px solid rgba(127,127,127,.16)}
.idr-modal__header h3{margin:4px 0 2px}
.idr-modal__header p{margin:0;opacity:.7}
.idr-modal__close{width:40px;height:40px;flex:0 0 40px;display:grid;place-items:center;border:0;border-radius:50%;cursor:pointer;background:rgba(15,23,42,.07);color:inherit}
.idr-modal__body{padding:18px 24px;overflow-y:auto}
.idr-modal__footer{display:flex;justify-content:flex-end;gap:10px;padding:14px 24px;border-top:1px solid rgba(127,127,127,.16)}
body.idr-modal-open{overflow:hidden}
html[data-theme="dark"] .idr-modal__dialog,body.dark-mode .idr-modal__dialog{background:#111827;color:#f8fafc}
html[data-theme="dark"] .idr-modal__close,body.dark-mode .idr-modal__close{background:rgba(255,255,255,.08);color:#f8fafc}
html[data-theme="dark"] .idr-select option,body.dark-mode .idr-select option{background:#111827;color:#f8fafc}
@media(max-width:768px){.idr-modal{padding:12px}.idr-modal__dialog{border-radius:16px}}
</style>

<!-- ================================================================
     HERO
================================================================ -->

<section class="rc-hero">
    <div>
        <span class="rc-eyebrow"><i class="fas fa-gavel"></i> Interview outcomes</span>
        <h2>Interview Results &amp; Decisions</h2>
        <p>
            Record interview results, review consolidated interviewer feedback and
            document the next step for every candidate: progress them to the next
            stage or close the application.
        </p>
        <div class="rc-hero__actions">
            <a class="rc-btn rc-btn--light" href="<?= url('recruiter/interview_workspace.php') ?>">
                <i class="fas fa-calendar-check"></i> Interview Workspace
            </a>
            <a class="rc-btn rc-btn--glass" href="<?= url('recruiter/follow_up.php') ?>">
                <i class="fas fa-route"></i> Follow-Up Pipeline
            </a>
        </div>
    </div>
</section>

<?php if (!$schemaReady): ?>

    <div class="rc-card"><div class="rc-card__body">
        <div class="rc-empty">
            <i class="fas fa-database"></i>
            <strong>Interview tables not found</strong>
            <span>Open the Interview Workspace once so its tables are created, then reload this page.</span>
        </div>
    </div></div>

<?php else: ?>

<section class="rc-stats">
    <article><i class="fas fa-gavel"></i><strong><?= number_format($stats['ready']) ?></strong><span>Ready for decision</span></article>
    <article><i class="fas fa-hourglass-half"></i><strong><?= number_format($stats['progress']) ?></strong><span>In progress</span></article>
    <article><i class="fas fa-circle-check"></i><strong><?= number_format($stats['decided']) ?></strong><span>Decided</span></article>
    <article><i class="fas fa-user-pen"></i><strong><?= number_format($stats['feedback']) ?></strong><span>Feedback outstanding</span></article>
</section>

<section class="rc-card rc-space" id="decisions">

    <div class="rc-card__header">
        <div>
            <span class="rc-eyebrow">Candidates</span>
            <h3>Results, feedback and next steps</h3>
            <p>Every step you record is documented with your reasoning and kept in an audit log.</p>
        </div>
    </div>

    <div class="rc-card__body">

        <div class="idr-toolbar">
            <nav class="idr-tabs" aria-label="Filter candidates">
                <?php
                $tabDefs = ['all' => 'All', 'ready' => 'Ready for decision', 'progress' => 'In progress', 'decided' => 'Decided'];
                foreach ($tabDefs as $key => $label):
                    $href = url('recruiter/interview_decisions.php?tab=' . $key . ($q !== '' ? '&q=' . urlencode($q) : ''));
                ?>
                    <a class="idr-tab <?= $tab === $key ? 'is-active' : '' ?>" href="<?= $href ?>"><?= e($label) ?></a>
                <?php endforeach; ?>
            </nav>

            <form class="idr-search" method="get" action="<?= $action ?>">
                <input type="hidden" name="tab" value="<?= e($tab) ?>">
                <input class="idr-input" type="search" name="q" value="<?= e($q) ?>"
                       placeholder="Name, email, role or reference" aria-label="Search candidates">
                <button class="rc-btn rc-btn--secondary rc-btn--small" type="submit">
                    <i class="fas fa-magnifying-glass"></i> Search
                </button>
            </form>
        </div>

        <?php if (!$cards): ?>
            <div class="rc-empty">
                <i class="fas fa-clipboard-list"></i>
                <strong>No candidates found</strong>
                <span>
                    <?= ($tab !== 'all' || $q !== '')
                        ? 'Nothing matches the current filter.'
                        : 'Candidates appear here once you schedule an interview for their application.' ?>
                </span>
            </div>
        <?php endif; ?>

    </div>
</section>

<?php foreach ($cards as $c):
    $aid    = (int)$c['id'];
    $app    = $c['app'];
    $d      = $c['data'];
    $status = $c['status'];
    $ref    = (string)($app['application_reference'] ?? '');
    $search = $ref !== '' ? $ref : (string)($app['email'] ?? '');
    $fbLink = url('recruiter/interview_workspace.php?tab=completed&q=' . urlencode($search));
?>
<section class="rc-card rc-space" id="app-<?= $aid ?>">

    <div class="rc-card__header">
        <div>
            <span class="rc-eyebrow"><?= e((string)($app['opportunity_title'] ?: 'Opportunity not set')) ?></span>
            <h3><?= e($c['name'] !== '' ? $c['name'] : 'Candidate #' . (int)$app['candidate_id']) ?></h3>
            <p>
                <?= e((string)($app['email'] ?? '')) ?>
                <?= $ref !== '' ? ' · ' . e($ref) : '' ?>
            </p>
        </div>
    </div>

    <div class="rc-card__body">

        <div class="idr-head">
            <span class="idr-pill">Application: <?= e(rc_label($status)) ?></span>
            <?php if ($c['decision'] !== ''): ?>
                <span class="idr-pill is-good">Decision: <?= e(IDR_DECISION_LABELS[$c['decision']] ?? rc_label($c['decision'])) ?></span>
            <?php endif; ?>
            <span class="idr-pill"><?= (int)$d['held'] ?> held · <?= (int)$d['open'] ?> scheduled<?= $d['no_show'] > 0 ? ' · ' . (int)$d['no_show'] . ' no-show' : '' ?></span>
            <?php if ($d['assigned'] > 0): ?>
                <span class="idr-pill <?= $d['received'] >= $d['assigned'] ? 'is-good' : 'is-warn' ?>">
                    <?= (int)$d['received'] ?> of <?= (int)$d['assigned'] ?> feedback received
                </span>
            <?php endif; ?>
            <?php if ($d['avg'] !== null): ?>
                <span class="idr-pill">Average <?= number_format((float)$d['avg'], 1) ?> / 5</span>
            <?php endif; ?>
            <?php if ($d['consensus'] !== ''): ?>
                <span class="idr-pill <?= e($d['class']) ?>"><?= e($d['consensus']) ?></span>
            <?php endif; ?>
        </div>

        <div class="rc-table-wrap">
            <table class="rc-table">
                <thead>
                    <tr>
                        <th>Interview</th>
                        <th>When</th>
                        <th>Result</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($d['ivs'] as $iv):
                    $iid       = (int)$iv['id'];
                    $isSched   = $iv['status'] === 'scheduled';
                    $started   = (string)$iv['scheduled_at'] <= $now;
                    $isHeldRow = in_array($iv['status'], ['completed', 'no_show'], true);
                    $statusCls = $iv['status'] === 'completed' ? 'rc-status--success'
                               : ($iv['status'] === 'no_show' ? 'rc-status--danger' : '');
                ?>
                    <tr>
                        <td class="idr-cell">
                            <strong><?= e((string)$iv['round_label']) ?></strong>
                            <small><?= e((string)$iv['mode']) ?></small>
                        </td>
                        <td class="idr-cell">
                            <?= e(idr_fmt((string)$iv['scheduled_at'])) ?>
                            <small><?= (int)$iv['duration_minutes'] ?> min</small>
                        </td>
                        <td class="idr-cell">
                            <span class="rc-status <?= $statusCls ?>"><?= e($statusLabels[$iv['status']] ?? (string)$iv['status']) ?></span>
                            <?php if ($isSched && $started): ?><small>Result needed</small><?php endif; ?>
                            <?php if ($iv['status'] === 'completed'): ?>
                                <small><?= e(IDR_OUTCOME_LABELS[$iv['outcome']] ?? '') ?></small>
                                <?php if ($iv['rating'] !== null): ?>
                                    <small class="idr-stars" aria-label="Rating <?= (int)$iv['rating'] ?> of 5"><?= e(idr_stars((int)$iv['rating'])) ?></small>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="idr-actions">
                                <?php if (($isSched && $started) || $isHeldRow): ?>
                                    <button type="button" class="rc-btn rc-btn--primary rc-btn--small"
                                            data-idr-open="idrResultModal"
                                            data-id="<?= $iid ?>"
                                            data-result="<?= e($iv['status'] === 'no_show' ? 'no_show' : 'completed') ?>"
                                            data-outcome="<?= e((string)$iv['outcome'] !== 'pending' ? (string)$iv['outcome'] : '') ?>"
                                            data-rating="<?= $iv['rating'] !== null ? (int)$iv['rating'] : '' ?>"
                                            data-notes="<?= e((string)$iv['outcome_notes']) ?>"
                                            data-name="<?= e($c['name']) ?>">
                                        <i class="fas fa-clipboard-check"></i> <?= $isSched ? 'Record result' : 'Edit result' ?>
                                    </button>
                                <?php endif; ?>
                                <?php if ($iv['status'] === 'completed'): ?>
                                    <a class="rc-btn rc-btn--secondary rc-btn--small" href="<?= e($fbLink) ?>"
                                       title="Add or edit interviewer feedback in the Interview Workspace">
                                        <i class="fas fa-user-pen"></i> Feedback
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>

                    <?php if ($isHeldRow && (string)$iv['outcome_notes'] !== ''): ?>
                    <tr>
                        <td colspan="4" class="idr-cell">
                            <small><strong>Recruiter notes:</strong> <?= nl2br(e((string)$iv['outcome_notes'])) ?></small>
                        </td>
                    </tr>
                    <?php endif; ?>

                    <?php if ($iv['status'] === 'completed' && $iv['reviewers']): ?>
                    <tr>
                        <td colspan="4">
                            <?php foreach ($iv['reviewers'] as $rv):
                                $fb = $rv['fb'];
                            ?>
                                <div class="idr-rv">
                                    <div class="idr-cell">
                                        <strong><?= e($rv['name']) ?></strong>
                                        <?php if ($rv['email'] !== ''): ?><small><?= e($rv['email']) ?></small><?php endif; ?>
                                        <?php if (!$rv['assigned']): ?><small>No longer assigned to this interview</small><?php endif; ?>
                                    </div>
                                    <div>
                                        <?php if ($fb):
                                            $rec = (string)$fb['recommendation'];
                                        ?>
                                            <span class="idr-stars" title="<?= (int)$fb['overall_rating'] ?> / 5"><?= e(idr_stars((int)$fb['overall_rating'])) ?></span>
                                            <span class="idr-pill <?= in_array($rec, IDR_POSITIVE_RECS, true) ? 'is-good' : 'is-bad' ?>">
                                                <?= e(IDR_RECOMMENDATIONS[$rec] ?? rc_label($rec)) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="idr-pill is-warn">Feedback pending</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($fb):
                                        $sub = [];
                                        if ($fb['technical_rating'] !== null)     $sub[] = 'Technical ' . (int)$fb['technical_rating'] . '/5';
                                        if ($fb['communication_rating'] !== null) $sub[] = 'Communication ' . (int)$fb['communication_rating'] . '/5';
                                        if ($fb['culture_rating'] !== null)       $sub[] = 'Culture fit ' . (int)$fb['culture_rating'] . '/5';
                                    ?>
                                        <details>
                                            <summary>Read feedback</summary>
                                            <?php if ($sub): ?><p><strong>Scores:</strong> <?= e(implode(' · ', $sub)) ?></p><?php endif; ?>
                                            <?php if ((string)$fb['strengths'] !== ''): ?><p><strong>Strengths:</strong> <?= nl2br(e((string)$fb['strengths'])) ?></p><?php endif; ?>
                                            <?php if ((string)$fb['concerns'] !== ''): ?><p><strong>Concerns:</strong> <?= nl2br(e((string)$fb['concerns'])) ?></p><?php endif; ?>
                                            <?php if ((string)$fb['comments'] !== ''): ?><p><strong>Comments:</strong> <?= nl2br(e((string)$fb['comments'])) ?></p><?php endif; ?>
                                        </details>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ---------------- Next step ---------------- -->
        <div class="idr-decide">
            <h4>Next step</h4>

            <?php if ($c['blockers']): ?>
                <div class="idr-notice">
                    <strong>Not ready yet</strong>
                    <ul><?php foreach ($c['blockers'] as $b): ?><li><?= e($b) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>

            <?php if (!$c['actionable']): ?>
                <p class="idr-hint">
                    This application is at the “<?= e(rc_label($status)) ?>” stage, so a next step can no longer be recorded here.
                    Use the existing application workflow for further changes.
                </p>
            <?php elseif (!$logReady): ?>
                <p class="idr-hint">The decision log is unavailable on this system, so next steps cannot be recorded.</p>
            <?php elseif ($c['can_act']): ?>

                <?php if ($d['missing'] || $d['latest_outcome'] === 'reject'): ?>
                    <div class="idr-notice">
                        <strong>Review before deciding</strong>
                        <ul>
                            <?php if ($d['missing']): ?>
                                <li>Feedback outstanding from: <?= e(implode(', ', $d['missing'])) ?>.</li>
                            <?php endif; ?>
                            <?php if ($d['latest_outcome'] === 'reject'): ?>
                                <li>The latest interview outcome was “Reject”.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php
                $steps = [];
                foreach (IDR_STEPS as $k => $s) {
                    if (in_array($status, IDR_DECISION_STATUSES, true) && $s['decision'] === null) continue;
                    if ($s['target'] !== null && !idr_app_status_supported($conn, (string)$s['target'])) continue;
                    $steps[$k] = $s;
                }
                ?>

                <form method="post" action="<?= $action ?>" onsubmit="return idrConfirm(this);">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="progress">
                    <input type="hidden" name="application_id" value="<?= $aid ?>">

                    <div class="idr-field">
                        <label for="idrStep<?= $aid ?>">Choose the next step</label>
                        <select class="idr-select" id="idrStep<?= $aid ?>" name="step" required>
                            <option value="">Select…</option>
                            <?php foreach ($steps as $k => $s): ?>
                                <option value="<?= e($k) ?>"><?= e($s['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="idr-hint">
                            <?php foreach ($steps as $s): ?><?= e($s['label']) ?>: <?= e($s['hint']) ?> <?php endforeach; ?>
                        </span>
                    </div>

                    <div class="idr-field">
                        <label for="idrNote<?= $aid ?>">Reason (documented, never shown to the candidate)</label>
                        <textarea class="idr-textarea" id="idrNote<?= $aid ?>" name="decision_note" minlength="10" maxlength="2000" required
                                  placeholder="Summarise the interview results and feedback that support this step…"></textarea>
                    </div>

                    <label class="idr-check">
                        <input type="checkbox" name="ack" value="1">
                        <span>I have reviewed the warnings for this application (outstanding feedback or results that conflict with this step) and want to proceed.</span>
                    </label>

                    <button type="submit" class="rc-btn rc-btn--primary"><i class="fas fa-gavel"></i> Record step</button>
                </form>

            <?php endif; ?>

            <?php if ($c['log']): ?>
                <div class="idr-log">
                    <strong>Recent steps</strong>
                    <?php foreach ($c['log'] as $lg): ?>
                        <div>
                            <?= e(idr_fmt((string)$lg['created_at'], 'd M Y · H:i')) ?> —
                            <?= e(IDR_STEPS[$lg['step']]['label'] ?? rc_label((string)$lg['step'])) ?>:
                            <?= e(mb_substr((string)$lg['decision_note'], 0, 160)) ?><?= mb_strlen((string)$lg['decision_note']) > 160 ? '…' : '' ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</section>
<?php endforeach; ?>

<!-- ================================================================
     MODAL: RECORD RESULT
================================================================ -->

<div class="idr-modal" id="idrResultModal" aria-hidden="true">
    <div class="idr-modal__backdrop" data-idr-close></div>
    <form class="idr-modal__dialog" method="post" action="<?= $action ?>"
          role="dialog" aria-modal="true" aria-labelledby="idrResultTitle">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="result">
        <input type="hidden" name="interview_id" data-fill="id">

        <div class="idr-modal__header">
            <div>
                <span class="rc-eyebrow">Interview result</span>
                <h3 id="idrResultTitle">Record result</h3>
                <p data-fill-text="name"></p>
            </div>
            <button type="button" class="idr-modal__close" data-idr-close aria-label="Close"><i class="fas fa-xmark"></i></button>
        </div>

        <div class="idr-modal__body">
            <div class="idr-field">
                <label for="idrResult">Did the interview take place?</label>
                <select class="idr-select" id="idrResult" name="result" data-fill="result" required>
                    <option value="completed">Yes — interview held</option>
                    <option value="no_show">No — candidate did not attend</option>
                </select>
            </div>

            <div id="idrResultFields">
                <div class="idr-row2">
                    <div class="idr-field">
                        <label for="idrOutcome">Outcome</label>
                        <select class="idr-select" id="idrOutcome" name="outcome" data-fill="outcome">
                            <option value="">Select…</option>
                            <?php foreach (IDR_OUTCOMES as $k => $label): ?>
                                <option value="<?= e($k) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="idr-field">
                        <label for="idrRating">Rating</label>
                        <select class="idr-select" id="idrRating" name="rating" data-fill="rating">
                            <option value="">No rating</option>
                            <option value="1">1 — Poor</option>
                            <option value="2">2 — Below expectations</option>
                            <option value="3">3 — Meets expectations</option>
                            <option value="4">4 — Strong</option>
                            <option value="5">5 — Outstanding</option>
                        </select>
                    </div>
                </div>
                <div class="idr-field">
                    <label for="idrNotes">Feedback notes</label>
                    <textarea class="idr-textarea" id="idrNotes" name="outcome_notes" maxlength="4000"
                              data-fill="notes" placeholder="Strengths, concerns, next steps…"></textarea>
                </div>
            </div>
        </div>

        <div class="idr-modal__footer">
            <button type="button" class="rc-btn rc-btn--secondary" data-idr-close>Close</button>
            <button type="submit" class="rc-btn rc-btn--primary">Save result</button>
        </div>
    </form>
</div>

<script>
function idrConfirm(form) {
    var sel = form.querySelector('[name=step]');
    if (!sel) return true;
    var notifying = ['assessment', 'selected', 'waitlisted', 'close'];
    if (notifying.indexOf(sel.value) !== -1) {
        return window.confirm('This updates the application stage and notifies the candidate. Continue?');
    }
    return true;
}

(function () {
    var openModal = null;

    function closeModal() {
        if (!openModal) return;
        openModal.classList.remove('is-open');
        openModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('idr-modal-open');
        openModal = null;
    }

    function fill(modal, data) {
        modal.querySelectorAll('[data-fill]').forEach(function (el) {
            var key = el.getAttribute('data-fill');
            el.value = (data[key] !== undefined) ? data[key] : '';
        });
        modal.querySelectorAll('[data-fill-text]').forEach(function (el) {
            var key = el.getAttribute('data-fill-text');
            el.textContent = data[key] || '';
        });
    }

    function toggleResultFields() {
        var result = document.getElementById('idrResult');
        var box = document.getElementById('idrResultFields');
        if (!result || !box) return;
        var held = result.value === 'completed';
        box.style.display = held ? '' : 'none';
        document.getElementById('idrOutcome').required = held;
        document.getElementById('idrNotes').required = held;
    }

    document.querySelectorAll('[data-idr-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var modal = document.getElementById(btn.getAttribute('data-idr-open'));
            if (!modal) return;

            closeModal();
            fill(modal, btn.dataset);

            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('idr-modal-open');
            openModal = modal;

            toggleResultFields();

            var first = modal.querySelector('input:not([type=hidden]), select, textarea');
            if (first) first.focus();
        });
    });

    document.querySelectorAll('[data-idr-close]').forEach(function (el) {
        el.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') closeModal();
    });

    var resultSel = document.getElementById('idrResult');
    if (resultSel) resultSel.addEventListener('change', toggleResultFields);
})();
</script>

<?php endif; ?>

<?php
require __DIR__ . '/_layout_end.php';
