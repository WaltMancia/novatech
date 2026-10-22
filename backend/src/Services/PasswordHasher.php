<?php
declare(strict_types=1);

namespace NovaTech\Services;

use RuntimeException;

/** Argon2id con los parámetros mínimos recomendados por OWASP (19 MiB, 2 iteraciones, 1 hilo). */
final class PasswordHasher
{
    public const OPTIONS = ['memory_cost' => 19456, 'time_cost' => 2, 'threads' => 1];

    // Hash de una clave aleatoria con OPTIONS: iguala el tiempo de respuesta cuando el usuario no existe.
    // Si cambia OPTIONS, regenerar con password_hash(); una prueba detecta el desajuste.
    private const DUMMY = '$argon2id$v=19$m=19456,t=2,p=1$TUFROGU3Q0trVG1sUGFVTQ$Iqt/0Wn2Q7RVNa5SYEcmsB3ulPGUJxYGSA8CiOf+RSY';

    public function __construct()
    {
        if (!defined('PASSWORD_ARGON2ID')) {
            throw new RuntimeException('PHP sin soporte Argon2id.');
        }
    }

    public function hash(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, self::OPTIONS);
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, self::OPTIONS);
    }

    public function burn(string $password): void
    {
        password_verify($password, self::DUMMY);
    }

    public static function dummyHash(): string
    {
        return self::DUMMY;
    }
}
