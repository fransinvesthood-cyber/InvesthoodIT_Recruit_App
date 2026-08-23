<?php
/**
 * ================================================
 * INVESTHOOD IT - Application Question Model
 * ================================================
 * Configurable eligibility and application questions
 * associated with an opportunity. Questions belong to
 * the opportunity configuration, not to each application.
 */

class ApplicationQuestion
{
    /**
     * Get all questions for an opportunity, grouped by section.
     *
     * @param int $opportunityId
     * @return array ['eligibility' => [...], 'application' => [...]]
     */
    public static function forOpportunity(int $opportunityId): array
    {
        $rows = Database::fetchAll(
            "SELECT id, opportunity_id, section, question_text, question_type,
                    options, is_required, is_knockout, sort_order
             FROM opportunity_questions
             WHERE opportunity_id = ?
             ORDER BY section, sort_order ASC, id ASC",
            'i',
            [$opportunityId]
        );

        $grouped = ['eligibility' => [], 'application' => []];

        foreach ($rows as $row) {
            $section = (string) ($row['section'] ?? 'application');
            if (!isset($grouped[$section])) {
                $grouped[$section] = [];
            }

            // Decode options JSON if present
            $options = null;
            if (!empty($row['options'])) {
                $decoded = json_decode($row['options'], true);
                if (is_array($decoded)) {
                    $options = $decoded;
                }
            }

            $row['options'] = $options;
            $row['is_required'] = (bool) $row['is_required'];
            $row['is_knockout'] = (bool) $row['is_knockout'];

            $grouped[$section][] = $row;
        }

        return $grouped;
    }

    /**
     * Get eligibility questions for an opportunity.
     *
     * @param int $opportunityId
     * @return array
     */
    public static function eligibilityForOpportunity(int $opportunityId): array
    {
        $all = self::forOpportunity($opportunityId);
        return $all['eligibility'] ?? [];
    }

    /**
     * Get application questions for an opportunity.
     *
     * @param int $opportunityId
     * @return array
     */
    public static function applicationForOpportunity(int $opportunityId): array
    {
        $all = self::forOpportunity($opportunityId);
        return $all['application'] ?? [];
    }

    /**
     * Find a single question by ID.
     *
     * @param int $id
     * @return array|null
     */
    public static function find(int $id): ?array
    {
        $row = Database::fetchOne(
            "SELECT * FROM opportunity_questions WHERE id = ? LIMIT 1",
            'i',
            [$id]
        );

        if (!$row) {
            return null;
        }

        if (!empty($row['options'])) {
            $decoded = json_decode($row['options'], true);
            $row['options'] = is_array($decoded) ? $decoded : null;
        }

        return $row;
    }

    /**
     * Create a question for an opportunity.
     *
     * @param int   $opportunityId
     * @param array $data
     * @return int  New question ID
     */
    public static function create(int $opportunityId, array $data): int
    {
        $options = null;
        if (!empty($data['options']) && is_array($data['options'])) {
            $options = json_encode(array_values($data['options']));
        } elseif (!empty($data['options']) && is_string($data['options'])) {
            $options = $data['options'];
        }

        Database::execute(
            "INSERT INTO opportunity_questions
             (opportunity_id, section, question_text, question_type, options,
              is_required, is_knockout, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            'issssiii',
            [
                $opportunityId,
                $data['section'] ?? 'application',
                $data['question_text'],
                $data['question_type'] ?? 'text',
                $options,
                !empty($data['is_required']) ? 1 : 0,
                !empty($data['is_knockout']) ? 1 : 0,
                (int) ($data['sort_order'] ?? 0),
            ]
        );

        return Database::lastInsertId();
    }

    /**
     * Update a question.
     *
     * @param int   $id
     * @param array $data
     * @return bool
     */
    public static function update(int $id, array $data): bool
    {
        $allowed = [
            'section', 'question_text', 'question_type', 'options',
            'is_required', 'is_knockout', 'sort_order',
        ];

        $sets = [];
        $types = '';
        $params = [];

        foreach ($data as $col => $val) {
            if (!in_array($col, $allowed, true)) {
                continue;
            }

            if ($col === 'options' && is_array($val)) {
                $val = json_encode(array_values($val));
            }

            if (in_array($col, ['is_required', 'is_knockout', 'sort_order'], true)) {
                $sets[] = "`{$col}` = ?";
                $types .= 'i';
                $params[] = (int) $val;
            } else {
                $sets[] = "`{$col}` = ?";
                $types .= 's';
                $params[] = $val;
            }
        }

        if (empty($sets)) {
            return false;
        }

        $sets[] = "`updated_at` = NOW()";
        $types .= 'i';
        $params[] = $id;

        return Database::execute(
            "UPDATE opportunity_questions SET " . implode(', ', $sets) . " WHERE id = ?",
            $types,
            $params
        ) >= 0;
    }

    /**
     * Delete a question.
     *
     * @param int $id
     * @return bool
     */
    public static function delete(int $id): bool
    {
        return Database::execute(
            "DELETE FROM opportunity_questions WHERE id = ?",
            'i',
            [$id]
        ) > 0;
    }
}