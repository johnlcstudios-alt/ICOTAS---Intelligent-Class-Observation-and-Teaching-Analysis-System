<?php

namespace App\Core;

use Throwable;

/**
 * Institution settings persisted in the database, cached per request.
 */
final class Settings
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        try {
            $stmt = Database::connection()->query('SELECT setting_key, setting_value FROM settings');
            $rows = $stmt->fetchAll();
            $out = [];
            foreach ($rows as $row) {
                $out[(string) $row['setting_key']] = $row['setting_value'];
            }
            self::$cache = $out;
        } catch (Throwable) {
            self::$cache = [];
        }
        return self::$cache;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key, (string) $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    public static function set(string $key, mixed $value, ?int $userId = null, string $group = 'general'): void
    {
        self::setMany([$key => $value], $userId, $group);
    }

    public static function setMany(array $values, ?int $userId = null, string $group = 'general'): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO settings (setting_key, setting_value, setting_group, updated_by)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value),
                                     setting_group = VALUES(setting_group),
                                     updated_by = VALUES(updated_by)'
        );
        foreach ($values as $key => $value) {
            $stmt->execute([$key, (string) $value, $group, $userId]);
        }
        self::$cache = null;
    }

    public static function refresh(): void
    {
        self::$cache = null;
    }

    public static function ratingLabels(): array
    {
        $labels = [];
        for ($i = 1; $i <= 5; $i++) {
            $labels[$i] = (string) self::get('rating_label_' . $i, 'Level ' . $i);
        }
        return $labels;
    }
}
