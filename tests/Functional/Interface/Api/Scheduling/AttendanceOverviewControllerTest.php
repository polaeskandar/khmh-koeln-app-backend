<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Scheduling;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Scheduling\Domain\Entity\Attendance;
use App\Context\Scheduling\Domain\Entity\Schedule;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Organization\Domain\Entity\ServedGroups;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Context\Scheduling\Domain\Entity\Schedules;
use App\Tests\Support\Database;
use App\Tests\Support\JsonBody;
use App\Tests\Support\Logs;
use DateTimeImmutable;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class AttendanceOverviewControllerTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    private const string ACTIVE = "status = 'active', opened_at = created_at";

    public function testTodaysSessionsComeInTimeAndNameOrderAndArchivedOnesAreLeftOut(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        $schedules = new Schedules(self::getContainer());
        $school = $this->service('Sunday School');
        $youth = $this->service('Youth Meeting');
        $old = $this->service('Old Choir');
        $old->archive(Structure::now());
        new Database(self::getContainer())->persisted($old);
        $schedules->session($this->at($schedules->weekly($school, 'Class', 7), '12:00'), '2026-11-08', self::ACTIVE);
        $schedules->session($this->at($schedules->weekly($school, 'Choir', 7), '10:00'), '2026-11-08');
        $schedules->session($this->at($schedules->weekly($youth, 'Youth', 7), '18:00'), '2026-11-08');
        $schedules->session($schedules->weekly($old, 'Old', 7), '2026-11-08');
        $scouts = $this->service('Scout Group');
        $retired = Structure::stage($scouts, 'Retired');
        $retired->archive(Structure::now());
        new Database(self::getContainer())->persisted($retired);
        $schedules->session($schedules->weekly($scouts, 'Retired class', 7, stage: $retired), '2026-11-08');

        foreach (['Youth Choir', 'chor', 'Ökumene'] as $name) {
            $schedules->session($schedules->weekly($this->service($name), $name . ' evening', 7), '2026-11-08');
        }

        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest('GET', '/api/attendance/overview');

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('today', '2026-11-08');

        $this->assertJsonPath('sessions.0.session.title', 'Choir');
        $this->assertJsonPath('sessions.1.session.title', 'Class');
        $this->assertJsonPath('sessions.2.session.title', 'Youth');
        $this->assertJsonPath('sessions.3.session.title', 'chor evening');
        $this->assertJsonPath('sessions.4.session.title', 'Ökumene evening');
        $this->assertJsonPath('sessions.5.session.title', 'Youth Choir evening');
        self::assertCount(6, JsonBody::asArray($this->responseJson()['sessions'] ?? null, 'sessions'));

        $this->assertJsonPath('sessions.1.state.status', 'active');
        $this->assertJsonPath('sessions.1.state.openedAt', '2026-11-08T12:08:00+01:00');
        $this->assertJsonPath('openCount', 1);
    }

    public function testEarlierHoldsOpenSessionsOfThreeWeeksAndClosedTheSixNewest(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        $schedules = new Schedules(self::getContainer());
        $schedule = $schedules->weekly($this->service('Sunday School'), 'Class', 7);
        $schedules->session($schedule, '2026-11-01');
        $schedules->session($schedule, '2026-10-25', self::ACTIVE);
        $schedules->session($schedule, '2026-10-18', "status = 'canceled', cancel_reason = 'Holiday'");
        $schedules->session($schedule, '2026-10-11');
        $saturday = $schedules->weekly($this->service('Youth Meeting'), 'Youth', 6);
        $schedules->session($saturday, '2026-10-17');
        $schedules->session($schedules->weekly($this->service('Choir'), 'Choir', 7), '2026-10-18');
        $schedules->session($schedules->weekly($this->service('Scouts'), 'Scouts', 7), '2026-11-08');

        $closer = new ServedGroups(self::getContainer())->everyService($this->signInWithRoles(
            'servant@example.org',
            'servant',
        ));

        foreach ([
            '2026-09-06',
            '2026-09-13',
            '2026-09-20',
            '2026-09-27',
            '2026-10-04',
            '2026-10-17',
            '2026-10-24',
        ] as $index => $date) {
            $closedAt = '2026-10-' . (10 + $index) . ' 13:00:00+02';

            $schedules->session(
                $schedule,
                $date,
                "status = 'completed', opened_at = created_at, closed_at = '"
                . $closedAt
                . "', closed_by = '"
                . $closer
                . "'",
            );
        }

        $this->jsonRequest('GET', '/api/attendance/overview');

        self::assertCount(3, JsonBody::asArray($this->responseJson()['earlier'] ?? null, 'earlier'));
        $this->assertJsonPath('earlier.0.session.occurrenceDate', '2026-10-18');
        $this->assertJsonPath('earlier.1.session.occurrenceDate', '2026-10-25');
        $this->assertJsonPath('earlier.1.session.startsAt', '2026-10-25T18:30:00+01:00');
        $this->assertJsonPath('earlier.2.session.occurrenceDate', '2026-11-01');
        $this->assertJsonPath('closed.0.state.closedBy.id', $closer);

        self::assertCount(6, JsonBody::asArray($this->responseJson()['closed'] ?? null, 'closed'));
        $this->assertJsonPath('closed.0.session.occurrenceDate', '2026-10-24');
        $this->assertJsonPath('closed.5.session.occurrenceDate', '2026-09-13');
        $this->assertJsonPath('openCount', 1);
    }

    public function testClosedSessionsOfArchivedServicesTakeNoneOfTheSixPlaces(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        $schedules = new Schedules(self::getContainer());
        $class = $schedules->weekly($this->service('Sunday School'), 'Class', 7);
        $archived = Structure::service('Old Club');
        $archived->archive(Structure::now());
        new Database(self::getContainer())->persisted($archived);
        $club = $schedules->weekly($archived, 'Club', 6);

        foreach ([
            '2026-09-06',
            '2026-09-13',
            '2026-09-20',
            '2026-09-27',
            '2026-10-04',
            '2026-10-11',
        ] as $day => $date) {
            $schedules->session($class, $date, self::closedAt('2026-10-' . (10 + $day)));
        }

        $schedules->session($club, '2026-10-24', self::closedAt('2026-10-30'));
        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles('servant@example.org', 'servant'));

        $this->jsonRequest('GET', '/api/attendance/overview');

        self::assertCount(6, JsonBody::asArray($this->responseJson()['closed'] ?? null, 'closed'));
        $this->assertJsonPath('closed.0.session.occurrenceDate', '2026-10-11');
    }

    public function testThirtySessionsCostAsManyStatementsAsThree(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        $schedules = new Schedules(self::getContainer());
        $service = $this->service('Sunday School');

        foreach (range(start: 1, end: 3) as $number) {
            $schedules->session($schedules->weekly($service, 'Few ' . $number, 7), '2026-11-08');
        }

        $servant = new ServedGroups(self::getContainer())->everyService($this->signInWithRoles(
            'servant@example.org',
            'servant',
        ));

        $this->jsonRequest('GET', '/api/attendance/overview');
        $few = count(new Logs(self::getContainer())->containing('SELECT'));

        foreach (range(start: 1, end: 27) as $number) {
            $schedules->session(
                $schedules->weekly($this->service('Service ' . $number), 'Many ' . $number, 7),
                '2026-11-08',
            );
        }

        new ServedGroups(self::getContainer())->everyService($servant);

        $this->jsonRequest('GET', '/api/attendance/overview');

        self::assertCount(30, JsonBody::asArray($this->responseJson()['sessions'] ?? null, 'sessions'));
        self::assertCount($few, new Logs(self::getContainer())->containing('SELECT'));
    }

    public function testCountsComeForTheAskedSessionsInTheirOrder(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        $schedules = new Schedules(self::getContainer());
        $school = $this->service('Sunday School');
        $third = Structure::stage($school);
        new Database(self::getContainer())->persisted($third);

        $class = $schedules->session(
            $schedules->weekly($school, 'Class', 7, stage: $third),
            '2026-11-08',
            self::ACTIVE,
        );

        $choir = $schedules->session($schedules->weekly($school, 'Choir', 7), '2026-11-08', self::ACTIVE);
        $rosters = new Rosters(self::getContainer());
        $mina = $rosters->person('Mina');
        $rosters->place($mina, $school, $third, '2026-09-01');
        $rosters->place($rosters->person('Marina'), $school, $third, '2026-09-01');

        new Database(self::getContainer())->persisted(
            new Attendance(
                $schedules->managed($class),
                $mina->id(),
                new DateTimeImmutable('2026-11-08T12:00:00+01:00'),
                recordedBy: null,
            ),
        );

        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles('servant@example.org', 'servant'));

        $ids = [
            $choir->id()->toRfc4122(),
            Uuid::v7()->toRfc4122(),
            strtoupper($class->id()->toRfc4122()),
            $class->id()->toRfc4122(),
        ];

        $this->jsonRequest('GET', '/api/attendance/counts?' . http_build_query(['sessionIds' => $ids]));

        self::assertResponseIsSuccessful();
        self::assertCount(2, JsonBody::asArray($this->responseJson()['items'] ?? null, 'items'));
        $this->assertJsonPath('items.0.sessionId', $choir->id()->toRfc4122());
        $this->assertJsonPath('items.0.counts.rosterSize', 0);
        $this->assertJsonPath('items.1.sessionId', $class->id()->toRfc4122());

        $this->assertJsonPath('items.1.counts', [
            'present' => 1,
            'rosterSize' => 2,
            'rosterPresent' => 1,
            'notMarked' => 1,
            'notOnRoster' => 0,
        ]);
    }

    public function testCountsCheckTheIdsAndParentsAreRefused(): void
    {
        $this->jsonRequest('GET', '/api/attendance/overview');
        $this->assertProblem(401, 'auth.unauthenticated');

        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles('servant@example.org', 'servant'));
        $this->jsonRequest('GET', '/api/attendance/counts?' . http_build_query(['sessionIds' => ['not-a-uuid']]));
        $this->assertFieldError('sessionIds.0', 'validation.invalidType');

        $many = array_map(static fn (): string => Uuid::v7()->toRfc4122(), range(start: 1, end: 101));
        $this->jsonRequest('GET', '/api/attendance/counts?' . http_build_query(['sessionIds' => $many]));
        $this->assertFieldError('sessionIds', 'validation.tooMany', ['max' => 100]);

        $this->jsonRequest('GET', '/api/attendance/counts');
        $this->assertJsonPath('items', []);

        $this->signInWithRoles('parent@example.org', 'parent');
        $this->jsonRequest('GET', '/api/attendance/overview');
        $this->assertProblem(403, 'forbidden');
        $this->jsonRequest('GET', '/api/attendance/counts');
        $this->assertProblem(403, 'forbidden');
    }

    private static function closedAt(string $day): string
    {
        return "status = 'completed', opened_at = created_at, closed_at = '" . $day . " 13:00:00+02'";
    }

    private function service(string $name): Service
    {
        $service = Structure::service($name);
        new Database(self::getContainer())->persisted($service);

        return $service;
    }

    private function at(Schedule $schedule, string $startTime): Schedule
    {
        $database = new Database(self::getContainer());

        $database->connection()->update(
            'scheduling.schedule',
            ['start_time' => $startTime, 'end_time' => null],
            [
                'id' => $schedule->id()->toRfc4122(),
            ],
        );

        $database->entityManager()->clear();
        $reloaded = $database->entityManager()->find(Schedule::class, $schedule->id());
        self::assertInstanceOf(Schedule::class, $reloaded);

        return $reloaded;
    }
}
