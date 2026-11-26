<?php
declare(strict_types=1);

namespace NovaTech\Repositories;

use NovaTech\Core\Database;
use InvalidArgumentException;
use NovaTech\Domain\{DuplicateSkuException, Product};
use PDOException;

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

    public function findAny(int $id): ?Product
    {
        $row = $this->db->fetchOne(self::SELECT . ' WHERE id = ?', [$id]);
        return $row === null ? null : $this->hydrate($row);
    }

    public function paginate(int $limit, int $offset): array
    {
        $total = (int) ($this->db->fetchOne('SELECT COUNT(*) AS n FROM products')['n'] ?? 0);
        // $limit y $offset son int tipados: se interpolan como literales.
        // nosemgrep: novatech-php-sql-interpolation - $limit y $offset son int tipados.
        $rows = $this->db->fetchAll(self::SELECT . " ORDER BY id LIMIT {$limit} OFFSET {$offset}");
        return ['items' => array_map($this->hydrate(...), $rows), 'total' => $total];
    }

    public function create(string $sku, string $name, string $description, int $priceCents, int $stock, ?string $imagePath): Product
    {
        try {
            $this->db->execute(
                'INSERT INTO products (sku, name, description, price_cents, stock, image_path) VALUES (?, ?, ?, ?, ?, ?)',
                [$sku, $name, $description, $priceCents, $stock, $imagePath],
            );
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                throw new DuplicateSkuException();
            }
            throw $e;
        }
        return new Product($this->db->lastInsertId(), $sku, $name, $description, $priceCents, $stock, $imagePath, true);
    }

    public function update(int $id, array $changes): void
    {
        // Lista blanca de columnas: los nombres de columna nunca provienen de la entrada del usuario.
        $columns = [
            'name' => 'name', 'description' => 'description', 'priceCents' => 'price_cents',
            'stock' => 'stock', 'imagePath' => 'image_path', 'active' => 'is_active',
        ];
        $sets = [];
        $params = [];
        foreach ($changes as $field => $value) {
            if (!isset($columns[$field])) {
                throw new InvalidArgumentException("Campo no actualizable: {$field}");
            }
            $sets[] = $columns[$field] . ' = ?';
            $params[] = is_bool($value) ? (int) $value : $value;
        }
        if ($sets === []) {
            return;
        }
        $params[] = $id;
        // nosemgrep: novatech-php-sql-interpolation - Las columnas salen de una lista blanca fija; los valores van enlazados.
        $this->db->execute('UPDATE products SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
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
        // nosemgrep: novatech-php-sql-interpolation - $marks solo contiene marcadores '?' y $suffix es un literal fijo.
        $rows = $this->db->fetchAll(self::SELECT . " WHERE is_active = 1 AND id IN ({$marks}) ORDER BY id" . $suffix, $ids);

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
