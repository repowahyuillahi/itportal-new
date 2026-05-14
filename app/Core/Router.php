<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Tiny router supporting GET/POST, `{param}` path placeholders, and a
 * per-route middleware pipeline.
 *
 * Handlers may be:
 *   - a Closure
 *   - [ControllerClass::class, 'method']
 *   - 'ControllerClass@method'
 *
 * Middleware can be:
 *   - a string class name (must expose `handle(Request, callable): string`)
 *   - an instance with the same `handle` method
 *   - a closure `(Request, callable $next) => string`
 *
 * Controllers receive (Request $request, array $params) and return a string
 * (rendered HTML/JSON) or void/null (already echoed).
 */
final class Router
{
    /** @var array<int, array{method:string, pattern:string, regex:string, params:array<int,string>, handler:mixed, middleware:array<int,mixed>}> */
    private array $routes = [];

    /** @param array<int,mixed> $middleware */
    public function get(string $pattern, mixed $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    /** @param array<int,mixed> $middleware */
    public function post(string $pattern, mixed $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    /** @param array<int,mixed> $middleware */
    private function add(string $method, string $pattern, mixed $handler, array $middleware): void
    {
        $params = [];
        $regex = preg_replace_callback('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', function ($m) use (&$params) {
            $params[] = $m[1];
            return '([^/]+)';
        }, $pattern);
        $regex = '#^' . $regex . '$#';

        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'regex' => $regex,
            'params' => $params,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(Request $request): string
    {
        $method = $request->method();
        $path = $request->path();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (preg_match($route['regex'], $path, $matches)) {
                array_shift($matches);
                $params = array_combine($route['params'], $matches) ?: [];
                return $this->runPipeline($route['middleware'], $route['handler'], $request, $params);
            }
        }

        return Response::errorPage(404, '404 Not Found',
            'Halaman "' . $path . '" tidak ditemukan.');
    }

    /**
     * @param array<int,mixed> $middleware
     * @param array<string,string> $params
     */
    private function runPipeline(array $middleware, mixed $handler, Request $request, array $params): string
    {
        $core = function (Request $req) use ($handler, $params): string {
            return $this->call($handler, $req, $params);
        };

        // Build chain in reverse so first item runs first.
        $next = $core;
        foreach (array_reverse($middleware) as $mw) {
            $instance = $this->resolveMiddleware($mw);
            $current = $next;
            $next = function (Request $req) use ($instance, $current): string {
                $out = $instance->handle($req, $current);
                return is_string($out) ? $out : '';
            };
        }
        return $next($request);
    }

    /** @return object{handle: callable} */
    private function resolveMiddleware(mixed $mw): object
    {
        if (is_object($mw) && method_exists($mw, 'handle')) {
            /** @var object{handle: callable} $mw */
            return $mw;
        }
        if (is_string($mw) && class_exists($mw)) {
            /** @var object{handle: callable} $obj */
            $obj = new $mw();
            return $obj;
        }
        if ($mw instanceof \Closure) {
            return new class ($mw) {
                /** @var \Closure */
                private $fn;
                public function __construct(\Closure $fn) { $this->fn = $fn; }
                public function handle(Request $req, callable $next): string
                {
                    $out = ($this->fn)($req, $next);
                    return is_string($out) ? $out : '';
                }
            };
        }
        throw new \RuntimeException('Invalid middleware definition.');
    }

    /** @param array<string,string> $params */
    private function call(mixed $handler, Request $request, array $params): string
    {
        if ($handler instanceof \Closure) {
            $out = $handler($request, $params);
            return is_string($out) ? $out : '';
        }
        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler, 2);
        } elseif (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
        } else {
            return Response::serverError('Invalid route handler.');
        }
        if (!class_exists($class)) {
            return Response::serverError('Controller not found: ' . $class);
        }
        $instance = new $class();
        if (!method_exists($instance, $method)) {
            return Response::serverError("Method not found: $class::$method");
        }
        $out = $instance->{$method}($request, $params);
        return is_string($out) ? $out : '';
    }
}
