<?php
// ============================================================================
// Attendance Monitoring helpers (Supervisor Portal).
// Include from any supervisor page with:
//     require_once __DIR__ . '/_attendance.php';
//
// "Attendance" = a candidate's attendance at programme / training sessions in
// the cohorts assigned to the logged-in Supervisor.
//
// Counting rules
//   present / late  -> counted as attended
//   absent          -> counted as missed
//   excused         -> tracked, but excluded from the attendance rate
//   rate            =  attended / (attended + absent)
//
// Data source
//   1. If the database already has an attendance table with a user, a cohort
//      (or participation), a date and a status column, it is detected and used.
//   2. Otherwise `candidate_attendance` is created on first use (same approach
//      as _progress_history.php) so other modules / the helper sv_att_record()
//      can start filling it.
//
// Every function is guarded and failure-safe: attendance can never break a page.
// ============================================================================

if (!function_exists('sv_att_config')) {
    /** Thresholds used to flag candidates. Adjust here in one place. */
    function sv_att_config(): array {
        return [
            'warn'            => 80, // rate below this  => "At risk"
            'critical'        => 60, // rate below this  => "Critical"
            'streak_warn'     => 2,  // consecutive absences => "At risk"
            'streak_critical' => 3,  // consecutive absences => "Critical"
            'min_sessions'    => 2,  // sessions needed before the rate alone can flag someone
            'window'          => 30, // default reporting window (days)
            'weeks'           => 8,  // weekly trend buckets
        ];
    }
}

if (!function_exists('sv_att_ensure_table')) {
    function sv_att_ensure_table(): bool {
        try {
            $conn = Database::getConnection();
            $ok = $conn->query(
                "CREATE TABLE IF NOT EXISTS candidate_attendance (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    cohort_id INT NOT NULL,
                    user_id INT NOT NULL,
                    session_date DATE NOT NULL,
                    session_title VARCHAR(190) NOT NULL DEFAULT '',
                    status VARCHAR(20) NOT NULL DEFAULT 'present',
                    notes TEXT NULL,
                    recorded_by INT NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_att_session (cohort_id, user_id, session_date, session_title),
                    KEY idx_att_cohort_date (cohort_id, session_date),
                    KEY idx_att_user (user_id, session_date)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            return (bool)$ok;
        } catch (Throwable $ex) {
            error_log('sv_att_ensure_table failed: ' . $ex->getMessage());
            return false;
        }
    }
}

if (!function_exists('sv_att_schema')) {
    /**
     * Detects which table/columns hold attendance. Returns null when nothing usable exists.
     * Result keys: table, from (FROM/JOIN SQL aliasing a, cp, c), date, status, own.
     */
    function sv_att_schema(): ?array {
        static $schema = false;
        if ($schema !== false) return $schema;
        $schema = null;
        try {
            $conn = Database::getConnection();
            $detect = function () use ($conn): ?array {
                $tables = ['candidate_attendance', 'cohort_attendance', 'attendance', 'attendances',
                           'session_attendance', 'training_attendance', 'programme_attendance'];
                foreach ($tables as $t) {
                    $r = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($t) . "'");
                    if (!$r || !$r->num_rows) continue;
                    $cols = [];
                    $cr = $conn->query("SHOW COLUMNS FROM `$t`");
                    while ($cr && $x = $cr->fetch_assoc()) $cols[strtolower((string)$x['Field'])] = strtolower((string)$x['Type']);
                    $pick = static function (array $names, bool $dateOnly = false) use ($cols): ?string {
                        foreach ($names as $n) {
                            if (!isset($cols[$n]) || !preg_match('/^[a-z0-9_]+$/', $n)) continue;
                            if ($dateOnly && !preg_match('/date|time/', $cols[$n])) continue;
                            return $n;
                        }
                        return null;
                    };
                    $user   = $pick(['user_id', 'candidate_id', 'student_id']);
                    $cohort = $pick(['cohort_id']);
                    $part   = $pick(['participation_id', 'cohort_participant_id', 'participant_id']);
                    $date   = $pick(['session_date', 'attendance_date', 'date', 'session_at', 'attended_at', 'created_at'], true);
                    $status = $pick(['status', 'attendance_status', 'attended', 'is_present', 'present']);
                    if (!$date || !$status) continue;
                    if ($cohort && $user) {
                        $from = "FROM `$t` a JOIN cohorts c ON c.id=a.`$cohort` JOIN cohort_participants cp ON cp.cohort_id=c.id AND cp.user_id=a.`$user`";
                    } elseif ($part) {
                        $from = "FROM `$t` a JOIN cohort_participants cp ON cp.id=a.`$part` JOIN cohorts c ON c.id=cp.cohort_id";
                    } else {
                        continue;
                    }
                    return ['table' => $t, 'from' => $from, 'date' => $date, 'status' => $status, 'own' => ($t === 'candidate_attendance')];
                }
                return null;
            };
            $schema = $detect();
            if ($schema === null && sv_att_ensure_table()) $schema = $detect();
        } catch (Throwable $ex) {
            error_log('sv_att_schema failed: ' . $ex->getMessage());
            $schema = null;
        }
        return $schema;
    }
}

