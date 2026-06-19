<?php

declare(strict_types=1);

namespace App\Allocation\DTO;

class AllocationResult
{
    /** @var AllocationItem[] */
    private array $allocationItems;

    /** @var MissingItem[] */
    private array $missingItems;

    /**
     * @param AllocationItem[] $allocationItems
     * @param MissingItem[]    $missingItems
     */
    public function __construct(array $allocationItems, array $missingItems)
    {
        foreach ($allocationItems as $item) {
            if (!$item instanceof AllocationItem) {
                throw new \InvalidArgumentException(
                    'All allocation items must be instances of AllocationItem.'
                );
            }
        }
        foreach ($missingItems as $item) {
            if (!$item instanceof MissingItem) {
                throw new \InvalidArgumentException(
                    'All missing items must be instances of MissingItem.'
                );
            }
        }

        $this->allocationItems = $allocationItems;
        $this->missingItems = $missingItems;
    }

    public function isFullyAllocated(): bool
    {
        return empty($this->missingItems);
    }

    public function isPartiallyAllocated(): bool
    {
        return !empty($this->allocationItems) && !empty($this->missingItems);
    }

    public function isEmpty(): bool
    {
        return empty($this->allocationItems);
    }

    /** @return AllocationItem[] */
    public function getAllocationItems(): array
    {
        return $this->allocationItems;
    }

    /** @return MissingItem[] */
    public function getMissingItems(): array
    {
        return $this->missingItems;
    }

    /** @return string[] */
    public function getUsedWarehouseCodes(): array
    {
        $codes = array_unique(
            array_map(static fn(AllocationItem $item) => $item->warehouseCode, $this->allocationItems)
        );
        sort($codes);

        return array_values($codes);
    }

    public function getUsedWarehouseCount(): int
    {
        return count($this->getUsedWarehouseCodes());
    }
}
