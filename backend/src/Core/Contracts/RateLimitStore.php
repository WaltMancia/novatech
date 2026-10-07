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
}
