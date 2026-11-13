<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Access;

use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Library\Types\Model\Integers;
use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Access\Domain\Entity\RoleGrants;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Database;
use App\Tests\Support\JsonBody;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class RolesControllerWritesTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    private const string ADMINISTRATOR_ROLE = '019a3f00-0000-7000-8000-000000000001';

    private const string SERVANT_ROLE = '019a3f00-0000-7000-8000-000000000003';

    private const array READ_SERVICES = ['action' => 'read', 'resource' => 'services'];

    public function testARoleIsCreatedWithItsNameTidiedAndItsPermissionsInCatalogOrder(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest('POST', '/api/roles', [
            'name' => '   Choir    leader  ',
            'permissions' => [['action' => 'create', 'resource' => 'attendance'], self::READ_SERVICES],
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame(['id'], array_keys($this->responseJson()));
        $id = $this->createdId();
        self::assertResponseHeaderSame('Location', '/api/roles/' . $id);

        $this->jsonRequest('GET', '/api/roles/' . $id);

        self::assertSame(
            [
                'id' => $id,
                'name' => 'Choir leader',
                'scope' => 'church',
                'permissions' => [self::READ_SERVICES, ['action' => 'create', 'resource' => 'attendance']],
                'createdAt' => '2026-11-08T10:00:00+01:00',
                'updatedAt' => '2026-11-08T10:00:00+01:00',
                'holders' => [],
                'onlyWayToChangeRoles' => false,
            ],
            $this->responseJson(),
        );
    }

    public function testANameIsRequiredAtMostEightyCharactersAndNotTakenByAnotherRole(): void
    {
        $this->signInWithRoles('admin@example.org', 'administrator');
        $before = $this->roleCount();

        $this->jsonRequest('POST', '/api/roles', ['permissions' => [self::READ_SERVICES]]);
        $this->assertNameRefused('roles.nameRequired');
        $this->jsonRequest('POST', '/api/roles', ['name' => '   ', 'permissions' => [self::READ_SERVICES]]);
        $this->assertNameRefused('roles.nameRequired');
        $this->jsonRequest('POST', '/api/roles', ['name' => str_repeat('a', times: 81), 'permissions' => []]);
        $this->assertNameRefused('validation.tooLong', ['max' => 80]);
        $this->jsonRequest('POST', '/api/roles', ['name' => '  SERVICE   leader ', 'permissions' => []]);
        $this->assertNameRefused('roles.nameTaken', ['name' => 'Service leader']);
        $this->jsonRequest('PUT', '/api/roles/' . self::SERVANT_ROLE, ['name' => ' parent', 'permissions' => []]);
        $this->assertNameRefused('roles.nameTaken', ['name' => 'Parent']);

        self::assertSame($before, $this->roleCount());

        $this->jsonRequest('POST', '/api/roles', ['name' => str_repeat('a', times: 80), 'permissions' => []]);
        self::assertResponseStatusCodeSame(201);

        $this->jsonRequest('PUT', '/api/roles/' . self::SERVANT_ROLE, ['name' => 'SERVANT', 'permissions' => []]);
        self::assertResponseIsSuccessful();
        $this->assertJsonPath('name', 'SERVANT');

        $this->jsonRequest('POST', '/api/roles', ['name' => "Chorleiter M\u{00FC}hle", 'permissions' => []]);
        self::assertResponseStatusCodeSame(201);
        $this->jsonRequest('POST', '/api/roles', ['name' => "chorleiter mu\u{0308}hle", 'permissions' => []]);
        $this->assertNameRefused('roles.nameTaken', ['name' => "Chorleiter M\u{00FC}hle"]);
    }

    public function testEveryPermissionComesFromTheCatalogOnce(): void
    {
        $this->signInWithRoles('admin@example.org', 'administrator');
        $before = $this->roleCount();

        $refused = [
            [['action' => 'launch', 'resource' => 'services']],
            [['action' => 'read', 'resource' => 'rockets']],
            [['action' => 'read']],
            [self::READ_SERVICES, ['action' => 'read', 'resource' => 'lessons'], self::READ_SERVICES],
        ];

        foreach ($refused as $permissions) {
            $this->jsonRequest('POST', '/api/roles', ['name' => 'Choir leader', 'permissions' => $permissions]);
            $this->assertProblem(422, 'checkFields');
            $this->assertFieldError('permissions', 'validation.invalidChoice');
        }

        $this->jsonRequest('PUT', '/api/roles/' . self::SERVANT_ROLE, [
            'name' => '',
            'permissions' => [self::READ_SERVICES, self::READ_SERVICES],
        ]);

        $this->assertProblem(422, 'checkFields');
        $this->assertFieldError('name', 'roles.nameRequired');
        $this->assertFieldError('permissions', 'validation.invalidChoice');
        self::assertSame($before, $this->roleCount());

        $this->jsonRequest('GET', '/api/roles/' . self::SERVANT_ROLE);
        $this->assertJsonPath('name', 'Servant');
        self::assertCount(17, JsonBody::asArray($this->responseJson()['permissions'] ?? null, 'permissions'));
    }

    public function testAChangeReplacesTheWholeRoleAndReachesItsHoldersAtOnce(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $bishoy = new SignInAccounts(self::getContainer())->add('bishoy@example.org');
        new RoleGrants(self::getContainer())->grant($bishoy, 'servant');
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest('PUT', '/api/roles/' . self::SERVANT_ROLE, [
            'name' => ' Helper ',
            'permissions' => [self::READ_SERVICES],
        ]);

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('id', self::SERVANT_ROLE);
        $this->assertJsonPath('name', 'Helper');
        $this->assertJsonPath('permissions', [self::READ_SERVICES]);
        $this->assertJsonPath('createdAt', '2026-11-01T01:00:00+01:00');
        $this->assertJsonPath('updatedAt', '2026-11-08T10:00:00+01:00');
        $this->assertJsonPath('holders.0.id', $bishoy);
        $this->assertJsonPath('onlyWayToChangeRoles', false);

        self::mockTime('2026-11-08T11:00:00+01:00');

        $this->jsonRequest('PUT', '/api/roles/' . self::SERVANT_ROLE, [
            'name' => 'Helper',
            'permissions' => [['action' => 'read', 'resource' => 'lessons'], self::READ_SERVICES],
        ]);

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('permissions', [self::READ_SERVICES, ['action' => 'read', 'resource' => 'lessons']]);
        $this->assertJsonPath('scope', 'assignedGroups');
        $this->assertJsonPath('updatedAt', '2026-11-08T11:00:00+01:00');

        $this->browser()->getCookieJar()->clear();
        $this->signInAs('bishoy@example.org', SignInAccounts::TYPED);
        $this->jsonRequest('GET', '/api/me');
        $this->assertJsonPath('roles', [['id' => self::SERVANT_ROLE, 'name' => 'Helper']]);

        $this->assertJsonPath('permissions', [
            [...self::READ_SERVICES, 'scope' => 'assignedGroups'],
            ['action' => 'read', 'resource' => 'lessons', 'scope' => 'assignedGroups'],
        ]);
    }

    public function testTheLastWayToChangeRolesIsNeitherTakenAwayNorDeleted(): void
    {
        $this->signInWithRoles('admin@example.org', 'administrator');
        $withoutRoleChanges = [...self::everythingButRoleChanges()];

        $this->jsonRequest('PUT', '/api/roles/' . self::ADMINISTRATOR_ROLE, [
            'name' => 'Administrator',
            'permissions' => $withoutRoleChanges,
        ]);

        $this->assertProblem(409, 'roles.lastAdministrator');
        $this->jsonRequest('DELETE', '/api/roles/' . self::ADMINISTRATOR_ROLE);
        $this->assertProblem(409, 'roles.lastAdministrator');

        $this->jsonRequest('GET', '/api/roles/' . self::ADMINISTRATOR_ROLE);
        self::assertCount(36, JsonBody::asArray($this->responseJson()['permissions'] ?? null, 'permissions'));
        $this->assertJsonPath('onlyWayToChangeRoles', true);

        $this->jsonRequest('PUT', '/api/roles/' . self::ADMINISTRATOR_ROLE, [
            'name' => 'Administrator',
            'permissions' => [...$withoutRoleChanges, ['action' => 'update', 'resource' => 'roles']],
        ]);

        self::assertResponseIsSuccessful();

        new RoleGrants(self::getContainer())->createRole('Role keeper', Action::Update, Resource::Roles);
        $keeper = new SignInAccounts(self::getContainer())->add('mariam@example.org');
        new RoleGrants(self::getContainer())->grant($keeper, 'role_keeper');

        $this->jsonRequest('PUT', '/api/roles/' . self::ADMINISTRATOR_ROLE, [
            'name' => 'Administrator',
            'permissions' => $withoutRoleChanges,
        ]);

        self::assertResponseIsSuccessful();
        self::assertCount(35, JsonBody::asArray($this->responseJson()['permissions'] ?? null, 'permissions'));

        $this->jsonRequest('DELETE', '/api/roles/' . self::ADMINISTRATOR_ROLE);
        self::assertResponseStatusCodeSame(204);
    }

    public function testADeletedRoleIsGoneAndItsHoldersLoseItAtOnce(): void
    {
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest('POST', '/api/roles', [
            'name' => 'Choir leader',
            'permissions' => [['action' => 'update', 'resource' => 'services']],
        ]);

        $choir = $this->createdId();
        $bishoy = new SignInAccounts(self::getContainer())->add('bishoy@example.org');
        new RoleGrants(self::getContainer())->grant($bishoy, 'servant', 'choir_leader');

        $this->jsonRequest('DELETE', '/api/roles/' . $choir);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('', $this->browser()->getResponse()->getContent());
        $this->jsonRequest('GET', '/api/roles/' . $choir);
        $this->assertProblem(404, 'notFound.role');
        $this->jsonRequest('GET', '/api/roles');

        self::assertStringNotContainsString('Choir leader', (string) $this->browser()->getResponse()->getContent());

        $this->browser()->getCookieJar()->clear();
        $this->signInAs('bishoy@example.org', SignInAccounts::TYPED);
        $this->jsonRequest('GET', '/api/me');
        $this->assertJsonPath('roles', [['id' => self::SERVANT_ROLE, 'name' => 'Servant']]);
        $permissions = JsonBody::asArray($this->responseJson()['permissions'] ?? null, 'permissions');
        self::assertNotContains(['action' => 'update', 'resource' => 'services', 'scope' => 'church'], $permissions);
        self::assertContains([...self::READ_SERVICES, 'scope' => 'assignedGroups'], $permissions);
    }

    /**
     * @return iterable<int, array{action: string, resource: string}>
     */
    private static function everythingButRoleChanges(): iterable
    {
        $resources = [
            'users',
            'roles',
            'services',
            'stages',
            'sessions',
            'attendance',
            'members',
            'lessons',
            'homework',
        ];

        foreach ($resources as $resource) {
            foreach (['create', 'read', 'update', 'delete'] as $action) {
                if ($resource === 'roles' && $action === 'update') {
                    continue;
                }

                yield ['action' => $action, 'resource' => $resource];
            }
        }
    }

    /**
     * @param array<string, int|string> $params
     */
    private function assertNameRefused(string $code, array $params = []): void
    {
        $this->assertProblem(422, 'checkFields');
        $this->assertFieldError('name', $code, $params);
    }

    private function createdId(): string
    {
        return Type::of($this->responseJson()['id'] ?? null)->toString();
    }

    private function roleCount(): int
    {
        return Integers::of(
            new Database(self::getContainer())
                ->connection()
                ->fetchOne('SELECT count(*) FROM access.role'),
        );
    }
}
