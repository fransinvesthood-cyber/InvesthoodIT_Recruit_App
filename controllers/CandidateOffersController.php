<?php
/**
 * ================================================
 * INVESTHOOD IT - Candidate Offers Controller (Stage 12)
 * ================================================
 * Business logic for the candidate-side Offers module: listing and
 * filtering the offers issued by administrators, the accept/decline
 * workflow (including the response deadline rules), dashboard summary
 * data and offer notifications.
 *
 * All data access is delegated to CandidateOffer, which scopes every query
 * to the authenticated candidate. The controller never trusts an offer id
 * coming from the browser.
 */

class CandidateOffersController
{
    /** Offers per page on the candidate Offers page */
    public const PER_PAGE = 8;

    /**
     * Status filter options shown to the candidate. The values are the
     * EXISTING offers.status values — 'Pending' is the candidate-facing
     * label of the stored 'issued' status.
     *
     * @var array<string,string>
     */
    public const STATUS_FILTERS = [
        'all'       => 'All Offers',
        'issued'    => 'Pending',
        'accepted'  => 'Accepted',
        'declined'  => 'Declined',
        'expired'   => 'Expired',
        'withdrawn' => 'Withdrawn',
    ];

    /**
     * Accepted filter aliases (URL-friendly synonyms of the stored status).
     *
     * @var array<string,string>
     */
    public const STATUS_ALIASES = [
        'pending' => 'issued',
        'sent'    => 'issued',
        'issued'  => 'issued',
    ];

    /**
     * Sort options for the offers list.
     *
     * @var array<string,string>
     */
    public const SORT_OPTIONS = [
        'recent'    => 'Most Recent',
        'deadline'  => 'Response Deadline',
        'status'    => 'Status',
        'programme' => 'Programme',
    ];

    /** Notification icon tone per notification type (existing .notif-item tones) */
    public const NOTIFICATION_TONES = [
        'offer_issued'               => 'offer',
        'offer_updated'              => 'offer',
        'offer_withdrawn'            => 'announcement',
        'offer_deadline_approaching' => 'application',
        'offer_expired'              => 'announcement',
        'offer_response_recorded'    => 'interview',
    ];

    /** Notification icon per notification type */
    public const NOTIFICATION_ICONS = [
        'offer_issued'               => 'fa-file-signature',
        'offer_updated'              => 'fa-pen-to-square',
        'offer_withdrawn'            => 'fa-ban',
        'offer_deadline_approaching' => 'fa-hourglass-half',
        'offer_expired'              => 'fa-clock',
        'offer_response_recorded'    => 'fa-check-double',
    ];

    /**
     * Normalise the filters coming from the query string.
     *
     * @param array $input Raw $_GET
     * @return array{status:string,search:string,sort:string,programme_id:int}
     */
    public static function normaliseFilters(array $input): array
    {
        $status = strtolower(trim((string) ($input['status'] ?? 'all')));
        $status = self::STATUS_ALIASES[$status] ?? $status;
        if ($status !== 'all' && !in_array($status, CandidateOffer::CANDIDATE_STATUSES, true)) {
            $status = 'all';
        }

        $sort = (string) ($input['sort'] ?? 'recent');
        if (!array_key_exists($sort, self::SORT_OPTIONS)) {
            $sort = 'recent';
        }

        return [
            'status'       => $status,
            'search'       => trim((string) ($input['q'] ?? '')),
            'sort'         => $sort,
            'programme_id' => (int) ($input['programme_id'] ?? 0),
        ];
    }

    /**
     * Refreshed state before rendering the candidate offers UI
     * (expire overdue offers + generate notifications).
     *
     * @return int Number of offers that expired during this call
     */
    public static function refresh(int $candidateId): int
    {
        return CandidateOffer::refreshForCandidate($candidateId);
    }

    /**
     * Paginated offers for the authenticated candidate.
     *
     * @param array $filters normalised filters
     * @return array{records:array,total:int,pages:int,page:int,perPage:int,statusCounts:array}
     */
    public static function listOffers(int $candidateId, array $filters = [], int $page = 1, int $perPage = self::PER_PAGE): array
    {
        return CandidateOffer::forCandidate($candidateId, $filters, $page, $perPage);
    }

    /**
     * A single offer of the authenticated candidate (null when it does not
     * exist OR belongs to another candidate).
     */
    public static function find(int $offerId, int $candidateId): ?array
    {
        return CandidateOffer::findForCandidate($offerId, $candidateId);
    }

