<?php

declare(strict_types=1);

namespace App\Tests\Unit\Library\Time\Model;

use App\Library\Time\Exception\InvalidLocalDate;
use App\Library\Time\Model\LocalDate;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LocalDateTest extends TestCase
{
    public function testAValidDateKeepsItsText(): void
    {
        self::assertSame('2026-11-08', LocalDate::of('2026-11-08')->toString());
        self::assertSame('"2028-02-29"', json_encode(LocalDate::of('2028-02-29'), JSON_THROW_ON_ERROR));
    }

    #[DataProvider('invalidDates')]
    public function testAnythingButAnExistingYearMonthDayIsRejected(string $value): void
    {
        $this->expectException(InvalidLocalDate::class);

        LocalDate::of($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidDates(): iterable
    {
        yield 'empty' => [''];
        yield 'day after february' => ['2026-02-30'];
        yield 'no leap year' => ['2027-02-29'];
        yield 'month thirteen' => ['2026-13-01'];
        yield 'unpadded' => ['2026-2-3'];
        yield 'two digit year' => ['26-01-01'];
        yield 'with time' => ['2026-11-08T00:00'];
        yield 'german order' => ['08.11.2026'];
    }

    public function testDaysAreAddedAcrossMonthsYearsAndClockChanges(): void
    {
        self::assertSame('2026-10-26', LocalDate::of('2026-10-24')->addDays(2)->toString());
        self::assertSame('2027-01-01', LocalDate::of('2026-12-31')->addDays(1)->toString());
        self::assertSame('2027-03-27', LocalDate::of('2027-03-28')->addDays(-1)->toString());
    }

    public function testTheIsoWeekdayRunsFromMondayToSunday(): void
    {
        self::assertSame(1, LocalDate::of('2026-11-09')->isoWeekday());
        self::assertSame(7, LocalDate::of('2026-11-08')->isoWeekday());
    }

    public function testDatesCompareByDay(): void
    {
        $saturday = LocalDate::of('2026-11-07');
        $sunday = LocalDate::of('2026-11-08');

        self::assertTrue($saturday->isBefore($sunday));
        self::assertFalse($sunday->isBefore($saturday));
        self::assertFalse($saturday->isBefore($saturday));
        self::assertEquals($sunday, LocalDate::of('2026-11-08'));
        self::assertNotEquals($sunday, $saturday);
    }

    public function testTheDateOfAnInstantIsTheDayInItsOwnZone(): void
    {
        $lateInBerlin = CarbonImmutable::parse('2026-11-08 00:30', 'Europe/Berlin');

        self::assertSame('2026-11-08', LocalDate::ofInstant($lateInBerlin)->toString());
        self::assertSame('2026-11-07', LocalDate::ofInstant($lateInBerlin->setTimezone('UTC'))->toString());
        self::assertSame('08.11.2026', LocalDate::ofInstant($lateInBerlin)->format('d.m.Y'));
    }
}
