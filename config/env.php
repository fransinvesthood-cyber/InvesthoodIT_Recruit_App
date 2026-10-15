<?php
/**
 * ================================================
 * INVESTHOOD IT - Environment Loader
 * ================================================
 * Loads key=value pairs from a `.env` file in the
 * project root (if present) and exposes them via
 * the env() helper. Keeps secrets out of version
 * control — config/config.php reads mail/DB
 * credentials through env() with safe defaults.
 */

if (!function_exists('env')) {
    /**
     * Get an environment variable with an optional default.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    function env(string $key, $default = null)
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }
        return $value;
    }
}

if (!function_exists('load_dotenv')) {
    /**
     * Load `.env` from the project root into $_ENV/$_SERVER.
     * Never overwrites variables already set in the real environment.
     *
     * @param string $path Full path to the .env file
     */
    function load_dotenv(string $path): void
    {
        if (!is_readable($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            // Allow optional `export ` prefix
            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $raw = trim(substr($line, $pos + 1));
            // Strip surrounding quotes
            if (strlen($raw) >= 2) {
                $first = $raw[0];
                $last  = $raw[strlen($raw) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $raw = substr($raw, 1, -1);
                }
            }
            if ($key === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
                continue;
            }
            if (getenv($key) === false && !isset($_ENV[$key]) && !isset($_SERVER[$key])) {
                putenv($key . '=' . $raw);
                $_ENV[$key]    = $raw;
                $_SERVER[$key] = $raw;
            }
        }
    }
}

// Load project-root .env (one level above /config)
load_dotenv(dirname(__DIR__) . '/.env');
