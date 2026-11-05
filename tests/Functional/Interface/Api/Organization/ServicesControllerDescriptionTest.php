<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Organization;

use App\Context\Organization\Domain\Entity\Service;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Database;

final class ServicesControllerDescriptionTest extends ApiTestCase
{
    public function testAnOmittedDescriptionStays(): void
    {
        $service = $this->describedService();

        $this->jsonRequest('PATCH', '/api/services/' . $service->id()->toRfc4122(), ['name' => 'Kids Church']);

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('name', 'Kids Church');
        $this->assertJsonPath('description', 'Every Sunday after the liturgy');
    }

    public function testANullDescriptionClearsIt(): void
    {
        $service = $this->describedService();

        $this->jsonRequest('PATCH', '/api/services/' . $service->id()->toRfc4122(), ['description' => null]);

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('name', 'Sunday School');
        $this->assertJsonPath('description', null);
    }

    private function describedService(): Service
    {
        $service = new Service('Sunday School', 'Every Sunday after the liturgy');
        new Database(self::getContainer())->persisted($service);
        $this->signInWithRoles('leader@example.org', 'service_leader');

        return $service;
    }
}
