<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Organization;

use App\Context\Organization\Domain\Entity\Service;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;

final class ServicesControllerDescriptionTest extends ApiTestCase
{
    public function testAnEditReplacesTheDescription(): void
    {
        $service = $this->describedService();

        $this->jsonRequest('PUT', '/api/services/' . $service->id()->toRfc4122(), [
            'name' => 'Kids Church',
            'description' => ' After the liturgy ',
        ]);

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('name', 'Kids Church');
        $this->assertJsonPath('description', 'After the liturgy');
    }

    /**
     * @param array<string, string|null> $body
     */
    #[DataProvider('withoutDescription')]
    public function testAnEditWithoutADescriptionClearsIt(array $body): void
    {
        $service = $this->describedService();

        $this->jsonRequest('PUT', '/api/services/' . $service->id()->toRfc4122(), $body);

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('name', 'Sunday School');
        $this->assertJsonPath('description', null);
    }

    /**
     * @return iterable<string, array{array<string, string|null>}>
     */
    public static function withoutDescription(): iterable
    {
        yield 'left out' => [['name' => 'Sunday School']];
        yield 'null' => [['name' => 'Sunday School', 'description' => null]];
    }

    private function describedService(): Service
    {
        $service = Structure::service('Sunday School', description: 'Every Sunday after the liturgy');
        new Database(self::getContainer())->persisted($service);
        $this->signInWithRoles('leader@example.org', 'service_leader');

        return $service;
    }
}
