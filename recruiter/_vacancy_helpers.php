<?php
/*
|--------------------------------------------------------------------------
| Vacancy Management helpers (new file - nothing existing is modified)
|--------------------------------------------------------------------------
| Vacancies are the existing `opportunities` records. Recruitment progress
| comes from the existing `applications` workflow. No new tables are created.
*/

const VM_STAGES = [
    'submitted'           => 'Awaiting screening',
    'under_review'        => 'Screening / review',
    'shortlisted'         => 'Shortlisted',
    'assessment'          => 'Assessment',
    'interview_required'  => 'Interview required',
    'interview_scheduled' => 'Interview scheduled',
    'interview_completed' => 'Awaiting feedback',
    'selected'            => 'Selected',
    'waitlisted'          => 'Waitlisted',
    'offer_sent'          => 'Offer sent',
    'offer_accepted'      => 'Offer accepted',
    'offer_declined'      => 'Offer declined',
    'rejected'            => 'Rejected',
    'withdrawn'           => 'Withdrawn',
    'on_hold'             => 'On hold',
];

const VM_TERMINAL     = ['offer_accepted', 'offer_declined', 'rejected', 'withdrawn'];
const VM_TERMINAL_SQL = "'offer_accepted','offer_declined','rejected','withdrawn'";
const VM_OPEN_STATUSES = ['published', 'closing_soon'];
const VM_STATUS_FALLBACK = ['draft', 'published', 'closing_soon', 'closed'];
const VM_STALE_DAYS   = 7;

function vm_stage_label(string $status): string
{
    return VM_STAGES[$status] ?? rc_label($status);
}

/** Column metadata of the opportunities table, keyed by column name. */
function vm_columns(mysqli $conn): array
{
    static $cache = null;
    if ($cache !== null) return $cache;

    $cache = [];
    $res = $conn->query(
        "SELECT COLUMN_NAME AS name, COLUMN_TYPE AS ctype, DATA_TYPE AS dtype,
                IS_NULLABLE AS nullable, COLUMN_DEFAULT AS cdefault, EXTRA AS extra
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'opportunities'"
    );
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $cache[(string)$r['name']] = $r;
        }
        $res->free();
    }
    return $cache;
}

/** Allowed values of an ENUM column, or null when it is not an ENUM. */
function vm_enum_options(array $cols, string $name): ?array
{
    $type = (string)($cols[$name]['ctype'] ?? '');
    if (stripos($type, 'enum(') !== 0) return null;

    preg_match_all("/'((?:[^']|'')*)'/", $type, $m);
    $out = [];
    foreach ($m[1] as $v) $out[] = str_replace("''", "'", $v);
    return $out;
}

function vm_status_options(array $cols): array
{
    return vm_enum_options($cols, 'status') ?? VM_STATUS_FALLBACK;
}

/** Columns that must be supplied on INSERT but are unknown to this feature. */
function vm_create_blockers(array $cols, array $supplied): array
{
    $out = [];
    foreach ($cols as $name => $meta) {
        if (in_array($name, $supplied, true)) continue;
        $extra = strtolower((string)$meta['extra']);
        if (
            $meta['nullable'] === 'NO'
            && $meta['cdefault'] === null
            && strpos($extra, 'auto_increment') === false
            && strpos($extra, 'generated') === false
        ) {
            $out[] = $name;
        }
    }
    return $out;
}

function vm_parse_date(string $v): ?DateTimeImmutable
{
    $v = trim($v);
    if ($v === '') return null;
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $v);
    return ($d && $d->format('Y-m-d') === $v) ? $d : null;
}

/**
 * Application counts per vacancy and stage (drafts excluded).
 * Returns [opportunity_id => ['total'=>, 'stages'=>[status=>n], 'overdue'=>n]].
 */
