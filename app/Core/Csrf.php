<?php

namespace App\Core;

final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function fromRequest(): string
    {
        $header = Request::header('X-CSRF-Token');
        if (is_string($header) && $header !== '') {
            return $header;
        }
        $token = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        return is_string($token) ? $token : '';
    }

    public static function validate(?string $token = null): bool
    {
        $token = $token ?? self::fromRequest();
        $session = $_SESSION[self::KEY] ?? '';
        return is_string($session) && $session !== '' && is_string($token) && hash_equals($session, $token);
    }

    public static function rotate(): void
    {
        $_SESSION[self::KEY] = bin2hex(random_bytes(32));
    }
}
