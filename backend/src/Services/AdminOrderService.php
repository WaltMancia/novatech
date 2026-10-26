<?php
declare(strict_types=1);

namespace NovaTech\Services;

use NovaTech\Core\Contracts\Transactor;
use NovaTech\Core\Exceptions\HttpException;
use NovaTech\Domain\{Order, OrderStatus, User};
use NovaTech\Repositories\{OrderRepository, ProductRepository};

final class AdminOrderService
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly ProductRepository $products,
        private readonly Transactor $tx,
        private readonly AuditLog $audit,
    ) {
    }

    public function find(int $orderId): Order
    {
        return $this->orders->findAny($orderId) ?? throw new HttpException(404, 'order_not_found', 'Pedido no encontrado.');
    }

    /** Solo se cancelan pedidos pagados; el stock vuelve al inventario. */
    public function cancel(User $admin, int $orderId, string $ip): Order
    {
        $order = $this->find($orderId);

        $this->tx->transaction(function () use ($order): void {
            // La transición condicional garantiza que dos cancelaciones simultáneas no devuelvan el stock dos veces.
            if (!$this->orders->transition($order->id, OrderStatus::Paid, OrderStatus::Cancelled)) {
                throw new HttpException(409, 'invalid_transition', 'Solo se pueden cancelar pedidos pagados.');
            }
            foreach ($order->items as $item) {
                $this->products->restoreStock($item->productId, $item->quantity);
            }
        });

        $this->audit->record('admin.order_cancelled', $admin->id, $ip, ['order_id' => $order->id]);
        return $this->find($orderId);
    }
}
