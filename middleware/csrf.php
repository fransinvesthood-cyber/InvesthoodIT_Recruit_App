<?php
/**
 * ================================================
 * INVESTHOOD IT - CSRF Middleware
 * ================================================
 * Enforces CSRF validation on all POST requests.
 * Fails closed (403) if the token is missing/invalid.
 */

function enforce_csrf(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        require_csrf();
    }
}

