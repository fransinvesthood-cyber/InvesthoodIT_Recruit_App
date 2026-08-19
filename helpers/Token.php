<?php
/**
 * ================================================
 * INVESTHOOD IT - Secure Token Generator
 * ================================================
 * Handles creation, hashing, and validation of
 * security tokens for email verification, password
 * resets, and remember-me functionality.
 */

class Token
{
    /**
     * Generate a secure random token (plain text).
     * Returns the token that should be sent to the user.
     *
     * @param int $bytes Length of random bytes (output hex = 2x bytes)
     * @return string
     */
    public static function generate(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    /**
     * Hash a token for secure database storage.
     * Uses SHA-256 — tokens are stored hashed, not plain.
     *
     * @param string $token
     * @return string
     */
    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Verify a plain-text token against a stored hash.
     *
     * @param string $token
     * @param string $storedHash
     * @return bool
     */
    public static function verify(string $token, string $storedHash): bool
    {
        return hash_equals($storedHash, self::hash($token));
    }

    /**
     * Generate a split-token (selector:validator) for
     * remember-me cookies. The selector is stored in
     * plain text; the validator is hashed.
     *
     * @return array [selector, validator, hashedValidator]
     */
    public static function generateSplit(): array
    {
        $selector = self::generate(16);   // 32 hex chars
        $validator = self::generate(32);  // 64 hex chars
        $hashedValidator = self::hash($validator);

        return [$selector, $validator, $hashedValidator];
    }
}
