<?php

namespace App\Models;

/**
 * Append-only audit trail. There is deliberately no update/delete API.
 */
final class AuditLog extends BaseModel
{
    protected static string $table = 'audit_logs';
    protected static string $primaryKey = 'audit_id';

    public static function record(?int $userId, string $action, string $module, ?string $reference = null, ?string $details = null): void
    {
        try {
            static::insert([
                'user_id'          => $userId,
                'action'           => $action,
                'module'           => $module,
                'record_reference' => $reference,
                'details'          => $details,
                'ip_address'       => \App\Core\Request::ip(),
                'user_agent'       => \App\Core\Request::userAgent(),
            ]);
        } catch (\Throwable $e) {
            app_log('error', 'Audit log write failed: ' . $e->getMessage());
        }
    }

    public static function paginate(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = self::filterSql($filters);
        return static::fetchAll(
            "SELECT a.*, u.full_name AS user_name, u.role
             FROM audit_logs a
             LEFT JOIN users u ON u.user_id = a.user_id
             {$where}
             ORDER BY a.created_at DESC, a.audit_id DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
    }

    public static function countFiltered(array $filters): int
    {
        [$where, $params] = self::filterSql($filters);
        return (int) static::scalar("SELECT COUNT(*) FROM audit_logs a {$where}", $params);
    }

    private static function filterSql(array $filters): array
    {
        $conditions = ['1 = 1'];
        $params = [];

        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $conditions[] = '(a.action LIKE ? OR a.module LIKE ? OR a.details LIKE ? OR a.record_reference LIKE ? OR u.full_name LIKE ?)';
            array_push($params, $term, $term, $term, $term, $term);
        }
        if (!empty($filters['module'])) {
            $conditions[] = 'a.module = ?';
            $params[] = $filters['module'];
        }
        if (!empty($filters['action'])) {
            $conditions[] = 'a.action = ?';
            $params[] = $filters['action'];
        }
        if (!empty($filters['user_id'])) {
            $conditions[] = 'a.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }
        if (!empty($filters['date_from'])) {
            $conditions[] = 'a.created_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $conditions[] = 'a.created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        return ['WHERE ' . implode(' AND ', $conditions), $params];
    }

    public static function modules(): array
    {
        return array_column(
            static::fetchAll('SELECT DISTINCT module FROM audit_logs ORDER BY module ASC'),
            'module'
        );
    }

    public static function actions(): array
    {
        return array_column(
            static::fetchAll('SELECT DISTINCT action FROM audit_logs ORDER BY action ASC'),
            'action'
        );
    }

    public static function recent(int $limit = 10): array
    {
        return static::fetchAll(
            "SELECT a.*, u.full_name AS user_name, u.role
             FROM audit_logs a
             LEFT JOIN users u ON u.user_id = a.user_id
             ORDER BY a.created_at DESC, a.audit_id DESC
             LIMIT {$limit}"
        );
    }
}
