<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

use App\Library\Time\Model\LocalDate;
use App\Library\Time\Model\LocalTime;
use DateTimeImmutable;

final readonly class MeetingMoment
{
    public function __construct(
        public DateTimeImmutable $instant,
        public LocalDate $date,
        public LocalTime $time,
    ) {
    }

    public function isLaterOnTheDayOf(self $start): bool
    {
        $sameDay = $this->date->toString() === $start->date->toString();
        $later = $this->instant > $start->instant && $this->time->isAfter($start->time);

        return $sameDay && $later;
    }
}
