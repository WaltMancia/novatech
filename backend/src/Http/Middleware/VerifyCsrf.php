<?php
declare(strict_types=1);

namespace NovaTech\Http\Middleware;

use NovaTech\Core\Contracts\Middleware;
use NovaTech\Core\Exceptions\HttpException;
use NovaTech\Core\Security\CsrfTokenManager;
use NovaTech\Core\{Config, Request, Response};

/** Exige token CSRF y Origin permitido en métodos que cambian estado. */
final class VerifyCsrf implements Middleware
{
    private const SAFE = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(private readonly CsrfTokenManager $csrf, private readonly Config $config)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        if (in_array($request->method, self::SAFE, true)) {
            return $next($request);
        }

        $origin = $request->header('origin');
        if ($origin !== null && !in_array($origin, (array) $this->config->get('app.allowed_origins', []), true)) {
            throw new HttpException(403, 'origin_not_allowed', 'Origen no permitido.');
        }
        if (!$this->csrf->isValid($request->header('x-csrf-token'))) {
            throw new HttpException(403, 'csrf_mismatch', 'Token CSRF inválido o ausente.');
        }
        return $next($request);
    }
}
