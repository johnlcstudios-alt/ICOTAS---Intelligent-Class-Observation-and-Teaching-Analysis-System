<?php

namespace App\Models;

use App\Core\Database;

final class Notification extends Model
{
    protected string $table = 'notifications';

    public function createFor(int $userId, string $type, string $title, string $message, ?string $link = null): int
    {
        return Database::insert(
            'INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
             VALUES (?, ?, ?, ?, ?, 0, NOW())',
            [$userId, $type, mb_substr($title, 0, 180), mb_substr($message, 0, 500), $link]
        );
    }

    public function createForRole(string $roleSlug, string $type, string $title, string $message, ?string $link = null, bool $demo = false): void
    {
        $users = Database::fetchAll(
            'SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id
             WHERE r.slug = ? AND u.status = "active" AND u.is_demo = ?',
            [$roleSlug, $demo ? 1 : 0]
        );
        foreach ($users as $user) {
            $this->createFor((int) $user['id'], $type, $title, $message, $link);
        }
    }

    public function forUser(int $userId, int $limit = 20): array
    {
        return Database::fetchAll(
            'SELECT * FROM notifications WHERE user_id = ? ORDER BY is_read ASC, created_at DESC LIMIT ' . max(1, $limit),
            [$userId]
        );
    }

    public function paginateForUser(int $userId, int $page = 1, int $perPage = 20): array
    {
        $total = (int) Database::scalar('SELECT COUNT(*) FROM notifications WHERE user_id = ?', [$userId], 0);
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        return [
            'rows' => Database::fetchAll(
                'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
                [$userId]
            ),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function unreadCount(int $userId): int
    {
        return (int) Database::scalar('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$userId], 0);
    }

    public function markRead(int $id, int $userId): bool
    {
        return Database::execute('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?', [$id, $userId]) > 0;
    }

    public function markAllRead(int $userId): int
    {
        return Database::execute('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0', [$userId]);
    }
}
