<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Session wrapper with secure defaults. Call Session::start() once at boot
 * before any output.
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        // Skip session entirely in CLI / when headers were already sent
        // (e.g. utility scripts that print before requiring bootstrap.php).
        if (PHP_SAPI === 'cli' || headers_sent()) {
            $GLOBALS['_SESSION'] = $GLOBALS['_SESSION'] ?? [];
            return;
        }
        $name = Env::get('SESSION_NAME', 'itportal_session') ?? 'itportal_session';
        $lifetimeMin = (int) (Env::get('SESSION_LIFETIME_MINUTES', '480') ?? '480');
        $secure = (Env::get('APP_ENV', 'local') === 'production');

        session_name($name);
        session_set_cookie_params([
            'lifetime' => $lifetimeMin * 60,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_start();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool) $params['secure'],
                (bool) $params['httponly']
            );
        }
        session_destroy();
    }

    public static function flash(string $key, ?string $value = null): ?string
    {
        if ($value !== null) {
            $_SESSION['_flash'][$key] = $value;
            return null;
        }
        $msg = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $msg;
    }
}
