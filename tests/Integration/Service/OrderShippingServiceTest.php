<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Entity\CustomerOrder;
use App\Entity\WarehouseStock;
use App\Enum\OrderStatus;
use App\Enum\ReservationStatus;
use App\Service\OrderShippingService;
use App\Service\SampleDataGenerator;
use App\Service\StockReservationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class OrderShippingServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private StockReservationService $reservationService;
    private OrderShippingService $shippingService;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->reservationService = $container->get(StockReservationService::class);
        $this->shippingService = $container->get(OrderShippingService::class);

        $container->get(SampleDataGenerator::class)->seed(reset: true);
    }

    public function testShipsFullyReservedOrder(): void
    {
        $order = $this->findOrderWithItem('PENCIL', 8);
        $this->reservationService->reserve($order);

        $stockBefore = $this->findStock('WH_A', 'PENCIL')->getQuantity();

        $this->shippingService->ship($order);

        $this->assertSame(OrderStatus::Shipped, $order->getStatus());
        $this->assertNotNull($order->getShippedAt());
        $this->assertSame(ReservationStatus::Released, $order->getReservation()->getStatus());
        $this->assertNotEmpty($order->getReservation()->getReservationItems());

        $reservedFromA = $this->reservedQuantityFromWarehouse($order, 'WH_A', 'PENCIL');
        $this->assertSame($stockBefore - $reservedFromA, $this->findStock('WH_A', 'PENCIL')->getQuantity());
    }

    public function testShipsPartiallyReservedOrder(): void
    {
        $order = $this->findOrderWithItem('BAG', 10);
        $result = $this->reservationService->reserve($order);

        $bagStockB = $this->findStock('WH_B', 'BAG')->getQuantity();
        $bagStockC = $this->findStock('WH_C', 'BAG')->getQuantity();

        $this->shippingService->ship($order);

        $this->assertSame(OrderStatus::Shipped, $order->getStatus());
        $this->assertSame(ReservationStatus::Released, $order->getReservation()->getStatus());

        $allocatedBagB = $this->reservedQuantityFromWarehouse($order, 'WH_B', 'BAG');
        $allocatedBagC = $this->reservedQuantityFromWarehouse($order, 'WH_C', 'BAG');

        $this->assertSame($bagStockB - $allocatedBagB, $this->findStock('WH_B', 'BAG')->getQuantity());
        $this->assertSame($bagStockC - $allocatedBagC, $this->findStock('WH_C', 'BAG')->getQuantity());

        $missingBag = 0;
        foreach ($result->allocationResult->getMissingItems() as $missing) {
            if ($missing->sku === 'BAG') {
                $missingBag = $missing->quantity;
            }
        }
        $this->assertGreaterThan(0, $missingBag);
    }

    public function testCannotShipPendingOrder(): void
    {
        $order = $this->findOrderWithItem('PENCIL', 8);

        $this->expectException(\DomainException::class);
        $this->shippingService->ship($order);
    }

    public function testCannotShipOrderWithoutReservation(): void
    {
        $order = $this->findOrderWithItem('PENCIL', 8);
        $order->setStatus(OrderStatus::Reserved);
        $this->em->flush();

        $this->expectException(\DomainException::class);
        $this->shippingService->ship($order);
    }

    public function testCannotShipAlreadyShippedOrder(): void
    {
        $order = $this->findOrderWithItem('PENCIL', 8);
        $this->reservationService->reserve($order);
        $this->shippingService->ship($order);

        $this->expectException(\DomainException::class);
        $this->shippingService->ship($order);
    }

    public function testCannotShipCancelledReservation(): void
    {
        $order = $this->findOrderWithItem('PENCIL', 8);
        $this->reservationService->reserve($order);
        $order->getReservation()->setStatus(ReservationStatus::Cancelled);
        $this->em->flush();

        $this->expectException(\DomainException::class);
        $this->shippingService->ship($order);
    }

    private function findOrderWithItem(string $sku, int $quantity): CustomerOrder
    {
        /** @var CustomerOrder $order */
        $order = $this->em->createQuery(
            'SELECT o FROM App\Entity\CustomerOrder o
             JOIN o.orderItems oi
             JOIN oi.product p
             WHERE p.sku = :sku AND oi.quantity = :qty'
        )->setParameter('sku', $sku)
         ->setParameter('qty', $quantity)
         ->getSingleResult();

        return $order;
    }

    private function findStock(string $warehouseCode, string $sku): WarehouseStock
    {
        /** @var WarehouseStock $stock */
        $stock = $this->em->createQuery(
            'SELECT ws FROM App\Entity\WarehouseStock ws
             JOIN ws.warehouse w
             JOIN ws.product p
             WHERE w.code = :code AND p.sku = :sku'
        )->setParameter('code', $warehouseCode)
         ->setParameter('sku', $sku)
         ->getSingleResult();

        return $stock;
    }

    private function reservedQuantityFromWarehouse(CustomerOrder $order, string $warehouseCode, string $sku): int
    {
        $total = 0;
        foreach ($order->getReservation()->getReservationItems() as $item) {
            if ($item->getWarehouse()->getCode() === $warehouseCode && $item->getProduct()->getSku() === $sku) {
                $total += $item->getQuantity();
            }
        }

        return $total;
    }
}
