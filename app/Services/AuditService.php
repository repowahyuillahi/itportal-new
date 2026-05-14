<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Repositories\AuditRepository;

/**
 * AuditService - thin wrapper that hashes IP/User-Agent before storing.
 *
 * IP and User-Agent are hashed with SHA-256 so we can audit suspicious
 * patterns without storing raw PII.
 */
final class AuditService
{
    private AuditRepository $repo;

    public function __construct(?AuditRepository $repo = null)
    {
        $this->repo = $repo ?? new AuditRepository();
    }

    public function log(
        ?int $actorUserId,
        string $action,
        ?Request $request = null,
        ?string $resourceType = null,
        ?string $resourceId = null,
        ?array $beforeJson = null,
        ?array $afterJson = null,
    ): void {
        $ipHash = null;
        $uaHash = null;
        if ($request !== null) {
            $ipHash = hash('sha256', $request->ip());
            $uaHash = hash('sha256', $request->userAgent());
        }

        try {
            $this->repo->insert(
                $actorUserId,
                $action,
                $resourceType,
                $resourceId,
                $beforeJson,
                $afterJson,
                $ipHash,
                $uaHash,
            );
        } catch (\Throwable $e) {
            // Auditing must not break the request. Log to error log instead.
            error_log('Audit insert failed: ' . $e->getMessage());
        }
    }
}
