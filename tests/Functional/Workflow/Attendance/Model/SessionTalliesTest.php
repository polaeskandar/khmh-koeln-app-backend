<?php

declare(strict_types=1);

namespace App\Tests\Functional\Workflow\Attendance\Model;

use App\Context\Attendance\Application\Service\AttendanceMarks;
use App\Context\Attendance\Domain\Entity\Attendance;
use App\Context\Attendance\Domain\ValueObject\Removal;
use App\Context\Attendance\Infrastructure\Persistence\Repository\DoctrineAttendanceRepository;
use App\Context\Meetings\Application\Service\MeetingLookup;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\People\Application\Service\Rosters as PeopleRosters;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Repository\RosterRepositoryInterface;
use App\Context\People\Domain\ValueObject\RosterKey;
use App\Tests\Support\Context\Meetings\Domain\Entity\Schedules;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Database;
use App\Tests\Support\Logs;
use App\Workflow\Attendance\Model\SessionTallies;
use App\Workflow\Attendance\Model\SessionTally;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class SessionTalliesTest extends KernelTestCase
{
    private const string SUNDAY = '2026-11-08';

    public function testEachSessionGetsItsOwnRosterOnItsDate(): void
    {
        $service = Structure::service();
        $third = Structure::stage($service);
        new Database(self::getContainer())->persisted($service, $third);
        $rosters = new Rosters(self::getContainer());
        $starting = $rosters->person('Mina');
        $ending = $rosters->person('Marina');
        $gone = $rosters->person('Bishoy');
        $adult = $rosters->person('Kirollos');
        $rosters->place($starting, $service, $third, self::SUNDAY);
        $rosters->place($ending, $service, $third, '2026-09-01', self::SUNDAY);
        $rosters->place($gone, $service, $third, '2026-09-01', '2026-11-07');
        $rosters->place($adult, $service, null, '2026-09-01');

        new Database(self::getContainer())
            ->connection()
            ->update('people.person', ['status' => 'inactive'], ['id' => $ending->id()->toRfc4122()]);

        $schedules = new Schedules(self::getContainer());
        $class = $schedules->session($schedules->weekly($service, 'Class', 7, stage: $third), self::SUNDAY);
        $hall = $schedules->session($schedules->weekly($service, 'Service', 7), self::SUNDAY);

        $tallies = $this->tallies(new ArrayCollection([$class, $hall]));

        self::assertEqualsCanonicalizing(
            self::idsOf($starting, $ending),
            $tallies->get($class->id()->toRfc4122())?->rosterIds,
        );

        self::assertSame(self::idsOf($adult), $tallies->get($hall->id()->toRfc4122())?->rosterIds);
    }

    public function testCountsLeaveRemovedMarksOutAndAddVisitors(): void
    {
        $service = Structure::service();
        new Database(self::getContainer())->persisted($service);
        $rosters = new Rosters(self::getContainer());
        $member = $rosters->person('Mina');
        $other = $rosters->person('Marina');
        $visitor = $rosters->person('Bishoy');
        $rosters->place($member, $service, null, '2026-09-01');
        $rosters->place($other, $service, null, '2026-09-01');
        $schedules = new Schedules(self::getContainer());
        $session = $schedules->session($schedules->weekly($service, 'Service', 7), self::SUNDAY);
        $openSchedule = $schedules->weekly($service, 'Open evening', 5);
        $open = $schedules->session($openSchedule, '2026-11-06');

        new Database(self::getContainer())
            ->connection()
            ->update(
                'meetings.meeting_schedule',
                ['roster_mode' => 'open'],
                ['id' => $openSchedule->id()->toRfc4122()],
            );

        $removed = new Attendance($session->id(), $other->id(), new DateTimeImmutable(), recordedBy: null);
        $removed->remove(Removal::of(new DateTimeImmutable(), by: null, reason: null));

        new Database(self::getContainer())->persisted(
            new Attendance($session->id(), $member->id(), new DateTimeImmutable(), recordedBy: null),
            new Attendance($session->id(), $visitor->id(), new DateTimeImmutable(), recordedBy: null),
            new Attendance($open->id(), $other->id(), new DateTimeImmutable(), recordedBy: null),
            $removed,
        );

        $tallies = $this->tallies(new ArrayCollection($this->reloaded([$session, $open])));
        $counts = $tallies->get($session->id()->toRfc4122())?->counts;
        $openCounts = $tallies->get($open->id()->toRfc4122())?->counts;

        self::assertSame([2, 2, 1, 1, 1], [
            $counts?->present,
            $counts?->rosterSize,
            $counts?->rosterPresent,
            $counts?->notMarked,
            $counts?->notOnRoster,
        ]);

        self::assertSame([1, null, 0], [$openCounts?->present, $openCounts?->rosterSize, $openCounts?->notMarked]);
        self::assertSame(self::idsOf($other), $tallies->get($open->id()->toRfc4122())?->presentIds);
    }

    public function testThirtySessionsTakeAsManyStatementsAsOneAndMatchTheSingleRoster(): void
    {
        $service = Structure::service();
        $third = Structure::stage($service);
        new Database(self::getContainer())->persisted($service, $third);
        $rosters = new Rosters(self::getContainer());
        $rosters->place($rosters->person('Mina'), $service, $third, '2026-09-01');
        $schedules = new Schedules(self::getContainer());
        $created = [];

        foreach ([5, 6, 7] as $weekday) {
            $schedule = $schedules->weekly($service, 'Class ' . $weekday, $weekday, stage: $third);

            foreach (range(start: 0, end: 9) as $week) {
                $created[] = $schedules->session(
                    $schedule,
                    new DateTimeImmutable('2026-09-07')
                        ->modify('+' . $week . ' weeks +' . ($weekday - 1) . ' days')
                        ->format('Y-m-d'),
                );
            }
        }

        $one = $this->reloaded([$created[0]]);
        $before = count(new Logs(self::getContainer())->containing('SELECT'));
        $this->tallies(new ArrayCollection($one));
        $forOne = count(new Logs(self::getContainer())->containing('SELECT')) - $before;

        $sessions = $this->reloaded($created);
        $before = count(new Logs(self::getContainer())->containing('SELECT'));

        $tallies = $this->tallies(new ArrayCollection($sessions));

        self::assertSame($forOne, count(new Logs(self::getContainer())->containing('SELECT')) - $before);
        self::assertLessThanOrEqual(3, $forOne);

        foreach ($sessions as $session) {
            $single = $this->rosters()->rosterOn(
                new RosterKey($service->id(), $third->id(), $session->occurrenceDate()),
            );

            self::assertSame(
                array_map(static fn (Uuid $id): string => $id->toRfc4122(), $single),
                $tallies->get($session->id()->toRfc4122())?->rosterIds,
            );
        }
    }

    /**
     * @return list<string>
     */
    private static function idsOf(Person ...$people): array
    {
        return array_values(array_map(static fn (Person $person): string => $person->id()->toRfc4122(), $people));
    }

    /**
     * @param list<Session> $sessions
     *
     * @return list<Session>
     */
    private function reloaded(array $sessions): array
    {
        $entityManager = new Database(self::getContainer())->entityManager();
        $entityManager->clear();

        return array_values($entityManager
            ->getRepository(Session::class)
            ->findBy([
                'id' => array_map(static fn (Session $session): Uuid => $session->id(), $sessions),
            ]));
    }

    /**
     * @param ArrayCollection<int, Session> $sessions
     *
     * @return ReadableCollection<non-empty-string, SessionTally>
     */
    private function tallies(ArrayCollection $sessions): ReadableCollection
    {
        $rosters = self::getContainer()->get(PeopleRosters::class);
        self::assertInstanceOf(PeopleRosters::class, $rosters);

        $marks = self::getContainer()->get(AttendanceMarks::class);
        self::assertInstanceOf(AttendanceMarks::class, $marks);

        $meetings = self::getContainer()->get(MeetingLookup::class);
        self::assertInstanceOf(MeetingLookup::class, $meetings);

        $schedules = $meetings->schedules(SessionTallies::scheduleIdsOf($sessions));

        return SessionTallies::of(
            $rosters->on(SessionTallies::rosterKeysOf($sessions, $schedules)),
            $marks->presentBySession(SessionTallies::sessionIdsOf($sessions)),
            $sessions,
            $schedules,
        );
    }

    private function rosters(): RosterRepositoryInterface
    {
        $rosters = self::getContainer()->get(RosterRepositoryInterface::class);
        self::assertInstanceOf(RosterRepositoryInterface::class, $rosters);

        return $rosters;
    }
}
