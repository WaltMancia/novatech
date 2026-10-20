<?php
declare(strict_types=1);

namespace NovaTech\Repositories;

use NovaTech\Core\Database;
use NovaTech\Domain\Product;

final class PdoProductRepository implements ProductRepository
{
    private const SELECT = 'SELECT id, sku, name, description, price_cents, stock, image_path, is_active FROM products';

    public function __construct(private readonly Database $db)
    {
    }

    public function listActive(): array
    {
        return array_map($this->hydrate(...), $this->db->fetchAll(self::SELECT . ' WHERE is_active = 1 ORDER BY id'));
    }

    public function findActive(int $id): ?Product
    {
        $row = $this->db->fetchOne(self::SELECT . ' WHERE is_active = 1 AND id = ?', [$id]);
        return $row === null ? null : $this->hydrate($row);
    }

    public function findActiveByIds(array $ids): array
    {
        return $this->byIds($ids, '');
    }

    public function lockActiveByIds(array $ids): array
    {
        // Orden por id fijo: evita interbloqueos entre pedidos concurrentes.
        return $this->byIds($ids, ' FOR UPDATE');
    }

    public function decrementStock(int $id, int $quantity): bool
    {
        return $this->db->execute(
            'UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?',
            [$quantity, $id, $quantity],
        ) === 1;
    }

    public function restoreStock(int $id, int $quantity): void
    {
        $this->db->execute('UPDATE products SET stock = stock + ? WHERE id = ?', [$quantity, $id]);
    }

    /**
     * @param list<int> $ids
     * @return array<int, Product>
     */
    private function byIds(array $ids, string $suffix): array
    {
        if ($ids === []) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->db->fetchAll(self::SELECT . " WHERE is_active = 1 AND id IN ({$marks}) ORDER BY id" . $suffix, array_values($ids));

        $out = [];
        foreach ($rows as $row) {
            $product = $this->hydrate($row);
            $out[$product->id] = $product;
        }
        return $out;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Product
    {
        return new Product(
            (int) $row['id'],
            (string) $row['sku'],
            (string) $row['name'],
            (string) $row['description'],
            (int) $row['price_cents'],
            (int) $row['stock'],
            $row['image_path'] === null ? null : (string) $row['image_path'],
            (bool) $row['is_active'],
        );
    }
}
