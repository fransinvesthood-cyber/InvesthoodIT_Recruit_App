<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('candidate');

$user = current_user();
$candidateId = (int) current_user_id();
if ($candidateId <= 0) {
    http_response_code(403);
    exit('Invalid candidate account.');
}

$conn = Database::getConnection();
$flashes = render_flashes();
$errors = [];
$cleanupUpload = static function (?string $storedFilename): void {
    if ($storedFilename !== null && !FileUploader::delete('documents', $storedFilename) && FileUploader::path('documents', $storedFilename)) {
        error_log('[Deliverables] Failed to remove unreferenced upload: ' . $storedFilename);
    }
};
$moduleResult = $conn->query("SHOW TABLES LIKE 'programme_deliverables'");
if (!$moduleResult) {
    throw new RuntimeException('Unable to check deliverables schema: ' . $conn->error);
}
$submissionResult = $conn->query("SHOW TABLES LIKE 'deliverable_submissions'");
if (!$submissionResult) {
    throw new RuntimeException('Unable to check deliverable submissions schema: ' . $conn->error);
}
$moduleReady = $moduleResult->num_rows > 0 && $submissionResult->num_rows > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $moduleReady) {
    $deliverableIdValue = filter_var($_POST['deliverable_id'] ?? null, FILTER_VALIDATE_INT);
    $deliverableId = $deliverableIdValue === false ? 0 : (int) $deliverableIdValue;
    $notesValue = $_POST['notes'] ?? '';
    $referenceValue = $_POST['reference_url'] ?? '';
    $notes = is_string($notesValue) ? trim($notesValue) : '';
    $referenceUrl = is_string($referenceValue) ? trim($referenceValue) : '';

    if (!validate_csrf()) {
        $errors[] = 'Your session validation failed. Refresh the page and try again.';
    }
    if (!is_string($notesValue) || !is_string($referenceValue)) {
        $errors[] = 'Submission details must be plain text.';
    }
    if ($deliverableId <= 0) {
        $errors[] = 'Choose a valid deliverable.';
    }
    if (mb_strlen($notes) > 5000) {
        $errors[] = 'Notes must be 5,000 characters or fewer.';
    }
    if ($referenceUrl !== '') {
        $urlParts = parse_url($referenceUrl);
        if (mb_strlen($referenceUrl) > 500) {
            $errors[] = 'Reference links must be 500 characters or fewer.';
        } elseif (
            !filter_var($referenceUrl, FILTER_VALIDATE_URL)
            || !is_array($urlParts)
            || !in_array(strtolower((string) ($urlParts['scheme'] ?? '')), ['http', 'https'], true)
        ) {
            $errors[] = 'Enter a valid HTTP or HTTPS reference link.';
        }
    }

    $submissionTransaction = false;
    if (!$errors) {
        $conn->begin_transaction();
        $submissionTransaction = true;
    }

    $deliverable = null;
    if ($deliverableId > 0) {
        $stmt = $conn->prepare("
            SELECT d.id, d.status, d.due_date
            FROM programme_deliverables d
            WHERE d.id = ? AND d.status = 'open'
              AND EXISTS (
                  SELECT 1
                  FROM cohort_participants cp
                  INNER JOIN cohorts c ON c.id = cp.cohort_id
                  WHERE cp.user_id = ?
                    AND cp.status <> 'withdrawn'
                    AND c.programme_id = d.programme_id
                    AND (d.cohort_id IS NULL OR d.cohort_id = c.id)
              )
            LIMIT 1
            FOR UPDATE
        ");
        if (!$stmt) {
            throw new RuntimeException('Unable to verify deliverable access: ' . $conn->error);
        }
        $stmt->bind_param('ii', $deliverableId, $candidateId);
        $stmt->execute();
        $deliverable = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        if (!$deliverable) {
            $errors[] = 'This deliverable is unavailable or is outside your cohort.';
        }
    }

    $latestSubmission = null;
    if ($deliverable && !$errors) {
        $stmt = $conn->prepare("
            SELECT id, status
            FROM deliverable_submissions
            WHERE deliverable_id = ? AND candidate_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        if (!$stmt) {
            throw new RuntimeException('Unable to check your previous submissions: ' . $conn->error);
        }
        $stmt->bind_param('ii', $deliverableId, $candidateId);
        $stmt->execute();
        $latestSubmission = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        if ($latestSubmission && in_array($latestSubmission['status'], ['submitted', 'verified'], true)) {
            $errors[] = $latestSubmission['status'] === 'verified'
                ? 'This deliverable has already been verified.'
                : 'Your submission is awaiting review.';
        }
    }

    $upload = null;
    if (!$errors && isset($_FILES['submission_file'])) {
        $file = $_FILES['submission_file'];
        if (!is_array($file) || !isset($file['error']) || is_array($file['error'])) {
            $errors[] = 'Invalid file upload.';
        } elseif ((int) $file['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload = FileUploader::uploadDocument($file);
            if (!$upload['success']) {
                $errors[] = $upload['message'];
                $upload = null;
            }
        }
    }
    if (!$errors && $notes === '' && $referenceUrl === '' && !$upload) {
        $errors[] = 'Add notes, a reference link, or a file to submit.';
    }
    if ($submissionTransaction && $errors) {
        $conn->rollback();
        $submissionTransaction = false;
    }

    if (!$errors) {
        $storedFilename = $upload['stored_filename'] ?? null;
        $originalName = $upload['original_filename'] ?? null;
        $mimeType = $upload['mime'] ?? null;
        $fileSize = isset($upload['size']) ? (int) $upload['size'] : null;
        $notesValue = $notes !== '' ? $notes : null;
        $urlValue = $referenceUrl !== '' ? $referenceUrl : null;
        $stmt = $conn->prepare("
            INSERT INTO deliverable_submissions
                (deliverable_id, candidate_id, submitted_by, notes, reference_url,
                 file_path, original_name, mime_type, file_size)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if (!$stmt) {
            $conn->rollback();
            $cleanupUpload($storedFilename);
            throw new RuntimeException('Unable to prepare submission: ' . $conn->error);
        }
        $stmt->bind_param(
            'iiisssssi',
            $deliverableId,
            $candidateId,
            $candidateId,
            $notesValue,
            $urlValue,
            $storedFilename,
            $originalName,
            $mimeType,
            $fileSize
        );
        try {
            $saved = $stmt->execute();
        } catch (mysqli_sql_exception $exception) {
            $stmt->close();
            $conn->rollback();
            $cleanupUpload($storedFilename);
            throw $exception;
        }
        $stmt->close();
        if (!$saved) {
            $conn->rollback();
            $cleanupUpload($storedFilename);
            $errors[] = 'Your submission could not be saved. Please try again.';
        } else {
            $submissionRecordId = (int) $conn->insert_id;
            $conn->commit();
            CandidateNotification::create(
                $candidateId,
                'deliverables',
                'Deliverable submitted',
                'Your deliverable submission is awaiting review.',
                'deliverables',
                $deliverableId,
                'deliverable-submitted-' . $deliverableId . '-' . $candidateId . '-' . $submissionRecordId
            );
            ProgrammeOfficerNotification::forDeliverableSubmission($submissionRecordId);
            set_flash('success', 'Submission received', 'Your deliverable has been sent for verification.');
            safe_redirect('candidate/deliverables.php');
        }
    }
}

$deliverables = [];
if ($moduleReady) {
    $stmt = $conn->prepare("
        SELECT d.id, d.title, d.description, d.deliverable_type, d.due_date, d.status,
               p.name AS programme_name, c.name AS cohort_name,
               s.id AS submission_id, s.status AS submission_status,
               s.notes AS submission_notes, s.reference_url, s.original_name,
               s.submitted_at, s.verification_comment, s.file_path
        FROM programme_deliverables d
        INNER JOIN programmes p ON p.id = d.programme_id
        LEFT JOIN cohorts c ON c.id = d.cohort_id
        LEFT JOIN deliverable_submissions s
          ON s.id = (
              SELECT MAX(s2.id)
              FROM deliverable_submissions s2
              WHERE s2.deliverable_id = d.id AND s2.candidate_id = ?
          )
        WHERE EXISTS (
            SELECT 1
            FROM cohort_participants cp
            INNER JOIN cohorts c2 ON c2.id = cp.cohort_id
            WHERE cp.user_id = ?
              AND cp.status <> 'withdrawn'
              AND c2.programme_id = d.programme_id
              AND (d.cohort_id IS NULL OR d.cohort_id = c2.id)
        )
        ORDER BY d.due_date ASC, d.id DESC
    ");
    if (!$stmt) {
        throw new RuntimeException('Unable to load your deliverables: ' . $conn->error);
    }
    $stmt->bind_param('ii', $candidateId, $candidateId);
    $stmt->execute();
    $deliverables = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$name = trim((string) ($user['full_name'] ?? $user['fullname'] ?? 'Candidate'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Deliverables | Investhood IT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= url('css/styles.css') ?>">
    <link rel="stylesheet" href="<?= url('candidate/theme.css') ?>?v=20261112-profilemenu1">
    <style>
        .deliverables-content{padding:1.75rem;max-width:1400px;margin:0 auto}
        .deliverables-hero{padding:1.5rem 1.75rem;margin-bottom:1.25rem;border-radius:16px;background:linear-gradient(120deg,#1d4ed8,#4338ca);color:#fff}
        .deliverables-hero h1{margin:.4rem 0;font-size:clamp(1.5rem,3vw,2rem)}
        .deliverables-hero p{margin:0;max-width:720px;color:rgba(255,255,255,.82);line-height:1.6}
        .deliverable-card{margin-bottom:1rem;padding:1.25rem;border:1px solid var(--border,#e2e8f0);border-radius:14px;background:var(--bg-white,#fff);box-shadow:0 3px 14px rgba(15,23,42,.05)}
        .deliverable-card__top{display:flex;justify-content:space-between;gap:1rem;align-items:flex-start}
        .deliverable-card h2{margin:0;font-size:1.1rem;color:var(--dark,#0f172a)}
        .deliverable-card p{color:var(--text-light,#64748b);line-height:1.6}
        .deliverable-meta{display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.6rem;color:var(--text-light,#64748b);font-size:.8rem}
        .deliverable-status{padding:.3rem .65rem;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:.75rem;font-weight:700}
        .deliverable-status--verified{background:#dcfce7;color:#166534}
        .deliverable-status--rejected{background:#fee2e2;color:#991b1b}
        .deliverable-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.8rem;margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border,#e2e8f0)}
        .deliverable-form label{display:block;margin-bottom:.35rem;font-size:.8rem;font-weight:700}
        .deliverable-form input,.deliverable-form textarea{width:100%;padding:.7rem;border:1px solid #cbd5e1;border-radius:8px;font:inherit}
        .deliverable-form textarea{min-height:84px;resize:vertical}
        .deliverable-form__wide{grid-column:1/-1}
        .deliverable-empty,.deliverable-notice{padding:2rem;text-align:center;border:1px solid var(--border,#e2e8f0);border-radius:14px;background:var(--bg-white,#fff);color:var(--text-light,#64748b)}
        .deliverable-notice{margin-bottom:1rem;padding:1rem;text-align:left}
        .deliverable-alert{margin-bottom:1rem;padding:.85rem 1rem;border-radius:9px;background:#fef2f2;color:#991b1b}
        .deliverable-actions{display:flex;gap:.7rem;align-items:center;flex-wrap:wrap}
        .deliverable-file{font-size:.85rem}
        html[data-theme="dark"] .deliverable-status{background:#1d2c43;color:#bfdbfe!important}
        html[data-theme="dark"] .deliverable-status--verified{background:rgba(34,197,94,.16);color:#86efac!important}
        html[data-theme="dark"] .deliverable-status--rejected{background:rgba(239,68,68,.16);color:#fca5a5!important}
        html[data-theme="dark"] .deliverable-alert{background:rgba(239,68,68,.14);color:#fca5a5}
        @media(max-width:700px){.deliverables-content{padding:1rem}.deliverable-card__top{display:block}.deliverable-form{grid-template-columns:1fr}}
    </style>
</head>
<body class="dashboard-page">
<div class="dashboard">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar__header">
            <a href="<?= url('index.php') ?>" class="logo"><span class="logo__icon"><i class="fas fa-code"></i></span><span class="logo__text">Investhood <span class="logo__accent">IT</span></span></a>
            <button class="sidebar__close" id="sidebarClose" aria-label="Close sidebar"><i class="fas fa-times"></i></button>
        </div>
        <nav class="sidebar__nav">
            <div class="sidebar__section-label">Main</div>
            <ul class="sidebar__menu">
                <li><a href="<?= url('candidate/dashboard.php') ?>" class="sidebar__link"><i class="fas fa-th-large"></i> Dashboard</a></li>
                <li><a href="<?= url('candidate/opportunities.php') ?>" class="sidebar__link"><i class="fas fa-briefcase"></i> Opportunities</a></li>
                <li><a href="<?= url('candidate/applications.php') ?>" class="sidebar__link"><i class="fas fa-file-alt"></i> Applications</a></li>
                <li><a href="<?= url('candidate/learning.php') ?>" class="sidebar__link"><i class="fas fa-graduation-cap"></i> My Learning</a></li>
                <li><a href="<?= url('candidate/skills.php') ?>" class="sidebar__link"><i class="fas fa-star"></i> Skills</a></li>
                <li><a href="<?= url('candidate/deliverables.php') ?>" class="sidebar__link active"><i class="fas fa-list-check"></i> Deliverables</a></li>
                <li><a href="<?= url('candidate/certifications.php') ?>" class="sidebar__link"><i class="fas fa-certificate"></i> Certifications</a></li>
                <li><a href="<?= url('candidate/notifications.php') ?>" class="sidebar__link"><i class="fas fa-bell"></i> Notifications</a></li>
                <li><a href="<?= url('candidate/profile.php') ?>" class="sidebar__link"><i class="fas fa-user"></i> My Profile</a></li>
                <li><a href="<?= url('candidate/settings.php') ?>" class="sidebar__link"><i class="fas fa-cog"></i> Settings</a></li>
            </ul>
        </nav>
        <div class="sidebar__footer">
            <div class="sidebar__user">
                <div class="sidebar__user-avatar"><img src="<?= url('candidate/avatar.php') ?>" alt="Profile"></div>
                <div class="sidebar__user-info"><span class="sidebar__user-name"><?= e($name) ?></span><span class="sidebar__user-role">Candidate</span></div>
            </div>
            <a href="<?= url('auth/logout.php') ?>" class="sidebar__logout"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
        </div>
    </aside>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <main class="dashboard__main">
        <header class="dash-header">
            <div class="dash-header__left"><button type="button" class="dash-header__toggle" id="sidebarToggle" aria-label="Show or hide menu" aria-controls="sidebar" aria-expanded="false"><i class="fas fa-bars"></i></button></div>
            <div class="dash-header__right">
                <button class="dash-header__icon-btn" id="themeToggle" aria-label="Toggle dark mode"><i class="fas fa-moon"></i></button>
                <div class="dash-header__user profile-menu" data-profile-menu>
                    <button type="button" class="dash-header__avatar-trigger" data-profile-menu-toggle aria-haspopup="true" aria-expanded="false" aria-label="Open profile menu">
                        <img src="<?= url('candidate/avatar.php') ?>" alt="Profile" class="dash-header__avatar">
                        <span class="profile-menu__chevron" aria-hidden="true"><i class="fas fa-chevron-down"></i></span>
                    </button>
                    <div class="profile-menu__panel" data-profile-menu-panel aria-hidden="true">
                        <a href="<?= url('candidate/profile.php') ?>" class="profile-menu__item"><i class="fas fa-user"></i><span>Profile</span></a>
                        <a href="<?= url('candidate/settings.php') ?>" class="profile-menu__item"><i class="fas fa-cog"></i><span>Settings</span></a>
                        <div class="profile-menu__divider"></div>
                        <a href="<?= url('auth/logout.php') ?>" class="profile-menu__item profile-menu__item--logout"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
                    </div>
                </div>
            </div>
        </header>
        <div class="dash-content">
            <div class="deliverables-content">
                <?= $flashes ?>
                <section class="deliverables-hero">
                    <span>Programme work</span>
                    <h1>My Deliverables</h1>
                    <p>Submit programme outputs before their due dates and track the Programme Officer’s verification feedback.</p>
                </section>
                <?php if (!$moduleReady): ?>
                    <div class="deliverable-notice">Deliverables are not available yet. The Programme Officer must install the deliverables module.</div>
                <?php endif; ?>
                <?php foreach ($errors as $error): ?><div class="deliverable-alert"><?= e($error) ?></div><?php endforeach; ?>
                <?php if ($moduleReady && !$deliverables): ?>
                    <div class="deliverable-empty"><i class="fas fa-inbox"></i><h2>No deliverables assigned</h2><p>Programme deliverables for your active cohorts will appear here.</p></div>
                <?php endif; ?>
                <?php foreach ($deliverables as $deliverable):
                    $status = (string) ($deliverable['submission_status'] ?? '');
                    $dueTimestamp = strtotime((string) $deliverable['due_date']);
                    $dueDate = $dueTimestamp === false ? 'Date not available' : date('d M Y', $dueTimestamp);
                    $isOverdue = $deliverable['status'] === 'open' && $dueTimestamp !== false && $deliverable['due_date'] < date('Y-m-d');
                    $canSubmit = $deliverable['status'] === 'open' && !in_array($status, ['submitted', 'verified'], true);
                ?>
                    <article class="deliverable-card" id="deliverable-<?= (int) $deliverable['id'] ?>">
                        <div class="deliverable-card__top">
                            <div>
                                <h2><?= e($deliverable['title']) ?></h2>
                                <div class="deliverable-meta">
                                    <span><?= e($deliverable['programme_name']) ?><?= !empty($deliverable['cohort_name']) ? ' · ' . e($deliverable['cohort_name']) : ' · All cohorts' ?></span>
                                    <span><?= e(ucfirst((string) $deliverable['deliverable_type'])) ?></span>
                                    <span>Due <?= e($dueDate) ?></span>
                                    <?php if ($isOverdue): ?><span class="deliverable-status deliverable-status--rejected">Overdue — late submissions are still accepted</span><?php endif; ?>
                                </div>
                            </div>
                            <?php if ($status !== ''): ?>
                                <span class="deliverable-status <?= $status === 'verified' ? 'deliverable-status--verified' : ($status === 'rejected' ? 'deliverable-status--rejected' : '') ?>"><?= e(ucfirst($status)) ?></span>
                            <?php else: ?>
                                <span class="deliverable-status"><?= $deliverable['status'] === 'closed' ? 'Closed' : 'Not submitted' ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($deliverable['description'])): ?><p><?= nl2br(e($deliverable['description'])) ?></p><?php endif; ?>
                        <?php if (!empty($deliverable['submission_id'])): ?>
                            <div class="deliverable-meta">
                                <span>Submitted <?= e(date('d M Y, H:i', strtotime($deliverable['submitted_at']))) ?></span>
                                <?php if (!empty($deliverable['original_name'])): ?><a class="deliverable-file" href="<?= url('candidate/deliverable_download.php?id=' . (int) $deliverable['submission_id']) ?>"><i class="fas fa-download"></i> <?= e($deliverable['original_name']) ?></a><?php endif; ?>
                                <?php if (!empty($deliverable['reference_url']) && preg_match('#^https?://#i', $deliverable['reference_url'])): ?><a href="<?= e($deliverable['reference_url']) ?>" target="_blank" rel="noopener noreferrer">Open reference link</a><?php endif; ?>
                            </div>
                            <?php if (!empty($deliverable['submission_notes'])): ?><p><?= nl2br(e($deliverable['submission_notes'])) ?></p><?php endif; ?>
                            <?php if (!empty($deliverable['verification_comment'])): ?><p><strong>Reviewer feedback:</strong> <?= nl2br(e($deliverable['verification_comment'])) ?></p><?php endif; ?>
                        <?php endif; ?>
                        <?php if ($canSubmit): ?>
                            <form class="deliverable-form" method="post" enctype="multipart/form-data">
                                <?= csrf_field() ?>
                                <input type="hidden" name="deliverable_id" value="<?= (int) $deliverable['id'] ?>">
                                <div>
                                    <label for="notes-<?= (int) $deliverable['id'] ?>">Notes</label>
                                    <textarea id="notes-<?= (int) $deliverable['id'] ?>" name="notes" maxlength="5000" placeholder="Describe your output or submission"></textarea>
                                </div>
                                <div>
                                    <label for="reference-<?= (int) $deliverable['id'] ?>">Reference URL (optional)</label>
                                    <input id="reference-<?= (int) $deliverable['id'] ?>" type="url" name="reference_url" maxlength="500" placeholder="https://">
                                </div>
                                <div class="deliverable-form__wide">
                                    <label for="file-<?= (int) $deliverable['id'] ?>">Attach a file (optional)</label>
                                    <input id="file-<?= (int) $deliverable['id'] ?>" type="file" name="submission_file" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp,.txt">
                                    <small>Maximum 10 MB. Upload a file, enter notes, or provide a reference link.</small>
                                </div>
                                <div class="deliverable-actions deliverable-form__wide">
                                    <button class="btn btn--primary" type="submit"><i class="fas fa-paper-plane"></i> <?= $status === 'rejected' ? 'Resubmit' : 'Submit deliverable' ?></button>
                                </div>
                            </form>
                        <?php elseif ($status === 'submitted'): ?>
                            <p><strong>Your submission is awaiting verification.</strong></p>
                        <?php elseif ($status === 'verified'): ?>
                            <p><strong>This deliverable is verified.</strong></p>
                        <?php elseif ($deliverable['status'] === 'closed'): ?>
                            <p><strong>This deliverable is closed.</strong></p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</div>
<script src="<?= url('js/script.js') ?>"></script>
<script src="<?= url('candidate/theme.js') ?>?v=20261112-profilemenu1" defer></script>
</body>
</html>
