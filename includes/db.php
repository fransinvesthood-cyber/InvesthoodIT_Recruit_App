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

            // Check connection
            if ($conn->connect_errno) {
                error_log('[DB] Connection failed: ' . $conn->connect_error);
                http_response_code(500);
                exit('A system error occurred. Please try again later.');
            }

            // Set charset
            if (!$conn->set_charset(DB_CHARSET)) {
                error_log('[DB] Failed to set charset: ' . $conn->error);
            }

            self::$instance = $conn;
        }

        return self::$instance;
    }

    /**
     * Helper: run a SELECT with prepared statement
     * and return a single associative row.
     *
     * @param string $sql
     * @param string $types e.g. 'is'
     * @param array  $params
     * @return array|null
     */
    public static function fetchOne(string $sql, string $types = '', array $params = []): ?array
    {
        $row = self::fetchAll($sql, $types, $params);
        return $row[0] ?? null;
    }

    /**
     * Helper: run a SELECT with prepared statement
     * and return all associative rows.
     *
     * @param string $sql
     * @param string $types
     * @param array  $params
     * @return array
     */
    public static function fetchAll(string $sql, string $types = '', array $params = []): array
    {
        $stmt = self::prepare($sql, $types, $params);
        $result = $stmt->get_result();
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows;
    }

    /**
     * Helper: run an INSERT/UPDATE/DELETE with prepared
     * statement and return affected rows.
     *
     * @param string $sql
     * @param string $types
     * @param array  $params
     * @return int affected rows
     */
    public static function execute(string $sql, string $types = '', array $params = []): int
    {
        $stmt = self::prepare($sql, $types, $params);
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected;
    }

    /**
     * Prepare a statement with bound parameters.
     *
     * @param string $sql
     * @param string $types
     * @param array  $params
     * @return mysqli_stmt
     */
    public static function prepare(string $sql, string $types = '', array $params = []): mysqli_stmt
    {
        $conn = self::getConnection();
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            error_log('[DB] Prepare failed: ' . $conn->error . ' | SQL: ' . $sql);
            throw new RuntimeException('Database prepare error.');
        }

        if ($types !== '' && count($params) > 0) {
            // Spread parameters by reference
            $bindArgs = [$types];
            foreach ($params as &$p) {
                $bindArgs[] = &$p;
            }
            unset($p);
            call_user_func_array([$stmt, 'bind_param'], $bindArgs);
        }

        if (!$stmt->execute()) {
            error_log('[DB] Execute failed: ' . $stmt->error);
            throw new RuntimeException('Database execute error.');
        }

        return $stmt;
    }

    /**
     * Return the ID of the last inserted row.
     *
     * @return int
     */
    public static function lastInsertId(): int
    {
        return (int) self::getConnection()->insert_id;
    }

    /**
     * Begin a transaction.
     */
    public static function beginTransaction(): void
    {
        self::getConnection()->begin_transaction();
    }

    /**
     * Commit the active transaction.
     */
    public static function commit(): void
    {
        self::getConnection()->commit();
    }

    /**
     * Roll back the active transaction.
     */
    public static function rollback(): void
    {
        self::getConnection()->rollback();
    }
}

