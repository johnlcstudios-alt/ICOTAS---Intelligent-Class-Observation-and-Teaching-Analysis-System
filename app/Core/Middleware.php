<?php

namespace App\Core;

/**
 * Route middleware: authentication, role isolation and maintenance mode.
 */
final class Middleware
{
    public static function run(array $names): void
    {
        foreach ($names as $name) {
            match ($name) {
                'auth'    => self::auth(),
                'guest'   => self::guest(),
                'dean'    => self::dean(),
                'faculty' => self::faculty(),
                default   => throw new \RuntimeException('Unknown middleware: ' . $name),
            };
        }
    }

    public static function auth(): void
    {
        if (Auth::check()) {
            return;
        }
        Session::put('intended_url', Request::fullPath());
        Flash::warning('Please sign in to continue.');
        redirect('/login');
    }

    public static function guest(): void
    {
        if (Auth::check()) {
            redirect(Auth::dashboardPath());
        }
    }

    public static function dean(): void
    {
        if (!Auth::check()) {
            Session::put('intended_url', Request::fullPath());
            Flash::warning('Please sign in to continue.');
            redirect('/login');
        }
        if (!Auth::isDean()) {
            abort(403, 'You do not have permission to access the Dean workspace.');
        }
    }

    public static function faculty(): void
    {
        if (!Auth::check()) {
            Session::put('intended_url', Request::fullPath());
            Flash::warning('Please sign in to continue.');
            redirect('/login');
        }
        if (!Auth::isFaculty()) {
            abort(403, 'You do not have permission to access the faculty workspace.');
        }
    }

    public static function maintenanceBlocked(string $path): bool
    {
        if (!in_array($path, ['/login', '/logout', '/maintenance', '/setup'], true)) {
            // allow static assets (normally served by the web server anyway)
            if (str_starts_with($path, '/assets/')) {
                return false;
            }
        } else {
            return false;
        }

        try {
            if (Settings::get('maintenance_mode', '0') !== '1') {
                return false;
            }
        } catch (\Throwable) {
            return false;
        }

        return !Auth::isDean();
    }
}
