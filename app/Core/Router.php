<?php

namespace App\Core;

use App\Exceptions\AppException;
use App\Exceptions\HttpException;
use App\Exceptions\NotFoundException;
use App\Http\Middleware\Middleware;
use App\Core\Response;

/**
 * Pattern-matching router with middleware support.
 *
 * Handlers are either closures or "Controller@method" strings. Middleware
 * classes are resolved from the container and executed in declaration order.
 */
final class Router
{
    /** @var array<int,array> */
    private $routes = [];
    /** @var array<string,string> */
    private $names = [];
    /** @var array<int,string> */
    private $globalMiddleware = [];

    public function addGlobalMiddleware($middleware)
    {
        $this->globalMiddleware[] = $middleware;
        return $this;
    }

    public function get($path, $handler, array $middleware = [], $name = null)
    {
        return $this->add('GET', $path, $handler, $middleware, $name);
    }

    public function post($path, $handler, array $middleware = [], $name = null)
    {
        return $this->add('POST', $path, $handler, $middleware, $name);
    }

    public function put($path, $handler, array $middleware = [], $name = null)
    {
        return $this->add('PUT', $path, $handler, $middleware, $name);
    }

    public function delete($path, $handler, array $middleware = [], $name = null)
    {
        return $this->add('DELETE', $path, $handler, $middleware, $name);
    }

    public function any($path, $handler, array $middleware = [], $name = null)
    {
        return $this->add('*', $path, $handler, $middleware, $name);
    }

    public function add($method, $path, $handler, array $middleware = [], $name = null)
    {
        $route = [
            'method'     => strtoupper($method),
            'path'       => '/' . trim($path, '/'),
            'pattern'    => $this->compile($path),
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
        if ($route['path'] === '//') {
            $route['path'] = '/';
        }
        $this->routes[] = $route;
        if ($name !== null) {
            $this->names[$name] = $route['path'];
        }
        return $this;
    }

    private function compile($path)
    {
        $path = '/' . trim($path, '/');
        if ($path === '/') {
            return '#^/$#';
        }
        // The separator slash that precedes a placeholder belongs to the
        // placeholder so optional parameters such as {name?} compile to
        // `/page(?:/(...))?` instead of `/page/(?:/(...))?`.
        $pattern = preg_replace_callback('#(/?)\{([a-zA-Z_][a-zA-Z0-9_]*)(\?)?\}#', static function ($m) {
            $slash = $m[1];
            $inner = $m[2] !== 'id' ? '[A-Za-z0-9._~-]+' : '[0-9]+';
            if (isset($m[3]) && $m[3] === '?') {
                return '(?:' . $slash . '(?P<' . $m[2] . '>' . $inner . '))?';
            }
            return $slash . '(?P<' . $m[2] . '>' . $inner . ')';
        }, $path);
        return '#^' . $pattern . '$#';
    }

    public function url($name, array $params = [])
    {
        if (!isset($this->names[$name])) {
            throw new AppException('Unknown route name: ' . $name);
        }
        $path = $this->names[$name];
        foreach ($params as $key => $value) {
            $path = str_replace('{' . $key . '}', rawurlencode((string) $value), $path);
        }
        return $path;
    }

    /** @return \App\Core\Response */
    public function dispatch(Request $request)
    {
        $path = $request->path();
        $method = $request->method();
        $methodMismatch = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['pattern'], $path, $matches)) {
                continue;
            }
            if ($route['method'] !== '*' && $route['method'] !== $method) {
                $methodMismatch = true;
                continue;
            }
            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key) && $value !== '' && $value !== null) {
                    $params[$key] = $value;
                }
            }
            $request->setRouteParams($params);

            $stack = array_merge($this->globalMiddleware, $route['middleware']);

            return $this->runPipeline($request, $stack, function (Request $req) use ($route) {
                return $this->callHandler($route['handler'], $req);
            });
        }

        if ($methodMismatch) {
            throw new HttpException(405, 'That method is not allowed for this address.');
        }
        throw new NotFoundException();
    }

    private function runPipeline(Request $request, array $stack, callable $destination)
    {
        $next = $destination;
        foreach (array_reverse($stack) as $middleware) {
            $nextHandler = $next;
            $next = function (Request $req) use ($middleware, $nextHandler) {
                $instance = is_string($middleware) ? new $middleware() : $middleware;
                if (!$instance instanceof Middleware) {
                    throw new AppException('Middleware must implement ' . Middleware::class . ': ' . (is_object($middleware) ? get_class($middleware) : (string) $middleware));
                }
                return $instance->handle($req, $nextHandler);
            };
        }
        return $next($request);
    }

    private function callHandler($handler, Request $request)
    {
        if (is_string($handler) && strpos($handler, '@') !== false) {
            list($class, $action) = explode('@', $handler, 2);
            $class = 'App\\Controllers\\' . $class;
            $instance = new $class();
            if (!method_exists($instance, $action)) {
                throw new AppException('Controller action not found: ' . $class . '@' . $action);
            }
            $result = $instance->{$action}($request);
        } elseif (is_callable($handler)) {
            $result = call_user_func($handler, $request);
        } else {
            throw new AppException('Invalid route handler.');
        }

        if ($result instanceof Response) {
            return $result;
        }
        if (is_array($result) || is_object($result)) {
            return Response::json($result);
        }
        return Response::html((string) $result);
    }

    public function routes()
    {
        return $this->routes;
    }

    public function names()
    {
        return $this->names;
    }
}
