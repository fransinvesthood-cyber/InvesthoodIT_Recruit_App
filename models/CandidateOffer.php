<?php
/**
 * ================================================
 * INVESTHOOD IT - Candidate Offer Model (Stage 12)
 * ================================================
 * Candidate-facing data access for the offer records created by the
 * completed Administrator Selection & Offer module.
 *
 * Design rules
 *  - RE-USES the existing tables only (offers, offer_status_history,
 *    applications, application_status_history). No duplicate offer table
 *    and no second status system: the candidate-facing labels ("Pending")
 *    are display-only mappings over the existing offers.status ENUM
 *    (draft / issued / accepted / declined / expired / withdrawn).
 *  - RE-USES Selection::changeOfferStatus() for every candidate response,
 *    so status transitions, the responded_at timestamp, the
 *    offer_status_history audit trail and the applications.status
 *    pipeline sync behave exactly like an administrator action.
 *  - Ownership is enforced in SQL: every query is scoped by the
 *    authenticated candidate id, so a candidate can never read or respond
 *    to another candidate's offer by changing an id.
 *  - Additive schema (see database/candidate_offers.sql):
 *       offers.decline_reason  - candidate decline reason
 *       offer_notifications    - candidate offer notifications
 *    applied lazily by ensureSchema() exactly like
 *    Interview::ensureFeedbackColumns() does for its own module.
 */

class CandidateOffer
{
    /** @var string[] Offer statuses visible to candidates (drafts are admin-only) */
    public const CANDIDATE_STATUSES = ['issued', 'accepted', 'declined', 'expired', 'withdrawn'];

    /**
     * Display label per offer status. The candidate sees the database
     * status 'issued' as "Pending" (the administrator sees it as issued /
     * sent) — the stored value is unchanged, so no conflicting status is
     * introduced.
     *
     * @var array<string,string>
     */
    public const STATUS_DISPLAY_LABELS = [
        'draft'     => 'Draft',
        'issued'    => 'Pending',
        'accepted'  => 'Accepted',
        'declined'  => 'Declined',
        'expired'   => 'Expired',
        'withdrawn' => 'Withdrawn',
    ];

    /**
     * Badge tone per offer status (shared badge tones from the platform
     * design system: muted / primary / success / danger / amber).
     *
     * @var array<string,string>
     */
    public const STATUS_DISPLAY_TONES = [
        'draft'     => 'muted',
        'issued'    => 'primary',
        'accepted'  => 'success',
        'declined'  => 'danger',
        'expired'   => 'amber',
        'withdrawn' => 'muted',
    ];

    /** @var string[] Notification types raised by the offers module */
    public const NOTIFICATION_TYPES = [
        'offer_issued',
        'offer_updated',
        'offer_withdrawn',
        'offer_deadline_approaching',
        'offer_expired',
        'offer_response_recorded',
    ];

    /** @var bool Lazily applied schema guard (per request) */
    private static bool $schemaChecked = false;

    /** @var array<string,bool> Cached column-existence lookups */
    private static array $columnCache = [];

    // --------------------------------------------------------
    // SCHEMA (additive, idempotent)
    // --------------------------------------------------------

