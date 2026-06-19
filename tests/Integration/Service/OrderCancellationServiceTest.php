<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Entity\CustomerOrder;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Enum\OrderStatus;
use App\Enum\ReservationStatus;
use App\Service\OrderCancellationService;
use App\Service\OrderShippingService;
use App\Service\SampleDataGenerator;
use App\Service\StockReservationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class OrderCancellationServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private StockReservationService $reservationService;
    private OrderShippingService $shippingService;
    private OrderCancellationService $cancellationService;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->reservationService = $container->get(StockReservationService::class);
        $this->shippingService = $container->get(OrderShippingService::class);
        $this->cancellationService = $container->get(OrderCancellationService::class);

        $container->get(SampleDataGenerator::class)->seed(reset: true);
    }

    public function testCancelsFullyReservedOrder(): void
    {
        $order = $this->findOrderWithItem('PENCIL', 8);
        $this->reservationService->reserve($order);

        $this->cancellationService->cancel($order);

        $this->assertSame(OrderStatus::Cancelled, $order->getStatus());
        $this->assertNotNull($order->getCancelledAt());
        $this->assertSame(ReservationStatus::Cancelled, $order->getReservation()->getStatus());
    }

    public function testCancelsPartiallyReservedOrder(): void
    {
        $order = $this->findOrderWithItem('BAG', 10);
        $this->reservationService->reserve($order);

        $this->cancellationService->cancel($order);

        $this->assertSame(OrderStatus::Cancelled, $order->getStatus());
        $this->assertNotNull($order->getCancelledAt());
        $this->assertSame(ReservationStatus::Cancelled, $order->getReservation()->getStatus());
    }

    public function testCannotCancelPendingOrder(): void
    {
        $order = $this->findOrderWithItem('PENCIL', 8);

        $this->expectException(\DomainException::class);
        $this->cancellationService->cancel($order);
    }

    public function testCannotCancelShippedOrder(): void
    {
        $order = $this->findOrderWithItem('PENCIL', 8);
        $this->reservationService->reserve($order);
        $this->shippingService->ship($order);

        $this->expectException(\DomainException::class);
        $this->cancellationService->cancel($order);
    }

    public function testCannotCancelAlreadyCancelledOrder(): void
    {
        $order = $this->findOrderWithItem('PENCIL', 8);
        $this->reservationService->reserve($order);
        $this->cancellationService->cancel($order);

        $this->expectException(\DomainException::class);
        $this->cancellationService->cancel($order);
    }

    public function testCancellationRecalculatesExistingReservations(): void
    {
        $pencil = $this->em->getRepository(Product::class)->findOneBy(['sku' => 'PENCIL']);

        $firstOrder = new CustomerOrder();
        $firstOrder->addOrderItem(new OrderItem($firstOrder, $pencil, 100));
        $this->em->persist($firstOrder);
        $this->em->flush();

        $this->reservationService->reserve($firstOrder);
        $this->assertSame(OrderStatus::Reserved, $firstOrder->getStatus());

        $secondOrder = new CustomerOrder();
        $secondOrder->addOrderItem(new OrderItem($secondOrder, $pencil, 100));
        $this->em->persist($secondOrder);
        $this->em->flush();

        $secondResult = $this->reservationService->reserve($secondOrder);
        $this->assertSame(OrderStatus::PartiallyReserved, $secondOrder->getStatus());
        $this->assertFalse($secondResult->allocationResult->isFullyAllocated());

        $this->cancellationService->cancel($firstOrder);

        $this->assertSame(OrderStatus::Cancelled, $firstOrder->getStatus());
        $this->assertSame(ReservationStatus::Cancelled, $firstOrder->getReservation()->getStatus());

        $this->assertSame(OrderStatus::Reserved, $secondOrder->getStatus());
        $this->assertSame(ReservationStatus::Active, $secondOrder->getReservation()->getStatus());

        $totalPencil = 0;
        foreach ($secondOrder->getReservation()->getReservationItems() as $item) {
            if ($item->getProduct()->getSku() === 'PENCIL') {
                $totalPencil += $item->getQuantity();
            }
        }
        $this->assertSame(100, $totalPencil);
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
}
