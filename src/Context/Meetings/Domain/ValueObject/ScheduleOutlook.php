<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

use App\Context\Meetings\Domain\Entity\Schedule;

final readonly class ScheduleOutlook
{
    /**
     * @param list<PlannedDate> $nextDates
     */
    public function __construct(
        public Schedule $schedule,
        public bool $generating,
        public array $nextDates,
        public int $sessionCount,
    ) {
    }
}
