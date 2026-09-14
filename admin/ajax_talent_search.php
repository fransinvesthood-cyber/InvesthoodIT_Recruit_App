<?php
/**
 * ================================================
 * INVESTHOOD IT - AJAX: Talent Intelligence Search
 * ================================================
 * Server-side talent search across the FULL
 * candidate pool (not just the dashboard preview).
 * Every active filter is combined with AND
 * semantics so multi-filter results are accurate.
 *
 * Role: Administrator only.
 *
 * GET params (all optional, slugified values):
 *   qualifications  - comma-separated qualification-level slugs
 *   qual_names      - comma-separated qualification-name slugs
 *   skills          - comma-separated skill slugs
 *   career          - single career-interest slug
 *   location        - single city slug
 *   availability    - single availability-label slug
 *   experience      - single experience-level slug
 *
 * Returns JSON: { total, html } or { error }.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('admin');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

// ---- Split a comma-separated query param into a clean slug list ----
function split_param($raw): array
{
    $out = [];
    if ($raw === null || (string) $raw === '') return $out;
    foreach (explode(',', (string) $raw) as $part) {
        $part = trim((string) $part);
        if ($part !== '') $out[] = $part;
    }
    return $out;
}

$qualSlugs      = split_param($_GET['qualifications'] ?? '');
$qualNameSlugs  = split_param($_GET['qual_names'] ?? '');
$skillSlugs     = split_param($_GET['skills'] ?? '');
$careerSlug     = trim((string) ($_GET['career'] ?? ''));
$locationSlug   = trim((string) ($_GET['location'] ?? ''));
$availabilitySlug = trim((string) ($_GET['availability'] ?? ''));
$experienceSlug = trim((string) ($_GET['experience'] ?? ''));

// ---- BASE query (all active candidates) ----
$selectCols = "u.id, u.first_name, u.last_name, u.email, u.created_at,
               cp.professional_title, cp.completion_percent, cp.city,
               avs.label AS availability_label";

$sql = "SELECT " . $selectCols . "
        FROM users u
        INNER JOIN roles r ON r.id = u.role_id
        LEFT JOIN candidate_profiles cp ON cp.user_id = u.id
        LEFT JOIN availability_statuses avs ON avs.id = cp.availability_status_id";

$groupCols = "u.id, u.first_name, u.last_name, u.email, u.created_at,
              cp.professional_title, cp.completion_percent, cp.city,
              avs.label";

$types   = '';
$params  = [];
$having  = [];
$hasQualsJoin = false;

// ---- SKILLS filter (candidate must have ANY selected skill) ----
if (count($skillSlugs) > 0) {
    $sql .= "
        LEFT JOIN candidate_skills cs ON cs.user_id = u.id
        LEFT JOIN skills s ON s.id = cs.skill_id";
    $inList = [];
    foreach ($skillSlugs as $sk) {
        $inList[] = '?';
        $types .= 's';
        $params[] = $sk;
    }
    $having[] = "SUM(CASE WHEN LOWER(REPLACE(s.name, ' ', '-')) IN (" . implode(',', $inList) . ") THEN 1 ELSE 0 END) > 0";
}

// ---- QUALIFICATION LEVELS filter (ANY selected) ----
if (count($qualSlugs) > 0) {
    if (!$hasQualsJoin) {
        $sql .= "
        LEFT JOIN qualifications q ON q.user_id = u.id";
        $hasQualsJoin = true;
    }
    $inList = [];
    foreach ($qualSlugs as $ql) {
        $inList[] = '?';
        $types .= 's';
        $params[] = $ql;
    }
    $having[] = "SUM(CASE WHEN LOWER(REPLACE(q.level, ' ', '-')) IN (" . implode(',', $inList) . ") THEN 1 ELSE 0 END) > 0";
}

// ---- QUALIFICATION NAMES filter (ANY selected) ----
if (count($qualNameSlugs) > 0) {
    if (!$hasQualsJoin) {
        $sql .= "
        LEFT JOIN qualifications q ON q.user_id = u.id";
        $hasQualsJoin = true;
    }
    $inList = [];
    foreach ($qualNameSlugs as $qn) {
        $inList[] = '?';
        $types .= 's';
        $params[] = $qn;
    }
    $having[] = "SUM(CASE WHEN LOWER(REPLACE(q.name, ' ', '-')) IN (" . implode(',', $inList) . ") THEN 1 ELSE 0 END) > 0";
}

// ---- CAREER INTEREST filter (single value, exact token match) ----
if ($careerSlug !== '') {
    $types .= 's';
    $params[] = $careerSlug;
    $having[] = "SUM(FIND_IN_SET(?, LOWER(REPLACE(REPLACE(LTRIM(RTRIM(cp.career_interests)), ', ', ','), ' ', '-')))) > 0";
}

// ---- LOCATION filter (single city) ----
if ($locationSlug !== '') {
    $types .= 's';
    $params[] = $locationSlug;
    $having[] = "SUM(CASE WHEN LOWER(REPLACE(LTRIM(RTRIM(cp.city)), ' ', '-')) = ? THEN 1 ELSE 0 END) > 0";
}

// ---- AVAILABILITY filter (single label) ----
if ($availabilitySlug !== '') {
    $types .= 's';
    $params[] = $availabilitySlug;
    $having[] = "SUM(CASE WHEN LOWER(REPLACE(LTRIM(RTRIM(avs.label)), ' ', '-')) = ? THEN 1 ELSE 0 END) > 0";
}

// ---- EXPERIENCE LEVEL filter (single level) ----
$expCond = '';
if ($experienceSlug === 'entry-level') {
    $expCond = "MAX(COALESCE(wex.total_years,0)) < 1";
} elseif ($experienceSlug === 'junior') {
    $expCond = "MAX(COALESCE(wex.total_years,0)) >= 1 AND MAX(COALESCE(wex.total_years,0)) < 3";
} elseif ($experienceSlug === 'mid-level') {
    $expCond = "MAX(COALESCE(wex.total_years,0)) >= 3 AND MAX(COALESCE(wex.total_years,0)) < 5";
} elseif ($experienceSlug === 'senior') {
    $expCond = "MAX(COALESCE(wex.total_years,0)) >= 5 AND MAX(COALESCE(wex.total_years,0)) < 10";
} elseif ($experienceSlug === 'expert') {
    $expCond = "MAX(COALESCE(wex.total_years,0)) >= 10";
}

if ($expCond !== '') {
    $sql .= "
        LEFT JOIN (
            SELECT we.user_id,
                   SUM(TIMESTAMPDIFF(YEAR, we.start_date, COALESCE(we.end_date, CURDATE()))) AS total_years
            FROM work_experience we
            WHERE we.start_date IS NOT NULL
            GROUP BY we.user_id
        ) wex ON wex.user_id = u.id";
    $having[] = $expCond;
}

// ---- Assemble final queries ----
$baseWhere = "WHERE r.slug = 'candidate' AND u.status = 'active'";
$groupBy   = "GROUP BY " . $groupCols;
$havingSql = $having ? " HAVING " . implode(' AND ', $having) : '';

$mainQuery = $sql . " " . $baseWhere . " " . $groupBy . $havingSql . " ORDER BY u.created_at DESC LIMIT 500";
$countQuery = "SELECT COUNT(*) AS total FROM (" . $sql . " " . $baseWhere . " " . $groupBy . $havingSql . ") t";

$rows = [];
$total = 0;

try {
    $rows = Database::fetchAll($mainQuery, $types, $params);
} catch (Exception $ex) {
    error_log('[TALENT-HUB] Search main query: ' . $ex->getMessage() . ' | SQL: ' . $mainQuery);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Talent search failed.']);
    exit;
}

try {
    $totalRow = Database::fetchOne($countQuery, $types, $params);
    $total = (int) ($totalRow['total'] ?? 0);
} catch (Exception $ex) {
    error_log('[TALENT-HUB] Search count query: ' . $ex->getMessage());
    $total = count($rows);
}

// ---- Render candidate cards (same markup as the dashboard) ----
$html = '';
foreach ($rows as $candidate) {
    $cId     = (int) ($candidate['id'] ?? 0);
    $cName   = e(($candidate['first_name'] ?? '') . ' ' . ($candidate['last_name'] ?? ''));
    $cEmail  = e($candidate['email'] ?? '');
    $cTitle  = e($candidate['professional_title'] ?? 'Candidate');
    $cCompletion = (int) ($candidate['completion_percent'] ?? 0);
    $cRegistered = date('M j, Y', strtotime($candidate['created_at']));
    $cInitials = strtoupper(substr($candidate['first_name'] ?? '', 0, 1) . substr($candidate['last_name'] ?? '', 0, 1));
    $cCity = e(strtolower(str_replace(' ', '-', $candidate['city'] ?? '')));
    $cAvailability = e(strtolower(str_replace(' ', '-', $candidate['availability_label'] ?? '')));

    $html .= '<div class="admin-programme-card" style="flex-direction:row;align-items:center;gap:1rem;" '
          .  'data-city="' . $cCity . '" data-availability="' . $cAvailability . '" '
          .  'data-title="' . e(strtolower($cTitle)) . '" data-name="' . e(strtolower($cName)) . '">'
          .  '<div style="width:48px;height:48px;border-radius:50%;background:var(--primary-bg);color:var(--primary);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1rem;">' . $cInitials . '</div>'
          .  '<div style="flex:1;min-width:0;">'
          .    '<div style="font-weight:700;color:var(--dark);">' . ($cName !== '' ? $cName : 'Unnamed Candidate') . '</div>'
          .    '<div style="font-size:0.8rem;color:var(--text-light);">' . $cTitle . '</div>'
          .    '<div style="font-size:0.75rem;color:var(--text-lighter);">' . $cEmail . '</div>'
          .  '</div>'
          .  '<div class="admin-programme-card__side" style="display:flex;flex-direction:column;align-items:flex-end;gap:0.45rem;text-align:right;">'
          .    '<div style="font-size:0.8rem;font-weight:600;color:var(--success);">' . $cCompletion . '% complete</div>'
          .    '<a class="btn btn--outline btn--sm" href="' . e('candidate_profile.php?user_id=' . $cId) . '"><i class="fas fa-user"></i> View Profile</a>'
          .  '</div>'
          . '</div>';
}

if ($html === '') {
    $html = '<div class="admin-empty-state" style="grid-column:1/-1;">'
          . '<div class="admin-empty-state__icon"><i class="fas fa-search"></i></div>'
          . '<h3>No matches found</h3>'
          . '<p>No candidates match your search criteria. Try adjusting your filters.</p>'
          . '</div>';
}

header('Content-Type: application/json');
echo json_encode(['total' => $total, 'html' => $html]);
exit;