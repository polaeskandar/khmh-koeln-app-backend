<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\Validation;

use App\Context\Meetings\Domain\ValueObject\DateSpan;
use App\Context\Meetings\Domain\ValueObject\RecurrenceDraft;
use App\Library\Problem\Exception\InvalidInput;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class RecurrenceRule
{
    private const int MAX_INTERVAL = 52;

    private const array WEEKDAYS = [1, 2, 3, 4, 5, 6, 7];

    private const array MONTH_WEEKS = [1, 2, 3, 4, -1];

    public static function ensureValid(RecurrenceDraft $draft): void
    {
        InvalidInput::all(
            static fn () => self::ensureInterval($draft->interval ?? 0),
            static fn () => self::ensureWeekdays($draft),
            static fn () => self::ensureMonthWeek($draft),
            static fn () => self::ensureEndAfterStart($draft->span),
        );
    }

    private static function ensureInterval(int $interval): void
    {
        if ($interval < 1 || $interval > self::MAX_INTERVAL) {
            throw self::refusal('interval', 'recurrence.interval');
        }
    }

    private static function ensureWeekdays(RecurrenceDraft $draft): void
    {
        $weekdays = new ArrayCollection($draft->weekdays);

        $code = match (true) {
            $weekdays->isEmpty() => 'recurrence.weekdays',
            !$weekdays->filter(self::isOutsideTheWeek(...))->isEmpty() => 'recurrence.weekdayRange',
            $draft->isMonthly() && $weekdays->count() !== 1 => 'recurrence.monthlyOneDay',
            default => null,
        };

        if ($code !== null) {
            throw self::refusal('weekdays', $code);
        }
    }

    private static function ensureMonthWeek(RecurrenceDraft $draft): void
    {
        $isWeekOfMonth = new ArrayCollection(self::MONTH_WEEKS)->contains($draft->monthWeek);

        $code = match (true) {
            $draft->isMonthly() && !$isWeekOfMonth => 'recurrence.monthWeek',
            !$draft->isMonthly() && $draft->monthWeek !== null => 'recurrence.monthWeekOnlyMonthly',
            default => null,
        };

        if ($code !== null) {
            throw self::refusal('monthWeek', $code);
        }
    }

    private static function ensureEndAfterStart(DateSpan $span): void
    {
        if ($span->endsOn?->isBefore($span->startsOn) === true) {
            throw self::refusal('span.endsOn', 'recurrence.endBeforeStart');
        }
    }

    private static function isOutsideTheWeek(int $weekday): bool
    {
        return !new ArrayCollection(self::WEEKDAYS)->contains($weekday);
    }

    private static function refusal(string $field, string $code): InvalidInput
    {
        return InvalidInput::field('recurrence.' . $field, $code);
    }
}
