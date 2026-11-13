<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Identity;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Access\Domain\Entity\RoleGrants;
use App\Tests\Support\Context\Identity\Domain\Entity\MemberLinks;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\JsonBody;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class StaffAccountsControllerTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    private const string SERVANT_ROLE = '019a3f00-0000-7000-8000-000000000003';

    public function testOnlyAdministratorsMayManageAccounts(): void
    {
        $someone = Uuid::v7()->toRfc4122();

        $calls = [
            ['GET', '/api/staff-accounts', 'forbidden', []],
            ['POST', '/api/accounts/' . $someone . '/roles', 'roles.adminOnly', ['roleId' => self::SERVANT_ROLE]],
            ['DELETE', '/api/accounts/' . $someone . '/roles/' . self::SERVANT_ROLE, 'roles.adminOnly', []],
        ];

        foreach ($calls as [$method, $path, , $body]) {
            $this->jsonRequest($method, $path, $body);
            $this->assertProblem(401, 'auth.unauthenticated');
        }

        foreach (['service_leader', 'servant', 'parent'] as $role) {
            $this->signInWithRoles($role . '@example.org', $role);

            foreach ($calls as [$method, $path, $code, $body]) {
                $this->jsonRequest($method, $path, $body);
                $this->assertProblem(403, $code);
            }

            $this->browser()->getCookieJar()->clear();
        }
    }

    public function testTheListHasStaffAndRoleLessAccountsWithDisabledOnesLast(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $this->account('bishoy@example.org', 'Bishoy Adel', [], 'servant', 'parent');
        $this->account('zeina@example.org', 'Zeina Gerges', []);
        $this->account('adel@example.org', 'Adel Fahmy', ['disabled_at' => '2026-11-01 10:00:00+01'], 'servant');
        $this->account('hoda@example.org', 'Hoda Farag', [], 'parent');
        $nevine = $this->account('nevine@example.org', 'Nevine Boulos', []);
        $marina = $this->account('marina@example.org', 'Marina Tawfik', [], 'servant');
        $member = new Rosters(self::getContainer())->person('Nevine', 'Boulos');
        $memberToo = new Rosters(self::getContainer())->person('Marina', 'Tawfik');
        new MemberLinks(self::getContainer())->link($nevine, $member->id()->toRfc4122());
        new MemberLinks(self::getContainer())->link($marina, $memberToo->id()->toRfc4122());
        $this->signInNamed('admin@example.org', 'Sara Hanna', 'administrator');

        $this->jsonRequest('GET', '/api/staff-accounts');

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('today', '2026-11-08');

        self::assertSame(
            [
                ['Bishoy Adel',   false],
                ['Marina Tawfik', false],
                ['Sara Hanna',    false],
                ['Zeina Gerges',  false],
                ['Adel Fahmy',    true],
            ],
            array_map(static fn (array $item): array => [
                $item['displayName'] ?? null,
                $item['disabled'] ?? null,
            ], $this->items()),
        );

        $this->assertJsonPath('items.0.roles', [
            ['id' => '019a3f00-0000-7000-8000-000000000004', 'name' => 'Parent'],
            ['id' => self::SERVANT_ROLE, 'name' => 'Servant'],
        ]);

        $this->assertJsonPath('items.0.username', 'bishoy');
        $this->assertJsonPath('items.0.assignments', []);
        $this->assertJsonPath('items.3.roles', []);
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

    /**
     * @return list<array<array-key, mixed>>
     */
    private function items(): array
    {
        $items = JsonBody::asArray($this->responseJson()['items'] ?? null, 'items');

        return array_values(array_filter($items, is_array(...)));
    }
}
