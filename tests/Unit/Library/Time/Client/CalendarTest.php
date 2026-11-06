<?php

declare(strict_types=1);

namespace App\Tests\Unit\Library\Time\Client;

use App\Library\Time\Client\Calendar;
use App\Library\Time\Model\LocalDate;
use App\Library\Time\Model\LocalTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class CalendarTest extends TestCase
{
    private const array DEFAULT_ZONES = ['UTC', 'Europe/Berlin', 'America/Los_Angeles'];

    #[\Override]
    protected function tearDown(): void
    {
        date_default_timezone_set('UTC');
    }

    #[DataProvider('todays')]
    public function testTodayIsTheDateInBerlinWhateverTheDefaultZone(string $now, string $today, string $zone): void
    {
        date_default_timezone_set($zone);

        self::assertSame($today, self::calendarAt($now)->today()->toString());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function todays(): iterable
    {
        $cases = [
            '2026-11-07T22:05:00Z' => '2026-11-07',
            '2026-11-07T23:05:00Z' => '2026-11-08',
            '2027-06-05T22:05:00Z' => '2027-06-06',
        ];

        foreach ($cases as $now => $today) {
            foreach (self::DEFAULT_ZONES as $zone) {
                yield $now . ' in ' . $zone => [$now, $today, $zone];
            }
        }
    }

    public function testNowAndTomorrowFollowTheClockInBerlin(): void
    {
        $calendar = self::calendarAt('2026-11-07T23:05:00Z');

        self::assertSame('2026-11-08T00:05:00+01:00', $calendar->now()->toRfc3339String());
        self::assertSame('2026-11-09', $calendar->tomorrow()->toString());
    }

    #[DataProvider('instants')]
    public function testAWallClockTimeBecomesTheInstantBerlinMeans(string $date, string $time, string $instant): void
    {
        $actual = self::calendarAt('2026-11-07T12:00:00Z')->instantAt(LocalDate::of($date), LocalTime::of($time));

        self::assertSame($instant, $actual->toRfc3339String());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function instants(): iterable
    {
        yield 'summer time' => ['2027-03-28', '12:00', '2027-03-28T12:00:00+02:00'];
        yield 'winter time' => ['2027-03-21', '12:00', '2027-03-21T12:00:00+01:00'];
        yield 'skipped hour moves forward' => ['2027-03-28', '02:30', '2027-03-28T03:30:00+02:00'];
        yield 'repeated hour takes the later' => ['2026-10-25', '02:30', '2026-10-25T02:30:00+01:00'];
    }

    public function testTheDateOfAnInstantIsItsDateInBerlin(): void
    {
        $calendar = self::calendarAt('2026-11-07T12:00:00Z');
        $lateSaturday = $calendar->instantAt(LocalDate::of('2026-11-07'), LocalTime::of('23:30'));

        self::assertSame('2026-11-07', $calendar->localDateOf($lateSaturday->setTimezone('UTC'))->toString());
    }

    #[DataProvider('dayLengths')]
    public function testADayRunsFromItsMidnightToTheNext(string $date, string $start, int $hours): void
    {
        $bounds = self::calendarAt('2026-11-07T12:00:00Z')->dayBounds(LocalDate::of($date));

        self::assertSame($start, $bounds->start->toRfc3339String());
        self::assertSame($hours, (int) $bounds->start->diffInHours($bounds->end));
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function dayLengths(): iterable
    {
        yield 'ordinary' => ['2026-11-08', '2026-11-08T00:00:00+01:00', 24];
        yield 'clocks go back' => ['2026-10-25', '2026-10-25T00:00:00+02:00', 25];
        yield 'clocks go forward' => ['2027-03-28', '2027-03-28T00:00:00+01:00', 23];
    }

    private static function calendarAt(string $now): Calendar
    {
        return new Calendar(new MockClock($now), 'Europe/Berlin');
    }
}
