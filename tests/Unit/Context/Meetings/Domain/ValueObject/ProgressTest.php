<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Meetings\Domain\ValueObject;

use App\Context\Meetings\Domain\Enum\SessionStatus;
use App\Context\Meetings\Domain\ValueObject\Progress;
use App\Context\Meetings\Domain\ValueObject\Stamp;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Time\Model\LocalDate;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ProgressTest extends TestCase
{
    public function testASessionOpensFromItsDayOn(): void
    {
        $day = LocalDate::of('2026-11-08');
        $stamp = self::stamp();

        self::assertSame('meetings.opensOnTheDay', self::refusal(static fn (): Progress => Progress::scheduled()->opened(
            $stamp,
            $day,
            LocalDate::of('2026-11-07'),
        )));

        $late = Progress::scheduled()->opened($stamp, $day, LocalDate::of('2026-11-15'));

        self::assertSame(SessionStatus::Active, $late->status);
        self::assertSame($stamp, $late->opened);
    }

    public function testRepeatingATransitionChangesNothing(): void
    {
        $day = LocalDate::of('2026-11-08');
        $active = Progress::scheduled()->opened(self::stamp(), $day, $day);
        $completed = $active->closed(self::stamp());

        self::assertSame($active, $active->opened(self::stamp(), $day, $day));
        self::assertSame($active, $active->reopened());
        self::assertSame($completed, $completed->closed(self::stamp()));
    }

    public function testClosingAndReopeningKeepTheOpening(): void
    {
        $day = LocalDate::of('2026-11-08');
        $opened = self::stamp();
        $closed = self::stamp();

        $completed = Progress::scheduled()->opened($opened, $day, $day)->closed($closed);
        $reopened = $completed->reopened();

        self::assertSame(SessionStatus::Completed, $completed->status);
        self::assertSame($opened, $completed->opened);
        self::assertSame($closed, $completed->closed);
        self::assertSame(SessionStatus::Active, $reopened->status);
        self::assertSame($opened, $reopened->opened);
        self::assertNull($reopened->closed->at);
    }

    public function testEachRefusalHasItsOwnCode(): void
    {
        $day = LocalDate::of('2026-11-08');
        $scheduled = Progress::scheduled();
        $completed = $scheduled->opened(self::stamp(), $day, $day)->closed(self::stamp());

        self::assertSame('meetings.attendanceClosed', self::refusal(static fn (): Progress => $completed->opened(
            self::stamp(),
            $day,
            $day,
        )));

        self::assertSame('meetings.onlyActiveCanClose', self::refusal(static fn (): Progress => $scheduled->closed(
            self::stamp(),
        )));

        self::assertSame('meetings.onlyCompletedCanReopen', self::refusal($scheduled->reopened(...)));
    }

    public function testOnlyAScheduledSessionIsCanceledAndKeepsItsReason(): void
    {
        $day = LocalDate::of('2026-11-08');
        $canceled = Progress::scheduled()->canceled('Holiday');
        $active = Progress::scheduled()->opened(self::stamp(), $day, $day);

        self::assertSame(SessionStatus::Canceled, $canceled->status);
        self::assertSame('Holiday', $canceled->cancelReason);

        foreach ([$active, $active->closed(self::stamp()), $canceled] as $progress) {
            self::assertSame('meetings.onlyScheduledCanCancel', self::refusal(
                static fn (): Progress => $progress->canceled('Again'),
            ));
        }
    }

    /**
     * @param callable(): Progress $transition
     */
    private static function refusal(callable $transition): ?string
    {
        try {
            $transition();
        } catch (RuleViolation $violation) {
            return $violation->getMessage();
        }

        return null;
    }

    private static function stamp(): Stamp
    {
        return new Stamp(new DateTimeImmutable('2026-11-08T10:00:00+01:00'), Uuid::v7());
    }
}
