<?php

namespace App\Core;

/**
 * Database-backed system settings with an in-request cache.
 */
final class Setting
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        self::$cache = [];
        try {
            if (Database::tableExists('system_settings')) {
                $rows = Database::fetchAll('SELECT setting_key, setting_value FROM system_settings');
                foreach ($rows as $row) {
                    self::$cache[$row['setting_key']] = $row['setting_value'];
                }
            }
        } catch (\Throwable $e) {
            // Installation / unavailable database: fall back to defaults.
        }

        return self::$cache;
    }

    public static function get(string $key, $default = null)
    {
        $all = self::all();
        if (array_key_exists($key, $all) && $all[$key] !== null && $all[$key] !== '') {
            return $all[$key];
        }
        return $default;
    }

    public static function set(string $key, $value, string $group = 'general', ?int $userId = null): void
    {
        Database::execute(
            'INSERT INTO system_settings (setting_key, setting_value, setting_group, updated_by)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)',
            [$key, (string) $value, $group, $userId]
        );
        self::$cache = null;
    }

    public static function demoMode(): bool
    {
        return (string) self::get('demo_mode', '0') === '1';
    }

    public static function privacyThreshold(): int
    {
        return max(1, (int) self::get('privacy_min_responses', '3'));
    }

    public static function institution(): string
    {
        return (string) self::get('institution_name', config('app.institution', 'ACLC College Tacloban City'));
    }

    public static function clearCache(): void
    {
        self::$cache = null;
    }
}
