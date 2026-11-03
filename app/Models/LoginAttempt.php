<?php

namespace App\Models;

/**
 * Login attempt history for rate limiting and security monitoring.
 */
final class LoginAttempt extends BaseModel
{
    protected static string $table = 'login_attempts';
    protected static string $primaryKey = 'attempt_id';

    public static function record(string $identifier, bool $success, ?string $ip = null, string $reason = ''): void
    {
        try {
            static::insert([
                'account_identifier' => substr($identifier, 0, 190),
                'success_status'     => $success ? 1 : 0,
                'ip_address'         => $ip ?? \App\Core\Request::ip(),
                'user_agent'         => substr(\App\Core\Request::userAgent() . ($reason !== '' ? ' | ' . $reason : ''), 0, 255),
            ]);
        } catch (\Throwable $e) {
            app_log('warning', 'Login attempt record failed: ' . $e->getMessage());
        }
    }

    public static function recentFailures(string $identifier, ?string $ip, int $windowMinutes): int
    {
        $since = date('Y-m-d H:i:s', time() - $windowMinutes * 60);
        $sql = 'SELECT COUNT(*) FROM login_attempts
                WHERE success_status = 0 AND attempted_at >= ? AND (account_identifier = ?';
        $params = [$since, $identifier];
        if ($ip !== null && $ip !== '') {
            $sql .= ' OR ip_address = ?';
            $params[] = $ip;
        }
        $sql .= ')';
        return (int) static::scalar($sql, $params);
    }

    public static function lastFailureAt(string $identifier, ?string $ip): ?string
    {
        $sql = 'SELECT MAX(attempted_at) FROM login_attempts
                WHERE success_status = 0 AND (account_identifier = ?';
        $params = [$identifier];
        if ($ip !== null && $ip !== '') {
            $sql .= ' OR ip_address = ?';
            $params[] = $ip;
        }
        $sql .= ')';
        $value = static::scalar($sql, $params);
        return $value === null || $value === false ? null : (string) $value;
    }

    public static function recent(int $limit = 20): array
    {
        return static::fetchAll(
            'SELECT * FROM login_attempts ORDER BY attempted_at DESC, attempt_id DESC LIMIT ' . max(1, $limit)
        );
    }

    public static function countFailuresSince(string $since): int
    {
        return (int) static::scalar(
            'SELECT COUNT(*) FROM login_attempts WHERE success_status = 0 AND attempted_at >= ?',
            [$since]
        );
    }

    public static function purgeOlderThan(int $days = 90): int
    {
        $stmt = static::pdo()->prepare('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL ? DAY)');
        $stmt->execute([$days]);
        return $stmt->rowCount();
    }
}
