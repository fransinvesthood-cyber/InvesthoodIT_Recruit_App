<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('recruiter');

$user        = current_user();
$flashes     = render_flashes();
$currentPage = 'candidate_matching';
$pageTitle   = 'Candidate-to-Vacancy Matching';

require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/_vacancy_helpers.php';
require_once __DIR__ . '/_matching_helpers.php';

$conn = Database::getConnection();

$recruiterId = (int)($user['id'] ?? $user['user_id'] ?? 0);

if ($recruiterId <= 0) {
    http_response_code(403);
    exit('Invalid Recruiter account.');
}

$self = url('recruiter/candidate_matching.php');

/* ==================================================================
 | Inputs (GET only - this page never writes data)
 * ==================================================================*/

$mode = (string)($_GET['mode'] ?? 'vacancy');
if (!in_array($mode, ['vacancy', 'candidate'], true)) $mode = 'vacancy';

$vacancyId   = (int)($_GET['vacancy_id'] ?? 0);
$candidateId = (int)($_GET['candidate_id'] ?? 0);

$scope = (string)($_GET['scope'] ?? 'open');
if (!in_array($scope, ['open', 'all'], true)) $scope = 'open';

$minScore = (int)($_GET['min'] ?? 0);
if (!in_array($minScore, [0, 35, 60, 80], true)) $minScore = 0;

$sameProvince = (($_GET['province'] ?? '') === '1');
$hideApplied  = (($_GET['hide_applied'] ?? '') === '1');

$customReqRaw = $_GET['req'] ?? [];
if (!is_array($customReqRaw)) $customReqRaw = [];

/* ==================================================================
 | Reference data
 * ==================================================================*/

$hasOpp      = rc_has_table($conn, 'opportunities');
$oppColumns  = $hasOpp ? vm_columns($conn) : [];
$catalog     = cm_load_catalog($conn);
$candidates  = cm_load_candidates($conn);
$vacancies   = cm_load_vacancies($conn);

$openVacancies = array_filter($vacancies, 'cm_is_open');

/** Vacancies offered in the dropdown / used for ranking in candidate mode. */
if (!isset($_GET['scope']) && !$openVacancies && $vacancies) $scope = 'all';
$pool = $scope === 'all' ? $vacancies : $openVacancies;

$customReq = [];
foreach ($customReqRaw as $sid) {
    $sid = (int)$sid;
    if ($sid > 0 && isset($catalog[$sid])) $customReq[$sid] = false;
}

$results      = [];     // ranked rows to display
$evaluated    = 0;      // how many comparisons were made
$selVacancy   = null;
$selCandidate = null;
$reqInfo      = ['source' => 'none', 'skills' => []];
$noReqCount   = 0;      // candidate mode: vacancies skipped for lack of requirements
$tablesOk     = $hasOpp && $catalog && $candidates;
$focus        = false;  // head-to-head: one vacancy compared with one candidate