if (!function_exists('sv_att_record')) {
    /**
     * Record (or update) one attendance entry. Only writes to the built-in
     * candidate_attendance table, and only for a cohort the Supervisor owns and
     * a candidate who participates in it. Returns true when stored. Never throws.
     * Example: sv_att_record($sid, 4, 15, '2026-10-09', 'absent', 'Week 3 workshop', 'Sick');
     */
    function sv_att_record(int $supervisorId, int $cohortId, int $userId, string $date, string $status, string $title = '', ?string $notes = null): bool {
        try {
            $status = strtolower(trim($status));
            if ($supervisorId <= 0 || $cohortId <= 0 || $userId <= 0) return false;
            if (!in_array($status, ['present', 'late', 'absent', 'excused'], true)) return false;
            $ts = strtotime($date);
            if (!$ts || $ts > time()) return false;
            $schema = sv_att_schema();
            if (!$schema || empty($schema['own'])) return false;
            $stmt = Database::prepare(
                "SELECT cp.id FROM cohort_participants cp JOIN cohorts c ON c.id=cp.cohort_id WHERE cp.user_id=? AND cp.cohort_id=? AND c.supervisor_id=? LIMIT 1",
                'iii', [$userId, $cohortId, $supervisorId]
            );
            $ok = (bool)$stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$ok) return false;
            $day   = date('Y-m-d', $ts);
            $title = function_exists('mb_substr') ? mb_substr(trim($title), 0, 190) : substr(trim($title), 0, 190);
            $notes = $notes !== null ? trim($notes) : null;
            $notes = ($notes === '') ? null : $notes;
            $ins = Database::prepare(
                "INSERT INTO candidate_attendance (cohort_id,user_id,session_date,session_title,status,notes,recorded_by,created_at)
                 VALUES (?,?,?,?,?,?,?,NOW())
                 ON DUPLICATE KEY UPDATE status=VALUES(status),notes=VALUES(notes),recorded_by=VALUES(recorded_by),updated_at=NOW()",
                'iissssi', [$cohortId, $userId, $day, $title, $status, $notes, $supervisorId]
            );
            if ($ins) $ins->close();
            return true;
        } catch (Throwable $ex) {
            error_log('sv_att_record failed: ' . $ex->getMessage());
            return false;
        }
    }
}

