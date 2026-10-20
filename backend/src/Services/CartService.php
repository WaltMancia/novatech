<?php
declare(strict_types=1);

namespace NovaTech\Services;

use NovaTech\Core\Contracts\Session;
use NovaTech\Core\Exceptions\{HttpException, ValidationException};
use NovaTech\Domain\User;
use NovaTech\Repositories\ProductRepository;

/**
 * Carrito en la sesión del servidor: solo guarda id de producto y cantidad.
 * Los precios se leen siempre de la base de datos, nunca del cliente.
 */
final class CartService
{
    public const MAX_QUANTITY = 10;

    public function __construct(private readonly Session $session, private readonly ProductRepository $products)
    {
    }

    /** @return array{items: list<array<string, mixed>>, totalCents: int} */
    public function view(User $user): array
    {
        $lines = $this->lines($user);
        $products = $this->products->findActiveByIds(array_keys($lines));

        $items = [];
        $total = 0;
        foreach ($lines as $productId => $quantity) {
            $product = $products[$productId] ?? null;
            if ($product === null) {
                continue;
            }
            $subtotal = $product->priceCents * $quantity;
            $total += $subtotal;
            $items[] = [
                'productId' => $product->id,
                'name' => $product->name,
                'unitPriceCents' => $product->priceCents,
                'quantity' => $quantity,
                'subtotalCents' => $subtotal,
                'stock' => $product->stock,
            ];
        }
        return ['items' => $items, 'totalCents' => $total];
    }

    /** @return array<int, int> productId => cantidad */
    public function lines(User $user): array
    {
        $raw = $this->session->get($this->key($user), []);
        $lines = [];
        foreach (is_array($raw) ? $raw : [] as $productId => $quantity) {
            if (is_int($productId) && is_int($quantity) && $quantity > 0) {
                $lines[$productId] = $quantity;
            }
        }
        return $lines;
    }

    public function add(User $user, int $productId, int $quantity): void
    {
        $lines = $this->lines($user);
        $this->setQuantity($user, $productId, ($lines[$productId] ?? 0) + $quantity);
    }

    public function setQuantity(User $user, int $productId, int $quantity): void
    {
        $product = $this->products->findActive($productId);
        if ($product === null) {
            throw new HttpException(404, 'product_not_found', 'Producto no encontrado.');
        }
        $limit = min(self::MAX_QUANTITY, $product->stock);
        if ($quantity < 1 || $quantity > $limit) {
            throw new ValidationException(['quantity' => ["La cantidad debe estar entre 1 y {$limit}."]]);
        }
        $lines = $this->lines($user);
        $lines[$productId] = $quantity;
        $this->session->set($this->key($user), $lines);
    }

    public function remove(User $user, int $productId): void
    {
        $lines = $this->lines($user);
        unset($lines[$productId]);
        $this->session->set($this->key($user), $lines);
    }

    public function clear(User $user): void
    {
        $this->session->remove($this->key($user));
    }

    // Un carrito por usuario: si cambia la cuenta en la misma sesión, no se hereda el carrito anterior.
    private function key(User $user): string
    {
        return 'cart_u' . $user->id;
    }
}
