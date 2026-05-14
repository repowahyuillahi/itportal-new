<?php

declare(strict_types=1);

/**
 * Seed an initial admin user from environment variables.
 *
 * Required env vars (define in .env):
 *   ADMIN_EMAIL
 *   ADMIN_PASSWORD
 * Optional:
 *   ADMIN_NAME   (default "Administrator")
 *
 * Behavior:
 *   - If ADMIN_EMAIL is missing, this seeder is skipped silently.
 *   - If a user with that email already exists, it is NOT overwritten.
 *
 * Use scripts/create_admin.php for interactive creation/reset.
 *
 * @var PDO $pdo provided by scripts/seed.php
 */

/** @var PDO $pdo */

$email = (string) (App\Core\Env::get('ADMIN_EMAIL') ?? '');
$password = (string) (App\Core\Env::get('ADMIN_PASSWORD') ?? '');
$name = (string) (App\Core\Env::get('ADMIN_NAME') ?? 'Administrator');

if ($email === '' || $password === '') {
    echo "  admin_user: skipped (ADMIN_EMAIL / ADMIN_PASSWORD not set in .env)\n";
    return;
}

$stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
$stmt->execute(['email' => $email]);
if ($stmt->fetchColumn()) {
    echo "  admin_user: skipped (user exists: $email)\n";
    return;
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$now = date('Y-m-d H:i:s');

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

echo "  admin_user: created admin '$email'\n";
