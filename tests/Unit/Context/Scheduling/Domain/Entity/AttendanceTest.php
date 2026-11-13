<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Domain\Entity;

use App\Context\Scheduling\Domain\Entity\Attendance;
use App\Context\Scheduling\Domain\ValueObject\Removal;
use App\Tests\Support\Context\Scheduling\Domain\Entity\Meetings;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class AttendanceTest extends TestCase
{
    public function testRecordingKeepsTheRecorderAndTheTime(): void
    {
        $recorder = Uuid::v7();
        $at = new DateTimeImmutable('2026-11-08T12:05:00+01:00');

        $mark = new Attendance(Meetings::session(Meetings::schedule()), Uuid::v7(), $at, $recorder);

        self::assertTrue($mark->isCurrent());
        self::assertSame($recorder, $mark->recordedBy());
        self::assertSame($at, $mark->recordedAt());
    }

    public function testRemovingKeepsTheRemoval(): void
    {
        $mark = new Attendance(
            Meetings::session(Meetings::schedule()),
            Uuid::v7(),
            new DateTimeImmutable('2026-11-08T12:05:00+01:00'),
            null,
        );

        $remover = Uuid::v7();

        $mark->remove(Removal::of(new DateTimeImmutable('2026-11-08T12:10:00+01:00'), $remover, '   '));

        self::assertFalse($mark->isCurrent());
        self::assertSame($remover, $mark->removal()->by);
        self::assertNull($mark->removal()->reason);
    }
}
