<?php
/**
 * Investhood IT Platform - Database Configuration
 * 
 * This file provides database connection using MySQLi
 * with prepared statements for security.
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'investhood_platform');

/**
 * Get database connection
 * 
 * @return mysqli
 * @throws Exception
 */
function getDbConnection() {
    static $connection = null;
    
    if ($connection === null) {
        $connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($connection->connect_error) {
            throw new Exception('Database connection failed: ' . $connection->connect_error);
        }
        
        $connection->set_charset('utf8mb4');
    }
    
    return $connection;
}

/**
 * Execute a prepared statement and return results
 * 
 * @param string $sql SQL query with placeholders
 * @param array $params Parameters for placeholders
 * @param string $types Parameter types (s=string, i=integer, d=double, b=blob)
 * @return array Results as associative array
 */
function dbQuery($sql, $params = [], $types = '') {
    $conn = getDbConnection();
    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        error_log('MySQL prepare error: ' . $conn->error);
        return [];
    }
    
    if (!empty($params)) {
        if ($types === '') {
            // Auto-detect types
            $types = '';
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param) || is_double($param)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
            }
        }
        
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    
    $result = $stmt->get_result();
    if ($result === false) {
        return [];
    }
    
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    
    $stmt->close();
    return $rows;
}

/**
 * Execute a prepared statement that doesn't return results (INSERT, UPDATE, DELETE)
 * 
 * @param string $sql SQL query with placeholders
 * @param array $params Parameters for placeholders
 * @param string $types Parameter types
 * @return int|bool Affected rows or false on error
 */
function dbExecute($sql, $params = [], $types = '') {
    $conn = getDbConnection();
    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        error_log('MySQL prepare error: ' . $conn->error);
        return false;
    }
    
    if (!empty($params)) {
        if ($types === '') {
            $types = '';
            foreach ($params as $param) {
                if (is_int($param)) $types .= 'i';
                elseif (is_float($param) || is_double($param)) $types .= 'd';
                else $types .= 's';
            }
        }
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    return $affected;
}

/**
 * Get single row from database
 * 
 * @param string $sql SQL query
 * @param array $params Parameters
 * @param string $types Parameter types
 * @return array|null Single row or null
 */
function dbFetchOne($sql, $params = [], $types = '') {
    $rows = dbQuery($sql, $params, $types);
    return !empty($rows) ? $rows[0] : null;
}

/**
 * Get single value from database
 * 
 * @param string $sql SQL query
 * @param array $params Parameters
 * @param string $types Parameter types
 * @return mixed Single value or null
 */
function dbFetchValue($sql, $params = [], $types = '') {
    $row = dbFetchOne($sql, $params, $types);
    if ($row === null) return null;
    return reset($row);
}

/**
 * Generate unique reference number
 * 
 * @param string $prefix Prefix for reference (e.g., 'PLAC')
 * @param int $length Length of random part
 * @return string Unique reference
 */
function generateReference($prefix = 'REF', $length = 6) {
    $random = '';
    for ($i = 0; $i < $length; $i++) {
        $random .= mt_rand(0, 9);
    }
    return $prefix . '-' . date('Y') . '-' . $random;
}

/**
 * Generate unique reference ensuring no duplicates
 * 
 * @param string $table Table name
 * @param string $column Column name
 * @param string $prefix Prefix
 * @param int $length Length of random part
 * @return string Unique reference
 */
function generateUniqueReference($table, $column, $prefix = 'REF', $length = 6) {
    do {
        $ref = generateReference($prefix, $length);
        $count = dbFetchValue(
            "SELECT COUNT(*) FROM `$table` WHERE `$column` = ?",
            [$ref],
            's'
        );
    } while ($count > 0);
    
    return $ref;
}
