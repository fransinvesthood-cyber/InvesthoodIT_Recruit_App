<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('recruiter');

$user        = current_user();
$flashes     = render_flashes();
$currentPage = 'interviews';
$pageTitle   = 'Interview Scheduling & Tracking';

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

const IV_MODES = [
    'online'    => 'Online / video call',
    'in_person' => 'In person',
    'phone'     => 'Phone call',
];

const IV_OUTCOMES = [
    'recommend' => 'Recommend — move forward',
    'offer'     => 'Make an offer',
    'hold'      => 'Hold — undecided',
    'reject'    => 'Reject — not suitable',
];

// Applications in these states are finished; no interviews can be booked for them.
const IV_CLOSED_APP_STATUSES = ['draft', 'rejected', 'withdrawn', 'offer_accepted', 'offer_declined'];

// Selection decisions a recruiter can record after an interview outcome.
const IV_DECISIONS     = ['selected' => 'Selected', 'waitlisted' => 'Waitlisted', 'not_selected' => 'Not selected'];
const IV_DECISION_APP  = ['selected' => 'selected', 'waitlisted' => 'waitlisted', 'not_selected' => 'rejected'];
// A decision can only be set or changed while the application has not reached the offer stage.
const IV_DECISION_FROM = ['interview_scheduled', 'interview_completed', 'selected', 'waitlisted', 'rejected', 'on_hold'];

/* ==================================================================
 | Helpers (page-local, all prefixed iv_)
 * ==================================================================*/

if (!function_exists('iv_ensure_tables')) {
    function iv_ensure_tables(mysqli $conn, ?string &$error): bool
    {
        try {
            if (!rc_has_table($conn, 'recruiter_interviews')) {
                $ok = $conn->query(
                    "CREATE TABLE IF NOT EXISTS recruiter_interviews (
                        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                        recruiter_id INT UNSIGNED NOT NULL,
                        application_id INT UNSIGNED NOT NULL,
                        candidate_id INT UNSIGNED NOT NULL,
                        opportunity_id INT UNSIGNED NULL,
                        round_label VARCHAR(60) NOT NULL DEFAULT 'Interview',
                        scheduled_at DATETIME NOT NULL,
                        duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 45,
                        mode ENUM('online','in_person','phone') NOT NULL DEFAULT 'online',
                        location VARCHAR(255) NOT NULL DEFAULT '',
                        status ENUM('scheduled','completed','cancelled','no_show') NOT NULL DEFAULT 'scheduled',
                        outcome ENUM('pending','recommend','offer','hold','reject') NOT NULL DEFAULT 'pending',
                        rating TINYINT UNSIGNED NULL,
                        outcome_notes TEXT NULL,
                        outcome_recorded_at DATETIME NULL,
                        cancel_reason VARCHAR(255) NULL,
                        reschedule_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (id),
                        KEY idx_ri_recruiter (recruiter_id, scheduled_at),
                        KEY idx_ri_application (application_id),
                        KEY idx_ri_candidate (candidate_id, scheduled_at),
                        KEY idx_ri_status (status, scheduled_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
                );
                if (!$ok) { $error = $conn->error; return false; }
            }

            if (!rc_has_table($conn, 'recruiter_interview_interviewers')) {
                $ok = $conn->query(
                    "CREATE TABLE IF NOT EXISTS recruiter_interview_interviewers (
                        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                        interview_id INT UNSIGNED NOT NULL,
                        user_id INT UNSIGNED NULL,
                        interviewer_name VARCHAR(150) NOT NULL,
                        interviewer_email VARCHAR(190) NOT NULL DEFAULT '',
                        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (id),
                        KEY idx_rii_interview (interview_id),
                        KEY idx_rii_user (user_id),
                        KEY idx_rii_email (interviewer_email)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
                );
                if (!$ok) { $error = $conn->error; return false; }
            }
        } catch (Throwable $t) {
            $error = $t->getMessage();
            return false;
        }

        return true;
    }
}

if (!function_exists('iv_parse_datetime')) {
    /** Parses an <input type="datetime-local"> value. Returns Y-m-d H:i:s or null. */
    function iv_parse_datetime(string $raw): ?string
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
}

if (!function_exists('iv_staff_list')) {
    /** Active recruiter accounts that can be assigned as interviewers. */
    function iv_staff_list(mysqli $conn): array
    {
        $res = $conn->query(
            "SELECT u.id, u.first_name, u.last_name, u.email
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE r.slug = 'recruiter' AND u.status = 'active'
             ORDER BY u.first_name ASC, u.last_name ASC"
        );
        $out = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $name = trim((string)$r['first_name'] . ' ' . (string)$r['last_name']);
                $out[(int)$r['id']] = [
                    'id'    => (int)$r['id'],
                    'name'  => $name !== '' ? $name : 'Recruiter #' . (int)$r['id'],
                    'email' => trim((string)$r['email']),
                ];
            }
            $res->free();
        }
        return $out;
    }
}

if (!function_exists('iv_build_interviewers')) {
    /**
     * Builds the interviewer list from selected staff ids and a free-text box
     * ("Name, email" per line). Returns [list, errorMessage|null].
     */
    function iv_build_interviewers(array $staffIds, string $extraText, array $staff): array
    {
        $list = [];
        $seen = [];

        foreach ($staffIds as $sid) {
            $sid = (int)$sid;
            if ($sid <= 0 || !isset($staff[$sid]) || isset($seen['u' . $sid])) continue;
            $seen['u' . $sid] = true;
            $list[] = [
                'user_id' => $sid,
                'name'    => $staff[$sid]['name'],
                'email'   => $staff[$sid]['email'],
            ];
        }

        $lines = preg_split('/\r\n|\r|\n/', $extraText) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;

            $parts = array_map('trim', explode(',', $line, 2));
            $name  = $parts[0] ?? '';
            $email = $parts[1] ?? '';

            if ($name === '') {
                return [[], 'Each additional interviewer needs a name.'];
            }
            if (mb_strlen($name) > 150) {
                return [[], 'Interviewer name "' . mb_substr($name, 0, 30) . '…" is too long.'];
            }
            if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190)) {
                return [[], 'The email "' . $email . '" is not valid.'];
            }

            $key = $email !== '' ? 'e' . strtolower($email) : 'n' . strtolower($name);
            if (isset($seen[$key])) continue;
            $seen[$key] = true;

            $list[] = ['user_id' => null, 'name' => $name, 'email' => $email];
        }

        if (count($list) > 12) {
            return [[], 'A maximum of 12 interviewers can be assigned to one interview.'];
        }

        return [$list, null];
    }
}

if (!function_exists('iv_save_interviewers')) {
    function iv_save_interviewers(mysqli $conn, int $interviewId, array $list): void
    {
        $del = $conn->prepare('DELETE FROM recruiter_interview_interviewers WHERE interview_id = ?');
        $del->bind_param('i', $interviewId);
        $del->execute();
        $del->close();

        $ins = $conn->prepare(
            'INSERT INTO recruiter_interview_interviewers
                (interview_id, user_id, interviewer_name, interviewer_email)
             VALUES (?, ?, ?, ?)'
        );
        foreach ($list as $iv) {
            $uid   = $iv['user_id'];
            $name  = $iv['name'];
            $email = $iv['email'];
            $ins->bind_param('iiss', $interviewId, $uid, $name, $email);
            $ins->execute();
        }
        $ins->close();
    }
}

if (!function_exists('iv_conflicts')) {
    /** Returns human-readable clash messages for the proposed time slot. */
    function iv_conflicts(
        mysqli $conn,
        string $start,
        int $durationMinutes,
        int $candidateId,
        array $interviewers,
        int $excludeId = 0
    ): array {
        $messages = [];
        $end = date('Y-m-d H:i:s', strtotime($start) + ($durationMinutes * 60));

        $stmt = $conn->prepare(
            "SELECT id FROM recruiter_interviews
             WHERE candidate_id = ? AND status = 'scheduled' AND id <> ?
               AND scheduled_at < ?
               AND DATE_ADD(scheduled_at, INTERVAL duration_minutes MINUTE) > ?
             LIMIT 1"
        );
        $stmt->bind_param('iiss', $candidateId, $excludeId, $end, $start);
        $stmt->execute();
        if ($stmt->get_result()->fetch_row()) {
            $messages[] = 'The candidate already has an interview that overlaps this time slot.';
        }
        $stmt->close();

        $stmt = $conn->prepare(
            "SELECT ri.id FROM recruiter_interviews ri
             INNER JOIN recruiter_interview_interviewers ii ON ii.interview_id = ri.id
             WHERE ri.status = 'scheduled' AND ri.id <> ?
               AND ri.scheduled_at < ?
               AND DATE_ADD(ri.scheduled_at, INTERVAL ri.duration_minutes MINUTE) > ?
               AND (
                    (ii.user_id IS NOT NULL AND ii.user_id = ?)
                    OR (ii.interviewer_email <> '' AND LOWER(ii.interviewer_email) = ?)
               )
             LIMIT 1"
        );
        foreach ($interviewers as $iv) {
            $uid   = (int)($iv['user_id'] ?? 0);
            $email = strtolower((string)($iv['email'] ?? ''));
            if ($uid === 0 && $email === '') continue;

            // An empty email must never match a stored email.
            $emailParam = $email !== '' ? $email : '__none__';

            $stmt->bind_param('issis', $excludeId, $end, $start, $uid, $emailParam);
            $stmt->execute();
            if ($stmt->get_result()->fetch_row()) {
                $messages[] = $iv['name'] . ' is already booked for another interview in this time slot.';
            }
        }
        $stmt->close();

        return $messages;
    }
}

