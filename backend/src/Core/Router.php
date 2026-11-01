<?php
declare(strict_types=1);

namespace NovaTech\Core;

use Closure;
use NovaTech\Core\Contracts\Middleware;
use NovaTech\Core\Exceptions\HttpException;

/**
 * @phpstan-type Handler array{class-string, string}|Closure(Request): Response
 * @phpstan-type MiddlewareList list<string|Middleware>
 */
final class Router
{
    /** @var list<array{method: string, pattern: string, regex: string, handler: Handler, middleware: MiddlewareList}> */
    private array $routes = [];
    /** @var list<array{prefix: string, middleware: MiddlewareList}> */
    private array $stack = [['prefix' => '', 'middleware' => []]];

    /**
     * @param MiddlewareList $middleware
     * @param Closure(Router): void $define
     */
    public function group(string $prefix, array $middleware, Closure $define): void
    {
        $top = $this->top();
        $this->stack[] = ['prefix' => $top['prefix'] . $prefix, 'middleware' => [...$top['middleware'], ...$middleware]];
        $define($this);
        array_pop($this->stack);
    }

    /**
     * @param Handler $handler
     * @param MiddlewareList $middleware
     */
    public function get(string $path, array|Closure $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    /**
     * @param Handler $handler
     * @param MiddlewareList $middleware
     */
    public function post(string $path, array|Closure $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    /**
     * @param Handler $handler
     * @param MiddlewareList $middleware
     */
    public function put(string $path, array|Closure $handler, array $middleware = []): void
    {
        $this->add('PUT', $path, $handler, $middleware);
    }

    /**
     * @param Handler $handler
     * @param MiddlewareList $middleware
     */
    public function patch(string $path, array|Closure $handler, array $middleware = []): void
    {
        $this->add('PATCH', $path, $handler, $middleware);
    }

    /**
     * @param Handler $handler
     * @param MiddlewareList $middleware
     */
    public function delete(string $path, array|Closure $handler, array $middleware = []): void
    {
        $this->add('DELETE', $path, $handler, $middleware);
    }

    /**
     * @param Handler $handler
     * @param MiddlewareList $middleware
     */
    public function add(string $method, string $path, array|Closure $handler, array $middleware = []): void
    {
        $top = $this->top();
        $pattern = $top['prefix'] . $path;
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'regex' => $this->compile($pattern),
            'handler' => $handler,
            'middleware' => [...$top['middleware'], ...$middleware],
        ];
    }

    /** @return array{handler: Handler, params: array<string, string>, middleware: MiddlewareList, pattern: string} */
    public function dispatch(Request $request): array
    {
        $allowed = [];
        $method = $request->method === 'HEAD' ? 'GET' : $request->method;

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $request->path, $m) !== 1) {
                continue;
            }
            if ($route['method'] !== $method) {
                $allowed[] = $route['method'];
                continue;
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            return ['handler' => $route['handler'], 'params' => $params, 'middleware' => $route['middleware'], 'pattern' => $route['pattern']];
        }

        if ($allowed !== []) {
            throw new HttpException(405, 'method_not_allowed', 'Método no permitido.', ['Allow' => implode(', ', array_unique($allowed))]);
        }
        throw new HttpException(404, 'not_found', 'Recurso no encontrado.');
    }

    /** @return array{prefix: string, middleware: MiddlewareList} */
    private function top(): array
    {
        return $this->stack[count($this->stack) - 1];
    }

    private function compile(string $path): string
    {
        // El patrón de un parámetro admite un nivel de llaves: {id:\d{1,9}}
        $param = '\{\w+(?::(?:[^{}]|\{[^{}]*\})+)?\}';
        $parts = preg_split("#({$param})#", $path, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $regex = '';
        foreach ($parts as $part) {
            if (preg_match("#^\{(\w+)(?::(.+))?\}$#", $part, $m) === 1) {
                $regex .= '(?P<' . $m[1] . '>' . ($m[2] ?? '[^/]+') . ')';
            } else {
                $regex .= preg_quote($part, '#');
            }
        }
        return '#^' . $regex . '$#';
    }
}
