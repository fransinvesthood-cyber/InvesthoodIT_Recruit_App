<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('recruiter');

$user        = current_user();
$flashes     = render_flashes();
$currentPage = 'vacancy_view';
$pageTitle   = 'Vacancy Progress';

require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/_vacancy_helpers.php';

$conn = Database::getConnection();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0 || !rc_has_table($conn, 'opportunities')) {
    http_response_code(404);
    exit('Vacancy not found.');
}

$stmt = $conn->prepare('SELECT * FROM opportunities WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$vacancy = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$vacancy) {
    http_response_code(404);
    exit('Vacancy not found.');
}

$self      = url('recruiter/vacancy_view.php?id=' . $id);
$listUrl   = url('recruiter/vacancies.php');
$vStatus   = (string)($vacancy['status'] ?? '');

/* ---------------------------------------------------------------
 | Stage counts
 * --------------------------------------------------------------*/

$counts = vm_stage_counts($conn, $id)[$id] ?? vm_empty_counts();
$total  = (int)$counts['total'];
$active = vm_active_count($counts);

$offers   = (int)($counts['stages']['offer_sent'] ?? 0) + (int)($counts['stages']['offer_accepted'] ?? 0);
$accepted = (int)($counts['stages']['offer_accepted'] ?? 0);
$positions = (int)($vacancy['available_positions'] ?? 0);

/* ---------------------------------------------------------------
 | Filters
 * --------------------------------------------------------------*/

$allowedStages = array_merge(['all', 'overdue', 'active'], array_keys(VM_STAGES));
$filter = trim((string)($_GET['stage'] ?? 'all'));
if (!in_array($filter, $allowedStages, true)) $filter = 'all';
$search = trim((string)($_GET['q'] ?? ''));

/* ---------------------------------------------------------------
 | Applications for this vacancy
 * --------------------------------------------------------------*/

$rows = [];

if (rc_has_table($conn, 'applications')) {

    $hasInterviews = rc_has_table($conn, 'recruiter_interviews');
    $hasDecisions  = rc_has_table($conn, 'selection_decisions');
    $hasConsents   = rc_has_table($conn, 'candidate_consents');

    $sql = "SELECT a.id AS application_id, a.application_reference, a.candidate_id, a.status,
                   a.created_at, a.updated_at, a.submitted_at,
                   u.first_name, u.last_name, u.email,
                   " . ($hasInterviews
                        ? "(SELECT COUNT(*) FROM recruiter_interviews ri WHERE ri.application_id = a.id AND ri.status = 'scheduled') AS upcoming_interviews,
                           (SELECT COUNT(*) FROM recruiter_interviews ri WHERE ri.application_id = a.id AND ri.status = 'completed') AS completed_interviews,"
                        : "0 AS upcoming_interviews, 0 AS completed_interviews,") . "
                   " . ($hasDecisions ? 'sd.decision' : 'NULL') . " AS decision,
                   " . ($hasConsents
                        ? "(SELECT COUNT(*) FROM candidate_consents cc
                            WHERE cc.candidate_id = a.candidate_id
                              AND cc.consent_type = 'recruiter_discovery'
                              AND cc.status = 'consented'
                              AND (cc.expires_at IS NULL OR cc.expires_at > NOW())) AS has_consent"
                        : "0 AS has_consent") . "
            FROM applications a
            LEFT JOIN users u ON u.id = a.candidate_id
            " . ($hasDecisions ? 'LEFT JOIN selection_decisions sd ON sd.application_id = a.id' : '') . "
            WHERE a.opportunity_id = ? AND a.status <> 'draft'
            ORDER BY COALESCE(a.updated_at, a.submitted_at, a.created_at) DESC, a.id DESC
            LIMIT 1000";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();

        while ($r = $res->fetch_assoc()) {

            $status = (string)$r['status'];
            $last   = strtotime((string)($r['updated_at'] ?? $r['submitted_at'] ?? $r['created_at'] ?? '')) ?: time();
            $age    = max(0, (int)floor((time() - $last) / 86400));
            $term   = in_array($status, VM_TERMINAL, true);

            $r['age_days']   = $age;
            $r['is_overdue'] = !$term && $age >= VM_STALE_DAYS;
            $r['is_active']  = !$term;
            $r['name']       = trim((string)($r['first_name'] ?? '') . ' ' . (string)($r['last_name'] ?? '')) ?: 'Candidate';

            if ($filter === 'overdue' && !$r['is_overdue']) continue;
            if ($filter === 'active' && !$r['is_active']) continue;
            if (!in_array($filter, ['all', 'overdue', 'active'], true) && $status !== $filter) continue;

            if ($search !== '') {
                $hay = strtolower($r['name'] . ' ' . (string)($r['email'] ?? '') . ' ' . (string)($r['application_reference'] ?? ''));
                if (!str_contains($hay, strtolower($search))) continue;
            }

            $rows[] = $r;
        }
        $stmt->close();
    }
}

