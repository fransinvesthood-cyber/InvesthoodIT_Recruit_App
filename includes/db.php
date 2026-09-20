<?php
/**
 * ================================================
 * INVESTHOOD IT - Database Connection (MySQLi)
 * ================================================
 * Singleton-style connection handler using MySQLi
 * with prepared-statement support. Prevents
 * duplicate connections across includes.
 */

require_once __DIR__ . '/../config/config.php';

class Database
{
    /** @var mysqli|null */
    private static $instance = null;

    /**
     * Get the single shared MySQLi connection instance.
     *
     * @return mysqli
     */
    public static function getConnection(): mysqli
    {
        if (self::$instance === null) {
            mysqli_report(MYSQLI_REPORT_OFF);

            $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if ($conn->connect_errno) {
                error_log('[DB] Connection failed: ' . $conn->connect_error);
                http_response_code(500);
                exit('A system error occurred. Please try again later.');
            }

            if (!$conn->set_charset(DB_CHARSET)) {
                error_log('[DB] Failed to set charset: ' . $conn->error);
            }

            self::$instance = $conn;
        }

        return self::$instance;
    }

    public static function fetchOne(string $sql, string $types = '', array $params = []): ?array
    {
        $row = self::fetchAll($sql, $types, $params);
        return $row[0] ?? null;
    }

    public static function fetchAll(string $sql, string $types = '', array $params = []): array
    {
        $stmt = self::prepare($sql, $types, $params);
        try {
            $result = $stmt->get_result();
            $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
            if ($result) {
                $result->free();
            }
            return $rows;
        } finally {
            $stmt->close();
        }
    }

    public static function execute(string $sql, string $types = '', array $params = []): int
    {
        $stmt = self::prepare($sql, $types, $params);
        try {
            return (int) $stmt->affected_rows;
        } finally {
            $stmt->close();
        }
    }

    /**
     * Run a raw SQL statement that cannot be parameterised (e.g. DDL such as
     * CREATE TABLE / ALTER TABLE). Use sparingly — never with user input.
     *
     * @param string $sql
     * @return bool True on success
     * @throws RuntimeException on failure
     */
    public static function query(string $sql): bool
    {
        $conn = self::getConnection();

        if ($conn->query($sql) === false) {
            error_log('[DB] Query failed: ' . $conn->error . ' | SQL: ' . $sql);
            throw new RuntimeException('Database query error.');
        }

        return true;
    }

    public static function prepare(string $sql, string $types = '', array $params = []): mysqli_stmt
    {
        $conn = self::getConnection();

        $placeholderCount = substr_count($sql, '?');
        if (($types !== '' || $params !== []) && strlen($types) !== $placeholderCount) {
            throw new RuntimeException('Database prepare error.');
        }

        if ($types !== '' && count($params) > 0 && strlen($types) !== count($params)) {
            throw new RuntimeException('Database prepare error.');
        }

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log('[DB] Prepare failed: ' . $conn->error . ' | SQL: ' . $sql);
            throw new RuntimeException('Database prepare error.');
        }

        if ($types !== '' && $params !== []) {
            $bindArgs = [$types];
            foreach ($params as $index => &$value) {
                $bindArgs[] = &$value;
            }
            unset($value);

            if (!call_user_func_array([$stmt, 'bind_param'], $bindArgs)) {
                // Capture the error BEFORE closing the statement — accessing
                // ->error on a closed mysqli_stmt throws
                // "mysqli_stmt object is already closed" and masks the real
                // database problem.
                $error = $stmt->error;
                $stmt->close();
                error_log('[DB] Bind failed: ' . $error . ' | SQL: ' . $sql);
                throw new RuntimeException('Database prepare error.');
            }
        }

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            error_log('[DB] Execute failed: ' . $error . ' | SQL: ' . $sql);
            throw new RuntimeException('Database execute error.');
        }

        return $stmt;
    }

    public static function lastInsertId(): int
    {
        return (int) self::getConnection()->insert_id;
    }

    public static function beginTransaction(): void
    {
        self::getConnection()->begin_transaction();
    }

    public static function commit(): void
    {
        self::getConnection()->commit();
    }

    public static function rollback(): void
    {
        self::getConnection()->rollback();
    }
}

