<?php

declare(strict_types=1);

namespace App\Allocation\Strategy;

use App\Allocation\DTO\AllocationItem;
use App\Allocation\DTO\AllocationResult;
use App\Allocation\DTO\MissingItem;
use App\Allocation\DTO\RequestedItem;
use App\Allocation\DTO\WarehouseInventory;

final class FewestWarehousesAllocationStrategy implements AllocationStrategyInterface
{
    /**
     * @param RequestedItem[]      $requestedItems
     * @param WarehouseInventory[] $warehouses
     */
    public function allocate(array $requestedItems, array $warehouses): AllocationResult
    {
        $needed = $this->aggregateRequested($requestedItems);

        if ($needed === []) {
            return new AllocationResult([], []);
        }

        $useful = $this->filterUseful($warehouses, $needed);

        if ($useful === []) {
            return $this->buildMissingOnly($needed);
        }

        usort($useful, static fn(WarehouseInventory $a, WarehouseInventory $b)
            => strcmp($a->warehouseCode, $b->warehouseCode));

        $total = count($useful);
        for ($k = 1; $k <= $total; ++$k) {
            $best = $this->findBestSubset($this->combinations($useful, $k), $needed);
            if ($best !== null) {
                return $this->buildResult($best, $needed);
            }
        }

        return $this->buildResult($useful, $needed);
    }

    /**
     * @param RequestedItem[]    $requestedItems
     * @return array<string, int>
     */
    private function aggregateRequested(array $requestedItems): array
    {
        $needed = [];
        foreach ($requestedItems as $item) {
            $needed[$item->sku] = ($needed[$item->sku] ?? 0) + $item->quantity;
        }
        ksort($needed);

        return $needed;
    }

    /**
     * @param WarehouseInventory[] $warehouses
     * @param array<string, int>   $needed
     * @return WarehouseInventory[]
     */
    private function filterUseful(array $warehouses, array $needed): array
    {
        return array_values(array_filter(
            $warehouses,
            function (WarehouseInventory $wh) use ($needed): bool {
                foreach (array_keys($needed) as $sku) {
                    if (($wh->stockBySku[$sku] ?? 0) > 0) {
                        return true;
                    }
                }
                return false;
            }
        ));
    }

    /**
     * @param WarehouseInventory[][] $subsets
     * @param array<string, int>     $needed
     * @return WarehouseInventory[]|null
     */
    private function findBestSubset(array $subsets, array $needed): ?array
    {
        $bestSubset = null;
        $bestScore = PHP_INT_MAX;

        foreach ($subsets as $subset) {
            if ($this->canFulfill($subset, $needed)) {
                $score = $this->surplusScore($subset, $needed);
                if ($score < $bestScore) {
                    $bestScore = $score;
                    $bestSubset = $subset;
                }
            }
        }

        return $bestSubset;
    }

    /**
     * @param WarehouseInventory[] $subset
     * @param array<string, int>   $needed
     */
    private function canFulfill(array $subset, array $needed): bool
    {
        foreach ($needed as $sku => $required) {
            $available = 0;
            foreach ($subset as $wh) {
                $available += $wh->stockBySku[$sku] ?? 0;
            }
            if ($available < $required) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param WarehouseInventory[] $subset
     * @param array<string, int>   $needed
     */
    private function surplusScore(array $subset, array $needed): int
    {
        $surplus = 0;
        foreach ($needed as $sku => $required) {
            $available = 0;
            foreach ($subset as $wh) {
                $available += $wh->stockBySku[$sku] ?? 0;
            }
            $surplus += max(0, $available - $required);
        }

        return $surplus;
    }

    /**
     * @template T
     * @param T[] $items
     * @return T[][]
     */
    private function combinations(array $items, int $k): array
    {
        if ($k === 0) {
            return [[]];
        }
        if ($items === []) {
            return [];
        }

        $first = array_shift($items);

        $withFirst = array_map(
            static fn(array $combo) => array_merge([$first], $combo),
            $this->combinations($items, $k - 1)
        );
        $withoutFirst = $this->combinations($items, $k);

        return array_merge($withFirst, $withoutFirst);
    }

    /**
     * @param WarehouseInventory[] $subset
     * @param array<string, int>   $needed
     */
    private function buildResult(array $subset, array $needed): AllocationResult
    {
        $allocationItems = [];
        $missingItems = [];

        foreach ($needed as $sku => $required) {
            $remaining = $required;

            foreach ($subset as $wh) {
                $available = $wh->stockBySku[$sku] ?? 0;
                if ($available <= 0) {
                    continue;
                }

                $allocate = min($remaining, $available);
                $allocationItems[] = new AllocationItem($wh->warehouseCode, $sku, $allocate);
                $remaining -= $allocate;

                if ($remaining === 0) {
                    break;
                }
            }

            if ($remaining > 0) {
                $missingItems[] = new MissingItem($sku, $remaining);
            }
        }

        return new AllocationResult($allocationItems, $missingItems);
    }

    /**
     * @param array<string, int> $needed
     */
    private function buildMissingOnly(array $needed): AllocationResult
    {
        $missing = [];
        foreach ($needed as $sku => $quantity) {
            $missing[] = new MissingItem($sku, $quantity);
        }

        return new AllocationResult([], $missing);
    }
}
