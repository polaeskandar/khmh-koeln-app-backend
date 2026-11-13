<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Domain\ValueObject;

use App\Context\Scheduling\Domain\Enum\SessionStatus;
use App\Context\Scheduling\Domain\ValueObject\Progress;
use App\Context\Scheduling\Domain\ValueObject\Stamp;
use App\Library\Problem\Exception\RuleViolation;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ProgressTest extends TestCase
{
    public function testANewSessionIsScheduledWithoutStampsOrReason(): void
    {
        $progress = Progress::scheduled();

        self::assertSame(SessionStatus::Scheduled, $progress->status);
        self::assertNull($progress->cancelReason);
        self::assertNull($progress->opened->at);
        self::assertNull($progress->closed->at);
    }

    public function testEachChangeReplacesOnlyItsOwnPart(): void
    {
        $opened = self::stamp();
        $closed = self::stamp();

        $progress = Progress::scheduled()
            ->withOpened($opened)
            ->withClosed($closed)
            ->withStatus(SessionStatus::Completed);

        self::assertSame(SessionStatus::Completed, $progress->status);
        self::assertSame($opened, $progress->opened);
        self::assertSame($closed, $progress->closed);
        self::assertNull($progress->cancelReason);
        self::assertSame('Holiday', Progress::scheduled()->withCancelReason('Holiday')->cancelReason);
        self::assertSame(SessionStatus::Scheduled, Progress::scheduled()->withCancelReason('Holiday')->status);
    }

    public function testOnlyACanceledSessionRefusesChanges(): void
    {
        foreach ([SessionStatus::Scheduled, SessionStatus::Active, SessionStatus::Completed] as $status) {
            Progress::scheduled()->withStatus($status)->ensureNotCanceled();
        }

        $this->expectExceptionObject(new RuleViolation('meetings.sessionCanceled'));
        Progress::scheduled()->withStatus(SessionStatus::Canceled)->ensureNotCanceled();
    }

    public function testASessionWasOpenedOnceItIsActiveOrCompleted(): void
    {
        $opened = static fn (SessionStatus $status): bool => Progress::scheduled()->withStatus($status)->wasOpened();

        self::assertFalse($opened(SessionStatus::Scheduled));
        self::assertTrue($opened(SessionStatus::Active));
        self::assertTrue($opened(SessionStatus::Completed));
        self::assertFalse($opened(SessionStatus::Canceled));
    }

    private static function stamp(): Stamp
    {
        return new Stamp(new DateTimeImmutable('2026-11-08T10:00:00+01:00'), Uuid::v7());
    }
}
