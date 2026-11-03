<?php

namespace App\Core;

/**
 * CSRF token generation and validation for all state-changing requests.
 */
final class Csrf
{
    public static function token(): string
    {
        $key = config('security.csrf.session_key');
        $token = Session::get($key);
        if (!is_string($token) || strlen($token) < 32) {
            $token = bin2hex(random_bytes(32));
            Session::put($key, $token);
        }
        return $token;
    }

    public static function regenerate(): void
    {
        Session::put(config('security.csrf.session_key'), bin2hex(random_bytes(32)));
    }

    public static function validate(?string $token): bool
    {
        $expected = Session::get(config('security.csrf.session_key'));
        if (!is_string($expected) || $expected === '' || !is_string($token) || $token === '') {
            return false;
        }
        return hash_equals($expected, $token);
    }

    public static function fromRequest(): ?string
    {
        $header = config('security.csrf.header_name');
        $inputName = config('security.csrf.input_name');

        if (!empty($_SERVER[$header])) {
            return (string) $_SERVER[$header];
        }
        if (isset($_POST[$inputName])) {
            return (string) $_POST[$inputName];
        }
        $body = Request::jsonBody();
        if (isset($body[$inputName])) {
            return (string) $body[$inputName];
        }
        return null;
    }
}
