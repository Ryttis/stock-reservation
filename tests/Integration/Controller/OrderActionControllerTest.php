<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\CustomerOrder;
use App\Service\SampleDataGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class OrderActionControllerTest extends WebTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $client = static::createClient();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $container->get(SampleDataGenerator::class)->seed(reset: true);
    }

    public function testReservePendingOrder(): void
    {
        $order = $this->findOrderWithItem('PENCIL', 8);
        $client = static::getClient();

        $client->request('POST', '/api/orders/' . $order->getId() . '/reserve');

        $this->assertResponseIsSuccessful();
        $data = $this->decodeJson($client->getResponse()->getContent());

        $this->assertSame($order->getId(), $data['id']);
        $this->assertSame('reserved', $data['status']);
        $this->assertNotNull($data['createdAt']);
        $this->assertNull($data['shippedAt']);
        $this->assertNull($data['cancelledAt']);
    }

    public function testShipReservedOrder(): void
    {
        $order = $this->findOrderWithItem('PENCIL', 8);
        $client = static::getClient();

        $client->request('POST', '/api/orders/' . $order->getId() . '/reserve');
        $this->assertResponseIsSuccessful();

        $client->request('POST', '/api/orders/' . $order->getId() . '/ship');
        $this->assertResponseIsSuccessful();

        $data = $this->decodeJson($client->getResponse()->getContent());
        $this->assertSame('shipped', $data['status']);
        $this->assertNotNull($data['shippedAt']);
    }

    public function testCancelReservedOrder(): void
    {
        $order = $this->findOrderWithItem('PENCIL', 8);
        $client = static::getClient();

        $client->request('POST', '/api/orders/' . $order->getId() . '/reserve');
        $this->assertResponseIsSuccessful();

        $client->request('POST', '/api/orders/' . $order->getId() . '/cancel');
        $this->assertResponseIsSuccessful();

        $data = $this->decodeJson($client->getResponse()->getContent());
        $this->assertSame('cancelled', $data['status']);
        $this->assertNotNull($data['cancelledAt']);
    }

    public function testReturns404ForUnknownOrder(): void
    {
        $client = static::getClient();

        $client->request('POST', '/api/orders/999999/reserve');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testReturns409WhenReservingAlreadyReservedOrder(): void
    {
        $order = $this->findOrderWithItem('PENCIL', 8);
        $client = static::getClient();

        $client->request('POST', '/api/orders/' . $order->getId() . '/reserve');
        $this->assertResponseIsSuccessful();

        $client->request('POST', '/api/orders/' . $order->getId() . '/reserve');
        $this->assertResponseStatusCodeSame(409);
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
     * @return array<string, mixed>
     */
    private function decodeJson(string $content): array
    {
        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}
