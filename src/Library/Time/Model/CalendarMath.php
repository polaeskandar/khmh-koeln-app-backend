<?php

declare(strict_types=1);

namespace App\Library\Time\Model;

use Carbon\CarbonImmutable;
use LogicException;

final readonly class CalendarMath
{
    private const int DAYS_PER_WEEK = 7;

    private const int MONTHS_PER_YEAR = 12;

    public static function weekOfMonth(LocalDate $date): int
    {
        return intdiv(self::midnight($date)->day - 1, self::DAYS_PER_WEEK) + 1;
    }

    public static function isInLastWeekOfMonth(LocalDate $date): bool
    {
        $midnight = self::midnight($date);
        $lastWeekStarts = $midnight->daysInMonth - self::DAYS_PER_WEEK;

        return $midnight->day > $lastWeekStarts;
    }

    public static function monthsBetween(LocalDate $from, LocalDate $to): int
    {
        return self::monthNumber(self::midnight($to)) - self::monthNumber(self::midnight($from));
    }

    public static function isoWeeksBetween(LocalDate $from, LocalDate $to): int
    {
        $start = self::midnight($from)->startOfWeek(CarbonImmutable::MONDAY);
        $days = (int) round($start->diffInDays(self::midnight($to)->startOfWeek(CarbonImmutable::MONDAY)));

        return intdiv($days, self::DAYS_PER_WEEK);
    }

    private static function monthNumber(CarbonImmutable $midnight): int
    {
        return ($midnight->year * self::MONTHS_PER_YEAR) + $midnight->month;
    }

    private static function midnight(LocalDate $date): CarbonImmutable
    {
        $midnight = CarbonImmutable::createFromFormat('!Y-m-d', $date->toString(), 'UTC');

        return $midnight ?? throw new LogicException('A local date always reads as a date.');
    }
}
