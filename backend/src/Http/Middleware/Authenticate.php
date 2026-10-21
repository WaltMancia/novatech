<?php
declare(strict_types=1);

namespace NovaTech\Http\Middleware;

use NovaTech\Core\Contracts\{Middleware, Session};
use NovaTech\Core\Exceptions\HttpException;
use NovaTech\Core\{Request, Response};
use NovaTech\Repositories\UserRepository;
use NovaTech\Services\AuthService;

/** Exige sesión válida y recarga el usuario en cada petición (cambios de rol o borrado aplican de inmediato). */
final class Authenticate implements Middleware
{
    public function __construct(private readonly Session $session, private readonly UserRepository $users)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $id = $this->session->get(AuthService::SESSION_KEY);
        $user = is_int($id) ? $this->users->findById($id) : null;

        if ($user === null) {
            if ($id !== null) {
                $this->session->destroy();
            }
            throw new HttpException(401, 'unauthenticated', 'Debes iniciar sesión.');
        }

        $request->context['user_id'] = $user->id;
        return $next($request->withAttribute('user', $user));
    }
}