    /**
     * Apply the additive structures the candidate module needs. Safe to
     * call on every request: the checks are cached per request and every
     * statement is idempotent.
     */
    public static function ensureSchema(): void
    {
        if (self::$schemaChecked) {
            return;
        }
        self::$schemaChecked = true;

        try {
            $exists = Database::fetchOne("SHOW TABLES LIKE 'offer_notifications'");
            if (!$exists) {
                Database::query(
                    'CREATE TABLE IF NOT EXISTS `offer_notifications` (
                      `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
                      `offer_id`          INT UNSIGNED NOT NULL,
                      `candidate_id`      INT UNSIGNED NOT NULL,
                      `sender_id`         INT UNSIGNED NULL,
                      `notification_type` ENUM(\'offer_issued\',\'offer_updated\',\'offer_withdrawn\',
                                               \'offer_deadline_approaching\',\'offer_expired\',
                                               \'offer_response_recorded\') NOT NULL,
                      `title`             VARCHAR(200) NOT NULL,
                      `message`           VARCHAR(500) NOT NULL,
                      `is_read`           TINYINT(1)   NOT NULL DEFAULT 0,
                      `read_at`           DATETIME     NULL DEFAULT NULL,
                      `created_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                      PRIMARY KEY (`id`),
                      UNIQUE KEY `uq_offer_notification_event` (`offer_id`, `candidate_id`, `notification_type`),
                      KEY `idx_offer_notifications_candidate` (`candidate_id`, `is_read`, `created_at`),
                      KEY `idx_offer_notifications_offer` (`offer_id`),
                      CONSTRAINT `fk_offer_notifications_offer`
                        FOREIGN KEY (`offer_id`) REFERENCES `offers` (`id`)
                        ON UPDATE CASCADE ON DELETE CASCADE,
                      CONSTRAINT `fk_offer_notifications_candidate`
                        FOREIGN KEY (`candidate_id`) REFERENCES `users` (`id`)
                        ON UPDATE CASCADE ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
                );
            }

            if (!self::columnExists('offers', 'decline_reason')) {
                Database::query(
                    'ALTER TABLE `offers` ADD COLUMN `decline_reason` VARCHAR(500) NULL DEFAULT NULL '
                    . 'COMMENT \'Reason provided by the candidate when declining the offer\' AFTER `responded_at`'
                );
            }
        } catch (Throwable $e) {
            error_log('[CandidateOffer] schema migration skipped: ' . $e->getMessage());
        }
    }

    /**
     * Whether a column exists (cached per request).
     */
    private static function columnExists(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (!array_key_exists($key, self::$columnCache)) {
            try {
                $safeTable  = preg_replace('/[^a-z0-9_]/i', '', $table);
                $safeColumn = preg_replace('/[^a-z0-9_]/i', '', $column);
                self::$columnCache[$key] = Database::fetchOne(
                    "SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'"
                ) !== null;
            } catch (Throwable $e) {
                self::$columnCache[$key] = false;
            }
        }
        return self::$columnCache[$key];
    }

    // --------------------------------------------------------
    // SHARED SELECT
    // --------------------------------------------------------

    /**
     * Columns every candidate offer view needs: the offer plus its
     * application, opportunity, programme, cohort, organisation and
     * issuing administrator context.
     */
    private const CANDIDATE_SELECT =
        "SELECT off.*,
                a.application_reference, a.candidate_id AS application_candidate_id,
                a.status AS application_status, a.submitted_at,
                o.title AS opportunity_title, o.type AS opportunity_type,
                o.organisation, o.work_arrangement,
                o.province AS opportunity_province, o.city AS opportunity_city,
                p.name AS programme_name, p.type AS programme_type,
                c.name AS cohort_name,
                CONCAT(u.first_name, ' ', u.last_name) AS candidate_name,
                u.email AS candidate_email, u.phone AS candidate_phone,
                CONCAT(ib.first_name, ' ', ib.last_name) AS issued_by_name,
                ib.email AS issued_by_email
         FROM offers off
         INNER JOIN applications a ON a.id = off.application_id AND a.candidate_id = off.candidate_id
         INNER JOIN users u ON u.id = off.candidate_id
         LEFT JOIN opportunities o ON o.id = off.opportunity_id
         LEFT JOIN programmes p ON p.id = off.programme_id
         LEFT JOIN cohorts c ON c.id = off.cohort_id
         LEFT JOIN users ib ON ib.id = off.issued_by";

    /**
     * Candidate visibility rule: drafts are never visible (an offer only
     * becomes visible once it is issued) and the offer must belong to the
     * authenticated candidate — enforced in SQL, never in the browser.
     */
    private const CANDIDATE_SCOPE = "off.candidate_id = ? AND a.candidate_id = ? AND off.status <> 'draft'";

