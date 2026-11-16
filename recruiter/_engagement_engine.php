<?php
/**
 * Engagement & Follow-Up Automation engine (constants + helpers).
 * Shared by recruiter/engagement_automation.php and the sidebar reminder notice.
 * Expects includes/bootstrap.php to be loaded already.
 */

require_once __DIR__ . '/_helpers.php';

/* ==================================================================
 | Constants
 * ==================================================================*/

// Applications in these states are finished (or not yet submitted): no engagement needed.
const EA_CLOSED_APP_STATUSES = ['draft', 'offer_accepted', 'offer_declined', 'rejected', 'withdrawn'];

const EA_PRIORITIES = [
    'high'   => 'High',
    'normal' => 'Normal',
    'low'    => 'Low',
];

const EA_SNOOZE_DAYS = [1, 2, 3, 7, 14];

const EA_TABS = ['due', 'upcoming', 'closed', 'all'];

// Same keys as recruiter/communications.php so records stay compatible.
const EA_CHANNELS = [
    'email'     => 'Email',
    'phone'     => 'Phone call',
    'sms'       => 'SMS',
    'whatsapp'  => 'WhatsApp',
    'video'     => 'Video call',
    'in_person' => 'In person',
    'linkedin'  => 'LinkedIn message',
    'other'     => 'Other',
];

const EA_DIRECTIONS = [
    'outbound' => 'Outbound (we contacted the candidate)',
    'inbound'  => 'Inbound (the candidate contacted us)',
];

const EA_OUTCOMES = [
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

const EA_PER_PAGE      = 25;
const EA_GEN_LIMIT     = 500;     // candidates examined per rule per run
const EA_AUTO_INTERVAL = 300;     // seconds between automatic runs per session

// One automation rule exists per trigger.
const EA_TRIGGERS = [
    'stale_contact' => [
        'label'    => 'No recent contact',
        'desc'     => 'An active application whose candidate has not been contacted for the set number of days, with no follow-up scheduled.',
        'default'  => 7,
        'min'      => 1,
        'max'      => 90,
        'unit'     => 'days without contact',
        'priority' => 'normal',
        'template' => 'check_in',
    ],
    'no_response' => [
        'label'    => 'Awaiting candidate reply',
        'desc'     => 'Your last interaction ended with no response or a left message, and nothing has happened since.',
        'default'  => 3,
        'min'      => 1,
        'max'      => 30,
        'unit'     => 'days after the attempt',
        'priority' => 'high',
        'template' => 'awaiting_reply',
    ],
    'follow_up_due' => [
        'label'    => 'Scheduled follow-up due',
        'desc'     => 'A follow-up you scheduled on the Communications page is due, or about to be.',
        'default'  => 0,
        'min'      => 0,
        'max'      => 14,
        'unit'     => 'days before it is due',
        'priority' => 'high',
        'template' => 'check_in',
    ],
    'stage_stalled' => [
        'label'    => 'Application stalled in stage',
        'desc'     => 'An active application has had no status update for the set number of days.',
        'default'  => 7,
        'min'      => 1,
        'max'      => 90,
        'unit'     => 'days without a status update',
        'priority' => 'normal',
        'template' => 'status_update',
    ],
    'interview_upcoming' => [
        'label'    => 'Interview coming up',
        'desc'     => 'One of your scheduled interviews starts within the set number of days (needs the Interview Workspace).',
        'default'  => 1,
        'min'      => 1,
        'max'      => 14,
        'unit'     => 'days before the interview',
        'priority' => 'normal',
        'template' => 'interview_reminder',
    ],
];

const EA_DEFAULT_TEMPLATES = [
    'check_in' => [
        'name'    => 'Checking in',
        'subject' => 'Checking in on your application - {opportunity}',
        'body'    => "Hi {first_name},\n\nI hope you are well. I wanted to check in regarding your application for {opportunity} (ref {reference}). Please let me know if you are still interested and available, or if anything has changed.\n\nKind regards,\n{recruiter_name}",
    ],
    'awaiting_reply' => [
        'name'    => 'Awaiting your reply',
        'subject' => 'Following up - {opportunity}',
        'body'    => "Hi {first_name},\n\nI tried to reach you earlier about your application for {opportunity} (ref {reference}) and have not heard back yet. Could you reply with a good time to talk?\n\nKind regards,\n{recruiter_name}",
    ],
    'status_update' => [
        'name'    => 'Application status update',
        'subject' => 'An update on your application - {opportunity}',
        'body'    => "Hi {first_name},\n\nThank you for your patience with your application for {opportunity} (ref {reference}). I wanted to let you know that it is still being progressed and to confirm that you remain interested.\n\nKind regards,\n{recruiter_name}",
    ],
    'interview_reminder' => [
        'name'    => 'Interview reminder',
        'subject' => 'Reminder: your upcoming interview - {opportunity}',
        'body'    => "Hi {first_name},\n\nThis is a friendly reminder about your upcoming interview for {opportunity}. Please reply to confirm that you can attend.\n\nKind regards,\n{recruiter_name}",
    ],
];

/* ==================================================================
 | Helpers (page-local, all prefixed ea_)
 * ==================================================================*/

if (!function_exists('ea_fetch_all')) {
    /** Runs a prepared SELECT and returns every row as an associative array. */
    function ea_fetch_all(mysqli $conn, string $sql, string $types = '', array $params = []): array
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
}

if (!function_exists('ea_exec')) {
    /** Runs a prepared INSERT / UPDATE / DELETE and returns the affected row count. */
    function ea_exec(mysqli $conn, string $sql, string $types = '', array $params = []): int
    {
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            throw new RuntimeException('Query failed: ' . $conn->error);
        }

        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $n = (int)$stmt->affected_rows;
        $stmt->close();

        return $n;
    }
}

