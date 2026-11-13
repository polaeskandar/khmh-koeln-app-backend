<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling\Model;

use App\Context\Scheduling\Domain\ValueObject\PlannedDate;

final readonly class PlannedSession
{
    public function __construct(
        public ScheduleDetails $schedule,
        public ScheduleRoster $roster,
        public SessionTimes $times,
    ) {
    }

    public static function of(PlannedDate $date, RosterReferences $references, SessionTimes $times): self
    {
        return new self(ScheduleDetails::of($date->schedule), ScheduleRoster::of($date->schedule, $references), $times);
    }
}
