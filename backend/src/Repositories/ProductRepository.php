<?php
declare(strict_types=1);

namespace NovaTech\Repositories;

use NovaTech\Domain\Product;

interface ProductRepository
{
    /** @return list<Product> */
    public function listActive(): array;

    public function findActive(int $id): ?Product;

    /**
     * @param list<int> $ids
     * @return array<int, Product> indexado por id
     */
    public function findActiveByIds(array $ids): array;

    /**
     * Igual que findActiveByIds pero con bloqueo de filas; solo dentro de una transacción.
     *
     * @param list<int> $ids
     * @return array<int, Product>
     */
    public function lockActiveByIds(array $ids): array;

    /** Descuenta stock solo si alcanza; devuelve false si no hubo cambio. */
    public function decrementStock(int $id, int $quantity): bool;

    public function restoreStock(int $id, int $quantity): void;
}
