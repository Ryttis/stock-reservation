<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Allocation\DTO\MissingItem;
use App\Entity\CustomerOrder;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Enum\OrderStatus;
use App\Enum\ReservationStatus;
use App\Service\ReservationResult;
use App\Service\SampleDataGenerator;
use App\Service\StockReservationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class StockReservationServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private StockReservationService $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->service = $container->get(StockReservationService::class);

        $container->get(SampleDataGenerator::class)->seed(reset: true);
    }

    public function testReservesPendingOrderFully(): void
    {
        $order = $this->findOrderWithItem('PENCIL', 8);

        $result = $this->service->reserve($order);

        $this->assertSame(OrderStatus::Reserved, $order->getStatus());
        $this->assertSame(ReservationStatus::Active, $result->reservation->getStatus());
        $this->assertNotEmpty($result->reservation->getReservationItems());
        $this->assertTrue($result->allocationResult->isFullyAllocated());
        $this->assertEmpty($result->allocationResult->getMissingItems());

        $pencilA = $this->findStock('WH_A', 'PENCIL');
        $this->assertSame(10, $pencilA->getQuantity());

        $notebookA = $this->findStock('WH_A', 'NOTEBOOK');
        $this->assertSame(2, $notebookA->getQuantity());
    }

    public function testReservesPendingOrderPartially(): void
    {
        $order = $this->findOrderWithItem('BAG', 10);

        $result = $this->service->reserve($order);

        $this->assertSame(OrderStatus::PartiallyReserved, $order->getStatus());
        $this->assertFalse($result->allocationResult->isFullyAllocated());
        $this->assertNotEmpty($result->reservation->getReservationItems());

        $missing = $this->indexMissingBySku($result->allocationResult->getMissingItems());
        $this->assertArrayHasKey('BAG', $missing);
        $this->assertSame(5, $missing['BAG']->quantity);
        $this->assertArrayHasKey('ERASER', $missing);
        $this->assertSame(5, $missing['ERASER']->quantity);
    }

    public function testReservedStockCannotBeReservedTwice(): void
    {
        $pencil = $this->em->getRepository(Product::class)->findOneBy(['sku' => 'PENCIL']);

        $firstOrder = new CustomerOrder();
        $firstOrder->addOrderItem(new OrderItem($firstOrder, $pencil, 100));
        $this->em->persist($firstOrder);
        $this->em->flush();

        $firstResult = $this->service->reserve($firstOrder);
        $this->assertTrue($firstResult->allocationResult->isFullyAllocated());
        $this->assertSame(ReservationStatus::Active, $firstResult->reservation->getStatus());

        $secondOrder = new CustomerOrder();
        $secondOrder->addOrderItem(new OrderItem($secondOrder, $pencil, 100));
        $this->em->persist($secondOrder);
        $this->em->flush();

        $secondResult = $this->service->reserve($secondOrder);

        $this->assertSame(OrderStatus::PartiallyReserved, $secondOrder->getStatus());

        $missing = $this->indexMissingBySku($secondResult->allocationResult->getMissingItems());
        $this->assertArrayHasKey('PENCIL', $missing);
        $this->assertSame(85, $missing['PENCIL']->quantity);
    }

    public function testCannotReserveAlreadyReservedOrder(): void
    {
        $order = $this->findOrderWithItem('PENCIL', 8);

        $this->service->reserve($order);

        $this->expectException(\DomainException::class);
        $this->service->reserve($order);
    }

    public function testCannotReserveNonPendingOrder(): void
    {
        $order = new CustomerOrder();
        $order->setStatus(OrderStatus::Cancelled);
        $this->em->persist($order);
        $this->em->flush();

        $this->expectException(\DomainException::class);
        $this->service->reserve($order);
    }

    public function testCannotReserveEmptyOrder(): void
    {
        $order = new CustomerOrder();
        $this->em->persist($order);
        $this->em->flush();

        $this->expectException(\DomainException::class);
        $this->service->reserve($order);
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

    private function findStock(string $warehouseCode, string $sku): \App\Entity\WarehouseStock
    {
        /** @var \App\Entity\WarehouseStock $stock */
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

    /**
     * @param MissingItem[] $items
     * @return array<string, MissingItem>
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
