<?php

declare(strict_types=1);

namespace App\Core;

/**
 * CSRF token helper. Token is stored in the session and verified on every
 * POST request. Use `csrf_token()` (see Helpers/escape.php) in views.
 */
final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    public static function verify(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }
        $stored = $_SESSION[self::KEY] ?? '';
        if ($stored === '') {
            return false;
        }
        return hash_equals($stored, $token);
    }

    /** Generate a fresh token (call after login or sensitive actions). */
    public static function rotate(): void
    {
        $_SESSION[self::KEY] = bin2hex(random_bytes(32));
    }
}