function vm_stage_counts(mysqli $conn, ?int $opportunityId = null): array
{
    $out = [];
    if (!rc_has_table($conn, 'applications')) return $out;

    $cutoff = date('Y-m-d H:i:s', strtotime('-' . VM_STALE_DAYS . ' days'));

    $sql = "SELECT a.opportunity_id, a.status, COUNT(*) AS n,
                   SUM(CASE WHEN a.status NOT IN (" . VM_TERMINAL_SQL . ")
                             AND COALESCE(a.updated_at, a.submitted_at, a.created_at) < ?
                            THEN 1 ELSE 0 END) AS overdue
            FROM applications a
            WHERE a.status <> 'draft' AND a.opportunity_id IS NOT NULL";
    $types  = 's';
    $params = [$cutoff];

    if ($opportunityId !== null) {
        $sql    .= ' AND a.opportunity_id = ?';
        $types  .= 'i';
        $params[] = $opportunityId;
    }
    $sql .= ' GROUP BY a.opportunity_id, a.status';

    $stmt = $conn->prepare($sql);
    if (!$stmt) return $out;
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($r = $res->fetch_assoc()) {
        $oid = (int)$r['opportunity_id'];
        if (!isset($out[$oid])) {
            $out[$oid] = ['total' => 0, 'stages' => [], 'overdue' => 0];
        }
        $n = (int)$r['n'];
        $out[$oid]['total']   += $n;
        $out[$oid]['overdue'] += (int)$r['overdue'];
        $out[$oid]['stages'][(string)$r['status']] = $n;
    }
    $stmt->close();

    return $out;
}

function vm_empty_counts(): array
{
    return ['total' => 0, 'stages' => [], 'overdue' => 0];
}

/** Applications that are still moving through the pipeline. */
function vm_active_count(array $c): int
{
    $active = (int)$c['total'];
    foreach (VM_TERMINAL as $t) $active -= (int)($c['stages'][$t] ?? 0);
    return max(0, $active);
}

/** Days from today to a Y-m-d date (negative when in the past), or null. */
function vm_days_until(?string $date): ?int
{
    if (!$date) return null;
    $ts = strtotime(substr($date, 0, 10));
    if (!$ts) return null;
    return (int)round(($ts - strtotime('today')) / 86400);
}

function vm_closing_label(?string $date, string $status): string
{
    $d = vm_days_until($date);
    if ($d === null) return 'No closing date';
    $open = in_array($status, VM_OPEN_STATUSES, true);
    if ($d < 0)  return $open ? 'Past closing date (' . abs($d) . ' d)' : 'Closed';
    if ($d === 0) return 'Closes today';
    return $d . ($d === 1 ? ' day left' : ' days left');
}

/**
 * Reasons a vacancy needs a recruiter's attention.
 * Returns a list of ['level' => 'danger'|'warning', 'text' => string].
 */
function vm_attention_flags(array $vacancy, array $c): array
{
    $flags  = [];
    $status = (string)($vacancy['status'] ?? '');
    $open   = in_array($status, VM_OPEN_STATUSES, true);
    $days   = vm_days_until($vacancy['application_close_date'] ?? null);
    $active = vm_active_count($c);
    $accepted = (int)($c['stages']['offer_accepted'] ?? 0);
    $positions = (int)($vacancy['available_positions'] ?? 0);

    if ($open && $days !== null && $days < 0) {
        $flags[] = ['level' => 'danger', 'text' => 'Past closing date but still open'];
    } elseif ($open && $days !== null && $days <= 7) {
        $flags[] = ['level' => 'warning', 'text' => $days === 0 ? 'Closes today' : 'Closes in ' . $days . ($days === 1 ? ' day' : ' days')];
    }
    if ($open && (int)$c['total'] === 0) {
        $flags[] = ['level' => 'warning', 'text' => 'No applications yet'];
    }
    if ((int)$c['overdue'] > 0) {
        $flags[] = ['level' => 'warning', 'text' => (int)$c['overdue'] . ' application' . ((int)$c['overdue'] === 1 ? '' : 's') . ' need follow-up'];
    }
    if ($status === 'closed' && $active > 0) {
        $flags[] = ['level' => 'warning', 'text' => 'Closed with ' . $active . ' application' . ($active === 1 ? '' : 's') . ' still in progress'];
    }
    if ($open && $positions > 0 && $accepted >= $positions) {
        $flags[] = ['level' => 'warning', 'text' => 'All positions filled but vacancy still open'];
    }
    return $flags;
}

/** Furthest-stage milestones for the lifecycle view (counts applications that reached each point). */
function vm_milestones(array $c): array
{
    $s = $c['stages'];
    $n = static fn(array $keys): int => array_sum(array_map(static fn($k) => (int)($s[$k] ?? 0), $keys));
    return [
        'Applied'      => (int)$c['total'],
        'Shortlisted+' => $n(['shortlisted','assessment','interview_required','interview_scheduled','interview_completed','selected','waitlisted','offer_sent','offer_accepted','offer_declined']),
        'Interview+'   => $n(['interview_required','interview_scheduled','interview_completed','selected','waitlisted','offer_sent','offer_accepted','offer_declined']),
        'Selected+'    => $n(['selected','offer_sent','offer_accepted','offer_declined']),
        'Accepted'     => $n(['offer_accepted']),
    ];
}
