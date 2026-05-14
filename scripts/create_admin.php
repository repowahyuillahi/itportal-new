<?php

declare(strict_types=1);

/**
 * Interactive admin creator / password reset.
 *
 * Usage:
 *   php scripts/create_admin.php
 *   php scripts/create_admin.php --email=foo@bar.com --name="Admin" --password=secret
 *
 * If a user with the given email already exists, the script offers to
 * reset their password and promote them to role=admin.
 */

require __DIR__ . '/../bootstrap.php';

use App\Core\Database;

$args = parseArgs(array_slice($argv, 1));

$email = $args['email'] ?? prompt('Email: ');
$name  = $args['name']  ?? prompt('Name [Administrator]: ', 'Administrator');
$password = $args['password'] ?? prompt('Password: ');

if ($email === '' || $password === '') {
    fwrite(STDERR, "Email and password are required.\n");
    exit(1);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Invalid email.\n");
    exit(1);
}
if (strlen($password) < 8) {
    fwrite(STDERR, "Password must be at least 8 characters.\n");
    exit(1);
}

try {
    $pdo = Database::pdo();
} catch (Throwable $e) {
    fwrite(STDERR, "DB connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$now = date('Y-m-d H:i:s');

$find = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
$find->execute(['email' => $email]);
$existing = $find->fetchColumn();

if ($existing) {
    $update = $pdo->prepare(
        'UPDATE users
         SET name = :name, password_hash = :hash, role = "admin",
             status = "active", updated_at = :updated
         WHERE id = :id'
    );
    $update->execute([
        'name' => $name,
        'hash' => $hash,
        'updated' => $now,
        'id' => $existing,
    ]);
    echo "Updated user #$existing ($email) as admin.\n";
    exit(0);
}

$insert = $pdo->prepare(
    'INSERT INTO users (name, email, password_hash, role, status, created_at, updated_at)
     VALUES (:name, :email, :hash, "admin", "active", :created, :updated)'
);
$insert->execute([
    'name' => $name,
    'email' => $email,
    'hash' => $hash,
    'created' => $now,
    'updated' => $now,
]);

echo "Created admin user '$email' (id=" . $pdo->lastInsertId() . ").\n";

// ---------- helpers ----------

/** @param array<int,string> $argv */
function parseArgs(array $argv): array
{
    $out = [];
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--') && str_contains($arg, '=')) {
            [$k, $v] = explode('=', substr($arg, 2), 2);
            $out[$k] = $v;
        }
    }
    return $out;
}

function prompt(string $label, string $default = ''): string
{
    fwrite(STDOUT, $label);
    $line = fgets(STDIN);
    if ($line === false) {
        return $default;
    }
    $line = trim($line);
    return $line === '' ? $default : $line;
}
