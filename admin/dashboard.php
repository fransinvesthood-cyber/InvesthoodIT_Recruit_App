<?php
/**
 * ================================================
 * INVESTHOOD IT - Admin Dashboard (root entry)
 * ================================================
 * Role: Administrator
 * Accessible only by users with role 'admin'.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// Protect — only Administrator
require_role('admin');

$user = current_user();
$flashes = render_flashes();

// Talent Intelligence Hub filter data
$skills = Database::fetchAll("SELECT name FROM skills WHERE is_active = 1 ORDER BY name");
$citiesRow = Database::fetchOne("SELECT GROUP_CONCAT(DISTINCT city ORDER BY city SEPARATOR ',') AS cities FROM candidate_profiles WHERE city IS NOT NULL AND city != ''");
$cityList = array_filter(explode(',', $citiesRow['cities'] ?? ''));

// Fetch initial candidates for Talent Intelligence Hub (show all by default)
$initialCandidates = [];
try {
    $initialCandidates = Database::fetchAll(
        "SELECT DISTINCT u.id, u.first_name, u.last_name, u.email, u.created_at,
                cp.professional_title, cp.completion_percent, cp.city
         FROM users u
         INNER JOIN roles r ON r.id = u.role_id
         LEFT JOIN candidate_profiles cp ON cp.user_id = u.id
         WHERE r.slug = 'candidate' AND u.status = 'active'
         ORDER BY cp.completion_percent DESC, u.created_at DESC
         LIMIT 50"
    );
} catch (Exception $ex) {
    error_log('[TALENT-HUB] Initial candidates: ' . $ex->getMessage());
}

// ------------------------------------------------------------
// Real dynamic programme statistics (from MySQL, not hard-coded)
// ------------------------------------------------------------
$progCountsByStatus = Programme::countsByStatus();
$totalProgrammes     = array_sum($progCountsByStatus);
$activeProgrammes    = (int) ($progCountsByStatus['active'] ?? 0);
$draftProgrammes     = (int) ($progCountsByStatus['draft'] ?? 0);
$completedProgrammes = (int) ($progCountsByStatus['completed'] ?? 0);
$archivedProgrammes  = (int) ($progCountsByStatus['archived'] ?? 0);

// Active cohorts across all programmes
$activeCohortsRow = Database::fetchOne("SELECT COUNT(*) AS cnt FROM cohorts WHERE status = 'active'");
$activeCohorts = (int) ($activeCohortsRow['cnt'] ?? 0);

// Total capacity across all cohorts
$capacityRow = Database::fetchOne("SELECT COALESCE(SUM(max_capacity),0) AS total FROM cohorts");
$totalCapacity = (int) ($capacityRow['total'] ?? 0);

// Total current participants (selected/onboarded/active)
$participantsRow = Database::fetchOne(
    "SELECT COUNT(*) AS cnt FROM cohort_participants WHERE status IN ('selected','onboarded','active')"
);
$totalParticipants = (int) ($participantsRow['cnt'] ?? 0);

// Total applications across cohorts
$appsRow = Database::fetchOne("SELECT COALESCE(SUM(applications_count),0) AS total FROM cohorts");
$totalApplications = (int) ($appsRow['total'] ?? 0);

// Total candidates (active users with the candidate role)
$candidatesRow = Database::fetchOne(
    "SELECT COUNT(*) AS cnt FROM users u
     INNER JOIN roles r ON r.id = u.role_id
     WHERE r.slug = 'candidate' AND u.status = 'active'"
);
$totalCandidates = (int) ($candidatesRow['cnt'] ?? 0);

// New candidates registered within the last 30 days
$newCandidatesRow = Database::fetchOne(
    "SELECT COUNT(*) AS cnt FROM users u
     INNER JOIN roles r ON r.id = u.role_id
     WHERE r.slug = 'candidate' AND u.status = 'active'
       AND u.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)"
);
$newCandidatesMonth = (int) ($newCandidatesRow['cnt'] ?? 0);

// Profile completeness (average completion % + count of complete profiles >= 80%)
$profileStatsRow = Database::fetchOne(
    "SELECT ROUND(AVG(completion_percent)) AS avg_completion,
            COUNT(*) AS total_profiles,
            COALESCE(SUM(completion_percent >= 80), 0) AS complete_profiles
     FROM candidate_profiles"
);
$avgProfileCompleteness = (int) ($profileStatsRow['avg_completion'] ?? 0);
$totalCandidateProfiles  = (int) ($profileStatsRow['total_profiles'] ?? 0);
$completeProfiles        = (int) ($profileStatsRow['complete_profiles'] ?? 0);

// ------------------------------------------------------------
// Talent Intelligence Hub — Real-time Data from Database
// ------------------------------------------------------------

// --- Key Talent Metrics ---

// Active candidates (with active profile)
try {
$talentActiveCandidatesRow = Database::fetchOne(
    "SELECT COUNT(*) AS cnt FROM candidate_profiles cp
     INNER JOIN users u ON u.id = cp.user_id
     INNER JOIN roles r ON r.id = u.role_id
     WHERE r.slug = 'candidate' AND u.status = 'active' AND cp.is_active = 1"
);
$talentActiveCandidates = (int) ($talentActiveCandidatesRow['cnt'] ?? 0);
} catch (Exception $ex) { error_log('[TALENT-HUB] Active candidates: ' . $ex->getMessage()); $talentActiveCandidates = 0; }

// --- Candidate Distribution by Cohort ---
try {
$talentCohortDistribution = Database::fetchAll(
    "SELECT c.id, c.name AS cohort_name, c.status AS cohort_status,
            p.name AS programme_name,
            c.max_capacity,
            COUNT(cp.user_id) AS participant_count
     FROM cohorts c
     INNER JOIN programmes p ON p.id = c.programme_id
     LEFT JOIN cohort_participants cp ON cp.cohort_id = c.id AND cp.status IN ('selected','onboarded','active')
     GROUP BY c.id
     ORDER BY participant_count DESC, c.name ASC
     LIMIT 10"
);
} catch (Exception $ex) { error_log('[TALENT-HUB] Cohort distribution: ' . $ex->getMessage()); $talentCohortDistribution = []; }

// --- Skills Distribution (top skills by candidate count) ---
try {
$talentSkillsDistribution = Database::fetchAll(
    "SELECT s.id, s.name, s.category,
            COUNT(cs.user_id) AS candidate_count
     FROM skills s
     INNER JOIN candidate_skills cs ON cs.skill_id = s.id
     INNER JOIN users u ON u.id = cs.user_id
     INNER JOIN roles r ON r.id = u.role_id
     WHERE r.slug = 'candidate' AND u.status = 'active'
     GROUP BY s.id
     ORDER BY candidate_count DESC
     LIMIT 15"
);
} catch (Exception $ex) { error_log('[TALENT-HUB] Skills distribution: ' . $ex->getMessage()); $talentSkillsDistribution = []; }

// --- Skills by Category ---
try {
$talentSkillsByCategory = Database::fetchAll(
    "SELECT s.category,
            COUNT(DISTINCT s.id) AS skill_count,
            COUNT(cs.user_id) AS total_mentions
     FROM skills s
     INNER JOIN candidate_skills cs ON cs.skill_id = s.id
     INNER JOIN users u ON u.id = cs.user_id
     INNER JOIN roles r ON r.id = u.role_id
     WHERE r.slug = 'candidate' AND u.status = 'active'
     GROUP BY s.category
     ORDER BY total_mentions DESC"
);
} catch (Exception $ex) { error_log('[TALENT-HUB] Skills by category: ' . $ex->getMessage()); $talentSkillsByCategory = []; }

// --- Qualification Trends ---
try {
$talentQualificationLevels = Database::fetchAll(
    "SELECT COALESCE(NULLIF(q.level, ''), 'Not Specified') AS qualification_level,
            COUNT(q.id) AS qualification_count
     FROM qualifications q
     INNER JOIN users u ON u.id = q.user_id
     INNER JOIN roles r ON r.id = u.role_id
     WHERE r.slug = 'candidate' AND u.status = 'active'
     GROUP BY COALESCE(NULLIF(q.level, ''), 'Not Specified')
     ORDER BY qualification_count DESC"
);
} catch (Exception $ex) { error_log('[TALENT-HUB] Qualification levels: ' . $ex->getMessage()); $talentQualificationLevels = []; }

// --- Top Qualifications ---
try {
$talentTopQualifications = Database::fetchAll(
    "SELECT q.qualification_name,
            COUNT(q.id) AS candidate_count
     FROM qualifications q
     INNER JOIN users u ON u.id = q.user_id
     INNER JOIN roles r ON r.id = u.role_id
     WHERE r.slug = 'candidate' AND u.status = 'active'
     GROUP BY q.qualification_name
     ORDER BY candidate_count DESC
     LIMIT 10"
);
} catch (Exception $ex) { error_log('[TALENT-HUB] Top qualifications: ' . $ex->getMessage()); $talentTopQualifications = []; }

// --- Experience Levels ---
try {
$talentExperienceLevels = Database::fetchAll(
    "SELECT 
        CASE 
            WHEN total_years < 1 THEN 'Entry Level (< 1 year)'
            WHEN total_years < 3 THEN 'Junior (1-3 years)'
            WHEN total_years < 5 THEN 'Mid-Level (3-5 years)'
            WHEN total_years < 10 THEN 'Senior (5-10 years)'
            ELSE 'Expert (10+ years)'
        END AS experience_level,
        COUNT(*) AS candidate_count
     FROM (
        SELECT we.user_id,
               SUM(TIMESTAMPDIFF(YEAR, we.start_date, COALESCE(we.end_date, CURDATE()))) AS total_years
        FROM work_experience we
        INNER JOIN users u ON u.id = we.user_id
        INNER JOIN roles r ON r.id = u.role_id
        WHERE r.slug = 'candidate' AND u.status = 'active'
          AND we.start_date IS NOT NULL
        GROUP BY we.user_id
     ) AS candidate_experience
     GROUP BY CASE 
            WHEN total_years < 1 THEN 'Entry Level (< 1 year)'
            WHEN total_years < 3 THEN 'Junior (1-3 years)'
            WHEN total_years < 5 THEN 'Mid-Level (3-5 years)'
            WHEN total_years < 10 THEN 'Senior (5-10 years)'
            ELSE 'Expert (10+ years)'
        END
     ORDER BY candidate_count DESC"
);
} catch (Exception $ex) { error_log('[TALENT-HUB] Experience levels: ' . $ex->getMessage()); $talentExperienceLevels = []; }

// --- Geographic Distribution (by city) ---
try {
$talentGeoDistribution = Database::fetchAll(
    "SELECT COALESCE(NULLIF(cp.city, ''), 'Not Specified') AS city,
            COUNT(cp.user_id) AS candidate_count
     FROM candidate_profiles cp
     INNER JOIN users u ON u.id = cp.user_id
     INNER JOIN roles r ON r.id = u.role_id
     WHERE r.slug = 'candidate' AND u.status = 'active' AND cp.is_active = 1
     GROUP BY COALESCE(NULLIF(cp.city, ''), 'Not Specified')
     ORDER BY candidate_count DESC
     LIMIT 10"
);
} catch (Exception $ex) { error_log('[TALENT-HUB] Geo distribution: ' . $ex->getMessage()); $talentGeoDistribution = []; }

// --- Geographic Distribution (by province from cohorts) ---
try {
$talentProvinceDistribution = Database::fetchAll(
    "SELECT COALESCE(NULLIF(c.province, ''), 'Not Specified') AS province,
            COUNT(DISTINCT cp.user_id) AS candidate_count
     FROM cohorts c
     INNER JOIN cohort_participants cp ON cp.cohort_id = c.id AND cp.status IN ('selected','onboarded','active')
     INNER JOIN users u ON u.id = cp.user_id
     WHERE u.status = 'active'
     GROUP BY COALESCE(NULLIF(c.province, ''), 'Not Specified')
     ORDER BY candidate_count DESC"
);
} catch (Exception $ex) { error_log('[TALENT-HUB] Province distribution: ' . $ex->getMessage()); $talentProvinceDistribution = []; }

// --- Availability Status ---
try {
$talentAvailability = Database::fetchAll(
    "SELECT avs.label AS availability_label,
            COUNT(cp.user_id) AS candidate_count
     FROM candidate_profiles cp
     INNER JOIN users u ON u.id = cp.user_id
     INNER JOIN roles r ON r.id = u.role_id
     LEFT JOIN availability_statuses avs ON avs.id = cp.availability_status_id
     WHERE r.slug = 'candidate' AND u.status = 'active' AND cp.is_active = 1
     GROUP BY avs.label
     ORDER BY candidate_count DESC"
);
} catch (Exception $ex) { error_log('[TALENT-HUB] Availability: ' . $ex->getMessage()); $talentAvailability = []; }

// --- Employment Status Distribution ---
try {
$talentEmploymentStatus = Database::fetchAll(
    "SELECT COALESCE(NULLIF(cp.employment_status, ''), 'Not Specified') AS employment_status,
            COUNT(cp.user_id) AS candidate_count
     FROM candidate_profiles cp
     INNER JOIN users u ON u.id = cp.user_id
     INNER JOIN roles r ON r.id = u.role_id
     WHERE r.slug = 'candidate' AND u.status = 'active' AND cp.is_active = 1
     GROUP BY COALESCE(NULLIF(cp.employment_status, ''), 'Not Specified')
     ORDER BY candidate_count DESC"
);
} catch (Exception $ex) { error_log('[TALENT-HUB] Employment status: ' . $ex->getMessage()); $talentEmploymentStatus = []; }

// --- Recent Candidate Activity (last 30 days) ---
try {
$talentRecentActivity = Database::fetchAll(
    "SELECT u.id, u.first_name, u.last_name, u.email, u.created_at,
            cp.professional_title, cp.completion_percent, cp.city, cp.employment_status,
            cp.availability_status_id,
            avs.label AS availability_label
     FROM users u
     INNER JOIN roles r ON r.id = u.role_id
     LEFT JOIN candidate_profiles cp ON cp.user_id = u.id
     LEFT JOIN availability_statuses avs ON avs.id = cp.availability_status_id
     WHERE r.slug = 'candidate' AND u.status = 'active'
     ORDER BY u.created_at DESC
     LIMIT 10"
);
} catch (Exception $ex) { error_log('[TALENT-HUB] Recent activity: ' . $ex->getMessage()); $talentRecentActivity = []; }

// --- Fetch skills for all candidates (for filtering) ---
try {
$candidateSkillsRaw = Database::fetchAll(
    "SELECT cs.user_id, s.name AS skill_name
     FROM candidate_skills cs
     INNER JOIN skills s ON s.id = cs.skill_id
     INNER JOIN users u ON u.id = cs.user_id
     INNER JOIN roles r ON r.id = u.role_id
     WHERE r.slug = 'candidate' AND u.status = 'active'"
);
$candidateSkillsMap = [];
foreach ($candidateSkillsRaw as $row) {
    $uid = $row['user_id'];
    if (!isset($candidateSkillsMap[$uid])) $candidateSkillsMap[$uid] = [];
    $candidateSkillsMap[$uid][] = strtolower(str_replace(' ', '-', $row['skill_name']));
}
} catch (Exception $ex) { error_log('[TALENT-HUB] Candidate skills: ' . $ex->getMessage()); $candidateSkillsMap = []; }

// --- Fetch qualifications for all candidates (for filtering) ---
try {
$candidateQualificationsRaw = Database::fetchAll(
    "SELECT q.user_id, q.level AS qualification_level
     FROM qualifications q
     INNER JOIN users u ON u.id = q.user_id
     INNER JOIN roles r ON r.id = u.role_id
     WHERE r.slug = 'candidate' AND u.status = 'active'
       AND q.level IS NOT NULL AND q.level != ''"
);
$candidateQualificationsMap = [];
foreach ($candidateQualificationsRaw as $row) {
    $uid = $row['user_id'];
    if (!isset($candidateQualificationsMap[$uid])) $candidateQualificationsMap[$uid] = [];
    $candidateQualificationsMap[$uid][] = strtolower(str_replace(' ', '-', $row['qualification_level']));
}
} catch (Exception $ex) { error_log('[TALENT-HUB] Candidate qualifications: ' . $ex->getMessage()); $candidateQualificationsMap = []; }

// --- Fetch career interests for all candidates (for filtering) ---
try {
$candidateCareerInterestsRaw = Database::fetchAll(
    "SELECT user_id, career_interests
     FROM candidate_profiles
     WHERE career_interests IS NOT NULL AND career_interests != ''"
);
$candidateCareerMap = [];
foreach ($candidateCareerInterestsRaw as $row) {
    $uid = $row['user_id'];
    $interests = array_map('trim', explode(',', $row['career_interests']));
    foreach ($interests as $interest) {
        if ($interest !== '') {
            if (!isset($candidateCareerMap[$uid])) $candidateCareerMap[$uid] = [];
            $candidateCareerMap[$uid][] = strtolower(str_replace(' ', '-', $interest));
        }
    }
}
} catch (Exception $ex) { error_log('[TALENT-HUB] Candidate career interests: ' . $ex->getMessage()); $candidateCareerMap = []; }

// --- Career Interests for filter dropdown ---
try {
$careerInterestsList = Database::fetchAll(
    "SELECT DISTINCT career_interests
     FROM candidate_profiles
     WHERE career_interests IS NOT NULL AND career_interests != ''"
);
$careerInterestsOptions = [];
foreach ($careerInterestsList as $row) {
    $interests = array_map('trim', explode(',', $row['career_interests']));
    foreach ($interests as $interest) {
        if ($interest !== '' && !in_array($interest, $careerInterestsOptions)) {
            $careerInterestsOptions[] = $interest;
        }
    }
}
sort($careerInterestsOptions);
} catch (Exception $ex) { error_log('[TALENT-HUB] Career interests list: ' . $ex->getMessage()); $careerInterestsOptions = []; }

// --- Fetch qualification names for all candidates (for filtering) ---
try {
$candidateQualNamesRaw = Database::fetchAll(
    "SELECT user_id, name AS qualification_name
     FROM qualifications
     WHERE name IS NOT NULL AND name != ''"
);
$candidateQualNamesMap = [];
foreach ($candidateQualNamesRaw as $row) {
    $uid = $row['user_id'];
    if (!isset($candidateQualNamesMap[$uid])) $candidateQualNamesMap[$uid] = [];
    $candidateQualNamesMap[$uid][] = strtolower(str_replace(' ', '-', $row['qualification_name']));
}
} catch (Exception $ex) { error_log('[TALENT-HUB] Candidate qualification names: ' . $ex->getMessage()); $candidateQualNamesMap = []; }

// --- Qualification Names for filter dropdown ---
try {
$qualNamesList = Database::fetchAll(
    "SELECT DISTINCT name AS qualification_name
     FROM qualifications
     WHERE name IS NOT NULL AND name != ''"
);
$qualificationNamesOptions = array_map(function ($row) { return $row['qualification_name']; }, $qualNamesList);
sort($qualificationNamesOptions);
} catch (Exception $ex) { error_log('[TALENT-HUB] Qualification names list: ' . $ex->getMessage()); $qualificationNamesOptions = []; }

// --- Programme Participation Summary ---
try {
$talentProgrammeParticipation = Database::fetchAll(
    "SELECT p.id, p.name, p.type, p.status,
            COUNT(DISTINCT c.id) AS total_cohorts,
            COALESCE(SUM(c.max_capacity), 0) AS total_capacity,
            COUNT(DISTINCT cp.user_id) AS total_participants,
            COALESCE(SUM(c.applications_count), 0) AS total_applications
     FROM programmes p
     LEFT JOIN cohorts c ON c.programme_id = p.id
     LEFT JOIN cohort_participants cp ON cp.cohort_id = c.id AND cp.status IN ('selected','onboarded','active')
     GROUP BY p.id
     ORDER BY total_participants DESC, p.name ASC"
);
} catch (Exception $ex) { error_log('[TALENT-HUB] Programme participation: ' . $ex->getMessage()); $talentProgrammeParticipation = []; }

// --- Calculate trend percentages for key metrics (compare to previous month) ---
try {
$talentPrevMonthCandidatesRow = Database::fetchOne(
    "SELECT COUNT(*) AS cnt FROM users u
     INNER JOIN roles r ON r.id = u.role_id
     WHERE r.slug = 'candidate' AND u.status = 'active'
       AND u.created_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH)
       AND u.created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH)"
);
$talentPrevMonthCandidates = (int) ($talentPrevMonthCandidatesRow['cnt'] ?? 0);
$talentNewCandidateTrend = $talentPrevMonthCandidates > 0
    ? round((($newCandidatesMonth - $talentPrevMonthCandidates) / $talentPrevMonthCandidates) * 100, 1)
    : ($newCandidatesMonth > 0 ? 100 : 0);
} catch (Exception $ex) { error_log('[TALENT-HUB] Prev month candidates: ' . $ex->getMessage()); $talentPrevMonthCandidates = 0; $talentNewCandidateTrend = 0; }

// Profile completion trend
try {
$talentPrevAvgCompletionRow = Database::fetchOne(
    "SELECT ROUND(AVG(completion_percent)) AS avg_completion
     FROM candidate_profiles
     WHERE updated_at < DATE_SUB(NOW(), INTERVAL 1 MONTH)"
);
$talentPrevAvgCompletion = (int) ($talentPrevAvgCompletionRow['avg_completion'] ?? 0);
$talentCompletionTrend = $talentPrevAvgCompletion > 0
    ? round((($avgProfileCompleteness - $talentPrevAvgCompletion) / $talentPrevAvgCompletion) * 100, 1)
    : ($avgProfileCompleteness > 0 ? 100 : 0);
} catch (Exception $ex) { error_log('[TALENT-HUB] Prev avg completion: ' . $ex->getMessage()); $talentPrevAvgCompletion = 0; $talentCompletionTrend = 0; }

// ------------------------------------------------------------
// Real dynamic opportunity statistics (from MySQL, not hard-coded)
// ------------------------------------------------------------
$oppCountsByStatus = Opportunity::countsByStatus();
$totalOpportunities  = array_sum($oppCountsByStatus);
$publishedOpps       = (int) ($oppCountsByStatus['published'] ?? 0);
$draftOpps           = (int) ($oppCountsByStatus['draft'] ?? 0);
$closingSoonOpps     = (int) ($oppCountsByStatus['closing_soon'] ?? 0);
$closedOpps          = (int) ($oppCountsByStatus['closed'] ?? 0);
$archivedOpps        = (int) ($oppCountsByStatus['archived'] ?? 0);

// Opportunities created within the last 7 days (for the "new this week" metric)
$newOppsWeekRow = Database::fetchOne(
    "SELECT COUNT(*) AS cnt FROM opportunities WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
);
$newOppsWeek = (int) ($newOppsWeekRow['cnt'] ?? 0);

// Recent opportunities (most recently created)
$allOpportunities = Opportunity::all();
usort($allOpportunities, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

// Separate published opportunities from others for organized display
$publishedOpportunities = array_filter($allOpportunities, fn($o) => ($o['status'] ?? '') === 'published');
$otherOpportunities = array_filter($allOpportunities, fn($o) => ($o['status'] ?? '') !== 'published');

$recentOpportunities = array_slice($allOpportunities, 0, 5);
$recentPublishedOpportunities = array_slice($publishedOpportunities, 0, 5);
$recentOtherOpportunities = array_slice($otherOpportunities, 0, 5);

// Aggregate opportunity statistics
$oppAggregateStats = Opportunity::aggregateStats();
$totalPositions = (int) ($oppAggregateStats['total_positions'] ?? 0);
$totalOppApplications = (int) ($oppAggregateStats['total_applications'] ?? 0);
$avgApplicationsPerOpp = (float) ($oppAggregateStats['avg_applications'] ?? 0);

// Recent programmes (most recently updated)
$recentProgrammes = Programme::all();
usort($recentProgrammes, fn($a, $b) => strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? ''));
$recentProgrammes = array_slice($recentProgrammes, 0, 5);

// Active cohorts across all programmes (real data for the executive overview)
$activeCohortRows = Cohort::activeAll();
$activeCohortData = [];
foreach ($activeCohortRows as $ac) {
    $max = (int) ($ac['max_capacity'] ?? 0);
    $committed = (int) ($ac['committed'] ?? 0);
    $activeCohortData[] = [
        'id'            => (int) $ac['id'],
        'name'          => $ac['name'],
        'programme_name'=> $ac['programme_name'],
        'delivery_mode' => $ac['delivery_mode'],
        'location'      => $ac['location'],
        'province'      => $ac['province'],
        'start_date'    => $ac['start_date'],
        'end_date'      => $ac['end_date'],
        'max_capacity'  => $max,
        'applications'  => (int) ($ac['applications_count'] ?? 0),
        'committed'     => $committed,
        'available'     => max(0, $max - $committed),
        'pct'           => $max > 0 ? (int) round(($committed / $max) * 100) : 0,
    ];
}

// ------------------------------------------------------------
// Real dynamic application statistics (from MySQL, not hard-coded)
// ------------------------------------------------------------
$appStatusCounts = Application::adminCountByStatus();
$totalAppCount   = array_sum($appStatusCounts);

// Pipeline stage counts (grouped for the dashboard pipeline)
$appSubmitted    = (int) ($appStatusCounts['submitted'] ?? 0);
$appReview       = (int) (($appStatusCounts['eligibility_review'] ?? 0) + ($appStatusCounts['screened'] ?? 0));
$appAssessment   = (int) ($appStatusCounts['assessment'] ?? 0);
$appInterview    = (int) ($appStatusCounts['interview'] ?? 0);
$appWaitlisted   = (int) ($appStatusCounts['waitlisted'] ?? 0);
$appSelected     = (int) ($appStatusCounts['selected'] ?? 0);
$appRejected     = (int) ($appStatusCounts['rejected'] ?? 0);
$appWithdrawn    = (int) ($appStatusCounts['withdrawn'] ?? 0);

// Recent applications for the dashboard table (latest 10)
$recentAppsResult = Application::adminList([], 1, 10);
$recentApplications = $recentAppsResult['records'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Administration Dashboard - Investhood IT Programme & Scarce Skills Platform">
  <title>Admin Dashboard | Investhood IT</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.min.css" crossorigin="anonymous">
<link rel="stylesheet" href="<?= url('css/styles.css') ?>">
  <link rel="stylesheet" href="<?= url('css/admin_programmes.css') ?>">
  <link rel="stylesheet" href="<?= url('css/admin_opportunities.css') ?>">
  <link rel="stylesheet" href="<?= url('css/admin_applications.css') ?>">
</head>
<body class="dashboard-page admin-dashboard">

  <!-- =============================================
       ADMIN DASHBOARD LAYOUT
       ============================================= -->
  <div class="dashboard">

    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar admin-sidebar" id="adminSidebar">
      <div class="sidebar__header">
        <a href="<?= url('index.php') ?>" class="logo">
          <span class="logo__icon"><i class="fas fa-code"></i></span>
          <span class="logo__text">Investhood <span class="logo__accent">IT</span></span>
        </a>
        <button class="sidebar__close" id="sidebarClose" aria-label="Close sidebar">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <nav class="sidebar__nav">
        <div class="sidebar__section-label">Command Centre</div>
        <ul class="sidebar__menu">
          <li><a href="#admin-executive" class="sidebar__link active" data-section="executive"><i class="fas fa-th-large"></i> Executive Overview</a></li>
          <li><a href="#admin-analytics" class="sidebar__link" data-section="analytics"><i class="fas fa-chart-pie"></i> Analytics</a></li>
          <li><a href="#admin-alerts" class="sidebar__link" data-section="alerts"><i class="fas fa-exclamation-triangle"></i> Alerts <span class="alert-badge-sidebar">6</span></a></li>
        </ul>

<div class="sidebar__section-label">Management</div>
        <ul class="sidebar__menu">
<li><a href="#admin-programmes" class="sidebar__link" data-section="programmes"><i class="fas fa-graduation-cap"></i> Programmes</a></li>
          <li><a href="#admin-opportunities" class="sidebar__link" data-section="opportunities"><i class="fas fa-briefcase"></i> Opportunities</a></li>
          <li><a href="#admin-applications" class="sidebar__link" data-section="applications"><i class="fas fa-file-alt"></i> Applications</a></li>
          <li><a href="#admin-placements" class="sidebar__link" data-section="placements"><i class="fas fa-handshake"></i> Placements</a></li>
          <li><a href="<?= url('admin/dashboard.php') ?>#admin-interviews" class="sidebar__link"><i class="fas fa-calendar-check"></i> Interviews</a></li>
        </ul>

        <div class="sidebar__section-label">Talent</div>
        <ul class="sidebar__menu">
          <li><a href="#admin-talent-hub" class="sidebar__link" data-section="talent-hub"><i class="fas fa-users"></i> Talent Intelligence Hub</a></li>
          <li><a href="#admin-talent-matching" class="sidebar__link" data-section="talent-matching"><i class="fas fa-handshake"></i> Talent Matching</a></li>
          <li><a href="#admin-talent-pool" class="sidebar__link" data-section="talent-pool"><i class="fas fa-database"></i> Talent Pools</a></li>
        </ul>

        <div class="sidebar__section-label">Operations</div>
        <ul class="sidebar__menu">
          <li><a href="#admin-attendance" class="sidebar__link" data-section="attendance"><i class="fas fa-clock"></i> Attendance & Timesheets</a></li>
          <li><a href="#admin-communication" class="sidebar__link" data-section="communication"><i class="fas fa-bullhorn"></i> Communication Centre</a></li>
          <li><a href="#admin-reporting" class="sidebar__link" data-section="reporting"><i class="fas fa-file-alt"></i> Reporting & Analytics</a></li>
          <li><a href="#admin-compliance" class="sidebar__link" data-section="compliance"><i class="fas fa-shield-alt"></i> Consent & Compliance</a></li>
          <li><a href="#admin-audit" class="sidebar__link" data-section="audit"><i class="fas fa-history"></i> Audit Log</a></li>
          <li><a href="#admin-users" class="sidebar__link" data-section="users"><i class="fas fa-user-shield"></i> User Management</a></li>
        </ul>

        <div class="sidebar__section-label">Quick Access</div>
        <ul class="sidebar__menu">
          <li><a href="#admin-quick-actions" class="sidebar__link" data-section="quick-actions"><i class="fas fa-bolt"></i> Quick Actions</a></li>
        </ul>
      </nav>

      <div class="sidebar__footer">
        <div class="sidebar__user">
          <div class="sidebar__user-avatar">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'Admin+User') ?>&background=1a56db&color=fff&size=80" alt="Profile">
          </div>
          <div class="sidebar__user-info">
            <span class="sidebar__user-name"><?= e($user['fullname'] ?? 'Admin User') ?></span>
            <span class="sidebar__user-role"><?= e($user['role_name'] ?? 'Platform Administrator') ?></span>
          </div>
        </div>
        <a href="<?= url('auth/logout.php') ?>" class="sidebar__logout" id="sidebarLogoutBtn" onclick="event.preventDefault(); var m=document.getElementById('logoutModal'); if(m){m.classList.add('active'); m.setAttribute('aria-hidden','false');}">
          <i class="fas fa-sign-out-alt"></i> Sign Out
        </a>
      </div>
    </aside>

    <!-- ===== SIDEBAR OVERLAY ===== -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="dashboard__main">

      <!-- ===== DASHBOARD HEADER ===== -->
      <header class="dash-header admin-dash-header" id="adminDashHeader">
        <div class="dash-header__left">
          <button class="dash-header__toggle" id="sidebarToggle" aria-label="Toggle sidebar">
            <i class="fas fa-bars"></i>
          </button>
          <div class="dash-header__search" id="adminDashSearch">
            <i class="fas fa-search"></i>
            <input type="text" class="dash-header__search-input" id="adminGlobalSearch" placeholder="Search candidates, programmes, reports..." aria-label="Search admin dashboard">
          </div>
        </div>
        <div class="dash-header__right">
          <button class="dash-header__icon-btn" id="themeToggle" aria-label="Toggle dark mode">
            <i class="fas fa-moon"></i>
          </button>
          <button class="dash-header__icon-btn dash-header__notif-btn" id="adminNotifBtn" aria-label="Notifications">
            <i class="fas fa-bell"></i>
            <span class="dash-header__notif-badge">8</span>
          </button>
          <button class="dash-header__icon-btn" id="adminAlertsBtn" aria-label="Alerts">
            <i class="fas fa-exclamation-circle"></i>
            <span class="dash-header__notif-badge" style="background:var(--accent);">6</span>
          </button>
          <div class="dash-header__user">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname'] ?? 'Admin+User') ?>&background=1a56db&color=fff&size=80" alt="Profile" class="dash-header__avatar">
          </div>
        </div>
      </header>

      <!-- ===== DASHBOARD CONTENT ===== -->
      <div class="dash-content" id="adminDashContent">

        <!-- =============================================
             SECTION 1: EXECUTIVE OVERVIEW
             ============================================= -->
        <section class="dash-section admin-section" id="admin-executive" data-section="executive">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <span class="section__badge">Command Centre</span>
            <h2 class="section__title" style="font-size:1.5rem;">Executive <span class="text-gradient">Overview</span></h2>
            <p class="section__text" style="font-size:0.9rem;">Real-time platform performance metrics and intelligence.</p>
          </div>

          <!-- Executive Stats Grid -->
          <div class="admin-executive-grid" id="adminExecutiveGrid">
            <div class="admin-exec-card">
              <div class="admin-exec-card__header">
                <div class="admin-exec-card__icon admin-exec-card__icon--primary"><i class="fas fa-users"></i></div>
                <span class="admin-exec-card__change up">+12.5%</span>
              </div>
<span class="admin-exec-card__number" data-count="<?= (int)$totalCandidates ?>">0</span>
              <span class="admin-exec-card__label">Total Candidates</span>
              <div class="admin-exec-card__footer">
                <span class="admin-exec-card__period"><i class="fas fa-arrow-up"></i> <?= (int)$newCandidatesMonth ?> new this month</span>
                <a href="#" class="admin-exec-card__link">View <i class="fas fa-arrow-right"></i></a>
              </div>
            </div>
            <div class="admin-exec-card">
              <div class="admin-exec-card__header">
                <div class="admin-exec-card__icon admin-exec-card__icon--cyan"><i class="fas fa-graduation-cap"></i></div>
                <span class="admin-exec-card__change up">+8.3%</span>
              </div>
<span class="admin-exec-card__number" data-count="<?= (int)$activeProgrammes ?>">0</span>
              <span class="admin-exec-card__label">Active Programmes</span>
              <div class="admin-exec-card__footer">
                <span class="admin-exec-card__period"><i class="fas fa-arrow-up"></i> <?= (int)$totalProgrammes ?> total programmes</span>
                <a href="#" class="admin-exec-card__link">View <i class="fas fa-arrow-right"></i></a>
              </div>
            </div>
            <div class="admin-exec-card">
              <div class="admin-exec-card__header">
                <div class="admin-exec-card__icon admin-exec-card__icon--amber"><i class="fas fa-layer-group"></i></div>
                <span class="admin-exec-card__change up">+15.0%</span>
              </div>
<span class="admin-exec-card__number" data-count="<?= (int)$activeCohorts ?>">0</span>
              <span class="admin-exec-card__label">Active Cohorts</span>
              <div class="admin-exec-card__footer">
                <span class="admin-exec-card__period"><i class="fas fa-users"></i> <?= (int)$totalParticipants ?> participants</span>
                <a href="#" class="admin-exec-card__link">View <i class="fas fa-arrow-right"></i></a>
              </div>
            </div>
            <div class="admin-exec-card">
              <div class="admin-exec-card__header">
                <div class="admin-exec-card__icon admin-exec-card__icon--green"><i class="fas fa-briefcase"></i></div>
                <span class="admin-exec-card__change up">+22.1%</span>
              </div>
<span class="admin-exec-card__number" data-count="<?= (int)$totalOpportunities ?>">0</span>
              <span class="admin-exec-card__label">Available Opportunities</span>
              <div class="admin-exec-card__footer">
                <span class="admin-exec-card__period"><i class="fas fa-arrow-up"></i> <?= (int)$newOppsWeek ?> new this week</span>
                <a href="<?= url('admin/opportunities.php') ?>" class="admin-exec-card__link">View <i class="fas fa-arrow-right"></i></a>
              </div>
            </div>
            <div class="admin-exec-card">
              <div class="admin-exec-card__header">
                <div class="admin-exec-card__icon admin-exec-card__icon--purple"><i class="fas fa-file-alt"></i></div>
                <span class="admin-exec-card__change up">+18.7%</span>
              </div>
<span class="admin-exec-card__number" data-count="<?= (int)$totalAppCount ?>">0</span>
              <span class="admin-exec-card__label">Total Applications</span>
              <div class="admin-exec-card__footer">
                <span class="admin-exec-card__period"><i class="fas fa-file-alt"></i> across all opportunities</span>
                <a href="#admin-applications" class="admin-exec-card__link">View <i class="fas fa-arrow-right"></i></a>
              </div>
            </div>
            <div class="admin-exec-card">
              <div class="admin-exec-card__header">
                <div class="admin-exec-card__icon admin-exec-card__icon--indigo"><i class="fas fa-handshake"></i></div>
                <span class="admin-exec-card__change up">+6.8%</span>
              </div>
              <span class="admin-exec-card__number" data-count="248">0</span>
              <span class="admin-exec-card__label">Active Placements</span>
              <div class="admin-exec-card__footer">
                <span class="admin-exec-card__period"><i class="fas fa-arrow-up"></i> 18 new this month</span>
                <a href="#" class="admin-exec-card__link">View <i class="fas fa-arrow-right"></i></a>
              </div>
            </div>
            <div class="admin-exec-card">
              <div class="admin-exec-card__header">
                <div class="admin-exec-card__icon admin-exec-card__icon--primary"><i class="fas fa-database"></i></div>
                <span class="admin-exec-card__change up">+11.2%</span>
              </div>
              <span class="admin-exec-card__number" data-count="1280">0</span>
              <span class="admin-exec-card__label">Talent Pool Members</span>
              <div class="admin-exec-card__footer">
                <span class="admin-exec-card__period"><i class="fas fa-arrow-up"></i> 89 new this month</span>
                <a href="#" class="admin-exec-card__link">View <i class="fas fa-arrow-right"></i></a>
              </div>
            </div>
            <div class="admin-exec-card">
              <div class="admin-exec-card__header">
                <div class="admin-exec-card__icon admin-exec-card__icon--green"><i class="fas fa-check-circle"></i></div>
                <span class="admin-exec-card__change up">+9.4%</span>
              </div>
              <span class="admin-exec-card__number" data-count="1056">0</span>
              <span class="admin-exec-card__label">Successful Placements</span>
              <div class="admin-exec-card__footer">
                <span class="admin-exec-card__period"><i class="fas fa-arrow-up"></i> 42 this quarter</span>
                <a href="#" class="admin-exec-card__link">View <i class="fas fa-arrow-right"></i></a>
              </div>
            </div>
            <div class="admin-exec-card">
              <div class="admin-exec-card__header">
                <div class="admin-exec-card__icon admin-exec-card__icon--cyan"><i class="fas fa-chart-line"></i></div>
                <span class="admin-exec-card__change up">+4.2%</span>
              </div>
              <span class="admin-exec-card__number">87<span class="admin-exec-card__suffix">%</span></span>
              <span class="admin-exec-card__label">Completion Rate</span>
              <div class="admin-exec-card__footer">
                <span class="admin-exec-card__period"><i class="fas fa-arrow-up"></i> +2.1% vs last month</span>
                <a href="#" class="admin-exec-card__link">View <i class="fas fa-arrow-right"></i></a>
              </div>
            </div>
            <div class="admin-exec-card">
              <div class="admin-exec-card__header">
                <div class="admin-exec-card__icon admin-exec-card__icon--amber"><i class="fas fa-calendar-check"></i></div>
                <span class="admin-exec-card__change up">+7.6%</span>
              </div>
              <span class="admin-exec-card__number" data-count="189">0</span>
              <span class="admin-exec-card__label">Interviews This Month</span>
              <div class="admin-exec-card__footer">
                <span class="admin-exec-card__period"><i class="fas fa-arrow-up"></i> 68% attendance</span>
                <a href="#" class="admin-exec-card__link">View <i class="fas fa-arrow-right"></i></a>
              </div>
            </div>
            <div class="admin-exec-card">
              <div class="admin-exec-card__header">
                <div class="admin-exec-card__icon admin-exec-card__icon--purple"><i class="fas fa-clipboard-check"></i></div>
                <span class="admin-exec-card__change up">+5.8%</span>
              </div>
              <span class="admin-exec-card__number">92<span class="admin-exec-card__suffix">%</span></span>
              <span class="admin-exec-card__label">Skills Verification Rate</span>
              <div class="admin-exec-card__footer">
                <span class="admin-exec-card__period"><i class="fas fa-check"></i> 2,450 skills verified</span>
                <a href="#" class="admin-exec-card__link">View <i class="fas fa-arrow-right"></i></a>
              </div>
            </div>
            <div class="admin-exec-card">
              <div class="admin-exec-card__header">
                <div class="admin-exec-card__icon admin-exec-card__icon--indigo"><i class="fas fa-user-check"></i></div>
                <span class="admin-exec-card__change up">+3.4%</span>
              </div>
<span class="admin-exec-card__number"><?= (int)$avgProfileCompleteness ?><span class="admin-exec-card__suffix">%</span></span>
              <span class="admin-exec-card__label">Profile Completeness</span>
              <div class="admin-exec-card__footer">
                <span class="admin-exec-card__period"><i class="fas fa-user"></i> <?= (int)$completeProfiles ?> complete profiles</span>
                <a href="#" class="admin-exec-card__link">View <i class="fas fa-arrow-right"></i></a>
              </div>
            </div>
          </div>

<!-- Mini Charts Row -->
          <div class="admin-mini-charts">
            <div class="admin-mini-chart-card">
              <div class="admin-mini-chart-card__header">
                <h4>Candidate Growth</h4>
                <span class="admin-mini-chart-card__period">Last 6 months</span>
              </div>
              <div class="admin-mini-chart-container">
                <canvas id="execCandidateChart"></canvas>
              </div>
            </div>
            <div class="admin-mini-chart-card">
              <div class="admin-mini-chart-card__header">
                <h4>Applications Trend</h4>
                <span class="admin-mini-chart-card__period">Weekly comparison</span>
              </div>
              <div class="admin-mini-chart-container">
                <canvas id="execApplicationChart"></canvas>
              </div>
            </div>
            <div class="admin-mini-chart-card">
              <div class="admin-mini-chart-card__header">
                <h4>Placement Success</h4>
                <span class="admin-mini-chart-card__period">Monthly rate</span>
              </div>
              <div class="admin-mini-chart-container">
                <canvas id="execPlacementChart"></canvas>
              </div>
            </div>
          </div>

          <!-- Active Cohorts Panel (real data) -->
          <div class="admin-exec-cohorts">
            <div class="admin-exec-cohorts__head">
              <div>
                <h3 class="admin-exec-cohorts__title"><i class="fas fa-layer-group"></i> Active Cohorts</h3>
                <p class="admin-exec-cohorts__sub">Live cohorts currently accepting participants across all programmes</p>
              </div>
              <a href="<?= url('admin/programmes.php') ?>" class="btn btn--ghost btn--sm"><i class="fas fa-arrow-right"></i> View All</a>
            </div>

            <?php if (empty($activeCohortData)): ?>
              <div class="pm-empty pm-empty--sm">
                <div class="pm-empty__icon"><i class="fas fa-layer-group"></i></div>
                <h3>No active cohorts right now.</h3>
                <p>Activate a cohort to see its live performance here.</p>
                <a href="<?= url('admin/programmes.php') ?>" class="btn btn--primary btn--sm"><i class="fas fa-plus"></i> Manage Programmes</a>
              </div>
            <?php else: ?>
              <div class="admin-exec-cohorts__grid">
                <?php foreach ($activeCohortData as $ac): ?>
                  <div class="pm-cohort-card admin-exec-cohort-item">
                    <div class="pm-cohort-card__top">
                      <div>
                        <h3 class="pm-cohort-card__name"><?= e($ac['name']) ?></h3>
                        <span class="pm-cohort-card__meta"><?= e($ac['programme_name']) ?></span>
                      </div>
                      <?= status_badge('active', 'Active') ?>
                    </div>
                    <div class="pm-cohort-card__meta" style="margin-top:0.35rem;">
                      <i class="fas fa-map-marker-alt"></i> <?= e($ac['location'] ?: ucwords(str_replace('-', ' ', $ac['province'] ?: 'TBC'))) ?>
                      · <i class="fas fa-broadcast-tower"></i> <?= e(delivery_mode_label($ac['delivery_mode'])) ?>
                    </div>
                    <div class="pm-cohort-card__dates">
                      <span><i class="fas fa-calendar-alt"></i> <?= e(!empty($ac['start_date']) ? format_date($ac['start_date'], 'd M Y') : 'TBC') ?> — <?= e(!empty($ac['end_date']) ? format_date($ac['end_date'], 'd M Y') : 'TBC') ?></span>
                    </div>
                    <div class="pm-cohort-card__capacity">
                      <div class="pm-cohort-card__capacity-head">
                        <span><?= (int) $ac['committed'] ?> / <?= (int) $ac['max_capacity'] ?> participants</span>
                        <span><?= (int) $ac['pct'] ?>%</span>
                      </div>
                      <div class="pm-progress"><div class="pm-progress__bar" style="width:<?= (int) $ac['pct'] ?>%"></div></div>
                    </div>
                    <div class="admin-exec-cohort-item__footer">
                      <span class="admin-exec-cohort-item__stat"><i class="fas fa-file-alt"></i> <?= (int) $ac['applications'] ?> applications</span>
                      <span class="admin-exec-cohort-item__stat"><i class="fas fa-user-plus"></i> <?= (int) $ac['available'] ?> available</span>
                      <a href="<?= url('admin/cohort_detail.php?id=' . (int) $ac['id']) ?>" class="btn btn--ghost btn--sm">Manage <i class="fas fa-arrow-right"></i></a>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </section>

        <!-- =============================================
             SECTION 2: DASHBOARD ANALYTICS
             ============================================= -->
        <section class="dash-section admin-section" id="admin-analytics" data-section="analytics">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <span class="section__badge">Data Intelligence</span>
            <h2 class="section__title" style="font-size:1.5rem;">Dashboard <span class="text-gradient">Analytics</span></h2>
            <p class="section__text" style="font-size:0.9rem;">Comprehensive analytics across all platform dimensions.</p>
          </div>

          <!-- Analytics Filter Bar -->
          <div class="admin-analytics-filters">
            <div class="admin-analytics-filters__left">
              <select class="admin-filter-select" id="analyticsPeriod">
                <option value="7d">Last 7 Days</option>
                <option value="30d" selected>Last 30 Days</option>
                <option value="90d">Last Quarter</option>
                <option value="12m">Last 12 Months</option>
                <option value="custom">Custom Range</option>
              </select>
              <select class="admin-filter-select" id="analyticsCategory">
                <option value="all">All Categories</option>
                <option value="programmes">Programmes</option>
                <option value="applications">Applications</option>
                <option value="placements">Placements</option>
                <option value="talent">Talent Pool</option>
                <option value="skills">Skills</option>
              </select>
            </div>
            <div class="admin-analytics-filters__right">
              <button class="btn btn--ghost btn--sm"><i class="fas fa-download"></i> Export</button>
              <button class="btn btn--ghost btn--sm"><i class="fas fa-sync-alt"></i> Refresh</button>
            </div>
          </div>

          <!-- Analytics Charts Grid -->
          <div class="admin-analytics-grid">
            <div class="admin-analytics-card admin-analytics-card--full">
              <div class="admin-analytics-card__header">
                <h3>Programme Performance</h3>
                <span class="admin-analytics-card__badge">Active</span>
              </div>
              <div class="admin-analytics-chart-container">
                <canvas id="analyticsProgrammeChart"></canvas>
              </div>
            </div>
            <div class="admin-analytics-card">
              <div class="admin-analytics-card__header">
                <h3>Application Statistics</h3>
              </div>
              <div class="admin-analytics-chart-container">
                <canvas id="analyticsApplicationChart"></canvas>
              </div>
            </div>
            <div class="admin-analytics-card">
              <div class="admin-analytics-card__header">
                <h3>Placement Statistics</h3>
              </div>
              <div class="admin-analytics-chart-container">
                <canvas id="analyticsPlacementChart"></canvas>
              </div>
            </div>
            <div class="admin-analytics-card">
              <div class="admin-analytics-card__header">
                <h3>Talent Pool Analytics</h3>
              </div>
              <div class="admin-analytics-chart-container">
                <canvas id="analyticsTalentChart"></canvas>
              </div>
            </div>
            <div class="admin-analytics-card">
              <div class="admin-analytics-card__header">
                <h3>Skills Supply & Demand</h3>
              </div>
              <div class="admin-analytics-chart-container">
                <canvas id="analyticsSkillsChart"></canvas>
              </div>
            </div>
            <div class="admin-analytics-card">
              <div class="admin-analytics-card__header">
                <h3>Attendance Statistics</h3>
              </div>
              <div class="admin-analytics-chart-container">
                <canvas id="analyticsAttendanceChart"></canvas>
              </div>
            </div>
            <div class="admin-analytics-card">
              <div class="admin-analytics-card__header">
                <h3>Candidate Growth</h3>
              </div>
              <div class="admin-analytics-chart-container">
                <canvas id="analyticsGrowthChart"></canvas>
              </div>
            </div>
            <div class="admin-analytics-card">
              <div class="admin-analytics-card__header">
                <h3>Learning & Assessment</h3>
              </div>
              <div class="admin-analytics-chart-container">
                <canvas id="analyticsLearningChart"></canvas>
              </div>
            </div>
            <div class="admin-analytics-card">
              <div class="admin-analytics-card__header">
                <h3>Employment Outcomes</h3>
              </div>
              <div class="admin-analytics-chart-container">
                <canvas id="analyticsOutcomeChart"></canvas>
              </div>
            </div>
            <div class="admin-analytics-card admin-analytics-card--full">
              <div class="admin-analytics-card__header">
                <h3>Recruitment Funnel</h3>
                <span class="admin-analytics-card__badge">Real-time</span>
              </div>
              <div class="admin-analytics-chart-container" style="height:100px;">
                <canvas id="analyticsFunnelChart"></canvas>
              </div>
            </div>
          </div>

          <!-- Analytics Stats Summary -->
          <div class="admin-analytics-stats">
<div class="admin-analytics-stat">
              <span class="admin-analytics-stat__number" data-count="<?= (int)$activeProgrammes ?>">0</span>
              <span class="admin-analytics-stat__label">Active Programmes</span>
              <span class="admin-analytics-stat__trend up"><i class="fas fa-check"></i> <?= (int)$totalProgrammes ?> total</span>
            </div>
            <div class="admin-analytics-stat">
              <span class="admin-analytics-stat__number" data-count="<?= (int)$totalApplications ?>">0</span>
              <span class="admin-analytics-stat__label">Total Applications</span>
              <span class="admin-analytics-stat__trend up"><i class="fas fa-file-alt"></i> across cohorts</span>
            </div>
            <div class="admin-analytics-stat">
              <span class="admin-analytics-stat__number" data-count="248">0</span>
              <span class="admin-analytics-stat__label">Active Placements</span>
              <span class="admin-analytics-stat__trend up"><i class="fas fa-arrow-up"></i> +6.8%</span>
            </div>
            <div class="admin-analytics-stat">
              <span class="admin-analytics-stat__number" data-count="1280">0</span>
              <span class="admin-analytics-stat__label">Talent Pool</span>
              <span class="admin-analytics-stat__trend up"><i class="fas fa-arrow-up"></i> +11.2%</span>
            </div>
            <div class="admin-analytics-stat">
              <span class="admin-analytics-stat__number">87<span class="admin-analytics-stat__suffix">%</span></span>
              <span class="admin-analytics-stat__label">Completion Rate</span>
              <span class="admin-analytics-stat__trend up"><i class="fas fa-arrow-up"></i> +4.2%</span>
            </div>
            <div class="admin-analytics-stat">
              <span class="admin-analytics-stat__number">78<span class="admin-analytics-stat__suffix">%</span></span>
              <span class="admin-analytics-stat__label">Profile Completeness</span>
              <span class="admin-analytics-stat__trend up"><i class="fas fa-arrow-up"></i> +3.4%</span>
            </div>
          </div>
        </section>

        <!-- =============================================
             SECTION 3: ADMINISTRATIVE ALERTS
             ============================================= -->
        <section class="dash-section admin-section" id="admin-alerts" data-section="alerts">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <span class="section__badge">Intelligent Monitoring</span>
            <h2 class="section__title" style="font-size:1.5rem;">Administrative <span class="text-gradient">Alerts</span></h2>
            <p class="section__text" style="font-size:0.9rem;">Smart alerts and notifications requiring your attention.</p>
          </div>

          <div class="admin-alerts-grid">
            <div class="admin-alert-card admin-alert-card--urgent">
              <div class="admin-alert-card__icon"><i class="fas fa-file-excel"></i></div>
              <div class="admin-alert-card__content">
                <span class="admin-alert-card__title">Expiring Documents</span>
                <span class="admin-alert-card__message">12 candidates have expiring compliance documents within 7 days.</span>
              </div>
              <span class="admin-alert-card__time">2h ago</span>
              <button class="admin-alert-card__action" aria-label="Dismiss"><i class="fas fa-times"></i></button>
            </div>
            <div class="admin-alert-card admin-alert-card--warning">
              <div class="admin-alert-card__icon"><i class="fas fa-calendar-times"></i></div>
              <div class="admin-alert-card__content">
                <span class="admin-alert-card__title">Closing Opportunities</span>
                <span class="admin-alert-card__message">5 opportunities are closing within the next 48 hours.</span>
              </div>
              <span class="admin-alert-card__time">5h ago</span>
              <button class="admin-alert-card__action" aria-label="Dismiss"><i class="fas fa-times"></i></button>
            </div>
            <div class="admin-alert-card admin-alert-card--danger">
              <div class="admin-alert-card__icon"><i class="fas fa-clock"></i></div>
              <div class="admin-alert-card__content">
                <span class="admin-alert-card__title">Missing Attendance Records</span>
                <span class="admin-alert-card__message">24 candidates missing attendance logs for the current period.</span>
              </div>
              <span class="admin-alert-card__time">1d ago</span>
              <button class="admin-alert-card__action" aria-label="Dismiss"><i class="fas fa-times"></i></button>
            </div>
            <div class="admin-alert-card">
              <div class="admin-alert-card__icon"><i class="fas fa-user-edit"></i></div>
              <div class="admin-alert-card__content">
                <span class="admin-alert-card__title">Incomplete Profiles</span>
                <span class="admin-alert-card__message">340 candidates have profiles below 50% completeness.</span>
              </div>
              <span class="admin-alert-card__time">1d ago</span>
              <button class="admin-alert-card__action" aria-label="Dismiss"><i class="fas fa-times"></i></button>
            </div>
            <div class="admin-alert-card admin-alert-card--warning">
              <div class="admin-alert-card__icon"><i class="fas fa-shield-alt"></i></div>
              <div class="admin-alert-card__content">
                <span class="admin-alert-card__title">Consent Issues</span>
                <span class="admin-alert-card__message">18 candidates require consent renewal for data processing.</span>
              </div>
              <span class="admin-alert-card__time">2d ago</span>
              <button class="admin-alert-card__action" aria-label="Dismiss"><i class="fas fa-times"></i></button>
            </div>
            <div class="admin-alert-card admin-alert-card--danger">
              <div class="admin-alert-card__icon"><i class="fas fa-exclamation-triangle"></i></div>
              <div class="admin-alert-card__content">
                <span class="admin-alert-card__title">Placement Conflicts</span>
                <span class="admin-alert-card__message">3 candidates have overlapping placement schedules.</span>
              </div>
              <span class="admin-alert-card__time">3d ago</span>
              <button class="admin-alert-card__action" aria-label="Dismiss"><i class="fas fa-times"></i></button>
            </div>
          </div>
        </section>

<!-- =============================================
             SECTION 4: PROGRAMME MANAGEMENT (dynamic)
             ============================================= -->
        <section class="dash-section admin-section" id="admin-programmes" data-section="programmes">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <span class="section__badge">Programme Management</span>
            <h2 class="section__title" style="font-size:1.5rem;">Programme <span class="text-gradient">Management</span></h2>
            <p class="section__text" style="font-size:0.9rem;">Create, manage, and monitor programme performance across cohorts.</p>
          </div>

          <!-- Programme Toolbar -->
          <div class="admin-toolbar">
            <div class="admin-toolbar__left">
              <a href="<?= url('admin/programme_create.php') ?>" class="btn btn--primary btn--sm"><i class="fas fa-plus"></i> Create Programme</a>
              <a href="<?= url('admin/programmes.php') ?>" class="btn btn--outline btn--sm"><i class="fas fa-th-list"></i> Manage Programmes</a>
            </div>
            <div class="admin-toolbar__right">
              <div class="admin-search-bar">
                <i class="fas fa-search"></i>
                <input type="text" class="admin-search-input" id="progSearchInput" placeholder="Search programmes..." oninput="filterDashProgrammes(this.value)">
              </div>
            </div>
          </div>

          <!-- Programme Stats (dynamic) -->
          <div class="admin-stats-row">
            <div class="admin-stat-chip"><span class="admin-stat-chip__value"><?= (int)$totalProgrammes ?></span> Total</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value"><?= (int)$activeProgrammes ?></span> Active</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value"><?= (int)$draftProgrammes ?></span> Draft</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value"><?= (int)$completedProgrammes ?></span> Completed</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value"><?= (int)$archivedProgrammes ?></span> Archived</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value"><?= (int)$activeCohorts ?></span> Active Cohorts</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value"><?= (int)$totalCapacity ?></span> Capacity</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value"><?= (int)$totalParticipants ?></span> Participants</div>
          </div>

          <!-- Recent Programmes -->
          <div class="admin-programme-grid" id="adminProgGrid">
            <?php if (empty($recentProgrammes)): ?>
              <div class="admin-empty-state">
                <div class="admin-empty-state__icon"><i class="fas fa-graduation-cap"></i></div>
                <h3>No programmes have been created yet.</h3>
                <p>Create your first programme to begin configuring cohorts and opportunities.</p>
                <a href="<?= url('admin/programme_create.php') ?>" class="btn btn--primary btn--sm"><i class="fas fa-plus"></i> Create Programme</a>
              </div>
            <?php else: ?>
              <?php foreach ($recentProgrammes as $prog): ?>
                <div class="admin-programme-card" data-search="<?= e(strtolower($prog['name'] . ' ' . $prog['type'] . ' ' . ($prog['description'] ?? ''))) ?>">
                  <div class="admin-programme-card__head">
                    <div class="admin-programme-card__icon"><i class="fas fa-graduation-cap"></i></div>
                    <span class="tag tag--<?= e($prog['status']) ?>"><?= e(ucfirst($prog['status'])) ?></span>
                  </div>
                  <h3 class="admin-programme-card__title"><?= e($prog['name']) ?></h3>
                  <p class="admin-programme-card__sub"><?= e(programme_type_label($prog['type'])) ?></p>
                  <p class="admin-programme-card__desc"><?= e(mb_strimwidth($prog['description'] ?? 'No description', 0, 90, '…')) ?></p>
                  <div class="admin-programme-card__meta">
                    <span><i class="fas fa-layer-group"></i> <?= (int)$prog['cohort_count'] ?> cohorts</span>
                    <span><i class="fas fa-users"></i> <?= (int)Programme::participantCount((int)$prog['id']) ?> participants</span>
                  </div>
                  <div class="admin-programme-card__actions">
                    <a href="<?= url('admin/programme_detail.php?id=' . (int)$prog['id']) ?>" class="btn btn--outline btn--sm">View</a>
                    <a href="<?= url('admin/programme_edit.php?id=' . (int)$prog['id']) ?>" class="btn btn--ghost btn--sm">Edit</a>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </section>

        <!-- =============================================
             SECTION 5: OPPORTUNITY MANAGEMENT
             ============================================= -->
        <section class="dash-section admin-section" id="admin-opportunities" data-section="opportunities">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <span class="section__badge">Opportunity Management</span>
            <h2 class="section__title" style="font-size:1.5rem;">Opportunity <span class="text-gradient">Management</span></h2>
            <p class="section__text" style="font-size:0.9rem;">Create, publish, and manage opportunities across programmes.</p>
          </div>

<div class="admin-toolbar">
            <div class="admin-toolbar__left">
              <a href="<?= url('admin/opportunity_create.php') ?>" class="btn btn--primary btn--sm"><i class="fas fa-plus"></i> Create Opportunity</a>
              <a href="<?= url('admin/opportunities.php') ?>" class="btn btn--outline btn--sm"><i class="fas fa-th-list"></i> Manage Opportunities</a>
            </div>
            <div class="admin-toolbar__right">
              <div class="admin-search-bar">
                <i class="fas fa-search"></i>
                <input type="text" class="admin-search-input" id="adminOppSearch" placeholder="Search opportunities..." oninput="filterDashOpportunities(this.value)">
              </div>
            </div>
          </div>

          <!-- Opportunity Stats (matching programme management style) -->
          <div class="admin-stats-row">
            <div class="admin-stat-chip"><span class="admin-stat-chip__value"><?= number_format($totalOpportunities) ?></span> Total Opportunities</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value"><?= number_format($totalPositions) ?></span> Available Positions</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value"><?= number_format($totalOppApplications) ?></span> Total Applications</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value"><?= number_format($avgApplicationsPerOpp, 1) ?></span> Avg. Applications / Opportunity</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value"><?= (int)$publishedOpps ?></span> Published</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value"><?= (int)$draftOpps ?></span> Draft</div>
            <div class="admin-stat-chip admin-stat-chip--danger"><span class="admin-stat-chip__value"><?= (int)$closingSoonOpps ?></span> Closing Soon</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value"><?= (int)$closedOpps ?></span> Closed</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value"><?= (int)$archivedOpps ?></span> Archived</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value"><?= (int)$newOppsWeek ?></span> New This Week</div>
          </div>

          <?php if (empty($allOpportunities)): ?>
            <!-- Empty State -->
            <div class="opp-section-container">
              <div class="admin-empty-state">
                <div class="admin-empty-state__icon"><i class="fas fa-briefcase"></i></div>
                <h3>No opportunities have been created yet.</h3>
                <p>Create your first opportunity and connect it to an existing programme and cohort.</p>
                <a href="<?= url('admin/opportunity_create.php') ?>" class="btn btn--primary btn--sm"><i class="fas fa-plus"></i> Create Opportunity</a>
              </div>
            </div>
          <?php else: ?>

            <!-- Published Opportunities Container -->
            <?php if (!empty($recentPublishedOpportunities)): ?>
            <div class="opp-section-container opp-section-container--published">
              <div class="opp-section-header">
                <div class="opp-section-header__left">
                  <div class="opp-section-header__icon"><i class="fas fa-globe"></i></div>
                  <div>
                    <h3 class="opp-section-header__title">Published Opportunities</h3>
                    <p class="opp-section-header__sub">Live opportunities accepting applications</p>
                  </div>
                </div>
                <span class="opp-section-header__badge"><?= count($publishedOpportunities) ?> published</span>
              </div>
              <div class="admin-opp-grid" id="adminOppGrid">
                <?php foreach ($recentPublishedOpportunities as $op): ?>
                  <div class="admin-opp-card" data-search="<?= e(strtolower($op['title'] . ' ' . ($op['programme_name'] ?? '') . ' ' . ($op['cohort_name'] ?? ''))) ?>">
                    <div class="admin-opp-card__head">
                      <div class="admin-opp-card__icon"><i class="fas fa-briefcase"></i></div>
                      <span class="tag tag--published">Published</span>
                    </div>
                    <h3 class="admin-opp-card__title"><?= e($op['title']) ?></h3>
                    <p class="admin-opp-card__sub"><?= e(OPPORTUNITY_TYPE_LABELS[$op['type']] ?? ucfirst($op['type'])) ?></p>
                    <p class="admin-opp-card__desc"><?= e(mb_strimwidth($op['short_description'] ?? 'No description', 0, 90, '…')) ?></p>
                    <div class="admin-opp-card__meta">
                      <span><i class="fas fa-graduation-cap"></i> <?= e($op['programme_name'] ?? '—') ?></span>
                      <span><i class="fas fa-layer-group"></i> <?= e($op['cohort_name'] ?? 'No cohort') ?></span>
                    </div>
                    <div class="admin-opp-card__stats">
                      <span><i class="fas fa-users"></i> <?= (int)$op['available_positions'] ?> positions</span>
                      <span><i class="fas fa-file-alt"></i> <?= (int)($op['applications_count'] ?? 0) ?> applications</span>
                    </div>
                    <div class="admin-opp-card__actions">
                      <a href="<?= url('admin/opportunity_detail.php?id=' . (int)$op['id']) ?>" class="btn btn--outline btn--sm"><i class="fas fa-eye"></i> View</a>
                      <a href="<?= url('admin/opportunity_edit.php?id=' . (int)$op['id']) ?>" class="btn btn--ghost btn--sm"><i class="fas fa-edit"></i> Edit</a>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endif; ?>

            <!-- Other Opportunities Container (Draft, Closing Soon, Closed) -->
            <?php if (!empty($recentOtherOpportunities)): ?>
            <div class="opp-section-container opp-section-container--others">
              <div class="opp-section-header">
                <div class="opp-section-header__left">
                  <div class="opp-section-header__icon"><i class="fas fa-clipboard-list"></i></div>
                  <div>
                    <h3 class="opp-section-header__title">Other Opportunities</h3>
                    <p class="opp-section-header__sub">Drafts, closing soon, and closed opportunities</p>
                  </div>
                </div>
                <span class="opp-section-header__badge"><?= count($otherOpportunities) ?> items</span>
              </div>
              <div class="admin-opp-grid" id="adminOppGridOther">
                <?php foreach ($recentOtherOpportunities as $op): ?>
                  <?php
                    $statusLabel = OPPORTUNITY_STATUS_LABELS[$op['status']] ?? ucfirst($op['status']);
                  ?>
                  <div class="admin-opp-card" data-search="<?= e(strtolower($op['title'] . ' ' . ($op['programme_name'] ?? '') . ' ' . ($op['cohort_name'] ?? ''))) ?>">
                    <div class="admin-opp-card__head">
                      <div class="admin-opp-card__icon"><i class="fas fa-briefcase"></i></div>
                      <span class="tag tag--<?= e($op['status']) ?>"><?= e($statusLabel) ?></span>
                    </div>
                    <h3 class="admin-opp-card__title"><?= e($op['title']) ?></h3>
                    <p class="admin-opp-card__sub"><?= e(OPPORTUNITY_TYPE_LABELS[$op['type']] ?? ucfirst($op['type'])) ?></p>
                    <p class="admin-opp-card__desc"><?= e(mb_strimwidth($op['short_description'] ?? 'No description', 0, 90, '…')) ?></p>
                    <div class="admin-opp-card__meta">
                      <span><i class="fas fa-graduation-cap"></i> <?= e($op['programme_name'] ?? '—') ?></span>
                      <span><i class="fas fa-layer-group"></i> <?= e($op['cohort_name'] ?? 'No cohort') ?></span>
                    </div>
                    <div class="admin-opp-card__stats">
                      <span><i class="fas fa-users"></i> <?= (int)$op['available_positions'] ?> positions</span>
                      <span><i class="fas fa-file-alt"></i> <?= (int)($op['applications_count'] ?? 0) ?> applications</span>
                    </div>
                    <div class="admin-opp-card__actions">
                      <a href="<?= url('admin/opportunity_detail.php?id=' . (int)$op['id']) ?>" class="btn btn--outline btn--sm"><i class="fas fa-eye"></i> View</a>
                      <a href="<?= url('admin/opportunity_edit.php?id=' . (int)$op['id']) ?>" class="btn btn--ghost btn--sm"><i class="fas fa-edit"></i> Edit</a>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endif; ?>

          <?php endif; ?>
        </section>

        <!-- =============================================
             SECTION 6: APPLICATION MANAGEMENT
             ============================================= -->
        <section class="dash-section admin-section" id="admin-applications" data-section="applications">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <span class="section__badge">Application Management</span>
            <h2 class="section__title" style="font-size:1.5rem;">Application <span class="text-gradient">Management</span></h2>
            <p class="section__text" style="font-size:0.9rem;">Review, shortlist, and manage candidate applications.</p>
          </div>

          <!-- Application Status Pipeline (dynamic) -->
          <div class="admin-pipeline" id="adminPipeline">
            <div class="admin-pipeline__stage">
              <div class="admin-pipeline__header">
                <span class="admin-pipeline__count"><?= $appSubmitted ?></span>
                <span class="admin-pipeline__label">Submitted</span>
              </div>
              <div class="admin-pipeline__cards" data-stage="submitted">
                <!-- Populated by JS -->
              </div>
            </div>
            <div class="admin-pipeline__stage">
              <div class="admin-pipeline__header">
                <span class="admin-pipeline__count"><?= $appReview ?></span>
                <span class="admin-pipeline__label">Under Review</span>
              </div>
              <div class="admin-pipeline__cards" data-stage="review"></div>
            </div>
            <div class="admin-pipeline__stage">
              <div class="admin-pipeline__header">
                <span class="admin-pipeline__count"><?= $appAssessment ?></span>
                <span class="admin-pipeline__label">Assessment</span>
              </div>
              <div class="admin-pipeline__cards" data-stage="assessment"></div>
            </div>
            <div class="admin-pipeline__stage">
              <div class="admin-pipeline__header">
                <span class="admin-pipeline__count"><?= $appInterview ?></span>
                <span class="admin-pipeline__label">Interview</span>
              </div>
              <div class="admin-pipeline__cards" data-stage="interview"></div>
            </div>
            <div class="admin-pipeline__stage">
              <div class="admin-pipeline__header">
                <span class="admin-pipeline__count"><?= $appWaitlisted ?></span>
                <span class="admin-pipeline__label">Waitlisted</span>
              </div>
              <div class="admin-pipeline__cards" data-stage="waitlisted"></div>
            </div>
            <div class="admin-pipeline__stage">
              <div class="admin-pipeline__header">
                <span class="admin-pipeline__count admin-pipeline__count--success"><?= $appSelected ?></span>
                <span class="admin-pipeline__label">Selected</span>
              </div>
              <div class="admin-pipeline__cards" data-stage="selected"></div>
            </div>
            <div class="admin-pipeline__stage">
              <div class="admin-pipeline__header">
                <span class="admin-pipeline__count admin-pipeline__count--danger"><?= $appRejected ?></span>
                <span class="admin-pipeline__label">Rejected</span>
              </div>
              <div class="admin-pipeline__cards" data-stage="rejected"></div>
            </div>
            <div class="admin-pipeline__stage">
              <div class="admin-pipeline__header">
                <span class="admin-pipeline__count admin-pipeline__count--muted"><?= $appWithdrawn ?></span>
                <span class="admin-pipeline__label">Withdrawn</span>
              </div>
              <div class="admin-pipeline__cards" data-stage="withdrawn"></div>
            </div>
          </div>

          <!-- Application Filters -->
          <div class="admin-toolbar" style="margin-top:1.5rem;">
            <div class="admin-toolbar__left">
              <button class="btn btn--outline btn--sm"><i class="fas fa-filter"></i> Advanced Filters</button>
              <button class="btn btn--outline btn--sm"><i class="fas fa-check-double"></i> Bulk Actions</button>
              <button class="btn btn--outline btn--sm"><i class="fas fa-file-export"></i> Export</button>
            </div>
            <div class="admin-toolbar__right">
              <a href="<?= url('admin/applications.php') ?>" class="btn btn--primary btn--sm"><i class="fas fa-external-link-alt"></i> View All Applications</a>
              <div class="admin-search-bar">
                <i class="fas fa-search"></i>
                <input type="text" class="admin-search-input" id="appSearchInput" placeholder="Search applications...">
              </div>
            </div>
          </div>

          <!-- Application Stats Summary -->
          <div class="app-stats">
            <div class="app-stat app-stat--total">
              <div class="app-stat__icon"><i class="fas fa-file-alt"></i></div>
              <div>
                <span class="app-stat__value"><?= number_format($totalAppCount) ?></span>
                <span class="app-stat__label">Total Applications</span>
              </div>
            </div>
            <div class="app-stat app-stat--submitted">
              <div class="app-stat__icon"><i class="fas fa-paper-plane"></i></div>
              <div>
                <span class="app-stat__value"><?= number_format($appSubmitted) ?></span>
                <span class="app-stat__label">Submitted</span>
              </div>
            </div>
            <div class="app-stat app-stat--review">
              <div class="app-stat__icon"><i class="fas fa-search"></i></div>
              <div>
                <span class="app-stat__value"><?= number_format($appReview) ?></span>
                <span class="app-stat__label">Under Review</span>
              </div>
            </div>
            <div class="app-stat app-stat--interview">
              <div class="app-stat__icon"><i class="fas fa-user-tie"></i></div>
              <div>
                <span class="app-stat__value"><?= number_format($appInterview) ?></span>
                <span class="app-stat__label">Interview</span>
              </div>
            </div>
            <div class="app-stat app-stat--selected">
              <div class="app-stat__icon"><i class="fas fa-check-circle"></i></div>
              <div>
                <span class="app-stat__value"><?= number_format($appSelected) ?></span>
                <span class="app-stat__label">Selected</span>
              </div>
            </div>
            <div class="app-stat app-stat--rejected">
              <div class="app-stat__icon"><i class="fas fa-times-circle"></i></div>
              <div>
                <span class="app-stat__value"><?= number_format($appRejected) ?></span>
                <span class="app-stat__label">Rejected</span>
              </div>
            </div>
          </div>

          <!-- Application Table (dynamic) -->
          <div class="app-table-container" id="adminAppTable">
            <?php if (empty($recentApplications)): ?>
              <div style="padding:2rem;text-align:center;color:var(--text-light);">No applications found.</div>
            <?php else: ?>
            <table class="app-table">
              <thead>
                <tr>
                  <th>Reference</th>
                  <th>Candidate</th>
                  <th>Opportunity</th>
                  <th>Status</th>
                  <th>Submitted</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recentApplications as $app): ?>
                  <?php
                    $status = $app['status'] ?? 'draft';
                    $badgeTone = Application::badgeTone($status);
                    $statusLabel = Application::label($status);
                    $candidateName = $app['candidate_name'] ?? $app['candidate_email'] ?? '—';
                  ?>
                  <tr>
                    <td><a href="<?= url('admin/application.php?id=' . (int) $app['id']) ?>" class="app-ref-link"><?= e($app['application_reference'] ?? '—') ?></a></td>
                    <td>
                      <div class="app-candidate">
                        <div class="app-candidate__name"><?= e($candidateName) ?></div>
                        <div class="app-candidate__email"><?= e($app['candidate_email'] ?? '') ?></div>
                      </div>
                    </td>
                    <td>
                      <div class="app-opportunity">
                        <div class="app-opportunity__title"><?= e($app['opportunity_title'] ?? '—') ?></div>
                        <div class="app-opportunity__programme"><?= e($app['programme_name'] ?? '') ?></div>
                      </div>
                    </td>
                    <td><span class="app-status-badge app-status-badge--<?= e($badgeTone) ?>"><?= e($statusLabel) ?></span></td>
                    <td class="app-date"><?= !empty($app['submitted_at']) ? e(format_date($app['submitted_at'], 'd M Y')) : '<span class="app-status-badge app-status-badge--muted">Draft</span>' ?></td>
                    <td class="app-actions">
                      <a href="<?= url('admin/application.php?id=' . (int) $app['id']) ?>" class="btn btn--ghost btn--sm" title="View Details"><i class="fas fa-eye"></i></a>
                      <a href="<?= url('admin/application.php?id=' . (int) $app['id']) ?>&action=manage" class="btn btn--primary btn--sm" title="Manage"><i class="fas fa-cog"></i></a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <?php endif; ?>
          </div>
        </section>

        <!-- =============================================
             SECTION 7: PLACEMENT MANAGEMENT
             ============================================= -->
        <section class="dash-section admin-section" id="admin-placements" data-section="placements">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <span class="section__badge">Placement Management</span>
            <h2 class="section__title" style="font-size:1.5rem;">Placement <span class="text-gradient">Management</span></h2>
            <p class="section__text" style="font-size:0.9rem;">Manage candidate placements, host organisations, and workplace activities.</p>
          </div>

          <div class="admin-toolbar">
            <div class="admin-toolbar__left">
              <button class="btn btn--primary btn--sm"><i class="fas fa-plus"></i> New Placement</button>
              <select class="admin-filter-select" id="placementFilterStatus">
                <option value="all">All Statuses</option>
                <option value="active">Active</option>
                <option value="completed">Completed</option>
                <option value="upcoming">Upcoming</option>
                <option value="risk">At Risk</option>
              </select>
            </div>
            <div class="admin-toolbar__right">
              <div class="admin-search-bar">
                <i class="fas fa-search"></i>
                <input type="text" class="admin-search-input" id="placementSearch" placeholder="Search placements...">
              </div>
            </div>
          </div>

          <!-- Placement Stats -->
          <div class="admin-stats-row">
            <div class="admin-stat-chip"><span class="admin-stat-chip__value">248</span> Active Placements</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value">1,056</span> Completed</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value">64</span> Host Orgs</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value">92%</span> Retention Rate</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value">4.6/5</span> Avg Feedback</div>
          </div>

          <div class="admin-placement-grid" id="adminPlacementGrid">
            <!-- Populated by JS -->
          </div>
        </section>

        <!-- =============================================
             SECTION 8: INTERVIEW MANAGEMENT
             ============================================= -->
        <section class="dash-section admin-section" id="admin-interviews" data-section="interviews">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <span class="section__badge">Interview Management</span>
            <h2 class="section__title" style="font-size:1.5rem;">Interview <span class="text-gradient">Management</span></h2>
            <p class="section__text" style="font-size:0.9rem;">Schedule, manage, and track interviews and assessments.</p>
          </div>

          <div class="admin-toolbar">
            <div class="admin-toolbar__left">
              <a href="<?= url('admin/interview_schedule.php') ?>" class="btn btn--primary btn--sm"><i class="fas fa-plus"></i> Schedule Interview</a>
              <button class="btn btn--outline btn--sm"><i class="fas fa-calendar-alt"></i> Calendar View</button>
              <select class="admin-filter-select" id="interviewFilterDate">
                <option value="today">Today</option>
                <option value="week" selected>This Week</option>
                <option value="month">This Month</option>
                <option value="all">All</option>
              </select>
            </div>
            <div class="admin-toolbar__right" style="display:flex;align-items:center;gap:0.75rem;">
              <div class="admin-search-bar">
                <i class="fas fa-search"></i>
                <input type="text" class="admin-search-input" id="interviewSearch" placeholder="Search interviews...">
              </div>
              <a href="<?= url('admin/interviews.php') ?>" class="btn btn--primary btn--sm"><i class="fas fa-calendar-check"></i> Manage Interviews</a>
            </div>
          </div>

          <div class="admin-interview-grid" id="adminInterviewGrid">
            <!-- Populated by JS -->
          </div>
        </section>

        <!-- =============================================
             SECTION 9: TALENT INTELLIGENCE HUB
             ============================================= -->
        <section class="dash-section admin-section" id="admin-talent-hub" data-section="talent-hub">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <span class="section__badge">Talent Intelligence</span>
            <h2 class="section__title" style="font-size:1.5rem;">Talent Intelligence <span class="text-gradient">Hub</span></h2>
            <p class="section__text" style="font-size:0.9rem;">Discover, analyse, and engage with platform talent.</p>
          </div>

<?php
// Talent Hub Stats (dynamic from database)
$talentTotalPool = $totalCandidates;
$talentActiveProfiles = $talentActiveCandidates;
$talentAvailableNow = 0;
if (!empty($talentAvailability)) {
    foreach ($talentAvailability as $av) {
        if (stripos($av['availability_label'] ?? '', 'immediate') !== false || stripos($av['availability_label'] ?? '', 'available') !== false) {
            $talentAvailableNow += (int) $av['candidate_count'];
        }
    }
}
try {
$talentSkillsCategoriesRow = Database::fetchOne(
    "SELECT COUNT(DISTINCT category) AS cnt FROM skills"
);
$talentSkillsCategories = (int) ($talentSkillsCategoriesRow['cnt'] ?? 0);
} catch (Exception $ex) { error_log('[TALENT-HUB] Skills categories: ' . $ex->getMessage()); $talentSkillsCategories = 0; }
?>
          <!-- Talent Hub Stats -->
          <div class="admin-stats-row">
            <div class="admin-stat-chip admin-stat-chip--lg"><span class="admin-stat-chip__value"><?= number_format($talentTotalPool) ?></span> Total Talent Pool</div>
            <div class="admin-stat-chip admin-stat-chip--lg"><span class="admin-stat-chip__value"><?= number_format($talentActiveProfiles) ?></span> Active Profiles</div>
            <div class="admin-stat-chip admin-stat-chip--lg"><span class="admin-stat-chip__value"><?= number_format($talentAvailableNow) ?></span> Available Now</div>
            <div class="admin-stat-chip admin-stat-chip--lg"><span class="admin-stat-chip__value"><?= number_format($talentSkillsCategories) ?></span> Skills Categories</div>
            <div class="admin-stat-chip admin-stat-chip--lg"><span class="admin-stat-chip__value"><?= $avgProfileCompleteness ?>%</span> Avg Profile Score</div>
          </div>

          <!-- Advanced Filters -->
          <div class="admin-talent-filters">
            <div class="admin-talent-filters__row">
              <div class="admin-filter-group">
                <label class="admin-filter-label">Qualifications</label>
                <select class="admin-filter-select" id="talentQualificationFilter" multiple size="4">
                  <option value="">All Qualifications</option>
                  <?php if (!empty($talentQualificationLevels)): ?>
                    <?php foreach ($talentQualificationLevels as $ql): ?>
                      <option value="<?= e(strtolower(str_replace(' ', '-', $ql['qualification_level']))) ?>"><?= e($ql['qualification_level']) ?> (<?= (int)$ql['qualification_count'] ?>)</option>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <option value="" disabled>No qualifications found</option>
                  <?php endif; ?>
                </select>
              </div>
              <div class="admin-filter-group">
                <label class="admin-filter-label">Qualification Name</label>
                <select class="admin-filter-select" id="talentQualNameFilter">
                  <option value="">All Qualification Names</option>
                  <?php if (!empty($qualificationNamesOptions)): ?>
                    <?php foreach ($qualificationNamesOptions as $qname): ?>
                      <option value="<?= e(strtolower(str_replace(' ', '-', $qname))) ?>"><?= e($qname) ?></option>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <option value="" disabled>No qualification names found</option>
                  <?php endif; ?>
                </select>
              </div>
              <div class="admin-filter-group">
                <label class="admin-filter-label">Skills (multi-select)</label>
                <select class="admin-filter-select" id="talentSkillsFilter" multiple size="4">
                  <option value="">All Skills</option>
                  <?php if (!empty($talentSkillsDistribution)): ?>
                    <?php foreach ($talentSkillsDistribution as $sk): ?>
                      <option value="<?= e(strtolower(str_replace(' ', '-', $sk['name']))) ?>"><?= e($sk['name']) ?> (<?= (int)$sk['candidate_count'] ?>)</option>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <option value="" disabled>No skills found</option>
                  <?php endif; ?>
                </select>
                <small style="font-size:0.65rem;color:var(--text-lighter);">Hold Ctrl/Cmd to select multiple</small>
              </div>
              <div class="admin-filter-group">
                <label class="admin-filter-label">Career Interests</label>
                <select class="admin-filter-select" id="talentCareerFilter">
                  <option value="">All Interests</option>
                  <?php if (!empty($careerInterestsOptions)): ?>
                    <?php foreach ($careerInterestsOptions as $interest): ?>
                      <option value="<?= e(strtolower(str_replace(' ', '-', $interest))) ?>"><?= e($interest) ?></option>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <option value="" disabled>No career interests found</option>
                  <?php endif; ?>
                </select>
              </div>
              <div class="admin-filter-group">
                <label class="admin-filter-label">Location</label>
                <select class="admin-filter-select" id="talentLocationFilter">
                  <option value="">All Locations</option>
                  <?php if (!empty($talentGeoDistribution)): ?>
                    <?php foreach ($talentGeoDistribution as $geo): ?>
                      <option value="<?= e(strtolower(str_replace(' ', '-', $geo['city']))) ?>"><?= e($geo['city']) ?> (<?= (int)$geo['candidate_count'] ?>)</option>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <option value="" disabled>No locations found</option>
                  <?php endif; ?>
                </select>
              </div>
              <div class="admin-filter-group">
                <label class="admin-filter-label">Availability</label>
                <select class="admin-filter-select" id="talentAvailabilityFilter">
                  <option value="">All</option>
                  <?php if (!empty($talentAvailability)): ?>
                    <?php foreach ($talentAvailability as $av): ?>
                      <option value="<?= e(strtolower(str_replace(' ', '-', $av['availability_label'] ?? 'unknown'))) ?>"><?= e($av['availability_label'] ?? 'Unknown') ?> (<?= (int)$av['candidate_count'] ?>)</option>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <option value="" disabled>No availability data</option>
                  <?php endif; ?>
                </select>
              </div>
              <div class="admin-filter-group">
                <label class="admin-filter-label">Experience</label>
                <select class="admin-filter-select" id="talentExperienceFilter">
                  <option value="">All Levels</option>
                  <?php if (!empty($talentExperienceLevels)): ?>
                    <?php foreach ($talentExperienceLevels as $el): ?>
                      <?php
                      $_elLabel = $el['experience_level'];
                      $_elParen = stripos($_elLabel, ' (');
                      if ($_elParen !== false) $_elLabel = substr($_elLabel, 0, $_elParen);
                      $_elSlug = e(strtolower(str_replace(' ', '-', $_elLabel)));
                      ?>
                      <option value="<?= $_elSlug ?>"><?= e($el['experience_level']) ?> (<?= (int)$el['candidate_count'] ?>)</option>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <option value="" disabled>No experience data</option>
                  <?php endif; ?>
                </select>
              </div>
            </div>
            <div class="admin-talent-filters__actions">
              <button type="button" class="btn btn--primary btn--sm" id="talentSearchBtn"><i class="fas fa-search"></i> Search Talent</button>
              <button type="button" class="btn btn--outline btn--sm" id="talentSaveSearchBtn"><i class="fas fa-save"></i> Save Search</button>
              <button type="button" class="btn btn--ghost btn--sm" id="talentExportBtn"><i class="fas fa-file-export"></i> Export Results</button>
              <span class="admin-talent-filters__count" id="talentResultCount">Showing <strong><?= number_format($talentTotalPool) ?></strong> candidates</span>
            </div>
          </div>

          <!-- Talent Search Results -->
          <div class="admin-talent-results" id="talentResultsContainer">
            <?php if (empty($talentRecentActivity)): ?>
              <div class="admin-empty-state" style="grid-column:1/-1;">
                <div class="admin-empty-state__icon"><i class="fas fa-users"></i></div>
                <h3>No candidates found</h3>
                <p>Candidates will appear here once they register and create profiles.</p>
              </div>
            <?php else: ?>
              <?php foreach ($talentRecentActivity as $candidate): ?>
                <?php
                $cId = (int) ($candidate['id'] ?? 0);
                $cName = e(($candidate['first_name'] ?? '') . ' ' . ($candidate['last_name'] ?? ''));
                $cEmail = e($candidate['email'] ?? '');
                $cTitle = e($candidate['professional_title'] ?? 'Candidate');
                $cCompletion = (int) ($candidate['completion_percent'] ?? 0);
                $cRegistered = date('M j, Y', strtotime($candidate['created_at']));
                $cInitials = strtoupper(substr($candidate['first_name'] ?? '', 0, 1) . substr($candidate['last_name'] ?? '', 0, 1));
                $cCity = e(strtolower(str_replace(' ', '-', $candidate['city'] ?? '')));
                $cAvailability = e(strtolower(str_replace(' ', '-', $candidate['availability_label'] ?? '')));
                $cSkills = isset($candidateSkillsMap[$cId]) ? implode(',', $candidateSkillsMap[$cId]) : '';
                $cQualifications = isset($candidateQualificationsMap[$cId]) ? implode(',', $candidateQualificationsMap[$cId]) : '';
                $cQualNames = isset($candidateQualNamesMap[$cId]) ? implode(',', $candidateQualNamesMap[$cId]) : '';
                $cCareerInterests = isset($candidateCareerMap[$cId]) ? implode(',', $candidateCareerMap[$cId]) : '';
                ?>
                <div class="admin-programme-card" style="flex-direction:row;align-items:center;gap:1rem;" data-skills="<?= $cSkills ?>" data-city="<?= $cCity ?>" data-availability="<?= $cAvailability ?>" data-qualifications="<?= $cQualifications ?>" data-qual-names="<?= $cQualNames ?>" data-career="<?= $cCareerInterests ?>" data-title="<?= e(strtolower($cTitle)) ?>" data-name="<?= e(strtolower($cName)) ?>">
                  <div style="width:48px;height:48px;border-radius:50%;background:var(--primary-bg);color:var(--primary);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1rem;"><?= $cInitials ?></div>
                  <div style="flex:1;min-width:0;">
                    <div style="font-weight:700;color:var(--dark);"><?= $cName ?: 'Unnamed Candidate' ?></div>
                    <div style="font-size:0.8rem;color:var(--text-light);"><?= $cTitle ?></div>
                    <div style="font-size:0.75rem;color:var(--text-lighter);"><?= $cEmail ?></div>
                  </div>
                  <div style="text-align:right;">
                    <div style="font-size:0.8rem;font-weight:600;color:var(--success);"><?= $cCompletion ?>% complete</div>
                    <div style="font-size:0.7rem;color:var(--text-lighter);"><?= $cRegistered ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <!-- Talent Analytics Dashboard -->
          <div style="margin-top:2rem;">
            <div class="section__header" style="text-align:left;margin-bottom:1.25rem;">
              <h3 style="font-size:1.1rem;font-weight:700;">Talent Analytics</h3>
              <p style="font-size:0.8rem;color:var(--text-light);">Visual insights into candidate skills, qualifications, experience, and distribution.</p>
            </div>

            <div class="admin-analytics-grid">
              <!-- Skills Distribution -->
              <div class="admin-analytics-card">
                <div class="admin-analytics-card__header">
                  <h3>Skills Distribution</h3>
                  <span class="admin-analytics-card__badge">Top Skills</span>
                </div>
                <?php if (empty($talentSkillsDistribution)): ?>
                  <div style="text-align:center;padding:1rem;color:var(--text-light);font-size:0.8rem;">No skills data available</div>
                <?php else: ?>
                  <div style="display:flex;flex-direction:column;gap:0.5rem;">
                    <?php
                    $maxSkillCount = max(array_column($talentSkillsDistribution, 'candidate_count'));
                    $topSkills = array_slice($talentSkillsDistribution, 0, 8);
                    foreach ($topSkills as $skill):
                      $skillName = e($skill['name']);
                      $skillCount = (int) $skill['candidate_count'];
                      $skillPct = $maxSkillCount > 0 ? round(($skillCount / $maxSkillCount) * 100) : 0;
                      $skillCategory = e($skill['category'] ?? 'technical');
                    ?>
                      <div>
                        <div style="display:flex;justify-content:space-between;font-size:0.75rem;margin-bottom:0.2rem;">
                          <span style="font-weight:500;"><?= $skillName ?></span>
                          <span style="color:var(--text-lighter);"><?= $skillCount ?> <span style="font-size:0.65rem;">(<?= $skillCategory ?>)</span></span>
                        </div>
                        <div style="height:6px;background:var(--border-light);border-radius:3px;overflow:hidden;">
                          <div style="height:100%;width:<?= $skillPct ?>%;background:linear-gradient(90deg,var(--primary),var(--cyan));border-radius:3px;"></div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>

              <!-- Qualification Trends -->
              <div class="admin-analytics-card">
                <div class="admin-analytics-card__header">
                  <h3>Qualification Trends</h3>
                  <span class="admin-analytics-card__badge">Levels</span>
                </div>
                <?php if (empty($talentQualificationLevels)): ?>
                  <div style="text-align:center;padding:1rem;color:var(--text-light);font-size:0.8rem;">No qualification data available</div>
                <?php else: ?>
                  <div style="display:flex;flex-direction:column;gap:0.5rem;">
                    <?php
                    $maxQualCount = max(array_column($talentQualificationLevels, 'qualification_count'));
                    foreach ($talentQualificationLevels as $qual):
                      $qualName = e($qual['qualification_level']);
                      $qualCount = (int) $qual['qualification_count'];
                      $qualPct = $maxQualCount > 0 ? round(($qualCount / $maxQualCount) * 100) : 0;
                    ?>
                      <div>
                        <div style="display:flex;justify-content:space-between;font-size:0.75rem;margin-bottom:0.2rem;">
                          <span style="font-weight:500;"><?= $qualName ?></span>
                          <span style="color:var(--text-lighter);"><?= $qualCount ?></span>
                        </div>
                        <div style="height:6px;background:var(--border-light);border-radius:3px;overflow:hidden;">
                          <div style="height:100%;width:<?= $qualPct ?>%;background:linear-gradient(90deg,var(--success),#34d399);border-radius:3px;"></div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <div class="admin-analytics-grid" style="margin-top:1rem;">
              <!-- Experience Levels -->
              <div class="admin-analytics-card">
                <div class="admin-analytics-card__header">
                  <h3>Experience Levels</h3>
                  <span class="admin-analytics-card__badge">Distribution</span>
                </div>
                <?php if (empty($talentExperienceLevels)): ?>
                  <div style="text-align:center;padding:1rem;color:var(--text-light);font-size:0.8rem;">No experience data available</div>
                <?php else: ?>
                  <div style="display:flex;flex-direction:column;gap:0.5rem;">
                    <?php
                    $maxExpCount = max(array_column($talentExperienceLevels, 'candidate_count'));
                    foreach ($talentExperienceLevels as $exp):
                      $expName = e($exp['experience_level']);
                      $expCount = (int) $exp['candidate_count'];
                      $expPct = $maxExpCount > 0 ? round(($expCount / $maxExpCount) * 100) : 0;
                    ?>
                      <div>
                        <div style="display:flex;justify-content:space-between;font-size:0.75rem;margin-bottom:0.2rem;">
                          <span style="font-weight:500;"><?= $expName ?></span>
                          <span style="color:var(--text-lighter);"><?= $expCount ?></span>
                        </div>
                        <div style="height:6px;background:var(--border-light);border-radius:3px;overflow:hidden;">
                          <div style="height:100%;width:<?= $expPct ?>%;background:linear-gradient(90deg,var(--accent),#fbbf24);border-radius:3px;"></div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>

              <!-- Geographic Distribution -->
              <div class="admin-analytics-card">
                <div class="admin-analytics-card__header">
                  <h3>Geographic Distribution</h3>
                  <span class="admin-analytics-card__badge">By City</span>
                </div>
                <?php if (empty($talentGeoDistribution)): ?>
                  <div style="text-align:center;padding:1rem;color:var(--text-light);font-size:0.8rem;">No location data available</div>
                <?php else: ?>
                  <div style="display:flex;flex-direction:column;gap:0.5rem;">
                    <?php
                    $maxGeoCount = max(array_column($talentGeoDistribution, 'candidate_count'));
                    foreach ($talentGeoDistribution as $geo):
                      $geoName = e($geo['city']);
                      $geoCount = (int) $geo['candidate_count'];
                      $geoPct = $maxGeoCount > 0 ? round(($geoCount / $maxGeoCount) * 100) : 0;
                    ?>
                      <div>
                        <div style="display:flex;justify-content:space-between;font-size:0.75rem;margin-bottom:0.2rem;">
                          <span style="font-weight:500;"><?= $geoName ?></span>
                          <span style="color:var(--text-lighter);"><?= $geoCount ?></span>
                        </div>
                        <div style="height:6px;background:var(--border-light);border-radius:3px;overflow:hidden;">
                          <div style="height:100%;width:<?= $geoPct ?>%;background:linear-gradient(90deg,var(--purple),#a78bfa);border-radius:3px;"></div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <!-- Programme Participation -->
            <div class="admin-analytics-grid" style="margin-top:1rem;">
              <div class="admin-analytics-card admin-analytics-card--full">
                <div class="admin-analytics-card__header">
                  <h3>Programme Participation</h3>
                  <span class="admin-analytics-card__badge">Cohort Enrollment</span>
                </div>
                <?php if (empty($talentProgrammeParticipation)): ?>
                  <div style="text-align:center;padding:1rem;color:var(--text-light);font-size:0.8rem;">No programme data available</div>
                <?php else: ?>
                  <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:0.8rem;">
                      <thead>
                        <tr style="border-bottom:1px solid var(--border);">
                          <th style="text-align:left;padding:0.5rem;color:var(--text-lighter);font-weight:600;">Programme</th>
                          <th style="text-align:left;padding:0.5rem;color:var(--text-lighter);font-weight:600;">Type</th>
                          <th style="text-align:center;padding:0.5rem;color:var(--text-lighter);font-weight:600;">Status</th>
                          <th style="text-align:center;padding:0.5rem;color:var(--text-lighter);font-weight:600;">Cohorts</th>
                          <th style="text-align:center;padding:0.5rem;color:var(--text-lighter);font-weight:600;">Capacity</th>
                          <th style="text-align:center;padding:0.5rem;color:var(--text-lighter);font-weight:600;">Participants</th>
                          <th style="text-align:center;padding:0.5rem;color:var(--text-lighter);font-weight:600;">Applications</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($talentProgrammeParticipation as $prog): ?>
                          <?php
                          $progName = e($prog['name']);
                          $progType = e(ucwords(str_replace('_', ' ', $prog['type'])));
                          $progStatus = e($prog['status']);
                          $progCohorts = (int) $prog['total_cohorts'];
                          $progCapacity = (int) $prog['total_capacity'];
                          $progParticipants = (int) $prog['total_participants'];
                          $progApplications = (int) $prog['total_applications'];
                          $statusColor = 'var(--text-lighter)';
                          if ($progStatus === 'active') $statusColor = 'var(--success)';
                          elseif ($progStatus === 'draft') $statusColor = 'var(--text-lighter)';
                          elseif ($progStatus === 'completed') $statusColor = 'var(--primary)';
                          elseif ($progStatus === 'archived') $statusColor = '#ef4444';
                          ?>
                          <tr style="border-bottom:1px solid var(--border-light);">
                            <td style="padding:0.5rem;font-weight:600;"><?= $progName ?></td>
                            <td style="padding:0.5rem;color:var(--text-light);"><?= $progType ?></td>
                            <td style="padding:0.5rem;text-align:center;"><span style="color:<?= $statusColor ?>;font-weight:600;text-transform:capitalize;"><?= $progStatus ?></span></td>
                            <td style="padding:0.5rem;text-align:center;"><?= $progCohorts ?></td>
                            <td style="padding:0.5rem;text-align:center;"><?= number_format($progCapacity) ?></td>
                            <td style="padding:0.5rem;text-align:center;font-weight:600;color:var(--primary);"><?= number_format($progParticipants) ?></td>
                            <td style="padding:0.5rem;text-align:center;"><?= number_format($progApplications) ?></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <!-- Talent Insights Row -->
            <div class="admin-analytics-grid" style="margin-top:1rem;">
              <!-- Employment Status -->
              <div class="admin-analytics-card">
                <div class="admin-analytics-card__header">
                  <h3>Employment Status</h3>
                  <span class="admin-analytics-card__badge">Candidates</span>
                </div>
                <?php if (empty($talentEmploymentStatus)): ?>
                  <div style="text-align:center;padding:1rem;color:var(--text-light);font-size:0.8rem;">No employment data available</div>
                <?php else: ?>
                  <div style="display:flex;flex-direction:column;gap:0.5rem;">
                    <?php
                    $maxEmpCount = max(array_column($talentEmploymentStatus, 'candidate_count'));
                    foreach ($talentEmploymentStatus as $emp):
                      $empName = e($emp['employment_status']);
                      $empCount = (int) $emp['candidate_count'];
                      $empPct = $maxEmpCount > 0 ? round(($empCount / $maxEmpCount) * 100) : 0;
                    ?>
                      <div>
                        <div style="display:flex;justify-content:space-between;font-size:0.75rem;margin-bottom:0.2rem;">
                          <span style="font-weight:500;"><?= $empName ?></span>
                          <span style="color:var(--text-lighter);"><?= $empCount ?></span>
                        </div>
                        <div style="height:6px;background:var(--border-light);border-radius:3px;overflow:hidden;">
                          <div style="height:100%;width:<?= $empPct ?>%;background:linear-gradient(90deg,var(--indigo),#818cf8);border-radius:3px;"></div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>

              <!-- Key Metrics Summary -->
              <div class="admin-analytics-card">
                <div class="admin-analytics-card__header">
                  <h3>Key Metrics</h3>
                  <span class="admin-analytics-card__badge">Trends</span>
                </div>
                <div style="display:flex;flex-direction:column;gap:0.75rem;">
                  <div style="display:flex;justify-content:space-between;align-items:center;padding:0.5rem;background:var(--bg);border-radius:var(--radius-sm);">
                    <span style="font-size:0.8rem;font-weight:500;">New Candidates (30d)</span>
                    <div style="display:flex;align-items:center;gap:0.5rem;">
                      <span style="font-weight:700;color:var(--dark);"><?= number_format($newCandidatesMonth) ?></span>
                      <span style="font-size:0.7rem;color:<?= $talentNewCandidateTrend >= 0 ? 'var(--success)' : '#ef4444' ?>;">
                        <i class="fas fa-arrow-<?= $talentNewCandidateTrend >= 0 ? 'up' : 'down' ?>"></i>
                        <?= abs($talentNewCandidateTrend) ?>%
                      </span>
                    </div>
                  </div>
                  <div style="display:flex;justify-content:space-between;align-items:center;padding:0.5rem;background:var(--bg);border-radius:var(--radius-sm);">
                    <span style="font-size:0.8rem;font-weight:500;">Avg Profile Completion</span>
                    <div style="display:flex;align-items:center;gap:0.5rem;">
                      <span style="font-weight:700;color:var(--dark);"><?= $avgProfileCompleteness ?>%</span>
                      <span style="font-size:0.7rem;color:<?= $talentCompletionTrend >= 0 ? 'var(--success)' : '#ef4444' ?>;">
                        <i class="fas fa-arrow-<?= $talentCompletionTrend >= 0 ? 'up' : 'down' ?>"></i>
                        <?= abs($talentCompletionTrend) ?>%
                      </span>
                    </div>
                  </div>
                  <div style="display:flex;justify-content:space-between;align-items:center;padding:0.5rem;background:var(--bg);border-radius:var(--radius-sm);">
                    <span style="font-size:0.8rem;font-weight:500;">Complete Profiles (80%+)</span>
                    <span style="font-weight:700;color:var(--success);"><?= number_format($completeProfiles) ?></span>
                  </div>
                  <div style="display:flex;justify-content:space-between;align-items:center;padding:0.5rem;background:var(--bg);border-radius:var(--radius-sm);">
                    <span style="font-size:0.8rem;font-weight:500;">Total Profiles</span>
                    <span style="font-weight:700;color:var(--primary);"><?= number_format($totalCandidateProfiles) ?></span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- =============================================
             SECTION 10: TALENT MATCHING SYSTEM
             ============================================= -->
        <section class="dash-section admin-section" id="admin-talent-matching" data-section="talent-matching">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <span class="section__badge">Intelligent Matching</span>
            <h2 class="section__title" style="font-size:1.5rem;">Talent <span class="text-gradient">Matching System</span></h2>
            <p class="section__text" style="font-size:0.9rem;">AI-powered matching to find the best candidates for opportunities.</p>
          </div>

          <div class="admin-matching-dashboard">
            <!-- Match Overview -->
            <div class="admin-matching-overview">
              <div class="admin-matching-stat">
                <span class="admin-matching-stat__value">92%</span>
                <span class="admin-matching-stat__label">Average Match Accuracy</span>
              </div>
              <div class="admin-matching-stat">
                <span class="admin-matching-stat__value">156</span>
                <span class="admin-matching-stat__label">Pending Matches</span>
              </div>
              <div class="admin-matching-stat">
                <span class="admin-matching-stat__value">48</span>
                <span class="admin-matching-stat__label">Recommended Placements</span>
              </div>
              <div class="admin-matching-stat">
                <span class="admin-matching-stat__value">89%</span>
                <span class="admin-matching-stat__label">Candidate Readiness</span>
              </div>
            </div>

            <!-- Matching Cards -->
            <div class="admin-matching-grid" id="adminMatchingGrid">
              <!-- Populated by JS -->
            </div>
          </div>
        </section>

        <!-- =============================================
             SECTION 11: TALENT POOLS
             ============================================= -->
        <section class="dash-section admin-section" id="admin-talent-pool" data-section="talent-pool">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <span class="section__badge">Talent Pools</span>
            <h2 class="section__title" style="font-size:1.5rem;">Talent <span class="text-gradient">Pools</span></h2>
            <p class="section__text" style="font-size:0.9rem;">Manage and monitor talent pools across skill categories.</p>
          </div>

          <div class="admin-toolbar">
            <div class="admin-toolbar__left">
              <button class="btn btn--primary btn--sm"><i class="fas fa-plus"></i> Create Talent Pool</button>
            </div>
            <div class="admin-toolbar__right">
              <div class="admin-search-bar">
                <i class="fas fa-search"></i>
                <input type="text" class="admin-search-input" id="poolSearch" placeholder="Search pools...">
              </div>
            </div>
          </div>

          <div class="admin-pool-grid" id="adminPoolGrid">
            <!-- Populated by JS -->
          </div>
        </section>

        <!-- =============================================
             SECTION 12: ATTENDANCE & TIMESHEETS
             ============================================= -->
        <section class="dash-section admin-section" id="admin-attendance" data-section="attendance">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <span class="section__badge">Attendance Management</span>
            <h2 class="section__title" style="font-size:1.5rem;">Attendance & <span class="text-gradient">Timesheets</span></h2>
            <p class="section__text" style="font-size:0.9rem;">Monitor attendance, approve timesheets, and track compliance.</p>
          </div>

          <!-- Attendance Stats -->
          <div class="admin-stats-row">
            <div class="admin-stat-chip"><span class="admin-stat-chip__value">94%</span> Attendance Rate</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value">24</span> Missing Timesheets</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value">88%</span> Approval Rate</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value">96%</span> Programme Compliance</div>
            <div class="admin-stat-chip admin-stat-chip--danger"><span class="admin-stat-chip__value">6</span> Attendance Risks</div>
          </div>

          <div class="admin-attendance-grid" id="adminAttendanceGrid">
            <!-- Populated by JS -->
          </div>
        </section>

        <!-- =============================================
             SECTION 13: COMMUNICATION CENTRE
             ============================================= -->
        <section class="dash-section admin-section" id="admin-communication" data-section="communication">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <span class="section__badge">Communication Centre</span>
            <h2 class="section__title" style="font-size:1.5rem;">Communication <span class="text-gradient">Centre</span></h2>
            <p class="section__text" style="font-size:0.9rem;">Send notifications, manage templates, and monitor delivery.</p>
          </div>

          <div class="admin-comm-toolbar">
            <button class="btn btn--primary btn--sm"><i class="fas fa-paper-plane"></i> Send Notification</button>
            <button class="btn btn--outline btn--sm"><i class="fas fa-users"></i> Bulk Notify</button>
            <button class="btn btn--outline btn--sm"><i class="fas fa-envelope"></i> Email Templates</button>
            <button class="btn btn--outline btn--sm"><i class="fas fa-clock"></i> Schedule Reminder</button>
            <button class="btn btn--ghost btn--sm"><i class="fas fa-history"></i> Notification History</button>
          </div>

          <!-- Delivery Stats -->
          <div class="admin-stats-row" style="margin-top:1rem;">
            <div class="admin-stat-chip admin-stat-chip--lg"><span class="admin-stat-chip__value">12,450</span> Total Sent</div>
            <div class="admin-stat-chip admin-stat-chip--lg"><span class="admin-stat-chip__value">94%</span> Delivery Rate</div>
            <div class="admin-stat-chip admin-stat-chip--lg"><span class="admin-stat-chip__value">68%</span> Open Rate</div>
            <div class="admin-stat-chip admin-stat-chip--lg"><span class="admin-stat-chip__value">42%</span> Click Rate</div>
          </div>

          <!-- Communication Channels -->
          <div class="admin-comm-channels">
            <div class="admin-comm-channel">
              <div class="admin-comm-channel__icon admin-comm-channel__icon--email"><i class="fas fa-envelope"></i></div>
              <div class="admin-comm-channel__info">
                <span class="admin-comm-channel__name">Email</span>
                <span class="admin-comm-channel__status">Connected</span>
              </div>
              <span class="admin-comm-channel__count">8,450 sent</span>
            </div>
            <div class="admin-comm-channel">
              <div class="admin-comm-channel__icon admin-comm-channel__icon--sms"><i class="fas fa-sms"></i></div>
              <div class="admin-comm-channel__info">
                <span class="admin-comm-channel__name">SMS</span>
                <span class="admin-comm-channel__status">Connected</span>
              </div>
              <span class="admin-comm-channel__count">3,200 sent</span>
            </div>
            <div class="admin-comm-channel">
              <div class="admin-comm-channel__icon admin-comm-channel__icon--inapp"><i class="fas fa-bell"></i></div>
              <div class="admin-comm-channel__info">
                <span class="admin-comm-channel__name">In-App Notifications</span>
                <span class="admin-comm-channel__status">Active</span>
              </div>
              <span class="admin-comm-channel__count">12,450 pushed</span>
            </div>
            <div class="admin-comm-channel admin-comm-channel--disabled">
              <div class="admin-comm-channel__icon"><i class="fab fa-whatsapp"></i></div>
              <div class="admin-comm-channel__info">
                <span class="admin-comm-channel__name">WhatsApp</span>
                <span class="admin-comm-channel__status admin-comm-channel__status--disabled">Coming Soon</span>
              </div>
            </div>
          </div>
        </section>

        <!-- =============================================
             SECTION 14: REPORTING & ANALYTICS
             ============================================= -->
        <section class="dash-section admin-section" id="admin-reporting" data-section="reporting">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <span class="section__badge">Reporting</span>
            <h2 class="section__title" style="font-size:1.5rem;">Reporting & <span class="text-gradient">Analytics</span></h2>
            <p class="section__text" style="font-size:0.9rem;">Generate, export, and schedule comprehensive reports.</p>
          </div>

          <div class="admin-report-categories">
            <button class="admin-report-cat active" data-report="programmes"><i class="fas fa-graduation-cap"></i> Programmes</button>
            <button class="admin-report-cat" data-report="candidates"><i class="fas fa-users"></i> Candidates</button>
            <button class="admin-report-cat" data-report="placements"><i class="fas fa-handshake"></i> Placements</button>
            <button class="admin-report-cat" data-report="recruitment"><i class="fas fa-search"></i> Recruitment</button>
            <button class="admin-report-cat" data-report="skills"><i class="fas fa-code"></i> Skills</button>
            <button class="admin-report-cat" data-report="attendance"><i class="fas fa-clock"></i> Attendance</button>
            <button class="admin-report-cat" data-report="completion"><i class="fas fa-check-circle"></i> Completion</button>
            <button class="admin-report-cat" data-report="employment"><i class="fas fa-briefcase"></i> Employment</button>
            <button class="admin-report-cat" data-report="governance"><i class="fas fa-shield-alt"></i> Governance</button>
            <button class="admin-report-cat" data-report="talent"><i class="fas fa-database"></i> Talent Pool</button>
          </div>

          <div class="admin-reports-list" id="adminReportsList">
            <!-- Populated by JS -->
          </div>
        </section>

        <!-- =============================================
             SECTION 15: CONSENT & COMPLIANCE
             ============================================= -->
        <section class="dash-section admin-section" id="admin-compliance" data-section="compliance">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <span class="section__badge">Compliance</span>
            <h2 class="section__title" style="font-size:1.5rem;">Consent & <span class="text-gradient">Compliance</span></h2>
            <p class="section__text" style="font-size:0.9rem;">Manage consent records, data quality, and governance.</p>
          </div>

          <div class="admin-compliance-grid">
            <div class="admin-compliance-card">
              <div class="admin-compliance-card__header">
                <div class="admin-compliance-card__icon admin-compliance-card__icon--green"><i class="fas fa-check-circle"></i></div>
                <span class="admin-compliance-card__value">92%</span>
              </div>
              <span class="admin-compliance-card__label">Consent Provided</span>
              <span class="admin-compliance-card__sub">3,146 candidates</span>
            </div>
            <div class="admin-compliance-card">
              <div class="admin-compliance-card__header">
                <div class="admin-compliance-card__icon admin-compliance-card__icon--primary"><i class="fas fa-id-card"></i></div>
                <span class="admin-compliance-card__value">78%</span>
              </div>
              <span class="admin-compliance-card__label">Profile Verification</span>
              <span class="admin-compliance-card__sub">2,668 verified</span>
            </div>
            <div class="admin-compliance-card">
              <div class="admin-compliance-card__header">
                <div class="admin-compliance-card__icon admin-compliance-card__icon--amber"><i class="fas fa-database"></i></div>
                <span class="admin-compliance-card__value">95%</span>
              </div>
              <span class="admin-compliance-card__label">Data Quality Score</span>
              <span class="admin-compliance-card__sub">A rating</span>
            </div>
            <div class="admin-compliance-card">
              <div class="admin-compliance-card__header">
                <div class="admin-compliance-card__icon admin-compliance-card__icon--purple"><i class="fas fa-file-export"></i></div>
                <span class="admin-compliance-card__value">142</span>
              </div>
              <span class="admin-compliance-card__label">Export Activities</span>
              <span class="admin-compliance-card__sub">This month</span>
            </div>
            <div class="admin-compliance-card">
              <div class="admin-compliance-card__header">
                <div class="admin-compliance-card__icon admin-compliance-card__icon--cyan"><i class="fas fa-history"></i></div>
                <span class="admin-compliance-card__value">2,450</span>
              </div>
              <span class="admin-compliance-card__label">Audit Events</span>
              <span class="admin-compliance-card__sub">This quarter</span>
            </div>
            <div class="admin-compliance-card">
              <div class="admin-compliance-card__header">
                <div class="admin-compliance-card__icon admin-compliance-card__icon--indigo"><i class="fas fa-calendar-alt"></i></div>
                <span class="admin-compliance-card__value">24</span>
              </div>
              <span class="admin-compliance-card__label">Retention Schedules</span>
              <span class="admin-compliance-card__sub">Active policies</span>
            </div>
          </div>
        </section>

        <!-- =============================================
             SECTION 16: AUDIT LOG
             ============================================= -->
        <section class="dash-section admin-section" id="admin-audit" data-section="audit">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <span class="section__badge">Audit Trail</span>
            <h2 class="section__title" style="font-size:1.5rem;">Audit <span class="text-gradient">Log</span></h2>
            <p class="section__text" style="font-size:0.9rem;">Comprehensive audit trail of all platform activities.</p>
          </div>

          <div class="admin-toolbar">
            <div class="admin-toolbar__left">
              <select class="admin-filter-select" id="auditFilterAction">
                <option value="all">All Actions</option>
                <option value="auth">Authentication</option>
                <option value="programme">Programme Changes</option>
                <option value="candidate">Candidate Updates</option>
                <option value="permissions">Permission Changes</option>
                <option value="exports">Data Exports</option>
                <option value="reports">Report Generation</option>
                <option value="admin">Admin Actions</option>
                <option value="notifications">Notifications</option>
              </select>
              <select class="admin-filter-select" id="auditFilterDate">
                <option value="24h">Last 24 Hours</option>
                <option value="7d">Last 7 Days</option>
                <option value="30d" selected>Last 30 Days</option>
                <option value="90d">Last Quarter</option>
              </select>
            </div>
            <div class="admin-toolbar__right">
              <div class="admin-search-bar">
                <i class="fas fa-search"></i>
                <input type="text" class="admin-search-input" id="auditSearch" placeholder="Search audit log...">
              </div>
            </div>
          </div>

          <div class="admin-table-container admin-audit-table" id="adminAuditTable">
            <!-- Populated by JS -->
          </div>
        </section>

        <!-- =============================================
             SECTION 17: USER MANAGEMENT (dynamic)
             ============================================= -->
        <?php
        // ------------------------------------------------------------
        // Fetch dynamic user statistics from the database
        // ------------------------------------------------------------

        // Get filter parameters from request
        $userSearch = isset($_GET['user_search']) ? trim($_GET['user_search']) : '';
        $userRoleFilter = isset($_GET['user_role']) ? trim($_GET['user_role']) : '';
        $userStatusFilter = isset($_GET['user_status']) ? trim($_GET['user_status']) : '';

        // Build the base query with conditions
        $queryConditions = [];
        $queryParams = [];
        $queryTypes = '';

        if ($userSearch !== '') {
            $queryConditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)";
            $searchTerm = '%' . $userSearch . '%';
            $queryParams[] = $searchTerm;
            $queryParams[] = $searchTerm;
            $queryParams[] = $searchTerm;
            $queryParams[] = $searchTerm;
            $queryTypes .= 'ssss';
        }

        if ($userRoleFilter !== '') {
            $queryConditions[] = "r.slug = ?";
            $queryParams[] = $userRoleFilter;
            $queryTypes .= 's';
        }

        if ($userStatusFilter !== '') {
            $queryConditions[] = "u.status = ?";
            $queryParams[] = $userStatusFilter;
            $queryTypes .= 's';
        }

        $whereClause = !empty($queryConditions) ? 'WHERE ' . implode(' AND ', $queryConditions) : '';

        // Total users count
        $totalUsersRow = Database::fetchOne("SELECT COUNT(*) AS cnt FROM users u");
        $totalUsers = (int) ($totalUsersRow['cnt'] ?? 0);

        // Active users count
        $activeUsersRow = Database::fetchOne("SELECT COUNT(*) AS cnt FROM users u WHERE u.status = 'active'");
        $activeUsers = (int) ($activeUsersRow['cnt'] ?? 0);

        // Inactive users count (suspended + disabled + pending)
        $inactiveUsersRow = Database::fetchOne("SELECT COUNT(*) AS cnt FROM users u WHERE u.status IN ('suspended', 'disabled', 'pending')");
        $inactiveUsers = (int) ($inactiveUsersRow['cnt'] ?? 0);

        // Candidate count
        $candidatesCountRow = Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE r.slug = 'candidate'"
        );
        $candidatesCount = (int) ($candidatesCountRow['cnt'] ?? 0);

        // Administrator count
        $adminsCountRow = Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE r.slug = 'admin'"
        );
        $adminsCount = (int) ($adminsCountRow['cnt'] ?? 0);

        // Fetch all roles for the filter dropdown
        $roles = Database::fetchAll("SELECT id, name, slug FROM roles ORDER BY name ASC");

        // Fetch users with role information (dashboard shows a preview of 7 users;
        // the full, paginated list lives on admin/users.php)
        $usersSql = "SELECT u.id, u.first_name, u.last_name, u.username, u.email, u.status,
                            u.last_login, u.created_at, r.name AS role_name, r.slug AS role_slug
                     FROM users u
                     INNER JOIN roles r ON r.id = u.role_id
                     $whereClause
                     ORDER BY u.created_at DESC
                     LIMIT 7";
        $users = Database::fetchAll($usersSql, $queryTypes, $queryParams);

        // Total users matching the current filters (for the summary line below the table)
        $filteredCountSql = "SELECT COUNT(*) AS cnt FROM users u INNER JOIN roles r ON r.id = u.role_id $whereClause";
        $filteredCountRow = Database::fetchOne($filteredCountSql, $queryTypes, $queryParams);
        $filteredUsersCount = (int) ($filteredCountRow['cnt'] ?? 0);

        // "View all users" link — preserves the active search & filters
        $viewAllParams = [];
        if ($userSearch !== '') $viewAllParams['q'] = $userSearch;
        if ($userRoleFilter !== '') $viewAllParams['role'] = $userRoleFilter;
        if ($userStatusFilter !== '') $viewAllParams['status'] = $userStatusFilter;
        $viewAllUrl = url('admin/users.php');
        if (!empty($viewAllParams)) {
            $viewAllUrl .= '?' . http_build_query($viewAllParams);
        }
        ?>

        <section class="dash-section admin-section" id="admin-users" data-section="users">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <span class="section__badge">User Management</span>
            <h2 class="section__title" style="font-size:1.5rem;">User <span class="text-gradient">Management</span></h2>
            <p class="section__text" style="font-size:0.9rem;">Manage platform users, roles, permissions, and security settings.</p>
          </div>

          <div class="admin-toolbar">
            <div class="admin-toolbar__left">
              <a href="<?= url('admin/user_create.php') ?>" class="btn btn--primary btn--sm"><i class="fas fa-user-plus"></i> Create User</a>
              <a href="<?= url('admin/users.php') ?>" class="btn btn--outline btn--sm"><i class="fas fa-users"></i> Manage Users</a>
              <button class="btn btn--outline btn--sm"><i class="fas fa-users"></i> Bulk Invite</button>
              <button class="btn btn--outline btn--sm"><i class="fas fa-user-tag"></i> Roles</button>
              <button class="btn btn--outline btn--sm"><i class="fas fa-shield-alt"></i> Permissions</button>
            </div>
            <div class="admin-toolbar__right">
              <div class="admin-search-bar">
                <i class="fas fa-search"></i>
                <input type="text" class="admin-search-input" id="userSearchInput" placeholder="Search users..." value="<?= e($userSearch) ?>">
              </div>
            </div>
          </div>

          <!-- User Filters -->
          <div class="admin-stats-row" style="margin-bottom:1rem;">
            <form method="GET" action="#admin-users" id="userFilterForm" style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:center;">
              <select name="user_role" class="admin-filter-select" id="userRoleFilter" onchange="this.form.submit()">
                <option value="">All Roles</option>
                <?php foreach ($roles as $role): ?>
                  <option value="<?= e($role['slug']) ?>" <?= $userRoleFilter === $role['slug'] ? 'selected' : '' ?>><?= e($role['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <select name="user_status" class="admin-filter-select" id="userStatusFilter" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="active" <?= $userStatusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="pending" <?= $userStatusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="suspended" <?= $userStatusFilter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                <option value="disabled" <?= $userStatusFilter === 'disabled' ? 'selected' : '' ?>>Disabled</option>
              </select>
              <?php if ($userSearch !== ''): ?>
                <input type="hidden" name="user_search" value="<?= e($userSearch) ?>">
              <?php endif; ?>
              <button type="button" class="btn btn--ghost btn--sm" id="clearUserFilters"><i class="fas fa-times"></i> Clear</button>
            </form>
          </div>

          <!-- User Stats (dynamic) -->
          <div class="admin-stats-row">
            <div class="admin-stat-chip"><span class="admin-stat-chip__value"><?= number_format($totalUsers) ?></span> Total Users</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value"><?= number_format($activeUsers) ?></span> Active</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value"><?= number_format($inactiveUsers) ?></span> Inactive</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value"><?= number_format($candidatesCount) ?></span> Candidates</div>
            <div class="admin-stat-chip"><span class="admin-stat-chip__value"><?= number_format($adminsCount) ?></span> Administrators</div>
          </div>

          <!-- User Table -->
          <div class="admin-table-container" id="adminUserTable">
            <?php if (empty($users)): ?>
              <div class="admin-empty-state">
                <div class="admin-empty-state__icon"><i class="fas fa-users"></i></div>
                <h3>No users found</h3>
                <p><?php if ($userSearch !== '' || $userRoleFilter !== '' || $userStatusFilter !== ''): ?>
                  No users match your search criteria. Try adjusting your filters.
                <?php else: ?>
                  No users have been registered yet.
                <?php endif; ?></p>
              </div>
            <?php else: ?>
              <table class="admin-table" style="width:100%;border-collapse:collapse;min-width:700px;">
                <thead>
                  <tr style="border-bottom:1px solid var(--border);background:var(--bg);">
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:700;color:var(--text-lighter);text-transform:uppercase;">Name</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:700;color:var(--text-lighter);text-transform:uppercase;">Email</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:700;color:var(--text-lighter);text-transform:uppercase;">Role</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:700;color:var(--text-lighter);text-transform:uppercase;">Status</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:700;color:var(--text-lighter);text-transform:uppercase;">Registered</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:700;color:var(--text-lighter);text-transform:uppercase;">Last Login</th>
                    <th style="padding:0.75rem 1rem;font-size:0.7rem;font-weight:700;color:var(--text-lighter);text-transform:uppercase;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($users as $user): ?>
                    <?php
                    $fullName = e($user['first_name'] . ' ' . $user['last_name']);
                    $email = e($user['email']);
                    $roleName = e($user['role_name']);
                    $status = e($user['status']);
                    $registeredDate = date('M j, Y', strtotime($user['created_at']));
                    $lastLogin = $user['last_login'] ? date('M j, Y g:ia', strtotime($user['last_login'])) : 'Never';

                    // Role tag color
                    $roleTagClass = 'tag--green';
                    if ($user['role_slug'] === 'admin') {
                        $roleTagClass = 'tag--primary';
                    } elseif ($user['role_slug'] === 'programme_manager') {
                        $roleTagClass = 'tag--cyan';
                    } elseif ($user['role_slug'] === 'recruiter') {
                        $roleTagClass = 'tag--amber';
                    } elseif ($user['role_slug'] === 'candidate') {
                        $roleTagClass = 'tag--green';
                    }

                    // Status color
                    $statusColor = '#ef4444';
                    if ($user['status'] === 'active') {
                        $statusColor = 'var(--success)';
                    } elseif ($user['status'] === 'pending') {
                        $statusColor = '#f59e0b';
                    }
                    ?>
                    <tr style="border-bottom:1px solid var(--border-light);" data-user-id="<?= (int)$user['id'] ?>" data-search="<?= e(strtolower($fullName . ' ' . $email . ' ' . $roleName)) ?>">
                      <td style="padding:0.75rem 1rem;">
                        <div class="pm-table__user">
                          <div class="pm-table__user-avatar" style="width:32px;height:32px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:0.7rem;font-weight:700;margin-right:0.75rem;">
                            <?= e(strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1))) ?>
                          </div>
                          <div class="pm-table__user-info">
                            <span class="pm-table__user-name" style="font-size:0.85rem;font-weight:600;color:var(--text);"><?= $fullName ?></span>
                            <span class="pm-table__user-username" style="font-size:0.7rem;color:var(--text-lighter);">@<?= e($user['username']) ?></span>
                          </div>
                        </div>
                      </td>
                      <td style="padding:0.75rem 1rem;font-size:0.8rem;color:var(--text-light);"><?= $email ?></td>
                      <td style="padding:0.75rem 1rem;"><span class="tag <?= $roleTagClass ?>" style="font-size:0.6rem;"><?= $roleName ?></span></td>
                      <td style="padding:0.75rem 1rem;font-size:0.8rem;color:<?= $statusColor ?>;font-weight:600;"><?= ucfirst($status) ?></td>
                      <td style="padding:0.75rem 1rem;font-size:0.8rem;color:var(--text-light);"><?= $registeredDate ?></td>
                      <td style="padding:0.75rem 1rem;font-size:0.8rem;color:var(--text-light);"><?= $lastLogin ?></td>
                      <td style="padding:0.75rem 1rem;">
                        <a href="#" class="btn btn--ghost btn--sm" style="font-size:0.65rem;"><i class="fas fa-eye"></i></a>
                        <a href="#" class="btn btn--ghost btn--sm" style="font-size:0.65rem;"><i class="fas fa-edit"></i></a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>

          <!-- Table summary + View all users -->
          <?php if (!empty($users)): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin-top:0.9rem;padding:0.85rem 1rem;border:1px solid var(--border);border-radius:12px;background:var(--card-bg,#fff);">
              <span style="font-size:0.8rem;color:var(--text-lighter);">
                <?php if ($filteredUsersCount > count($users)): ?>
                  Showing <strong style="color:var(--text);"><?= count($users) ?></strong> of <?= number_format($filteredUsersCount) ?> users — refine with the search &amp; filters above
                <?php else: ?>
                  Showing <?= count($users) ?> user<?= count($users) === 1 ? '' : 's' ?>
                <?php endif; ?>
              </span>
              <a href="<?= e($viewAllUrl) ?>" class="btn btn--outline btn--sm"><i class="fas fa-users"></i> View all users</a>
            </div>
          <?php endif; ?>
        </section>

        <!-- =============================================
             SECTION 18: QUICK ACTIONS PANEL
             ============================================= -->
        <section class="dash-section admin-section" id="admin-quick-actions" data-section="quick-actions">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <span class="section__badge">Productivity</span>
            <h2 class="section__title" style="font-size:1.5rem;">Quick <span class="text-gradient">Actions</span></h2>
            <p class="section__text" style="font-size:0.9rem;">Frequently used administrative actions at your fingertips.</p>
          </div>

<div class="admin-quick-actions-grid">
            <a href="<?= url('admin/programme_create.php') ?>" class="admin-quick-action">
              <div class="admin-quick-action__icon admin-quick-action__icon--primary"><i class="fas fa-graduation-cap"></i></div>
              <span>Create Programme</span>
            </a>
            <a href="<?= url('admin/cohort_create.php') ?>" class="admin-quick-action">
              <div class="admin-quick-action__icon admin-quick-action__icon--cyan"><i class="fas fa-layer-group"></i></div>
              <span>Create Cohort</span>
            </a>
            <a href="<?= url('admin/programmes.php') ?>" class="admin-quick-action">
              <div class="admin-quick-action__icon admin-quick-action__icon--green"><i class="fas fa-th-list"></i></div>
              <span>Manage Programmes</span>
            </a>
            <a href="#admin-talent-hub" class="admin-quick-action">
              <div class="admin-quick-action__icon admin-quick-action__icon--amber"><i class="fas fa-database"></i></div>
              <span>Talent Intelligence</span>
            </a>
            <a href="#admin-applications" class="admin-quick-action">
              <div class="admin-quick-action__icon admin-quick-action__icon--purple"><i class="fas fa-file-alt"></i></div>
              <span>Review Applications</span>
            </a>
            <a href="#admin-reporting" class="admin-quick-action">
              <div class="admin-quick-action__icon admin-quick-action__icon--indigo"><i class="fas fa-chart-line"></i></div>
              <span>Generate Reports</span>
            </a>
            <a href="#admin-communication" class="admin-quick-action">
              <div class="admin-quick-action__icon admin-quick-action__icon--red"><i class="fas fa-bullhorn"></i></div>
              <span>Send Notifications</span>
            </a>
            <a href="#admin-audit" class="admin-quick-action">
              <div class="admin-quick-action__icon admin-quick-action__icon--primary"><i class="fas fa-history"></i></div>
              <span>View Audit Logs</span>
            </a>
            <a href="#admin-placements" class="admin-quick-action">
              <div class="admin-quick-action__icon admin-quick-action__icon--cyan"><i class="fas fa-handshake"></i></div>
              <span>Manage Placements</span>
            </a>
            <a href="#admin-compliance" class="admin-quick-action">
              <div class="admin-quick-action__icon admin-quick-action__icon--amber"><i class="fas fa-shield-alt"></i></div>
              <span>Review Compliance</span>
            </a>
            <a href="<?= url('candidate/settings.php') ?>" class="admin-quick-action">
              <div class="admin-quick-action__icon admin-quick-action__icon--green"><i class="fas fa-cog"></i></div>
              <span>System Settings</span>
            </a>
            <a href="<?= url('auth/logout.php') ?>" class="admin-quick-action">
              <div class="admin-quick-action__icon admin-quick-action__icon--purple"><i class="fas fa-sign-out-alt"></i></div>
              <span>Sign Out</span>
            </a>
          </div>
        </section>

        <!-- ===== TALENT INTELLIGENCE HUB SECTION ===== -->
        <section class="dash-section admin-section" id="admin-talent-hub" data-section="talent" style="margin-top:2rem;">
          <div class="section__header" style="text-align:left;margin-bottom:1.5rem;">
            <span class="section__badge">Talent Intelligence</span>
            <h2 class="section__title" style="font-size:1.5rem;">Talent <span class="text-gradient">Intelligence Hub</span></h2>
            <p class="section__text" style="font-size:0.9rem;">Search and discover candidates from the complete talent pool.</p>
          </div>

          <!-- Talent Filters -->
          <div class="admin-talent-filters" style="background:var(--bg-white);border:1px solid var(--border);border-radius:14px;padding:1.25rem;margin-bottom:1.5rem;">
            <div class="admin-talent-filters__row" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:0.875rem;margin-bottom:1rem;">
              <div>
                <label style="font-size:0.75rem;font-weight:600;color:var(--text-muted);margin-bottom:0.25rem;display:block;">Qualification Level</label>
                <select id="talentQualificationFilter" class="admin-filter-select" style="width:100%;">
                  <option value="">All Levels</option>
                  <option value="certificate">Certificate</option>
                  <option value="diploma">Diploma</option>
                  <option value="degree">Degree</option>
                  <option value="masters">Masters</option>
                  <option value="phd">PhD</option>
                </select>
              </div>
              <div>
                <label style="font-size:0.75rem;font-weight:600;color:var(--text-muted);margin-bottom:0.25rem;display:block;">Skills</label>
                <select id="talentSkillsFilter" multiple style="width:100%;min-height:42px;">
                  <?php foreach ($skills as $s): ?><option value="<?= e($s['name']) ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
                </select>
              </div>
              <div>
                <label style="font-size:0.75rem;font-weight:600;color:var(--text-muted);margin-bottom:0.25rem;display:block;">Location</label>
                <select id="talentLocationFilter" class="admin-filter-select" style="width:100%;">
                  <option value="">All Locations</option>
                  <?php foreach ($cityList as $c): ?><option value="<?= e(strtolower(str_replace(' ', '-', trim($c)))) ?>"><?= e(trim($c)) ?></option><?php endforeach; ?>
                </select>
              </div>
              <div>
                <label style="font-size:0.75rem;font-weight:600;color:var(--text-muted);margin-bottom:0.25rem;display:block;">Career Interest</label>
                <select id="talentCareerFilter" class="admin-filter-select" style="width:100%;">
                  <option value="">All Careers</option>
                  <option value="software-development">Software Development</option>
                  <option value="it-support">IT Support</option>
                  <option value="data-analysis">Data Analysis</option>
                  <option value="cybersecurity">Cybersecurity</option>
                  <option value="networking">Networking</option>
                </select>
              </div>
              <div>
                <label style="font-size:0.75rem;font-weight:600;color:var(--text-muted);margin-bottom:0.25rem;display:block;">Availability</label>
                <select id="talentAvailabilityFilter" class="admin-filter-select" style="width:100%;">
                  <option value="">All Statuses</option>
                  <option value="unemployed">Unemployed</option>
                  <option value="employed">Employed</option>
                  <option value="recent-graduate">Recent Graduate</option>
                </select>
              </div>
              <div>
                <label style="font-size:0.75rem;font-weight:600;color:var(--text-muted);margin-bottom:0.25rem;display:block;">Experience</label>
                <select id="talentExperienceFilter" class="admin-filter-select" style="width:100%;">
                  <option value="">Any Experience</option>
                  <option value="none">No Experience</option>
                  <option value="1-2">1-2 Years</option>
                  <option value="3+">3+ Years</option>
                </select>
              </div>
            </div>
            <div class="admin-talent-filters__actions" style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:center;">
              <button class="btn btn--primary btn--sm" id="talentSearchBtn"><i class="fas fa-search"></i> Search Talent</button>
              <button class="btn btn--outline btn--sm" id="talentSaveSearchBtn"><i class="fas fa-bookmark"></i> Save Search</button>
              <button class="btn btn--outline btn--sm" id="talentExportBtn"><i class="fas fa-download"></i> Export</button>
              <span class="admin-talent-filters__count" id="talentResultCount" style="margin-left:auto;font-size:0.8rem;color:var(--text-muted);">Showing <strong><?= count($initialCandidates) ?></strong> candidate<?= count($initialCandidates) !== 1 ? 's' : '' ?></span>
            </div>
          </div>

          <!-- Talent Results Container -->
          <div class="admin-talent-results" id="talentResultsContainer" style="display:grid;grid-template-columns:repeat(2,1fr);gap:1.25rem;">
            <?php if (!empty($initialCandidates)): ?>
              <?php foreach ($initialCandidates as $cand): ?>
                <?php
                  $cId = (int) $cand['id'];
                  $cName = e($cand['first_name'] . ' ' . $cand['last_name']);
                  $cTitle = e($cand['professional_title'] ?? 'Candidate');
                  $cEmail = e($cand['email']);
                  $cCompletion = (int) ($cand['completion_percent'] ?? 0);
                  $cCity = e(strtolower(str_replace(' ', '-', $cand['city'] ?? '')));
                  $cInitials = strtoupper(substr($cand['first_name'] ?? '', 0, 1) . substr($cand['last_name'] ?? '', 0, 1));
                  $completionColor = $cCompletion >= 80 ? 'var(--success)' : ($cCompletion >= 50 ? 'var(--warning)' : 'var(--danger)');
                ?>
                <div class="admin-programme-card" style="flex-direction:column;align-items:stretch;gap:0.75rem;" data-city="<?= $cCity ?>" data-title="<?= e(strtolower($cTitle)) ?>" data-name="<?= e(strtolower($cName)) ?>">
                  <div style="display:flex;align-items:center;gap:0.875rem;">
                    <div style="width:48px;height:48px;border-radius:50%;background:var(--primary-bg);color:var(--primary);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1rem;"><?= $cInitials ?></div>
                    <div style="flex:1;min-width:0;">
                      <div style="font-weight:700;color:var(--dark);"><?= $cName ?></div>
                      <div style="font-size:0.8rem;color:var(--text-light);"><?= $cTitle ?></div>
                      <div style="font-size:0.75rem;color:var(--text-lighter);"><?= $cEmail ?></div>
                    </div>
                  </div>
                  <div style="display:flex;align-items:center;gap:0.5rem;font-size:0.78rem;color:var(--text-light);">
                    <i class="fas fa-map-marker-alt" style="color:var(--primary);font-size:0.7rem;"></i>
                    <span><?= e($cand['city'] ?? 'Not specified') ?></span>
                  </div>
                  <div style="display:flex;align-items:center;gap:0.5rem;margin-top:auto;">
                    <div style="flex:1;height:6px;background:var(--bg);border-radius:3px;overflow:hidden;">
                      <div style="width:<?= $cCompletion ?>%;height:100%;background:<?= $completionColor ?>;border-radius:3px;"></div>
                    </div>
                    <span style="font-size:0.75rem;font-weight:600;color:<?= $completionColor ?>;"><?= $cCompletion ?>%</span>
                  </div>
                  <a class="btn btn--primary btn--sm" href="<?= url('admin/candidate_profile.php?user_id=' . $cId) ?>" style="width:100%;justify-content:center;"><i class="fas fa-user"></i> View Profile</a>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="admin-empty-state" style="grid-column:1/-1;">
                <div class="admin-empty-state__icon"><i class="fas fa-users"></i></div>
                <h3>No candidates yet</h3>
                <p>Candidates will appear here once they register on the platform.</p>
              </div>
            <?php endif; ?>
          </div>
        </section>


      </div><!-- // dash-content -->

      <!-- ===== DASHBOARD FOOTER ===== -->
      <footer class="dash-footer admin-footer">
        <div class="container">
          <div class="dash-footer__inner">
            <p>&copy; 2025 Investhood IT. All rights reserved.</p>
            <div class="dash-footer__links">
              <a href="#">Privacy Policy</a>
              <a href="#">Terms & Conditions</a>
              <a href="<?= url('index.php') ?>">Back to Home</a>
              <a href="<?= url('candidate/dashboard.php') ?>">Candidate Dashboard</a>
            </div>
          </div>
        </div>
      </footer>

    </main>
  </div>

  <!-- ===== SKELETON LOADER TEMPLATES ===== -->
  <template id="skeletonExecCard">
    <div class="admin-exec-card skeleton">
      <div class="admin-exec-card__header">
        <div class="skeleton skeleton--icon"></div>
        <div class="skeleton skeleton--badge"></div>
      </div>
      <div class="skeleton skeleton--h2" style="width:60%;"></div>
      <div class="skeleton skeleton--text" style="width:40%;"></div>
      <div class="skeleton skeleton--text" style="width:70%;"></div>
    </div>
  </template>

  <!-- =============================================
       SIGN OUT CONFIRMATION MODAL
       ============================================= -->
  <div class="modal-overlay" id="logoutModal" role="dialog" aria-modal="true" aria-labelledby="logoutModalTitle" aria-hidden="true">
    <div class="modal">
      <div class="modal__icon modal__icon--info">
        <i class="fas fa-sign-out-alt"></i>
      </div>
      <h3 id="logoutModalTitle">Sign Out?</h3>
      <p>Are you sure you want to sign out of your account? You will need to sign in again to access your dashboard.</p>
      <div style="display:flex;gap:0.75rem;justify-content:center;flex-wrap:wrap;">
        <button type="button" class="btn btn--ghost" id="logoutCancel"><i class="fas fa-times"></i> Cancel</button>
        <a href="<?= url('auth/logout.php') ?>" class="btn btn--primary" id="logoutConfirm"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
<script src="<?= url('js/script.js') ?>"></script>
  <script src="<?= url('js/admin_dashboard.js') ?>"></script>
  <script src="<?= url('js/admin_programmes.js') ?>"></script>
  <?= $flashes ?>
</body>
</html>