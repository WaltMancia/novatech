<?php
declare(strict_types=1);

namespace NovaTech\Http\Middleware;

use NovaTech\Core\Contracts\{Middleware, RateLimitStore};
use NovaTech\Core\Exceptions\HttpException;
use NovaTech\Core\{Request, Response};

/** Límite de tasa por IP y por ruta (patrón, no URL, para que /x/1../x/N compartan cubo). */
final class RateLimit implements Middleware
{
    public function __construct(
        private readonly RateLimitStore $store,
        private readonly int $maxAttempts,
        private readonly int $windowSeconds,
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        $bucket = $request->method . ' ' . ($request->attribute('route') ?? 'global');
        ['count' => $count, 'resetAt' => $resetAt] = $this->store->hit(
            "{$bucket}|{$request->ip()}|{$this->maxAttempts}/{$this->windowSeconds}",
            $this->windowSeconds,
        );

        $limitHeaders = [
            'X-RateLimit-Limit' => (string) $this->maxAttempts,
            'X-RateLimit-Remaining' => (string) max(0, $this->maxAttempts - $count),
        ];
        if ($count > $this->maxAttempts) {
            throw new HttpException(429, 'rate_limited', 'Demasiadas solicitudes. Intenta de nuevo más tarde.',
                [...$limitHeaders, 'Retry-After' => (string) max(1, $resetAt - time())]);
        }

        $response = $next($request);
        foreach ($limitHeaders as $name => $value) {
            $response = $response->withHeader($name, $value);
        }
        return $response;
    }
}
