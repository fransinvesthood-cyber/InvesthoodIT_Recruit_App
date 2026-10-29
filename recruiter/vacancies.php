<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('recruiter');

$user        = current_user();
$flashes     = render_flashes();
$currentPage = 'vacancies';
$pageTitle   = 'Vacancy Management';

require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/_vacancy_helpers.php';

$conn = Database::getConnection();

$recruiterId = (int)($user['id'] ?? $user['user_id'] ?? 0);

if ($recruiterId <= 0) {
    http_response_code(403);
    exit('Invalid Recruiter account.');
}

$hasOpp         = rc_has_table($conn, 'opportunities');
$hasApplication = rc_has_table($conn, 'applications');
$cols           = $hasOpp ? vm_columns($conn) : [];
$statusOptions  = vm_status_options($cols);
$typeOptions    = vm_enum_options($cols, 'type');
$self           = url('recruiter/vacancies.php');

/* ==================================================================
 | POST handling (post -> redirect -> get)
 * ==================================================================*/

if ($hasOpp && $_SERVER['REQUEST_METHOD'] === 'POST') {

    rc_verify_csrf();

    $action = trim((string)($_POST['action'] ?? ''));

    try {

        /* -------------------- Create a vacancy -------------------- */
        if ($action === 'create') {

            $errors = [];

            $title    = trim((string)($_POST['title'] ?? ''));
            $org      = trim((string)($_POST['organisation'] ?? ''));
            $type     = trim((string)($_POST['type'] ?? ''));
            $province = trim((string)($_POST['province'] ?? ''));
            $city     = trim((string)($_POST['city'] ?? ''));
            $openRaw  = trim((string)($_POST['application_open_date'] ?? ''));
            $closeRaw = trim((string)($_POST['application_close_date'] ?? ''));
            $posRaw   = trim((string)($_POST['available_positions'] ?? ''));
            $status   = trim((string)($_POST['status'] ?? ''));

            if (!isset($cols['title']))            $errors[] = 'The opportunities table has no title column.';
            if ($title === '')                     $errors[] = 'Please enter a vacancy title.';
            if (mb_strlen($title) > 255)           $errors[] = 'The title is too long (255 characters max).';
            if (mb_strlen($org) > 255)             $errors[] = 'The organisation is too long (255 characters max).';
            if (mb_strlen($type) > 100)            $errors[] = 'The type is too long (100 characters max).';
            if (mb_strlen($province) > 100)        $errors[] = 'The province is too long (100 characters max).';
            if (mb_strlen($city) > 100)            $errors[] = 'The city is too long (100 characters max).';
            if ($typeOptions !== null && $type !== '' && !in_array($type, $typeOptions, true)) {
                $errors[] = 'Please choose a valid vacancy type.';
            }

            $openDate  = vm_parse_date($openRaw);
            $closeDate = vm_parse_date($closeRaw);
            if ($openRaw !== '' && !$openDate)     $errors[] = 'The opening date is not valid.';
            if ($closeRaw !== '' && !$closeDate)   $errors[] = 'The closing date is not valid.';
            if ($openDate && $closeDate && $closeDate < $openDate) {
                $errors[] = 'The closing date cannot be before the opening date.';
            }

            $positions = null;
            if ($posRaw !== '') {
                if (!ctype_digit($posRaw) || (int)$posRaw < 1 || (int)$posRaw > 10000) {
                    $errors[] = 'Available positions must be a whole number between 1 and 10000.';
                } else {
                    $positions = (int)$posRaw;
                }
            }

            if ($status === '') $status = in_array('draft', $statusOptions, true) ? 'draft' : $statusOptions[0];
            if (!in_array($status, $statusOptions, true)) {
                $errors[] = 'Please choose a valid status.';
            } elseif (in_array($status, VM_OPEN_STATUSES, true) && $closeDate && $closeDate < new DateTimeImmutable('today')) {
                $errors[] = 'A published vacancy cannot have a closing date in the past.';
            }

            // Only columns that exist and have a value are written.
            $data = [];
            $add  = static function (string $name, $value, string $t) use (&$data, $cols): void {
                if (isset($cols[$name]) && $value !== null && $value !== '') {
                    $data[$name] = ['v' => $value, 't' => $t];
                }
            };
            $add('title', $title, 's');
            $add('organisation', $org, 's');
            $add('type', $type, 's');
            $add('province', $province, 's');
            $add('city', $city, 's');
            $add('application_open_date', $openDate ? $openDate->format('Y-m-d') : null, 's');
            $add('application_close_date', $closeDate ? $closeDate->format('Y-m-d') : null, 's');
            $add('available_positions', $positions, 'i');
            $add('status', $status, 's');

            if (!$errors) {
                $blockers = vm_create_blockers($cols, array_keys($data));
                if ($blockers) {
                    $errors[] = 'The database requires a value for: ' . implode(', ', $blockers)
                        . '. Fill those in on this form if available, or create the vacancy in the existing opportunity management.';
                }
            }

            if ($errors) {
                rc_flash('danger', implode(' ', $errors));
            } else {
                $names  = array_keys($data);
                $colSql = implode(',', array_map(static fn($n) => '`' . $n . '`', $names));
                $marks  = implode(',', array_fill(0, count($names), '?'));
                $types  = '';
                $vals   = [];
                foreach ($data as $d) {
                    $types .= $d['t'];
                    $vals[] = $d['v'];
                }

                $stmt = $conn->prepare("INSERT INTO opportunities ($colSql) VALUES ($marks)");
                if (!$stmt) {
                    throw new RuntimeException($conn->error);
                }
                $stmt->bind_param($types, ...$vals);
                $stmt->execute();
                $newId = (int)$conn->insert_id;
                $stmt->close();

                rc_activity(
                    $conn, $recruiterId, 'vacancy_created',
                    'Created vacancy: ' . $title . ' (' . $status . ')',
                    'opportunity', $newId
                );
                rc_flash('success', 'Vacancy created.');
                header('Location: ' . url('recruiter/vacancy_view.php?id=' . $newId));
                exit;
            }
        }

        /* -------------------- Change status -------------------- */
        elseif ($action === 'set_status') {

            $id  = (int)($_POST['opportunity_id'] ?? 0);
            $new = trim((string)($_POST['status'] ?? ''));

            $stmt = $conn->prepare('SELECT * FROM opportunities WHERE id = ? LIMIT 1');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$row) {
                rc_flash('danger', 'Vacancy not found.');
            } elseif (!in_array($new, $statusOptions, true)) {
                rc_flash('danger', 'Please choose a valid status.');
            } elseif ((string)$row['status'] === $new) {
                rc_flash('info', 'The vacancy already has that status.');
            } elseif (
                in_array($new, VM_OPEN_STATUSES, true)
                && !empty($row['application_close_date'])
                && vm_days_until((string)$row['application_close_date']) !== null
                && vm_days_until((string)$row['application_close_date']) < 0
            ) {
                rc_flash('danger', 'This vacancy\'s closing date has passed, so it cannot be published. Update the closing date in the existing opportunity management first.');
            } else {
                $stmt = $conn->prepare('UPDATE opportunities SET status = ? WHERE id = ?');
                $stmt->bind_param('si', $new, $id);
                $stmt->execute();
                $stmt->close();

                rc_activity(
                    $conn, $recruiterId, 'vacancy_status_changed',
                    'Vacancy #' . $id . ' status ' . (string)$row['status'] . ' -> ' . $new,
                    'opportunity', $id
                );
                rc_flash('success', 'Vacancy status updated to ' . rc_label($new) . '.');
            }
        }

        else {
            rc_flash('danger', 'Unknown action.');
        }

    } catch (Throwable $t) {
        error_log('vacancies.php: ' . $t->getMessage());
        rc_flash('danger', 'Something went wrong while saving. Please try again.');
    }

    header('Location: ' . $self);
    exit;
}

