<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\ValueObject;

use App\Context\Scheduling\Domain\Entity\Schedule;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Time\Model\LocalDate;

final readonly class ScheduleDate
{
    private function __construct(
        public Schedule $schedule,
        public LocalDate $date,
    ) {
    }

    public static function cancelable(Schedule $schedule, LocalDate $date, LocalDate $today): self
    {
        $refusal = match (true) {
            $date->isBefore($today) => 'meetings.dateInPast',
            !$schedule->recurrence()->occursOn($date) => 'meetings.noMeetingOnDate',
            !$schedule->isActive() => 'meetings.scheduleInactive',
            default => null,
        };

        return $refusal === null ? new self($schedule, $date) : throw new RuleViolation($refusal);
    }
}
