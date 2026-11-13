<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Identity;

use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Domain\Entity\RoleGrants;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\JsonBody;
use Symfony\Component\Uid\Uuid;

final class RolesControllerTest extends ApiTestCase
{
    private const string ADMINISTRATOR_ROLE = '019a3f00-0000-7000-8000-000000000001';

    private const string SERVANT_ROLE = '019a3f00-0000-7000-8000-000000000003';

    private const string PARENT_ROLE = '019a3f00-0000-7000-8000-000000000004';

    private const array BODY = [
        'name' => 'Choir leader',
        'permissions' => [['action' => 'read', 'resource' => 'services']],
    ];

    private const array SERVANT_PERMISSIONS = [
        ['action' => 'read', 'resource' => 'services'],
        ['action' => 'create', 'resource' => 'attendance'],
        ['action' => 'read', 'resource' => 'attendance'],
        ['action' => 'update', 'resource' => 'attendance'],
        ['action' => 'delete', 'resource' => 'attendance'],
        ['action' => 'create', 'resource' => 'members'],
        ['action' => 'read', 'resource' => 'members'],
        ['action' => 'update', 'resource' => 'members'],
        ['action' => 'delete', 'resource' => 'members'],
        ['action' => 'create', 'resource' => 'lessons'],
        ['action' => 'read', 'resource' => 'lessons'],
        ['action' => 'update', 'resource' => 'lessons'],
        ['action' => 'delete', 'resource' => 'lessons'],
        ['action' => 'create', 'resource' => 'homework'],
        ['action' => 'read', 'resource' => 'homework'],
        ['action' => 'update', 'resource' => 'homework'],
        ['action' => 'delete', 'resource' => 'homework'],
    ];

    private const string DISABLED = '2026-11-01 10:00:00+01';

    public function testOnlyAccountsWithTheRolesPermissionsMayUseTheCatalog(): void
    {
        $calls = [
            ['GET', '/api/roles', []],
            ['GET', '/api/roles/' . self::SERVANT_ROLE, []],
            ['POST', '/api/roles', self::BODY],
            ['PUT', '/api/roles/' . self::SERVANT_ROLE, self::BODY],
            ['DELETE', '/api/roles/' . self::SERVANT_ROLE, []],
        ];

        foreach ($calls as [$method, $path, $body]) {
            $this->jsonRequest($method, $path, $body);
            $this->assertProblem(401, 'auth.unauthenticated');
        }

        foreach (['service_leader', 'servant', 'parent'] as $role) {
            $this->signInWithRoles($role . '@example.org', $role);

            foreach ($calls as [$method, $path, $body]) {
                $this->jsonRequest($method, $path, $body);
                $this->assertProblem(403, 'roles.adminOnly');
            }

            $this->browser()->getCookieJar()->clear();
        }
    }

    public function testEachEndpointNeedsItsOwnPermission(): void
    {
        new RoleGrants(self::getContainer())->createRole('Role reader', Action::Read, Resource::Roles);
        $this->signInWithRoles('reader@example.org', 'role_reader');

        $this->jsonRequest('GET', '/api/roles');
        self::assertResponseIsSuccessful();
        $this->jsonRequest('GET', '/api/roles/' . self::SERVANT_ROLE);
        self::assertResponseIsSuccessful();

        $this->jsonRequest('POST', '/api/roles', self::BODY);
        $this->assertProblem(403, 'roles.adminOnly');
        $this->jsonRequest('PUT', '/api/roles/' . self::SERVANT_ROLE, self::BODY);
        $this->assertProblem(403, 'roles.adminOnly');
        $this->jsonRequest('DELETE', '/api/roles/' . self::SERVANT_ROLE);
        $this->assertProblem(403, 'roles.adminOnly');
    }

    public function testTheListHasEveryRoleByNameIgnoringCaseWithItsPermissionsAndHolderCount(): void
    {
        new RoleGrants(self::getContainer())->createRole('choir', Action::Read, Resource::Services);
        $this->account('bishoy@example.org', 'Bishoy Adel', [], 'servant', 'parent');
        $this->account('adel@example.org', 'Adel Fahmy', ['disabled_at' => self::DISABLED], 'servant');
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest('GET', '/api/roles');

        self::assertResponseIsSuccessful();
        self::assertSame(['items'], array_keys($this->responseJson()));

        self::assertSame(
            [
                ['Administrator', 1],
                ['choir', 0],
                ['Parent', 1],
                ['Servant', 2],
                ['Service leader', 0],
            ],
            array_map(static fn (array $item): array => [
                $item['name'] ?? null,
                $item['holderCount'] ?? null,
            ], $this->items()),
        );

        $this->assertJsonPath('items.1.permissions', [['action' => 'read', 'resource' => 'services']]);
        $this->assertJsonPath('items.2.id', self::PARENT_ROLE);
        $this->assertJsonPath('items.2.permissions', [['action' => 'read', 'resource' => 'parentPortal']]);
        $this->assertJsonPath('items.3.id', self::SERVANT_ROLE);
        $this->assertJsonPath('items.3.permissions', self::SERVANT_PERMISSIONS);
    }

