<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

use App\Library\Collections\Model\Collections;
use App\Library\Time\Model\LocalDate;

final readonly class Occurrences
{
    public const int HORIZON_DAYS = 400;

    /**
     * @return list<LocalDate>
     */
    public static function between(Recurrence $recurrence, LocalDate $from, LocalDate $to): array
    {
        return Collections::of(self::days($from, $to))->filter($recurrence->occursOn(...))->getValues();
    }

    public static function next(Recurrence $recurrence, LocalDate $from): ?LocalDate
    {
        $next = Collections::of(self::days($from, $from->addDays(self::HORIZON_DAYS)))->filter(
            $recurrence->occursOn(...),
        )->first();

        return $next === false ? null : $next;
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
