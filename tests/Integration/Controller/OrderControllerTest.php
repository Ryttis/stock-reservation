<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\CustomerOrder;
use App\Service\SampleDataGenerator;
use App\Service\StockReservationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class OrderControllerTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private StockReservationService $reservationService;

    protected function setUp(): void
    {
        static::createClient();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->reservationService = $container->get(StockReservationService::class);

        $container->get(SampleDataGenerator::class)->seed(reset: true);
    }

    public function testListReturnsAllOrders(): void
    {
        $client = static::getClient();
        $client->request('GET', '/api/orders');

        $this->assertResponseIsSuccessful();

        $data = $this->decodeJson($client->getResponse()->getContent());

        $this->assertIsArray($data);
        $this->assertCount(4, $data);

        $first = $data[0];
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('status', $first);
        $this->assertArrayHasKey('createdAt', $first);
        $this->assertArrayHasKey('shippedAt', $first);
        $this->assertArrayHasKey('cancelledAt', $first);
        $this->assertSame('pending', $first['status']);
    }

    public function testShowReturnsPendingOrderWithItems(): void
    {
        $order = $this->findOrderWithItem('PENCIL', 8);
        $client = static::getClient();

        $client->request('GET', '/api/orders/' . $order->getId());

        $this->assertResponseIsSuccessful();

        $data = $this->decodeJson($client->getResponse()->getContent());

        $this->assertSame($order->getId(), $data['id']);
        $this->assertSame('pending', $data['status']);
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('reservation', $data);
        $this->assertNull($data['reservation']);
        $this->assertNotEmpty($data['items']);

        $item = $data['items'][0];
        $this->assertArrayHasKey('product', $item);
        $this->assertArrayHasKey('quantity', $item);
    }

    public function testShowReturnsReservedOrderWithReservation(): void
    {
        $order = $this->findOrderWithItem('PENCIL', 8);
        $this->reservationService->reserve($order);

        $client = static::getClient();
        $client->request('GET', '/api/orders/' . $order->getId());

        $this->assertResponseIsSuccessful();

        $data = $this->decodeJson($client->getResponse()->getContent());

        $this->assertSame('reserved', $data['status']);
        $this->assertNotNull($data['reservation']);
        $this->assertSame('active', $data['reservation']['status']);
        $this->assertNotEmpty($data['reservation']['items']);

        $ri = $data['reservation']['items'][0];
        $this->assertArrayHasKey('product', $ri);
        $this->assertArrayHasKey('warehouse', $ri);
        $this->assertArrayHasKey('quantity', $ri);
    }

    public function testShowReturns404ForUnknownOrder(): void
    {
        $client = static::getClient();
        $client->request('GET', '/api/orders/999999');

        $this->assertResponseStatusCodeSame(404);
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

    /**
     * @return array<mixed>
     */
    private function decodeJson(string $content): array
    {
        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}
