<?php

declare(strict_types=1);

namespace App\Interface\Api\Meetings\Response;

use App\Workflow\Meetings\Model\DatedSchedule;

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
