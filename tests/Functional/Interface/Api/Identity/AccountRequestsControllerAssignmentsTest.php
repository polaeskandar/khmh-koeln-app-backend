<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Identity;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Context\Organization\Domain\Entity\ServedGroups;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use App\Tests\Support\Interface\Api\Identity\Registrations;
use Symfony\Component\Uid\Uuid;

final class AccountRequestsControllerAssignmentsTest extends ApiTestCase
{
    private const string SERVANT = 'daniel@example.org';

    private const string SERVANT_ROLE = '019a3f00-0000-7000-8000-000000000003';

    public function testAServantIsApprovedWithTheGroupsItServesInOnce(): void
    {
        [$sundaySchool, $third] = $this->registered();
        $this->signInWithRoles('admin@example.org', 'administrator');
        $chosen = ['serviceId' => $sundaySchool->id()->toRfc4122(), 'stageId' => $third->id()->toRfc4122()];

        $this->approve([
            'as' => 'servant',
            'roleIds' => [self::SERVANT_ROLE],
            'assignments' => [
                $chosen,
                ['serviceId' => strtoupper($chosen['serviceId']), 'stageId' => $chosen['stageId']],
            ],
        ]);

        self::assertResponseIsSuccessful();
        $connection = new Database(self::getContainer())->connection();
        self::assertSame(0, $connection->fetchOne('SELECT count(*) FROM organization.registration_service'));

        self::assertSame(
            [['service_id' => $chosen['serviceId'], 'stage_id' => $chosen['stageId']]],
            $connection->fetchAllAssociative('SELECT service_id, stage_id FROM organization.staff_assignment'),
        );

        $this->browser()->getCookieJar()->clear();
        $this->signInAs(self::SERVANT, SignInAccounts::TYPED);
        $this->jsonRequest('GET', '/api/me');
        $this->assertJsonPath('assignments.0.stage.name', '3rd Class');
    }

    public function testAStageListedWithItsWholeServiceIsLeftOutAndTheServiceAbsorbsHeldStages(): void
    {
        [$sundaySchool, $third] = $this->registered();
        $youth = Structure::service('Youth Meeting');
        $juniors = Structure::stage($youth, 'Juniors');
        $database = new Database(self::getContainer());
        $database->persisted($youth, $juniors);
        $groups = new ServedGroups(self::getContainer());
        $groups->assign($this->servantId(), $youth->id()->toRfc4122(), $juniors->id()->toRfc4122());
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->approve([
            'as' => 'servant',
            'roleIds' => [self::SERVANT_ROLE],
            'assignments' => [
                ['serviceId' => $sundaySchool->id()->toRfc4122(), 'stageId' => $third->id()->toRfc4122()],
                ['serviceId' => $sundaySchool->id()->toRfc4122()],
                ['serviceId' => $youth->id()->toRfc4122(), 'stageId' => null],
            ],
        ]);

        self::assertResponseIsSuccessful();

        self::assertSame(
            [
                ['service_id' => $sundaySchool->id()->toRfc4122(), 'stage_id' => null],
                ['service_id' => $youth->id()->toRfc4122(), 'stage_id' => null],
            ],
            $database
                ->connection()
                ->fetchAllAssociative(
                    'SELECT service_id, stage_id FROM organization.staff_assignment ORDER BY service_id',
                ),
        );
    }

    public function testEveryInvalidGroupIsReportedAndNothingIsStored(): void
    {
        [$sundaySchool, $third] = $this->registered();
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->approve([
            'as' => 'servant',
            'roleIds' => [],
            'assignments' => [
                ['serviceId' => $sundaySchool->id()->toRfc4122(), 'stageId' => $third->id()->toRfc4122()],
                ['serviceId' => Uuid::v7()->toRfc4122()],
                ['serviceId' => $sundaySchool->id()->toRfc4122(), 'stageId' => Uuid::v7()->toRfc4122()],
            ],
        ]);

        $this->assertFieldError('roleIds', 'roles.chooseRole');
        $this->assertFieldError('assignments.1.serviceId', 'meetings.chooseService');
        $this->assertFieldError('assignments.2.stageId', 'meetings.chooseStage');

        $this->approve(['as' => 'servant', 'roleIds' => [self::SERVANT_ROLE], 'assignments' => [['serviceId' => 4]]]);
        $this->assertFieldError('assignments.0.serviceId', 'validation.invalidType');

        $connection = new Database(self::getContainer())->connection();
        self::assertSame(0, $connection->fetchOne('SELECT count(*) FROM organization.staff_assignment'));

        self::assertNull($connection->fetchOne('SELECT approved_at FROM identity.app_user WHERE email = ?', [
            self::SERVANT,
        ]));
    }

    public function testAnAdministratorWhoseUserRightsHoldInGroupsOnlyAssignsWhereItLeads(): void
    {
        [$sundaySchool] = $this->registered();
        $connection = new Database(self::getContainer())->connection();

        $connection->executeStatement(
            "UPDATE identity.role SET scope = 'assignedGroups' WHERE name_key = 'administrator'",
        );

        $admin = $this->signInWithRoles('admin@example.org', 'administrator');
        $choir = Structure::service('Choir');
        new Database(self::getContainer())->persisted($choir);
        new ServedGroups(self::getContainer())->assign($admin, $choir->id()->toRfc4122());

        $this->approve([
            'as' => 'servant',
            'roleIds' => [self::SERVANT_ROLE],
            'assignments' => [['serviceId' => $sundaySchool->id()->toRfc4122()]],
        ]);

        $this->assertProblem(403, 'assignments.notAllowed');

        self::assertNull($connection->fetchOne('SELECT approved_at FROM identity.app_user WHERE email = ?', [
            self::SERVANT,
        ]));

        $this->approve([
            'as' => 'servant',
            'roleIds' => [self::SERVANT_ROLE],
            'assignments' => [['serviceId' => $choir->id()->toRfc4122()]],
        ]);

        self::assertResponseIsSuccessful();
    }

    /**
     * @return array{Service, Stage}
     */
    private function registered(): array
    {
        $sundaySchool = Structure::service('Sunday School');
        $third = Structure::stage($sundaySchool, '3rd Class', position: 0);
        new Database(self::getContainer())->persisted($sundaySchool, $third);

        $this->jsonRequest('POST', '/api/auth/register', Registrations::servant([$sundaySchool->id()->toRfc4122()]));

        return [$sundaySchool, $third];
    }

    /**
     * @param array<string, mixed> $body
     */
    private function approve(array $body): void
    {
        $this->jsonRequest('POST', '/api/account-requests/' . $this->servantId() . '/approve', $body);
    }

    private function servantId(): string
    {
        $id = new Database(self::getContainer())
            ->connection()
            ->fetchOne('SELECT id FROM identity.app_user WHERE email = ?', [self::SERVANT]);

        self::assertIsString($id);

        return $id;
    }
}
