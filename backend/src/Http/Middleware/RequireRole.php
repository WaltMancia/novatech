<?php
declare(strict_types=1);

namespace NovaTech\Http\Middleware;

use NovaTech\Core\Contracts\Middleware;
use NovaTech\Core\Exceptions\HttpException;
use NovaTech\Core\{Request, Response};
use NovaTech\Domain\{Role, User};

/** Debe ir después de Authenticate. Deniega por defecto si no hay usuario o el rol no coincide. */
final class RequireRole implements Middleware
{
    public function __construct(private readonly Role $role)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $user = $request->attribute('user');
        if (!$user instanceof User || $user->role !== $this->role) {
            throw new HttpException(403, 'forbidden', 'No tienes permiso para esta acción.');
        }
        return $next($request);
    }
}
