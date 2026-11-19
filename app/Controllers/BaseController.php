<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Setting;
use App\Services\AuditService;
use App\Services\NotificationService;

/**
 * Shared conveniences for HTTP controllers. Every action still performs its
 * own object-level authorization before touching a record.
 */
abstract class BaseController extends Controller
{
    protected function demo(): bool
    {
        return Setting::demoMode();
    }

    protected function adminBase(): string
    {
        return \App\Core\Auth::isAdmin() ? 'dean' : 'instructor';
    }

    protected function requireAdmin(): void
    {
        \App\Core\Auth::requireAdmin();
    }

    protected function requireDean(): void
    {
        \App\Core\Auth::requireDean();
    }

    /**
     * Returns the department ID that scopes this user's data access.
     * Dean and Academic Head accounts are scoped to their department_id.
     * Developer accounts are unscoped (null).
     *
     * @return int|null
     */
    protected function scopeDepartmentId(): ?int
    {
        return \App\Core\Auth::scopeDepartmentId();
    }

    /**
     * Returns a SQL WHERE clause fragment for department-scoped queries,
     * or null when the user is unscoped (developer, etc.).
     *
     * @param string $tableAlias Optional table alias prefix (e.g. 'u.', 'c.', 'd.')
     * @return string|null SQL fragment like "u.department_id = 5" or null
     */
    protected function scopeClause(string $tableAlias = ''): ?string
    {
        return \App\Core\Auth::scopeClause($tableAlias);
    }

    /**
     * Adds department_id to a filters array when the user is department-scoped.
     * Dean/Academic Head users can only see records in their own department.
     *
     * @param array $filters Existing filter key-value pairs
     * @return array Filters with department_id added when scoped
     */
    protected function scopeFilters(array $filters = []): array
    {
        $deptId = $this->scopeDepartmentId();
        if ($deptId !== null) {
            $filters['department_id'] = $deptId;
        }
        return $filters;
    }

    /**
     * Validates that a record belongs to the user's department scope.
     * Returns true when unscoped (developer) or when the record's department_id matches.
     */
    protected function inDepartmentScope(?array $row): bool
    {
        if (!$row) {
            return false;
        }
        $deptId = $this->scopeDepartmentId();
        if ($deptId === null) {
            return true; // Developer/unscoped: can see everything
        }
        if (!isset($row['department_id'])) {
            return false;
        }
        return (int) $row['department_id'] === $deptId;
    }

    protected function audit(string $action, ?string $entityType = null, ?int $entityId = null, ?string $description = null): void
    {
        (new AuditService())->record($action, $entityType, $entityId, $description);
    }

    protected function notifyAdmins(string $type, string $title, string $message, ?string $link = null): void
    {
        (new NotificationService())->toAdmins($type, $title, $message, $link, $this->demo());
    }

    protected function postString(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);
        return is_string($value) ? trim($value) : $default;
    }

    protected function postArray(string $key): array
    {
        $value = $this->input($key, []);
        return is_array($value) ? $value : [];
    }

    protected function backWithError(string $message, string $fallback = ''): void
    {
        flash('error', $message);
        $this->back($fallback);
    }

    protected function paginationLinks(array $pagination): string
    {
        ob_start();
        \App\Core\View::partial('partials.pagination', ['pagination' => $pagination]);
        return (string) ob_get_clean();
    }

    /**
     * Validate that a record belongs to the active demo/live scope.
     */
    protected function inScope(?array $row): bool
    {
        if (!$row) {
            return false;
        }
        if (!array_key_exists('is_demo', $row)) {
            return true;
        }
        return (int) $row['is_demo'] === ($this->demo() ? 1 : 0);
    }

    protected function findOrFail(string $table, int $id): array
    {
        $row = Database::fetch('SELECT * FROM ' . $table . ' WHERE id = ? LIMIT 1', [$id]);
        if (!$row) {
            abort(404);
        }
        return $row;
    }

    protected function optionRows(string $sql, array $params = []): array
    {
        return Database::fetchAll($sql, $params);
    }
}
