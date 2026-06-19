<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\CustomerOrder;
use App\Service\OrderCancellationService;
use App\Service\OrderShippingService;
use App\Service\StockReservationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/orders/{id}', requirements: ['id' => '\d+'])]
final class OrderActionController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly StockReservationService $reservationService,
        private readonly OrderShippingService $shippingService,
        private readonly OrderCancellationService $cancellationService,
    ) {}

    #[Route('/reserve', methods: ['POST'])]
    public function reserve(int $id): JsonResponse
    {
        $order = $this->findOr404($id);

        try {
            $this->reservationService->reserve($order);
        } catch (\DomainException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->orderResponse($order);
    }

    #[Route('/ship', methods: ['POST'])]
    public function ship(int $id): JsonResponse
    {
        $order = $this->findOr404($id);

        try {
            $this->shippingService->ship($order);
        } catch (\DomainException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->orderResponse($order);
    }

    #[Route('/cancel', methods: ['POST'])]
    public function cancel(int $id): JsonResponse
    {
        $order = $this->findOr404($id);

        try {
            $this->cancellationService->cancel($order);
        } catch (\DomainException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->orderResponse($order);
    }

    private function findOr404(int $id): CustomerOrder
    {
        $order = $this->em->find(CustomerOrder::class, $id);

        if ($order === null) {
            throw $this->notFound($id);
        }

        return $order;
    }

    private function orderResponse(CustomerOrder $order): JsonResponse
    {
        return new JsonResponse([
            'id'          => $order->getId(),
            'status'      => $order->getStatus()->value,
            'createdAt'   => $order->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'shippedAt'   => $order->getShippedAt()?->format(\DateTimeInterface::ATOM),
            'cancelledAt' => $order->getCancelledAt()?->format(\DateTimeInterface::ATOM),
        ]);
    }

    private function errorResponse(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $message], $status);
    }

    private function notFound(int $id): \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
    {
        return new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException(
            sprintf('Order %d not found.', $id)
        );
    }
}
