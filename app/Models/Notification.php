<?php

namespace App\Models;

/**
 * Event-driven in-system notifications. No email/SMS dependency.
 */
final class Notification extends BaseModel
{
    protected static string $table = 'notifications';
    protected static string $primaryKey = 'notification_id';

    public static function forUser(int $userId, int $limit = 8): array
    {
        return static::fetchAll(
            'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC, notification_id DESC LIMIT ' . max(1, $limit),
            [$userId]
        );
    }

    public static function paginateForUser(int $userId, int $limit, int $offset): array
    {
        return static::fetchAll(
            'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC, notification_id DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
            [$userId]
        );
    }

    public static function countForUser(int $userId): int
    {
        return (int) static::scalar('SELECT COUNT(*) FROM notifications WHERE user_id = ?', [$userId]);
    }

    public static function unreadCount(int $userId): int
    {
        return (int) static::scalar('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$userId]);
    }

    public static function push(int $userId, string $title, string $message, ?string $link = null, string $type = 'info'): int
    {
        return static::insert([
            'user_id' => $userId,
            'title'   => $title,
            'message' => $message,
            'link'    => $link,
            'type'    => $type,
        ]);
    }

    public static function markRead(int $notificationId, int $userId): bool
    {
        $stmt = static::pdo()->prepare(
            'UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?'
        );
        $stmt->execute([$notificationId, $userId]);
        return $stmt->rowCount() > 0;
    }

    public static function markAllRead(int $userId): int
    {
        $stmt = static::pdo()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$userId]);
        return $stmt->rowCount();
    }

    public static function pushToDeans(string $title, string $message, ?string $link = null, string $type = 'info'): void
    {
        $deans = static::fetchAll("SELECT user_id FROM users WHERE role = 'Dean' AND account_status = 'Active'");
        foreach ($deans as $dean) {
            self::push((int) $dean['user_id'], $title, $message, $link, $type);
        }
    }
}
