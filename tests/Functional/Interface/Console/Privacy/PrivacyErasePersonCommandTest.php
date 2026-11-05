<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Console\Privacy;

use App\Context\Meetings\Domain\Entity\Attendance;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\ValueObject\Removal;
use App\Context\People\Domain\Entity\Person;
use App\Context\Privacy\Domain\Enum\RequestKind;
use App\Library\Types\Model\Type;
use App\Tests\Support\Context\Meetings\Domain\Entity\Schedules;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Database;
use App\Tests\Support\Interface\Api\Privacy\ErasureFixture;
use App\Tests\Support\Workflow\Attendance\Model\SessionTallyLookups;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class PrivacyErasePersonCommandTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    private const string DONE = "status = 'completed', opened_at = created_at, closed_at = created_at";

    private const array HANDLED = [
        'identity.app_user.person_id',
        'meetings.attendance.person_id',
        'learning.homework_completion.person_id',
        'people.guardian_relationship.person_id',
        'people.membership.person_id',
        'privacy.data_request.person_id',
    ];

    public function testPastTotalsStayTheSameAfterTheErasure(): void
    {
        self::mockTime('2026-11-08T12:00:00+01:00');
        [$mina, $sessions] = $this->fixture();
        $before = $this->countsOf($sessions);

        self::assertSame(Command::SUCCESS, $this->erase('app:privacy:erase-person', $mina));

        self::assertSame($before, $this->countsOf($sessions));
        self::assertNotSame([], array_filter($before, static fn (array $counts): bool => $counts !== [0, 0, 0, 0, 0]));
    }

    public function testCanceledFutureSessionsAndRemovedMarksAreNotCounted(): void
    {
        self::mockTime('2026-11-08T12:00:00+01:00');
        [$mina] = $this->fixture();

        $this->erase('app:privacy:erase-person', $mina);

        self::assertSame(
            [
                'canceled' => '0/0/0',
                'expected' => '1/1/1',
                'future' => '0/0/0',
                'open' => '0/1/0',
                'other stage' => '0/1/0',
                'removed mark' => '1/0/0',
            ],
            $this->counters(),
        );
    }

    public function testNothingPointsAtThePersonAfterwards(): void
    {
        self::mockTime('2026-11-08T12:00:00+01:00');
        [$mina] = $this->fixture();
        $connection = new Database(self::getContainer())->connection();

        $this->erase('app:privacy:erase-person', $mina);

        $columns = array_map(
            static fn (array $row): array => [
                Type::of($row[0] ?? null)->toString(),
                Type::of($row[1] ?? null)->toString(),
            ],
            $connection->fetchAllNumeric("SELECT table_schema || '.' || table_name, column_name FROM information_schema.columns
                 WHERE data_type = 'uuid' AND column_name = 'person_id'"),
        );

        foreach ($columns as [$table, $column]) {
            self::assertContains(
                $table . '.' . $column,
                self::HANDLED,
                $table . '.' . $column . ' points at a person but the erasure ignores it',
            );

            self::assertSame(0, $connection->fetchOne('SELECT count(*) FROM '
            . $table
            . ' WHERE '
            . $column
            . ' = ?', [$mina->id()->toRfc4122()]));
        }

        self::assertSame(0, $connection->fetchOne('SELECT count(*) FROM people.person WHERE id = ?', [$mina->id()->toRfc4122()]));
    }

    public function testADryRunAndASecondRunChangeNothing(): void
    {
        self::mockTime('2026-11-08T12:00:00+01:00');
        [$mina] = $this->fixture();

        self::assertSame(Command::SUCCESS, $this->erase('app:privacy:erase-person:dry-run', $mina));

        self::assertSame(1, new Database(self::getContainer())
            ->connection()
            ->fetchOne('SELECT count(*) FROM people.person WHERE id = ?', [$mina->id()->toRfc4122()]));

        self::assertSame(['0/0/0'], array_values(array_unique($this->counters())));

        self::assertSame(Command::SUCCESS, $this->erase('app:privacy:erase-person', $mina));
        $after = $this->counters();
        self::assertSame(Command::FAILURE, $this->erase('app:privacy:erase-person', $mina));
        self::assertSame($after, $this->counters());

        $tester = new CommandTester(new Application(self::bootKernel())->find('app:privacy:erase-person'));
        self::assertSame(Command::FAILURE, $tester->execute(['person' => 'not-a-person']));
        self::assertStringContainsString('notFound.person', $tester->getDisplay());
    }

    public function testTheMembersRequestsKeepOnlyAnAnonymousSubject(): void
    {
        self::mockTime('2026-11-08T12:00:00+01:00');
        [$mina] = $this->fixture();
        new ErasureFixture(self::getContainer())->request(RequestKind::Erasure, $mina);

        $this->erase('app:privacy:erase-person', $mina);

        self::assertSame(
            [[
                'person_id' => null,
                'erased_subject_kind' => 'member',
                'erased_subject_group' => '3rd Class',
                'status' => 'open',
            ]],
            new Database(self::getContainer())
                ->connection()
                ->fetchAllAssociative(
                    'SELECT person_id, erased_subject_kind, erased_subject_group, status FROM privacy.data_request',
                ),
        );
    }

    private function erase(string $command, Person $person): int
    {
        return new CommandTester(new Application(self::bootKernel())->find($command))->execute([
            'person' => $person->id()->toRfc4122(),
        ]);
    }

    /**
     * @return array<array-key, string>
     */
    private function counters(): array
    {
        $rows = new Database(self::getContainer())
            ->connection()
            ->fetchAllKeyValue(
                "SELECT sc.name, s.erased_roster || '/' || s.erased_present || '/' || s.erased_roster_present
             FROM meetings.meeting_session s JOIN meetings.meeting_schedule sc ON sc.id = s.schedule_id ORDER BY 1",
            );

        return array_map(static fn (mixed $value): string => Type::of($value)->toString(), $rows);
    }

    /**
     * @param list<Session> $sessions
     *
     * @return list<list<int|null>>
     */
    private function countsOf(array $sessions): array
    {
        $entityManager = new Database(self::getContainer())->entityManager();
        $entityManager->clear();

        $fresh = array_map(static fn (Session $session): ?Session => $entityManager->find(
            Session::class,
            $session->id(),
        ), $sessions);

        $tallies = new SessionTallyLookups(self::getContainer())->tallies(new ArrayCollection(array_values(array_filter(
            $fresh,
        ))));

        return array_map(static function (Session $session) use ($tallies): array {
            $counts = $tallies->get($session->id()->toRfc4122())?->counts;

            return [
                $counts?->present,
                $counts?->rosterSize,
                $counts?->rosterPresent,
                $counts?->notMarked,
                $counts?->notOnRoster,
            ];
        }, $sessions);
    }

    /**
     * @return array{Person, list<Session>}
     */
    private function fixture(): array
    {
        $service = Structure::service();
        $third = Structure::stage($service);
        $first = Structure::stage($service, '1st Class', 1);
        new Database(self::getContainer())->persisted($service, $third, $first);
        $rosters = new Rosters(self::getContainer());
        $mina = $rosters->person('Mina', 'Hanna');
        $rosters->place($mina, $service, $third, '2026-09-01');
        $rosters->place($rosters->person('Kiro', 'Nashed'), $service, $third, '2026-09-01');
        $schedules = new Schedules(self::getContainer());

        $expected = $schedules->session(
            $schedules->weekly($service, 'expected', 7, stage: $third),
            '2026-11-01',
            self::DONE,
        );

        $other = $schedules->session(
            $schedules->weekly($service, 'other stage', 7, stage: $first),
            '2026-11-01',
            self::DONE,
        );

        $openSchedule = $schedules->weekly($service, 'open', 7);

        new Database(self::getContainer())
            ->connection()
            ->update(
                'meetings.meeting_schedule',
                ['roster_mode' => 'open'],
                ['id' => $openSchedule->id()->toRfc4122()],
            );

        $open = $schedules->session($openSchedule, '2026-11-01', self::DONE . ", roster_mode = 'open'");

        $schedules->session(
            $schedules->weekly($service, 'canceled', 7, stage: $third),
            '2026-11-01',
            "status = 'canceled'",
        );

        $schedules->session($schedules->weekly($service, 'future', 7, stage: $third), '2026-11-15');

        $removed = $schedules->session(
            $schedules->weekly($service, 'removed mark', 7, stage: $third),
            '2026-11-01',
            self::DONE,
        );

        foreach ([$expected, $other, $open] as $session) {
            new Database(self::getContainer())->persisted(
                new Attendance(
                    $schedules->managed($session),
                    $mina->id(),
                    new DateTimeImmutable('2026-11-01T12:00:00+01:00'),
                    recordedBy: null,
                ),
            );
        }

        $mark = new Attendance(
            $schedules->managed($removed),
            $mina->id(),
            new DateTimeImmutable('2026-11-01T12:00:00+01:00'),
            recordedBy: null,
        );

        $mark->remove(Removal::of(new DateTimeImmutable('2026-11-01T12:05:00+01:00'), by: null, reason: null));
        new Database(self::getContainer())->persisted($mark);

        return [$mina, [$expected, $other, $open, $removed]];
    }
}
