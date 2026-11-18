<?php

class ProgrammeOfficerNotification
{
    public static function forDeliverableSubmission(int $submissionId): void
    {
        if ($submissionId <= 0) {
            return;
        }

        try {
            $conn = Database::getConnection();
            $table = $conn->query("SHOW TABLES LIKE 'programme_officer_notifications'");
            if (!$table) {
                throw new RuntimeException('Unable to check notification schema: ' . $conn->error);
            }
            if ($table->num_rows === 0) {
                error_log('[ProgrammeOfficerNotification] Notification table is not installed.');
                return;
            }

            $stmt = $conn->prepare("
                SELECT d.id AS deliverable_id, d.programme_id, d.cohort_id, d.title,
                       CONCAT_WS(' ', u.first_name, u.last_name) AS candidate_name
                FROM deliverable_submissions s
                INNER JOIN programme_deliverables d ON d.id = s.deliverable_id
                INNER JOIN users u ON u.id = s.candidate_id
                WHERE s.id = ?
                LIMIT 1
            ");
            if (!$stmt) {
                throw new RuntimeException('Unable to load submitted deliverable: ' . $conn->error);
            }
            $stmt->bind_param('i', $submissionId);
            $stmt->execute();
            $submission = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$submission) {
                error_log('[ProgrammeOfficerNotification] Submission not found: ' . $submissionId);
                return;
            }

            $officers = self::eligibleOfficers(
                $conn,
                (int) $submission['programme_id'],
                $submission['cohort_id'] !== null ? (int) $submission['cohort_id'] : null
            );
            if (!$officers) {
                error_log('[ProgrammeOfficerNotification] No Programme Officers found for submission: ' . $submissionId);
                return;
            }

            $candidateName = trim((string) $submission['candidate_name']);
            if ($candidateName === '') {
                $candidateName = 'A candidate';
            }
            $title = 'Deliverable submitted';
            $message = $candidateName . ' submitted "' . (string) $submission['title'] . '". Review the submission.';
            $stmt = $conn->prepare("
                INSERT IGNORE INTO programme_officer_notifications
                    (programme_officer_id, deliverable_id, submission_id, title, message)
                VALUES (?, ?, ?, ?, ?)
            ");
            if (!$stmt) {
                throw new RuntimeException('Unable to prepare Programme Officer notification: ' . $conn->error);
            }
            $deliverableId = (int) $submission['deliverable_id'];
            foreach ($officers as $officerId) {
                $officerId = (int) $officerId;
                $stmt->bind_param('iiiss', $officerId, $deliverableId, $submissionId, $title, $message);
                if (!$stmt->execute()) {
                    error_log('[ProgrammeOfficerNotification] Unable to create notification: ' . $stmt->error);
                }
            }
            $stmt->close();
        } catch (Throwable $exception) {
            error_log('[ProgrammeOfficerNotification] Notification failed: ' . $exception->getMessage());
        }
    }

    private static function eligibleOfficers(mysqli $conn, int $programmeId, ?int $cohortId): array
    {
        $roleJoin = "INNER JOIN roles r ON r.id = u.role_id";
        $activeFilter = "r.slug = 'programme_officer' AND u.status = 'active'";
        if (self::hasColumn($conn, 'programmes', 'programme_officer_id')) {
            $sql = "
                SELECT DISTINCT u.id
                FROM users u
                {$roleJoin}
                INNER JOIN programmes p ON p.programme_officer_id = u.id
                WHERE {$activeFilter} AND p.id = ?
            ";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new RuntimeException('Unable to find assigned Programme Officers: ' . $conn->error);
            }
            $stmt->bind_param('i', $programmeId);
        } elseif (self::hasColumn($conn, 'cohorts', 'programme_officer_id')) {
            $sql = "
                SELECT DISTINCT u.id
                FROM users u
                {$roleJoin}
                INNER JOIN cohorts c ON c.programme_officer_id = u.id
                WHERE {$activeFilter}
                  AND c.programme_id = ?
                  AND (? IS NULL OR c.id = ?)
            ";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new RuntimeException('Unable to find assigned Programme Officers: ' . $conn->error);
            }
            $stmt->bind_param('iii', $programmeId, $cohortId, $cohortId);
        } else {
            $stmt = $conn->prepare("
                SELECT u.id
                FROM users u
                {$roleJoin}
                WHERE {$activeFilter}
            ");
            if (!$stmt) {
                throw new RuntimeException('Unable to find Programme Officers: ' . $conn->error);
            }
        }

        $stmt->execute();
        $officerIds = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id');
        $stmt->close();
        return $officerIds;
    }

    private static function hasColumn(mysqli $conn, string $table, string $column): bool
    {
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS total
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");
        if (!$stmt) {
            throw new RuntimeException('Unable to inspect Programme Officer assignment schema: ' . $conn->error);
        }
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        $exists = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0) > 0;
        $stmt->close();
        return $exists;
    }
}
