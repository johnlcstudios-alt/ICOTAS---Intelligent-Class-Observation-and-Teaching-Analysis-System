<?php

namespace App\Core;

use App\Models\User;

/**
 * Session-backed authentication. Role is always re-read from the database
 * so a deactivated or role-changed account cannot keep stale privileges.
 */
final class Auth
{
    private static bool $resolved = false;
    private static ?array $user = null;

    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }
        self::$resolved = true;

        $id = Session::get('user_id');
        if (!is_numeric($id)) {
            return null;
        }

        try {
            $user = User::findForSession((int) $id);
        } catch (\Throwable) {
            $user = null;
        }

        if (!$user || ($user['account_status'] ?? '') !== 'Active'
            || !in_array($user['role'] ?? '', ['Dean', 'Faculty'], true)) {
            Session::expireAuthentication();
            self::$user = null;
            return null;
        }

        self::$user = $user;
        return self::$user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user ? (int) $user['user_id'] : null;
    }

    public static function role(): ?string
    {
        $user = self::user();
        return $user['role'] ?? null;
    }

    public static function isDean(): bool
    {
        return self::role() === 'Dean';
    }

    public static function isFaculty(): bool
    {
        return self::role() === 'Faculty';
    }

    public static function login(array $user): void
    {
        Session::regenerate();
        Session::put('user_id', (int) $user['user_id']);
        Session::put('role', (string) $user['role']);
        Session::put('auth_time', time());
        Session::put('last_activity', time());
        Session::forget('qr_verified_at');
        Session::forget('qr_verified_faculty');
        Csrf::regenerate();
        self::$resolved = true;
        self::$user = null;
        try {
            User::touchLogin((int) $user['user_id']);
        } catch (\Throwable $e) {
            app_log('warning', 'Unable to update last_login_at: ' . $e->getMessage());
        }
    }

    public static function logout(): void
    {
        Session::expireAuthentication();
        self::$resolved = true;
        self::$user = null;
    }

    public static function dashboardPath(): string
    {
        return match (self::role()) {
            'Dean'    => '/dean/dashboard',
            'Faculty' => '/faculty/dashboard',
            default   => '/login',
        };
    }
}
