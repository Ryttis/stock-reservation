<?php

declare(strict_types=1);

namespace App\Allocation\DTO;

readonly class WarehouseInventory
{
    /**
     * @param array<string, int> $stockBySku
     */
    public function __construct(
        public string $warehouseCode,
        public array $stockBySku,
    ) {
        if ($warehouseCode === '') {
            throw new \InvalidArgumentException('Warehouse code must not be empty.');
        }
        foreach ($stockBySku as $sku => $quantity) {
            if (!is_int($quantity) || $quantity < 0) {
                throw new \InvalidArgumentException(
                    "Stock quantity for SKU '$sku' must be a non-negative integer."
                );
            }
        }
    }
}
