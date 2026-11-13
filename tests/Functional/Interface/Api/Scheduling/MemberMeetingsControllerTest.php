<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Scheduling;

use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Context\Scheduling\Domain\Entity\Schedules;
use App\Tests\Support\Database;
use App\Tests\Support\Interface\Api\People\MemberGroupsFixture;
use App\Tests\Support\JsonBody;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class MemberMeetingsControllerTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testTheNextTwoWeeksIncludeCanceledMeetingsWithoutTheReason(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        new MemberGroupsFixture(self::getContainer())->nevine();
        $this->signInAs('nevine@example.org', SignInAccounts::TYPED);

        $this->jsonRequest('GET', '/api/member/meetings');

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('from', '2026-11-08');
        $this->assertJsonPath('to', '2026-11-21');

        self::assertSame(
            [
                '2026-11-09 18:00 Level 1 class Level 1 planned',
                '2026-11-11 18:30 Bible Study evening Bible Study canceled',
                '2026-11-16 18:00 Level 1 class Level 1 planned',
                '2026-11-18 18:30 Bible Study evening Bible Study planned',
            ],
            $this->meetings(),
        );

        self::assertStringNotContainsString('Retreat', (string) $this->browser()->getResponse()->getContent());

        self::assertSame(
            ['key', 'date', 'startTime', 'endTime', 'title', 'group', 'service', 'location', 'status'],
            array_keys(JsonBody::asArray(
                JsonBody::asArray($this->responseJson()['items'] ?? null, 'items')[0] ?? null,
                'item',
            )),
        );
    }

    public function testTheYouthGetsHisOwnGroupsAndNotTheOtherMinasClass(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        $school = Structure::service();
        $third = Structure::stage($school);
        $youth = Structure::service('Youth Meeting');
        $deacons = Structure::service('Deacons’ School');
        $advanced = Structure::stage($deacons, 'Advanced');
        new Database(self::getContainer())->persisted($school, $third, $youth, $deacons, $advanced);
        $schedules = new Schedules(self::getContainer());
        $schedules->weekly($school, '3rd Class', 7, stage: $third);
        $schedules->weekly($youth, 'Youth evening', 5);
        $schedules->weekly($deacons, 'Advanced class', 6, stage: $advanced);
        $rosters = new Rosters(self::getContainer());
        $rosters->place($rosters->person('Mina', 'Hanna'), $school, $third, '2026-09-01');

        new MemberGroupsFixture(self::getContainer())->member('mina@example.org', 'Mina', 'Hanna', [
            [$youth, null],
            [$deacons, $advanced],
        ]);

        $this->signInAs('mina@example.org', SignInAccounts::TYPED);

        $this->jsonRequest('GET', '/api/member/groups');
        $this->assertJsonPath('items.0.service.name', 'Deacons’ School');
        $this->assertJsonPath('items.1.service.name', 'Youth Meeting');
        $this->jsonRequest('GET', '/api/member/meetings');

        self::assertSame(
            [
                '2026-11-13 18:30 Youth evening Youth Meeting planned',
                '2026-11-14 18:30 Advanced class Advanced planned',
                '2026-11-20 18:30 Youth evening Youth Meeting planned',
                '2026-11-21 18:30 Advanced class Advanced planned',
            ],
            $this->meetings(),
        );
    }

    /**
     * @return list<string>
     */
    private function meetings(): array
    {
        return array_values(array_map(
            static function (mixed $raw): string {
                $item = JsonBody::asArray($raw, 'item');
                $parts = array_map(static fn (string $key): string => Type::of($item[$key] ?? null)->toString(), [
                    'date',
                    'startTime',
                    'title',
                    'group',
                    'status',
                ]);

                return implode(' ', $parts);
            },
            JsonBody::asArray($this->responseJson()['items'] ?? null, 'items'),
        ));
    }
}
