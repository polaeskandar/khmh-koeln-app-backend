<?php

declare(strict_types=1);

namespace App\Tests\Unit\Library\Time\Model;

use App\Library\Time\Model\CalendarMath;
use App\Library\Time\Model\LocalDate;
use PHPUnit\Framework\TestCase;

final class CalendarMathTest extends TestCase
{
    public function testTheWeekOfTheMonthCountsSevenDayBlocks(): void
    {
        self::assertSame(1, CalendarMath::weekOfMonth(LocalDate::of('2026-11-07')));
        self::assertSame(2, CalendarMath::weekOfMonth(LocalDate::of('2026-11-08')));
        self::assertSame(5, CalendarMath::weekOfMonth(LocalDate::of('2026-11-29')));
    }

    public function testTheLastWeekIsTheFinalSevenDays(): void
    {
        self::assertTrue(CalendarMath::isInLastWeekOfMonth(LocalDate::of('2026-02-22')));
        self::assertFalse(CalendarMath::isInLastWeekOfMonth(LocalDate::of('2026-02-21')));
        self::assertTrue(CalendarMath::isInLastWeekOfMonth(LocalDate::of('2026-11-24')));
    }

    public function testMonthsAndIsoWeeksBetweenDates(): void
    {
        self::assertSame(4, CalendarMath::monthsBetween(LocalDate::of('2026-09-20'), LocalDate::of('2027-01-02')));
        self::assertSame(2, CalendarMath::isoWeeksBetween(LocalDate::of('2026-09-09'), LocalDate::of('2026-09-21')));
        self::assertSame(0, CalendarMath::isoWeeksBetween(LocalDate::of('2026-09-07'), LocalDate::of('2026-09-13')));
    }
}