    /**
     * Paginated offers for one candidate.
     *
     * @param int   $candidateId Authenticated candidate (users.id)
     * @param array $filters     status / search / programme_id / sort
     * @param int   $page        1-based page number
     * @param int   $perPage
     * @return array{records:array,total:int,pages:int,page:int,perPage:int,statusCounts:array}
     */
    public static function forCandidate(int $candidateId, array $filters = [], int $page = 1, int $perPage = 10): array
    {
        self::ensureSchema();

        $filters['candidate_id'] = $candidateId;
        [$whereSql, $types, $params] = self::buildFilters($filters);

        try {
            $total = (int) (Database::fetchOne(
                "SELECT COUNT(*) AS total
                 FROM offers off
                 INNER JOIN applications a ON a.id = off.application_id AND a.candidate_id = off.candidate_id
                 LEFT JOIN opportunities o ON o.id = off.opportunity_id
                 LEFT JOIN programmes p ON p.id = off.programme_id
                 LEFT JOIN cohorts c ON c.id = off.cohort_id
                 WHERE {$whereSql}",
                $types,
                $params
            )['total'] ?? 0);

            $pages  = max(1, (int) ceil($total / max(1, $perPage)));
            $page   = min(max(1, $page), $pages);
            $offset = ($page - 1) * $perPage;

            $records = $total > 0
                ? Database::fetchAll(
                    self::CANDIDATE_SELECT . "
                     WHERE {$whereSql}
                     ORDER BY " . self::orderBy((string) ($filters['sort'] ?? 'recent')) . "
                     LIMIT ?, ?",
                    $types . 'ii',
                    array_merge($params, [$offset, $perPage])
                )
                : [];

            return [
                'records'      => $records,
                'total'        => $total,
                'pages'        => $pages,
                'page'         => $page,
                'perPage'      => $perPage,
                'statusCounts' => self::statusCounts($candidateId),
            ];
        } catch (Throwable $e) {
            error_log('[CandidateOffer] forCandidate failed: ' . $e->getMessage());
            return [
                'records'      => [],
                'total'        => 0,
                'pages'        => 1,
                'page'         => 1,
                'perPage'      => $perPage,
                'statusCounts' => self::emptyCounts(),
            ];
        }
    }

