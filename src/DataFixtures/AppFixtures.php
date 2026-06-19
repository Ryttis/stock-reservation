<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\CustomerOrder;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\Warehouse;
use App\Entity\WarehouseStock;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // --- Products ---
        $productDefs = [
            'PENCIL'   => 'Pencil',
            'NOTEBOOK' => 'Notebook',
            'BAG'      => 'Bag',
            'PEN'      => 'Pen',
            'ERASER'   => 'Eraser',
        ];

        /** @var array<string, Product> $products */
        $products = [];
        foreach ($productDefs as $sku => $name) {
            $product = new Product($sku, $name);
            $manager->persist($product);
            $products[$sku] = $product;
        }

        // --- Warehouses ---
        $warehouseDefs = [
            'WH_A' => 'Warehouse A',
            'WH_B' => 'Warehouse B',
            'WH_C' => 'Warehouse C',
        ];

        /** @var array<string, Warehouse> $warehouses */
        $warehouses = [];
        foreach ($warehouseDefs as $code => $name) {
            $warehouse = new Warehouse($code, $name);
            $manager->persist($warehouse);
            $warehouses[$code] = $warehouse;
        }

        // --- Stock ---
        $stockDefs = [
            'WH_A' => ['PENCIL' => 10, 'NOTEBOOK' =>  2, 'BAG' =>  0, 'PEN' => 20, 'ERASER' =>  5],
            'WH_B' => ['PENCIL' =>  5, 'NOTEBOOK' => 10, 'BAG' =>  3, 'PEN' =>  5, 'ERASER' => 20],
            'WH_C' => ['PENCIL' => 100, 'NOTEBOOK' =>  0, 'BAG' =>  2, 'PEN' =>  1, 'ERASER' =>  0],
        ];

        foreach ($stockDefs as $warehouseCode => $entries) {
            foreach ($entries as $sku => $quantity) {
                $stock = new WarehouseStock($warehouses[$warehouseCode], $products[$sku], $quantity);
                $manager->persist($stock);
            }
        }

        // --- Orders ---
        // Each entry is a SKU => quantity map. Orders stay in Pending status (set by constructor).
        $orderDefs = [
            // Fulfillable from a single warehouse (WH_A has PENCIL:10, NOTEBOOK:2).
            ['PENCIL' => 8, 'NOTEBOOK' => 2],
            // Requires multiple warehouses to fulfil.
            ['PENCIL' => 12, 'NOTEBOOK' => 8, 'BAG' => 2],
            // Partially reservable — total BAG stock is 5, ERASER stock is 25; both insufficient.
            ['BAG' => 10, 'ERASER' => 30],
        ];

        foreach ($orderDefs as $items) {
            $order = new CustomerOrder();
            $manager->persist($order);

            foreach ($items as $sku => $quantity) {
                $orderItem = new OrderItem($order, $products[$sku], $quantity);
                $manager->persist($orderItem);
            }
        }

        $manager->flush();
    }
}
