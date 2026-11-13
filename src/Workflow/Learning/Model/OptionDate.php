<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Scheduling\Domain\ValueObject\PlannedDate;

final readonly class OptionDate
{
    public function __construct(
        public string $scheduleId,
        public string $date,
        public string $startTime,
        public string $title,
        public string $state,
    ) {
    }

    public static function of(PlannedDate $date): self
    {
        $plan = $date->plan;

        return new self(
            scheduleId: $date->schedule->id()->toRfc4122(),
            date: $date->date->toString(),
            startTime: $plan->timeSlot->startTime->toString(),
            title: $plan->title,
            state: $date->status,
        );
    }
}
