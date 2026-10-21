<?php
declare(strict_types=1);

namespace NovaTech\Http;

use NovaTech\Core\Exceptions\HttpException;
use NovaTech\Core\Request;
use NovaTech\Domain\User;

final class CurrentUser
{
    public static function of(Request $request): User
    {
        $user = $request->attribute('user');
        return $user instanceof User ? $user : throw new HttpException(401, 'unauthenticated', 'Debes iniciar sesión.');
    }
}
