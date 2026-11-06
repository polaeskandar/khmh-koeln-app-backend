<?php

declare(strict_types=1);

namespace App\Tests\Functional\Workflow\Meetings;

use App\Tests\Support\Context\Meetings\Application\Service\SessionGeneration;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use App\Workflow\Meetings\GenerateSessionsWorkflow;
use App\Workflow\Meetings\Model\GeneratedSessions;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class GenerateSessionsWorkflowTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    private const string GENERATOR_CREATIONS =
        'SELECT count(*) FROM meetings.session_event ' . "WHERE type = 'created' AND by_generator AND actor_id IS NULL";

    #[DataProvider('days')]
    public function testTomorrowsSessionIsCreatedOnceAlsoAcrossClockChanges(string $today, string $tomorrow): void
    {
        $this->database()->persisted(SessionGeneration::sundays());

        self::assertEquals(new GeneratedSessions(created: 1), $this->generate($today));
        self::assertEquals(new GeneratedSessions(existing: 1), $this->generate($tomorrow));
        self::assertSame([$tomorrow], $this->generation()->sessionDates());
        self::assertSame(1, $this->database()->connection()->fetchOne(self::GENERATOR_CREATIONS));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function days(): iterable
    {
        yield 'November' => ['2026-11-07', '2026-11-08'];
        yield 'spring clock change' => ['2027-03-27', '2027-03-28'];
        yield 'autumn clock change' => ['2026-10-24', '2026-10-25'];
    }

    public function testTodaysMissedSessionIsCaughtUpAndTheEndDateCounts(): void
    {
        $this->database()->persisted(SessionGeneration::sundays(endsOn: '2026-11-08'));

        self::assertEquals(new GeneratedSessions(created: 1), $this->generate('2026-11-08'));
        self::assertEquals(new GeneratedSessions(), $this->generate('2026-11-14'));
        self::assertSame(['2026-11-08'], $this->generation()->sessionDates());
    }

    public function testInactiveSchedulesAndArchivedServicesOrStagesCreateNothing(): void
    {
        $archived = Structure::service('Old Club');
        $archived->archive(Structure::now());
        $school = Structure::service('Sunday School');
        $oldStage = Structure::stage($school, 'Old Class');
        $oldStage->archive(Structure::now());
        $inactive = SessionGeneration::sundays();

        $this->database()->persisted(
            $archived,
            $school,
            $oldStage,
            $inactive,
            SessionGeneration::sundays($archived->id()),
            SessionGeneration::sundays($school->id(), $oldStage->id()),
        );

        $this
            ->database()
            ->connection()
            ->executeStatement('UPDATE meetings.meeting_schedule SET active = false WHERE id = ?', [
                $inactive->id()->toRfc4122(),
            ]);

        $this->database()->entityManager()->clear();

        self::assertEquals(new GeneratedSessions(), $this->generate('2026-11-07'));
        self::assertSame([], $this->generation()->sessionDates());
    }

    public function testAnExistingCanceledSessionIsLeftAlone(): void
    {
        $this->database()->persisted(SessionGeneration::sundays());
        $this->generate('2026-11-07');

        $this
            ->database()
            ->connection()
            ->executeStatement("UPDATE meetings.meeting_session SET status = 'canceled', cancel_reason = 'Holiday'");

        self::assertEquals(new GeneratedSessions(existing: 1), $this->generate('2026-11-07'));

        self::assertSame(
            'canceled',
            $this->database()->connection()->fetchOne('SELECT status FROM meetings.meeting_session'),
        );
    }

    public function testTodayIsTheChurchDayAfterBerlinMidnight(): void
    {
        $this->database()->persisted(SessionGeneration::sundays());
        self::mockTime('2026-11-08T23:30:00Z');
        $workflow = self::getContainer()->get(GenerateSessionsWorkflow::class);
        self::assertInstanceOf(GenerateSessionsWorkflow::class, $workflow);

        self::assertEquals(new GeneratedSessions(), $workflow());
        self::assertSame([], $this->generation()->sessionDates());
    }

    public function testAFailingScheduleLeavesTheOthersCreated(): void
    {
        $broken = SessionGeneration::sundays();
        $this->database()->persisted(SessionGeneration::sundays(), SessionGeneration::sundays(), $broken);
        $this->generation()->refuseSessionsOf($broken);

        self::assertEquals(new GeneratedSessions(created: 2, failed: 1), $this->generate('2026-11-07'));
        self::assertSame(2, $this->database()->connection()->fetchOne('SELECT count(*) FROM meetings.meeting_session'));
    }

    private function generate(string $today): GeneratedSessions
    {
        self::mockTime($today . ' 12:00:00 Europe/Berlin');
        $workflow = self::getContainer()->get(GenerateSessionsWorkflow::class);
        self::assertInstanceOf(GenerateSessionsWorkflow::class, $workflow);

        return $workflow();
    }

    private function generation(): SessionGeneration
    {
        return new SessionGeneration(self::getContainer());
    }

    private function database(): Database
    {
        return new Database(self::getContainer());
    }
}
