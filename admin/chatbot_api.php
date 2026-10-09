<?php
/**
 * Investhood IT - Admin Assistant API
 * Role: Administrator
 *
 * Local, database-backed assistant. It does not require an external AI key.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('admin');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function admin_chat_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function admin_chat_int($value): int
{
    return (int)($value ?? 0);
}

function admin_chat_actions(array $items): array
{
    $actions = [];
    foreach ($items as $item) {
        if (!is_array($item) || empty($item['url']) || empty($item['label'])) {
            continue;
        }
        $actions[] = [
            'label' => (string)$item['label'],
            'url'   => (string)$item['url'],
        ];
    }
    return $actions;
}

function admin_chat_stats(): array
{
    $stats = [
        'candidates' => 0,
        'new_candidates' => 0,
        'programmes' => 0,
        'active_programmes' => 0,
        'cohorts' => 0,
        'active_cohorts' => 0,
        'participants' => 0,
        'opportunities' => 0,
        'published_opportunities' => 0,
        'applications' => 0,
        'placements' => 0,
        'active_placements' => 0,
        'placed' => 0,
        'interviews' => 0,
        'upcoming_interviews' => 0,
        'interviews_this_month' => 0,
        'selected' => 0,
        'waitlisted' => 0,
        'pending_offers' => 0,
        'offers_issued' => 0,
        'offers_accepted' => 0,
        'avg_profile' => 0,
        'complete_profiles' => 0,
    ];

    $stats['candidates'] = admin_chat_int(Database::fetchOne(
        "SELECT COUNT(*) AS cnt
         FROM users u INNER JOIN roles r ON r.id=u.role_id
         WHERE r.slug='candidate' AND u.status='active'"
    )['cnt'] ?? 0);

    $stats['new_candidates'] = admin_chat_int(Database::fetchOne(
        "SELECT COUNT(*) AS cnt
         FROM users u INNER JOIN roles r ON r.id=u.role_id
         WHERE r.slug='candidate' AND u.status='active'
           AND u.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)"
    )['cnt'] ?? 0);

    $stats['programmes'] = admin_chat_int(Database::fetchOne(
        "SELECT COUNT(*) AS cnt FROM programmes"
    )['cnt'] ?? 0);
    $stats['active_programmes'] = admin_chat_int(Database::fetchOne(
        "SELECT COUNT(*) AS cnt FROM programmes WHERE status='active'"
    )['cnt'] ?? 0);

    $stats['cohorts'] = admin_chat_int(Database::fetchOne(
        "SELECT COUNT(*) AS cnt FROM cohorts"
    )['cnt'] ?? 0);
    $stats['active_cohorts'] = admin_chat_int(Database::fetchOne(
        "SELECT COUNT(*) AS cnt FROM cohorts WHERE status='active'"
    )['cnt'] ?? 0);
    $stats['participants'] = admin_chat_int(Database::fetchOne(
        "SELECT COUNT(*) AS cnt FROM cohort_participants
         WHERE status IN ('selected','onboarded','active')"
    )['cnt'] ?? 0);

    try {
        $stats['opportunities'] = admin_chat_int(Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM opportunities"
        )['cnt'] ?? 0);
        $stats['published_opportunities'] = admin_chat_int(Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM opportunities WHERE status='published'"
        )['cnt'] ?? 0);
    } catch (Throwable $e) {
        error_log('[ADMIN ASSISTANT] opportunities: '.$e->getMessage());
    }

    try {
        $stats['applications'] = admin_chat_int(Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM applications"
        )['cnt'] ?? 0);
    } catch (Throwable $e) {
        error_log('[ADMIN ASSISTANT] applications: '.$e->getMessage());
    }

    try {
        $row = Database::fetchOne(
            "SELECT COUNT(*) AS total,
                    SUM(status IN ('placed','active','placement_in_progress')) AS active_count,
                    SUM(status='placed') AS placed_count
             FROM placements"
        );
        $stats['placements'] = admin_chat_int($row['total'] ?? 0);
        $stats['active_placements'] = admin_chat_int($row['active_count'] ?? 0);
        $stats['placed'] = admin_chat_int($row['placed_count'] ?? 0);
    } catch (Throwable $e) {
        error_log('[ADMIN ASSISTANT] placements: '.$e->getMessage());
    }

    try {
        $status = Interview::countByStatus();
        $stats['interviews'] = array_sum(array_map('intval', $status));
        $stats['upcoming_interviews'] = admin_chat_int(Interview::countUpcoming());
        $stats['interviews_this_month'] = admin_chat_int(
            Interview::rangeSummary(date('Y-m-01'), date('Y-m-t'))['total'] ?? 0
        );
    } catch (Throwable $e) {
        error_log('[ADMIN ASSISTANT] interviews: '.$e->getMessage());
    }

    try {
        $selection = Selection::dashboardStats();
        $stats['selected'] = admin_chat_int($selection['selected'] ?? 0);
        $stats['waitlisted'] = admin_chat_int($selection['waitlisted'] ?? 0);
        $stats['pending_offers'] = admin_chat_int($selection['pending_offers'] ?? 0);
        $stats['offers_issued'] = admin_chat_int($selection['offers_issued'] ?? 0);
        $stats['offers_accepted'] = admin_chat_int($selection['offers_accepted'] ?? 0);
    } catch (Throwable $e) {
        error_log('[ADMIN ASSISTANT] selection: '.$e->getMessage());
    }

    try {
        $row = Database::fetchOne(
            "SELECT ROUND(AVG(completion_percent)) AS avg_completion,
                    COALESCE(SUM(completion_percent >= 80),0) AS complete_profiles
             FROM candidate_profiles"
        );
        $stats['avg_profile'] = admin_chat_int($row['avg_completion'] ?? 0);
        $stats['complete_profiles'] = admin_chat_int($row['complete_profiles'] ?? 0);
    } catch (Throwable $e) {
        error_log('[ADMIN ASSISTANT] profiles: '.$e->getMessage());
    }

    return $stats;
}

function admin_chat_response(string $message): array
{
    $q = strtolower(trim($message));
    $s = admin_chat_stats();

    if ($q === '') {
        return ['message' => 'Ask me about candidates, programmes, cohorts, applications, opportunities, interviews, placements, selection, offers, or talent intelligence.'];
    }

    if (preg_match('/executive|overview|dashboard|summary/', $q)) {
        return [
            'message' =>
                "Executive overview\n\n".
                "• Candidates: {$s['candidates']} ({$s['new_candidates']} new this month)\n".
                "• Programmes: {$s['programmes']} total, {$s['active_programmes']} active\n".
                "• Cohorts: {$s['cohorts']} total, {$s['active_cohorts']} active\n".
                "• Current participants: {$s['participants']}\n".
                "• Opportunities: {$s['opportunities']} total, {$s['published_opportunities']} published\n".
                "• Applications: {$s['applications']}\n".
                "• Placements: {$s['placements']} total, {$s['active_placements']} active, {$s['placed']} placed\n".
                "• Interviews this month: {$s['interviews_this_month']} ({$s['upcoming_interviews']} upcoming)\n".
                "• Selected candidates: {$s['selected']}; pending offers: {$s['pending_offers']}; accepted offers: {$s['offers_accepted']}\n".
                "• Average profile completeness: {$s['avg_profile']}%; complete profiles: {$s['complete_profiles']}",
            'actions' => admin_chat_actions([
                ['label'=>'Applications','url'=>url('admin/dashboard.php').'#admin-applications'],
                ['label'=>'Placements','url'=>url('admin/placements.php')],
                ['label'=>'Talent Intelligence','url'=>url('admin/dashboard.php').'#admin-talent-hub'],
            ])
        ];
    }

    if (preg_match('/candidate|talent pool|talent intelligence|skills|qualification|profile/', $q)) {
        return [
            'message' =>
                "Candidate & talent intelligence\n\n".
                "• Active candidates: {$s['candidates']}\n".
                "• New candidates this month: {$s['new_candidates']}\n".
                "• Average profile completeness: {$s['avg_profile']}%\n".
                "• Profiles at 80%+ completeness: {$s['complete_profiles']}\n\n".
                "The Talent Intelligence Hub on this dashboard contains live candidate distribution, skills, qualifications, experience, geography, availability and employment information.",
            'actions' => admin_chat_actions([
                ['label'=>'Open Talent Intelligence','url'=>url('admin/dashboard.php').'#admin-talent-hub'],
                ['label'=>'Talent Pools','url'=>url('admin/dashboard.php').'#admin-talent-pool'],
            ])
        ];
    }

    if (preg_match('/programme|cohort|participant|enrol/', $q)) {
        return [
            'message' =>
                "Programmes & cohorts\n\n".
                "• Programmes: {$s['programmes']} total\n".
                "• Active programmes: {$s['active_programmes']}\n".
                "• Cohorts: {$s['cohorts']} total\n".
                "• Active cohorts: {$s['active_cohorts']}\n".
                "• Current participants: {$s['participants']}\n\n".
                "Use the Programmes section to manage programme and cohort operations.",
            'actions' => admin_chat_actions([
                ['label'=>'Manage Programmes','url'=>url('admin/programmes.php')],
                ['label'=>'Executive Overview','url'=>url('admin/dashboard.php').'#admin-executive'],
            ])
        ];
    }

    if (preg_match('/application|pipeline|submitted|screen|assessment|waitlist|rejected/', $q)) {
        return [
            'message' =>
                "Application pipeline\n\n".
                "• Total applications: {$s['applications']}\n\n".
                "The dashboard pipeline breaks applications into submitted, review, assessment, interview, waitlisted, selected, rejected and withdrawn stages. Open Applications on the dashboard for the detailed stage breakdown.",
            'actions' => admin_chat_actions([
                ['label'=>'Applications','url'=>url('admin/dashboard.php').'#admin-applications'],
            ])
        ];
    }

    if (preg_match('/opportunit|vacanc|position|opening/', $q)) {
        return [
            'message' =>
                "Opportunities\n\n".
                "• Total opportunities: {$s['opportunities']}\n".
                "• Published opportunities: {$s['published_opportunities']}\n\n".
                "The dashboard also tracks positions, applications and recent opportunity activity.",
            'actions' => admin_chat_actions([
                ['label'=>'Manage Opportunities','url'=>url('admin/opportunities.php')],
                ['label'=>'Opportunities section','url'=>url('admin/dashboard.php').'#admin-opportunities'],
            ])
        ];
    }

    if (preg_match('/interview|schedule|upcoming/', $q)) {
        return [
            'message' =>
                "Interview operations\n\n".
                "• Total interviews: {$s['interviews']}\n".
                "• Upcoming interviews: {$s['upcoming_interviews']}\n".
                "• Interviews this month: {$s['interviews_this_month']}\n\n".
                "The Interviews section provides the live interview schedule, status information and search/filter controls.",
            'actions' => admin_chat_actions([
                ['label'=>'Open Interviews','url'=>url('admin/interviews.php')],
                ['label'=>'Interview section','url'=>url('admin/dashboard.php').'#admin-interviews'],
            ])
        ];
    }

    if (preg_match('/placement|placed/', $q)) {
        return [
            'message' =>
                "Placement operations\n\n".
                "• Total placements: {$s['placements']}\n".
                "• Active placements: {$s['active_placements']}\n".
                "• Successfully placed: {$s['placed']}\n\n".
                "Placement records are managed from the Placements area.",
            'actions' => admin_chat_actions([
                ['label'=>'Manage Placements','url'=>url('admin/placements.php')],
                ['label'=>'Placements section','url'=>url('admin/dashboard.php').'#admin-placements'],
            ])
        ];
    }

    if (preg_match('/selection|offer|selected|waitlisted|accepted|declined/', $q)) {
        return [
            'message' =>
                "Selection & offers\n\n".
                "• Selected candidates: {$s['selected']}\n".
                "• Waitlisted: {$s['waitlisted']}\n".
                "• Pending offers: {$s['pending_offers']}\n".
                "• Offers issued: {$s['offers_issued']}\n".
                "• Offers accepted: {$s['offers_accepted']}\n\n".
                "Open the Selection & Offers section for recent selection and offer activity.",
            'actions' => admin_chat_actions([
                ['label'=>'Selection & Offers','url'=>url('admin/dashboard.php').'#admin-selection'],
            ])
        ];
    }

    if (preg_match('/help|what can you|what do you|how can you/', $q)) {
        return [
            'message' =>
                "I can answer questions using the Admin dashboard's live database-backed metrics, including:\n\n".
                "• Executive platform overview\n".
                "• Candidates and talent intelligence\n".
                "• Programmes, cohorts and participants\n".
                "• Applications and pipeline\n".
                "• Opportunities\n".
                "• Interviews\n".
                "• Placements\n".
                "• Selection and offers\n\n".
                "Try: “Give me an executive overview” or “How many candidates are there?”"
        ];
    }

    return [
        'message' =>
            "I can help with the Admin portal's live data. Try asking about candidates, programmes, cohorts, applications, opportunities, interviews, placements, selection, offers, or talent intelligence."
    ];
}

try {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw ?: '{}', true);
    $message = is_array($payload) ? trim((string)($payload['message'] ?? '')) : '';

    if (mb_strlen($message) > 500) {
        admin_chat_json(['success'=>false,'message'=>'Please keep your question under 500 characters.'], 422);
    }

    $result = admin_chat_response($message);
    admin_chat_json([
        'success' => true,
        'message' => $result['message'] ?? '',
        'actions' => $result['actions'] ?? [],
    ]);
} catch (Throwable $e) {
    error_log('[ADMIN ASSISTANT API] '.$e->getMessage());
    admin_chat_json([
        'success' => false,
        'message' => 'The Admin Assistant could not read the platform data right now. Please try again.'
    ], 500);
}
