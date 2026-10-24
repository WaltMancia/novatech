<?php
declare(strict_types=1);

namespace NovaTech\Repositories;

use NovaTech\Domain\{DuplicateSkuException, Product};

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

    /** Incluye productos inactivos (uso administrativo). */
    public function findAny(int $id): ?Product;

    /** @return array{items: list<Product>, total: int} */
    public function paginate(int $limit, int $offset): array;

    /** @throws DuplicateSkuException */
    public function create(string $sku, string $name, string $description, int $priceCents, int $stock, ?string $imagePath): Product;

    /** @param array<string, scalar|null> $changes claves permitidas: name, description, priceCents, stock, imagePath, active */
    public function update(int $id, array $changes): void;
}