if (!function_exists('sv_att_fetch')) {
    /**
     * Attendance rows for the Supervisor's cohorts (withdrawn candidates excluded),
     * newest first. Each row: uid, cid, sdate, st (present|late|absent|excused|other), names, cohort, programme.
     */
    function sv_att_fetch(int $supervisorId, int $days = 90, int $cohortId = 0): array {
        try {
            $s = sv_att_schema();
            if (!$s || $supervisorId <= 0) return [];
            $days = max(7, min(366, $days));
            $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
            $to   = date('Y-m-d');
            $d    = "DATE(a.`{$s['date']}`)";
            $raw  = "LOWER(TRIM(CAST(a.`{$s['status']}` AS CHAR)))";
            $sql  = "SELECT cp.user_id AS uid, c.id AS cid, $d AS sdate,
                        CASE
                            WHEN $raw IN ('present','attended','p','1','yes','y','true') THEN 'present'
                            WHEN $raw IN ('late','l','tardy') THEN 'late'
                            WHEN $raw IN ('excused','e','leave','permitted') THEN 'excused'
                            WHEN $raw IN ('absent','a','0','no','n','false','missed','no_show','no-show','no show') THEN 'absent'
                            ELSE 'other'
                        END AS st,
                        u.first_name, u.last_name, u.email,
                        c.name AS cohort_name, p.name AS programme_name, cp.status AS pstatus
                     {$s['from']}
                     JOIN users u ON u.id=cp.user_id
                     JOIN programmes p ON p.id=c.programme_id
                     WHERE c.supervisor_id=? AND cp.status<>'withdrawn' AND $d BETWEEN ? AND ?";
            $types  = 'iss';
            $params = [$supervisorId, $from, $to];
            if ($cohortId > 0) { $sql .= ' AND c.id=?'; $types .= 'i'; $params[] = $cohortId; }
            $sql .= ' ORDER BY sdate DESC LIMIT 50000';
            $stmt = Database::prepare($sql, $types, $params);
            $rows = [];
            $res  = $stmt->get_result();
            while ($res && $row = $res->fetch_assoc()) $rows[] = $row;
            $stmt->close();
            return $rows;
        } catch (Throwable $ex) {
            error_log('sv_att_fetch failed: ' . $ex->getMessage());
            return [];
        }
    }
}

