<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal PSR-4 autoloader.
 *
 * Used when Composer is not installed yet. After `composer install`, the
 * Composer autoloader is preferred (bootstrap.php prefers vendor/autoload.php
 * if it exists).
 */
final class Autoloader
{
    /** @var array<string, string> prefix => base directory */
    private array $prefixes = [];

    public function addNamespace(string $prefix, string $baseDir): void
    {
        $prefix = trim($prefix, '\\') . '\\';
        $baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->prefixes[$prefix] = $baseDir;
    }

    public function register(): void
    {
        spl_autoload_register([$this, 'load']);
    }

    public function load(string $class): bool
    {
        foreach ($this->prefixes as $prefix => $baseDir) {
            if (str_starts_with($class, $prefix)) {
                $relative = substr($class, strlen($prefix));
                $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
                if (is_file($file)) {
                    require $file;
                    return true;
                }
            }
        }
        return false;
    }
}
