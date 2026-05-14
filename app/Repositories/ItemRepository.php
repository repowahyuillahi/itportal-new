<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * ItemRepository - all SQL touching `items` lives here.
 *
 * Master data is never hard-deleted. Use `setStatus()` to deactivate.
 */
final class ItemRepository
{
    private function pdo(): PDO
    {
        return Database::pdo();
    }

    /** @return array<int, array<string, mixed>> */
    public function listActive(): array
    {
        $stmt = $this->pdo()->query(
            'SELECT id, name, slug, status, sort_order
             FROM items
             WHERE status = "active"
             ORDER BY sort_order ASC, name ASC'
        );
        return $stmt->fetchAll();
    }

    /**
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
            $clauses[] = '(name LIKE :q1 OR slug LIKE :q2)';
            $like = '%' . $q . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
        }
        $where = $clauses === [] ? '' : 'WHERE ' . implode(' AND ', $clauses);

        $sql = 'SELECT id, name, slug, description, status, sort_order, created_at, updated_at
                FROM items ' . $where . '
                ORDER BY status ASC, sort_order ASC, name ASC';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT id, name, slug, description, status, sort_order, created_at, updated_at
             FROM items WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function exists(int $id): bool
    {
        $stmt = $this->pdo()->prepare('SELECT 1 FROM items WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return (bool) $stmt->fetchColumn();
    }

    /** Returns the row whose `slug` matches, or null. */
    public function findBySlug(string $slug): ?array
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }
        $stmt = $this->pdo()->prepare(
            'SELECT id, name, slug FROM items WHERE slug = :slug LIMIT 1'
        );
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data): int
    {
        $sql =
            'INSERT INTO items
             (name, slug, description, status, sort_order, created_at, updated_at)
             VALUES
             (:name, :slug, :description, :status, :sort_order, :created_at, :updated_at)';
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
            'UPDATE items SET ' . implode(', ', $sets) . ' WHERE id = :id'
        );
        $stmt->execute($data);
    }

    /** Lookup the largest current sort_order. */
    public function maxSortOrder(): int
    {
        $v = $this->pdo()->query('SELECT MAX(sort_order) FROM items')->fetchColumn();
        return $v === false || $v === null ? 0 : (int) $v;
    }
}
