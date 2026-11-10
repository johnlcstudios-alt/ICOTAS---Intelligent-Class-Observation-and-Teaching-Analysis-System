<?php

namespace App\Core;

use Closure;

/**
 * Explicit router with per-route middleware and global CSRF enforcement for
 * state-changing requests.
 */
final class Router
{
    private array $routes = [];

    public function get(string $pattern, array|Closure $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, array|Closure $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    private function add(string $method, string $pattern, array|Closure $handler, array $middleware): void
    {
        $regex = '#^' . preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            static fn (array $m): string => '(?P<' . $m[1] . '>[^/]+)',
            '/' . ltrim($pattern, '/')
        ) . '$#';

        $this->routes[] = [
            'method'     => $method,
            'pattern'    => $pattern,
            'regex'      => $regex,
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(): void
    {
        $method = Request::method();
        $path = Request::path();
        $methodMismatch = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            if ($route['method'] !== $method) {
                $methodMismatch = true;
                continue;
            }

            if (Request::isStateChanging() && !Csrf::validate()) {
                if (Request::wantsJson()) {
                    json_response(['success' => false, 'message' => 'Your security token expired. Refresh and try again.'], 419);
                }
                abort(419, 'Your security token expired. Refresh the page and try again.');
            }

            $this->runMiddleware($route['middleware']);

            $params = [];
            foreach ($matches as $key => $value) {
                if (!is_int($key)) {
                    $params[] = urldecode($value);
                }
            }

            $this->call($route['handler'], $params);
            return;
        }

        abort($methodMismatch ? 405 : 404);
    }

    private function runMiddleware(array $middleware): void
    {
        foreach ($middleware as $item) {
            [$name, $argument] = array_pad(explode(':', (string) $item, 2), 2, null);
            switch ($name) {
                case 'auth':
                    if (!Auth::check()) {
                        if (Request::wantsJson()) {
                            json_response(['success' => false, 'message' => 'Authentication required.'], 401);
                        }
                        $target = Request::path() . (!empty($_GET) ? '?' . http_build_query($_GET) : '');
                        Session::set('_intended', $target);
                        flash('error', 'Please sign in to continue.');
                        redirect('login');
                    }
                    break;
                case 'guest':
                    if (Auth::check()) {
                        redirect(Auth::dashboardPath());
                    }
                    break;
                case 'admin':
                    Auth::requireAdmin();
                    break;
                case 'role':
                    Auth::requireRole(array_map('trim', explode(',', (string) $argument)));
                    break;
                case 'csrf':
                    // Global enforcement already applies to all POST requests.
                    break;
                default:
                    throw new \RuntimeException('Unknown middleware: ' . $name);
            }
        }
    }

    private function call(array|Closure $handler, array $params): void
    {
        if ($handler instanceof Closure) {
            $handler(...$params);
            return;
        }

        [$class, $method] = $handler;
        if (!class_exists($class)) {
            throw new \RuntimeException('Route controller not found: ' . $class);
        }
        $controller = new $class();
        if (!method_exists($controller, $method)) {
            throw new \RuntimeException('Route action not found: ' . $class . '::' . $method);
        }
        $controller->{$method}(...$params);
    }
}
