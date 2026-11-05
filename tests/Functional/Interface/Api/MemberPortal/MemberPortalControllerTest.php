<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\MemberPortal;

use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use App\Tests\Support\Interface\Api\MemberPortal\MemberGroupsFixture;
use App\Tests\Support\JsonBody;
use App\Tests\Support\Logs;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class MemberPortalControllerTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testAMemberGetsTheirGroupsWithMeetingTimesServantsAndTheLastLesson(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        new MemberGroupsFixture(self::getContainer())->nevine();
        $this->signInAs('nevine@example.org', SignInAccounts::TYPED);

        $this->jsonRequest('GET', '/api/member/groups');

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('today', '2026-11-08');
        self::assertSame(['Bible Study', 'Coptic Language Course'], $this->column('items', 'service', 'name'));
        $this->assertJsonPath('items.0.stage', null);
        $this->assertJsonPath('items.1.stage.name', 'Level 1');
        $this->assertJsonPath('items.1.since', '2026-09-01');
        $this->assertJsonPath('items.1.schedules.0.name', 'Level 1 class');
        $this->assertJsonPath('items.1.schedules.0.startTime', '18:00');
        $this->assertJsonPath('items.1.schedules.0.recurrence.weekdays', [1]);

        $this->assertJsonPath('items.1.lastLesson', [
            'title' => 'The first eight letters',
            'teachingDate' => '2026-11-02',
            'passage' => 'Psalm 1',
            'memoryVerse' => 'Christ is risen.',
        ]);

        $this->assertJsonPath('items.0.lastLesson', null);

        self::assertSame(
            ['id', 'service', 'stage', 'since', 'schedules', 'lastLesson'],
            array_keys(JsonBody::asArray(
                JsonBody::asArray($this->responseJson()['items'] ?? null, 'items')[1] ?? null,
                'item',
            )),
        );

        $this->jsonRequest('GET', '/api/member/servants');
        self::assertResponseIsSuccessful();
        $this->assertJsonPath('items.0.name', 'Irene Wahba');
        $this->assertJsonPath('items.0.role', 'service_leader');
        $this->assertJsonPath('items.1.name', 'Youssef Nashed');
    }

    public function testArchivedStagesDropOutAnInactiveRecordHasNoGroupsAndOthersAreRefused(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        $nevine = new MemberGroupsFixture(self::getContainer())->nevine();
        $connection = new Database(self::getContainer())->connection();
        $connection->executeStatement("UPDATE organization.stage SET archived_at = now() WHERE name = 'Level 1'");
        $this->signInAs('nevine@example.org', SignInAccounts::TYPED);

        $this->jsonRequest('GET', '/api/member/groups');
        self::assertSame(['Bible Study'], $this->column('items', 'service', 'name'));

        $connection->update('people.person', ['status' => 'inactive'], ['id' => $nevine->id()->toRfc4122()]);
        $this->jsonRequest('GET', '/api/member/groups');
        $this->assertJsonPath('items', []);

        $this->browser()->getCookieJar()->clear();
        $this->jsonRequest('GET', '/api/member/groups');
        $this->assertProblem(401, 'auth.unauthenticated');
        $this->signInWithRoles('daniel@example.org', 'servant');
        $this->jsonRequest('GET', '/api/member/groups');
        $this->assertProblem(403, 'forbidden');
    }

    public function testMoreGroupsCostNoMoreStatements(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        $fixture = new MemberGroupsFixture(self::getContainer());
        $choir = Structure::service('Choir');
        $singers = Structure::stage($choir, 'Singers');
        new Database(self::getContainer())->persisted($choir, $singers);
        $fixture->member('mina@example.org', 'Mina', 'Hanna', [[$choir, $singers]]);
        $this->signInAs('mina@example.org', SignInAccounts::TYPED);
        $this->jsonRequest('GET', '/api/member/groups');
        $one = \count(new Logs(self::getContainer())->containing('SELECT'));

        $groups = [];

        foreach (['Deacons’ School', 'Youth Meeting', 'Hymns', 'Scouts'] as $name) {
            $service = Structure::service($name);
            $stage = Structure::stage($service, $name . ' group');
            new Database(self::getContainer())->persisted($service, $stage);
            $groups[] = [$service, $stage];
        }

        $fixture->member('peter@example.org', 'Peter', 'Botros', [[$choir, $singers], ...$groups]);
        $this->browser()->getCookieJar()->clear();
        $this->signInAs('peter@example.org', SignInAccounts::TYPED);
        $this->jsonRequest('GET', '/api/member/groups');

        self::assertCount(5, JsonBody::asArray($this->responseJson()['items'] ?? null, 'items'));
        self::assertCount($one, new Logs(self::getContainer())->containing('SELECT'));
    }

    /**
     * @return list<string>
     */
    private function column(string $list, string $object, string $key): array
    {
        return array_values(array_map(
            static fn (mixed $item): string => Type::of(
                JsonBody::asArray(JsonBody::asArray($item, 'item')[$object] ?? null, $object)[$key] ?? null,
            )->toString(),
            JsonBody::asArray($this->responseJson()[$list] ?? null, $list),
        ));
    }
}
