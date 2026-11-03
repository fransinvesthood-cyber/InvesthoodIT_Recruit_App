<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('recruiter');

$user        = current_user();
$flashes     = render_flashes();
$currentPage = 'offers';
$pageTitle   = 'Offers & Placements';

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

// Applications that can receive a new offer.
const OF_ELIGIBLE_APP_STATUSES = [
    'submitted', 'under_review', 'shortlisted', 'assessment', 'interview_required',
    'interview_scheduled', 'interview_completed', 'selected', 'waitlisted',
];

// Offer states that block creating another offer for the same application.
const OF_ACTIVE_STATUSES = ['draft', 'sent', 'accepted'];

const OF_STATUS_LABELS = [
    'draft'     => 'Draft',
    'sent'      => 'Offer sent',
    'accepted'  => 'Accepted',
    'declined'  => 'Declined',
    'withdrawn' => 'Withdrawn',
];

const OF_PLACEMENT_LABELS = [
    'none'           => 'Not applicable',
    'awaiting_start' => 'Awaiting start',
    'placed'         => 'Placed in programme',
    'did_not_start'  => 'Did not start',
];

// Application stages shown in the pipeline overview, in pipeline order.
const OF_PIPELINE_STAGES = [
    'submitted', 'under_review', 'shortlisted', 'assessment', 'interview_required',
    'interview_scheduled', 'interview_completed', 'selected', 'waitlisted',
    'offer_sent', 'offer_accepted', 'offer_declined',
];

/* ==================================================================
 | Helpers (page-local, all prefixed of_)
 * ==================================================================*/

