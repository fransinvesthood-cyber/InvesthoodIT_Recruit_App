<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('recruiter');

$user        = current_user();
$flashes     = render_flashes();
$currentPage = 'engagement_automation';
$pageTitle   = 'Engagement & Follow-Up Automation';

require_once __DIR__ . '/_helpers.php';

$conn = Database::getConnection();

$recruiterId = (int)($user['id'] ?? $user['user_id'] ?? 0);

if ($recruiterId <= 0) {
    http_response_code(403);
    exit('Invalid Recruiter account.');
}

$recruiterName = trim((string)($user['full_name'] ?? $user['fullname'] ?? trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))));
if ($recruiterName === '') {
    $recruiterName = 'Recruiter';
}

require_once __DIR__ . '/_engagement_engine.php';

/* ==================================================================
 | Schema + shared state
 * ==================================================================*/

$schemaError = null;
$schemaReady = ea_ensure_tables($conn, $schemaError);

$hasApps       = rc_has_table($conn, 'applications');
$hasOpps       = rc_has_table($conn, 'opportunities');
$hasInterviews = rc_has_table($conn, 'recruiter_interviews');
$ready         = $schemaReady && $hasApps && $hasOpps;

$now      = date('Y-m-d H:i:s');
$nowLocal = date('Y-m-d\TH:i');

if ($ready) {
    try {
        ea_seed_defaults($conn, $recruiterId);
    } catch (Throwable $t) {
        error_log('engagement_automation.php seed: ' . $t->getMessage());
    }
}

$sessionKey = 'ea_last_run_' . $recruiterId;

/* ==================================================================
 | POST handling (post → redirect → get)
 * ==================================================================*/

