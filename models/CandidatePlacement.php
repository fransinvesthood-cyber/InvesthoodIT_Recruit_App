<?php
/**
 * INVESTHOOD IT - Candidate Placement Model
 * Candidate-facing data access for admin-managed placements (Stage 12).
 * Ownership enforced in SQL via placements.candidate_id.
 * placements.notes is ADMIN ONLY and never selected here.
 */
class CandidatePlacement
{
    public const STATUSES = ['pending_placement','placement_in_progress','placed','active','completed','withdrawn','cancelled'];
    public const STATUS_LABELS = [
        'pending_placement' => 'Placement Pending',
        'placement_in_progress' => 'Active',
        'placed' => 'Placement Confirmed',
        'active' => 'Active',
        'completed' => 'Completed',
        'withdrawn' => 'Withdrawn',
        'cancelled' => 'Cancelled',
    ];
    public const STATUS_TONES = [
        'pending_placement' => 'pending',
        'placement_in_progress' => 'active',
        'placed' => 'confirmed',
        'active' => 'active',
        'completed' => 'completed',
        'withdrawn' => 'withdrawn',
        'cancelled' => 'cancelled',
    ];
    public const STAGES = ['application'=>1,'selection'=>2,'offer'=>3,'placement'=>4];
    public const STAGE_LABELS = ['application'=>'Application','selection'=>'Selection','offer'=>'Offer','placement'=>'Placement'];

    private static function baseSelect(): string
    {
        return "SELECT p.id, p.placement_reference, p.candidate_id,"
            . " p.application_id, p.offer_id, p.programme_id, p.cohort_id,"
            . " p.department, p.location, p.supervisor_id,"
            . " p.start_date, p.end_date, p.status, p.created_at, p.updated_at,"
            . " pr.name AS programme_name, pr.type AS programme_type,"
            . " c.name AS cohort_name,"
            . " o.position AS offer_position, o.title AS offer_title, o.status AS offer_status,"
            . " opp.title AS opportunity_title, opp.organisation AS organisation_name,"
            . " TRIM(CONCAT(IFNULL(sup.first_name,''),' ',IFNULL(sup.last_name,''))) AS supervisor_name"
            . " FROM placements p"
            . " LEFT JOIN programmes pr ON pr.id = p.programme_id"
            . " LEFT JOIN cohorts c ON c.id = p.cohort_id"
            . " LEFT JOIN offers o ON o.id = p.offer_id"
            . " LEFT JOIN applications ap ON ap.id = p.application_id"
            . " LEFT JOIN opportunities opp ON opp.id = ap.opportunity_id"
            . " LEFT JOIN users sup ON sup.id = p.supervisor_id";
    }

    public static function currentForCandidate(int $candidateId): ?array
    {
        if ($candidateId <= 0) return null;
        try {
            return Database::fetchOne(
                self::baseSelect() . " WHERE p.candidate_id = ?"
                . " ORDER BY CASE WHEN p.status IN ('active','placement_in_progress','placed') THEN 0"
                . " WHEN p.status='pending_placement' THEN 1 WHEN p.status='completed' THEN 2 ELSE 3 END,"
                . " p.updated_at DESC, p.id DESC LIMIT 1",
                'i', [$candidateId]
            );
        } catch (Throwable $e) {
            error_log('[CandidatePlacement] current failed: ' . $e->getMessage());
            return null;
        }
    }

    public static function findForCandidate(int $placementId, int $candidateId): ?array
    {
        if ($placementId <= 0 || $candidateId <= 0) return null;
        try {
            return Database::fetchOne(
                self::baseSelect() . " WHERE p.id = ? AND p.candidate_id = ? LIMIT 1",
                'ii', [$placementId, $candidateId]
            );
        } catch (Throwable $e) {
            error_log('[CandidatePlacement] find failed: ' . $e->getMessage());
            return null;
        }
    }

    public static function allForCandidate(int $candidateId, int $limit = 5): array
    {
        if ($candidateId <= 0) return [];
        $limit = max(1, min(20, $limit));
        try {
            return Database::fetchAll(
                self::baseSelect() . " WHERE p.candidate_id = ?"
                . " ORDER BY p.updated_at DESC, p.id DESC LIMIT " . (int)$limit,
                'i', [$candidateId]
            );
        } catch (Throwable $e) { return []; }
    }

