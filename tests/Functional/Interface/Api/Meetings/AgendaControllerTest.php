<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Meetings;

use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Domain\Entity\Service;
use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Domain\Entity\RoleGrants;
use App\Tests\Support\Context\Meetings\Domain\Entity\Schedules;
use App\Tests\Support\Context\Organization\Domain\Entity\ServedGroups;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use App\Tests\Support\Interface\Api\Meetings\ScheduleRows;
use App\Tests\Support\JsonBody;
use App\Tests\Support\Logs;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class AgendaControllerTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    private const string FRIDAYS = '/api/agenda?from=2026-10-25&to=2026-11-28';

    public function testSessionsReplaceTheirDatesAndOnlyGeneratingSchedulesPlanFromToday(): void
    {
        self::mockTime('2026-11-09T10:00:00+01:00');
        $schedules = new Schedules(self::getContainer());
        $friday = $schedules->weekly($this->service('Sunday School'), 'Friday class', 5);

        $schedules->session(
            $friday,
            '2026-11-06',
            "status = 'completed', opened_at = created_at, closed_at = created_at",
        );

        $schedules->session($friday, '2026-11-13');
        $schedules->session($friday, '2026-11-20', "status = 'canceled', cancel_reason = 'Holiday'");

        $rows = new ScheduleRows(self::getContainer());
        $rows->deactivate($schedules->weekly($this->service('Choir'), 'Paused', 5)->id()->toRfc4122());
        $old = $this->service('Old Choir');
        $schedules->weekly($old, 'Archived', 5);
        $rows->archive('organization.service', $old->id());

        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles(
            'leader@example.org',
            'service_leader',
        ));

        $this->jsonRequest('GET', self::FRIDAYS);

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('today', '2026-11-09');
        $this->assertJsonPath('from', '2026-10-25');
        $this->assertJsonPath('to', '2026-11-28');

        self::assertSame(
            [
                'session 2026-11-06 completed',
                'session 2026-11-13 scheduled',
                'session 2026-11-20 canceled',
                'planned 2026-11-27 Friday class',
            ],
            $this->items(),
        );

        $this->assertJsonPath('items.2.cancelReason', 'Holiday');
        $this->assertJsonPath('items.3.service.name', 'Sunday School');
        $this->assertJsonPath('items.3.startsAt', '2026-11-27T18:30:00+01:00');
        $this->assertJsonPath('items.3.rosterMode', 'expected');

        self::assertArrayNotHasKey('can', JsonBody::asArray(
            JsonBody::valueAt($this->responseJson(), ['items', '3'], 'items.3'),
            'items.3',
        ));
    }

    public function testWithoutAttendanceAccessCancelReasonsAreHiddenAndItemsComeByStartThenTitle(): void
    {
        self::mockTime('2026-11-09T10:00:00+01:00');
        $schedules = new Schedules(self::getContainer());
        $service = $this->service('Sunday School');

        $schedules->session(
            $schedules->weekly($service, 'Zacharias', 5),
            '2026-11-13',
            "status = 'canceled', cancel_reason = 'Holiday'",
        );

        $schedules->weekly($service, 'Ägypten', 5);
        $schedules->weekly($service, 'Bible', 5);
        new RoleGrants(self::getContainer())->createRole('Viewer', Action::Read, Resource::Services);
        $this->signInWithRoles('viewer@example.org', 'viewer');

        $this->jsonRequest('GET', '/api/agenda?from=2026-11-13&to=2026-11-13');

        self::assertSame(
            ['planned 2026-11-13 Ägypten', 'planned 2026-11-13 Bible', 'session 2026-11-13 canceled'],
            $this->items(),
        );

        $this->assertJsonPath('items.2.cancelReason', null);
    }

    public function testTheLastDayIsIncludedAcrossTheSummerTimeChange(): void
    {
        self::mockTime('2027-03-22T10:00:00+01:00');
        $schedules = new Schedules(self::getContainer());
        $service = $this->service('Sunday School');
        $schedules->session($schedules->weekly($service, 'Sunday class', 7), '2027-03-28');
        $schedules->session($schedules->weekly($service, 'Monday class', 1), '2027-03-29');
        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles('servant@example.org', 'servant'));

        $this->jsonRequest('GET', '/api/agenda?from=2027-03-23&to=2027-03-28');

        self::assertSame(['session 2027-03-28 scheduled'], $this->items());
        $this->assertJsonPath('items.0.startsAt', '2027-03-28T18:30:00+02:00');
    }

    public function testTheDefaultRangeAndTheFilters(): void
    {
        self::mockTime('2026-11-09T10:00:00+01:00');
        $schedules = new Schedules(self::getContainer());
        $school = $this->service('Sunday School');
        $third = Structure::stage($school);
        new Database(self::getContainer())->persisted($third);
        $schedules->weekly($school, 'Third class', 5, stage: $third);
        $schedules->weekly($school, 'Choir', 5);
        $youth = $schedules->weekly($this->service('Youth Meeting'), 'Youth', 5);
        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles('servant@example.org', 'servant'));

        $this->jsonRequest('GET', '/api/agenda');
        $this->assertJsonPath('from', '2026-11-03');
        $this->assertJsonPath('to', '2026-11-16');
        self::assertCount(3, $this->items());

        $this->jsonRequest('GET', '/api/agenda?serviceId=' . $school->id()->toRfc4122());
        self::assertSame(['planned 2026-11-13 Choir', 'planned 2026-11-13 Third class'], $this->items());

        $this->jsonRequest('GET', '/api/agenda?stageId=' . $third->id()->toRfc4122());
        self::assertSame(['planned 2026-11-13 Third class'], $this->items());

        $this->jsonRequest('GET', '/api/agenda?scheduleId=' . $youth->id()->toRfc4122());
        self::assertSame(['planned 2026-11-13 Youth'], $this->items());
    }

    /**
     * @param array<string, string|int> $params
     */
    #[DataProvider('invalidQueries')]
    public function testInvalidQueriesHaveTheirFieldCodes(
        string $query,
        string $field,
        string $code,
        array $params = [],
    ): void {
        self::mockTime('2026-11-09T10:00:00+01:00');
        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles('servant@example.org', 'servant'));

        $this->jsonRequest('GET', '/api/agenda?' . $query);

        self::assertResponseStatusCodeSame(422);
        $this->assertFieldError($field, $code, $params);
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string, 3?: array<string, string|int>}>
     */
    public static function invalidQueries(): iterable
    {
        yield 'end before start' => ['from=2026-11-10&to=2026-11-09', 'to', 'validation.invalidRange', ['max' => 120]];
        yield 'over 120 days' => ['from=2026-11-01&to=2027-03-02', 'to', 'validation.invalidRange', ['max' => 120]];
        yield 'malformed date' => ['from=9.11.2026', 'from', 'validation.invalidDate'];
        yield 'malformed id' => ['serviceId=school', 'serviceId', 'validation.invalidType'];
        yield 'empty date' => ['from=', 'from', 'validation.invalidDate'];
        yield 'empty id' => ['scheduleId=', 'scheduleId', 'validation.invalidType'];
    }

    public function testSixteenSchedulesCostAsManyStatementsAsTwo(): void
    {
        self::mockTime('2026-11-09T10:00:00+01:00');
        $schedules = new Schedules(self::getContainer());

        foreach (range(start: 1, end: 2) as $number) {
            $schedules->session($schedules->weekly($this->service('Few ' . $number), 'Few', 1), '2026-11-09');
        }

        $servant = new ServedGroups(self::getContainer())->everyService($this->signInWithRoles(
            'servant@example.org',
            'servant',
        ));

        $this->jsonRequest('GET', '/api/agenda?from=2026-11-03&to=2026-11-16');
        $few = count(new Logs(self::getContainer())->containing('SELECT'));

        foreach (range(start: 1, end: 14) as $number) {
            $schedules->session($schedules->weekly($this->service('Many ' . $number), 'Many', 1), '2026-11-09');
        }

        new ServedGroups(self::getContainer())->everyService($servant);

        $this->jsonRequest('GET', '/api/agenda?from=2026-11-03&to=2026-11-16');

        self::assertCount(32, $this->items());
        self::assertCount($few, new Logs(self::getContainer())->containing('SELECT'));
    }

    public function testParentsAreRefusedAndAnonymousCallersUnauthenticated(): void
    {
        $this->jsonRequest('GET', '/api/agenda');
        $this->assertProblem(401, 'auth.unauthenticated');

        $this->signInWithRoles('parent@example.org', 'parent');
        $this->jsonRequest('GET', '/api/agenda');
        $this->assertProblem(403, 'forbidden');
    }

    /**
     * @return list<string>
     */
    private function items(): array
    {
        $items = JsonBody::asArray($this->responseJson()['items'] ?? null, 'items');

        return array_values(array_map(static function (mixed $raw): string {
            $item = JsonBody::asArray($raw, 'item');
            $kind = Type::of($item['kind'] ?? null)->toString();
            $last = Type::of($kind === 'session' ? $item['status'] ?? null : $item['title'] ?? null)->toString();

            return $kind . ' ' . Type::of($item['date'] ?? null)->toString() . ' ' . $last;
        }, $items));
    }

    private function service(string $name): Service
    {
        $service = Structure::service($name);
        new Database(self::getContainer())->persisted($service);

        return $service;
    }
}
