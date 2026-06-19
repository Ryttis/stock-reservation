<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CustomerOrder;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\Warehouse;
use App\Entity\WarehouseStock;
use Doctrine\ORM\EntityManagerInterface;

final class SampleDataGenerator
{
    /** @var array<string, string> */
    private const PRODUCTS = [
        'PENCIL'   => 'Pencil',
        'NOTEBOOK' => 'Notebook',
        'BAG'      => 'Bag',
        'PEN'      => 'Pen',
        'ERASER'   => 'Eraser',
    ];

    /** @var array<string, string> */
    private const WAREHOUSES = [
        'WH_A' => 'Warehouse A',
        'WH_B' => 'Warehouse B',
        'WH_C' => 'Warehouse C',
    ];

    /** @var array<string, array<string, int>> */
    private const STOCK = [
        'WH_A' => ['PENCIL' => 10, 'NOTEBOOK' =>  2, 'BAG' =>  0, 'PEN' => 20, 'ERASER' =>  5],
        'WH_B' => ['PENCIL' =>  5, 'NOTEBOOK' => 10, 'BAG' =>  3, 'PEN' =>  5, 'ERASER' => 20],
        'WH_C' => ['PENCIL' => 100, 'NOTEBOOK' =>  0, 'BAG' =>  2, 'PEN' =>  1, 'ERASER' =>  0],
    ];

    /**
     * @var array<int, array<string, int>>
     *
     * Four scenarios for the reservation algorithm:
     *   [0] single warehouse — WH_A covers PENCIL:10, NOTEBOOK:2
     *   [1] multiple warehouses required
     *   [2] partial reservation — total BAG:5, ERASER:25, both short
     *   [3] competing order — requests all PENCIL stock (total 115 across warehouses)
     */
    private const ORDERS = [
        ['PENCIL' => 8, 'NOTEBOOK' => 2],
        ['PENCIL' => 12, 'NOTEBOOK' => 8, 'BAG' => 2],
        ['BAG' => 10, 'ERASER' => 30],
        ['PENCIL' => 100],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    public function seed(bool $reset = false): SampleDataSummary
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();

        try {
            if ($reset) {
                $this->deleteAll();
                $this->em->clear();
            }

            [$products, $productsCount] = $this->seedProducts();
            [$warehouses, $warehousesCount] = $this->seedWarehouses();
            $stocksCount = $this->seedStock($warehouses, $products);
            [$ordersCount, $itemsCount] = $this->seedOrders($products);

            $this->em->flush();
            $connection->commit();

            return new SampleDataSummary(
                products: $productsCount,
                warehouses: $warehousesCount,
                warehouseStocks: $stocksCount,
                orders: $ordersCount,
                orderItems: $itemsCount,
                reset: $reset,
            );
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    private function deleteAll(): void
    {
        $this->em->createQuery('DELETE FROM App\Entity\ReservationItem ri')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Reservation r')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\OrderItem oi')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\CustomerOrder co')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\WarehouseStock ws')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Warehouse w')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Product p')->execute();
    }

    /**
     * @return array{array<string, Product>, int}
     */
    private function seedProducts(): array
    {
        $repo = $this->em->getRepository(Product::class);
        /** @var array<string, Product> $products */
        $products = [];

        foreach (self::PRODUCTS as $sku => $name) {
            $product = $repo->findOneBy(['sku' => $sku]);

            if ($product === null) {
                $product = new Product($sku, $name);
                $this->em->persist($product);
            }

            $products[$sku] = $product;
        }

        return [$products, count($products)];
    }

    /**
     * @return array{array<string, Warehouse>, int}
     */
    private function seedWarehouses(): array
    {
        $repo = $this->em->getRepository(Warehouse::class);
        /** @var array<string, Warehouse> $warehouses */
        $warehouses = [];

        foreach (self::WAREHOUSES as $code => $name) {
            $warehouse = $repo->findOneBy(['code' => $code]);

            if ($warehouse === null) {
                $warehouse = new Warehouse($code, $name);
                $this->em->persist($warehouse);
            }

            $warehouses[$code] = $warehouse;
        }

        return [$warehouses, count($warehouses)];
    }

    /**
     * @param array<string, Warehouse> $warehouses
     * @param array<string, Product>   $products
     */
    private function seedStock(array $warehouses, array $products): int
    {
        $repo = $this->em->getRepository(WarehouseStock::class);
        $count = 0;

        foreach (self::STOCK as $warehouseCode => $entries) {
            $warehouse = $warehouses[$warehouseCode];

            foreach ($entries as $sku => $quantity) {
                $product = $products[$sku];

                // findOneBy requires both sides to have DB identities.
                // If either was just created this run, stock cannot exist yet.
                $stock = ($warehouse->getId() !== null && $product->getId() !== null)
                    ? $repo->findOneBy(['warehouse' => $warehouse, 'product' => $product])
                    : null;

                if ($stock === null) {
                    $this->em->persist(new WarehouseStock($warehouse, $product, $quantity));
                }

                ++$count;
            }
        }

        return $count;
    }

    /**
     * @param array<string, Product> $products
     * @return array{int, int}
     */
    private function seedOrders(array $products): array
    {
        $existingCount = (int) $this->em
            ->createQuery('SELECT COUNT(o.id) FROM App\Entity\CustomerOrder o')
            ->getSingleScalarResult();

        if ($existingCount > 0) {
            return [0, 0];
        }

        $ordersCount = 0;
        $itemsCount = 0;

        foreach (self::ORDERS as $items) {
            $order = new CustomerOrder();
            $this->em->persist($order);
            ++$ordersCount;

            foreach ($items as $sku => $quantity) {
                $this->em->persist(new OrderItem($order, $products[$sku], $quantity));
                ++$itemsCount;
            }
        }

        return [$ordersCount, $itemsCount];
    }
}
