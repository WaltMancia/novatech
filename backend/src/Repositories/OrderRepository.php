<?php
declare(strict_types=1);

namespace NovaTech\Repositories;

use NovaTech\Domain\Order;

interface OrderRepository
{
    public function create(int $userId, int $totalCents): int;

    public function addItem(int $orderId, int $productId, int $quantity, int $unitPriceCents): void;

    public function markPaid(int $orderId, string $paymentRef): void;

    public function markFailed(int $orderId): void;

    /** La propiedad se verifica en la propia consulta: un pedido ajeno es indistinguible de uno inexistente. */
    public function findForUser(int $orderId, int $userId): ?Order;

    /** @return list<Order> */
    public function listForUser(int $userId): array;
}