if (!function_exists('iv_fetch_owned')) {
    function iv_fetch_owned(mysqli $conn, int $id, int $recruiterId): ?array
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

if (!function_exists('iv_fetch_interviewers')) {
    function iv_fetch_interviewers(mysqli $conn, int $interviewId): array
    {
        $stmt = $conn->prepare(
            'SELECT user_id, interviewer_name AS name, interviewer_email AS email
             FROM recruiter_interview_interviewers WHERE interview_id = ? ORDER BY id ASC'
        );
        $stmt->bind_param('i', $interviewId);
        $stmt->execute();
        $res = $stmt->get_result();
        $out = [];
        while ($r = $res->fetch_assoc()) {
            $out[] = [
                'user_id' => $r['user_id'] !== null ? (int)$r['user_id'] : null,
                'name'    => (string)$r['name'],
                'email'   => (string)$r['email'],
            ];
        }
        $stmt->close();
        return $out;
    }
}

if (!function_exists('iv_fmt')) {
    function iv_fmt(?string $dt, string $format = 'D, d M Y · H:i'): string
    {
        if (!$dt) return '—';
        $ts = strtotime($dt);
        return $ts ? date($format, $ts) : '—';
    }
}

if (!function_exists('iv_app_status_supported')) {
    /** True when applications.status can hold $value (guards against ENUMs that lack it). */
    function iv_app_status_supported(mysqli $conn, string $value): bool
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

if (!function_exists('iv_sync_application_status')) {
    /**
     * Moves an application along the existing pipeline stages
     * (shown on the Follow-Up Pipeline page). Only moves it from one of the
     * $from stages, and only when the column supports $to. Every change is
     * written to application_status_history when that table exists.
     */
    function iv_sync_application_status(
        mysqli $conn, int $applicationId, string $to, array $from,
        ?int $changedBy = null, string $reason = ''
    ): void {
        if ($applicationId <= 0 || !$from || !iv_app_status_supported($conn, $to)) return;

        $stmt = $conn->prepare('SELECT status FROM applications WHERE id = ? LIMIT 1');
        if (!$stmt) return;
        $stmt->bind_param('i', $applicationId);
        $stmt->execute();
        $current = (string)($stmt->get_result()->fetch_row()[0] ?? '');
        $stmt->close();

        if ($current === '' || $current === $to || !in_array($current, $from, true)) return;

        $stmt = $conn->prepare('UPDATE applications SET status = ? WHERE id = ? AND status = ?');
        if (!$stmt) return;
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
    }
}

if (!function_exists('iv_notify_candidate')) {
    /** In-app notification for the candidate (best effort; never blocks the action). */
    function iv_notify_candidate(
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
            error_log('interviews.php notify: ' . $t->getMessage());
        }
    }
}

if (!function_exists('iv_ics_escape')) {
    function iv_ics_escape(string $s): string
    {
        $s = str_replace(['\\', ';', ','], ['\\\\', '\\;', '\\,'], $s);
        return str_replace(["\r\n", "\r", "\n"], '\\n', $s);
    }
}

/* ==================================================================
 | Schema
 * ==================================================================*/

$schemaError = null;
$schemaReady = iv_ensure_tables($conn, $schemaError);

$staff = $schemaReady ? iv_staff_list($conn) : [];

$now      = date('Y-m-d H:i:s');
$todayFrom = date('Y-m-d 00:00:00');
$todayTo   = date('Y-m-d 00:00:00', strtotime('+1 day'));

/* ==================================================================
 | Calendar export — interviews.php?ics=ID (before any output)
 * ==================================================================*/

$icsId = (int)($_GET['ics'] ?? 0);

if ($schemaReady && $icsId > 0) {

    $iv = iv_fetch_owned($conn, $icsId, $recruiterId);

    if (!$iv) {
        http_response_code(404);
        exit('Interview not found.');
    }

    $stmt = $conn->prepare(
        'SELECT u.first_name, u.last_name, o.title AS opportunity_title
         FROM recruiter_interviews ri
         LEFT JOIN users u ON u.id = ri.candidate_id
         LEFT JOIN opportunities o ON o.id = ri.opportunity_id
         WHERE ri.id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $icsId);
    $stmt->execute();
    $meta = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    $candName = trim((string)($meta['first_name'] ?? '') . ' ' . (string)($meta['last_name'] ?? ''));
    if ($candName === '') $candName = 'Candidate';

    $people = [];
    foreach (iv_fetch_interviewers($conn, $icsId) as $p) $people[] = $p['name'];

    $startTs = strtotime((string)$iv['scheduled_at']);
    $endTs   = $startTs + ((int)$iv['duration_minutes'] * 60);

    $summary = (string)$iv['round_label'] . ': ' . $candName;
    $desc    = 'Candidate: ' . $candName
             . "\n" . 'Opportunity: ' . (string)($meta['opportunity_title'] ?? '—')
             . "\n" . 'Type: ' . (IV_MODES[$iv['mode']] ?? (string)$iv['mode'])
             . "\n" . 'Interviewers: ' . ($people ? implode(', ', $people) : '—');

    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Investhood IT//Interview Scheduling//EN',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'BEGIN:VEVENT',
        'UID:interview-' . $icsId . '@investhood-it',
        'DTSTAMP:' . gmdate('Ymd\THis\Z'),
        'DTSTART:' . date('Ymd\THis', $startTs),
        'DTEND:' . date('Ymd\THis', $endTs),
        'SUMMARY:' . iv_ics_escape($summary),
        'DESCRIPTION:' . iv_ics_escape($desc),
        'LOCATION:' . iv_ics_escape((string)$iv['location']),
        'STATUS:' . ($iv['status'] === 'cancelled' ? 'CANCELLED' : 'CONFIRMED'),
        'END:VEVENT',
        'END:VCALENDAR',
    ];

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="interview-' . $icsId . '.ics"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    echo implode("\r\n", $lines) . "\r\n";
    exit;
}

/* ==================================================================
 | POST handling (post → redirect → get)
 * ==================================================================*/

