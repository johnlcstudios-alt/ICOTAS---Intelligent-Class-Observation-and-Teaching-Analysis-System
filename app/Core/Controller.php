<?php

namespace App\Core;

abstract class Controller
{
    protected function view(string $view, array $data = [], string $layout = 'layouts/app'): void
    {
        $data['pageTitle'] = $data['pageTitle'] ?? 'Dashboard';
        View::render($view, $data, $layout);
    }

    protected function printView(string $view, array $data = []): void
    {
        View::render($view, $data, 'layouts/print');
    }

    protected function redirect(string $path): void
    {
        redirect($path);
    }

    protected function back(string $fallback = ''): void
    {
        back($fallback);
    }

    protected function json(array $data, int $status = 200): void
    {
        json_response($data, $status);
    }

    protected function requirePost(): void
    {
        if (!Request::isPost()) {
            abort(405, 'This action requires a POST request.');
        }
    }

    protected function input(string $key, $default = null)
    {
        return Request::input($key, $default);
    }

    protected function intInput(string $key, int $default = 0): int
    {
        $value = Request::input($key, $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    protected function mustValidate(array $data, array $rules, string $redirectPath, bool $preserveOld = true): array
    {
        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            if ($preserveOld) {
                Session::set('_old', $data);
            }
            flash('error', (string) $validator->firstError());
            redirect($redirectPath);
        }
        return $validator->validated();
    }

    protected function success(string $message, string $path = ''): void
    {
        flash('success', $message);
        if ($path !== '') {
            redirect($path);
        }
    }

    protected function error(string $message, string $path = ''): void
    {
        flash('error', $message);
        if ($path !== '') {
            redirect($path);
        }
    }

    protected function page(): int
    {
        return max(1, (int) Request::input('page', 1));
    }

    protected function respondJsonCsrfFailure(): void
    {
        json_response(['success' => false, 'message' => 'Your security token expired. Refresh the page.'], 419);
    }
}
