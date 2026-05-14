<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal .env loader. Only handles KEY=VALUE lines, # comments, and quoted
 * values. No interpolation. Good enough for Phase 1. Switch to
 * vlucas/phpdotenv later if needed.
 */
final class Env
{
    /** @var array<string, string> */
    private static array $values = [];
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (!is_file($path)) {
            self::$loaded = true;
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if ($value !== '' && ($value[0] === '"' || $value[0] === '\'')) {
                $quote = $value[0];
                if (substr($value, -1) === $quote) {
                    $value = substr($value, 1, -1);
                }
            }
            self::$values[$key] = $value;
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
            }
            if (getenv($key) === false) {
                putenv("$key=$value");
            }
        }
        self::$loaded = true;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, self::$values)) {
            return self::$values[$key];
        }
        $env = getenv($key);
        if ($env !== false) {
            return $env;
        }
        return $_ENV[$key] ?? $default;
    }
}
