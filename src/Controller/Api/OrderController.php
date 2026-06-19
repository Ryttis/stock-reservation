<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\CustomerOrder;
use App\Entity\OrderItem;
use App\Entity\Reservation;
use App\Entity\ReservationItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/orders')]
final class OrderController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var CustomerOrder[] $orders */
        $orders = $this->em->getRepository(CustomerOrder::class)->findBy([], ['createdAt' => 'ASC', 'id' => 'ASC']);

        return new JsonResponse(array_map($this->serializeSummary(...), $orders));
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        /** @var CustomerOrder|null $order */
        $order = $this->em->createQuery(
            'SELECT o, oi, oip, r, ri, riw, rip
             FROM App\Entity\CustomerOrder o
             LEFT JOIN o.orderItems oi
             LEFT JOIN oi.product oip
             LEFT JOIN o.reservation r
             LEFT JOIN r.reservationItems ri
             LEFT JOIN ri.warehouse riw
             LEFT JOIN ri.product rip
             WHERE o.id = :id'
        )->setParameter('id', $id)
         ->getOneOrNullResult();

        if ($order === null) {
            throw new NotFoundHttpException(sprintf('Order %d not found.', $id));
        }

        return new JsonResponse($this->serializeDetail($order));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSummary(CustomerOrder $order): array
    {
        return [
            'id'          => $order->getId(),
            'status'      => $order->getStatus()->value,
            'createdAt'   => $order->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'shippedAt'   => $order->getShippedAt()?->format(\DateTimeInterface::ATOM),
            'cancelledAt' => $order->getCancelledAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDetail(CustomerOrder $order): array
    {
        $items = array_map(
            fn(OrderItem $item): array => [
                'product'  => ['id' => $item->getProduct()->getId(), 'sku' => $item->getProduct()->getSku()],
                'quantity' => $item->getQuantity(),
            ],
            $order->getOrderItems()->toArray(),
        );

        $reservation = $order->getReservation();

        return [
            'id'          => $order->getId(),
            'status'      => $order->getStatus()->value,
            'createdAt'   => $order->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'shippedAt'   => $order->getShippedAt()?->format(\DateTimeInterface::ATOM),
            'cancelledAt' => $order->getCancelledAt()?->format(\DateTimeInterface::ATOM),
            'items'       => $items,
            'reservation' => $reservation !== null ? $this->serializeReservation($reservation) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeReservation(Reservation $reservation): array
    {
        $items = array_map(
            fn(ReservationItem $item): array => [
                'product'   => ['id' => $item->getProduct()->getId(), 'sku' => $item->getProduct()->getSku()],
                'warehouse' => ['id' => $item->getWarehouse()->getId(), 'code' => $item->getWarehouse()->getCode()],
                'quantity'  => $item->getQuantity(),
            ],
            $reservation->getReservationItems()->toArray(),
        );

        return [
            'status' => $reservation->getStatus()->value,
            'items'  => $items,
        ];
    }
}
