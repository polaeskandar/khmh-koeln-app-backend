<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Organization;

use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Organization\Domain\Entity\ServedGroups;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\Organization\Domain\Entity\SundaySchool;
use App\Tests\Support\Database;
use App\Tests\Support\JsonBody;
use PHPUnit\Framework\Attributes\DataProvider;

final class StagesControllerTest extends ApiTestCase
{
    public function testALeaderAddsAStageAfterTheActiveOnesAndBeforeTheArchived(): void
    {
        $service = SundaySchool::withStagesIn(new Database(self::getContainer()));

        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles(
            'leader@example.org',
            'service_leader',
        ));

        $response = $this->jsonRequest('POST', SundaySchool::stagesPath($service), ['name' => ' 3rd  Class ']);

        self::assertSame(201, $response->getStatusCode());
        $created = $this->responseJson();
        self::assertSame('3rd Class', $created['name'] ?? null);
        self::assertSame(2, $created['position'] ?? null);

        $this->jsonRequest('GET', (string) $response->headers->get('Location'));
        self::assertSame($created, $this->responseJson());

        $this->jsonRequest('GET', SundaySchool::stagesPath($service));
        $this->assertJsonPath('stages.2.name', '3rd Class');
        $this->assertJsonPath('archivedStages.0.name', 'Old Class');
        $this->assertJsonPath('archivedStages.0.archivedAt', '2026-11-08T12:00:00+01:00');
        self::assertSame(['service', 'stages', 'archivedStages'], array_keys($this->responseJson()));
    }

    #[DataProvider('outsiders')]
    public function testOnlyStructureManagersAddStages(string $role): void
    {
        $service = SundaySchool::withStagesIn(new Database(self::getContainer()));
        $this->signInWithRoles('someone@example.org', $role);

        $this->jsonRequest('POST', SundaySchool::stagesPath($service), ['name' => '3rd Class']);

        $this->assertProblem(403, 'forbidden');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function outsiders(): iterable
    {
        yield 'servant' => ['servant'];
        yield 'parent' => ['parent'];
    }

    public function testAnArchivedServiceTakesNoStageChanges(): void
    {
        $service = SundaySchool::withStagesIn(new Database(self::getContainer()));
        $service->archive(Structure::now());
        new Database(self::getContainer())->persisted($service);
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest('POST', SundaySchool::stagesPath($service), ['name' => '3rd Class']);
        $this->assertProblem(409, 'services.archivedNoStageChanges', ['name' => 'Sunday School']);

        $this->jsonRequest('GET', SundaySchool::stagesPath($service));
        $this->assertJsonPath('service.archived', true);
    }

    /**
     * @param array<string, string> $params
     */
    #[DataProvider('takenNames')]
    public function testAStageNameIsFreeWithinItsService(string $name, string $code, array $params): void
    {
        $service = SundaySchool::withStagesIn(new Database(self::getContainer()));

        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles(
            'leader@example.org',
            'service_leader',
        ));

        $this->jsonRequest('POST', SundaySchool::stagesPath($service), ['name' => $name]);

        $this->assertProblem(422, 'checkFields');
        $this->assertFieldError('name', $code, $params);
    }

    /**
     * @return iterable<string, array{string, string, array<string, string>}>
     */
    public static function takenNames(): iterable
    {
        yield 'active' => [' 1st   CLASS', 'stages.nameTaken', ['name' => '1st Class']];
        yield 'archived' => ['old class', 'stages.nameTakenArchived', ['name' => 'Old Class']];
    }

    public function testAServantReadsAStage(): void
    {
        $service = SundaySchool::withStagesIn(new Database(self::getContainer()));
        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles('servant@example.org', 'servant'));

        $this->jsonRequest('GET', SundaySchool::stagesPath($service));
        $stageId = Type::of(JsonBody::valueAt($this->responseJson(), ['stages', '1', 'id'], 'stages.1.id'))->toString();

        $this->jsonRequest('GET', '/api/stages/' . $stageId);

        self::assertSame(
            ['id', 'name', 'description', 'position', 'archived', 'memberCount', 'service'],
            array_keys($this->responseJson()),
        );

        $this->assertJsonPath('service.name', 'Sunday School');

        $this->jsonRequest('GET', '/api/stages/019a3b6e-0000-7000-8000-000000000001');
        $this->assertProblem(404, 'notFound.stage');

        $this->jsonRequest('GET', '/api/stages/not-a-uuid');
        $this->assertProblem(404, 'notFound.stage');
    }
}
