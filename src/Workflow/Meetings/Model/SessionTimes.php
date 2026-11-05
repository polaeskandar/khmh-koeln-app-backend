<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Meetings\Domain\Entity\Session;
use App\Library\Time\Client\Calendar;
use DateTimeInterface;

final readonly class SessionTimes
{
    public function __construct(
        public string $date,
        public string $startTime,
        public ?string $endTime,
        public string $startsAt,
        public ?string $endsAt,
    ) {
    }

    public static function of(Session $session, Calendar $calendar): self
    {
        $date = $session->occurrenceDate();
        $slot = $session->schedule()->timeSlot();
        $endTime = $slot->endTime;

        return new self(
            date: $date->toString(),
            startTime: $slot->startTime->toString(),
            endTime: $endTime?->toString(),
            startsAt: $calendar->instantAt($date, $slot->startTime)->format(DateTimeInterface::ATOM),
            endsAt: $endTime === null ? null : $calendar->instantAt($date, $endTime)->format(DateTimeInterface::ATOM),
        );
    }
}