if ($schemaReady && $_SERVER['REQUEST_METHOD'] === 'POST') {

    rc_verify_csrf();

    $redirect = url('recruiter/interviews.php');
    $action   = trim((string)($_POST['action'] ?? ''));

    try {

        /* -------------------- Schedule a new interview -------------------- */
        if ($action === 'schedule') {

            $applicationId = (int)($_POST['application_id'] ?? 0);
            $start         = iv_parse_datetime((string)($_POST['scheduled_at'] ?? ''));
            $duration      = (int)($_POST['duration_minutes'] ?? 0);
            $mode          = (string)($_POST['mode'] ?? '');
            $location      = trim((string)($_POST['location'] ?? ''));
            $round         = trim((string)($_POST['round_label'] ?? ''));
            if ($round === '') $round = 'Interview';

            $errors = [];

            $closedMarks = implode(',', array_fill(0, count(IV_CLOSED_APP_STATUSES), '?'));
            $stmt = $conn->prepare(
                "SELECT id, candidate_id, opportunity_id
                 FROM applications
                 WHERE id = ? AND status NOT IN ($closedMarks) LIMIT 1"
            );
            $closedList = IV_CLOSED_APP_STATUSES;
            $stmt->bind_param('i' . str_repeat('s', count($closedList)), $applicationId, ...$closedList);
            $stmt->execute();
            $app = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$app)                                   $errors[] = 'Please choose an active, submitted application.';
            if ($start === null)                         $errors[] = 'Please enter a valid date and time.';
            elseif ($start <= $now)                      $errors[] = 'The interview must be scheduled in the future.';
            if ($duration < 15 || $duration > 480)       $errors[] = 'Duration must be between 15 and 480 minutes.';
            if (!isset(IV_MODES[$mode]))                 $errors[] = 'Please choose an interview type.';
            if (mb_strlen($location) > 255)              $errors[] = 'Location / link is too long (255 characters max).';
            if (mb_strlen($round) > 60)                  $errors[] = 'Round label is too long (60 characters max).';
            if ($mode !== 'phone' && $location === '')   $errors[] = 'Please add a meeting link or venue.';

            [$interviewers, $ivErr] = iv_build_interviewers(
                (array)($_POST['staff_ids'] ?? []),
                (string)($_POST['extra_interviewers'] ?? ''),
                $staff
            );
            if ($ivErr)                                  $errors[] = $ivErr;
            elseif (!$interviewers)                      $errors[] = 'Assign at least one interviewer.';

            if (!$errors) {
                $errors = iv_conflicts($conn, (string)$start, $duration, (int)$app['candidate_id'], $interviewers);
            }

            if ($errors) {
                rc_flash('danger', implode(' ', $errors));
            } else {
                $conn->begin_transaction();
                try {
                    $candidateId   = (int)$app['candidate_id'];
                    $opportunityId = $app['opportunity_id'] !== null ? (int)$app['opportunity_id'] : null;

                    $stmt = $conn->prepare(
                        'INSERT INTO recruiter_interviews
                            (recruiter_id, application_id, candidate_id, opportunity_id,
                             round_label, scheduled_at, duration_minutes, mode, location)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->bind_param(
                        'iiiississ',
                        $recruiterId, $applicationId, $candidateId, $opportunityId,
                        $round, $start, $duration, $mode, $location
                    );
                    $stmt->execute();
                    $newId = (int)$conn->insert_id;
                    $stmt->close();

                    iv_save_interviewers($conn, $newId, $interviewers);
                    $conn->commit();
                } catch (Throwable $t) {
                    $conn->rollback();
                    throw $t;
                }

                // Keep the Follow-Up Pipeline stage in step (best effort).
                try {
                    iv_sync_application_status(
                        $conn, $applicationId, 'interview_scheduled',
                        ['submitted', 'under_review', 'shortlisted', 'assessment', 'interview_required'],
                        $recruiterId, 'Interview scheduled'
                    );
                } catch (Throwable $t) {
                    error_log('interviews.php status sync: ' . $t->getMessage());
                }

                iv_notify_candidate(
                    $conn, (int)$app['candidate_id'], 'Interview scheduled',
                    'Your ' . strtolower($round) . ' is scheduled for ' . iv_fmt($start, 'D, d M Y \a\t H:i')
                        . ' (' . (IV_MODES[$mode] ?? $mode) . ($location !== '' ? ', ' . $location : '') . ').',
                    $newId, 'scheduled'
                );

                rc_activity(
                    $conn, $recruiterId, 'interview_scheduled',
                    'Scheduled ' . $round . ' for application #' . $applicationId . ' on ' . $start,
                    'recruiter_interview', $newId
                );
                rc_flash('success', 'Interview scheduled for ' . iv_fmt($start) . '.');
            }
        }

        /* -------------------- Reschedule -------------------- */
        elseif ($action === 'reschedule') {

            $id  = (int)($_POST['interview_id'] ?? 0);
            $iv  = iv_fetch_owned($conn, $id, $recruiterId);

            $start    = iv_parse_datetime((string)($_POST['scheduled_at'] ?? ''));
            $duration = (int)($_POST['duration_minutes'] ?? 0);
            $location = trim((string)($_POST['location'] ?? ''));

            $errors = [];
            if (!$iv)                                    $errors[] = 'Interview not found.';
            elseif ($iv['status'] !== 'scheduled')       $errors[] = 'Only scheduled interviews can be rescheduled.';
            if ($start === null)                         $errors[] = 'Please enter a valid date and time.';
            elseif ($start <= $now)                      $errors[] = 'The new time must be in the future.';
            if ($duration < 15 || $duration > 480)       $errors[] = 'Duration must be between 15 and 480 minutes.';
            if (mb_strlen($location) > 255)              $errors[] = 'Location / link is too long (255 characters max).';
            if ($iv && $iv['mode'] !== 'phone' && $location === '') {
                $errors[] = 'Please add a meeting link or venue.';
            }

            if (!$errors && $iv) {
                $errors = iv_conflicts(
                    $conn, (string)$start, $duration, (int)$iv['candidate_id'],
                    iv_fetch_interviewers($conn, $id), $id
                );
            }

            if ($errors) {
                rc_flash('danger', implode(' ', $errors));
            } else {
                $stmt = $conn->prepare(
                    "UPDATE recruiter_interviews
                     SET scheduled_at = ?, duration_minutes = ?, location = ?,
                         reschedule_count = reschedule_count + 1
                     WHERE id = ? AND recruiter_id = ? AND status = 'scheduled'"
                );
                $stmt->bind_param('sisii', $start, $duration, $location, $id, $recruiterId);
                $stmt->execute();
                $stmt->close();

                $why = trim((string)($_POST['reason'] ?? ''));
                iv_notify_candidate(
                    $conn, (int)$iv['candidate_id'], 'Interview rescheduled',
                    'Your interview has been moved to ' . iv_fmt($start, 'D, d M Y \a\t H:i')
                        . ($location !== '' ? ' (' . $location . ')' : '') . '.',
                    $id, 'rescheduled'
                );
                rc_activity(
                    $conn, $recruiterId, 'interview_rescheduled',
                    'Rescheduled interview #' . $id . ' to ' . $start . ($why !== '' ? ' — ' . mb_substr($why, 0, 200) : ''),
                    'recruiter_interview', $id
                );
                rc_flash('success', 'Interview rescheduled to ' . iv_fmt($start) . '.');
            }
        }

        /* -------------------- Manage interviewers -------------------- */
        elseif ($action === 'interviewers') {

            $id = (int)($_POST['interview_id'] ?? 0);
            $iv = iv_fetch_owned($conn, $id, $recruiterId);

            [$interviewers, $ivErr] = iv_build_interviewers(
                (array)($_POST['staff_ids'] ?? []),
                (string)($_POST['extra_interviewers'] ?? ''),
                $staff
            );

            $errors = [];
            if (!$iv)                                    $errors[] = 'Interview not found.';
            elseif ($iv['status'] !== 'scheduled')       $errors[] = 'Interviewers can only be changed on scheduled interviews.';
            if ($ivErr)                                  $errors[] = $ivErr;
            elseif (!$interviewers)                      $errors[] = 'Assign at least one interviewer.';

            if (!$errors && $iv) {
                $errors = iv_conflicts(
                    $conn, (string)$iv['scheduled_at'], (int)$iv['duration_minutes'],
                    (int)$iv['candidate_id'], $interviewers, $id
                );
            }

            if ($errors) {
                rc_flash('danger', implode(' ', $errors));
            } else {
                $conn->begin_transaction();
                try {
                    iv_save_interviewers($conn, $id, $interviewers);
                    $conn->commit();
                } catch (Throwable $t) {
                    $conn->rollback();
                    throw $t;
                }
                rc_activity(
                    $conn, $recruiterId, 'interview_interviewers_updated',
                    'Updated interviewers on interview #' . $id . ' (' . count($interviewers) . ' assigned)',
                    'recruiter_interview', $id
                );
                rc_flash('success', 'Interviewers updated.');
            }
        }

        /* -------------------- Cancel -------------------- */
        elseif ($action === 'cancel') {

            $id     = (int)($_POST['interview_id'] ?? 0);
            $iv     = iv_fetch_owned($conn, $id, $recruiterId);
            $reason = trim((string)($_POST['reason'] ?? ''));

            if (!$iv) {
                rc_flash('danger', 'Interview not found.');
            } elseif ($iv['status'] !== 'scheduled') {
                rc_flash('danger', 'Only scheduled interviews can be cancelled.');
            } elseif ($reason === '') {
                rc_flash('danger', 'Please give a reason for cancelling.');
            } elseif (mb_strlen($reason) > 255) {
                rc_flash('danger', 'The cancellation reason is too long (255 characters max).');
            } else {
                $stmt = $conn->prepare(
                    "UPDATE recruiter_interviews
                     SET status = 'cancelled', cancel_reason = ?
                     WHERE id = ? AND recruiter_id = ? AND status = 'scheduled'"
                );
                $stmt->bind_param('sii', $reason, $id, $recruiterId);
                $stmt->execute();
                $stmt->close();

                iv_notify_candidate(
                    $conn, (int)$iv['candidate_id'], 'Interview cancelled',
                    'Your interview on ' . iv_fmt((string)$iv['scheduled_at'], 'D, d M Y \a\t H:i')
                        . ' has been cancelled. You will be contacted if it is rescheduled.',
                    $id, 'cancelled'
                );

                rc_activity(
                    $conn, $recruiterId, 'interview_cancelled',
                    'Cancelled interview #' . $id . ' — ' . $reason,
                    'recruiter_interview', $id
                );
                rc_flash('success', 'Interview cancelled.');
            }
        }

        /* -------------------- Record outcome -------------------- */
        elseif ($action === 'outcome') {

            $id        = (int)($_POST['interview_id'] ?? 0);
            $iv        = iv_fetch_owned($conn, $id, $recruiterId);
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
                if (!isset(IV_OUTCOMES[$outcome]))       $errors[] = 'Please choose an outcome.';
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

                // Once a held interview is recorded and nothing else is booked,
                // the application moves to "awaiting interview feedback" (best effort).
                if ($result === 'completed' && $iv) {
                    try {
                        $stmt = $conn->prepare(
                            "SELECT COUNT(*) FROM recruiter_interviews
                             WHERE application_id = ? AND status = 'scheduled'"
                        );
                        $appId = (int)$iv['application_id'];
                        $stmt->bind_param('i', $appId);
                        $stmt->execute();
                        $remaining = (int)($stmt->get_result()->fetch_row()[0] ?? 0);
                        $stmt->close();

                        if ($remaining === 0) {
                            iv_sync_application_status($conn, $appId, 'interview_completed', ['interview_scheduled'], $recruiterId, 'Interview completed');
                        }
                    } catch (Throwable $t) {
                        error_log('interviews.php status sync: ' . $t->getMessage());
                    }
                }

                rc_activity(
                    $conn, $recruiterId, 'interview_outcome_recorded',
                    'Recorded ' . ($result === 'no_show' ? 'no-show' : 'outcome "' . $outcome . '"') . ' for interview #' . $id,
                    'recruiter_interview', $id
                );
                rc_flash('success', 'Interview outcome saved.');
            }
        }

        /* -------------------- Record selection decision -------------------- */
        elseif ($action === 'decision') {

            $id   = (int)($_POST['interview_id'] ?? 0);
            $iv   = iv_fetch_owned($conn, $id, $recruiterId);
            $dec  = (string)($_POST['decision'] ?? '');
            $note = mb_substr(trim((string)($_POST['decision_note'] ?? '')), 0, 2000);
            $app  = null;

            $errors = [];
            if (!rc_has_table($conn, 'selection_decisions')) {
                $errors[] = 'Decisions are not available on this system.';
            } elseif (!$iv) {
                $errors[] = 'Interview not found.';
            } elseif ($iv['status'] !== 'completed' || $iv['outcome'] === 'pending') {
                $errors[] = 'Record the interview outcome before making a decision.';
            }
            if (!isset(IV_DECISIONS[$dec])) $errors[] = 'Please choose a decision.';

            if (!$errors && $iv) {
                $stmt = $conn->prepare('SELECT id, status, candidate_id, opportunity_id FROM applications WHERE id = ? LIMIT 1');
                $appId = (int)$iv['application_id'];
                $stmt->bind_param('i', $appId);
                $stmt->execute();
                $app = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$app) {
                    $errors[] = 'Application not found.';
                } elseif (!in_array((string)$app['status'], IV_DECISION_FROM, true)) {
                    $errors[] = 'A decision can no longer be changed for an application at this stage.';
                }
            }

            if ($errors) {
                rc_flash('danger', implode(' ', $errors));
            } else {
                $stmt = $conn->prepare('SELECT decision FROM selection_decisions WHERE application_id = ? LIMIT 1');
                $stmt->bind_param('i', $appId);
                $stmt->execute();
                $prevDecision = (string)($stmt->get_result()->fetch_row()[0] ?? '');
                $stmt->close();

                $candidateId   = (int)$app['candidate_id'];
                $opportunityId = (int)$app['opportunity_id'];

                $conn->begin_transaction();
                try {
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

                    iv_sync_application_status(
                        $conn, $appId, IV_DECISION_APP[$dec], [(string)$app['status']],
                        $recruiterId, 'Selection decision recorded: ' . IV_DECISIONS[$dec]
                    );
                    $conn->commit();
                } catch (Throwable $t) {
                    $conn->rollback();
                    throw $t;
                }

                if ($prevDecision !== $dec) {
                    $decisionMessages = [
                        'selected'     => 'Congratulations, you have been selected for this opportunity. Further steps will be communicated to you.',
                        'waitlisted'   => 'You have been placed on the waitlist for this opportunity. We will contact you if a place becomes available.',
                        'not_selected' => 'Thank you for taking part in the interview. Unfortunately, you were not selected for this opportunity.',
                    ];
                    iv_notify_candidate($conn, $candidateId, 'Application decision', $decisionMessages[$dec], $id, 'decision_' . $dec);
                }

                rc_activity(
                    $conn, $recruiterId, 'selection_decision',
                    'Recorded decision "' . $dec . '" for application #' . $appId,
                    'recruiter_interview', $id
                );
                rc_flash('success', 'Decision recorded.');
            }
        }

        else {
            rc_flash('danger', 'Unknown action.');
        }

    } catch (Throwable $t) {
        error_log('interviews.php: ' . $t->getMessage());
        rc_flash('danger', 'Something went wrong while saving. Please try again.');
    }

    header('Location: ' . $redirect);
    exit;
}

