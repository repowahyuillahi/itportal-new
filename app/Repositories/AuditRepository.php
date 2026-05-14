<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * AuditRepository - inserts into `audit_logs`. Read APIs come later.
 */
final class AuditRepository
{
    public function insert(
        ?int $actorUserId,
        string $action,
        ?string $resourceType = null,
        ?string $resourceId = null,
        ?array $beforeJson = null,
        ?array $afterJson = null,
        ?string $ipHash = null,
        ?string $userAgentHash = null,
    ): void {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO audit_logs
             (actor_user_id, action, resource_type, resource_id,
              before_json, after_json, ip_hash, user_agent_hash, created_at)
             VALUES
             (:actor, :action, :rtype, :rid,
              :before, :after, :ip, :ua, :created)'
        );
        $stmt->execute([
            'actor' => $actorUserId,
            'action' => $action,
            'rtype' => $resourceType,
            'rid' => $resourceId,
            'before' => $beforeJson === null ? null : json_encode($beforeJson, JSON_UNESCAPED_UNICODE),
            'after' => $afterJson === null ? null : json_encode($afterJson, JSON_UNESCAPED_UNICODE),
            'ip' => $ipHash,
            'ua' => $userAgentHash,
            'created' => date('Y-m-d H:i:s'),
        ]);
    }
}
