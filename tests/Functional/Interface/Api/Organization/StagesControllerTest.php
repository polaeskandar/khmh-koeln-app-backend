<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Organization;

use App\Context\Organization\Domain\Entity\Service;
use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use App\Tests\Support\JsonBody;
use PHPUnit\Framework\Attributes\DataProvider;

final class StagesControllerTest extends ApiTestCase
{
    public function testALeaderAddsAStageAfterTheActiveOnesAndBeforeTheArchived(): void
    {
        $service = $this->schoolWithStages();
        $this->signInWithRoles('leader@example.org', 'service_leader');

        $response = $this->jsonRequest('POST', $this->stagesOf($service), ['name' => ' 3rd  Class ']);

        self::assertSame(201, $response->getStatusCode());
        $created = $this->responseJson();
        self::assertSame('3rd Class', $created['name'] ?? null);
        self::assertSame(2, $created['position'] ?? null);

        $this->jsonRequest('GET', (string) $response->headers->get('Location'));
        self::assertSame($created, $this->responseJson());

        $this->jsonRequest('GET', $this->stagesOf($service));
        $this->assertJsonPath('stages.2.name', '3rd Class');
        $this->assertJsonPath('archivedStages.0.name', 'Old Class');
        $this->assertJsonPath('can.manageStructure', true);
    }

    #[DataProvider('outsiders')]
    public function testOnlyStructureManagersAddStages(string $role): void
    {
        $service = $this->schoolWithStages();
        $this->signInWithRoles('someone@example.org', $role);

        $this->jsonRequest('POST', $this->stagesOf($service), ['name' => '3rd Class']);

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
        $service = $this->schoolWithStages();
        $service->archive(Structure::now());
        new Database(self::getContainer())->persisted($service);
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest('POST', $this->stagesOf($service), ['name' => '3rd Class']);
        $this->assertProblem(409, 'services.archivedNoStageChanges', ['name' => 'Sunday School']);

        $this->jsonRequest('GET', $this->stagesOf($service));
        $this->assertJsonPath('can.manageStructure', false);
    }

    /**
     * @param array<string, string> $params
     */
    #[DataProvider('takenNames')]
    public function testAStageNameIsFreeWithinItsService(string $name, string $code, array $params): void
    {
        $service = $this->schoolWithStages();
        $this->signInWithRoles('leader@example.org', 'service_leader');

        $this->jsonRequest('POST', $this->stagesOf($service), ['name' => $name]);

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

    public function testTheSameNameInAnotherServiceAndACaseOnlyRenameAreFine(): void
    {
        $school = $this->schoolWithStages();
        $youth = Structure::service('Youth');
        new Database(self::getContainer())->persisted($youth);
        $this->signInWithRoles('leader@example.org', 'service_leader');

        $this->jsonRequest('POST', $this->stagesOf($youth), ['name' => '1st Class']);
        self::assertResponseStatusCodeSame(201);

        $this->jsonRequest('GET', $this->stagesOf($school));
        $firstId = Type::of(JsonBody::valueAt($this->responseJson(), ['stages', '0', 'id'], 'stages.0.id'))->toString();
        $this->jsonRequest('PATCH', '/api/stages/' . $firstId, ['name' => '1ST class']);
        self::assertResponseIsSuccessful();
        $this->assertJsonPath('name', '1ST class');
        $this->assertJsonPath('position', 0);
    }

    public function testAServantReadsAStageWithoutStructureRights(): void
    {
        $service = $this->schoolWithStages();
        $this->signInWithRoles('servant@example.org', 'servant');

        $this->jsonRequest('GET', $this->stagesOf($service));
        $stageId = Type::of(JsonBody::valueAt($this->responseJson(), ['stages', '1', 'id'], 'stages.1.id'))->toString();

        $this->jsonRequest('GET', '/api/stages/' . $stageId);
        $this->assertJsonPath('can.manageStructure', false);
        $this->assertJsonPath('can.manageMembers', true);
        $this->assertJsonPath('service.name', 'Sunday School');

        $this->jsonRequest('GET', '/api/stages/019a3b6e-0000-7000-8000-000000000001');
        $this->assertProblem(404, 'notFound.stage');
    }

    private function schoolWithStages(): Service
    {
        $service = Structure::service('Sunday School');
        $old = Structure::stage($service, 'Old Class', position: 2);
        $old->archive(Structure::now());

        new Database(self::getContainer())->persisted(
            $service,
            Structure::stage($service, '1st Class', position: 0),
            Structure::stage($service, '2nd Class', position: 1),
            $old,
        );

        return $service;
    }

    private function stagesOf(Service $service): string
    {
        return '/api/services/' . $service->id()->toRfc4122() . '/stages';
    }
}
