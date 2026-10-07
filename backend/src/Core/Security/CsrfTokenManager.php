<?php
declare(strict_types=1);

namespace NovaTech\Core\Security;

use NovaTech\Core\Contracts\Session;

/** Patrón "synchronizer token": el token vive en la sesión y se envía en X-CSRF-Token. */
final class CsrfTokenManager
{
    private const KEY = 'csrf_token';

    public function __construct(private readonly Session $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get(self::KEY);
        if (!is_string($token) || $token === '') {
            $token = $this->rotate();
        }
        return $token;
    }

    public function rotate(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->session->set(self::KEY, $token);
        return $token;
    }

    public function isValid(?string $given): bool
    {
        $expected = $this->session->get(self::KEY);
        return is_string($expected) && $expected !== '' && is_string($given) && hash_equals($expected, $given);
    }
}
