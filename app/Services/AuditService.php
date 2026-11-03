<?php

namespace App\Services;

use App\Models\AuditLog;

/**
 * Convenience facade over the append-only audit log.
 */
final class AuditService
{
    public function log(?int $userId, string $action, string $module, ?string $reference = null, ?string $details = null): void
    {
        AuditLog::record($userId, $action, $module, $reference, $details);
    }

    public function deanAction(string $action, string $module, ?string $reference = null, ?string $details = null): void
    {
        $user = auth_user();
        AuditLog::record($user !== null ? (int) $user['user_id'] : null, $action, $module, $reference, $details);
    }
}
