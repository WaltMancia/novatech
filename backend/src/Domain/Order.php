<?php
declare(strict_types=1);

namespace NovaTech\Domain;

final readonly class Order
{
    /** @param list<OrderItem> $items */
    public function __construct(
        public int $id,
        public int $userId,
        public OrderStatus $status,
        public int $totalCents,
        public ?string $paymentRef,
        public string $createdAt,
        public array $items = [],
    ) {
    }

    public function toPublic(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'totalCents' => $this->totalCents,
            'paymentRef' => $this->paymentRef,
            'createdAt' => $this->createdAt,
            'items' => array_map(static fn (OrderItem $i) => $i->toPublic(), $this->items),
        ];
    }

    public function toAdmin(): array
    {
        return ['userId' => $this->userId, ...$this->toPublic()];
    }
}
