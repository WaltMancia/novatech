<?php
declare(strict_types=1);

namespace NovaTech\Domain;

final readonly class OrderItem
{
    public function __construct(
        public int $productId,
        public string $name,
        public int $quantity,
        public int $unitPriceCents,
    ) {
    }

    /** @return array<string, mixed> */
    public function toPublic(): array
    {
        return [
            'productId' => $this->productId,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'unitPriceCents' => $this->unitPriceCents,
            'subtotalCents' => $this->unitPriceCents * $this->quantity,
        ];
    }
}