if (!function_exists('sv_att_analyse')) {
    /** Turns raw rows into totals, weekly trend, per-cohort and per-candidate metrics. */
    function sv_att_analyse(array $rows, int $window = 30): array {
        $cfg    = sv_att_config();
        $window = max(7, min(180, $window));
        $today  = date('Y-m-d');
        $winStart  = date('Y-m-d', strtotime('-' . ($window - 1) . ' days'));
        $prevStart = date('Y-m-d', strtotime('-' . (2 * $window - 1) . ' days'));

        $weeks = [];
        for ($i = $cfg['weeks'] - 1; $i >= 0; $i--) {
            $end   = strtotime('-' . (7 * $i) . ' days');
            $start = strtotime('-6 days', $end);
            $weeks[] = ['start' => date('Y-m-d', $start), 'end' => date('Y-m-d', $end), 'label' => date('d M', $start), 'att' => 0, 'tot' => 0, 'rate' => null];
        }

        $blank = ['att' => 0, 'tot' => 0, 'present' => 0, 'late' => 0, 'absent' => 0, 'excused' => 0];
        $cur = $blank; $prev = $blank;
        $sessions = []; $cohorts = []; $cands = []; $hasData = false;
        $bump = static function (array &$b, string $st, bool $isAtt, bool $isTot): void {
            $b[$st]++;
            if ($isAtt) $b['att']++;
            if ($isTot) $b['tot']++;
        };

        foreach ($rows as $r) {
            $st = (string)($r['st'] ?? 'other');
            $dt = (string)($r['sdate'] ?? '');
            if ($st === 'other' || $dt === '' || $dt > $today) continue;
            $hasData = true;
            $isAtt = ($st === 'present' || $st === 'late');
            $isTot = ($isAtt || $st === 'absent');
            $uid = (int)$r['uid']; $cid = (int)$r['cid'];
            $key = $uid . '|' . $cid;

            if (!isset($cands[$key])) {
                $fn = trim((string)($r['first_name'] ?? '')); $ln = trim((string)($r['last_name'] ?? ''));
                $cands[$key] = ['uid' => $uid, 'cid' => $cid, 'first' => $fn, 'last' => $ln,
                    'name' => trim($fn . ' ' . $ln) ?: 'Candidate', 'email' => (string)($r['email'] ?? ''),
                    'cohort' => (string)($r['cohort_name'] ?? ''), 'programme' => (string)($r['programme_name'] ?? ''),
                    'pstatus' => (string)($r['pstatus'] ?? ''), 'w' => $blank, 'recs' => [], 'last_att' => null];
            }
            $cands[$key]['recs'][] = [$dt, $st];
            if ($isAtt && ($cands[$key]['last_att'] === null || $dt > $cands[$key]['last_att'])) $cands[$key]['last_att'] = $dt;

            foreach ($weeks as $i => $w) {
                if ($dt >= $w['start'] && $dt <= $w['end']) {
                    if ($isAtt) $weeks[$i]['att']++;
                    if ($isTot) $weeks[$i]['tot']++;
                    break;
                }
            }

            if ($dt >= $winStart) {
                $sessions[$cid . '|' . $dt] = true;
                $bump($cur, $st, $isAtt, $isTot);
                $bump($cands[$key]['w'], $st, $isAtt, $isTot);
                if (!isset($cohorts[$cid])) $cohorts[$cid] = ['cid' => $cid, 'name' => (string)($r['cohort_name'] ?? ''), 'programme' => (string)($r['programme_name'] ?? ''), 'att' => 0, 'tot' => 0, 'people' => []];
                if ($isAtt) $cohorts[$cid]['att']++;
                if ($isTot) $cohorts[$cid]['tot']++;
                $cohorts[$cid]['people'][$uid] = true;
            } elseif ($dt >= $prevStart) {
                $prev[$st]++;
                if ($isAtt) $prev['att']++;
                if ($isTot) $prev['tot']++;
            }
        }

        foreach ($weeks as $i => $w) $weeks[$i]['rate'] = $w['tot'] > 0 ? (int)round($w['att'] / $w['tot'] * 100) : null;

        $rateOf = static fn(array $b): ?int => $b['tot'] > 0 ? (int)round($b['att'] / $b['tot'] * 100) : null;
        $rate = $rateOf($cur); $prevRate = $rateOf($prev);

        foreach ($cohorts as $cid => $c) {
            $cohorts[$cid]['rate'] = $c['tot'] > 0 ? (int)round($c['att'] / $c['tot'] * 100) : null;
            $cohorts[$cid]['candidates'] = count($c['people']);
            unset($cohorts[$cid]['people']);
        }
        uasort($cohorts, static fn($a, $b) => ($a['rate'] ?? 101) <=> ($b['rate'] ?? 101));

        $critical = 0; $warning = 0;
        foreach ($cands as $key => $c) {
            usort($c['recs'], static fn($a, $b) => strcmp($b[0], $a[0])); // newest first
            $streak = 0;
            foreach ($c['recs'] as $rec) {
                if ($rec[1] === 'absent') $streak++;
                elseif ($rec[1] === 'present' || $rec[1] === 'late') break;
                // excused sessions neither extend nor break a streak
            }
            $cr = $rateOf($c['w']);
            $level = 'ok'; $reasons = [];
            if ($cr === null && $streak === 0) {
                $level = 'nodata';
            } elseif ($c['pstatus'] !== 'completed') {
                if ($streak >= $cfg['streak_critical']) { $level = 'critical'; $reasons[] = $streak . ' consecutive absences'; }
                elseif ($streak >= $cfg['streak_warn']) { $level = 'warning'; $reasons[] = $streak . ' consecutive absences'; }
                if ($cr !== null && $c['w']['tot'] >= $cfg['min_sessions']) {
                    if ($cr < $cfg['critical']) { $level = 'critical'; $reasons[] = 'Attendance ' . $cr . '% (below ' . $cfg['critical'] . '%)'; }
                    elseif ($cr < $cfg['warn']) { if ($level === 'ok') $level = 'warning'; $reasons[] = 'Attendance ' . $cr . '% (below ' . $cfg['warn'] . '%)'; }
                }
            }
            if ($level === 'critical') $critical++;
            if ($level === 'warning') $warning++;
            $cands[$key] = ['rate' => $cr, 'streak' => $streak, 'level' => $level, 'reasons' => $reasons] + $c;
            unset($cands[$key]['recs']);
        }
        $order = ['critical' => 0, 'warning' => 1, 'ok' => 2, 'nodata' => 3];
        $list = array_values($cands);
        usort($list, static fn($a, $b) =>
            [$order[$a['level']], $a['rate'] ?? 101, -$a['streak'], $a['name']] <=> [$order[$b['level']], $b['rate'] ?? 101, -$b['streak'], $b['name']]);
        $concerns = array_values(array_filter($list, static fn($c) => $c['level'] === 'critical' || $c['level'] === 'warning'));

        return [
            'has_data' => $hasData, 'window' => $window, 'cfg' => $cfg,
            'cur' => $cur, 'prev' => $prev, 'rate' => $rate, 'prev_rate' => $prevRate,
            'delta' => ($rate !== null && $prevRate !== null) ? $rate - $prevRate : null,
            'sessions' => count($sessions), 'weeks' => $weeks,
            'cohorts' => array_values($cohorts), 'candidates' => $list, 'concerns' => $concerns,
            'critical' => $critical, 'warning' => $warning,
        ];
    }
}

