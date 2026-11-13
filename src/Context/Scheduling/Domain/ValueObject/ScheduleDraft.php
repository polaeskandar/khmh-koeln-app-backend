<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\ValueObject;

use App\Context\Scheduling\Domain\Enum\RosterMode;

final readonly class ScheduleDraft
{
    public function __construct(
        public DetailsDraft $details,
        public RecurrenceDraft $recurrence,
        public TimesDraft $times,
        public RosterMode $rosterMode,
    ) {
    }
}
