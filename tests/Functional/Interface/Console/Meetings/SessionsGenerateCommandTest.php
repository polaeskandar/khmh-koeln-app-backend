<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Console\Meetings;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Enum\RosterMode;
use App\Context\Meetings\Domain\ValueObject\DateSpan;
use App\Context\Meetings\Domain\ValueObject\MeetingDetails;
use App\Context\Meetings\Domain\ValueObject\Recurrence;
use App\Context\Meetings\Domain\ValueObject\Roster;
use App\Context\Meetings\Domain\ValueObject\TimeSlot;
use App\Context\Meetings\Infrastructure\Lock\SymfonyGeneratorLock;
use App\Library\Time\Model\LocalDate;
use App\Library\Time\Model\LocalTime;
use App\Tests\Support\Context\Meetings\Application\Service\SessionGeneration;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use App\Tests\Support\Logs;
use Doctrine\DBAL\DriverManager;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\DoctrineDbalPostgreSqlStore;

final class SessionsGenerateCommandTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    #[Override]
    protected function tearDown(): void
    {
        date_default_timezone_set('UTC');
        parent::tearDown();
    }

    /**
     * @param list<string> $dates
     */
    #[DataProvider('churchDays')]
    public function testTheChurchDayAndTheNextAreGeneratedWhateverTheDefaultZone(
        string $now,
        string $zone,
        array $dates,
    ): void {
        date_default_timezone_set($zone);
        self::mockTime($now);
        $this->dailySchedule();

        self::assertSame(Command::SUCCESS, $this->generate([]));

        self::assertSame($dates, $this->sessionDates());
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function churchDays(): iterable
    {
        foreach (['UTC', 'America/Los_Angeles'] as $zone) {
            yield 'late Saturday, ' . $zone => ['2026-11-07T22:05:00Z', $zone, ['2026-11-07', '2026-11-08']];
            yield 'Sunday after midnight, ' . $zone => ['2026-11-07T23:05:00Z', $zone, ['2026-11-08', '2026-11-09']];
            yield 'summer time, ' . $zone => ['2027-06-05T22:05:00Z', $zone, ['2027-06-06', '2027-06-07']];
        }
    }

    public function testARunThatFindsTheLockHeldDoesNothing(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $this->dailySchedule();
        $other = DriverManager::getConnection(new Database(self::getContainer())->connection()->getParams());

        $held = new LockFactory(new DoctrineDbalPostgreSqlStore($other))->createLock(
            SymfonyGeneratorLock::RESOURCE,
            ttl: null,
            autoRelease: false,
        );

        self::assertTrue($held->acquire());

        try {
            self::assertSame(Command::SUCCESS, $this->generate([]));
        } finally {
            $other->close();
        }

        self::assertSame([], $this->sessionDates());
    }

    public function testADateBackfillsThatDayAndTheNextButNeverAfterToday(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $this->dailySchedule();

        self::assertSame(Command::INVALID, $this->generate(['--date' => '2026-11-09']));
        self::assertSame(Command::INVALID, $this->generate(['--date' => '8.11.2026']));
        self::assertSame([], $this->sessionDates());

        self::assertSame(Command::SUCCESS, $this->generate(['--date' => '2026-11-07']));
        self::assertSame(['2026-11-07', '2026-11-08'], $this->sessionDates());
    }

    public function testAFailingScheduleExitsOneAndTheOthersAreCreated(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $broken = SessionGeneration::sundays();
        new Database(self::getContainer())->persisted(SessionGeneration::sundays(), $broken);
        new SessionGeneration(self::getContainer())->refuseSessionsOf($broken);

        self::assertSame(Command::FAILURE, $this->generate([]));
        self::assertSame(['2026-11-08'], $this->sessionDates());
    }

    public function testEachRunWritesOneSummaryOnItsChannel(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $this->dailySchedule();

        $this->generate([]);

        $records = new Logs(self::getContainer())->containing('"channel":"session_generator"');
        self::assertCount(1, $records);
        self::assertStringContainsString('"created":2', $records[0] ?? '');
        self::assertStringContainsString('"today":"2026-11-08"', $records[0] ?? '');
    }

    /**
     * @param array<string, string> $input
     */
    private function generate(array $input): int
    {
        return new CommandTester(new Application(self::bootKernel())->find('app:sessions:generate'))->execute($input);
    }

    private function dailySchedule(): void
    {
        $service = Structure::service();

        $schedule = new Schedule(
            new Roster($service->id(), stageId: null, mode: RosterMode::Expected),
            new MeetingDetails('Daily prayer', description: null, location: null),
            Recurrence::weekly(
                1,
                weekdays: [1, 2, 3, 4, 5, 6, 7],
                span: new DateSpan(LocalDate::of('2026-01-01'), endsOn: null),
            ),
            new TimeSlot(LocalTime::of('07:00'), endTime: null),
        );

        new Database(self::getContainer())->persisted($service, $schedule);
    }

    /**
     * @return list<string>
     */
    private function sessionDates(): array
    {
        $dates = new Database(self::getContainer())
            ->connection()
            ->fetchFirstColumn('SELECT occurrence_date::text FROM meetings.meeting_session ORDER BY 1');

        return array_values(array_filter($dates, is_string(...)));
    }
}
