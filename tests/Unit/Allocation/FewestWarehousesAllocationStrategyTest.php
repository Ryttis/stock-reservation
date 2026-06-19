<?php

declare(strict_types=1);

namespace App\Tests\Unit\Allocation;

use App\Allocation\DTO\AllocationItem;
use App\Allocation\DTO\RequestedItem;
use App\Allocation\DTO\WarehouseInventory;
use App\Allocation\Strategy\FewestWarehousesAllocationStrategy;
use PHPUnit\Framework\TestCase;

final class FewestWarehousesAllocationStrategyTest extends TestCase
{
    private FewestWarehousesAllocationStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new FewestWarehousesAllocationStrategy();
    }

    private function standardWarehouses(): array
    {
        return [
            new WarehouseInventory('WH_A', ['PENCIL' => 10, 'NOTEBOOK' =>  2, 'BAG' =>  0, 'PEN' => 20, 'ERASER' =>  5]),
            new WarehouseInventory('WH_B', ['PENCIL' =>  5, 'NOTEBOOK' => 10, 'BAG' =>  3, 'PEN' =>  5, 'ERASER' => 20]),
            new WarehouseInventory('WH_C', ['PENCIL' => 100, 'NOTEBOOK' =>  0, 'BAG' =>  2, 'PEN' =>  1, 'ERASER' =>  0]),
        ];
    }

    public function testAllocatesFromSingleWarehouseWhenPossible(): void
    {
        $result = $this->strategy->allocate(
            [
                new RequestedItem('PENCIL', 8),
                new RequestedItem('NOTEBOOK', 2),
            ],
            [
                new WarehouseInventory('WH_A', ['PENCIL' => 10, 'NOTEBOOK' => 2]),
                new WarehouseInventory('WH_B', ['PENCIL' =>  5, 'NOTEBOOK' => 10]),
            ]
        );

        $this->assertTrue($result->isFullyAllocated());
        $this->assertSame(1, $result->getUsedWarehouseCount());
        $this->assertSame(['WH_A'], $result->getUsedWarehouseCodes());
        $this->assertEmpty($result->getMissingItems());
    }

    public function testPrefersSmallestSufficientSurplusWhenWarehouseCountIsEqual(): void
    {
        $result = $this->strategy->allocate(
            [
                new RequestedItem('PENCIL', 8),
                new RequestedItem('NOTEBOOK', 2),
            ],
            [
                new WarehouseInventory('WH_A', ['PENCIL' =>  10, 'NOTEBOOK' =>   2]),
                new WarehouseInventory('WH_B', ['PENCIL' => 100, 'NOTEBOOK' => 100]),
            ]
        );

        $this->assertTrue($result->isFullyAllocated());
        $this->assertSame(1, $result->getUsedWarehouseCount());
        $this->assertSame(['WH_A'], $result->getUsedWarehouseCodes());
    }

    public function testAllocatesFromFewestWarehouses(): void
    {
        $result = $this->strategy->allocate(
            [
                new RequestedItem('PENCIL', 12),
                new RequestedItem('NOTEBOOK', 8),
                new RequestedItem('BAG', 2),
            ],
            $this->standardWarehouses()
        );

        $this->assertTrue($result->isFullyAllocated());
        $this->assertSame(2, $result->getUsedWarehouseCount());
        $this->assertEmpty($result->getMissingItems());
    }

    public function testReturnsPartialAllocationWithMissingItems(): void
    {
        $result = $this->strategy->allocate(
            [
                new RequestedItem('BAG', 10),
                new RequestedItem('ERASER', 30),
            ],
            [
                new WarehouseInventory('WH_A', ['BAG' => 0, 'ERASER' =>  5]),
                new WarehouseInventory('WH_B', ['BAG' => 3, 'ERASER' => 20]),
                new WarehouseInventory('WH_C', ['BAG' => 2, 'ERASER' =>  0]),
            ]
        );

        $this->assertFalse($result->isFullyAllocated());
        $this->assertTrue($result->isPartiallyAllocated());

        $missing = $this->indexMissingBySku($result->getMissingItems());
        $this->assertArrayHasKey('BAG', $missing);
        $this->assertSame(5, $missing['BAG']->quantity);
        $this->assertArrayHasKey('ERASER', $missing);
        $this->assertSame(5, $missing['ERASER']->quantity);
    }

    public function testDoesNotUseWarehousesWithNoUsefulStock(): void
    {
        $result = $this->strategy->allocate(
            [new RequestedItem('PEN', 5)],
            [
                new WarehouseInventory('WH_A', ['NOTEBOOK' => 100]),
                new WarehouseInventory('WH_B', ['PEN' => 5]),
            ]
        );

        $this->assertTrue($result->isFullyAllocated());
        $this->assertSame(['WH_B'], $result->getUsedWarehouseCodes());
    }

    public function testAggregatesDuplicateRequestedSkus(): void
    {
        $result = $this->strategy->allocate(
            [
                new RequestedItem('PENCIL', 5),
                new RequestedItem('PENCIL', 7),
            ],
            [
                new WarehouseInventory('WH_A', ['PENCIL' => 10]),
                new WarehouseInventory('WH_B', ['PENCIL' =>  5]),
            ]
        );

        $this->assertTrue($result->isFullyAllocated());

        $pencilTotal = array_sum(array_map(
            static fn(AllocationItem $item) => $item->quantity,
            array_filter(
                $result->getAllocationItems(),
                static fn(AllocationItem $item) => $item->sku === 'PENCIL'
            )
        ));
        $this->assertSame(12, $pencilTotal);
    }

    public function testReturnsEmptyResultWhenNothingCanBeAllocated(): void
    {
        $result = $this->strategy->allocate(
            [new RequestedItem('BAG', 2)],
            [
                new WarehouseInventory('WH_A', ['PENCIL' => 10]),
                new WarehouseInventory('WH_B', ['NOTEBOOK' =>  5]),
            ]
        );

        $this->assertFalse($result->isFullyAllocated());
        $this->assertTrue($result->isEmpty());
        $this->assertEmpty($result->getAllocationItems());

        $missing = $this->indexMissingBySku($result->getMissingItems());
        $this->assertArrayHasKey('BAG', $missing);
        $this->assertSame(2, $missing['BAG']->quantity);
    }

    /**
     * @param \App\Allocation\DTO\MissingItem[] $items
     * @return array<string, \App\Allocation\DTO\MissingItem>
     */
    private function indexMissingBySku(array $items): array
    {
        $indexed = [];
        foreach ($items as $item) {
            $indexed[$item->sku] = $item;
        }

        return $indexed;
    }
}
