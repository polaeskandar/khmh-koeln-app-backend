<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

use App\Context\Meetings\Domain\Enum\RosterMode;

final readonly class ScheduleEdit
{
    public function __construct(
        public DetailsDraft $details,
        public ?RecurrenceDraft $recurrence,
        public TimesDraft $times,
        public ?RosterMode $rosterMode,
    ) {
    }
}
