<?php

namespace App\Services;

use App\Models\AuditLog;

/**
 * Audit logging is best-effort: an audit failure must never abort a completed
 * business action. In particular, demo reset deletes the acting demo account
 * before writing its final audit record.
 */
final class AuditService
{
    public function record(string $action, ?string $entityType = null, ?int $entityId = null, ?string $description = null, ?int $userId = null): void
    {
        try {
            (new AuditLog())->record($userId ?? \App\Core\Auth::id(), $action, $entityType, $entityId, $description);
        } catch (\Throwable $e) {
            error_log('ICOTAS audit log failed (' . $action . '): ' . $e->getMessage());
        }
    }
}
