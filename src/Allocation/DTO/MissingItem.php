<?php

declare(strict_types=1);

namespace App\Allocation\DTO;

readonly class MissingItem
{
    public function __construct(
        public string $sku,
        public int $quantity,
    ) {
        if ($sku === '') {
            throw new \InvalidArgumentException('SKU must not be empty.');
        }
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be greater than 0.');
        }
    }
}
