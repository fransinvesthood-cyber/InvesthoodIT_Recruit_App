<?php
/**
 * ================================================
 * INVESTHOOD IT - Server-Side Validator
 * ================================================
 * Validates form input against defined rules and
 * returns an associative array of error messages.
 */

class Validator
{
    /** @var array Field => error message */
    private array $errors = [];

    /** @var array Field => cleaned value */
    private array $data = [];

    /**
     * Run validation rules against an input array.
     *
     * @param array $fields  [field => value]
     * @param array $rules   [field => [rule1, rule2, ...]]
     *                       Rules: required|email|min:N|max:N|alpha|alphanumeric|matches:field
     *                              |unique:table,column|in:val1,val2|strength|phone|url|name
     */
    public function validate(array $fields, array $rules): void
    {
        foreach ($rules as $field => $fieldRules) {
            $value = $fields[$field] ?? '';
            $label = $this->fieldLabel($field);

            foreach ($fieldRules as $rule) {
                $params = [];

                // Parse rule + parameters (e.g. "min:8")
                if (str_contains($rule, ':')) {
                    [$rule, $paramStr] = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                }

                $this->applyRule($field, $value, $label, $rule, $params, $fields);
            }

            // Store cleaned value
            $this->data[$field] = $this->cleanValue($field, $value);
        }
    }

    /**
     * Apply a single validation rule.
     */
    private function applyRule(string $field, $value, string $label, string $rule, array $params, array $allFields): void
    {
        switch ($rule) {
            case 'required':
                if (trim((string) $value) === '') {
                    $this->errors[$field] = "{$label} is required.";
                }
                break;

            case 'email':
                if (trim((string) $value) !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->errors[$field] = "Please enter a valid email address.";
                }
                break;

            case 'min':
                $min = (int) ($params[0] ?? 0);
                if (mb_strlen(trim((string) $value)) < $min && trim((string) $value) !== '') {
                    $this->errors[$field] = "{$label} must be at least {$min} characters.";
                }
                break;

            case 'max':
                $max = (int) ($params[0] ?? 0);
                if (mb_strlen(trim((string) $value)) > $max) {
                    $this->errors[$field] = "{$label} must not exceed {$max} characters.";
                }
                break;

            case 'matches':
                $otherField = $params[0] ?? '';
                $otherLabel = $this->fieldLabel($otherField);
                if ((string) $value !== (string) ($allFields[$otherField] ?? '')) {
                    $this->errors[$field] = "{$label} does not match {$otherLabel}.";
                }
                break;

            case 'unique':
                [$table, $column] = $params;
                $excludeId = $params[2] ?? null;
                if ($this->existsInDb($table, $column, $value, $excludeId)) {
                    $this->errors[$field] = "This {$label} is already taken.";
                }
                break;

            case 'in':
                if (trim((string) $value) !== '' && !in_array($value, $params, true)) {
                    $this->errors[$field] = "Please select a valid {$label}.";
                }
                break;

case 'strength':
                // Strong password policy
                $pw = (string) $value;
                if (trim($pw) === '') break;
                $missing = [];
                if (!preg_match('/[A-Z]/', $pw)) $missing[] = 'an uppercase letter';
                if (!preg_match('/[a-z]/', $pw)) $missing[] = 'a lowercase letter';
                if (!preg_match('/[0-9]/', $pw)) $missing[] = 'a number';
                if (!preg_match('/[^a-zA-Z0-9]/', $pw)) $missing[] = 'a special character';
                if (!empty($missing)) {
                    $this->errors[$field] = "{$label} must contain " . implode(', ', $missing) . '.';
                }
                break;

            case 'phone':
                if (trim((string) $value) !== '' && !preg_match('/^[0-9+\- ]{7,20}$/', trim((string) $value))) {
                    $this->errors[$field] = "Please enter a valid phone number.";
                }
                break;

            case 'alphanumeric':
                if (trim((string) $value) !== '' && !preg_match('/^[a-zA-Z0-9_]+$/', trim((string) $value))) {
                    $this->errors[$field] = "{$label} may only contain letters, numbers, and underscores.";
                }
                break;

            case 'checked':
                if (empty($value) || $value !== 'on') {
                    $this->errors[$field] = "You must agree to the {$label}.";
                }
                break;
        }
    }

    /**
     * Check if a value exists in the database.
     */
    private function existsInDb(string $table, string $column, $value, ?string $excludeId): bool
    {
        $sql = "SELECT COUNT(*) AS cnt FROM `{$table}` WHERE `{$column}` = ?";
        $params = [$value];
        $types = 's';

        if ($excludeId !== null) {
            $sql .= " AND `id` != ?";
            $params[] = (int) $excludeId;
            $types .= 'i';
        }

        try {
            $row = Database::fetchOne($sql, $types, $params);
            return $row && (int) $row['cnt'] > 0;
        } catch (Exception $e) {
            error_log('[Validator] DB check error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Determine a human-readable field label.
     */
    private function fieldLabel(string $field): string
    {
        $labels = [
            'first_name' => 'First name',
            'last_name'  => 'Last name',
            'username'   => 'Username',
            'email'      => 'Email address',
            'password'   => 'Password',
            'confirm_password' => 'Confirm password',
            'phone'      => 'Phone number',
            'province'   => 'Province',
            'employment_status' => 'Employment status',
            'qualification_level' => 'Qualification level',
            'terms'      => 'Terms and Conditions',
            'privacy'    => 'Privacy Policy',
            'admin_consent' => 'Programme Administration consent',
        ];
        return $labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }

    /**
     * Return a cleaned value for a given field.
     */
    private function cleanValue(string $field, $value): string
    {
        $cleaned = trim((string) $value);
        if ($field === 'email') {
            $cleaned = strtolower($cleaned);
        }
        return $cleaned;
    }

    /**
     * Check if validation passed.
     *
     * @return bool
     */
    public function passes(): bool
    {
        return empty($this->errors);
    }

    /**
     * Get all validation errors.
     *
     * @return array
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Get all cleaned data.
     *
     * @return array
     */
    public function data(): array
    {
        return $this->data;
    }
}