/* ==================================================================
 | Data for display
 * ==================================================================*/

$tab = (string)($_GET['tab'] ?? 'all');
if (!in_array($tab, ['all', 'upcoming', 'awaiting', 'completed', 'cancelled'], true)) $tab = 'all';
$q = trim((string)($_GET['q'] ?? ''));

$prefillApplication = (int)($_GET['schedule'] ?? 0);

$stats          = ['upcoming' => 0, 'today' => 0, 'awaiting' => 0, 'completed' => 0, 'cancelled' => 0];
$interviews     = [];
$interviewerMap = [];
$consentSet     = [];
$applications   = [];
$progress       = [];

if ($schemaReady) {

    /* ---- Stats ---- */
    $stmt = $conn->prepare(
        "SELECT
            COALESCE(SUM(status = 'scheduled' AND scheduled_at > ?), 0) AS upcoming,
            COALESCE(SUM(status = 'scheduled' AND scheduled_at >= ? AND scheduled_at < ?), 0) AS today,
            COALESCE(SUM(status = 'scheduled' AND scheduled_at <= ?), 0) AS awaiting,
            COALESCE(SUM(status IN ('completed','no_show')), 0) AS completed,
            COALESCE(SUM(status = 'cancelled'), 0) AS cancelled
         FROM recruiter_interviews WHERE recruiter_id = ?"
    );
    $stmt->bind_param('ssssi', $now, $todayFrom, $todayTo, $now, $recruiterId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) foreach (array_keys($stats) as $k) $stats[$k] = (int)$row[$k];

    /* ---- Interview list ---- */
    $where  = ['ri.recruiter_id = ?'];
    $types  = 'i';
    $params = [$recruiterId];

    switch ($tab) {
        case 'upcoming':
            $where[] = "ri.status = 'scheduled' AND ri.scheduled_at > ?";
            $types  .= 's'; $params[] = $now;
            break;
        case 'awaiting':
            $where[] = "ri.status = 'scheduled' AND ri.scheduled_at <= ?";
            $types  .= 's'; $params[] = $now;
            break;
        case 'completed':
            $where[] = "ri.status IN ('completed','no_show')";
            break;
        case 'cancelled':
            $where[] = "ri.status = 'cancelled'";
            break;
    }

    if ($q !== '') {
        $where[] = "(CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ?
                     OR o.title LIKE ? OR a.application_reference LIKE ?)";
        $like    = '%' . $q . '%';
        $types  .= 'ssss';
        array_push($params, $like, $like, $like, $like);
    }

    $order = in_array($tab, ['upcoming', 'awaiting'], true) ? 'ASC' : 'DESC';

    $hasDecisions = rc_has_table($conn, 'selection_decisions');

    $stmt = $conn->prepare(
        "SELECT ri.*, u.first_name, u.last_name, u.email AS candidate_email,
                o.title AS opportunity_title,
                a.application_reference, a.status AS application_status,
                " . ($hasDecisions ? 'sd.decision' : 'NULL') . " AS decision
         FROM recruiter_interviews ri
         LEFT JOIN users u ON u.id = ri.candidate_id
         LEFT JOIN opportunities o ON o.id = ri.opportunity_id
         LEFT JOIN applications a ON a.id = ri.application_id
         " . ($hasDecisions ? 'LEFT JOIN selection_decisions sd ON sd.application_id = ri.application_id' : '') . "
         WHERE " . implode(' AND ', $where) . "
         ORDER BY ri.scheduled_at $order, ri.id DESC
         LIMIT 300"
    );
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $interviews[] = $r;
        $stmt->close();
    }

    if ($interviews) {

        $ids = implode(',', array_map(fn($r) => (int)$r['id'], $interviews));
        $res = $conn->query(
            "SELECT interview_id, user_id, interviewer_name, interviewer_email
             FROM recruiter_interview_interviewers
             WHERE interview_id IN ($ids) ORDER BY id ASC"
        );
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $interviewerMap[(int)$r['interview_id']][] = $r;
            }
            $res->free();
        }

        // Candidate profiles are only openable with valid recruiter-discovery consent.
        if (rc_has_table($conn, 'candidate_consents')) {
            $candIds = implode(',', array_unique(array_map(fn($r) => (int)$r['candidate_id'], $interviews)));
            $stmt = $conn->prepare(
                "SELECT DISTINCT candidate_id FROM candidate_consents
                 WHERE candidate_id IN ($candIds)
                   AND consent_type = 'recruiter_discovery'
                   AND status = 'consented'
                   AND (expires_at IS NULL OR expires_at > ?)"
            );
            if ($stmt) {
                $stmt->bind_param('s', $now);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($r = $res->fetch_assoc()) $consentSet[(int)$r['candidate_id']] = true;
                $stmt->close();
            }
        }
    }

    /* ---- Active submitted applications to pick from ---- */
    $closedMarks = implode(',', array_fill(0, count(IV_CLOSED_APP_STATUSES), '?'));
    $stmt = $conn->prepare(
        "SELECT a.id, a.application_reference, a.status, u.first_name, u.last_name,
                o.title AS opportunity_title
         FROM applications a
         LEFT JOIN users u ON u.id = a.candidate_id
         LEFT JOIN opportunities o ON o.id = a.opportunity_id
         WHERE a.status NOT IN ($closedMarks)
         ORDER BY COALESCE(a.submitted_at, a.created_at) DESC, a.id DESC
         LIMIT 500"
    );
    if ($stmt) {
        $closedList = IV_CLOSED_APP_STATUSES;
        $stmt->bind_param(str_repeat('s', count($closedList)), ...$closedList);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $applications[] = $r;
        $stmt->close();
    }

    /* ---- Candidate progress ---- */
    $stmt = $conn->prepare(
        "SELECT ri.application_id, ri.candidate_id,
                u.first_name, u.last_name, o.title AS opportunity_title,
                a.application_reference, a.status AS application_status,
                COUNT(*) AS total,
                SUM(ri.status IN ('completed','no_show')) AS done,
                SUM(ri.status = 'scheduled') AS pending,
                MAX(ri.scheduled_at) AS last_at,
                (SELECT x.outcome FROM recruiter_interviews x
                  WHERE x.application_id = ri.application_id
                    AND x.recruiter_id = ri.recruiter_id
                    AND x.status = 'completed'
                  ORDER BY x.scheduled_at DESC, x.id DESC LIMIT 1) AS latest_outcome
         FROM recruiter_interviews ri
         LEFT JOIN users u ON u.id = ri.candidate_id
         LEFT JOIN applications a ON a.id = ri.application_id
         LEFT JOIN opportunities o ON o.id = a.opportunity_id
         WHERE ri.recruiter_id = ?
         GROUP BY ri.application_id, ri.candidate_id, ri.recruiter_id,
                  u.first_name, u.last_name, o.title,
                  a.application_reference, a.status
         ORDER BY last_at DESC
         LIMIT 100"
    );
    if ($stmt) {
        $stmt->bind_param('i', $recruiterId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $progress[] = $r;
        $stmt->close();
    }
}

