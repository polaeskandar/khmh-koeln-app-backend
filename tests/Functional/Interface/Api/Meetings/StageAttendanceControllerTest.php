<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Meetings;

use App\Context\Meetings\Domain\Entity\AttendanceMark;
use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Domain\Entity\Person;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Meetings\Domain\Entity\Schedules;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Database;
use App\Tests\Support\JsonBody;
use App\Tests\Support\Logs;
use DateTimeImmutable;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class StageAttendanceControllerTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    private const string DONE = "status = 'completed', opened_at = created_at, closed_at = created_at";

    private const array SUNDAYS = [
        '2026-08-30',
        '2026-09-06',
        '2026-09-13',
        '2026-09-20',
        '2026-09-27',
        '2026-10-04',
        '2026-10-11',
        '2026-10-18',
        '2026-10-25',
        '2026-11-01',
    ];

    public function testTheEightLatestClosedSessionsAgainstTodaysMembers(): void
    {
        self::mockTime('2026-11-08T12:00:00+01:00');
        [$third, $lastSession, $mina, $kiro] = $this->history();
        $this->signInWithRoles('leader@example.org', 'service_leader');

        $this->jsonRequest('GET', '/api/stages/' . $third->id()->toRfc4122() . '/attendance-history');

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('today', '2026-11-08');
        $this->assertJsonPath('sessions.0.date', '2026-09-13');
        $this->assertJsonPath('sessions.7.date', '2026-11-01');
        $this->assertJsonPath('sessions.7.id', $lastSession);
        $this->assertJsonPath('sessions.7.startsAt', '2026-11-01T18:30:00+01:00');
        $this->assertJsonPath('sessions.7.present', 2);
        $this->assertJsonPath('sessions.3.present', 1);
        $this->assertJsonPath('rows.0.person.id', $mina->id()->toRfc4122());

        $this->assertJsonPath('rows.0.cells', [
            ...array_fill(start_index: 0, count: 6, value: 'notMarked'),
            'present',
            'present',
        ]);

        $this->assertJsonPath('rows.0.presentCount', 2);
        $this->assertJsonPath('rows.1.person.id', $kiro->id()->toRfc4122());

        $this->assertJsonPath('rows.1.cells', [
            'notOnRoster',
            'notOnRoster',
            'notOnRoster',
            'present',
            'notOnRoster',
            'notMarked',
            'notMarked',
            'notMarked',
        ]);

        $this->assertJsonPath('rows.1.presentCount', 1);
        self::assertCount(2, JsonBody::asArray($this->responseJson()['rows'] ?? null, 'rows'));
    }

    public function testMoreMembersAndSessionsCostNoMoreStatements(): void
    {
        self::mockTime('2026-11-08T12:00:00+01:00');
        [$third, , , , $service, $schedule] = $this->history();
        $this->signInWithRoles('servant@example.org', 'servant');
        $path = '/api/stages/' . $third->id()->toRfc4122() . '/attendance-history';

        $this->jsonRequest('GET', $path);
        self::assertResponseIsSuccessful();
        $few = \count(new Logs(self::getContainer())->containing('SELECT'));

        $rosters = new Rosters(self::getContainer());

        foreach (range(start: 1, end: 5) as $n) {
            $rosters->place($rosters->person('Person', 'Number ' . $n), $service, $third, '2026-09-01');
        }

        $fresh = new Database(self::getContainer())
            ->entityManager()
            ->find(Schedule::class, $schedule->id());

        self::assertInstanceOf(Schedule::class, $fresh);
        new Schedules(self::getContainer())->session($fresh, '2026-11-08', self::DONE);

        $this->jsonRequest('GET', $path);
        self::assertCount($few, new Logs(self::getContainer())->containing('SELECT'));
    }

    public function testParentsAndUnknownStagesAreRefused(): void
    {
        [$third] = $this->history();
        $this->signInWithRoles('parent@example.org', 'parent');
        $this->jsonRequest('GET', '/api/stages/' . $third->id()->toRfc4122() . '/attendance-history');
        $this->assertProblem(403, 'forbidden');

        $this->browser()->getCookieJar()->clear();
        $this->signInWithRoles('servant@example.org', 'servant');
        $this->jsonRequest('GET', '/api/stages/' . Uuid::v7()->toRfc4122() . '/attendance-history');
        $this->assertProblem(404, 'notFound.stage');
        $this->jsonRequest('GET', '/api/stages/not-a-stage/attendance-history');
        $this->assertProblem(404, 'notFound.stage');
    }

    public function testSameDayMeetingsComeInTheOrderTheyStart(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $service = Structure::service();
        $third = Structure::stage($service);
        new Database(self::getContainer())->persisted($service, $third);
        $schedules = new Schedules(self::getContainer());

        $evening = $schedules->session(
            $schedules->weekly($service, 'Evening', 7, stage: $third),
            '2026-11-01',
            self::DONE,
        );

        $early = $schedules->weekly($service, 'Morning', 7, stage: $third);

        new Database(self::getContainer())
            ->connection()
            ->update('meetings.meeting_schedule', ['start_time' => '09:00:00'], ['id' => $early->id()->toRfc4122()]);

        $morning = $schedules->session($early, '2026-11-01', self::DONE);
        $this->signInWithRoles('servant@example.org', 'servant');

        $this->jsonRequest('GET', '/api/stages/' . $third->id()->toRfc4122() . '/attendance-history');

        $this->assertJsonPath('sessions.0.id', $morning->id()->toRfc4122());
        $this->assertJsonPath('sessions.1.id', $evening->id()->toRfc4122());
    }

    /**
     * @return array{Stage, string, Person, Person, Service, Schedule}
     */
    private function history(): array
    {
        $service = Structure::service();
        $third = Structure::stage($service);
        $second = Structure::stage($service, '2nd Class', position: 1);
        new Database(self::getContainer())->persisted($service, $third, $second);
        $schedules = new Schedules(self::getContainer());
        $class = $schedules->weekly($service, 'Sunday class', 7, stage: $third);
        $other = $schedules->weekly($service, 'Second class', 7, stage: $second);
        $sessions = [];

        foreach (self::SUNDAYS as $date) {
            $sessions[$date] = $schedules->session($class, $date, self::DONE);
            $schedules->session($other, $date, self::DONE);
        }

        $schedules->session($class, '2026-10-30', "status = 'canceled'");
        $schedules->session($class, '2026-11-05', "status = 'active', opened_at = created_at");
        $schedules->session($class, '2026-11-15');
        $rosters = new Rosters(self::getContainer());
        $mina = $rosters->person('Mina', 'Hanna');
        $rosters->place($mina, $service, $third, '2026-09-01');
        $kiro = $rosters->person('Kiro', 'Nashed');
        $rosters->place($kiro, $service, $third, '2026-10-15');
        $this->mark($sessions['2026-10-25'], $mina);
        $this->mark($sessions['2026-11-01'], $mina);
        $this->mark($sessions['2026-11-01'], $rosters->person('Bishoy', 'Adel'));
        $this->mark($sessions['2026-10-04'], $kiro);

        return [$third, $sessions['2026-11-01']->id()->toRfc4122(), $mina, $kiro, $service, $class];
    }

    private function mark(Session $session, Person $person): void
    {
        new Database(self::getContainer())->persisted(
            new AttendanceMark(
                new Schedules(self::getContainer())->managed($session),
                $person->id(),
                new DateTimeImmutable($session->occurrenceDate()->toString() . 'T18:45:00+01:00'),
                recordedBy: null,
            ),
        );
    }
}
