<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Domain\ValueObject;

use App\Context\Scheduling\Domain\Enum\Frequency;
use App\Context\Scheduling\Domain\ValueObject\DateSpan;
use App\Context\Scheduling\Domain\ValueObject\Recurrence;
use App\Library\Time\Model\LocalDate;
use PHPUnit\Framework\TestCase;

final class RecurrenceTest extends TestCase
{
    public function testWeekdaysAreStoredAsABitmaskAndReadBackSorted(): void
    {
        $recurrence = Recurrence::weekly(2, weekdays: [7, 1, 1], span: self::span());

        self::assertSame([1, 7], $recurrence->weekdays());
        self::assertSame(65, $recurrence->weekdayMask);

        self::assertSame([Frequency::Weekly, 2, null], [
            $recurrence->frequency,
            $recurrence->interval,
            $recurrence->monthWeek,
        ]);
    }

    public function testAMonthlyRecurrenceHasOneWeekdayAndItsWeekOfTheMonth(): void
    {
        $lastSunday = Recurrence::monthly(-1, weekday: 7, span: self::span());

        self::assertSame([7], $lastSunday->weekdays());

        self::assertSame([Frequency::Monthly, 1, -1], [
            $lastSunday->frequency,
            $lastSunday->interval,
            $lastSunday->monthWeek,
        ]);
    }

    private static function span(): DateSpan
    {
        return new DateSpan(LocalDate::of('2026-09-06'), endsOn: null);
    }
}
