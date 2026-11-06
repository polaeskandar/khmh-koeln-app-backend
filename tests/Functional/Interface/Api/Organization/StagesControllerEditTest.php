<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Organization;

use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\Organization\Domain\Entity\SundaySchool;
use App\Tests\Support\Database;
use App\Tests\Support\JsonBody;

final class StagesControllerEditTest extends ApiTestCase
{
    public function testTheSameNameInAnotherServiceAndACaseOnlyRenameAreFine(): void
    {
        $school = SundaySchool::withStagesIn(new Database(self::getContainer()));
        $youth = Structure::service('Youth');
        new Database(self::getContainer())->persisted($youth);
        $this->signInWithRoles('leader@example.org', 'service_leader');

        $this->jsonRequest('POST', SundaySchool::stagesPath($youth), ['name' => '1st Class']);
        self::assertResponseStatusCodeSame(201);

        $this->jsonRequest('GET', SundaySchool::stagesPath($school));
        $firstId = Type::of(JsonBody::valueAt($this->responseJson(), ['stages', '0', 'id'], 'stages.0.id'))->toString();
        $this->jsonRequest('PUT', '/api/stages/' . $firstId, ['name' => '1ST class']);
        self::assertResponseIsSuccessful();
        $this->assertJsonPath('name', '1ST class');
        $this->assertJsonPath('position', 0);
    }

    public function testEveryFailingFieldIsReportedAtOnceAndAnEditNeedsAName(): void
    {
        $service = SundaySchool::withStagesIn(new Database(self::getContainer()));
        $this->signInWithRoles('leader@example.org', 'service_leader');

        $this->jsonRequest('POST', SundaySchool::stagesPath($service), [
            'name' => ' ',
            'description' => str_repeat('a', times: 501),
        ]);

        $this->assertProblem(422, 'checkFields');
        $this->assertFieldError('name', 'stages.nameRequired');
        $this->assertFieldError('description', 'validation.tooLong', ['max' => 500]);

        $this->jsonRequest('GET', SundaySchool::stagesPath($service));
        $firstId = Type::of(JsonBody::valueAt($this->responseJson(), ['stages', '0', 'id'], 'stages.0.id'))->toString();
        $this->jsonRequest('PUT', '/api/stages/' . $firstId, ['name' => null]);
        $this->assertProblem(422, 'checkFields');
        $this->assertFieldError('name', 'stages.nameRequired');
    }

    public function testAMalformedServiceOrStageIdIsNotFound(): void
    {
        $this->signInWithRoles('leader@example.org', 'service_leader');

        $this->jsonRequest('POST', '/api/services/not-a-uuid/stages', ['name' => '3rd Class']);
        $this->assertProblem(404, 'notFound.service');

        $this->jsonRequest('PUT', '/api/stages/not-a-uuid', ['name' => '3rd Class']);
        $this->assertProblem(404, 'notFound.stage');
    }
}
