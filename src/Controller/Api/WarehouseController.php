<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Warehouse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/warehouses')]
final class WarehouseController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $warehouses = $this->em->getRepository(Warehouse::class)->findBy([], ['id' => 'ASC']);

        return new JsonResponse(array_map($this->serialize(...), $warehouses));
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Warehouse $warehouse): array
    {
        return [
            'id'   => $warehouse->getId(),
            'code' => $warehouse->getCode(),
            'name' => $warehouse->getName(),
        ];
    }
}
