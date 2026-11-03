<?php

namespace App\Core;

/**
 * Read-only request information.
 */
final class Request
{
    private static ?array $json = null;

    public static function method(): string
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        return $method === 'HEAD' ? 'GET' : $method;
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    public static function isStateChanging(): bool
    {
        return in_array(self::method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    public static function path(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $uri = rawurldecode($uri);
        $base = app_base_path();

        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        $uri = '/' . trim($uri, '/');
        return $uri === '/' ? '/' : rtrim($uri, '/');
    }

    public static function fullPath(): string
    {
        $query = $_SERVER['QUERY_STRING'] ?? '';
        return self::path() . ($query !== '' ? '?' . $query : '');
    }

    public static function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $_POST)) {
            return $_POST[$key];
        }
        $body = self::jsonBody();
        if (array_key_exists($key, $body)) {
            return $body[$key];
        }
        return $default;
    }

    public static function query(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $_GET) ? $_GET[$key] : $default;
    }

    public static function allPost(): array
    {
        return $_POST;
    }

    public static function allQuery(): array
    {
        return $_GET;
    }

    public static function jsonBody(): array
    {
        if (self::$json !== null) {
            return self::$json;
        }
        self::$json = [];
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    self::$json = $decoded;
                }
            }
        }
        return self::$json;
    }

    public static function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return isset($_SERVER[$key]) ? (string) $_SERVER[$key] : null;
    }

    public static function wantsJson(): bool
    {
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $requested = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        return str_contains($accept, 'application/json') || $requested === 'xmlhttprequest';
    }

    public static function ip(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    public static function userAgent(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'), 0, 255);
    }

    public static function isSecure(): bool
    {
        return Session::isHttps();
    }
}
