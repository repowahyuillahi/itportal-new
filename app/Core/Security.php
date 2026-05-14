<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Security - centralizes baseline HTTP security headers and the
 * production HTTPS redirect.
 *
 * Called once near the top of `bootstrap.php`, before routing. Headers
 * are emitted unconditionally; each `Response::*` helper later sets its
 * own `Content-Type`, which does not conflict with the security headers
 * we add here.
 *
 * Why these headers:
 *   - X-Content-Type-Options: nosniff
 *       Disables MIME sniffing - browsers must honor our Content-Type.
 *   - X-Frame-Options: DENY
 *       Prevents clickjacking. The app has no embed-able iframe surface.
 *   - Referrer-Policy: same-origin
 *       Don't leak full URLs (which may contain filter query strings) to
 *       third parties.
 *   - Content-Security-Policy
 *       Locks scripts/styles/images to same origin. The app ships no
 *       third-party JS, no inline <script>; minimal inline CSS exists
 *       in some Phase 6 pages, hence 'unsafe-inline' for style-src.
 *       (TODO: hash the inline blocks and tighten to 'self' only.)
 *   - Strict-Transport-Security
 *       Only in production. Forces HTTPS for one year, including
 *       subdomains.
 *
 * The application has no external assets / CDNs in V1; if any are added,
 * update the CSP directives here.
 */
final class Security
{
    /** Apply baseline HTTP headers. Safe to call multiple times; PHP
     *  will overwrite any duplicate header name.
     */
    public static function sendHeaders(): void
    {
        if (headers_sent()) {
            return;
        }
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: same-origin');

        // Tight default. Allow inline styles for now (some Phase 6 views
        // use small inline CSS); no inline JS is allowed.
        $csp = "default-src 'self'; "
             . "script-src 'self'; "
             . "style-src 'self' 'unsafe-inline'; "
             . "img-src 'self' data:; "
             . "font-src 'self' data:; "
             . "object-src 'none'; "
             . "base-uri 'self'; "
             . "form-action 'self'; "
             . "frame-ancestors 'none';";
        header('Content-Security-Policy: ' . $csp);

        if ((string) Env::get('APP_ENV', 'local') === 'production') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    /**
     * If `APP_ENV=production` and the request is plain HTTP, redirect to
     * the HTTPS equivalent. Honors `X-Forwarded-Proto: https` so proxies
     * (Cloudflare, nginx) terminate TLS upstream.
     *
     * Returns true if a redirect was sent (caller must abort).
     */
    public static function enforceHttpsIfProduction(): bool
    {
        if ((string) Env::get('APP_ENV', 'local') !== 'production') {
            return false;
        }
        if (self::isHttps()) {
            return false;
        }
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $uri  = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        if ($host === '') {
            return false; // can't redirect without a host header.
        }
        $location = 'https://' . $host . $uri;
        http_response_code(301);
        header('Location: ' . $location);
        return true;
    }

    private static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
            && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return true;
        }
        return false;
    }
}
