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
