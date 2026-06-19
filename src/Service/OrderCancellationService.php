<?php

declare(strict_types=1);

namespace App\Service;

use App\Allocation\DTO\AllocationResult;
use App\Allocation\DTO\RequestedItem;
use App\Allocation\DTO\WarehouseInventory;
use App\Allocation\Strategy\AllocationStrategyInterface;
use App\Entity\CustomerOrder;
use App\Entity\Reservation;
use App\Entity\ReservationItem;
use App\Entity\WarehouseStock;
use App\Enum\OrderStatus;
use App\Enum\ReservationStatus;
use Doctrine\ORM\EntityManagerInterface;

final class OrderCancellationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AllocationStrategyInterface $allocationStrategy,
    ) {}

    public function cancel(CustomerOrder $order): void
    {
        if ($order->getStatus() !== OrderStatus::Reserved && $order->getStatus() !== OrderStatus::PartiallyReserved) {
            throw new \DomainException('Only reserved or partially reserved orders can be cancelled.');
        }

        $reservation = $order->getReservation();

        if ($reservation === null) {
            throw new \DomainException('Order has no reservation.');
        }

        if ($reservation->getStatus() !== ReservationStatus::Active) {
            throw new \DomainException('Reservation is not active.');
        }

        $reservation->setStatus(ReservationStatus::Cancelled);
        $order->setStatus(OrderStatus::Cancelled);
        $order->setCancelledAt(new \DateTimeImmutable());

        $this->recalculateActiveReservations($order);

        $this->em->flush();
    }

    private function recalculateActiveReservations(CustomerOrder $excludedOrder): void
    {
        /** @var CustomerOrder[] $activeOrders */
        $activeOrders = $this->em->createQuery(
            'SELECT o FROM App\Entity\CustomerOrder o
             JOIN o.reservation r
             WHERE o.status IN (:statuses)
             AND r.status = :reservationStatus
             AND o.id != :excludedId
             ORDER BY o.createdAt ASC, o.id ASC'
        )->setParameter('statuses', [OrderStatus::Reserved, OrderStatus::PartiallyReserved])
         ->setParameter('reservationStatus', ReservationStatus::Active)
         ->setParameter('excludedId', $excludedOrder->getId())
         ->getResult();

        if ($activeOrders === []) {
            return;
        }

        /** @var WarehouseStock[] $allStocks */
        $allStocks = $this->em->createQuery(
            'SELECT ws, w, p FROM App\Entity\WarehouseStock ws JOIN ws.warehouse w JOIN ws.product p'
        )->getResult();

        /** @var array<string, \App\Entity\Warehouse> $warehousesByCode */
        $warehousesByCode = [];
        /** @var array<string, \App\Entity\Product> $productsBySku */
        $productsBySku = [];

        foreach ($allStocks as $stock) {
            $warehousesByCode[$stock->getWarehouse()->getCode()] = $stock->getWarehouse();
            $productsBySku[$stock->getProduct()->getSku()] = $stock->getProduct();
        }

        /** @var array<int, array<int, int>> $reservedMap */
        $reservedMap = [];

        foreach ($activeOrders as $order) {
            $reservation = $order->getReservation();
            $requestedItems = $this->buildRequestedItems($order);
            $warehouses = $this->buildWarehouseInventories($allStocks, $reservedMap);

            $allocationResult = $this->allocationStrategy->allocate($requestedItems, $warehouses);

            $this->replaceReservationItems($reservation, $allocationResult, $warehousesByCode, $productsBySku);

            $order->setStatus(
                $allocationResult->isFullyAllocated()
                    ? OrderStatus::Reserved
                    : OrderStatus::PartiallyReserved
            );

            foreach ($reservation->getReservationItems() as $item) {
                $wId = $item->getWarehouse()->getId();
                $pId = $item->getProduct()->getId();
                $reservedMap[$wId][$pId] = ($reservedMap[$wId][$pId] ?? 0) + $item->getQuantity();
            }
        }
    }

    /**
     * @return RequestedItem[]
     */
    private function buildRequestedItems(CustomerOrder $order): array
    {
        $items = [];
        foreach ($order->getOrderItems() as $orderItem) {
            $items[] = new RequestedItem(
                $orderItem->getProduct()->getSku(),
                $orderItem->getQuantity(),
            );
        }

        return $items;
    }

    /**
     * @param WarehouseStock[] $allStocks
     * @param array<int, array<int, int>> $reservedMap
     * @return WarehouseInventory[]
     */
    private function buildWarehouseInventories(array $allStocks, array $reservedMap): array
    {
        /** @var array<string, array<string, int>> $inventoryBySku */
        $inventoryBySku = [];

        foreach ($allStocks as $stock) {
            $wId = $stock->getWarehouse()->getId();
            $pId = $stock->getProduct()->getId();
            $warehouseCode = $stock->getWarehouse()->getCode();
            $sku = $stock->getProduct()->getSku();
            $available = $stock->getQuantity() - ($reservedMap[$wId][$pId] ?? 0);

            if ($available <= 0) {
                continue;
            }

            $inventoryBySku[$warehouseCode][$sku] = $available;
        }

        $warehouses = [];
        foreach ($inventoryBySku as $warehouseCode => $stockBySku) {
            $warehouses[] = new WarehouseInventory($warehouseCode, $stockBySku);
        }

        return $warehouses;
    }

    /**
     * @param array<string, \App\Entity\Warehouse> $warehousesByCode
     * @param array<string, \App\Entity\Product> $productsBySku
     */
    private function replaceReservationItems(
        Reservation $reservation,
        AllocationResult $allocationResult,
        array $warehousesByCode,
        array $productsBySku,
    ): void {
        foreach ($reservation->getReservationItems()->toArray() as $item) {
            $reservation->removeReservationItem($item);
        }

        foreach ($allocationResult->getAllocationItems() as $allocationItem) {
            $reservationItem = new ReservationItem(
                $reservation,
                $warehousesByCode[$allocationItem->warehouseCode],
                $productsBySku[$allocationItem->sku],
                $allocationItem->quantity,
            );
            $reservation->addReservationItem($reservationItem);
        }
    }
}
