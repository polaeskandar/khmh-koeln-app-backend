<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Meetings\Domain\ValueObject\PlannedDate;
use App\Library\Time\Client\Calendar;

final readonly class PlannedSession
{
    public function __construct(
        public ScheduleDetails $schedule,
        public ScheduleRoster $roster,
        public SessionTimes $times,
        public SessionAbilities $can,
    ) {
    }

    public static function of(
        PlannedDate $date,
        RosterReferences $references,
        Calendar $calendar,
        SessionAbilities $can,
    ): self {
        return new self(
            schedule: ScheduleDetails::of($date->schedule),
            roster: ScheduleRoster::of($date->schedule, $references),
            times: SessionTimes::at($date->schedule, $date->date, $calendar),
            can: $can,
        );
    }
}
