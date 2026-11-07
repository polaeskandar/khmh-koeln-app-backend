<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

use App\Context\Meetings\Domain\Enum\RosterMode;

final readonly class SchedulePlan
{
    public function __construct(
        public MeetingDetails $details,
        public Recurrence $recurrence,
        public TimeSlot $timeSlot,
        public RosterMode $rosterMode,
    ) {
    }
}
