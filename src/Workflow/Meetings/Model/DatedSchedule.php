<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

final readonly class DatedSchedule
{
    public function __construct(
        public string $today,
        public ScheduleSummary $schedule,
    ) {
    }
}
