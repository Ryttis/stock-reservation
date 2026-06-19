<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Service\SampleDataGenerator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProductControllerTest extends WebTestCase
{
    protected function setUp(): void
    {
        static::createClient();
        static::getContainer()->get(SampleDataGenerator::class)->seed(reset: true);
    }

    public function testListReturnsAllProducts(): void
    {
        $client = static::getClient();
        $client->request('GET', '/api/products');

        $this->assertResponseIsSuccessful();

        $data = $this->decodeJson($client->getResponse()->getContent());

        $this->assertIsArray($data);
        $this->assertCount(5, $data);

        $skus = array_column($data, 'sku');
        $this->assertContains('PENCIL', $skus);
        $this->assertContains('NOTEBOOK', $skus);
        $this->assertContains('BAG', $skus);
        $this->assertContains('PEN', $skus);
        $this->assertContains('ERASER', $skus);

        $first = $data[0];
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('sku', $first);
        $this->assertArrayHasKey('name', $first);
    }

    /**
     * @return array<mixed>
     */
    private function decodeJson(string $content): array
    {
        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}