if ($ready && $_SERVER['REQUEST_METHOD'] === 'POST') {

    rc_verify_csrf();

    $action    = trim((string)($_POST['action'] ?? ''));
    $returnTab = (string)($_POST['return_tab'] ?? 'due');
    if (!in_array($returnTab, EA_TABS, true)) $returnTab = 'due';

    $anchor   = '';
    $redirect = url('recruiter/engagement_automation.php' . ($returnTab !== 'due' ? '?tab=' . $returnTab : ''));

    try {

        /* -------------------- Run automation now -------------------- */
        if ($action === 'run_now') {

            $result = ea_run_all($conn, $recruiterId, $recruiterName, $hasInterviews);

            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION[$sessionKey] = time();
            }

            rc_activity(
                $conn, $recruiterId, 'engagement_automation_run',
                'Ran engagement automation: ' . $result['created'] . ' new, ' . $result['resolved'] . ' auto-resolved',
                'engagement_automation', null
            );

            rc_flash(
                'success',
                'Automation run complete: ' . ea_plural($result['created'], 'new reminder')
                . ' created, ' . ea_plural($result['resolved'], 'reminder') . ' resolved automatically.'
            );
        }

        /* -------------------- Save a rule -------------------- */
        elseif ($action === 'save_rule') {

            $anchor = '#rules';
            $key    = (string)($_POST['trigger_key'] ?? '');
            $def    = EA_TRIGGERS[$key] ?? null;

            $active    = !empty($_POST['is_active']) ? 1 : 0;
            $threshold = (int)($_POST['threshold_days'] ?? -1);
            $priority  = (string)($_POST['priority'] ?? '');
            $tplId     = (int)($_POST['template_id'] ?? 0);

            $errors = [];

            if (!$def) {
                $errors[] = 'Unknown automation rule.';
            } else {
                if ($threshold < (int)$def['min'] || $threshold > (int)$def['max']) {
                    $errors[] = 'The number of days must be between ' . (int)$def['min'] . ' and ' . (int)$def['max'] . '.';
                }
            }

            if (!isset(EA_PRIORITIES[$priority])) {
                $errors[] = 'Please choose a priority.';
            }

            if ($tplId > 0 && !isset(ea_load_templates($conn, $recruiterId)[$tplId])) {
                $errors[] = 'The selected message template was not found.';
            }

            if ($errors) {
                rc_flash('danger', implode(' ', $errors));
            } else {
                $tplBind = $tplId > 0 ? $tplId : null;

                ea_exec(
                    $conn,
                    'UPDATE ea_automation_rules
                     SET is_active = ?, threshold_days = ?, priority = ?, template_id = ?
                     WHERE recruiter_id = ? AND trigger_key = ?',
                    'iisiis',
                    [$active, $threshold, $priority, $tplBind, $recruiterId, $key]
                );

                rc_activity(
                    $conn, $recruiterId, 'engagement_rule_saved',
                    'Updated automation rule "' . $key . '" (' . ($active ? 'on' : 'off') . ', ' . $threshold . ' days)',
                    'engagement_automation', null
                );

                rc_flash('success', 'Rule "' . $def['label'] . '" saved.');
            }
        }

        /* -------------------- Save a template -------------------- */
        elseif ($action === 'save_template') {

            $anchor  = '#templates';
            $id      = (int)($_POST['template_id'] ?? 0);
            $name    = trim((string)($_POST['name'] ?? ''));
            $subject = trim((string)($_POST['subject'] ?? ''));
            $body    = trim((string)($_POST['body'] ?? ''));

            $errors = [];

            if ($name === '')                 $errors[] = 'Please give the template a name.';
            if (mb_strlen($name) > 100)       $errors[] = 'The template name is too long (100 characters max).';
            if (mb_strlen($subject) > 190)    $errors[] = 'The subject is too long (190 characters max).';
            if ($body === '')                 $errors[] = 'Please write the message body.';
            if (mb_strlen($body) > 3000)      $errors[] = 'The message body is too long (3000 characters max).';

            $existing = ea_load_templates($conn, $recruiterId);

            if ($id > 0 && !isset($existing[$id])) {
                $errors[] = 'That template was not found.';
            }
            if ($id === 0 && count($existing) >= 30) {
                $errors[] = 'A maximum of 30 templates can be stored.';
            }

            if ($errors) {
                rc_flash('danger', implode(' ', $errors));
            } else {
                if ($id > 0) {
                    ea_exec(
                        $conn,
                        'UPDATE ea_message_templates SET name = ?, subject = ?, body = ? WHERE id = ? AND recruiter_id = ?',
                        'sssii',
                        [$name, $subject, $body, $id, $recruiterId]
                    );
                    rc_flash('success', 'Template updated.');
                } else {
                    ea_exec(
                        $conn,
                        'INSERT INTO ea_message_templates (recruiter_id, name, subject, body) VALUES (?, ?, ?, ?)',
                        'isss',
                        [$recruiterId, $name, $subject, $body]
                    );
                    rc_flash('success', 'Template created.');
                }

                rc_activity(
                    $conn, $recruiterId, 'engagement_template_saved',
                    'Saved message template "' . mb_substr($name, 0, 60) . '"',
                    'engagement_automation', $id > 0 ? $id : null
                );
            }
        }

        /* -------------------- Delete a template -------------------- */
        elseif ($action === 'delete_template') {

            $anchor = '#templates';
            $id     = (int)($_POST['template_id'] ?? 0);

            $existing = ea_load_templates($conn, $recruiterId);

            if ($id <= 0 || !isset($existing[$id])) {
                rc_flash('danger', 'That template was not found.');
            } else {
                ea_exec(
                    $conn,
                    'UPDATE ea_automation_rules SET template_id = NULL WHERE recruiter_id = ? AND template_id = ?',
                    'ii',
                    [$recruiterId, $id]
                );
                ea_exec(
                    $conn,
                    'DELETE FROM ea_message_templates WHERE id = ? AND recruiter_id = ?',
                    'ii',
                    [$id, $recruiterId]
                );

                rc_activity(
                    $conn, $recruiterId, 'engagement_template_deleted',
                    'Deleted message template "' . mb_substr((string)$existing[$id]['name'], 0, 60) . '"',
                    'engagement_automation', $id
                );
                rc_flash('success', 'Template deleted. Rules that used it now send no suggested message.');
            }
        }

        /* -------------------- Add a manual reminder -------------------- */
        elseif ($action === 'add_manual') {

            $candidateId = (int)($_POST['candidate_id'] ?? 0);
            $title       = trim((string)($_POST['title'] ?? ''));
            $note        = trim((string)($_POST['note'] ?? ''));
            $remindAt    = ea_parse_datetime((string)($_POST['remind_at'] ?? ''));
            $priority    = (string)($_POST['priority'] ?? 'normal');

            $errors = [];

            $cand = $candidateId > 0 ? ea_candidate_list($conn, $candidateId) : [];

            if (!$cand)                          $errors[] = 'Please choose a valid candidate.';
            if ($title === '')                   $errors[] = 'Please enter a reminder title.';
            if (mb_strlen($title) > 190)         $errors[] = 'The title is too long (190 characters max).';
            if (mb_strlen($note) > 500)          $errors[] = 'The note is too long (500 characters max).';
            if (!isset(EA_PRIORITIES[$priority])) $errors[] = 'Please choose a priority.';

            if ($remindAt === null) {
                $errors[] = 'Please enter a valid reminder date and time.';
            } elseif ($remindAt <= $now) {
                $errors[] = 'The reminder must be scheduled in the future.';
            }

            if ($errors) {
                rc_flash('danger', implode(' ', $errors));
            } else {
                ea_insert_reminder($conn, [
                    'recruiter_id'   => $recruiterId,
                    'rule_id'        => null,
                    'source_type'    => 'manual',
                    'related_id'     => null,
                    'candidate_id'   => $candidateId,
                    'application_id' => null,
                    'ref_status'     => '',
                    'title'          => $title,
                    'detail'         => $note,
                    'priority'       => $priority,
                    'subject'        => '',
                    'body'           => '',
                    'remind_at'      => $remindAt,
                    'dedupe_key'     => 'manual:' . bin2hex(random_bytes(8)),
                ]);

                rc_activity(
                    $conn, $recruiterId, 'engagement_reminder_created',
                    'Created a reminder for candidate #' . $candidateId . ' due ' . $remindAt,
                    'engagement_automation', null
                );

                rc_flash('success', 'Reminder scheduled for ' . ea_fmt($remindAt) . '.');
                $redirect = url('recruiter/engagement_automation.php?tab=upcoming');
            }
        }

        /* -------------------- Log interaction (+ optional follow-up) -------------------- */
        elseif ($action === 'log_interaction') {

            $reminderId  = (int)($_POST['reminder_id'] ?? 0);
            $candidateId = (int)($_POST['candidate_id'] ?? 0);
            $channel     = (string)($_POST['channel'] ?? '');
            $direction   = (string)($_POST['direction'] ?? '');
            $outcome     = (string)($_POST['outcome'] ?? '');
            $subject     = trim((string)($_POST['subject'] ?? ''));
            $summary     = trim((string)($_POST['summary'] ?? ''));
            $occurredAt  = ea_parse_datetime((string)($_POST['occurred_at'] ?? ''));
            $followRaw   = trim((string)($_POST['follow_up_at'] ?? ''));
            $followNote  = trim((string)($_POST['follow_up_note'] ?? ''));
            $resolveOld  = !empty($_POST['resolve_pending']);
            $closeRem    = !empty($_POST['complete_reminder']);
            $followAt    = null;
            $applicationId = null;
            $reminder    = null;

            $errors = [];

            // When opened from a reminder, the candidate always comes from the reminder.
            if ($reminderId > 0) {
                $found = ea_fetch_all(
                    $conn,
                    'SELECT id, candidate_id, application_id, status FROM ea_reminders
                     WHERE id = ? AND recruiter_id = ? LIMIT 1',
                    'ii',
                    [$reminderId, $recruiterId]
                );

                if (!$found) {
                    $errors[] = 'That reminder was not found.';
                } else {
                    $reminder    = $found[0];
                    $candidateId = (int)$reminder['candidate_id'];
                }
            }

            $candRows = $candidateId > 0 ? ea_candidate_list($conn, $candidateId) : [];

            if (!$candRows) {
                $errors[] = 'Please choose a valid candidate.';
            }

            if ($reminder && $reminder['application_id'] !== null && $candRows) {
                $okApp = ea_fetch_all(
                    $conn,
                    "SELECT id FROM applications WHERE id = ? AND candidate_id = ? AND status <> 'draft' LIMIT 1",
                    'ii',
                    [(int)$reminder['application_id'], $candidateId]
                );

                if ($okApp) {
                    $applicationId = (int)$reminder['application_id'];
                }
            }

            if (!isset(EA_CHANNELS[$channel]))     $errors[] = 'Please choose a communication channel.';
            if (!isset(EA_DIRECTIONS[$direction])) $errors[] = 'Please choose who initiated the contact.';
            if (!isset(EA_OUTCOMES[$outcome]))     $errors[] = 'Please choose an outcome.';

            if ($occurredAt === null) {
                $errors[] = 'Please enter a valid date and time for the interaction.';
            } elseif ($occurredAt > $now) {
                $errors[] = 'The interaction date cannot be in the future.';
            }

            if ($summary === '')              $errors[] = 'Please add a summary of the conversation.';
            if (mb_strlen($summary) > 5000)   $errors[] = 'The summary is too long (5000 characters max).';
            if (mb_strlen($subject) > 160)    $errors[] = 'The subject is too long (160 characters max).';
            if (mb_strlen($followNote) > 255) $errors[] = 'The follow-up note is too long (255 characters max).';

            if ($followRaw !== '') {
                $followAt = ea_parse_datetime($followRaw);

                if ($followAt === null) {
                    $errors[] = 'Please enter a valid follow-up date and time.';
                } elseif ($followAt <= $now) {
                    $errors[] = 'The follow-up must be scheduled in the future.';
                }
            } elseif ($followNote !== '') {
                $errors[] = 'Add a follow-up date or clear the follow-up note.';
            }

            if ($errors) {
                rc_flash('danger', implode(' ', array_unique($errors)));
            } else {
                $followStatus = $followAt !== null ? 'pending' : 'none';
                $followNote   = $followAt !== null ? $followNote : '';

                $conn->begin_transaction();

                try {
                    ea_exec(
                        $conn,
                        'INSERT INTO recruiter_candidate_communications
                            (recruiter_id, candidate_id, application_id, channel, direction,
                             subject, summary, outcome, occurred_at,
                             follow_up_at, follow_up_note, follow_up_status)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                        'iiisssssssss',
                        [
                            $recruiterId, $candidateId, $applicationId, $channel, $direction,
                            $subject, $summary, $outcome, $occurredAt,
                            $followAt, $followNote, $followStatus,
                        ]
                    );
                    $newId = (int)$conn->insert_id;

                    if ($resolveOld) {
                        ea_exec(
                            $conn,
                            "UPDATE recruiter_candidate_communications
                             SET follow_up_status = 'completed',
                                 follow_up_closed_at = ?,
                                 follow_up_close_note = ?
                             WHERE recruiter_id = ?
                               AND candidate_id = ?
                               AND follow_up_status = 'pending'
                               AND id <> ?",
                            'ssiii',
                            [$now, 'Closed by interaction #' . $newId, $recruiterId, $candidateId, $newId]
                        );
                    }

                    if ($reminder && $closeRem && (string)$reminder['status'] === 'pending') {
                        ea_exec(
                            $conn,
                            "UPDATE ea_reminders
                             SET status = 'done', resolved_at = ?, resolution_note = 'Completed by logging an interaction'
                             WHERE id = ? AND recruiter_id = ? AND status = 'pending'",
                            'sii',
                            [$now, $reminderId, $recruiterId]
                        );
                    }

                    $conn->commit();
                } catch (Throwable $t) {
                    $conn->rollback();
                    throw $t;
                }

                rc_activity(
                    $conn, $recruiterId, 'candidate_communication_logged',
                    'Logged a ' . strtolower(EA_CHANNELS[$channel]) . ' interaction with candidate #' . $candidateId
                        . ' from the engagement reminders',
                    'candidate_communication', $newId
                );

                $msg = 'Interaction logged.';

                if ($followAt !== null) {
                    $msg .= ' Follow-up scheduled for ' . ea_fmt($followAt) . '.';
                }

                if ($reminder && $closeRem && (string)$reminder['status'] === 'pending') {
                    $msg .= ' Reminder completed.';
                }

                rc_flash('success', $msg);
            }
        }

        /* -------------------- Reminder actions -------------------- */
        elseif (in_array($action, ['complete', 'dismiss', 'snooze', 'reopen'], true)) {

            $id = (int)($_POST['reminder_id'] ?? 0);

            $owned = $id > 0 ? ea_fetch_all(
                $conn,
                'SELECT id, candidate_id, status FROM ea_reminders WHERE id = ? AND recruiter_id = ? LIMIT 1',
                'ii',
                [$id, $recruiterId]
            ) : [];

            if (!$owned) {
                rc_flash('danger', 'That reminder was not found.');
            } else {
                $status = (string)$owned[0]['status'];
                $cid    = (int)$owned[0]['candidate_id'];

                if ($action === 'complete' && $status === 'pending') {
                    ea_exec(
                        $conn,
                        "UPDATE ea_reminders SET status = 'done', resolved_at = ?, resolution_note = 'Completed by recruiter'
                         WHERE id = ? AND recruiter_id = ? AND status = 'pending'",
                        'sii',
                        [$now, $id, $recruiterId]
                    );
                    rc_activity($conn, $recruiterId, 'engagement_reminder_completed', 'Completed reminder #' . $id . ' for candidate #' . $cid, 'engagement_automation', $id);
                    rc_flash('success', 'Reminder marked as done.');
                }
                elseif ($action === 'dismiss' && $status === 'pending') {
                    ea_exec(
                        $conn,
                        "UPDATE ea_reminders SET status = 'dismissed', resolved_at = ?, resolution_note = 'Dismissed by recruiter'
                         WHERE id = ? AND recruiter_id = ? AND status = 'pending'",
                        'sii',
                        [$now, $id, $recruiterId]
                    );
                    rc_activity($conn, $recruiterId, 'engagement_reminder_dismissed', 'Dismissed reminder #' . $id . ' for candidate #' . $cid, 'engagement_automation', $id);
                    rc_flash('success', 'Reminder dismissed.');
                }
                elseif ($action === 'snooze' && $status === 'pending') {
                    $days = (int)($_POST['snooze_days'] ?? 1);
                    if (!in_array($days, EA_SNOOZE_DAYS, true)) $days = 1;

                    $until = date('Y-m-d H:i:s', strtotime('+' . $days . ' days'));

                    ea_exec(
                        $conn,
                        "UPDATE ea_reminders SET remind_at = ?
                         WHERE id = ? AND recruiter_id = ? AND status = 'pending'",
                        'sii',
                        [$until, $id, $recruiterId]
                    );
                    rc_activity($conn, $recruiterId, 'engagement_reminder_snoozed', 'Snoozed reminder #' . $id . ' until ' . $until, 'engagement_automation', $id);
                    rc_flash('success', 'Reminder snoozed until ' . ea_fmt($until) . '.');
                }
                elseif ($action === 'reopen' && in_array($status, ['done', 'dismissed'], true)) {
                    ea_exec(
                        $conn,
                        "UPDATE ea_reminders
                         SET status = 'pending', remind_at = ?, resolved_at = NULL, resolution_note = ''
                         WHERE id = ? AND recruiter_id = ? AND status IN ('done', 'dismissed')",
                        'sii',
                        [$now, $id, $recruiterId]
                    );
                    rc_activity($conn, $recruiterId, 'engagement_reminder_reopened', 'Reopened reminder #' . $id . ' for candidate #' . $cid, 'engagement_automation', $id);
                    rc_flash('success', 'Reminder reopened.');
                }
                else {
                    rc_flash('danger', 'That action is not available for this reminder.');
                }
            }
        }

        else {
            rc_flash('danger', 'Unknown action.');
        }

    } catch (Throwable $t) {
        error_log('engagement_automation.php: ' . $t->getMessage());
        rc_flash('danger', 'Something went wrong while saving. Please try again.');
    }

    header('Location: ' . $redirect . $anchor);
    exit;
}

