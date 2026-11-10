<?php

namespace App\Core;

/**
 * Small database-backed rate limiter for public or semi-public actions.
 * Identifiers are HMAC-hashed; raw IP addresses and tokens are never stored.
 */
final class RateLimiter
{
    public static function tooMany(string $bucket, int $maxAttempts = 10, int $windowSeconds = 300, ?string $identifier = null): bool
    {
        $identifier = $identifier !== null && $identifier !== '' ? $identifier : Request::ip();
        $key = Security::hashIp($identifier);
        $windowSeconds = max(30, $windowSeconds);
        $maxAttempts = max(1, $maxAttempts);

        try {
            Database::transaction(function () use ($bucket, $key, $maxAttempts, $windowSeconds): void {
                Database::execute(
                    'INSERT INTO rate_limits (bucket, identifier_hash, hits, window_started_at)
                     VALUES (?, ?, 1, NOW())
                     ON DUPLICATE KEY UPDATE
                       hits = IF(window_started_at < (NOW() - INTERVAL ' . $windowSeconds . ' SECOND), 1, hits + 1),
                       window_started_at = IF(window_started_at < (NOW() - INTERVAL ' . $windowSeconds . ' SECOND), NOW(), window_started_at)',
                    [$bucket, $key]
                );
            });

            $hits = (int) Database::scalar(
                'SELECT hits FROM rate_limits WHERE bucket = ? AND identifier_hash = ?',
                [$bucket, $key],
                0
            );
            return $hits > $maxAttempts;
        } catch (\Throwable $e) {
            // Rate limiting must never take the application down.
            error_log('ICOTAS rate limiter unavailable: ' . $e->getMessage());
            return false;
        }
    }

    public static function clear(string $bucket, ?string $identifier = null): void
    {
        $identifier = $identifier !== null && $identifier !== '' ? $identifier : Request::ip();
        try {
            Database::execute(
                'DELETE FROM rate_limits WHERE bucket = ? AND identifier_hash = ?',
                [$bucket, Security::hashIp($identifier)]
            );
        } catch (\Throwable $e) {
            // Ignore cleanup failures.
        }
    }
}
