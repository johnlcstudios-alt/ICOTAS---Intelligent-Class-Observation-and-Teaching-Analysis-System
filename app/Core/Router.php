<?php

namespace App\Core;

use Closure;

/**
 * Small explicit router with route-level middleware and automatic CSRF
 * enforcement for state-changing HTTP methods.
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
            $pattern
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

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            if (Middleware::maintenanceBlocked($path)) {
                abort(503, 'ICOTAS is temporarily unavailable for maintenance. Please try again later.');
            }

            if (Request::isStateChanging() && !Csrf::validate(Csrf::fromRequest())) {
                if (Request::wantsJson()) {
                    json_response([
                        'success' => false,
                        'message' => 'Your security token expired. Refresh the page and try again.',
                    ], 419);
                }
                abort(419, 'Your security token expired. Refresh the page and try again.');
            }

            Middleware::run($route['middleware']);

            $params = [];
            foreach ($matches as $key => $value) {
                if (!is_int($key)) {
                    $params[] = $value;
                }
            }

            $this->call($route['handler'], $params);
            return;
        }

        abort(404);
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
