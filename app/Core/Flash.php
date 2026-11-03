<?php

namespace App\Core;

/**
 * One-request flash messages, validation errors and old input.
 */
final class Flash
{
    private static ?array $errors = null;
    private static ?array $old = null;

    public static function add(string $type, string $message): void
    {
        Session::flash($type, $message);
    }

    public static function success(string $message): void
    {
        self::add('success', $message);
    }

    public static function error(string $message): void
    {
        self::add('error', $message);
    }

    public static function warning(string $message): void
    {
        self::add('warning', $message);
    }

    public static function info(string $message): void
    {
        self::add('info', $message);
    }

    public static function messages(): array
    {
        return Session::takeFlash();
    }

    public static function withErrors(array $errors, array $oldInput = []): void
    {
        Session::put('_errors', $errors);
        Session::put('_old', $oldInput);
    }

    public static function errors(): array
    {
        if (self::$errors === null) {
            self::$errors = Session::pull('_errors', []);
            if (!is_array(self::$errors)) {
                self::$errors = [];
            }
        }
        return self::$errors;
    }

    public static function firstError(?string $field = null): ?string
    {
        $errors = self::errors();
        if ($field === null) {
            return $errors === [] ? null : (string) reset($errors);
        }
        return isset($errors[$field]) ? (string) (is_array($errors[$field]) ? reset($errors[$field]) : $errors[$field]) : null;
    }

    public static function old(string $key, mixed $default = ''): mixed
    {
        if (self::$old === null) {
            self::$old = Session::pull('_old', []);
            if (!is_array(self::$old)) {
                self::$old = [];
            }
        }
        return array_get(self::$old, $key, $default);
    }
}