/* ==================================================================
 | Automatic run on page load (throttled per session)
 * ==================================================================*/

if ($ready && $_SERVER['REQUEST_METHOD'] === 'GET' && session_status() === PHP_SESSION_ACTIVE) {

    $lastRun = (int)($_SESSION[$sessionKey] ?? 0);

    if (time() - $lastRun >= EA_AUTO_INTERVAL) {
        try {
            ea_run_all($conn, $recruiterId, $recruiterName, $hasInterviews);
        } catch (Throwable $t) {
            error_log('engagement_automation.php auto-run: ' . $t->getMessage());
        }
        $_SESSION[$sessionKey] = time();
    }
}

/* ==================================================================
 | Read state
 * ==================================================================*/

$tab = (string)($_GET['tab'] ?? 'due');
if (!in_array($tab, EA_TABS, true)) $tab = 'due';

$q    = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));

$loadError  = null;
$stats      = ['due' => 0, 'high' => 0, 'upcoming' => 0, 'closed' => 0];
$reminders  = [];
$totalRows  = 0;
$totalPages = 1;
$rules      = [];
$templates  = [];
$candidates = [];
$lastContact = [];
$nextFollow  = [];

if ($ready) {
    try {

        $thirtyAgo = date('Y-m-d H:i:s', strtotime('-30 days'));

        $rules     = ea_load_rules($conn, $recruiterId);
        $templates = ea_load_templates($conn, $recruiterId);
        $candidates = ea_candidate_list($conn);

        $s = ea_fetch_all(
            $conn,
            "SELECT
                COALESCE(SUM(status = 'pending' AND remind_at <= ?), 0) AS due,
                COALESCE(SUM(status = 'pending' AND remind_at <= ? AND priority = 'high'), 0) AS high,
                COALESCE(SUM(status = 'pending' AND remind_at > ?), 0) AS upcoming,
                COALESCE(SUM(status IN ('done', 'dismissed') AND resolved_at >= ?), 0) AS closed
             FROM ea_reminders
             WHERE recruiter_id = ?",
            'ssssi',
            [$now, $now, $now, $thirtyAgo, $recruiterId]
        );

        if ($s) {
            foreach (array_keys($stats) as $k) {
                $stats[$k] = (int)$s[0][$k];
            }
        }

        $where  = ['rr.recruiter_id = ?'];
        $types  = 'i';
        $params = [$recruiterId];

        switch ($tab) {
            case 'due':
                $where[] = "rr.status = 'pending' AND rr.remind_at <= ?";
                $types  .= 's';
                $params[] = $now;
                $order   = "CASE rr.priority WHEN 'high' THEN 0 WHEN 'normal' THEN 1 ELSE 2 END ASC, rr.remind_at ASC, rr.id ASC";
                break;
            case 'upcoming':
                $where[] = "rr.status = 'pending' AND rr.remind_at > ?";
                $types  .= 's';
                $params[] = $now;
                $order   = 'rr.remind_at ASC, rr.id ASC';
                break;
            case 'closed':
                $where[] = "rr.status IN ('done', 'dismissed')";
                $order   = 'rr.resolved_at DESC, rr.id DESC';
                break;
            default:
                $order = 'rr.created_at DESC, rr.id DESC';
        }

        if ($q !== '') {
            $like    = ea_like($q);
            $where[] = "(CONCAT(cu.first_name, ' ', cu.last_name) LIKE ? OR rr.title LIKE ? OR rr.detail LIKE ?)";
            $types  .= 'sss';
            array_push($params, $like, $like, $like);
        }

        $whereSql = implode(' AND ', $where);

        $c = ea_fetch_all(
            $conn,
            "SELECT COUNT(*) AS total
             FROM ea_reminders rr
             LEFT JOIN users cu ON cu.id = rr.candidate_id
             WHERE {$whereSql}",
            $types,
            $params
        );

        $totalRows  = $c ? (int)$c[0]['total'] : 0;
        $totalPages = max(1, (int)ceil($totalRows / EA_PER_PAGE));
        $page       = min($page, $totalPages);
        $offset     = ($page - 1) * EA_PER_PAGE;

        $reminders = ea_fetch_all(
            $conn,
            "SELECT rr.*, cu.first_name, cu.last_name, cu.email
             FROM ea_reminders rr
             LEFT JOIN users cu ON cu.id = rr.candidate_id
             WHERE {$whereSql}
             ORDER BY {$order}
             LIMIT ? OFFSET ?",
            $types . 'ii',
            array_merge($params, [EA_PER_PAGE, $offset])
        );

        if ($reminders) {
            $cidSet = [];

            foreach ($reminders as $r) {
                $cidSet[(int)$r['candidate_id']] = true;
            }

            $cidList = implode(',', array_keys($cidSet));

            foreach (ea_fetch_all(
                $conn,
                "SELECT c.candidate_id, c.channel, c.outcome, c.occurred_at
                 FROM recruiter_candidate_communications c
                 WHERE c.candidate_id IN ({$cidList})
                   AND NOT EXISTS (SELECT 1
                                     FROM recruiter_candidate_communications n
                                    WHERE n.candidate_id = c.candidate_id
                                      AND (n.occurred_at > c.occurred_at
                                           OR (n.occurred_at = c.occurred_at AND n.id > c.id)))"
            ) as $r) {
                $lastContact[(int)$r['candidate_id']] = $r;
            }

            foreach (ea_fetch_all(
                $conn,
                "SELECT candidate_id, MIN(follow_up_at) AS next_at
                 FROM recruiter_candidate_communications
                 WHERE follow_up_status = 'pending' AND candidate_id IN ({$cidList})
                 GROUP BY candidate_id"
            ) as $r) {
                $nextFollow[(int)$r['candidate_id']] = (string)$r['next_at'];
            }
        }

    } catch (Throwable $t) {
        error_log('engagement_automation.php load: ' . $t->getMessage());
        $loadError = 'The reminders could not be loaded: ' . $t->getMessage();
    }
}

