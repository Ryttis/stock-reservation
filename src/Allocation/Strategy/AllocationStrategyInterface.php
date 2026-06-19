<?php

declare(strict_types=1);

namespace App\Allocation\Strategy;

use App\Allocation\DTO\AllocationResult;
use App\Allocation\DTO\RequestedItem;
use App\Allocation\DTO\WarehouseInventory;

interface AllocationStrategyInterface
{
    /**
     * @param RequestedItem[]     $requestedItems
     * @param WarehouseInventory[] $warehouses
     */
    public function allocate(array $requestedItems, array $warehouses): AllocationResult;
}
