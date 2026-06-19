<?php

declare(strict_types=1);

namespace App\Service;

readonly class SampleDataSummary
{
    public function __construct(
        public int $products,
        public int $warehouses,
        public int $warehouseStocks,
        public int $orders,
        public int $orderItems,
        public bool $reset,
    ) {}
}