if (!function_exists('of_ensure_tables')) {
    function of_ensure_tables(mysqli $conn, ?string &$error): bool
    {
        try {
            if (!rc_has_table($conn, 'recruiter_offers')) {
                $ok = $conn->query(
                    "CREATE TABLE IF NOT EXISTS recruiter_offers (
                        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                        recruiter_id INT UNSIGNED NOT NULL,
                        application_id INT UNSIGNED NOT NULL,
                        candidate_id INT UNSIGNED NOT NULL,
                        opportunity_id INT UNSIGNED NULL,
                        position_title VARCHAR(190) NOT NULL,
                        programme_name VARCHAR(190) NOT NULL,
                        start_date DATE NOT NULL,
                        end_date DATE NULL,
                        compensation VARCHAR(120) NOT NULL DEFAULT '',
                        location VARCHAR(255) NOT NULL DEFAULT '',
                        terms TEXT NULL,
                        status ENUM('draft','sent','accepted','declined','withdrawn') NOT NULL DEFAULT 'draft',
                        expires_on DATE NULL,
                        sent_at DATETIME NULL,
                        responded_at DATETIME NULL,
                        response_note VARCHAR(500) NULL,
                        withdraw_reason VARCHAR(255) NULL,
                        placement_status ENUM('none','awaiting_start','placed','did_not_start') NOT NULL DEFAULT 'none',
                        placement_recorded_at DATETIME NULL,
                        placement_note VARCHAR(500) NULL,
                        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (id),
                        KEY idx_ro_recruiter (recruiter_id, status),
                        KEY idx_ro_application (application_id, status),
                        KEY idx_ro_candidate (candidate_id),
                        KEY idx_ro_programme (programme_name, placement_status)
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

if (!function_exists('of_parse_date')) {
    /** Parses a Y-m-d value. Returns Y-m-d or null. */
    function of_parse_date(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') return null;
        $dt = DateTime::createFromFormat('!Y-m-d', $raw);
        if (!$dt || $dt->format('Y-m-d') !== $raw) return null;
        return $raw;
    }
}

if (!function_exists('of_fmt')) {
    function of_fmt(?string $dt, string $format = 'd M Y'): string
    {
        if (!$dt) return '—';
        $ts = strtotime($dt);
        return $ts ? date($format, $ts) : '—';
    }
}

if (!function_exists('of_fetch_owned')) {
    function of_fetch_owned(mysqli $conn, int $id, int $recruiterId): ?array
    {
        $stmt = $conn->prepare(
            'SELECT * FROM recruiter_offers WHERE id = ? AND recruiter_id = ? LIMIT 1'
        );
        $stmt->bind_param('ii', $id, $recruiterId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }
}

if (!function_exists('of_app_status_supported')) {
    /** True when applications.status can hold $value (guards against ENUMs that lack it). */
    function of_app_status_supported(mysqli $conn, string $value): bool
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

if (!function_exists('of_sync_application_status')) {
    /**
     * Moves an application along the existing pipeline stages (shown on the
     * Follow-Up Pipeline page). Only moves it from one of the $from stages,
     * and only when the column supports $to. Every change is written to
     * application_status_history when that table exists.
     */
    function of_sync_application_status(
        mysqli $conn, int $applicationId, string $to, array $from,
        ?int $changedBy = null, string $reason = ''
    ): void {
        if ($applicationId <= 0 || !$from || !of_app_status_supported($conn, $to)) return;

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

if (!function_exists('of_pick_notification_value')) {
    /**
     * Returns the first preferred value that candidate_notifications.<column> accepts.
     * Non-ENUM columns accept the first preference; null means nothing usable.
     */
    function of_pick_notification_value(mysqli $conn, string $column, array $preferred): ?string
    {
        static $cache = [];
        if (!isset($cache[$column])) {
            $cache[$column] = '';
            $stmt = $conn->prepare(
                "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'candidate_notifications' AND COLUMN_NAME = ?
                 LIMIT 1"
            );
            if ($stmt) {
                $stmt->bind_param('s', $column);
                $stmt->execute();
                $stmt->bind_result($ct);
                if ($stmt->fetch()) $cache[$column] = strtolower((string)$ct);
                $stmt->close();
            }
        }
        $type = $cache[$column];
        if (strpos($type, 'enum(') !== 0 && strpos($type, 'set(') !== 0) {
            return $preferred[0];
        }
        foreach ($preferred as $v) {
            if (strpos($type, "'" . strtolower($v) . "'") !== false) return $v;
        }
        return null;
    }
}

if (!function_exists('of_notify_candidate')) {
    /** In-app notification for the candidate (best effort; never blocks the action). */
    function of_notify_candidate(
        mysqli $conn, int $candidateId, string $title, string $message, int $offerId, string $event
    ): void {
        try {
            if ($candidateId <= 0 || !rc_has_table($conn, 'candidate_notifications')) return;
            // Use 'offer'/'offers' when the columns accept it; if either column is an
            // ENUM/SET that lacks those values, fall back to the closest allowed value
            // (or skip the notification) so the insert never fails on a bad value.
            $category = of_pick_notification_value($conn, 'category', ['offer', 'application', 'general', 'system', 'interview']);
            $module   = of_pick_notification_value($conn, 'module', ['offers', 'applications', 'general', 'system', 'interviews']);
            if ($category === null || $module === null) return;
            $dedupe   = 'offer_' . $offerId . '_' . $event . '_' . time();
            $stmt = $conn->prepare(
                'INSERT INTO candidate_notifications
                    (user_id, category, title, message, module, record_id, dedupe_key)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            if (!$stmt) return;
            $stmt->bind_param('issssis', $candidateId, $category, $title, $message, $module, $offerId, $dedupe);
            $stmt->execute();
            $stmt->close();
        } catch (Throwable $t) {
            error_log('offers.php notify: ' . $t->getMessage());
        }
    }
}

if (!function_exists('of_read_offer_input')) {
    /**
     * Reads and validates the offer form fields shared by create and edit.
     * Returns [data, errors].
     */
    function of_read_offer_input(): array
    {
        $errors = [];

        $position  = trim((string)($_POST['position_title'] ?? ''));
        $programme = trim((string)($_POST['programme_name'] ?? ''));
        $startRaw  = trim((string)($_POST['start_date'] ?? ''));
        $endRaw    = trim((string)($_POST['end_date'] ?? ''));
        $expRaw    = trim((string)($_POST['expires_on'] ?? ''));
        $comp      = trim((string)($_POST['compensation'] ?? ''));
        $location  = trim((string)($_POST['location'] ?? ''));
        $terms     = trim((string)($_POST['terms'] ?? ''));

        if ($position === '')               $errors[] = 'Please enter the position title.';
        if (mb_strlen($position) > 190)     $errors[] = 'The position title is too long (190 characters max).';
        if ($programme === '')              $errors[] = 'Please enter the programme the candidate will be placed into.';
        if (mb_strlen($programme) > 190)    $errors[] = 'The programme name is too long (190 characters max).';
        if (mb_strlen($comp) > 120)         $errors[] = 'Compensation is too long (120 characters max).';
        if (mb_strlen($location) > 255)     $errors[] = 'Location is too long (255 characters max).';
        if (mb_strlen($terms) > 4000)       $errors[] = 'Terms are too long (4000 characters max).';

        $start = of_parse_date($startRaw);
        $end   = of_parse_date($endRaw);
        $exp   = of_parse_date($expRaw);

        if ($start === null)                          $errors[] = 'Please enter a valid start date.';
        if ($endRaw !== '' && $end === null)          $errors[] = 'The end date is not valid.';
        if ($expRaw !== '' && $exp === null)          $errors[] = 'The response deadline is not valid.';
        if ($start !== null && $end !== null && $end < $start) {
            $errors[] = 'The end date cannot be before the start date.';
        }
        if ($start !== null && $exp !== null && $exp > $start) {
            $errors[] = 'The response deadline cannot be after the start date.';
        }

        return [[
            'position'  => $position,
            'programme' => $programme,
            'start'     => $start,
            'end'       => $end,
            'expires'   => $exp,
            'comp'      => $comp,
            'location'  => $location,
            'terms'     => $terms,
        ], $errors];
    }
}

if (!function_exists('of_history_columns')) {
    /** Finds the timestamp / id columns of application_status_history (names can differ between installs). */
    function of_history_columns(mysqli $conn): array
    {
        $out = ['time' => null, 'id' => false, 'usable' => false];

        $res = $conn->query(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'application_status_history'"
        );
        if (!$res) return $out;

        $cols = [];
        while ($r = $res->fetch_row()) $cols[] = strtolower((string)$r[0]);
        $res->free();

        foreach (['created_at', 'changed_at', 'updated_at', 'timestamp'] as $c) {
            if (in_array($c, $cols, true)) { $out['time'] = $c; break; }
        }
        $out['id']     = in_array('id', $cols, true);
        $out['usable'] = count(array_intersect(
            ['application_id', 'previous_status', 'new_status', 'change_reason'], $cols
        )) === 4;

        return $out;
    }
}

if (!function_exists('of_fetch_history')) {
    /** Status history rows for the given applications, keyed by application id (oldest first). */
    function of_fetch_history(mysqli $conn, array $applicationIds): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $applicationIds),
            fn($v) => $v > 0
        )));
        if (!$ids || !rc_has_table($conn, 'application_status_history')) return [];

        $cols = of_history_columns($conn);
        if (!$cols['usable']) return [];

        $select = 'application_id, previous_status, new_status, change_reason';
        $order  = [];
        if ($cols['time'] !== null) {
            $select .= ', `' . $cols['time'] . '` AS ev_time';
            $order[] = '`' . $cols['time'] . '` ASC';
        }
        if ($cols['id']) $order[] = 'id ASC';

        $sql = "SELECT $select FROM application_status_history
                WHERE application_id IN (" . implode(',', $ids) . ')'
             . ($order ? ' ORDER BY ' . implode(', ', $order) : '');

        $out = [];
        $res = $conn->query($sql);
        if ($res) {
            while ($r = $res->fetch_assoc()) $out[(int)$r['application_id']][] = $r;
            $res->free();
        }
        return $out;
    }
}

if (!function_exists('of_build_timeline')) {
    /**
     * Builds the step-by-step timeline for one offer: the offer's own milestones
     * plus every logged application status change, oldest first.
     * Returns a list of [when, label, detail] (plain text; escaped on output).
     */
    function of_build_timeline(array $of, array $history): array
    {
        $events = [];
        $add = function (?string $dt, string $label, string $detail = '', string $type = 'status') use (&$events): void {
            $ts = $dt ? strtotime($dt) : false;
            $events[] = [
                'ts'     => $ts ?: null,
                'when'   => $ts ? date('d M Y, H:i', $ts) : '—',
                'label'  => $label,
                'detail' => $detail,
                'type'   => $type,
            ];
        };

        $status = (string)($of['status'] ?? '');

        $add(
            (string)($of['created_at'] ?? ''), 'Offer drafted',
            (string)$of['position_title'] . ' · starts ' . of_fmt((string)$of['start_date']),
            'draft'
        );

        if (!empty($of['sent_at'])) {
            $add(
                (string)$of['sent_at'], 'Offer sent to candidate',
                !empty($of['expires_on']) ? 'Respond by ' . of_fmt((string)$of['expires_on']) : '',
                'sent'
            );
        }

        if (!empty($of['responded_at']) && in_array($status, ['accepted', 'declined'], true)) {
            $add(
                (string)$of['responded_at'],
                $status === 'accepted' ? 'Offer accepted (recorded by recruiter)' : 'Offer declined (recorded by recruiter)',
                (string)($of['response_note'] ?? ''),
                $status
            );
        }

        if ($status === 'withdrawn') {
            $add((string)($of['updated_at'] ?? ''), 'Offer withdrawn', (string)($of['withdraw_reason'] ?? ''), 'withdrawn');
        }

        if (!empty($of['placement_recorded_at'])) {
            $add(
                (string)$of['placement_recorded_at'],
                'Placement: ' . (OF_PLACEMENT_LABELS[(string)$of['placement_status']] ?? rc_label((string)$of['placement_status'])),
                (string)($of['placement_note'] ?? ''),
                (string)$of['placement_status'] === 'did_not_start' ? 'declined' : 'placement'
            );
        }

        foreach ($history as $h) {
            $prev = (string)($h['previous_status'] ?? '');
            $new  = (string)($h['new_status'] ?? '');
            $add(
                isset($h['ev_time']) ? (string)$h['ev_time'] : null,
                'Application status: ' . ($prev !== '' ? rc_label($prev) . ' → ' : '') . rc_label($new),
                (string)($h['change_reason'] ?? '')
            );
        }

        usort($events, fn($a, $b) => ($a['ts'] ?? PHP_INT_MAX) <=> ($b['ts'] ?? PHP_INT_MAX));

        return array_map(
            fn($e) => ['when' => $e['when'], 'label' => $e['label'], 'detail' => $e['detail'], 'type' => $e['type']],
            $events
        );
    }
}

if (!function_exists('of_render_fields')) {
    /** Offer form fields, shared by the create and edit modals. */
    function of_render_fields(string $p): void
    {
        ?>
        <div class="of-field">
            <label for="<?= $p ?>Position">Position title</label>
            <input class="of-input" id="<?= $p ?>Position" name="position_title" maxlength="190" data-fill="position" required>
        </div>

        <div class="of-field">
            <label for="<?= $p ?>Programme">Programme</label>
            <input class="of-input" id="<?= $p ?>Programme" name="programme_name" maxlength="190" data-fill="programme"
                   placeholder="e.g. Graduate Software Development Programme" required>
            <span class="of-hint">The programme the candidate will be placed into once the offer is accepted.</span>
        </div>

        <div class="of-row2">
            <div class="of-field">
                <label for="<?= $p ?>Start">Start date</label>
                <input class="of-input" type="date" id="<?= $p ?>Start" name="start_date" data-fill="start" required>
            </div>
            <div class="of-field">
                <label for="<?= $p ?>End">End date <small>(optional)</small></label>
                <input class="of-input" type="date" id="<?= $p ?>End" name="end_date" data-fill="end">
            </div>
        </div>

        <div class="of-row2">
            <div class="of-field">
                <label for="<?= $p ?>Comp">Stipend / salary <small>(optional)</small></label>
                <input class="of-input" id="<?= $p ?>Comp" name="compensation" maxlength="120" data-fill="comp"
                       placeholder="e.g. R12 000 per month">
            </div>
            <div class="of-field">
                <label for="<?= $p ?>Expires">Respond by <small>(optional)</small></label>
                <input class="of-input" type="date" id="<?= $p ?>Expires" name="expires_on" data-fill="expires">
            </div>
        </div>

        <div class="of-field">
            <label for="<?= $p ?>Location">Work location <small>(optional)</small></label>
            <input class="of-input" id="<?= $p ?>Location" name="location" maxlength="255" data-fill="location">
        </div>

        <div class="of-field">
            <label for="<?= $p ?>Terms">Terms &amp; conditions <small>(optional)</small></label>
            <textarea class="of-textarea" id="<?= $p ?>Terms" name="terms" maxlength="4000" data-fill="terms"></textarea>
        </div>
        <?php
    }
}

/* ==================================================================
 | Schema
 * ==================================================================*/

$schemaError = null;
$schemaReady = of_ensure_tables($conn, $schemaError);

$today = date('Y-m-d');
$now   = date('Y-m-d H:i:s');

/* ==================================================================
 | POST handling (post → redirect → get)
 * ==================================================================*/

if ($schemaReady && $_SERVER['REQUEST_METHOD'] === 'POST') {

    rc_verify_csrf();

    $redirect = url('recruiter/offers.php');
    $action   = trim((string)($_POST['action'] ?? ''));

    try {

        /* -------------------- Prepare a new offer (draft) -------------------- */
        if ($action === 'create') {

            $applicationId = (int)($_POST['application_id'] ?? 0);
            [$in, $errors] = of_read_offer_input();

            $eligible = "'" . implode("','", OF_ELIGIBLE_APP_STATUSES) . "'";
            $active   = "'" . implode("','", OF_ACTIVE_STATUSES) . "'";

            $stmt = $conn->prepare(
                "SELECT a.id, a.candidate_id, a.opportunity_id
                 FROM applications a
                 WHERE a.id = ? AND a.status IN ($eligible)
                   AND NOT EXISTS (
                        SELECT 1 FROM recruiter_offers x
                        WHERE x.application_id = a.id AND x.status IN ($active)
                   )
                 LIMIT 1"
            );
            $stmt->bind_param('i', $applicationId);
            $stmt->execute();
            $app = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$app) {
                $errors[] = 'Please choose an application that is ready for an offer and does not already have an active one.';
            }

            if ($errors) {
                rc_flash('danger', implode(' ', $errors));
            } else {
                $candidateId   = (int)$app['candidate_id'];
                $opportunityId = $app['opportunity_id'] !== null ? (int)$app['opportunity_id'] : null;

                $stmt = $conn->prepare(
                    'INSERT INTO recruiter_offers
                        (recruiter_id, application_id, candidate_id, opportunity_id,
                         position_title, programme_name, start_date, end_date,
                         compensation, location, terms, expires_on)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->bind_param(
                    'iiiissssssss',
                    $recruiterId, $applicationId, $candidateId, $opportunityId,
                    $in['position'], $in['programme'], $in['start'], $in['end'],
                    $in['comp'], $in['location'], $in['terms'], $in['expires']
                );
                $stmt->execute();
                $newId = (int)$conn->insert_id;
                $stmt->close();

                rc_activity(
                    $conn, $recruiterId, 'offer_prepared',
                    'Prepared offer draft "' . $in['position'] . '" for application #' . $applicationId,
                    'recruiter_offer', $newId
                );
                rc_flash('success', 'Offer saved as a draft. Review it, then send it to the candidate.');
            }
        }

        /* -------------------- Edit a draft -------------------- */
        elseif ($action === 'update') {

            $id    = (int)($_POST['offer_id'] ?? 0);
            $offer = of_fetch_owned($conn, $id, $recruiterId);

            [$in, $errors] = of_read_offer_input();

            if (!$offer) {
                $errors[] = 'Offer not found.';
            } elseif ($offer['status'] !== 'draft') {
                $errors[] = 'Only draft offers can be edited.';
            }

            if ($errors) {
                rc_flash('danger', implode(' ', $errors));
            } else {
                $stmt = $conn->prepare(
                    "UPDATE recruiter_offers
                     SET position_title = ?, programme_name = ?, start_date = ?, end_date = ?,
                         compensation = ?, location = ?, terms = ?, expires_on = ?
                     WHERE id = ? AND recruiter_id = ? AND status = 'draft'"
                );
                $stmt->bind_param(
                    'ssssssssii',
                    $in['position'], $in['programme'], $in['start'], $in['end'],
                    $in['comp'], $in['location'], $in['terms'], $in['expires'],
                    $id, $recruiterId
                );
                $stmt->execute();
                $stmt->close();

                rc_activity(
                    $conn, $recruiterId, 'offer_updated',
                    'Updated offer draft #' . $id,
                    'recruiter_offer', $id
                );
                rc_flash('success', 'Draft updated.');
            }
        }

        /* -------------------- Send the offer -------------------- */
        elseif ($action === 'send') {

            $id    = (int)($_POST['offer_id'] ?? 0);
            $offer = of_fetch_owned($conn, $id, $recruiterId);

            if (!$offer) {
                rc_flash('danger', 'Offer not found.');
            } elseif ($offer['status'] !== 'draft') {
                rc_flash('danger', 'Only draft offers can be sent.');
            } elseif ($offer['expires_on'] !== null && (string)$offer['expires_on'] < $today) {
                rc_flash('danger', 'The response deadline has passed. Edit the draft and set a new deadline before sending.');
            } elseif ((string)$offer['start_date'] < $today) {
                rc_flash('danger', 'The start date is in the past. Edit the draft and set a new start date before sending.');
            } else {
                $stmt = $conn->prepare(
                    "UPDATE recruiter_offers SET status = 'sent', sent_at = ?
                     WHERE id = ? AND recruiter_id = ? AND status = 'draft'"
                );
                $stmt->bind_param('sii', $now, $id, $recruiterId);
                $stmt->execute();
                $sent = $stmt->affected_rows > 0;
                $stmt->close();

                if ($sent) {
                    try {
                        of_sync_application_status(
                            $conn, (int)$offer['application_id'], 'offer_sent',
                            OF_ELIGIBLE_APP_STATUSES, $recruiterId, 'Offer sent'
                        );
                    } catch (Throwable $t) {
                        error_log('offers.php status sync: ' . $t->getMessage());
                    }

                    of_notify_candidate(
                        $conn, (int)$offer['candidate_id'], 'You have received an offer',
                        'You have been offered the position "' . (string)$offer['position_title'] . '" in the '
                            . (string)$offer['programme_name'] . ', starting ' . of_fmt((string)$offer['start_date'])
                            . ($offer['expires_on'] ? '. Please respond by ' . of_fmt((string)$offer['expires_on']) : '') . '.',
                        $id, 'sent'
                    );

                    rc_activity(
                        $conn, $recruiterId, 'offer_sent',
                        'Sent offer #' . $id . ' for application #' . (int)$offer['application_id'],
                        'recruiter_offer', $id
                    );
                    rc_flash('success', 'Offer sent to the candidate.');
                } else {
                    rc_flash('danger', 'The offer could not be sent. Please refresh and try again.');
                }
            }
        }

        /* -------------------- Record the candidate's response -------------------- */
        elseif ($action === 'respond') {

            $id     = (int)($_POST['offer_id'] ?? 0);
            $offer  = of_fetch_owned($conn, $id, $recruiterId);
            $result = (string)($_POST['result'] ?? '');
            $note   = trim((string)($_POST['response_note'] ?? ''));

            $errors = [];
            if (!$offer) {
                $errors[] = 'Offer not found.';
            } elseif ($offer['status'] !== 'sent') {
                $errors[] = 'A response can only be recorded for an offer that has been sent and is awaiting a response.';
            }
            if (!in_array($result, ['accepted', 'declined'], true)) {
                $errors[] = 'Please choose whether the candidate accepted or declined.';
            }
            if (mb_strlen($note) > 500) {
                $errors[] = 'The note is too long (500 characters max).';
            }

            if ($errors) {
                rc_flash('danger', implode(' ', $errors));
            } else {
                $placement = $result === 'accepted' ? 'awaiting_start' : 'none';

                $stmt = $conn->prepare(
                    "UPDATE recruiter_offers
                     SET status = ?, responded_at = ?, response_note = ?, placement_status = ?
                     WHERE id = ? AND recruiter_id = ? AND status = 'sent'"
                );
                $stmt->bind_param('ssssii', $result, $now, $note, $placement, $id, $recruiterId);
                $stmt->execute();
                $changed = $stmt->affected_rows > 0;
                $stmt->close();

                if ($changed) {
                    try {
                        of_sync_application_status(
                            $conn, (int)$offer['application_id'],
                            $result === 'accepted' ? 'offer_accepted' : 'offer_declined',
                            ['offer_sent', 'selected', 'interview_completed'],
                            $recruiterId,
                            $result === 'accepted' ? 'Offer accepted' : 'Offer declined'
                        );
                    } catch (Throwable $t) {
                        error_log('offers.php status sync: ' . $t->getMessage());
                    }

                    rc_activity(
                        $conn, $recruiterId, 'offer_response_recorded',
                        'Recorded offer #' . $id . ' as ' . $result,
                        'recruiter_offer', $id
                    );
                    rc_flash('success', $result === 'accepted'
                        ? 'Offer recorded as accepted. The candidate is now awaiting placement.'
                        : 'Offer recorded as declined.');
                } else {
                    rc_flash('danger', 'The response could not be saved. Please refresh and try again.');
                }
            }
        }

        /* -------------------- Extend the response deadline -------------------- */
        elseif ($action === 'extend') {

            $id    = (int)($_POST['offer_id'] ?? 0);
            $offer = of_fetch_owned($conn, $id, $recruiterId);
            $new   = of_parse_date((string)($_POST['expires_on'] ?? ''));

            if (!$offer) {
                rc_flash('danger', 'Offer not found.');
            } elseif ($offer['status'] !== 'sent') {
                rc_flash('danger', 'The deadline can only be changed on an offer awaiting a response.');
            } elseif ($new === null) {
                rc_flash('danger', 'Please enter a valid date.');
            } elseif ($new < $today) {
                rc_flash('danger', 'The new deadline must not be in the past.');
            } elseif ($new > (string)$offer['start_date']) {
                rc_flash('danger', 'The deadline cannot be after the start date.');
            } else {
                $stmt = $conn->prepare(
                    "UPDATE recruiter_offers SET expires_on = ?
                     WHERE id = ? AND recruiter_id = ? AND status = 'sent'"
                );
                $stmt->bind_param('sii', $new, $id, $recruiterId);
                $stmt->execute();
                $stmt->close();

                of_notify_candidate(
                    $conn, (int)$offer['candidate_id'], 'Offer deadline updated',
                    'The deadline to respond to your offer for "' . (string)$offer['position_title']
                        . '" is now ' . of_fmt($new) . '.',
                    $id, 'extended'
                );
                rc_activity(
                    $conn, $recruiterId, 'offer_deadline_changed',
                    'Set response deadline of offer #' . $id . ' to ' . $new,
                    'recruiter_offer', $id
                );
                rc_flash('success', 'Response deadline updated.');
            }
        }

        /* -------------------- Withdraw a sent offer -------------------- */
        elseif ($action === 'withdraw') {

            $id     = (int)($_POST['offer_id'] ?? 0);
            $offer  = of_fetch_owned($conn, $id, $recruiterId);
            $reason = trim((string)($_POST['reason'] ?? ''));

            if (!$offer) {
                rc_flash('danger', 'Offer not found.');
            } elseif ($offer['status'] !== 'sent') {
                rc_flash('danger', 'Only an offer awaiting a response can be withdrawn.');
            } elseif ($reason === '') {
                rc_flash('danger', 'Please give a reason for withdrawing the offer.');
            } elseif (mb_strlen($reason) > 255) {
                rc_flash('danger', 'The reason is too long (255 characters max).');
            } else {
                $stmt = $conn->prepare(
                    "UPDATE recruiter_offers SET status = 'withdrawn', withdraw_reason = ?
                     WHERE id = ? AND recruiter_id = ? AND status = 'sent'"
                );
                $stmt->bind_param('sii', $reason, $id, $recruiterId);
                $stmt->execute();
                $changed = $stmt->affected_rows > 0;
                $stmt->close();

                if ($changed) {
                    try {
                        of_sync_application_status(
                            $conn, (int)$offer['application_id'], 'selected',
                            ['offer_sent'], $recruiterId, 'Offer withdrawn'
                        );
                    } catch (Throwable $t) {
                        error_log('offers.php status sync: ' . $t->getMessage());
                    }

                    of_notify_candidate(
                        $conn, (int)$offer['candidate_id'], 'Offer withdrawn',
                        'Your offer for "' . (string)$offer['position_title'] . '" has been withdrawn. You will be contacted if anything changes.',
                        $id, 'withdrawn'
                    );
                    rc_activity(
                        $conn, $recruiterId, 'offer_withdrawn',
                        'Withdrew offer #' . $id . ' — ' . $reason,
                        'recruiter_offer', $id
                    );
                    rc_flash('success', 'Offer withdrawn.');
                } else {
                    rc_flash('danger', 'The offer could not be withdrawn. Please refresh and try again.');
                }
            }
        }

        /* -------------------- Delete a draft -------------------- */
        elseif ($action === 'delete') {

            $id    = (int)($_POST['offer_id'] ?? 0);
            $offer = of_fetch_owned($conn, $id, $recruiterId);

            if (!$offer) {
                rc_flash('danger', 'Offer not found.');
            } elseif ($offer['status'] !== 'draft') {
                rc_flash('danger', 'Only draft offers can be deleted.');
            } else {
                $stmt = $conn->prepare(
                    "DELETE FROM recruiter_offers WHERE id = ? AND recruiter_id = ? AND status = 'draft'"
                );
                $stmt->bind_param('ii', $id, $recruiterId);
                $stmt->execute();
                $stmt->close();

                rc_activity(
                    $conn, $recruiterId, 'offer_draft_deleted',
                    'Deleted offer draft #' . $id,
                    'recruiter_offer', $id
                );
                rc_flash('success', 'Draft deleted.');
            }
        }

        /* -------------------- Record placement -------------------- */
        elseif ($action === 'placement') {

            $id     = (int)($_POST['offer_id'] ?? 0);
            $offer  = of_fetch_owned($conn, $id, $recruiterId);
            $status = (string)($_POST['placement_status'] ?? '');
            $note   = trim((string)($_POST['placement_note'] ?? ''));

            $errors = [];
            if (!$offer) {
                $errors[] = 'Offer not found.';
            } elseif ($offer['status'] !== 'accepted') {
                $errors[] = 'Placements can only be recorded for accepted offers.';
            }
            if (!in_array($status, ['awaiting_start', 'placed', 'did_not_start'], true)) {
                $errors[] = 'Please choose a placement status.';
            }
            if (mb_strlen($note) > 500) {
                $errors[] = 'The note is too long (500 characters max).';
            }
            if ($offer && $status === 'placed' && (string)$offer['start_date'] > $today) {
                $errors[] = 'The candidate cannot be marked as placed before the programme start date ('
                    . of_fmt((string)$offer['start_date']) . ').';
            }

            if ($errors) {
                rc_flash('danger', implode(' ', $errors));
            } else {
                $stmt = $conn->prepare(
                    "UPDATE recruiter_offers
                     SET placement_status = ?, placement_recorded_at = ?, placement_note = ?
                     WHERE id = ? AND recruiter_id = ? AND status = 'accepted'"
                );
                $stmt->bind_param('sssii', $status, $now, $note, $id, $recruiterId);
                $stmt->execute();
                $stmt->close();

                if ($status === 'placed') {
                    of_notify_candidate(
                        $conn, (int)$offer['candidate_id'], 'Placement confirmed',
                        'You have been placed in the ' . (string)$offer['programme_name'] . '. Welcome aboard!',
                        $id, 'placed'
                    );
                }

                rc_activity(
                    $conn, $recruiterId, 'offer_placement_recorded',
                    'Recorded placement "' . $status . '" for offer #' . $id,
                    'recruiter_offer', $id
                );
                rc_flash('success', 'Placement updated.');
            }
        }

        else {
            rc_flash('danger', 'Unknown action.');
        }

    } catch (Throwable $t) {
        error_log('offers.php: ' . $t->getMessage());
        rc_flash('danger', 'Something went wrong while saving. Please try again.');
    }

    header('Location: ' . $redirect);
    exit;
}

/* ==================================================================
 | Data for display
 * ==================================================================*/

$tab = (string)($_GET['tab'] ?? 'all');
if (!in_array($tab, ['all', 'draft', 'awaiting', 'accepted', 'closed'], true)) $tab = 'all';
$q = trim((string)($_GET['q'] ?? ''));

$prefillApplication = (int)($_GET['prepare'] ?? 0);

$stats = [
    'draft' => 0, 'awaiting' => 0, 'overdue' => 0, 'accepted' => 0,
    'declined' => 0, 'withdrawn' => 0, 'placed' => 0,
];
$offers       = [];
$applications = [];
$placements   = [];
$programmes   = [];
$consentSet   = [];
$offerFunnel  = ['prepared' => 0, 'sent' => 0, 'responded' => 0, 'accepted' => 0, 'placed' => 0];
$stageCounts  = [];
$timelines    = [];

if ($schemaReady) {

    /* ---- Stats ---- */
    $stmt = $conn->prepare(
        "SELECT
            COALESCE(SUM(status = 'draft'), 0) AS draft,
            COALESCE(SUM(status = 'sent'), 0) AS awaiting,
            COALESCE(SUM(status = 'sent' AND expires_on IS NOT NULL AND expires_on < ?), 0) AS overdue,
            COALESCE(SUM(status = 'accepted'), 0) AS accepted,
            COALESCE(SUM(status = 'declined'), 0) AS declined,
            COALESCE(SUM(status = 'withdrawn'), 0) AS withdrawn,
            COALESCE(SUM(status = 'accepted' AND placement_status = 'placed'), 0) AS placed
         FROM recruiter_offers WHERE recruiter_id = ?"
    );
    $stmt->bind_param('si', $today, $recruiterId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) foreach (array_keys($stats) as $k) $stats[$k] = (int)$row[$k];

    $responded  = $stats['accepted'] + $stats['declined'];
    $acceptRate = $responded > 0 ? (int)round(($stats['accepted'] / $responded) * 100) : null;

    /* ---- Offer funnel (this recruiter's offers) ---- */
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS prepared,
                COALESCE(SUM(sent_at IS NOT NULL), 0) AS sent,
                COALESCE(SUM(status IN ('accepted','declined')), 0) AS responded,
                COALESCE(SUM(status = 'accepted'), 0) AS accepted,
                COALESCE(SUM(status = 'accepted' AND placement_status = 'placed'), 0) AS placed
         FROM recruiter_offers WHERE recruiter_id = ?"
    );
    if ($stmt) {
        $stmt->bind_param('i', $recruiterId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) foreach (array_keys($offerFunnel) as $k) $offerFunnel[$k] = (int)$row[$k];
    }

    /* ---- Applications by current stage (same unscoped view as the "ready for an offer" list) ---- */
    $res = $conn->query('SELECT status, COUNT(*) AS c FROM applications GROUP BY status');
    if ($res) {
        while ($r = $res->fetch_assoc()) $stageCounts[(string)$r['status']] = (int)$r['c'];
        $res->free();
    }

    /* ---- Offer list ---- */
    $where  = ['o.recruiter_id = ?'];
    $types  = 'i';
    $params = [$recruiterId];

    switch ($tab) {
        case 'draft':    $where[] = "o.status = 'draft'"; break;
        case 'awaiting': $where[] = "o.status = 'sent'"; break;
        case 'accepted': $where[] = "o.status = 'accepted'"; break;
        case 'closed':   $where[] = "o.status IN ('declined','withdrawn')"; break;
    }

    if ($q !== '') {
        $where[] = "(CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ?
                     OR o.position_title LIKE ? OR o.programme_name LIKE ?
                     OR a.application_reference LIKE ?)";
        $like    = '%' . $q . '%';
        $types  .= 'sssss';
        array_push($params, $like, $like, $like, $like, $like);
    }

    $stmt = $conn->prepare(
        "SELECT o.*, u.first_name, u.last_name, u.email AS candidate_email,
                a.application_reference, a.status AS application_status
         FROM recruiter_offers o
         LEFT JOIN users u ON u.id = o.candidate_id
         LEFT JOIN applications a ON a.id = o.application_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY o.updated_at DESC, o.id DESC
         LIMIT 300"
    );
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $offers[] = $r;
        $stmt->close();
    }

    /* ---- Timelines: offer milestones + logged application status changes ---- */
    if ($offers) {
        try {
            $historyByApp = of_fetch_history($conn, array_map(fn($r) => (int)$r['application_id'], $offers));
        } catch (Throwable $t) {
            error_log('offers.php history: ' . $t->getMessage());
            $historyByApp = [];
        }
        foreach ($offers as $r) {
            $timelines[(string)(int)$r['id']] = of_build_timeline($r, $historyByApp[(int)$r['application_id']] ?? []);
        }
    }

    /* ---- Candidate profile links (only with valid recruiter-discovery consent) ---- */
    if ($offers && rc_has_table($conn, 'candidate_consents')) {
        $candIds = implode(',', array_unique(array_map(fn($r) => (int)$r['candidate_id'], $offers)));
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

    /* ---- Applications ready for an offer ---- */
    $eligible = "'" . implode("','", OF_ELIGIBLE_APP_STATUSES) . "'";
    $active   = "'" . implode("','", OF_ACTIVE_STATUSES) . "'";

    $res = $conn->query(
        "SELECT a.id, a.application_reference, a.status, u.first_name, u.last_name,
                o.title AS opportunity_title
         FROM applications a
         LEFT JOIN users u ON u.id = a.candidate_id
         LEFT JOIN opportunities o ON o.id = a.opportunity_id
         WHERE a.status IN ($eligible)
           AND NOT EXISTS (
                SELECT 1 FROM recruiter_offers x
                WHERE x.application_id = a.id AND x.status IN ($active)
           )
         ORDER BY COALESCE(a.submitted_at, a.created_at) DESC, a.id DESC
         LIMIT 500"
    );
    if ($res) {
        while ($r = $res->fetch_assoc()) $applications[] = $r;
        $res->free();
    } else {
        error_log('offers.php applications query failed: ' . $conn->error);
    }

    /* ---- Placements: accepted offers ---- */
    $stmt = $conn->prepare(
        "SELECT o.*, u.first_name, u.last_name, u.email AS candidate_email,
                a.application_reference
         FROM recruiter_offers o
         LEFT JOIN users u ON u.id = o.candidate_id
         LEFT JOIN applications a ON a.id = o.application_id
         WHERE o.recruiter_id = ? AND o.status = 'accepted'
         ORDER BY o.start_date ASC, o.id DESC
         LIMIT 200"
    );
    if ($stmt) {
        $stmt->bind_param('i', $recruiterId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $placements[] = $r;
        $stmt->close();
    }

    /* ---- Programme summary ---- */
    $stmt = $conn->prepare(
        "SELECT programme_name,
                COUNT(*) AS accepted,
                SUM(placement_status = 'placed') AS placed,
                SUM(placement_status = 'awaiting_start') AS awaiting,
                SUM(placement_status = 'did_not_start') AS did_not_start
         FROM recruiter_offers
         WHERE recruiter_id = ? AND status = 'accepted'
         GROUP BY programme_name
         ORDER BY placed DESC, accepted DESC, programme_name ASC
         LIMIT 50"
    );
    if ($stmt) {
        $stmt->bind_param('i', $recruiterId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $programmes[] = $r;
        $stmt->close();
    }
}

$csrf   = rc_csrf();
$action = url('recruiter/offers.php');

require __DIR__ . '/_layout_start.php';
?>

<style>
.of-toolbar{display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;margin-bottom:16px}
.of-tabs{display:flex;flex-wrap:wrap;gap:6px}
.of-tab{padding:8px 13px;border-radius:999px;text-decoration:none;font-size:13px;font-weight:600;color:inherit;background:rgba(127,127,127,.10)}
.of-tab.is-active{background:#2454d8;color:#fff}
.of-search{display:flex;gap:8px}
.of-search input{min-width:220px}

.of-field{display:flex;flex-direction:column;gap:6px;margin-bottom:14px}
.of-field label{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;opacity:.75}
.of-field label small{text-transform:none;letter-spacing:0;font-weight:400;opacity:.8}
.of-input,.of-select,.of-textarea{width:100%;box-sizing:border-box;padding:10px 12px;border-radius:10px;border:1px solid rgba(127,127,127,.35);background:transparent;color:inherit;font:inherit}
.of-textarea{min-height:90px;resize:vertical}
.of-input:focus,.of-select:focus,.of-textarea:focus{outline:2px solid #2454d8;outline-offset:1px}
.of-hint{font-size:12px;opacity:.65}
.of-row2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media(max-width:600px){.of-row2{grid-template-columns:1fr}}

.of-cell small{display:block;opacity:.65;margin-top:3px}
.of-actions{display:flex;flex-wrap:wrap;gap:6px}
.of-inline{display:inline}
.of-warn{color:#d97706;font-weight:700}

.of-pill{display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700;background:rgba(127,127,127,.12)}
.of-pill.is-good{background:rgba(20,184,123,.16);color:#0b6b48}
.of-pill.is-bad{background:rgba(210,75,75,.16);color:#a02727}
.of-pill.is-warn{background:rgba(230,162,24,.18);color:#8a5a00}
.of-pill.is-info{background:rgba(36,84,216,.14);color:#2454d8}
[data-theme="dark"] .of-pill.is-good{color:#6ee7b7}
[data-theme="dark"] .of-pill.is-bad{color:#fca5a5}
[data-theme="dark"] .of-pill.is-warn{color:#fcd34d}
[data-theme="dark"] .of-pill.is-info{color:#93b4ff}

.of-programmes{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:14px;margin-bottom:22px}
.of-programme{padding:16px;border-radius:14px;border:1px solid rgba(127,127,127,.22);background:rgba(127,127,127,.06)}
.of-programme strong{display:block;margin-bottom:10px}
.of-programme dl{display:grid;grid-template-columns:1fr auto;gap:4px 12px;margin:0;font-size:13px}
.of-programme dt{opacity:.7}
.of-programme dd{margin:0;text-align:right;font-weight:700}

.of-modal{position:fixed;inset:0;z-index:10000;display:none;align-items:center;justify-content:center;padding:24px}
.of-modal.is-open{display:flex}
.of-modal__backdrop{position:absolute;inset:0;background:rgba(15,23,42,.70);backdrop-filter:blur(5px)}
.of-modal__dialog{position:relative;z-index:1;width:min(620px,100%);max-height:90vh;display:flex;flex-direction:column;overflow:hidden;border-radius:22px;background:#fff;color:#172b4d;box-shadow:0 25px 80px rgba(0,0,0,.28)}
.of-modal__header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:22px 24px 16px;border-bottom:1px solid rgba(127,127,127,.16)}
.of-modal__header h3{margin:4px 0 2px}
.of-modal__header p{margin:0;opacity:.7}
.of-modal__close{width:40px;height:40px;flex:0 0 40px;display:grid;place-items:center;border:0;border-radius:50%;cursor:pointer;background:rgba(15,23,42,.07);color:inherit}
.of-modal__body{padding:18px 24px;overflow-y:auto}
.of-modal__footer{display:flex;justify-content:flex-end;gap:10px;padding:14px 24px;border-top:1px solid rgba(127,127,127,.16)}
body.of-modal-open{overflow:hidden}
html[data-theme="dark"] .of-modal__dialog,body.dark-mode .of-modal__dialog{background:#111827;color:#f8fafc}
html[data-theme="dark"] .of-modal__close,body.dark-mode .of-modal__close{background:rgba(255,255,255,.08);color:#f8fafc}
html[data-theme="dark"] .of-select option,body.dark-mode .of-select option{background:#111827;color:#f8fafc}
@media(max-width:768px){.of-modal{padding:12px}.of-modal__dialog{border-radius:16px}}

.of-overview{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:36px}
.of-overview h4{margin:0 0 4px;font-size:16px}
.of-overview .of-hint{display:block;margin-bottom:16px}

.of-bars{display:flex;flex-direction:column;gap:6px}
.of-bar{display:grid;grid-template-columns:140px 1fr 40px;gap:12px;align-items:center;font-size:13px;padding:3px 0}
.of-bar__label{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.of-bar__track{height:10px;border-radius:999px;background:rgba(127,127,127,.14);overflow:hidden}
.of-bar:hover .of-bar__track{background:rgba(127,127,127,.24)}
.of-bar__fill{display:block;height:100%;border-radius:999px;background:#9db4ee}
.of-bar__fill.is-early{background:#9db4ee}
.of-bar__fill.is-screen{background:#6f93f0}
.of-bar__fill.is-interview{background:#2454d8}
.of-bar__fill.is-decision{background:#1b3fa8}
.of-bar__fill.is-offer{background:#0e9aa7}
.of-bar__fill.is-good{background:#12a06c}
.of-bar__fill.is-bad{background:#d24b4b}
.of-bar__fill.is-warn{background:#e6a218}
.of-bar__fill.is-other{background:rgba(127,127,127,.5)}
.of-bar__count{text-align:right;font-variant-numeric:tabular-nums}
@media(max-width:600px){.of-bar{grid-template-columns:110px 1fr 36px}}

.of-funnel{display:flex;flex-direction:column;gap:12px}
.of-funnel__step{display:flex;flex-direction:column;align-items:center;gap:4px}
.of-funnel__bar{display:flex;align-items:center;justify-content:center;box-sizing:border-box;min-height:38px;border-radius:10px;color:#fff;font-size:15px;font-weight:700;font-variant-numeric:tabular-nums}
.of-funnel__bar.is-step0{background:#6f93f0}
.of-funnel__bar.is-step1{background:#2f5fd9}
.of-funnel__bar.is-step2{background:#1b3fa8}
.of-funnel__bar.is-good{background:#12a06c}
.of-funnel__bar.is-empty{background:rgba(127,127,127,.22);color:inherit;opacity:.8}
.of-funnel__label{font-size:12px;opacity:.85}
.of-funnel__label em{font-style:normal;opacity:.65;margin-left:6px}

.of-timeline{list-style:none;margin:4px 0 0;padding:0 0 0 10px;border-left:2px solid rgba(127,127,127,.28)}
.of-timeline li{position:relative;padding:0 0 18px 18px}
.of-timeline li:last-child{padding-bottom:2px}
.of-timeline li::before{content:"";position:absolute;left:-16px;top:3px;width:10px;height:10px;border-radius:50%;background:#2454d8;box-shadow:0 0 0 3px #fff}
html[data-theme="dark"] .of-timeline li::before,body.dark-mode .of-timeline li::before{box-shadow:0 0 0 3px #111827}
.of-timeline li.is-draft::before{background:rgba(127,127,127,.75)}
.of-timeline li.is-sent::before{background:#2454d8}
.of-timeline li.is-accepted::before,.of-timeline li.is-placement::before{background:#12a06c}
.of-timeline li.is-declined::before,.of-timeline li.is-withdrawn::before{background:#d24b4b}
.of-timeline li.is-status::before{left:-15px;top:5px;width:8px;height:8px;background:rgba(127,127,127,.6)}
.of-timeline li time{display:block;font-size:12px;opacity:.65;margin-bottom:1px}
.of-timeline li strong{display:block;font-size:14px}
.of-timeline li.is-status strong{font-weight:500}
.of-timeline li span{display:block;font-size:13px;opacity:.75;margin-top:2px}

/* Neatness pass */
.of-actions{align-items:center}
.of-actions .rc-btn{white-space:nowrap}
#ofTimelineModal .of-modal__dialog{width:min(480px,100%)}
#ofTimelineModal .of-modal__body{min-height:120px}
.of-overview > div{min-width:0}
.of-funnel{max-width:460px;margin:0 auto}
.of-funnel__bar{transition:width .2s ease}
@media(max-width:600px){.of-funnel{max-width:none}}
</style>

<!-- ================================================================
     HERO
================================================================ -->

<section class="rc-hero">
    <div>
        <span class="rc-eyebrow"><i class="fas fa-file-signature"></i> Offer management</span>
        <h2>Offers &amp; Placements</h2>
        <p>
            Prepare offers for selected candidates, track their responses and
            monitor successful placements into programmes.
        </p>
        <div class="rc-hero__actions">
            <?php if ($schemaReady): ?>
                <button type="button" class="rc-btn rc-btn--light" data-of-open="ofCreateModal">
                    <i class="fas fa-plus"></i> Prepare offer
                </button>
            <?php endif; ?>
            <a class="rc-btn rc-btn--glass" href="<?= url('recruiter/interviews.php') ?>">
                <i class="fas fa-calendar-check"></i> Interviews
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
            <strong>Offer tables could not be created</strong>
            <span>
                The <code>recruiter_offers</code> table could not be created automatically. Please check database permissions, then reload this page.
                <?= $schemaError ? '(' . e($schemaError) . ')' : '' ?>
            </span>
        </div>
    </div></div>

<?php else: ?>

<!-- ================================================================
     STATS
================================================================ -->

<section class="rc-stats">
    <article><i class="fas fa-pen-ruler"></i><strong><?= number_format($stats['draft']) ?></strong><span>Drafts</span></article>
    <article><i class="fas fa-paper-plane"></i><strong><?= number_format($stats['awaiting']) ?></strong><span>Awaiting response</span></article>
    <article><i class="fas fa-clock"></i><strong><?= number_format($stats['overdue']) ?></strong><span>Past deadline</span></article>
    <article><i class="fas fa-circle-check"></i><strong><?= number_format($stats['accepted']) ?></strong><span>Accepted</span></article>
    <article><i class="fas fa-circle-xmark"></i><strong><?= number_format($stats['declined']) ?></strong><span>Declined</span></article>
    <article><i class="fas fa-user-check"></i><strong><?= number_format($stats['placed']) ?></strong><span>Placed</span></article>
    <article><i class="fas fa-percent"></i><strong><?= $acceptRate !== null ? number_format($acceptRate) . '%' : '—' ?></strong><span>Acceptance rate</span></article>
</section>

<!-- ================================================================
     PIPELINE OVERVIEW
================================================================ -->

<?php
$ofStageTone = [
    'submitted' => 'early', 'under_review' => 'early',
    'shortlisted' => 'screen', 'assessment' => 'screen',
    'interview_required' => 'interview', 'interview_scheduled' => 'interview', 'interview_completed' => 'interview',
    'selected' => 'decision', 'waitlisted' => 'warn',
    'offer_sent' => 'offer', 'offer_accepted' => 'good', 'offer_declined' => 'bad',
];

$ofStageRows = [];
foreach (OF_PIPELINE_STAGES as $st) $ofStageRows[$st] = (int)($stageCounts[$st] ?? 0);
foreach ($stageCounts as $st => $c) {
    if ((string)$st !== '' && !isset($ofStageRows[(string)$st])) $ofStageRows[(string)$st] = (int)$c;
}
$ofStageMax   = max(1, max($ofStageRows ?: [0]));
$ofStageTotal = array_sum($ofStageRows);

$ofFunnelRows = [
    'Offers prepared'   => $offerFunnel['prepared'],
    'Offers sent'       => $offerFunnel['sent'],
    'Response recorded' => $offerFunnel['responded'],
    'Accepted'          => $offerFunnel['accepted'],
    'Placed'            => $offerFunnel['placed'],
];
$ofFunnelMax = max(1, max($offerFunnel));
?>

<section class="rc-card rc-space" id="pipeline">

    <div class="rc-card__header">
        <div>
            <span class="rc-eyebrow">Pipeline</span>
            <h3>Pipeline overview</h3>
            <p>Where applications currently sit, and how your offers move from draft to placement.</p>
        </div>
    </div>

    <div class="rc-card__body">
        <div class="of-overview">

            <div>
                <h4>Applications by current stage</h4>
                <span class="of-hint"><?= number_format($ofStageTotal) ?> applications. Current statuses, not a history of who passed through each stage.</span>
                <div class="of-bars">
                    <?php foreach ($ofStageRows as $st => $cnt):
                        $pct  = $cnt > 0 ? max(2, (int)round(($cnt / $ofStageMax) * 100)) : 0;
                        $tone = $ofStageTone[(string)$st] ?? 'other';
                    ?>
                        <div class="of-bar" title="<?= e(rc_label((string)$st)) ?>: <?= number_format($cnt) ?>">
                            <span class="of-bar__label"><?= e(rc_label((string)$st)) ?></span>
                            <span class="of-bar__track"><span class="of-bar__fill is-<?= e($tone) ?>" style="width:<?= $pct ?>%"></span></span>
                            <strong class="of-bar__count"><?= number_format($cnt) ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div>
                <h4>Your offer funnel</h4>
                <span class="of-hint">Draft to placement, for the offers you have prepared.</span>
                <div class="of-funnel">
                    <?php
                    $ofPrev = null;
                    $ofIdx  = 0;
                    foreach ($ofFunnelRows as $label => $cnt):
                        $pct  = $cnt > 0 ? max(16, (int)round(($cnt / $ofFunnelMax) * 100)) : 5;
                        $conv = ($ofPrev !== null && $ofPrev > 0) ? (int)round(($cnt / $ofPrev) * 100) : null;
                        $tone = in_array($label, ['Accepted', 'Placed'], true) ? 'good' : 'step' . min($ofIdx, 2);
                        $ofPrev = $cnt;
                        $ofIdx++;
                    ?>
                        <div class="of-funnel__step">
                            <div class="of-funnel__bar is-<?= $tone ?><?= $cnt === 0 ? ' is-empty' : '' ?>" style="width:<?= $pct ?>%"><?= $cnt > 0 ? number_format($cnt) : '0' ?></div>
                            <span class="of-funnel__label"><?= e($label) ?><?php if ($conv !== null): ?> <em><?= $conv ?>% of previous step</em><?php endif; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- ================================================================
     OFFERS
================================================================ -->

<section class="rc-card rc-space" id="offers">

    <div class="rc-card__header">
        <div>
            <span class="rc-eyebrow">Offers</span>
            <h3>Your offers</h3>
            <p>Prepare drafts, send offers, record the candidate's response and withdraw offers if needed.</p>
        </div>
    </div>

    <div class="rc-card__body">

        <div class="of-toolbar">
            <nav class="of-tabs" aria-label="Filter offers">
                <?php
                $tabDefs = [
                    'all' => 'All', 'draft' => 'Drafts', 'awaiting' => 'Awaiting response',
                    'accepted' => 'Accepted', 'closed' => 'Declined / withdrawn',
                ];
                foreach ($tabDefs as $key => $label):
                    $href = url('recruiter/offers.php?tab=' . $key . ($q !== '' ? '&q=' . urlencode($q) : ''));
                ?>
                    <a class="of-tab <?= $tab === $key ? 'is-active' : '' ?>" href="<?= $href ?>"><?= e($label) ?></a>
                <?php endforeach; ?>
            </nav>

            <form class="of-search" method="get" action="<?= $action ?>">
                <input type="hidden" name="tab" value="<?= e($tab) ?>">
                <input class="of-input" type="search" name="q" value="<?= e($q) ?>"
                       placeholder="Name, email, position, programme" aria-label="Search offers">
                <button class="rc-btn rc-btn--secondary rc-btn--small" type="submit">
                    <i class="fas fa-magnifying-glass"></i> Search
                </button>
            </form>
        </div>

        <?php if (!$offers): ?>

            <div class="rc-empty">
                <i class="fas fa-file-signature"></i>
                <strong>No offers found</strong>
                <span>
                    <?= ($tab !== 'all' || $q !== '')
                        ? 'Nothing matches the current filter.'
                        : 'Prepare your first offer using the button above.' ?>
                </span>
            </div>

        <?php else: ?>

            <div class="rc-table-wrap">
                <table class="rc-table">
                    <thead>
                        <tr>
                            <th>Candidate</th>
                            <th>Position &amp; programme</th>
                            <th>Dates</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($offers as $of):
                        $id       = (int)$of['id'];
                        $candName = trim((string)$of['first_name'] . ' ' . (string)$of['last_name']);
                        $status   = (string)$of['status'];
                        $overdue  = $status === 'sent' && $of['expires_on'] !== null && (string)$of['expires_on'] < $today;

                        $pillClass = $status === 'accepted' ? 'is-good'
                                   : (in_array($status, ['declined', 'withdrawn'], true) ? 'is-bad'
                                   : ($status === 'sent' ? 'is-info' : ''));
                    ?>
                        <tr>
                            <td class="of-cell">
                                <strong><?= e($candName !== '' ? $candName : 'Candidate #' . (int)$of['candidate_id']) ?></strong>
                                <?php if ((string)$of['candidate_email'] !== ''): ?>
                                    <small><?= e((string)$of['candidate_email']) ?></small>
                                <?php endif; ?>
                                <small>
                                    <?= $of['application_reference'] ? e((string)$of['application_reference']) : 'Application #' . (int)$of['application_id'] ?>
                                    <?= $of['application_status'] ? ' · Application: ' . e(rc_label((string)$of['application_status'])) : '' ?>
                                </small>
                            </td>

                            <td class="of-cell">
                                <strong><?= e((string)$of['position_title']) ?></strong>
                                <small><?= e((string)$of['programme_name']) ?></small>
                                <?php if ((string)$of['compensation'] !== ''): ?>
                                    <small><?= e((string)$of['compensation']) ?></small>
                                <?php endif; ?>
                                <?php if ((string)$of['location'] !== ''): ?>
                                    <small><?= e((string)$of['location']) ?></small>
                                <?php endif; ?>
                            </td>

                            <td class="of-cell">
                                Starts <?= e(of_fmt((string)$of['start_date'])) ?>
                                <?php if ($of['end_date']): ?>
                                    <small>Ends <?= e(of_fmt((string)$of['end_date'])) ?></small>
                                <?php endif; ?>
                                <?php if ($of['expires_on'] && in_array($status, ['draft', 'sent'], true)): ?>
                                    <small class="<?= $overdue ? 'of-warn' : '' ?>">
                                        Respond by <?= e(of_fmt((string)$of['expires_on'])) ?><?= $overdue ? ' — overdue' : '' ?>
                                    </small>
                                <?php endif; ?>
                            </td>

                            <td class="of-cell">
                                <span class="of-pill <?= $pillClass ?>"><?= e(OF_STATUS_LABELS[$status] ?? rc_label($status)) ?></span>
                                <?php if ($of['sent_at']): ?>
                                    <small>Sent <?= e(of_fmt((string)$of['sent_at'])) ?></small>
                                <?php endif; ?>
                                <?php if ($of['responded_at']): ?>
                                    <small>Responded <?= e(of_fmt((string)$of['responded_at'])) ?></small>
                                <?php endif; ?>
                                <?php if ($status === 'accepted'): ?>
                                    <small><?= e(OF_PLACEMENT_LABELS[(string)$of['placement_status']] ?? '') ?></small>
                                <?php endif; ?>
                                <?php if ((string)$of['response_note'] !== ''): ?>
                                    <small><?= e((string)$of['response_note']) ?></small>
                                <?php endif; ?>
                                <?php if ($status === 'withdrawn' && (string)$of['withdraw_reason'] !== ''): ?>
                                    <small><?= e((string)$of['withdraw_reason']) ?></small>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="of-actions">

                                    <button type="button" class="rc-btn rc-btn--secondary rc-btn--small"
                                            data-of-open="ofTimelineModal"
                                            data-id="<?= $id ?>"
                                            data-name="<?= e($candName) ?>">
                                        <i class="fas fa-timeline"></i> Timeline
                                    </button>

                                    <?php if ($status === 'draft'): ?>
                                        <button type="button" class="rc-btn rc-btn--secondary rc-btn--small"
                                                data-of-open="ofEditModal"
                                                data-id="<?= $id ?>"
                                                data-name="<?= e($candName) ?>"
                                                data-position="<?= e((string)$of['position_title']) ?>"
                                                data-programme="<?= e((string)$of['programme_name']) ?>"
                                                data-start="<?= e((string)$of['start_date']) ?>"
                                                data-end="<?= e((string)($of['end_date'] ?? '')) ?>"
                                                data-comp="<?= e((string)$of['compensation']) ?>"
                                                data-location="<?= e((string)$of['location']) ?>"
                                                data-terms="<?= e((string)$of['terms']) ?>"
                                                data-expires="<?= e((string)($of['expires_on'] ?? '')) ?>">
                                            <i class="fas fa-pen"></i> Edit
                                        </button>

                                        <form class="of-inline" method="post" action="<?= $action ?>"
                                              onsubmit="return confirm('Send this offer to the candidate now?');">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                            <input type="hidden" name="action" value="send">
                                            <input type="hidden" name="offer_id" value="<?= $id ?>">
                                            <button type="submit" class="rc-btn rc-btn--primary rc-btn--small">
                                                <i class="fas fa-paper-plane"></i> Send offer
                                            </button>
                                        </form>

                                        <form class="of-inline" method="post" action="<?= $action ?>"
                                              onsubmit="return confirm('Delete this draft?');">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="offer_id" value="<?= $id ?>">
                                            <button type="submit" class="rc-btn rc-btn--secondary rc-btn--small">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($status === 'sent'): ?>
                                        <button type="button" class="rc-btn rc-btn--primary rc-btn--small"
                                                data-of-open="ofRespondModal"
                                                data-id="<?= $id ?>"
                                                data-name="<?= e($candName) ?>">
                                            <i class="fas fa-reply"></i> Record response
                                        </button>
                                        <button type="button" class="rc-btn rc-btn--secondary rc-btn--small"
                                                data-of-open="ofExtendModal"
                                                data-id="<?= $id ?>"
                                                data-name="<?= e($candName) ?>"
                                                data-expires="<?= e((string)($of['expires_on'] ?? '')) ?>"
                                                data-max="<?= e((string)$of['start_date']) ?>">
                                            <i class="fas fa-calendar-plus"></i> Deadline
                                        </button>
                                        <button type="button" class="rc-btn rc-btn--secondary rc-btn--small"
                                                data-of-open="ofWithdrawModal"
                                                data-id="<?= $id ?>"
                                                data-name="<?= e($candName) ?>">
                                            <i class="fas fa-ban"></i> Withdraw
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($status === 'accepted'): ?>
                                        <button type="button" class="rc-btn rc-btn--primary rc-btn--small"
                                                data-of-open="ofPlacementModal"
                                                data-id="<?= $id ?>"
                                                data-name="<?= e($candName) ?>"
                                                data-placement="<?= e((string)$of['placement_status']) ?>"
                                                data-note="<?= e((string)$of['placement_note']) ?>">
                                            <i class="fas fa-user-check"></i> Placement
                                        </button>
                                    <?php endif; ?>

                                    <?php if (isset($consentSet[(int)$of['candidate_id']])): ?>
                                        <a class="rc-btn rc-btn--secondary rc-btn--small"
                                           href="<?= url('recruiter/candidate_view.php?id=' . (int)$of['candidate_id']) ?>">
                                            <i class="fas fa-user"></i> Profile
                                        </a>
                                    <?php endif; ?>
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
     PLACEMENTS
================================================================ -->

<section class="rc-card rc-space" id="placements">

    <div class="rc-card__header">
        <div>
            <span class="rc-eyebrow">Placements</span>
            <h3>Placements into programmes</h3>
            <p>Candidates who accepted an offer, grouped by programme. Mark them as placed once they have started.</p>
        </div>
    </div>

    <div class="rc-card__body">

        <?php if (!$placements): ?>

            <div class="rc-empty">
                <i class="fas fa-user-check"></i>
                <strong>No accepted offers yet</strong>
                <span>Candidates appear here once they accept an offer.</span>
            </div>

        <?php else: ?>

            <?php if ($programmes): ?>
                <div class="of-programmes">
                    <?php foreach ($programmes as $pg): ?>
                        <div class="of-programme">
                            <strong><?= e((string)$pg['programme_name']) ?></strong>
                            <dl>
                                <dt>Accepted offers</dt><dd><?= number_format((int)$pg['accepted']) ?></dd>
                                <dt>Placed</dt><dd><?= number_format((int)$pg['placed']) ?></dd>
                                <dt>Awaiting start</dt><dd><?= number_format((int)$pg['awaiting']) ?></dd>
                                <dt>Did not start</dt><dd><?= number_format((int)$pg['did_not_start']) ?></dd>
                            </dl>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="rc-table-wrap">
                <table class="rc-table">
                    <thead>
                        <tr>
                            <th>Candidate</th>
                            <th>Programme</th>
                            <th>Start date</th>
                            <th>Placement</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($placements as $pl):
                        $plId   = (int)$pl['id'];
                        $plName = trim((string)$pl['first_name'] . ' ' . (string)$pl['last_name']);
                        $plSt   = (string)$pl['placement_status'];
                        $plPill = $plSt === 'placed' ? 'is-good' : ($plSt === 'did_not_start' ? 'is-bad' : 'is-warn');
                        $due    = $plSt === 'awaiting_start' && (string)$pl['start_date'] <= $today;
                    ?>
                        <tr>
                            <td class="of-cell">
                                <strong><?= e($plName !== '' ? $plName : 'Candidate #' . (int)$pl['candidate_id']) ?></strong>
                                <small><?= e((string)$pl['position_title']) ?></small>
                            </td>
                            <td class="of-cell"><?= e((string)$pl['programme_name']) ?></td>
                            <td class="of-cell">
                                <?= e(of_fmt((string)$pl['start_date'])) ?>
                                <?php if ($due): ?><small class="of-warn">Start date reached — confirm placement</small><?php endif; ?>
                            </td>
                            <td class="of-cell">
                                <span class="of-pill <?= $plPill ?>"><?= e(OF_PLACEMENT_LABELS[$plSt] ?? rc_label($plSt)) ?></span>
                                <?php if ($pl['placement_recorded_at']): ?>
                                    <small><?= e(of_fmt((string)$pl['placement_recorded_at'])) ?></small>
                                <?php endif; ?>
                                <?php if ((string)$pl['placement_note'] !== ''): ?>
                                    <small><?= e((string)$pl['placement_note']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="rc-btn rc-btn--secondary rc-btn--small"
                                        data-of-open="ofPlacementModal"
                                        data-id="<?= $plId ?>"
                                        data-name="<?= e($plName) ?>"
                                        data-placement="<?= e($plSt) ?>"
                                        data-note="<?= e((string)$pl['placement_note']) ?>">
                                    <i class="fas fa-user-check"></i> Update
                                </button>
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
     MODAL: PREPARE OFFER
================================================================ -->

<div class="of-modal" id="ofCreateModal" aria-hidden="true">
    <div class="of-modal__backdrop" data-of-close></div>
    <form class="of-modal__dialog" method="post" action="<?= $action ?>"
          role="dialog" aria-modal="true" aria-labelledby="ofCreateTitle">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="create">

        <div class="of-modal__header">
            <div>
                <span class="rc-eyebrow">New offer</span>
                <h3 id="ofCreateTitle">Prepare offer</h3>
                <p>Saved as a draft. Nothing is sent to the candidate until you send it.</p>
            </div>
            <button type="button" class="of-modal__close" data-of-close aria-label="Close"><i class="fas fa-xmark"></i></button>
        </div>

        <div class="of-modal__body">
            <div class="of-field">
                <label for="ofApplication">Candidate application</label>
                <select class="of-select" id="ofApplication" name="application_id" required>
                    <option value="">Select an application…</option>
                    <?php foreach ($applications as $a):
                        $an = trim((string)$a['first_name'] . ' ' . (string)$a['last_name']);
                    ?>
                        <option value="<?= (int)$a['id'] ?>"
                                data-title="<?= e((string)$a['opportunity_title']) ?>"
                                <?= $prefillApplication === (int)$a['id'] ? 'selected' : '' ?>>
                            <?= e(($an !== '' ? $an : 'Candidate') . ' — ' . ((string)$a['opportunity_title'] ?: 'Opportunity') . ' (' . ((string)$a['application_reference'] ?: '#' . (int)$a['id']) . ' · ' . rc_label((string)$a['status']) . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$applications): ?>
                    <span class="of-hint">No applications are ready for an offer. Submitted applications appear here (from submitted up to selected or waitlisted) unless they are rejected, withdrawn, on hold or already have an active offer.</span>
                <?php else: ?>
                    <span class="of-hint">Only applications that are selected or have a completed interview, and have no active offer, are listed.</span>
                <?php endif; ?>
            </div>

            <?php of_render_fields('ofC'); ?>
        </div>

        <div class="of-modal__footer">
            <button type="button" class="rc-btn rc-btn--secondary" data-of-close>Close</button>
            <button type="submit" class="rc-btn rc-btn--primary"><i class="fas fa-floppy-disk"></i> Save draft</button>
        </div>
    </form>
</div>

<!-- ================================================================
     MODAL: EDIT DRAFT
================================================================ -->

<div class="of-modal" id="ofEditModal" aria-hidden="true">
    <div class="of-modal__backdrop" data-of-close></div>
    <form class="of-modal__dialog" method="post" action="<?= $action ?>"
          role="dialog" aria-modal="true" aria-labelledby="ofEditTitle">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="offer_id" data-fill="id">

        <div class="of-modal__header">
            <div>
                <span class="rc-eyebrow">Draft offer</span>
                <h3 id="ofEditTitle">Edit offer</h3>
                <p data-fill-text="name"></p>
            </div>
            <button type="button" class="of-modal__close" data-of-close aria-label="Close"><i class="fas fa-xmark"></i></button>
        </div>

        <div class="of-modal__body">
            <?php of_render_fields('ofE'); ?>
        </div>

        <div class="of-modal__footer">
            <button type="button" class="rc-btn rc-btn--secondary" data-of-close>Close</button>
            <button type="submit" class="rc-btn rc-btn--primary">Save changes</button>
        </div>
    </form>
</div>

<!-- ================================================================
     MODAL: RECORD RESPONSE
================================================================ -->

<div class="of-modal" id="ofRespondModal" aria-hidden="true">
    <div class="of-modal__backdrop" data-of-close></div>
    <form class="of-modal__dialog" method="post" action="<?= $action ?>"
          role="dialog" aria-modal="true" aria-labelledby="ofRespondTitle">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="respond">
        <input type="hidden" name="offer_id" data-fill="id">

        <div class="of-modal__header">
            <div>
                <span class="rc-eyebrow">Candidate response</span>
                <h3 id="ofRespondTitle">Record response</h3>
                <p data-fill-text="name"></p>
            </div>
            <button type="button" class="of-modal__close" data-of-close aria-label="Close"><i class="fas fa-xmark"></i></button>
        </div>

        <div class="of-modal__body">
            <div class="of-field">
                <label for="ofResult">Response</label>
                <select class="of-select" id="ofResult" name="result" required>
                    <option value="">Select…</option>
                    <option value="accepted">Accepted the offer</option>
                    <option value="declined">Declined the offer</option>
                </select>
            </div>
            <div class="of-field">
                <label for="ofResponseNote">Note <small>(optional)</small></label>
                <textarea class="of-textarea" id="ofResponseNote" name="response_note" maxlength="500"
                          placeholder="How the response was received, or the reason given."></textarea>
            </div>
            <p class="of-hint">Accepted offers move to placement tracking. The application status is updated automatically.</p>
        </div>

        <div class="of-modal__footer">
            <button type="button" class="rc-btn rc-btn--secondary" data-of-close>Close</button>
            <button type="submit" class="rc-btn rc-btn--primary">Save response</button>
        </div>
    </form>
</div>

<!-- ================================================================
     MODAL: DEADLINE
================================================================ -->

<div class="of-modal" id="ofExtendModal" aria-hidden="true">
    <div class="of-modal__backdrop" data-of-close></div>
    <form class="of-modal__dialog" method="post" action="<?= $action ?>"
          role="dialog" aria-modal="true" aria-labelledby="ofExtendTitle">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="extend">
        <input type="hidden" name="offer_id" data-fill="id">

        <div class="of-modal__header">
            <div>
                <span class="rc-eyebrow">Manage offer</span>
                <h3 id="ofExtendTitle">Response deadline</h3>
                <p data-fill-text="name"></p>
            </div>
            <button type="button" class="of-modal__close" data-of-close aria-label="Close"><i class="fas fa-xmark"></i></button>
        </div>

        <div class="of-modal__body">
            <div class="of-field">
                <label for="ofExtendDate">Respond by</label>
                <input class="of-input" type="date" id="ofExtendDate" name="expires_on" data-fill="expires"
                       min="<?= e($today) ?>" required>
                <span class="of-hint">Cannot be after the programme start date. The candidate is notified of the new deadline.</span>
            </div>
        </div>

        <div class="of-modal__footer">
            <button type="button" class="rc-btn rc-btn--secondary" data-of-close>Close</button>
            <button type="submit" class="rc-btn rc-btn--primary">Save deadline</button>
        </div>
    </form>
</div>

<!-- ================================================================
     MODAL: WITHDRAW
================================================================ -->

<div class="of-modal" id="ofWithdrawModal" aria-hidden="true">
    <div class="of-modal__backdrop" data-of-close></div>
    <form class="of-modal__dialog" method="post" action="<?= $action ?>"
          role="dialog" aria-modal="true" aria-labelledby="ofWithdrawTitle">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="withdraw">
        <input type="hidden" name="offer_id" data-fill="id">

        <div class="of-modal__header">
            <div>
                <span class="rc-eyebrow">Manage offer</span>
                <h3 id="ofWithdrawTitle">Withdraw offer</h3>
                <p data-fill-text="name"></p>
            </div>
            <button type="button" class="of-modal__close" data-of-close aria-label="Close"><i class="fas fa-xmark"></i></button>
        </div>

        <div class="of-modal__body">
            <div class="of-field">
                <label for="ofWithdrawReason">Reason for withdrawing</label>
                <input class="of-input" id="ofWithdrawReason" name="reason" maxlength="255" required>
                <span class="of-hint">The candidate is notified that the offer has been withdrawn. The reason is internal.</span>
            </div>
        </div>

        <div class="of-modal__footer">
            <button type="button" class="rc-btn rc-btn--secondary" data-of-close>Keep offer</button>
            <button type="submit" class="rc-btn rc-btn--primary">Withdraw offer</button>
        </div>
    </form>
</div>

<!-- ================================================================
     MODAL: PLACEMENT
================================================================ -->

<div class="of-modal" id="ofPlacementModal" aria-hidden="true">
    <div class="of-modal__backdrop" data-of-close></div>
    <form class="of-modal__dialog" method="post" action="<?= $action ?>"
          role="dialog" aria-modal="true" aria-labelledby="ofPlacementTitle">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="placement">
        <input type="hidden" name="offer_id" data-fill="id">

        <div class="of-modal__header">
            <div>
                <span class="rc-eyebrow">Placement</span>
                <h3 id="ofPlacementTitle">Update placement</h3>
                <p data-fill-text="name"></p>
            </div>
            <button type="button" class="of-modal__close" data-of-close aria-label="Close"><i class="fas fa-xmark"></i></button>
        </div>

        <div class="of-modal__body">
            <div class="of-field">
                <label for="ofPlacementStatus">Placement status</label>
                <select class="of-select" id="ofPlacementStatus" name="placement_status" data-fill="placement" required>
                    <option value="awaiting_start">Awaiting start</option>
                    <option value="placed">Placed in programme</option>
                    <option value="did_not_start">Did not start</option>
                </select>
                <span class="of-hint">A candidate can only be marked as placed on or after the programme start date.</span>
            </div>
            <div class="of-field">
                <label for="ofPlacementNote">Note <small>(optional)</small></label>
                <textarea class="of-textarea" id="ofPlacementNote" name="placement_note" maxlength="500" data-fill="note"></textarea>
            </div>
        </div>

        <div class="of-modal__footer">
            <button type="button" class="rc-btn rc-btn--secondary" data-of-close>Close</button>
            <button type="submit" class="rc-btn rc-btn--primary">Save placement</button>
        </div>
    </form>
</div>

<!-- ================================================================
     MODAL: OFFER TIMELINE
================================================================ -->

<div class="of-modal" id="ofTimelineModal" aria-hidden="true">
    <div class="of-modal__backdrop" data-of-close></div>
    <div class="of-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="ofTimelineTitle">
        <div class="of-modal__header">
            <div>
                <span class="rc-eyebrow">Offer history</span>
                <h3 id="ofTimelineTitle">Timeline</h3>
                <p data-fill-text="name"></p>
            </div>
            <button type="button" class="of-modal__close" data-of-close aria-label="Close"><i class="fas fa-xmark"></i></button>
        </div>

        <div class="of-modal__body">
            <ol class="of-timeline" id="ofTimelineList"></ol>
        </div>

        <div class="of-modal__footer">
            <button type="button" class="rc-btn rc-btn--secondary" data-of-close>Close</button>
        </div>
    </div>
</div>

<script>
(function () {
    var openModal = null;

    function closeModal() {
        if (!openModal) return;
        openModal.classList.remove('is-open');
        openModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('of-modal-open');
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

    function show(modal) {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('of-modal-open');
        openModal = modal;
        var first = modal.querySelector('input:not([type=hidden]), select, textarea');
        if (first) first.focus();
    }

    document.querySelectorAll('[data-of-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var modal = document.getElementById(btn.getAttribute('data-of-open'));
            if (!modal) return;

            closeModal();
            fill(modal, btn.dataset);

            if (modal.id === 'ofExtendModal') {
                var d = modal.querySelector('input[type=date]');
                if (d && btn.dataset.max) d.max = btn.dataset.max;
            }

            show(modal);
        });
    });

    document.querySelectorAll('[data-of-close]').forEach(function (el) {
        el.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') closeModal();
    });

    // Pre-fill the position title from the chosen application's opportunity.
    var appSel = document.getElementById('ofApplication');
    var posInput = document.getElementById('ofCPosition');
    var posTouched = false;
    if (posInput) posInput.addEventListener('input', function () { posTouched = true; });
    function prefillPosition() {
        if (!appSel || !posInput || posTouched) return;
        var opt = appSel.options[appSel.selectedIndex];
        posInput.value = (opt && opt.getAttribute('data-title')) || '';
    }
    if (appSel) appSel.addEventListener('change', prefillPosition);

    <?php if ($prefillApplication > 0): ?>
    var autoBtn = document.querySelector('[data-of-open="ofCreateModal"]');
    if (autoBtn) { autoBtn.click(); prefillPosition(); }
    <?php endif; ?>
})();
</script>

<script>
(function () {
    var timelines = <?= json_encode(
        $timelines,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    ) ?: '{}' ?>;

    var list = document.getElementById('ofTimelineList');
    if (!list) return;

    function add(parent, tag, text) {
        var el = document.createElement(tag);
        el.textContent = text;
        parent.appendChild(el);
        return el;
    }

    document.querySelectorAll('[data-of-open="ofTimelineModal"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var events = timelines[btn.dataset.id] || [];
            list.textContent = '';

            if (!events.length) {
                add(list, 'li', 'No history recorded for this offer.');
                return;
            }

            events.forEach(function (ev) {
                var li = document.createElement('li');
                li.className = 'is-' + (ev.type || 'status');
                add(li, 'time', ev.when);
                add(li, 'strong', ev.label);
                if (ev.detail) add(li, 'span', ev.detail);
                list.appendChild(li);
            });
        });
    });
})();
</script>

<?php endif; ?>

<?php
require __DIR__ . '/_layout_end.php';
