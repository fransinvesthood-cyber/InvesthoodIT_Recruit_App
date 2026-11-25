<?php
/**
 * Central browser security headers for every request using includes/bootstrap.php.
 * CSP is initially Report-Only so legacy inline scripts and third-party integrations
 * can be inventoried before an enforcing policy is enabled.
 */
if (!function_exists('investhood_is_https_request')) {
    function investhood_is_https_request(): bool
    {
        return (
            (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
        );
    }
}

if (!function_exists('investhood_apply_security_headers')) {
    function investhood_apply_security_headers(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header('X-Permitted-Cross-Domain-Policies: none');

        // Start in report-only mode. Review browser reports before enforcing.
        $csp = [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
            "form-action 'self' https:",
            "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net",
            "font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com",
            "img-src 'self' data: blob: https:",
            "connect-src 'self' https: wss:",
            "media-src 'self' data: blob: https:",
            "worker-src 'self' blob:",
        ];
        header('Content-Security-Policy-Report-Only: ' . implode('; ', $csp));

        if (defined('APP_ENV') && APP_ENV === 'production' && investhood_is_https_request()) {
            // No includeSubDomains/preload until all subdomains are confirmed HTTPS.
            header('Strict-Transport-Security: max-age=15552000');
        }
    }
}
