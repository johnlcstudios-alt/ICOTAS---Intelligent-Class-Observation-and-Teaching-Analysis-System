<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\User;

/**
 * Server-side authorization helpers used in addition to route middleware.
 * Hiding navigation is never the only control.
 */
final class AuthorizationService
{
    public function requireDean(): array
    {
        $user = auth_user();
        if ($user === null) {
            abort(401, 'Sign-in is required.');
        }
        if (($user['role'] ?? '') !== 'Dean') {
            AuditLog::record((int) $user['user_id'], 'authorization.denied', 'Security', 'dean-workspace', 'Faculty attempted to access a Dean-only resource.');
            abort(403, 'You do not have permission to access the Dean workspace.');
        }
        return $user;
    }

    public function requireFaculty(): array
    {
        $user = auth_user();
        if ($user === null) {
            abort(401, 'Sign-in is required.');
        }
        if (($user['role'] ?? '') !== 'Faculty') {
            abort(403, 'You do not have permission to access the faculty workspace.');
        }
        return $user;
    }

    /**
     * Confirm a faculty-owned record. Returns the record or aborts 404 so
     * existence of other faculty records is not disclosed.
     */
    public function requireFacultyOwnsObservation(int $observationId, int $facultyId): array
    {
        $observation = \App\Models\Observation::findDetailed($observationId);
        if ($observation === null || (int) $observation['faculty_id'] !== $facultyId) {
            AuditLog::record($facultyId, 'authorization.ownership_denied', 'Faculty Portal', 'observation:' . $observationId, 'Attempted to access a record not owned by the account.');
            abort(404, 'The requested evaluation could not be found.');
        }
        return $observation;
    }

    public function requirePublishedObservation(int $observationId, int $facultyId): array
    {
        $observation = $this->requireFacultyOwnsObservation($observationId, $facultyId);
        if ($observation['status'] !== 'Published') {
            abort(404, 'The requested evaluation is not available.');
        }
        return $observation;
    }

    public function requireFacultyId(int $facultyId): array
    {
        $faculty = User::findFaculty($facultyId);
        if ($faculty === null) {
            abort(404, 'Faculty member not found.');
        }
        return $faculty;
    }

    public function notify(int $userId, string $title, string $message, ?string $link = null, string $type = 'info'): void
    {
        try {
            Notification::push($userId, $title, $message, $link, $type);
        } catch (\Throwable $e) {
            app_log('warning', 'Notification failed: ' . $e->getMessage());
        }
    }
}
