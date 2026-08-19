<?php
/**
 * ================================================
 * INVESTHOOD IT - Input Sanitisation Helper
 * ================================================
 * Provides static methods to clean and normalise
 * user-supplied data before validation or storage.
 */

class Sanitizer
{
    /**
     * Trim and normalise a string value.
     *
     * @param mixed $value
     * @return string
     */
    public static function clean($value): string
    {
        return trim((string) $value);
    }

    /**
     * Sanitise an email address (lowercase, trimmed).
     *
     * @param string $email
     * @return string
     */
    public static function email(string $email): string
    {
        return strtolower(trim(filter_var($email, FILTER_SANITIZE_EMAIL)));
    }

    /**
     * Sanitise a username (alphanumeric + underscores, trimmed).
     *
     * @param string $username
     * @return string
     */
    public static function username(string $username): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '', trim($username));
    }

    /**
     * Sanitise a phone number (digits, +, -, spaces).
     *
     * @param string $phone
     * @return string
     */
    public static function phone(string $phone): string
    {
        return preg_replace('/[^0-9+\- ]/', '', trim($phone));
    }

    /**
     * Strip all HTML tags from a string.
     *
     * @param string $value
     * @return string
     */
    public static function stripTags(string $value): string
    {
        return strip_tags(trim($value));
    }

    /**
     * Normalise a name (capitalise first letter, trim).
     *
     * @param string $name
     * @return string
     */
    public static function name(string $name): string
    {
        return ucfirst(strtolower(trim($name)));
    }

    /**
     * Sanitise a URL.
     *
     * @param string $url
     * @return string
     */
    public static function url(string $url): string
    {
        return filter_var(trim($url), FILTER_SANITIZE_URL);
    }
}
