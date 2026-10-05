<?php
declare(strict_types=1);

namespace NovaTech\Core;

use Closure;
use LogicException;
use NovaTech\Core\Contracts\Middleware;

/** Kernel HTTP: compone la tubería de middleware y despacha al controlador. */
final class App
{
    /**
     * @param list<string|Middleware>                     $globalMiddleware
     * @param array<string, Closure(Container, list<string>): Middleware> $aliases
     */
    public function __construct(
        private readonly Container $container,
        private readonly Router $router,
        private readonly array $globalMiddleware,
        private readonly array $aliases = [],
    ) {
    }

    public function run(): void
    {
        $max = (int) $this->container->get(Config::class)->get('app.max_body_bytes', 1_048_576);
        $this->handle(Request::fromGlobals($max))->send();
    }

    public function handle(Request $request): Response
    {
        $core = function (Request $r): Response {
            $match = $this->router->dispatch($r);
            $r->context['route'] = $match['pattern'];
            $r = $r->withParams($match['params'])->withAttribute('route', $match['pattern']);
            $handler = $match['handler'];
            $final = fn (Request $req): Response => $this->invoke($handler, $req);
            return $this->pipe($match['middleware'], $final)($r);
        };

        return $this->pipe($this->globalMiddleware, $core)($request);
    }

    /** @param list<string|Middleware> $specs */
    private function pipe(array $specs, callable $core): callable
    {
        $next = $core;
        foreach (array_reverse($specs) as $spec) {
            $middleware = $this->resolveMiddleware($spec);
            $previous = $next;
            $next = static fn (Request $r): Response => $middleware->handle($r, $previous);
        }
        return $next;
    }

    private function resolveMiddleware(string|Middleware $spec): Middleware
    {
        if ($spec instanceof Middleware) {
            return $spec;
        }
        [$name, $args] = array_pad(explode(':', $spec, 2), 2, '');
        if (isset($this->aliases[$name])) {
            return ($this->aliases[$name])($this->container, $args === '' ? [] : explode(',', $args));
        }
        $instance = $this->container->get($spec);
        if (!$instance instanceof Middleware) {
            throw new LogicException("'{$spec}' no implementa Middleware.");
        }
        return $instance;
    }

    private function invoke(array|Closure $handler, Request $request): Response
    {
        $response = $handler instanceof Closure
            ? $handler($request)
            : [$this->container->get($handler[0]), $handler[1]]($request);

        if (!$response instanceof Response) {
            throw new LogicException('Un controlador debe devolver una Response.');
        }
        return $response;
    }
}