$statusClass = $vStatus === 'closed' ? 'rc-status--danger'
    : (in_array($vStatus, VM_OPEN_STATUSES, true) ? 'rc-status--success' : '');

$loc = trim((string)($vacancy['city'] ?? '') . ', ' . (string)($vacancy['province'] ?? ''), ' ,');
$flags = vm_attention_flags($vacancy, $counts);
$milestones = vm_milestones($counts);
$maxStage = 0;
foreach ($counts['stages'] as $n) $maxStage = max($maxStage, (int)$n);

require __DIR__ . '/_layout_start.php';
?>

<style>
.vv-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-bottom:22px}
.vv-stat{padding:20px;border-radius:18px;background:rgba(127,127,127,.08);border:1px solid rgba(127,127,127,.18)}
.vv-stat strong{display:block;font-size:26px;line-height:1}
.vv-stat span{display:block;margin-top:8px;font-size:13px;opacity:.7}
.vv-stat small{display:block;margin-top:5px;font-size:11px;opacity:.55}
.vv-funnel{display:flex;flex-direction:column;gap:10px}
.vv-funnel a{display:grid;grid-template-columns:190px 1fr 40px;gap:12px;align-items:center;color:inherit;text-decoration:none;font-size:13px}
.vv-funnel .vv-track{height:12px;border-radius:999px;background:rgba(127,127,127,.15);overflow:hidden}
.vv-funnel .vv-fill{display:block;height:100%;border-radius:999px;background:#2563eb}
.vv-funnel .vv-fill--end{background:#64748b}
.vv-funnel b{text-align:right}
.vv-toolbar{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;margin-bottom:16px}
.vv-toolbar label{display:flex;flex-direction:column;gap:6px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;opacity:.85}
.vv-input,.vv-select{box-sizing:border-box;padding:10px 12px;border-radius:10px;border:1px solid rgba(127,127,127,.35);background:transparent;color:inherit;font:inherit;min-width:200px}
.vv-select option{color:#172b4d}
.vv-cell small{display:block;opacity:.65;margin-top:3px}
.vv-warn{color:#d97706;font-weight:700}
.vv-actions{display:flex;flex-direction:column;gap:6px;min-width:150px}
.vv-meta{display:flex;flex-wrap:wrap;gap:18px;margin-top:14px;font-size:13px;opacity:.85}
.vv-note{margin-top:14px;font-size:12px;opacity:.65}
@media(max-width:900px){.vv-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.vv-funnel a{grid-template-columns:130px 1fr 34px}}
@media(max-width:520px){.vv-stats{grid-template-columns:1fr}}
</style>

<section class="rc-hero">
    <div>
        <span class="rc-eyebrow"><i class="fas fa-chart-simple"></i> Recruitment progress</span>
        <h2><?= e((string)($vacancy['title'] ?? 'Vacancy')) ?></h2>
        <p>
            <?= e((string)($vacancy['organisation'] ?? '')) ?>
            <?= $loc !== '' ? ' · ' . e($loc) : '' ?>
        </p>
        <div class="vv-meta">
            <span class="rc-status <?= e($statusClass) ?>"><?= e(rc_label($vStatus)) ?></span>
            <span><i class="fas fa-calendar"></i> Closes <?= e(rc_date((string)($vacancy['application_close_date'] ?? ''), 'not set')) ?>
                (<?= e(vm_closing_label($vacancy['application_close_date'] ?? null, $vStatus)) ?>)</span>
            <?php if ($positions > 0): ?>
                <span><i class="fas fa-users"></i> <?= number_format($positions) ?> position<?= $positions === 1 ? '' : 's' ?></span>
            <?php endif; ?>
        </div>
        <div class="rc-hero__actions">
            <a class="rc-btn rc-btn--glass" href="<?= e($listUrl) ?>"><i class="fas fa-arrow-left"></i> All vacancies</a>
            <a class="rc-btn rc-btn--glass" href="<?= e(url('recruiter/follow_up.php')) ?>"><i class="fas fa-route"></i> Follow-Up Pipeline</a>
            <a class="rc-btn rc-btn--glass" href="<?= e(url('recruiter/interviews.php')) ?>"><i class="fas fa-calendar-check"></i> Interviews</a>
        </div>
    </div>
</section>

<section class="vv-stats" aria-label="Vacancy progress summary">
    <div class="vv-stat"><strong><?= number_format($total) ?></strong><span>Applications received</span><small>Drafts excluded</small></div>
    <div class="vv-stat"><strong><?= number_format($active) ?></strong><span>In progress</span><small>Excludes finished outcomes</small></div>
    <div class="vv-stat"><strong><?= number_format((int)$counts['overdue']) ?></strong><span>Need follow-up</span><small>No update for <?= (int)VM_STALE_DAYS ?>+ days</small></div>
    <div class="vv-stat"><strong><?= number_format($accepted) ?><?= $positions > 0 ? ' / ' . number_format($positions) : '' ?></strong><span>Offers accepted</span><small><?= number_format($offers) ?> offer<?= $offers === 1 ? '' : 's' ?> sent or accepted</small></div>
</section>

<?php if ($flags): ?>
<section class="rc-card rc-space">
    <div class="rc-card__header"><div><span class="rc-eyebrow">Attention</span><h3>This vacancy needs attention</h3></div></div>
    <div class="rc-card__body">
        <?php foreach ($flags as $f): ?>
            <p class="vv-warn" style="<?= $f['level'] === 'danger' ? 'color:#dc2626' : '' ?>"><i class="fas fa-triangle-exclamation"></i> <?= e($f['text']) ?></p>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="rc-card rc-space">
    <div class="rc-card__header">
        <div>
            <span class="rc-eyebrow">Lifecycle</span>
            <h3>From vacancy to selection</h3>
            <p>Created <?= e(rc_date((string)($vacancy['created_at'] ?? ''), 'date unknown')) ?> · <?= e(rc_label($vStatus)) ?> · closes <?= e(rc_date((string)($vacancy['application_close_date'] ?? ''), 'not set')) ?></p>
        </div>
    </div>
    <div class="rc-card__body">
        <div class="vv-stats" style="grid-template-columns:repeat(5,minmax(0,1fr));margin-bottom:0">
            <?php foreach ($milestones as $label => $n): ?>
                <div class="vv-stat"><strong><?= number_format($n) ?></strong><span><?= e($label) ?></span>
                <small><?= $total > 0 ? (int)round($n / $total * 100) . '% of applicants' : '—' ?></small></div>
            <?php endforeach; ?>
        </div>
        <p class="vv-note">Counts are applications currently at or beyond each point (rejected and withdrawn applicants are not counted as progressed).<?= $positions > 0 ? ' Positions filled: ' . $accepted . ' of ' . $positions . '.' : '' ?></p>
    </div>
</section>

<section class="rc-card rc-space">
    <div class="rc-card__header">
        <div>
            <span class="rc-eyebrow">Stages</span>
            <h3>Recruitment funnel</h3>
            <p>Select a stage to list the applications in it.</p>
        </div>
    </div>
    <div class="rc-card__body">
        <?php if ($total === 0): ?>
            <div class="rc-empty">
                <i class="fas fa-inbox"></i>
                <strong>No applications yet</strong>
                <span>Applications will appear here as candidates apply.</span>
            </div>
        <?php else: ?>
            <div class="vv-funnel">
                <?php
                $ordered = [];
                foreach (VM_STAGES as $key => $label) {
                    if (!empty($counts['stages'][$key])) $ordered[$key] = (int)$counts['stages'][$key];
                }
                foreach ($counts['stages'] as $key => $n) {
                    if (!isset(VM_STAGES[$key])) $ordered[(string)$key] = (int)$n;
                }
                foreach ($ordered as $key => $n):
                    $w   = $maxStage > 0 ? max(2, (int)round(($n / $maxStage) * 100)) : 0;
                    $end = in_array($key, VM_TERMINAL, true);
                ?>
                    <a href="<?= e($self . '&stage=' . rawurlencode($key) . '#applications') ?>">
                        <span><?= e(vm_stage_label($key)) ?></span>
                        <span class="vv-track"><span class="vv-fill <?= $end ? 'vv-fill--end' : '' ?>" style="width:<?= $w ?>%"></span></span>
                        <b><?= $n ?></b>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="rc-card rc-space" id="applications">
    <div class="rc-card__header">
        <div>
            <span class="rc-eyebrow">Applications</span>
            <h3>Applicants for this vacancy</h3>
        </div>
    </div>
    <div class="rc-card__body">

        <form class="vv-toolbar" method="get" action="<?= e(url('recruiter/vacancy_view.php')) ?>">
            <input type="hidden" name="id" value="<?= $id ?>">
            <label>Search
                <input class="vv-input" type="search" name="q" value="<?= e($search) ?>" placeholder="Name, email or reference">
            </label>
            <label>Stage
                <select class="vv-select" name="stage">
                    <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All stages</option>
                    <option value="active" <?= $filter === 'active' ? 'selected' : '' ?>>In progress only</option>
                    <option value="overdue" <?= $filter === 'overdue' ? 'selected' : '' ?>>Needs follow-up</option>
                    <?php foreach (VM_STAGES as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $filter === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="rc-btn rc-btn--secondary" type="submit"><i class="fas fa-filter"></i> Apply</button>
            <a class="rc-btn rc-btn--secondary" href="<?= e($self) ?>">Reset</a>
        </form>

        <?php if (!$rows): ?>
            <div class="rc-empty">
                <i class="fas fa-inbox"></i>
                <strong>No applications match this view</strong>
                <span>Try another stage or search term.</span>
            </div>
        <?php else: ?>
            <div class="rc-table-wrap">
                <table class="rc-table">
                    <thead>
                        <tr>
                            <th>Candidate</th>
                            <th>Stage</th>
                            <th>Interviews</th>
                            <th>Last update</th>
                            <th>Follow-up</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td class="vv-cell">
                                <strong><?= e($r['name']) ?></strong>
                                <small><?= e((string)($r['application_reference'] ?? ('Application #' . (int)$r['application_id']))) ?></small>
                                <small><?= e((string)($r['email'] ?? '')) ?></small>
                            </td>
                            <td class="vv-cell">
                                <span class="rc-status"><?= e(vm_stage_label((string)$r['status'])) ?></span>
                                <?php if (!empty($r['decision'])): ?>
                                    <small>Decision: <?= e(rc_label((string)$r['decision'])) ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="vv-cell">
                                <?= (int)$r['upcoming_interviews'] ?> upcoming
                                <small><?= (int)$r['completed_interviews'] ?> completed</small>
                            </td>
                            <td class="vv-cell"><?= e(rc_datetime((string)($r['updated_at'] ?? $r['submitted_at'] ?? $r['created_at'] ?? ''))) ?></td>
                            <td class="vv-cell">
                                <?php if ($r['is_overdue']): ?>
                                    <span class="vv-warn"><i class="fas fa-clock"></i> Review needed</span>
                                <?php else: ?>
                                    <span><?= $r['is_active'] ? 'Up to date' : 'Finished' ?></span>
                                <?php endif; ?>
                                <small><?= (int)$r['age_days'] ?> days since update</small>
                            </td>
                            <td>
                                <div class="vv-actions">
                                    <?php if ((int)$r['has_consent'] > 0): ?>
                                        <a class="rc-btn rc-btn--secondary rc-btn--small" href="<?= e(url('recruiter/candidate_view.php?id=' . (int)$r['candidate_id'])) ?>">
                                            <i class="fas fa-user"></i> View candidate
                                        </a>
                                    <?php else: ?>
                                        <small>Profile hidden: no valid recruiter consent</small>
                                    <?php endif; ?>
                                    <?php if ($r['is_active']): ?>
                                        <a class="rc-btn rc-btn--secondary rc-btn--small" href="<?= e(url('recruiter/interviews.php?schedule=' . (int)$r['application_id'])) ?>">
                                            <i class="fas fa-calendar-plus"></i> Schedule interview
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

        <p class="vv-note">
            Stage changes are made in the existing screening, interview and decision workflow;
            this page reflects them as they happen.
        </p>
    </div>
</section>

<?php require __DIR__ . '/_layout_end.php'; ?>
