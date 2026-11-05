<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Attendance;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Organization\Domain\Entity\Service;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Meetings\Domain\Entity\Schedules;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use App\Tests\Support\JsonBody;
use App\Tests\Support\Logs;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class AttendanceOverviewControllerTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    private const string ACTIVE = "status = 'active', opened_at = created_at";

    public function testTodaysSessionsComeInTimeOrderWithCountsAndArchivedServicesAreLeftOut(): void
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
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest('GET', '/api/attendance/overview');

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('today', '2026-11-08');

        $this->assertJsonPath('sessions.0.title', 'Choir');
        $this->assertJsonPath('sessions.1.title', 'Class');
        $this->assertJsonPath('sessions.2.title', 'Youth');
        self::assertCount(3, JsonBody::asArray($this->responseJson()['sessions'] ?? null, 'sessions'));

        $this->assertJsonPath('sessions.1.counts.present', 0);
        $this->assertJsonPath('sessions.1.openedAt', '2026-11-08T12:08:00+01:00');
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
                "status = 'completed', opened_at = created_at, closed_at = '" . $closedAt . "'",
            );
        }

        $this->signInWithRoles('servant@example.org', 'servant');

        $this->jsonRequest('GET', '/api/attendance/overview');

        self::assertCount(2, JsonBody::asArray($this->responseJson()['earlier'] ?? null, 'earlier'));
        $this->assertJsonPath('earlier.0.occurrenceDate', '2026-10-25');
        $this->assertJsonPath('earlier.1.occurrenceDate', '2026-11-01');

        self::assertCount(6, JsonBody::asArray($this->responseJson()['closed'] ?? null, 'closed'));
        $this->assertJsonPath('closed.0.occurrenceDate', '2026-10-24');
        $this->assertJsonPath('closed.5.occurrenceDate', '2026-09-13');
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
        $this->signInWithRoles('servant@example.org', 'servant');

        $this->jsonRequest('GET', '/api/attendance/overview');

        self::assertCount(6, JsonBody::asArray($this->responseJson()['closed'] ?? null, 'closed'));
        $this->assertJsonPath('closed.0.occurrenceDate', '2026-10-11');
    }

    public function testThirtySessionsCostAsManyStatementsAsThree(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        $schedules = new Schedules(self::getContainer());
        $service = $this->service('Sunday School');

        foreach (range(start: 1, end: 3) as $number) {
            $schedules->session($schedules->weekly($service, 'Few ' . $number, 7), '2026-11-08');
        }

        $this->signInWithRoles('servant@example.org', 'servant');
        $this->jsonRequest('GET', '/api/attendance/overview');
        $few = count(new Logs(self::getContainer())->containing('SELECT'));

        foreach (range(start: 1, end: 27) as $number) {
            $schedules->session(
                $schedules->weekly($this->service('Service ' . $number), 'Many ' . $number, 7),
                '2026-11-08',
            );
        }

        $this->jsonRequest('GET', '/api/attendance/overview');

        self::assertCount(30, JsonBody::asArray($this->responseJson()['sessions'] ?? null, 'sessions'));
        self::assertCount($few, new Logs(self::getContainer())->containing('SELECT'));
    }

    public function testParentsAreRefusedAndAnonymousCallersUnauthenticated(): void
    {
        $this->jsonRequest('GET', '/api/attendance/overview');
        $this->assertProblem(401, 'auth.unauthenticated');

        $this->signInWithRoles('parent@example.org', 'parent');
        $this->jsonRequest('GET', '/api/attendance/overview');
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
            'meetings.meeting_schedule',
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