    public static function stageForCandidate(int $candidateId, ?array $placement = null): array
    {
        $stage = ['stage'=>1,'key'=>'application','label'=>'Application','offer_status'=>null,'selected'=>false,'has_application'=>false];
        if ($candidateId <= 0) return $stage;
        try {
            if ($placement === null) $placement = self::currentForCandidate($candidateId);
            $status = (string)($placement['status'] ?? '');
            if ($placement !== null && !in_array($status, ['cancelled','withdrawn'], true)) {
                $stage['stage'] = 4; $stage['key'] = 'placement'; $stage['label'] = 'Placement';
                $stage['offer_status'] = isset($placement['offer_status']) ? (string)$placement['offer_status'] : null;
                $stage['selected'] = true; $stage['has_application'] = true;
                return $stage;
            }
            $offer = Database::fetchOne(
                "SELECT status FROM offers WHERE candidate_id = ? AND status != 'draft' ORDER BY updated_at DESC, id DESC LIMIT 1",
                'i', [$candidateId]
            );
            if ($offer !== null) {
                $os = (string)($offer['status'] ?? '');
                $stage['offer_status'] = $os; $stage['selected'] = true; $stage['has_application'] = true;
                if (in_array($os, ['issued','accepted','expired','withdrawn','declined'], true)) {
                    $stage['stage'] = 3; $stage['key'] = 'offer'; $stage['label'] = 'Offer';
                    return $stage;
                }
            }
            $decision = null; $app = null;
            try {
                $decision = Database::fetchOne(
                    "SELECT decision FROM selection_decisions WHERE candidate_id = ? ORDER BY decided_at DESC, id DESC LIMIT 1",
                    'i', [$candidateId]
                );
            } catch (Throwable $e) { $decision = null; }
            try {
                $app = Database::fetchOne(
                    "SELECT status FROM applications WHERE candidate_id = ? ORDER BY updated_at DESC, id DESC LIMIT 1",
                    'i', [$candidateId]
                );
            } catch (Throwable $e) { $app = null; }
            if ($app !== null) $stage['has_application'] = true;
            $appStatus = (string)($app['status'] ?? '');
            $dec = (string)($decision['decision'] ?? '');
            if ($dec === 'selected' || in_array($appStatus, ['selected','offer_sent','offer_accepted','offer_declined'], true)) {
                $stage['selected'] = true;
                $stage['stage'] = 2; $stage['key'] = 'selection'; $stage['label'] = 'Selection';
                return $stage;
            }
            if (in_array($dec, ['waitlisted','not_selected'], true)
                || in_array($appStatus, ['interview_completed','on_hold','waitlisted'], true)) {
                $stage['stage'] = 2; $stage['key'] = 'selection'; $stage['label'] = 'Selection';
                return $stage;
            }
            return $stage;
        } catch (Throwable $e) { return $stage; }
    }

    public static function statusLabel(?string $status): string
    {
        if ($status === null || $status === '') return 'Not Yet Placed';
        return self::STATUS_LABELS[$status] ?? ucwords(str_replace('_', ' ', $status));
    }

    public static function statusTone(?string $status): string
    {
        return self::STATUS_TONES[$status ?? ''] ?? 'pending';
    }

    public static function roleFor(array $placement): ?string
    {
        foreach (['offer_position','offer_title','opportunity_title','department'] as $k) {
            $v = trim((string)($placement[$k] ?? ''));
            if ($v !== '') return $v;
        }
        return null;
    }

    public static function organisationFor(array $placement): ?string
    {
        $v = trim((string)($placement['organisation_name'] ?? ''));
        return $v !== '' ? $v : null;
    }

    public static function durationLabel(?string $startDate, ?string $endDate): string
    {
        if (empty($startDate) || empty($endDate)) return '—';
        try {
            $s = new DateTime($startDate); $en = new DateTime($endDate);
            if ($en < $s) return '—';
            $days = (int)$s->diff($en)->days;
            if ($days < 30) return $days . ($days === 1 ? ' Day' : ' Days');
            $m = (int)round($days / 30.44);
            return $m . ($m === 1 ? ' Month' : ' Months');
        } catch (Throwable $e) { return '—'; }
    }

    public static function daysUntilStart(?array $placement): ?int
    {
        $start = (string)($placement['start_date'] ?? '');
        if ($start === '') return null;
        try {
            $today = new DateTime('today'); $sd = new DateTime($start);
            if ($sd <= $today) return null;
            return (int)$today->diff($sd)->days;
        } catch (Throwable $e) { return null; }
    }

    public static function progressForStage(string $stageKey, ?string $placementStatus = null): int
    {
        if ($stageKey === 'placement') return $placementStatus === 'pending_placement' ? 90 : 100;
        if ($stageKey === 'offer') return 75;
        if ($stageKey === 'selection') return 50;
        return 25;
    }

    public static function formatDay(string $date): string
    {
        if (function_exists('format_date')) return format_date($date, 'd M Y');
        try { return (new DateTime($date))->format('d M Y'); }
        catch (Throwable $e) { return $date; }
    }

    public static function statusMessage(?array $placement): array
    {
        if ($placement === null) {
            return ['tone'=>'info','message'=>'No placement has been assigned yet. Once the programme team places you, your organisation, role and dates will appear here.'];
        }
        $status = (string)($placement['status'] ?? '');
        $start = (string)($placement['start_date'] ?? '');
        $end = (string)($placement['end_date'] ?? '');
        $sl = $start !== '' ? self::formatDay($start) : '';
        $el = $end !== '' ? self::formatDay($end) : '';
        if ($status === 'pending_placement') {
            return ['tone'=>'pending','message'=>'Your placement is still being processed by the programme team.'
                . ($sl !== '' ? ' Placement starts on ' . $sl . '.' : '')];
        }
        if ($status === 'placed') {
            return ['tone'=>'success','message'=>'Your placement is confirmed.'
                . ($sl !== '' ? ' Placement starts on ' . $sl . '.' : '')];
        }
        if ($status === 'active' || $status === 'placement_in_progress') {
            return ['tone'=>'success','message'=>'Placement in progress — you are currently placed.'];
        }
        if ($status === 'completed') {
            return ['tone'=>'muted','message'=>'Placement completed' . ($el !== '' ? ' on ' . $el . '.' : '.')];
        }
        if ($status === 'withdrawn') {
            return ['tone'=>'muted','message'=>'This placement was withdrawn. Please contact the programme team if you need assistance.'];
        }
        if ($status === 'cancelled') {
            return ['tone'=>'danger','message'=>'This placement was cancelled. Please contact the programme team if you need assistance.'];
        }
        return ['tone'=>'info','message'=>'Your placement status is ' . self::statusLabel($status) . '.'];
    }
}