/* ==================================================================
 | Data for display
 * ==================================================================*/

$q            = trim((string)($_GET['q'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? 'all'));
$validFilters = array_merge(['all', 'open', 'attention'], $statusOptions);
if (!in_array($statusFilter, $validFilters, true)) $statusFilter = 'all';

$vacancies = [];
$counts    = [];

if ($hasOpp) {
    $res = $conn->query('SELECT * FROM opportunities ORDER BY id DESC LIMIT 1000');
    if ($res) {
        while ($r = $res->fetch_assoc()) $vacancies[] = $r;
        $res->free();
    }
    $counts = vm_stage_counts($conn);
}

$stats = [
    'total'      => count($vacancies),
    'open'       => 0,
    'closing'    => 0,
    'active'     => 0,
    'attention'  => 0,
    'flagged'    => 0,
];

foreach ($vacancies as $i => $v) {

    $id     = (int)$v['id'];
    $status = (string)($v['status'] ?? '');
    $c      = $counts[$id] ?? vm_empty_counts();

    $vacancies[$i]['_counts']  = $c;
    $vacancies[$i]['_active']  = vm_active_count($c);
    $vacancies[$i]['_days']    = vm_days_until($v['application_close_date'] ?? null);
    $vacancies[$i]['_flags']   = vm_attention_flags($v, $c);
    if ($vacancies[$i]['_flags']) $stats['flagged']++;

    if (in_array($status, VM_OPEN_STATUSES, true)) {
        $stats['open']++;
        $d = $vacancies[$i]['_days'];
        if ($d !== null && $d >= 0 && $d <= 7) $stats['closing']++;
    }
    $stats['active']    += $vacancies[$i]['_active'];
    $stats['attention'] += (int)$c['overdue'];
}

$visible = [];
foreach ($vacancies as $v) {

    $status = (string)($v['status'] ?? '');

    if ($statusFilter === 'open' && !in_array($status, VM_OPEN_STATUSES, true)) continue;
    if ($statusFilter === 'attention' && !$v['_flags']) continue;
    if (!in_array($statusFilter, ['all', 'open', 'attention'], true) && $status !== $statusFilter) continue;

    if ($q !== '') {
        $hay = strtolower(implode(' ', [
            (string)($v['title'] ?? ''), (string)($v['organisation'] ?? ''),
            (string)($v['city'] ?? ''), (string)($v['province'] ?? ''), (string)($v['type'] ?? ''),
        ]));
        if (!str_contains($hay, strtolower($q))) continue;
    }

    $visible[] = $v;
}

$statusClass = static function (string $s): string {
    if (in_array($s, ['published', 'closing_soon'], true)) return 'rc-status--success';
    if ($s === 'closed') return 'rc-status--danger';
    return '';
};

$csrf = rc_csrf();

require __DIR__ . '/_layout_start.php';
?>

<style>
.vm-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-bottom:22px}
.vm-stat{padding:20px;border-radius:18px;background:rgba(127,127,127,.08);border:1px solid rgba(127,127,127,.18);color:inherit;text-decoration:none;display:block}
.vm-stat i{display:block;margin-bottom:12px;font-size:20px}
.vm-stat strong{display:block;font-size:26px;line-height:1}
.vm-stat span{display:block;margin-top:8px;font-size:13px;opacity:.7}
.vm-stat small{display:block;margin-top:5px;font-size:11px;opacity:.55}
.vm-toolbar{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;margin-bottom:16px}
.vm-toolbar label{display:flex;flex-direction:column;gap:6px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;opacity:.85}
.vm-input,.vm-select{box-sizing:border-box;padding:10px 12px;border-radius:10px;border:1px solid rgba(127,127,127,.35);background:transparent;color:inherit;font:inherit;min-width:200px}
.vm-select option{color:#172b4d}
.vm-cell small{display:block;opacity:.65;margin-top:3px}
.vm-chips{display:flex;flex-wrap:wrap;gap:5px;max-width:340px}
.vm-chip{padding:3px 9px;border-radius:999px;font-size:11px;font-weight:600;background:rgba(127,127,127,.12)}
.vm-warn{color:#d97706;font-weight:700}
.vm-actions{display:flex;flex-direction:column;gap:6px;min-width:150px}
.vm-inline{display:flex;gap:6px}
.vm-inline .vm-select{min-width:0;flex:1;padding:6px 8px;font-size:12px}
.vm-create summary{cursor:pointer;list-style:none;display:inline-flex}
.vm-create summary::-webkit-details-marker{display:none}
.vm-form{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-top:18px}
.vm-field{display:flex;flex-direction:column;gap:6px}
.vm-field label{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;opacity:.8}
.vm-field .vm-input,.vm-field .vm-select{width:100%;min-width:0}
.vm-form__submit{grid-column:1/-1;display:flex;justify-content:flex-end}
.vm-note{margin-top:14px;font-size:12px;opacity:.65}
@media(max-width:1000px){.vm-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.vm-form{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:560px){.vm-stats,.vm-form{grid-template-columns:1fr}}
</style>

<section class="rc-hero">
    <div>
        <span class="rc-eyebrow"><i class="fas fa-briefcase"></i> Vacancy management</span>
        <h2>Vacancy Management &amp; Recruitment Progress</h2>
        <p>
            Manage job vacancies, monitor the applications they attract and
            track every applicant through the recruitment stages.
        </p>
        <div class="rc-hero__actions">
            <a class="rc-btn rc-btn--glass" href="<?= url('recruiter/follow_up.php') ?>">
                <i class="fas fa-route"></i> Follow-Up Pipeline
            </a>
            <a class="rc-btn rc-btn--glass" href="<?= url('recruiter/interviews.php') ?>">
                <i class="fas fa-calendar-check"></i> Interviews
            </a>
        </div>
    </div>
</section>

<?php if (!$hasOpp): ?>

    <div class="rc-card"><div class="rc-card__body">
        <div class="rc-empty">
            <i class="fas fa-database"></i>
            <strong>Opportunities table not found</strong>
            <span>Vacancies are the existing <code>opportunities</code> records. Apply the project's opportunity migration first.</span>
        </div>
    </div></div>

<?php else: ?>

<section class="vm-stats" aria-label="Vacancy summary">
    <a class="vm-stat" href="<?= e($self) ?>">
        <i class="fas fa-briefcase"></i><strong><?= number_format($stats['total']) ?></strong>
        <span>Total vacancies</span>
    </a>
    <a class="vm-stat" href="<?= e($self . '?status=open') ?>">
        <i class="fas fa-bullhorn"></i><strong><?= number_format($stats['open']) ?></strong>
        <span>Open for applications</span>
        <small><?= number_format($stats['closing']) ?> closing within 7 days</small>
    </a>
    <div class="vm-stat">
        <i class="fas fa-file-lines"></i><strong><?= number_format($stats['active']) ?></strong>
        <span>Applications in progress</span>
        <small>Excludes finished outcomes</small>
    </div>
    <a class="vm-stat" href="<?= e($self . '?status=attention') ?>">
        <i class="fas fa-clock"></i><strong><?= number_format($stats['flagged']) ?></strong>
        <span>Vacancies needing attention</span>
        <small><?= number_format($stats['attention']) ?> stale applications</small>
    </a>
</section>

<section class="rc-card rc-space">

    <div class="rc-card__header">
        <div>
            <span class="rc-eyebrow">Create</span>
            <h3>New vacancy</h3>
            <p>New vacancies are saved as a draft unless you choose another status.</p>
        </div>
    </div>

    <div class="rc-card__body">
        <details class="vm-create">
            <summary class="rc-btn rc-btn--primary"><i class="fas fa-plus"></i> Add vacancy</summary>

            <form class="vm-form" method="post" action="<?= e($self) ?>">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="create">

                <div class="vm-field" style="grid-column:span 2">
                    <label for="vmTitle">Vacancy title</label>
                    <input class="vm-input" id="vmTitle" name="title" maxlength="255" required>
                </div>

                <div class="vm-field">
                    <label for="vmOrg">Organisation</label>
                    <input class="vm-input" id="vmOrg" name="organisation" maxlength="255">
                </div>

                <div class="vm-field">
                    <label for="vmType">Type</label>
                    <?php if ($typeOptions !== null): ?>
                        <select class="vm-select" id="vmType" name="type">
                            <option value="">Select…</option>
                            <?php foreach ($typeOptions as $opt): ?>
                                <option value="<?= e($opt) ?>"><?= e(rc_label($opt)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input class="vm-input" id="vmType" name="type" maxlength="100">
                    <?php endif; ?>
                </div>

                <div class="vm-field">
                    <label for="vmProvince">Province</label>
                    <input class="vm-input" id="vmProvince" name="province" maxlength="100">
                </div>

                <div class="vm-field">
                    <label for="vmCity">City</label>
                    <input class="vm-input" id="vmCity" name="city" maxlength="100">
                </div>

                <div class="vm-field">
                    <label for="vmOpen">Applications open</label>
                    <input class="vm-input" type="date" id="vmOpen" name="application_open_date">
                </div>

                <div class="vm-field">
                    <label for="vmClose">Applications close</label>
                    <input class="vm-input" type="date" id="vmClose" name="application_close_date">
                </div>

                <div class="vm-field">
                    <label for="vmPositions">Available positions</label>
                    <input class="vm-input" type="number" id="vmPositions" name="available_positions" min="1" max="10000" step="1">
                </div>

                <div class="vm-field">
                    <label for="vmStatus">Status</label>
                    <select class="vm-select" id="vmStatus" name="status">
                        <?php foreach ($statusOptions as $opt): ?>
                            <option value="<?= e($opt) ?>" <?= $opt === 'draft' ? 'selected' : '' ?>><?= e(rc_label($opt)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="vm-form__submit">
                    <button type="submit" class="rc-btn rc-btn--primary"><i class="fas fa-check"></i> Create vacancy</button>
                </div>
            </form>
        </details>
    </div>
</section>

<section class="rc-card rc-space" id="vacancies">

    <div class="rc-card__header">
        <div>
            <span class="rc-eyebrow">Vacancies</span>
            <h3>All vacancies</h3>
            <p>Application volume and stage breakdown for each vacancy.</p>
        </div>
    </div>

    <div class="rc-card__body">

        <form class="vm-toolbar" method="get" action="<?= e($self) ?>">
            <label>Search
                <input class="vm-input" type="search" name="q" value="<?= e($q) ?>" placeholder="Title, organisation, location">
            </label>
            <label>Show
                <select class="vm-select" name="status">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All vacancies</option>
                    <option value="open" <?= $statusFilter === 'open' ? 'selected' : '' ?>>Open for applications</option>
                    <option value="attention" <?= $statusFilter === 'attention' ? 'selected' : '' ?>>Needs attention</option>
                    <?php foreach ($statusOptions as $opt): ?>
                        <option value="<?= e($opt) ?>" <?= $statusFilter === $opt ? 'selected' : '' ?>><?= e(rc_label($opt)) ?> only</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="rc-btn rc-btn--secondary" type="submit"><i class="fas fa-filter"></i> Apply</button>
            <a class="rc-btn rc-btn--secondary" href="<?= e($self) ?>">Reset</a>
        </form>

        <?php if (!$visible): ?>

            <div class="rc-empty">
                <i class="fas fa-briefcase"></i>
                <strong>No vacancies found</strong>
                <span><?= ($q !== '' || $statusFilter !== 'all') ? 'Nothing matches the current filter.' : 'Create your first vacancy above.' ?></span>
            </div>

        <?php else: ?>

            <div class="rc-table-wrap">
                <table class="rc-table">
                    <thead>
                        <tr>
                            <th>Vacancy</th>
                            <th>Status</th>
                            <th>Closing</th>
                            <th>Applications</th>
                            <th>Stage breakdown</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($visible as $v):
                        $id     = (int)$v['id'];
                        $status = (string)($v['status'] ?? '');
                        $c      = $v['_counts'];
                        $loc    = trim((string)($v['city'] ?? '') . ', ' . (string)($v['province'] ?? ''), ' ,');
                    ?>
                        <tr>
                            <td class="vm-cell">
                                <strong><?= e((string)($v['title'] ?? 'Untitled vacancy')) ?></strong>
                                <?php if (!empty($v['organisation'])): ?><small><?= e((string)$v['organisation']) ?></small><?php endif; ?>
                                <?php if ($loc !== ''): ?><small><?= e($loc) ?></small><?php endif; ?>
                                <?php if (!empty($v['type'])): ?><small><?= e(rc_label((string)$v['type'])) ?></small><?php endif; ?>
                                <?php foreach ($v['_flags'] as $f): ?>
                                    <small class="vm-warn" style="<?= $f['level'] === 'danger' ? 'color:#dc2626' : '' ?>"><i class="fas fa-triangle-exclamation"></i> <?= e($f['text']) ?></small>
                                <?php endforeach; ?>
                            </td>

                            <td class="vm-cell">
                                <span class="rc-status <?= e($statusClass($status)) ?>"><?= e(rc_label($status)) ?></span>
                                <?php if (!empty($v['available_positions'])): ?>
                                    <small><?= number_format((int)$v['available_positions']) ?> position<?= (int)$v['available_positions'] === 1 ? '' : 's' ?></small>
                                <?php endif; ?>
                            </td>

                            <td class="vm-cell">
                                <?= e(rc_date((string)($v['application_close_date'] ?? ''), 'Not set')) ?>
                                <small><?= e(vm_closing_label($v['application_close_date'] ?? null, $status)) ?></small>
                            </td>

                            <td class="vm-cell">
                                <strong><?= number_format((int)$c['total']) ?></strong> total
                                <small><?= number_format((int)$v['_active']) ?> in progress</small>
                                <?php $acc = (int)($c['stages']['offer_accepted'] ?? 0); if ((int)($v['available_positions'] ?? 0) > 0): ?>
                                    <small><?= $acc ?> of <?= (int)$v['available_positions'] ?> positions filled</small>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if (!$c['stages']): ?>
                                    <span class="vm-chip">No applications yet</span>
                                <?php else: ?>
                                    <div class="vm-chips">
                                        <?php foreach (VM_STAGES as $key => $label):
                                            if (empty($c['stages'][$key])) continue; ?>
                                            <span class="vm-chip"><?= e($label) ?> · <?= (int)$c['stages'][$key] ?></span>
                                        <?php endforeach; ?>
                                        <?php foreach ($c['stages'] as $key => $n):
                                            if (isset(VM_STAGES[$key])) continue; ?>
                                            <span class="vm-chip"><?= e(rc_label((string)$key)) ?> · <?= (int)$n ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="vm-actions">
                                    <a class="rc-btn rc-btn--primary rc-btn--small" href="<?= e(url('recruiter/vacancy_view.php?id=' . $id)) ?>">
                                        <i class="fas fa-chart-simple"></i> Track progress
                                    </a>
                                    <form class="vm-inline" method="post" action="<?= e($self) ?>">
                                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                        <input type="hidden" name="action" value="set_status">
                                        <input type="hidden" name="opportunity_id" value="<?= $id ?>">
                                        <select class="vm-select" name="status" aria-label="Vacancy status">
                                            <?php foreach ($statusOptions as $opt): ?>
                                                <option value="<?= e($opt) ?>" <?= $opt === $status ? 'selected' : '' ?>><?= e(rc_label($opt)) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="rc-btn rc-btn--secondary rc-btn--small" type="submit">Set</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (count($vacancies) >= 1000): ?>
                <p class="vm-note">Showing the 1000 most recent vacancies.</p>
            <?php endif; ?>

        <?php endif; ?>

        <p class="vm-note">
            Vacancies are the platform's opportunity records, so changes here appear to candidates
            immediately. Application counts exclude drafts. "Need follow-up" means a non-final application
            with no update for <?= (int)VM_STALE_DAYS ?> or more days. A vacancy needs attention when it is past its closing date but still open, closes within 7 days, has no applications, has stale applications, is closed with applicants still in progress, or is still open after all positions are filled. Editing other vacancy details
            stays in the existing opportunity management.
        </p>
    </div>
</section>

<?php endif; ?>

<?php require __DIR__ . '/_layout_end.php'; ?>
