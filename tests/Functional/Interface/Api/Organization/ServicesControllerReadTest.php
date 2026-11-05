<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Organization;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use App\Tests\Support\JsonBody;

final class ServicesControllerReadTest extends ApiTestCase
{
    public function testAServantReadsActiveServicesWithTheirActiveStagesAndFlags(): void
    {
        $school = Structure::service('Sunday School');
        $old = Structure::stage($school, 'Old Class', position: 0);
        $old->archive(Structure::now());
        $archived = Structure::service('Archived Club');
        $archived->archive(Structure::now());

        $this->database()->persisted(
            Structure::service('Youth'),
            $school,
            $archived,
            Structure::stage($school, '3rd Class', position: 2),
            Structure::stage($school, '1st Class', position: 1),
            $old,
        );

        $this->signInWithRoles('servant@example.org', 'servant');

        $this->jsonRequest('GET', '/api/services');

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('can.create', false);
        $this->assertJsonPath('items.0.name', 'Sunday School');
        $this->assertJsonPath('items.0.stages.0.name', '1st Class');
        $this->assertJsonPath('items.0.stages.1.name', '3rd Class');
        $this->assertJsonPath('items.0.stages.1.can.manageMembers', true);
        $this->assertJsonPath('items.0.can.edit', false);
        $this->assertJsonPath('items.0.can.manageMembers', true);
        $this->assertJsonPath('items.1.name', 'Youth');
        self::assertCount(2, JsonBody::asArray($this->responseJson()['items'] ?? null, 'items'));
    }

    public function testTheStatusChoosesArchivedOrAllServices(): void
    {
        $archived = Structure::service('Archived Club');
        $archived->archive(Structure::now());
        $this->database()->persisted(Structure::service('Youth'), $archived);
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest('GET', '/api/services?status=archived');
        $this->assertJsonPath('items.0.name', 'Archived Club');
        $this->assertJsonPath('items.0.archived', true);
        $this->assertJsonPath('can.create', true);

        $this->jsonRequest('GET', '/api/services?status=all');
        $this->assertJsonPath('items.1.name', 'Youth');

        $this->jsonRequest('GET', '/api/services?status=deleted');
        $this->assertProblem(422, 'checkFields');
        $this->assertFieldError('status', 'validation.invalidChoice');
    }

    public function testParentsCanNotReadServicesAndUnknownIdsAreNotFound(): void
    {
        $this->signInWithRoles('parent@example.org', 'parent');
        $this->jsonRequest('GET', '/api/services');
        $this->assertProblem(403, 'forbidden');

        $this->signInWithRoles('servant@example.org', 'servant');
        $this->jsonRequest('GET', '/api/services/019a3b6e-0000-7000-8000-000000000001');
        $this->assertProblem(404, 'notFound.service');
    }

    private function database(): Database
    {
        return new Database(self::getContainer());
    }
}
