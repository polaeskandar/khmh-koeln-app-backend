<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Meetings\Domain\ValueObject\PlannedDate;

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
        $schedule = $date->schedule;

        return new self(
            scheduleId: $schedule->id()->toRfc4122(),
            date: $date->date->toString(),
            startTime: $schedule->timeSlot()->startTime->toString(),
            title: $schedule->details()->name,
            state: $date->status,
        );
    }
}
