<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\ValueObject;

use App\Context\Scheduling\Domain\Enum\Frequency;
use App\Library\Time\Model\CalendarMath;
use App\Library\Time\Model\LocalDate;

final readonly class Cycles
{
    private const int LAST_WEEK = -1;

    public static function match(Recurrence $recurrence, LocalDate $date): bool
    {
        return $recurrence->frequency === Frequency::Weekly
            ? self::weekly($recurrence, $date)
            : self::monthly($recurrence, $date);
    }

    private static function weekly(Recurrence $recurrence, LocalDate $date): bool
    {
        $weeks = CalendarMath::isoWeeksBetween($recurrence->span->startsOn, $date);
        $remainder = $weeks % $recurrence->interval;

        return $remainder === 0;
    }

    private static function monthly(Recurrence $recurrence, LocalDate $date): bool
    {
        $months = CalendarMath::monthsBetween($recurrence->span->startsOn, $date);
        $remainder = $months % $recurrence->interval;

        $inWeek = $recurrence->monthWeek === self::LAST_WEEK
            ? CalendarMath::isInLastWeekOfMonth($date)
            : CalendarMath::weekOfMonth($date) === $recurrence->monthWeek;

        return $remainder === 0 && $inWeek;
    }
}