if ($tablesOk) {

    /* -------------------- Vacancy -> candidates -------------------- */
    if ($mode === 'vacancy' && $vacancyId > 0 && isset($vacancies[$vacancyId])) {

        $selVacancy = $vacancies[$vacancyId];

        $built   = cm_build_requirements($conn, [$vacancyId => $selVacancy], $catalog, $oppColumns);
        $reqInfo = $built[$vacancyId];
        if ($customReq) {
            $reqInfo['source']   = 'custom';
            $reqInfo['skills']   = $customReq;
            $reqInfo['unmapped'] = [];
        }

        if ($reqInfo['skills']) {

            $candSkills = cm_load_candidate_skills($conn);
            $apps       = cm_applications($conn, $vacancyId, null)[$vacancyId] ?? [];
            $vProvince  = (string)($selVacancy['province'] ?? '');

            $iterCandidates = $candidates;
            if ($candidateId > 0 && isset($candidates[$candidateId])) {
                $focus          = true;
                $iterCandidates = [$candidateId => $candidates[$candidateId]];
            }

            foreach ($iterCandidates as $cid => $cand) {

                $evaluated++;
                $res = cm_assess($reqInfo, $candSkills[$cid] ?? [], $catalog, $cand);

                if (!$focus && ($res['matched'] < 1 || $res['score'] < $minScore)) continue;

                $app = $apps[$cid] ?? null;
                if (!$focus && $hideApplied && $app !== null) continue;

                $samePlace = cm_same_place($cand['province'], $vProvince);
                if (!$focus && $sameProvince && !$samePlace) continue;

                $results[] = [
                    'label'     => $cand['name'],
                    'cand'      => $cand,
                    'res'       => $res,
                    'app'       => $app,
                    'samePlace' => $samePlace,
                ];
            }
            usort($results, 'cm_compare_results');
        }
    }

    /* -------------------- Candidate -> vacancies -------------------- */
    if ($mode === 'candidate' && $candidateId > 0 && isset($candidates[$candidateId])) {

        $selCandidate = $candidates[$candidateId];
        $candSkills   = cm_load_candidate_skills($conn, $candidateId)[$candidateId] ?? [];
        $apps         = [];
        foreach (cm_applications($conn, null, $candidateId) as $oid => $byUser) {
            if (isset($byUser[$candidateId])) $apps[(int)$oid] = $byUser[$candidateId];
        }

        $candPool = $pool;
        if ($vacancyId > 0 && isset($vacancies[$vacancyId])) {
            $focus    = true;
            $candPool = [$vacancyId => $vacancies[$vacancyId]];
        }

        $reqMap = cm_build_requirements($conn, $candPool, $catalog, $oppColumns);

        foreach ($candPool as $oid => $vac) {

            $req = $reqMap[(int)$oid] ?? ['source' => 'none', 'skills' => []];
            if (!$req['skills']) { $noReqCount++; continue; }

            $evaluated++;
            $res = cm_assess($req, $candSkills, $catalog, $selCandidate);

            if (!$focus && ($res['matched'] < 1 || $res['score'] < $minScore)) continue;

            $app = $apps[(int)$oid] ?? null;
            if (!$focus && $hideApplied && $app !== null) continue;

            $samePlace = cm_same_place($selCandidate['province'], (string)($vac['province'] ?? ''));
            if (!$focus && $sameProvince && !$samePlace) continue;

            $results[] = [
                'label'     => (string)($vac['title'] ?? ''),
                'vac'       => $vac,
                'res'       => $res,
                'app'       => $app,
                'source'    => $req['source'],
                'samePlace' => $samePlace,
            ];
        }
        usort($results, 'cm_compare_results');
    }

    /* Audit, like the other recruiter pages */
    try {
        if ($selVacancy !== null) {
            rc_activity($conn, $recruiterId, 'candidate_matching_viewed',
                'Ran candidate matching for vacancy #' . $vacancyId . '.', 'opportunity', $vacancyId);
        } elseif ($selCandidate !== null) {
            rc_activity($conn, $recruiterId, 'candidate_matching_viewed',
                'Ran vacancy matching for a recruiter-consented candidate.', 'candidate', $candidateId);
        }
    } catch (Throwable $t) {
        error_log('candidate_matching.php audit: ' . $t->getMessage());
    }
}

$shown   = count($results);
$visible = array_slice($results, 0, CM_PAGE_LIMIT);
$strong  = 0;
foreach ($results as $r) {
    if ($r['res']['score'] >= 80) $strong++;
}

/* Dropdown list of vacancies; always include the selected one. */
$vacancyOptions = $pool;
if ($vacancyId > 0 && isset($vacancies[$vacancyId]) && !isset($vacancyOptions[$vacancyId])) {
    $vacancyOptions = [$vacancyId => $vacancies[$vacancyId]] + $vacancyOptions;
}

$sourceLabel = static function (string $s): string {
    return match ($s) {
        'table'  => 'Vacancy skills & eligibility',
        'text'   => 'Detected from vacancy text',
        'custom' => 'Custom requirements',
        default  => 'No requirements found',
    };
};

$renderDetail = static function (array $res, bool $open = false): void {
    ?>
    <details class="cm-detail" <?= $open ? 'open' : '' ?>>
        <summary>Full comparison</summary>
        <table class="cm-mini">
            <thead><tr><th>Required skill</th><th>Candidate has it</th><th>Verification</th><th>Proficiency</th></tr></thead>
            <tbody>
            <?php foreach ($res['rows'] as $row): ?>
                <tr>
                    <td>
                        <?= e($row['name']) ?>
                        <?php if ($row['mandatory']): ?><span class="cm-tag">Mandatory</span><?php endif; ?>
                    </td>
                    <td>
                        <?php if ($row['state'] === 'matched'): ?>
                            <span class="cm-yes"><i class="fas fa-circle-check"></i> Yes</span>
                        <?php elseif ($row['state'] === 'failed'): ?>
                            <span class="cm-no"><i class="fas fa-circle-xmark"></i> Failed verification</span>
                        <?php else: ?>
                            <span class="cm-no"><i class="fas fa-circle-xmark"></i> Missing</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $row['status'] !== null ? e(rc_label((string)$row['status'])) : '—' ?></td>
                    <td><?= ($row['state'] === 'matched' && $row['proficiency'] !== null && $row['proficiency'] !== '') ? e(rc_label((string)$row['proficiency'])) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!empty($res['criteria'])): ?>
        <table class="cm-mini">
            <thead><tr><th>Other criteria</th><th>Vacancy asks for</th><th>Candidate</th><th>Result</th></tr></thead>
            <tbody>
            <?php foreach ($res['criteria'] as $c): ?>
                <tr>
                    <td><?= e($c['label']) ?></td>
                    <td><?= e($c['required']) ?></td>
                    <td><?= e($c['candidate']) ?></td>
                    <td>
                        <?php if ($c['state'] === 'met'): ?><span class="cm-yes"><i class="fas fa-circle-check"></i> Meets</span>
                        <?php elseif ($c['state'] === 'unmet'): ?><span class="cm-no"><i class="fas fa-circle-xmark"></i> Does not meet</span>
                        <?php elseif ($c['state'] === 'unknown'): ?><span class="cm-warn"><i class="fas fa-circle-question"></i> Not provided</span>
                        <?php else: ?><small>For review (not scored)</small><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </details>
    <?php
};

