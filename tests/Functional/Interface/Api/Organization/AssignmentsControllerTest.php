<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Organization;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Domain\Entity\RoleGrants;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Context\Organization\Domain\Entity\ServedGroups;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use Symfony\Component\Uid\Uuid;

final class AssignmentsControllerTest extends ApiTestCase
{
    public function testAnAdministratorAssignsAServantToAStageAndTheAccountSeesIt(): void
    {
        [$sundaySchool, , $third, $youth] = $this->structure();
        $bishoy = $this->servant('bishoy@example.org', 'Bishoy Adel', 'servant');
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest('POST', '/api/accounts/' . $bishoy . '/assignments', [
            'serviceId' => $sundaySchool->id()->toRfc4122(),
            'stageId' => $third->id()->toRfc4122(),
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame(['id', 'service', 'stage'], array_keys($this->responseJson()));
        $this->assertJsonPath('service.name', 'Sunday School');

        $this->assertJsonPath('stage', [
            'id' => $third->id()->toRfc4122(),
            'name' => '3rd Class',
            'position' => 2,
            'archived' => false,
        ]);

        $this->jsonRequest('POST', '/api/accounts/' . $bishoy . '/assignments', [
            'serviceId' => $youth->id()->toRfc4122(),
            'stageId' => null,
        ]);

        self::assertResponseStatusCodeSame(201);
        $this->assertJsonPath('stage', null);

        $this->jsonRequest('GET', '/api/staff-accounts');
        $this->assertJsonPath('items.0.displayName', 'Bishoy Adel');
        $this->assertJsonPath('items.0.assignments.0.service.name', 'Sunday School');
        $this->assertJsonPath('items.0.assignments.0.stage.name', '3rd Class');
        $this->assertJsonPath('items.0.assignments.1.service.name', 'Youth Meeting');

        $this->browser()->getCookieJar()->clear();
        $this->signInAs('bishoy@example.org', SignInAccounts::TYPED);
        $this->jsonRequest('GET', '/api/me');

        $this->assertJsonPath('assignments', [
            [
                'service' => [
                    'id' => $sundaySchool->id()->toRfc4122(),
                    'name' => 'Sunday School',
                    'tone' => 1,
                    'archived' => false,
                ],
                'stage' => [
                    'id' => $third->id()->toRfc4122(),
                    'name' => '3rd Class',
                    'position' => 2,
                    'archived' => false,
                ],
            ],
            [
                'service' => [
                    'id' => $youth->id()->toRfc4122(),
                    'name' => 'Youth Meeting',
                    'tone' => 1,
                    'archived' => false,
                ],
                'stage' => null,
            ],
        ]);
    }

    public function testAGroupIsAssignedOnceAndOnlyOpenGroupsOfTheServiceCanBeChosen(): void
    {
        [$sundaySchool, $first, $third, $youth] = $this->structure();
        $bishoy = $this->servant('bishoy@example.org', 'Bishoy Adel', 'servant');
        $this->signInWithRoles('admin@example.org', 'administrator');
        $path = '/api/accounts/' . $bishoy . '/assignments';
        $service = $sundaySchool->id()->toRfc4122();

        $this->jsonRequest('POST', $path, ['serviceId' => $service, 'stageId' => $third->id()->toRfc4122()]);
        $this->jsonRequest('POST', $path, ['serviceId' => $service, 'stageId' => $third->id()->toRfc4122()]);
        $this->assertProblem(409, 'assignments.alreadyAssigned', ['name' => 'Bishoy Adel', 'where' => '3rd Class']);

        $this->jsonRequest('POST', $path, ['serviceId' => $service]);
        self::assertResponseStatusCodeSame(201);
        $this->jsonRequest('POST', $path, ['serviceId' => $service, 'stageId' => null]);
        $this->assertProblem(409, 'assignments.alreadyAssigned', ['name' => 'Bishoy Adel', 'where' => 'Sunday School']);

        $connection = new Database(self::getContainer())->connection();

        $connection->update(
            'organization.stage',
            ['archived_at' => '2026-11-01 12:00:00+01'],
            ['id' => $first->id()->toRfc4122()],
        );

        $this->jsonRequest('POST', $path, ['serviceId' => $service, 'stageId' => $first->id()->toRfc4122()]);
        $this->assertFieldError('stageId', 'meetings.stageArchived', ['name' => '1st Class']);

        $this->jsonRequest('POST', $path, [
            'serviceId' => $youth->id()->toRfc4122(),
            'stageId' => $third->id()->toRfc4122(),
        ]);

        $this->assertFieldError('stageId', 'meetings.chooseStage');

        $connection->update(
            'organization.service',
            ['archived_at' => '2026-11-01 12:00:00+01'],
            ['id' => $youth->id()->toRfc4122()],
        );

        $this->jsonRequest('POST', $path, ['serviceId' => $youth->id()->toRfc4122()]);
        $this->assertFieldError('serviceId', 'meetings.serviceArchived', ['name' => 'Youth Meeting']);

        $this->jsonRequest('POST', $path, []);
        $this->assertFieldError('serviceId', 'meetings.chooseService');
        $this->jsonRequest('POST', $path, ['serviceId' => 7]);
        $this->assertFieldError('serviceId', 'validation.invalidType');

        $this->jsonRequest('POST', '/api/accounts/' . Uuid::v7()->toRfc4122() . '/assignments', [
            'serviceId' => $service,
        ]);

        $this->assertProblem(404, 'notFound.user');
        $this->jsonRequest('POST', '/api/accounts/not-an-id/assignments', ['serviceId' => $service]);
        $this->assertProblem(404, 'notFound.user');

        self::assertSame(
            [['service_id' => $service, 'stage_id' => null]],
            $connection->fetchAllAssociative('SELECT service_id, stage_id FROM organization.staff_assignment'),
        );
    }

    public function testAStageOfAServiceTheAccountServesAsAWholeIsRefusedNamingTheService(): void
    {
        [$sundaySchool, , $third] = $this->structure();
        $bishoy = $this->servant('bishoy@example.org', 'Bishoy Adel', 'servant');
        $whole = new ServedGroups(self::getContainer())->assign($bishoy, $sundaySchool->id()->toRfc4122());
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest('POST', '/api/accounts/' . $bishoy . '/assignments', [
            'serviceId' => $sundaySchool->id()->toRfc4122(),
            'stageId' => $third->id()->toRfc4122(),
        ]);

        $this->assertProblem(409, 'assignments.alreadyAssigned', ['name' => 'Bishoy Adel', 'where' => 'Sunday School']);

        self::assertSame(
            [$whole],
            new Database(self::getContainer())
                ->connection()
                ->fetchFirstColumn('SELECT id FROM organization.staff_assignment'),
        );
    }

    public function testAssigningTheWholeServiceRemovesTheAccountsAssignmentsToItsStages(): void
    {
        [$sundaySchool, $first, $third, $youth] = $this->structure();
        $juniors = Structure::stage($youth, 'Juniors');
        new Database(self::getContainer())->persisted($juniors);
        $bishoy = $this->servant('bishoy@example.org', 'Bishoy Adel', 'servant');
        $mina = $this->servant('mina@example.org', 'Mina Samir', 'servant');
        $groups = new ServedGroups(self::getContainer());
        $groups->assign($bishoy, $sundaySchool->id()->toRfc4122(), $first->id()->toRfc4122());
        $groups->assign($bishoy, $sundaySchool->id()->toRfc4122(), $third->id()->toRfc4122());
        $otherService = $groups->assign($bishoy, $youth->id()->toRfc4122(), $juniors->id()->toRfc4122());
        $otherAccount = $groups->assign($mina, $sundaySchool->id()->toRfc4122(), $third->id()->toRfc4122());
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest('POST', '/api/accounts/' . $bishoy . '/assignments', [
            'serviceId' => $sundaySchool->id()->toRfc4122(),
            'stageId' => null,
        ]);

        self::assertResponseStatusCodeSame(201);
        $this->assertJsonPath('service.id', $sundaySchool->id()->toRfc4122());
        $this->assertJsonPath('stage', null);
        $created = Type::of($this->responseJson()['id'] ?? null)->toString();

        self::assertEqualsCanonicalizing(
            [$created, $otherService, $otherAccount],
            new Database(self::getContainer())
                ->connection()
                ->fetchFirstColumn('SELECT id FROM organization.staff_assignment'),
        );
    }

    public function testAServiceLeaderAssignsInTheServicesHeLeadsOnly(): void
    {
        [$sundaySchool, , $third, $youth] = $this->structure();
        $bishoy = $this->servant('bishoy@example.org', 'Bishoy Adel', 'servant');
        $leader = $this->signInWithRoles('leader@example.org', 'service_leader');
        $groups = new ServedGroups(self::getContainer());
        $groups->assign($leader, $sundaySchool->id()->toRfc4122());
        $groups->assign($leader, $youth->id()->toRfc4122(), null);
        $path = '/api/accounts/' . $bishoy . '/assignments';

        $this->jsonRequest('POST', $path, [
            'serviceId' => $sundaySchool->id()->toRfc4122(),
            'stageId' => $third->id()->toRfc4122(),
        ]);

        self::assertResponseStatusCodeSame(201);
        $assigned = Type::of($this->responseJson()['id'] ?? null)->toString();

        $choir = Structure::service('Choir');
        new Database(self::getContainer())->persisted($choir);
        $this->jsonRequest('POST', $path, ['serviceId' => $choir->id()->toRfc4122()]);
        $this->assertProblem(403, 'assignments.notAllowed');

        $other = $groups->assign($bishoy, $choir->id()->toRfc4122());
        $this->jsonRequest('DELETE', '/api/assignments/' . $other);
        $this->assertProblem(403, 'assignments.notAllowed');

        $this->jsonRequest('DELETE', '/api/assignments/' . $assigned);
        self::assertResponseStatusCodeSame(204);
        $this->jsonRequest('DELETE', '/api/assignments/' . $assigned);
        $this->assertProblem(404, 'notFound.assignment');
        $this->jsonRequest('DELETE', '/api/assignments/not-an-id');
        $this->assertProblem(404, 'notFound.assignment');
    }

    public function testServantsAndStageLeadersMayNotAssign(): void
    {
        [$sundaySchool, , $third] = $this->structure();
        $bishoy = $this->servant('bishoy@example.org', 'Bishoy Adel', 'servant');
        $assignment = new ServedGroups(self::getContainer())->assign($bishoy, $sundaySchool->id()->toRfc4122());
        $body = ['serviceId' => $sundaySchool->id()->toRfc4122(), 'stageId' => $third->id()->toRfc4122()];

        $this->jsonRequest('POST', '/api/accounts/' . $bishoy . '/assignments', $body);
        $this->assertProblem(401, 'auth.unauthenticated');

        $leader = $this->signInWithRoles('leader@example.org', 'service_leader');

        new ServedGroups(self::getContainer())->assign(
            $leader,
            $sundaySchool->id()->toRfc4122(),
            $third->id()->toRfc4122(),
        );

        $this->jsonRequest('POST', '/api/accounts/' . $bishoy . '/assignments', $body);
        $this->assertProblem(403, 'assignments.notAllowed');
        $this->jsonRequest('DELETE', '/api/assignments/' . $assignment);
        $this->assertProblem(403, 'assignments.notAllowed');

        foreach (['servant', 'parent'] as $role) {
            $this->browser()->getCookieJar()->clear();
            $this->signInWithRoles($role . '@example.org', $role);
            $this->jsonRequest('POST', '/api/accounts/' . $bishoy . '/assignments', $body);
            $this->assertProblem(403, 'assignments.notAllowed');
            $this->jsonRequest('DELETE', '/api/assignments/' . $assignment);
            $this->assertProblem(403, 'assignments.notAllowed');
        }

        self::assertSame(1, new Database(self::getContainer())
            ->connection()
            ->fetchOne('SELECT count(*) FROM organization.staff_assignment WHERE account_id = ?', [$bishoy]));
    }

    /**
     * @return array{Service, Stage, Stage, Service}
     */
    private function structure(): array
    {
        $sundaySchool = Structure::service('Sunday School');
        $first = Structure::stage($sundaySchool, '1st Class', position: 0);
        $second = Structure::stage($sundaySchool, '2nd Class', position: 1);
        $third = Structure::stage($sundaySchool, '3rd Class', position: 2);
        $youth = Structure::service('Youth Meeting');
        new Database(self::getContainer())->persisted($sundaySchool, $first, $second, $third);
        new Database(self::getContainer())->persisted($youth);

        return [$sundaySchool, $first, $third, $youth];
    }

    private function servant(string $email, string $name, string $role): string
    {
        $id = new SignInAccounts(self::getContainer())->add($email, ['display_name' => $name]);
        new RoleGrants(self::getContainer())->grant($id, $role);

        return $id;
    }
}
