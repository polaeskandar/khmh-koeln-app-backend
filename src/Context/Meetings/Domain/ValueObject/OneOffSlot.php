<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

use App\Library\Problem\Exception\InvalidInput;
use App\Library\Time\Model\LocalDate;

final readonly class OneOffSlot
{
    private function __construct(
        public LocalDate $date,
        public TimeSlot $times,
    ) {
    }

    public static function between(MeetingMoment $start, ?MeetingMoment $end, LocalDate $today): self
    {
        if ($start->date->isBefore($today)) {
            throw InvalidInput::field('startsAt', 'meetings.dateInPast');
        }

        if ($end !== null && !$end->isLaterOnTheDayOf($start)) {
            throw InvalidInput::field('endsAt', 'meetings.endAfterStart');
        }

        return new self($start->date, new TimeSlot($start->time, $end?->time));
    }
}
