<?php

declare(strict_types=1);

/**
 * Seeder runner.
 *
 * Loads every PHP file in database/seeders in filename order. Each seeder
 * receives a `$pdo` variable and should be idempotent.
 *
 * Usage:
 *   php scripts/seed.php          # run all seeders
 *   php scripts/seed.php items    # run a single seeder by base name
 */

require __DIR__ . '/../bootstrap.php';

use App\Core\Database;

$onlyName = $argv[1] ?? null;

try {
    $pdo = Database::pdo();
} catch (Throwable $e) {
    fwrite(STDERR, "DB connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

$seedersDir = __DIR__ . '/../database/seeders';
$files = glob($seedersDir . '/*.php') ?: [];
sort($files, SORT_NATURAL);

if (count($files) === 0) {
    echo "No seeders in $seedersDir\n";
    exit(0);
}

echo "Running seeders...\n";
$ran = 0;
foreach ($files as $file) {
    $base = basename($file, '.php');
    if ($onlyName !== null && $onlyName !== $base) {
        continue;
    }
    echo "* $base\n";
    /** @psalm-suppress UnresolvableInclude */
    require $file;
    $ran++;
}

if ($onlyName !== null && $ran === 0) {
    fwrite(STDERR, "Seeder not found: $onlyName\n");
    exit(1);
}

echo "Done.\n";
