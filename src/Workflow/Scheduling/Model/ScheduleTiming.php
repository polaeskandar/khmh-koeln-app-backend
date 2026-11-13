<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling\Model;

use App\Context\Scheduling\Domain\Entity\Schedule;

final readonly class ScheduleTiming
{
    public function __construct(
        public RecurrenceSummary $recurrence,
        public string $startTime,
        public ?string $endTime,
    ) {
    }

    public static function of(Schedule $schedule): self
    {
        return new self(
            recurrence: RecurrenceSummary::of($schedule->recurrence()),
            startTime: $schedule->timeSlot()->startTime->toString(),
            endTime: $schedule->timeSlot()->endTime?->toString(),
        );
    }
}