$csrf = rc_csrf();
$self = url('recruiter/engagement_automation.php');

$link = static function (array $override = []) use ($tab, $q, $page): string {
    $p = ['tab' => $tab, 'q' => $q, 'page' => $page];

    foreach ($override as $k => $v) {
        $p[$k] = $v;
    }

    if ((int)($p['page'] ?? 1) <= 1) {
        unset($p['page']);
    }

    $p = array_filter($p, static fn($v) => $v !== '' && $v !== null);

    return $p ? '?' . http_build_query($p) : '';
};

$tabDefs = [
    'due'      => 'Due now',
    'upcoming' => 'Upcoming / snoozed',
    'closed'   => 'Completed',
    'all'      => 'All',
];

require __DIR__ . '/_layout_start.php';
?>

<style>
.ea-tabs{display:flex;flex-wrap:wrap;gap:6px}
.ea-tab{padding:8px 13px;border-radius:999px;text-decoration:none;font-size:13px;font-weight:600;color:inherit;background:rgba(127,127,127,.10)}
.ea-tab.is-active{background:#2454d8;color:#fff}
.ea-toolbar{display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;margin-bottom:16px}
.ea-search{display:flex;gap:8px}
.ea-search input{min-width:220px}

.ea-field{display:flex;flex-direction:column;gap:6px;margin-bottom:14px}
.ea-field label{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;opacity:.75}
.ea-input,.ea-select,.ea-textarea{width:100%;box-sizing:border-box;padding:10px 12px;border-radius:10px;border:1px solid rgba(127,127,127,.35);background:transparent;color:inherit;font:inherit}
.ea-textarea{min-height:110px;resize:vertical}
.ea-input:focus,.ea-select:focus,.ea-textarea:focus{outline:2px solid #2454d8;outline-offset:1px}
.ea-select option{color:#111}
html[data-theme="dark"] .ea-select option,body.dark-mode .ea-select option{background:#111827;color:#f8fafc}
.ea-hint{font-size:12px;opacity:.65}
.ea-row2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.ea-row3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
@media(max-width:700px){.ea-row2,.ea-row3{grid-template-columns:1fr}}

.ea-cell small{display:block;opacity:.65;margin-top:3px}
.ea-cell a{color:inherit}
.ea-actions{display:flex;flex-wrap:wrap;gap:6px;align-items:center}
.ea-inline{display:inline-flex;gap:6px;align-items:center;margin:0}
.ea-inline select{padding:6px 8px;border-radius:8px;border:1px solid rgba(127,127,127,.35);background:transparent;color:inherit;font:inherit;font-size:12px}
.ea-msg{margin-top:8px;font-size:13px}
.ea-msg summary{cursor:pointer;font-weight:600}
.ea-msg pre{white-space:pre-wrap;word-break:break-word;margin:6px 0 0;padding:10px;border-radius:10px;background:rgba(127,127,127,.10);font:inherit;font-size:12px;line-height:1.5}

.ea-pill{display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700;background:rgba(127,127,127,.14)}
.ea-pill--high{background:rgba(210,75,75,.16);color:#a02727}
.ea-pill--normal{background:rgba(36,84,216,.14);color:#2454d8}
.ea-pill--low{background:rgba(127,127,127,.16)}
.ea-pill--warn{background:rgba(230,162,24,.20);color:#8a5a00}
.ea-pill--ok{background:rgba(20,184,123,.16);color:#0b6b48}
[data-theme="dark"] .ea-pill--high{color:#fca5a5}
[data-theme="dark"] .ea-pill--normal{color:#93b4ff}
[data-theme="dark"] .ea-pill--warn{color:#fcd34d}
[data-theme="dark"] .ea-pill--ok{color:#6ee7b7}

.ea-rules{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
@media(max-width:900px){.ea-rules{grid-template-columns:1fr}}
.ea-rule{padding:16px;border-radius:16px;border:1px solid rgba(127,127,127,.2);background:rgba(127,127,127,.06)}
.ea-rule.is-off{opacity:.7}
.ea-rule h4{margin:0 0 4px}
.ea-rule p{margin:0 0 12px;font-size:13px;opacity:.75}
.ea-check{display:flex;gap:8px;align-items:center;font-size:14px;font-weight:600;margin-bottom:12px}
.ea-meta{margin-top:8px;font-size:12px;opacity:.6}

.ea-tpl{border:1px solid rgba(127,127,127,.22);border-radius:14px;padding:12px 14px;margin-bottom:12px}
.ea-tpl summary{cursor:pointer;font-weight:700}
.ea-tpl form{margin-top:12px}
.ea-pager{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:16px;flex-wrap:wrap}
.ea-note{margin-top:14px;font-size:12px;opacity:.65}

.ea-modal{position:fixed;inset:0;z-index:10000;display:none;align-items:center;justify-content:center;padding:24px}
.ea-modal.is-open{display:flex}
.ea-modal__backdrop{position:absolute;inset:0;background:rgba(15,23,42,.70);backdrop-filter:blur(5px)}
.ea-modal__dialog{position:relative;z-index:1;width:min(660px,100%);max-height:90vh;display:flex;flex-direction:column;overflow:hidden;border-radius:22px;background:#fff;color:#172b4d;box-shadow:0 25px 80px rgba(0,0,0,.28)}
.ea-modal__header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:22px 24px 16px;border-bottom:1px solid rgba(127,127,127,.16)}
.ea-modal__header h3{margin:4px 0 2px}
.ea-modal__header p{margin:0;opacity:.7}
.ea-modal__close{width:40px;height:40px;flex:0 0 40px;display:grid;place-items:center;border:0;border-radius:50%;cursor:pointer;background:rgba(15,23,42,.07);color:inherit}
.ea-modal__body{padding:18px 24px;overflow-y:auto}
.ea-modal__footer{display:flex;justify-content:flex-end;gap:10px;padding:14px 24px;border-top:1px solid rgba(127,127,127,.16)}
.ea-sep{margin:6px 0 16px;padding-top:14px;border-top:1px solid rgba(127,127,127,.18);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;opacity:.75}
.ea-checkrow{display:flex;gap:8px;align-items:flex-start;font-size:14px;margin-bottom:8px}
.ea-checkrow input{margin-top:3px}
body.ea-modal-open{overflow:hidden}
html[data-theme="dark"] .ea-modal__dialog,body.dark-mode .ea-modal__dialog{background:#111827;color:#f8fafc}
html[data-theme="dark"] .ea-modal__close,body.dark-mode .ea-modal__close{background:rgba(255,255,255,.08);color:#f8fafc}
@media(max-width:768px){.ea-modal{padding:12px}.ea-modal__dialog{border-radius:16px}}
</style>

<!-- ================================================================
     HERO
================================================================ -->

<section class="rc-hero">
    <div>
        <span class="rc-eyebrow"><i class="fas fa-bell"></i> Candidate engagement</span>
        <h2>Engagement &amp; Follow-Up Automation</h2>
        <p>
            Automatic reminders for candidates who need contact, replies that are
            overdue, follow-ups that are due, stalled applications and upcoming
            interviews, with ready-to-send message templates.
        </p>
        <div class="rc-hero__actions">
            <?php if ($ready && $candidates): ?>
                <button type="button" class="rc-btn rc-btn--light" data-ea-open>
                    <i class="fas fa-plus"></i> Log interaction
                </button>
            <?php endif; ?>
            <a class="rc-btn rc-btn--glass" href="<?= e(url('recruiter/communications.php')) ?>">
                <i class="fas fa-comments"></i> Communications
            </a>
            <a class="rc-btn rc-btn--glass" href="<?= e(url('recruiter/follow_up.php')) ?>">
                <i class="fas fa-route"></i> Follow-Up Pipeline
            </a>
        </div>
    </div>
</section>

<?php if (!$schemaReady): ?>

    <div class="rc-card"><div class="rc-card__body">
        <div class="rc-empty">
            <i class="fas fa-database"></i>
            <strong>Automation tables could not be created</strong>
            <span>Check that the database user may create tables, then reload this page.
                <?= $schemaError ? '(' . e($schemaError) . ')' : '' ?></span>
        </div>
    </div></div>

<?php elseif (!$hasApps || !$hasOpps): ?>

    <div class="rc-card"><div class="rc-card__body">
        <div class="rc-empty">
            <i class="fas fa-database"></i>
            <strong>Application data not available</strong>
            <span>This module needs the existing <code>applications</code> and <code>opportunities</code> tables.</span>
        </div>
    </div></div>

<?php else: ?>

<?php if ($loadError !== null): ?>
    <div class="rc-card rc-space"><div class="rc-card__body"><?= e($loadError) ?></div></div>
<?php endif; ?>

<!-- ================================================================
     STATS
================================================================ -->

<section class="rc-stats">
    <article><i class="fas fa-bell"></i><strong><?= number_format($stats['due']) ?></strong><span>Due now</span></article>
    <article><i class="fas fa-triangle-exclamation"></i><strong><?= number_format($stats['high']) ?></strong><span>High priority due</span></article>
    <article><i class="fas fa-clock"></i><strong><?= number_format($stats['upcoming']) ?></strong><span>Upcoming / snoozed</span></article>
    <article><i class="fas fa-circle-check"></i><strong><?= number_format($stats['closed']) ?></strong><span>Closed (last 30 days)</span></article>
</section>

<!-- ================================================================
     REMINDER QUEUE
================================================================ -->

<section class="rc-card rc-space" id="reminders">

    <div class="rc-card__header">
        <div>
            <span class="rc-eyebrow">Reminders</span>
            <h3>Your reminder queue</h3>
            <p>Most urgent first. Contact the candidate, then mark the reminder done, snooze it or dismiss it.</p>
        </div>
    </div>

    <div class="rc-card__body">

        <div class="ea-toolbar">
            <nav class="ea-tabs" aria-label="Filter reminders">
                <?php foreach ($tabDefs as $key => $label): ?>
                    <a class="ea-tab <?= $tab === $key ? 'is-active' : '' ?>"
                       href="<?= e($self . $link(['tab' => $key, 'page' => 1])) ?>"><?= e($label) ?></a>
                <?php endforeach; ?>
            </nav>

            <form class="ea-search" method="get" action="<?= e($self) ?>">
                <input type="hidden" name="tab" value="<?= e($tab) ?>">
                <input class="ea-input" type="search" name="q" value="<?= e($q) ?>"
                       placeholder="Candidate or reminder" aria-label="Search reminders">
                <button class="rc-btn rc-btn--secondary rc-btn--small" type="submit">
                    <i class="fas fa-magnifying-glass"></i> Search
                </button>
            </form>
        </div>

        <?php if (!$reminders): ?>

            <div class="rc-empty">
                <i class="fas fa-circle-check"></i>
                <strong>No reminders here</strong>
                <span>
                    <?= ($q !== '')
                        ? 'Nothing matches your search.'
                        : ($tab === 'due'
                            ? 'You are all caught up. New reminders appear automatically as candidates need attention.'
                            : 'There is nothing in this list yet.') ?>
                </span>
            </div>

        <?php else: ?>

            <div class="rc-table-wrap">
                <table class="rc-table">
                    <thead>
                        <tr>
                            <th>Reminder</th>
                            <th>Candidate</th>
                            <th>Priority</th>
                            <th>When</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($reminders as $row):
                        $rid       = (int)$row['id'];
                        $cid       = (int)$row['candidate_id'];
                        $status    = (string)$row['status'];
                        $name      = ea_person($row['first_name'] ?? '', $row['last_name'] ?? '', 'Candidate #' . $cid);
                        $email     = trim((string)($row['email'] ?? ''));
                        $priority  = isset(EA_PRIORITIES[(string)$row['priority']]) ? (string)$row['priority'] : 'normal';
                        $source    = (string)$row['source_type'];
                        $srcLabel  = EA_TRIGGERS[$source]['label'] ?? 'Manual reminder';
                        $isDue     = $status === 'pending' && (string)$row['remind_at'] <= $now;
                        $subject   = (string)$row['suggested_subject'];
                        $body      = (string)($row['suggested_body'] ?? '');
                        $mailto    = '';

                        if ($email !== '') {
                            $mailto = 'mailto:' . $email;
                            if ($subject !== '' || $body !== '') {
                                $mailto .= '?subject=' . rawurlencode($subject) . '&body=' . rawurlencode($body);
                            }
                        }
                    ?>
                        <tr>
                            <td class="ea-cell">
                                <strong><?= e((string)$row['title']) ?></strong>
                                <small><span class="ea-pill"><?= e($srcLabel) ?></span></small>
                                <?php if ((string)$row['detail'] !== ''): ?>
                                    <small><?= e((string)$row['detail']) ?></small>
                                <?php endif; ?>
                                <?php if ($body !== ''): ?>
                                    <details class="ea-msg">
                                        <summary>Suggested message</summary>
                                        <?php if ($subject !== ''): ?><strong><?= e($subject) ?></strong><?php endif; ?>
                                        <pre><?= e($body) ?></pre>
                                    </details>
                                <?php endif; ?>
                            </td>

                            <td class="ea-cell">
                                <strong><a href="<?= e(url('recruiter/communications.php?candidate_id=' . $cid)) ?>"><?= e($name) ?></a></strong>
                                <?php if ($email !== ''): ?><small><?= e($email) ?></small><?php endif; ?>
                                <?php $lc = $lastContact[$cid] ?? null; ?>
                                <small>
                                    Last contact:
                                    <?php if ($lc): ?>
                                        <?= e(EA_CHANNELS[(string)$lc['channel']] ?? rc_label((string)$lc['channel'])) ?>
                                        · <?= e(ea_fmt((string)$lc['occurred_at'], 'd M Y')) ?>
                                        (<?= e(EA_OUTCOMES[(string)$lc['outcome']] ?? rc_label((string)$lc['outcome'])) ?>)
                                    <?php else: ?>
                                        none recorded
                                    <?php endif; ?>
                                </small>
                                <?php if (isset($nextFollow[$cid])): ?>
                                    <small>Next follow-up: <?= e(ea_fmt($nextFollow[$cid])) ?></small>
                                <?php endif; ?>
                            </td>

                            <td>
                                <span class="ea-pill ea-pill--<?= e($priority) ?>"><?= e(EA_PRIORITIES[$priority]) ?></span>
                            </td>

                            <td class="ea-cell">
                                <?php if ($status === 'pending'): ?>
                                    <?php if ($isDue): ?>
                                        <span class="ea-pill ea-pill--warn">Due</span>
                                        <small>Since <?= e(ea_fmt((string)$row['remind_at'])) ?></small>
                                    <?php else: ?>
                                        <span class="ea-pill">Scheduled</span>
                                        <small><?= e(ea_fmt((string)$row['remind_at'])) ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="ea-pill <?= $status === 'done' ? 'ea-pill--ok' : '' ?>"><?= $status === 'done' ? 'Done' : 'Dismissed' ?></span>
                                    <small><?= e(ea_fmt((string)$row['resolved_at'])) ?></small>
                                    <?php if ((string)$row['resolution_note'] !== ''): ?>
                                        <small><?= e((string)$row['resolution_note']) ?></small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="ea-actions">
                                    <?php if ($status === 'pending'): ?>

                                        <?php if ($candidates): ?>
                                            <button type="button" class="rc-btn rc-btn--primary rc-btn--small"
                                                    data-ea-open
                                                    data-reminder="<?= $rid ?>"
                                                    data-candidate="<?= $cid ?>"
                                                    data-name="<?= e($name) ?>">
                                                <i class="fas fa-plus"></i> Log interaction
                                            </button>
                                            <button type="button" class="rc-btn rc-btn--secondary rc-btn--small"
                                                    data-ea-open
                                                    data-focus="follow"
                                                    data-reminder="<?= $rid ?>"
                                                    data-candidate="<?= $cid ?>"
                                                    data-name="<?= e($name) ?>">
                                                <i class="fas fa-calendar-plus"></i> Schedule follow-up
                                            </button>
                                        <?php endif; ?>
                                        <a class="rc-btn rc-btn--secondary rc-btn--small"
                                           href="<?= e(url('recruiter/communications.php?candidate_id=' . $cid)) ?>">
                                            <i class="fas fa-clock-rotate-left"></i> History
                                        </a>

                                        <?php if ($mailto !== ''): ?>
                                            <a class="rc-btn rc-btn--secondary rc-btn--small" href="<?= e($mailto) ?>">
                                                <i class="fas fa-envelope"></i> Email
                                            </a>
                                        <?php endif; ?>

                                        <form class="ea-inline" method="post" action="<?= e($self) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                            <input type="hidden" name="action" value="complete">
                                            <input type="hidden" name="reminder_id" value="<?= $rid ?>">
                                            <input type="hidden" name="return_tab" value="<?= e($tab) ?>">
                                            <button type="submit" class="rc-btn rc-btn--secondary rc-btn--small">
                                                <i class="fas fa-check"></i> Done
                                            </button>
                                        </form>

                                        <form class="ea-inline" method="post" action="<?= e($self) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                            <input type="hidden" name="action" value="snooze">
                                            <input type="hidden" name="reminder_id" value="<?= $rid ?>">
                                            <input type="hidden" name="return_tab" value="<?= e($tab) ?>">
                                            <select name="snooze_days" aria-label="Snooze for">
                                                <?php foreach (EA_SNOOZE_DAYS as $d): ?>
                                                    <option value="<?= (int)$d ?>"><?= e(ea_plural($d, 'day')) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="rc-btn rc-btn--secondary rc-btn--small">
                                                <i class="fas fa-clock"></i> Snooze
                                            </button>
                                        </form>

                                        <form class="ea-inline" method="post" action="<?= e($self) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                            <input type="hidden" name="action" value="dismiss">
                                            <input type="hidden" name="reminder_id" value="<?= $rid ?>">
                                            <input type="hidden" name="return_tab" value="<?= e($tab) ?>">
                                            <button type="submit" class="rc-btn rc-btn--secondary rc-btn--small">
                                                <i class="fas fa-ban"></i> Dismiss
                                            </button>
                                        </form>

                                    <?php else: ?>

                                        <form class="ea-inline" method="post" action="<?= e($self) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                            <input type="hidden" name="action" value="reopen">
                                            <input type="hidden" name="reminder_id" value="<?= $rid ?>">
                                            <input type="hidden" name="return_tab" value="<?= e($tab) ?>">
                                            <button type="submit" class="rc-btn rc-btn--secondary rc-btn--small">
                                                <i class="fas fa-rotate-left"></i> Reopen
                                            </button>
                                        </form>

                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav class="ea-pager" aria-label="Reminder pages">
                    <span class="ea-hint">Page <?= (int)$page ?> of <?= (int)$totalPages ?> · <?= number_format($totalRows) ?> reminders</span>
                    <span class="ea-actions">
                        <?php if ($page > 1): ?>
                            <a class="rc-btn rc-btn--secondary rc-btn--small" href="<?= e($self . $link(['page' => $page - 1]) . '#reminders') ?>"><i class="fas fa-chevron-left"></i> Previous</a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a class="rc-btn rc-btn--secondary rc-btn--small" href="<?= e($self . $link(['page' => $page + 1]) . '#reminders') ?>">Next <i class="fas fa-chevron-right"></i></a>
                        <?php endif; ?>
                    </span>
                </nav>
            <?php endif; ?>

        <?php endif; ?>

        <p class="ea-note">
            Automatic reminders close themselves when the situation is resolved (you log a new interaction,
            complete the follow-up, the application changes stage or the interview is no longer scheduled).
            Dismissing or completing a reminder prevents the same one from being raised again.
        </p>
    </div>
</section>

<!-- ================================================================
     MANUAL REMINDER
================================================================ -->

<section class="rc-card rc-space" id="manual">

    <div class="rc-card__header">
        <div>
            <span class="rc-eyebrow">Custom</span>
            <h3>Add a reminder</h3>
            <p>Set your own reminder for any candidate you can work with.</p>
        </div>
    </div>

    <div class="rc-card__body">
        <?php if (!$candidates): ?>
            <div class="rc-empty">
                <i class="fas fa-user-slash"></i>
                <strong>No eligible candidates</strong>
                <span>Candidates appear here once they consent to recruiter discovery or submit an application.</span>
            </div>
        <?php else: ?>
            <form method="post" action="<?= e($self) ?>">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="add_manual">
                <input type="hidden" name="return_tab" value="upcoming">

                <div class="ea-row2">
                    <div class="ea-field">
                        <label for="eaCandidate">Candidate</label>
                        <select class="ea-select" id="eaCandidate" name="candidate_id" required>
                            <option value="">Select a candidate…</option>
                            <?php foreach ($candidates as $c):
                                $cn = ea_person($c['first_name'] ?? '', $c['last_name'] ?? '', 'Candidate #' . (int)$c['id']);
                            ?>
                                <option value="<?= (int)$c['id'] ?>">
                                    <?= e($cn . ((string)$c['email'] !== '' ? ' — ' . (string)$c['email'] : '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ea-field">
                        <label for="eaTitle">Title</label>
                        <input class="ea-input" id="eaTitle" type="text" name="title" maxlength="190" required
                               placeholder="e.g. Chase updated CV">
                    </div>
                </div>

                <div class="ea-row2">
                    <div class="ea-field">
                        <label for="eaWhen">Remind me on</label>
                        <input class="ea-input" id="eaWhen" type="datetime-local" name="remind_at"
                               min="<?= e($nowLocal) ?>" required>
                    </div>

                    <div class="ea-field">
                        <label for="eaPriority">Priority</label>
                        <select class="ea-select" id="eaPriority" name="priority">
                            <?php foreach (EA_PRIORITIES as $k => $label): ?>
                                <option value="<?= e($k) ?>" <?= $k === 'normal' ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="ea-field">
                    <label for="eaNote">Note (optional)</label>
                    <input class="ea-input" id="eaNote" type="text" name="note" maxlength="500">
                </div>

                <button type="submit" class="rc-btn rc-btn--primary"><i class="fas fa-bell"></i> Schedule reminder</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<!-- ================================================================
     AUTOMATION RULES
================================================================ -->

<section class="rc-card rc-space" id="rules">

    <div class="rc-card__header">
        <div>
            <span class="rc-eyebrow">Automation</span>
            <h3>Automation rules</h3>
            <p>Rules run automatically every time you log in and while you work. Nothing needs to be clicked.</p>
        </div>
    </div>

    <div class="rc-card__body">
        <div class="ea-rules">
            <?php foreach (EA_TRIGGERS as $key => $def):
                $rule = $rules[$key] ?? null;
                if (!$rule) continue;
                $on = (int)$rule['is_active'] === 1;
            ?>
                <form class="ea-rule <?= $on ? '' : 'is-off' ?>" method="post" action="<?= e($self) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="save_rule">
                    <input type="hidden" name="trigger_key" value="<?= e($key) ?>">
                    <input type="hidden" name="return_tab" value="<?= e($tab) ?>">

                    <h4><?= e($def['label']) ?></h4>
                    <p><?= e($def['desc']) ?></p>

                    <label class="ea-check">
                        <input type="checkbox" name="is_active" value="1" <?= $on ? 'checked' : '' ?>>
                        <span>Rule is active</span>
                    </label>

                    <div class="ea-row3">
                        <div class="ea-field">
                            <label for="eaTh<?= e($key) ?>">Days</label>
                            <input class="ea-input" id="eaTh<?= e($key) ?>" type="number" name="threshold_days"
                                   min="<?= (int)$def['min'] ?>" max="<?= (int)$def['max'] ?>"
                                   value="<?= (int)$rule['threshold_days'] ?>" required>
                            <span class="ea-hint"><?= e($def['unit']) ?></span>
                        </div>

                        <div class="ea-field">
                            <label for="eaPr<?= e($key) ?>">Priority</label>
                            <select class="ea-select" id="eaPr<?= e($key) ?>" name="priority">
                                <?php foreach (EA_PRIORITIES as $k => $label): ?>
                                    <option value="<?= e($k) ?>" <?= (string)$rule['priority'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="ea-field">
                            <label for="eaTp<?= e($key) ?>">Message template</label>
                            <select class="ea-select" id="eaTp<?= e($key) ?>" name="template_id">
                                <option value="0">None</option>
                                <?php foreach ($templates as $tid => $tpl): ?>
                                    <option value="<?= (int)$tid ?>" <?= (int)($rule['template_id'] ?? 0) === (int)$tid ? 'selected' : '' ?>><?= e((string)$tpl['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="rc-btn rc-btn--primary rc-btn--small"><i class="fas fa-floppy-disk"></i> Save rule</button>

                    <div class="ea-meta">
                        Last run: <?= e(ea_fmt($rule['last_run_at'] !== null ? (string)$rule['last_run_at'] : null, 'd M Y · H:i')) ?>
                        <?php if ($key === 'interview_upcoming' && !$hasInterviews): ?>
                            · Interview Workspace not set up yet, so this rule is skipped.
                        <?php endif; ?>
                    </div>
                </form>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ================================================================
     MESSAGE TEMPLATES
================================================================ -->

<section class="rc-card rc-space" id="templates">

    <div class="rc-card__header">
        <div>
            <span class="rc-eyebrow">Messages</span>
            <h3>Follow-up message templates</h3>
            <p>
                Attached to rules so each reminder carries a ready-to-send email. Placeholders:
                <code>{first_name}</code> <code>{candidate_name}</code> <code>{opportunity}</code>
                <code>{reference}</code> <code>{recruiter_name}</code>
            </p>
        </div>
    </div>

    <div class="rc-card__body">

        <?php foreach ($templates as $tid => $tpl): ?>
            <details class="ea-tpl">
                <summary><?= e((string)$tpl['name']) ?></summary>

                <form method="post" action="<?= e($self) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="save_template">
                    <input type="hidden" name="template_id" value="<?= (int)$tid ?>">
                    <input type="hidden" name="return_tab" value="<?= e($tab) ?>">

                    <div class="ea-field">
                        <label for="eaTn<?= (int)$tid ?>">Name</label>
                        <input class="ea-input" id="eaTn<?= (int)$tid ?>" type="text" name="name" maxlength="100" required value="<?= e((string)$tpl['name']) ?>">
                    </div>
                    <div class="ea-field">
                        <label for="eaTs<?= (int)$tid ?>">Subject</label>
                        <input class="ea-input" id="eaTs<?= (int)$tid ?>" type="text" name="subject" maxlength="190" value="<?= e((string)$tpl['subject']) ?>">
                    </div>
                    <div class="ea-field">
                        <label for="eaTb<?= (int)$tid ?>">Message</label>
                        <textarea class="ea-textarea" id="eaTb<?= (int)$tid ?>" name="body" maxlength="3000" required><?= e((string)$tpl['body']) ?></textarea>
                    </div>

                    <div class="ea-actions">
                        <button type="submit" class="rc-btn rc-btn--primary rc-btn--small"><i class="fas fa-floppy-disk"></i> Save template</button>
                    </div>
                </form>

                <form method="post" action="<?= e($self) ?>" style="margin-top:8px"
                      onsubmit="return confirm('Delete this template?');">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="delete_template">
                    <input type="hidden" name="template_id" value="<?= (int)$tid ?>">
                    <input type="hidden" name="return_tab" value="<?= e($tab) ?>">
                    <button type="submit" class="rc-btn rc-btn--secondary rc-btn--small"><i class="fas fa-trash"></i> Delete</button>
                </form>
            </details>
        <?php endforeach; ?>

        <details class="ea-tpl" <?= $templates ? '' : 'open' ?>>
            <summary><i class="fas fa-plus"></i> New template</summary>

            <form method="post" action="<?= e($self) ?>">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="save_template">
                <input type="hidden" name="template_id" value="0">
                <input type="hidden" name="return_tab" value="<?= e($tab) ?>">

                <div class="ea-field">
                    <label for="eaNewName">Name</label>
                    <input class="ea-input" id="eaNewName" type="text" name="name" maxlength="100" required>
                </div>
                <div class="ea-field">
                    <label for="eaNewSubject">Subject</label>
                    <input class="ea-input" id="eaNewSubject" type="text" name="subject" maxlength="190">
                </div>
                <div class="ea-field">
                    <label for="eaNewBody">Message</label>
                    <textarea class="ea-textarea" id="eaNewBody" name="body" maxlength="3000" required></textarea>
                </div>

                <button type="submit" class="rc-btn rc-btn--primary rc-btn--small"><i class="fas fa-floppy-disk"></i> Create template</button>
            </form>
        </details>
    </div>
</section>

<?php if ($candidates): ?>
<!-- ================================================================
     MODAL: LOG INTERACTION + SCHEDULE FOLLOW-UP
================================================================ -->

<div class="ea-modal" id="eaLogModal" aria-hidden="true">
    <div class="ea-modal__backdrop" data-ea-close></div>
    <form class="ea-modal__dialog" method="post" action="<?= e($self) ?>"
          role="dialog" aria-modal="true" aria-labelledby="eaLogTitle">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="log_interaction">
        <input type="hidden" name="return_tab" value="<?= e($tab) ?>">
        <input type="hidden" name="reminder_id" id="eaLogReminder" value="0">

        <div class="ea-modal__header">
            <div>
                <span class="rc-eyebrow">Candidate interaction</span>
                <h3 id="eaLogTitle">Log interaction</h3>
                <p id="eaLogSub">Record what was discussed and, if needed, schedule the next follow-up.</p>
            </div>
            <button type="button" class="ea-modal__close" data-ea-close aria-label="Close"><i class="fas fa-xmark"></i></button>
        </div>

        <div class="ea-modal__body">

            <div class="ea-field" id="eaLogCandidateField">
                <label for="eaLogCandidate">Candidate</label>
                <select class="ea-select" id="eaLogCandidate" name="candidate_id" required>
                    <option value="">Select a candidate…</option>
                    <?php foreach ($candidates as $c):
                        $cn = ea_person($c['first_name'] ?? '', $c['last_name'] ?? '', 'Candidate #' . (int)$c['id']);
                    ?>
                        <option value="<?= (int)$c['id'] ?>">
                            <?= e($cn . ((string)$c['email'] !== '' ? ' — ' . (string)$c['email'] : '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="ea-row2">
                <div class="ea-field">
                    <label for="eaLogChannel">Channel</label>
                    <select class="ea-select" id="eaLogChannel" name="channel" required>
                        <?php foreach (EA_CHANNELS as $k => $label): ?>
                            <option value="<?= e($k) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ea-field">
                    <label for="eaLogDirection">Initiated by</label>
                    <select class="ea-select" id="eaLogDirection" name="direction" required>
                        <?php foreach (EA_DIRECTIONS as $k => $label): ?>
                            <option value="<?= e($k) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="ea-row2">
                <div class="ea-field">
                    <label for="eaLogOccurred">Date &amp; time</label>
                    <input class="ea-input" id="eaLogOccurred" type="datetime-local" name="occurred_at"
                           value="<?= e($nowLocal) ?>" max="<?= e($nowLocal) ?>" required>
                </div>

                <div class="ea-field">
                    <label for="eaLogOutcome">Outcome</label>
                    <select class="ea-select" id="eaLogOutcome" name="outcome" required>
                        <option value="">Select an outcome…</option>
                        <?php foreach (EA_OUTCOMES as $k => $label): ?>
                            <option value="<?= e($k) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="ea-field">
                <label for="eaLogSubject">Subject (optional)</label>
                <input class="ea-input" id="eaLogSubject" type="text" name="subject" maxlength="160"
                       placeholder="e.g. Availability check for Senior Developer role">
            </div>

            <div class="ea-field">
                <label for="eaLogSummary">Summary</label>
                <textarea class="ea-textarea" id="eaLogSummary" name="summary" maxlength="5000" required
                          placeholder="What was discussed, agreed or promised?"></textarea>
            </div>

            <div class="ea-sep">Next follow-up (optional)</div>

            <div class="ea-row2">
                <div class="ea-field">
                    <label for="eaLogFollowAt">Follow up on</label>
                    <input class="ea-input" id="eaLogFollowAt" type="datetime-local" name="follow_up_at" min="<?= e($nowLocal) ?>">
                </div>

                <div class="ea-field">
                    <label for="eaLogFollowNote">Purpose</label>
                    <input class="ea-input" id="eaLogFollowNote" type="text" name="follow_up_note" maxlength="255"
                           placeholder="e.g. Chase updated CV">
                </div>
            </div>

            <label class="ea-checkrow">
                <input type="checkbox" name="resolve_pending" value="1">
                <span>Mark my other pending follow-ups for this candidate as completed</span>
            </label>

            <label class="ea-checkrow" id="eaLogCompleteRow">
                <input type="checkbox" name="complete_reminder" value="1" checked>
                <span>Mark this reminder as done</span>
            </label>
        </div>

        <div class="ea-modal__footer">
            <button type="button" class="rc-btn rc-btn--secondary" data-ea-close>Cancel</button>
            <button type="submit" class="rc-btn rc-btn--primary"><i class="fas fa-floppy-disk"></i> Save interaction</button>
        </div>
    </form>
</div>

<script>
(function () {
    var modal = document.getElementById('eaLogModal');
    if (!modal) return;

    var form      = modal.querySelector('form');
    var reminder  = document.getElementById('eaLogReminder');
    var candSel   = document.getElementById('eaLogCandidate');
    var candField = document.getElementById('eaLogCandidateField');
    var completeRow = document.getElementById('eaLogCompleteRow');
    var sub       = document.getElementById('eaLogSub');
    var defaultSub = sub.textContent;

    function openModal(btn) {
        form.reset();

        var rid  = btn.getAttribute('data-reminder') || '0';
        var cid  = btn.getAttribute('data-candidate') || '';
        var name = btn.getAttribute('data-name') || '';

        reminder.value = rid;

        if (rid !== '0' && cid !== '') {
            candSel.value = cid;
            candSel.required = false;
            candField.style.display = 'none';
            completeRow.style.display = '';
            sub.textContent = 'Candidate: ' + name;
        } else {
            candSel.required = true;
            candField.style.display = '';
            completeRow.style.display = 'none';
            completeRow.querySelector('input').checked = false;
            sub.textContent = defaultSub;
        }

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('ea-modal-open');

        var first = btn.getAttribute('data-focus') === 'follow'
            ? document.getElementById('eaLogFollowAt')
            : (rid !== '0' ? document.getElementById('eaLogChannel') : candSel);
        if (first) first.focus();
    }

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('ea-modal-open');
    }

    document.querySelectorAll('[data-ea-open]').forEach(function (btn) {
        btn.addEventListener('click', function () { openModal(btn); });
    });

    modal.querySelectorAll('[data-ea-close]').forEach(function (el) {
        el.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
    });
})();
</script>
<?php endif; ?>

<?php endif; ?>

<?php require __DIR__ . '/_layout_end.php'; ?>
