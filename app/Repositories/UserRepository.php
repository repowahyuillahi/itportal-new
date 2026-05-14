<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * UserRepository - all SQL touching the `users` table lives here.
 */
final class UserRepository
{
    private function pdo(): PDO
    {
        return Database::pdo();
    }

    /** @return array<string, mixed>|null */
    public function findActiveByEmail(string $email): ?array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT id, name, email, password_hash, role, status, last_login_at
             FROM users
             WHERE email = :email
             LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT id, name, email, role, status, last_login_at, created_at, updated_at
             FROM users
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function touchLastLogin(int $userId): void
    {
        $now = date('Y-m-d H:i:s');
        // Distinct placeholders required because PDO::ATTR_EMULATE_PREPARES is off.
        $stmt = $this->pdo()->prepare(
            'UPDATE users SET last_login_at = :now1, updated_at = :now2 WHERE id = :id'
        );
        $stmt->execute(['now1' => $now, 'now2' => $now, 'id' => $userId]);
    }
}
