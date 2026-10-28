<?php
/**
 * ================================================
 * INVESTHOOD IT - Candidate Notification Model
 * ================================================
 * One candidate-facing notification feed shared by every module
 * (applications, programmes/opportunities, interviews, selection &
 * offers, placements, account/system).
 *
 * Design rules
 *  - RE-USES the existing `users` table for the recipient — no
 *    duplicate candidate table.
 *  - Ownership is enforced in SQL: every query is scoped by the
 *    authenticated user id, so a candidate can never read, mark or
 *    delete somebody else's notification by changing an id.
 *  - Every write goes through create() which uses INSERT IGNORE with
 *    a per-event dedupe key: retrying a request, refreshing a page
 *    or processing an administrative action twice can never
 *    duplicate a notification.
 *  - Notification creation is ALWAYS best-effort: it never throws,
 *    so a notification-table problem can never break the business
 *    process that raised the event.
 *  - Additive schema (see database/candidate_notifications.sql)
 *    applied lazily by ensureSchema() exactly like
 *    CandidateOffer::ensureSchema() does for the offers module.
 */

class CandidateNotification
{
    /**
     * Supported notification categories (candidate-facing labels and
     * the design-system icon/tone used to render them).
     *
     * @var array<string,array{label:string,icon:string,tone:string}>
     */
    public const CATEGORIES = [
        'application' => ['label' => 'Applications',               'icon' => 'fa-file-alt',           'tone' => 'application'],
        'opportunity' => ['label' => 'Programmes & Opportunities', 'icon' => 'fa-briefcase',          'tone' => 'opportunity'],
        'interview'   => ['label' => 'Interviews',                 'icon' => 'fa-calendar-check',     'tone' => 'interview'],
        'offer'       => ['label' => 'Selection & Offers',         'icon' => 'fa-envelope-open-text', 'tone' => 'offer'],
        'placement'   => ['label' => 'Placements',                 'icon' => 'fa-map-marker-alt',     'tone' => 'placement'],
        'account'     => ['label' => 'Account & System',           'icon' => 'fa-user-shield',        'tone' => 'account'],
        'learning'    => ['label' => 'Learning & Skills',            'icon' => 'fa-book-open',          'tone' => 'account'],
    ];

    /** @var string[] Destination modules a notification can link to */
    public const MODULES = ['application', 'interview', 'offer', 'opportunity', 'placement', 'settings', 'notifications', 'learning'];

    /** @var bool Lazily applied schema guard (per request) */
    private static bool $schemaChecked = false;

    // --------------------------------------------------------
    // SCHEMA (additive, idempotent)
    // --------------------------------------------------------

