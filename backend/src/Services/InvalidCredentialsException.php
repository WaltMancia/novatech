<?php
declare(strict_types=1);

namespace NovaTech\Services;

use NovaTech\Core\Exceptions\HttpException;

final class InvalidCredentialsException extends HttpException
{
    public function __construct()
    {
        // Mensaje único para usuario inexistente, clave errónea y cuenta bloqueada (anti enumeración).
        parent::__construct(401, 'invalid_credentials', 'Credenciales inválidas o cuenta temporalmente bloqueada.');
    }
}