if (!function_exists('ea_ensure_tables')) {
    function ea_ensure_tables(mysqli $conn, ?string &$error): bool
    {
        $defs = [
            // Same definition as recruiter/communications.php so either page can create it first.
            'recruiter_candidate_communications' =>
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'ea_message_templates' =>
                "CREATE TABLE IF NOT EXISTS ea_message_templates (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    recruiter_id INT UNSIGNED NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    subject VARCHAR(190) NOT NULL DEFAULT '',
                    body TEXT NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_emt_recruiter (recruiter_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'ea_automation_rules' =>
                "CREATE TABLE IF NOT EXISTS ea_automation_rules (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    recruiter_id INT UNSIGNED NOT NULL,
                    trigger_key VARCHAR(30) NOT NULL,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    threshold_days SMALLINT UNSIGNED NOT NULL DEFAULT 7,
                    priority VARCHAR(10) NOT NULL DEFAULT 'normal',
                    template_id INT UNSIGNED NULL,
                    last_run_at DATETIME NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_ear_trigger (recruiter_id, trigger_key)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'ea_reminders' =>
                "CREATE TABLE IF NOT EXISTS ea_reminders (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    recruiter_id INT UNSIGNED NOT NULL,
                    rule_id INT UNSIGNED NULL,
                    source_type VARCHAR(30) NOT NULL DEFAULT 'manual',
                    related_id INT UNSIGNED NULL,
                    candidate_id INT UNSIGNED NOT NULL,
                    application_id INT UNSIGNED NULL,
                    ref_status VARCHAR(40) NOT NULL DEFAULT '',
                    title VARCHAR(190) NOT NULL,
                    detail VARCHAR(500) NOT NULL DEFAULT '',
                    priority VARCHAR(10) NOT NULL DEFAULT 'normal',
                    suggested_subject VARCHAR(190) NOT NULL DEFAULT '',
                    suggested_body TEXT NULL,
                    remind_at DATETIME NOT NULL,
                    status VARCHAR(12) NOT NULL DEFAULT 'pending',
                    resolved_at DATETIME NULL,
                    resolution_note VARCHAR(255) NOT NULL DEFAULT '',
                    dedupe_key VARCHAR(120) NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_er_dedupe (recruiter_id, dedupe_key),
                    KEY idx_er_queue (recruiter_id, status, remind_at),
                    KEY idx_er_candidate (candidate_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];

        try {
            foreach ($defs as $table => $ddl) {
                if (!rc_has_table($conn, $table)) {
                    if (!$conn->query($ddl)) {
                        $error = $conn->error;
                        return false;
                    }
                }
            }
        } catch (Throwable $t) {
            $error = $t->getMessage();
            return false;
        }

        return true;
    }
}

if (!function_exists('ea_seed_defaults')) {
    /** Creates starter templates (first visit only) and one rule per trigger. */
    function ea_seed_defaults(mysqli $conn, int $recruiterId): void
    {
        $r = ea_fetch_all($conn, 'SELECT COUNT(*) AS n FROM ea_automation_rules WHERE recruiter_id = ?', 'i', [$recruiterId]);
        $ruleCount = (int)($r[0]['n'] ?? 0);

        if ($ruleCount >= count(EA_TRIGGERS)) {
            return;
        }

        $r = ea_fetch_all($conn, 'SELECT COUNT(*) AS n FROM ea_message_templates WHERE recruiter_id = ?', 'i', [$recruiterId]);
        $tplCount = (int)($r[0]['n'] ?? 0);

        $tplIds = [];

        if ($ruleCount === 0 && $tplCount === 0) {
            foreach (EA_DEFAULT_TEMPLATES as $key => $tpl) {
                ea_exec(
                    $conn,
                    'INSERT INTO ea_message_templates (recruiter_id, name, subject, body) VALUES (?, ?, ?, ?)',
                    'isss',
                    [$recruiterId, $tpl['name'], $tpl['subject'], $tpl['body']]
                );
                $tplIds[$key] = (int)$conn->insert_id;
            }
        }

        foreach (EA_TRIGGERS as $key => $def) {
            $tplId = $tplIds[$def['template']] ?? null;

            ea_exec(
                $conn,
                'INSERT IGNORE INTO ea_automation_rules
                    (recruiter_id, trigger_key, is_active, threshold_days, priority, template_id)
                 VALUES (?, ?, ?, ?, ?, ?)',
                'isiisi',
                [$recruiterId, $key, 1, (int)$def['default'], $def['priority'], $tplId]
            );
        }
    }
}

if (!function_exists('ea_load_rules')) {
    function ea_load_rules(mysqli $conn, int $recruiterId): array
    {
        $out = [];

        foreach (ea_fetch_all($conn, 'SELECT * FROM ea_automation_rules WHERE recruiter_id = ?', 'i', [$recruiterId]) as $r) {
            $out[(string)$r['trigger_key']] = $r;
        }

        return $out;
    }
}

if (!function_exists('ea_load_templates')) {
    function ea_load_templates(mysqli $conn, int $recruiterId): array
    {
        $out = [];

        foreach (ea_fetch_all(
            $conn,
            'SELECT id, name, subject, body FROM ea_message_templates WHERE recruiter_id = ? ORDER BY name ASC, id ASC',
            'i',
            [$recruiterId]
        ) as $r) {
            $out[(int)$r['id']] = $r;
        }

        return $out;
    }
}

if (!function_exists('ea_eligibility_sql')) {
    /**
     * SQL condition for "a recruiter may work with this candidate":
     * valid recruiter-discovery consent OR a submitted (non-draft) application.
     */
    function ea_eligibility_sql(mysqli $conn, string $alias): ?string
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
}

if (!function_exists('ea_candidate_list')) {
    /** Active candidates a recruiter may set reminders for (optionally just one). */
    function ea_candidate_list(mysqli $conn, ?int $onlyId = null): array
    {
        $elig = ea_eligibility_sql($conn, 'u');

        if ($elig === null) {
            return [];
        }

        $sql = "SELECT u.id, u.first_name, u.last_name, u.email
                FROM users u
                INNER JOIN roles r ON r.id = u.role_id
                WHERE r.slug = 'candidate'
                  AND u.status = 'active'
                  AND {$elig}";

        $types  = '';
        $params = [];

        if ($onlyId !== null) {
            $sql   .= ' AND u.id = ?';
            $types  = 'i';
            $params = [$onlyId];
        }

        $sql .= ' ORDER BY u.first_name ASC, u.last_name ASC LIMIT 1000';

        return ea_fetch_all($conn, $sql, $types, $params);
    }
}

if (!function_exists('ea_parse_datetime')) {
    /** Parses an <input type="datetime-local"> value. Returns Y-m-d H:i:s or null. */
    function ea_parse_datetime(string $raw): ?string
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

if (!function_exists('ea_fmt')) {
    function ea_fmt(?string $dt, string $format = 'D, d M Y · H:i'): string
    {
        if (!$dt) {
            return '—';
        }

        $ts = strtotime($dt);

        return $ts ? date($format, $ts) : '—';
    }
}

if (!function_exists('ea_person')) {
    function ea_person(?string $first, ?string $last, string $fallback): string
    {
        $name = trim((string)$first . ' ' . (string)$last);

        return $name !== '' ? $name : $fallback;
    }
}

if (!function_exists('ea_like')) {
    function ea_like(string $term): string
    {
        return '%' . addcslashes($term, '%_\\') . '%';
    }
}

if (!function_exists('ea_days_since')) {
    function ea_days_since(?string $dt): int
    {
        $ts = $dt ? strtotime($dt) : false;

        return $ts ? max(0, (int)floor((time() - $ts) / 86400)) : 0;
    }
}

if (!function_exists('ea_plural')) {
    function ea_plural(int $n, string $word): string
    {
        return $n . ' ' . $word . ($n === 1 ? '' : 's');
    }
}

if (!function_exists('ea_render')) {
    /** Replaces {placeholders} in a template string. */
    function ea_render(string $text, array $vars): string
    {
        $map = [];

        foreach ($vars as $k => $v) {
            $map['{' . $k . '}'] = (string)$v;
        }

        return strtr($text, $map);
    }
}

if (!function_exists('ea_make_message')) {
    /** Returns [subject, body] for a template and variables ('' / '' when no template). */
    function ea_make_message(?array $tpl, array $vars): array
    {
        if (!$tpl) {
            return ['', ''];
        }

        return [
            mb_substr(ea_render((string)$tpl['subject'], $vars), 0, 190),
            ea_render((string)$tpl['body'], $vars),
        ];
    }
}

if (!function_exists('ea_insert_reminder')) {
    /** Inserts a reminder; returns false when the dedupe key already exists. */
    function ea_insert_reminder(mysqli $conn, array $d): bool
    {
        return ea_exec(
            $conn,
            'INSERT IGNORE INTO ea_reminders
                (recruiter_id, rule_id, source_type, related_id, candidate_id, application_id,
                 ref_status, title, detail, priority, suggested_subject, suggested_body,
                 remind_at, dedupe_key)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            'iisiiissssssss',
            [
                $d['recruiter_id'], $d['rule_id'], $d['source_type'], $d['related_id'],
                $d['candidate_id'], $d['application_id'], $d['ref_status'], $d['title'],
                $d['detail'], $d['priority'], $d['subject'], $d['body'],
                $d['remind_at'], $d['dedupe_key'],
            ]
        ) > 0;
    }
}

if (!function_exists('ea_auto_resolve')) {
    /**
     * Closes automatic reminders whose underlying situation has been dealt with
     * (follow-up completed, candidate contacted, status changed, interview no
     * longer scheduled). Returns the number closed.
     */
    function ea_auto_resolve(mysqli $conn, int $recruiterId, bool $hasInterviews, string $now): int
    {
        $pending = ea_fetch_all(
            $conn,
            "SELECT id, source_type, related_id, candidate_id, application_id, ref_status, created_at
             FROM ea_reminders
             WHERE recruiter_id = ? AND status = 'pending' AND source_type <> 'manual'
             LIMIT 2000",
            'i',
            [$recruiterId]
        );

        if (!$pending) {
            return 0;
        }

        $by = [];

        foreach ($pending as $p) {
            $by[(string)$p['source_type']][] = $p;
        }

        $resolve = [];

        // Follow-up no longer pending.
        if (!empty($by['follow_up_due'])) {
            $ids = [];
            foreach ($by['follow_up_due'] as $p) {
                if ((int)$p['related_id'] > 0) $ids[(int)$p['related_id']] = true;
            }

            $map = [];
            if ($ids) {
                foreach (ea_fetch_all(
                    $conn,
                    'SELECT id, follow_up_status FROM recruiter_candidate_communications
                     WHERE id IN (' . implode(',', array_keys($ids)) . ')'
                ) as $r) {
                    $map[(int)$r['id']] = (string)$r['follow_up_status'];
                }
            }

            foreach ($by['follow_up_due'] as $p) {
                $st = $map[(int)$p['related_id']] ?? null;
                if ($st === null || $st !== 'pending') $resolve[] = (int)$p['id'];
            }
        }

        // Candidate contacted after the reminder was raised.
        $contactTypes = array_merge($by['stale_contact'] ?? [], $by['no_response'] ?? []);

        if ($contactTypes) {
            $ids = [];
            foreach ($contactTypes as $p) {
                if ((int)$p['candidate_id'] > 0) $ids[(int)$p['candidate_id']] = true;
            }

            $map = [];
            if ($ids) {
                foreach (ea_fetch_all(
                    $conn,
                    'SELECT candidate_id, MAX(created_at) AS latest
                     FROM recruiter_candidate_communications
                     WHERE candidate_id IN (' . implode(',', array_keys($ids)) . ')
                     GROUP BY candidate_id'
                ) as $r) {
                    $map[(int)$r['candidate_id']] = (string)$r['latest'];
                }
            }

            foreach ($contactTypes as $p) {
                $latest = $map[(int)$p['candidate_id']] ?? null;
                if ($latest !== null && $latest > (string)$p['created_at']) $resolve[] = (int)$p['id'];
            }
        }

        // Application moved on (or closed).
        if (!empty($by['stage_stalled'])) {
            $ids = [];
            foreach ($by['stage_stalled'] as $p) {
                if ((int)$p['application_id'] > 0) $ids[(int)$p['application_id']] = true;
            }

            $map = [];
            if ($ids) {
                foreach (ea_fetch_all(
                    $conn,
                    'SELECT id, status FROM applications WHERE id IN (' . implode(',', array_keys($ids)) . ')'
                ) as $r) {
                    $map[(int)$r['id']] = (string)$r['status'];
                }
            }

            foreach ($by['stage_stalled'] as $p) {
                $st = $map[(int)$p['application_id']] ?? null;
                if ($st === null || $st !== (string)$p['ref_status'] || in_array($st, EA_CLOSED_APP_STATUSES, true)) {
                    $resolve[] = (int)$p['id'];
                }
            }
        }

        // Interview no longer scheduled.
        if (!empty($by['interview_upcoming']) && $hasInterviews) {
            $ids = [];
            foreach ($by['interview_upcoming'] as $p) {
                if ((int)$p['related_id'] > 0) $ids[(int)$p['related_id']] = true;
            }

            $map = [];
            if ($ids) {
                foreach (ea_fetch_all(
                    $conn,
                    'SELECT id, status FROM recruiter_interviews WHERE id IN (' . implode(',', array_keys($ids)) . ')'
                ) as $r) {
                    $map[(int)$r['id']] = (string)$r['status'];
                }
            }

            foreach ($by['interview_upcoming'] as $p) {
                $st = $map[(int)$p['related_id']] ?? null;
                if ($st === null || $st !== 'scheduled') $resolve[] = (int)$p['id'];
            }
        }

        $resolve = array_values(array_unique($resolve));
        $closed  = 0;

        foreach (array_chunk($resolve, 500) as $chunk) {
            $closed += ea_exec(
                $conn,
                "UPDATE ea_reminders
                 SET status = 'done', resolved_at = ?, resolution_note = 'Resolved automatically'
                 WHERE recruiter_id = ? AND status = 'pending'
                   AND id IN (" . implode(',', array_map('intval', $chunk)) . ')',
                'si',
                [$now, $recruiterId]
            );
        }

        return $closed;
    }
}

if (!function_exists('ea_generate')) {
    /** Evaluates every active rule and creates any new reminders. Returns the number created. */
    function ea_generate(
        mysqli $conn,
        int $recruiterId,
        string $recruiterName,
        array $rules,
        array $templates,
        bool $hasInterviews,
        string $now
    ): int {
        $created = 0;
        $closed  = "'" . implode("','", EA_CLOSED_APP_STATUSES) . "'";
        $nowTs   = strtotime($now);

        foreach (EA_TRIGGERS as $key => $def) {

            $rule = $rules[$key] ?? null;

            if (!$rule || (int)$rule['is_active'] !== 1) {
                continue;
            }

            if ($key === 'interview_upcoming' && !$hasInterviews) {
                continue;
            }

            $days     = max((int)$def['min'], min((int)$def['max'], (int)$rule['threshold_days']));
            $priority = isset(EA_PRIORITIES[(string)$rule['priority']]) ? (string)$rule['priority'] : (string)$def['priority'];
            $tpl      = $templates[(int)($rule['template_id'] ?? 0)] ?? null;
            $ruleId   = (int)$rule['id'];

            $add = function (
                ?int $relatedId, int $candidateId, ?int $applicationId, string $refStatus,
                string $title, string $detail, string $dedupe,
                string $first, string $last, string $opp, string $reference
            ) use (&$created, $conn, $recruiterId, $recruiterName, $key, $priority, $tpl, $ruleId, $now): void {

                $vars = [
                    'first_name'     => $first !== '' ? $first : 'there',
                    'candidate_name' => ea_person($first, $last, 'Candidate'),
                    'opportunity'    => $opp !== '' ? $opp : 'the role',
                    'reference'      => $reference,
                    'recruiter_name' => $recruiterName,
                ];

                [$subject, $body] = ea_make_message($tpl, $vars);

                $ok = ea_insert_reminder($conn, [
                    'recruiter_id' => $recruiterId,
                    'rule_id'      => $ruleId,
                    'source_type'  => $key,
                    'related_id'   => $relatedId,
                    'candidate_id' => $candidateId,
                    'application_id' => $applicationId,
                    'ref_status'   => mb_substr($refStatus, 0, 40),
                    'title'        => mb_substr($title, 0, 190),
                    'detail'       => mb_substr($detail, 0, 500),
                    'priority'     => $priority,
                    'subject'      => $subject,
                    'body'         => $body,
                    'remind_at'    => $now,
                    'dedupe_key'   => mb_substr($dedupe, 0, 120),
                ]);

                if ($ok) {
                    $created++;
                }
            };

            try {

                /* ---------- No recent contact ---------- */
                if ($key === 'stale_contact') {

                    $cutoff = date('Y-m-d H:i:s', strtotime('-' . $days . ' days', $nowTs));

                    $rows = ea_fetch_all(
                        $conn,
                        "SELECT a.id AS app_id, a.candidate_id, a.application_reference, a.status AS app_status,
                                cu.first_name, cu.last_name, o.title AS opp_title,
                                COALESCE(a.submitted_at, a.created_at) AS opened_at,
                                (SELECT MAX(c.occurred_at)
                                   FROM recruiter_candidate_communications c
                                  WHERE c.candidate_id = a.candidate_id) AS last_contact
                         FROM applications a
                         INNER JOIN users cu ON cu.id = a.candidate_id
                         INNER JOIN roles r ON r.id = cu.role_id
                         LEFT JOIN opportunities o ON o.id = a.opportunity_id
                         WHERE r.slug = 'candidate'
                           AND cu.status = 'active'
                           AND a.status NOT IN ({$closed})
                           AND a.id = (SELECT a2.id
                                         FROM applications a2
                                        WHERE a2.candidate_id = a.candidate_id
                                          AND a2.status NOT IN ({$closed})
                                        ORDER BY COALESCE(a2.updated_at, a2.created_at) DESC, a2.id DESC
                                        LIMIT 1)
                           AND NOT EXISTS (SELECT 1
                                             FROM recruiter_candidate_communications p
                                            WHERE p.candidate_id = a.candidate_id
                                              AND p.follow_up_status = 'pending')
                           AND COALESCE((SELECT MAX(c3.occurred_at)
                                           FROM recruiter_candidate_communications c3
                                          WHERE c3.candidate_id = a.candidate_id),
                                        a.submitted_at, a.created_at) < ?
                         ORDER BY a.id ASC
                         LIMIT " . (int)EA_GEN_LIMIT,
                        's',
                        [$cutoff]
                    );

                    foreach ($rows as $r) {
                        $name = ea_person($r['first_name'] ?? '', $r['last_name'] ?? '', 'Candidate');
                        $last = $r['last_contact'] !== null ? (string)$r['last_contact'] : null;

                        if ($last !== null) {
                            $d     = ea_days_since($last);
                            $title = 'No contact with ' . $name . ' for ' . ea_plural($d, 'day');
                            $det   = 'Last contact ' . ea_fmt($last, 'd M Y') . '. ';
                        } else {
                            $d     = ea_days_since((string)$r['opened_at']);
                            $title = $name . ' has not been contacted yet';
                            $det   = 'Applied ' . ea_plural($d, 'day') . ' ago. ';
                        }

                        $det .= trim((string)$r['opp_title'] . ' · ' . (string)$r['application_reference'] . ' · ' . rc_label((string)$r['app_status']), ' ·');

                        $add(
                            (int)$r['app_id'], (int)$r['candidate_id'], (int)$r['app_id'], (string)$r['app_status'],
                            $title, $det,
                            'stale:' . (int)$r['app_id'] . ':' . ($last !== null ? date('Ymd', (int)strtotime($last)) : 'none'),
                            (string)($r['first_name'] ?? ''), (string)($r['last_name'] ?? ''),
                            (string)($r['opp_title'] ?? ''), (string)($r['application_reference'] ?? '')
                        );
                    }
                }

                /* ---------- Awaiting reply ---------- */
                elseif ($key === 'no_response') {

                    $cutoff = date('Y-m-d H:i:s', strtotime('-' . $days . ' days', $nowTs));

                    $rows = ea_fetch_all(
                        $conn,
                        "SELECT c.id, c.candidate_id, c.application_id, c.channel, c.outcome, c.occurred_at,
                                cu.first_name, cu.last_name,
                                ap.application_reference, ap.status AS app_status, o.title AS opp_title
                         FROM recruiter_candidate_communications c
                         INNER JOIN users cu ON cu.id = c.candidate_id
                         INNER JOIN roles r ON r.id = cu.role_id
                         LEFT JOIN applications ap ON ap.id = c.application_id
                         LEFT JOIN opportunities o ON o.id = ap.opportunity_id
                         WHERE c.recruiter_id = ?
                           AND r.slug = 'candidate'
                           AND cu.status = 'active'
                           AND c.outcome IN ('no_response', 'left_message')
                           AND c.occurred_at < ?
                           AND NOT EXISTS (SELECT 1
                                             FROM recruiter_candidate_communications n
                                            WHERE n.candidate_id = c.candidate_id
                                              AND (n.occurred_at > c.occurred_at
                                                   OR (n.occurred_at = c.occurred_at AND n.id > c.id)))
                           AND NOT EXISTS (SELECT 1
                                             FROM recruiter_candidate_communications p
                                            WHERE p.candidate_id = c.candidate_id
                                              AND p.follow_up_status = 'pending')
                         ORDER BY c.occurred_at ASC
                         LIMIT " . (int)EA_GEN_LIMIT,
                        'is',
                        [$recruiterId, $cutoff]
                    );

                    foreach ($rows as $r) {
                        $name = ea_person($r['first_name'] ?? '', $r['last_name'] ?? '', 'Candidate');
                        $d    = ea_days_since((string)$r['occurred_at']);

                        $det = rc_label((string)$r['channel']) . ' attempt on ' . ea_fmt((string)$r['occurred_at'], 'd M Y')
                             . ' (' . ea_plural($d, 'day') . ' ago): ' . rc_label((string)$r['outcome']) . '.';

                        $add(
                            (int)$r['id'], (int)$r['candidate_id'],
                            $r['application_id'] !== null ? (int)$r['application_id'] : null, '',
                            'Awaiting reply from ' . $name, $det,
                            'noresp:' . (int)$r['id'],
                            (string)($r['first_name'] ?? ''), (string)($r['last_name'] ?? ''),
                            (string)($r['opp_title'] ?? ''), (string)($r['application_reference'] ?? '')
                        );
                    }
                }

                /* ---------- Scheduled follow-up due ---------- */
                elseif ($key === 'follow_up_due') {

                    $cutoff = date('Y-m-d H:i:s', strtotime('+' . $days . ' days', $nowTs));

                    $rows = ea_fetch_all(
                        $conn,
                        "SELECT c.id, c.candidate_id, c.application_id, c.follow_up_at, c.follow_up_note,
                                cu.first_name, cu.last_name,
                                ap.application_reference, o.title AS opp_title
                         FROM recruiter_candidate_communications c
                         INNER JOIN users cu ON cu.id = c.candidate_id
                         LEFT JOIN applications ap ON ap.id = c.application_id
                         LEFT JOIN opportunities o ON o.id = ap.opportunity_id
                         WHERE c.recruiter_id = ?
                           AND c.follow_up_status = 'pending'
                           AND c.follow_up_at IS NOT NULL
                           AND c.follow_up_at <= ?
                         ORDER BY c.follow_up_at ASC
                         LIMIT " . (int)EA_GEN_LIMIT,
                        'is',
                        [$recruiterId, $cutoff]
                    );

                    foreach ($rows as $r) {
                        $name  = ea_person($r['first_name'] ?? '', $r['last_name'] ?? '', 'Candidate');
                        $dueTs = (int)strtotime((string)$r['follow_up_at']);

                        $title = ($dueTs <= $nowTs ? 'Follow-up due: ' : 'Follow-up due soon: ') . $name;
                        $det   = 'Scheduled for ' . ea_fmt((string)$r['follow_up_at']) . '.'
                               . ((string)$r['follow_up_note'] !== '' ? ' ' . (string)$r['follow_up_note'] : '');

                        $add(
                            (int)$r['id'], (int)$r['candidate_id'],
                            $r['application_id'] !== null ? (int)$r['application_id'] : null, '',
                            $title, $det,
                            'fudue:' . (int)$r['id'] . ':' . date('YmdHi', $dueTs),
                            (string)($r['first_name'] ?? ''), (string)($r['last_name'] ?? ''),
                            (string)($r['opp_title'] ?? ''), (string)($r['application_reference'] ?? '')
                        );
                    }
                }

                /* ---------- Stalled application ---------- */
                elseif ($key === 'stage_stalled') {

                    $cutoff = date('Y-m-d H:i:s', strtotime('-' . $days . ' days', $nowTs));

                    $rows = ea_fetch_all(
                        $conn,
                        "SELECT a.id AS app_id, a.candidate_id, a.application_reference, a.status AS app_status,
                                cu.first_name, cu.last_name, o.title AS opp_title,
                                COALESCE(a.updated_at, a.submitted_at, a.created_at) AS last_activity
                         FROM applications a
                         INNER JOIN users cu ON cu.id = a.candidate_id
                         INNER JOIN roles r ON r.id = cu.role_id
                         LEFT JOIN opportunities o ON o.id = a.opportunity_id
                         WHERE r.slug = 'candidate'
                           AND cu.status = 'active'
                           AND a.status NOT IN ({$closed})
                           AND a.status NOT IN ('on_hold', 'waitlisted')
                           AND COALESCE(a.updated_at, a.submitted_at, a.created_at) < ?
                         ORDER BY last_activity ASC
                         LIMIT " . (int)EA_GEN_LIMIT,
                        's',
                        [$cutoff]
                    );

                    foreach ($rows as $r) {
                        $name = ea_person($r['first_name'] ?? '', $r['last_name'] ?? '', 'Candidate');
                        $d    = ea_days_since((string)$r['last_activity']);

                        $det = 'Stage "' . rc_label((string)$r['app_status']) . '" unchanged for ' . ea_plural($d, 'day') . '. '
                             . trim((string)$r['opp_title'] . ' · ' . (string)$r['application_reference'], ' ·');

                        $add(
                            (int)$r['app_id'], (int)$r['candidate_id'], (int)$r['app_id'], (string)$r['app_status'],
                            $name . ': application stalled in ' . rc_label((string)$r['app_status']), $det,
                            'stalled:' . (int)$r['app_id'] . ':' . (string)$r['app_status'] . ':' . date('Ymd', (int)strtotime((string)$r['last_activity'])),
                            (string)($r['first_name'] ?? ''), (string)($r['last_name'] ?? ''),
                            (string)($r['opp_title'] ?? ''), (string)($r['application_reference'] ?? '')
                        );
                    }
                }

                /* ---------- Upcoming interview ---------- */
                elseif ($key === 'interview_upcoming') {

                    $cutoff = date('Y-m-d H:i:s', strtotime('+' . $days . ' days', $nowTs));

                    $rows = ea_fetch_all(
                        $conn,
                        "SELECT ri.id, ri.application_id, ri.candidate_id, ri.round_label, ri.scheduled_at, ri.location,
                                cu.first_name, cu.last_name,
                                ap.application_reference, ap.status AS app_status, o.title AS opp_title
                         FROM recruiter_interviews ri
                         INNER JOIN users cu ON cu.id = ri.candidate_id
                         LEFT JOIN applications ap ON ap.id = ri.application_id
                         LEFT JOIN opportunities o ON o.id = ri.opportunity_id
                         WHERE ri.recruiter_id = ?
                           AND ri.status = 'scheduled'
                           AND ri.scheduled_at > ?
                           AND ri.scheduled_at <= ?
                         ORDER BY ri.scheduled_at ASC
                         LIMIT " . (int)EA_GEN_LIMIT,
                        'iss',
                        [$recruiterId, $now, $cutoff]
                    );

                    foreach ($rows as $r) {
                        $name = ea_person($r['first_name'] ?? '', $r['last_name'] ?? '', 'Candidate');

                        $det = (string)$r['round_label'] . ' on ' . ea_fmt((string)$r['scheduled_at'])
                             . ((string)$r['location'] !== '' ? ' · ' . (string)$r['location'] : '');

                        $add(
                            (int)$r['id'], (int)$r['candidate_id'],
                            $r['application_id'] !== null ? (int)$r['application_id'] : null, '',
                            'Interview coming up: ' . $name, $det,
                            'ivup:' . (int)$r['id'] . ':' . date('YmdHi', (int)strtotime((string)$r['scheduled_at'])),
                            (string)($r['first_name'] ?? ''), (string)($r['last_name'] ?? ''),
                            (string)($r['opp_title'] ?? ''), (string)($r['application_reference'] ?? '')
                        );
                    }
                }

                ea_exec(
                    $conn,
                    'UPDATE ea_automation_rules SET last_run_at = ? WHERE id = ? AND recruiter_id = ?',
                    'sii',
                    [$now, $ruleId, $recruiterId]
                );

            } catch (Throwable $t) {
                error_log('engagement_automation.php rule ' . $key . ': ' . $t->getMessage());
            }
        }

        return $created;
    }
}

if (!function_exists('ea_run_all')) {
    /** Auto-resolves finished reminders, then generates new ones. */
    function ea_run_all(mysqli $conn, int $recruiterId, string $recruiterName, bool $hasInterviews): array
    {
        $now = date('Y-m-d H:i:s');

        $resolved = 0;

        try {
            $resolved = ea_auto_resolve($conn, $recruiterId, $hasInterviews, $now);
        } catch (Throwable $t) {
            error_log('engagement_automation.php resolve: ' . $t->getMessage());
        }

        $created = ea_generate(
            $conn, $recruiterId, $recruiterName,
            ea_load_rules($conn, $recruiterId),
            ea_load_templates($conn, $recruiterId),
            $hasInterviews, $now
        );

        return ['created' => $created, 'resolved' => $resolved];
    }
}
