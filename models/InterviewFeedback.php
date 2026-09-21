<?php
/** INVESTHOOD IT - Interview Feedback Decision Module */
class InterviewFeedback
{
    public const OUTCOMES = ['selected', 'waitlisted', 'rejected', 'further_review', 'another_interview'];
    public const LABELS = [
        'selected' => 'Selected',
        'waitlisted' => 'Waitlisted',
        'rejected' => 'Rejected',
        'further_review' => 'Further Review',
        'another_interview' => 'Recommended for Another Interview',
    ];
    public const TONES = [
        'selected' => 'green',
        'waitlisted' => 'amber',
        'rejected' => 'danger',
        'further_review' => 'primary',
        'another_interview' => 'primary',
    ];
    public const STATUS_MAP = [
        'selected' => 'selected',
        'waitlisted' => 'waitlisted',
        'rejected' => 'rejected',
        'further_review' => 'under_review',
        'another_interview' => 'interview_required',
    ];
    public static function label(?string $o): string { return self::LABELS[$o ?? ''] ?? '—'; }
    public static function tone(?string $o): string { return self::TONES[$o ?? ''] ?? 'muted'; }
    public static function targetStatus(?string $o): ?string { return self::STATUS_MAP[$o ?? ''] ?? null; }
    public static function validate(array $d): array {
        $errors = [];
        if (!in_array(trim((string)($d['outcome'] ?? '')), self::OUTCOMES, true)) {
            $errors['outcome'] = 'Please select an interview outcome/decision.';
        }
        if (trim((string)($d['general_feedback'] ?? '')) === '') {
            $errors['general_feedback'] = 'General interview feedback is required.';
        }
        $raw = $d['rating'] ?? null;
        if ($raw !== null && $raw !== '') {
            $r = (int)$raw;
            if ($r < 1 || $r > 5) { $errors['rating'] = 'Rating must be between 1 and 5.'; }
        }
        return $errors;
    }
    public static function sanitise(array $d): array {
        $raw = $d['rating'] ?? null;
        return [
            'outcome' => trim((string)($d['outcome'] ?? '')),
            'rating' => ($raw === '' || $raw === null) ? null : (int)$raw,
            'strengths' => trim((string)($d['strengths'] ?? '')),
            'areas_of_concern' => trim((string)($d['areas_of_concern'] ?? '')),
            'general_feedback' => trim((string)($d['general_feedback'] ?? '')),
            'internal_notes' => trim((string)($d['internal_notes'] ?? '')),
        ];
    }
    public static function save(int $interviewId, array $clean, int $adminId): array {
        $interview = Interview::find($interviewId);
        if (!$interview) { throw new RuntimeException('Interview not found.'); }
        if (($interview['status'] ?? '') !== 'completed') {
            throw new RuntimeException('Feedback can only be recorded for completed interviews.');
        }
        $applicationId = (int)($interview['application_id'] ?? 0);
        Interview::ensureFeedbackColumns();
        $cols = Interview::feedbackColumns();
        $existing = Interview::getFeedback($interviewId);
        $has = function (string $c) use ($cols): bool { return in_array($c, $cols, true); };
        if (!$has('interview_id') || !$has('application_id')) {
            throw new RuntimeException('Interview feedback storage is unavailable. Please contact support.');
        }
        Database::beginTransaction();
        try {
            if ($existing) {
                $sets = []; $params = []; $types = '';
                if ($has('application_id')) { $sets[] = '`application_id` = ?'; $params[] = $applicationId; $types .= 'i'; }
                if ($has('admin_id')) { $sets[] = '`admin_id` = ?'; $params[] = $adminId; $types .= 'i'; }
                if ($has('interviewer_id')) { $sets[] = '`interviewer_id` = ?'; $params[] = $adminId; $types .= 'i'; }
                if ($has('outcome')) { $sets[] = '`outcome` = ?'; $params[] = $clean['outcome']; $types .= 's'; }
                if ($has('overall_rating')) {
                    if ($clean['rating'] !== null) { $sets[] = '`overall_rating` = ?'; $params[] = $clean['rating']; $types .= 'i'; }
                    else { $sets[] = '`overall_rating` = NULL'; }
                }
                if ($has('recommendation')) { $sets[] = '`recommendation` = ?'; $params[] = $clean['outcome']; $types .= 's'; }
                if ($has('submitted_at')) { $sets[] = '`submitted_at` = NOW()'; }
                if ($sets !== []) {
                    $params[] = (int)$existing['id']; $types .= 'i';
                    Database::execute('UPDATE interview_feedback SET ' . implode(', ', $sets) . ' WHERE id = ?', $types, $params);
                }
                $fid = (int)$existing['id'];
                self::saveTexts($fid, $clean, $has);
                $isUpdate = true;
            } else {
                $ic = ['interview_id', 'application_id']; $pl = ['?', '?']; $params2 = [$interviewId, $applicationId]; $types2 = 'ii';
                if ($has('admin_id')) { $ic[] = 'admin_id'; $pl[] = '?'; $params2[] = $adminId; $types2 .= 'i'; }
                if ($has('interviewer_id')) { $ic[] = 'interviewer_id'; $pl[] = '?'; $params2[] = $adminId; $types2 .= 'i'; }
                if ($has('outcome')) { $ic[] = 'outcome'; $pl[] = '?'; $params2[] = $clean['outcome']; $types2 .= 's'; }
                if ($has('overall_rating')) {
                    if ($clean['rating'] !== null) { $ic[] = 'overall_rating'; $pl[] = '?'; $params2[] = $clean['rating']; $types2 .= 'i'; }
                    else { $ic[] = 'overall_rating'; $pl[] = 'NULL'; }
                }
                if ($has('recommendation')) { $ic[] = 'recommendation'; $pl[] = '?'; $params2[] = $clean['outcome']; $types2 .= 's'; }
                Database::execute(
                    'INSERT INTO interview_feedback (`' . implode('`, `', $ic) . '`) VALUES (' . implode(', ', $pl) . ')
                     ON DUPLICATE KEY UPDATE application_id = VALUES(application_id)',
                    $types2, $params2
                );
                $fresh = Interview::getFeedback($interviewId);
                $fid = $fresh ? (int)$fresh['id'] : Database::lastInsertId();
                self::saveTexts($fid, $clean, $has);
                $isUpdate = false;
            }
            $target = self::targetStatus($clean['outcome']);
            $changed = false;
            if ($target && $applicationId > 0) {
                try { $changed = Application::updateStatus($applicationId, $target, $adminId, 'Interview feedback: ' . self::label($clean['outcome'])); }
                catch (Exception $e) { error_log('[Feedback] status sync skipped: ' . $e->getMessage()); }
            }
            Database::commit();
            return ['feedback_id' => $fid, 'is_update' => $isUpdate, 'outcome' => $clean['outcome'], 'target_status' => $target, 'status_changed' => $changed];
        } catch (Exception $e) {
            Database::rollback();
            if ($e instanceof RuntimeException) { throw $e; }
            throw new RuntimeException('Failed to save interview feedback: ' . $e->getMessage());
        }
    }

    private static function saveTexts(int $fid, array $clean, callable $has): void {
        if ($fid <= 0) { return; }
        $sets = []; $params = []; $types = '';
        $map = [
            'strengths' => $clean['strengths'] !== '' ? $clean['strengths'] : null,
            'areas_for_improvement' => $clean['areas_of_concern'] !== '' ? $clean['areas_of_concern'] : null,
            'areas_of_concern' => $clean['areas_of_concern'] !== '' ? $clean['areas_of_concern'] : null,
            'general_comments' => $clean['general_feedback'],
            'general_feedback' => $clean['general_feedback'],
            'internal_notes' => $clean['internal_notes'] !== '' ? $clean['internal_notes'] : null,
        ];
        foreach ($map as $col => $val) {
            if ($has($col)) { $sets[] = "`{$col}` = ?"; $params[] = $val; $types .= 's'; }
        }
        if ($sets === []) { return; }
        $params[] = $fid; $types .= 'i';
        Database::execute('UPDATE interview_feedback SET ' . implode(', ', $sets) . ' WHERE id = ?', $types, $params);
    }
}
