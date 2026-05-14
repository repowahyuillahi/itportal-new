<?php

declare(strict_types=1);

/**
 * Minimal migration runner.
 *
 * Reads `database/migrations/*.sql` in filename order, runs each file that is
 * not yet recorded in the `migrations` table, then records it.
 *
 * Usage:
 *   php scripts/migrate.php          # run pending migrations
 *   php scripts/migrate.php --status # show what is pending / applied
 *   php scripts/migrate.php --fresh  # DROP DATABASE then re-run all (DANGEROUS)
 */

require __DIR__ . '/../bootstrap.php';

use App\Core\Config;
use App\Core\Database;

$flags = array_slice($argv, 1);
$showStatus = in_array('--status', $flags, true);
$fresh = in_array('--fresh', $flags, true);

$migrationsDir = __DIR__ . '/../database/migrations';
if (!is_dir($migrationsDir)) {
    fwrite(STDERR, "Migrations directory missing: $migrationsDir\n");
    exit(1);
}

try {
    if ($fresh) {
        confirmFresh();
        dropAndRecreateDatabase();
        Database::reset();
    }
    $pdo = Database::pdo();
} catch (Throwable $e) {
    fwrite(STDERR, "DB connection failed: " . $e->getMessage() . "\n");
    fwrite(STDERR, "Hint: check .env DB_* values and that the database '"
        . Config::get('database.database') . "' exists.\n");
    exit(1);
}

// Ensure the migrations table exists (the very first migration creates it,
// but we may need it before we can decide what is pending). Run 001 first
// if migrations table is missing.
ensureMigrationsTable($pdo, $migrationsDir);

$files = glob($migrationsDir . '/*.sql') ?: [];
sort($files, SORT_NATURAL);

$applied = fetchAppliedMigrations($pdo);

if ($showStatus) {
    echo "Applied migrations:\n";
    foreach ($files as $file) {
        $name = basename($file);
        $mark = isset($applied[$name]) ? '[x]' : '[ ]';
        echo "  $mark $name\n";
    }
    exit(0);
}

$batch = nextBatch($pdo);
$pending = array_filter($files, fn($f) => !isset($applied[basename($f)]));

if (count($pending) === 0) {
    echo "Nothing to migrate. Database is up to date.\n";
    exit(0);
}

echo "Running " . count($pending) . " migration(s), batch #$batch...\n";

foreach ($pending as $file) {
    $name = basename($file);
    $sql = file_get_contents($file);
    if ($sql === false || trim($sql) === '') {
        echo "  - $name (skip: empty)\n";
        continue;
    }
    echo "  - $name ... ";
    // NOTE: MySQL/MariaDB issue an implicit commit on DDL statements
    // (CREATE TABLE, ALTER, etc.), so we cannot wrap migrations in an
    // explicit transaction. Each migration file must be safe to re-run
    // partially if it fails midway (prefer single-statement files or
    // IF NOT EXISTS clauses).
    try {
        $pdo->exec($sql);
        $stmt = $pdo->prepare('INSERT INTO migrations (filename, batch, ran_at) VALUES (?, ?, ?)');
        $stmt->execute([$name, $batch, date('Y-m-d H:i:s')]);
        echo "OK\n";
    } catch (Throwable $e) {
        echo "FAILED\n";
        fwrite(STDERR, "ERROR in $name: " . $e->getMessage() . "\n");
        exit(1);
    }
}

echo "Done.\n";

// ---------- helpers ----------

function ensureMigrationsTable(PDO $pdo, string $dir): void
{
    try {
        $pdo->query('SELECT 1 FROM migrations LIMIT 1');
        return;
    } catch (Throwable) {
        // table missing; create from file 001
    }
    $first = $dir . '/001_create_migrations_table.sql';
    if (!is_file($first)) {
        throw new RuntimeException("Missing $first");
    }
    $pdo->exec((string) file_get_contents($first));
}

/** @return array<string, true> */
function fetchAppliedMigrations(PDO $pdo): array
{
    $rows = $pdo->query('SELECT filename FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
    return array_fill_keys($rows, true);
}

function nextBatch(PDO $pdo): int
{
    $row = $pdo->query('SELECT COALESCE(MAX(batch), 0) AS b FROM migrations')->fetch();
    return ((int) ($row['b'] ?? 0)) + 1;
}

function confirmFresh(): void
{
    $env = (string) (App\Core\Config::get('app.env', 'local'));
    if ($env === 'production') {
        fwrite(STDERR, "Refusing --fresh in production.\n");
        exit(1);
    }
    fwrite(STDOUT, "About to DROP and RECREATE the database. Type 'yes' to continue: ");
    $line = trim((string) fgets(STDIN));
    if ($line !== 'yes') {
        fwrite(STDERR, "Aborted.\n");
        exit(1);
    }
}

function dropAndRecreateDatabase(): void
{
    $host = (string) App\Core\Config::get('database.host', '127.0.0.1');
    $port = (string) App\Core\Config::get('database.port', '3306');
    $name = (string) App\Core\Config::get('database.database', 'itportal');
    $user = (string) App\Core\Config::get('database.username', 'root');
    $pass = (string) App\Core\Config::get('database.password', '');

    $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("DROP DATABASE IF EXISTS `$name`");
    $pdo->exec("CREATE DATABASE `$name` DEFAULT CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Recreated database `$name`.\n";
}