    public function testARoleShowsItsPermissionsItsTimesAndItsHoldersByName(): void
    {
        $bishoy = $this->account('bishoy@example.org', 'bishoy Adel', [], 'servant');
        $zeina = $this->account('zeina@example.org', 'Zeina Gerges', ['disabled_at' => self::DISABLED], 'servant');
        $adel = $this->account('adel@example.org', 'Adel Fahmy', [], 'servant', 'parent');
        $this->account('hoda@example.org', 'Hoda Farag', [], 'parent');
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest('GET', '/api/roles/' . self::SERVANT_ROLE);

        self::assertResponseIsSuccessful();

        self::assertSame(
            [
                'id' => self::SERVANT_ROLE,
                'name' => 'Servant',
                'scope' => 'assignedGroups',
                'permissions' => self::SERVANT_PERMISSIONS,
                'createdAt' => '2026-11-01T01:00:00+01:00',
                'updatedAt' => '2026-11-01T01:00:00+01:00',
                'holders' => [
                    ['id' => $adel, 'displayName' => 'Adel Fahmy', 'email' => 'adel@example.org', 'disabled' => false],
                    [
                        'id' => $bishoy,
                        'displayName' => 'bishoy Adel',
                        'email' => 'bishoy@example.org',
                        'disabled' => false,
                    ],
                    [
                        'id' => $zeina,
                        'displayName' => 'Zeina Gerges',
                        'email' => 'zeina@example.org',
                        'disabled' => true,
                    ],
                ],
                'onlyWayToChangeRoles' => false,
            ],
            $this->responseJson(),
        );
    }

    public function testOnlyWayToChangeRolesSaysWhetherAnotherActiveAccountCouldChangeRoles(): void
    {
        $grants = new RoleGrants(self::getContainer());
        $grants->createRole('Role keeper', Action::Update, Resource::Roles);
        $this->signInWithRoles('admin@example.org', 'administrator');

        self::assertTrue($this->onlyWayToChangeRoles(self::ADMINISTRATOR_ROLE));
        self::assertFalse($this->onlyWayToChangeRoles(self::SERVANT_ROLE));

        $this->account('mariam@example.org', 'Mariam Saad', ['disabled_at' => self::DISABLED], 'role_keeper');
        self::assertTrue($this->onlyWayToChangeRoles(self::ADMINISTRATOR_ROLE));

        $this->account('kirollos@example.org', 'Kirollos Nabil', [], 'role_keeper');
        self::assertFalse($this->onlyWayToChangeRoles(self::ADMINISTRATOR_ROLE));
        self::assertFalse($this->onlyWayToChangeRoles($this->idOf('Role keeper')));
    }

    public function testAnUnknownRoleIsNotFound(): void
    {
        $this->signInWithRoles('admin@example.org', 'administrator');

        foreach ([Uuid::v7()->toRfc4122(), 'not-a-role'] as $id) {
            $this->jsonRequest('GET', '/api/roles/' . $id);
            $this->assertProblem(404, 'notFound.role');
            $this->jsonRequest('PUT', '/api/roles/' . $id, self::BODY);
            $this->assertProblem(404, 'notFound.role');
            $this->jsonRequest('DELETE', '/api/roles/' . $id);
            $this->assertProblem(404, 'notFound.role');
        }
    }

    /**
     * @param array<string, string|null> $columns
     */
    private function account(string $email, string $name, array $columns, string ...$roles): string
    {
        $id = new SignInAccounts(self::getContainer())->add($email, ['display_name' => $name, ...$columns]);
        new RoleGrants(self::getContainer())->grant($id, ...$roles);

        return $id;
    }

    private function onlyWayToChangeRoles(string $role): bool
    {
        $this->jsonRequest('GET', '/api/roles/' . $role);
        self::assertResponseIsSuccessful();

        return ($this->responseJson()['onlyWayToChangeRoles'] ?? null) === true;
    }

    private function idOf(string $name): string
    {
        $this->jsonRequest('GET', '/api/roles');

        foreach ($this->items() as $item) {
            if (($item['name'] ?? null) === $name) {
                return is_string($item['id'] ?? null) ? $item['id'] : '';
            }
        }

        return '';
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function items(): array
    {
        $items = JsonBody::asArray($this->responseJson()['items'] ?? null, 'items');

        return array_values(array_filter($items, is_array(...)));
    }
}
