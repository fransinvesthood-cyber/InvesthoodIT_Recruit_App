<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('programme_officer');
require_once __DIR__ . '/_helpers.php';

$user = current_user();
$flashes = render_flashes();
$currentPage = 'deliverables';
$pageTitle = 'Deliverable Details';
$conn = Database::getConnection();
$officerId = (int) ($user['id'] ?? $user['user_id'] ?? 0);
$deliverableIdValue = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$deliverableId = $deliverableIdValue === false ? 0 : (int) $deliverableIdValue;
if ($officerId <= 0 || $deliverableId <= 0) {
    http_response_code(400);
    exit('Invalid request.');
}

$tableResult = $conn->query("SHOW TABLES LIKE 'programme_deliverables'");
if (!$tableResult) {
    throw new RuntimeException('Unable to check deliverables schema: ' . $conn->error);
}
$submissionResult = $conn->query("SHOW TABLES LIKE 'deliverable_submissions'");
if (!$submissionResult) {
    throw new RuntimeException('Unable to check deliverable submissions schema: ' . $conn->error);
}
if ($tableResult->num_rows === 0 || $submissionResult->num_rows === 0) {
    http_response_code(503);
    exit('The deliverables module is not installed.');
}
$milestoneResult = $conn->query("SHOW TABLES LIKE 'programme_milestones'");
if (!$milestoneResult) {
    throw new RuntimeException('Unable to check milestone schema: ' . $conn->error);
}
$milestonesReady = $milestoneResult->num_rows > 0;

$scope = po_scope($conn, 'p', 'c');
if (po_scope_mode($scope) === 'none') {
    http_response_code(403);
    exit('Access scope unavailable.');
}
$scopeCondition = po_scope_condition($scope);

