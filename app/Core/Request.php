<?php

namespace App\Core;

final class Request
{
    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function path(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        $basePath = '';
        $configured = trim((string) config('app.base_url', ''));
        if ($configured !== '') {
            $basePath = rtrim((string) parse_url($configured, PHP_URL_PATH), '/');
        } else {
            $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
            $basePath = rtrim(dirname($script), '/.');
            if ($basePath === '/' || $basePath === '\\') {
                $basePath = '';
            }
        }

        if ($basePath !== '' && strpos($path, $basePath) === 0) {
            $path = substr($path, strlen($basePath));
        }

        $path = '/' . ltrim($path, '/');
        $path = rtrim($path, '/');
        return $path === '' ? '/' : $path;
    }

    public static function input(string $key, $default = null)
    {
        if (array_key_exists($key, $_POST)) {
            return is_string($_POST[$key]) ? trim($_POST[$key]) : $_POST[$key];
        }
        if (array_key_exists($key, $_GET)) {
            return is_string($_GET[$key]) ? trim($_GET[$key]) : $_GET[$key];
        }
        return $default;
    }

    public static function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    public static function only(array $keys): array
    {
        $data = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $_POST)) {
                $data[$key] = is_string($_POST[$key]) ? trim($_POST[$key]) : $_POST[$key];
            } elseif (array_key_exists($key, $_GET)) {
                $data[$key] = is_string($_GET[$key]) ? trim($_GET[$key]) : $_GET[$key];
            }
        }
        return $data;
    }

    public static function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    public static function isStateChanging(): bool
    {
        return in_array(self::method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    public static function wantsJson(): bool
    {
        $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
        return strpos($accept, 'application/json') !== false
            || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    }

    public static function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public static function userAgent(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250);
    }

    public static function isSecure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443')
            || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }

    public static function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$key] ?? null;
    }
}
