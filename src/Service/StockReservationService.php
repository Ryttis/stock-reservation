<?php

declare(strict_types=1);

namespace App\Service;

use App\Allocation\DTO\RequestedItem;
use App\Allocation\DTO\WarehouseInventory;
use App\Allocation\Strategy\AllocationStrategyInterface;
use App\Entity\CustomerOrder;
use App\Entity\Reservation;
use App\Entity\ReservationItem;
use App\Enum\OrderStatus;
use App\Enum\ReservationStatus;
use Doctrine\ORM\EntityManagerInterface;

final class StockReservationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AllocationStrategyInterface $allocationStrategy,
    ) {}

    public function reserve(CustomerOrder $order): ReservationResult
    {
        if ($order->getStatus() !== OrderStatus::Pending) {
            throw new \DomainException('Only pending orders can be reserved.');
        }

        if ($order->getReservation() !== null) {
            throw new \DomainException('Order already has a reservation.');
        }

        $orderItems = $order->getOrderItems();

        if ($orderItems->isEmpty()) {
            throw new \DomainException('Order has no items.');
        }

        /** @var array<int, \App\Entity\Product> $products */
        $products = [];
        foreach ($orderItems as $orderItem) {
            $product = $orderItem->getProduct();
            $products[$product->getId()] = $product;
        }

        /** @var \App\Entity\WarehouseStock[] $stockRows */
        $stockRows = $this->em->createQuery(
            'SELECT ws, w, p
             FROM App\Entity\WarehouseStock ws
             JOIN ws.warehouse w
             JOIN ws.product p
             WHERE ws.product IN (:products)'
        )->setParameter('products', array_values($products))
         ->getResult();

        /** @var \App\Entity\ReservationItem[] $activeItems */
        $activeItems = $this->em->createQuery(
            'SELECT ri, rw, rp
             FROM App\Entity\ReservationItem ri
             JOIN ri.reservation r
             JOIN ri.warehouse rw
             JOIN ri.product rp
             WHERE ri.product IN (:products) AND r.status = :status'
        )->setParameter('products', array_values($products))
         ->setParameter('status', ReservationStatus::Active)
         ->getResult();

        /** @var array<int, array<int, int>> $reserved */
        $reserved = [];
        foreach ($activeItems as $activeItem) {
            $wId = $activeItem->getWarehouse()->getId();
            $pId = $activeItem->getProduct()->getId();
            $reserved[$wId][$pId] = ($reserved[$wId][$pId] ?? 0) + $activeItem->getQuantity();
        }

        /** @var array<string, array<string, int>> $inventoryBySku */
        $inventoryBySku = [];
        /** @var array<string, \App\Entity\Warehouse> $warehousesByCode */
        $warehousesByCode = [];
        /** @var array<string, \App\Entity\Product> $productsBySku */
        $productsBySku = [];

        foreach ($stockRows as $stock) {
            $warehouse = $stock->getWarehouse();
            $product = $stock->getProduct();
            $warehouseCode = $warehouse->getCode();
            $sku = $product->getSku();

            $warehousesByCode[$warehouseCode] = $warehouse;
            $productsBySku[$sku] = $product;

            $wId = $warehouse->getId();
            $pId = $product->getId();
            $available = $stock->getQuantity() - ($reserved[$wId][$pId] ?? 0);

            if ($available <= 0) {
                continue;
            }

            $inventoryBySku[$warehouseCode][$sku] = $available;
        }

        $warehouses = [];
        foreach ($inventoryBySku as $warehouseCode => $stockBySku) {
            $warehouses[] = new WarehouseInventory($warehouseCode, $stockBySku);
        }

        $requestedItems = [];
        foreach ($orderItems as $orderItem) {
            $requestedItems[] = new RequestedItem(
                $orderItem->getProduct()->getSku(),
                $orderItem->getQuantity(),
            );
        }

        $allocationResult = $this->allocationStrategy->allocate($requestedItems, $warehouses);

        $reservation = new Reservation($order);
        $this->em->persist($reservation);

        foreach ($allocationResult->getAllocationItems() as $allocationItem) {
            $reservationItem = new ReservationItem(
                $reservation,
                $warehousesByCode[$allocationItem->warehouseCode],
                $productsBySku[$allocationItem->sku],
                $allocationItem->quantity,
            );
            $reservation->addReservationItem($reservationItem);
        }

        $order->setStatus(
            $allocationResult->isFullyAllocated()
                ? OrderStatus::Reserved
                : OrderStatus::PartiallyReserved
        );

        $this->em->flush();

        return new ReservationResult($reservation, $allocationResult);
    }
}
