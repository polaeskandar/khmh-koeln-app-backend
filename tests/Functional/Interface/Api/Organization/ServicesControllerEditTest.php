<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Organization;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;

final class ServicesControllerEditTest extends ApiTestCase
{
    public function testALeaderEditsAServiceAlsoWhenArchivedOrOnlyTheCaseChanges(): void
    {
        $service = Structure::service('Sunday School');
        $service->archive(Structure::now());
        $this->database()->persisted($service);
        $this->signInWithRoles('leader@example.org', 'service_leader');

        $this->jsonRequest('PUT', '/api/services/' . $service->id()->toRfc4122(), ['name' => 'sunday school']);

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('name', 'sunday school');
        $this->assertJsonPath('archived', true);
        $this->assertJsonPath('archivedAt', '2026-11-08T12:00:00+01:00');
    }

    public function testAnEditNeedsAName(): void
    {
        $service = Structure::service('Sunday School');
        $this->database()->persisted($service);
        $this->signInWithRoles('leader@example.org', 'service_leader');

        $this->jsonRequest('PUT', '/api/services/' . $service->id()->toRfc4122(), ['name' => null]);

        $this->assertProblem(422, 'checkFields');
        $this->assertFieldError('name', 'services.nameRequired');
    }

    public function testAnEditOfAnUnknownOrMalformedIdIsNotFound(): void
    {
        $this->signInWithRoles('leader@example.org', 'service_leader');

        $this->jsonRequest('PUT', '/api/services/019a3b6e-0000-7000-8000-000000000001', ['name' => 'Kids']);
        $this->assertProblem(404, 'notFound.service');

        $this->jsonRequest('PUT', '/api/services/not-a-uuid', ['name' => 'Kids']);
        $this->assertProblem(404, 'notFound.service');
    }

    public function testAServantCanNotEdit(): void
    {
        $service = Structure::service('Sunday School');
        $this->database()->persisted($service);
        $this->signInWithRoles('servant@example.org', 'servant');

        $this->jsonRequest('PUT', '/api/services/' . $service->id()->toRfc4122(), ['name' => 'Kids']);

        $this->assertProblem(403, 'forbidden');
    }

    private function database(): Database
    {
        return new Database(self::getContainer());
    }
}