    /**
     * Create the candidate_notifications table when it is missing.
     * Safe to call on every request: the check is cached per request.
     */
    public static function ensureSchema(): void
    {
        if (self::$schemaChecked) {
            return;
        }
        self::$schemaChecked = true;

        try {
            $exists = Database::fetchOne("SHOW TABLES LIKE 'candidate_notifications'");
            if ($exists) {
                return;
            }
            Database::query(
                'CREATE TABLE IF NOT EXISTS `candidate_notifications` (
                  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                  `user_id`     INT UNSIGNED NOT NULL,
                  `category`    VARCHAR(30)  NOT NULL,
                  `title`       VARCHAR(180) NOT NULL,
                  `message`     TEXT         NOT NULL,
                  `module`      VARCHAR(40)  DEFAULT NULL,
                  `record_id`   INT UNSIGNED DEFAULT NULL,
                  `is_read`     TINYINT(1)   NOT NULL DEFAULT 0,
                  `read_at`     DATETIME     DEFAULT NULL,
                  `dedupe_key`  VARCHAR(160) DEFAULT NULL,
                  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `uq_candidate_notification_event` (`user_id`, `dedupe_key`),
                  KEY `idx_candidate_notifications_feed` (`user_id`, `is_read`, `created_at`),
                  KEY `idx_candidate_notifications_category` (`user_id`, `category`, `created_at`),
                  CONSTRAINT `fk_candidate_notifications_user`
                    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
                    ON UPDATE CASCADE ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (Throwable $e) {
            error_log('[CandidateNotification] ensureSchema failed: ' . $e->getMessage());
        }
    }

    // --------------------------------------------------------
    // HELPERS
    // --------------------------------------------------------

    /**
     * Valid category for storage (falls back to "account" so a stray
     * value can never break an insert).
     */
    public static function normaliseCategory(?string $category): string
    {
        $category = strtolower(trim((string) $category));
        return isset(self::CATEGORIES[$category]) ? $category : 'account';
    }

    public static function categoryLabel(?string $category): string
    {
        return self::CATEGORIES[self::normaliseCategory($category)]['label'];
    }

    public static function categoryIcon(?string $category): string
    {
        return self::CATEGORIES[self::normaliseCategory($category)]['icon'];
    }

    public static function categoryTone(?string $category): string
    {
        return self::CATEGORIES[self::normaliseCategory($category)]['tone'];
    }

    /**
     * Destination URL for a notification's related record. Falls back
     * to the notifications page when the module/record is unknown, so
     * a stale link can never dead-end the candidate.
     */
    public static function destinationUrl(?string $module, ?int $recordId = null): string
    {
        $recordId = $recordId !== null && $recordId > 0 ? $recordId : 0;

        switch ((string) $module) {
            case 'application':
                return $recordId > 0
                    ? 'candidate/application_detail.php?id=' . $recordId
                    : 'candidate/applications.php';
            case 'interview':
                return $recordId > 0
                    ? 'candidate/interviews.php#interview-modal-' . $recordId
                    : 'candidate/interviews.php';
            case 'offer':
                return $recordId > 0
                    ? 'candidate/offer_detail.php?id=' . $recordId
                    : 'candidate/offers.php';
            case 'opportunity':
                return $recordId > 0
                    ? 'candidate/opportunity_detail.php?id=' . $recordId
                    : 'candidate/opportunities.php';
            case 'placement':
                return 'candidate/dashboard.php#dashboard-placements';
            case 'settings':
                return 'candidate/settings.php';
            case 'learning':
                return $recordId > 0
                    ? 'candidate/course.php?id=' . $recordId
                    : 'candidate/learning.php';
            default:
                return 'candidate/notifications.php';
        }
    }

    /**
     * JSON payload for one notification row (used by the API + JS).
     */
    public static function payload(array $row): array
    {
        $category = self::normaliseCategory((string) ($row['category'] ?? ''));
        $recordId = isset($row['record_id']) && $row['record_id'] !== null ? (int) $row['record_id'] : null;
        $rawModule = (string) ($row['module'] ?? '');
        $module = $rawModule !== '' ? $rawModule : null;

        return [
            'id'            => (int) ($row['id'] ?? 0),
            'category'      => $category,
            'categoryLabel' => self::CATEGORIES[$category]['label'],
            'icon'          => self::CATEGORIES[$category]['icon'],
            'tone'          => self::CATEGORIES[$category]['tone'],
            'title'         => (string) ($row['title'] ?? ''),
            'message'       => (string) ($row['message'] ?? ''),
            'url'           => url(self::destinationUrl($module, $recordId)),
            'is_read'       => (int) ($row['is_read'] ?? 0) === 1,
            'time'          => time_ago((string) ($row['created_at'] ?? null)),
            'created_at'    => (string) ($row['created_at'] ?? ''),
        ];
    }

    // --------------------------------------------------------
    // CREATE (best-effort, duplicate-proof)
    // --------------------------------------------------------

    /**
     * Store one notification for a candidate.
     *
     * INSERT IGNORE + the (user_id, dedupe_key) unique key makes this
     * idempotent: repeated events never duplicate a notification.
     * Always returns false instead of throwing so the calling business
     * process (submission, status change, scheduling, ...) is never
     * broken by the notification subsystem.
     *
     * @param int         $userId    Recipient (users.id — the candidate)
     * @param string      $category  One of CATEGORIES
     * @param string      $title
     * @param string      $message
     * @param string|null $module    Destination module (see MODULES)
     * @param int|null    $recordId  Related record id
     * @param string|null $dedupeKey Unique per-event key preventing duplicates
     */
    public static function create(
        int $userId,
        string $category,
        string $title,
        string $message,
        ?string $module = null,
        ?int $recordId = null,
        ?string $dedupeKey = null
    ): bool {
        if ($userId <= 0 || trim($title) === '' || trim($message) === '') {
            return false;
        }

        self::ensureSchema();

        $category  = self::normaliseCategory($category);
        $title     = mb_substr(trim($title), 0, 180);
        $message   = trim($message);
        $module    = $module !== null && in_array($module, self::MODULES, true) ? $module : null;
        $recordId  = $recordId !== null && $recordId > 0 ? $recordId : null;
        $dedupeKey = $dedupeKey !== null && trim($dedupeKey) !== ''
            ? mb_substr(trim($dedupeKey), 0, 160)
            : null;

        try {
            Database::execute(
                'INSERT IGNORE INTO candidate_notifications
                    (user_id, category, title, message, module, record_id, dedupe_key)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                'issssis',
                [$userId, $category, $title, $message, $module, $recordId, $dedupeKey]
            );
            return true;
        } catch (Throwable $e) {
            error_log('[CandidateNotification] create failed: ' . $e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------
    // READ (candidate-scoped)
    // --------------------------------------------------------

    /**
     * Paginated notifications for one candidate, newest first.
     *
     * @param int   $candidateId Authenticated candidate (users.id)
     * @param array $filters     status (all|unread|read) / category / q
     * @param int   $page        1-based page number
     * @param int   $perPage
     * @return array{records:array,total:int,unread:int,pages:int,page:int,perPage:int,statusCounts:array,categoryCounts:array}
     */
    public static function forCandidate(int $candidateId, array $filters = [], int $page = 1, int $perPage = 15): array
    {
        $empty = [
            'records'        => [],
            'total'          => 0,
            'unread'         => 0,
            'pages'          => 1,
            'page'           => 1,
            'perPage'        => $perPage,
            'statusCounts'   => ['all' => 0, 'unread' => 0, 'read' => 0],
            'categoryCounts' => [],
        ];

        if ($candidateId <= 0) {
            return $empty;
        }

        self::ensureSchema();

        $status   = (string) ($filters['status'] ?? 'all');
        $hasCat   = array_key_exists('category', $filters) && (string) $filters['category'] !== '';
        $category = self::normaliseCategory((string) ($filters['category'] ?? ''));
        $q        = trim((string) ($filters['q'] ?? ''));
        if (!in_array($status, ['all', 'unread', 'read'], true)) {
            $status = 'all';
        }

        $where  = ['n.user_id = ?'];
        $types  = 'i';
        $params = [$candidateId];

        if ($status === 'unread') {
            $where[] = 'n.is_read = 0';
        } elseif ($status === 'read') {
            $where[] = 'n.is_read = 1';
        }

        if ($hasCat) {
            $where[]  = 'n.category = ?';
            $types   .= 's';
            $params[] = $category;
        }

        if ($q !== '') {
            $where[]  = '(n.title LIKE ? OR n.message LIKE ?)';
            $types   .= 'ss';
            $like     = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $whereSql = implode(' AND ', $where);

        try {
            $total = (int) (Database::fetchOne(
                "SELECT COUNT(*) AS total FROM candidate_notifications n WHERE {$whereSql}",
                $types,
                $params
            )['total'] ?? 0);

            $pages  = max(1, (int) ceil($total / max(1, $perPage)));
            $page   = min(max(1, $page), $pages);
            $offset = ($page - 1) * $perPage;

            $records = $total > 0
                ? Database::fetchAll(
                    "SELECT n.* FROM candidate_notifications n
                     WHERE {$whereSql}
                     ORDER BY n.created_at DESC, n.id DESC
                     LIMIT ?, ?",
                    $types . 'ii',
                    array_merge($params, [$offset, $perPage])
                )
                : [];

            return [
                'records'        => $records,
                'total'          => $total,
                'unread'         => self::unreadCount($candidateId),
                'pages'          => $pages,
                'page'           => $page,
                'perPage'        => $perPage,
                'statusCounts'   => self::statusCounts($candidateId),
                'categoryCounts' => self::categoryCounts($candidateId),
            ];
        } catch (Throwable $e) {
            error_log('[CandidateNotification] forCandidate failed: ' . $e->getMessage());
            return $empty;
        }
    }

    /**
     * Unread notification count for the header badge.
     */
    public static function unreadCount(int $candidateId): int
    {
        if ($candidateId <= 0) {
            return 0;
        }
        self::ensureSchema();

        try {
            return (int) (Database::fetchOne(
                'SELECT COUNT(*) AS cnt FROM candidate_notifications WHERE user_id = ? AND is_read = 0',
                'i',
                [$candidateId]
            )['cnt'] ?? 0);
        } catch (Throwable $e) {
            error_log('[CandidateNotification] unreadCount failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Most recent notifications for the header dropdown.
     */
    public static function recentForCandidate(int $candidateId, int $limit = 5): array
    {
        if ($candidateId <= 0) {
            return [];
        }
        self::ensureSchema();

        try {
            return Database::fetchAll(
                'SELECT * FROM candidate_notifications
                 WHERE user_id = ?
                 ORDER BY is_read ASC, created_at DESC, id DESC
                 LIMIT ?',
                'ii',
                [$candidateId, max(1, $limit)]
            );
        } catch (Throwable $e) {
            error_log('[CandidateNotification] recentForCandidate failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * All / unread / read counts for the filter tabs.
     */
    public static function statusCounts(int $candidateId): array
    {
        $counts = ['all' => 0, 'unread' => 0, 'read' => 0];
        if ($candidateId <= 0) {
            return $counts;
        }
        self::ensureSchema();

        try {
            $row = Database::fetchOne(
                'SELECT COUNT(*) AS total, COALESCE(SUM(is_read = 0), 0) AS unread
                 FROM candidate_notifications WHERE user_id = ?',
                'i',
                [$candidateId]
            );
            $counts['all']    = (int) ($row['total'] ?? 0);
            $counts['unread'] = (int) ($row['unread'] ?? 0);
            $counts['read']   = $counts['all'] - $counts['unread'];
            return $counts;
        } catch (Throwable $e) {
            error_log('[CandidateNotification] statusCounts failed: ' . $e->getMessage());
            return $counts;
        }
    }

    /**
     * Per-category counts (used to badge the category filter).
     */
    public static function categoryCounts(int $candidateId): array
    {
        $counts = array_fill_keys(array_keys(self::CATEGORIES), 0);
        if ($candidateId <= 0) {
            return $counts;
        }
        self::ensureSchema();

        try {
            $rows = Database::fetchAll(
                'SELECT category, COUNT(*) AS cnt FROM candidate_notifications
                 WHERE user_id = ? GROUP BY category',
                'i',
                [$candidateId]
            );
            foreach ($rows as $row) {
                $category = self::normaliseCategory((string) ($row['category'] ?? ''));
                $counts[$category] = (int) ($row['cnt'] ?? 0);
            }
            return $counts;
        } catch (Throwable $e) {
            error_log('[CandidateNotification] categoryCounts failed: ' . $e->getMessage());
            return $counts;
        }
    }

    /**
     * Fetch one notification, scoped to its owner.
     */
    public static function findForCandidate(int $notificationId, int $candidateId): ?array
    {
        if ($notificationId <= 0 || $candidateId <= 0) {
            return null;
        }
        self::ensureSchema();

        try {
            return Database::fetchOne(
                'SELECT * FROM candidate_notifications WHERE id = ? AND user_id = ?',
                'ii',
                [$notificationId, $candidateId]
            );
        } catch (Throwable $e) {
            error_log('[CandidateNotification] findForCandidate failed: ' . $e->getMessage());
            return null;
        }
    }

    // --------------------------------------------------------
    // WRITE ACTIONS (candidate-scoped)
    // --------------------------------------------------------

    /**
     * Mark a single notification as read (scoped to its owner).
     */
    public static function markRead(int $notificationId, int $candidateId): bool
    {
        if ($notificationId <= 0 || $candidateId <= 0) {
            return false;
        }
        self::ensureSchema();

        try {
            return Database::execute(
                'UPDATE candidate_notifications
                 SET is_read = 1, read_at = NOW()
                 WHERE id = ? AND user_id = ? AND is_read = 0',
                'ii',
                [$notificationId, $candidateId]
            ) > 0;
        } catch (Throwable $e) {
            error_log('[CandidateNotification] markRead failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark every unread notification of the candidate as read.
     *
     * @return int Rows updated
     */
    public static function markAllRead(int $candidateId): int
    {
        if ($candidateId <= 0) {
            return 0;
        }
        self::ensureSchema();

        try {
            return Database::execute(
                'UPDATE candidate_notifications
                 SET is_read = 1, read_at = NOW()
                 WHERE user_id = ? AND is_read = 0',
                'i',
                [$candidateId]
            );
        } catch (Throwable $e) {
            error_log('[CandidateNotification] markAllRead failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Delete one notification (scoped to its owner).
     */
    public static function delete(int $notificationId, int $candidateId): bool
    {
        if ($notificationId <= 0 || $candidateId <= 0) {
            return false;
        }
        self::ensureSchema();

        try {
            return Database::execute(
                'DELETE FROM candidate_notifications WHERE id = ? AND user_id = ?',
                'ii',
                [$notificationId, $candidateId]
            ) > 0;
        } catch (Throwable $e) {
            error_log('[CandidateNotification] delete failed: ' . $e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------
    // EVENT TRIGGERS (called by the modules that raise events)
    // --------------------------------------------------------

    /**
     * Application submitted confirmation.
     */
    public static function forApplicationSubmitted(int $candidateId, int $applicationId, string $reference, string $opportunityTitle = ''): bool
    {
        $title   = 'Application submitted';
        $message = 'Your application'
            . ($reference !== '' ? ' (' . $reference . ')' : '')
            . ($opportunityTitle !== '' ? ' for "' . mb_substr($opportunityTitle, 0, 120) . '"' : '')
            . ' was submitted successfully. We will notify you as soon as it moves to the next stage.';

        return self::create(
            $candidateId,
            'application',
            $title,
            $message,
            'application',
            $applicationId,
            'app_submitted_' . $applicationId
        );
    }

    /**
     * Application moved to a new pipeline stage.
     */
    public static function forApplicationStatusChange(
        int $candidateId,
        int $applicationId,
        string $reference,
        string $oldStatus,
        string $newStatus,
        ?string $reason = null
    ): bool {
        $ref      = $reference !== '' ? ' (' . $reference . ')' : '';
        $oldLabel = Application::label($oldStatus);
        $newLabel = Application::label($newStatus);

        $titles = [
            'shortlisted' => 'You have been shortlisted',
            'selected'    => 'Great news — you have been selected',
            'rejected'    => 'Application outcome update',
            'waitlisted'  => 'You have been waitlisted',
        ];
        $title = $titles[$newStatus] ?? 'Application status update';

        $message = "Your application{$ref} moved from \"{$oldLabel}\" to \"{$newLabel}\".";
        if ($newStatus === 'shortlisted') {
            $message .= ' Congratulations! The programme team will be in touch with the next steps.';
        } elseif ($newStatus === 'selected') {
            $message .= ' Congratulations! Keep an eye on your offers for the formal offer details.';
        } elseif ($newStatus === 'rejected') {
            $message .= ' Thank you for your interest — please explore other opportunities on the platform.';
        } elseif ($reason !== null && trim($reason) !== '') {
            $message .= ' Reason: ' . mb_substr(trim($reason), 0, 200);
        }

        return self::create(
            $candidateId,
            'application',
            $title,
            $message,
            'application',
            $applicationId,
            'app_status_' . $applicationId . '_' . $newStatus
        );
    }

    /**
     * Interview scheduled / rescheduled / status change.
     *
     * @param string|null $dedupeSuffix Extra key segment so two DIFFERENT
     *        updates of the same interview each raise a notification while
     *        an identical retried request stays duplicate-proof.
     */
    public static function forInterviewEvent(
        int $candidateId,
        int $interviewId,
        string $event,
        string $interviewDate = '',
        string $startTime = '',
        ?string $reason = null,
        ?string $dedupeSuffix = null
    ): bool {
        $when = '';
        if ($interviewDate !== '') {
            $when = ' on ' . date('D, d M Y', (int) strtotime($interviewDate));
            if ($startTime !== '') {
                $when .= ' at ' . date('g:i A', (int) strtotime($startTime));
            }
        }

        switch ($event) {
            case 'scheduled':
                $title   = 'Interview scheduled';
                $message = 'An interview has been scheduled for your application' . $when
                    . '. Open the interview for the full details and meeting link.';
                $key = 'interview_scheduled_' . $interviewId;
                break;
            case 'updated':
                $title   = 'Interview details updated';
                $message = 'The details of your interview' . $when . ' have been updated by the programme team. '
                    . 'Please review the latest date, time and meeting information.';
                $key = 'interview_updated_' . $interviewId;
                break;
            case 'rescheduled':
                $title   = 'Interview rescheduled';
                $message = 'Your interview has been rescheduled' . $when . '. '
                    . 'Please review the updated details before the new time.';
                $key = 'interview_rescheduled_' . $interviewId;
                break;
            case 'cancelled':
                $title   = 'Interview cancelled';
                $message = 'Your interview' . $when . ' has been cancelled by the programme team.'
                    . ($reason !== null && trim($reason) !== '' ? ' Reason: ' . mb_substr(trim($reason), 0, 200) : '')
                    . ' The programme team will contact you if a new slot is arranged.';
                $key = 'interview_cancelled_' . $interviewId;
                break;
            case 'confirmed':
                $title   = 'Interview confirmed';
                $message = 'Your interview' . $when . ' has been confirmed. We look forward to seeing you!';
                $key = 'interview_confirmed_' . $interviewId;
                break;
            case 'completed':
                $title   = 'Interview completed';
                $message = 'Thank you for attending your interview' . $when
                    . '. Your interview has been marked as completed — we will be in touch with the outcome.';
                $key = 'interview_completed_' . $interviewId;
                break;
            default:
                $title   = 'Interview update';
                $message = 'There is an update on one of your interviews' . $when . '.';
                $key     = 'interview_' . preg_replace('/[^a-z0-9_]/i', '', $event) . '_' . $interviewId;
        }

        if ($dedupeSuffix !== null && trim($dedupeSuffix) !== '') {
            $key .= '_' . preg_replace('/[^a-zA-Z0-9_\-]/', '', $dedupeSuffix);
        }

        return self::create($candidateId, 'interview', $title, $message, 'interview', $interviewId, $key);
    }

    /**
     * Offer lifecycle event (issued / accepted / declined / expired /
     * withdrawn / deadline approaching).
     */
    public static function forOfferEvent(int $candidateId, int $offerId, string $offerStatus, string $position = ''): bool
    {
        $positionText = $position !== '' ? ' for "' . mb_substr($position, 0, 120) . '"' : '';

        switch ($offerStatus) {
            case 'issued':
                $title   = 'New offer available';
                $message = 'Congratulations! A new offer' . $positionText
                    . ' is now available. Open it to review the terms and respond before the deadline.';
                break;
            case 'accepted':
                $title   = 'Offer accepted';
                $message = 'Your offer' . $positionText . ' has been accepted. The programme team has been notified '
                    . 'and will share the next steps, including your placement details, soon.';
                break;
            case 'declined':
                $title   = 'Offer declined';
                $message = 'Your response to the offer' . $positionText . ' has been recorded as declined.';
                break;
            case 'expired':
                $title   = 'Offer expired';
                $message = 'The response deadline for your offer' . $positionText . ' has passed, so the offer is now expired.';
                break;
            case 'withdrawn':
                $title   = 'Offer withdrawn';
                $message = 'The offer' . $positionText . ' has been withdrawn by the programme team.';
                break;
            case 'offer_deadline_approaching':
                $title   = 'Offer response deadline approaching';
                $message = 'Your offer' . $positionText . ' expires soon. Please accept or decline it before the deadline.';
                break;
            default:
                $title   = 'Offer update';
                $message = 'There is an update on your offer' . $positionText . '.';
        }

        return self::create(
            $candidateId,
            'offer',
            $title,
            $message,
            'offer',
            $offerId,
            'offer_' . $offerId . '_' . $offerStatus
        );
    }

    /**
     * Placement lifecycle event (created / status change / cancelled).
     */
    public static function forPlacementEvent(int $candidateId, int $placementId, string $event, string $detail = ''): bool
    {
        switch ($event) {
            case 'placement_created':
                $title   = 'Placement assigned';
                $message = 'You have been assigned a placement'
                    . ($detail !== '' ? '. ' . $detail : '')
                    . ' Open your dashboard to review the placement details, dates and supervisor.';
                break;
            case 'status_changed':
                $title   = 'Placement status updated';
                $message = 'The status of your placement has been updated'
                    . ($detail !== '' ? ' (' . $detail . ')' : '') . '.';
                break;
            case 'placement_cancelled':
                $title   = 'Placement cancelled';
                $message = 'Your placement has been cancelled'
                    . ($detail !== '' ? '. Reason: ' . $detail : '')
                    . '. Please contact the programme team if you have questions.';
                break;
            default:
                $title   = 'Placement update';
                $message = 'There is an update on your placement'
                    . ($detail !== '' ? '. ' . $detail : '') . '.';
        }

        return self::create(
            $candidateId,
            'placement',
            $title,
            $message,
            'placement',
            $placementId,
            $event . '_' . $placementId
        );
    }

    /**
     * Account / system events (email verification, password change).
     */
    public static function forAccountEvent(int $userId, string $event, ?string $detail = null): bool
    {
        $detail = $detail !== null ? trim($detail) : '';

        switch ($event) {
            case 'email_verified':
                $title   = 'Email verified';
                $message = 'Your email address has been verified successfully. Your account is now fully active.';
                $key     = 'account_email_verified_' . $userId;
                break;
            case 'password_changed':
                $title   = 'Password changed';
                $message = 'The password for your account was changed'
                    . ($detail !== '' ? ' ' . $detail : '')
                    . '. If this was not you, reset your password immediately and contact support.';
                $key     = 'account_password_changed_' . $userId . '_' . date('YmdHis');
                break;
            default:
                $title   = 'Account update';
                $message = 'There is an important update on your account.'
                    . ($detail !== '' ? ' ' . $detail : '');
                $key     = 'account_' . preg_replace('/[^a-z0-9_]/i', '', $event) . '_' . $userId . '_' . date('YmdHis');
        }

        return self::create($userId, 'account', $title, $message, 'settings', null, $key);
    }

    // --------------------------------------------------------
    // LAZY, IDEMPOTENT SYNCS (state-derived, the same pattern as
    // CandidateOffer::generateNotifications)
    // --------------------------------------------------------

    /**
     * Mirror the module-scoped offer_notifications rows into the
     * unified feed. INSERT IGNORE + the dedupe key
     * "offer_{offer_id}_{notification_type}" guarantees that repeated
     * calls (page refreshes) never duplicate a notification.
     */
    public static function mirrorOfferNotifications(int $candidateId): void
    {
        if ($candidateId <= 0) {
            return;
        }
        self::ensureSchema();

        try {
            Database::execute(
                "INSERT IGNORE INTO candidate_notifications
                    (user_id, category, title, message, module, record_id, dedupe_key)
                 SELECT n.candidate_id, 'offer', n.title, n.message, 'offer', n.offer_id,
                        CONCAT('offer_', n.offer_id, '_', n.notification_type)
                 FROM offer_notifications n
                 WHERE n.candidate_id = ?",
                'i',
                [$candidateId]
            );
        } catch (Throwable $e) {
            error_log('[CandidateNotification] mirrorOfferNotifications failed: ' . $e->getMessage());
        }
    }

    /**
     * Programmes & Opportunities events derived from live state:
     *   1. Application deadline reminders (closing within 3 days).
     *   2. New relevant opportunities published in the last 7 days
     *      that the candidate has not applied to yet.
     * Both are INSERT IGNORE with per-event dedupe keys, so repeated
     * page loads never duplicate anything.
     */
    public static function syncOpportunityEvents(int $candidateId): void
    {
        if ($candidateId <= 0) {
            return;
        }
        self::ensureSchema();

        // ---- 1. Application deadline reminders ----
        try {
            Database::execute(
                "INSERT IGNORE INTO candidate_notifications
                    (user_id, category, title, message, module, record_id, dedupe_key)
                 SELECT a.candidate_id, 'opportunity',
                        'Application deadline approaching',
                        CONCAT('The application window for ', o.title,
                               ' closes on ', DATE_FORMAT(o.application_close_date, '%d %b %Y'),
                               '. Make sure your application is complete and submitted before the deadline.'),
                        'application', a.id,
                        CONCAT('app_deadline_', o.id, '_', a.candidate_id)
                 FROM applications a
                 INNER JOIN opportunities o ON o.id = a.opportunity_id
                 WHERE a.candidate_id = ?
                   AND a.status IN ('draft', 'submitted', 'under_review')
                   AND o.application_close_date IS NOT NULL
                   AND o.application_close_date >= CURDATE()
                   AND o.application_close_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)",
                'i',
                [$candidateId]
            );
        } catch (Throwable $e) {
            error_log('[CandidateNotification] syncOpportunityEvents (deadlines) failed: ' . $e->getMessage());
        }

        // ---- 2. New relevant opportunities (never applied to) ----
        try {
            Database::execute(
                "INSERT IGNORE INTO candidate_notifications
                    (user_id, category, title, message, module, record_id, dedupe_key)
                 SELECT u.id, 'opportunity',
                        'New opportunity for you',
                        CONCAT(o.title,
                               ' (', COALESCE(NULLIF(o.organisation, ''), 'Investhood IT'),
                               ') was just published and matches your profile. Applications close ',
                               COALESCE(DATE_FORMAT(o.application_close_date, '%d %b %Y'), 'soon'), '.'),
                        'opportunity', o.id,
                        CONCAT('opp_new_', o.id, '_', u.id)
                 FROM opportunities o
                 CROSS JOIN users u
                 LEFT JOIN candidate_profiles cp ON cp.user_id = u.id
                 WHERE u.id = ?
                   AND u.role_id = (SELECT id FROM roles WHERE slug = 'candidate' LIMIT 1)
                   AND o.status = 'published'
                   AND o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                   AND (o.application_close_date IS NULL OR o.application_close_date >= CURDATE())
                   AND (o.province IS NULL OR o.province = ''
                        OR o.province = COALESCE(NULLIF(u.province, ''), NULLIF(cp.province, '')))
                   AND NOT EXISTS (
                       SELECT 1 FROM applications a2
                       WHERE a2.candidate_id = u.id AND a2.opportunity_id = o.id
                   )
                 ORDER BY o.created_at DESC
                 LIMIT 3",
                'i',
                [$candidateId]
            );
        } catch (Throwable $e) {
            error_log('[CandidateNotification] syncOpportunityEvents (new opportunities) failed: ' . $e->getMessage());
        }
    }
    /**
     * Notifications for the authenticated candidate's learning activity
     * (assignment changes + lesson completion events).
     */
    public static function forLearning(int $candidateId, int $limit = 6): array
    {
        if ($candidateId <= 0) {
            return [];
        }
        self::ensureSchema();
        try {
            return Database::fetchAll(
                "SELECT n.*
                 FROM candidate_notifications n
                 WHERE n.category = 'learning'
                   AND n.user_id = ?
                 ORDER BY n.created_at DESC, n.id DESC
                 LIMIT ?",
                'ii',
                [$candidateId, $limit]
            );
        } catch (Throwable $e) {
            error_log('[CandidateNotification] forLearning failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Queue a learning notification (assignment, deadline, completion).
     * Idempotent via dedupe key.
     */
    public static function addLearningNotification(
        int $candidateId,
        string $category,
        string $title,
        string $message,
        int $recordId,
        string $dedupeKey
    ): bool {
        return self::create($candidateId, 'learning', $title, $message, 'learning', $recordId, $dedupeKey);
    }

}