    /**
     * A single offer, scoped to the authenticated candidate. Returns null
     * for both "does not exist" and "belongs to somebody else", so the
     * caller can never leak the existence of another candidate's offer.
     */
    public static function findForCandidate(int $offerId, int $candidateId): ?array
    {
        self::ensureSchema();

        if ($offerId <= 0 || $candidateId <= 0) {
            return null;
        }

        try {
            return Database::fetchOne(
                self::CANDIDATE_SELECT . "
                 WHERE off.id = ? AND " . self::CANDIDATE_SCOPE . "
                 LIMIT 1",
                'iii',
                [$offerId, $candidateId, $candidateId]
            );
        } catch (Throwable $e) {
            error_log('[CandidateOffer] findForCandidate failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * A candidate's offers, newest first (dashboard preview).
     */
    public static function recentForCandidate(int $candidateId, int $limit = 3): array
    {
        self::ensureSchema();

        try {
            return Database::fetchAll(
                self::CANDIDATE_SELECT . '
                 WHERE ' . self::CANDIDATE_SCOPE . '
                 ORDER BY COALESCE(off.issued_at, off.created_at) DESC, off.id DESC
                 LIMIT ?',
                'iii',
                [$candidateId, $candidateId, $limit]
            );
        } catch (Throwable $e) {
            error_log('[CandidateOffer] recentForCandidate failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * The candidate's most recent offer (dashboard "latest offer" card).
     */
    public static function mostRecentForCandidate(int $candidateId): ?array
    {
        $rows = self::recentForCandidate($candidateId, 1);
        return $rows[0] ?? null;
    }

    /**
     * Offers still awaiting a response (status issued), nearest response
     * deadline first. Drives the "respond by" reminders on the dashboard.
     */
    public static function pendingDeadlines(int $candidateId, int $limit = 5): array
    {
        self::ensureSchema();

        try {
            return Database::fetchAll(
                self::CANDIDATE_SELECT . '
                 WHERE ' . self::CANDIDATE_SCOPE . "
                   AND off.status = 'issued'
                 ORDER BY off.expiry_date ASC, off.id ASC
                 LIMIT ?",
                'iii',
                [$candidateId, $candidateId, $limit]
            );
        } catch (Throwable $e) {
            error_log('[CandidateOffer] pendingDeadlines failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Candidate-facing status counts.
     *
     * @return array{total:int,pending:int,accepted:int,declined:int,expired:int,withdrawn:int}
     */
    public static function statusCounts(int $candidateId): array
    {
        self::ensureSchema();

        $counts = self::emptyCounts();

        try {
            $rows = Database::fetchAll(
                'SELECT off.status, COUNT(*) AS cnt
                 FROM offers off
                 INNER JOIN applications a ON a.id = off.application_id AND a.candidate_id = off.candidate_id
                 WHERE ' . self::CANDIDATE_SCOPE . '
                 GROUP BY off.status',
                'ii',
                [$candidateId, $candidateId]
            );

            foreach ($rows as $row) {
                $status = (string) ($row['status'] ?? '');
                $count  = (int) ($row['cnt'] ?? 0);
                $counts['total'] += $count;
                if ($status === 'issued') {
                    $counts['pending'] += $count;
                } elseif (isset($counts[$status])) {
                    $counts[$status] += $count;
                }
            }
        } catch (Throwable $e) {
            error_log('[CandidateOffer] statusCounts failed: ' . $e->getMessage());
        }

        return $counts;
    }

    /**
     * Zeroed status counters.
     *
     * @return array{total:int,pending:int,accepted:int,declined:int,expired:int,withdrawn:int}
     */
    public static function emptyCounts(): array
    {
        return [
            'total'     => 0,
            'pending'   => 0,
            'accepted'  => 0,
            'declined'  => 0,
            'expired'   => 0,
            'withdrawn' => 0,
        ];
    }

    /**
     * SQL ORDER BY clause for the candidate offer list.
     */
    private static function orderBy(string $sort): string
    {
        switch ($sort) {
            case 'deadline':
                return "CASE WHEN off.status = 'issued' THEN 0 ELSE 1 END ASC, off.expiry_date ASC, off.id DESC";
            case 'status':
                return "FIELD(off.status, 'issued','accepted','declined','expired','withdrawn'), off.id DESC";
            case 'programme':
                return 'p.name ASC, off.id DESC';
            case 'recent':
            default:
                return 'COALESCE(off.issued_at, off.created_at) DESC, off.id DESC';
        }
    }

    /**
     * Build the WHERE clause + bound parameters for candidate reads.
     * The candidate scope always comes first, so filters can never widen it.
     *
     * @return array{0:string,1:string,2:array}
     */
    private static function buildFilters(array $filters): array
    {
        $candidateId = (int) ($filters['candidate_id'] ?? 0);

        $where  = [self::CANDIDATE_SCOPE];
        $params = [$candidateId, $candidateId];
        $types  = 'ii';

        $status = (string) ($filters['status'] ?? '');
        if ($status !== '' && $status !== 'all' && in_array($status, self::CANDIDATE_STATUSES, true)) {
            $where[]  = 'off.status = ?';
            $params[] = $status;
            $types   .= 's';
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like    = '%' . $search . '%';
            $where[] = '(off.title LIKE ? OR off.position LIKE ? OR o.title LIKE ? OR p.name LIKE ?'
                     . ' OR c.name LIKE ? OR o.organisation LIKE ? OR off.location LIKE ?'
                     . ' OR a.application_reference LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like, $like, $like, $like);
            $types .= 'ssssssss';
        }

        $programmeId = (int) ($filters['programme_id'] ?? 0);
        if ($programmeId > 0) {
            $where[]  = 'off.programme_id = ?';
            $params[] = $programmeId;
            $types   .= 'i';
        }

        return [implode(' AND ', $where), $types, $params];
    }

    // --------------------------------------------------------
    // WORKFLOW: EXPIRY + NOTIFICATIONS
    // --------------------------------------------------------

    /**
     * Keep the candidate's offers consistent before rendering:
     *   1. offers past their response deadline are marked Expired through
     *      the shared offer workflow (audit trail + application pipeline
     *      stay in sync);
     *   2. missing offer notifications are generated (idempotent).
     *
     * Called once per request from the candidate Offers pages and from the
     * candidate dashboard.
     *
     * @return int Number of offers newly expired by this call
     */
    public static function refreshForCandidate(int $candidateId): int
    {
        self::ensureSchema();

        $expired = self::expireOverdueOffers($candidateId);
        self::generateNotifications($candidateId);

        return $expired;
    }

    /**
     * Mark every overdue 'issued' offer of this candidate as expired.
     * A candidate can therefore never respond to an offer whose response
     * deadline has passed (server-side rule, not just a disabled button).
     */
    public static function expireOverdueOffers(int $candidateId): int
    {
        self::ensureSchema();

        try {
            $overdue = Database::fetchAll(
                "SELECT off.id
                 FROM offers off
                 INNER JOIN applications a ON a.id = off.application_id AND a.candidate_id = off.candidate_id
                 WHERE off.candidate_id = ? AND a.candidate_id = ?
                   AND off.status = 'issued'
                   AND off.expiry_date < CURDATE()",
                'ii',
                [$candidateId, $candidateId]
            );
        } catch (Throwable $e) {
            error_log('[CandidateOffer] expireOverdueOffers lookup failed: ' . $e->getMessage());
            return 0;
        }

        $expired = 0;
        foreach ($overdue as $row) {
            try {
                // Re-uses the Administrator module's workflow: transitions,
                // history and the application status sync are handled by the
                // same code path an administrator status change uses.
                Selection::changeOfferStatus((int) $row['id'], 'expired', null, 'Response deadline passed');
                $expired++;
            } catch (Throwable $e) {
                error_log('[CandidateOffer] expire offer ' . (int) $row['id'] . ' failed: ' . $e->getMessage());
            }
        }

        return $expired;
    }

    /**
     * Generate the candidate's offer notifications for events that have
     * happened but have no notification row yet. Every insert is guarded by
     * the UNIQUE (offer_id, candidate_id, notification_type) key, so
     * repeated calls (page refreshes) never duplicate a notification.
     */
    public static function generateNotifications(int $candidateId): void
    {
        self::ensureSchema();

        $events = [
            // A new offer has been issued.
            'offer_issued' => "SELECT off.id, off.candidate_id, off.issued_by, 'offer_issued',
                                      'New offer received',
                                      CONCAT('You have received an offer for \"', off.position,
                                             '\" (', off.title, '). Please review the terms and conditions and respond by ',
                                             DATE_FORMAT(off.expiry_date, '%d %b %Y'), '.')
                              FROM offers off
                              WHERE off.candidate_id = ? AND off.status <> 'draft'",
            // The offer was changed after it had been issued.
            'offer_updated' => "SELECT off.id, off.candidate_id, off.issued_by, 'offer_updated',
                                      'Offer updated',
                                      CONCAT('The offer for \"', off.position,
                                             '\" was updated by the programme team. Please review the latest details.')
                              FROM offers off
                              WHERE off.candidate_id = ?
                                AND off.status = 'issued'
                                AND off.issued_at IS NOT NULL
                                AND off.updated_at > DATE_ADD(off.issued_at, INTERVAL 1 SECOND)",
            // The administrator withdrew the offer.
            'offer_withdrawn' => "SELECT off.id, off.candidate_id, off.issued_by, 'offer_withdrawn',
                                      'Offer withdrawn',
                                      CONCAT('The offer for \"', off.position,
                                             '\" has been withdrawn by the programme team.')
                              FROM offers off
                              WHERE off.candidate_id = ? AND off.status = 'withdrawn'",
            // The offer expired without a response.
            'offer_expired' => "SELECT off.id, off.candidate_id, off.issued_by, 'offer_expired',
                                      'Offer expired',
                                      CONCAT('The response deadline for \"', off.position, '\" (',
                                             DATE_FORMAT(off.expiry_date, '%d %b %Y'),
                                             ') has passed, so this offer is now expired.')
                              FROM offers off
                              WHERE off.candidate_id = ? AND off.status = 'expired'",
            // The response deadline is approaching (3 days or less).
            'offer_deadline_approaching' => "SELECT off.id, off.candidate_id, off.issued_by, 'offer_deadline_approaching',
                                      'Response deadline approaching',
                                      CONCAT('Your offer for \"', off.position, '\" expires on ',
                                             DATE_FORMAT(off.expiry_date, '%d %b %Y'),
                                             '. Please accept or decline it before the deadline.')
                              FROM offers off
                              WHERE off.candidate_id = ?
                                AND off.status = 'issued'
                                AND off.expiry_date >= CURDATE()
                                AND off.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)",
        ];

        foreach ($events as $type => $select) {
            try {
                Database::execute(
                    'INSERT IGNORE INTO offer_notifications
                        (offer_id, candidate_id, sender_id, notification_type, title, message)
                     SELECT src.id, src.candidate_id, src.sender_id, src.notification_type, src.title, src.message
                     FROM (' . $select . ') AS src',
                    'i',
                    [$candidateId]
                );
            } catch (Throwable $e) {
                error_log('[CandidateOffer] notification (' . $type . ') skipped: ' . $e->getMessage());
            }
        }
    }

    /**
     * Record a single notification (best effort). The UNIQUE key keeps the
     * call idempotent.
     */
    public static function recordNotification(
        int $offerId,
        int $candidateId,
        string $type,
        string $title,
        string $message,
        ?int $senderId = null
    ): void {
        if (!in_array($type, self::NOTIFICATION_TYPES, true)) {
            return;
        }
        self::ensureSchema();

        try {
            Database::execute(
                'INSERT IGNORE INTO offer_notifications
                    (offer_id, candidate_id, sender_id, notification_type, title, message)
                 VALUES (?, ?, ?, ?, ?, ?)',
                'iiisss',
                [$offerId, $candidateId, $senderId, $type, mb_substr($title, 0, 200), mb_substr($message, 0, 500)]
            );
        } catch (Throwable $e) {
            error_log('[CandidateOffer] recordNotification failed: ' . $e->getMessage());
        }
    }

    /**
     * Notifications for the authenticated candidate (unread first, newest
     * first).
     */
    public static function notificationsForCandidate(int $candidateId, int $limit = 6, bool $unreadOnly = false): array
    {
        self::ensureSchema();

        try {
            $where = 'n.candidate_id = ?' . ($unreadOnly ? ' AND n.is_read = 0' : '');

            return Database::fetchAll(
                "SELECT n.*, off.position AS offer_position, off.title AS offer_title,
                        off.status AS offer_status, off.expiry_date AS offer_expiry_date
                 FROM offer_notifications n
                 LEFT JOIN offers off ON off.id = n.offer_id
                 WHERE {$where}
                 ORDER BY n.is_read ASC, n.created_at DESC, n.id DESC
                 LIMIT ?",
                'ii',
                [$candidateId, $limit]
            );
        } catch (Throwable $e) {
            error_log('[CandidateOffer] notificationsForCandidate failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Unread offer notifications for a candidate.
     */
    public static function unreadNotificationCount(int $candidateId): int
    {
        self::ensureSchema();

        try {
            return (int) (Database::fetchOne(
                'SELECT COUNT(*) AS cnt FROM offer_notifications WHERE candidate_id = ? AND is_read = 0',
                'i',
                [$candidateId]
            )['cnt'] ?? 0);
        } catch (Throwable $e) {
            error_log('[CandidateOffer] unreadNotificationCount failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Mark a single notification as read (scoped to its owner, so a
     * candidate can never mark somebody else's notification).
     */
    public static function markNotificationRead(int $notificationId, int $candidateId): bool
    {
        if ($notificationId <= 0 || $candidateId <= 0) {
            return false;
        }
        self::ensureSchema();

        try {
            return Database::execute(
                'UPDATE offer_notifications
                 SET is_read = 1, read_at = NOW()
                 WHERE id = ? AND candidate_id = ? AND is_read = 0',
                'ii',
                [$notificationId, $candidateId]
            ) > 0;
        } catch (Throwable $e) {
            error_log('[CandidateOffer] markNotificationRead failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark all of the candidate's offer notifications as read.
     *
     * @return int Rows updated
     */
    public static function markAllNotificationsRead(int $candidateId): int
    {
        self::ensureSchema();

        try {
            return Database::execute(
                'UPDATE offer_notifications
                 SET is_read = 1, read_at = NOW()
                 WHERE candidate_id = ? AND is_read = 0',
                'i',
                [$candidateId]
            );
        } catch (Throwable $e) {
            error_log('[CandidateOffer] markAllNotificationsRead failed: ' . $e->getMessage());
            return 0;
        }
    }

    // --------------------------------------------------------
    // WORKFLOW: CANDIDATE RESPONSE
    // --------------------------------------------------------

    /**
     * Whether an offer is awaiting a candidate response right now
     * (status issued and the response deadline has not passed).
     */
    public static function isAwaitingResponse(array $offer): bool
    {
        if (($offer['status'] ?? '') !== 'issued') {
            return false;
        }
        $deadline = (string) ($offer['expiry_date'] ?? '');
        if ($deadline === '') {
            return true;
        }
        return $deadline >= date('Y-m-d');
    }

    /**
     * Store the candidate's decline reason (best effort — the column is
     * added by ensureSchema() / the migration runner).
     */
    public static function saveDeclineReason(int $offerId, ?string $reason): void
    {
        $reason = $reason !== null ? trim($reason) : '';
        if ($reason === '' || !self::columnExists('offers', 'decline_reason')) {
            return;
        }

        try {
            Database::execute(
                'UPDATE offers SET decline_reason = ? WHERE id = ?',
                'si',
                [mb_substr($reason, 0, 500), $offerId]
            );
        } catch (Throwable $e) {
            error_log('[CandidateOffer] saveDeclineReason failed: ' . $e->getMessage());
        }
    }

    /**
     * Record the candidate's response to an offer.
     *
     * The status change itself is delegated to the Administrator module's
     * Selection::changeOfferStatus(), so the offer status, the responded_at
     * timestamp, the offer_status_history audit trail and the
     * applications.status pipeline stay consistent with an administrator
     * response — including the duplicate-response protection.
     *
     * @param int         $offerId
     * @param int         $candidateId Authenticated candidate (users.id)
     * @param string      $response    accepted | declined
     * @param string|null $reason      Optional decline reason
     * @return array{status:string,label:string,previous_status:string,responded_at:string}
     * @throws RuntimeException when the offer cannot be responded to
     */
    public static function respond(int $offerId, int $candidateId, string $response, ?string $reason = null): array
    {
        self::ensureSchema();

        if (!in_array($response, ['accepted', 'declined'], true)) {
            throw new RuntimeException('Invalid offer response.');
        }

        $offer = self::findForCandidate($offerId, $candidateId);
        if (!$offer) {
            // Same message for "not found" and "not yours": never reveal the
            // existence of another candidate's offer.
            throw new RuntimeException('Offer not found.');
        }

        $status = (string) $offer['status'];

        if ($status === 'accepted') {
            throw new RuntimeException('You have already accepted this offer.');
        }
        if ($status === 'declined') {
            throw new RuntimeException('You have already declined this offer.');
        }
        if ($status === 'expired') {
            throw new RuntimeException('This offer has expired and can no longer be responded to.');
        }
        if ($status === 'withdrawn') {
            throw new RuntimeException('This offer has been withdrawn by the programme team and can no longer be responded to.');
        }
        if ($status !== 'issued') {
            throw new RuntimeException('This offer is not awaiting a response.');
        }

        $deadline = (string) ($offer['expiry_date'] ?? '');
        if ($deadline !== '' && $deadline < date('Y-m-d')) {
            // Keep the record consistent even when the lazy refresh has not
            // run for this candidate yet.
            try {
                Selection::changeOfferStatus($offerId, 'expired', null, 'Response deadline passed');
            } catch (Throwable $e) {
                error_log('[CandidateOffer] deferred expiry failed: ' . $e->getMessage());
            }
            throw new RuntimeException('The response deadline for this offer has passed. The offer is now expired.');
        }

        $cleanReason = null;
        if ($response === 'declined' && $reason !== null && trim($reason) !== '') {
            $cleanReason = mb_substr(trim($reason), 0, 500);
        }

        // Shared workflow: transactions, re-validation of the transition,
        // responded_at, offer audit trail and application status sync.
        $result = Selection::changeOfferStatus(
            $offerId,
            $response,
            $candidateId,
            $response === 'declined'
                ? ('Declined by candidate' . ($cleanReason !== null ? ' — ' . $cleanReason : ''))
                : 'Accepted by candidate'
        );

        if ($response === 'declined') {
            self::saveDeclineReason($offerId, $cleanReason);
        }

        $label = self::STATUS_DISPLAY_LABELS[$response] ?? ucfirst($response);

        self::recordNotification(
            $offerId,
            $candidateId,
            'offer_response_recorded',
            'Offer ' . $label,
            $response === 'accepted'
                ? 'You accepted the offer for "' . (string) $offer['position'] . '". The programme team has been notified and will arrange your placement.'
                : 'You declined the offer for "' . (string) $offer['position'] . '". The programme team has been notified.',
            null
        );

        return [
            'status'          => $response,
            'label'           => $label,
            'previous_status' => (string) ($result['previous_status'] ?? 'issued'),
            'responded_at'    => date('Y-m-d H:i:s'),
        ];
    }
}