    /**
     * Everything the candidate dashboard needs about offers.
     *
     * @return array{stats:array,recent:?array,recentList:array,deadlines:array,
     *               notifications:array,unread:int,expired:int}
     */
    public static function dashboard(int $candidateId): array
    {
        $expired = self::refresh($candidateId);

        return [
            'stats'         => CandidateOffer::statusCounts($candidateId),
            'recent'        => CandidateOffer::mostRecentForCandidate($candidateId),
            'recentList'    => CandidateOffer::recentForCandidate($candidateId, 3),
            'deadlines'     => CandidateOffer::pendingDeadlines($candidateId, 4),
            'notifications' => CandidateOffer::notificationsForCandidate($candidateId, 5),
            'unread'        => CandidateOffer::unreadNotificationCount($candidateId),
            'expired'       => $expired,
        ];
    }

    // --------------------------------------------------------
    // DISPLAY HELPERS
    // --------------------------------------------------------

    /**
     * Candidate-facing status of an offer. An 'issued' offer whose response
     * deadline has passed is displayed as Expired even before the lazy
     * expiry refresh has stored that status.
     */
    public static function statusSlug(array $offer): string
    {
        $status = (string) ($offer['status'] ?? '');
        if ($status === 'issued' && !CandidateOffer::isAwaitingResponse($offer)) {
            return 'expired';
        }
        return $status;
    }

    /**
     * Label for a status slug, or for a whole offer record.
     */
    public static function statusLabel($offerOrStatus): string
    {
        $slug = is_array($offerOrStatus) ? self::statusSlug($offerOrStatus) : (string) $offerOrStatus;
        return CandidateOffer::STATUS_DISPLAY_LABELS[$slug] ?? ucfirst($slug);
    }

    /**
     * Badge tone for a status slug, or for a whole offer record.
     */
    public static function statusTone($offerOrStatus): string
    {
        $slug = is_array($offerOrStatus) ? self::statusSlug($offerOrStatus) : (string) $offerOrStatus;
        return CandidateOffer::STATUS_DISPLAY_TONES[$slug] ?? 'muted';
    }

    /**
     * Whether the offer currently accepts an accept/decline response.
     */
    public static function canRespond(array $offer): bool
    {
        return CandidateOffer::isAwaitingResponse($offer);
    }

    /**
     * Response deadline information used for the countdown/urgency display.
     *
     * @return array{date:?string,days:?int,expired:bool,due_today:bool,label:string,tone:string}
     */
    public static function deadline(array $offer): array
    {
        $date = $offer['expiry_date'] ?? null;

        if (empty($date)) {
            return [
                'date'      => null,
                'days'      => null,
                'expired'   => false,
                'due_today' => false,
                'label'     => 'No deadline set',
                'tone'      => 'muted',
            ];
        }

        $today = new DateTimeImmutable(date('Y-m-d'));
        try {
            $deadlineDate = new DateTimeImmutable((new DateTimeImmutable((string) $date))->format('Y-m-d'));
        } catch (Throwable $e) {
            $deadlineDate = $today;
        }

        $days    = (int) $today->diff($deadlineDate)->format('%r%a');
        $expired = $days < 0;

        if ($expired) {
            $label = 'Deadline passed ' . abs($days) . ' day' . (abs($days) === 1 ? '' : 's') . ' ago';
            $tone  = 'danger';
        } elseif ($days === 0) {
            $label = 'Due today';
            $tone  = 'amber';
        } elseif ($days === 1) {
            $label = '1 day remaining';
            $tone  = 'amber';
        } elseif ($days <= 3) {
            $label = $days . ' days remaining';
            $tone  = 'amber';
        } else {
            $label = $days . ' days remaining';
            $tone  = 'success';
        }

        return [
            'date'      => (string) $date,
            'days'      => $days,
            'expired'   => $expired,
            'due_today' => $days === 0,
            'label'     => $label,
            'tone'      => $tone,
        ];
    }

    /**
     * Offer date (issued date, falling back to the creation date).
     */
    public static function offerDate(array $offer): ?string
    {
        return $offer['issued_at'] ?? $offer['created_at'] ?? null;
    }

    /**
     * Organisation / company of the offer (opportunity, falling back to the
     * programme name).
     */
    public static function organisation(array $offer): string
    {
        $org = trim((string) ($offer['organisation'] ?? ''));
        if ($org !== '') {
            return $org;
        }
        return trim((string) ($offer['programme_name'] ?? '')) ?: 'Investhood IT';
    }