$renderScore = static function (array $res): void {
    [$label, $cls] = cm_tier((int)$res['score']);
    ?>
    <div class="cm-score <?= e($cls) ?>">
        <strong><?= (int)$res['score'] ?>%</strong>
        <span class="cm-bar"><i style="width:<?= max(0, min(100, (int)$res['score'])) ?>%"></i></span>
        <small><?= e($label) ?></small>
    </div>
    <?php
};

require __DIR__ . '/_layout_start.php';
?>

<style>
.cm-tabs{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:18px}
.cm-tab{padding:10px 16px;border-radius:12px;border:1px solid rgba(127,127,127,.3);color:inherit;text-decoration:none;font-weight:600;font-size:14px}
.cm-tab.is-active{background:#2454d8;border-color:#2454d8;color:#fff}
.cm-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-bottom:22px}
.cm-stat{padding:20px;border-radius:18px;background:rgba(127,127,127,.08);border:1px solid rgba(127,127,127,.18)}
.cm-stat i{display:block;margin-bottom:12px;font-size:20px}
.cm-stat strong{display:block;font-size:26px;line-height:1}
.cm-stat span{display:block;margin-top:8px;font-size:13px;opacity:.7}
.cm-filters{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end}
.cm-filters label.cm-f{display:flex;flex-direction:column;gap:6px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;opacity:.85}
.cm-input{box-sizing:border-box;padding:10px 12px;border-radius:10px;border:1px solid rgba(127,127,127,.35);background:transparent;color:inherit;font:inherit;min-width:220px;max-width:100%}
.cm-input option,.cm-input optgroup{color:#172b4d}
.cm-check{display:flex;align-items:center;gap:7px;font-size:13px;padding:10px 0}
.cm-override{margin-top:14px;width:100%}
.cm-override summary{cursor:pointer;font-weight:600;font-size:13px}
.cm-override select{width:100%;margin-top:10px;min-height:150px}
.cm-banner{display:flex;flex-wrap:wrap;gap:8px 18px;align-items:center;padding:14px 16px;border-radius:14px;background:rgba(36,84,216,.08);border:1px solid rgba(36,84,216,.25);margin-bottom:16px;font-size:13px}
.cm-chips{display:flex;flex-wrap:wrap;gap:5px;margin-top:6px}
.cm-chip{padding:3px 9px;border-radius:999px;font-size:11px;font-weight:600;background:rgba(127,127,127,.14)}
.cm-chip--m{background:rgba(36,84,216,.18)}
.cm-cell small{display:block;opacity:.65;margin-top:3px}
.cm-person{display:flex;gap:12px;align-items:center}
.cm-score{min-width:120px}
.cm-score strong{font-size:20px}
.cm-score small{display:block;margin-top:4px;font-weight:600}
.cm-bar{display:block;height:7px;border-radius:99px;background:rgba(127,127,127,.2);margin-top:6px;overflow:hidden}
.cm-bar i{display:block;height:100%;border-radius:99px;background:#94a3b8}
.cm-tier--strong .cm-bar i{background:#16a34a}.cm-tier--strong small{color:#16a34a}
.cm-tier--good .cm-bar i{background:#2454d8}.cm-tier--good small{color:#2454d8}
.cm-tier--partial .cm-bar i{background:#d97706}.cm-tier--partial small{color:#d97706}
.cm-tier--low small{opacity:.7}
.cm-detail{margin-top:8px}
.cm-detail summary{cursor:pointer;font-size:12px;font-weight:700;color:#2454d8}
.cm-mini{width:100%;border-collapse:collapse;margin-top:8px;font-size:12px;min-width:420px}
.cm-mini th,.cm-mini td{padding:6px 8px;text-align:left;border-bottom:1px solid rgba(127,127,127,.18)}
.cm-yes{color:#16a34a;font-weight:700}.cm-no{color:#dc2626;font-weight:700}
.cm-tag{margin-left:6px;padding:1px 7px;border-radius:99px;font-size:10px;font-weight:700;background:rgba(36,84,216,.18)}
.cm-warn{color:#d97706;font-weight:700}
.cm-actions{display:flex;flex-direction:column;gap:6px;min-width:140px}
.cm-diag{margin-top:18px;font-size:13px;padding:14px 16px;border-radius:14px;border:1px solid rgba(127,127,127,.25)}
.cm-diag summary{cursor:pointer;font-weight:700}
.cm-diag ul{margin:10px 0 0 18px;line-height:1.8}
.cm-note{margin-top:14px;font-size:12px;opacity:.65;line-height:1.6}
@media(max-width:1000px){.cm-stats{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:560px){.cm-stats{grid-template-columns:1fr}}
</style>

<section class="rc-hero">
    <div>
        <span class="rc-eyebrow"><i class="fas fa-bullseye"></i> Talent matching</span>
        <h2>Candidate-to-Vacancy Matching</h2>
        <p>
            Compare candidate profiles against vacancy requirements (skills, qualification, experience, location and availability) and rank the best fits by suitability score.
            Only candidates who have given valid recruiter-discovery consent are included.
        </p>
    </div>
</section>

<?php if (!$hasOpp): ?>

    <div class="rc-card"><div class="rc-card__body">
        <div class="rc-empty"><i class="fas fa-database"></i><strong>Opportunities table not found</strong>
        <span>Vacancies are the existing <code>opportunities</code> records.</span></div>
    </div></div>

<?php elseif (!$catalog): ?>

    <div class="rc-card"><div class="rc-card__body">
        <div class="rc-empty"><i class="fas fa-code"></i><strong>No active skills</strong>
        <span>Matching needs the active skills taxonomy.</span></div>
    </div></div>

<?php elseif (!$candidates): ?>

    <div class="rc-card"><div class="rc-card__body">
        <div class="rc-empty"><i class="fas fa-user-shield"></i><strong>No consented candidates</strong>
        <span>No active candidate currently has valid recruiter-discovery consent.</span></div>
    </div></div>

<?php else: ?>

<nav class="cm-tabs" aria-label="Matching direction">
    <a class="cm-tab <?= $mode === 'vacancy' ? 'is-active' : '' ?>" href="<?= e($self . '?mode=vacancy') ?>"><i class="fas fa-briefcase"></i> Find candidates for a vacancy</a>
    <a class="cm-tab <?= $mode === 'candidate' ? 'is-active' : '' ?>" href="<?= e($self . '?mode=candidate') ?>"><i class="fas fa-user"></i> Find vacancies for a candidate</a>
</nav>

<section class="rc-card rc-space">
    <div class="rc-card__header">
        <div>
            <span class="rc-eyebrow">Compare</span>
            <h3><?= $mode === 'vacancy' ? 'Choose a vacancy' : 'Choose a candidate' ?></h3>
        </div>
    </div>
    <div class="rc-card__body">
        <form class="cm-filters" method="get" action="<?= e($self) ?>">
            <input type="hidden" name="mode" value="<?= e($mode) ?>">

            <?php if ($mode === 'vacancy'): ?>
                <label class="cm-f">Vacancy
                    <select class="cm-input" name="vacancy_id" required>
                        <option value="">Select a vacancy…</option>
                        <?php foreach ($vacancyOptions as $oid => $v): ?>
                            <option value="<?= (int)$oid ?>" <?= (int)$oid === $vacancyId ? 'selected' : '' ?>>
                                <?= e((string)($v['title'] ?? 'Untitled vacancy')) ?>
                                <?= !empty($v['organisation']) ? ' — ' . e((string)$v['organisation']) : '' ?>
                                (<?= e(rc_label((string)($v['status'] ?? ''))) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="cm-f">Compare with one candidate (optional)
                    <select class="cm-input" name="candidate_id">
                        <option value="0">All candidates (rank them)</option>
                        <?php foreach ($candidates as $cid => $cand): ?>
                            <option value="<?= (int)$cid ?>" <?= (int)$cid === $candidateId ? 'selected' : '' ?>>
                                <?= e($cand['name']) ?><?= $cand['title'] !== '' ? ' — ' . e($cand['title']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php else: ?>
                <label class="cm-f">Candidate
                    <select class="cm-input" name="candidate_id" required>
                        <option value="">Select a candidate…</option>
                        <?php foreach ($candidates as $cid => $cand): ?>
                            <option value="<?= (int)$cid ?>" <?= (int)$cid === $candidateId ? 'selected' : '' ?>>
                                <?= e($cand['name']) ?><?= $cand['title'] !== '' ? ' — ' . e($cand['title']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>

            <?php if ($mode === 'candidate'): ?>
                <label class="cm-f">Compare with one vacancy (optional)
                    <select class="cm-input" name="vacancy_id">
                        <option value="0">All vacancies (rank them)</option>
                        <?php foreach ($vacancyOptions as $oid => $v): ?>
                            <option value="<?= (int)$oid ?>" <?= (int)$oid === $vacancyId ? 'selected' : '' ?>>
                                <?= e((string)($v['title'] ?? 'Untitled vacancy')) ?>
                                <?= !empty($v['organisation']) ? ' — ' . e((string)$v['organisation']) : '' ?>
                                (<?= e(rc_label((string)($v['status'] ?? ''))) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>

            <label class="cm-f">Vacancies
                <select class="cm-input" name="scope">
                    <option value="open" <?= $scope === 'open' ? 'selected' : '' ?>>Open for applications</option>
                    <option value="all" <?= $scope === 'all' ? 'selected' : '' ?>>All vacancies</option>
                </select>
            </label>

            <label class="cm-f">Minimum match
                <select class="cm-input" name="min" style="min-width:160px">
                    <option value="0" <?= $minScore === 0 ? 'selected' : '' ?>>Any skill match</option>
                    <option value="35" <?= $minScore === 35 ? 'selected' : '' ?>>35% and above</option>
                    <option value="60" <?= $minScore === 60 ? 'selected' : '' ?>>60% and above</option>
                    <option value="80" <?= $minScore === 80 ? 'selected' : '' ?>>80% and above</option>
                </select>
            </label>

            <label class="cm-check"><input type="checkbox" name="province" value="1" <?= $sameProvince ? 'checked' : '' ?>> Same province only</label>
            <label class="cm-check"><input type="checkbox" name="hide_applied" value="1" <?= $hideApplied ? 'checked' : '' ?>> Hide existing applicants</label>

            <button class="rc-btn rc-btn--primary" type="submit"><i class="fas fa-bullseye"></i> Match</button>

            <?php if ($mode === 'vacancy'): ?>
                <details class="cm-override" <?= $customReq ? 'open' : '' ?>>
                    <summary>Override this vacancy's requirements (optional)</summary>
                    <select class="cm-input" name="req[]" multiple size="8" aria-label="Required skills">
                        <?php foreach ($catalog as $sid => $skill): ?>
                            <option value="<?= (int)$sid ?>" <?= isset($customReq[$sid]) ? 'selected' : '' ?>><?= e($skill['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small>Hold Ctrl / Cmd to pick several. When used, these replace the vacancy's own requirements for this comparison only; nothing is saved.</small>
                </details>
            <?php endif; ?>
        </form>
    </div>
</section>

<?php if (!$pool): ?>
    <div class="cm-banner"><i class="fas fa-triangle-exclamation"></i>
        <span><?= $vacancies
            ? 'No vacancies are open for applications right now. ' . count($vacancies) . ' vacanc' . (count($vacancies) === 1 ? 'y exists' : 'ies exist') . ' in total; choose “All vacancies” above to include them.'
            : 'No vacancies were found in the opportunities table.' ?></span>
    </div>
<?php endif; ?>

<?php if ($mode === 'vacancy' && $vacancyId > 0 && $selVacancy === null): ?>
    <div class="cm-banner"><i class="fas fa-triangle-exclamation"></i> That vacancy could not be found.</div>
<?php elseif ($mode === 'candidate' && $candidateId > 0 && $selCandidate === null): ?>
    <div class="cm-banner"><i class="fas fa-triangle-exclamation"></i> That candidate was not found or has no valid recruiter-discovery consent.</div>
<?php endif; ?>

<?php if ($selVacancy !== null): ?>

    <div class="cm-banner">
        <span><strong><?= e((string)($selVacancy['title'] ?? 'Untitled vacancy')) ?></strong>
            <?php if (!empty($selVacancy['organisation'])): ?> · <?= e((string)$selVacancy['organisation']) ?><?php endif; ?></span>
        <span><?= e(rc_label((string)($selVacancy['status'] ?? ''))) ?> · <?= e(vm_closing_label($selVacancy['application_close_date'] ?? null, (string)($selVacancy['status'] ?? ''))) ?></span>
        <span>Requirements: <strong><?= e($sourceLabel($reqInfo['source'])) ?></strong></span>
        <a href="<?= e(url('recruiter/vacancy_view.php?id=' . $vacancyId)) ?>">Open vacancy</a>
    </div>

    <?php if (!$reqInfo['skills']): ?>
        <div class="rc-card"><div class="rc-card__body">
            <div class="rc-empty">
                <i class="fas fa-circle-question"></i>
                <strong>No required skills could be found for this vacancy</strong>
                <span>Its skills list, eligibility fields and text do not name any skill from the skills taxonomy<?= !empty($reqInfo['unmapped']) ? ' (listed skills not in the taxonomy: ' . e(implode(', ', $reqInfo['unmapped'])) . ')' : '' ?>. Use “Override this vacancy's requirements” above to pick the skills to compare against.</span>
            </div>
        </div></div>
    <?php else: ?>

        <div class="cm-chips" style="margin:-4px 0 18px">
            <?php foreach ($reqInfo['skills'] as $sid => $mand): ?>
                <span class="cm-chip <?= $mand ? 'cm-chip--m' : '' ?>"><?= e($catalog[$sid]['name']) ?><?= $mand ? ' · mandatory' : ' · preferred' ?></span>
            <?php endforeach; ?>
        </div>
        <?php if (!empty($reqInfo['unmapped'])): ?>
            <p class="cm-note" style="margin:-8px 0 18px">
                <i class="fas fa-circle-info"></i> Not scored, because they are not in the skills taxonomy and no candidate can hold them:
                <?= e(implode(', ', $reqInfo['unmapped'])) ?>.
            </p>
        <?php endif; ?>

        <section class="cm-stats">
            <div class="cm-stat"><i class="fas fa-list-check"></i><strong><?= count($reqInfo['skills']) ?></strong><span>Required skills</span></div>
            <div class="cm-stat"><i class="fas fa-users"></i><strong><?= number_format($evaluated) ?></strong><span>Consented candidates compared</span></div>
            <div class="cm-stat"><i class="fas fa-bullseye"></i><strong><?= number_format($shown) ?></strong><span>Candidates matched</span></div>
            <div class="cm-stat"><i class="fas fa-star"></i><strong><?= number_format($strong) ?></strong><span>Strong matches (80%+)</span></div>
        </section>

        <section class="rc-card rc-space">
            <div class="rc-card__header"><div><span class="rc-eyebrow">Results</span><h3>Best-fit candidates</h3></div></div>
            <div class="rc-card__body">
                <?php if (!$visible): ?>
                    <div class="rc-empty"><i class="fas fa-user-slash"></i><strong>No matching candidates</strong>
                        <span>No candidate has any of the required skills under the current filters.</span></div>
                <?php else: ?>
                    <div class="rc-table-wrap">
                        <table class="rc-table">
                            <thead><tr><th>#</th><th>Candidate</th><th>Match</th><th>Skills</th><th>Application</th><th>Actions</th></tr></thead>
                            <tbody>
                            <?php foreach ($visible as $i => $row):
                                $c = $row['cand']; $res = $row['res']; ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td class="cm-cell">
                                        <div class="cm-person">
                                            <div class="rc-avatar"><?= e(rc_initials($c['first'], $c['last'])) ?></div>
                                            <div>
                                                <strong><?= e($c['name']) ?></strong>
                                                <?php if ($c['title'] !== ''): ?><small><?= e($c['title']) ?></small><?php endif; ?>
                                                <?php $loc = implode(', ', array_unique(array_filter([$c['city'], $c['province']]))); ?>
                                                <?php if ($loc !== ''): ?><small><?= e($loc) ?><?= $row['samePlace'] ? ' · same province' : '' ?></small><?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php $renderScore($res); ?></td>
                                    <td class="cm-cell">
                                        <strong><?= (int)$res['matched'] ?> of <?= (int)$res['required'] ?></strong> required
                                        <small><?= (int)$res['verified'] ?> verified · skills fit <?= (int)$res['skill_score'] ?>%</small>
                                        <?php if ($res['missing_mandatory'] > 0): ?>
                                            <small class="cm-warn"><i class="fas fa-triangle-exclamation"></i> Missing <?= (int)$res['missing_mandatory'] ?> mandatory</small>
                                        <?php endif; ?>
                                        <?php $renderDetail($res, $focus); ?>
                                    </td>
                                    <td class="cm-cell">
                                        <?php if ($row['app'] !== null): ?>
                                            <span class="rc-status"><?= e(vm_stage_label((string)$row['app'])) ?></span>
                                        <?php else: ?>
                                            <small>Not applied</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="cm-actions">
                                            <a class="rc-btn rc-btn--primary rc-btn--small" href="<?= e(url('recruiter/candidate_view.php?id=' . (int)$c['id'])) ?>"><i class="fas fa-user"></i> Profile</a>
                                            <a class="rc-btn rc-btn--secondary rc-btn--small" href="<?= e(url('recruiter/shortlists.php?candidate_id=' . (int)$c['id'])) ?>"><i class="fas fa-plus"></i> Shortlist</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($shown > CM_PAGE_LIMIT): ?>
                        <p class="cm-note">Showing the top <?= (int)CM_PAGE_LIMIT ?> of <?= number_format($shown) ?> matches. Raise the minimum match to narrow the list.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

<?php elseif ($selCandidate !== null): ?>

    <div class="cm-banner">
        <span><strong><?= e($selCandidate['name']) ?></strong><?= $selCandidate['title'] !== '' ? ' · ' . e($selCandidate['title']) : '' ?></span>
        <span><?= count($candSkills) ?> recorded skill<?= count($candSkills) === 1 ? '' : 's' ?></span>
        <a href="<?= e(url('recruiter/candidate_view.php?id=' . $candidateId)) ?>">Open profile</a>
    </div>

    <section class="cm-stats">
        <div class="cm-stat"><i class="fas fa-briefcase"></i><strong><?= number_format($evaluated) ?></strong><span>Vacancies compared</span></div>
        <div class="cm-stat"><i class="fas fa-bullseye"></i><strong><?= number_format($shown) ?></strong><span>Vacancies matched</span></div>
        <div class="cm-stat"><i class="fas fa-star"></i><strong><?= number_format($strong) ?></strong><span>Strong matches (80%+)</span></div>
        <div class="cm-stat"><i class="fas fa-circle-question"></i><strong><?= number_format($noReqCount) ?></strong><span>Skipped: no requirements found</span></div>
    </section>

    <section class="rc-card rc-space">
        <div class="rc-card__header"><div><span class="rc-eyebrow">Results</span><h3>Best-fit vacancies</h3></div></div>
        <div class="rc-card__body">
            <?php if (!$candSkills): ?>
                <div class="rc-empty"><i class="fas fa-code"></i><strong>No skills recorded</strong>
                    <span>This candidate has not added any skills, so there is nothing to match.</span></div>
            <?php elseif (!$visible): ?>
                <div class="rc-empty"><i class="fas fa-briefcase"></i><strong>No matching vacancies</strong>
                    <span><?= $evaluated === 0
                        ? ($noReqCount > 0
                            ? number_format($noReqCount) . ' vacancies were skipped because no required skills could be found for them. See the data check below.'
                            : 'There are no vacancies to compare against.')
                        : 'None of the compared vacancies requires a skill this candidate has, under the current filters.' ?></span></div>
            <?php else: ?>
                <div class="rc-table-wrap">
                    <table class="rc-table">
                        <thead><tr><th>#</th><th>Vacancy</th><th>Match</th><th>Skills</th><th>Application</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($visible as $i => $row):
                            $v = $row['vac']; $res = $row['res'];
                            $vStatus = (string)($v['status'] ?? '');
                            $loc = implode(', ', array_unique(array_filter([trim((string)($v['city'] ?? '')), trim((string)($v['province'] ?? ''))]))); ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td class="cm-cell">
                                    <strong><?= e((string)($v['title'] ?? 'Untitled vacancy')) ?></strong>
                                    <?php if (!empty($v['organisation'])): ?><small><?= e((string)$v['organisation']) ?></small><?php endif; ?>
                                    <?php if ($loc !== ''): ?><small><?= e($loc) ?><?= $row['samePlace'] ? ' · same province' : '' ?></small><?php endif; ?>
                                    <small><?= e(rc_label($vStatus)) ?> · <?= e(vm_closing_label($v['application_close_date'] ?? null, $vStatus)) ?></small>
                                    <small>Requirements: <?= e($sourceLabel((string)$row['source'])) ?></small>
                                </td>
                                <td><?php $renderScore($res); ?></td>
                                <td class="cm-cell">
                                    <strong><?= (int)$res['matched'] ?> of <?= (int)$res['required'] ?></strong> required
                                    <small><?= (int)$res['verified'] ?> verified · skills fit <?= (int)$res['skill_score'] ?>%</small>
                                    <?php if ($res['missing_mandatory'] > 0): ?>
                                        <small class="cm-warn"><i class="fas fa-triangle-exclamation"></i> Missing <?= (int)$res['missing_mandatory'] ?> mandatory</small>
                                    <?php endif; ?>
                                    <?php $renderDetail($res, $focus); ?>
                                </td>
                                <td class="cm-cell">
                                    <?php if ($row['app'] !== null): ?>
                                        <span class="rc-status"><?= e(vm_stage_label((string)$row['app'])) ?></span>
                                    <?php else: ?>
                                        <small>Not applied</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="cm-actions">
                                        <a class="rc-btn rc-btn--primary rc-btn--small" href="<?= e(url('recruiter/vacancy_view.php?id=' . (int)$v['id'])) ?>"><i class="fas fa-briefcase"></i> Vacancy</a>
                                        <a class="rc-btn rc-btn--secondary rc-btn--small" href="<?= e($self . '?mode=vacancy&vacancy_id=' . (int)$v['id'] . '&scope=all') ?>"><i class="fas fa-users"></i> All candidates</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($shown > CM_PAGE_LIMIT): ?>
                    <p class="cm-note">Showing the top <?= (int)CM_PAGE_LIMIT ?> of <?= number_format($shown) ?> matches.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

<?php endif; ?>

<?php
$statusCounts = [];
foreach ($vacancies as $dv) {
    $st = (string)($dv['status'] ?? '');
    $statusCounts[$st] = ($statusCounts[$st] ?? 0) + 1;
}
$diagReq = $pool ? cm_build_requirements($conn, $pool, $catalog, $oppColumns) : [];
$withReq = 0;
foreach ($diagReq as $dr) {
    if ($dr['skills']) $withReq++;
}
$linkT = cm_link_table($conn);
$eligT = cm_eligibility_table($conn);
$expT  = cm_find_owner_table($conn, ['job_title', 'company', 'start_date'], 'experience');
$qualT = cm_find_owner_table($conn, ['name', 'institution', 'year_completed'], 'qualification');
?>
<details class="cm-diag">
    <summary>Data check (what this page found)</summary>
    <ul>
        <li>Vacancies loaded: <strong><?= count($vacancies) ?></strong>
            <?php if ($statusCounts): ?>(<?= e(implode(', ', array_map(
                static fn($k, $n) => ($k !== '' ? $k : 'no status') . ': ' . $n,
                array_keys($statusCounts), $statusCounts))) ?>)<?php endif; ?></li>
        <li>Open for applications: <strong><?= count($openVacancies) ?></strong>; compared in this view (<?= e($scope === 'all' ? 'all vacancies' : 'open only') ?>): <strong><?= count($pool) ?></strong></li>
        <li>Compared vacancies with at least one detectable required skill: <strong><?= $withReq ?></strong> of <?= count($pool) ?></li>
        <li>Vacancy skills table: <strong><?= $linkT ? e($linkT['table']) : 'not found' ?></strong>;
            eligibility table: <strong><?= $eligT ? e($eligT['table']) : 'not found' ?></strong></li>
        <li>Active skills in the taxonomy: <strong><?= count($catalog) ?></strong>; consented candidates: <strong><?= count($candidates) ?></strong></li>
        <li>Candidate work-history table: <strong><?= $expT ? e($expT['table']) : 'not found' ?></strong>;
            qualifications table: <strong><?= $qualT ? e($qualT['table']) : 'not found' ?></strong></li>
    </ul>
</details>

<p class="cm-note">
    <strong>How the suitability score works.</strong> Skills carry 60 points. Each required skill is worth 1 (2 if mandatory);
    a held skill earns full credit when verified, 80% when verification is pending and 60% when unverified, and nothing if it failed verification.
    The other criteria count only when the vacancy states them:
    qualification 20 (the candidate's highest level, from their profile level or the names of their recorded qualifications, must reach the lowest level the vacancy names, e.g. “diploma or degree” needs a diploma; field of study is shown but not scored),
    experience 10 (total years in the candidate's work history, overlapping jobs counted once, proportional to the years asked for; all work experience counts, whatever the field),
    location 5 (same province; skipped for remote vacancies) and availability 5 (available by the start date, or by today if it has passed).
    A stated criterion the candidate has not provided earns nothing and is flagged “Not provided”.
    The score is the points earned as a percentage of the points that apply. Bands: 80%+ strong, 60–79% good, 35–59% partial, below 35% low.
    Other requirements are shown for your judgement and never scored, and only candidates holding at least one required skill are listed.
    Vacancy skills come from its Required Skills list and eligibility lists (typed names are matched to the skills taxonomy by exact name,
    then by any taxonomy skill named inside the text); if a vacancy has neither, skills named in its own text are used.
    Only active candidates with valid recruiter-discovery consent are compared. Review the requirements shown, or override the skills, before relying on a ranking.
</p>

<?php endif; ?>

<?php require __DIR__ . '/_layout_end.php'; ?>
