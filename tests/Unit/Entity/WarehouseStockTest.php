<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Product;
use App\Entity\Warehouse;
use App\Entity\WarehouseStock;
use PHPUnit\Framework\TestCase;

final class WarehouseStockTest extends TestCase
{
    private Warehouse $warehouse;
    private Product $product;

    protected function setUp(): void
    {
        $this->warehouse = new Warehouse('WH_A', 'Warehouse A');
        $this->product = new Product('PENCIL', 'Pencil');
    }

    public function testConstructorAcceptsPositiveQuantity(): void
    {
        $stock = new WarehouseStock($this->warehouse, $this->product, 10);

        $this->assertSame(10, $stock->getQuantity());
    }

    public function testConstructorAcceptsZeroQuantity(): void
    {
        $stock = new WarehouseStock($this->warehouse, $this->product, 0);

        $this->assertSame(0, $stock->getQuantity());
    }

    public function testSetQuantityRejectsNegative(): void
    {
        $stock = new WarehouseStock($this->warehouse, $this->product, 5);

        $this->expectException(\InvalidArgumentException::class);
        $stock->setQuantity(-1);
    }

    public function testIncreaseQuantityIncreasesStock(): void
    {
        $stock = new WarehouseStock($this->warehouse, $this->product, 10);
        $result = $stock->increaseQuantity(5);

        $this->assertSame(15, $stock->getQuantity());
        $this->assertSame($stock, $result);
    }

    public function testIncreaseQuantityRejectsZeroAmount(): void
    {
        $stock = new WarehouseStock($this->warehouse, $this->product, 10);

        $this->expectException(\InvalidArgumentException::class);
        $stock->increaseQuantity(0);
    }

    public function testIncreaseQuantityRejectsNegativeAmount(): void
    {
        $stock = new WarehouseStock($this->warehouse, $this->product, 10);

        $this->expectException(\InvalidArgumentException::class);
        $stock->increaseQuantity(-3);
    }

    public function testDecreaseQuantityDecreasesStock(): void
    {
        $stock = new WarehouseStock($this->warehouse, $this->product, 10);
        $result = $stock->decreaseQuantity(4);

        $this->assertSame(6, $stock->getQuantity());
        $this->assertSame($stock, $result);
    }

    public function testDecreaseQuantityRejectsZeroAmount(): void
    {
        $stock = new WarehouseStock($this->warehouse, $this->product, 10);

        $this->expectException(\InvalidArgumentException::class);
        $stock->decreaseQuantity(0);
    }

    public function testDecreaseQuantityRejectsNegativeAmount(): void
    {
        $stock = new WarehouseStock($this->warehouse, $this->product, 10);

        $this->expectException(\InvalidArgumentException::class);
        $stock->decreaseQuantity(-2);
    }

    public function testDecreaseQuantityRejectsDecreaseBelowZero(): void
    {
        $stock = new WarehouseStock($this->warehouse, $this->product, 5);

        $this->expectException(\DomainException::class);
        $stock->decreaseQuantity(6);
    }
}
