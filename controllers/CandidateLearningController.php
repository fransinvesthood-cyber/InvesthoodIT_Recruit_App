<?php
/**
 * ================================================
 * INVESTHOOD IT - Candidate Learning Controller
 * ================================================
 * Business logic for the candidate-facing Learning & Skills module:
 * listing assigned programmes, viewing a course + its resources, marking
 * lessons complete, and surfacing skills/certifications context.
 *
 * All data access is delegated to LearningProgramme, which scopes every
 * query to the authenticated candidate. The controller never trusts an id
 * coming from the browser.
 */

class CandidateLearningController
{
    public const PER_PAGE = 12;

    /** Notification icon tone per notification type */
    public const NOTIFICATION_TONES = [
        'assignment_created' => 'account',
        'assignment_updated' => 'account',
        'deadline_approaching' => 'application',
        'assignment_completed' => 'success',
        'certificate_earned' => 'success',
    ];

    public const NOTIFICATION_ICONS = [
        'assignment_created' => 'fa-book-open',
        'assignment_updated' => 'fa-arrows-alt',
        'deadline_approaching' => 'fa-hourglass-half',
        'assignment_completed' => 'fa-check-circle',
        'certificate_earned' => 'fa-file-signature',
    ];

    /**
     * Normalise filters from $_GET.
     */
    public static function normaliseFilters(array $input): array
    {
        $search   = trim((string) ($input['search'] ?? ''));
        $category = trim((string) ($input['category'] ?? ''));
        $status   = strtolower(trim((string) ($input['status'] ?? 'all')));
        if ($status !== 'all' && !in_array($status, LearningProgramme::ALL_STATUSES, true)) {
            $status = 'all';
        }
        $sort = in_array((string) ($input['sort'] ?? 'assigned_at'), ['title', 'due_date', 'assigned_at'], true)
            ? (string) ($input['sort'] ?? 'assigned_at')
            : 'assigned_at';
        return [
            'search'  => $search,
            'category' => $category,
            'status'  => $status,
            'sort'    => $sort,
        ];
    }

    /**
     * List assignments for the authenticated candidate with computed status.
     */
    public static function index(int $candidateId, array $filters = []): array
    {
        $clean = self::normaliseFilters($filters);
        $rows = LearningProgramme::forCandidate($candidateId, $clean);

        $totalProgress = 0;
        foreach ($rows as $r) {
            $totalProgress += (int) $r['progress'];
        }
        $overall = $rows ? (int) round($totalProgress / count($rows)) : 0;

        return [
            'assignments' => $rows,
            'stats' => LearningProgramme::candidateStats($candidateId),
            'overall_progress' => $overall,
            'filters' => $clean,
        ];
    }

    /**
     * Show a single course (programme) with its lessons and progress.
     */
    public static function show(int $candidateId, int $assignmentId): array
    {
        $assignment = LearningProgramme::findAssignmentForCandidate($assignmentId, $candidateId);
        if (!$assignment) {
            return ['error' => 'Course not found or access denied.'];
        }

        $lessons = LearningProgramme::lessonsForProgramme((int) $assignment['programme_id']);

        $completed = [];
        $conn = Database::getConnection();
        $stmt = $conn->prepare(
            "SELECT lesson_id FROM candidate_lesson_progress
             WHERE assignment_id = ? AND candidate_id = ? AND completed = 1"
        );
        if ($stmt) {
            $stmt->bind_param('ii', $assignmentId, $candidateId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) {
                $completed[(int) $r['lesson_id']] = true;
            }
            $stmt->close();
        }

        return [
            'assignment' => $assignment,
            'lessons' => $lessons,
            'completed_lessons' => $completed,
        ];
    }

    /**
     * Start (resume) an assignment.
     */
    public static function start(int $candidateId, int $assignmentId): array
    {
        $assignment = LearningProgramme::findAssignmentForCandidate($assignmentId, $candidateId);
        if (!$assignment) {
            return ['success' => false, 'message' => 'Course not found.'];
        }
        if (!LearningProgramme::start($assignmentId, $candidateId)) {
            return ['success' => false, 'message' => 'Unable to start this course.'];
        }
        set_flash('success', 'Course Started', 'You\'re now working on this course.');
        return ['success' => true, 'redirect' => 'learning.php'];
    }

    /**
     * Mark a lesson complete (AJAX endpoint).
     */
    public static function markLessonComplete(int $candidateId, int $assignmentId, int $lessonId): array
    {
        if (!is_logged_in() || (int) current_user_id() !== $candidateId) {
            return ['success' => false, 'message' => 'Unauthenticated.'];
        }
        $result = LearningProgramme::markLessonComplete($assignmentId, $lessonId, $candidateId);
        if ($result === null) {
            return ['success' => false, 'message' => 'Failed to update progress.'];
        }

        // Resolve the assignment row so the notification can name the course.
        $assignment = LearningProgramme::findAssignmentForCandidate($assignmentId, $candidateId);

        try {
            CandidateNotification::addLearningNotification(
                $candidateId,
                'assignment_completed',
                'Lesson completed',
                'One of your lessons in ' . ($assignment['title'] ?? 'the course') . ' is complete.',
                $assignmentId,
                'lesson_complete_' . $assignmentId . '_' . $lessonId
            );
        } catch (Throwable $e) {
            error_log('[Learning] notification: ' . $e->getMessage());
        }

        return ['success' => true] + $result;
    }

    /**
     * Skills view data for the candidate.
     */
    public static function skillsView(int $userId): array
    {
        return [
            'selfReported' => Skill::forUser($userId),
            'verifiedCount' => Skill::countVerifiedForUser($userId),
            'totalSkills' => count(Skill::forUser($userId)),
        ];
    }

    /**
     * Certifications view data (reuses the Certification model).
     */
    public static function certificationsView(int $userId): array
    {
        $certs = Certification::forUser($userId);
        $currentDate = new DateTime('now', new DateTimeZone(date_default_timezone_get()));
        foreach ($certs as &$c) {
            $c['expired'] = false;
            if (!empty($c['expiry_date'])) {
                $expiry = new DateTime($c['expiry_date'], new DateTimeZone(date_default_timezone_get()));
                if ($expiry < $currentDate) {
                    $c['expired'] = true;
                }
            }
        }
        unset($c);
        return ['certificates' => $certs];
    }

    /**
     * Learning notifications for the header dropdown.
     */
    public static function notifications(int $candidateId, int $limit = 6, bool $unreadOnly = false): array
    {
        $q = $unreadOnly ? ['status' => 'unread'] : [];
        $records = CandidateNotification::forLearning($candidateId, $limit);
        $unread = CandidateNotification::forLearning($candidateId, 1000);
        $unreadCount = 0;
        foreach ($unread as $n) {
            if ((int) $n['is_read'] === 0) {
                $unreadCount++;
            }
        }
        return [
            'records' => $records,
            'unread' => $unreadCount,
        ];
    }
}
