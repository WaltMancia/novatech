<?php
declare(strict_types=1);

namespace NovaTech\Core\Contracts;

interface Transactor
{
    /**
     * Ejecuta $work en una transacción: commit si termina, rollback si lanza.
     *
     * @template T
     * @param callable(): T $work
     * @return T
     */
    public function transaction(callable $work): mixed;
}
