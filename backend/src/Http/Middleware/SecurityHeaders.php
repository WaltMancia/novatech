<?php
declare(strict_types=1);

namespace NovaTech\Http\Middleware;

use NovaTech\Core\Contracts\Middleware;
use NovaTech\Core\{Request, Response};

/** Cabeceras de seguridad para respuestas de la API (la SPA tiene su propia CSP en Nginx). */
final class SecurityHeaders implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'no-referrer',
            'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'",
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            'Cache-Control' => 'no-store',
        ];
        if ($request->isHttps()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }
        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }
        return $response;
    }
}
