<?php
/**
 * ================================================
 * INVESTHOOD IT - User Model
 * ================================================
 * Handles user records: registration, authentication
 * lookup, profile updates and status management.
 */

class User
{
    /**
     * Find a user by ID.
     *
     * @param int $id
     * @return array|null
     */
    public static function find(int $id): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM users WHERE id = ? LIMIT 1",
            'i',
            [$id]
        );
    }

    /**
     * Find a user by email address.
     *
     * @param string $email
     * @return array|null
     */
    public static function findByEmail(string $email): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM users WHERE email = ? LIMIT 1",
            's',
            [strtolower(trim($email))]
        );
    }

    /**
     * Find a user by username.
     *
     * @param string $username
     * @return array|null
     */
    public static function findByUsername(string $username): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM users WHERE username = ? LIMIT 1",
            's',
            [trim($username)]
        );
    }

    /**
     * Find a user by email OR username (for login).
     *
     * @param string $login
     * @return array|null
     */
    public static function findByLogin(string $login): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM users WHERE email = ? OR username = ? LIMIT 1",
            'ss',
            [strtolower(trim($login)), trim($login)]
        );
    }

    /**
     * Check whether an email is already registered.
     *
     * @param string $email
     * @param int|null $excludeId
     * @return bool
     */
    public static function emailExists(string $email, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) AS cnt FROM users WHERE email = ?";
        $types = 's';
        $params = [strtolower(trim($email))];
        if ($excludeId !== null) {
            $sql .= " AND id != ?";
            $types .= 'i';
            $params[] = $excludeId;
        }
        $row = Database::fetchOne($sql, $types, $params);
        return $row && (int) $row['cnt'] > 0;
    }

    /**
     * Check whether a username is already registered.
     *
     * @param string $username
     * @param int|null $excludeId
     * @return bool
     */
    public static function usernameExists(string $username, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) AS cnt FROM users WHERE username = ?";
        $types = 's';
        $params = [trim($username)];
        if ($excludeId !== null) {
            $sql .= " AND id != ?";
            $types .= 'i';
            $params[] = $excludeId;
        }
        $row = Database::fetchOne($sql, $types, $params);
        return $row && (int) $row['cnt'] > 0;
    }

    /**
     * Create a new user account.
     *
     * @param array $data   Fields to insert
     * @return int          New user ID
     */
    public static function create(array $data): int
    {
        $sql = "INSERT INTO users
                (role_id, first_name, last_name, username, email, phone,
                 date_of_birth, gender, province, employment_status,
                 qualification_level, professional_title, password_hash,
                 status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        Database::execute($sql, 'isssssssssssss', [
            $data['role_id'],
            $data['first_name'],
            $data['last_name'],
            $data['username'],
            $data['email'],
            $data['phone'] ?? null,
            $data['date_of_birth'] ?? null,
            $data['gender'] ?? null,
            $data['province'] ?? null,
            $data['employment_status'] ?? null,
            $data['qualification_level'] ?? null,
            $data['professional_title'] ?? null,
            $data['password_hash'],
            $data['status'] ?? STATUS_PENDING,
        ]);

        return Database::lastInsertId();
    }

    /**
     * Update user fields.
     *
     * @param int    $id
     * @param array  $data   Column => value map (safe whitelist)
     * @return bool
     */
    public static function update(int $id, array $data): bool
    {
        $allowed = [
            'first_name', 'last_name', 'username', 'email', 'phone',
            'date_of_birth', 'gender', 'province', 'employment_status',
            'qualification_level', 'professional_title', 'profile_picture',
            'password_hash', 'status', 'email_verified_at', 'last_login',
        ];

        $sets = [];
        $types = '';
        $params = [];

        foreach ($data as $col => $val) {
            if (in_array($col, $allowed, true)) {
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

        $sql = "UPDATE users SET " . implode(', ', $sets) . " WHERE id = ?";
        Database::execute($sql, $types, $params);
        return true;
    }

    /**
     * Verify a user's credentials (password check).
     *
     * @param string $login    Username or email
     * @param string $password Plain-text password
     * @return array|null      User row if credentials valid
     */
    public static function authenticate(string $login, string $password): ?array
    {
        $user = self::findByLogin($login);
        if (!$user || empty($user['password_hash'])) {
            return null;
        }
        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }
        return $user;
    }

    /**
     * Update the last login timestamp.
     *
     * @param int $id
     */
    public static function recordLogin(int $id): void
    {
        Database::execute(
            "UPDATE users SET last_login = NOW() WHERE id = ?",
            'i',
            [$id]
        );
    }

    /**
     * Get user with their role.
     *
     * @param int $id
     * @return array|null
     */
    public static function findWithRole(int $id): ?array
    {
        return Database::fetchOne(
            "SELECT u.*, r.name AS role_name, r.slug AS role_slug
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.id = ? LIMIT 1",
            'i',
            [$id]
        );
    }

    /**
     * Get all users with their roles, with optional search and filters.
     *
     * @param string $search Search term for name or email
     * @param string $roleSlug Filter by role slug
     * @param string $status Filter by account status
     * @param int $page Page number (1-based)
     * @param int $perPage Items per page
     * @return array ['users' => array, 'total' => int, 'pages' => int]
     */
    public static function adminList(string $search = '', string $roleSlug = '', string $status = '', int $page = 1, int $perPage = 15): array
    {
        $where = [];
        $types = '';
        $params = [];

        if ($search !== '') {
            $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)";
            $searchTerm = '%' . $search . '%';
            $types .= 'ssss';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if ($roleSlug !== '') {
            $where[] = "r.slug = ?";
            $types .= 's';
            $params[] = $roleSlug;
        }

        if ($status !== '') {
            $where[] = "u.status = ?";
            $types .= 's';
            $params[] = $status;
        }

        $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Get total count
        $countRow = Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM users u INNER JOIN roles r ON r.id = u.role_id {$whereSql}",
            $types,
            $params
        );
        $total = (int) ($countRow['cnt'] ?? 0);
        $pages = (int) ceil($total / $perPage);
        $page = max(1, min($page, $pages ?: 1));
        $offset = ($page - 1) * $perPage;

        $users = Database::fetchAll(
            "SELECT u.id, u.first_name, u.last_name, u.username, u.email, u.status,
                    u.last_login, u.created_at, u.updated_at,
                    r.name AS role_name, r.slug AS role_slug
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             {$whereSql}
             ORDER BY u.created_at DESC
             LIMIT ? OFFSET ?",
            $types . 'ii',
            array_merge($params, [$perPage, $offset])
        );

        return [
            'users' => $users,
            'total' => $total,
            'pages' => $pages,
            'page' => $page,
        ];
    }

    /**
     * Count users by role slug.
     *
     * @param string $roleSlug
     * @return int
     */
    public static function countByRole(string $roleSlug): int
    {
        $row = Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE r.slug = ?",
            's',
            [$roleSlug]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Count users by status.
     *
     * @param string $status
     * @return int
     */
    public static function countByStatus(string $status): int
    {
        $row = Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM users WHERE status = ?",
            's',
            [$status]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Count total users.
     *
     * @return int
     */
    public static function countAll(): int
    {
        $row = Database::fetchOne("SELECT COUNT(*) AS cnt FROM users");
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Check if a user has related records that would prevent deletion.
     *
     * @param int $userId
     * @return array ['can_delete' => bool, 'reasons' => array]
     */
    public static function canDelete(int $userId): array
    {
        $reasons = [];

        // Check for applications
        $row = Database::fetchOne("SELECT COUNT(*) AS cnt FROM applications WHERE candidate_id = ?", 'i', [$userId]);
        if ($row && (int) $row['cnt'] > 0) {
            $reasons[] = 'Has ' . $row['cnt'] . ' application(s)';
        }

        // Check for candidate profile
        $row = Database::fetchOne("SELECT COUNT(*) AS cnt FROM candidate_profiles WHERE user_id = ?", 'i', [$userId]);
        if ($row && (int) $row['cnt'] > 0) {
            $reasons[] = 'Has candidate profile data';
        }

        // Check for cohort participations
        $row = Database::fetchOne("SELECT COUNT(*) AS cnt FROM cohort_participants WHERE user_id = ?", 'i', [$userId]);
        if ($row && (int) $row['cnt'] > 0) {
            $reasons[] = 'Has cohort participation records';
        }

        // Check for documents
        $row = Database::fetchOne("SELECT COUNT(*) AS cnt FROM documents WHERE user_id = ?", 'i', [$userId]);
        if ($row && (int) $row['cnt'] > 0) {
            $reasons[] = 'Has uploaded documents';
        }

        // Check for qualifications
        $row = Database::fetchOne("SELECT COUNT(*) AS cnt FROM qualifications WHERE user_id = ?", 'i', [$userId]);
        if ($row && (int) $row['cnt'] > 0) {
            $reasons[] = 'Has qualification records';
        }

        // Check for work experience
        $row = Database::fetchOne("SELECT COUNT(*) AS cnt FROM work_experience WHERE user_id = ?", 'i', [$userId]);
        if ($row && (int) $row['cnt'] > 0) {
            $reasons[] = 'Has work experience records';
        }

        return [
            'can_delete' => empty($reasons),
            'reasons' => $reasons,
        ];
    }

    /**
     * Permanently delete a user and their related records.
     * Only call after checking canDelete().
     *
     * @param int $userId
     * @return bool
     */
    public static function delete(int $userId): bool
    {
        // Deactivate all sessions first
        UserSession::deactivateAllForUser($userId);

        // Delete remember me tokens
        RememberMeToken::deleteAllForUser($userId);

        // Delete the user (cascades will handle related records with ON DELETE CASCADE)
        Database::execute("DELETE FROM users WHERE id = ?", 'i', [$userId]);
        return true;
    }

    /**
     * Update user role.
     *
     * @param int $userId
     * @param int $roleId
     * @return bool
     */
    public static function updateRole(int $userId, int $roleId): bool
    {
        return Database::execute(
            "UPDATE users SET role_id = ?, updated_at = NOW() WHERE id = ?",
            'ii',
            [$roleId, $userId]
        ) >= 0;
    }

    /**
     * Update user status.
     *
     * @param int $userId
     * @param string $status
     * @return bool
     */
    public static function updateStatus(int $userId, string $status): bool
    {
        $result = Database::execute(
            "UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?",
            'si',
            [$status, $userId]
        );

        // If deactivating, also deactivate all sessions
        if (in_array($status, [STATUS_SUSPENDED, STATUS_DISABLED], true)) {
            UserSession::deactivateAllForUser($userId);
        }

        return $result >= 0;
    }
}

