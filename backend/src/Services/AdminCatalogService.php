<?php
declare(strict_types=1);

namespace NovaTech\Services;

use NovaTech\Core\Exceptions\{HttpException, ValidationException};
use NovaTech\Domain\{DuplicateSkuException, Product, User};
use NovaTech\Repositories\ProductRepository;

final class AdminCatalogService
{
    public function __construct(private readonly ProductRepository $products, private readonly AuditLog $audit)
    {
    }

    /** @param array{sku: string, name: string, description: string, priceCents: int, stock: int, imagePath?: ?string} $data */
    public function create(User $admin, array $data, string $ip): Product
    {
        try {
            $product = $this->products->create(
                $data['sku'], $data['name'], $data['description'], $data['priceCents'], $data['stock'], $data['imagePath'] ?? null,
            );
        } catch (DuplicateSkuException) {
            throw new ValidationException(['sku' => ['Ya existe un producto con ese SKU.']]);
        }
        $this->audit->record('admin.product_created', $admin->id, $ip, ['product_id' => $product->id, 'sku' => $product->sku]);
        return $product;
    }

    /** @param array<string, scalar|null> $changes */
    public function update(User $admin, int $id, array $changes, string $ip): Product
    {
        $this->find($id);
        $this->products->update($id, $changes);
        $this->audit->record('admin.product_updated', $admin->id, $ip, ['product_id' => $id, 'fields' => implode(',', array_keys($changes))]);
        return $this->find($id);
    }

    /** Baja lógica: los pedidos históricos conservan la referencia al producto. */
    public function deactivate(User $admin, int $id, string $ip): void
    {
        $this->find($id);
        $this->products->update($id, ['active' => false]);
        $this->audit->record('admin.product_deactivated', $admin->id, $ip, ['product_id' => $id]);
    }

    public function find(int $id): Product
    {
        return $this->products->findAny($id) ?? throw new HttpException(404, 'product_not_found', 'Producto no encontrado.');
    }
}
