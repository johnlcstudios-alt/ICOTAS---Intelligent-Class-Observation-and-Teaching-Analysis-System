<?php

namespace App\Core;

/**
 * Session bootstrap and helpers with hardened cookie parameters,
 * idle/absolute timeouts and flash-compatible lifecycle.
 */
final class Session
{
    public static function start(): void
    {
        if (PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $cfg = config('app.session');

        // Institution-configurable timeouts (System Settings), clamped to the
        // ranges enforced by the settings validator.
        $idleMinutes = (int) Settings::get('session_timeout_minutes', $cfg['idle_minutes']);
        $absoluteMinutes = (int) Settings::get('session_absolute_minutes', $cfg['absolute_minutes']);
        $idleMinutes = max(5, min(240, $idleMinutes > 0 ? $idleMinutes : (int) $cfg['idle_minutes']));
        $absoluteMinutes = max(30, min(1440, $absoluteMinutes > 0 ? $absoluteMinutes : (int) $cfg['absolute_minutes']));
        if ($idleMinutes > $absoluteMinutes) {
            $idleMinutes = $absoluteMinutes;
        }

        $secure = self::isHttps();
        $path = app_base_path() !== '' ? app_base_path() . '/' : '/';

        session_name($cfg['name']);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => $path,
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => $cfg['same_site'],
        ]);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.gc_maxlifetime', (string) (max($idleMinutes, $absoluteMinutes) * 60));

        session_start();

        // Expire idle or over-age authenticated sessions.
        if (isset($_SESSION['user_id'])) {
            $now = time();
            $idleLimit = $idleMinutes * 60;
            $absoluteLimit = $absoluteMinutes * 60;
            $last = (int) ($_SESSION['last_activity'] ?? 0);
            $authAt = (int) ($_SESSION['auth_time'] ?? 0);

            if (($last > 0 && ($now - $last) > $idleLimit) ||
                ($authAt > 0 && ($now - $authAt) > $absoluteLimit)) {
                self::expireAuthentication();
                self::flash('warning', 'Your session expired. Please sign in again.');
            } else {
                $_SESSION['last_activity'] = $now;
            }
        }
    }

    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443')
            || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, $_SESSION);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = self::get($key, $default);
        self::forget($key);
        return $value;
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function expireAuthentication(): void
    {
        unset(
            $_SESSION['user_id'],
            $_SESSION['role'],
            $_SESSION['auth_time'],
            $_SESSION['last_activity'],
            $_SESSION['qr_verified_at'],
            $_SESSION['qr_verified_faculty']
        );
        self::regenerate();
        Csrf::regenerate();
    }

    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'] ?: '/',
                'domain'   => $params['domain'] ?: '',
                'secure'   => (bool) $params['secure'],
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        session_destroy();
    }

    public static function flash(string $type, string $message): void
    {
        $messages = $_SESSION['_flash'] ?? [];
        $messages[] = ['type' => $type, 'message' => $message];
        $_SESSION['_flash'] = $messages;
    }

    public static function takeFlash(): array
    {
        $messages = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $messages;
    }
}
