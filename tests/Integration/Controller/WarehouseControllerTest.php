<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Service\SampleDataGenerator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class WarehouseControllerTest extends WebTestCase
{
    protected function setUp(): void
    {
        static::createClient();
        static::getContainer()->get(SampleDataGenerator::class)->seed(reset: true);
    }

    public function testListReturnsAllWarehouses(): void
    {
        $client = static::getClient();
        $client->request('GET', '/api/warehouses');

        $this->assertResponseIsSuccessful();

        $data = $this->decodeJson($client->getResponse()->getContent());

        $this->assertIsArray($data);
        $this->assertCount(3, $data);

        $codes = array_column($data, 'code');
        $this->assertContains('WH_A', $codes);
        $this->assertContains('WH_B', $codes);
        $this->assertContains('WH_C', $codes);

        $first = $data[0];
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('code', $first);
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
