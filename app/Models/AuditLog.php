<?php

namespace App\Models;

use App\Core\Database;

final class AuditLog extends Model
{
    protected string $table = 'audit_logs';

    public function record(?int $userId, string $action, ?string $entityType = null, ?int $entityId = null, ?string $description = null): void
    {
        Database::execute(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_hash, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [$userId, $action, $entityType, $entityId, $description, \App\Core\Security::hashIp()]
        );
    }

    public function paginate(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (!empty($filters['action'])) {
            $where[] = 'a.action LIKE ?';
            $params[] = '%' . $filters['action'] . '%';
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'a.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }
        if (!empty($filters['from'])) {
            $where[] = 'a.created_at >= ?';
            $params[] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $where[] = 'a.created_at <= ?';
            $params[] = $filters['to'] . ' 23:59:59';
        }
        $clause = implode(' AND ', $where);
        $total = (int) Database::scalar('SELECT COUNT(*) FROM audit_logs a WHERE ' . $clause, $params, 0);
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        return [
            'rows' => Database::fetchAll(
                'SELECT a.*, u.first_name, u.last_name, u.username,
                        CASE WHEN a.ip_hash IS NULL THEN NULL ELSE LEFT(a.ip_hash, 10) END AS ip_hint
                 FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
                 WHERE ' . $clause . ' ORDER BY a.created_at DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
                $params
            ),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }
}
