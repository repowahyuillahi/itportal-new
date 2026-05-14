<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Simple config registry. Loads PHP files from /config and exposes
 * dot-notation access (e.g. `Config::get('database.host')`).
 */
final class Config
{
    /** @var array<string, mixed> */
    private static array $items = [];

    public static function loadDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.php') ?: [] as $file) {
            $name = basename($file, '.php');
            /** @psalm-suppress UnresolvableInclude */
            $data = require $file;
            if (is_array($data)) {
                self::$items[$name] = $data;
            }
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $value = self::$items;
        foreach ($parts as $p) {
            if (!is_array($value) || !array_key_exists($p, $value)) {
                return $default;
            }
            $value = $value[$p];
        }
        return $value;
    }
}
