<?php
/**
 * ================================================
 * INVESTHOOD IT - Application Response Model
 * ================================================
 * Stores candidate responses to opportunity questions
 * against a specific application. Responses belong to
 * the candidate's application, never the profile.
 */

class ApplicationResponse
{
    /**
     * Get all responses for an application, keyed by question_id.
     *
     * @param int $applicationId
     * @return array  [question_id => response]
     */
    public static function forApplication(int $applicationId): array
    {
        $rows = Database::fetchAll(
            "SELECT question_id, response
             FROM application_responses
             WHERE application_id = ?
             ORDER BY id ASC",
            'i',
            [$applicationId]
        );

        $responses = [];
        foreach ($rows as $row) {
            $responses[(int) $row['question_id']] = $row['response'];
        }

        return $responses;
    }

    /**
     * Save a response for a question on an application.
     * Uses INSERT ... ON DUPLICATE KEY UPDATE to upsert.
     *
     * @param int    $applicationId
     * @param int    $questionId
     * @param string $response
     * @return bool
     */
    public static function save(int $applicationId, int $questionId, string $response): bool
    {
        return Database::execute(
            "INSERT INTO application_responses (application_id, question_id, response)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE response = VALUES(response), updated_at = NOW()",
            'iis',
            [$applicationId, $questionId, $response]
        ) >= 0;
    }

    /**
     * Save multiple responses for an application in a transaction.
     *
     * @param int   $applicationId
     * @param array $responses  [question_id => response]
     * @return bool
     */
    public static function saveMany(int $applicationId, array $responses): bool
    {
        if (empty($responses)) {
            return true;
        }

        Database::beginTransaction();

        try {
            foreach ($responses as $questionId => $response) {
                $questionId = (int) $questionId;
                if ($questionId <= 0) {
                    continue;
                }

                // Verify the question exists and belongs to the same opportunity
                $question = ApplicationQuestion::find($questionId);
                if (!$question) {
                    continue;
                }

                self::save($applicationId, $questionId, (string) $response);
            }

            Database::commit();
            return true;
        } catch (Throwable $ex) {
            Database::rollback();
            error_log('[ApplicationResponse] Failed to save responses: ' . $ex->getMessage());
            return false;
        }
    }

    /**
     * Delete a response for a question on an application.
     *
     * @param int $applicationId
     * @param int $questionId
     * @return bool
     */
    public static function delete(int $applicationId, int $questionId): bool
    {
        return Database::execute(
            "DELETE FROM application_responses WHERE application_id = ? AND question_id = ?",
            'ii',
            [$applicationId, $questionId]
        ) > 0;
    }

    /**
     * Delete all responses for an application.
     *
     * @param int $applicationId
     * @return bool
     */
    public static function deleteForApplication(int $applicationId): bool
    {
        return Database::execute(
            "DELETE FROM application_responses WHERE application_id = ?",
            'i',
            [$applicationId]
        ) >= 0;
    }
}