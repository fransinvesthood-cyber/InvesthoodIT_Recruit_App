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
}

