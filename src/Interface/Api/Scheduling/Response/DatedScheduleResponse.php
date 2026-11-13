<?php

declare(strict_types=1);

namespace App\Interface\Api\Scheduling\Response;

use App\Workflow\Scheduling\Model\DatedSchedule;

final readonly class DatedScheduleResponse
{
    public function __construct(
        public string $today,
        public ScheduleResponse $schedule,
    ) {
    }

    public static function of(DatedSchedule $dated): self
    {
        return new self($dated->today, new ScheduleResponse($dated->schedule));
    }
}
