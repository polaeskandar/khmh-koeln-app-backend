<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Attendance\Domain\Entity;

use App\Context\Attendance\Domain\Entity\Attendance;
use App\Library\Problem\Exception\RuleViolation;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class AttendanceTest extends TestCase
{
    public function testRecordingKeepsTheRecorderAndTheTime(): void
    {
        $recorder = Uuid::v7();
        $at = new DateTimeImmutable('2026-11-08T12:05:00+01:00');

        $mark = Attendance::record(Uuid::v7(), Uuid::v7(), $at, $recorder);

        self::assertTrue($mark->isCurrent());
        self::assertSame($recorder, $mark->recordedBy());
        self::assertSame($at, $mark->recordedAt());
    }

    public function testRemovingSetsTheRemovalOnceWithACleanReason(): void
    {
        $mark = Attendance::record(Uuid::v7(), Uuid::v7(), new DateTimeImmutable('2026-11-08T12:05:00+01:00'), null);
        $remover = Uuid::v7();

        $mark->remove($remover, new DateTimeImmutable('2026-11-08T12:10:00+01:00'), '   ');

        self::assertFalse($mark->isCurrent());
        self::assertSame($remover, $mark->removal()->by);
        self::assertNull($mark->removal()->reason);

        $this->expectException(RuleViolation::class);
        $mark->remove($remover, new DateTimeImmutable('2026-11-08T12:11:00+01:00'), 'Twice');
    }
}
