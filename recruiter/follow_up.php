<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('recruiter');
$user = current_user();
$flashes = render_flashes();
$currentPage = 'follow_up';
$pageTitle = 'Candidate Follow-Up & Pipeline';
require_once __DIR__ . '/_helpers.php';
$conn = Database::getConnection();

function rc_pipeline_stage(string $status): string {
    return match ($status) {
        'submitted' => 'Awaiting screening',
        'under_review' => 'Screening / review',
        'shortlisted' => 'Shortlisted',
        'assessment' => 'Assessment',
        'interview_scheduled' => 'Interview scheduled',
        'interview_completed' => 'Awaiting interview feedback',
        'selected' => 'Final decision: selected',
        'offer_sent' => 'Offer sent',
        'offer_accepted' => 'Offer accepted',
        'offer_declined' => 'Offer declined',
        'rejected' => 'Decision: rejected',
        'withdrawn' => 'Withdrawn',
        'on_hold' => 'On hold',
        default => rc_label($status),
    };
}

$filter = trim((string)($_GET['stage'] ?? 'all'));
$allowed = ['all','submitted','under_review','shortlisted','assessment','interview_scheduled','interview_completed','selected','offer_sent','offer_accepted','offer_declined','rejected','withdrawn','on_hold','overdue'];
if (!in_array($filter, $allowed, true)) $filter = 'all';
$search = trim((string)($_GET['q'] ?? ''));
$rows = [];
$counts = array_fill_keys($allowed, 0);
$hasApplications = rc_has_table($conn, 'applications');
if ($hasApplications) {
    $sql = "SELECT a.id AS application_id, a.application_reference, a.candidate_id, a.opportunity_id, a.status, a.created_at, a.updated_at, a.submitted_at, u.first_name, u.last_name, u.email, o.title AS opportunity_title,
            (SELECT COUNT(*) FROM interviews i WHERE i.application_id=a.id AND i.status IN ('scheduled','confirmed','rescheduled')) AS upcoming_interviews,
            (SELECT COUNT(*) FROM interviews i WHERE i.application_id=a.id AND i.status='completed') AS completed_interviews,
            (SELECT COUNT(*) FROM interviews i LEFT JOIN interview_feedback f ON f.interview_id=i.id WHERE i.application_id=a.id AND i.status='completed' AND f.id IS NULL) AS missing_feedback
            FROM applications a
            LEFT JOIN users u ON u.id=a.candidate_id
            LEFT JOIN opportunities o ON o.id=a.opportunity_id
            WHERE a.status <> 'draft'";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->execute(); $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $status = (string)$row['status'];
            $lastActivity = strtotime((string)($row['updated_at'] ?? $row['submitted_at'] ?? $row['created_at'] ?? 'now')) ?: time();
            $ageDays = max(0, (int)floor((time() - $lastActivity) / 86400));
            // No dedicated follow-up due-date field exists in the current application schema.
            // Flag non-terminal applications with no update for 7+ days as a review prompt.
            $terminal = in_array($status, ['offer_accepted','offer_declined','rejected','withdrawn'], true);
            $row['age_days'] = $ageDays;
            $row['is_overdue'] = !$terminal && $ageDays >= 7;
            $row['stage_label'] = rc_pipeline_stage($status);
            if (isset($counts[$status])) $counts[$status]++;
            if ($row['is_overdue']) $counts['overdue']++;
            if ($search !== '') {
                $haystack = strtolower(implode(' ', [(string)($row['first_name']??''),(string)($row['last_name']??''),(string)($row['email']??''),(string)($row['application_reference']??''),(string)($row['opportunity_title']??'')]));
                if (!str_contains($haystack, strtolower($search))) continue;
            }
            if ($filter === 'overdue' && !$row['is_overdue']) continue;
            if ($filter !== 'all' && $filter !== 'overdue' && $status !== $filter) continue;
            $rows[] = $row;
        }
        $stmt->close();
    }
}
usort($rows, static function($a,$b) { return ((int)$b['is_overdue'] <=> (int)$a['is_overdue']) ?: ((int)$b['age_days'] <=> (int)$a['age_days']); });
require __DIR__ . '/_layout_start.php';
?>
<link rel="stylesheet" href="<?= url('css/recruiter_pipeline.css') ?>?v=20261015">
<section class="rp-hero"><div><span class="rc-eyebrow">Recruitment operations</span><h2>Candidate Follow-Up &amp; Recruitment Pipeline</h2><p>Track submitted applications through screening, shortlisting, interviews, feedback and final decisions. Records below come from the existing application workflow.</p></div><a class="rp-secondary" href="<?= url('recruiter/dashboard.php') ?>"><i class="fas fa-arrow-left"></i> Back to dashboard</a></section>
<?php if (!$hasApplications): ?><div class="rp-alert"><strong>Applications table not found.</strong> The pipeline needs the existing <code>applications</code> table. Apply the project's application migration before using this page.</div><?php else: ?>
<section class="rp-metrics" aria-label="Pipeline summary">
<a class="rp-metric" href="?stage=all"><span>Active pipeline records</span><strong><?= number_format(array_sum(array_intersect_key($counts, array_flip(['submitted','under_review','shortlisted','assessment','interview_scheduled','interview_completed','selected','offer_sent','on_hold'])))) ?></strong><small>All active stages</small></a>
<a class="rp-metric rp-metric--warning" href="?stage=overdue"><span>Needs follow-up</span><strong><?= number_format($counts['overdue']) ?></strong><small>No update for 7+ days*</small></a>
<a class="rp-metric" href="?stage=submitted"><span>Awaiting screening</span><strong><?= number_format($counts['submitted']) ?></strong><small>Submitted applications</small></a>
<a class="rp-metric" href="?stage=interview_completed"><span>Awaiting feedback</span><strong><?= number_format($counts['interview_completed']) ?></strong><small>Interview completed stage</small></a>
</section>
<section class="rp-panel"><form class="rp-filters" method="get" action="<?= url('recruiter/follow_up.php') ?>"><label>Search candidates or applications<input type="search" name="q" value="<?= e($search) ?>" placeholder="Name, email, reference, opportunity"></label><label>Recruitment stage<select name="stage"><option value="all" <?= $filter==='all'?'selected':'' ?>>All stages</option><option value="overdue" <?= $filter==='overdue'?'selected':'' ?>>Needs follow-up</option><?php foreach (['submitted','under_review','shortlisted','assessment','interview_scheduled','interview_completed','selected','offer_sent','offer_accepted','offer_declined','rejected','withdrawn','on_hold'] as $stage): ?><option value="<?= e($stage) ?>" <?= $filter===$stage?'selected':'' ?>><?= e(rc_pipeline_stage($stage)) ?></option><?php endforeach; ?></select></label><button class="rp-primary" type="submit"><i class="fas fa-filter"></i> Apply filters</button><a class="rp-secondary" href="<?= url('recruiter/follow_up.php') ?>">Reset</a></form>
<div class="rp-table-wrap"><table class="rp-table"><thead><tr><th>Candidate / reference</th><th>Opportunity</th><th>Current stage</th><th>Last updated</th><th>Follow-up</th><th>Actions</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="6"><div class="rp-empty"><i class="fas fa-inbox"></i><strong>No applications match this view</strong><span>Try another stage or search term.</span></div></td></tr><?php else: foreach ($rows as $row): ?><tr><td><strong><?= e(trim((string)($row['first_name']??'').' '.(string)($row['last_name']??'')) ?: 'Candidate') ?></strong><small><?= e((string)($row['application_reference']??('Application #'.$row['application_id']))) ?></small><small><?= e((string)($row['email']??'')) ?></small></td><td><?= e((string)($row['opportunity_title']??'Opportunity unavailable')) ?></td><td><span class="rp-stage rp-stage--<?= e(preg_replace('/[^a-z0-9_-]/','', (string)$row['status'])) ?>"><?= e((string)$row['stage_label']) ?></span><?php if ((int)$row['missing_feedback'] > 0): ?><small class="rp-feedback-note"><i class="fas fa-message"></i> Feedback missing</small><?php endif; ?></td><td><?= e(rc_datetime((string)($row['updated_at']??$row['submitted_at']??$row['created_at']??''))) ?></td><td><?php if ($row['is_overdue']): ?><span class="rp-overdue"><i class="fas fa-clock"></i> Review needed</span><small><?= (int)$row['age_days'] ?> days since update</small><?php else: ?><span class="rp-current">Up to date</span><small><?= (int)$row['age_days'] ?> days since update</small><?php endif; ?></td><td><div class="rp-actions"><a href="<?= url('recruiter/candidate_view.php?id='.(int)$row['candidate_id']) ?>">View candidate</a><a href="<?= url('recruiter/shortlists.php') ?>">Shortlists</a></div></td></tr><?php endforeach; endif; ?>
</tbody></table></div><p class="rp-footnote">* Follow-up is a review prompt based on application <code>updated_at</code> (7 days without an update), not a configured contractual deadline. Closed outcomes are excluded from overdue counts. Use the existing candidate/application pages for updates; this view does not create duplicate records or change application status.</p></section>
<?php endif; ?>
<?php require __DIR__ . '/_layout_end.php'; ?>
