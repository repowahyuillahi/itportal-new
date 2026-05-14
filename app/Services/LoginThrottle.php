<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Request;

/**
 * LoginThrottle - rate-limits failed login attempts per (ip + email).
 *
 * Data source: `audit_logs` (no new table required). Counts rows where
 *   action = 'login.failed'
 *   AND created_at >= NOW() - INTERVAL `windowMinutes` MINUTE
 *   AND ip_hash = SHA-256(remote_addr)
 *   AND after_json LIKE '%email = email%'  (best-effort match by audit payload)
 *
 * Defaults: 5 failed attempts per 15-minute window. When exceeded, the
 * caller should respond with a generic "too many attempts" error and a
 * Retry-After header; the actual block duration is the remaining window.
 *
 * Notes:
 *  - Email matching uses the JSON `after_json` field rather than parsing
 *    JSON in SQL. This is intentionally fuzzy - we want a low false-
 *    negative rate (don't let a bot bypass with subtle email variants).
 *    `email` is normalized to lowercase by AuthService before logging.
 *  - The IP-only count is also tracked so a single IP can't hammer many
 *    different emails to bypass the per-email cap.
 */
final class LoginThrottle
{
    public const DEFAULT_MAX_PER_EMAIL = 5;
    public const DEFAULT_MAX_PER_IP    = 20;
    public const DEFAULT_WINDOW_MIN    = 15;

    private int $maxPerEmail;
    private int $maxPerIp;
    private int $windowMinutes;

    public function __construct(
        int $maxPerEmail   = self::DEFAULT_MAX_PER_EMAIL,
        int $maxPerIp      = self::DEFAULT_MAX_PER_IP,
        int $windowMinutes = self::DEFAULT_WINDOW_MIN,
    ) {
        $this->maxPerEmail   = max(1, $maxPerEmail);
        $this->maxPerIp      = max(1, $maxPerIp);
        $this->windowMinutes = max(1, $windowMinutes);
    }

    /**
     * Check whether the current attempt should be blocked. Returns
     *   ['blocked' => bool, 'retry_after_seconds' => int, 'reason' => string|null]
     *
     * Read-only against `audit_logs`; never writes.
     *
     * @return array{blocked: bool, retry_after_seconds: int, reason: ?string}
     */
    public function check(Request $request, string $email): array
    {
        $email = strtolower(trim($email));
        $ipHash = hash('sha256', $request->ip());

        $pdo = Database::pdo();

        $emailCount = 0;
        if ($email !== '') {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM audit_logs
                 WHERE action = "login.failed"
                   AND ip_hash = :ip
                   AND created_at >= (NOW() - INTERVAL :win MINUTE)
                   AND (after_json LIKE :email_in_after OR after_json LIKE :email_alt)'
            );
            $stmt->bindValue(':ip', $ipHash);
            $stmt->bindValue(':win', $this->windowMinutes, \PDO::PARAM_INT);
            // Match either {"email":"<value>", ...} or "email":"<value>" anywhere in payload.
            $needle = '%"email":"' . str_replace(['\\', '"', '%', '_'], ['\\\\','\"','\\%','\\_'], $email) . '"%';
            $stmt->bindValue(':email_in_after', $needle);
            $stmt->bindValue(':email_alt',      $needle);
            $stmt->execute();
            $emailCount = (int) $stmt->fetchColumn();
        }

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM audit_logs
             WHERE action = "login.failed"
               AND ip_hash = :ip
               AND created_at >= (NOW() - INTERVAL :win MINUTE)'
        );
        $stmt->bindValue(':ip', $ipHash);
        $stmt->bindValue(':win', $this->windowMinutes, \PDO::PARAM_INT);
        $stmt->execute();
        $ipCount = (int) $stmt->fetchColumn();

        $blocked = false;
        $reason  = null;
        if ($email !== '' && $emailCount >= $this->maxPerEmail) {
            $blocked = true;
            $reason  = 'too_many_failed_for_email';
        } elseif ($ipCount >= $this->maxPerIp) {
            $blocked = true;
            $reason  = 'too_many_failed_for_ip';
        }

        return [
            'blocked'             => $blocked,
            'retry_after_seconds' => $blocked ? ($this->windowMinutes * 60) : 0,
            'reason'              => $reason,
        ];
    }
}