    /**
     * Location / work arrangement of the offer.
     */
    public static function location(array $offer): string
    {
        $location = trim((string) ($offer['location'] ?? ''));
        if ($location === '') {
            $parts = array_filter([
                trim((string) ($offer['opportunity_city'] ?? '')),
                trim((string) ($offer['opportunity_province'] ?? '')),
            ]);
            $location = implode(', ', $parts);
        }
        if ($location === '') {
            $location = 'Location to be confirmed';
        }

        $arrangements = [
            'on_site' => 'On-site',
            'remote'  => 'Remote',
            'hybrid'  => 'Hybrid',
        ];
        $arrangement = $arrangements[$offer['work_arrangement'] ?? ''] ?? '';

        return $arrangement !== '' ? $location . ' (' . $arrangement . ')' : $location;
    }

    /**
     * Compensation / stipend display.
     */
    public static function compensation(array $offer): string
    {
        $compensation = trim((string) ($offer['compensation'] ?? ''));
        return $compensation !== '' ? $compensation : 'Not specified';
    }

    /**
     * Duration of the offer, derived from the existing start/end dates.
     * Returns null when there is nothing meaningful to show.
     */
    public static function duration(array $offer): ?string
    {
        $start = $offer['start_date'] ?? null;
        $end   = $offer['end_date'] ?? null;

        if (empty($start) && empty($end)) {
            return null;
        }

        if (!empty($start) && !empty($end)) {
            $startDate = date_create((string) $start);
            $endDate   = date_create((string) $end);
            if ($startDate && $endDate && $endDate >= $startDate) {
                $months = ((int) $endDate->format('Y') - (int) $startDate->format('Y')) * 12
                    + ((int) $endDate->format('n') - (int) $startDate->format('n'));
                $days   = (int) $startDate->diff($endDate)->format('%a') + 1;

                $length = $months > 0
                    ? ($months . ' month' . ($months === 1 ? '' : 's'))
                    : ($days . ' day' . ($days === 1 ? '' : 's'));

                return $length . ' (' . format_date((string) $start, 'd M Y')
                    . ' – ' . format_date((string) $end, 'd M Y') . ')';
            }
        }

        if (!empty($start)) {
            return 'From ' . format_date((string) $start, 'd M Y');
        }

        return 'Until ' . format_date((string) $end, 'd M Y');
    }

    /**
     * Short offer summary for the list view: the first part of the offer
     * terms when provided, otherwise a factual summary built from the real
     * offer record (never placeholder text).
     */
    public static function summary(array $offer, int $limit = 190): string
    {
        $terms = trim((string) preg_replace('/\s+/', ' ', (string) ($offer['terms'] ?? '')));
        if ($terms !== '') {
            if (mb_strlen($terms) <= $limit) {
                return $terms;
            }
            return rtrim(mb_substr($terms, 0, $limit), " \t\n\r\0\x0B.,;:") . '…';
        }

        $parts   = [];
        $parts[] = 'Offer for the position of ' . (string) ($offer['position'] ?? 'the advertised role');
        if (!empty($offer['programme_name'])) {
            $parts[] = 'in the ' . (string) $offer['programme_name'];
        }
        if (!empty($offer['cohort_name'])) {
            $parts[] = '(' . (string) $offer['cohort_name'] . ')';
        }
        $parts[] = 'at ' . self::organisation($offer) . '.';

        if (!empty($offer['start_date'])) {
            $parts[] = 'Expected start date: ' . format_date((string) $offer['start_date'], 'd M Y') . '.';
        }
        if (trim((string) ($offer['compensation'] ?? '')) !== '') {
            $parts[] = 'Compensation: ' . trim((string) $offer['compensation']) . '.';
        }

        return implode(' ', $parts);
    }

    // --------------------------------------------------------
    // RESPONSE WORKFLOW
    // --------------------------------------------------------

