<?php
declare(strict_types=1);

namespace NovaTech\Http\Middleware;

use NovaTech\Core\Contracts\Middleware;
use NovaTech\Core\{Logger, Request, Response};

/** Asigna un request-id y escribe el access log estructurado (insumo del SIEM). */
final class RequestContext implements Middleware
{
    public function __construct(private readonly Logger $logger)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $incoming = $request->header('x-request-id');
        // Solo se acepta un formato seguro (evita log injection); si no, se genera uno.
        $id = $incoming !== null && preg_match('/^[A-Za-z0-9-]{8,64}$/', $incoming) === 1
            ? $incoming : bin2hex(random_bytes(8));

        $start = microtime(true);
        $response = $next($request->withAttribute('request_id', $id));
        $response = $response->withHeader('X-Request-Id', $id);

        $this->logger->info('http_request', [
            'request_id' => $id,
            'method' => $request->method,
            'path' => $request->path,
            'route' => $request->context['route'] ?? null,
            'status' => $response->status,
            'duration_ms' => (int) round((microtime(true) - $start) * 1000),
            'user_id' => $request->context['user_id'] ?? null,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
        return $response;
    }
}
