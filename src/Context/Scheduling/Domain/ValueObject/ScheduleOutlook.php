<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\ValueObject;

use App\Context\Scheduling\Domain\Entity\Schedule;

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
