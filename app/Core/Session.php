<?php

namespace App\Core;

/**
 * Hardened PHP session wrapper.
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        if (headers_sent()) {
            // A CLI tool or an early fatal error may have produced output already.
            // Sessions cannot be started at that point; callers degrade gracefully.
            return;
        }

        $config = config('app');
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443')
            || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        session_name($config['session_name'] ?? 'icotas_session');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.gc_maxlifetime', (string) ($config['session_lifetime'] ?? 7200));
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        // Idle timeout.
        $lifetime = (int) ($config['session_lifetime'] ?? 7200);
        $last = (int) ($_SESSION['_last_activity'] ?? time());
        if ($lifetime > 0 && time() - $last > $lifetime) {
            self::destroy();
            session_start();
        }
        $_SESSION['_last_activity'] = time();
    }

    public static function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, $_SESSION);
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function pull(string $key, $default = null)
    {
        $value = self::get($key, $default);
        self::remove($key);
        return $value;
    }

    /**
     * Flash values survive exactly one request.
     */
    public static function flash(string $key, $value = null)
    {
        if (func_num_args() === 2) {
            $_SESSION['_flash'][$key] = $value;
            return null;
        }

        $flash = $_SESSION['_flash'] ?? [];
        $value = $flash[$key] ?? null;
        unset($flash[$key]);
        $_SESSION['_flash'] = $flash;
        return $value;
    }

    public static function regenerate(bool $deleteOld = true): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE || headers_sent()) {
            return;
        }
        session_regenerate_id($deleteOld);
        $_SESSION['_last_activity'] = time();
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies') && !headers_sent()) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function id(): string
    {
        return session_id();
    }
}