if (!function_exists('sv_att_color')) {
    function sv_att_color(?int $rate): string {
        if ($rate === null) return '#98a2b3';
        $cfg = sv_att_config();
        return $rate >= $cfg['warn'] ? '#16a34a' : ($rate >= $cfg['critical'] ? '#d97706' : '#dc2626');
    }
}

if (!function_exists('sv_att_styles')) {
    function sv_att_styles(): void {
        static $done = false;
        if ($done) return;
        $done = true;
        echo '<style>
.sv-att__tiles{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:18px}
.sv-att__tile{padding:14px;border:1px solid var(--sv-border,#e4e7ec);border-radius:14px;background:var(--sv-soft,#f8fafc)}
.sv-att__tile strong{display:block;font-size:24px;font-weight:800;line-height:1.1}
.sv-att__tile span{display:block;margin-top:5px;color:var(--sv-muted,#667085);font-size:11px;font-weight:700}
.sv-att__tile small{display:block;margin-top:6px;font-size:10px;font-weight:700;color:var(--sv-muted,#667085)}
.sv-att__up{color:#16a34a}.sv-att__down{color:#dc2626}
.sv-att__split{display:grid;grid-template-columns:minmax(0,1.3fr) minmax(0,1fr);gap:18px;margin-bottom:18px}
.sv-att__panel{padding:14px;border:1px solid var(--sv-border,#e4e7ec);border-radius:14px}
.sv-att__panel h4{margin:0 0 3px;font-size:13px;font-weight:800}
.sv-att__panel>p{margin:0 0 14px;color:var(--sv-muted,#667085);font-size:10px}
.sv-att__chart{position:relative;box-sizing:border-box;display:flex;align-items:flex-end;gap:8px;height:150px;padding-top:18px}
.sv-att__line{position:absolute;left:0;right:0;border-top:1px dashed #d97706;opacity:.6;pointer-events:none}
.sv-att__line em{position:absolute;right:0;top:-15px;font-size:9px;font-style:normal;font-weight:700;color:#d97706}
.sv-att__col{flex:1;min-width:0;height:100%;display:flex;flex-direction:column;justify-content:flex-end;align-items:center}
.sv-att__bar{width:100%;max-width:38px;min-height:3px;border-radius:7px 7px 0 0}
.sv-att__val{margin-bottom:4px;font-size:10px;font-weight:800}
.sv-att__labels{display:flex;gap:8px;margin-top:6px}
.sv-att__labels span{flex:1;min-width:0;text-align:center;color:var(--sv-muted,#667085);font-size:9px;white-space:nowrap}
.sv-att__row{display:flex;align-items:center;gap:12px;padding:9px 0;border-bottom:1px solid var(--sv-border,#e4e7ec);color:inherit;text-decoration:none}
.sv-att__row:last-child{border-bottom:0}
.sv-att__row-main{min-width:0;flex:1}
.sv-att__row-main strong{display:block;overflow:hidden;font-size:12px;text-overflow:ellipsis;white-space:nowrap}
.sv-att__row-main span{display:block;margin-top:2px;color:var(--sv-muted,#667085);font-size:10px}
.sv-att__meter{width:110px;flex:0 0 110px}
.sv-att__meter b{display:block;margin-bottom:4px;font-size:11px;text-align:right}
.sv-att__track{height:7px;border-radius:999px;background:var(--sv-border,#e4e7ec);overflow:hidden}
.sv-att__fill{height:100%;border-radius:999px}
.sv-att__badge{display:inline-block;padding:3px 9px;border-radius:999px;font-size:10px;font-weight:800;white-space:nowrap}
.sv-att__badge--critical{background:rgba(220,38,38,.12);color:#dc2626}
.sv-att__badge--warning{background:rgba(217,119,6,.14);color:#b45309}
.sv-att__badge--ok{background:rgba(22,163,74,.12);color:#16a34a}
.sv-att__badge--nodata{background:rgba(152,162,179,.18);color:#667085}
.sv-att__reasons{display:block;margin-top:3px;color:var(--sv-muted,#667085);font-size:10px}
.sv-att__sub{margin:0 0 10px;font-size:13px;font-weight:800}
.sv-att__empty{padding:34px 16px;text-align:center;color:var(--sv-muted,#667085)}
.sv-att__empty i{display:block;margin-bottom:10px;font-size:26px;color:#98a2b3}
.sv-att__empty strong{display:block;font-size:13px;color:inherit}
.sv-att__filters{display:flex;flex-wrap:wrap;align-items:flex-end;gap:12px;margin-bottom:18px}
.sv-att__filters .sv-field{min-width:170px}
.sv-att__nums{white-space:nowrap;font-size:11px}
html[data-theme="dark"] .sv-att__tile{background:rgba(255,255,255,.05);border-color:rgba(255,255,255,.09)}
html[data-theme="dark"] .sv-att__panel,html[data-theme="dark"] .sv-att__row{border-color:rgba(255,255,255,.09)}
html[data-theme="dark"] .sv-att__track{background:rgba(255,255,255,.1)}
@media(max-width:900px){.sv-att__tiles{grid-template-columns:repeat(2,minmax(0,1fr))}.sv-att__split{grid-template-columns:minmax(0,1fr)}}
@media(max-width:480px){.sv-att__meter{width:80px;flex-basis:80px}}
</style>';
    }
}

if (!function_exists('sv_att_render_overview')) {
    /** Summary tiles + weekly trend + per-cohort breakdown. */
    function sv_att_render_overview(array $a): void {
        $cfg = $a['cfg']; $win = (int)$a['window'];
        $rate = $a['rate']; $delta = $a['delta'];
        $atRisk = (int)$a['critical'] + (int)$a['warning'];

        echo '<div class="sv-att__tiles">';
        echo '<div class="sv-att__tile"><strong style="color:' . e(sv_att_color($rate)) . '">' . ($rate !== null ? (int)$rate . '%' : '—') . '</strong><span>Attendance rate</span>';
        if ($delta !== null) {
            $cls = $delta > 0 ? 'sv-att__up' : ($delta < 0 ? 'sv-att__down' : '');
            $ico = $delta > 0 ? 'fa-arrow-trend-up' : ($delta < 0 ? 'fa-arrow-trend-down' : 'fa-minus');
            echo '<small class="' . $cls . '"><i class="fas ' . $ico . '"></i> ' . ($delta > 0 ? '+' : '') . (int)$delta . ' pts vs previous ' . $win . ' days</small>';
        } else {
            echo '<small>Last ' . $win . ' days</small>';
        }
        echo '</div>';
        echo '<div class="sv-att__tile"><strong>' . number_format((int)$a['sessions']) . '</strong><span>Sessions held</span><small>' . number_format((int)$a['cur']['absent']) . ' absences · ' . number_format((int)$a['cur']['late']) . ' late · ' . number_format((int)$a['cur']['excused']) . ' excused</small></div>';
        echo '<div class="sv-att__tile"><strong style="color:' . ($atRisk > 0 ? '#d97706' : '#16a34a') . '">' . number_format($atRisk) . '</strong><span>Candidates with concerns</span><small>Below ' . (int)$cfg['warn'] . '% or ' . (int)$cfg['streak_warn'] . '+ missed in a row</small></div>';
        echo '<div class="sv-att__tile"><strong style="color:' . ((int)$a['critical'] > 0 ? '#dc2626' : '#16a34a') . '">' . number_format((int)$a['critical']) . '</strong><span>Critical</span><small>Below ' . (int)$cfg['critical'] . '% or ' . (int)$cfg['streak_critical'] . '+ missed in a row</small></div>';
        echo '</div>';

        // Weekly trend
        $summary = [];
        foreach ($a['weeks'] as $w) $summary[] = $w['label'] . ': ' . ($w['rate'] !== null ? $w['rate'] . '%' : 'no data');
        echo '<div class="sv-att__split"><div class="sv-att__panel"><h4>Weekly attendance trend</h4><p>Attendance rate per 7-day period, last ' . (int)$cfg['weeks'] . ' weeks. Dashed line = ' . (int)$cfg['warn'] . '% target.</p>';
        echo '<div class="sv-att__chart" role="img" aria-label="' . e('Weekly attendance rate. ' . implode('; ', $summary)) . '"><div class="sv-att__line" style="bottom:calc((100% - 36px) * ' . ((int)$cfg['warn'] / 100) . ')"><em>' . (int)$cfg['warn'] . '%</em></div>';
        foreach ($a['weeks'] as $w) {
            $r = $w['rate'];
            echo '<div class="sv-att__col" title="' . e($w['label'] . ' – ' . ($r !== null ? $r . '% (' . $w['att'] . ' of ' . $w['tot'] . ')' : 'no sessions recorded')) . '">';
            echo '<div class="sv-att__val" style="color:' . e(sv_att_color($r)) . '">' . ($r !== null ? (int)$r . '%' : '–') . '</div>';
            echo '<div class="sv-att__bar" style="height:calc((100% - 18px) * ' . ($r !== null ? max(0.02, $r / 100) : 0.02) . ');background:' . e(sv_att_color($r)) . ';opacity:' . ($r !== null ? '1' : '.35') . '"></div></div>';
        }
        echo '</div><div class="sv-att__labels">';
        foreach ($a['weeks'] as $w) echo '<span>' . e($w['label']) . '</span>';
        echo '</div></div>';

        // Cohorts
        echo '<div class="sv-att__panel"><h4>Attendance by cohort</h4><p>Last ' . $win . ' days, lowest first.</p>';
        if (!$a['cohorts']) {
            echo '<div class="sv-att__empty"><i class="fas fa-layer-group"></i><strong>No cohort data</strong></div>';
        } else {
            foreach (array_slice($a['cohorts'], 0, 6) as $c) {
                $r = $c['rate'];
                echo '<a class="sv-att__row" href="' . e(url('supervisor/cohort_view.php?id=' . (int)$c['cid'])) . '">';
                echo '<div class="sv-att__row-main"><strong>' . e($c['name']) . '</strong><span>' . e($c['programme']) . ' · ' . number_format((int)$c['candidates']) . ' candidates</span></div>';
                echo '<div class="sv-att__meter"><b style="color:' . e(sv_att_color($r)) . '">' . ($r !== null ? (int)$r . '%' : '—') . '</b><div class="sv-att__track"><div class="sv-att__fill" style="width:' . (int)($r ?? 0) . '%;background:' . e(sv_att_color($r)) . '"></div></div></div></a>';
            }
        }
        echo '</div></div>';
    }
}

if (!function_exists('sv_att_render_candidates')) {
    /** Candidate table. $limit = 0 shows everything passed in. */
    function sv_att_render_candidates(array $list, int $limit = 0): void {
        $labels = ['critical' => 'Critical', 'warning' => 'At risk', 'ok' => 'On track', 'nodata' => 'No data'];
        $total = count($list);
        if ($limit > 0) $list = array_slice($list, 0, $limit);
        echo '<div class="sv-table-wrap"><table class="sv-table"><thead><tr><th>Candidate</th><th>Cohort</th><th>Attendance</th><th>Present / Late / Absent</th><th>Missed in a row</th><th>Last attended</th><th>Status</th><th></th></tr></thead><tbody>';
        foreach ($list as $c) {
            $r = $c['rate'];
            echo '<tr><td><div class="sv-person"><div class="sv-avatar">' . e(function_exists('sv_initials') ? sv_initials($c['first'], $c['last']) : 'SV') . '</div><div class="sv-person__copy"><strong>' . e($c['name']) . '</strong><span>' . e($c['email']) . '</span></div></div></td>';
            echo '<td>' . e($c['cohort']) . '</td>';
            echo '<td style="min-width:120px"><div class="sv-att__meter" style="width:100%"><b style="text-align:left;color:' . e(sv_att_color($r)) . '">' . ($r !== null ? (int)$r . '%' : '—') . '</b><div class="sv-att__track"><div class="sv-att__fill" style="width:' . (int)($r ?? 0) . '%;background:' . e(sv_att_color($r)) . '"></div></div></div></td>';
            echo '<td class="sv-att__nums">' . (int)$c['w']['present'] . ' / ' . (int)$c['w']['late'] . ' / ' . (int)$c['w']['absent'] . ((int)$c['w']['excused'] > 0 ? ' <span style="color:var(--sv-muted,#667085)">(+' . (int)$c['w']['excused'] . ' excused)</span>' : '') . '</td>';
            echo '<td>' . ((int)$c['streak'] > 0 ? '<strong>' . (int)$c['streak'] . '</strong>' : '0') . '</td>';
            echo '<td>' . e($c['last_att'] ? sv_date($c['last_att']) : 'Never') . '</td>';
            echo '<td><span class="sv-att__badge sv-att__badge--' . e($c['level']) . '">' . e($labels[$c['level']] ?? '—') . '</span>';
            if ($c['reasons']) echo '<span class="sv-att__reasons">' . e(implode(' · ', $c['reasons'])) . '</span>';
            echo '</td><td><a class="sv-icon-btn" href="' . e(url('supervisor/candidate_view.php?id=' . (int)$c['uid'] . '&cohort_id=' . (int)$c['cid'])) . '" title="View candidate"><i class="fas fa-eye"></i></a></td></tr>';
        }
        echo '</tbody></table></div>';
        if ($limit > 0 && $total > $limit) echo '<p style="margin:12px 4px 0;color:var(--sv-muted,#667085);font-size:11px">Showing the ' . $limit . ' most urgent of ' . $total . ' candidates.</p>';
    }
}

if (!function_exists('sv_att_render_dashboard')) {
    /** The Attendance Monitoring section for the Supervisor Dashboard. */
    function sv_att_render_dashboard(int $supervisorId): void {
        sv_att_styles();
        $cfg = sv_att_config();
        $schema = null; $a = null;
        try {
            $schema = sv_att_schema();
            if ($schema) $a = sv_att_analyse(sv_att_fetch($supervisorId, 90), (int)$cfg['window']);
        } catch (Throwable $ex) {
            error_log('sv_att_render_dashboard failed: ' . $ex->getMessage());
        }
        echo '<section class="sv-card" id="attendance-monitoring" style="margin-top:18px"><div class="sv-card__header"><div><h3>Attendance Monitoring</h3><p>Attendance at programme and training sessions across your cohorts · last ' . (int)$cfg['window'] . ' days.</p></div>';
        echo '<a class="sv-card__link" href="' . e(url('supervisor/attendance.php')) . '">View details <i class="fas fa-arrow-right"></i></a></div><div class="sv-card__body">';
        if (!$schema || !$a) {
            echo '<div class="sv-att__empty"><i class="fas fa-calendar-xmark"></i><strong>Attendance data is not available</strong><span>The attendance records could not be loaded right now.</span></div>';
        } elseif (!$a['has_data']) {
            echo '<div class="sv-att__empty"><i class="fas fa-calendar-check"></i><strong>No attendance recorded yet</strong><span>Trends and attendance concerns will appear here once session attendance is recorded for your cohorts.</span></div>';
        } else {
            sv_att_render_overview($a);
            echo '<h4 class="sv-att__sub">Candidates needing attention</h4>';
            if (!$a['concerns']) {
                echo '<div class="sv-att__empty" style="padding:22px 16px"><i class="fas fa-circle-check" style="color:#16a34a"></i><strong>No attendance concerns</strong><span>Every candidate with recorded sessions is meeting the ' . (int)$cfg['warn'] . '% attendance target.</span></div>';
            } else {
                sv_att_render_candidates($a['concerns'], 6);
            }
        }
        echo '</div></section>';
    }
}
