<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * DealerRepository - all SQL touching `dealers` lives here.
 *
 * Master data is never hard-deleted. Use `setStatus()` to deactivate.
 */
final class DealerRepository
{
    private function pdo(): PDO
    {
        return Database::pdo();
    }

    /** Active rows only - used by ticket form selectors. */
    /** @return array<int, array<string, mixed>> */
    public function listActive(): array
    {
        $stmt = $this->pdo()->query(
            'SELECT id, code, name, area, status
             FROM dealers
             WHERE status = "active"
             ORDER BY name ASC'
        );
        return $stmt->fetchAll();
    }

    /**
     * List for the management page (admin/staff).
     *
     * @param array<string, mixed> $filters supports: status, q
     * @return array<int, array<string, mixed>>
     */
    public function listAll(array $filters = []): array
    {
        $clauses = [];
        $params = [];
        if (!empty($filters['status'])) {
            $clauses[] = 'status = :status';
            $params['status'] = (string) $filters['status'];
        }
        $q = isset($filters['q']) ? trim((string) $filters['q']) : '';
        if ($q !== '') {
            $clauses[] = '(name LIKE :q1 OR code LIKE :q2 OR area LIKE :q3)';
            $like = '%' . $q . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }
        $where = $clauses === [] ? '' : 'WHERE ' . implode(' AND ', $clauses);

        $sql = 'SELECT id, code, name, area, address, pic_name, pic_phone, status,
                       created_at, updated_at
                FROM dealers ' . $where . '
                ORDER BY status ASC, name ASC';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT id, code, name, area, address, pic_name, pic_phone, status,
                    created_at, updated_at
             FROM dealers WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function exists(int $id): bool
    {
        $stmt = $this->pdo()->prepare('SELECT 1 FROM dealers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return (bool) $stmt->fetchColumn();
    }

    /** Returns the row whose `code` matches, or null. Empty code returns null. */
    public function findByCode(string $code): ?array
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }
        $stmt = $this->pdo()->prepare(
            'SELECT id, code, name FROM dealers WHERE code = :code LIMIT 1'
        );
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data): int
    {
        $sql =
            'INSERT INTO dealers
             (code, name, area, address, pic_name, pic_phone, status, created_at, updated_at)
             VALUES
             (:code, :name, :area, :address, :pic_name, :pic_phone, :status, :created_at, :updated_at)';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($data);
        return (int) $this->pdo()->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $sets = [];
        foreach (array_keys($data) as $col) {
            $sets[] = "$col = :$col";
        }
        $data['id'] = $id;
        $stmt = $this->pdo()->prepare(
            'UPDATE dealers SET ' . implode(', ', $sets) . ' WHERE id = :id'
        );
        $stmt->execute($data);
    }
}
