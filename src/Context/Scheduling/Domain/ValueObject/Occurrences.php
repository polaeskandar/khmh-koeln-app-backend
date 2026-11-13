<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\ValueObject;

use App\Context\Scheduling\Domain\Enum\Frequency;
use App\Library\Collections\Model\Collections;
use App\Library\Time\Model\LocalDate;

final readonly class Occurrences
{
    public const int HORIZON_DAYS = 400;

    private const int WEEK_DAYS = 7;

    private const int MONTH_DAYS = 31;

    /**
     * @return list<LocalDate>
     */
    public static function between(Recurrence $recurrence, LocalDate $from, LocalDate $to): array
    {
        return Collections::of(self::days($from, $to))->filter($recurrence->occursOn(...))->getValues();
    }

    public static function next(Recurrence $recurrence, LocalDate $from): ?LocalDate
    {
        $startsOn = $recurrence->span->startsOn;
        $start = $from->isBefore($startsOn) ? $startsOn : $from;

        $next = Collections::of(self::days($start, $start->addDays(self::horizonDays($recurrence) - 1)))->filter(
            $recurrence->occursOn(...),
        )->first();

        return $next === false ? null : $next;
    }

    private static function horizonDays(Recurrence $recurrence): int
    {
        return $recurrence->frequency === Frequency::Monthly
            ? ($recurrence->interval * self::MONTH_DAYS) + self::WEEK_DAYS
            : self::HORIZON_DAYS;
    }

    /**
     * @return iterable<int, LocalDate>
     */
    private static function days(LocalDate $from, LocalDate $to): iterable
    {
        $day = $from;

        while (!$to->isBefore($day)) {
            yield $day;

            $day = $day->addDays(1);
        }
    }
}
