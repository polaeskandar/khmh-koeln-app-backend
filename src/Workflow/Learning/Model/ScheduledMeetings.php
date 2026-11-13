<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Scheduling\Domain\Entity\Schedule;
use App\Context\Scheduling\Domain\ValueObject\ArchivedStructure;
use App\Library\Time\Model\LocalDate;

final readonly class ScheduledMeetings
{
    public static function on(
        LocalDate $date,
        ?Schedule $schedule,
        ArchivedStructure $archived,
    ): OffScheduleMeeting|PlannedMeeting {
        if ($schedule !== null && $archived->generates($schedule) && $schedule->recurrence()->occursOn($date)) {
            return PlannedMeeting::of($schedule, $date);
        }

        return OffScheduleMeeting::of($schedule, $date);
    }
}
