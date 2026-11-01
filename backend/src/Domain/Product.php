<?php
declare(strict_types=1);

namespace NovaTech\Domain;

final readonly class Product
{
    public function __construct(
        public int $id,
        public string $sku,
        public string $name,
        public string $description,
        public int $priceCents,
        public int $stock,
        public ?string $imagePath = null,
        public bool $active = true,
    ) {
    }

    /** @return array<string, mixed> */
    public function toPublic(): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'priceCents' => $this->priceCents,
            'stock' => $this->stock,
            'imagePath' => $this->imagePath,
        ];
    }
}