    /**
     * Accept or decline an offer on behalf of the authenticated candidate.
     * The heavy lifting (ownership, deadline and status validation, shared
     * status workflow) lives in CandidateOffer::respond().
     *
     * @param int    $offerId
     * @param int    $candidateId
     * @param string $response 'accept' | 'decline' | 'accepted' | 'declined'
     * @param string $reason   Optional decline reason
     * @return array{success:bool,message:string,status:string,label:string}
     */
    public static function recordResponse(int $offerId, int $candidateId, string $response, string $reason = ''): array
    {
        $map    = ['accept' => 'accepted', 'decline' => 'declined'];
        $normal = $map[strtolower(trim($response))] ?? strtolower(trim($response));

        if (!in_array($normal, ['accepted', 'declined'], true)) {
            return [
                'success' => false,
                'message' => 'Invalid offer response.',
                'status'  => '',
                'label'   => '',
            ];
        }

        try {
            $result = CandidateOffer::respond($offerId, $candidateId, $normal, $reason);

            return [
                'success' => true,
                'message' => $normal === 'accepted'
                    ? 'Thank you — your acceptance has been recorded. The programme team will be in touch about your placement.'
                    : 'Your decline has been recorded. Thank you for letting the programme team know.',
                'status'  => (string) $result['status'],
                'label'   => (string) $result['label'],
            ];
        } catch (RuntimeException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'status'  => '',
                'label'   => '',
            ];
        } catch (Throwable $e) {
            error_log('[CandidateOffersController] recordResponse failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'We could not save your response. Please try again.',
                'status'  => '',
                'label'   => '',
            ];
        }
    }

    /**
     * Response information already stored on an offer (accept/decline date
     * and the decline reason when one was provided).
     *
     * @return array{responded:bool,status:string,label:string,responded_at:?string,decline_reason:?string}
     */
    public static function responseInfo(array $offer): array
    {
        $status = (string) ($offer['status'] ?? '');

        return [
            'responded'      => in_array($status, ['accepted', 'declined'], true),
            'status'         => $status,
            'label'          => self::statusLabel($status),
            'responded_at'   => $offer['responded_at'] ?? null,
            'decline_reason' => !empty($offer['decline_reason']) ? (string) $offer['decline_reason'] : null,
        ];
    }

    /**
     * Offer status audit trail (the same history the administrator sees).
     * This is the OFFER history only — internal selection notes are never
     * exposed to candidates.
     */
    public static function history(int $offerId): array
    {
        try {
            return Selection::offerHistory($offerId);
        } catch (Throwable $e) {
            error_log('[CandidateOffersController] history failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Notes recorded by the administrator on the offer workflow
     * (offer_status_history.change_reason entries).
     *
     * @return array<int,array{label:string,reason:string,at:?string}>
     */
    public static function adminNotes(array $offer): array
    {
        $notes = [];
        foreach (self::history((int) ($offer['id'] ?? 0)) as $entry) {
            $reason = trim((string) ($entry['change_reason'] ?? ''));
            if ($reason === '') {
                continue;
            }

            // A candidate decline note is the candidate's own input, not an
            // administrator note — keep it out of this list.
            if (stripos($reason, 'Declined by candidate') === 0) {
                continue;
            }

            $notes[] = [
                'label'  => self::statusLabel((string) ($entry['new_status'] ?? '')),
                'reason' => $reason,
                'at'     => $entry['created_at'] ?? null,
            ];
        }

        return array_reverse($notes);
    }

    /**
     * Documents attached to the offer's application, served through the
     * existing secure candidate document endpoint.
     */
    public static function attachments(int $applicationId): array
    {
        if ($applicationId <= 0) {
            return [];
        }

        try {
            return ApplicationDocument::forApplication($applicationId);
        } catch (Throwable $e) {
            error_log('[CandidateOffersController] attachments failed: ' . $e->getMessage());
            return [];
        }
    }

    // --------------------------------------------------------
    // NOTIFICATIONS
    // --------------------------------------------------------

    /**
     * Offer notifications for the authenticated candidate.
     */
    public static function notifications(int $candidateId, int $limit = 6, bool $unreadOnly = false): array
    {
        return CandidateOffer::notificationsForCandidate($candidateId, $limit, $unreadOnly);
    }

    /**
     * Number of unread offer notifications.
     */
    public static function unreadNotifications(int $candidateId): int
    {
        return CandidateOffer::unreadNotificationCount($candidateId);
    }

    /**
     * Mark one notification as read (owner-scoped).
     */
    public static function markNotificationRead(int $notificationId, int $candidateId): bool
    {
        return CandidateOffer::markNotificationRead($notificationId, $candidateId);
    }

    /**
     * Mark all of the candidate's offer notifications as read.
     *
     * @return int Rows updated
     */
    public static function markAllNotificationsRead(int $candidateId): int
    {
        return CandidateOffer::markAllNotificationsRead($candidateId);
    }

    /**
     * Icon tone + Font Awesome icon for a notification type (reusing the
     * existing .notif-item design-system tones).
     *
     * @return array{tone:string,icon:string}
     */
    public static function notificationStyle(string $type): array
    {
        return [
            'tone' => self::NOTIFICATION_TONES[$type] ?? 'offer',
            'icon' => self::NOTIFICATION_ICONS[$type] ?? 'fa-bell',
        ];
    }
}
