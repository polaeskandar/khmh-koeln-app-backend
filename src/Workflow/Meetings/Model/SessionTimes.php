<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Entity\Session;
use App\Library\Time\Client\Calendar;
use App\Library\Time\Model\LocalDate;
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
        return self::at($session->schedule(), $session->occurrenceDate(), $calendar);
    }

    public static function at(Schedule $schedule, LocalDate $date, Calendar $calendar): self
    {
        $slot = $schedule->timeSlot();
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
