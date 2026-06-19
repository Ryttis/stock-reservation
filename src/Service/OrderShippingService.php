<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CustomerOrder;
use App\Entity\WarehouseStock;
use App\Enum\OrderStatus;
use App\Enum\ReservationStatus;
use Doctrine\ORM\EntityManagerInterface;

final class OrderShippingService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    public function ship(CustomerOrder $order): void
    {
        if ($order->getStatus() !== OrderStatus::Reserved && $order->getStatus() !== OrderStatus::PartiallyReserved) {
            throw new \DomainException('Only reserved or partially reserved orders can be shipped.');
        }

        $reservation = $order->getReservation();

        if ($reservation === null) {
            throw new \DomainException('Order has no reservation.');
        }

        if ($reservation->getStatus() !== ReservationStatus::Active) {
            throw new \DomainException('Reservation is not active.');
        }

        foreach ($reservation->getReservationItems() as $reservationItem) {
            $stock = $this->em->getRepository(WarehouseStock::class)->findOneBy([
                'warehouse' => $reservationItem->getWarehouse(),
                'product'   => $reservationItem->getProduct(),
            ]);

            if ($stock === null) {
                throw new \DomainException(sprintf(
                    'WarehouseStock not found for warehouse "%s" and product "%s".',
                    $reservationItem->getWarehouse()->getCode(),
                    $reservationItem->getProduct()->getSku(),
                ));
            }

            $stock->decreaseQuantity($reservationItem->getQuantity());
        }

        $reservation->setStatus(ReservationStatus::Released);
        $order->setStatus(OrderStatus::Shipped);
        $order->setShippedAt(new \DateTimeImmutable());

        $this->em->flush();
    }
}
