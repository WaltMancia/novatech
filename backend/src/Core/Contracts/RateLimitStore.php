<?php
declare(strict_types=1);

namespace NovaTech\Core\Contracts;

interface RateLimitStore
{
    /**
     * Registra un intento y devuelve el estado de la ventana fija.
     *
     * @return array{count: int, resetAt: int}
     */
    public function hit(string $key, int $windowSeconds): array;

    /**
     * Consulta el estado de una ventana sin registrar un intento.
     *
     * @return array{count: int, resetAt: int} count = 0 si no existe o ya venció
     */
    public function peek(string $key): array;
}
