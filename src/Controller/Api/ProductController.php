<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/products')]
final class ProductController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $products = $this->em->getRepository(Product::class)->findBy([], ['id' => 'ASC']);

        return new JsonResponse(array_map($this->serialize(...), $products));
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Product $product): array
    {
        return [
            'id'   => $product->getId(),
            'sku'  => $product->getSku(),
            'name' => $product->getName(),
        ];
    }
}
