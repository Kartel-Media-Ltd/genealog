<?php
declare(strict_types=1);

namespace App\Core;

class NotFoundException extends \RuntimeException {}

class Router
{
    private array $routes = [];

    public function add(string $method, string $path, mixed $handler, array $middleware = []): static
    {
        $this->routes[] = [
            'method'     => strtoupper($method),
            'path'       => $path,
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
        return $this;
    }

    public function get(string $path, mixed $handler, array $middleware = []): static
    {
        return $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, mixed $handler, array $middleware = []): static
    {
        return $this->add('POST', $path, $handler, $middleware);
    }

    public function group(string $prefix, array $middleware, callable $callback): void
    {
        $sub = new self();
        $callback($sub);
        foreach ($sub->routes as $route) {
            $route['path']       = $prefix . $route['path'];
            $route['middleware'] = array_merge($middleware, $route['middleware']);
            $this->routes[] = $route;
        }
    }

    public function dispatch(Request $request): void
    {
        $method = $request->getMethod();
        $path   = $request->getPath();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;

            $params = $this->matchRoute($route['path'], $path);
            if ($params === null) continue;

            $request->setRouteParams($params);

            if (!$this->runMiddleware($route['middleware'], $request)) {
                return;
            }

            $this->callHandler($route['handler'], $request);
            return;
        }

        throw new NotFoundException("No route matched: {$method} {$path}");
    }

    private function matchRoute(string $routePath, string $requestPath): ?array
    {
        // Konwertuj {param} na regex
        $pattern = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $routePath);
        $pattern = '#^' . $pattern . '$#';

        if (!preg_match($pattern, $requestPath, $matches)) {
            return null;
        }

        // Zwróć tylko nazwane grupy
        return array_filter($matches, fn($k) => !is_int($k), ARRAY_FILTER_USE_KEY);
    }

    private function runMiddleware(array $middleware, Request $request): bool
    {
        foreach ($middleware as $mw) {
            if (!call_user_func($mw, $request)) {
                return false;
            }
        }
        return true;
    }

    private function callHandler(mixed $handler, Request $request): void
    {
        if (is_callable($handler)) {
            $handler($request);
            return;
        }

        if (is_array($handler) && count($handler) === 2) {
            [$object, $method] = $handler;
            $object->$method($request);
            return;
        }

        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler, 2);
            (new $class())->$method($request);
            return;
        }

        throw new \RuntimeException('Invalid route handler');
    }
}
