<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Identity;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Domain\Entity\MemberLinks;
use App\Tests\Support\Context\Identity\Domain\Entity\RoleGrants;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Context\Organization\Domain\Entity\ServedGroups;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Database;
use App\Tests\Support\JsonBody;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class MemberServantsControllerTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testAMemberSeesTheServantsAssignedToItsGroupsLeadersFirst(): void
    {
        self::mockTime('2026-11-08T12:00:00+01:00');
        $sundaySchool = Structure::service('Sunday School');
        $third = Structure::stage($sundaySchool, '3rd Class', position: 2);
        $first = Structure::stage($sundaySchool, '1st Class', position: 0);
        $choir = Structure::service('Choir');
        $youth = Structure::service('Youth Meeting');
        new Database(self::getContainer())->persisted($sundaySchool, $third, $first, $choir, $youth);
        $rosters = new Rosters(self::getContainer());
        $kirollos = $rosters->person('Kirollos', 'Girgis');
        $rosters->place($kirollos, $sundaySchool, $third, '2026-09-01');
        $rosters->place($kirollos, $choir, null, '2026-09-01');
        $account = new SignInAccounts(self::getContainer())->add('kirollos@example.org');
        new MemberLinks(self::getContainer())->link($account, $kirollos->id()->toRfc4122());

        $sunday = $sundaySchool->id()->toRfc4122();
        $this->staff('Zeina Gerges', ['servant'], [$sunday, $third->id()->toRfc4122()]);
        $this->staff('Adel Fahmy', ['service_leader'], [$sunday, null]);
        $this->staff('Marina Tawfik', ['servant'], [$choir->id()->toRfc4122(), null]);
        $this->staff('Bishoy Adel', ['servant', 'administrator'], [$sunday, $third->id()->toRfc4122()]);
        $this->staff('Ramy Soliman', ['servant'], [$sunday, $first->id()->toRfc4122()]);
        $this->staff('Youssef Nashed', ['service_leader'], [$youth->id()->toRfc4122(), null]);
        $this->staff('Hany Wahba', ['administrator'], [$sunday, null]);

        $this->staff(
            'Irene Wahba',
            ['servant'],
            [$sunday, $third->id()->toRfc4122()],
            ['disabled_at' => '2026-11-01 12:00:00+01'],
        );

        $this->signInAs('kirollos@example.org', SignInAccounts::TYPED);

        $this->jsonRequest('GET', '/api/member/servants');

        self::assertResponseIsSuccessful();

        self::assertSame(
            [
                ['Adel Fahmy',    'service_leader'],
                ['Marina Tawfik', 'service_leader'],
                ['Bishoy Adel',   'servant'],
                ['Zeina Gerges',  'servant'],
            ],
            array_map(static fn (mixed $item): array => [
                JsonBody::asArray($item, 'item')['name'] ?? null,
                JsonBody::asArray($item, 'item')['role'] ?? null,
            ], $this->items()),
        );

        self::assertSame(['id', 'name', 'role'], array_keys(JsonBody::asArray($this->items()[0] ?? null, 'item')));
    }

    /**
     * @return array<array-key, mixed>
     */
    private function items(): array
    {
        return JsonBody::asArray($this->responseJson()['items'] ?? null, 'items');
    }

    /**
     * @param list<string> $roles
     * @param array{string, string|null} $group
     * @param array<string, string|null> $columns
     */
    private function staff(string $name, array $roles, array $group, array $columns = []): void
    {
        $email = strtolower(strtr($name, from: ' ', to: '.')) . '@example.org';
        $id = new SignInAccounts(self::getContainer())->add($email, ['display_name' => $name, ...$columns]);

        new RoleGrants(self::getContainer())->grant($id, ...$roles);
        new ServedGroups(self::getContainer())->assign($id, ...$group);
    }
}