$stmt = $conn->prepare("
    SELECT d.*, p.name AS programme_name, c.name AS cohort_name
    FROM programme_deliverables d
    INNER JOIN programmes p ON p.id = d.programme_id
    LEFT JOIN cohorts c ON c.id = d.cohort_id
    WHERE d.id = ? AND {$scopeCondition}
    LIMIT 1
");
if (!$stmt) {
    throw new RuntimeException('Unable to prepare deliverable query: ' . $conn->error);
}
$stmt->bind_param('ii', $deliverableId, $officerId);
$stmt->execute();
$deliverable = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$deliverable) {
    http_response_code(404);
    exit('Deliverable not found or access denied.');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submissionIdValue = filter_var($_POST['submission_id'] ?? null, FILTER_VALIDATE_INT);
    $decisionValue = $_POST['decision'] ?? '';
    $commentValue = $_POST['comment'] ?? '';
    $submissionId = $submissionIdValue === false ? 0 : (int) $submissionIdValue;
    $decision = is_string($decisionValue) ? $decisionValue : '';
    $comment = is_string($commentValue) ? trim($commentValue) : '';
    if (!validate_csrf()) {
        $errors[] = 'Your session validation failed. Refresh the page and try again.';
    }
    if (!is_string($decisionValue) || !is_string($commentValue)) {
        $errors[] = 'Review details must be plain text.';
    }
    if ($submissionId <= 0 || !in_array($decision, ['verified', 'rejected'], true)) {
        $errors[] = 'Choose a valid submission review action.';
    }
    if (mb_strlen($comment) > 5000) {
        $errors[] = 'The review comment must be 5,000 characters or fewer.';
    }
    if ($decision === 'rejected' && $comment === '') {
        $errors[] = 'A comment is required when rejecting a submission.';
    }

    if (!$errors) {
        $conn->begin_transaction();
        $stmt = $conn->prepare("
            SELECT candidate_id
            FROM deliverable_submissions
            WHERE id = ? AND deliverable_id = ? AND status = 'submitted'
            FOR UPDATE
        ");
        if (!$stmt) {
            $conn->rollback();
            throw new RuntimeException('Unable to lock submission for review: ' . $conn->error);
        }
        $stmt->bind_param('ii', $submissionId, $deliverableId);
        $stmt->execute();
        $submission = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$submission) {
            $conn->rollback();
            $errors[] = 'Submission not found or already reviewed.';
        } else {
            $reviewerComment = $comment !== '' ? $comment : null;
            $stmt = $conn->prepare("
                UPDATE deliverable_submissions
                SET status = ?, verified_by = ?, verified_at = NOW(), verification_comment = ?
                WHERE id = ? AND deliverable_id = ? AND status = 'submitted'
            ");
            if (!$stmt) {
                $conn->rollback();
                throw new RuntimeException('Unable to prepare submission review: ' . $conn->error);
            }
            $stmt->bind_param('sisii', $decision, $officerId, $reviewerComment, $submissionId, $deliverableId);
            $updated = $stmt->execute();
            $changed = $stmt->affected_rows > 0;
            $stmt->close();
            if (!$updated || !$changed) {
                $conn->rollback();
                $errors[] = 'Submission review could not be saved.';
            } else {
                $expectedSql = "
                    SELECT COUNT(DISTINCT cp.user_id) AS total
                    FROM cohort_participants cp
                    INNER JOIN cohorts c2 ON c2.id = cp.cohort_id
                    WHERE c2.programme_id = ?
                      AND (? IS NULL OR c2.id = ?)
                      AND cp.status <> 'withdrawn'
                ";
                $stmt = $conn->prepare($expectedSql);
                if (!$stmt) {
                    $conn->rollback();
                    throw new RuntimeException('Unable to count expected submissions: ' . $conn->error);
                }
                $cohortId = $deliverable['cohort_id'] !== null ? (int) $deliverable['cohort_id'] : null;
                $stmt->bind_param('iii', $deliverable['programme_id'], $cohortId, $cohortId);
                $stmt->execute();
                $expected = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
                $stmt->close();

                $stmt = $conn->prepare("
                    SELECT COUNT(DISTINCT candidate_id) AS total
                    FROM deliverable_submissions
                    WHERE deliverable_id = ? AND status = 'verified'
                ");
                if (!$stmt) {
                    $conn->rollback();
                    throw new RuntimeException('Unable to count verified submissions: ' . $conn->error);
                }
                $stmt->bind_param('i', $deliverableId);
                $stmt->execute();
                $verified = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
                $stmt->close();
                $fullyVerified = $expected > 0 && $verified >= $expected;

                if ($fullyVerified) {
                    $stmt = $conn->prepare("UPDATE programme_deliverables SET status = 'closed' WHERE id = ?");
                    if (!$stmt) {
                        $conn->rollback();
                        throw new RuntimeException('Unable to close completed deliverable: ' . $conn->error);
                    }
                    $stmt->bind_param('i', $deliverableId);
                    $stmt->execute();
                    $stmt->close();
                }

                if ($milestonesReady && !empty($deliverable['milestone_id'])) {
                    $stmt = $conn->prepare("
                        SELECT COUNT(*) AS total,
                               COALESCE(SUM(status = 'closed'), 0) AS completed
                        FROM programme_deliverables
                        WHERE milestone_id = ?
                    ");
                    if (!$stmt) {
                        $conn->rollback();
                        throw new RuntimeException('Unable to update milestone progress: ' . $conn->error);
                    }
                    $stmt->bind_param('i', $deliverable['milestone_id']);
                    $stmt->execute();
                    $milestoneCounts = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    $milestoneTotal = (int) ($milestoneCounts['total'] ?? 0);
                    $milestoneCompleted = (int) ($milestoneCounts['completed'] ?? 0);
                    if ($milestoneTotal > 0) {
                        $milestoneProgress = (int) round(($milestoneCompleted / $milestoneTotal) * 100);
                        $milestoneStatus = $milestoneProgress === 100 ? 'completed' : 'in_progress';
                        $stmt = $conn->prepare("
                            UPDATE programme_milestones
                            SET progress = ?, status = ?
                            WHERE id = ? AND status <> 'on_hold'
                        ");
                        if (!$stmt) {
                            $conn->rollback();
                            throw new RuntimeException('Unable to save milestone progress: ' . $conn->error);
                        }
                        $stmt->bind_param('isi', $milestoneProgress, $milestoneStatus, $deliverable['milestone_id']);
                        $stmt->execute();
                        $stmt->close();
                    }
                }

                $conn->commit();
                $candidateId = (int) $submission['candidate_id'];
                $message = $decision === 'verified'
                    ? 'Your submission for "' . $deliverable['title'] . '" has been verified.'
                    : 'Your submission for "' . $deliverable['title'] . '" needs changes. See the reviewer feedback.';
                CandidateNotification::create(
                    $candidateId,
                    'deliverables',
                    $decision === 'verified' ? 'Deliverable verified' : 'Deliverable needs changes',
                    $message,
                    'deliverables',
                    $deliverableId,
                    'deliverable-review-' . $submissionId . '-' . $decision
                );

                if (po_activity_table_exists($conn)) {
                    $action = $decision === 'verified' ? 'deliverable_submission_verified' : 'deliverable_submission_rejected';
                    $description = substr(
                        $decision === 'verified' ? 'Verified submission for ' : 'Rejected submission for ',
                        0,
                        500
                    ) . substr((string) $deliverable['title'], 0, 400);
                    $ipAddress = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
                    $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
                    $stmt = $conn->prepare("
                        INSERT INTO programme_officer_activity_log
                            (programme_officer_id, action, description, entity_type, entity_id, ip_address, user_agent)
                        VALUES (?, ?, ?, 'deliverable_submission', ?, ?, ?)
                    ");
                    if (!$stmt) {
                        error_log('[Deliverables] Unable to prepare Programme Officer activity log: ' . $conn->error);
                    } else {
                        $stmt->bind_param('ississ', $officerId, $action, $description, $submissionId, $ipAddress, $userAgent);
                        if (!$stmt->execute()) {
                            error_log('[Deliverables] Unable to record Programme Officer activity: ' . $stmt->error);
                        }
                        $stmt->close();
                    }
                }

                set_flash(
                    'success',
                    $decision === 'verified' ? 'Submission verified' : 'Submission rejected',
                    $fullyVerified ? 'The deliverable is fully verified and its linked milestone was updated.' : 'The review has been recorded.'
                );
                safe_redirect('programme_officer/deliverable_view.php?id=' . $deliverableId);
            }
        }
    }
}

$expectedQuery = $conn->prepare("
    SELECT DISTINCT u.id, u.first_name, u.last_name,
           s.status AS latest_status
    FROM cohort_participants cp
    INNER JOIN users u ON u.id = cp.user_id
    INNER JOIN cohorts c2 ON c2.id = cp.cohort_id
    LEFT JOIN deliverable_submissions s
      ON s.id = (
          SELECT MAX(s2.id)
          FROM deliverable_submissions s2
          WHERE s2.deliverable_id = ? AND s2.candidate_id = u.id
      )
    WHERE c2.programme_id = ?
      AND (? IS NULL OR c2.id = ?)
      AND cp.status <> 'withdrawn'
    ORDER BY u.first_name, u.last_name
");
if (!$expectedQuery) {
    throw new RuntimeException('Unable to load expected candidates: ' . $conn->error);
}
$cohortId = $deliverable['cohort_id'] !== null ? (int) $deliverable['cohort_id'] : null;
$expectedQuery->bind_param('iiii', $deliverableId, $deliverable['programme_id'], $cohortId, $cohortId);
$expectedQuery->execute();
$participants = $expectedQuery->get_result()->fetch_all(MYSQLI_ASSOC);
$expectedQuery->close();

$stmt = $conn->prepare("
    SELECT s.*, u.first_name, u.last_name, u.email
    FROM deliverable_submissions s
    INNER JOIN users u ON u.id = s.candidate_id
    WHERE s.deliverable_id = ?
    ORDER BY FIELD(s.status, 'submitted', 'rejected', 'verified'), s.submitted_at DESC, s.id DESC
");
if (!$stmt) {
    throw new RuntimeException('Unable to load submissions: ' . $conn->error);
}
$stmt->bind_param('i', $deliverableId);
$stmt->execute();
$submissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$verifiedCount = 0;
foreach ($participants as $participant) {
    if ($participant['latest_status'] === 'verified') {
        $verifiedCount++;
    }
}
$expectedCount = count($participants);
$isOverdue = $deliverable['status'] === 'open' && $deliverable['due_date'] < date('Y-m-d') && $verifiedCount < $expectedCount;
$milestone = null;
if ($milestonesReady && !empty($deliverable['milestone_id'])) {
    $stmt = $conn->prepare("SELECT title, status, progress FROM programme_milestones WHERE id = ?");
    if (!$stmt) {
        throw new RuntimeException('Unable to load linked milestone: ' . $conn->error);
    }
    $stmt->bind_param('i', $deliverable['milestone_id']);
    $stmt->execute();
    $milestone = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
}

require __DIR__ . '/_layout_start.php';
?>
<style>
.po-card form input[name="comment"] {
    background: var(--po-soft);
    color: var(--po-text);
    border: 1px solid var(--po-border);
    border-radius: 10px;
    padding: 10px 12px;
    min-width: 240px;
}
</style>

<div class="po-breadcrumb"><a href="<?= url('programme_officer/deliverables.php') ?>">Deliverables</a><i class="fas fa-chevron-right"></i><span><?= e($deliverable['title']) ?></span></div>

<section class="po-detail-hero"><div>
    <span class="po-detail-hero__eyebrow"><?= e($deliverable['programme_name']) ?> · <?= e($deliverable['cohort_name'] ?? 'All cohorts') ?></span>
    <h2><?= e($deliverable['title']) ?></h2>
    <div class="po-detail-hero__meta">
        <span><i class="fas fa-calendar"></i> Due <?= e(po_date($deliverable['due_date'])) ?></span>
        <?php if ($isOverdue): ?><span class="po-status po-status--danger">Overdue</span><?php endif; ?>
        <span class="po-status <?= $deliverable['status'] === 'closed' ? 'po-status--active' : 'po-status--info' ?>"><?= e(ucfirst($deliverable['status'])) ?></span>
    </div>
    <?php if (!empty($deliverable['description'])): ?><p><?= nl2br(e($deliverable['description'])) ?></p><?php endif; ?>
    <?php if ($milestone): ?><p><strong>Milestone:</strong> <?= e($milestone['title']) ?> — <?= e(ucwords(str_replace('_', ' ', $milestone['status']))) ?> (<?= (int) $milestone['progress'] ?>%)</p><?php endif; ?>
</div></section>

<?= $flashes ?>
<?php foreach ($errors as $error): ?><div class="po-alert"><i class="fas fa-circle-exclamation"></i><div><strong><?= e($error) ?></strong></div></div><?php endforeach; ?>

<section class="po-stats po-stats--4">
    <article class="po-stat"><span class="po-stat__icon po-stat__icon--blue"><i class="fas fa-users"></i></span><strong><?= number_format($expectedCount) ?></strong><span>Expected candidates</span></article>
    <article class="po-stat"><span class="po-stat__icon po-stat__icon--green"><i class="fas fa-circle-check"></i></span><strong><?= number_format($verifiedCount) ?></strong><span>Verified</span></article>
    <article class="po-stat"><span class="po-stat__icon po-stat__icon--orange"><i class="fas fa-inbox"></i></span><strong><?= number_format(count(array_filter($participants, static fn(array $p): bool => $p['latest_status'] === 'submitted'))) ?></strong><span>Awaiting review</span></article>
    <article class="po-stat"><span class="po-stat__icon po-stat__icon--purple"><i class="fas fa-clock"></i></span><strong><?= number_format(max(0, $expectedCount - $verifiedCount)) ?></strong><span>Outstanding</span></article>
</section>

<div class="po-grid po-grid--2">
    <section class="po-card">
        <div class="po-card__header"><div><h3>Candidate progress</h3><p>Latest submission status for each active cohort participant.</p></div></div>
        <?php if (!$participants): ?>
            <div class="po-empty"><div class="po-empty__icon"><i class="fas fa-users"></i></div><strong>No eligible candidates</strong><span>There are no non-withdrawn candidates in this deliverable’s scope.</span></div>
        <?php else: ?>
            <div class="po-activity-list">
                <?php foreach ($participants as $participant):
                    $participantName = trim($participant['first_name'] . ' ' . $participant['last_name']);
                    $participantStatus = $participant['latest_status'] ?: 'outstanding';
                    $statusClass = $participantStatus === 'verified' ? 'active' : ($participantStatus === 'rejected' ? 'danger' : ($participantStatus === 'submitted' ? 'warning' : 'neutral')); ?>
                    <article class="po-activity">
                        <div class="po-activity__icon"><i class="fas fa-user"></i></div>
                        <div class="po-activity__body"><div class="po-activity__title"><strong><?= e($participantName !== '' ? $participantName : 'Candidate') ?></strong><span class="po-status po-status--<?= e($statusClass) ?>"><?= e(ucfirst($participantStatus)) ?></span></div><p><?= $participantStatus === 'outstanding' ? 'No submission received.' : 'Latest submission status.' ?></p></div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="po-card">
        <div class="po-card__header"><div><h3>Submissions</h3><p>Review candidate outputs, download files and verify or reject them.</p></div></div>
        <?php if (!$submissions): ?>
            <div class="po-empty"><div class="po-empty__icon"><i class="fas fa-inbox"></i></div><strong>No submissions yet</strong><span>Submitted outputs will appear here.</span></div>
        <?php else: ?>
            <div class="po-activity-list">
                <?php foreach ($submissions as $submission):
                    $name = trim(($submission['first_name'] ?? '') . ' ' . ($submission['last_name'] ?? '')); ?>
                    <article class="po-activity" style="align-items:flex-start;flex-wrap:wrap">
                        <div class="po-activity__icon"><i class="fas fa-file-arrow-up"></i></div>
                        <div class="po-activity__body">
                            <div class="po-activity__title"><strong><?= e($name !== '' ? $name : 'Candidate') ?> · <?= e(ucfirst($submission['status'])) ?></strong><span><?= e(po_datetime($submission['submitted_at'])) ?></span></div>
                            <?php if (!empty($submission['notes'])): ?><p><?= nl2br(e($submission['notes'])) ?></p><?php endif; ?>
                            <?php if (!empty($submission['reference_url']) && preg_match('#^https?://#i', $submission['reference_url'])): ?><p><a href="<?= e($submission['reference_url']) ?>" target="_blank" rel="noopener noreferrer">Open reference link</a></p><?php endif; ?>
                            <?php if (!empty($submission['original_name'])): ?><p><a href="<?= url('programme_officer/deliverable_download.php?id=' . (int) $submission['id']) ?>"><i class="fas fa-download"></i> <?= e($submission['original_name']) ?></a></p><?php endif; ?>
                            <?php if (!empty($submission['verification_comment'])): ?><p><strong>Reviewer feedback:</strong> <?= nl2br(e($submission['verification_comment'])) ?></p><?php endif; ?>
                            <?php if ($submission['status'] === 'submitted'): ?>
                                <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="submission_id" value="<?= (int) $submission['id'] ?>">
                                    <input name="comment" maxlength="5000" placeholder="Comment (required to reject)" aria-label="Review comment">
                                    <button class="po-btn po-btn--primary" type="submit" name="decision" value="verified">Verify</button>
                                    <button class="po-btn po-btn--secondary" type="submit" name="decision" value="rejected">Reject</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/_layout_end.php'; ?>