$csrf     = rc_csrf();
$nowLocal = date('Y-m-d\TH:i');
$action   = url('recruiter/interviews.php');

$statusLabels = [
    'scheduled' => 'Scheduled',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
    'no_show'   => 'No-show',
];
$outcomeLabels = [
    'pending'   => 'Pending',
    'recommend' => 'Recommended',
    'offer'     => 'Offer',
    'hold'      => 'On hold',
    'reject'    => 'Rejected',
];

require __DIR__ . '/_layout_start.php';
?>

<style>
.iv-toolbar{display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;margin-bottom:16px}
.iv-tabs{display:flex;flex-wrap:wrap;gap:6px}
.iv-tab{padding:8px 13px;border-radius:999px;text-decoration:none;font-size:13px;font-weight:600;color:inherit;background:rgba(127,127,127,.10)}
.iv-tab.is-active{background:#2454d8;color:#fff}
.iv-search{display:flex;gap:8px}
.iv-search input{min-width:220px}

.iv-field{display:flex;flex-direction:column;gap:6px;margin-bottom:14px}
.iv-field label{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;opacity:.75}
.iv-input,.iv-select,.iv-textarea{width:100%;box-sizing:border-box;padding:10px 12px;border-radius:10px;border:1px solid rgba(127,127,127,.35);background:transparent;color:inherit;font:inherit}
.iv-textarea{min-height:84px;resize:vertical}
.iv-select[multiple]{min-height:120px}
.iv-input:focus,.iv-select:focus,.iv-textarea:focus{outline:2px solid #2454d8;outline-offset:1px}
.iv-hint{font-size:12px;opacity:.65}
.iv-row2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media(max-width:600px){.iv-row2{grid-template-columns:1fr}}

.iv-cell small{display:block;opacity:.65;margin-top:3px}
.iv-people{display:flex;flex-wrap:wrap;gap:5px}
.iv-actions{display:flex;flex-wrap:wrap;gap:6px}
.iv-stars{letter-spacing:2px;color:#e6a218}

.iv-progress{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.iv-step{padding:4px 9px;border-radius:999px;font-size:11px;font-weight:700;background:rgba(127,127,127,.12);opacity:.6}
.iv-step.is-on{opacity:1;background:rgba(36,84,216,.16);color:#2454d8}
.iv-step.is-good{opacity:1;background:rgba(20,184,123,.16);color:#0b6b48}
.iv-step.is-bad{opacity:1;background:rgba(210,75,75,.16);color:#a02727}
[data-theme="dark"] .iv-step.is-on{color:#93b4ff}
[data-theme="dark"] .iv-step.is-good{color:#6ee7b7}
[data-theme="dark"] .iv-step.is-bad{color:#fca5a5}

.iv-modal{position:fixed;inset:0;z-index:10000;display:none;align-items:center;justify-content:center;padding:24px}
.iv-modal.is-open{display:flex}
.iv-modal__backdrop{position:absolute;inset:0;background:rgba(15,23,42,.70);backdrop-filter:blur(5px)}
.iv-modal__dialog{position:relative;z-index:1;width:min(620px,100%);max-height:90vh;display:flex;flex-direction:column;overflow:hidden;border-radius:22px;background:#fff;color:#172b4d;box-shadow:0 25px 80px rgba(0,0,0,.28)}
.iv-modal__header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:22px 24px 16px;border-bottom:1px solid rgba(127,127,127,.16)}
.iv-modal__header h3{margin:4px 0 2px}
.iv-modal__header p{margin:0;opacity:.7}
.iv-modal__close{width:40px;height:40px;flex:0 0 40px;display:grid;place-items:center;border:0;border-radius:50%;cursor:pointer;background:rgba(15,23,42,.07);color:inherit}
.iv-modal__body{padding:18px 24px;overflow-y:auto}
.iv-modal__footer{display:flex;justify-content:flex-end;gap:10px;padding:14px 24px;border-top:1px solid rgba(127,127,127,.16)}
body.iv-modal-open{overflow:hidden}
html[data-theme="dark"] .iv-modal__dialog,body.dark-mode .iv-modal__dialog{background:#111827;color:#f8fafc}
html[data-theme="dark"] .iv-modal__close,body.dark-mode .iv-modal__close{background:rgba(255,255,255,.08);color:#f8fafc}
html[data-theme="dark"] .iv-select option,body.dark-mode .iv-select option{background:#111827;color:#f8fafc}
@media(max-width:768px){.iv-modal{padding:12px}.iv-modal__dialog{border-radius:16px}}
</style>

<!-- ================================================================
     HERO
================================================================ -->

<section class="rc-hero">
    <div>
        <span class="rc-eyebrow"><i class="fas fa-calendar-check"></i> Interview management</span>
        <h2>Interview Scheduling &amp; Tracking</h2>
        <p>
            Schedule interviews, assign interviewers, manage appointments,
            record outcomes and follow each candidate's progress through
            the recruitment process.
        </p>
        <div class="rc-hero__actions">
            <?php if ($schemaReady): ?>
                <button type="button" class="rc-btn rc-btn--light" data-iv-open="ivScheduleModal">
                    <i class="fas fa-plus"></i> Schedule interview
                </button>
            <?php endif; ?>
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
            <strong>Interview tables could not be created</strong>
            <span>
                Run <code>database/interviews.sql</code> on your database, then reload this page.
                <?= $schemaError ? '(' . e($schemaError) . ')' : '' ?>
            </span>
        </div>
    </div></div>

<?php else: ?>

<!-- ================================================================
     STATS
================================================================ -->

<section class="rc-stats">
    <article><i class="fas fa-calendar-day"></i><strong><?= number_format($stats['today']) ?></strong><span>Today</span></article>
    <article><i class="fas fa-calendar-plus"></i><strong><?= number_format($stats['upcoming']) ?></strong><span>Upcoming</span></article>
    <article><i class="fas fa-hourglass-half"></i><strong><?= number_format($stats['awaiting']) ?></strong><span>Awaiting outcome</span></article>
    <article><i class="fas fa-circle-check"></i><strong><?= number_format($stats['completed']) ?></strong><span>Held</span></article>
    <article><i class="fas fa-ban"></i><strong><?= number_format($stats['cancelled']) ?></strong><span>Cancelled</span></article>
</section>

<!-- ================================================================
     APPOINTMENTS
================================================================ -->

<section class="rc-card rc-space" id="appointments">

    <div class="rc-card__header">
        <div>
            <span class="rc-eyebrow">Appointments</span>
            <h3>Your interviews</h3>
            <p>Reschedule, change interviewers, cancel or record the outcome.</p>
        </div>
    </div>

    <div class="rc-card__body">

        <div class="iv-toolbar">
            <nav class="iv-tabs" aria-label="Filter interviews">
                <?php
                $tabDefs = [
                    'all' => 'All', 'upcoming' => 'Upcoming', 'awaiting' => 'Awaiting outcome',
                    'completed' => 'Held', 'cancelled' => 'Cancelled',
                ];
                foreach ($tabDefs as $key => $label):
                    $href = url('recruiter/interviews.php?tab=' . $key . ($q !== '' ? '&q=' . urlencode($q) : ''));
                ?>
                    <a class="iv-tab <?= $tab === $key ? 'is-active' : '' ?>" href="<?= $href ?>"><?= e($label) ?></a>
                <?php endforeach; ?>
            </nav>

            <form class="iv-search" method="get" action="<?= $action ?>">
                <input type="hidden" name="tab" value="<?= e($tab) ?>">
                <input class="iv-input" type="search" name="q" value="<?= e($q) ?>"
                       placeholder="Name, email, role or reference" aria-label="Search interviews">
                <button class="rc-btn rc-btn--secondary rc-btn--small" type="submit">
                    <i class="fas fa-magnifying-glass"></i> Search
                </button>
            </form>
        </div>

        <?php if (!$interviews): ?>

            <div class="rc-empty">
                <i class="fas fa-calendar-xmark"></i>
                <strong>No interviews found</strong>
                <span>
                    <?= ($tab !== 'all' || $q !== '')
                        ? 'Nothing matches the current filter.'
                        : 'Schedule your first interview using the button above.' ?>
                </span>
            </div>

        <?php else: ?>

            <div class="rc-table-wrap">
                <table class="rc-table">
                    <thead>
                        <tr>
                            <th>Candidate</th>
                            <th>When</th>
                            <th>Type</th>
                            <th>Interviewers</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($interviews as $iv):
                        $id        = (int)$iv['id'];
                        $candName  = trim((string)$iv['first_name'] . ' ' . (string)$iv['last_name']);
                        $people    = $interviewerMap[$id] ?? [];
                        $isSched   = $iv['status'] === 'scheduled';
                        $started   = (string)$iv['scheduled_at'] <= $now;
                        $canRecord = ($isSched && $started) || in_array($iv['status'], ['completed', 'no_show'], true);

                        $staffIds = [];
                        $extra    = [];
                        foreach ($people as $p) {
                            if ($p['user_id'] !== null) {
                                $staffIds[] = (int)$p['user_id'];
                            } else {
                                $extra[] = $p['interviewer_name'] . ($p['interviewer_email'] !== '' ? ', ' . $p['interviewer_email'] : '');
                            }
                        }

                        $statusClass = $iv['status'] === 'completed' ? 'rc-status--success'
                                     : (in_array($iv['status'], ['cancelled', 'no_show'], true) ? 'rc-status--danger' : '');
                    ?>
                        <tr>
                            <td class="iv-cell">
                                <strong><?= e($candName !== '' ? $candName : 'Candidate #' . (int)$iv['candidate_id']) ?></strong>
                                <?php if ((string)$iv['candidate_email'] !== ''): ?>
                                    <small><?= e((string)$iv['candidate_email']) ?></small>
                                <?php endif; ?>
                                <small><?= e((string)($iv['opportunity_title'] ?: 'Opportunity not set')) ?></small>
                                <small><?= e((string)$iv['round_label']) ?>
                                    <?= $iv['application_reference'] ? ' · ' . e((string)$iv['application_reference']) : '' ?>
                                    <?= $iv['application_status'] ? ' · Application: ' . e(rc_label((string)$iv['application_status'])) : '' ?></small>
                            </td>

                            <td class="iv-cell">
                                <?= e(iv_fmt((string)$iv['scheduled_at'])) ?>
                                <small><?= (int)$iv['duration_minutes'] ?> min
                                    <?= (int)$iv['reschedule_count'] > 0 ? ' · rescheduled ' . (int)$iv['reschedule_count'] . '×' : '' ?></small>
                            </td>

                            <td class="iv-cell">
                                <?= e(IV_MODES[$iv['mode']] ?? (string)$iv['mode']) ?>
                                <?php if ((string)$iv['location'] !== ''): ?>
                                    <small><?= e((string)$iv['location']) ?></small>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="iv-people">
                                    <?php foreach ($people as $p): ?>
                                        <span class="rc-chip" title="<?= e((string)$p['interviewer_email']) ?>">
                                            <i class="fas fa-user-tie"></i> <?= e((string)$p['interviewer_name']) ?>
                                        </span>
                                    <?php endforeach; ?>
                                    <?php if (!$people): ?>—<?php endif; ?>
                                </div>
                            </td>

                            <td class="iv-cell">
                                <span class="rc-status <?= $statusClass ?>">
                                    <?= e($statusLabels[$iv['status']] ?? (string)$iv['status']) ?>
                                </span>
                                <?php if ($isSched && $started): ?>
                                    <small>Outcome needed</small>
                                <?php endif; ?>
                                <?php if ($iv['status'] === 'completed'): ?>
                                    <small><?= e($outcomeLabels[$iv['outcome']] ?? '') ?></small>
                                    <?php if ($iv['rating'] !== null): ?>
                                        <small class="iv-stars" aria-label="Rating <?= (int)$iv['rating'] ?> of 5">
                                            <?= str_repeat('★', (int)$iv['rating']) . str_repeat('☆', 5 - (int)$iv['rating']) ?>
                                        </small>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if (!empty($iv['decision'])): ?>
                                    <small>Decision: <?= e(IV_DECISIONS[$iv['decision']] ?? rc_label((string)$iv['decision'])) ?></small>
                                <?php endif; ?>
                                <?php if ($iv['status'] === 'cancelled' && (string)$iv['cancel_reason'] !== ''): ?>
                                    <small><?= e((string)$iv['cancel_reason']) ?></small>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="iv-actions">
                                    <?php if ($isSched): ?>
                                        <button type="button" class="rc-btn rc-btn--secondary rc-btn--small"
                                                data-iv-open="ivRescheduleModal"
                                                data-id="<?= $id ?>"
                                                data-when="<?= e(date('Y-m-d\TH:i', strtotime((string)$iv['scheduled_at']))) ?>"
                                                data-duration="<?= (int)$iv['duration_minutes'] ?>"
                                                data-location="<?= e((string)$iv['location']) ?>"
                                                data-name="<?= e($candName) ?>">
                                            <i class="fas fa-calendar-pen"></i> Reschedule
                                        </button>
                                        <button type="button" class="rc-btn rc-btn--secondary rc-btn--small"
                                                data-iv-open="ivPeopleModal"
                                                data-id="<?= $id ?>"
                                                data-staff="<?= e(implode(',', $staffIds)) ?>"
                                                data-extra="<?= e(implode("\n", $extra)) ?>"
                                                data-name="<?= e($candName) ?>">
                                            <i class="fas fa-user-tie"></i> Interviewers
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($canRecord): ?>
                                        <button type="button" class="rc-btn rc-btn--primary rc-btn--small"
                                                data-iv-open="ivOutcomeModal"
                                                data-id="<?= $id ?>"
                                                data-result="<?= e($iv['status'] === 'no_show' ? 'no_show' : 'completed') ?>"
                                                data-outcome="<?= e($iv['outcome'] !== 'pending' ? (string)$iv['outcome'] : '') ?>"
                                                data-rating="<?= $iv['rating'] !== null ? (int)$iv['rating'] : '' ?>"
                                                data-notes="<?= e((string)$iv['outcome_notes']) ?>"
                                                data-name="<?= e($candName) ?>">
                                            <i class="fas fa-clipboard-check"></i>
                                            <?= $isSched ? 'Record outcome' : 'Edit outcome' ?>
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($hasDecisions && $iv['status'] === 'completed' && $iv['outcome'] !== 'pending'
                                              && in_array((string)$iv['application_status'], IV_DECISION_FROM, true)): ?>
                                        <button type="button" class="rc-btn rc-btn--secondary rc-btn--small"
                                                data-iv-open="ivDecisionModal"
                                                data-id="<?= $id ?>"
                                                data-decision="<?= e((string)($iv['decision'] ?? '')) ?>"
                                                data-name="<?= e($candName) ?>">
                                            <i class="fas fa-gavel"></i>
                                            <?= !empty($iv['decision']) ? 'Change decision' : 'Record decision' ?>
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($isSched): ?>
                                        <button type="button" class="rc-btn rc-btn--secondary rc-btn--small"
                                                data-iv-open="ivCancelModal"
                                                data-id="<?= $id ?>"
                                                data-name="<?= e($candName) ?>">
                                            <i class="fas fa-ban"></i> Cancel
                                        </button>
                                        <a class="rc-btn rc-btn--secondary rc-btn--small"
                                           href="<?= url('recruiter/interviews.php?ics=' . $id) ?>"
                                           title="Download calendar file">
                                            <i class="fas fa-calendar-plus"></i> Calendar
                                        </a>
                                    <?php endif; ?>

                                    <?php if (isset($consentSet[(int)$iv['candidate_id']])): ?>
                                        <a class="rc-btn rc-btn--secondary rc-btn--small"
                                           href="<?= url('recruiter/candidate_view.php?id=' . (int)$iv['candidate_id']) ?>">
                                            <i class="fas fa-user"></i> Profile
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>

                        <?php if ($iv['status'] !== 'scheduled' && (string)$iv['outcome_notes'] !== ''): ?>
                        <tr>
                            <td colspan="6" class="iv-cell">
                                <small><strong>Feedback:</strong> <?= nl2br(e((string)$iv['outcome_notes'])) ?></small>
                            </td>
                        </tr>
                        <?php endif; ?>

                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>
    </div>
</section>

<!-- ================================================================
     CANDIDATE PROGRESS
================================================================ -->

<section class="rc-card rc-space" id="progress">

    <div class="rc-card__header">
        <div>
            <span class="rc-eyebrow">Candidate progress</span>
            <h3>Where each candidate stands</h3>
            <p>Based on the interviews you scheduled and the outcomes you recorded.</p>
        </div>
    </div>

    <div class="rc-card__body">
        <?php if (!$progress): ?>
            <div class="rc-empty">
                <i class="fas fa-route"></i>
                <strong>No candidates in the interview process yet</strong>
                <span>Candidates appear here once you schedule an interview for their application.</span>
            </div>
        <?php else: ?>
            <div class="rc-table-wrap">
                <table class="rc-table">
                    <thead>
                        <tr>
                            <th>Candidate</th>
                            <th>Interviews</th>
                            <th>Progress</th>
                            <th>Latest activity</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($progress as $p):
                        $name    = trim((string)$p['first_name'] . ' ' . (string)$p['last_name']);
                        $total   = (int)$p['total'];
                        $done    = (int)$p['done'];
                        $pending = (int)$p['pending'];
                        $latest  = (string)($p['latest_outcome'] ?? '');

                        $stage1 = 'is-on';
                        $stage2 = $done > 0 ? 'is-on' : '';
                        $stage3 = '';
                        $label3 = 'Decision';
                        if ($latest === 'offer')          { $stage3 = 'is-good'; $label3 = 'Offer'; }
                        elseif ($latest === 'recommend')  { $stage3 = 'is-good'; $label3 = 'Recommended'; }
                        elseif ($latest === 'hold')       { $stage3 = 'is-on';   $label3 = 'On hold'; }
                        elseif ($latest === 'reject')     { $stage3 = 'is-bad';  $label3 = 'Rejected'; }
                    ?>
                        <tr>
                            <td class="iv-cell">
                                <strong><?= e($name !== '' ? $name : 'Candidate #' . (int)$p['candidate_id']) ?></strong>
                                <small><?= e((string)($p['opportunity_title'] ?: 'Opportunity not set')) ?>
                                    <?= $p['application_reference'] ? ' · ' . e((string)$p['application_reference']) : '' ?></small>
                                <?php if ($p['application_status']): ?>
                                    <small>Application: <?= e(rc_label((string)$p['application_status'])) ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="iv-cell">
                                <?= number_format($total) ?> total
                                <small><?= number_format($done) ?> held · <?= number_format($pending) ?> scheduled</small>
                            </td>
                            <td>
                                <div class="iv-progress">
                                    <span class="iv-step <?= $stage1 ?>">Scheduled</span>
                                    <span class="iv-step <?= $stage2 ?>">Interviewed</span>
                                    <span class="iv-step <?= $stage3 ?>"><?= e($label3) ?></span>
                                </div>
                            </td>
                            <td class="iv-cell"><?= e(iv_fmt((string)$p['last_at'], 'd M Y · H:i')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php
$staffOptions = static function (array $staff): string {
    $html = '';
    foreach ($staff as $s) {
        $html .= '<option value="' . (int)$s['id'] . '">' . e($s['name']) . '</option>';
    }
    return $html;
};
?>

<!-- ================================================================
     MODAL: SCHEDULE
================================================================ -->

<div class="iv-modal" id="ivScheduleModal" aria-hidden="true">
    <div class="iv-modal__backdrop" data-iv-close></div>
    <form class="iv-modal__dialog" method="post" action="<?= $action ?>"
          role="dialog" aria-modal="true" aria-labelledby="ivScheduleTitle">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="schedule">

        <div class="iv-modal__header">
            <div>
                <span class="rc-eyebrow">New appointment</span>
                <h3 id="ivScheduleTitle">Schedule interview</h3>
                <p>Pick an application, set the time and assign interviewers.</p>
            </div>
            <button type="button" class="iv-modal__close" data-iv-close aria-label="Close"><i class="fas fa-xmark"></i></button>
        </div>

        <div class="iv-modal__body">

            <div class="iv-field">
                <label for="ivApplication">Candidate application</label>
                <select class="iv-select" id="ivApplication" name="application_id" required>
                    <option value="">Select an application…</option>
                    <?php foreach ($applications as $a):
                        $an = trim((string)$a['first_name'] . ' ' . (string)$a['last_name']);
                    ?>
                        <option value="<?= (int)$a['id'] ?>" <?= $prefillApplication === (int)$a['id'] ? 'selected' : '' ?>>
                            <?= e(($an !== '' ? $an : 'Candidate') . ' — ' . ((string)$a['opportunity_title'] ?: 'Opportunity') . ' (' . ((string)$a['application_reference'] ?: '#' . (int)$a['id']) . ' · ' . rc_label((string)$a['status']) . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$applications): ?>
                    <span class="iv-hint">There are no active submitted applications to schedule yet.</span>
                <?php endif; ?>
            </div>

            <div class="iv-row2">
                <div class="iv-field">
                    <label for="ivRound">Round</label>
                    <input class="iv-input" id="ivRound" name="round_label" maxlength="60" value="Interview" placeholder="e.g. Technical interview">
                </div>
                <div class="iv-field">
                    <label for="ivMode">Type</label>
                    <select class="iv-select" id="ivMode" name="mode" required>
                        <?php foreach (IV_MODES as $k => $label): ?>
                            <option value="<?= e($k) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="iv-row2">
                <div class="iv-field">
                    <label for="ivWhen">Date &amp; time</label>
                    <input class="iv-input" type="datetime-local" id="ivWhen" name="scheduled_at" min="<?= e($nowLocal) ?>" required>
                </div>
                <div class="iv-field">
                    <label for="ivDuration">Duration (minutes)</label>
                    <input class="iv-input" type="number" id="ivDuration" name="duration_minutes" min="15" max="480" step="5" value="45" required>
                </div>
            </div>

            <div class="iv-field">
                <label for="ivLocation">Meeting link or venue</label>
                <input class="iv-input" id="ivLocation" name="location" maxlength="255" placeholder="Video link, address or phone number">
                <span class="iv-hint">Optional for phone interviews.</span>
            </div>

            <div class="iv-field">
                <label for="ivStaff">Interviewers (your team)</label>
                <select class="iv-select" id="ivStaff" name="staff_ids[]" multiple>
                    <?= $staffOptions($staff) ?>
                </select>
                <span class="iv-hint">Hold Ctrl (⌘ on Mac) to select more than one.</span>
            </div>

            <div class="iv-field">
                <label for="ivExtra">Other interviewers</label>
                <textarea class="iv-textarea" id="ivExtra" name="extra_interviewers" placeholder="One per line: Name, email@example.com"></textarea>
                <span class="iv-hint">For client or external panel members. At least one interviewer is required.</span>
            </div>
        </div>

        <div class="iv-modal__footer">
            <button type="button" class="rc-btn rc-btn--secondary" data-iv-close>Close</button>
            <button type="submit" class="rc-btn rc-btn--primary"><i class="fas fa-calendar-check"></i> Schedule</button>
        </div>
    </form>
</div>

<!-- ================================================================
     MODAL: RESCHEDULE
================================================================ -->

<div class="iv-modal" id="ivRescheduleModal" aria-hidden="true">
    <div class="iv-modal__backdrop" data-iv-close></div>
    <form class="iv-modal__dialog" method="post" action="<?= $action ?>"
          role="dialog" aria-modal="true" aria-labelledby="ivRescheduleTitle">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="reschedule">
        <input type="hidden" name="interview_id" data-fill="id">

        <div class="iv-modal__header">
            <div>
                <span class="rc-eyebrow">Manage appointment</span>
                <h3 id="ivRescheduleTitle">Reschedule interview</h3>
                <p data-fill-text="name"></p>
            </div>
            <button type="button" class="iv-modal__close" data-iv-close aria-label="Close"><i class="fas fa-xmark"></i></button>
        </div>

        <div class="iv-modal__body">
            <div class="iv-row2">
                <div class="iv-field">
                    <label for="ivReWhen">New date &amp; time</label>
                    <input class="iv-input" type="datetime-local" id="ivReWhen" name="scheduled_at" min="<?= e($nowLocal) ?>" data-fill="when" required>
                </div>
                <div class="iv-field">
                    <label for="ivReDuration">Duration (minutes)</label>
                    <input class="iv-input" type="number" id="ivReDuration" name="duration_minutes" min="15" max="480" step="5" data-fill="duration" required>
                </div>
            </div>
            <div class="iv-field">
                <label for="ivReLocation">Meeting link or venue</label>
                <input class="iv-input" id="ivReLocation" name="location" maxlength="255" data-fill="location">
            </div>
            <div class="iv-field">
                <label for="ivReReason">Reason (optional)</label>
                <input class="iv-input" id="ivReReason" name="reason" maxlength="200">
            </div>
        </div>

        <div class="iv-modal__footer">
            <button type="button" class="rc-btn rc-btn--secondary" data-iv-close>Close</button>
            <button type="submit" class="rc-btn rc-btn--primary">Save new time</button>
        </div>
    </form>
</div>

<!-- ================================================================
     MODAL: INTERVIEWERS
================================================================ -->

<div class="iv-modal" id="ivPeopleModal" aria-hidden="true">
    <div class="iv-modal__backdrop" data-iv-close></div>
    <form class="iv-modal__dialog" method="post" action="<?= $action ?>"
          role="dialog" aria-modal="true" aria-labelledby="ivPeopleTitle">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="interviewers">
        <input type="hidden" name="interview_id" data-fill="id">

        <div class="iv-modal__header">
            <div>
                <span class="rc-eyebrow">Manage appointment</span>
                <h3 id="ivPeopleTitle">Assign interviewers</h3>
                <p data-fill-text="name"></p>
            </div>
            <button type="button" class="iv-modal__close" data-iv-close aria-label="Close"><i class="fas fa-xmark"></i></button>
        </div>

        <div class="iv-modal__body">
            <div class="iv-field">
                <label for="ivPeopleStaff">Interviewers (your team)</label>
                <select class="iv-select" id="ivPeopleStaff" name="staff_ids[]" multiple>
                    <?= $staffOptions($staff) ?>
                </select>
                <span class="iv-hint">Hold Ctrl (⌘ on Mac) to select more than one.</span>
            </div>
            <div class="iv-field">
                <label for="ivPeopleExtra">Other interviewers</label>
                <textarea class="iv-textarea" id="ivPeopleExtra" name="extra_interviewers"
                          data-fill="extra" placeholder="One per line: Name, email@example.com"></textarea>
                <span class="iv-hint">This list replaces the current interviewers. At least one is required.</span>
            </div>
        </div>

        <div class="iv-modal__footer">
            <button type="button" class="rc-btn rc-btn--secondary" data-iv-close>Close</button>
            <button type="submit" class="rc-btn rc-btn--primary">Save interviewers</button>
        </div>
    </form>
</div>

<!-- ================================================================
     MODAL: OUTCOME
================================================================ -->

<div class="iv-modal" id="ivOutcomeModal" aria-hidden="true">
    <div class="iv-modal__backdrop" data-iv-close></div>
    <form class="iv-modal__dialog" method="post" action="<?= $action ?>"
          role="dialog" aria-modal="true" aria-labelledby="ivOutcomeTitle">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="outcome">
        <input type="hidden" name="interview_id" data-fill="id">

        <div class="iv-modal__header">
            <div>
                <span class="rc-eyebrow">Interview result</span>
                <h3 id="ivOutcomeTitle">Record outcome</h3>
                <p data-fill-text="name"></p>
            </div>
            <button type="button" class="iv-modal__close" data-iv-close aria-label="Close"><i class="fas fa-xmark"></i></button>
        </div>

        <div class="iv-modal__body">
            <div class="iv-field">
                <label for="ivResult">Did the interview take place?</label>
                <select class="iv-select" id="ivResult" name="result" data-fill="result" required>
                    <option value="completed">Yes — interview held</option>
                    <option value="no_show">No — candidate did not attend</option>
                </select>
            </div>

            <div id="ivOutcomeFields">
                <div class="iv-row2">
                    <div class="iv-field">
                        <label for="ivOutcome">Outcome</label>
                        <select class="iv-select" id="ivOutcome" name="outcome" data-fill="outcome">
                            <option value="">Select…</option>
                            <?php foreach (IV_OUTCOMES as $k => $label): ?>
                                <option value="<?= e($k) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="iv-field">
                        <label for="ivRating">Rating</label>
                        <select class="iv-select" id="ivRating" name="rating" data-fill="rating">
                            <option value="">No rating</option>
                            <option value="1">1 — Poor</option>
                            <option value="2">2 — Below expectations</option>
                            <option value="3">3 — Meets expectations</option>
                            <option value="4">4 — Strong</option>
                            <option value="5">5 — Outstanding</option>
                        </select>
                    </div>
                </div>
                <div class="iv-field">
                    <label for="ivNotes">Feedback notes</label>
                    <textarea class="iv-textarea" id="ivNotes" name="outcome_notes" maxlength="4000"
                              data-fill="notes" placeholder="Strengths, concerns, next steps…"></textarea>
                </div>
            </div>
        </div>

        <div class="iv-modal__footer">
            <button type="button" class="rc-btn rc-btn--secondary" data-iv-close>Close</button>
            <button type="submit" class="rc-btn rc-btn--primary">Save outcome</button>
        </div>
    </form>
</div>

<!-- ================================================================
     MODAL: CANCEL
================================================================ -->

<div class="iv-modal" id="ivCancelModal" aria-hidden="true">
    <div class="iv-modal__backdrop" data-iv-close></div>
    <form class="iv-modal__dialog" method="post" action="<?= $action ?>"
          role="dialog" aria-modal="true" aria-labelledby="ivCancelTitle">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="cancel">
        <input type="hidden" name="interview_id" data-fill="id">

        <div class="iv-modal__header">
            <div>
                <span class="rc-eyebrow">Manage appointment</span>
                <h3 id="ivCancelTitle">Cancel interview</h3>
                <p data-fill-text="name"></p>
            </div>
            <button type="button" class="iv-modal__close" data-iv-close aria-label="Close"><i class="fas fa-xmark"></i></button>
        </div>

        <div class="iv-modal__body">
            <div class="iv-field">
                <label for="ivCancelReason">Reason for cancelling</label>
                <input class="iv-input" id="ivCancelReason" name="reason" maxlength="255" required>
            </div>
        </div>

        <div class="iv-modal__footer">
            <button type="button" class="rc-btn rc-btn--secondary" data-iv-close>Keep interview</button>
            <button type="submit" class="rc-btn rc-btn--primary">Cancel interview</button>
        </div>
    </form>
</div>

<div class="iv-modal" id="ivDecisionModal" aria-hidden="true">
    <div class="iv-modal__backdrop" data-iv-close></div>
    <form class="iv-modal__dialog" method="post" action="<?= $action ?>"
          role="dialog" aria-modal="true" aria-labelledby="ivDecisionTitle">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="decision">
        <input type="hidden" name="interview_id" data-fill="id">

        <div class="iv-modal__header">
            <div>
                <span class="rc-eyebrow">Recruitment decision</span>
                <h3 id="ivDecisionTitle">Selection decision</h3>
                <p data-fill-text="name"></p>
            </div>
            <button type="button" class="iv-modal__close" data-iv-close aria-label="Close"><i class="fas fa-xmark"></i></button>
        </div>

        <div class="iv-modal__body">
            <div class="iv-field">
                <label for="ivDecision">Decision</label>
                <select class="iv-select" id="ivDecision" name="decision" data-fill="decision" required>
                    <option value="">Select a decision…</option>
                    <?php foreach (IV_DECISIONS as $k => $label): ?>
                        <option value="<?= e($k) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="iv-field">
                <label for="ivDecisionNote">Internal note <small>(never shown to the candidate)</small></label>
                <textarea class="iv-textarea" id="ivDecisionNote" name="decision_note" maxlength="2000"></textarea>
            </div>
            <p class="iv-hint">This sets the application to selected, waitlisted or rejected. Offers are issued in the existing offer workflow.</p>
        </div>

        <div class="iv-modal__footer">
            <button type="button" class="rc-btn rc-btn--secondary" data-iv-close>Close</button>
            <button type="submit" class="rc-btn rc-btn--primary">Save decision</button>
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
        document.body.classList.remove('iv-modal-open');
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

    function toggleOutcomeFields() {
        var result = document.getElementById('ivResult');
        var box = document.getElementById('ivOutcomeFields');
        if (!result || !box) return;
        var held = result.value === 'completed';
        box.style.display = held ? '' : 'none';
        document.getElementById('ivOutcome').required = held;
        document.getElementById('ivNotes').required = held;
    }

    document.querySelectorAll('[data-iv-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var modal = document.getElementById(btn.getAttribute('data-iv-open'));
            if (!modal) return;

            closeModal();
            fill(modal, btn.dataset);

            if (modal.id === 'ivPeopleModal') {
                var ids = (btn.dataset.staff || '').split(',').filter(Boolean);
                Array.prototype.forEach.call(
                    modal.querySelector('select[multiple]').options,
                    function (opt) { opt.selected = ids.indexOf(opt.value) !== -1; }
                );
            }

            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('iv-modal-open');
            openModal = modal;

            if (modal.id === 'ivOutcomeModal') toggleOutcomeFields();

            var first = modal.querySelector('input:not([type=hidden]), select, textarea');
            if (first) first.focus();
        });
    });

    document.querySelectorAll('[data-iv-close]').forEach(function (el) {
        el.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') closeModal();
    });

    var resultSel = document.getElementById('ivResult');
    if (resultSel) resultSel.addEventListener('change', toggleOutcomeFields);

    var modeSel = document.getElementById('ivMode');
    var locInput = document.getElementById('ivLocation');
    function syncLocationRequired() {
        if (modeSel && locInput) locInput.required = modeSel.value !== 'phone';
    }
    if (modeSel) {
        modeSel.addEventListener('change', syncLocationRequired);
        syncLocationRequired();
    }

    <?php if ($prefillApplication > 0): ?>
    var autoBtn = document.querySelector('[data-iv-open="ivScheduleModal"]');
    if (autoBtn) autoBtn.click();
    <?php endif; ?>
})();
</script>

<?php endif; ?>

<?php
require __DIR__ . '/_layout_end.php';
