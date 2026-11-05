<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Identity;

use App\Library\Types\Model\Integers;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Domain\Entity\RoleGrants;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Database;
use App\Tests\Support\JsonBody;
use Symfony\Component\Uid\Uuid;

final class StaffAccountsControllerRolesTest extends ApiTestCase
{
    private const string ADMINISTRATOR_ROLE = '019a3f00-0000-7000-8000-000000000001';

    private const string SERVANT_ROLE = '019a3f00-0000-7000-8000-000000000003';

    public function testAServantRoleRecordsAttendanceFromTheNextRequest(): void
    {
        $bishoy = new SignInAccounts(self::getContainer())->add('bishoy@example.org');
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest('POST', '/api/accounts/' . $bishoy . '/roles', ['roleId' => self::SERVANT_ROLE]);

        self::assertResponseStatusCodeSame(201);
        $this->assertJsonPath('role', ['id' => self::SERVANT_ROLE, 'name' => 'Servant']);
        $this->browser()->getCookieJar()->clear();
        $this->signInAs('bishoy@example.org', SignInAccounts::TYPED);
        $this->jsonRequest('GET', '/api/me');
        $permissions = JsonBody::asArray($this->responseJson()['permissions'] ?? null, 'permissions');
        self::assertContains(['action' => 'create', 'resource' => 'attendance'], $permissions);
    }

    public function testEveryRefusalKeepsItsCodeAndStoresNothing(): void
    {
        $servant = new SignInAccounts(self::getContainer())->add('bishoy@example.org');
        new RoleGrants(self::getContainer())->grant($servant, 'servant');
        $pending = new SignInAccounts(self::getContainer())->add('mina@example.org', ['approved_at' => null]);
        $this->signInWithRoles('admin@example.org', 'administrator');
        $before = $this->roleCount();

        $this->give($servant, self::SERVANT_ROLE);
        $this->assertProblem(409, 'roles.alreadyHasRole', ['name' => 'Sara Hanna']);
        $this->give($pending, self::SERVANT_ROLE);
        $this->assertProblem(409, 'roles.accountPending');
        $this->give($servant, Uuid::v7()->toRfc4122());
        $this->assertFieldError('roleId', 'roles.unknown');
        $this->give(Uuid::v7()->toRfc4122(), self::SERVANT_ROLE);
        $this->assertProblem(404, 'notFound.user');
        $this->take($pending, self::SERVANT_ROLE);
        $this->assertProblem(409, 'roles.notHeld', ['name' => 'Sara Hanna']);
        $this->take($servant, Uuid::v7()->toRfc4122());
        $this->assertProblem(404, 'notFound.role');

        self::assertSame($before, $this->roleCount());
    }

    public function testAnAdministratorKeepsTheirOwnRoleButMayTakeAnothers(): void
    {
        $other = new SignInAccounts(self::getContainer())->add('mariam@example.org');
        new RoleGrants(self::getContainer())->grant($other, 'administrator');
        $self = $this->signInWithRoles('admin@example.org', 'administrator');

        $this->take($self, self::ADMINISTRATOR_ROLE);
        $this->assertProblem(409, 'roles.ownAdminRole');

        $this->take($other, self::ADMINISTRATOR_ROLE);
        self::assertResponseStatusCodeSame(204);

        $this->browser()->getCookieJar()->clear();
        $this->signInAs('mariam@example.org', SignInAccounts::TYPED);
        $this->jsonRequest('GET', '/api/staff-accounts');
        $this->assertProblem(403, 'forbidden');
    }

    private function give(string $account, string $role): void
    {
        $this->jsonRequest('POST', '/api/accounts/' . $account . '/roles', ['roleId' => $role]);
    }

    private function take(string $account, string $role): void
    {
        $this->jsonRequest('DELETE', '/api/accounts/' . $account . '/roles/' . $role);
    }

    private function roleCount(): int
    {
        return Integers::of(
            new Database(self::getContainer())
                ->connection()
                ->fetchOne('SELECT count(*) FROM identity.user_role'),
        );
    }
}
