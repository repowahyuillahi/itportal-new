<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * ExportRepository - inserts rows into `export_jobs`.
 *
 * Schema (see database/migrations/008_create_export_jobs_table.sql):
 *   id, type ENUM('monthly_excel','monthly_pdf'), filters_json JSON,
 *   file_path VARCHAR(500), created_by, created_at.
 */
final class ExportRepository
{
    /**
     * @param array<string, mixed> $filters
     */
    public function insert(string $type, array $filters, string $filePath, int $createdBy): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO export_jobs (type, filters_json, file_path, created_by, created_at)
             VALUES (:type, :filters, :path, :by, :at)'
        );
        $stmt->execute([
            'type'    => $type,
            'filters' => json_encode($filters, JSON_UNESCAPED_UNICODE),
            'path'    => $filePath,
            'by'      => $createdBy,
            'at'      => date('Y-m-d H:i:s'),
        ]);
        return (int) Database::pdo()->lastInsertId();
    }
}
