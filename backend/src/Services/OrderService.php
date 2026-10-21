<?php
declare(strict_types=1);

namespace NovaTech\Services;

use NovaTech\Core\Contracts\Transactor;
use NovaTech\Core\Exceptions\HttpException;
use NovaTech\Domain\{Order, User};
use NovaTech\Repositories\{OrderRepository, ProductRepository};
use NovaTech\Services\Payment\PaymentGateway;
use Throwable;

final class OrderService
{
    public function __construct(
        private readonly CartService $cart,
        private readonly ProductRepository $products,
        private readonly OrderRepository $orders,
        private readonly Transactor $tx,
        private readonly PaymentGateway $gateway,
        private readonly AuditLog $audit,
    ) {
    }

    /**
     * Flujo: reservar stock y crear el pedido (transacción) -> cobrar -> confirmar o liberar el stock.
     * El cobro queda fuera de la transacción de base de datos, como ocurriría con una pasarela real.
     */
    public function checkout(User $user, string $paymentMethod, string $ip): Order
    {
        $lines = $this->cart->lines($user);
        if ($lines === []) {
            throw new HttpException(422, 'empty_cart', 'El carrito está vacío.');
        }

        [$orderId, $total] = $this->tx->transaction(fn () => $this->reserve($user, $lines));
        $this->audit->record('order.created', $user->id, $ip, ['order_id' => $orderId, 'total_cents' => $total]);

        try {
            $result = $this->gateway->charge($total, $paymentMethod);
        } catch (Throwable $e) {
            $this->release($orderId, $lines);
            throw $e;
        }

        if (!$result->approved || $result->reference === null) {
            $this->release($orderId, $lines);
            $this->audit->record('order.payment_failed', $user->id, $ip, ['order_id' => $orderId]);
            throw new HttpException(402, 'payment_declined', 'El pago fue rechazado. Tu carrito se conserva.');
        }

        $this->orders->markPaid($orderId, $result->reference);
        $this->audit->record('order.paid', $user->id, $ip, ['order_id' => $orderId]);
        $this->cart->clear($user);

        return $this->find($user, $orderId);
    }

    /** @return list<Order> */
    public function list(User $user): array
    {
        return $this->orders->listForUser($user->id);
    }

    public function find(User $user, int $orderId): Order
    {
        return $this->orders->findForUser($orderId, $user->id)
            ?? throw new HttpException(404, 'order_not_found', 'Pedido no encontrado.');
    }

    /**
     * @param array<int, int> $lines
     * @return array{0: int, 1: int} [orderId, totalCents]
     */
    private function reserve(User $user, array $lines): array
    {
        $products = $this->products->lockActiveByIds(array_keys($lines));

        $total = 0;
        foreach ($lines as $productId => $quantity) {
            $product = $products[$productId] ?? throw new HttpException(409, 'product_unavailable', 'Un producto del carrito ya no está disponible.');
            if ($product->stock < $quantity) {
                throw new HttpException(409, 'insufficient_stock', "Stock insuficiente para {$product->name}.");
            }
            $total += $product->priceCents * $quantity;
        }

        $orderId = $this->orders->create($user->id, $total);
        foreach ($lines as $productId => $quantity) {
            $this->orders->addItem($orderId, $productId, $quantity, $products[$productId]->priceCents);
            if (!$this->products->decrementStock($productId, $quantity)) {
                throw new HttpException(409, 'insufficient_stock', 'El stock cambió durante la compra.');
            }
        }
        return [$orderId, $total];
    }

    /** @param array<int, int> $lines */
    private function release(int $orderId, array $lines): void
    {
        $this->tx->transaction(function () use ($orderId, $lines): void {
            foreach ($lines as $productId => $quantity) {
                $this->products->restoreStock($productId, $quantity);
            }
            $this->orders->markFailed($orderId);
        });
    }
}
