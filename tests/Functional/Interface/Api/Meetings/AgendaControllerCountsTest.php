<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Meetings;

use App\Context\Meetings\Domain\Entity\AttendanceMark;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\ValueObject\Removal;
use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Meetings\Domain\Entity\Schedules;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Database;
use App\Tests\Support\JsonBody;
use App\Tests\Support\Logs;
use DateTimeImmutable;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class AgendaControllerCountsTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    private const string ACTIVE = "status = 'active', opened_at = created_at";

    private const string DONE = "status = 'completed', opened_at = created_at, closed_at = created_at";

    private const array COUNTS = [
        'present' => 8,
        'rosterSize' => 14,
        'rosterPresent' => 7,
        'notMarked' => 7,
        'notOnRoster' => 1,
    ];

    public function testActiveAndCompletedSessionsCarryTheBoardsCounts(): void
    {
        self::mockTime('2026-11-08T12:00:00+01:00');
        [$service, $third] = $this->structure();
        $session = $this->classWithMarks($service, $third);
        $schedules = new Schedules(self::getContainer());
        $open = $schedules->weekly($service, 'Open evening', 6);

        new Database(self::getContainer())
            ->connection()
            ->update('meetings.meeting_schedule', ['roster_mode' => 'open'], ['id' => $open->id()->toRfc4122()]);

        $schedules->session($open, '2026-11-07', self::DONE . ", roster_mode = 'open'");
        $schedules->session($schedules->weekly($service, 'Choir', 1), '2026-11-09');
        $schedules->session($schedules->weekly($service, 'Youth', 2), '2026-11-10', "status = 'canceled'");
        $this->signInWithRoles('leader@example.org', 'service_leader');

        $this->jsonRequest('GET', '/api/agenda?from=2026-11-07&to=2026-11-10');

        self::assertResponseIsSuccessful();

        self::assertSame(
            [
                'Choir' => null,
                'Open evening' => [
                    'present' => 0,
                    'rosterSize' => null,
                    'rosterPresent' => 0,
                    'notMarked' => 0,
                    'notOnRoster' => 0,
                ],
                'Sunday class' => self::COUNTS,
                'Youth' => null,
            ],
            $this->countsByTitle(),
        );

        $this->jsonRequest('GET', '/api/sessions/' . $session->id()->toRfc4122() . '/attendance');
        $this->assertJsonPath('counts', self::COUNTS);
    }

    public function testMoreSchedulesAndDaysCostNoMoreStatements(): void
    {
        self::mockTime('2026-11-08T12:00:00+01:00');
        [$service, $third] = $this->structure();
        $this->classWithMarks($service, $third);
        $this->signInWithRoles('leader@example.org', 'service_leader');
        $this->jsonRequest('GET', '/api/agenda?from=2026-11-08&to=2026-11-08');
        $few = \count(new Logs(self::getContainer())->containing('SELECT'));

        $schedules = new Schedules(self::getContainer());

        foreach (range(start: 1, end: 15) as $n) {
            $schedule = $schedules->weekly($service, 'Meeting ' . $n, 1 + ($n % 7));

            foreach (['2026-11-02', '2026-11-03', '2026-11-04'] as $date) {
                $schedules->session($schedule, $date, self::DONE);
            }
        }

        $this->jsonRequest('GET', '/api/agenda?from=2026-11-01&to=2026-11-14');
        self::assertResponseIsSuccessful();
        self::assertCount($few, new Logs(self::getContainer())->containing('SELECT'));
    }

    /**
     * @return array<string, mixed>
     */
    private function countsByTitle(): array
    {
        $items = array_map(
            static fn (mixed $item): array => JsonBody::asArray($item, 'item'),
            JsonBody::asArray($this->responseJson()['items'] ?? null, 'items'),
        );

        $counts = array_column($items, column_key: 'counts', index_key: 'title');
        ksort($counts);

        return array_combine(array_map(strval(...), array_keys($counts)), $counts);
    }

    private function classWithMarks(Service $service, Stage $third): Session
    {
        $schedules = new Schedules(self::getContainer());

        $session = $schedules->session(
            $schedules->weekly($service, 'Sunday class', 7, stage: $third),
            '2026-11-08',
            self::ACTIVE,
        );

        $rosters = new Rosters(self::getContainer());
        $managed = new Schedules(self::getContainer())->managed($session);
        $at = new DateTimeImmutable('2026-11-08T10:30:00+01:00');
        $marks = [];

        foreach (range(start: 1, end: 14) as $n) {
            $member = $rosters->person('Member', 'Number ' . $n);
            $rosters->place($member, $service, $third, '2026-09-01');

            if ($n <= 8) {
                $marks[] = new AttendanceMark($managed, $member->id(), $at, recordedBy: null);
            }
        }

        $removed = end($marks);
        self::assertInstanceOf(AttendanceMark::class, $removed);
        $removed->remove(Removal::of($at, by: null, reason: 'Wrong child'));
        $marks[] = new AttendanceMark($managed, $rosters->person('Visiting', 'Cousin')->id(), $at, recordedBy: null);
        new Database(self::getContainer())->persisted(...$marks);

        return $session;
    }

    /**
     * @return array{Service, Stage}
     */
    private function structure(): array
    {
        $service = Structure::service();
        $third = Structure::stage($service);
        new Database(self::getContainer())->persisted($service, $third);

        return [$service, $third];
    }
}
