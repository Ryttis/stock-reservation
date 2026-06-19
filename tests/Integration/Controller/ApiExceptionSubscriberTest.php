<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\CustomerOrder;
use App\Service\SampleDataGenerator;
use App\Service\StockReservationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ApiExceptionSubscriberTest extends WebTestCase
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

    public function testUnknownOrderReturnsJson404(): void
    {
        $client = static::getClient();
        $client->request('GET', '/api/orders/999999');

        $this->assertResponseStatusCodeSame(404);
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = $this->decodeJson($client->getResponse()->getContent());
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('999999', $data['error']);
    }

    public function testDomainConflictReturnsJson409(): void
    {
        $order = $this->findOrderWithItem('PENCIL', 8);
        $this->reservationService->reserve($order);

        $client = static::getClient();
        $client->request('POST', '/api/orders/' . $order->getId() . '/reserve');

        $this->assertResponseStatusCodeSame(409);
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = $this->decodeJson($client->getResponse()->getContent());
        $this->assertArrayHasKey('error', $data);
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
